<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class local_ouadmin_faculty_form extends moodleform {

    public function definition() {
        $mform = $this->_form;

        // OU id (hidden).
        $mform->addElement('hidden', 'ouid');
        $mform->setType('ouid', PARAM_INT);

        // Faculty name.
        $mform->addElement('text', 'name', get_string('facultyname', 'local_ouadmin'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // Faculty code.
        $mform->addElement('text', 'code', get_string('facultycode', 'local_ouadmin'));
        $mform->setType('code', PARAM_ALPHANUMEXT);

        // Dean header.
        $mform->addElement('header', 'deanheader', get_string('facultydean', 'local_ouadmin'));

        $mform->addElement('text', 'deanfirstname', get_string('firstname'));
        $mform->setType('deanfirstname', PARAM_TEXT);

        $mform->addElement('text', 'deanlastname', get_string('lastname'));
        $mform->setType('deanlastname', PARAM_TEXT);

        $mform->addElement('text', 'deanemail', get_string('email'));
        $mform->setType('deanemail', PARAM_EMAIL);

        // Description.
        $mform->addElement('textarea', 'description', get_string('facultydescription', 'local_ouadmin'),
            'rows="4" cols="50"');
        $mform->setType('description', PARAM_TEXT);

        // Active.
        $mform->addElement('advcheckbox', 'active', get_string('facultyactive', 'local_ouadmin'));
        $mform->setDefault('active', 1);

        // Hidden id.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
