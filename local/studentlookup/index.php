<?php
require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/studentlookup:view', $context);

$tab = optional_param('tab', 'studenttype', PARAM_ALPHA);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/studentlookup/index.php', ['tab' => $tab]));
$PAGE->set_title(get_string('studentlookup', 'local_studentlookup'));
$PAGE->set_heading(get_string('studentlookup', 'local_studentlookup'));

echo $OUTPUT->header();

//
// --- CUSTOM CSS (similar look to Organization page) ---
//
$customcss = "
.studentlookup-page-title {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
}
.studentlookup-maincard {
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.04);
}
.studentlookup-tablecard {
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.04);
}
.studentlookup-bignumber {
    font-size: 2.75rem;
    font-weight: 700;
}
.studentlookup-subtitle {
    font-size: 0.875rem;
    color: #6c757d;
}
.studentlookup-badge-pill {
    border-radius: 999px;
}
.studentlookup-actions .btn {
    min-width: 70px;
}
";
echo html_writer::tag('style', $customcss);

//
// --- DATATABLES CSS/JS (copy / csv / print buttons) ---
//
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
    $('.studentlookup-datatable').DataTable({
        dom: 'Bfrtip',
        pageLength: 25,
        buttons: [
            { extend: 'copy',  title: 'Student Lookup' },
            { extend: 'csv',   title: 'Student Lookup' },
            { extend: 'print', title: 'Student Lookup' }
        ]
    });
});
JS;
echo html_writer::tag('script', $datatableinit);

//
// --- RENDER USING MUSTACHE TEMPLATE ---
//
$templatecontext = local_studentlookup_build_dashboard_context($tab);
echo $OUTPUT->render_from_template('local_studentlookup/dashboard', $templatecontext);

echo $OUTPUT->footer();

/**
 * Build the Mustache context for the student lookup dashboard.
 *
 * @param string $tab
 * @return array
 */
