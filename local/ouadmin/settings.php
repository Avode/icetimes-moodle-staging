<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_ouadmin_dashboard',
        get_string('pluginname', 'local_ouadmin'),
        new moodle_url('/local/ouadmin/index.php'),
        'local/ouadmin:view'
    ));
}
