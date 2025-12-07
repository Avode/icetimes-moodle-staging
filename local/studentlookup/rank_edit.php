<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/studentlookup/forms/rank_form.php');

$id = optional_param('id', 0, PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/studentlookup:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/studentlookup/rank_edit.php', ['id' => $id]));
$PAGE->set_title(get_string('editrank', 'local_studentlookup'));
$PAGE->set_heading(get_string('editrank', 'local_studentlookup'));

if ($id) {
    $record = $DB->get_record('local_studentlookup_rank', ['id' => $id], '*', MUST_EXIST);
} else {
    $record = null;
}

$mform = new local_studentlookup_rank_form();

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/studentlookup/index.php', ['tab' => 'rank']));
} else if ($data = $mform->get_data()) {
    $data->timemodified = time();
    $data->usermodified = $USER->id;

    if (empty($data->id)) {
        $data->timecreated = time();
        $id = $DB->insert_record('local_studentlookup_rank', $data);
    } else {
        $DB->update_record('local_studentlookup_rank', $data);
    }

    redirect(new moodle_url('/local/studentlookup/index.php', ['tab' => 'rank']),
        get_string('saved', 'local_studentlookup'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editrank', 'local_studentlookup'));

if ($record) {
    $mform->set_data($record);
} else {
    $mform->set_data(['active' => 1]);
}

$mform->display();

echo $OUTPUT->footer();

