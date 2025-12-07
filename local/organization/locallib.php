<?php
// Local library functions for local_organization.

defined('MOODLE_INTERNAL') || die();

/**
 * Get or create a user by email.
 *
 * - If the email already exists, returns existing user id and sets $isnew = false.
 * - If not, creates a new manual user and sets $isnew = true.
 *   (No email is sent here; caller decides what to send.)
 *
 * @param string      $firstname
 * @param string      $lastname
 * @param string      $email
 * @param bool|null   $isnew   Output flag: true if newly created, false if existing, null if no email.
 * @return int userid or 0 if no email provided
 */
function local_organization_get_or_create_user(string $firstname, string $lastname, string $email, ?bool &$isnew = null): int {
    global $DB, $CFG;

    $email = trim($email);
    if (empty($email)) {
        $isnew = null;
        return 0;
    }

    // Existing user?
    if ($user = $DB->get_record('user', ['email' => $email, 'deleted' => 0])) {
        $isnew = false;
        return (int)$user->id;
    }

    require_once($CFG->dirroot . '/user/lib.php');

    $user = new stdClass();
    $user->username    = $email;
    $user->email       = $email;
    $user->firstname   = $firstname ?: 'User';
    $user->lastname    = $lastname ?: 'OU';
    $user->auth        = 'manual';
    $user->confirmed   = 1;
    $user->mnethostid  = $CFG->mnet_localhost_id;

    // IMPORTANT: we do NOT send email here (second param = false).
    $userid = user_create_user($user, false);

    $isnew = true;
    return (int)$userid;
}

/**
 * Get or create a role with manager archetype.
 *
 * @param string $shortname
 * @param string $name
 * @param string $description
 * @return int role id
 */
function local_organization_get_or_create_role(string $shortname, string $name, string $description): int {
    global $DB, $CFG;

    if ($role = $DB->get_record('role', ['shortname' => $shortname])) {
        return (int)$role->id;
    }

    require_once($CFG->libdir . '/accesslib.php');

    // Use "manager" as archetype.
    $roleid = create_role($name, $shortname, $description, 'manager');

    return (int)$roleid;
}
