<?php
// local/studentinfo/print.php
//
// Generate a PDF "Joining Proforma – Malaysian Course Participant" for a student.

require_once(__DIR__.'/../../config.php');
require_login();

$context = context_system::instance();
// require_capability('local/studentinfo:view', $context); // Uncomment when ready

global $DB, $CFG, $USER;

$userid = required_param('userid', PARAM_INT);
$ouid   = optional_param('ou', 0, PARAM_INT);

// Load core data.
$user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
$rec  = $DB->get_record('local_studentinfo', ['userid' => $userid], '*', MUST_EXIST);

// Child tables (same as view.php).
$academics = $DB->get_records('local_student_academic',  ['studentid'=>$rec->id], 'tahun ASC');
$courses   = $DB->get_records('local_student_course',    ['studentid'=>$rec->id], 'mula ASC');
$ranks     = $DB->get_records('local_student_rank',      ['studentid'=>$rec->id], 'tarikh ASC');
$awards    = $DB->get_records('local_student_award',     ['studentid'=>$rec->id], 'tarikh ASC');
$families  = $DB->get_records('local_student_family',    ['studentid'=>$rec->id], 'id ASC');
$ins       = $DB->get_records('local_student_insurance', ['studentid'=>$rec->id], 'id ASC');
// Languages etc. can be added later if needed.

// Lookup-based labels (Student Type / Service / Kor / Rank).
$studenttypename = '';
$servicename     = '';
$korname         = '';
$rankname        = '';

if (!empty($rec->studenttypeid) && $DB->get_manager()->table_exists('local_studentlookup_type')) {
    if ($t = $DB->get_record('local_studentlookup_type', ['id' => $rec->studenttypeid], 'name', IGNORE_MISSING)) {
        $studenttypename = $t->name;
    }
}
if (!empty($rec->serviceid) && $DB->get_manager()->table_exists('local_studentlookup_service')) {
    if ($s = $DB->get_record('local_studentlookup_service', ['id' => $rec->serviceid], 'name', IGNORE_MISSING)) {
        $servicename = $s->name;
    }
}
if (!empty($rec->korid) && $DB->get_manager()->table_exists('local_studentlookup_korregimen')) {
    if ($k = $DB->get_record('local_studentlookup_korregimen', ['id' => $rec->korid], 'name', IGNORE_MISSING)) {
        $korname = $k->name;
    }
}
if (!empty($rec->rankid) && $DB->get_manager()->table_exists('local_studentlookup_rank')) {
    if ($r = $DB->get_record('local_studentlookup_rank', ['id' => $rec->rankid], 'name', IGNORE_MISSING)) {
        $rankname = $r->name;
    }
}
if ($rankname === '' && !empty($rec->pangkat)) {
    $rankname = $rec->pangkat;
}

// OU / Faculty / Intake from studentmap.
$college = '';
$faculty = '';
$intake  = '';

if ($DB->get_manager()->table_exists('local_studentinfo_studentmap')) {
    $map = $DB->get_record('local_studentinfo_studentmap', ['userid' => $userid], '*', IGNORE_MISSING);
    if ($map) {
        if (!empty($map->ouid) && $DB->get_manager()->table_exists('local_organization_ou')) {
            if ($ourec = $DB->get_record('local_organization_ou', ['id' => $map->ouid], 'fullname', IGNORE_MISSING)) {
                $college = $ourec->fullname;
            }
        }
        if (!empty($map->facultyid) && $DB->get_manager()->table_exists('local_ouadmin_faculty')) {
            if ($fac = $DB->get_record('local_ouadmin_faculty', ['id' => $map->facultyid], 'name', IGNORE_MISSING)) {
                $faculty = $fac->name;
            }
        }
        if (!empty($map->intakeid) && $DB->get_manager()->table_exists('local_ouadmin_intake')) {
            if ($int = $DB->get_record('local_ouadmin_intake', ['id' => $map->intakeid], 'name', IGNORE_MISSING)) {
                $intake = $int->name;
            }
        }
    }
}

