<?php
// Student Info dashboard: management cards + manage student info table.

require_once(__DIR__ . '/../../config.php');

require_login();

global $DB, $USER, $PAGE, $CFG;

$context = context_system::instance();
require_capability('local/studentinfo:view', $context);

$manager = $DB->get_manager();

// --- Read filters from URL ---
$studenttypeid = optional_param('studenttypeid', 0, PARAM_INT);
$serviceid     = optional_param('serviceid', 0, PARAM_INT);
$korid         = optional_param('korid', 0, PARAM_INT);
$ouid          = optional_param('ouid', 0, PARAM_INT);
$facultyid     = optional_param('facultyid', 0, PARAM_INT);
$intakeid      = optional_param('intakeid', 0, PARAM_INT);

// --- OU scoping (College) based on local_organization_ou ---
$ous = [];
if ($manager->table_exists('local_organization_ou')) {
    if (is_siteadmin()) {
        $ous = $DB->get_records_menu('local_organization_ou',
            ['deleted' => 0], 'fullname', 'id, fullname');
    } else {
        $ous = $DB->get_records_menu('local_organization_ou',
            ['deleted' => 0, 'adminuserid' => $USER->id],
            'fullname', 'id, fullname');
    }
}

// Non-siteadmin: enforce OU restriction.
if (!is_siteadmin()) {
    if (empty($ous)) {
        print_error('You are not assigned to any organisation unit.');
    } else if (!array_key_exists($ouid, $ous)) {
        reset($ous);
        $firstkey = key($ous);
        $ouid = (int)$firstkey;
    }
}

// Siteadmin: allow "All colleges" option.
$ouoptions = [];
if (is_siteadmin()) {
    $ouoptions[0] = get_string('all');
}
foreach ($ous as $id => $name) {
    $ouoptions[$id] = $name;
}

// --- Load lookup filters ---

// Student Type (local_studentlookup_type).
$studenttypeoptions = [0 => get_string('all')];
if ($manager->table_exists('local_studentlookup_type')) {
    $studenttypes = $DB->get_records_menu('local_studentlookup_type', null, 'name ASC', 'id, name');
    foreach ($studenttypes as $id => $name) {
        $studenttypeoptions[$id] = $name;
    }
}

// Service (local_studentlookup_service).
$serviceoptions = [0 => get_string('all')];
if ($manager->table_exists('local_studentlookup_service')) {
    $params = [];
    $wheres = [];
    if (!empty($studenttypeid)) {
        $wheres[] = 'studenttypeid = :stid';
        $params['stid'] = $studenttypeid;
    }
    $whereclause = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';
    $sql = "SELECT id, name FROM {local_studentlookup_service} {$whereclause} ORDER BY name ASC";
    $services = $DB->get_records_sql($sql, $params);
    foreach ($services as $s) {
        $serviceoptions[$s->id] = $s->name;
    }
}

// Regiment/Kor/Branch (local_studentlookup_korregimen).
$koroptions = [0 => get_string('all')];
if ($manager->table_exists('local_studentlookup_korregimen')) {
    $params = [];
    $wheres = [];
    if (!empty($serviceid)) {
        $wheres[] = 'serviceid = :sid';
        $params['sid'] = $serviceid;
    }
    $whereclause = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';
    $sql = "SELECT id, name FROM {local_studentlookup_korregimen} {$whereclause} ORDER BY name ASC";
    $kors = $DB->get_records_sql($sql, $params);
    foreach ($kors as $k) {
        $koroptions[$k->id] = $k->name;
    }
}

// Faculties for this OU (local_ouadmin_faculty).
$facultyoptions = [0 => get_string('all')];
if ($manager->table_exists('local_ouadmin_faculty')) {
    $params = [];
    $wheres = [];
    if (!empty($ouid)) {
        $wheres[] = 'ouid = :ou';
        $params['ou'] = $ouid;
    } elseif (!is_siteadmin() && !empty($ous)) {
        $in = array_keys($ous);
        list($sqlin, $inparams) = $DB->get_in_or_equal($in, SQL_PARAMS_NAMED, 'ou');
        $wheres[] = 'ouid ' . $sqlin;
        $params = array_merge($params, $inparams);
    }
    $whereclause = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';
    $sql = "SELECT id, name FROM {local_ouadmin_faculty} {$whereclause} ORDER BY name ASC";
    $faculties = $DB->get_records_sql($sql, $params);
    foreach ($faculties as $f) {
        $facultyoptions[$f->id] = $f->name;
    }
}

