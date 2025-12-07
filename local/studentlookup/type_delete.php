<?php
require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/studentlookup:manage', $context);
require_sesskey();

$tablename = 'local_studentlookup_type';

if ($DB->record_exists($tablename, ['id' => $id])) {
    $DB->delete_records($tablename, ['id' => $id]);
}

redirect(new moodle_url('/local/studentlookup/index.php', ['tab' => 'studenttype']),
    get_string('deleted', 'local_studentlookup'));

