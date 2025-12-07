<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class local_ouadmin_intake_form extends moodleform {

    public function definition() {
        global $DB;

        $mform = $this->_form;

        // OU id (hidden).
        $mform->addElement('hidden', 'ouid');
        $mform->setType('ouid', PARAM_INT);

        // Faculty dropdown (faculties under this OU only).
        $ouid = $this->_customdata['ouid'] ?? 0;
        $faculties = $DB->get_records_menu('local_ouadmin_faculty',
            ['ouid' => $ouid], 'name ASC', 'id, name');

        $mform->addElement('select', 'facultyid', get_string('facultyname', 'local_ouadmin'), $faculties);
        $mform->addRule('facultyid', null, 'required', null, 'client');

        // Intake name.
        $mform->addElement('text', 'name', get_string('intakename', 'local_ouadmin'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // Intake code.
        $mform->addElement('text', 'code', get_string('intakecode', 'local_ouadmin'));
        $mform->setType('code', PARAM_ALPHANUMEXT);

        // Start date & end date.
        $mform->addElement('date_selector', 'startdate', get_string('startdate'));
        $mform->addElement('date_selector', 'enddate', get_string('enddate'));

        // Status (simple text for now).
        $mform->addElement('text', 'status', get_string('status'));
        $mform->setType('status', PARAM_TEXT);

        // Hidden id.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
