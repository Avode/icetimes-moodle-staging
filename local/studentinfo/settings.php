<?php
defined('MOODLE_INTERNAL') || die();

if ($h = get_string_manager()->string_exists('pluginname','local_studentinfo')) {
    $ADMIN->add('localplugins', new admin_externalpage('local_studentinfo',
        get_string('pluginname','local_studentinfo'),
        new moodle_url('/local/studentinfo/index.php')));
}
