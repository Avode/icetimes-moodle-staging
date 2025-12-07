<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class local_studentlookup_rank_form extends moodleform {

    public function definition() {
        global $DB;

        $mform = $this->_form;

        // Name.
        $mform->addElement('text', 'name', get_string('rankname', 'local_studentlookup'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // Shortname.
        $mform->addElement('text', 'shortname', get_string('rankshortname', 'local_studentlookup'));
        $mform->setType('shortname', PARAM_ALPHANUMEXT);

        // Service.
        $services = $DB->get_records_menu('local_studentlookup_service', null, 'name ASC', 'id,name');
        $mform->addElement('select', 'serviceid', get_string('servicename', 'local_studentlookup'), $services);
        $mform->addRule('serviceid', null, 'required', null, 'client');

        // Rank level.
        $mform->addElement('text', 'ranklevel', get_string('ranklevel', 'local_studentlookup'));
        $mform->setType('ranklevel', PARAM_INT);

        // Active.
        $mform->addElement('advcheckbox', 'active', get_string('active', 'local_studentlookup'));
        $mform->setDefault('active', 1);

        // Hidden fields.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons();
    }
}

