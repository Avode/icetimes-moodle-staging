<?php
namespace local_studentinfo\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/filelib.php');


class edit_form extends \moodleform {


    public function definition() {
        
        
        global $DB;
        $mform = $this->_form;
        $cd    = $this->_customdata ?? [];
        $user  = $cd['user'] ?? null;
        $ouid       = $cd['ouid']       ?? 0;
        $studentmap = $cd['studentmap'] ?? null;
        $editingexisting = !empty($user) && !empty($user->id);
        //print_object($cd['studentinfo']); die;
        
        $studentinfo = $cd['studentinfo'] ?? null;
        $studentid   = $studentinfo->id ?? 0;
        
        $fs          = get_file_storage();
        $usercontext = \context_user::instance($user->id);

        // Preload lookup data for service structure.
        $typelist   = [];
        $servicelist= [];
        $korlist    = [];
        $ranklist   = [];
        
        if ($DB->get_manager()->table_exists('local_studentlookup_type')) {
            $typelist = $DB->get_records('local_studentlookup_type', null, 'sortorder ASC', 'id, name');
        }
        
        if ($DB->get_manager()->table_exists('local_studentlookup_service')) {
            $servicelist = $DB->get_records('local_studentlookup_service', null, 'sortorder ASC', 'id, studenttypeid, name');
        }
        
        if ($DB->get_manager()->table_exists('local_studentlookup_korregimen')) {
            $korlist = $DB->get_records('local_studentlookup_korregimen', null, 'sortorder ASC', 'id, serviceid, name');
        }
        
        // Optional rank table; if you don’t have it yet, you can comment this block.
        if ($DB->get_manager()->table_exists('local_studentlookup_rank')) {
            $ranklist = $DB->get_records('local_studentlookup_rank', null, 'ranklevel ASC', 'id, serviceid, name');
        }
        
        // Build options for selects.
        $typeoptions = [0 => get_string('select')];
        foreach ($typelist as $t) {
            $typeoptions[$t->id] = $t->name;
        }
        
        $serviceoptions = [0 => get_string('select')]; // will be filtered via JS
        $koroptions     = [0 => get_string('select')]; // filtered via JS
        $rankoptions    = [0 => get_string('select')]; // filtered via JS
        
        // Prepare JS data arrays.
        $servicesjs = [];
        foreach ($servicelist as $s) {
            $servicesjs[] = [
                'id'           => (int)$s->id,
                'studenttypeid'=> (int)$s->studenttypeid,
                'name'         => $s->name,
            ];
        }
        $korjs = [];
        foreach ($korlist as $k) {
            $korjs[] = [
                'id'        => (int)$k->id,
                'serviceid' => (int)$k->serviceid,
                'name'      => $k->name,
            ];
        }
        $rankjs = [];
        foreach ($ranklist as $r) {
            $rankjs[] = [
                'id'        => (int)$r->id,
                'serviceid' => (int)$r->serviceid,
                'name'      => $r->name,
            ];
        }
        
        $servicesjson = json_encode($servicesjs);
        $korjson      = json_encode($korjs);
        $rankjson     = json_encode($rankjs);


        // Existing child data (Akademik & Bahasa).
        $acad         = $cd['acad_list']     ?? [];
        $acad_editrec = $cd['acad_editrec']  ?? null;
        $lang         = $cd['lang_list']     ?? [];
        $lang_editrec = $cd['lang_editrec']  ?? null;

        // NEW child data (Waris, Kursus, Pangkat, Pertukaran, Pingat, Insuran).
        $fam_list       = $cd['fam_list']        ?? [];
        $fam_editrec    = $cd['fam_editrec']     ?? null;
        $course_list    = $cd['course_list']     ?? [];
        $course_editrec = $cd['course_editrec']  ?? null;
        $rank_list      = $cd['rank_list']       ?? [];
        $rank_editrec   = $cd['rank_editrec']    ?? null;
        $post_list      = $cd['post_list']       ?? [];
        $post_editrec   = $cd['post_editrec']    ?? null;
        $award_list     = $cd['award_list']      ?? [];
        $award_editrec  = $cd['award_editrec']   ?? null;
        $ins_list       = $cd['ins_list']        ?? [];
        $ins_editrec    = $cd['ins_editrec']     ?? null;

        
        // ---------------------------------------------------------------------
        // Hidden keys + active tab
        // ---------------------------------------------------------------------
        $mform->addElement('hidden', 'userid', $user->id ?? 0);
        $mform->setType('userid', PARAM_INT);

        $mform->addElement('hidden', 'id'); // local_studentinfo.id (if exists)
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'activetab', 'sec_identity'); // default first tab
        $mform->setType('activetab', PARAM_ALPHANUMEXT);

        // ---------------------------------------------------------------------
        // Identiti
        // ---------------------------------------------------------------------
        $mform->addElement('header', 'sec_identity', get_string('sec_identity', 'local_studentinfo'));
                // ==== Service Data: Service No, Student Type, Service, Regiment, Rank ====

        // Service Number (No. Tentera)
        $mform->addElement('text', 'tentera_no', get_string('tentera_no', 'local_studentinfo'));
        $mform->setType('tentera_no', PARAM_NOTAGS);

        // Student Type (FK -> local_studentlookup_type.id)
        $mform->addElement('select', 'studenttypeid', 'Student Type', $typeoptions);
        $mform->setType('studenttypeid', PARAM_INT);

        // Service – options will be filtered by Student Type via JS.
        $mform->addElement('select', 'serviceid', 'Service', $serviceoptions);
        $mform->setType('serviceid', PARAM_INT);

        // Regiment / Kor / Branch – filtered by Service.
        $mform->addElement('select', 'korid', 'Regiment / Kor / Branch', $koroptions);
        $mform->setType('korid', PARAM_INT);

        // Rank – filtered by Service (if you have local_studentlookup_rank).
        $mform->addElement('select', 'rankid', get_string('pangkat', 'local_studentinfo'), $rankoptions);
        $mform->setType('rankid', PARAM_INT);

        // Pre-fill defaults for existing student if data was passed in customdata.
        if (!empty($cd['studentinfo'])) {
            $si = $cd['studentinfo'];
            $mform->setDefault('tentera_no',    $si->tentera_no ?? '');
            $mform->setDefault('studenttypeid', $si->studenttypeid ?? 0);
            $mform->setDefault('serviceid',     $si->serviceid ?? 0);
            $mform->setDefault('korid',         $si->korid ?? 0);
            if (property_exists($si, 'rankid')) {
                $mform->setDefault('rankid', $si->rankid ?? 0);
            }
        }
        
        $curtypeid    = 0;
        $curserviceid = 0;
        $curkorid     = 0;
        $currankid    = 0;
        
        if (!empty($cd['studentinfo'])) {
            $si = $cd['studentinfo'];
            $curtypeid    = (int)($si->studenttypeid ?? 0);
            $curserviceid = (int)($si->serviceid     ?? 0);
            $curkorid     = (int)($si->korid         ?? 0);
            if (property_exists($si, 'rankid')) {
                $currankid = (int)($si->rankid ?? 0);
            }
        }


        // JS: Student Type → Service; Service → Regiment & Rank.
        $js = "
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var typeSelect    = document.querySelector('select[name=\"studenttypeid\"]');
            var serviceSelect = document.querySelector('select[name=\"serviceid\"]');
            var korSelect     = document.querySelector('select[name=\"korid\"]');
            var rankSelect    = document.querySelector('select[name=\"rankid\"]');
        
            if (!typeSelect || !serviceSelect || !korSelect || !rankSelect) { return; }
        
            var services = {$servicesjson} || [];
            var kors     = {$korjson}      || [];
            var ranks    = {$rankjson}     || [];
        
            var currentTypeId    = " . $curtypeid    . ";
            var currentServiceId = " . $curserviceid . ";
            var currentKorId     = " . $curkorid     . ";
            var currentRankId    = " . $currankid    . ";
        
