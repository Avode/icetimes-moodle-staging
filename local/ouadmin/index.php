<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ouadmin/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/ouadmin:view', $context);

$tab  = optional_param('tab', 'faculty', PARAM_ALPHA);
$ouid = optional_param('ouid', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ouadmin/index.php', ['tab' => $tab, 'ouid' => $ouid]));
$PAGE->set_title(get_string('dashboardtitle', 'local_ouadmin'));
$PAGE->set_heading(get_string('dashboardtitle', 'local_ouadmin'));

echo $OUTPUT->header();

// Custom CSS.
$customcss = "
.ouadmin-page-title {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 0.75rem;
}
.ouadmin-maincard {
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.04);
}
.ouadmin-tablecard {
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.04);
}
.ouadmin-bignumber {
    font-size: 2.4rem;
    font-weight: 700;
}
.ouadmin-subtitle {
    font-size: 0.875rem;
    color: #6c757d;
}
.ouadmin-badge-pill {
    border-radius: 999px;
}
";
echo html_writer::tag('style', $customcss);

// DataTables assets.
echo html_writer::empty_tag('link', [
    'rel'  => 'stylesheet',
    'href' => 'https://cdn.datatables.net/1.13.5/css/jquery.dataTables.min.css'
]);
echo html_writer::empty_tag('link', [
    'rel'  => 'stylesheet',
    'href' => 'https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css'
]);