// --------------------------------------------------------------------
// Set up Moodle PDF
// --------------------------------------------------------------------
require_once($CFG->libdir . '/pdflib.php');

$pdf = new pdf();
$pdf->SetCreator('Moodle local_studentinfo');
$pdf->SetAuthor(fullname($USER));
$pdf->SetTitle('Joining Proforma – Malaysian Course Participant');
$pdf->SetSubject('Joining Proforma');

// A4 portrait
$pdf->setPageOrientation('P');
$pdf->SetMargins(15, 20, 15);
$pdf->SetAutoPageBreak(true, 20);

$pdf->AddPage();

// Simple CSS for layout.
$style = '
<style>
  h1,h2,h3,h4 { font-family: helvetica, arial, sans-serif; }
  .title { font-size: 16pt; font-weight: bold; text-align: center; }
  .subtitle { font-size: 11pt; text-align: center; }
  .sectiontitle { font-size: 11pt; font-weight: bold; margin-top: 10px; }
  table.details td { font-size: 9pt; padding: 2px 4px; vertical-align: top; }
  table.details td.label { width: 25%; font-weight: bold; }
  table.details td.value { width: 75%; }
  .small { font-size: 8pt; }
</style>
';

$pdf->writeHTML($style, true, false, true, false, '');

// --------------------------------------------------------------------
// Header
// --------------------------------------------------------------------
$html = '
<div class="title">MALAYSIAN ARMED FORCES STAFF COLLEGE</div>
<div class="subtitle">JOINING PROFORMA – MALAYSIAN COURSE PARTICIPANT</div>
<br /><br />
';

// --------------------------------------------------------------------
// PART 1: PERSONAL PARTICULARS
// --------------------------------------------------------------------
$html .= '<div class="sectiontitle">PART 1: PERSONAL PARTICULARS</div>';

$html .= '<table class="details" border="0" cellpadding="2" cellspacing="0">
  <tr><td class="label">1. Service No :</td><td class="value">'.s($rec->tentera_no ?? '').'</td></tr>
  <tr><td class="label">2. Rank :</td><td class="value">'.s($rankname).'</td></tr>
  <tr><td class="label">3. Full Name :</td><td class="value">'.s(fullname($user)).'</td></tr>
  <tr><td class="label">4. Service Type :</td><td class="value">'.s($studenttypename ?: $servicename).'</td></tr>
  <tr><td class="label">5. Corps/Branch/Trade :</td><td class="value">'.s($korname).'</td></tr>
  <tr><td class="label">6. Date of Commission :</td><td class="value">'.($rec->tarikh_tauliah ? userdate($rec->tarikh_tauliah) : '').'</td></tr>
  <tr><td class="label">7. Name to be addressed :</td><td class="value">'.s($rec->nama_panggilan ?? '').'</td></tr>
  <tr><td class="label">8. Name on name tag :</td><td class="value">'.s($rec->nama_tag ?? '').'</td></tr>
  <tr><td class="label">9. Gender :</td><td class="value">'.s($rec->jantina ?? '').'</td></tr>
  <tr><td class="label">10. Date of Birth :</td><td class="value">'.($rec->tarikh_lahir ? userdate($rec->tarikh_lahir) : '').'</td></tr>
  <tr><td class="label">11. Place of Birth :</td><td class="value">'.s($rec->tempat_lahir ?? '').'</td></tr>
  <tr><td class="label">12. Nationality :</td><td class="value">'.s($rec->warganegara ?? '').'</td></tr>
  <tr><td class="label">13. Race :</td><td class="value">'.s($rec->bangsa ?? '').'</td></tr>
  <tr><td class="label">14. Religion :</td><td class="value">'.s($rec->agama ?? '').'</td></tr>
  <tr><td class="label">15. Height (m) :</td><td class="value">'.s($rec->tinggi_m ?? '').'</td></tr>
  <tr><td class="label">    Weight (kg) :</td><td class="value">'.s($rec->berat_kg ?? '').'</td></tr>
  <tr><td class="label">16. Body Mass Index (BMI) :</td><td class="value">'.s($rec->bmi ?? '').'</td></tr>
  <tr><td class="label">17. IC Number :</td><td class="value">'.s($rec->ic ?? '').'</td></tr>
  <tr><td class="label">18. Medical Status :</td><td class="value">'.s($rec->medical_status ?? '').'</td></tr>
  <tr><td class="label">    Date of Medical Check-Up :</td><td class="value">'.($rec->medical_date ? userdate($rec->medical_date) : '').'</td></tr>
  <tr><td class="label">19. Blood Group :</td><td class="value">'.s($rec->darah ?? '').'</td></tr>
  <tr><td class="label">20. Special Dietary Requirements :</td><td class="value">'.s($rec->diet ?? '').'</td></tr>
