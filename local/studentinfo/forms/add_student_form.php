<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form to manually on-board a student into OU / Faculty / Intake.
 */
class local_studentinfo_add_student_form extends moodleform {

    public function definition() {
        global $DB;

        $mform = $this->_form;
        $cd    = $this->_customdata ?? [];
        $ouid  = $cd['ouid'] ?? 0;
        $ori   = $cd['ori']  ?? 1;  // 1 = manual add, 2 = on-board & update

        // ---- Hidden OU & ORI ----
        $mform->addElement('hidden', 'ouid', $ouid);
        $mform->setType('ouid', PARAM_INT);

        $mform->addElement('hidden', 'ori', $ori);
        $mform->setType('ori', PARAM_INT);

        // ---- Personal info ----
        $mform->addElement('header', 'hdrperson', get_string('student', 'local_studentinfo'));

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

        // ---- Placement: Faculty & Intake ----
        $mform->addElement('header', 'hdrplacement', get_string('placement', 'local_studentinfo'));

        // Faculty dropdown (from local_ouadmin_faculty filtered by OU).
        $faculties = [];
        if ($ouid && $DB->get_manager()->table_exists('local_ouadmin_faculty')) {
            $faculties = $DB->get_records_menu('local_ouadmin_faculty',
                ['ouid' => $ouid], 'name ASC', 'id, name');
        }
        if (empty($faculties)) {
            $faculties = [0 => get_string('none')];
        }

        $mform->addElement('select', 'facultyid', get_string('facultyname', 'local_ouadmin'), $faculties);
        $mform->setType('facultyid', PARAM_INT);
        $mform->addRule('facultyid', null, 'required', null, 'client');

        // Intake dropdown – all intakes for this OU.
        $intakes = [];
        if ($ouid
            && $DB->get_manager()->table_exists('local_ouadmin_intake')
            && $DB->get_manager()->table_exists('local_ouadmin_faculty')) {

            $sql = "SELECT i.id, CONCAT(f.name, ' - ', i.name) AS fullname
                      FROM {local_ouadmin_intake} i
                      JOIN {local_ouadmin_faculty} f ON f.id = i.facultyid
                     WHERE f.ouid = :ouid
                  ORDER BY f.name ASC, i.name ASC";
            $records = $DB->get_records_sql($sql, ['ouid' => $ouid]);
            foreach ($records as $r) {
                $intakes[$r->id] = $r->fullname;
            }
        }
        if (empty($intakes)) {
            $intakes = [0 => get_string('none')];
        }

        $mform->addElement('select', 'intakeid', get_string('intakename', 'local_ouadmin'), $intakes);
        $mform->setType('intakeid', PARAM_INT);
        $mform->addRule('intakeid', null, 'required', null, 'client');

        // Hidden id (not used here).
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        // ---- Buttons: depend on ORI ----
        $buttonarray = [];

        if ((int)$ori === 1) {
            // From "Add student manually" → only Save + Cancel.
            $buttonarray[] = $mform->createElement('submit', 'saveandback', get_string('save', 'local_studentinfo'));
        } else {
            // From "On-board and update" → only Save and update detail + Cancel.
            $buttonarray[] = $mform->createElement('submit', 'saveandedit', get_string('saveandedit', 'local_studentinfo'));
        }

        $buttonarray[] = $mform->createElement('cancel', 'cancel', get_string('cancel'));
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
    }

    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        $facultyid = (int)$data['facultyid'];
        $intakeid  = (int)$data['intakeid'];

        // Ensure intake belongs to the selected faculty.
        if ($facultyid && $intakeid &&
            $DB->get_manager()->table_exists('local_ouadmin_intake')) {

            $intake = $DB->get_record('local_ouadmin_intake',
                ['id' => $intakeid], 'id, facultyid', IGNORE_MISSING);

            if ($intake && (int)$intake->facultyid !== $facultyid) {
                $errors['intakeid'] = 'Selected intake does not belong to the chosen faculty.';
            }
        }

        return $errors;
    }
}
