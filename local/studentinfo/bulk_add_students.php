<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/local/studentinfo/forms/bulk_add_student_form.php');

require_login();

global $DB, $USER, $PAGE, $CFG;

$context = context_system::instance();
require_capability('local/studentinfo:manage', $context);

$manager = $DB->get_manager();

$ouid = optional_param('ou', 0, PARAM_INT);

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

// --- Preload all intakes for this OU for optional JS friendliness ---
$intakemap = [];
if ($ouid
    && $manager->table_exists('local_ouadmin_intake')
    && $manager->table_exists('local_ouadmin_faculty')) {

    $sql = "SELECT i.id, i.name, i.facultyid
              FROM {local_ouadmin_intake} i
              JOIN {local_ouadmin_faculty} f ON f.id = i.facultyid
             WHERE f.ouid = :ouid
          ORDER BY f.name ASC, i.name ASC";
    $records = $DB->get_records_sql($sql, ['ouid' => $ouid]);

    foreach ($records as $r) {
        $intakemap[] = [
            'id'        => (int)$r->id,
            'name'      => $r->name,
            'facultyid' => (int)$r->facultyid,
        ];
    }
}
$intakemapjson = json_encode($intakemap);

// --- Page setup ---
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/studentinfo/bulk_add_students.php', ['ou' => $ouid]));
$PAGE->set_title(get_string('bulkaddstudents', 'local_studentinfo'));
$PAGE->set_heading(get_string('bulkaddstudents', 'local_studentinfo'));

$customdata = ['ouid' => $ouid];
$mform = new local_studentinfo_bulk_add_student_form(null, $customdata);

