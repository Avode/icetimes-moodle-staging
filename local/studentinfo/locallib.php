<?php
defined('MOODLE_INTERNAL') || die();

/** Ensure intake has a linked cohort; return cohortid (int or null) */
function local_studentinfo_ensure_intake_cohort(int $intakeid): ?int {
    global $DB;
    $intake = $DB->get_record('local_studentinfo_intake', ['id'=>$intakeid], '*', IGNORE_MISSING);
    if (!$intake) { return null; }
    if (!empty($intake->cohortid)) { return (int)$intake->cohortid; }

    // Create or find cohort with idnumber = INTAKE:{code}
    $idnum = 'INTAKE:'.$intake->code;
    $cohortid = $DB->get_field('cohort','id',['idnumber'=>$idnum], IGNORE_MISSING);
    if (!$cohortid) {
        $c = (object)[
            'contextid' => 1, 'name' => 'Intake '.$intake->code, 'idnumber' => $idnum,
            'description' => 'Auto cohort for intake', 'descriptionformat'=>1, 'visible'=>1,
            'timecreated'=>time(), 'timemodified'=>time()
        ];
        $cohortid = $DB->insert_record('cohort', $c);
    }
    $DB->set_field('local_studentinfo_intake','cohortid',$cohortid,['id'=>$intakeid]);
    return (int)$cohortid;
}

/** Add a user to an intake's cohort (idempotent). Returns cohortid or null. */
function local_studentinfo_add_user_to_intake_cohort(int $userid, ?int $intakeid): ?int {
    global $DB;
    if (empty($intakeid)) { return null; }
    $cohortid = local_studentinfo_ensure_intake_cohort($intakeid);
    if (!$cohortid) { return null; }
    $exists = $DB->record_exists('cohort_members', ['cohortid'=>$cohortid, 'userid'=>$userid]);
    if (!$exists) {
        $DB->insert_record('cohort_members', (object)[
            'cohortid'=>$cohortid,'userid'=>$userid,'timeadded'=>time(),'component'=>'local_studentinfo'
        ]);
    }
    return $cohortid;
}
