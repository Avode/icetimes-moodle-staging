<?php
namespace local_studentinfo\task;
defined('MOODLE_INTERNAL') || die();

class attendance_rollup_task extends \core\task\scheduled_task {
    public function get_name() {
        return get_string('attnrollup', 'local_studentinfo');
    }
    public function execute() {
        global $DB;
        $DB->execute("
        REPLACE INTO {local_studentinfo_attn_rollup}
          (userid, courseid, termid, pct, lastcalc_ts)
        SELECT
          l.studentid                 AS userid,
          a.course                    AS courseid,
          ct.termid                   AS termid,
          ROUND(
            SUM(CASE WHEN s.acronym IN ('P','L','E') THEN 1 ELSE 0 END) * 100.0
            / NULLIF(COUNT(*),0)
          , 2)                        AS pct,
          UNIX_TIMESTAMP()            AS lastcalc_ts
        FROM {attendance} a
        JOIN {attendance_sessions} ses ON ses.attendanceid = a.id
        JOIN {attendance_log} l        ON l.sessionid      = ses.id
        JOIN {attendance_statuses} s   ON s.id             = l.statusid
        LEFT JOIN {local_studentinfo_course_term} ct ON ct.courseid = a.course
        GROUP BY l.studentid, a.course, ct.termid");
    }
}