// Intakes for this OU/faculty (local_ouadmin_intake).
$intakeoptions = [0 => get_string('all')];
if ($manager->table_exists('local_ouadmin_intake') && $manager->table_exists('local_ouadmin_faculty')) {
    $params = [];
    $wheres = [];

    if (!empty($facultyid)) {
        $wheres[] = 'i.facultyid = :fid';
        $params['fid'] = $facultyid;
    } elseif (!empty($ouid)) {
        $wheres[] = 'f.ouid = :ouid';
        $params['ouid'] = $ouid;
    } elseif (!is_siteadmin() && !empty($ous)) {
        $in = array_keys($ous);
        list($sqlin, $inparams) = $DB->get_in_or_equal($in, SQL_PARAMS_NAMED, 'ou');
        $wheres[] = 'f.ouid ' . $sqlin;
        $params = array_merge($params, $inparams);
    }

    $whereclause = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';
    $sql = "SELECT i.id, CONCAT(f.name, ' - ', i.name) AS fullname
              FROM {local_ouadmin_intake} i
              JOIN {local_ouadmin_faculty} f ON f.id = i.facultyid
           {$whereclause}
          ORDER BY f.name ASC, i.name ASC";
    $intakes = $DB->get_records_sql($sql, $params);
    foreach ($intakes as $i) {
        $intakeoptions[$i->id] = $i->fullname;
    }
}

// --- Page setup ---
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/studentinfo/index.php', [
    'studenttypeid' => $studenttypeid,
    'serviceid'     => $serviceid,
    'korid'         => $korid,
    'ouid'          => $ouid,
    'facultyid'     => $facultyid,
    'intakeid'      => $intakeid,
]));
$PAGE->set_title(get_string('dashboard', 'local_studentinfo'));
$PAGE->set_heading(get_string('dashboard', 'local_studentinfo'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('dashboard', 'local_studentinfo'));

// OU badge.
if (!empty($ous) && $ouid && isset($ous[$ouid])) {
    echo html_writer::div(
        html_writer::tag('strong', 'Scope: ')
        . html_writer::span(s($ous[$ouid])),
        'alert alert-info',
        ['role' => 'status', 'style' => 'margin:10px 0;']
    );
}

// ======================================================================
//  MANAGEMENT CARDS (KEEPING THEM ABOVE THE TABLE)
// ======================================================================

echo html_writer::start_div('container mb-3');
echo html_writer::tag('h5', get_string('management', 'local_studentinfo'), ['class' => 'mb-2']);
echo '<br>';

echo html_writer::start_div('row g-3');

// Card 1: On-board Student.
echo html_writer::start_div('col-md-6');
echo html_writer::start_div('card shadow-sm h-100');
echo html_writer::start_div('card-body d-flex flex-column justify-content-between');
echo html_writer::tag('h5', get_string('addstudent', 'local_studentinfo'), ['class' => 'card-title']);
echo html_writer::tag('p',
    'Register new students manually or via CSV upload.',
    ['class' => 'card-text small text-muted']
);
$manualurl = new moodle_url('/local/studentinfo/add_student.php', [
    'ou'  => $ouid,
    'ori' => 1, // Add student manually
]);

$onboardurl = new moodle_url('/local/studentinfo/add_student.php', [
    'ou'  => $ouid,
    'ori' => 2, // On-board and update
]);

$bulkurla  = new moodle_url('/local/studentinfo/bulk_add_students.php', ['ou' => $ouid]);
echo html_writer::start_div('mt-2 d-grid gap-2');
echo html_writer::link($manualurl, get_string('addstudent', 'local_studentinfo'), ['class' => 'btn btn-primary']);
echo html_writer::link($bulkurla, get_string('bulkaddstudents', 'local_studentinfo'), ['class' => 'btn btn-outline-primary']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

// Card 2: On-board & Update Service Data.
echo html_writer::start_div('col-md-6');
echo html_writer::start_div('card shadow-sm h-100');
echo html_writer::start_div('card-body d-flex flex-column justify-content-between');
echo html_writer::tag('h5', 'On-board & Update Service Data', ['class' => 'card-title']);
echo html_writer::tag('p',
    'Maintain student service details (service branch, regiment/kor, postings, ranks).',
    ['class' => 'card-text small text-muted']
);
$manageurl = new moodle_url('/local/studentinfo/edit.php', [
    'userid' => -1,
    'ou'     => $ouid
]);
echo html_writer::link($onboardurl, 'On-board and update', ['class' => 'btn btn-secondary']);

echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // container

echo '<hr><br>';
// ======================================================================
//  FILTER BAR
// ======================================================================

echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'mb-3']);
echo html_writer::start_div('row g-2');

