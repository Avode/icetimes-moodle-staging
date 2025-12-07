<?php
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);

// Only users who can manage studentinfo should call this.
require_capability('local/studentinfo:manage', $context);

header('Content-Type: application/json; charset=utf-8');

$ouid      = required_param('ouid', PARAM_INT);      // kept for future / debugging
$facultyid = required_param('facultyid', PARAM_INT);

global $DB;

// Safety: use table_exists with string name, not xmldb_table.
$manager = $DB->get_manager();
if (!$manager->table_exists('local_ouadmin_intake')) {
    echo json_encode(['intakes' => []]);
    exit;
}

// Filter by facultyid (this is the relationship).
$records = $DB->get_records('local_ouadmin_intake',
    ['facultyid' => $facultyid],
    'name ASC', 'id, name');

$result = [];
foreach ($records as $r) {
    $result[] = [
        'id'   => (int)$r->id,
        'name' => format_string($r->name, true, ['context' => $context]),
    ];
}

echo json_encode(['intakes' => $result]);
exit;