</table>
<br />';
//
// Note: medical_status, medical_date, nama_panggilan, nama_tag, ic, diet etc.
// are placeholders – add columns or mappings if you have them.
//

// --------------------------------------------------------------------
// PART 2: PROFESSIONAL DATA
// --------------------------------------------------------------------
$html .= '<div class="sectiontitle">PART 2: PROFESSIONAL DATA</div>';

// 22. Civil Education (use academics + courses as proxy).
$html .= '<b>22. Civil Education:</b><br /><table class="details" border="1" cellpadding="2" cellspacing="0">
  <tr>
    <td class="label small">Name of Examination and Field of Study</td>
    <td class="label small">Institution</td>
    <td class="label small">Year</td>
    <td class="label small">Division/Class/Grade</td>
    <td class="label small">CGPA/Details</td>
  </tr>';

if ($academics) {
    foreach ($academics as $a) {
        $html .= '<tr>
          <td class="small">'.s($a->tahap ?? '').'</td>
          <td class="small">'.s($a->kelulusan ?? '').'</td>
          <td class="small">'.s($a->tahun ?? '').'</td>
          <td class="small"></td>
          <td class="small"></td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="5" class="small">No civil education records.</td></tr>';
}
$html .= '</table><br />';

// 23. Military Education – use course list.
$html .= '<b>23. Military Education (Courses Attended):</b><br /><table class="details" border="1" cellpadding="2" cellspacing="0">
  <tr>
    <td class="label small">Name of Course</td>
    <td class="label small">Institution / Location</td>
    <td class="label small">From</td>
    <td class="label small">Until</td>
    <td class="label small">Grade / Result</td>
  </tr>';

if ($courses) {
    foreach ($courses as $c) {
        $html .= '<tr>
          <td class="small">'.s($c->nama ?? '').'</td>
          <td class="small">'.s($c->tempat ?? '').'</td>
          <td class="small">'.($c->mula  ? userdate($c->mula)  : '').'</td>
          <td class="small">'.($c->tamat ? userdate($c->tamat) : '').'</td>
          <td class="small">'.s($c->keputusan ?? '').'</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="5" class="small">No military course records.</td></tr>';
}
$html .= '</table><br />';

// 24. Career Details – use postings list.
$html .= '<b>24. Career Details (appointments since commissioned):</b><br /><table class="details" border="1" cellpadding="2" cellspacing="0">
  <tr>
    <td class="label small">Appointment</td>
    <td class="label small">Unit / Formation</td>
    <td class="label small">From</td>
    <td class="label small">Until</td>
    <td class="label small">Remarks</td>
  </tr>';

if ($postings) {
    foreach ($postings as $p) {
        $html .= '<tr>
          <td class="small">'.s($p->jawatan ?? '').'</td>
          <td class="small">'.s($p->pasukan ?? '').' ('.s($p->negeri ?? '').')</td>
          <td class="small">'.($p->mula  ? userdate($p->mula)  : '').'</td>
          <td class="small">'.($p->tamat ? userdate($p->tamat) : '').'</td>
          <td class="small"></td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="5" class="small">No posting records.</td></tr>';
}
$html .= '</table><br />';

// --------------------------------------------------------------------
// PART 3: AWARDS AND DECORATIONS
// --------------------------------------------------------------------
$html .= '<div class="sectiontitle">PART 3: AWARDS AND DECORATIONS</div>';

$html .= '<table class="details" border="1" cellpadding="2" cellspacing="0">
  <tr>
    <td class="label small">Awards and Decorations</td>
    <td class="label small">Year Awarded</td>
  </tr>';

if ($awards) {
    foreach ($awards as $a) {
        $name = trim(($a->nama ?? '').' '.($a->singkatan ?? '').' '.($a->gelaran ?? ''));
        $html .= '<tr>
          <td class="small">'.s($name).'</td>
          <td class="small">'.($a->tarikh ? userdate($a->tarikh, '%Y') : '').'</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="2" class="small">No awards recorded.</td></tr>';
}
$html .= '</table><br />';

// --------------------------------------------------------------------
// PART 4: SPORTS AND GAMES (placeholder structure)
// --------------------------------------------------------------------
$html .= '<div class="sectiontitle">PART 4: SPORTS AND GAMES</div>';
$html .= '<p class="small">[To be completed manually if required – Golf, Football, Hockey, etc.]</p><br />';

// --------------------------------------------------------------------
// PART 5: FAMILY DETAILS
// --------------------------------------------------------------------
$html .= '<div class="sectiontitle">PART 5: FAMILY DETAILS</div>';

// Marital status etc. (use what you have).
$html .= '<table class="details" border="0" cellpadding="2" cellspacing="0">
  <tr><td class="label">Marital Status :</td><td class="value">'.s($rec->taraf_kahwin ?? '').'</td></tr>
</table><br />';

// 27–28: NOK and children – use first family record as NOK, rest as children.
if ($families) {
    $nok = reset($families);
    $children = $families;
    // Remove first element from children list.
    unset($children[key($families)]);

    $html .= '<b>Next of Kin (NOK):</b><br />
    <table class="details" border="0" cellpadding="2" cellspacing="0">
      <tr><td class="label">Full Name :</td><td class="value">'.s($nok->nama ?? '').'</td></tr>
      <tr><td class="label">Relationship :</td><td class="value">'.s($nok->hubungan ?? '').'</td></tr>
      <tr><td class="label">IC Number :</td><td class="value">'.s($nok->ic ?? '').'</td></tr>
      <tr><td class="label">Phone :</td><td class="value">'.s($nok->telefon ?? '').'</td></tr>
    </table><br />';

    if ($children) {
        $html .= '<b>Children:</b><br />
        <table class="details" border="1" cellpadding="2" cellspacing="0">
          <tr>
            <td class="label small">Name</td>
            <td class="label small">Gender</td>
            <td class="label small">Age</td>
            <td class="label small">Remarks</td>
          </tr>';
        foreach ($children as $c) {
            $age = '';
            if (!empty($c->tarikh_lahir)) {
                $birthyear = (int)userdate($c->tarikh_lahir, '%Y');
                $age = (int)date('Y') - $birthyear;
            }
            $html .= '<tr>
              <td class="small">'.s($c->nama ?? '').'</td>
              <td class="small"></td>
              <td class="small">'.s($age).'</td>
              <td class="small"></td>
            </tr>';
        }
        $html .= '</table><br />';
    }
} else {
    $html .= '<p class="small">No family details recorded.</p><br />';
}

// --------------------------------------------------------------------
// PART 6: MISCELLANEOUS DETAILS
// --------------------------------------------------------------------
$html .= '<div class="sectiontitle">PART 6: MISCELLANEOUS DETAILS</div>';

$html .= '<b>29. Insurance Policies:</b><br />';
if ($ins) {
    $html .= '<ul class="small">';
    foreach ($ins as $i) {
        $html .= '<li>'.s($i->penyedia).' — '.s($i->no_polis).' '.
            (is_null($i->jumlah_unit) ? '' : '('.(int)$i->jumlah_unit.' unit)').'</li>';
    }
    $html .= '</ul>';
} else {
    $html .= '<p class="small">No insurance records.</p>';
}

$html .= '<br /><b>30. International Passport:</b><br />
<table class="details" border="0" cellpadding="2" cellspacing="0">
  <tr><td class="label">Passport No :</td><td class="value">'.s($rec->passport_no ?? '').'</td></tr>
  <tr><td class="label">Expiry Date :</td><td class="value">'.($rec->passport_expiry ? userdate($rec->passport_expiry) : '').'</td></tr>
</table><br />';

// 31. Vehicles – not in DB yet, placeholder.
$html .= '<b>31. Motor Vehicles Owned (While on Course):</b><br />
<p class="small">[To be completed manually]</p><br />';

// --------------------------------------------------------------------
// PART 7: RESIDENCE (placeholders)
// --------------------------------------------------------------------
$html .= '<div class="sectiontitle">PART 7: RESIDENCE</div>';
$html .= '<p class="small">32–34. Residence details and other NOK – to be completed manually as required.</p>';

// Write full HTML into PDF.
$pdf->writeHTML($html, true, false, true, false, '');

// --------------------------------------------------------------------
// SECOND PAGE: Uploads checklist
// --------------------------------------------------------------------
$pdf->AddPage();

// We need file storage to check what has been uploaded.
$fs          = get_file_storage();
$usercontext = \context_user::instance($userid);
$itemid      = $rec->id;

// Helper: does a file exist in a given filearea?
$has_file = function(string $area) use ($fs, $usercontext, $itemid) {
    $files = $fs->get_area_files(
        $usercontext->id,
        'local_studentinfo',
        $area,
        $itemid,
        'filename',
        false
    );
    return !empty($files);
};

// Build checklist rows.
$checkrows = [
    'photoself'      => get_string('photoself', 'local_studentinfo'),
    'photospouse'    => get_string('photospouse', 'local_studentinfo'),
    'courseletter'   => get_string('doc_courseletter', 'local_studentinfo'),
    'coletter'       => get_string('doc_co_letter', 'local_studentinfo'),
    'personalpart'   => get_string('doc_personal_part', 'local_studentinfo'),
    'healthbmi'      => get_string('doc_health_bmi', 'local_studentinfo'),
    'bat118a'        => get_string('doc_bat118a', 'local_studentinfo'),
    'mytentera'      => get_string('doc_mytentera', 'local_studentinfo'),
    'pregnancy'      => get_string('doc_pregnancy', 'local_studentinfo'),
    'joiningproforma'  => get_string('doc_joiningproforma', 'local_studentinfo'), // ✅
    'enclosure1'       => get_string('doc_enclosure1', 'local_studentinfo'),      // ✅
];

$html2  = '<h3>'.get_string('uploads_checklist', 'local_studentinfo').'</h3>';
$html2 .= '<table border="1" cellpadding="3" cellspacing="0" width="100%">';
$html2 .= '<tr>
             <th width="70%" align="left">Item</th>
             <th width="30%" align="center">'.get_string('uploaded', 'local_studentinfo').'</th>
           </tr>';

foreach ($checkrows as $area => $label) {
    $exists = $has_file($area);
    $mark   = $exists ? '✔' : '';
    $html2 .= '<tr>
                 <td>'.s($label).'</td>
                 <td align="center">'.$mark.'</td>
               </tr>';
}

$html2 .= '</table>';

$pdf->writeHTML($html2, true, false, true, false, '');

// Output PDF to browser (inline, in new tab).
$pdf->Output('joining_proforma_'.$userid.'.pdf', 'I');