// Row 1: Student Type / Service / Kor
echo html_writer::start_div('col-md-2');
echo html_writer::label('Student Type', 'id_studenttypeid', ['class' => 'form-label']);
echo html_writer::select($studenttypeoptions, 'studenttypeid', $studenttypeid,
    null, ['class' => 'form-select', 'id' => 'id_studenttypeid']);
echo html_writer::end_div();

echo html_writer::start_div('col-md-2');
echo html_writer::label('Service', 'id_serviceid', ['class' => 'form-label']);
echo html_writer::select($serviceoptions, 'serviceid', $serviceid,
    null, ['class' => 'form-select', 'id' => 'id_serviceid']);
echo html_writer::end_div();

echo html_writer::start_div('col-md-2');
echo html_writer::label('Regiment / Kor / Branch', 'id_korid', ['class' => 'form-label']);
echo html_writer::select($koroptions, 'korid', $korid,
    null, ['class' => 'form-select', 'id' => 'id_korid']);
echo html_writer::end_div();

// Row 2: College / Faculty / Intake
echo html_writer::start_div('col-md-2');
echo html_writer::label('College (OU)', 'id_ouid', ['class' => 'form-label']);
echo html_writer::select($ouoptions, 'ouid', $ouid,
    null, ['class' => 'form-select', 'id' => 'id_ouid']);
echo html_writer::end_div();

echo html_writer::start_div('col-md-2');
echo html_writer::label('Faculty', 'id_facultyid', ['class' => 'form-label']);
echo html_writer::select($facultyoptions, 'facultyid', $facultyid,
    null, ['class' => 'form-select', 'id' => 'id_facultyid']);
echo html_writer::end_div();

echo html_writer::start_div('col-md-2');
echo html_writer::label('Intake', 'id_intakeid', ['class' => 'form-label']);
echo html_writer::select($intakeoptions, 'intakeid', $intakeid,
    null, ['class' => 'form-select', 'id' => 'id_intakeid']);
echo html_writer::end_div();

echo html_writer::end_div(); // row

echo html_writer::start_div('mt-2');
echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'class' => 'btn btn-primary me-2',
    'value' => get_string('filter')
]);
$reseturl = new moodle_url('/local/studentinfo/index.php');
echo html_writer::link($reseturl, get_string('reset'), ['class' => 'btn btn-secondary']);
echo html_writer::end_div();

echo html_writer::end_tag('form');

// ======================================================================
//  BUILD STUDENT LISTING QUERY
// ======================================================================

