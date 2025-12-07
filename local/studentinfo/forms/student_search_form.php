<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

class student_search_form extends moodleform {
    public function definition() {
        $mform = $this->_form;

        // Hidden value that Select2 will write into.
        $mform->addElement('hidden', 'userid', 0);
        $mform->setType('userid', PARAM_INT);
        $mform->addRule('userid', null, 'required', null, 'client');

        // Render the Select2 host <select>. We’ll wire it up in student.php.
        $mform->addElement('html', '
            <div class="mb-2">
              <label for="useridselect" class="form-label">'.get_string('student', 'local_studentinfo').'</label>
              <select id="useridselect" style="width:100%"></select>
            </div>
        ');

        $mform->addElement('submit', 'submitbtn', get_string('student', 'local_studentinfo'));
    }
}
