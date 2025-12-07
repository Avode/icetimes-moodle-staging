<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class local_studentlookup_type_form extends moodleform {

    public function definition() {
        $mform = $this->_form;

        // Name.
        $mform->addElement('text', 'name', get_string('studenttypename', 'local_studentlookup'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // Code.
        $mform->addElement('text', 'code', get_string('studenttypecode', 'local_studentlookup'));
        $mform->setType('code', PARAM_ALPHANUMEXT);

        // Sort order.
        $mform->addElement('text', 'sortorder', get_string('sortorder', 'local_studentlookup'));
        $mform->setType('sortorder', PARAM_INT);

        // Active.
        $mform->addElement('advcheckbox', 'active', get_string('active', 'local_studentlookup'));
        $mform->setDefault('active', 1);

        // Hidden fields.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons();
    }
}

