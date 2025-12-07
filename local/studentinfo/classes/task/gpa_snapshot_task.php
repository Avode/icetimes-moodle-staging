<?php
namespace local_studentinfo\task;
defined('MOODLE_INTERNAL') || die();

class gpa_snapshot_task extends \core\task\scheduled_task {
    public function get_name() {
        return get_string('gpasnapshot', 'local_studentinfo');
    }
    public function execute() {
        global $DB;
        $DB->execute("
        REPLACE INTO {local_studentinfo_gpa}
          (userid, termid, credits_attempted, credits_earned, gpa, cgpa, snapshot_ts)
        SELECT
          gg.userid,
          ct.termid,
          COUNT(*) AS credits_attempted,
          COUNT(*) AS credits_earned,
          ROUND(AVG( (gg.finalgrade / gi.grademax) * 4.00 ), 2) AS gpa,
          ROUND(AVG( (gg.finalgrade / gi.grademax) * 4.00 ), 2) AS cgpa,
          UNIX_TIMESTAMP()
        FROM {local_studentinfo_course_term} ct
        JOIN {grade_items} gi ON gi.courseid = ct.courseid AND gi.itemtype='course'
        JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.finalgrade IS NOT NULL
        GROUP BY gg.userid, ct.termid");
    }
}
