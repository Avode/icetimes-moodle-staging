<?php
// local/studentinfo/edit.php
require_once(__DIR__.'/../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/user/lib.php'); // for process_new_icon

require_login();

$context = context_system::instance();
//require_capability('local/studentinfo:edit', $context);

global $DB, $PAGE, $OUTPUT, $SESSION, $USER;
$userid = required_param('userid', PARAM_INT);
$user   = $DB->get_record('user', ['id'=>$userid, 'deleted'=>0], '*', MUST_EXIST);
$activetab = optional_param('tab', 'sec_identity', PARAM_ALPHANUMEXT);

// ===== Permission model: edit-own always allowed; edit-others needs capability.
$caneditothers = has_capability('local/studentinfo:manage', $context);
if (!$caneditothers && $USER->id != $userid) {
    // Redirect user to their own record if they try to edit someone else.
    redirect(new moodle_url('/local/studentinfo/edit.php', ['userid'=>$USER->id]), get_string('nopermissions', 'error'));
}

$PAGE->set_url(new moodle_url('/local/studentinfo/edit.php', [
    'userid' => $userid,
    'tab'    => $activetab,
]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('edit', 'local_studentinfo').': '.fullname($user));
$PAGE->set_heading(get_string('pluginname', 'local_studentinfo'));

$main = $DB->get_record('local_studentinfo', ['userid'=>$userid]);
$studentinfo = $main; // alias for clarity
$ouid = optional_param('ou', 0, PARAM_INT);

// Resolve rank name for header using local_studentlookup_rank if rankid exists.
$rankname = '';
if ($main) {
    // Fallback: old text field (if present).
    $rankname = $main->pangkat ?? '';

    // Prefer new lookup by rankid, if available.
    if (!empty($main->rankid) && $DB->get_manager()->table_exists('local_studentlookup_rank')) {
        $lookup = $DB->get_record('local_studentlookup_rank', ['id' => $main->rankid], 'name', IGNORE_MISSING);
        if ($lookup && !empty($lookup->name)) {
            $rankname = $lookup->name;
        }
    }
}

/* ===================== CHILD ACTIONS & LISTS ===================== */

/** AKADEMIK **/
$acadaction = optional_param('acadaction', '', PARAM_ALPHA); // '' | edit | delete
$acid       = optional_param('acid', 0, PARAM_INT);
$acad_editrec = null;

if ($acadaction === 'delete' && $acid && confirm_sesskey()) {
    if ($rec = $DB->get_record('local_student_academic', ['id'=>$acid])) {
        if ($main && (int)$rec->studentid === (int)$main->id) {
            $DB->delete_records('local_student_academic', ['id'=>$acid]);
        }
    }
    redirect(new moodle_url('/local/studentinfo/edit.php', ['userid'=>$userid, 'tab'=>'sec_academic']), 'Rekod akademik dipadam.');
}
if ($acadaction === 'edit' && $acid) {
    if ($rec = $DB->get_record('local_student_academic', ['id'=>$acid])) {
        if ($main && (int)$rec->studentid === (int)$main->id) $acad_editrec = $rec;
    }
}
$acad_list = $main ? array_values($DB->get_records('local_student_academic', ['studentid'=>$main->id], 'tahun ASC, id ASC')) : [];

/** BAHASA **/
$langaction = optional_param('langaction', '', PARAM_ALPHA); // '' | edit | delete
$lid        = optional_param('lid', 0, PARAM_INT);
$lang_editrec = null;

if ($langaction === 'delete' && $lid && confirm_sesskey()) {
    if ($rec = $DB->get_record('local_student_language', ['id'=>$lid])) {
        if ($main && (int)$rec->studentid === (int)$main->id) {
            $DB->delete_records('local_student_language', ['id'=>$lid]);
        }
    }
    redirect(new moodle_url('/local/studentinfo/edit.php', ['userid'=>$userid, 'tab'=>'sec_language']), 'Rekod bahasa dipadam.');
}
if ($langaction === 'edit' && $lid) {
    if ($rec = $DB->get_record('local_student_language', ['id'=>$lid])) {
        if ($main && (int)$rec->studentid === (int)$main->id) $lang_editrec = $rec;
    }
}
$lang_list = $main ? array_values($DB->get_records('local_student_language', ['studentid'=>$main->id], 'id ASC')) : [];

/** WARIS **/
$faction = optional_param('faction', '', PARAM_ALPHA); // '' | edit | delete
$fid     = optional_param('fid', 0, PARAM_INT);
$fam_editrec = null;

if ($faction === 'delete' && $fid && confirm_sesskey()) {
    if ($rec = $DB->get_record('local_student_family', ['id'=>$fid])) {
        if ($main && (int)$rec->studentid === (int)$main->id) {
            $DB->delete_records('local_student_family', ['id'=>$fid]);
        }
    }
    redirect(new moodle_url('/local/studentinfo/edit.php', ['userid'=>$userid, 'tab'=>'sec_family']), 'Rekod waris dipadam.');
}
if ($faction === 'edit' && $fid) {
    if ($rec = $DB->get_record('local_student_family', ['id'=>$fid])) {
        if ($main && (int)$rec->studentid === (int)$main->id) $fam_editrec = $rec;
    }
}
$fam_list = $main ? array_values($DB->get_records('local_student_family', ['studentid'=>$main->id], 'id ASC')) : [];

/** KURSUS **/
$courseaction = optional_param('courseaction', '', PARAM_ALPHA); // '' | edit | delete
$cid          = optional_param('cid', 0, PARAM_INT);
$course_editrec = null;

if ($courseaction === 'delete' && $cid && confirm_sesskey()) {
    if ($rec = $DB->get_record('local_student_course', ['id'=>$cid])) {
        if ($main && (int)$rec->studentid === (int)$main->id) {
            $DB->delete_records('local_student_course', ['id'=>$cid]);
        }
    }
    redirect(new moodle_url('/local/studentinfo/edit.php', ['userid'=>$userid, 'tab'=>'sec_courses']), 'Rekod kursus dipadam.');
}
if ($courseaction === 'edit' && $cid) {
    if ($rec = $DB->get_record('local_student_course', ['id'=>$cid])) {
        if ($main && (int)$rec->studentid === (int)$main->id) $course_editrec = $rec;
    }
}
$course_list = $main ? array_values($DB->get_records('local_student_course', ['studentid'=>$main->id], 'mula ASC, id ASC')) : [];

/** PANGKAT **/
$rankaction = optional_param('rankaction', '', PARAM_ALPHA); // '' | edit | delete
$rid        = optional_param('rid', 0, PARAM_INT);
$rank_editrec = null;

if ($rankaction === 'delete' && $rid && confirm_sesskey()) {
    if ($rec = $DB->get_record('local_student_rank', ['id'=>$rid])) {
        if ($main && (int)$rec->studentid === (int)$main->id) {
            $DB->delete_records('local_student_rank', ['id'=>$rid]);
        }
    }
    redirect(new moodle_url('/local/studentinfo/edit.php', ['userid'=>$userid, 'tab'=>'sec_ranks']), 'Rekod pangkat dipadam.');
}
if ($rankaction === 'edit' && $rid) {
    if ($rec = $DB->get_record('local_student_rank', ['id'=>$rid])) {
        if ($main && (int)$rec->studentid === (int)$main->id) $rank_editrec = $rec;
    }
}
$rank_list = $main ? array_values($DB->get_records('local_student_rank', ['studentid'=>$main->id], 'tarikh ASC, id ASC')) : [];

/** PERTUKARAN **/
$postaction = optional_param('postaction', '', PARAM_ALPHA); // '' | edit | delete
$pid        = optional_param('pid', 0, PARAM_INT);
$post_editrec = null;

if ($postaction === 'delete' && $pid && confirm_sesskey()) {
    if ($rec = $DB->get_record('local_student_posting', ['id'=>$pid])) {
        if ($main && (int)$rec->studentid === (int)$main->id) {
            $DB->delete_records('local_student_posting', ['id'=>$pid]);
        }
    }
    redirect(new moodle_url('/local/studentinfo/edit.php', ['userid'=>$userid, 'tab'=>'sec_postings']), 'Rekod pertukaran dipadam.');
}
if ($postaction === 'edit' && $pid) {
    if ($rec = $DB->get_record('local_student_posting', ['id'=>$pid])) {
        if ($main && (int)$rec->studentid === (int)$main->id) $post_editrec = $rec;
    }
}
$post_list = $main ? array_values($DB->get_records('local_student_posting', ['studentid'=>$main->id], 'mula ASC, id ASC')) : [];

/** PINGAT **/
$awardaction = optional_param('awardaction', '', PARAM_ALPHA); // '' | edit | delete
$awid        = optional_param('awid', 0, PARAM_INT);
$award_editrec = null;

if ($awardaction === 'delete' && $awid && confirm_sesskey()) {
    if ($rec = $DB->get_record('local_student_award', ['id'=>$awid])) {
        if ($main && (int)$rec->studentid === (int)$main->id) {
            $DB->delete_records('local_student_award', ['id'=>$awid]);
        }
    }
    redirect(new moodle_url('/local/studentinfo/edit.php', ['userid'=>$userid, 'tab'=>'sec_awards']), 'Rekod pingat dipadam.');
}
if ($awardaction === 'edit' && $awid) {
    if ($rec = $DB->get_record('local_student_award', ['id'=>$awid])) {
        if ($main && (int)$rec->studentid === (int)$main->id) $award_editrec = $rec;
    }
}
$award_list = $main ? array_values($DB->get_records('local_student_award', ['studentid'=>$main->id], 'tarikh ASC, id ASC')) : [];

/** INSURAN **/
$insaction = optional_param('insaction', '', PARAM_ALPHA); // '' | edit | delete
$insid     = optional_param('insid', 0, PARAM_INT);
$ins_editrec = null;

if ($insaction === 'delete' && $insid && confirm_sesskey()) {
    if ($rec = $DB->get_record('local_student_insurance', ['id'=>$insid])) {
        if ($main && (int)$rec->studentid === (int)$main->id) {
            $DB->delete_records('local_student_insurance', ['id'=>$insid]);
        }
    }
    redirect(new moodle_url('/local/studentinfo/edit.php', ['userid'=>$userid, 'tab'=>'sec_insurance']), 'Rekod insurans dipadam.');
}
if ($insaction === 'edit' && $insid) {
    if ($rec = $DB->get_record('local_student_insurance', ['id'=>$insid])) {
        if ($main && (int)$rec->studentid === (int)$main->id) $ins_editrec = $rec;
    }
}
$ins_list = $main ? array_values($DB->get_records('local_student_insurance', ['studentid'=>$main->id], 'id ASC')) : [];

/* ===================== BUILD FORM ===================== */
require_once(__DIR__.'/classes/form/edit_form.php');
$mform = new \local_studentinfo\form\edit_form(
    null,
    [
        'user'           => $user,
        'ouid'           => $ouid,         // ✅ added
        'studentmap'     => $studentmap ?? null, // if you have this
        'studentinfo'    => $studentinfo,  // ✅ added
        'activetab'      => $activetab,

        'acad_list'      => $acad_list,
        'acad_editrec'   => $acad_editrec,
        'lang_list'      => $lang_list,
        'lang_editrec'   => $lang_editrec,

        'fam_list'       => $fam_list,
        'fam_editrec'    => $fam_editrec,
        'course_list'    => $course_list,
        'course_editrec' => $course_editrec,
        'rank_list'      => $rank_list,
        'rank_editrec'   => $rank_editrec,
        'post_list'      => $post_list,
        'post_editrec'   => $post_editrec,
        'award_list'     => $award_list,
        'award_editrec'  => $award_editrec,
        'ins_list'       => $ins_list,
        'ins_editrec'    => $ins_editrec,
    ]
);

/* ===================== HANDLE SUBMIT ===================== */
if ($mform->is_cancelled()) {
    $tab = optional_param('tab', 'sec_identity', PARAM_ALPHANUMEXT);
    redirect(new moodle_url('/local/studentinfo/edit.php', ['userid'=>$userid, 'tab'=>$tab]));
} else if ($data = $mform->get_data()) {
    //print_object($data);
    //die;
    // Ensure new lookup IDs are present (fallback to raw request if needed).
    if (!isset($data->serviceid)) {
        $data->serviceid = optional_param('serviceid', 0, PARAM_INT);
    }
    if (!isset($data->korid)) {
        $data->korid = optional_param('korid', 0, PARAM_INT);
    }
    if (!isset($data->rankid)) {
        $data->rankid = optional_param('rankid', 0, PARAM_INT);
    }
    //print_object($data);
    //die;
        // Figure out which tab we are on from URL.
        $currenttab = optional_param('tab', 'sec_identity', PARAM_ALPHANUMEXT);
    
        // ============================
        // Save Identity & Service Data
        // ============================
        /*if ($currenttab === 'sec_identity') {
            // Load or create local_studentinfo record.
            $now = time();
            $si  = null;
            if ($DB->get_manager()->table_exists('local_studentinfo')) {
                $si = $DB->get_record('local_studentinfo', ['userid' => $user->id], '*', IGNORE_MISSING);
            }
    
            if ($si) {
                $si->tentera_no    = $data->tentera_no;
                $si->studenttypeid = !empty($data->studenttypeid) ? (int)$data->studenttypeid : null;
                $si->serviceid     = !empty($data->serviceid)     ? (int)$data->serviceid     : null;
                $si->korid         = !empty($data->korid)         ? (int)$data->korid         : null;
                if (property_exists($si, 'rankid')) {
                    $si->rankid   = !empty($data->rankid) ? (int)$data->rankid : null;
                }
                $si->timemodified  = $now;
                $DB->update_record('local_studentinfo', $si);
            } else if ($DB->get_manager()->table_exists('local_studentinfo')) {
                $si = new stdClass();
                $si->userid       = $user->id;
                $si->tentera_no   = $data->tentera_no;
                $si->studenttypeid= !empty($data->studenttypeid) ? (int)$data->studenttypeid : null;
                $si->serviceid    = !empty($data->serviceid)     ? (int)$data->serviceid     : null;
                $si->korid        = !empty($data->korid)         ? (int)$data->korid         : null;
                if ($DB->get_manager()->table_exists('local_studentinfo')) {
                    // Rank column optional.
                    if ($DB->get_manager()->table_exists('local_studentinfo')) {
                        $columns = $DB->get_columns('local_studentinfo');
                        if (isset($columns['rankid'])) {
                            $si->rankid = !empty($data->rankid) ? (int)$data->rankid : null;
                        }
                    }
                }
                $si->timecreated  = $now;
                $si->timemodified = $now;
                $DB->insert_record('local_studentinfo', $si);
            }
    
            // Decide what to do next: which button did they press?
            $ouid       = optional_param('ou', 0, PARAM_INT);
            $currenttab = optional_param('tab', 'sec_identity', PARAM_ALPHANUMEXT);
            
            // NEW: Decide where to go based on which button was pressed.
            if (!empty($data->saveandview)) {
                // Button: Save and View
                redirect(
                    new moodle_url('/local/studentinfo/view.php', [
                        'userid' => $userid,
                        'ou'     => $ouid,
                    ]),
                    get_string('savechanges', 'local_studentinfo'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            } else {
                // Default Moodle button: Save and stay (submitbutton) or any other save
                redirect(
                    new moodle_url('/local/studentinfo/edit.php', [
                        'userid' => $userid,
                        'ou'     => $ouid,
                        'tab'    => $currenttab,
                    ]),
                    get_string('savechanges', 'local_studentinfo'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            }

    
            // If we reached here, no save button handled; fall through to other section handlers.
        }*/

    /* AKADEMIK add/update */
    if (!empty($data->acad_submit)) {
        require_sesskey();
        $now = time();
        if (!$main) { $main = (object)['userid'=>$userid,'timecreated'=>$now,'timemodified'=>$now]; $main->id = $DB->insert_record('local_studentinfo', $main); }
        $rid  = (int)($data->acad_id ?? 0);
        $yr   = isset($data->acad_tahun) ? (int)$data->acad_tahun : null;
        $lvl  = trim((string)($data->acad_tahap ?? ''));
        $desc = trim((string)($data->acad_kelulusan ?? ''));
        if ($rid) {
            if ($rec = $DB->get_record('local_student_academic', ['id'=>$rid])) {
                if ((int)$rec->studentid === (int)$main->id) {
                    $rec->tahun=$yr?:null; $rec->tahap=$lvl?:null; $rec->kelulusan=$desc?:null; $rec->timemodified=$now;
                    $DB->update_record('local_student_academic', $rec);
                }
            }
            redirect(new moodle_url('/local/studentinfo/edit.php', ['userid'=>$userid,'tab'=>'sec_academic']), get_string('recordupdated', 'local_studentinfo'));
        } else {
            $ins = (object)['studentid'=>$main->id,'tahun'=>$yr?:null,'tahap'=>$lvl?:null,'kelulusan'=>$desc?:null,'timecreated'=>$now,'timemodified'=>$now];
            $DB->insert_record('local_student_academic',$ins);
            redirect(new moodle_url('/local/studentinfo/edit.php', ['userid'=>$userid,'tab'=>'sec_academic']), get_string('recordupdated', 'local_studentinfo'));
        }
    }

    /* BAHASA add/update */
    if (!empty($data->lang_submit)) {
        require_sesskey();
        $now=time();
        if (!$main) { $main = (object)['userid'=>$userid,'timecreated'=>$now,'timemodified'=>$now]; $main->id = $DB->insert_record('local_studentinfo', $main); }
        $lid  = (int)($data->lang_id ?? 0);
        $bah  = trim((string)($data->lang_bahasa ?? ''));
        $baca = trim((string)($data->lang_baca ?? ''));
        $lisan= trim((string)($data->lang_lisan ?? ''));
        $tulis= trim((string)($data->lang_tulis ?? ''));
        if ($lid) {
            if ($rec=$DB->get_record('local_student_language',['id'=>$lid])) {
                if ((int)$rec->studentid === (int)$main->id) {
                    $rec->bahasa=$bah?:null; $rec->baca=$baca?:null; $rec->lisan=$lisan?:null; $rec->tulis=$tulis?:null; $rec->timemodified=$now;
                    $DB->update_record('local_student_language',$rec);
                }
            }
            redirect(new moodle_url('/local/studentinfo/edit.php',['userid'=>$userid,'tab'=>'sec_language']),'Rekod dikemaskini.');
        } else {
            $ins=(object)['studentid'=>$main->id,'bahasa'=>$bah?:null,'baca'=>$baca?:null,'lisan'=>$lisan?:null,'tulis'=>$tulis?:null,'timecreated'=>$now,'timemodified'=>$now];
            $DB->insert_record('local_student_language',$ins);
            redirect(new moodle_url('/local/studentinfo/edit.php',['userid'=>$userid,'tab'=>'sec_language']),'Rekod ditambah.');
        }
    }

    /* WARIS add/update */
    if (!empty($data->fam_submit)) {
        require_sesskey();
        $now=time();
        if (!$main) { $main=(object)['userid'=>$userid,'timecreated'=>$now,'timemodified'=>$now]; $main->id=$DB->insert_record('local_studentinfo',$main); }
        $fid  = (int)($data->fam_id ?? 0);
        $hub  = trim((string)($data->fam_hubungan ?? ''));
        $nama = trim((string)($data->fam_nama ?? ''));
        $ic   = trim((string)($data->fam_ic ?? ''));
        $tel  = trim((string)($data->fam_telefon ?? ''));
        $dob  = (isset($data->fam_tarikh_lahir) && is_numeric($data->fam_tarikh_lahir)) ? (int)$data->fam_tarikh_lahir : 0;
        if ($fid) {
            if ($rec=$DB->get_record('local_student_family',['id'=>$fid])) {
                if ((int)$rec->studentid === (int)$main->id) {
                    $rec->hubungan=$hub?:null; $rec->nama=$nama?:null; $rec->ic=$ic?:null; $rec->telefon=$tel?:null; $rec->tarikh_lahir=$dob; $rec->timemodified=$now;
                    $DB->update_record('local_student_family',$rec);
                }
            }
            redirect(new moodle_url('/local/studentinfo/edit.php',['userid'=>$userid,'tab'=>'sec_family']),'Rekod dikemaskini.');
        } else {
            $ins=(object)['studentid'=>$main->id,'hubungan'=>$hub?:null,'nama'=>$nama?:null,'ic'=>$ic?:null,'telefon'=>$tel?:null,'tarikh_lahir'=>$dob,'timecreated'=>$now,'timemodified'=>$now];
            $DB->insert_record('local_student_family',$ins);
            redirect(new moodle_url('/local/studentinfo/edit.php',['userid'=>$userid,'tab'=>'sec_family']),'Rekod ditambah.');
        }
    }

    /* KURSUS add/update */
    if (!empty($data->course_submit)) {
        require_sesskey();
        $now=time();
        if (!$main) { $main=(object)['userid'=>$userid,'timecreated'=>$now,'timemodified'=>$now]; $main->id=$DB->insert_record('local_studentinfo',$main); }
        $cid  = (int)($data->course_id ?? 0);
        $nama = trim((string)($data->kursus_nama ?? ''));
        $tmp  = trim((string)($data->kursus_tempat ?? ''));
        $mula = (isset($data->kursus_mula)  && is_numeric($data->kursus_mula))  ? (int)$data->kursus_mula  : 0;
        $tmt  = (isset($data->kursus_tamat) && is_numeric($data->kursus_tamat)) ? (int)$data->kursus_tamat : 0;
        $kept = trim((string)($data->kursus_keputusan ?? ''));
        if ($cid) {
            if ($rec=$DB->get_record('local_student_course',['id'=>$cid])) {
                if ((int)$rec->studentid === (int)$main->id) {
                    $rec->nama=$nama?:null; $rec->tempat=$tmp?:null; $rec->mula=$mula; $rec->tamat=$tmt; $rec->keputusan=$kept?:null; $rec->timemodified=$now;
                    $DB->update_record('local_student_course',$rec);
                }
            }
            redirect(new moodle_url('/local/studentinfo/edit.php',['userid'=>$userid,'tab'=>'sec_courses']),'Rekod dikemaskini.');
        } else {
            $ins=(object)['studentid'=>$main->id,'nama'=>$nama?:null,'tempat'=>$tmp?:null,'mula'=>$mula,'tamat'=>$tmt,'keputusan'=>$kept?:null,'timecreated'=>$now,'timemodified'=>$now];
            $DB->insert_record('local_student_course',$ins);
            redirect(new moodle_url('/local/studentinfo/edit.php',['userid'=>$userid,'tab'=>'sec_courses']),'Rekod ditambah.');
        }
    }

    /* PANGKAT add/update */
    if (!empty($data->rank_submit)) {
        require_sesskey();
        $now=time();
        if (!$main) { $main=(object)['userid'=>$userid,'timecreated'=>$now,'timemodified'=>$now]; $main->id=$DB->insert_record('local_studentinfo',$main); }
        $rid = (int)($data->rank_id ?? 0);
        $pgk = trim((string)($data->rank_pangkat ?? ''));
        $dt  = (isset($data->rank_tarikh) && is_numeric($data->rank_tarikh)) ? (int)$data->rank_tarikh : 0;
        $kek = trim((string)($data->rank_kekananan ?? ''));
        $dkk = (isset($data->rank_tarikh_kekananan) && is_numeric($data->rank_tarikh_kekananan)) ? (int)$data->rank_tarikh_kekananan : 0;
        if ($rid) {
            if ($rec=$DB->get_record('local_student_rank',['id'=>$rid])) {
                if ((int)$rec->studentid === (int)$main->id) {
                    $rec->pangkat=$pgk?:null; $rec->tarikh=$dt; $rec->kekananan=$kek?:null; $rec->tarikh_kekananan=$dkk; $rec->timemodified=$now;
                    $DB->update_record('local_student_rank',$rec);
                }
            }
            redirect(new moodle_url('/local/studentinfo/edit.php',['userid'=>$userid,'tab'=>'sec_ranks']),'Rekod dikemaskini.');
        } else {
            $ins=(object)['studentid'=>$main->id,'pangkat'=>$pgk?:null,'tarikh'=>$dt,'kekananan'=>$kek?:null,'tarikh_kekananan'=>$dkk,'timecreated'=>$now,'timemodified'=>$now];
            $DB->insert_record('local_student_rank',$ins);
            redirect(new moodle_url('/local/studentinfo/edit.php',['userid'=>$userid,'tab'=>'sec_ranks']),'Rekod ditambah.');
        }
    }

    /* PERTUKARAN add/update */
    if (!empty($data->post_submit)) {
        require_sesskey();
        $now=time();
        if (!$main) { $main=(object)['userid'=>$userid,'timecreated'=>$now,'timemodified'=>$now]; $main->id=$DB->insert_record('local_studentinfo',$main); }
        $pid = (int)($data->post_id ?? 0);
        $jaw = trim((string)($data->posting_jawatan ?? ''));
        $pas = trim((string)($data->posting_pasukan ?? ''));
        $neg = trim((string)($data->posting_negeri ?? ''));
        $mul = (isset($data->posting_mula)  && is_numeric($data->posting_mula))  ? (int)$data->posting_mula  : 0;
        $tam = (isset($data->posting_tamat) && is_numeric($data->posting_tamat)) ? (int)$data->posting_tamat : 0;
        if ($pid) {
            if ($rec=$DB->get_record('local_student_posting',['id'=>$pid])) {
                if ((int)$rec->studentid === (int)$main->id) {
                    $rec->jawatan=$jaw?:null; $rec->pasukan=$pas?:null; $rec->negeri=$neg?:null; $rec->mula=$mul; $rec->tamat=$tam; $rec->timemodified=$now;
                    $DB->update_record('local_student_posting',$rec);
                }
            }
            redirect(new moodle_url('/local/studentinfo/edit.php',['userid'=>$userid,'tab'=>'sec_postings']),'Rekod dikemaskini.');
        } else {
            $ins=(object)['studentid'=>$main->id,'jawatan'=>$jaw?:null,'pasukan'=>$pas?:null,'negeri'=>$neg?:null,'mula'=>$mul,'tamat'=>$tam,'timecreated'=>$now,'timemodified'=>$now];
            $DB->insert_record('local_student_posting',$ins);
            redirect(new moodle_url('/local/studentinfo/edit.php',['userid'=>$userid,'tab'=>'sec_postings']),'Rekod ditambah.');
        }
    }

    /* PINGAT add/update */
    if (!empty($data->award_submit)) {
        require_sesskey();
        $now=time();
        if (!$main) { $main=(object)['userid'=>$userid,'timecreated'=>$now,'timemodified'=>$now]; $main->id=$DB->insert_record('local_studentinfo',$main); }
        $awid = (int)($data->award_id ?? 0);
        $nm   = trim((string)($data->award_nama ?? ''));
        $sgk  = trim((string)($data->award_singkatan ?? ''));
        $gel  = trim((string)($data->award_gelaran ?? ''));
        $dt   = (isset($data->award_tarikh) && is_numeric($data->award_tarikh)) ? (int)$data->award_tarikh : 0;
        if ($awid) {
            if ($rec=$DB->get_record('local_student_award',['id'=>$awid])) {
                if ((int)$rec->studentid === (int)$main->id) {
                    $rec->nama=$nm?:null; $rec->singkatan=$sgk?:null; $rec->gelaran=$gel?:null; $rec->tarikh=$dt; $rec->timemodified=$now;
                    $DB->update_record('local_student_award',$rec);
                }
            }
            redirect(new moodle_url('/local/studentinfo/edit.php',['userid'=>$userid,'tab'=>'sec_awards']),'Rekod dikemaskini.');
        } else {
            $ins=(object)['studentid'=>$main->id,'nama'=>$nm?:null,'singkatan'=>$sgk?:null,'gelaran'=>$gel?:null,'tarikh'=>$dt,'timecreated'=>$now,'timemodified'=>$now];
            $DB->insert_record('local_student_award',$ins);
            redirect(new moodle_url('/local/studentinfo/edit.php',['userid'=>$userid,'tab'=>'sec_awards']),'Rekod ditambah.');
        }
    }

    /* INSURAN add/update */
    if (!empty($data->ins_submit)) {
        require_sesskey();
        $now=time();
        if (!$main) { $main=(object)['userid'=>$userid,'timecreated'=>$now,'timemodified'=>$now]; $main->id=$DB->insert_record('local_studentinfo',$main); }
        $iid  = (int)($data->ins_id ?? 0);
        $pen  = trim((string)($data->ins_penyedia ?? ''));
        $unit = ($data->ins_jumlah_unit !== '' && $data->ins_jumlah_unit !== null && !is_array($data->ins_jumlah_unit)) ? (int)$data->ins_jumlah_unit : null;
        $polis= trim((string)($data->ins_no_polis ?? ''));
        if ($iid) {
            if ($rec=$DB->get_record('local_student_insurance',['id'=>$iid])) {
                if ((int)$rec->studentid === (int)$main->id) {
                    $rec->penyedia=$pen?:null; $rec->jumlah_unit=$unit; $rec->no_polis=$polis?:null; $rec->timemodified=$now;
                    $DB->update_record('local_student_insurance',$rec);
                }
            }
            redirect(new moodle_url('/local/studentinfo/edit.php',['userid'=>$userid,'tab'=>'sec_insurance']),'Rekod dikemaskini.');
        } else {
            $ins=(object)['studentid'=>$main->id,'penyedia'=>$pen?:null,'jumlah_unit'=>$unit,'no_polis'=>$polis?:null,'timecreated'=>$now,'timemodified'=>$now];
            $DB->insert_record('local_student_insurance',$ins);
            redirect(new moodle_url('/local/studentinfo/edit.php',['userid'=>$userid,'tab'=>'sec_insurance']),'Rekod ditambah.');
        }
    }

    /* ---------- Main SAVE (profile) ---------- */
        /* ---------- Main SAVE (profile) ---------- */
    $now = time();
    $scalar_or_null = function($v) {
        return (is_array($v) || is_object($v)) ? null : $v;
    };

     // Resolve mirrors (IDs -> names/values) from NEW lookup tables.
    if (empty($data->pangkat) && !empty($data->rankid) && $DB->get_manager()->table_exists('local_studentlookup_rank')) {
        $data->pangkat = $DB->get_field('local_studentlookup_rank', 'name', ['id' => (int)$data->rankid]) ?: null;
    }
    if (empty($data->perkhidmatan) && !empty($data->serviceid) && $DB->get_manager()->table_exists('local_studentlookup_service')) {
        $data->perkhidmatan = $DB->get_field('local_studentlookup_service', 'name', ['id' => (int)$data->serviceid]) ?: null;
    }
    if (empty($data->rejimen) && !empty($data->korid) && $DB->get_manager()->table_exists('local_studentlookup_korregimen')) {
        $data->rejimen = $DB->get_field('local_studentlookup_korregimen', 'name', ['id' => (int)$data->korid]) ?: null;
    }


    // Build main studentinfo record.
    $record = (object)[
        'id'             => !empty($data->id) ? (int)$data->id : 0,
        'userid'         => (int)$userid,
        'tentera_no'     => $scalar_or_null($data->tentera_no ?? null),
        'pangkat'        => $scalar_or_null($data->pangkat ?? null),
        'perkhidmatan'   => $scalar_or_null($data->perkhidmatan ?? null),
        'rejimen'        => $scalar_or_null($data->rejimen ?? null),
        'pengambilan'    => (isset($data->pengambilan_sel) && $data->pengambilan_sel !== '' && !is_array($data->pengambilan_sel))
                     ? (string)(int)$data->pengambilan_sel
                     : null,
        'jenis_tauliah'  => (isset($data->jenis_tauliah_sel) && $data->jenis_tauliah_sel !== '' && !is_array($data->jenis_tauliah_sel))
                             ? (string)$data->jenis_tauliah_sel
                             : null,
        'tarikh_masuk'   => (isset($data->tarikh_masuk)   && is_numeric($data->tarikh_masuk))   ? (int)$data->tarikh_masuk   : 0,
        'tarikh_tauliah' => (isset($data->tarikh_tauliah) && is_numeric($data->tarikh_tauliah)) ? (int)$data->tarikh_tauliah : 0,
        'tarikh_tamat'   => (isset($data->tarikh_tamat)   && is_numeric($data->tarikh_tamat))   ? (int)$data->tarikh_tamat   : 0,
        'tarikh_lahir'   => (isset($data->tarikh_lahir)   && is_numeric($data->tarikh_lahir))   ? (int)$data->tarikh_lahir   : 0,
        'tempat_lahir'   => $scalar_or_null($data->tempat_lahir ?? null),
        'berat_kg'       => ($data->berat_kg ?? '') !== '' && !is_array($data->berat_kg) ? (float)$data->berat_kg : null,
        'tinggi_m'       => ($data->tinggi_m ?? '') !== '' && !is_array($data->tinggi_m) ? (float)$data->tinggi_m : null,
        'bmi'            => ($data->bmi ?? '')      !== '' && !is_array($data->bmi)      ? (float)$data->bmi      : null,
        'darah'          => $scalar_or_null($data->darah ?? null),
        'bangsa'         => $scalar_or_null($data->bangsa ?? null),
        'agama'          => $scalar_or_null($data->agama ?? null),
        'warganegara'    => $scalar_or_null($data->warganegara ?? null),
        'taraf_kahwin'   => $scalar_or_null($data->taraf_kahwin ?? null),
        'telefon'        => $scalar_or_null($data->telefon ?? null),
        'email'          => $scalar_or_null($data->email ?? null),
        'batd11_nilaian' => ($data->batd11_nilaian ?? '')!=='' && !is_array($data->batd11_nilaian) ? (float)$data->batd11_nilaian : null,
        'batd11_tahun'   => (isset($data->batd11_tahun) && !is_array($data->batd11_tahun)) ? (int)$data->batd11_tahun : null,
        'adfelps_listening' => (isset($data->adfelps_listening) && !is_array($data->adfelps_listening)) ? (int)$data->adfelps_listening : null,
        'adfelps_speaking'  => (isset($data->adfelps_speaking)  && !is_array($data->adfelps_speaking))  ? (int)$data->adfelps_speaking  : null,
        'adfelps_reading'   => (isset($data->adfelps_reading)   && !is_array($data->adfelps_reading))   ? (int)$data->adfelps_reading   : null,
        'adfelps_writing'   => (isset($data->adfelps_writing)   && !is_array($data->adfelps_writing))   ? (int)$data->adfelps_writing   : null,
        'passport_no'    => $scalar_or_null($data->passport_no ?? null),
        'passport_expiry'=> (isset($data->passport_expiry) && is_numeric($data->passport_expiry)) ? (int)$data->passport_expiry : 0,
        'negara_dilawati'=> $scalar_or_null($data->negara_dilawati ?? null),
        'hobi'           => $scalar_or_null($data->hobi ?? null),
        'timemodified'   => $now
    ];
    
    // Store numeric lookups if columns exist.
    if ($DB->get_manager()->table_exists('local_studentinfo')) {
        $columns = $DB->get_columns('local_studentinfo');
    
        if (is_array($columns)) {
            if (isset($columns['studenttypeid'])) {
                $record->studenttypeid = !empty($data->studenttypeid) ? (int)$data->studenttypeid : null;
            }
            if (isset($columns['serviceid'])) {
                $record->serviceid = !empty($data->serviceid) ? (int)$data->serviceid : null;
            }
            if (isset($columns['korid'])) {
                $record->korid = !empty($data->korid) ? (int)$data->korid : null;
            }
            if (isset($columns['rankid'])) {
                $record->rankid = !empty($data->rankid) ? (int)$data->rankid : null;
            }
        }
    }

    // BMI auto-calc.
    if (!empty($record->berat_kg) && !empty($record->tinggi_m) && $record->tinggi_m > 0) {
        $calc = round($record->berat_kg / ($record->tinggi_m * $record->tinggi_m), 1);
        if (empty($record->bmi) || abs($record->bmi - $calc) >= 0.2) {
            $record->bmi = $calc;
        }
    }
    
    // Save photo dates from Uploads tab.
    if (isset($data->photoselfdate) && is_numeric($data->photoselfdate)) {
        $record->photoselfdate = (int)$data->photoselfdate;
    }
    if (isset($data->photospousedate) && is_numeric($data->photospousedate)) {
        $record->photospousedate = (int)$data->photospousedate;
    }

    // Insert/update main record.
    if (empty($record->id)) {
        $record->timecreated = $now;
        if ($main) {
            $record->id = $main->id;
        }
        if (empty($record->id)) {
            $record->id = $DB->insert_record('local_studentinfo', $record);
        }
    } else {
        $DB->update_record('local_studentinfo', $record);
    }
    // File save options.
    $fileoptions_pdf  = ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['.pdf']];
    $fileoptions_img  = ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['.jpg', '.jpeg', '.png']];
    $fileoptions_mixed= ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['.pdf', '.jpg', '.jpeg', '.png']];
    
    // Use user context to tie files to this user.
    $usercontext = context_user::instance($userid);
    
    // 1. Passport photo (self) – also Moodle profile pic.
    /*
    if (!empty($data->photoself_file)) {
        // Save as profile picture (standard Moodle).
        process_new_icon($usercontext, $data->photoself_file);
    
        // Optionally, also keep a copy in local_studentinfo area (if you want).
        // file_save_draft_area_files(
        //     $data->photoself_file,
        //     $usercontext->id,
        //     'local_studentinfo',
        //     'photoself',
        //     $record->id,
        //     $fileoptions_img
        // );
    }
    */
    
    // 1. Passport photo (self) – store in plugin file area for now.
    if (!empty($data->photoself_file)) {
        file_save_draft_area_files(
            $data->photoself_file,
            $usercontext->id,
            'local_studentinfo',
            'photoself',
            $record->id,
            $fileoptions_img
        );
    }

    // 2. Passport photo (spouse).
    if (!empty($data->photospouse_file)) {
        file_save_draft_area_files(
            $data->photospouse_file,
            $usercontext->id,
            'local_studentinfo',
            'photospouse',
            $record->id,
            $fileoptions_img
        );
    }
    
    // 3. Course attendance letter.
    if (!empty($data->doc_courseletter_file)) {
        file_save_draft_area_files(
            $data->doc_courseletter_file,
            $usercontext->id,
            'local_studentinfo',
            'courseletter',
            $record->id,
            $fileoptions_pdf
        );
    }
    
    // 4. CO confirmation letter.
    if (!empty($data->doc_co_letter_file)) {
        file_save_draft_area_files(
            $data->doc_co_letter_file,
            $usercontext->id,
            'local_studentinfo',
            'coletter',
            $record->id,
            $fileoptions_pdf
        );
    }
    
    // 5. Personal particulars.
    if (!empty($data->doc_personal_part_file)) {
        file_save_draft_area_files(
            $data->doc_personal_part_file,
            $usercontext->id,
            'local_studentinfo',
            'personalpart',
            $record->id,
            $fileoptions_pdf
        );
    }
    
    // 6. Health report (BMI).
    if (!empty($data->doc_health_bmi_file)) {
        file_save_draft_area_files(
            $data->doc_health_bmi_file,
            $usercontext->id,
            'local_studentinfo',
            'healthbmi',
            $record->id,
            $fileoptions_pdf
        );
    }
    
    // 7. BAT 118A.
    if (!empty($data->doc_bat118a_file)) {
        file_save_draft_area_files(
            $data->doc_bat118a_file,
            $usercontext->id,
            'local_studentinfo',
            'bat118a',
            $record->id,
            $fileoptions_pdf
        );
    }
    
    // 8. MyTentera.
    if (!empty($data->doc_mytentera_file)) {
        file_save_draft_area_files(
            $data->doc_mytentera_file,
            $usercontext->id,
            'local_studentinfo',
            'mytentera',
            $record->id,
            $fileoptions_mixed
        );
    }
    
    // 9. Pregnancy letter (women).
    if (!empty($data->doc_pregnancy_file)) {
        file_save_draft_area_files(
            $data->doc_pregnancy_file,
            $usercontext->id,
            'local_studentinfo',
            'pregnancy',
            $record->id,
            $fileoptions_pdf
        );
    }

    $ouid       = optional_param('ou', 0, PARAM_INT);
    $currenttab = optional_param('tab', 'sec_identity', PARAM_ALPHANUMEXT);
    
    // Save and View: save already done above, just go to view.php.
    if (!empty($data->saveandview)) {
        redirect(
            new moodle_url('/local/studentinfo/view.php', [
                'userid' => $userid,
                'ou'     => $ouid,
            ]),
            get_string('savechanges', 'local_studentinfo'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        // Default Save (Save and stay) → reload same edit page/tab.
        redirect(
            new moodle_url('/local/studentinfo/edit.php', [
                'userid' => $userid,
                'ou'     => $ouid,
                'tab'    => $currenttab,
            ]),
            get_string('savechanges', 'local_studentinfo'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    // Decide what to do based on our two global buttons.
    $ouid = optional_param('ou', 0, PARAM_INT);

    if (!empty($data->saveandview)) {
        // Save and View
        redirect(
            new moodle_url('/local/studentinfo/view.php', [
                'userid' => $userid,
                'ou'     => $ouid
            ]),
            get_string('savechanges','local_studentinfo')
        );

    } else if (!empty($data->saveandcontinue)) {
        // Save and Continue ★ move to next tab.
        $order = [
            'sec_identity',
            'sec_bio',
            'sec_contact',
            'sec_perf',
            'sec_passport',
            'sec_academic',
            'sec_language',
            'sec_family',
            'sec_courses',
            'sec_ranks',
            'sec_postings',
            'sec_awards',
            'sec_insurance',
        ];

        // Current tab: try activetab from form, fallback to URL.
        $currenttab = !empty($data->activetab)
            ? $data->activetab
            : optional_param('tab', 'sec_identity', PARAM_ALPHANUMEXT);

        $currentindex = array_search($currenttab, $order);
        $nexttab = $currenttab;
        if ($currentindex !== false && isset($order[$currentindex + 1])) {
            $nexttab = $order[$currentindex + 1];
        }

        redirect(
            new moodle_url('/local/studentinfo/edit.php', [
                'userid' => $userid,
                'ou'     => $ouid,
                'tab'    => $nexttab
            ]),
            get_string('savechanges','local_studentinfo')
        );

    } else {
        // Fallback: behave like old save (go to view).
        redirect(
            new moodle_url('/local/studentinfo/view.php', [
                'userid' => $userid,
                'ou'     => $ouid
            ]),
            get_string('savechanges','local_studentinfo')
        );
    }
}


/* ===================== RENDER ===================== */
echo $OUTPUT->header();

// --- One-time modal AFTER header so Bootstrap styles apply.
if (!empty($SESSION->studentgate_showpopup)) {
    ?>
    <div class="modal show" tabindex="-1" role="dialog" style="display:block;background:rgba(0,0,0,.35)">
      <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:12px">
          <div class="modal-header">
            <h5 class="modal-title">Lengkapkan Maklumat Pelajar</h5>
          </div>
          <div class="modal-body">
            <p>Sebelum anda boleh menggunakan portal, sila lengkapkan maklumat pelajar anda.</p>
          </div>
          <div class="modal-footer">
            <a class="btn btn-primary" href="<?php echo (new \moodle_url('/local/studentinfo/edit.php', ['userid'=>$USER->id])); ?>">Lengkapkan Sekarang</a>
          </div>
        </div>
      </div>
    </div>
    <?php
    $SESSION->studentgate_showpopup = 0; // show once
}
?>
<h3 class="mb-3" style="font-weight:600;">
  <span>No Tentera:</span> <?php echo s($main->tentera_no ?? '-'); ?>
  <br><span>Pangkat:</span> <?php echo s($rankname ?? '-'); ?>
  <br><span>Nama:</span> <?php echo s($user->firstname).' &nbsp;'.s($user->lastname); ?>
</h3>
<?php

// ====== Enrolment summary: College (OU), Faculty, Intake ======
$ouname   = '-';
$facname  = '-';
$intname  = '-';

// Use local_studentinfo_studentmap as the anchor: userid = user.id
if ($DB->get_manager()->table_exists('local_studentinfo_studentmap')) {
    $map = $DB->get_record('local_studentinfo_studentmap',
        ['userid' => $userid], '*', IGNORE_MISSING);

    if ($map) {
        // OU (College): local_organization_ou.id = map.ouid
        if (!empty($map->ouid)
            && $DB->get_manager()->table_exists('local_organization_ou')) {

            $ourec = $DB->get_record('local_organization_ou',
                ['id' => $map->ouid], 'fullname', IGNORE_MISSING);
            if ($ourec) {
                $ouname = $ourec->fullname;
            }
        }

        // Faculty: local_ouadmin_faculty.id = map.facultyid
        if (!empty($map->facultyid)
            && $DB->get_manager()->table_exists('local_ouadmin_faculty')) {

            $facrec = $DB->get_record('local_ouadmin_faculty',
                ['id' => $map->facultyid], 'name', IGNORE_MISSING);
            if ($facrec) {
                $facname = $facrec->name;
            }
        }

        // Intake: local_ouadmin_intake.id = map.intakeid
        if (!empty($map->intakeid)
            && $DB->get_manager()->table_exists('local_ouadmin_intake')) {

            $intrec = $DB->get_record('local_ouadmin_intake',
                ['id' => $map->intakeid], 'name', IGNORE_MISSING);
            if ($intrec) {
                $intname = $intrec->name;
            }
        }
    }
}

// Print summary line under header, above the tabs.
echo html_writer::tag(
    'h4',
    'College (OU): ' . s($ouname) . ' &nbsp; | &nbsp; ' .
    'Faculty: ' . s($facname) . ' &nbsp; | &nbsp; ' .
    'Intake (cohort): ' . s($intname),
    ['class' => 'mb-3']
);
echo '<hr>';

// Preload for form defaults
$set = new \stdClass();
if ($main) { foreach ($main as $k=>$v) { $set->$k=$v; } }
$set->email_display = $user->email;

// Prefill Akademik row
if (!empty($acad_editrec)) {
    $set->acad_id        = $acad_editrec->id;
    $set->acad_tahun     = $acad_editrec->tahun;
    $set->acad_tahap     = $acad_editrec->tahap;
    $set->acad_kelulusan = $acad_editrec->kelulusan;
}
// Prefill Bahasa row
if (!empty($lang_editrec)) {
    $set->lang_id     = $lang_editrec->id;
    $set->lang_bahasa = $lang_editrec->bahasa;
    $set->lang_baca   = $lang_editrec->baca;
    $set->lang_lisan  = $lang_editrec->lisan;
    $set->lang_tulis  = $lang_editrec->tulis;
}
// Prefill Waris row
if (!empty($fam_editrec)) {
    $set->fam_id           = $fam_editrec->id;
    $set->fam_hubungan     = $fam_editrec->hubungan;
    $set->fam_nama         = $fam_editrec->nama;
    $set->fam_ic           = $fam_editrec->ic;
    $set->fam_telefon      = $fam_editrec->telefon;
    $set->fam_tarikh_lahir = $fam_editrec->tarikh_lahir;
}
// Prefill Kursus row
if (!empty($course_editrec)) {
    $set->course_id        = $course_editrec->id;
    $set->kursus_nama      = $course_editrec->nama;
    $set->kursus_tempat    = $course_editrec->tempat;
    $set->kursus_mula      = $course_editrec->mula;
    $set->kursus_tamat     = $course_editrec->tamat;
    $set->kursus_keputusan = $course_editrec->keputusan;
}
// Prefill Pangkat row
if (!empty($rank_editrec)) {
    $set->rank_id                 = $rank_editrec->id;
    $set->rank_pangkat            = $rank_editrec->pangkat;
    $set->rank_tarikh             = $rank_editrec->tarikh;
    $set->rank_kekananan          = $rank_editrec->kekananan;
    $set->rank_tarikh_kekananan   = $rank_editrec->tarikh_kekananan;
}
// Prefill Pertukaran row
if (!empty($post_editrec)) {
    $set->post_id          = $post_editrec->id;
    $set->posting_jawatan  = $post_editrec->jawatan;
    $set->posting_pasukan  = $post_editrec->pasukan;
    $set->posting_negeri   = $post_editrec->negeri;
    $set->posting_mula     = $post_editrec->mula;
    $set->posting_tamat    = $post_editrec->tamat;
}
// Prefill Pingat row
if (!empty($award_editrec)) {
    $set->award_id        = $award_editrec->id;
    $set->award_nama      = $award_editrec->nama;
    $set->award_singkatan = $award_editrec->singkatan;
    $set->award_gelaran   = $award_editrec->gelaran;
    $set->award_tarikh    = $award_editrec->tarikh;
}
// Prefill Insuran row
if (!empty($ins_editrec)) {
    $set->ins_id          = $ins_editrec->id;
    $set->ins_penyedia    = $ins_editrec->penyedia;
    $set->ins_jumlah_unit = $ins_editrec->jumlah_unit;
    $set->ins_no_polis    = $ins_editrec->no_polis;
}

/* ==== Prefill selects from saved IDs (serviceid/korid/rankid) ==== */

// If $main is loaded from local_studentinfo earlier, use IDs directly.
// $set is the object you pass into set_data().

if (!empty($main)) {
    // Rank
    if (property_exists($main, 'rankid') && !empty($main->rankid)) {
        $set->pangkat_sel = (int)$main->rankid;
    }

    // Service
    if (property_exists($main, 'serviceid') && !empty($main->serviceid)) {
        $set->perkhidmatan_sel = (int)$main->serviceid;
    }

    // Kor/Regiment
    if (property_exists($main, 'korid') && !empty($main->korid)) {
        $set->rejimen_sel = (int)$main->korid;
    }

    // Intake and commission type still use your existing fields.
    if (!empty($main->pengambilan)) {
        $set->pengambilan_sel = (int)$main->pengambilan ?: '';
    }
    if (!empty($main->jenis_tauliah)) {
        $set->jenis_tauliah_sel = (string)$main->jenis_tauliah;
    }
}
/* ==== /Prefill selects ==== */


// Pass defaults into form
$mform->set_data($set);

/* ===================== NATIVE TABS (no JS) ===================== */

// Define your tabs (key => label). Adjust labels as needed.
// Tab labels (key => label) – now from language strings (English).
$tabdefs = [
    'sec_identity'  => get_string('sec_identity',  'local_studentinfo'),
    'sec_bio'       => get_string('sec_bio',       'local_studentinfo'),
    'sec_contact'   => get_string('sec_contact',   'local_studentinfo'),
    'sec_perf'      => get_string('sec_perf',      'local_studentinfo'),
    'sec_passport'  => get_string('sec_passport',  'local_studentinfo'),
    'sec_academic'  => get_string('sec_academic',  'local_studentinfo'),
    'sec_language'  => get_string('sec_language',  'local_studentinfo'),
    'sec_family'    => get_string('sec_family',    'local_studentinfo'),
    'sec_courses'   => get_string('sec_courses',   'local_studentinfo'),
    'sec_ranks'     => get_string('sec_ranks',     'local_studentinfo'),
    'sec_postings'  => get_string('sec_postings',  'local_studentinfo'),
    'sec_awards'    => get_string('sec_awards',    'local_studentinfo'),
    'sec_insurance' => get_string('sec_insurance', 'local_studentinfo'),
    'sec_uploads'   => get_string('sec_uploads',   'local_studentinfo'),
];


$tabs = [];
foreach ($tabdefs as $key => $label) {
    $tabs[] = new \tabobject(
        $key,
        new \moodle_url('/local/studentinfo/edit.php', ['userid' => $userid, 'tab' => $key]),
        $label
    );
}
echo $OUTPUT->tabtree($tabs, $activetab);

// Show ONLY the active fieldset, and force-expand any collapsibles inside it.
$activecssid = 'id_' . $activetab;

// CSS: hide others; show active; expand Moodle collapsibles + Bootstrap collapse inside active.
echo '<style>
  .mform fieldset[id^="id_sec_"]{display:none}
  .mform #' . s($activecssid) . '{display:block}

  /* Force-open Moodle legacy collapsibles inside the active fieldset */
  .mform #' . s($activecssid) . ' fieldset.collapsible .fcontainer{display:block !important}
  .mform #' . s($activecssid) . ' fieldset.collapsible{padding-bottom:.25rem}
  .mform #' . s($activecssid) . ' .collapsible-actions{display:none !important}

  /* Force-open Bootstrap-style collapse blocks inside the active fieldset */
  .mform #' . s($activecssid) . ' .collapse{display:block !important; height:auto !important; visibility:visible !important; opacity:1 !important;}
</style>';

// JS: remove "collapsed" class and fix aria on load (covers cases with inline styles).
echo '<script>
document.addEventListener("DOMContentLoaded", function(){
  var root = document.querySelector(".mform #' . $activecssid . '");
  if(!root) return;

  // Legacy Moodle collapsibles (fieldset.collapsible)
  root.querySelectorAll("fieldset.collapsible").forEach(function(fs){
    fs.classList.remove("collapsed");
    var tog = fs.querySelector(".ftoggler a, legend a");
    if (tog) { tog.setAttribute("aria-expanded","true"); }
    var cont = fs.querySelector(".fcontainer");
    if (cont) { cont.style.display = "block"; cont.style.height = "auto"; }
  });

  // Bootstrap collapse areas inside the active section
  root.querySelectorAll(".collapse").forEach(function(el){
    el.classList.add("show");
    el.style.display = "block";
    el.style.height = "auto";
  });
});
</script>';

// Hide all fieldsets except the active one (by fieldset id).


/* ===================== DISPLAY FORM ===================== */
$mform->display();

echo $OUTPUT->footer();
