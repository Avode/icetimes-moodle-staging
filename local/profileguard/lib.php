<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Before HTTP headers callback: enforce profile completion for users without system-level roles.
 */
function local_profileguard_before_http_headers() {
    global $USER, $DB, $CFG, $SESSION, $SCRIPT;

    // Only act for normal logged-in users.
    if (!isloggedin() || isguestuser()) {
        return;
    }

    // Skip for CLI/AJAX.
    if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
        return;
    }
    if (defined('AJAX_SCRIPT') && AJAX_SCRIPT) {
        return;
    }

    $systemcontext = \context_system::instance();

    // Skip site admins and users with bypass capability.
    if (is_siteadmin($USER) || has_capability('local/profileguard:bypass', $systemcontext)) {
        return;
    }

    // Scripts to ignore (so we do not trap on the completion page, login/logout, etc.).
    $ignoreends = [
        '/login/index.php',
        '/login/logout.php',
        '/login/change_password.php',   // ✅ ADD THIS
        '/local/profileguard/trap.php',
        '/local/studentinfo/edit.php',
        '/local/studentinfo/add_student.php',
    ];

    
    // $SCRIPT contains the full path, e.g. '/Icetimes_Production/local/profileguard/trap.php'.
    // We only care about "ends with".
    foreach ($ignoreends as $end) {
        if (substr($SCRIPT, -strlen($end)) === $end) {
            return;
        }
    }


    // Only care about users with NO role at system context.
    $hasrole = $DB->record_exists('role_assignments', [
        'userid'    => $USER->id,
        'contextid' => $systemcontext->id,
    ]);

    // If user HAS a system context role, just optionally redirect after they pass.
    $complete = local_profileguard_is_profile_complete($USER->id);

    if ($hasrole) {
        if ($complete && !empty($SESSION->profileguard_redirect)) {
            $target = $SESSION->profileguard_redirect;
            unset($SESSION->profileguard_redirect);
            redirect(new \moodle_url($target));
        }
        return;
    }

    // At this point: user has NO system-level role → apply guard.
    if (!$complete) {
        // Remember where to send them after they finally pass.
        if (empty($SESSION->profileguard_redirect)) {
            $SESSION->profileguard_redirect = '/my/';
        }
        redirect(new \moodle_url('/local/profileguard/trap.php'));
    } else {
        // Profile is now complete; if we had a pending redirect, send them there once.
        if (!empty($SESSION->profileguard_redirect)) {
            $target = $SESSION->profileguard_redirect;
            unset($SESSION->profileguard_redirect);
            redirect(new \moodle_url($target));
        }
    }
}

/**
 * Check whether the given user has a "complete enough" profile based on local_studentinfo.
 *
 * Required:
 *  - Identity & Service: tentera_no, studenttypeid, serviceid, korid, rankid
 *  - Bio: tarikh_lahir, tempat_lahir, bangsa, agama, warganegara, berat_kg, tinggi_m
 *  - Contact: telefon, and user.email
 */
function local_profileguard_is_profile_complete(int $userid): bool {
    global $DB;

    // Must have a local_studentinfo record.
    $rec = $DB->get_record('local_studentinfo', ['userid' => $userid]);
    if (!$rec) {
        return false;
    }

    // Identity & service.
    $requiredids = ['studenttypeid', 'serviceid', 'korid', 'rankid'];
    foreach ($requiredids as $field) {
        if (empty($rec->$field)) {
            return false;
        }
    }
    if (empty($rec->tentera_no)) {
        return false;
    }

    // Bio.
    if (empty($rec->tarikh_lahir) || (int)$rec->tarikh_lahir <= 0) {
        return false;
    }
    $requiredtext = ['tempat_lahir', 'bangsa', 'agama', 'warganegara'];
    foreach ($requiredtext as $field) {
        if (!isset($rec->$field) || trim((string)$rec->$field) === '') {
            return false;
        }
    }
    if (empty($rec->berat_kg) || empty($rec->tinggi_m)) {
        return false;
    }

    // Contact: telefon in local_studentinfo, email in core user record.
    if (empty($rec->telefon)) {
        return false;
    }
    $user = $DB->get_record('user', ['id' => $userid], 'id, email', IGNORE_MISSING);
    if (!$user || trim((string)$user->email) === '') {
        return false;
    }

    return true;
}
