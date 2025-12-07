<?php
namespace local_studentinfo\task;

defined('MOODLE_INTERNAL') || die();

class intake_cohort_sync_task extends \core\task\scheduled_task {
    public function get_name() {
        return 'Intake cohort sync';
    }
    public function execute() {
        global $DB;
        // Ensure cohorts for all intakes exist.
        $intakeids = $DB->get_fieldset_select('local_studentinfo_intake', 'id', 'id > 0', []);
        require_once(__DIR__.'/../../locallib.php');
        foreach ($intakeids as $iid) { \local_studentinfo_ensure_intake_cohort((int)$iid); }

        // Add missing members for all students with an intake.
        $DB->execute("
          INSERT IGNORE INTO {cohort_members} (cohortid, userid, timeadded, component)
          SELECT i.cohortid, sp.userid, :t, 'local_studentinfo'
            FROM {local_studentinfo_studentprog} sp
            JOIN {local_studentinfo_intake} i ON i.id = sp.intakeid
           WHERE sp.intakeid IS NOT NULL AND i.cohortid IS NOT NULL
        ", ['t'=>time()]);
        return true;
    }
}