$params = [];
$sql = "
    SELECT
        u.id                AS userid,
        u.firstname,
        u.lastname,
        si.tentera_no       AS serviceno,
        si.studenttypeid,
        si.serviceid,
        si.korid,
        st.name             AS stype_name,
        sv.name             AS service_name,
        kg.name             AS kor_name,
        sm.ouid,
        ou.fullname         AS college_name,
        sm.facultyid,
        f.name              AS faculty_name,
        sm.intakeid,
        i.name              AS intake_name
    FROM {local_studentinfo} si
    JOIN {user} u ON u.id = si.userid
    LEFT JOIN {local_studentinfo_studentmap} sm ON sm.userid = u.id
    LEFT JOIN {local_organization_ou} ou ON ou.id = sm.ouid
    LEFT JOIN {local_ouadmin_faculty} f ON f.id = sm.facultyid
    LEFT JOIN {local_ouadmin_intake} i ON i.id = sm.intakeid
    LEFT JOIN {local_studentlookup_type} st ON st.id = si.studenttypeid
    LEFT JOIN {local_studentlookup_service} sv ON sv.id = si.serviceid
    LEFT JOIN {local_studentlookup_korregimen} kg ON kg.id = si.korid
    WHERE 1=1
";

// Exclude users with system-level roles.
$sysctx = $context; // context_system.
$params['sysctxid'] = $sysctx->id;
$sql .= " AND NOT EXISTS (
            SELECT 1
              FROM {role_assignments} ra
             WHERE ra.userid = u.id
               AND ra.contextid = :sysctxid
          )";

// Apply filters:

if (!empty($studenttypeid)) {
    $sql .= " AND si.studenttypeid = :flt_stid";
    $params['flt_stid'] = $studenttypeid;
}

if (!empty($serviceid)) {
    $sql .= " AND si.serviceid = :flt_sid";
    $params['flt_sid'] = $serviceid;
}

if (!empty($korid)) {
    $sql .= " AND si.korid = :flt_kid";
    $params['flt_kid'] = $korid;
}

if (!empty($ouid)) {
    $sql .= " AND sm.ouid = :flt_ouid";
    $params['flt_ouid'] = $ouid;
} elseif (!is_siteadmin() && !empty($ous)) {
    $in = array_keys($ous);
    list($sqlin, $inparams) = $DB->get_in_or_equal($in, SQL_PARAMS_NAMED, 'uou');
    $sql .= " AND sm.ouid {$sqlin}";
    $params = array_merge($params, $inparams);
}

if (!empty($facultyid)) {
    $sql .= " AND sm.facultyid = :flt_fid";
    $params['flt_fid'] = $facultyid;
}

if (!empty($intakeid)) {
    $sql .= " AND sm.intakeid = :flt_iid";
    $params['flt_iid'] = $intakeid;
}

$sql .= " ORDER BY u.lastname ASC, u.firstname ASC";

$students = $DB->get_records_sql($sql, $params);

// ======================================================================
//  RENDER TABLE
// ======================================================================

$table = new html_table();
$table->head = [
    '#',
    'Service No',
    'Name',
    'Student Type',
    'Service',
    'Regiment / Kor / Branch',
    'College',
    'Faculty',
    'Intake',
    get_string('actions'),
];

$counter = 0;
foreach ($students as $s) {
    $counter++;

    $fullname = fullname((object)[
        'firstname' => $s->firstname,
        'lastname'  => $s->lastname
    ]);

    $stype  = $s->stype_name ?? '';
    $svc    = $s->service_name ?? '';
    $kor    = $s->kor_name ?? '';
    $college= $s->college_name ?? '-';
    $fac    = $s->faculty_name ?? '-';
    $int    = $s->intake_name ?? '-';

    // Action buttons – adapt URLs if needed.
    $viewurl = new moodle_url('/local/studentinfo/view.php', ['userid' => $s->userid]);
    $editurl = new moodle_url('/local/studentinfo/edit.php', ['userid' => $s->userid]);

    $actions = html_writer::start_div('btn-group');
    $actions .= html_writer::link($viewurl, get_string('view'), ['class' => 'btn btn-sm btn-primary']);
    $actions .= html_writer::link($editurl, get_string('edit'), ['class' => 'btn btn-sm btn-secondary']);
    $actions .= html_writer::end_div();

    $table->data[] = [
        $counter,
        s($s->serviceno),
        s($fullname),
        s($stype),
        s($svc),
        s($kor),
        s($college),
        s($fac),
        s($int),
        $actions,
    ];
}

if (empty($students)) {
    echo html_writer::div('No students found for current filters.', 'alert alert-info');
} else {
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
