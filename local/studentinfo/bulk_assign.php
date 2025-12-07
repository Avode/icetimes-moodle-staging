<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');
require_once(__DIR__ . '/forms/bulk_assign_form.php');
require_once(__DIR__ . '/locallib.php'); // ensure_* cohort helpers

// Guard load bridge (helps during dev/purge if autoloader cache is stale)
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

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/studentinfo/bulk_assign.php', ['ou'=>$ouid]));
$PAGE->set_title(get_string('bulkassign', 'local_studentinfo'));
$PAGE->set_heading(get_string('pluginname', 'local_studentinfo'));
$PAGE->requires->css('/local/studentinfo/style.css'); // your CSS

echo $OUTPUT->header();

/* Non-dismissible OU banner */
echo \local_studentinfo\local\orgstructure_bridge::ou_banner($ouid, 'Scope');

/* Optional: a compact OU bar (keeps ou on navigation) */
echo \local_studentinfo\local\orgstructure_bridge::render_ou_bar($ouid);

$mform = new bulk_assign_form();
$mform->display();

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/studentinfo/bulk_assign.php', ['ou'=>$ouid]));
    // no further output
}

if ($data = $mform->get_data()) {
    global $DB;

    $content = $mform->get_file_content('csvfile');
    if (!$content) {
        echo html_writer::div('No file content', 'alert alert-danger');
        echo $OUTPUT->footer();
        exit;
    }

    $csv = new csv_import_reader('studentinfo_bulk', 'studentinfo');
    $csv->load_csv_content($content, 'utf-8', ',');
    $cols = $csv->get_columns();

    $required = ['userid','programme_code','major_code','intake_code','admit_term_code','status'];
    foreach ($required as $r) {
        if (!in_array($r, $cols)) {
            echo html_writer::div('Missing column: '.s($r), 'alert alert-danger');
            $csv->close(); $csv->cleanup();
            echo $OUTPUT->footer(); exit;
        }
    }

    // Clear staging
    $DB->execute('TRUNCATE {tmp_studentprog_import}');

    // Load rows into staging
    $row = $csv->next(); $imported = 0;
    while ($row) {
        $rec = (object)[
            'userid'           => (int)$row[array_search('userid',$cols)],
            'programme_code'   => trim($row[array_search('programme_code',$cols)]),
            'major_code'       => trim($row[array_search('major_code',$cols)]),
            'intake_code'      => trim($row[array_search('intake_code',$cols)]),
            'admit_term_code'  => trim($row[array_search('admit_term_code',$cols)]),
            'status'           => trim($row[array_search('status',$cols)]),
        ];
        $DB->insert_record('tmp_studentprog_import', $rec);
        $imported++;
        $row = $csv->next();
    }
    $csv->close(); $csv->cleanup();

    // Resolve FKs (collation-safe) into a view so we can reuse in multiple statements
    $DB->execute('DROP VIEW IF EXISTS {tmp_v_sp_resolved}');
    $DB->execute("
        CREATE VIEW {tmp_v_sp_resolved} AS
        SELECT t.userid,
               p.id  AS programmeid,
               m.id  AS majorid,
               i.id  AS intakeid,
               tm.id AS admittermid,
               t.status
        FROM {tmp_studentprog_import} t
        JOIN {local_studentinfo_programme} p
          ON p.code COLLATE utf8mb4_unicode_ci = t.programme_code COLLATE utf8mb4_unicode_ci
        LEFT JOIN {local_studentinfo_major} m
          ON m.programmeid = p.id
         AND (t.major_code IS NULL OR m.code COLLATE utf8mb4_unicode_ci = t.major_code COLLATE utf8mb4_unicode_ci)
        LEFT JOIN {local_studentinfo_intake} i
          ON i.code COLLATE utf8mb4_unicode_ci = t.intake_code COLLATE utf8mb4_unicode_ci
        LEFT JOIN {local_studentinfo_term} tm
          ON tm.code COLLATE utf8mb4_unicode_ci = t.admit_term_code COLLATE utf8mb4_unicode_ci
    ");

    $now = time();

    // INSERT new studentprog (idempotent by (userid,programmeid,intakeid))
    $DB->execute("
        INSERT INTO {local_studentinfo_studentprog}
          (userid, programmeid, majorid, intakeid, admittermid, orgunitid, status, status_ts, timecreated, timemodified)
        SELECT r.userid, r.programmeid, r.majorid, r.intakeid, r.admittermid, NULL, r.status, :now, :now, :now
        FROM {tmp_v_sp_resolved} r
        LEFT JOIN {local_studentinfo_studentprog} sp
          ON sp.userid=r.userid AND sp.programmeid=r.programmeid
         AND ((r.intakeid IS NULL AND sp.intakeid IS NULL) OR sp.intakeid=r.intakeid)
        WHERE sp.id IS NULL
    ", ['now'=>$now]);

    // UPDATE existing studentprog
    $DB->execute("
        UPDATE {local_studentinfo_studentprog} sp
        JOIN {tmp_v_sp_resolved} r
          ON sp.userid=r.userid AND sp.programmeid=r.programmeid
         AND ((r.intakeid IS NULL AND sp.intakeid IS NULL) OR sp.intakeid=r.intakeid)
        SET sp.majorid     = r.majorid,
            sp.admittermid = r.admittermid,
            sp.status      = r.status,
            sp.status_ts   = :now,
            sp.timemodified= :now
    ", ['now'=>$now]);

    // Ensure cohorts exist for any referenced intakes
    $intakeids = $DB->get_fieldset_sql("SELECT DISTINCT intakeid FROM {tmp_v_sp_resolved} WHERE intakeid IS NOT NULL");
    foreach ($intakeids as $iid) { local_studentinfo_ensure_intake_cohort((int)$iid); }

    // Add users to their intake cohorts (idempotent)
    $DB->execute("
        INSERT IGNORE INTO {cohort_members} (cohortid, userid, timeadded, component)
        SELECT i.cohortid, r.userid, :now, 'local_studentinfo'
        FROM {tmp_v_sp_resolved} r
        JOIN {local_studentinfo_intake} i ON i.id=r.intakeid
        WHERE r.intakeid IS NOT NULL AND i.cohortid IS NOT NULL
    ", ['now'=>$now]);

    // Success notice (non-dismissible), includes OU name
    $ouname = \local_studentinfo\local\orgstructure_bridge::ou_name($ouid);
    echo html_writer::div(
        'Imported rows: '.(int)$imported.' · Intake cohorts updated · OU: '.s($ouname),
        'alert alert-success'
    );
}

echo $OUTPUT->footer();
