<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

class intake_enrol_form extends moodleform {
    public function definition() {
        global $DB;
        $mform = $this->_form;

        // First name.
        $mform->addElement('text', 'firstname', get_string('firstname'));
        $mform->setType('firstname', PARAM_NOTAGS);
        $mform->addRule('firstname', null, 'required', null, 'client');

        // Last name.
        $mform->addElement('text', 'lastname', get_string('lastname'));
        $mform->setType('lastname', PARAM_NOTAGS);
        $mform->addRule('lastname', null, 'required', null, 'client');

        // Email.
        $mform->addElement('text', 'email', get_string('email'));
        $mform->setType('email', PARAM_EMAIL);
        $mform->addRule('email', null, 'required', null, 'client');

        // Intake selector from local_studentinfo_intake.
        $intakes = [];
        if ($DB->get_manager()->table_exists('local_studentinfo_intake')) {
            $records = $DB->get_records('local_studentinfo_intake', null, 'code ASC', 'id, code');
            foreach ($records as $r) {
                $intakes[$r->id] = $r->code;
            }
        }

        $mform->addElement('select', 'intakeid', get_string('labelintake', 'local_studentinfo'), $intakes);
        $mform->addRule('intakeid', null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string('enrol', 'local_studentinfo'));
    }
}
