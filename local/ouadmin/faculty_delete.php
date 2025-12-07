<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ouadmin/lib.php');

$id   = required_param('id', PARAM_INT);
$ouid = optional_param('ouid', 0, PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/ouadmin:manage', $context);
require_sesskey();

$ous = local_ouadmin_get_user_ous($USER->id);

$faculty = $DB->get_record('local_ouadmin_faculty', ['id' => $id], '*', MUST_EXIST);

if (!array_key_exists($faculty->ouid, $ous) && !is_siteadmin($USER)) {
    print_error('nopermissions', 'error', '', 'delete this faculty');
}

$DB->delete_records('local_ouadmin_faculty', ['id' => $id]);

if ($ouid == 0) {
    $ouid = $faculty->ouid;
}

redirect(new moodle_url('/local/ouadmin/index.php', ['tab' => 'faculty', 'ouid' => $ouid]));