function local_studentlookup_build_dashboard_context(string $tab): array {
    global $DB, $PAGE;

    $currenturl = $PAGE->url->out(false);

    // Tab configuration (titles, labels, etc).
    $config = [
        'studenttype' => [
            'cardtitle'      => 'Student types',
            'cardsubtitle'   => 'Total registered student types',
            'tabletitle'     => 'Student Types',
            'unitlabel'      => 'types',
            'addlabel'       => get_string('addnewstudenttype', 'local_studentlookup'),
            'addurl'         => new moodle_url('/local/studentlookup/type_edit.php'),
        ],
        'service' => [
            'cardtitle'      => 'Services',
            'cardsubtitle'   => 'Total registered services',
            'tabletitle'     => 'Services',
            'unitlabel'      => 'services',
            'addlabel'       => get_string('addnewservice', 'local_studentlookup'),
            'addurl'         => new moodle_url('/local/studentlookup/service_edit.php'),
        ],
        'korregimen' => [
            'cardtitle'      => 'Kor / Regiments',
            'cardsubtitle'   => 'Total registered kor / regiments',
            'tabletitle'     => 'Kor / Regiments',
            'unitlabel'      => 'kor / regiments',
            'addlabel'       => get_string('addnewkorregimen', 'local_studentlookup'),
            'addurl'         => new moodle_url('/local/studentlookup/korregimen_edit.php'),
        ],
        'rank' => [
            'cardtitle'      => 'Ranks',
            'cardsubtitle'   => 'Total registered ranks',
            'tabletitle'     => 'Ranks',
            'unitlabel'      => 'ranks',
            'addlabel'       => get_string('addnewrank', 'local_studentlookup'),
            'addurl'         => new moodle_url('/local/studentlookup/rank_edit.php'),
        ],
    ];

    if (!isset($config[$tab])) {
        $tab = 'studenttype';
    }
    $cfg = $config[$tab];

    // Tabs navigation.
    $tabs = [
        [
            'key'    => 'studenttype',
            'label'  => get_string('tab_studenttype', 'local_studentlookup'),
            'url'    => (new moodle_url('/local/studentlookup/index.php', ['tab' => 'studenttype']))->out(false),
            'active' => $tab === 'studenttype',
        ],
        [
            'key'    => 'service',
            'label'  => get_string('tab_service', 'local_studentlookup'),
            'url'    => (new moodle_url('/local/studentlookup/index.php', ['tab' => 'service']))->out(false),
            'active' => $tab === 'service',
        ],
        [
            'key'    => 'korregimen',
            'label'  => get_string('tab_korregimen', 'local_studentlookup'),
            'url'    => (new moodle_url('/local/studentlookup/index.php', ['tab' => 'korregimen']))->out(false),
            'active' => $tab === 'korregimen',
        ],
        [
            'key'    => 'rank',
            'label'  => get_string('tab_rank', 'local_studentlookup'),
            'url'    => (new moodle_url('/local/studentlookup/index.php', ['tab' => 'rank']))->out(false),
            'active' => $tab === 'rank',
        ],
    ];

    // Build table data per tab.
    switch ($tab) {
        case 'service':
            $total   = $DB->count_records('local_studentlookup_service');
            $records = $DB->get_records('local_studentlookup_service', null, 'sortorder ASC, name ASC');
            $types   = $DB->get_records_menu('local_studentlookup_type', null, 'name ASC', 'id,name');

            $rows = [];
            $i = 1;
            foreach ($records as $rec) {
                $typename = (!empty($rec->studenttypeid) && isset($types[$rec->studenttypeid]))
                    ? format_string($types[$rec->studenttypeid]) : '-';

                $activebadge = $rec->active
                    ? '<span class="badge bg-success">' . get_string('yes') . '</span>'
                    : '<span class="badge bg-secondary">' . get_string('no') . '</span>';

                $editurl   = (new moodle_url('/local/studentlookup/service_edit.php', ['id' => $rec->id]))->out(false);
                $deleteurl = (new moodle_url('/local/studentlookup/service_delete.php', [
                    'id' => $rec->id, 'sesskey' => sesskey()
                ]))->out(false);

                $actions = '<div class="btn-group" role="group">'
                    . '<a href="' . $editurl . '" class="btn btn-sm" '
                    . 'style="background-color:#FFC107;border-color:#FFC107;color:#000;">'
                    . '<i class="fa fa-pencil-alt me-1"></i>' . get_string('edit') . '</a>'
                    . '<a href="' . $deleteurl . '" class="btn btn-sm" '
                    . 'style="background-color:#DC3545;border-color:#DC3545;color:#fff;" '
                    . 'onclick="return confirm(\'' . get_string('confirmdelete', 'local_studentlookup') . '\');">'
                    . '<i class="fa fa-trash me-1"></i>' . get_string('delete') . '</a>'
                    . '</div>';

                $rows[] = [
                    'cells' => [
                        ['value' => $i++],
                        ['value' => format_string($rec->name)],
                        ['value' => s($rec->code)],
                        ['value' => $typename],
                        ['value' => $activebadge],
                        ['value' => (int)$rec->sortorder],
                        ['value' => $actions],
                    ],
                ];
            }

            $columns = [
                '#',
                get_string('servicename', 'local_studentlookup'),
                get_string('servicecode', 'local_studentlookup'),
                get_string('studenttype', 'local_studentlookup'),
                get_string('active', 'local_studentlookup'),
                get_string('sortorder', 'local_studentlookup'),
                get_string('actions'),
            ];
            break;

        case 'korregimen':
            $total    = $DB->count_records('local_studentlookup_korregimen');
            $records  = $DB->get_records('local_studentlookup_korregimen', null, 'sortorder ASC, name ASC');
            $services = $DB->get_records_menu('local_studentlookup_service', null, 'name ASC', 'id,name');

            $rows = [];
            $i = 1;
            foreach ($records as $rec) {
                $servicename = (!empty($rec->serviceid) && isset($services[$rec->serviceid]))
                    ? format_string($services[$rec->serviceid]) : '-';

                $activebadge = $rec->active
                    ? '<span class="badge bg-success">' . get_string('yes') . '</span>'
                    : '<span class="badge bg-secondary">' . get_string('no') . '</span>';

                $editurl   = (new moodle_url('/local/studentlookup/korregimen_edit.php', ['id' => $rec->id]))->out(false);
                $deleteurl = (new moodle_url('/local/studentlookup/korregimen_delete.php', [
                    'id' => $rec->id, 'sesskey' => sesskey()
                ]))->out(false);

                $actions = '<div class="btn-group" role="group">'
                    . '<a href="' . $editurl . '" class="btn btn-sm" '
                    . 'style="background-color:#FFC107;border-color:#FFC107;color:#000;">'
                    . '<i class="fa fa-pencil-alt me-1"></i>' . get_string('edit') . '</a>'
                    . '<a href="' . $deleteurl . '" class="btn btn-sm" '
                    . 'style="background-color:#DC3545;border-color:#DC3545;color:#fff;" '
                    . 'onclick="return confirm(\'' . get_string('confirmdelete', 'local_studentlookup') . '\');">'
                    . '<i class="fa fa-trash me-1"></i>' . get_string('delete') . '</a>'
                    . '</div>';

                $rows[] = [
                    'cells' => [
                        ['value' => $i++],
                        ['value' => format_string($rec->name)],
                        ['value' => s($rec->code)],
                        ['value' => $servicename],
                        ['value' => $activebadge],
                        ['value' => (int)$rec->sortorder],
                        ['value' => $actions],
                    ],
                ];
            }

            $columns = [
                '#',
                get_string('korregimenname', 'local_studentlookup'),
                get_string('korregimencode', 'local_studentlookup'),
                get_string('servicename', 'local_studentlookup'),
                get_string('active', 'local_studentlookup'),
                get_string('sortorder', 'local_studentlookup'),
                get_string('actions'),
            ];
            break;

        case 'rank':
            $total    = $DB->count_records('local_studentlookup_rank');
            $records  = $DB->get_records('local_studentlookup_rank', null, 'ranklevel ASC, name ASC');
            $services = $DB->get_records_menu('local_studentlookup_service', null, 'name ASC', 'id,name');

            $rows = [];
            $i = 1;
            foreach ($records as $rec) {
                $servicename = (!empty($rec->serviceid) && isset($services[$rec->serviceid]))
                    ? format_string($services[$rec->serviceid]) : '-';

                $activebadge = $rec->active
                    ? '<span class="badge bg-success">' . get_string('yes') . '</span>'
                    : '<span class="badge bg-secondary">' . get_string('no') . '</span>';

                $editurl   = (new moodle_url('/local/studentlookup/rank_edit.php', ['id' => $rec->id]))->out(false);
                $deleteurl = (new moodle_url('/local/studentlookup/rank_delete.php', [
                    'id' => $rec->id, 'sesskey' => sesskey()
                ]))->out(false);

                $actions = '<div class="btn-group" role="group">'
                    . '<a href="' . $editurl . '" class="btn btn-sm" '
                    . 'style="background-color:#FFC107;border-color:#FFC107;color:#000;">'
                    . '<i class="fa fa-pencil-alt me-1"></i>' . get_string('edit') . '</a>'
                    . '<a href="' . $deleteurl . '" class="btn btn-sm" '
                    . 'style="background-color:#DC3545;border-color:#DC3545;color:#fff;" '
                    . 'onclick="return confirm(\'' . get_string('confirmdelete', 'local_studentlookup') . '\');">'
                    . '<i class="fa fa-trash me-1"></i>' . get_string('delete') . '</a>'
                    . '</div>';

                $rows[] = [
                    'cells' => [
                        ['value' => $i++],
                        ['value' => format_string($rec->name)],
                        ['value' => s($rec->shortname)],
                        ['value' => $servicename],
                        ['value' => (int)$rec->ranklevel],
                        ['value' => $activebadge],
                        ['value' => $actions],
                    ],
                ];
            }

            $columns = [
                '#',
                get_string('rankname', 'local_studentlookup'),
                get_string('rankshortname', 'local_studentlookup'),
                get_string('servicename', 'local_studentlookup'),
                get_string('ranklevel', 'local_studentlookup'),
                get_string('active', 'local_studentlookup'),
                get_string('actions'),
            ];
            break;

        case 'studenttype':
        default:
            $total   = $DB->count_records('local_studentlookup_type');
            $records = $DB->get_records('local_studentlookup_type', null, 'sortorder ASC, name ASC');

            $rows = [];
            $i = 1;
            foreach ($records as $rec) {
                $activebadge = $rec->active
                    ? '<span class="badge bg-success">' . get_string('yes') . '</span>'
                    : '<span class="badge bg-secondary">' . get_string('no') . '</span>';

                $editurl   = (new moodle_url('/local/studentlookup/type_edit.php', ['id' => $rec->id]))->out(false);
                $deleteurl = (new moodle_url('/local/studentlookup/type_delete.php', [
                    'id' => $rec->id, 'sesskey' => sesskey()
                ]))->out(false);

                $actions = '<div class="btn-group" role="group">'
                    . '<a href="' . $editurl . '" class="btn btn-sm" '
                    . 'style="background-color:#FFC107;border-color:#FFC107;color:#000;">'
                    . '<i class="fa fa-pencil-alt me-1"></i>' . get_string('edit') . '</a>'
                    . '<a href="' . $deleteurl . '" class="btn btn-sm" '
                    . 'style="background-color:#DC3545;border-color:#DC3545;color:#fff;" '
                    . 'onclick="return confirm(\'' . get_string('confirmdelete', 'local_studentlookup') . '\');">'
                    . '<i class="fa fa-trash me-1"></i>' . get_string('delete') . '</a>'
                    . '</div>';

                $rows[] = [
                    'cells' => [
                        ['value' => $i++],
                        ['value' => format_string($rec->name)],
                        ['value' => s($rec->code)],
                        ['value' => $activebadge],
                        ['value' => (int)$rec->sortorder],
                        ['value' => $actions],
                    ],
                ];
            }

            $columns = [
                '#',
                get_string('studenttypename', 'local_studentlookup'),
                get_string('studenttypecode', 'local_studentlookup'),
                get_string('active', 'local_studentlookup'),
                get_string('sortorder', 'local_studentlookup'),
                get_string('actions'),
            ];
            break;
    }

    $hasrows = !empty($rows);

    return [
        'pagetitle' => get_string('studentlookup', 'local_studentlookup'),
        'tabs'      => $tabs,
        'card'      => [
            'title'    => $cfg['cardtitle'],
            'value'    => (int)$total,
            'subtitle' => $cfg['cardsubtitle'],
        ],
        'quickactions' => [
            'title'        => 'Quick actions',
            'description'  => 'Add new records and manage existing structures.',
            'addlabel'     => $cfg['addlabel'],
            'addurl'       => $cfg['addurl']->out(false),
            'refreshlabel' => 'Refresh',
            'refreshurl'   => $currenturl,
        ],
        'table' => [
            'title'   => $cfg['tabletitle'],
            'badge'   => $total . ' ' . $cfg['unitlabel'],
            'columns' => $columns,
            'rows'    => $rows,
            'hasrows' => $hasrows,
            'colspan' => count($columns),
        ],
    ];
}