$results = [];

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/studentinfo/index.php', ['ou' => $ouid]));
} else if ($data = $mform->get_data()) {

    $ouid      = (int)$data->ouid;
    $facultyid = (int)$data->facultyid;
    $intakeid  = (int)$data->intakeid;

    // 1. Get CSV content.
    $content = $mform->get_file_content('csvfile');
    if ($content === false || trim($content) === '') {
        $results[] = ['row' => '-', 'email' => '-', 'status' => 'Empty CSV file.'];
    } else {
        $lines = preg_split("/\r\n|\n|\r/", trim($content));
        if (!$lines) {
            $results[] = ['row' => '-', 'email' => '-', 'status' => 'No rows found in CSV.'];
        } else {
            // Assume first line is header: firstname,lastname,email
            $header = str_getcsv(array_shift($lines));

            $rownum = 1; // data rows start at 2
            foreach ($lines as $line) {
                $rownum++;
                if (trim($line) === '') {
                    continue;
                }
                $cols = str_getcsv($line);

                $fn   = $cols[0] ?? '';
                $ln   = $cols[1] ?? '';
                $em   = $cols[2] ?? '';

                $fn = trim($fn);
                $ln = trim($ln);
                $em = core_text::strtolower(trim($em));

                $status = '';

                if ($em === '' || !validate_email($em)) {
                    $status = get_string('bulkadd_error_email', 'local_studentinfo');
                    $results[] = ['row' => $rownum, 'email' => $em, 'status' => $status];
                    continue;
                }
                if ($fn === '') {
                    $status = get_string('bulkadd_error_firstname', 'local_studentinfo');
                    $results[] = ['row' => $rownum, 'email' => $em, 'status' => $status];
                    continue;
                }
                if ($ln === '') {
                    $status = get_string('bulkadd_error_lastname', 'local_studentinfo');
                    $results[] = ['row' => $rownum, 'email' => $em, 'status' => $status];
                    continue;
                }

                try {
                    $transaction = $DB->start_delegated_transaction();

                    // 2. Create or get user by email.
                    $user = $DB->get_record('user', ['email' => $em, 'deleted' => 0]);
                    $creatednew = false;

                    if (!$user) {
                        $user = new stdClass();
                        $user->username   = $em;
                        $user->firstname  = $fn;
                        $user->lastname   = $ln;
                        $user->email      = $em;
                        $user->auth       = 'manual';
                        $user->confirmed  = 1;
                        $user->mnethostid = $DB->get_field('mnet_host', 'id', ['wwwroot' => $CFG->wwwroot]);

                        $password       = generate_password(10);
                        $user->password = hash_internal_user_password($password);

                        $userid = user_create_user($user, false, false);
                        $user   = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

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
                        $creatednew = true;
                    }

                    // 3. Upsert into local_studentinfo.
                    $now = time();
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

                    // 4. Upsert mapping into local_studentinfo_studentmap.
                    $mapdata = $DB->get_record('local_studentinfo_studentmap',
                        ['userid' => $user->id], '*', IGNORE_MISSING);

                    if ($mapdata) {
                        $mapdata->ouid        = $ouid;
                        $mapdata->facultyid   = $facultyid;
                        $mapdata->intakeid    = $intakeid;
                        $mapdata->timemodified= $now;
                        $mapdata->usermodified= $USER->id;
                        $DB->update_record('local_studentinfo_studentmap', $mapdata);
                        $status = get_string('bulkadd_ok_existing', 'local_studentinfo');
                    } else {
                        $mapdata = (object)[
                            'userid'      => $user->id,
                            'ouid'        => $ouid,
                            'facultyid'   => $facultyid,
                            'intakeid'    => $intakeid,
                            'timecreated' => $now,
                            'timemodified'=> $now,
                            'usermodified'=> $USER->id,
                        ];
                        $DB->insert_record('local_studentinfo_studentmap', $mapdata);
                        $status = get_string('bulkadd_ok_new', 'local_studentinfo');
                    }

                    // 5. Enrol into cohort based on intake.cohortid.
                    if (!empty($mapdata->intakeid)
                        && $manager->table_exists('local_ouadmin_intake')) {

                        $intake = $DB->get_record('local_ouadmin_intake',
                            ['id' => $mapdata->intakeid], 'id, cohortid', IGNORE_MISSING);

                        if ($intake && !empty($intake->cohortid)) {
                            require_once($CFG->dirroot . '/cohort/lib.php');
                            cohort_add_member((int)$intake->cohortid, $user->id);
                        }
                    }

                    $DB->commit_delegated_transaction($transaction);

                    $results[] = ['row' => $rownum, 'email' => $em, 'status' => $status];
                } catch (Exception $e) {
                    if (!empty($transaction)) {
                        $transaction->rollback($e);
                    }
                    $a = $e->getMessage();
                    $results[] = [
                        'row' => $rownum,
                        'email' => $em,
                        'status' => 'Error: ' . $a
                    ];
                }
            }
        }
    }
}

// --- Render page ---
echo $OUTPUT->header();

if (!empty($ous) && $ouid && isset($ous[$ouid])) {
    echo html_writer::div(
        html_writer::tag('strong', 'Scope: ')
        . html_writer::span(s($ous[$ouid])),
        'alert alert-info',
        ['role' => 'status', 'style' => 'margin:10px 0;']
    );
}

echo $OUTPUT->heading(get_string('bulkaddstudents', 'local_studentinfo'));

// Optional: JS to make intake list friendlier (faculty → intake), using $intakemapjson.
// If you’re tired, you can skip this JS and just show a flat intake list in the form.

$mform->display();

// Result table.
if (!empty($results)) {
    echo html_writer::tag('h3', get_string('bulkaddresults', 'local_studentinfo'), ['class' => 'mt-4']);
    $table = new html_table();
    $table->head = [
        get_string('bulkadd_row', 'local_studentinfo'),
        get_string('email'),
        get_string('bulkadd_status', 'local_studentinfo')
    ];
    foreach ($results as $r) {
        $table->data[] = [
            s($r['row']),
            s($r['email']),
            s($r['status']),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
