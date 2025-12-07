<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ouadmin/lib.php');
require_once($CFG->dirroot . '/local/ouadmin/forms/intake_form.php');

$id   = optional_param('id', 0, PARAM_INT);
$ouid = optional_param('ouid', 0, PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/ouadmin:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ouadmin/intake_edit.php', ['id' => $id, 'ouid' => $ouid]));
$PAGE->set_title(get_string('editintake', 'local_ouadmin'));
$PAGE->set_heading(get_string('editintake', 'local_ouadmin'));

$ous = local_ouadmin_get_user_ous($USER->id);
if (!array_key_exists($ouid, $ous)) {
    $ouid = (int)array_key_first($ous);
}

if ($id) {
    $intake = $DB->get_record('local_ouadmin_intake', ['id' => $id], '*', MUST_EXIST);
    if (!array_key_exists($intake->ouid, $ous) && !is_siteadmin($USER)) {
        print_error('nopermissions', 'error', '', 'manage this intake');
    }
} else {
    $intake = null;
}

// Pass ouid to form so it can filter faculties.
$customdata = ['ouid' => $ouid];
$mform = new local_ouadmin_intake_form(null, $customdata);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/ouadmin/index.php', ['tab' => 'intake', 'ouid' => $ouid]));
} else if ($data = $mform->get_data()) {
    $data->timemodified = time();
    $data->usermodified = $USER->id;

    if (empty($data->id)) {
        // New intake.
        $data->timecreated = time();
        $data->ouid = $ouid;

        // date_selector returns timestamp already.
        $data->id = $DB->insert_record('local_ouadmin_intake', $data);

        // Create subcategory under faculty category.
        try {
            local_ouadmin_after_intake_created($data);
        } catch (Exception $e) {
            debugging('Error in local_ouadmin_after_intake_created: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    } else {
        // Update existing.
        $DB->update_record('local_ouadmin_intake', $data);
        $ouid = $data->ouid;
    }

    redirect(new moodle_url('/local/ouadmin/index.php', ['tab' => 'intake', 'ouid' => $ouid]));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editintake', 'local_ouadmin'));

if ($intake) {
    $mform->set_data($intake);
} else {
    $mform->set_data(['ouid' => $ouid]);
}

$mform->display();

echo $OUTPUT->footer();
