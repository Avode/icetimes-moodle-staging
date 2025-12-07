<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class local_ouadmin_teacher_form extends moodleform {

    public function definition() {
        global $DB;

        $mform = $this->_form;

        // OU id (hidden).
        $mform->addElement('hidden', 'ouid');
        $mform->setType('ouid', PARAM_INT);

        // Faculty dropdown (optional, but useful).
        $ouid = $this->_customdata['ouid'] ?? 0;
        $faculties = $DB->get_records_menu('local_ouadmin_faculty',
            ['ouid' => $ouid], 'name ASC', 'id, name');
        $facoptions = [0 => get_string('none')];
        if (!empty($faculties)) {
            $facoptions += $faculties;
        }

        $mform->addElement('select', 'facultyid', get_string('facultyname', 'local_ouadmin'), $facoptions);
        $mform->setDefault('facultyid', 0);

        // First name.
        $mform->addElement('text', 'firstname', get_string('firstname'));
        $mform->setType('firstname', PARAM_TEXT);
        $mform->addRule('firstname', null, 'required', null, 'client');

        // Last name.
        $mform->addElement('text', 'lastname', get_string('lastname'));
        $mform->setType('lastname', PARAM_TEXT);
        $mform->addRule('lastname', null, 'required', null, 'client');

        // Email.
        $mform->addElement('text', 'email', get_string('email'));
        $mform->setType('email', PARAM_EMAIL);
        $mform->addRule('email', null, 'required', null, 'client');

        // Staff no.
        $mform->addElement('text', 'staffno', get_string('teacherstaffno', 'local_ouadmin'));
        $mform->setType('staffno', PARAM_TEXT);

        // Department.
        $mform->addElement('text', 'department', get_string('teacherdepartment', 'local_ouadmin'));
        $mform->setType('department', PARAM_TEXT);

        // Hidden id.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
