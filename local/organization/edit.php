<?php
// Add/edit Organization Unit.

require('../../config.php');
require_once($CFG->dirroot . '/local/organization/locallib.php');
require_once($CFG->dirroot . '/local/organization/classes/form/ou_form.php');

$id = optional_param('id', 0, PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/organization:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/organization/edit.php', ['id' => $id]));
$PAGE->set_title(get_string('pluginname', 'local_organization'));
$PAGE->set_heading(get_string('pluginname', 'local_organization'));

global $DB, $CFG;

$isupdate = $id > 0;

// Load or initialise OU record.
if ($isupdate) {
    $ou = $DB->get_record('local_organization_ou', ['id' => $id, 'deleted' => 0], '*', MUST_EXIST);
} else {
    $ou = (object)[
        'id'        => 0,
        'fullname'  => '',
        'shortname' => '',
        'oucode'    => '',
        'address1'  => '',
        'address2'  => '',
        'address3'  => '',
        'postcode'  => '',
        'state'     => '',
        'district'  => '',
        'commandantuserid' => 0,
        'adminuserid'      => 0,
        'cohortid'         => 0,
        'coursecatid'      => 0,
    ];
}

$mform = new \local_organization\form\ou_form(null, ['isupdate' => $isupdate]);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/organization/index.php'));

} else if ($data = $mform->get_data()) {

    require_sesskey();
    $now = time();

    // === Save or update OU core data ===
    if ($isupdate) {
        $ou->fullname     = $data->fullname;
        $ou->oucode       = $data->oucode;
        $ou->address1     = $data->address1;
        $ou->address2     = $data->address2;
        $ou->address3     = $data->address3;
        $ou->postcode     = $data->postcode;
        $ou->state        = $data->state;
        $ou->district     = $data->district;
        $ou->timemodified = $now;

        $DB->update_record('local_organization_ou', $ou);

    } else {
        $ou = new stdClass();
        $ou->fullname         = $data->fullname;
        $ou->shortname        = $data->shortname;
        $ou->oucode           = $data->oucode;
        $ou->address1         = $data->address1;
        $ou->address2         = $data->address2;
        $ou->address3         = $data->address3;
        $ou->postcode         = $data->postcode;
        $ou->state            = $data->state;
        $ou->district         = $data->district;
        $ou->cohortid         = 0;
        $ou->coursecatid      = 0;
        $ou->commandantuserid = 0;
        $ou->adminuserid      = 0;
        $ou->deleted          = 0;
        $ou->timecreated      = $now;
        $ou->timemodified     = $now;

        $ou->id = $DB->insert_record('local_organization_ou', $ou);
    }

    // === Ensure cohort & course category exist (reuse if already there) ===
    require_once($CFG->dirroot . '/cohort/lib.php');
    require_once($CFG->dirroot . '/course/lib.php');
    require_once($CFG->libdir . '/accesslib.php');
    require_once($CFG->libdir . '/moodlelib.php'); // for setnew_password_and_mail()

    // Cohort: idnumber = cohort_SHORTNAME, reuse if already exists.
    if (empty($ou->cohortid)) {
        $cohortidnumber = 'cohort_' . $ou->shortname;

        $existingcohort = $DB->get_record('cohort', [
            'idnumber'  => $cohortidnumber,
            'contextid' => $context->id
        ], '*', IGNORE_MISSING);

        if ($existingcohort) {
            $ou->cohortid = $existingcohort->id;
        } else {
            $cohort                     = new stdClass();
            $cohort->name               = $ou->fullname;
            $cohort->idnumber           = $cohortidnumber;
            $cohort->contextid          = $context->id;
            $cohort->description        = 'Cohort for OU ' . $ou->shortname;
            $cohort->descriptionformat  = FORMAT_HTML;
            $ou->cohortid               = cohort_add_cohort($cohort);
        }
    }

    // Course category: idnumber = cat_SHORTNAME, reuse if already exists.
    if (empty($ou->coursecatid)) {
        $catidnumber = 'cat_' . $ou->shortname;

        $existingcat = $DB->get_record('course_categories', [
            'idnumber' => $catidnumber
        ], '*', IGNORE_MISSING);

        if ($existingcat) {
            $ou->coursecatid = $existingcat->id;
        } else {
            $cat              = new stdClass();
            $cat->name        = $ou->fullname;
            $cat->idnumber    = $catidnumber;
            $cat->parent      = 0;
            $cat->description = 'Course category for OU ' . $ou->shortname;

            $category         = \core_course_category::create($cat);
            $ou->coursecatid  = $category->id;
        }
    }

    $catcontext = context_coursecat::instance($ou->coursecatid);
    $syscontext = context_system::instance();

    // === COMMANDANT: create/attach user, roles, cohort, send password email if new ===
    $cmdid = 0;
    if (!empty($data->commandantemail)) {
        $cmdemail = trim($data->commandantemail);
        $cmdisnew = null;

        $cmdid = local_organization_get_or_create_user(
            $data->commandantfirstname,
            $data->commandantlastname,
            $cmdemail,
            $cmdisnew
        );

        if ($cmdid) {
            $ou->commandantuserid = $cmdid;

            // Commandant role (manager archetype) assigned at SYSTEM context.
            $cmdroleid = local_organization_get_or_create_role(
                'commandant',
                'Commandant',
                'Commandant for Organization Units'
            );

            if (!user_has_role_assignment($cmdid, $cmdroleid, $syscontext->id)) {
                role_assign($cmdroleid, $cmdid, $syscontext->id);
            }

            // Add Commandant to cohort (still scoped to this OU).
            if (!empty($ou->cohortid) && !cohort_is_member($ou->cohortid, $cmdid)) {
                cohort_add_member($ou->cohortid, $cmdid);
            }

            // If this is a brand new user, send standard "set password" email.
            if ($cmdisnew === true) {
                $touser = $DB->get_record('user', ['id' => $cmdid], '*', MUST_EXIST);
                setnew_password_and_mail($touser);
            }
        }
    }

    // === ADMIN: create/attach user, roles, cohort, send password email if new ===
    $admid = 0;
    if (!empty($data->adminemail)) {
        $admemail = trim($data->adminemail);
        $admisnew = null;

        $admid = local_organization_get_or_create_user(
            $data->adminfirstname,
            $data->adminlastname,
            $admemail,
            $admisnew
        );

        if ($admid) {
            $ou->adminuserid = $admid;

            // Custom admin_[shortname] role assigned at SYSTEM context.
            $adminroleshort = 'admin_' . $ou->shortname;
            $adminrolename  = 'Administrator ' . $ou->shortname;
            $adminroledesc  = 'Administrator for OU ' . $ou->shortname;

            $adminroleid = local_organization_get_or_create_role(
                $adminroleshort,
                $adminrolename,
                $adminroledesc
            );
            if (!user_has_role_assignment($admid, $adminroleid, $syscontext->id)) {
                role_assign($adminroleid, $admid, $syscontext->id);
            }

            // Also assign standard Manager role at COURSE CATEGORY context
            // so this admin can manage courses under this OU.
            $managerrole = $DB->get_record('role', ['shortname' => 'manager'], '*', IGNORE_MISSING);
            if ($managerrole &&
                !user_has_role_assignment($admid, $managerrole->id, $catcontext->id)) {
                role_assign($managerrole->id, $admid, $catcontext->id);
            }

            // Add Admin to cohort.
            if (!empty($ou->cohortid) && !cohort_is_member($ou->cohortid, $admid)) {
                cohort_add_member($ou->cohortid, $admid);
            }

            // If this is a brand new user, send standard "set password" email.
            if ($admisnew === true) {
                $touser = $DB->get_record('user', ['id' => $admid], '*', MUST_EXIST);
                setnew_password_and_mail($touser);
            }
        }
    }

    // Save final OU state.
    $ou->timemodified = $now;
    $DB->update_record('local_organization_ou', $ou);

    redirect(
        new moodle_url('/local/organization/index.php'),
        get_string('changesaved', 'local_organization'),
        2
    );
}

