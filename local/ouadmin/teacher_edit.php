<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ouadmin/lib.php');
require_once($CFG->dirroot . '/local/ouadmin/forms/teacher_form.php');

$id   = optional_param('id', 0, PARAM_INT);
$ouid = optional_param('ouid', 0, PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/ouadmin:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ouadmin/teacher_edit.php', ['id' => $id, 'ouid' => $ouid]));
$PAGE->set_title(get_string('editteacher', 'local_ouadmin'));
$PAGE->set_heading(get_string('editteacher', 'local_ouadmin'));

$ous = local_ouadmin_get_user_ous($USER->id);
if (!array_key_exists($ouid, $ous)) {
    reset($ous);
    $firstkey = key($ous);
    $ouid = (int)$firstkey;
}

if ($id) {
    $teacher = $DB->get_record('local_ouadmin_teacher', ['id' => $id], '*', MUST_EXIST);
    if (!array_key_exists($teacher->ouid, $ous) && !is_siteadmin($USER)) {
        print_error('nopermissions', 'error', '', 'manage this teacher');
    }
} else {
    $teacher = null;
}

// Pass ouid to form so we can filter faculties.
$customdata = ['ouid' => $ouid];
$mform = new local_ouadmin_teacher_form(null, $customdata);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/ouadmin/index.php', ['tab' => 'teacher', 'ouid' => $ouid]));
} else if ($data = $mform->get_data()) {
    $data->timemodified = time();
    $data->usermodified = $USER->id;

    if (empty($data->id)) {
        // New teacher.
        $data->timecreated = time();
        $data->ouid = $ouid;

        // Create or link user by email.
        try {
            $user = local_ouadmin_create_or_get_teacher_user(
                $data->firstname,
                $data->lastname,
                $data->email
            );
            $data->userid = $user->id;
        } catch (Exception $e) {
            debugging('Error in local_ouadmin_create_or_get_teacher_user: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $data->userid = null;
        }

        $DB->insert_record('local_ouadmin_teacher', $data);
    } else {
        // Update existing teacher: we do NOT auto-change user account for now.
        $DB->update_record('local_ouadmin_teacher', $data);
        $ouid = $data->ouid;
    }

    redirect(new moodle_url('/local/ouadmin/index.php', ['tab' => 'teacher', 'ouid' => $ouid]));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editteacher', 'local_ouadmin'));

if ($teacher) {
    $mform->set_data($teacher);
} else {
    $mform->set_data(['ouid' => $ouid]);
}

$mform->display();

echo $OUTPUT->footer();