            function rebuildServices() {
                var stid = parseInt(typeSelect.value || currentTypeId, 10) || 0;
        
                serviceSelect.innerHTML = '';
                var o0 = document.createElement('option');
                o0.value = '';
                o0.textContent = '" . addslashes(get_string('select')) . "';
                serviceSelect.appendChild(o0);
        
                services.forEach(function(s) {
                    if (!stid || s.studenttypeid === stid) {
                        var o = document.createElement('option');
                        o.value = s.id;
                        o.textContent = s.name;
                        serviceSelect.appendChild(o);
                    }
                });
        
                // After building, explicitly select saved service if still visible.
                if (currentServiceId) {
                    serviceSelect.value = String(currentServiceId);
                    if (serviceSelect.value !== String(currentServiceId)) {
                        // If not found under current type, reset.
                        currentServiceId = 0;
                    }
                }
        
                rebuildKors();
                rebuildRanks();
            }
        
            function rebuildKors() {
                var sid = parseInt(serviceSelect.value || currentServiceId, 10) || 0;
                korSelect.innerHTML = '';
                var o0 = document.createElement('option');
                o0.value = '';
                o0.textContent = '" . addslashes(get_string('select')) . "';
                korSelect.appendChild(o0);
        
                kors.forEach(function(k) {
                    if (!sid || k.serviceid === sid) {
                        var o = document.createElement('option');
                        o.value = k.id;
                        o.textContent = k.name;
                        korSelect.appendChild(o);
                    }
                });
        
                if (currentKorId) {
                    korSelect.value = String(currentKorId);
                    if (korSelect.value !== String(currentKorId)) {
                        currentKorId = 0;
                    }
                }
            }
        
            function rebuildRanks() {
                var sid = parseInt(serviceSelect.value || currentServiceId, 10) || 0;
                rankSelect.innerHTML = '';
                var o0 = document.createElement('option');
                o0.value = '';
                o0.textContent = '" . addslashes(get_string('select')) . "';
                rankSelect.appendChild(o0);
        
                ranks.forEach(function(r) {
                    if (!sid || r.serviceid === sid) {
                        var o = document.createElement('option');
                        o.value = r.id;
                        o.textContent = r.name;
                        rankSelect.appendChild(o);
                    }
                });
        
                if (currentRankId) {
                    rankSelect.value = String(currentRankId);
                    if (rankSelect.value !== String(currentRankId)) {
                        currentRankId = 0;
                    }
                }
            }
        
            // Initial load: set type select, then build.
            if (currentTypeId) {
                typeSelect.value = String(currentTypeId);
            }
        
            typeSelect.addEventListener('change', function() {
                currentTypeId    = parseInt(typeSelect.value, 10) || 0;
                currentServiceId = 0;
                currentKorId     = 0;
                currentRankId    = 0;
                rebuildServices();
            });
        
            serviceSelect.addEventListener('change', function() {
                currentServiceId = parseInt(serviceSelect.value, 10) || 0;
                currentKorId     = 0;
                currentRankId    = 0;
                rebuildKors();
                rebuildRanks();
            });
        
