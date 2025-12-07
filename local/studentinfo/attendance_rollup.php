<?php
require_once(__DIR__ . '/../../config.php');
require_login();
$context = context_system::instance();
/** Site admin bypass; others must have manage cap */
if (!is_siteadmin()) {
    require_capability('local/studentinfo:manage', $context);
}


$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/studentinfo/attendance_rollup.php'));
$PAGE->set_title(get_string('attnrollup', 'local_studentinfo'));
$PAGE->set_heading(get_string('attnrollup', 'local_studentinfo'));

echo $OUTPUT->header();
global $DB;

$sql = "
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
GROUP BY l.studentid, a.course, ct.termid";
$DB->execute($sql);

echo $OUTPUT->notification('Attendance rollup updated.', 'notifysuccess');
echo $OUTPUT->footer();
