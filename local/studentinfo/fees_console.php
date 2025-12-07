<?php
require_once(__DIR__.'/../../config.php');

// Guard-load bridge
if (!class_exists('\\local_studentinfo\\local\\orgstructure_bridge')) {
    require_once($CFG->dirroot.'/local/studentinfo/classes/local/orgstructure_bridge.php');
}

require_login();
$context = context_system::instance();
/** Site admin bypass; others need finance cap */
if (!is_siteadmin()) {
    require_capability('local/studentinfo:finance', $context);
}

$ouid = optional_param('ou', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/studentinfo/fees_console.php', ['ou'=>$ouid]));
$PAGE->set_context($context);
$PAGE->set_title('Fees Console');
$PAGE->set_heading(get_string('pluginname', 'local_studentinfo'));
$PAGE->requires->css('/local/studentinfo/style.css');

global $DB, $OUTPUT;

echo $OUTPUT->header();

/* OU banner + OU bar */
echo \local_studentinfo\local\orgstructure_bridge::ou_banner($ouid, 'Scope');
echo \local_studentinfo\local\orgstructure_bridge::render_ou_bar($ouid);

/* -------------------------
   Actions (POST)
   ------------------------- */
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'postfee' && confirm_sesskey()) {
    $intakeid   = required_param('intakeid', PARAM_INT);
    $termcode   = optional_param('termcode', '', PARAM_RAW_TRIMMED);
    $itemcode   = required_param('itemcode', PARAM_ALPHANUMEXT);
    $itemname   = required_param('itemname', PARAM_TEXT);
    $amount     = required_param('amount', PARAM_FLOAT);
    $duedate    = optional_param('duedate', '', PARAM_RAW_TRIMMED);

    $termid = null;
    if ($termcode !== '') {
        $termid = $DB->get_field('local_studentinfo_term', 'id', ['code'=>$termcode], IGNORE_MISSING);
    }

    // Ensure only users in selected OU (if any)
    $ousql = '';
    $params = ['intake'=>$intakeid, 'itemcode'=>$itemcode, 'amount'=>$amount, 'now'=>time()];
    if ($ouid) { $ousql = " JOIN {local_org_member} om ON om.userid = sp.userid AND om.orgunitid = :ou "; $params['ou']=$ouid; }

    // Insert new fees for users with sp.intakeid. Avoid duplicates by NOT EXISTS on (userid, termid, itemcode)
    $sql = "
      INSERT INTO {local_studentinfo_fee}
        (userid, termid, itemcode, itemname, amount, duedate, paid_amount, `status`, timecreated, timemodified)
      SELECT sp.userid,
             :termid AS termid,
             :itemcode, :itemname, :amount,
             ".($duedate ? $DB->sql_placeholder() : "NULL")." AS duedate,
             0.00, 'unpaid', :now, :now
      FROM {local_studentinfo_studentprog} sp
      $ousql
      WHERE sp.intakeid = :intake
        AND NOT EXISTS (
          SELECT 1 FROM {local_studentinfo_fee} f
          WHERE f.userid = sp.userid
            AND ".($termid ? "f.termid = :termid2" : "f.termid IS NULL")."
            AND f.itemcode = :itemcode2
        )
    ";
    // Build params
    $execparams = $params + [
        'termid'   => $termid,
        'termid2'  => $termid,
        'itemname' => $itemname,
        'itemcode2'=> $itemcode,
    ];
    // Inject duedate if provided
    if ($duedate) {
        // insert placeholder value for duedate in the SQL param list:
        // the previous concatenation added a placeholder; append the value here
        $execparams[] = $duedate;
    }
    $DB->execute($sql, $execparams);

    echo html_writer::div('Fee posted to intake. (Duplicates skipped)', 'alert alert-success');
}
else if ($action === 'markpaid' && confirm_sesskey()) {
    $feeid   = required_param('feeid', PARAM_INT);
    $payamt  = required_param('payamt', PARAM_FLOAT);
    $receipt = optional_param('receipt', '', PARAM_ALPHANUMEXT);
    $method  = optional_param('method', 'BankIn', PARAM_ALPHANUMEXT);

    $DB->execute("
      UPDATE {local_studentinfo_fee}
         SET paid_amount = LEAST(amount, COALESCE(paid_amount,0)+:pay),
             paid_ts     = :now,
             receiptno   = :rc,
             method      = :m,
             `status`    = CASE
                             WHEN LEAST(amount, COALESCE(paid_amount,0)+:pay2) >= amount THEN 'paid'
                             WHEN (COALESCE(paid_amount,0)+:pay3) > 0 THEN 'partial'
                             ELSE 'unpaid'
                           END,
             timemodified = :now2
       WHERE id = :id
    ", ['pay'=>$payamt,'pay2'=>$payamt,'pay3'=>$payamt,'rc'=>$receipt,'m'=>$method,'now'=>time(),'now2'=>time(),'id'=>$feeid]);

    echo html_writer::div('Payment recorded.', 'alert alert-success');
}

/* -------------------------
   Filters (GET)
   ------------------------- */
$filter_status = optional_param('status', 'all', PARAM_ALPHA); // all|unpaid|partial|paid|waived
$filter_intake = optional_param('fintake', 0, PARAM_INT);
$filter_term   = optional_param('fterm', 0, PARAM_INT);

/* Lookup: intakes (respect OU if set) */
$intakes = [];
if ($ouid) {
    $intakes = $DB->get_records_sql_menu("
      SELECT i.id, i.name
        FROM {local_studentinfo_intake} i
        WHERE EXISTS (
          SELECT 1 FROM {local_studentinfo_studentprog} sp
          JOIN {local_org_member} om ON om.userid=sp.userid AND om.orgunitid=:ou
          WHERE sp.intakeid = i.id
        )
        ORDER BY i.startdate DESC, i.id DESC
    ", ['ou'=>$ouid]);
} else {
    $intakes = $DB->get_records_menu('local_studentinfo_intake', null, 'startdate DESC, id DESC', 'id,name');
}

/* Lookup: terms */
$terms = $DB->get_records_menu('local_studentinfo_term', null, 'startdate DESC, id DESC', 'id,code');

/* -------------------------
   Post Fee form (card)
   ------------------------- */
echo html_writer::start_div('container mb-3');
echo html_writer::start_div('row g-3');

// Post fee card
echo html_writer::start_div('col-lg-6');
echo html_writer::start_div('card h-100');
echo html_writer::div('Post Fee to Intake', 'card-header fw-bold');
echo html_writer::start_div('card-body');
$posturl = new moodle_url('/local/studentinfo/fees_console.php', ['ou'=>$ouid, 'action'=>'postfee', 'sesskey'=>sesskey()]);
echo html_writer::start_tag('form', ['method'=>'post', 'action'=>$posturl]);
echo html_writer::start_div('mb-2');
echo html_writer::tag('label', 'Intake', ['class'=>'form-label']);
echo html_writer::start_tag('select', ['name'=>'intakeid','class'=>'form-control','required'=>true]);
foreach ($intakes as $iid=>$iname) {
    $sel = ($iid==(int)$filter_intake) ? ['selected'=>'selected'] : [];
    echo html_writer::tag('option', s($iname), ['value'=>(int)$iid] + $sel);
}
echo html_writer::end_tag('select');
echo html_writer::end_div();

echo html_writer::start_div('mb-2');
echo html_writer::tag('label', 'Term (optional)', ['class'=>'form-label']);
echo html_writer::start_tag('select', ['name'=>'termcode','class'=>'form-control']);
echo html_writer::tag('option', '— None —', ['value'=>'']);
foreach ($terms as $tid=>$tcode) {
    echo html_writer::tag('option', s($tcode), ['value'=>s($tcode)]);
}
echo html_writer::end_tag('select');
echo html_writer::end_div();

echo html_writer::start_div('row g-2');
echo html_writer::start_div('col-md-6');
echo html_writer::tag('label', 'Item Code', ['class'=>'form-label']);
echo html_writer::empty_tag('input', ['type'=>'text','name'=>'itemcode','class'=>'form-control','required'=>true,'maxlength'=>64]);
echo html_writer::end_div();
echo html_writer::start_div('col-md-6');
echo html_writer::tag('label', 'Amount (RM)', ['class'=>'form-label']);
echo html_writer::empty_tag('input', ['type'=>'number','name'=>'amount','class'=>'form-control','required'=>true,'step'=>'0.01','min'=>'0']);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('mb-2');
echo html_writer::tag('label', 'Item Name', ['class'=>'form-label']);
echo html_writer::empty_tag('input', ['type'=>'text','name'=>'itemname','class'=>'form-control','required'=>true,'maxlength'=>255]);
echo html_writer::end_div();

echo html_writer::start_div('mb-2');
echo html_writer::tag('label', 'Due Date (optional)', ['class'=>'form-label']);
echo html_writer::empty_tag('input', ['type'=>'date','name'=>'duedate','class'=>'form-control']);
echo html_writer::end_div();

echo html_writer::empty_tag('input', ['type'=>'submit','class'=>'btn btn-primary','value'=>'Post Fee']);
echo html_writer::end_tag('form');
echo html_writer::end_div(); // body
echo html_writer::end_div(); // card
echo html_writer::end_div(); // col

// Filters card
echo html_writer::start_div('col-lg-6');
echo html_writer::start_div('card h-100');
echo html_writer::div('Filter Fees', 'card-header fw-bold');
echo html_writer::start_div('card-body');
$flturl = new moodle_url('/local/studentinfo/fees_console.php');
echo html_writer::start_tag('form', ['method'=>'get','action'=>$flturl]);
echo html_writer::empty_tag('input', ['type'=>'hidden','name'=>'ou','value'=>(int)$ouid]);

echo html_writer::start_div('mb-2');
echo html_writer::tag('label', 'Intake', ['class'=>'form-label']);
echo html_writer::start_tag('select', ['name'=>'fintake','class'=>'form-control']);
echo html_writer::tag('option', '— All —', ['value'=>0]);
foreach ($intakes as $iid=>$iname) {
    $sel = ($iid==(int)$filter_intake) ? ['selected'=>'selected'] : [];
    echo html_writer::tag('option', s($iname), ['value'=>(int)$iid] + $sel);
}
echo html_writer::end_tag('select');
echo html_writer::end_div();

echo html_writer::start_div('mb-2');
echo html_writer::tag('label', 'Term', ['class'=>'form-label']);
echo html_writer::start_tag('select', ['name'=>'fterm','class'=>'form-control']);
echo html_writer::tag('option', '— All —', ['value'=>0]);
foreach ($terms as $tid=>$tcode) {
    $sel = ($tid==(int)$filter_term) ? ['selected'=>'selected'] : [];
    echo html_writer::tag('option', s($tcode), ['value'=>(int)$tid] + $sel);
}
echo html_writer::end_tag('select');
echo html_writer::end_div();

echo html_writer::start_div('mb-2');
echo html_writer::tag('label', 'Status', ['class'=>'form-label']);
echo html_writer::start_tag('select', ['name'=>'status','class'=>'form-control']);
$statuses = ['all'=>'All','unpaid'=>'Unpaid','partial'=>'Partial','paid'=>'Paid','waived'=>'Waived'];
foreach ($statuses as $k=>$v) {
    $sel = ($k===$filter_status) ? ['selected'=>'selected'] : [];
    echo html_writer::tag('option', s($v), ['value'=>$k] + $sel);
}
echo html_writer::end_tag('select');
echo html_writer::end_div();

echo html_writer::empty_tag('input', ['type'=>'submit','class'=>'btn btn-outline-secondary','value'=>'Apply']);
echo html_writer::end_tag('form');
echo html_writer::end_div(); // body
echo html_writer::end_div(); // card
echo html_writer::end_div(); // col

echo html_writer::end_div(); // row
echo html_writer::end_div(); // container

/* -------------------------
   Fees Table (OU-scoped + filters)
   ------------------------- */
$where = [];
$params = [];

if ($ouid) {
    $where[] = "EXISTS (SELECT 1 FROM {local_org_member} om WHERE om.userid = f.userid AND om.orgunitid = :ou)";
    $params['ou'] = $ouid;
}
if ($filter_intake) {
    // Restrict by intake via studentprog
    $where[] = "EXISTS (SELECT 1 FROM {local_studentinfo_studentprog} sp WHERE sp.userid = f.userid AND sp.intakeid = :fi)";
    $params['fi'] = $filter_intake;
}
if ($filter_term) {
    $where[] = "f.termid = :ft";
    $params['ft'] = $filter_term;
}
if ($filter_status !== 'all') {
    $where[] = "f.status = :st";
    $params['st'] = $filter_status;
}

$sql = "
  SELECT f.*, u.firstname, u.lastname,
         t.code AS termcode
    FROM {local_studentinfo_fee} f
    JOIN {user} u ON u.id = f.userid
    LEFT JOIN {local_studentinfo_term} t ON t.id = f.termid
   ".($where ? "WHERE ".implode(" AND ", $where) : "")."
   ORDER BY f.duedate DESC, f.id DESC
";
$rows = $DB->get_records_sql($sql, $params);

echo html_writer::start_div('container');
echo html_writer::tag('h5', 'Fees');
$table = new html_table();
$table->head = ['User', 'Term', 'Item', 'Amount', 'Paid', 'Status', 'Due', 'Receipt', 'Method', 'Settle'];
foreach ($rows as $r) {
    $name = fullname((object)['firstname'=>$r->firstname,'lastname'=>$r->lastname]);
    $settleform = '';
    if ($r->status !== 'paid') {
        $settleurl = new moodle_url('/local/studentinfo/fees_console.php', ['ou'=>$ouid,'action'=>'markpaid','sesskey'=>sesskey()]);
        $settleform = html_writer::start_tag('form', ['method'=>'post','action'=>$settleurl,'class'=>'d-flex gap-1']);
        $settleform .= html_writer::empty_tag('input', ['type'=>'hidden','name'=>'feeid','value'=>(int)$r->id]);
        $settleform .= html_writer::empty_tag('input', ['type'=>'number','name'=>'payamt','step'=>'0.01','min'=>'0','value'=>max(0.00, (float)$r->amount - (float)$r->paid_amount),'class'=>'form-control','style'=>'width:110px','required'=>true]);
        $settleform .= html_writer::empty_tag('input', ['type'=>'text','name'=>'receipt','placeholder'=>'Receipt','class'=>'form-control','style'=>'width:120px']);
        $settleform .= html_writer::start_tag('select', ['name'=>'method','class'=>'form-control','style'=>'width:110px']);
        foreach (['BankIn','Cash','FPX','Card'] as $m) {
            $settleform .= html_writer::tag('option', $m, ['value'=>$m, 'selected'=>($r->method===$m?'selected':null)]);
        }
        $settleform .= html_writer::end_tag('select');
        $settleform .= html_writer::empty_tag('input', ['type'=>'submit','class'=>'btn btn-sm btn-primary','value'=>'Mark']);
        $settleform .= html_writer::end_tag('form');
    }
    $table->data[] = [
        format_string($name),
        s($r->termcode ?? '-'),
        format_string($r->itemname).' ('.s($r->itemcode).')',
        format_float($r->amount, 2),
        format_float($r->paid_amount ?? 0, 2),
        s($r->status),
        $r->duedate ?? '-',
        s($r->receiptno ?? ''),
        s($r->method ?? ''),
        $settleform ?: '-'
    ];
}
echo html_writer::table($table);
echo html_writer::end_div(); // container

echo $OUTPUT->footer();
