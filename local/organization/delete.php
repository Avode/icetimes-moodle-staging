<?php
// Soft-delete Organization Unit.

require('../../config.php');

$id = required_param('id', PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/organization:manage', $context);

global $DB;

if ($ou = $DB->get_record('local_organization_ou', ['id' => $id, 'deleted' => 0])) {
    $ou->deleted     = 1;
    $ou->timemodified = time();
    $DB->update_record('local_organization_ou', $ou);
}

redirect(
    new moodle_url('/local/organization/index.php'),
    get_string('oudeleted', 'local_organization'),
    2
);
