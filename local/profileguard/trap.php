<?php
require_once(__DIR__.'/../../config.php');
require_login();

$context = context_system::instance();
$PAGE->set_url(new moodle_url('/local/profileguard/trap.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('trapheading', 'local_profileguard'));
$PAGE->set_heading(get_string('trapheading', 'local_profileguard'));

global $SESSION, $USER;

// Ensure redirect target is set so that once they complete, they go to /my/.
if (empty($SESSION->profileguard_redirect)) {
    $SESSION->profileguard_redirect = '/my/';
}

echo $OUTPUT->header();

echo html_writer::tag('h3', get_string('trapheading', 'local_profileguard'));
echo html_writer::tag('p', get_string('trapmessage', 'local_profileguard'));

$completeurl = new moodle_url('/local/studentinfo/edit.php', ['userid' => $USER->id]);
$logouturl   = new moodle_url('/login/logout.php', ['sesskey' => sesskey()]);

echo html_writer::start_div('mt-3');
echo html_writer::link($completeurl, get_string('trapcomplete', 'local_profileguard'),
    ['class' => 'btn btn-primary me-2']);
echo html_writer::link($logouturl, get_string('traplogout', 'local_profileguard'),
    ['class' => 'btn btn-secondary']);
echo html_writer::end_div();

echo $OUTPUT->footer();
