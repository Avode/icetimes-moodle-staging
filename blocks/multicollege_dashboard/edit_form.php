<?php
defined('MOODLE_INTERNAL') || die();

class block_multicollege_dashboard_edit_form extends block_edit_form {
    protected function specific_definition($mform) {
        $mform->addElement('advcheckbox', 'config_show_viewdashboard',
            get_string('show_viewdashboard', 'block_multicollege_dashboard'));
        $mform->setDefault('config_show_viewdashboard', 1);

        $mform->addElement('advcheckbox', 'config_show_managedashboard',
            get_string('show_managedashboard', 'block_multicollege_dashboard'));
        $mform->setDefault('config_show_managedashboard', 1);

        $mform->addElement('advcheckbox', 'config_show_managecolleges',
            get_string('show_managecolleges', 'block_multicollege_dashboard'));
        $mform->setDefault('config_show_managecolleges', 1);

        $mform->addElement('advcheckbox', 'config_show_assignusers',
            get_string('show_assignusers', 'block_multicollege_dashboard'));
        $mform->setDefault('config_show_assignusers', 1);

        $mform->addElement('advcheckbox', 'config_show_reports',
            get_string('show_reports', 'block_multicollege_dashboard'));
        $mform->setDefault('config_show_reports', 1);
    }
}
