<?php
// Dashboard for local_organization.

require('../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/organization:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/organization/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_organization'));
$PAGE->set_heading(get_string('pluginname', 'local_organization'));

echo $OUTPUT->header();

// --- External CSS & JS for DataTables (loaded via raw HTML, not $PAGE->requires) ---
echo '
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css" />
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css" />

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
';

// --- Render dashboard template ---
$dashboard = new \local_organization\output\dashboard();
$data = $dashboard->export_for_template($OUTPUT);
echo $OUTPUT->render_from_template('local_organization/dashboard', $data);

// --- Init DataTables ---
echo "
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery !== 'undefined' && jQuery('#orgtable').length) {
        var \$ = jQuery;
        \$('#orgtable').DataTable({
            dom: 'Bfrtip',
            buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
            pageLength: 25
        });
    }
});
</script>
";

echo $OUTPUT->footer();
