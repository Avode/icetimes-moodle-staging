<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_studentinfo_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // 2025082301: Add 'tahap' to local_student_academic.
    if ($oldversion < 2025082301) {
        $table = new xmldb_table('local_student_academic');
        $field = new xmldb_field('tahap', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'tahun');
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2025082301, 'local', 'studentinfo');
    }

    return true;
}
