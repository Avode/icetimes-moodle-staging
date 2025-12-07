<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ouadmin/lib.php');

$id   = required_param('id', PARAM_INT);
$ouid = optional_param('ouid', 0, PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/ouadmin:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ouadmin/faculty_view.php', ['id' => $id, 'ouid' => $ouid]));
$PAGE->set_title(get_string('viewfaculty', 'local_ouadmin'));
$PAGE->set_heading(get_string('viewfaculty', 'local_ouadmin'));

$ous = local_ouadmin_get_user_ous($USER->id);

$faculty = $DB->get_record('local_ouadmin_faculty', ['id' => $id], '*', MUST_EXIST);

if (!array_key_exists($faculty->ouid, $ous) && !is_siteadmin($USER)) {
    print_error('nopermissions', 'error', '', 'view this faculty');
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($faculty->name));

echo html_writer::start_div('card mb-3');
echo html_writer::start_div('card-body');

echo html_writer::tag('h5', get_string('facultyname', 'local_ouadmin'));
echo html_writer::tag('p', format_string($faculty->name));

echo html_writer::tag('h5', get_string('facultycode', 'local_ouadmin'));
echo html_writer::tag('p', s($faculty->code));

echo html_writer::tag('h5', get_string('facultydean', 'local_ouadmin'));
$deanline = trim(($faculty->deanfirstname ?? '') . ' ' . ($faculty->deanlastname ?? ''));
if (!empty($faculty->deanemail)) {
    $deanline .= ' &lt;' . s($faculty->deanemail) . '&gt;';
}
echo html_writer::tag('p', s($deanline));

echo html_writer::tag('h5', get_string('facultydescription', 'local_ouadmin'));
echo html_writer::tag('p', nl2br(s($faculty->description)));

echo html_writer::tag('h5', get_string('facultyactive', 'local_ouadmin'));
$activebadge = $faculty->active
    ? html_writer::tag('span', get_string('yes'), ['class' => 'badge bg-success'])
    : html_writer::tag('span', get_string('no'), ['class' => 'badge bg-secondary']);
echo html_writer::tag('p', $activebadge);

echo html_writer::end_div(); // card-body
echo html_writer::end_div(); // card

$backurl = new moodle_url('/local/ouadmin/index.php', ['tab' => 'faculty', 'ouid' => $faculty->ouid]);
echo html_writer::link($backurl, get_string('back'), ['class' => 'btn btn-secondary']);

echo $OUTPUT->footer();
