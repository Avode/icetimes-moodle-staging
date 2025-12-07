<?php
namespace local_organization\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

class ou_form extends \moodleform {

    public function definition() {
        $mform      = $this->_form;
        $customdata = $this->_customdata ?? [];
        $isupdate   = !empty($customdata['isupdate']);

        $mform->setDisableShortForms(true);

        // Hidden id.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // === SECTION: Organization Unit Details ===
        $mform->addElement('header', 'orgheader',
            get_string('section_orgdetails', 'local_organization'));

        $mform->addElement('text', 'fullname', get_string('fullname'));
        $mform->setType('fullname', PARAM_TEXT);
        $mform->addRule('fullname', null, 'required', null, 'client');

        $mform->addElement('text', 'shortname', get_string('shortname'));
        $mform->setType('shortname', PARAM_ALPHANUMEXT);
        if (!$isupdate) {
            $mform->addRule('shortname', null, 'required', null, 'client');
        }
        if ($isupdate) {
            $mform->freeze('shortname');
        }

        $mform->addElement('text', 'oucode',
            get_string('oucode', 'local_organization'));
        $mform->setType('oucode', PARAM_ALPHANUMEXT);

        // === SECTION: Location & Address ===
        $mform->addElement('header', 'locheader',
            get_string('section_location', 'local_organization'));

        $mform->addElement('text', 'address1',
            get_string('addressline1', 'local_organization'));
        $mform->setType('address1', PARAM_TEXT);

        $mform->addElement('text', 'address2',
            get_string('addressline2', 'local_organization'));
        $mform->setType('address2', PARAM_TEXT);

        $mform->addElement('text', 'address3',
            get_string('addressline3', 'local_organization'));
        $mform->setType('address3', PARAM_TEXT);

        $mform->addElement('text', 'postcode',
            get_string('postcode', 'local_organization'));
        $mform->setType('postcode', PARAM_RAW_TRIMMED);

        $states = [
            ''                => get_string('choosestatems', 'local_organization'),
            'Johor'           => 'Johor',
            'Kedah'           => 'Kedah',
            'Kelantan'        => 'Kelantan',
            'Melaka'          => 'Melaka',
            'Negeri Sembilan' => 'Negeri Sembilan',
            'Pahang'          => 'Pahang',
            'Perak'           => 'Perak',
            'Perlis'          => 'Perlis',
            'Pulau Pinang'    => 'Pulau Pinang',
            'Sabah'           => 'Sabah',
            'Sarawak'         => 'Sarawak',
            'Selangor'        => 'Selangor',
            'Terengganu'      => 'Terengganu',
            'W.P. Kuala Lumpur' => 'W.P. Kuala Lumpur',
            'W.P. Labuan'       => 'W.P. Labuan',
            'W.P. Putrajaya'    => 'W.P. Putrajaya',
        ];
        $mform->addElement('select', 'state',
            get_string('state', 'local_organization'), $states);
        $mform->setType('state', PARAM_TEXT);

        $districtoptions = [
            '' => get_string('choosedistrict', 'local_organization'),
        ];
        $mform->addElement('select', 'district',
            get_string('district', 'local_organization'), $districtoptions);
        $mform->setType('district', PARAM_TEXT);

        // === SECTION: Commandant User ===
        $mform->addElement('header', 'cmdheader',
            get_string('section_commandant', 'local_organization'));

        $mform->addElement('static', 'cmdinfo', '',
            get_string('usersectioninfo', 'local_organization'));

        $mform->addElement('text', 'commandantfirstname', get_string('firstname'));
        $mform->setType('commandantfirstname', PARAM_NOTAGS);

        $mform->addElement('text', 'commandantlastname', get_string('lastname'));
        $mform->setType('commandantlastname', PARAM_NOTAGS);

        $mform->addElement('text', 'commandantemail', get_string('email'));
        $mform->setType('commandantemail', PARAM_EMAIL);

        // === SECTION: Administrator User ===
        $mform->addElement('header', 'adminheader',
            get_string('section_admin', 'local_organization'));

        $mform->addElement('static', 'admininfo', '',
            get_string('adminsectioninfo', 'local_organization'));

        $mform->addElement('text', 'adminfirstname', get_string('firstname'));
        $mform->setType('adminfirstname', PARAM_NOTAGS);

        $mform->addElement('text', 'adminlastname', get_string('lastname'));
        $mform->setType('adminlastname', PARAM_NOTAGS);

        $mform->addElement('text', 'adminemail', get_string('email'));
        $mform->setType('adminemail', PARAM_EMAIL);

        $this->add_action_buttons(
            true,
            $isupdate ? get_string('savechanges') : get_string('saveanddisplay')
        );
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['commandantemail']) &&
            (empty($data['commandantfirstname']) || empty($data['commandantlastname']))) {
            $errors['commandantfirstname'] =
                get_string('errornameforemail', 'local_organization');
        }

        if (!empty($data['adminemail']) &&
            (empty($data['adminfirstname']) || empty($data['adminlastname']))) {
            $errors['adminfirstname'] =
                get_string('errornameforemail', 'local_organization');
        }

        // Email trapping: admin email cannot be the same as commandant email.
        if (!empty($data['adminemail']) &&
            !empty($data['commandantemail']) &&
            $data['adminemail'] === $data['commandantemail']) {
            $errors['adminemail'] =
                get_string('erroradminsameascommandant', 'local_organization');
        }

        return $errors;
    }
}
