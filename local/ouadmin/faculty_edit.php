<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ouadmin/lib.php');
require_once($CFG->dirroot . '/local/ouadmin/forms/faculty_form.php');

$id   = optional_param('id', 0, PARAM_INT);
$ouid = optional_param('ouid', 0, PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/ouadmin:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ouadmin/faculty_edit.php', ['id' => $id, 'ouid' => $ouid]));
$PAGE->set_title(get_string('editfaculty', 'local_ouadmin'));
$PAGE->set_heading(get_string('editfaculty', 'local_ouadmin'));

$ous = local_ouadmin_get_user_ous($USER->id);
if (!array_key_exists($ouid, $ous)) {
    $ouid = (int)array_key_first($ous);
}

if ($id) {
    $faculty = $DB->get_record('local_ouadmin_faculty', ['id' => $id], '*', MUST_EXIST);
    if (!array_key_exists($faculty->ouid, $ous) && !is_siteadmin($USER)) {
        print_error('nopermissions', 'error', '', 'manage this faculty');
    }
} else {
    $faculty = null;
}

$mform = new local_ouadmin_faculty_form();

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/ouadmin/index.php', ['tab' => 'faculty', 'ouid' => $ouid]));
} else if ($data = $mform->get_data()) {
    $data->timemodified = time();
    $data->usermodified = $USER->id;

    if (empty($data->id)) {
        // New faculty.
        $data->timecreated = time();
        $data->ouid = $ouid;

        // Insert and get id.
        $data->id = $DB->insert_record('local_ouadmin_faculty', $data);

        // Post-create: category + dean user + Dean role.
        try {
            local_ouadmin_after_faculty_created($data);
        } catch (Exception $e) {
            // Log for debugging but do not block user.
            debugging('Error in local_ouadmin_after_faculty_created: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    } else {
        // Existing faculty update.
        $DB->update_record('local_ouadmin_faculty', $data);
        $ouid = $data->ouid;
    }

    redirect(new moodle_url('/local/ouadmin/index.php', ['tab' => 'faculty', 'ouid' => $ouid]));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editfaculty', 'local_ouadmin'));

if ($faculty) {
    $mform->set_data($faculty);
} else {
    $mform->set_data(['ouid' => $ouid, 'active' => 1]);
}

$mform->display();

echo $OUTPUT->footer();
