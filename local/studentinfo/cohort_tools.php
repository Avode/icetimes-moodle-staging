<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

if (!class_exists('\\local_studentinfo\\local\\orgstructure_bridge')) {
    require_once($CFG->dirroot.'/local/studentinfo/classes/local/orgstructure_bridge.php');
}else{
    echo 'exist';exit;
}
use local_studentinfo\local\orgstructure_bridge;;

require_login();
$context = context_system::instance();
if (!is_siteadmin()) {
    require_capability('local/studentinfo:manage', $context);
}
$ouid = optional_param('ou', 0, PARAM_INT);

$url = orgstructure_bridge::with_ou(new moodle_url('/local/studentinfo/student.php'), $ouid);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/studentinfo/cohort_tools.php', ['ou'=>$ouid]));
$PAGE->set_title(get_string('cohorttools', 'local_studentinfo'));
$PAGE->set_heading(get_string('cohorttools', 'local_studentinfo'));
$PAGE->requires->css('/local/studentinfo/style.css');

echo $OUTPUT->header();

/* Non-dismissible OU banner */
echo orgstructure_bridge::ou_banner($ouid, 'Scope');

$action = optional_param('action', '', PARAM_ALPHA);
$sess   = optional_param('sesskey', '', PARAM_ALPHANUM);
$sessok = $sess && confirm_sesskey($sess);


if ($action === 'makecohorts' && confirm_sesskey()) {
    // Create/link cohorts for all intakes (or only intakes that have students in this OU)
    if ($sessok) {
        $intakeids = $DB->get_fieldset_sql("
            SELECT DISTINCT sp.intakeid
              FROM {local_studentinfo_studentprog} sp
              JOIN {local_org_member} om ON om.userid=sp.userid AND om.orgunitid=:ou
             WHERE sp.intakeid IS NOT NULL
        ", ['ou'=>$ouid]);
    } else {
        $intakeids = $DB->get_fieldset_select('local_studentinfo_intake','id','cohortid IS NULL OR cohortid > 0', []);
        // ^ above just loads all; ensure cohort for each below
    }
    foreach ($intakeids as $iid) { local_studentinfo_ensure_intake_cohort((int)$iid); }
    echo html_writer::div('Cohorts created/linked for intakes in scope.', 'alert alert-success');
}
else if ($action === 'syncmembers' && confirm_sesskey()) {
    // Add any student with intake → into that intake cohort (OU-scoped if set)
    $params = [];
    $ousql  = '';
    if ($sessok) { $ousql = " JOIN {local_org_member} om ON om.userid=sp.userid AND om.orgunitid=:ou "; $params['ou']=$ouid; }

    // Ensure cohorts exist for all referenced intakes first
    $intakeids = $DB->get_fieldset_sql("SELECT DISTINCT sp.intakeid FROM {local_studentinfo_studentprog} sp WHERE sp.intakeid IS NOT NULL");
    foreach ($intakeids as $iid) { local_studentinfo_ensure_intake_cohort((int)$iid); }

    // Then insert missing cohort_members
    $sql = "
      INSERT IGNORE INTO {cohort_members} (cohortid, userid, timeadded)
      SELECT i.cohortid, sp.userid, :t
      FROM {local_studentinfo_studentprog} sp
      $ousql
      JOIN {local_studentinfo_intake} i ON i.id = sp.intakeid
      WHERE sp.intakeid IS NOT NULL AND i.cohortid IS NOT NULL
    ";
    $params['t'] = time();
    $DB->execute($sql, $params);

    echo html_writer::div('Cohort members synced from Programme & Intake.', 'alert alert-success');
}

/* OU select bar */
echo orgstructure_bridge::render_ou_bar($ouid, ['action'=>optional_param('action','',PARAM_ALPHA)], 'OU: ');

// Controls
$makeurl = orgstructure_bridge::with_ou(new moodle_url('/local/studentinfo/cohort_tools.php',
            ['action'=>'makecohorts','sesskey'=>sesskey()]), $ouid);
$syncurl = orgstructure_bridge::with_ou(new moodle_url('/local/studentinfo/cohort_tools.php',
            ['action'=>'syncmembers','sesskey'=>sesskey()]), $ouid);

echo html_writer::div(
    html_writer::link($makeurl, 'Create/Link Intake Cohorts', ['class'=>'btn btn-primary me-2']) .
    html_writer::link($syncurl, 'Sync Members from StudentProg', ['class'=>'btn btn-secondary']),
    'mb-3'
);

echo $OUTPUT->footer();
