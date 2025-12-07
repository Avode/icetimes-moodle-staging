<?php
require_once(__DIR__.'/../../config.php');
require_login();

$context = context_system::instance();
//require_capability('local/studentinfo:view', $context);

global $DB, $OUTPUT;

$userid = required_param('userid', PARAM_INT);
$ouid   = optional_param('ou', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/studentinfo/view.php', ['userid' => $userid, 'ou' => $ouid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('view', 'local_studentinfo'));
$PAGE->set_heading(get_string('pluginname', 'local_studentinfo'));

echo $OUTPUT->header();

$user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
$rec  = $DB->get_record('local_studentinfo', ['userid' => $userid]);
$fs          = get_file_storage();
$usercontext = \context_user::instance($userid);
$itemid      = $rec->id;


if (!$rec) {
    echo $OUTPUT->notification('No student info yet.','info');
    if (has_capability('local/studentinfo:edit', $context)) {
        echo html_writer::link(
            new moodle_url('/local/studentinfo/edit.php', ['userid' => $userid, 'ou' => $ouid]),
            get_string('edit','local_studentinfo'),
            ['class' => 'btn btn-primary']
        );
    }
    echo $OUTPUT->footer();
    exit;
}

// ---------------------------------------------------------
// Resolve lookup-based labels
// ---------------------------------------------------------

$studenttypename = '';
$servicename     = '';
$korname         = '';
$rankname        = '';

// Student type
if (!empty($rec->studenttypeid) && $DB->get_manager()->table_exists('local_studentlookup_type')) {
    if ($t = $DB->get_record('local_studentlookup_type', ['id' => $rec->studenttypeid], 'name', IGNORE_MISSING)) {
        $studenttypename = $t->name;
    }
}

// Service
if (!empty($rec->serviceid) && $DB->get_manager()->table_exists('local_studentlookup_service')) {
    if ($s = $DB->get_record('local_studentlookup_service', ['id' => $rec->serviceid], 'name', IGNORE_MISSING)) {
        $servicename = $s->name;
    }
}

// Kor / Regiment / Branch
if (!empty($rec->korid) && $DB->get_manager()->table_exists('local_studentlookup_korregimen')) {
    if ($k = $DB->get_record('local_studentlookup_korregimen', ['id' => $rec->korid], 'name', IGNORE_MISSING)) {
        $korname = $k->name;
    }
}

// Rank (from lookup, fallback to text field).
if (!empty($rec->rankid) && $DB->get_manager()->table_exists('local_studentlookup_rank')) {
    if ($r = $DB->get_record('local_studentlookup_rank', ['id' => $rec->rankid], 'name', IGNORE_MISSING)) {
        $rankname = $r->name;
    }
}
if ($rankname === '' && !empty($rec->pangkat)) {
    $rankname = $rec->pangkat;
}

// ---------------------------------------------------------
// Resolve OU / Faculty / Intake via mapping
// ---------------------------------------------------------
$college  = '';
$faculty  = '';
$intake   = '';

if ($DB->get_manager()->table_exists('local_studentinfo_studentmap')) {
    $map = $DB->get_record('local_studentinfo_studentmap', ['userid' => $userid], '*', IGNORE_MISSING);
    if ($map) {
        // OU
        if (!empty($map->ouid) && $DB->get_manager()->table_exists('local_organization_ou')) {
            if ($ourec = $DB->get_record('local_organization_ou', ['id' => $map->ouid], 'fullname', IGNORE_MISSING)) {
                $college = $ourec->fullname;
            }
        }
        // Faculty
        if (!empty($map->facultyid) && $DB->get_manager()->table_exists('local_ouadmin_faculty')) {
            if ($fac = $DB->get_record('local_ouadmin_faculty', ['id' => $map->facultyid], 'name', IGNORE_MISSING)) {
                $faculty = $fac->name;
            }
        }
        // Intake
        if (!empty($map->intakeid) && $DB->get_manager()->table_exists('local_ouadmin_intake')) {
            if ($int = $DB->get_record('local_ouadmin_intake', ['id' => $map->intakeid], 'name', IGNORE_MISSING)) {
                $intake = $int->name;
            }
        }
    }
}

// ---------------------------------------------------------
// Load child records
// ---------------------------------------------------------
$academics = $DB->get_records('local_student_academic',  ['studentid'=>$rec->id], 'tahun ASC');
$languages = $DB->get_records('local_student_language',  ['studentid'=>$rec->id]);
$families  = $DB->get_records('local_student_family',    ['studentid'=>$rec->id]);
$courses   = $DB->get_records('local_student_course',    ['studentid'=>$rec->id], 'mula ASC');
$ranks     = $DB->get_records('local_student_rank',      ['studentid'=>$rec->id], 'tarikh ASC');
$postings  = $DB->get_records('local_student_posting',   ['studentid'=>$rec->id], 'mula ASC');
$awards    = $DB->get_records('local_student_award',     ['studentid'=>$rec->id], 'tarikh ASC');
$ins       = $DB->get_records('local_student_insurance', ['studentid'=>$rec->id], 'id ASC');

?>
<div class="container-fluid">
  <div class="card mb-3">
    <div class="card-header">
      <strong><?php echo fullname($user); ?></strong>
      <?php if ($rankname) { ?> — <?php echo s($rankname); } ?>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6">
          <p><b><?php echo get_string('tentera_no','local_studentinfo'); ?>:</b> <?php echo s($rec->tentera_no ?? ''); ?></p>
          <p><b>Student Type:</b> <?php echo s($studenttypename ?: '-'); ?></p>
          <p><b><?php echo get_string('perkhidmatan','local_studentinfo'); ?>:</b> <?php echo s($servicename ?: '-'); ?></p>
          <p><b><?php echo get_string('rejimen','local_studentinfo'); ?>:</b> <?php echo s($korname ?: '-'); ?></p>
          <p><b><?php echo get_string('pengambilan','local_studentinfo'); ?>:</b> <?php echo s($rec->pengambilan ?? ''); ?></p>
        </div>
        <div class="col-md-6">
          <p><b>College (OU):</b> <?php echo s($college ?: '-'); ?></p>
          <p><b>Faculty:</b> <?php echo s($faculty ?: '-'); ?></p>
          <p><b>Intake (cohort):</b> <?php echo s($intake ?: '-'); ?></p>
          <p><b><?php echo get_string('tarikh_lahir','local_studentinfo'); ?>:</b> <?php echo $rec->tarikh_lahir ? userdate($rec->tarikh_lahir) : ''; ?></p>
          <p><b><?php echo get_string('tempat_lahir','local_studentinfo'); ?>:</b> <?php echo s($rec->tempat_lahir ?? ''); ?></p>
          <p><b><?php echo get_string('darah','local_studentinfo'); ?>:</b> <?php echo s($rec->darah ?? ''); ?></p>
          <p><b><?php echo get_string('telefon','local_studentinfo'); ?>:</b> <?php echo s($rec->telefon ?? ''); ?></p>
        </div>
      </div>
    </div>
  </div>

  <?php if ($academics) { ?>
  <div class="card mb-3">
    <div class="card-header"><?php echo get_string('sec_academic','local_studentinfo'); ?></div>
    <div class="card-body">
      <ul class="list-unstyled">
        <?php foreach ($academics as $a) {
          echo '<li>'.s($a->tahun).' — '.s($a->kelulusan).'</li>';
        } ?>
      </ul>
    </div>
  </div>
  <?php } ?>

  <?php if ($languages) { ?>
  <div class="card mb-3">
    <div class="card-header"><?php echo get_string('sec_language','local_studentinfo'); ?></div>
    <div class="card-body">
      <ul class="list-unstyled">
        <?php foreach ($languages as $l) {
          echo '<li><b>'.s($l->bahasa).':</b> '.s($l->baca).' / '.s($l->lisan).' / '.s($l->tulis).'</li>';
        } ?>
      </ul>
    </div>
  </div>
  <?php } ?>

  <?php if ($families) { ?>
  <div class="card mb-3">
    <div class="card-header"><?php echo get_string('sec_family','local_studentinfo'); ?></div>
    <div class="card-body">
      <ul class="list-unstyled">
        <?php foreach ($families as $f) {
          $dob = $f->tarikh_lahir ? ' ('.userdate($f->tarikh_lahir).')' : '';
          echo '<li><b>'.s($f->hubungan).'</b>: '.s($f->nama).$dob.' — '.s($f->telefon).'</li>';
        } ?>
      </ul>
    </div>
  </div>
  <?php } ?>

  <?php if ($courses) { ?>
  <div class="card mb-3">
    <div class="card-header"><?php echo get_string('sec_courses','local_studentinfo'); ?></div>
    <div class="card-body">
      <ul class="list-unstyled">
        <?php foreach ($courses as $c) {
          echo '<li><b>'.s($c->nama).'</b> — '.s($c->tempat).' ['.
            ($c->mula?userdate($c->mula):''). ' → '.($c->tamat?userdate($c->tamat):'').'] '.s($c->keputusan).'</li>';
        } ?>
      </ul>
    </div>
  </div>
  <?php } ?>

  <?php if ($ranks) { ?>
  <div class="card mb-3">
    <div class="card-header"><?php echo get_string('sec_ranks','local_studentinfo'); ?></div>
    <div class="card-body">
      <ul class="list-unstyled">
        <?php foreach ($ranks as $r) {
          echo '<li><b>'.s($r->pangkat).'</b> '.($r->tarikh?userdate($r->tarikh):'');
          if (!empty($r->kekananan)) echo ' — '.s($r->kekananan);
          if (!empty($r->tarikh_kekananan)) echo ' ('.userdate($r->tarikh_kekananan).')';
          echo '</li>';
        } ?>
      </ul>
    </div>
  </div>
  <?php } ?>

  <?php if ($postings) { ?>
  <div class="card mb-3">
    <div class="card-header"><?php echo get_string('sec_postings','local_studentinfo'); ?></div>
    <div class="card-body">
      <ul class="list-unstyled">
        <?php foreach ($postings as $p) {
          echo '<li><b>'.s($p->jawatan).'</b> — '.s($p->pasukan).' ('.s($p->negeri).') ['.
            ($p->mula?userdate($p->mula):'').' → '.($p->tamat?userdate($p->tamat):'').']</li>';
        } ?>
      </ul>
    </div>
  </div>
  <?php } ?>

  <?php if ($awards) { ?>
  <div class="card mb-3">
    <div class="card-header"><?php echo get_string('sec_awards','local_studentinfo'); ?></div>
    <div class="card-body">
      <ul class="list-unstyled">
        <?php foreach ($awards as $a) {
          $bits = [s($a->nama)];
          if ($a->singkatan) $bits[] = s($a->singkatan);
          if ($a->gelaran)  $bits[] = s($a->gelaran);
          $line = '<b>'.implode(' / ', $bits).'</b>';
          if ($a->tarikh) $line .= ' — '.userdate($a->tarikh);
          echo '<li>'.$line.'</li>';
        } ?>
      </ul>
    </div>
  </div>
  <?php } ?>

  <?php if ($ins) { ?>
  <div class="card mb-3">
    <div class="card-header"><?php echo get_string('sec_insurance','local_studentinfo'); ?></div>
    <div class="card-body">
      <ul class="list-unstyled">
        <?php foreach ($ins as $i) {
          echo '<li><b>'.s($i->penyedia).'</b> — '.s($i->no_polis).' ('.
            (is_null($i->jumlah_unit) ? '' : (int)$i->jumlah_unit.' unit').')</li>';
        } ?>
      </ul>
    </div>
  </div>
  <?php } ?>

      <!-- Uploads checklist -->
  <?php
  // Helper to check if a file exists in a given filearea.
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

  $uploaditems = [
      'photoself'        => get_string('photoself', 'local_studentinfo'),
      'photospouse'      => get_string('photospouse', 'local_studentinfo'),
      'courseletter'     => get_string('doc_courseletter', 'local_studentinfo'),
      'coletter'         => get_string('doc_co_letter', 'local_studentinfo'),
      'personalpart'     => get_string('doc_personal_part', 'local_studentinfo'),
      'healthbmi'        => get_string('doc_health_bmi', 'local_studentinfo'),
      'bat118a'          => get_string('doc_bat118a', 'local_studentinfo'),
      'mytentera'        => get_string('doc_mytentera', 'local_studentinfo'),
      'pregnancy'        => get_string('doc_pregnancy', 'local_studentinfo'),
      'joiningproforma'  => get_string('doc_joiningproforma', 'local_studentinfo'),
      'enclosure1'       => get_string('doc_enclosure1', 'local_studentinfo'),
  ];
  ?>

  <div class="card mb-3">
    <div class="card-header">Uploads checklist</div>
    <div class="card-body">
      <table class="table table-sm table-bordered mb-0">
        <thead>
          <tr>
            <th style="width:55%;">Item</th>
            <th style="width:15%; text-align:center;">Uploaded</th>
            <th style="width:30%; text-align:center;">View File</th>
          </tr>
        </thead>

        <tbody>
        <?php
        foreach ($uploaditems as $area => $label) {
        
            $files = $fs->get_area_files(
                $usercontext->id,
                'local_studentinfo',
                $area,
                $itemid,
                'filename',
                false
            );
        
            $exists = !empty($files);
            $filelink = '';
        
            if ($exists) {
                $file = reset($files); // first file (only 1 max)
                $url = moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    $file->get_itemid(),
                    $file->get_filepath(),
                    $file->get_filename()
                )->out(false);
        
                $filelink = '<a href="'.$url.'" target="_blank">View</a>';
            }
        
            echo '<tr>
                    <td>'.s($label).'</td>
                    <td style="text-align:center;">'.($exists ? "✔" : "").'</td>
                    <td style="text-align:center;">'.$filelink.'</td>
                  </tr>';
        }
        ?>
        </tbody>

      </table>
    </div>
  </div>

    <?php
  // Edit button (only for users with edit capability).
  if (has_capability('local/studentinfo:manage', $context)) {
      echo html_writer::link(
          new moodle_url('/local/studentinfo/edit.php', ['userid' => $userid, 'ou' => $ouid]),
          get_string('edit','local_studentinfo'),
          ['class' => 'btn btn-primary me-2']
      );
  }
  
  

  // --- Print & Close buttons ---

  // 1. Print: open a new tab (placeholder for future PDF).
  $printurl = new moodle_url('/local/studentinfo/print.php', [
      'userid' => $userid,
      'ou'     => $ouid,
  ]);
  echo html_writer::link(
      $printurl,
      'Print',
      ['class' => 'btn btn-secondary me-2', 'target' => '_blank']
  );

  // 2. Close: if user has edit role → go to index; else → /my/.
  if (has_capability('local/studentinfo:manage', $context)) {
      $closeurl = new moodle_url('/local/studentinfo/index.php', ['ou' => $ouid]);
  } else {
      $closeurl = new moodle_url('/my/');
  }
  echo html_writer::link(
      $closeurl,
      'Close',
      ['class' => 'btn btn-outline-secondary']
  );
  ?>
</div>
<?php
echo $OUTPUT->footer();