// === PREFILL: Commandant & Admin fields on edit ===
if ($isupdate) {
    // Commandant.
    if (!empty($ou->commandantuserid)) {
        if ($cmduser = $DB->get_record('user', ['id' => $ou->commandantuserid, 'deleted' => 0])) {
            $ou->commandantfirstname = $cmduser->firstname;
            $ou->commandantlastname  = $cmduser->lastname;
            $ou->commandantemail     = $cmduser->email;
        }
    }

    // Admin.
    if (!empty($ou->adminuserid)) {
        if ($admuser = $DB->get_record('user', ['id' => $ou->adminuserid, 'deleted' => 0])) {
            $ou->adminfirstname = $admuser->firstname;
            $ou->adminlastname  = $admuser->lastname;
            $ou->adminemail     = $admuser->email;
        }
    }
}

// Pre-fill form.
$mform->set_data($ou);

// === JS: State -> District dropdown mapping ===
$statedistricts = [
    'Selangor' => ['Petaling', 'Klang', 'Gombak', 'Hulu Langat', 'Kuala Selangor', 'Sabak Bernam', 'Sepang', 'Hulu Selangor'],
    'W.P. Kuala Lumpur' => ['Cheras', 'Kepong', 'Lembah Pantai', 'Setiawangsa', 'Titiwangsa'],
    'Johor' => ['Johor Bahru', 'Batu Pahat', 'Kluang', 'Muar', 'Pontian', 'Segamat', 'Mersing', 'Kota Tinggi', 'Kulai', 'Tangkak'],
    'Kedah' => ['Alor Setar', 'Kuala Muda', 'Kubang Pasu', 'Kulim', 'Langkawi', 'Baling', 'Padang Terap', 'Pendang'],
    // Extend as needed...
];

$js = '
    var orgStateDistricts = ' . json_encode($statedistricts) . ';
    var orgCurrentState   = ' . json_encode($ou->state) . ';
    var orgCurrentDistrict = ' . json_encode($ou->district) . ';

    document.addEventListener("DOMContentLoaded", function() {
        var stateSel = document.getElementById("id_state");
        var distSel  = document.getElementById("id_district");
        if (!stateSel || !distSel) {
            return;
        }

        function refillDistricts() {
            var st = stateSel.value;
            var opts = orgStateDistricts[st] || [];
            while (distSel.firstChild) {
                distSel.removeChild(distSel.firstChild);
            }
            var opt0 = document.createElement("option");
            opt0.value = "";
            opt0.textContent = "' . addslashes(get_string('choosedistrict', 'local_organization')) . '";
            distSel.appendChild(opt0);
            for (var i = 0; i < opts.length; i++) {
                var opt = document.createElement("option");
                opt.value = opts[i];
                opt.textContent = opts[i];
                distSel.appendChild(opt);
            }
            if (orgCurrentDistrict) {
                distSel.value = orgCurrentDistrict;
            }
        }

        refillDistricts();

        stateSel.addEventListener("change", function() {
            orgCurrentDistrict = "";
            refillDistricts();
        });
    });
';

$PAGE->requires->js_init_code($js);

echo $OUTPUT->header();
echo $OUTPUT->heading(
    $isupdate ? get_string('editou', 'local_organization')
              : get_string('addou', 'local_organization')
);
$mform->display();
echo $OUTPUT->footer();
