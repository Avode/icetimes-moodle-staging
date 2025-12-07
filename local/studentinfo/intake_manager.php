<?php
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/locallib.php');

// Guard-load bridge (autoload cache safe)
if (!class_exists('\\local_studentinfo\\local\\orgstructure_bridge')) {
    require_once($CFG->dirroot.'/local/studentinfo/classes/local/orgstructure_bridge.php');
}

require_login();
$context = context_system::instance();
/** Site admin bypass; others must have manage cap */
if (!is_siteadmin()) {
    require_capability('local/studentinfo:manage', $context);
}

$ouid = optional_param('ou', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$PAGE->set_url(new moodle_url('/local/studentinfo/intake_manager.php', ['ou'=>$ouid]));
$PAGE->set_context($context);
$PAGE->set_title('Intake Manager');
$PAGE->set_heading(get_string('pluginname', 'local_studentinfo'));
$PAGE->requires->css('/local/studentinfo/style.css');

global $DB, $OUTPUT;

// ---------- Actions (POST, sesskey) ----------
if ($action === 'ensure' && confirm_sesskey()) {
    $iid = required_param('id', PARAM_INT);
    \local_studentinfo_ensure_intake_cohort($iid);
    redirect(new moodle_url('/local/studentinfo/intake_manager.php', ['ou'=>$ouid]),
        'Cohort ensured for the intake.', null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($action === 'sync' && confirm_sesskey()) {
    $iid = required_param('id', PARAM_INT);
    // Ensure cohort then insert missing members for this intake (OU-scoped)
    $cohortid = \local_studentinfo_ensure_intake_cohort($iid);
    if ($cohortid) {
        $params = ['iid'=>$iid, 'now'=>time()];
        $ousql = '';
        if ($ouid) { $ousql = " JOIN {local_org_member} om ON om.userid=sp.userid AND om.orgunitid=:ou "; $params['ou']=$ouid; }
        $DB->execute("
            INSERT IGNORE INTO {cohort_members} (cohortid, userid, timeadded)
            SELECT i.cohortid, sp.userid, :now
              FROM {local_studentinfo_studentprog} sp
              $ousql
              JOIN {local_studentinfo_intake} i ON i.id = sp.intakeid
             WHERE sp.intakeid = :iid AND i.cohortid IS NOT NULL
        ", $params);
    }
    redirect(new moodle_url('/local/studentinfo/intake_manager.php', ['ou'=>$ouid]),
        'Members synced from Programme & Intake.', null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($action === 'ensure_all' && confirm_sesskey()) {
    // Ensure cohorts for all listed intakes (OU-bound list below)
    $list = optional_param_array('intakes', [], PARAM_INT);
    foreach ($list as $iid) { \local_studentinfo_ensure_intake_cohort((int)$iid); }
    redirect(new moodle_url('/local/studentinfo/intake_manager.php', ['ou'=>$ouid]),
        'Cohorts ensured for all listed intakes.', null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($action === 'sync_all' && confirm_sesskey()) {
    $list = optional_param_array('intakes', [], PARAM_INT);
    // Ensure all cohorts first
    foreach ($list as $iid) { \local_studentinfo_ensure_intake_cohort((int)$iid); }
    // Then add members (OU-scoped)
    $params = ['now'=>time()];
    $ousql = '';
    if ($ouid) { $ousql = " JOIN {local_org_member} om ON om.userid=sp.userid AND om.orgunitid=:ou "; $params['ou']=$ouid; }
    list($in, $inparams) = $DB->get_in_or_equal($list, SQL_PARAMS_NAMED);
    $params += $inparams;
    $DB->execute("
        INSERT IGNORE INTO {cohort_members} (cohortid, userid, timeadded, component)
        SELECT i.cohortid, sp.userid, :now, 'local_studentinfo'
          FROM {local_studentinfo_studentprog} sp
          $ousql
          JOIN {local_studentinfo_intake} i ON i.id = sp.intakeid
         WHERE sp.intakeid $in AND i.cohortid IS NOT NULL
    ", $params);
    redirect(new moodle_url('/local/studentinfo/intake_manager.php', ['ou'=>$ouid]),
        'All listed intakes synced.', null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

// ---------- OU banner + OU bar ----------
echo \local_studentinfo\local\orgstructure_bridge::ou_banner($ouid, 'Scope');
echo \local_studentinfo\local\orgstructure_bridge::render_ou_bar($ouid);

// ---------- Fetch OU-bound intake list with counts ----------
$where = '';
$params = [];
if ($ouid) {
    $where = "WHERE EXISTS (
        SELECT 1 FROM {local_studentinfo_studentprog} sp
        JOIN {local_org_member} om ON om.userid = sp.userid AND om.orgunitid = :ou
        WHERE sp.intakeid = i.id
    )";
    $params['ou'] = $ouid;
}
$intakes = $DB->get_records_sql("
    SELECT
      i.*,
      -- Students in this intake scoped to OU (or all if no OU)
      (
        SELECT COUNT(DISTINCT sp.userid)
          FROM {local_studentinfo_studentprog} sp
          ".($ouid ? "JOIN {local_org_member} om ON om.userid=sp.userid AND om.orgunitid=:ou" : "")."
         WHERE sp.intakeid = i.id
      ) AS oustudents,
      -- Cohort members count (if linked)
      (
        SELECT COUNT(1) FROM {cohort_members} cm
         WHERE i.cohortid IS NOT NULL AND cm.cohortid = i.cohortid
      ) AS cohortmembers
    FROM {local_studentinfo_intake} i
    $where
    ORDER BY i.startdate DESC, i.id DESC
", $params);

// Prepare list of IDs for bulk actions
$idlist = array_map(fn($r) => (int)$r->id, $intakes ? array_values($intakes) : []);

// ---------- Bulk actions toolbar ----------
if (!empty($intakes)) {
    $bulkurlEnsure = new moodle_url('/local/studentinfo/intake_manager.php', ['ou'=>$ouid, 'action'=>'ensure_all', 'sesskey'=>sesskey()]);
    $bulkurlSync   = new moodle_url('/local/studentinfo/intake_manager.php', ['ou'=>$ouid, 'action'=>'sync_all',   'sesskey'=>sesskey()]);
    echo html_writer::start_div('container mb-2');
    echo html_writer::start_tag('form', ['method'=>'post','action'=>$bulkurlEnsure, 'class'=>'d-inline']);
    foreach ($idlist as $iid) {
        echo html_writer::empty_tag('input', ['type'=>'hidden','name'=>'intakes[]','value'=>$iid]);
    }
    echo html_writer::empty_tag('input', ['type'=>'submit','class'=>'btn btn-primary me-2','value'=>'Ensure all (listed)']);
    echo html_writer::end_tag('form');

    echo html_writer::start_tag('form', ['method'=>'post','action'=>$bulkurlSync, 'class'=>'d-inline']);
    foreach ($idlist as $iid) {
        echo html_writer::empty_tag('input', ['type'=>'hidden','name'=>'intakes[]','value'=>$iid]);
    }
    echo html_writer::empty_tag('input', ['type'=>'submit','class'=>'btn btn-secondary','value'=>'Sync members (listed)']);
    echo html_writer::end_tag('form');
    echo html_writer::end_div();
}

// ---------- Table ----------
echo html_writer::start_div('container');
echo html_writer::tag('h5', 'Intakes');

$table = new html_table();
$table->head = ['Code', 'Name', 'Start', 'End', 'Cohort', 'Cohort Members', 'Students (OU)', 'Actions'];

foreach ($intakes as $i) {
    $cohortstr = '-';
    if (!empty($i->cohortid)) {
        $c = $DB->get_record('cohort', ['id'=>$i->cohortid], 'id,name,idnumber', IGNORE_MISSING);
        if ($c) {
            $cohortstr = s($c->name) . '<br><small>'.s($c->idnumber).'</small>';
        } else {
            $cohortstr = '<span class="text-muted">Linked (missing?)</span>';
        }
    }

    // Per-row actions
    $ensureurl = new moodle_url('/local/studentinfo/intake_manager.php', ['ou'=>$ouid,'action'=>'ensure','id'=>(int)$i->id,'sesskey'=>sesskey()]);
    $syncurl   = new moodle_url('/local/studentinfo/intake_manager.php', ['ou'=>$ouid,'action'=>'sync',  'id'=>(int)$i->id,'sesskey'=>sesskey()]);

    $actions = html_writer::link($ensureurl, 'Ensure Cohort', ['class'=>'btn btn-sm btn-outline-primary me-1'])
             . html_writer::link($syncurl,   'Sync Members',  ['class'=>'btn btn-sm btn-outline-secondary']);

    $table->data[] = [
        s($i->code),
        s($i->name),
        $i->startdate ?: '-',
        $i->enddate   ?: '-',
        $cohortstr,
        isset($i->cohortmembers) ? (int)$i->cohortmembers : 0,
        isset($i->oustudents)    ? (int)$i->oustudents    : 0,
        $actions
    ];
}
if (empty($intakes)) {
    $table->data[] = ['—','No intakes in this OU scope. Post some students to an intake first.','—','—','—','—','—','—'];
}

echo html_writer::table($table);
echo html_writer::end_div(); // container

echo $OUTPUT->footer();
