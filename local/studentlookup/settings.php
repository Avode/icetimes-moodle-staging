<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Add a link under Local plugins.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_studentlookup_manage',
        get_string('pluginname', 'local_studentlookup'),
        new moodle_url('/local/studentlookup/index.php'),
        'local/studentlookup:view'
    ));
}