echo html_writer::tag('script', '', ['src' => 'https://code.jquery.com/jquery-3.7.0.min.js']);
echo html_writer::tag('script', '', ['src' => 'https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js']);
echo html_writer::tag('script', '', ['src' => 'https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js']);
echo html_writer::tag('script', '', ['src' => 'https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js']);
echo html_writer::tag('script', '', ['src' => 'https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js']);
echo html_writer::tag('script', '', ['src' => 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js']);

$datatableinit = <<<JS
jQuery(function($) {
    $('.ouadmin-datatable').DataTable({
        dom: 'Bfrtip',
        pageLength: 10,
        buttons: [
            { extend: 'copy',  title: 'College Administration' },
            { extend: 'csv',   title: 'College Administration' },
            { extend: 'print', title: 'College Administration' }
        ]
    });
});
JS;
echo html_writer::tag('script', $datatableinit);

// Build context and render.
$templatecontext = local_ouadmin_build_dashboard_context($tab, $ouid);
echo $OUTPUT->render_from_template('local_ouadmin/dashboard', $templatecontext);

echo $OUTPUT->footer();

/**
 * Build dashboard context for template.
 *
 * @param string $tab
 * @param int $ouid
 * @return array
 */
function local_ouadmin_build_dashboard_context(string $tab, int $ouid): array {
    global $PAGE, $USER, $DB;

    $currenturl = $PAGE->url->out(false);

    // OU handling.
    $ous     = local_ouadmin_get_user_ous($USER->id);
    $isadmin = is_siteadmin($USER);

    // Pick valid OU.
    if (!array_key_exists($ouid, $ous)) {
        reset($ous);
        $firstkey = key($ous);
        $ouid = (int)$firstkey;
    }
    $ouname = $ous[$ouid];

    // KPI values.
    $facultycount = $DB->count_records('local_ouadmin_faculty', ['ouid' => $ouid]);
    $intakecount  = $DB->count_records('local_ouadmin_intake', ['ouid' => $ouid]);
    $teachercount = $DB->count_records('local_ouadmin_teacher', ['ouid' => $ouid]);
    
    $kpis = [
        'faculties' => $facultycount,
        'intakes'   => $intakecount,
        'teachers'  => $teachercount,
        'students'  => 420,  // still dummy until we build student module
    ];


    // Tabs.
    $tabs = [
        [
            'key'    => 'faculty',
            'label'  => get_string('tab_faculty', 'local_ouadmin'),
            'url'    => (new moodle_url('/local/ouadmin/index.php', ['tab' => 'faculty', 'ouid' => $ouid]))->out(false),
            'active' => $tab === 'faculty',
        ],
        [
            'key'    => 'intake',
            'label'  => get_string('tab_intake', 'local_ouadmin'),
            'url'    => (new moodle_url('/local/ouadmin/index.php', ['tab' => 'intake', 'ouid' => $ouid]))->out(false),
            'active' => $tab === 'intake',
        ],
        [
            'key'    => 'teacher',
            'label'  => get_string('tab_teacher', 'local_ouadmin'),
            'url'    => (new moodle_url('/local/ouadmin/index.php', ['tab' => 'teacher', 'ouid' => $ouid]))->out(false),
            'active' => $tab === 'teacher',
        ],
        [
            'key'    => 'student',
            'label'  => get_string('tab_student', 'local_ouadmin'),
            'url'    => (new moodle_url('/local/ouadmin/index.php', ['tab' => 'student', 'ouid' => $ouid]))->out(false),
            'active' => $tab === 'student',
        ],
        [
            'key'    => 'course',
            'label'  => get_string('tab_course', 'local_ouadmin'),
            'url'    => (new moodle_url('/local/ouadmin/index.php', ['tab' => 'course', 'ouid' => $ouid]))->out(false),
            'active' => $tab === 'course',
        ],
    ];

    // Per-tab config.
    $config = [
        'faculty' => [
            'tabletitle' => get_string('facultytabletitle', 'local_ouadmin'),
            'badge'      => $kpis['faculties'] . ' ' . get_string('totalfaculties', 'local_ouadmin'),
            'addlabel'   => get_string('addnewfaculty', 'local_ouadmin'),
        ],
        'intake' => [
            'tabletitle' => get_string('intaketabletitle', 'local_ouadmin'),
            'badge'      => $kpis['intakes'] . ' ' . get_string('totalintakes', 'local_ouadmin'),
            'addlabel'   => get_string('addnewintake', 'local_ouadmin'),
        ],
        'teacher' => [
            'tabletitle' => get_string('teachertabletitle', 'local_ouadmin'),
            'badge'      => $kpis['teachers'] . ' ' . get_string('totalteachers', 'local_ouadmin'),
            'addlabel'   => get_string('addnewteacher', 'local_ouadmin'),
        ],
        'student' => [
            'tabletitle' => get_string('studenttabletitle', 'local_ouadmin'),
            'badge'      => $kpis['students'] . ' ' . get_string('totalstudents', 'local_ouadmin'),
            'addlabel'   => get_string('addnewstudent', 'local_ouadmin'),
        ],
        'course' => [
            'tabletitle' => get_string('coursetabletitle', 'local_ouadmin'),
            'badge'      => '0 ' . get_string('totalcourses', 'local_ouadmin'),
            'addlabel'   => get_string('addnewcourse', 'local_ouadmin'),
        ],
    ];

    if (!isset($config[$tab])) {
        $tab = 'faculty';
    }
    $cfg = $config[$tab];

    // Build table data per tab.
    $columns = [];
    $rows    = [];

    // ===== FACULTY TAB =====
    if ($tab === 'faculty') {
        $columns = [
            '#',
            get_string('facultyname', 'local_ouadmin'),
            get_string('facultycode', 'local_ouadmin'),
            get_string('facultydean', 'local_ouadmin'),
            get_string('facultyactive', 'local_ouadmin'),
            get_string('actions', 'local_ouadmin')
        ];

        $records = $DB->get_records('local_ouadmin_faculty', ['ouid' => $ouid], 'name ASC');
        $i = 1;
        foreach ($records as $rec) {
            $viewurl = (new moodle_url('/local/ouadmin/faculty_view.php',
                ['id' => $rec->id, 'ouid' => $ouid]))->out(false);
            $editurl = (new moodle_url('/local/ouadmin/faculty_edit.php',
                ['id' => $rec->id, 'ouid' => $ouid]))->out(false);
            $delurl  = (new moodle_url('/local/ouadmin/faculty_delete.php',
                ['id' => $rec->id, 'ouid' => $ouid, 'sesskey' => sesskey()]))->out(false);

            $activebadge = $rec->active
                ? '<span class="badge bg-success">' . get_string('yes') . '</span>'
                : '<span class="badge bg-secondary">' . get_string('no') . '</span>';

            $deanname = '';
            if (!empty($rec->deanfirstname) || !empty($rec->deanlastname)) {
                $deanname = trim($rec->deanfirstname . ' ' . $rec->deanlastname);
            }

            $actions = '<div class="btn-group" role="group">'
                . '<a href="' . $viewurl . '" class="btn btn-sm btn-primary">'
                . '<i class="fa fa-eye me-1"></i>' . get_string('view', 'local_ouadmin') . '</a>'
                . '<a href="' . $editurl . '" class="btn btn-sm" '
                . 'style="background-color:#FFC107;border-color:#FFC107;color:#000;">'
                . '<i class="fa fa-pencil-alt me-1"></i>' . get_string('edit', 'local_ouadmin') . '</a>'
                . '<a href="' . $delurl . '" class="btn btn-sm" '
                . 'style="background-color:#DC3545;border-color:#DC3545;color:#fff;" '
                . 'onclick="return confirm(\'' . get_string('delete', 'local_ouadmin') . '?\');">'
                . '<i class="fa fa-trash me-1"></i>' . get_string('delete', 'local_ouadmin') . '</a>'
                . '</div>';

            $rows[] = [
                'cells' => [
                    ['value' => $i++],
                    ['value' => format_string($rec->name)],
                    ['value' => s($rec->code)],
                    ['value' => s($deanname)],
                    ['value' => $activebadge],
                    ['value' => $actions],
                ],
            ];
        }

    // ===== INTAKE TAB =====
    } else if ($tab === 'intake') {
        $columns = [
            '#',
            get_string('intakename', 'local_ouadmin'),
            get_string('intakecode', 'local_ouadmin'),
            get_string('facultyname', 'local_ouadmin'),
            get_string('startdate', 'local_ouadmin'),
            get_string('enddate', 'local_ouadmin'),
            get_string('status', 'local_ouadmin'),
            get_string('actions', 'local_ouadmin')
        ];

        $sql = "SELECT i.*, f.name AS facultyname
                  FROM {local_ouadmin_intake} i
                  JOIN {local_ouadmin_faculty} f ON f.id = i.facultyid
                 WHERE i.ouid = :ouid
              ORDER BY i.startdate DESC, i.name ASC";
        $records = $DB->get_records_sql($sql, ['ouid' => $ouid]);

        $i = 1;
        foreach ($records as $rec) {
            $viewurl = (new moodle_url('/local/ouadmin/intake_view.php',
                ['id' => $rec->id, 'ouid' => $ouid]))->out(false);
            $editurl = (new moodle_url('/local/ouadmin/intake_edit.php',
                ['id' => $rec->id, 'ouid' => $ouid]))->out(false);
            $delurl  = (new moodle_url('/local/ouadmin/intake_delete.php',
                ['id' => $rec->id, 'ouid' => $ouid, 'sesskey' => sesskey()]))->out(false);

            $actions = '<div class="btn-group" role="group">'
                . '<a href="' . $viewurl . '" class="btn btn-sm btn-primary">'
                . '<i class="fa fa-eye me-1"></i>' . get_string('view', 'local_ouadmin') . '</a>'
                . '<a href="' . $editurl . '" class="btn btn-sm" '
                . 'style="background-color:#FFC107;border-color:#FFC107;color:#000;">'
                . '<i class="fa fa-pencil-alt me-1"></i>' . get_string('edit', 'local_ouadmin') . '</a>'
                . '<a href="' . $delurl . '" class="btn btn-sm" '
                . 'style="background-color:#DC3545;border-color:#DC3545;color:#fff;" '
                . 'onclick="return confirm(\'' . get_string('delete', 'local_ouadmin') . '?\');">'
                . '<i class="fa fa-trash me-1"></i>' . get_string('delete', 'local_ouadmin') . '</a>'
                . '</div>';

            $rows[] = [
                'cells' => [
                    ['value' => $i++],
                    ['value' => format_string($rec->name)],
                    ['value' => s($rec->code)],
                    ['value' => format_string($rec->facultyname)],
                    ['value' => $rec->startdate ? userdate($rec->startdate) : '-'],
                    ['value' => $rec->enddate ? userdate($rec->enddate) : '-'],
                    ['value' => s($rec->status)],
                    ['value' => $actions],
                ],
            ];
        }

    // ===== OTHER TABS STILL DUMMY =====
    } else if ($tab === 'teacher') {
         $columns = [
            '#',
            get_string('firstname'),
            get_string('lastname'),
            get_string('email'),
            get_string('facultyname', 'local_ouadmin'),
            get_string('teacherdepartment', 'local_ouadmin'),
            get_string('actions', 'local_ouadmin')
        ];
    
        $sql = "SELECT t.*, f.name AS facultyname
                  FROM {local_ouadmin_teacher} t
             LEFT JOIN {local_ouadmin_faculty} f ON f.id = t.facultyid
                 WHERE t.ouid = :ouid
              ORDER BY t.lastname ASC, t.firstname ASC";
        $records = $DB->get_records_sql($sql, ['ouid' => $ouid]);
    
        $i = 1;
        foreach ($records as $rec) {
            $viewurl = (new moodle_url('/local/ouadmin/teacher_view.php',
                ['id' => $rec->id, 'ouid' => $ouid]))->out(false);
            $editurl = (new moodle_url('/local/ouadmin/teacher_edit.php',
                ['id' => $rec->id, 'ouid' => $ouid]))->out(false);
            $delurl  = (new moodle_url('/local/ouadmin/teacher_delete.php',
                ['id' => $rec->id, 'ouid' => $ouid, 'sesskey' => sesskey()]))->out(false);
    
            $actions = '<div class="btn-group" role="group">'
                . '<a href="' . $viewurl . '" class="btn btn-sm btn-primary">'
                . '<i class="fa fa-eye me-1"></i>' . get_string('view', 'local_ouadmin') . '</a>'
                . '<a href="' . $editurl . '" class="btn btn-sm" '
                . 'style="background-color:#FFC107;border-color:#FFC107;color:#000;">'
                . '<i class="fa fa-pencil-alt me-1"></i>' . get_string('edit', 'local_ouadmin') . '</a>'
                . '<a href="' . $delurl . '" class="btn btn-sm" '
                . 'style="background-color:#DC3545;border-color:#DC3545;color:#fff;" '
                . 'onclick="return confirm(\'' . get_string('delete', 'local_ouadmin') . '?\');">'
                . '<i class="fa fa-trash me-1"></i>' . get_string('delete', 'local_ouadmin') . '</a>'
                . '</div>';
    
            $rows[] = [
                'cells' => [
                    ['value' => $i++],
                    ['value' => format_string($rec->firstname)],
                    ['value' => format_string($rec->lastname)],
                    ['value' => s($rec->email)],
                    ['value' => $rec->facultyname ? format_string($rec->facultyname) : '-'],
                    ['value' => s($rec->department)],
                    ['value' => $actions],
                ],
            ];
        }
    } else if ($tab === 'student') {
        $columns = ['#', 'Name', 'Matric no.', 'Intake', 'Programme', get_string('actions', 'local_ouadmin')];
        // ... keep your dummy student rows or leave empty.
    } else if ($tab === 'course') {
        $columns = ['#', 'Course code', 'Course name', 'Credits', 'Teacher', get_string('actions', 'local_ouadmin')];
        // ... keep your dummy course rows or leave empty.
    }

    $hasrows = !empty($rows);

    // OU options for selector (site admin only).
    $ouoptions = [];
    foreach ($ous as $id => $name) {
        $ouoptions[] = [
            'id'       => $id,
            'name'     => $name,
            'selected' => ((int)$id === (int)$ouid),
        ];
    }

    // Decide add URL based on current tab.
    $addurl = '#';
    if ($tab === 'faculty') {
        $addurl = (new moodle_url('/local/ouadmin/faculty_edit.php', ['ouid' => $ouid]))->out(false);
    } else if ($tab === 'intake') {
        $addurl = (new moodle_url('/local/ouadmin/intake_edit.php', ['ouid' => $ouid]))->out(false);
    } else if ($tab === 'teacher') {
        $addurl = (new moodle_url('/local/ouadmin/teacher_edit.php', ['ouid' => $ouid]))->out(false);
    }

    return [
        'pagetitle'   => get_string('dashboardtitle', 'local_ouadmin'),
        'ouname'      => $ouname,
        'issiteadmin' => $isadmin,
        'currenttab'  => $tab,
        'ouoptions'   => $ouoptions,
        'selectou'    => get_string('selectou', 'local_ouadmin'),
        'labels'      => [
            'faculties' => get_string('totalfaculties', 'local_ouadmin'),
            'intakes'   => get_string('totalintakes', 'local_ouadmin'),
            'teachers'  => get_string('totalteachers', 'local_ouadmin'),
            'students'  => get_string('totalstudents', 'local_ouadmin'),
        ],
        'summary'   => [
            'title'          => get_string('summarytitle', 'local_ouadmin'),
            'subtitle'       => get_string('summarysubtitle', 'local_ouadmin'),
            'ouname'         => $ouname,
            'totalfaculties' => $kpis['faculties'],
            'totalintakes'   => $kpis['intakes'],
            'totalteachers'  => $kpis['teachers'],
            'totalstudents'  => $kpis['students'],
        ],
        'quickactions' => [
            'title'        => get_string('quickactions', 'local_ouadmin'),
            'description'  => get_string('quickactions_desc', 'local_ouadmin'),
            'addlabel'     => $cfg['addlabel'],
            'addurl'       => $addurl,
            'refreshlabel' => get_string('refresh', 'local_ouadmin'),
            'refreshurl'   => $currenturl,
        ],
        'tabs' => $tabs,
        'table' => [
            'title'   => $cfg['tabletitle'],
            'badge'   => $cfg['badge'],
            'columns' => $columns,
            'rows'    => $rows,
            'hasrows' => $hasrows,
            'colspan' => count($columns),
        ],
        'no_records' => get_string('no_records', 'local_ouadmin'),
    ];
}
