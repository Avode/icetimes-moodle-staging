<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ouadmin/lib.php');

$id   = required_param('id', PARAM_INT);
$ouid = optional_param('ouid', 0, PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/ouadmin:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ouadmin/teacher_view.php', ['id' => $id, 'ouid' => $ouid]));
$PAGE->set_title(get_string('viewteacher', 'local_ouadmin'));
$PAGE->set_heading(get_string('viewteacher', 'local_ouadmin'));

$ous = local_ouadmin_get_user_ous($USER->id);

$teacher = $DB->get_record('local_ouadmin_teacher', ['id' => $id], '*', MUST_EXIST);
if (!array_key_exists($teacher->ouid, $ous) && !is_siteadmin($USER)) {
    print_error('nopermissions', 'error', '', 'view this teacher');
}

$faculty = null;
if (!empty($teacher->facultyid)) {
    $faculty = $DB->get_record('local_ouadmin_faculty',
        ['id' => $teacher->facultyid], 'id, name', IGNORE_MISSING);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($teacher->firstname . ' ' . $teacher->lastname));

echo html_writer::start_div('card mb-3');
echo html_writer::start_div('card-body');

echo html_writer::tag('h5', get_string('firstname'));
echo html_writer::tag('p', format_string($teacher->firstname));

echo html_writer::tag('h5', get_string('lastname'));
echo html_writer::tag('p', format_string($teacher->lastname));

echo html_writer::tag('h5', get_string('email'));
echo html_writer::tag('p', s($teacher->email));

echo html_writer::tag('h5', get_string('facultyname', 'local_ouadmin'));
echo html_writer::tag('p', $faculty ? format_string($faculty->name) : '-');

echo html_writer::tag('h5', get_string('teacherstaffno', 'local_ouadmin'));
echo html_writer::tag('p', s($teacher->staffno));

echo html_writer::tag('h5', get_string('teacherdepartment', 'local_ouadmin'));
echo html_writer::tag('p', s($teacher->department));

echo html_writer::end_div();
echo html_writer::end_div();

$backurl = new moodle_url('/local/ouadmin/index.php', ['tab' => 'teacher', 'ouid' => $teacher->ouid]);
echo html_writer::link($backurl, get_string('back'), ['class' => 'btn btn-secondary']);

echo $OUTPUT->footer();
