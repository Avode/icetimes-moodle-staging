<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/studentinfo/forms/intake_enrol_form.php');
require_once($CFG->dirroot . '/local/studentinfo/locallib.php');

// Guard load bridge (for OU banner etc).
if (!class_exists('\\local_studentinfo\\local\\orgstructure_bridge')) {
    require_once($CFG->dirroot.'/local/studentinfo/classes/local/orgstructure_bridge.php');
}

require_login();

$context = context_system::instance();
require_capability('local/studentinfo:manage', $context);

$ouid = optional_param('ou', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/studentinfo/intake_enrol.php', ['ou' => $ouid]));
$PAGE->set_title(get_string('intakeenrol', 'local_studentinfo'));
$PAGE->set_heading(get_string('intakeenrol', 'local_studentinfo'));

$mform = new intake_enrol_form();

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/studentinfo/student.php', ['ou' => $ouid]));
} else if ($data = $mform->get_data()) {
    global $DB, $CFG, $USER;

    $transaction = $DB->start_delegated_transaction();

    // 1. Create or get user by email.
    $email = core_text::strtolower(trim($data->email));
    $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);

    if (!$user) {
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

        // Send email with login info.
        $supportuser = core_user::get_support_user();
        $subject = get_string('studentaccountcreated', 'local_studentinfo');
        $message = "Dear {$user->firstname},\n\n"
            . "An account has been created for you in the training system.\n\n"
            . "Username: {$user->username}\n"
            . "Password: {$password}\n\n"
            . "You can log in at: {$CFG->wwwroot}\n\n"
            . "Thank you.\n";

        email_to_user($user, $supportuser, $subject, $message);
    }

    // 2. Add user to intake cohort using local_studentinfo helper.
    $intakeid = (int)$data->intakeid;
    $cohortid = local_studentinfo_add_user_to_intake_cohort($user->id, $intakeid);

    // 3. Log in local_studentinfo_enrol_log.
    $log = (object)[
        'userid'      => $user->id,
        'intakeid'    => $intakeid,
        'timecreated' => time(),
        'createdby'   => $USER->id,
    ];
    $DB->insert_record('local_studentinfo_enrol_log', $log);

    $DB->commit_delegated_transaction($transaction);

    redirect(
        new moodle_url('/local/studentinfo/intake_enrol.php', ['ou' => $ouid]),
        get_string('studentenrolled', 'local_studentinfo'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();

// OU banner.
if (class_exists('\\local_studentinfo\\local\\orgstructure_bridge')) {
    echo \local_studentinfo\local\orgstructure_bridge::ou_banner($ouid, 'Scope');
}

echo $OUTPUT->heading(get_string('intakeenrol', 'local_studentinfo'));
$mform->display();

echo $OUTPUT->footer();
