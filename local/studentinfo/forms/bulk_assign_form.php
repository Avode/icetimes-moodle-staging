<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

class bulk_assign_form extends moodleform {
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('filepicker', 'csvfile', get_string('uploadcsv', 'local_studentinfo'), null, ['accepted_types' => ['.csv']]);
        $mform->addRule('csvfile', null, 'required', null, 'client');
        $mform->addElement('static', 'help', '', get_string('csvhelp', 'local_studentinfo'));
        $mform->addElement('submit', 'submitbtn', get_string('bulkassign', 'local_studentinfo'));
    }
}
