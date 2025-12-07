<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ouadmin/lib.php');

$id   = required_param('id', PARAM_INT);
$ouid = optional_param('ouid', 0, PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/ouadmin:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ouadmin/intake_view.php', ['id' => $id, 'ouid' => $ouid]));
$PAGE->set_title(get_string('viewintake', 'local_ouadmin'));
$PAGE->set_heading(get_string('viewintake', 'local_ouadmin'));

$ous = local_ouadmin_get_user_ous($USER->id);

$intake = $DB->get_record('local_ouadmin_intake', ['id' => $id], '*', MUST_EXIST);
if (!array_key_exists($intake->ouid, $ous) && !is_siteadmin($USER)) {
    print_error('nopermissions', 'error', '', 'view this intake');
}

$faculty = $DB->get_record('local_ouadmin_faculty',
    ['id' => $intake->facultyid], 'id, name', IGNORE_MISSING);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($intake->name));

echo html_writer::start_div('card mb-3');
echo html_writer::start_div('card-body');

echo html_writer::tag('h5', get_string('intakename', 'local_ouadmin'));
echo html_writer::tag('p', format_string($intake->name));

echo html_writer::tag('h5', get_string('intakecode', 'local_ouadmin'));
echo html_writer::tag('p', s($intake->code));

echo html_writer::tag('h5', get_string('facultyname', 'local_ouadmin'));
echo html_writer::tag('p', $faculty ? format_string($faculty->name) : '-');

echo html_writer::tag('h5', get_string('startdate', 'local_ouadmin'));
echo html_writer::tag('p', $intake->startdate ? userdate($intake->startdate) : '-');

echo html_writer::tag('h5', get_string('enddate', 'local_ouadmin'));
echo html_writer::tag('p', $intake->enddate ? userdate($intake->enddate) : '-');

echo html_writer::tag('h5', get_string('status', 'local_ouadmin'));
echo html_writer::tag('p', s($intake->status));

echo html_writer::end_div();
echo html_writer::end_div();

$backurl = new moodle_url('/local/ouadmin/index.php', ['tab' => 'intake', 'ouid' => $intake->ouid]);
echo html_writer::link($backurl, get_string('back'), ['class' => 'btn btn-secondary']);

echo $OUTPUT->footer();