            // Kick off initial population & selection.
            rebuildServices();
        });
        </script>
        ";
        $mform->addElement('html', $js);




        $yearfrom = 1980; $yearto = (int)date('Y') + 1;
        $years = ['' => '- '.get_string('col_year','local_studentinfo').' -']; for ($y=$yearto; $y >= $yearfrom; $y--) { $years[$y] = (string)$y; }
        $mform->addElement('select', 'pengambilan_sel', get_string('pengambilan','local_studentinfo'), $years);
        $mform->setType('pengambilan_sel', PARAM_INT);

        $jenisopts = ['' => '- '.get_string('select','local_studentinfo').' -', 'Tetap'=>'Tetap', 'Jangka Pendek'=>'Jangka Pendek'];
        $mform->addElement('select', 'jenis_tauliah_sel', get_string('jenis_tauliah','local_studentinfo'), $jenisopts);
        $mform->setType('jenis_tauliah_sel', PARAM_TEXT);

        $mform->addElement('date_selector', 'tarikh_masuk', get_string('tarikh_masuk','local_studentinfo'));
        $mform->addElement('date_selector', 'tarikh_tauliah', get_string('tarikh_tauliah','local_studentinfo'));
        $mform->addElement('date_selector', 'tarikh_tamat', get_string('tarikh_tamat','local_studentinfo'));

        // Mirrors (server-side also resolves).
        $mform->addElement('hidden', 'pangkat');       $mform->setType('pangkat', PARAM_TEXT);
        $mform->addElement('hidden', 'perkhidmatan');  $mform->setType('perkhidmatan', PARAM_TEXT);
        $mform->addElement('hidden', 'rejimen');       $mform->setType('rejimen', PARAM_TEXT);
        $mform->addElement('hidden', 'pengambilan');   $mform->setType('pengambilan', PARAM_TEXT);
        $mform->addElement('hidden', 'jenis_tauliah'); $mform->setType('jenis_tauliah', PARAM_TEXT);

        
        // ---------------------------------------------------------------------
        // DKT/Bio
        // ---------------------------------------------------------------------
        $mform->addElement('header', 'sec_bio', get_string('sec_bio', 'local_studentinfo'));

        $mform->addElement('date_selector', 'tarikh_lahir', get_string('tarikh_lahir','local_studentinfo'));
        $states=[
            '' => '- '.get_string('select','local_studentinfo').' -','Johor'=>'Johor','Kedah'=>'Kedah','Kelantan'=>'Kelantan','Melaka'=>'Melaka','Negeri Sembilan'=>'Negeri Sembilan',
            'Pahang'=>'Pahang','Perak'=>'Perak','Perlis'=>'Perlis','Pulau Pinang'=>'Pulau Pinang','Sabah'=>'Sabah','Sarawak'=>'Sarawak','Selangor'=>'Selangor',
            'Terengganu'=>'Terengganu','Wilayah Persekutuan Kuala Lumpur'=>'Wilayah Persekutuan Kuala Lumpur','Wilayah Persekutuan Putrajaya'=>'Wilayah Persekutuan Putrajaya',
            'Wilayah Persekutuan Labuan'=>'Wilayah Persekutuan Labuan','Luar Malaysia'=>'Luar Malaysia'
        ];
        $mform->addElement('select', 'tempat_lahir', get_string('tempat_lahir','local_studentinfo'), $states); $mform->setType('tempat_lahir', PARAM_TEXT);

        $mform->addElement('float', 'berat_kg', get_string('berat_kg','local_studentinfo')); $mform->setType('berat_kg', PARAM_RAW);
        $mform->addElement('float', 'tinggi_m', get_string('tinggi_m','local_studentinfo')); $mform->setType('tinggi_m', PARAM_RAW);
        $mform->addElement('float', 'bmi', get_string('bmi','local_studentinfo'));           $mform->setType('bmi', PARAM_RAW);

        $mform->addElement('select', 'darah', get_string('darah','local_studentinfo'), [
            '' => '- '.get_string('select','local_studentinfo').' -','O+'=>'O+','O-'=>'O-','A+'=>'A+','A-'=>'A-','B+'=>'B+','B-'=>'B-','AB+'=>'AB+','AB-'=>'AB-'
        ]); $mform->setType('darah', PARAM_TEXT);

        $mform->addElement('select', 'bangsa', get_string('bangsa','local_studentinfo'), [
            '' => '- '.get_string('select','local_studentinfo').' -','Melayu'=>'Melayu','Cina'=>'Cina','India'=>'India','Iban'=>'Iban','Kadazan-Dusun'=>'Kadazan-Dusun','Bidayuh'=>'Bidayuh','Melanau'=>'Melanau',
            'Bajau'=>'Bajau','Orang Asli'=>'Orang Asli','Bugis'=>'Bugis','Jawa'=>'Jawa','Sino-Native'=>'Sino-Native','Peranakan'=>'Peranakan','Lain-lain'=>'Lain-lain'
        ]); $mform->setType('bangsa', PARAM_TEXT);

        $mform->addElement('select', 'agama', get_string('agama','local_studentinfo'), [
            '' => '- '.get_string('select','local_studentinfo').' -','Islam'=>'Islam','Buddha'=>'Buddha','Kristian'=>'Kristian','Hindu'=>'Hindu','Sikh'=>'Sikh','Tao'=>'Tao','Konghucu'=>'Konghucu','Lain-lain'=>'Lain-lain'
        ]); $mform->setType('agama', PARAM_TEXT);

        $mform->addElement('select', 'warganegara', get_string('warganegara','local_studentinfo'),
            ['Malaysia'=>'Malaysia','Bukan Warganegara'=>'Bukan Warganegara']); $mform->setDefault('warganegara','Malaysia'); $mform->setType('warganegara', PARAM_TEXT);

        $mform->addElement('select', 'taraf_kahwin', get_string('taraf_kahwin','local_studentinfo'),
            ['' => '- '.get_string('select','local_studentinfo').' -','Berkahwin'=>'Berkahwin','Bujang'=>'Bujang','Duda'=>'Duda','Janda'=>'Janda','Balu'=>'Balu']); $mform->setType('taraf_kahwin', PARAM_TEXT);

        $mform->addElement('html','<script>(function(){const k=document.getElementById("id_berat_kg"),t=document.getElementById("id_tinggi_m"),b=document.getElementById("id_bmi");
        function c(){const w=parseFloat(k&&k.value||""),h=parseFloat(t&&t.value||"");if(!isNaN(w)&&!isNaN(h)&&h>0){const v=w/(h*h);if(b)b.value=(Math.round(v*10)/10).toString();}}
        if(k)k.addEventListener("input",c); if(t)t.addEventListener("input",c);})();</script>');

        // ---------------------------------------------------------------------
        // Kontak
        // ---------------------------------------------------------------------
        $mform->addElement('header', 'sec_contact', get_string('sec_contact', 'local_studentinfo'));
        $mform->addElement('text', 'telefon', get_string('telefon','local_studentinfo')); $mform->setType('telefon', PARAM_TEXT);
        $mform->addElement('text', 'email_display', get_string('email','local_studentinfo'));
        $mform->setType('email_display', PARAM_NOTAGS);
        $mform->freeze('email_display');

        // ---------------------------------------------------------------------
        // Prestasi
        // ---------------------------------------------------------------------
        $mform->addElement('header', 'sec_perf', get_string('sec_perf', 'local_studentinfo'));
        $mform->addElement('html','<div class="card mb-3"><div class="card-header"><strong>BAT D11</strong></div><div class="card-body"><div class="container-fluid"><div class="row">');
        $batyears=[''=>'- '.get_string('col_year','local_studentinfo').' -']; for($y=(int)date('Y')+1;$y>=1980;$y--){$batyears[$y]=(string)$y;}
        $mform->addElement('html','<div class="col-md-6">');
        $mform->addElement('select','batd11_tahun',get_string('col_year','local_studentinfo'),$batyears); $mform->setType('batd11_tahun',PARAM_INT);
        $mform->addElement('html','</div><div class="col-md-6">');
        $mform->addElement('text','batd11_nilaian',get_string('evaluation','local_studentinfo'),['size'=>8]); $mform->setType('batd11_nilaian',PARAM_RAW);
        $mform->addElement('html','</div></div></div></div>');

        $mform->addElement('html','<div class="card mb-3"><div class="card-header"><strong>ADFELPS</strong></div><div class="card-body"><div class="container-fluid"><div class="row">');
        $scoreopts=[]; for($i=0;$i<=10;$i++){$scoreopts[$i]=(string)$i;}
        $mform->addElement('html','<div class="col-md-6">');
        $mform->addElement('select','adfelps_listening','Listening',$scoreopts); $mform->setType('adfelps_listening',PARAM_INT); $mform->setDefault('adfelps_listening',0);
        $mform->addElement('select','adfelps_speaking','Speaking',$scoreopts);   $mform->setType('adfelps_speaking',PARAM_INT);  $mform->setDefault('adfelps_speaking',0);
        $mform->addElement('html','</div><div class="col-md-6">');
        $mform->addElement('select','adfelps_reading','Reading',$scoreopts);     $mform->setType('adfelps_reading',PARAM_INT);   $mform->setDefault('adfelps_reading',0);
        $mform->addElement('select','adfelps_writing','Writing',$scoreopts);     $mform->setType('adfelps_writing',PARAM_INT);   $mform->setDefault('adfelps_writing',0);
        $mform->addElement('html','</div></div></div></div>');

        // ---------------------------------------------------------------------
        // Passport & Travel
        // ---------------------------------------------------------------------
        $mform->addElement('header', 'sec_passport', get_string('sec_passport', 'local_studentinfo'));

        // Passport Number
        $mform->addElement('text', 'passport_no', get_string('passport_no', 'local_studentinfo'));
        $mform->setType('passport_no', PARAM_NOTAGS);

        // Passport Expiry Date
        $mform->addElement('date_selector', 'passport_expiry', get_string('passport_expiry', 'local_studentinfo'));

        // Foreign countries visited
        $mform->addElement(
            'textarea',
            'negara_dilawati',
            get_string('negara_dilawati', 'local_studentinfo'),
            ['rows' => 3, 'cols' => 60]
        );
        $mform->setType('negara_dilawati', PARAM_TEXT);

        
        // ---------------------------------------------------------------------
        // AKADEMIK – list + single-row form
        // ---------------------------------------------------------------------
        $mform->addElement('header', 'sec_academic', get_string('sec_academic','local_studentinfo'));

        $tablehtml = '<div class="card mb-2"><div class="card-body py-2"><div class="table-responsive">
          <table class="table table-sm table-bordered mb-0">
            <thead class="thead-light"><tr>
    
            <th>'.get_string('col_year','local_studentinfo').'</th>
            <th>'.get_string('col_level','local_studentinfo').'</th>
            <th>'.get_string('col_qualification','local_studentinfo').'</th>
            <th>'.get_string('col_action','local_studentinfo').'</th>

            </tr></thead><tbody>';
        
        if ($acad) {
            foreach ($acad as $r) {
                $editurl = new \moodle_url('/local/studentinfo/edit.php', ['userid'=>$user->id, 'acadaction'=>'edit',   'acid'=>$r->id], 'pane_sec_academic');
                $delurl  = new \moodle_url('/local/studentinfo/edit.php', ['userid'=>$user->id, 'acadaction'=>'delete', 'acid'=>$r->id, 'sesskey'=>sesskey()], 'pane_sec_academic');
                $tablehtml .= '<tr>'.
                  '<td>'.s($r->tahun ?? '').'</td>'.
                  '<td>'.s($r->tahap ?? '').'</td>'.
                  '<td>'.s($r->kelulusan ?? '').'</td>'.
                  '<td><a class="btn btn-sm btn-outline-primary mr-1" href="'.$editurl.'">Edit</a>'.
                  '<a class="btn btn-sm btn-outline-danger" href="'.$delurl.'" onclick="return confirm(\'Confirm to delete?\')">Delete</a></td>'.
                '</tr>';
            }
        } else {
            $tablehtml .= '<tr><td colspan="4" class="text-center text-muted">
            '.get_string('norecords_academic','local_studentinfo').'
        </td>
        </tr>';
        }
        $tablehtml .= '</tbody></table></div></div></div>';
        $mform->addElement('html', $tablehtml);

        // Add/Update Akademik
        $mform->addElement('html','<div class="card mb-3"><div class="card-header"><strong>'.get_string('addupdateacademic', 'local_studentinfo').'</strong></div><div class="card-body"><div class="container-fluid"><div class="row">');
        $mform->addElement('hidden', 'acad_id', $acad_editrec->id ?? 0); $mform->setType('acad_id', PARAM_INT);
        $yearopts=[''=>'-'.get_string('col_year','local_studentinfo').'-']; for($y=(int)date('Y')+1;$y>=1980;$y--){$yearopts[$y]=(string)$y;}
        $tahapopts=[
          '' => '- '.get_string('col_level','local_studentinfo').' -','PHD/Doctorate'=>'PhD / Doctorate','Sarjana'=>'Sarjana','Sarjana Muda'=>'Sarjana Muda','Diploma'=>'Diploma',
          'STPM'=>'STPM','SPM'=>'SPM','SRP/PMR/PT3'=>'SRP / PMR / PT3','UPSR/Penilaian'=>'UPSR / Penilaian','Tidak Bersekolah'=>'Tidak Bersekolah'
        ];
        $mform->addElement('html','<div class="col-md-4"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('col_year','local_studentinfo').'</label>');
        $mform->addElement('select','acad_tahun','',$yearopts); $mform->setType('acad_tahun',PARAM_INT);
        $mform->addElement('html','</div></div><div class="col-md-4"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('col_level','local_studentinfo').'</label>');
        $mform->addElement('select','acad_tahap','',$tahapopts); $mform->setType('acad_tahap',PARAM_TEXT);
        $mform->addElement('html','</div></div><div class="col-md-4"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('col_qualification','local_studentinfo').'</label>');
        $mform->addElement('text','acad_kelulusan','',['size'=>40]); $mform->setType('acad_kelulusan',PARAM_TEXT);
        $mform->addElement('html','</div></div></div>');

        $btngrp = [];
        $btngrp[] = $mform->createElement('submit','acad_submit', empty($acad_editrec)?get_string('add','local_studentinfo'):get_string('update','local_studentinfo'));
        $btngrp[] = $mform->createElement('cancel','acad_cancel', get_string('clear','local_studentinfo'));
        $mform->addGroup($btngrp,'acad_actions',' ',' ',false);
        $mform->addElement('html','</div></div>');

        // ---------------------------------------------------------------------
        // BAHASA – list + single-row form
        // ---------------------------------------------------------------------
        $mform->addElement('header', 'sec_language', get_string('sec_language','local_studentinfo'));

        $langtable = '<div class="card mb-2"><div class="card-body py-2"><div class="table-responsive">
          <table class="table table-sm table-bordered mb-0">
            <thead class="thead-light"><tr>
              <th style="width:22%">'.get_string('language','local_studentinfo').'</th>
              <th style="width:16%">'.get_string('reading','local_studentinfo').'</th>
              <th style="width:16%">'.get_string('speaking','local_studentinfo').'</th>
              <th style="width:16%">'.get_string('writing','local_studentinfo').'</th>
              <th style="width:16%">'.get_string('col_action','local_studentinfo').'</th>
            </tr></thead><tbody>';
        if ($lang) {
            foreach ($lang as $r) {
                $editurl = new \moodle_url('/local/studentinfo/edit.php', ['userid'=>$user->id, 'langaction'=>'edit',   'lid'=>$r->id], 'pane_sec_language');
                $delurl  = new \moodle_url('/local/studentinfo/edit.php', ['userid'=>$user->id, 'langaction'=>'delete', 'lid'=>$r->id, 'sesskey'=>sesskey()], 'pane_sec_language');
                $langtable .= '<tr>'.
                  '<td>'.s($r->bahasa ?? '').'</td>'.
                  '<td>'.s($r->baca ?? '').'</td>'.
                  '<td>'.s($r->lisan ?? '').'</td>'.
                  '<td>'.s($r->tulis ?? '').'</td>'.
                  '<td><a class="btn btn-sm btn-outline-primary mr-1" href="'.$editurl.'">Edit</a>'.
                  '<a class="btn btn-sm btn-outline-danger" href="'.$delurl.'" onclick="return confirm(\'Confirm to delete?\')">Delete</a></td>'.
                '</tr>';
            }
        } else {
            $langtable .= '<tr><td colspan="5" class="text-center text-muted">Tiada rekod bahasa.</td></tr>';
        }
        $langtable .= '</tbody></table></div></div></div>';
        $mform->addElement('html', $langtable);

        // Add/Update Bahasa
        $mform->addElement('html','<div class="card mb-3"><div class="card-header"><strong>'.get_string('addupdatelanguage','local_studentinfo').'</strong></div><div class="card-body"><div class="container-fluid"><div class="row">');
        $mform->addElement('hidden', 'lang_id', $lang_editrec->id ?? 0); $mform->setType('lang_id', PARAM_INT);
        $levelopts = ['' => '- '.get_string('select','local_studentinfo').' -', 'FASIH'=>'FASIH', 'BAIK'=>'BAIK', 'LEMAH'=>'LEMAH', 'ASAS'=>'ASAS', 'TIADA'=>'TIADA'];
        $mform->addElement('html','<div class="col-md-3"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('language','local_studentinfo').'</label>');
        $mform->addElement('text','lang_bahasa','',['size'=>20]); $mform->setType('lang_bahasa',PARAM_TEXT);
        $mform->addElement('html','</div></div><div class="col-md-3"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('reading','local_studentinfo').'</label>');
        $mform->addElement('select','lang_baca','',$levelopts); $mform->setType('lang_baca',PARAM_TEXT);
        $mform->addElement('html','</div></div><div class="col-md-3"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('speaking','local_studentinfo').'</label>');
        $mform->addElement('select','lang_lisan','',$levelopts); $mform->setType('lang_lisan',PARAM_TEXT);
        $mform->addElement('html','</div></div><div class="col-md-3"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('writing','local_studentinfo').'</label>');
        $mform->addElement('select','lang_tulis','',$levelopts); $mform->setType('lang_tulis',PARAM_TEXT);
        $mform->addElement('html','</div></div>');

        $btn2=[]; $btn2[]=$mform->createElement('submit','lang_submit', empty($lang_editrec)?get_string('add','local_studentinfo'):get_string('update','local_studentinfo'));
        $btn2[]=$mform->createElement('cancel','lang_cancel',get_string('clear','local_studentinfo'));
        $mform->addGroup($btn2,'lang_actions',' ',' ',false);
        $mform->addElement('html','</div></div>');

        // ---------------------------------------------------------------------
        // WARIS – list + single-row form
        // ---------------------------------------------------------------------
        $mform->addElement('header', 'sec_family', get_string('sec_family','local_studentinfo'));

        $html = '<div class="card mb-2"><div class="card-body py-2"><div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
        <thead class="thead-light"><tr>
          <th style="width:16%">'.get_string('hubungan','local_studentinfo').'</th>
          <th style="width:22%">'.get_string('nama','local_studentinfo').'</th>
          <th style="width:16%">'.get_string('ic','local_studentinfo').'</th>
          <th style="width:16%">'.get_string('telefon','local_studentinfo').'</th>
          <th style="width:16%">'.get_string('tarikh_lahir_child','local_studentinfo').'</th>
          <th style="width:14%">'.get_string('col_action','local_studentinfo').'</th>
        </tr></thead><tbody>';
        if ($fam_list) {
            foreach ($fam_list as $r) {
                $editurl = new \moodle_url('/local/studentinfo/edit.php', ['userid'=>$user->id, 'faction'=>'edit', 'fid'=>$r->id], 'pane_sec_family');
                $delurl  = new \moodle_url('/local/studentinfo/edit.php', ['userid'=>$user->id, 'faction'=>'delete', 'fid'=>$r->id, 'sesskey'=>sesskey()], 'pane_sec_family');
                $dob = $r->tarikh_lahir ? userdate($r->tarikh_lahir, get_string('strftimedate','langconfig')) : '';
                $html .= '<tr>'.
                  '<td>'.s($r->hubungan ?? '').'</td>'.
                  '<td>'.s($r->nama ?? '').'</td>'.
                  '<td>'.s($r->ic ?? '').'</td>'.
                  '<td>'.s($r->telefon ?? '').'</td>'.
                  '<td>'.s($dob).'</td>'.
                  '<td><a class="btn btn-sm btn-outline-primary mr-1" href="'.$editurl.'">Edit</a>'.
                  '<a class="btn btn-sm btn-outline-danger" href="'.$delurl.'" onclick="return confirm(\'Confirm to delete?\')">Delete</a></td>'.
                '</tr>';
            }
        } else {
            $html .= '<tr><td colspan="6" class="text-center text-muted">Tiada rekod waris.</td></tr>';
        }
        $html .= '</tbody></table></div></div></div>';
        $mform->addElement('html', $html);

        // Add/Update Waris
        $mform->addElement('html','<div class="card mb-3"><div class="card-header"><strong>'.get_string('addupdatefamily','local_studentinfo').'</strong></div><div class="card-body"><div class="container-fluid"><div class="row">');
        $mform->addElement('hidden', 'fam_id', $fam_editrec->id ?? 0); $mform->setType('fam_id', PARAM_INT);

        $mform->addElement('html','<div class="col-md-3"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('hubungan','local_studentinfo').'</label>');
        $mform->addElement('text','fam_hubungan','',['size'=>18]); $mform->setType('fam_hubungan',PARAM_TEXT);

        $mform->addElement('html','</div></div><div class="col-md-4"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('nama','local_studentinfo').'</label>');
        $mform->addElement('text','fam_nama','',['size'=>24]); $mform->setType('fam_nama',PARAM_TEXT);

        $mform->addElement('html','</div></div><div class="col-md-2"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('ic','local_studentinfo').'</label>');
        $mform->addElement('text','fam_ic','',['size'=>16]); $mform->setType('fam_ic',PARAM_TEXT);

        $mform->addElement('html','</div></div><div class="col-md-3"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('telefon','local_studentinfo').'</label>');
        $mform->addElement('text','fam_telefon','',['size'=>16]); $mform->setType('fam_telefon',PARAM_TEXT);

        $mform->addElement('html','</div></div><div class="col-md-3"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('tarikh_lahir_child','local_studentinfo').'</label>');
        $mform->addElement('date_selector','fam_tarikh_lahir','');

        $mform->addElement('html','</div></div></div>');
        $g=[]; $g[]=$mform->createElement('submit','fam_submit', empty($fam_editrec)?get_string('add','local_studentinfo'):get_string('update','local_studentinfo'));
        $g[]=$mform->createElement('cancel','fam_cancel',get_string('clear','local_studentinfo'));
        $mform->addGroup($g,'fam_actions',' ',' ',false);
        $mform->addElement('html','</div></div>');

        // ---------------------------------------------------------------------
        // KURSUS – list + single-row form
        // ---------------------------------------------------------------------
        $mform->addElement('header', 'sec_courses', get_string('sec_courses','local_studentinfo'));

        $html = '<div class="card mb-2"><div class="card-body py-2"><div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
        <thead class="thead-light"><tr>
          <th>'.get_string('kursus_nama','local_studentinfo').'</th>
          <th style="width:18%">'.get_string('kursus_tempat','local_studentinfo').'</th>
          <th style="width:14%">'.get_string('kursus_mula','local_studentinfo').'</th>
          <th style="width:14%">'.get_string('kursus_tamat','local_studentinfo').'</th>
          <th style="width:14%">'.get_string('kursus_keputusan','local_studentinfo').'</th>
          <th style="width:14%">'.get_string('col_action','local_studentinfo').'</th>
        </tr></thead><tbody>';
        if ($course_list) {
            foreach ($course_list as $r) {
                $editurl = new \moodle_url('/local/studentinfo/edit.php', ['userid'=>$user->id, 'courseaction'=>'edit', 'cid'=>$r->id], 'pane_sec_courses');
                $delurl  = new \moodle_url('/local/studentinfo/edit.php', ['userid'=>$user->id, 'courseaction'=>'delete', 'cid'=>$r->id, 'sesskey'=>sesskey()], 'pane_sec_courses');
                $html .= '<tr>'.
                  '<td>'.s($r->nama ?? '').'</td>'.
                  '<td>'.s($r->tempat ?? '').'</td>'.
                  '<td>'.($r->mula  ? s(userdate($r->mula,  get_string('strftimedate','langconfig'))) : '').'</td>'.
                  '<td>'.($r->tamat ? s(userdate($r->tamat, get_string('strftimedate','langconfig'))) : '').'</td>'.
                  '<td>'.s($r->keputusan ?? '').'</td>'.
                  '<td><a class="btn btn-sm btn-outline-primary mr-1" href="'.$editurl.'">Edit</a>'.
                  '<a class="btn btn-sm btn-outline-danger" href="'.$delurl.'" onclick="return confirm(\'Confirm to delete?\')">Delete</a></td>'.
                '</tr>';
            }
        } else {
            $html .= '<tr><td colspan="6" class="text-center text-muted">No Record</td></tr>';
        }
        $html .= '</tbody></table></div></div></div>';
        $mform->addElement('html', $html);

        // Add/Update Kursus
        $mform->addElement('html','<div class="card mb-3"><div class="card-header"><strong>'.get_string('addupdatecourse','local_studentinfo').'</strong></div><div class="card-body"><div class="container-fluid"><div class="row">');
        $mform->addElement('hidden','course_id',$course_editrec->id ?? 0); $mform->setType('course_id',PARAM_INT);

        $mform->addElement('html','<div class="col-md-4"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('kursus_nama','local_studentinfo').'</label>');
        $mform->addElement('text','kursus_nama','',['size'=>32]); $mform->setType('kursus_nama',PARAM_TEXT);

        $mform->addElement('html','</div></div><div class="col-md-3"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('kursus_tempat','local_studentinfo').'</label>');
        $mform->addElement('text','kursus_tempat','',['size'=>20]); $mform->setType('kursus_tempat',PARAM_TEXT);

        $mform->addElement('html','</div></div><div class="col-md-2"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('kursus_mula','local_studentinfo').'</label>');
        $mform->addElement('date_selector','kursus_mula','');

        $mform->addElement('html','</div></div><div class="col-md-2"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('kursus_tamat','local_studentinfo').'</label>');
        $mform->addElement('date_selector','kursus_tamat','');

        $mform->addElement('html','</div></div><div class="col-md-3"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('kursus_keputusan','local_studentinfo').'</label>');
        $mform->addElement('text','kursus_keputusan','',['size'=>16]); $mform->setType('kursus_keputusan',PARAM_TEXT);

        $mform->addElement('html','</div></div></div>');
        $g=[]; $g[]=$mform->createElement('submit','course_submit', empty($course_editrec)?get_string('add','local_studentinfo'):get_string('update','local_studentinfo'));
        $g[]=$mform->createElement('cancel','course_cancel',get_string('clear','local_studentinfo'));
        $mform->addGroup($g,'course_actions',' ',' ',false);
        $mform->addElement('html','</div></div>');

        // ---------------------------------------------------------------------
        // PANGKAT – list + single-row form
        // ---------------------------------------------------------------------
        $mform->addElement('header','sec_ranks',get_string('sec_ranks','local_studentinfo'));

        $html = '<div class="card mb-2"><div class="card-body py-2"><div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
        <thead class="thead-light"><tr>
          <th style="width:22%">'.get_string('rank_pangkat','local_studentinfo').'</th>
          <th style="width:14%">'.get_string('rank_tarikh','local_studentinfo').'</th>
          <th style="width:22%">'.get_string('rank_kekananan','local_studentinfo').'</th>
          <th style="width:14%">'.get_string('rank_tarikh_kekananan','local_studentinfo').'</th>
          <th style="width:14%">'.get_string('col_action','local_studentinfo').'</th>
        </tr></thead><tbody>';
        if ($rank_list) {
            foreach ($rank_list as $r) {
                $editurl = new \moodle_url('/local/studentinfo/edit.php', ['userid'=>$user->id, 'rankaction'=>'edit', 'rid'=>$r->id], 'pane_sec_ranks');
                $delurl  = new \moodle_url('/local/studentinfo/edit.php', ['userid'=>$user->id, 'rankaction'=>'delete', 'rid'=>$r->id, 'sesskey'=>sesskey()], 'pane_sec_ranks');
                $html .= '<tr>'.
                  '<td>'.s($r->pangkat ?? '').'</td>'.
                  '<td>'.($r->tarikh ? s(userdate($r->tarikh, get_string('strftimedate','langconfig'))) : '').'</td>'.
                  '<td>'.s($r->kekananan ?? '').'</td>'.
                  '<td>'.($r->tarikh_kekananan ? s(userdate($r->tarikh_kekananan, get_string('strftimedate','langconfig'))) : '').'</td>'.
                  '<td><a class="btn btn-sm btn-outline-primary mr-1" href="'.$editurl.'">Edit</a>'.
                  '<a class="btn btn-sm btn-outline-danger" href="'.$delurl.'" onclick="return confirm(\'Confirm to delete?\')">Delete</a></td>'.
                '</tr>';
            }
        } else {
            $html .= '<tr><td colspan="5" class="text-center text-muted">Tiada rekod pangkat.</td></tr>';
        }
        $html .= '</tbody></table></div></div></div>';
        $mform->addElement('html', $html);

        // Add/Update Pangkat
        $mform->addElement('html','<div class="card mb-3"><div class="card-header"><strong>'.get_string('addupdaterank','local_studentinfo').'</strong></div><div class="card-body"><div class="container-fluid"><div class="row">');
        $mform->addElement('hidden','rank_id',$rank_editrec->id ?? 0); $mform->setType('rank_id',PARAM_INT);

        $mform->addElement('html','<div class="col-md-4"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('rank_pangkat','local_studentinfo').'</label>');
        $mform->addElement('text','rank_pangkat','',['size'=>24]); $mform->setType('rank_pangkat',PARAM_TEXT);

        $mform->addElement('html','</div></div><div class="col-md-2"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('rank_tarikh','local_studentinfo').'</label>');
        $mform->addElement('date_selector','rank_tarikh','');

        $mform->addElement('html','</div></div><div class="col-md-3"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('rank_kekananan','local_studentinfo').'</label>');
        $mform->addElement('text','rank_kekananan','',['size'=>18]); $mform->setType('rank_kekananan',PARAM_TEXT);

        $mform->addElement('html','</div></div><div class="col-md-3"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('rank_tarikh_kekananan','local_studentinfo').'</label>');
        $mform->addElement('date_selector','rank_tarikh_kekananan','');

        $mform->addElement('html','</div></div></div>');
        $g=[]; $g[]=$mform->createElement('submit','rank_submit', empty($rank_editrec)?get_string('add','local_studentinfo'):get_string('update','local_studentinfo'));
        $g[]=$mform->createElement('cancel','rank_cancel',get_string('clear','local_studentinfo'));
        $mform->addGroup($g,'rank_actions',' ',' ',false);
        $mform->addElement('html','</div></div>');

        // ---------------------------------------------------------------------
        // PERTUKARAN – list + single-row form
        // ---------------------------------------------------------------------
        $mform->addElement('header','sec_postings',get_string('sec_postings','local_studentinfo'));
        
        $string['posting_jawatan'] = 'Position';
$string['posting_pasukan'] = 'Unit';
$string['posting_negeri'] = 'State';
$string['posting_mula'] = 'Start Date';
$string['posting_tamat'] = 'End Date';

        $html = '<div class="card mb-2"><div class="card-body py-2"><div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
        <thead class="thead-light"><tr>
          <th>'.get_string('posting_jawatan','local_studentinfo').'</th>
          <th style="width:18%">'.get_string('posting_pasukan','local_studentinfo').'</th>
          <th style="width:14%">'.get_string('posting_negeri','local_studentinfo').'</th>
          <th style="width:14%">'.get_string('posting_mula','local_studentinfo').'</th>
          <th style="width:14%">'.get_string('posting_tamat','local_studentinfo').'</th>
          <th style="width:14%">'.get_string('col_action','local_studentinfo').'</th>
        </tr></thead><tbody>';
        if ($post_list) {
            foreach ($post_list as $r) {
                $editurl = new \moodle_url('/local/studentinfo/edit.php', ['userid'=>$user->id, 'postaction'=>'edit', 'pid'=>$r->id], 'pane_sec_postings');
                $delurl  = new \moodle_url('/local/studentinfo/edit.php', ['userid'=>$user->id, 'postaction'=>'delete', 'pid'=>$r->id, 'sesskey'=>sesskey()], 'pane_sec_postings');
                $html .= '<tr>'.
                  '<td>'.s($r->jawatan ?? '').'</td>'.
                  '<td>'.s($r->pasukan ?? '').'</td>'.
                  '<td>'.s($r->negeri ?? '').'</td>'.
                  '<td>'.($r->mula  ? s(userdate($r->mula,  get_string('strftimedate','langconfig'))) : '').'</td>'.
                  '<td>'.($r->tamat ? s(userdate($r->tamat, get_string('strftimedate','langconfig'))) : '').'</td>'.
                  '<td><a class="btn btn-sm btn-outline-primary mr-1" href="'.$editurl.'">Edit</a>'.
                  '<a class="btn btn-sm btn-outline-danger" href="'.$delurl.'" onclick="return confirm(\'Confirm to delete?\')">Delete</a></td>'.
                '</tr>';
            }
        } else {
            $html .= '<tr><td colspan="6" class="text-center text-muted">No record.</td></tr>';
        }
        $html .= '</tbody></table></div></div></div>';
        $mform->addElement('html', $html);

        // Add/Update Pertukaran
        $mform->addElement('html','<div class="card mb-3"><div class="card-header"><strong>'.get_string('addupdatepost','local_studentinfo').'</strong></div><div class="card-body"><div class="container-fluid"><div class="row">');
        $mform->addElement('hidden','post_id',$post_editrec->id ?? 0); $mform->setType('post_id',PARAM_INT);

        $mform->addElement('html','<div class="col-md-3"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('posting_jawatan','local_studentinfo').'</label>');
        $mform->addElement('text','posting_jawatan','',['size'=>18]); $mform->setType('posting_jawatan',PARAM_TEXT);

        $mform->addElement('html','</div></div><div class="col-md-3"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('posting_pasukan','local_studentinfo').'</label>');
        $mform->addElement('text','posting_pasukan','',['size'=>18]); $mform->setType('posting_pasukan',PARAM_TEXT);

        $mform->addElement('html','</div></div><div class="col-md-2"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('posting_mula','local_studentinfo').'</label>');
        $mform->addElement('text','posting_negeri','',['size'=>14]); $mform->setType('posting_negeri',PARAM_TEXT);

        $mform->addElement('html','</div></div><div class="col-md-2"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('posting_tamat','local_studentinfo').'</label>');
        $mform->addElement('date_selector','posting_mula','');

        $mform->addElement('html','</div></div><div class="col-md-2"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('rank_pangkat','local_studentinfo').'</label>');
        $mform->addElement('date_selector','posting_tamat','');

        $mform->addElement('html','</div></div></div>');
        $g=[]; $g[]=$mform->createElement('submit','post_submit', empty($post_editrec)?get_string('add','local_studentinfo'):get_string('update','local_studentinfo'));
        $g[]=$mform->createElement('cancel','post_cancel',get_string('clear','local_studentinfo'));
        $mform->addGroup($g,'post_actions',' ',' ',false);
        $mform->addElement('html','</div></div>');

        // ---------------------------------------------------------------------
        // PINGAT – list + single-row form
        // ---------------------------------------------------------------------
        $mform->addElement('header','sec_awards',get_string('sec_awards','local_studentinfo'));

        $html = '<div class="card mb-2"><div class="card-body py-2"><div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
        <thead class="thead-light"><tr>
          <th>'.get_string('award_nama','local_studentinfo').'</th>
          <th style="width:16%">'.get_string('award_singkatan','local_studentinfo').'</th>
          <th style="width:18%">'.get_string('award_gelaran','local_studentinfo').'</th>
          <th style="width:14%">'.get_string('award_tarikh','local_studentinfo').'</th>
          <th style="width:14%">'.get_string('col_action','local_studentinfo').'</th>
        </tr></thead><tbody>';
        if ($award_list) {
            foreach ($award_list as $r) {
                $editurl = new \moodle_url('/local/studentinfo/edit.php', ['userid'=>$user->id, 'awardaction'=>'edit', 'awid'=>$r->id], 'pane_sec_awards');
                $delurl  = new \moodle_url('/local/studentinfo/edit.php', ['userid'=>$user->id, 'awardaction'=>'delete', 'awid'=>$r->id, 'sesskey'=>sesskey()], 'pane_sec_awards');
                $html .= '<tr>'.
                  '<td>'.s($r->nama ?? '').'</td>'.
                  '<td>'.s($r->singkatan ?? '').'</td>'.
                  '<td>'.s($r->gelaran ?? '').'</td>'.
                  '<td>'.($r->tarikh ? s(userdate($r->tarikh, get_string('strftimedate','langconfig'))) : '').'</td>'.
                  '<td><a class="btn btn-sm btn-outline-primary mr-1" href="'.$editurl.'">Edit</a>'.
                  '<a class="btn btn-sm btn-outline-danger" href="'.$delurl.'" onclick="return confirm(\'Confirm to delete?\')">Delete</a></td>'.
                '</tr>';
            }
        } else {
            $html .= '<tr><td colspan="5" class="text-center text-muted">No Record.</td></tr>';
        }
        $html .= '</tbody></table></div></div></div>';
        $mform->addElement('html', $html);

        // Add/Update Pingat
        $mform->addElement('html','<div class="card mb-3"><div class="card-header"><strong>'.get_string('addupdateaward','local_studentinfo').'</strong></div><div class="card-body"><div class="container-fluid"><div class="row">');
        $mform->addElement('hidden','award_id',$award_editrec->id ?? 0); $mform->setType('award_id',PARAM_INT);

        $mform->addElement('html','<div class="col-md-4"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('award_nama','local_studentinfo').'</label>');
        $mform->addElement('text','award_nama','',['size'=>24]); $mform->setType('award_nama',PARAM_TEXT);

        $mform->addElement('html','</div></div><div class="col-md-3"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('award_singkatan','local_studentinfo').'</label>');
        $mform->addElement('text','award_singkatan','',['size'=>16]); $mform->setType('award_singkatan',PARAM_TEXT);

        $mform->addElement('html','</div></div><div class="col-md-3"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('award_gelaran','local_studentinfo').'</label>');
        $mform->addElement('text','award_gelaran','',['size'=>16]); $mform->setType('award_gelaran',PARAM_TEXT);

        $mform->addElement('html','</div></div><div class="col-md-2"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('award_tarikh','local_studentinfo').'</label>');
        $mform->addElement('date_selector','award_tarikh','');

        $mform->addElement('html','</div></div></div>');
        $g=[]; $g[]=$mform->createElement('submit','award_submit', empty($award_editrec)?get_string('add','local_studentinfo'):get_string('update','local_studentinfo'));
        $g[]=$mform->createElement('cancel','award_cancel',get_string('clear','local_studentinfo'));
        $mform->addGroup($g,'award_actions',' ',' ',false);
        $mform->addElement('html','</div></div>');

        // ---------------------------------------------------------------------
        // INSURAN – list + single-row form
        // ---------------------------------------------------------------------
        $mform->addElement('header','sec_insurance',get_string('sec_insurance','local_studentinfo'));

        $html = '<div class="card mb-2"><div class="card-body py-2"><div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
        <thead class="thead-light"><tr>
          <th>'.get_string('ins_penyedia','local_studentinfo').'</th>
          <th style="width:18%">'.get_string('ins_jumlah_unit','local_studentinfo').'</th>
          <th style="width:22%"'.get_string('ins_no_polis','local_studentinfo').'</th>
          <th style="width:14%">'.get_string('col_action','local_studentinfo').'</th>
        </tr></thead><tbody>';
        if ($ins_list) {
            foreach ($ins_list as $r) {
                $editurl = new \moodle_url('/local/studentinfo/edit.php', ['userid'=>$user->id, 'insaction'=>'edit', 'insid'=>$r->id], 'pane_sec_insurance');
                $delurl  = new \moodle_url('/local/studentinfo/edit.php', ['userid'=>$user->id, 'insaction'=>'delete', 'insid'=>$r->id, 'sesskey'=>sesskey()], 'pane_sec_insurance');
                $html .= '<tr>'.
                  '<td>'.s($r->penyedia ?? '').'</td>'.
                  '<td>'.s(($r->jumlah_unit === null ? '' : $r->jumlah_unit)).'</td>'.
                  '<td>'.s($r->no_polis ?? '').'</td>'.
                  '<td><a class="btn btn-sm btn-outline-primary mr-1" href="'.$editurl.'">Edit</a>'.
                  '<a class="btn btn-sm btn-outline-danger" href="'.$delurl.'" onclick="return confirm(\'Confirm to delete?\')">Delete</a></td>'.
                '</tr>';
            }
        } else {
            $html .= '<tr><td colspan="4" class="text-center text-muted">No Record.</td></tr>';
        }
        $html .= '</tbody></table></div></div></div>';
        $mform->addElement('html', $html);

        // Add/Update Insuran
        $mform->addElement('html','<div class="card mb-3"><div class="card-header"><strong>'.get_string('addupdateins','local_studentinfo').'</strong></div><div class="card-body"><div class="container-fluid"><div class="row">');
        $mform->addElement('hidden','ins_id',$ins_editrec->id ?? 0); $mform->setType('ins_id',PARAM_INT);

        $mform->addElement('html','<div class="col-md-4"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('ins_penyedia','local_studentinfo').'</label>');
        $mform->addElement('text','ins_penyedia','',['size'=>24]); $mform->setType('ins_penyedia',PARAM_TEXT);

        $mform->addElement('html','</div></div><div class="col-md-3"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('ins_jumlah_unit','local_studentinfo').'</label>');
        $mform->addElement('text','ins_jumlah_unit','',['size'=>10]); $mform->setType('ins_jumlah_unit',PARAM_INT);

        $mform->addElement('html','</div></div><div class="col-md-5"><div class="form-group mb-2"><label class="form-label" style="font-weight:600;">'.get_string('ins_no_polis','local_studentinfo').'</label>');
        $mform->addElement('text','ins_no_polis','',['size'=>24]); $mform->setType('ins_no_polis',PARAM_TEXT);

        $mform->addElement('html','</div></div></div>');
        $g=[]; $g[]=$mform->createElement('submit','ins_submit', empty($ins_editrec)?get_string('add','local_studentinfo'):get_string('update','local_studentinfo'));
        $g[]=$mform->createElement('cancel','ins_cancel',get_string('clear','local_studentinfo'));
                $mform->addGroup($g,'ins_actions',' ',' ',false);
        $mform->addElement('html','</div></div>');

        // ---------------------------------------------------------------------
        // UPLOADS – Passport Photos & Documents
        // ---------------------------------------------------------------------
        $mform->addElement('header', 'sec_uploads', get_string('sec_uploads', 'local_studentinfo'));

        // Helper: check for existing file in a given filearea.
        $has_file = function(string $area) use ($fs, $usercontext, $studentid) {
            if (!$studentid) {
                return false;
            }
            $files = $fs->get_area_files(
                $usercontext->id,
                'local_studentinfo',
                $area,
                $studentid,
                'filename',
                false
            );
            return !empty($files) ? reset($files) : false;
        };

        // Helper: build a simple view/download link for a stored file.
        $file_link = function(\stored_file $file) {
            $url = \moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            );
            return '<a href="'.$url.'" target="_blank">'.s($file->get_filename()).'</a>';
        };

        // 1. Passport photo (self) – date + image/doc link OR filepicker.
        $existing_self = $has_file('photoself');

        if ($existing_self) {
            $date = !empty($studentinfo->photoselfdate)
                ? userdate($studentinfo->photoselfdate)
                : '';
            $html = $file_link($existing_self);
            if ($date) {
                $html .= '<br><span class="small">Date taken: '.s($date).'</span>';
            }
            $mform->addElement(
                'static',
                'photoself_existing',
                get_string('photoself', 'local_studentinfo'),
                $html
            );
        } else {
            $mform->addElement('date_selector', 'photoselfdate', get_string('photoselfdate', 'local_studentinfo'));

            $mform->addElement(
                'filepicker',
                'photoself_file',
                get_string('photoself', 'local_studentinfo'),
                null,
                ['accepted_types' => ['.jpg', '.jpeg', '.png']]
            );
        }

        // 2. Passport photo (spouse) – date + link OR filepicker.
        $existing_spouse = $has_file('photospouse');

        if ($existing_spouse) {
            $date = !empty($studentinfo->photospousedate)
                ? userdate($studentinfo->photospousedate)
                : '';
            $html = $file_link($existing_spouse);
            if ($date) {
                $html .= '<br><span class="small">Date taken: '.s($date).'</span>';
            }
            $mform->addElement(
                'static',
                'photospouse_existing',
                get_string('photospouse', 'local_studentinfo'),
                $html
            );
        } else {
            $mform->addElement('date_selector', 'photospousedate', get_string('photospousedate', 'local_studentinfo'));

            $mform->addElement(
                'filepicker',
                'photospouse_file',
                get_string('photospouse', 'local_studentinfo'),
                null,
                ['accepted_types' => ['.jpg', '.jpeg', '.png']]
            );
        }

        // 3. Course attendance letter (PDF).
        $existing_course = $has_file('courseletter');

        if ($existing_course) {
            $mform->addElement(
                'static',
                'doc_courseletter_existing',
                get_string('doc_courseletter', 'local_studentinfo'),
                $file_link($existing_course)
            );
        } else {
            $mform->addElement(
                'filepicker',
                'doc_courseletter_file',
                get_string('doc_courseletter', 'local_studentinfo'),
                null,
                ['accepted_types' => ['.pdf']]
            );
        }

        // 4. CO confirmation letter (PDF).
        $existing_co = $has_file('coletter');

        if ($existing_co) {
            $mform->addElement(
                'static',
                'doc_co_letter_existing',
                get_string('doc_co_letter', 'local_studentinfo'),
                $file_link($existing_co)
            );
        } else {
            $mform->addElement(
                'filepicker',
                'doc_co_letter_file',
                get_string('doc_co_letter', 'local_studentinfo'),
                null,
                ['accepted_types' => ['.pdf']]
            );
        }

        // 5. Personal particulars (PDF).
        $existing_personal = $has_file('personalpart');

        if ($existing_personal) {
            $mform->addElement(
                'static',
                'doc_personal_part_existing',
                get_string('doc_personal_part', 'local_studentinfo'),
                $file_link($existing_personal)
            );
        } else {
            $mform->addElement(
                'filepicker',
                'doc_personal_part_file',
                get_string('doc_personal_part', 'local_studentinfo'),
                null,
                ['accepted_types' => ['.pdf']]
            );
        }

        // 6. Health report (BMI) (PDF).
        $existing_health = $has_file('healthbmi');

        if ($existing_health) {
            $mform->addElement(
                'static',
                'doc_health_bmi_existing',
                get_string('doc_health_bmi', 'local_studentinfo'),
                $file_link($existing_health)
            );
        } else {
            $mform->addElement(
                'filepicker',
                'doc_health_bmi_file',
                get_string('doc_health_bmi', 'local_studentinfo'),
                null,
                ['accepted_types' => ['.pdf']]
            );
        }

        // 7. BAT 118A (PDF).
        $existing_bat = $has_file('bat118a');

        if ($existing_bat) {
            $mform->addElement(
                'static',
                'doc_bat118a_existing',
                get_string('doc_bat118a', 'local_studentinfo'),
                $file_link($existing_bat)
            );
        } else {
            $mform->addElement(
                'filepicker',
                'doc_bat118a_file',
                get_string('doc_bat118a', 'local_studentinfo'),
                null,
                ['accepted_types' => ['.pdf']]
            );
        }

        // 8. MyTentera (PDF or image).
        $existing_mytentera = $has_file('mytentera');

        if ($existing_mytentera) {
            $mform->addElement(
                'static',
                'doc_mytentera_existing',
                get_string('doc_mytentera', 'local_studentinfo'),
                $file_link($existing_mytentera)
            );
        } else {
            $mform->addElement(
                'filepicker',
                'doc_mytentera_file',
                get_string('doc_mytentera', 'local_studentinfo'),
                null,
                ['accepted_types' => ['.pdf', '.jpg', '.jpeg', '.png']]
            );
        }

        // 9. Pregnancy non-confirmation (PDF).
        $existing_preg = $has_file('pregnancy');

        if ($existing_preg) {
            $mform->addElement(
                'static',
                'doc_pregnancy_existing',
                get_string('doc_pregnancy', 'local_studentinfo'),
                $file_link($existing_preg)
            );
        } else {
            $mform->addElement(
                'filepicker',
                'doc_pregnancy_file',
                get_string('doc_pregnancy', 'local_studentinfo'),
                null,
                ['accepted_types' => ['.pdf']]
            );
        }

                // 10. Joining Proforma (PDF)
        $existing_joining = $has_file('joiningproforma');

        if ($existing_joining) {
            $mform->addElement(
                'static',
                'doc_joiningproforma_existing',
                get_string('doc_joiningproforma', 'local_studentinfo'),
                $file_link($existing_joining)
            );
        } else {
            $mform->addElement(
                'filepicker',
                'doc_joiningproforma_file',
                get_string('doc_joiningproforma', 'local_studentinfo'),
                null,
                ['accepted_types' => ['.pdf']]
            );
        }

        // 11. Enclosure 1 Form (PDF)
        $existing_encl1 = $has_file('enclosure1');

        if ($existing_encl1) {
            $mform->addElement(
                'static',
                'doc_enclosure1_existing',
                get_string('doc_enclosure1', 'local_studentinfo'),
                $file_link($existing_encl1)
            );
        } else {
            $mform->addElement(
                'filepicker',
                'doc_enclosure1_file',
                get_string('doc_enclosure1', 'local_studentinfo'),
                null,
                ['accepted_types' => ['.pdf']]
            );
        }

        
        // ============================================================
        // Global actions (Save)
        // ============================================================
        $buttonarray = [];
       
        $buttonarray[] = $mform->createElement('submit', 'saveandview',
            get_string('saveandview', 'local_studentinfo'));      // Save & view
        
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);

        // ---------------------------------------------------------------------
        // Default Moodle action buttons (keep for layout but hide via CSS)
        // ---------------------------------------------------------------------
        $this->add_action_buttons(true, get_string('savechanges','local_studentinfo'));

        // Hide the default Save/Cancel buttons.
        //$mform->addElement('html', '<style>.fitem.fitem_actionbuttons {display:none !important;}</style>');

        

    }
    

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $min = strtotime('1940-01-01'); $max = time();
        if (!empty($data['tarikh_lahir']) && ($data['tarikh_lahir'] < $min || $data['tarikh_lahir'] > $max)) {
            $errors['tarikh_lahir'] = 'Tarikh Lahir tidak logik (1940 hingga hari ini).';
        }
        if ($data['berat_kg'] !== '' && $data['berat_kg'] !== null && (!is_numeric($data['berat_kg']) || $data['berat_kg'] < 30 || $data['berat_kg'] > 200)) {
            $errors['berat_kg'] = 'Berat (kg) mesti antara 30 dan 200.';
        }
        if ($data['tinggi_m'] !== '' && $data['tinggi_m'] !== null && (!is_numeric($data['tinggi_m']) || $data['tinggi_m'] < 1.20 || $data['tinggi_m'] > 2.50)) {
            $errors['tinggi_m'] = 'Tinggi (m) mesti antara 1.20 dan 2.50.';
        }
        if ($data['bmi'] !== '' && $data['bmi'] !== null && (!is_numeric($data['bmi']) || $data['bmi'] < 10 || $data['bmi'] > 60)) {
            $errors['bmi'] = 'BMI di luar julat munasabah (10–60).';
        }

        // Optional: quick sanity checks for child single-row forms (non-blocking).
        if (!empty($data['course_submit'])) {
            if (!empty($data['kursus_tamat']) && !empty($data['kursus_mula']) && $data['kursus_tamat'] < $data['kursus_mula']) {
                $errors['kursus_tamat'] = 'Tarikh tamat tidak boleh lebih awal daripada tarikh mula.';
            }
        }
        if (!empty($data['post_submit'])) {
            if (!empty($data['posting_tamat']) && !empty($data['posting_mula']) && $data['posting_tamat'] < $data['posting_mula']) {
                $errors['posting_tamat'] = 'Tarikh tamat tidak boleh lebih awal daripada tarikh mula.';
            }
        }

        return $errors;
    }
}
