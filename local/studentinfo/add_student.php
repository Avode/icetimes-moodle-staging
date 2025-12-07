<?php
// local/studentinfo/add_student.php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');    // user_create_user, core_user
require_once($CFG->dirroot . '/cohort/lib.php');  // cohort_add_member
require_once($CFG->dirroot . '/local/studentinfo/forms/add_student_form.php');

require_login();

global $DB, $USER, $PAGE, $OUTPUT, $CFG;

$context = context_system::instance();
$manager = $DB->get_manager();

// Capability for this page (adjust to your plugin if needed).
require_capability('local/studentinfo:manage', $context);

// OU scope and origin (ori) from URL.
$ouid = optional_param('ou', 0, PARAM_INT);
$ori  = optional_param('ori', 1, PARAM_INT);  // 1 = manual add, 2 = on-board & update

// --- OU scoping based on local_organization_ou ---
$ous = [];
if ($manager->table_exists('local_organization_ou')) {
    if (is_siteadmin()) {
        $ous = $DB->get_records_menu('local_organization_ou',
            ['deleted' => 0], 'fullname', 'id, fullname');
    } else {
        $ous = $DB->get_records_menu('local_organization_ou',
            ['deleted' => 0, 'adminuserid' => $USER->id],
            'fullname', 'id, fullname');
    }
}

if (!is_siteadmin()) {
    if (empty($ous)) {
        print_error('You are not assigned to any organisation unit.');
    } else if (!array_key_exists($ouid, $ous)) {
        reset($ous);
        $firstkey = key($ous);
        $ouid = (int)$firstkey;
    }
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/studentinfo/add_student.php', ['ou' => $ouid, 'ori' => $ori]));
$PAGE->set_title(get_string('addstudent', 'local_studentinfo'));
$PAGE->set_heading(get_string('addstudent', 'local_studentinfo'));

$customdata = [
    'ouid' => $ouid,
    'ori'  => $ori,
];
$mform = new local_studentinfo_add_student_form(null, $customdata);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/studentinfo/index.php', ['ou' => $ouid]));
} else if ($data = $mform->get_data()) {

    $transaction = $DB->start_delegated_transaction();

    $now  = time();
    $ouid = (int)$data->ouid;
    $ori  = (int)$data->ori;

    // Fallback: if moodleform did not populate facultyid/intakeid, read from raw request.
    if (!isset($data->facultyid)) {
        $data->facultyid = optional_param('facultyid', 0, PARAM_INT);
    }
    if (!isset($data->intakeid)) {
        $data->intakeid = optional_param('intakeid', 0, PARAM_INT);
    }

    $facultyid = (int)$data->facultyid;
    $intakeid  = (int)$data->intakeid;

    // 1. Create or get user by email.
    $email = core_text::strtolower(trim($data->email));
    $user  = $DB->get_record('user', ['email' => $email, 'deleted' => 0], '*', IGNORE_MISSING);

    if (!$user) {
        // New user.
        $user = new stdClass();
        $user->username   = $email;
        $user->firstname  = $data->firstname;
        $user->lastname   = $data->lastname;
        $user->email      = $email;
        $user->auth       = 'manual';
        $user->confirmed  = 1;
        $user->mnethostid = $DB->get_field('mnet_host', 'id', ['wwwroot' => $CFG->wwwroot]);

        $password       = generate_password(10);
        $user->password = hash_internal_user_password($password);

        $userid = user_create_user($user, false, false);
        $user   = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

        // Send welcome email.
        $supportuser = core_user::get_support_user();
        $subject = get_string('studentaccountcreated', 'local_studentinfo');
        if (empty($subject)) {
            $subject = 'Student account created';
        }

        $message = "Dear {$user->firstname},\n\n"
            . "An account has been created for you in the training system.\n\n"
            . "Username: {$user->username}\n"
            . "Password: {$password}\n\n"
            . "You can log in at: {$CFG->wwwroot}\n\n"
            . "Thank you.\n";

        email_to_user($user, $supportuser, $subject, $message);
    }

    // 2. Upsert into local_studentinfo.
    if ($manager->table_exists('local_studentinfo')) {
        $si = $DB->get_record('local_studentinfo', ['userid' => $user->id], '*', IGNORE_MISSING);
        if ($si) {
            $si->timemodified = $now;
            $DB->update_record('local_studentinfo', $si);
        } else {
            $si = new stdClass();
            $si->userid       = $user->id;
            $si->timecreated  = $now;
            $si->timemodified = $now;
            $DB->insert_record('local_studentinfo', $si);
        }
    }

    // 3. Upsert mapping in local_studentinfo_studentmap.
    $map = null;
    if ($manager->table_exists('local_studentinfo_studentmap')) {
        $map = $DB->get_record('local_studentinfo_studentmap',
            ['userid' => $user->id], '*', IGNORE_MISSING);

        if ($map) {
            $map->ouid        = $ouid;
            $map->facultyid   = $facultyid;
            $map->intakeid    = $intakeid;
            $map->timemodified= $now;
            $map->usermodified= $USER->id;
            $DB->update_record('local_studentinfo_studentmap', $map);
        } else {
            $map = (object)[
                'userid'      => $user->id,
                'ouid'        => $ouid,
                'facultyid'   => $facultyid,
                'intakeid'    => $intakeid,
                'timecreated' => $now,
                'timemodified'=> $now,
                'usermodified'=> $USER->id,
            ];
            $DB->insert_record('local_studentinfo_studentmap', $map);
        }
    }

    // 4. Enrol into cohort based on intake.cohortid.
    if ($map && !empty($map->intakeid)
        && $manager->table_exists('local_ouadmin_intake')) {

        $intake = $DB->get_record('local_ouadmin_intake',
            ['id' => $map->intakeid], 'id, cohortid', IGNORE_MISSING);

        if ($intake && !empty($intake->cohortid)) {
            cohort_add_member((int)$intake->cohortid, $user->id);
        }
    }

    $DB->commit_delegated_transaction($transaction);

    // Decide redirect based on ori:
    // ori = 1 → manual add → back to index
    // ori = 2 → on-board & update → go to edit.php
    if ($ori === 2) {
        redirect(
            new moodle_url('/local/studentinfo/edit.php', [
                'userid' => $user->id,
                'ou'     => $ouid,
            ]),
            get_string('studentenrolled', 'local_studentinfo'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        redirect(
            new moodle_url('/local/studentinfo/index.php', ['ou' => $ouid]),
            get_string('studentenrolled', 'local_studentinfo'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// --- Render page ---
echo $OUTPUT->header();

// OU banner for info.
if (!empty($ous) && $ouid && isset($ous[$ouid])) {
    echo html_writer::div(
        html_writer::tag('strong', 'Scope: ')
        . html_writer::span(s($ous[$ouid])),
        'alert alert-info',
        ['role' => 'status', 'style' => 'margin:10px 0;']
    );
}

echo $OUTPUT->heading(get_string('addstudent', 'local_studentinfo'));

$mform->display();

echo $OUTPUT->footer();
