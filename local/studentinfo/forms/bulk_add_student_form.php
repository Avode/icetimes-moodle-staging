<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class local_studentinfo_bulk_add_student_form extends moodleform {

    public function definition() {
        global $DB;

        $mform = $this->_form;
        $cd    = $this->_customdata ?? [];
        $ouid  = $cd['ouid'] ?? 0;

        // Hidden OU so we know scope.
        $mform->addElement('hidden', 'ouid', $ouid);
        $mform->setType('ouid', PARAM_INT);

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

        // Intake dropdown – show ALL intakes for this OU, regardless of faculty.
        // Validation will ensure that the chosen intake matches the faculty.
        $intakes = [];
        if ($ouid && $DB->get_manager()->table_exists('local_ouadmin_intake')) {
            $records = $DB->get_records_sql("
                SELECT i.id, CONCAT(f.name, ' - ', i.name) AS fullname
                  FROM {local_ouadmin_intake} i
                  JOIN {local_ouadmin_faculty} f ON f.id = i.facultyid
                 WHERE f.ouid = :ouid
                 ORDER BY f.name ASC, i.name ASC
            ", ['ouid' => $ouid]);
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

        // ---- CSV upload ----
        $mform->addElement('header', 'hdrcsv', get_string('bulkaddstudents', 'local_studentinfo'));

        $mform->addElement('filepicker', 'csvfile', get_string('bulkaddcsv', 'local_studentinfo'),
            null, ['accepted_types' => ['.csv']]);
        $mform->addRule('csvfile', null, 'required', null, 'client');

        $mform->addElement('static', 'csvhelp', '',
            get_string('bulkaddhelp', 'local_studentinfo'));

        $this->add_action_buttons(true, get_string('bulkaddstudents', 'local_studentinfo'));
    }

    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        $facultyid = (int)$data['facultyid'];
        $intakeid  = (int)$data['intakeid'];

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
