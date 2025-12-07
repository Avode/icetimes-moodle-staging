<?php
require_once(__DIR__ . '/../../config.php');
require_login();
$context = context_system::instance();
if(!is_siteadmin()){
  require_capability('local/studentinfo:manage', $context);
}
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/studentinfo/gpa_snapshot.php'));
$PAGE->set_title(get_string('gpasnapshot', 'local_studentinfo'));
$PAGE->set_heading(get_string('gpasnapshot', 'local_studentinfo'));

echo $OUTPUT->header();
global $DB;
$now = time();

$sql = "
REPLACE INTO {local_studentinfo_gpa}
  (userid, termid, credits_attempted, credits_earned, gpa, cgpa, snapshot_ts)
SELECT
  gg.userid,
  ct.termid,
  COUNT(*) AS credits_attempted,
  COUNT(*) AS credits_earned,
  ROUND(AVG( (gg.finalgrade / gi.grademax) * 4.00 ), 2) AS gpa,
  ROUND(AVG( (gg.finalgrade / gi.grademax) * 4.00 ), 2) AS cgpa,
  :snap
FROM {local_studentinfo_course_term} ct
JOIN {grade_items} gi ON gi.courseid = ct.courseid AND gi.itemtype='course'
JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.finalgrade IS NOT NULL
GROUP BY gg.userid, ct.termid";
$DB->execute($sql, ['snap' => $now]);

echo $OUTPUT->notification('GPA snapshot updated.', 'notifysuccess');
echo $OUTPUT->footer();
