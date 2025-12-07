<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade script for local_organization.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_organization_upgrade($oldversion) {
    if ($oldversion < 2025112603) {
        // No DB changes, just CSS fix.
        upgrade_plugin_savepoint(true, 2025112603, 'local', 'organization');
    }
    return true;
}
