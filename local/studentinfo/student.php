<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/studentinfo/forms/student_search_form.php');

// Guard-load bridge so OU utils are available even if caches are stale.
if (!class_exists('\\local_studentinfo\\local\\orgstructure_bridge')) {
    require_once($CFG->dirroot.'/local/studentinfo/classes/local/orgstructure_bridge.php');
}

require_login();

$context = context_system::instance();
/** Site admin bypass; others must have view cap */
if (!is_siteadmin()) {
    require_capability('local/studentinfo:view', $context);
}

$ouid = optional_param('ou', 0, PARAM_INT);

// Page chrome
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/studentinfo/student.php', ['ou' => $ouid]));
$PAGE->set_title(get_string('student', 'local_studentinfo'));
$PAGE->set_heading(get_string('pluginname', 'local_studentinfo'));
$PAGE->requires->css('/local/studentinfo/style.css'); // your CSS

global $DB;

/* ==========================================================
   AJAX: Select2 user search (OU-scoped)
   ========================================================== */
$ajax = optional_param('ajax', '', PARAM_ALPHA);
if ($ajax === 'usersearch' || $ajax === 'userbyid') {
    @define('NO_DEBUG_DISPLAY', true);
    while (ob_get_level() > 0) { @ob_end_clean(); }

    $sess = optional_param('sesskey', '', PARAM_ALPHANUM);
    header('Content-Type: application/json; charset=utf-8');

    if (!$sess || !confirm_sesskey($sess)) {
        http_response_code(403);
        echo json_encode(['results'=>[], 'more'=>false, 'error'=>'invalid_sesskey']); exit;
    }

    if ($ajax === 'userbyid') {
        $userid = required_param('userid', PARAM_INT);
        $rec = $DB->get_record_sql("
            SELECT u.id,
                   CONCAT(u.firstname,' ',u.lastname) AS fullname,
                   s.tentera_no
              FROM {user} u
         LEFT JOIN {local_studentinfo} s ON s.userid = u.id
             WHERE u.deleted = 0 AND u.id = :u
        ", ['u'=>$userid]);

        if (!$rec) { echo json_encode(null); exit; }

        $label = trim($rec->fullname);
        if (!empty($rec->tentera_no)) { $label .= " ({$rec->tentera_no})"; }
        echo json_encode(['id'=>(int)$rec->id, 'text'=>$label]); exit;
    }

    // ajax === usersearch
    $q     = optional_param('q', '', PARAM_RAW_TRIMMED);
    $page  = max(1, (int)optional_param('page', 1, PARAM_INT));
    $limit = 20; $offset= ($page-1) * $limit;

    $where  = ["u.deleted = 0"];
    $params = [];

    if ($ouid) {
        $where[] = "EXISTS (SELECT 1 FROM {local_org_member} om WHERE om.userid = u.id AND om.orgunitid = :ou)";
        $params['ou'] = $ouid;
    }
    if ($q !== '') {
        $like = "%{$q}%";
        $where[] = "("
                 . $DB->sql_like('u.firstname', ':q1', false)
                 . " OR " . $DB->sql_like('u.lastname', ':q2', false)
                 . " OR " . $DB->sql_like($DB->sql_concat('u.firstname', "' '", 'u.lastname'), ':q3', false)
                 . " OR " . $DB->sql_like('s.tentera_no', ':q4', false)
                 . ")";
        $params['q1']=$like; $params['q2']=$like; $params['q3']=$like; $params['q4']=$like;
    }

    $sql = "
        SELECT u.id,
               CONCAT(u.firstname,' ',u.lastname) AS fullname,
               s.tentera_no
          FROM {user} u
     LEFT JOIN {local_studentinfo} s ON s.userid = u.id
         " . ( $where ? " WHERE ".implode(' AND ',$where) : '' ) . "
         ORDER BY u.firstname, u.lastname, u.id
    ";

    $rows = $DB->get_records_sql($sql, $params, $offset, $limit+1);
    $more = count($rows) > $limit;
    if ($more) { array_pop($rows); }

    $results = [];
    foreach ($rows as $r) {
        $label = trim($r->fullname);
        if (!empty($r->tentera_no)) { $label .= " ({$r->tentera_no})"; }
        $results[] = ['id'=>(int)$r->id, 'text'=>$label];
    }

    echo json_encode(['results'=>$results, 'more'=>$more]); exit;
}

/* =========================
   Normal page
   ========================= */
echo $OUTPUT->header();

/* OU banner only (non-dismissible). No OU selector here. */
echo \local_studentinfo\local\orgstructure_bridge::ou_banner($ouid, 'Scope');

/* Select2 assets (CDN) */
echo '
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
/* Print: hide Moodle chrome & non-info; show only the .si-print-area */
@media print {
  #page-header, #page-footer, .navbar, nav, .drawer, .drawer-toggler, .drawer-toggles,
  .secondary-navigation, .footer-popover, .breadcrumb, form, .select2-container, .alert,
  .btn, .btn-print, .input-group, .lib-oubar { display:none !important; }
  .si-print-area { margin:0 !important; padding:0 !important; }
  body { background:#fff !important; }
  .card { box-shadow:none !important; border:1px solid #ddd !important; }
}
/* Small UI polish */
.si-toolbar { display:flex; gap:8px; justify-content:flex-end; margin:8px 0 12px; }
</style>
';

$mform = new student_search_form();
$mform->display();

/* Init Select2 + write to hidden userid */
$prefill = optional_param('userid', 0, PARAM_INT);
echo "
<script>
  (function(){
    var \$sel = jQuery('#useridselect');
    if (!\$sel.length) return;

    \$sel.select2({
      placeholder: 'Cari pelajar… (nama / tentera no)',
      allowClear: true,
      ajax: {
        url: '".s($PAGE->url->out(false))."',
        dataType: 'json',
        delay: 250,
        cache: true,
        data: function (params) {
          return {
            ajax: 'usersearch',
            q: params.term || '',
            page: params.page || 1,
            ou: ".(int)$ouid.",
            sesskey: '".sesskey()."'
          };
        },
        processResults: function (data, params) {
          params.page = params.page || 1;
          return { results: (data && data.results) ? data.results : [], pagination: { more: !!(data && data.more) } };
        }
      },
      minimumInputLength: 1,
      width: 'resolve'
    });

    // Hook to hidden field
    \$sel.on('select2:select', function(e){
      var id = e && e.params && e.params.data ? e.params.data.id : null;
      jQuery('input[name=\"userid\"]').val(id || 0);
    });
    \$sel.on('select2:clear', function(){ jQuery('input[name=\"userid\"]').val(0); });

    // Prefill on reload
    var prefillId = ".(int)$prefill.";
    if (prefillId > 0) {
      jQuery.ajax({
        url: '".s($PAGE->url->out(false))."',
        dataType: 'json',
        data: { ajax:'userbyid', userid: prefillId, ou: ".(int)$ouid.", sesskey: '".sesskey()."' }
      }).then(function(item){
        if (item && item.id) {
          var option = new Option(item.text, item.id, true, true);
          \$sel.append(option).trigger('change');
          jQuery('input[name=\"userid\"]').val(item.id);
        }
      });
    }

    // Print
    jQuery(document).on('click', '#btn-print', function(e){ e.preventDefault(); window.print(); });
  })();
</script>
";

/* If submitted, show summary + fees + detailed sections in a professional grid */
if ($data = $mform->get_data()) {
    $userid = (int)$data->userid;

    if ($ouid && !\local_studentinfo\local\orgstructure_bridge::user_in_ou($userid, $ouid) && !is_siteadmin()) {
        echo html_writer::div('Selected user is not in the chosen OU.', 'alert alert-warning');
        echo $OUTPUT->footer(); exit;
    }

    // Summary row (programme/status/cgpa/balance/attendance/cases)
    $summary = $DB->get_record_sql(
        "SELECT * FROM {v_studentinfo_student_summary} WHERE userid = :u",
        ['u' => $userid]
    );

    if (!$summary) {
        echo $OUTPUT->notification('No student summary found.', 'warning');
        echo $OUTPUT->footer(); exit;
    }

    // Load detailed info (from your view.php)
    $userrec = $DB->get_record('user', ['id'=>$userid, 'deleted'=>0], '*', MUST_EXIST);
    $rec     = $DB->get_record('local_studentinfo', ['userid'=>$userid]);

    echo html_writer::start_div('si-print-area');

    // Header + summary table card
    $name = fullname((object)['firstname' => $summary->firstname, 'lastname' => $summary->lastname]);
    echo html_writer::start_div('card mb-3');
    echo html_writer::div(s($name) . ' (ID '.(int)$summary->userid.')', 'card-header fw-bold');
    echo html_writer::start_div('card-body');
    $info = new html_table();
    $info->head = ['Programme', 'Status', 'GPA', 'CGPA', 'Balance', 'Attendance', 'Open cases'];

    // Programme: code – name
    $programme = '-';
    if (!empty($summary->programme_code)) {
        $programme = $summary->programme_code;
        if (!empty($summary->programme_name)) { $programme .= ' – ' . $summary->programme_name; }
    }

    $info->data[] = [
        format_string($programme),
        format_string($summary->status ?? '-'),
        is_null($summary->gpa) ? '-' : format_float($summary->gpa, 2),
        is_null($summary->cgpa) ? '-' : format_float($summary->cgpa, 2),
        is_null($summary->balance) ? '-' : format_float($summary->balance, 2),
        is_null($summary->avg_attendance) ? '-' : format_float($summary->avg_attendance, 2) . '%',
        is_null($summary->opencases) ? 0 : (int)$summary->opencases
    ];
    echo html_writer::table($info);
    echo html_writer::end_div(); // card-body
    echo html_writer::end_div(); // card

    // Recent fees (top 10) card
    $fees = $DB->get_records_sql("
        SELECT * FROM {local_studentinfo_fee}
        WHERE userid = :u
        ORDER BY duedate DESC, id DESC
        LIMIT 10
    ", ['u' => $userid]);

    echo html_writer::start_div('card mb-3');
    echo html_writer::div('Recent fees', 'card-header');
    echo html_writer::start_div('card-body');
    $ft = new html_table();
    $ft->head = ['Item', 'Amount', 'Paid', 'Due date', 'Status', 'Receipt', 'Method'];
    foreach ($fees as $f) {
        $ft->data[] = [
            format_string($f->itemname),
            format_float($f->amount, 2),
            format_float($f->paid_amount ?? 0, 2),
            $f->duedate ?? '-',
            s($f->status),
            s($f->receiptno ?? ''),
            s($f->method ?? '')
        ];
    }
    echo html_writer::table($ft);
    echo html_writer::end_div(); // card-body
    echo html_writer::end_div(); // card

    // If no studentinfo profile exists, show notice only (no Edit button per request)
    if (!$rec) {
        echo $OUTPUT->notification('No student info yet.','info');
        echo html_writer::end_div(); // .si-print-area
        echo $OUTPUT->footer(); exit;
    }

    // Pull detail datasets
    $academics = $DB->get_records('local_student_academic', ['studentid'=>$rec->id], 'tahun ASC');
    $languages = $DB->get_records('local_student_language', ['studentid'=>$rec->id]);
    $families  = $DB->get_records('local_student_family', ['studentid'=>$rec->id]);
    $courses   = $DB->get_records('local_student_course', ['studentid'=>$rec->id], 'mula ASC');
    $ranks     = $DB->get_records('local_student_rank', ['studentid'=>$rec->id], 'tarikh ASC');
    $postings  = $DB->get_records('local_student_posting', ['studentid'=>$rec->id], 'mula ASC');
    $awards    = $DB->get_records('local_student_award', ['studentid'=>$rec->id], 'tarikh ASC');
    $ins       = $DB->get_records('local_student_insurance', ['studentid'=>$rec->id], 'id ASC');

    // Identity / header card (full width)
    echo html_writer::start_div('card mb-3');
    echo html_writer::div('<strong>'.s(fullname($userrec)).'</strong> — '.s($rec->pangkat ?? ''), 'card-header');
    echo html_writer::start_div('card-body');
    echo html_writer::start_div('row');
    echo html_writer::start_div('col-md-6');
    echo '<p><b>'.get_string('tentera_no','local_studentinfo').':</b> '.s($rec->tentera_no ?? '').'</p>';
    echo '<p><b>'.get_string('perkhidmatan','local_studentinfo').':</b> '.s($rec->perkhidmatan ?? '').'</p>';
    echo '<p><b>'.get_string('rejimen','local_studentinfo').':</b> '.s($rec->rejimen ?? '').'</p>';
    echo '<p><b>'.get_string('pengambilan','local_studentinfo').':</b> '.s($rec->pengambilan ?? '').'</p>';
    echo html_writer::end_div();
    echo html_writer::start_div('col-md-6');
    echo '<p><b>'.get_string('tarikh_lahir','local_studentinfo').':</b> '.($rec->tarikh_lahir ? userdate($rec->tarikh_lahir) : '').'</p>';
    echo '<p><b>'.get_string('tempat_lahir','local_studentinfo').':</b> '.s($rec->tempat_lahir ?? '').'</p>';
    echo '<p><b>'.get_string('darah','local_studentinfo').':</b> '.s($rec->darah ?? '').'</p>';
    echo '<p><b>'.get_string('telefon','local_studentinfo').':</b> '.s($rec->telefon ?? '').'</p>';
    echo html_writer::end_div();
    echo html_writer::end_div(); // row
    echo html_writer::end_div(); // card-body
    echo html_writer::end_div(); // card

    // === Two-column card grid (Akademik & Bahasa, Family & Courses, Ranks & Postings, Awards & Insurance) ===
    echo html_writer::start_div('row g-3');

    // Akademik (left)
    if ($academics) {
        echo html_writer::start_div('col-md-6');
        echo html_writer::start_div('card h-100');
        echo html_writer::div(get_string('sec_academic','local_studentinfo'), 'card-header');
        echo html_writer::start_div('card-body');
        echo '<ul class="list-unstyled">';
        foreach ($academics as $a) {
            echo '<li>'.s($a->tahun).' — '.s($a->kelulusan).'</li>';
        }
        echo '</ul>';
        echo html_writer::end_div(); echo html_writer::end_div(); echo html_writer::end_div();
    }

    // Bahasa (right)
    if ($languages) {
        echo html_writer::start_div('col-md-6');
        echo html_writer::start_div('card h-100');
        echo html_writer::div(get_string('sec_language','local_studentinfo'), 'card-header');
        echo html_writer::start_div('card-body');
        echo '<ul class="list-unstyled">';
        foreach ($languages as $l) {
            echo '<li><b>'.s($l->bahasa).':</b> '.s($l->baca).' / '.s($l->lisan).' / '.s($l->tulis).'</li>';
        }
        echo '</ul>';
        echo html_writer::end_div(); echo html_writer::end_div(); echo html_writer::end_div();
    }

    // Family (left)
    if ($families) {
        echo html_writer::start_div('col-md-6');
        echo html_writer::start_div('card h-100');
        echo html_writer::div(get_string('sec_family','local_studentinfo'), 'card-header');
        echo html_writer::start_div('card-body');
        echo '<ul class="list-unstyled">';
        foreach ($families as $f) {
            $dob = $f->tarikh_lahir ? ' ('.userdate($f->tarikh_lahir).')' : '';
            echo '<li><b>'.s($f->hubungan).'</b>: '.s($f->nama).$dob.' — '.s($f->telefon).'</li>';
        }
        echo '</ul>';
        echo html_writer::end_div(); echo html_writer::end_div(); echo html_writer::end_div();
    }

    // Courses / Latihan (right)
    if ($courses) {
        echo html_writer::start_div('col-md-6');
        echo html_writer::start_div('card h-100');
        echo html_writer::div(get_string('sec_courses','local_studentinfo'), 'card-header');
        echo html_writer::start_div('card-body');
        echo '<ul class="list-unstyled">';
        foreach ($courses as $c) {
            echo '<li><b>'.s($c->nama).'</b> — '.s($c->tempat).' ['.($c->mula?userdate($c->mula):'')
               . ' → '.($c->tamat?userdate($c->tamat):'').'] '.s($c->keputusan).'</li>';
        }
        echo '</ul>';
        echo html_writer::end_div(); echo html_writer::end_div(); echo html_writer::end_div();
    }

    // Pangkat (left)
    if ($ranks) {
        echo html_writer::start_div('col-md-6');
        echo html_writer::start_div('card h-100');
        echo html_writer::div(get_string('sec_ranks','local_studentinfo'), 'card-header');
        echo html_writer::start_div('card-body');
        echo '<ul class="list-unstyled">';
        foreach ($ranks as $r) {
            echo '<li><b>'.s($r->pangkat).'</b> '.($r->tarikh?userdate($r->tarikh):'');
            if (!empty($r->kekananan)) echo ' — '.s($r->kekananan);
            if (!empty($r->tarikh_kekananan)) echo ' ('.userdate($r->tarikh_kekananan).')';
            echo '</li>';
        }
        echo '</ul>';
        echo html_writer::end_div(); echo html_writer::end_div(); echo html_writer::end_div();
    }

    // Penempatan/Postings (right)
    if ($postings) {
        echo html_writer::start_div('col-md-6');
        echo html_writer::start_div('card h-100');
        echo html_writer::div(get_string('sec_postings','local_studentinfo'), 'card-header');
        echo html_writer::start_div('card-body');
        echo '<ul class="list-unstyled">';
        foreach ($postings as $p) {
            echo '<li><b>'.s($p->jawatan).'</b> — '.s($p->pasukan).' ('.s($p->negeri).') ['.($p->mula?userdate($p->mula):'')
               . ' → '.($p->tamat?userdate($p->tamat):'').']</li>';
        }
        echo '</ul>';
        echo html_writer::end_div(); echo html_writer::end_div(); echo html_writer::end_div();
    }

    // Anugerah (left)
    if ($awards) {
        echo html_writer::start_div('col-md-6');
        echo html_writer::start_div('card h-100');
        echo html_writer::div(get_string('sec_awards','local_studentinfo'), 'card-header');
        echo html_writer::start_div('card-body');
        echo '<ul class="list-unstyled">';
        foreach ($awards as $a) {
            $bits = [s($a->nama)];
            if ($a->singkatan) $bits[] = s($a->singkatan);
            if ($a->gelaran)   $bits[] = s($a->gelaran);
            $line = '<b>'.implode(' / ', $bits).'</b>';
            if ($a->tarikh) $line .= ' — '.userdate($a->tarikh);
            echo '<li>'.$line.'</li>';
        }
        echo '</ul>';
        echo html_writer::end_div(); echo html_writer::end_div(); echo html_writer::end_div();
    }

    // Insurans (right)
    if ($ins) {
        echo html_writer::start_div('col-md-6');
        echo html_writer::start_div('card h-100');
        echo html_writer::div(get_string('sec_insurance','local_studentinfo'), 'card-header');
        echo html_writer::start_div('card-body');
        echo '<ul class="list-unstyled">';
        foreach ($ins as $i) {
            echo '<li><b>'.s($i->penyedia).'</b> — '.s($i->no_polis).' ('
               . (is_null($i->jumlah_unit)?'' : (int)$i->jumlah_unit.' unit').')</li>';
        }
        echo '</ul>';
        echo html_writer::end_div(); echo html_writer::end_div(); echo html_writer::end_div();
    }

    echo html_writer::end_div(); // row g-3 (two-column grid)

    // Back to list and print (not printed)
    $backurl = new moodle_url('/local/studentinfo/index.php', ['ou' => $ouid]);
    echo html_writer::div(
        html_writer::link($backurl, 'Back to list', ['class' => 'btn btn-secondary']) .
        html_writer::link('#', 'Print', ['class' => 'btn btn-outline-secondary btn-print', 'id' => 'btn-print']),
        'd-flex justify-content-between align-items-center mt-3'
    );


    echo html_writer::end_div(); // .si-print-area
}

echo $OUTPUT->footer();
