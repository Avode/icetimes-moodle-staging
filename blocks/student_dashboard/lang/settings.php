<?php


defined('MOODLE_INTERNAL') || die();


if ($hassiteconfig) {
$settings = new admin_settingpage('block_student_dashboard', get_string('pluginname', 'block_student_dashboard'));


$settings->add(new admin_setting_configcheckbox(
'block_student_dashboard/useplaceholders',
get_string('useplaceholders', 'block_student_dashboard'),
get_string('useplaceholders_desc', 'block_student_dashboard'),
1
));


$ADMIN->add('blocks', $settings);
}