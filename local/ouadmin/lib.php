<?php
defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/accesslib.php');   // context_xxx, role_assign, etc.
require_once($CFG->dirroot . '/user/lib.php');   // user_create_user, core_user.
require_once($CFG->libdir . '/moodlelib.php');   // generate_password, moodle_exception, etc.
require_once($CFG->dirroot . '/cohort/lib.php'); // cohort_add_cohort

/**
 * Get list of OUs (organisation units) current user can manage.
 * Uses local_organization_ou (id, fullname, adminuserid).
 *
 * @param int $userid
 * @return array id => name
 */
function local_ouadmin_get_user_ous(int $userid): array {
    global $DB;

    $ous     = [];
    $manager = $DB->get_manager();

    if ($manager->table_exists('local_organization_ou')) {
        if (is_siteadmin($userid)) {
            // Site admin can see all OUs/colleges.
            $records = $DB->get_records('local_organization_ou', null,
                'fullname ASC', 'id, fullname');
        } else {
            // Non-site admin: only OUs where this user is adminuserid.
            $records = $DB->get_records('local_organization_ou',
                ['adminuserid' => $userid],
                'fullname ASC', 'id, fullname');
        }

        foreach ($records as $r) {
            $ous[(int)$r->id] = format_string($r->fullname);
        }
    }

    // Fallback if no organisation table or no assignments.
    if (empty($ous)) {
        $ous[0] = 'Default organisation';
    }

    return $ous;
}

/**
 * Get parent course category for an OU based on local_organization_ou.coursecatid.
 *
 * Relation:
 *   local_organization_ou.id          = ouid
 *   local_organization_ou.coursecatid = parent course category id (course_categories.id)
 *
 * @param int $ouid
 * @return stdClass|null course_categories record or null
 */
function local_ouadmin_get_ou_category(int $ouid) {
    global $DB;

    $manager = $DB->get_manager();
    if (!$manager->table_exists('local_organization_ou')) {
        return null;
    }

    // Expecting fields: id, fullname, coursecatid.
    $ou = $DB->get_record('local_organization_ou', ['id' => $ouid],
        'id, fullname, coursecatid', IGNORE_MISSING);
    if (!$ou || empty($ou->coursecatid)) {
        return null;
    }

    $category = $DB->get_record('course_categories',
        ['id' => $ou->coursecatid], '*', IGNORE_MISSING);

    return $category ?: null;
}

/**
 * Ensure we have a "Dean" role (category context, archetype manager).
 * Returns role id.
 *
 * @return int roleid
 */
function local_ouadmin_ensure_dean_role(): int {
    global $DB;

    // Already exists?
    if ($role = $DB->get_record('role', ['shortname' => 'dean'])) {
        return (int)$role->id;
    }

    // 1. Create role.
    $roleid = create_role('Dean', 'dean', 'Faculty Dean role (category-level)');

    // 2. Set archetype = manager.
    $DB->set_field('role', 'archetype', 'manager', ['id' => $roleid]);

    // 3. Restrict context level to category.
    set_role_contextlevels($roleid, [CONTEXT_COURSECAT]);

    // Capabilities can be tuned manually later in the UI.
    return (int)$roleid;
}

/**
 * Create or get user by dean email.
 * If user exists by email, returns that user.
 * If not, creates a new user with username = email and random password,
 * and emails the credentials.
 *
 * @param string $firstname
 * @param string $lastname
 * @param string $email
 * @return stdClass user record
 * @throws moodle_exception
 */
function local_ouadmin_create_or_get_dean_user(string $firstname, string $lastname, string $email): stdClass {
    global $DB, $CFG;

    $email = trim(core_text::strtolower($email));
    if (empty($email)) {
        throw new moodle_exception('Invalid dean email');
    }

    // Existing user by email?
    if ($user = $DB->get_record('user', ['email' => $email, 'deleted' => 0])) {
        return $user;
    }

    // New user.
    $user = new stdClass();
    $user->username   = $email;
    $user->firstname  = $firstname ?: 'Dean';
    $user->lastname   = $lastname ?: '';
    $user->email      = $email;
    $user->auth       = 'manual';
    $user->confirmed  = 1;
    $user->mnethostid = $DB->get_field('mnet_host', 'id',
        ['wwwroot' => $CFG->wwwroot]); // usually 1.

    $password         = generate_password(10);
    $user->password   = hash_internal_user_password($password);

    $userid = user_create_user($user, false, false);
    $user   = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

    // Send email with login info.
    $supportuser = core_user::get_support_user();
    $subject = 'Your Dean account has been created';
    $message = "Dear {$user->firstname},\n\n"
        . "An account has been created for you as Dean in the training system.\n\n"
        . "Username: {$user->username}\n"
        . "Password: {$password}\n\n"
        . "You can log in at: {$CFG->wwwroot}\n\n"
        . "Thank you.\n";

    email_to_user($user, $supportuser, $subject, $message);

    return $user;
}

/**
 * Create or get user by teacher email.
 * If user exists by email, returns that user.
 * If not, creates a new user with username = email and random password,
 * and emails the credentials.
 *
 * @param string $firstname
 * @param string $lastname
 * @param string $email
 * @return stdClass user record
 * @throws moodle_exception
 */
function local_ouadmin_create_or_get_teacher_user(string $firstname, string $lastname, string $email): stdClass {
    global $DB, $CFG;

    $email = trim(core_text::strtolower($email));
    if (empty($email)) {
        throw new moodle_exception('Invalid teacher email');
    }

    // Existing user by email?
    if ($user = $DB->get_record('user', ['email' => $email, 'deleted' => 0])) {
        return $user;
    }

    // New user.
    $user = new stdClass();
    $user->username   = $email;
    $user->firstname  = $firstname ?: 'Lecturer';
    $user->lastname   = $lastname ?: '';
    $user->email      = $email;
    $user->auth       = 'manual';
    $user->confirmed  = 1;
    $user->mnethostid = $DB->get_field('mnet_host', 'id',
        ['wwwroot' => $CFG->wwwroot]); // usually 1.

    $password         = generate_password(10);
    $user->password   = hash_internal_user_password($password);

    $userid = user_create_user($user, false, false);
    $user   = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

    // Send email with login info.
    $supportuser = core_user::get_support_user();
    $subject = 'Your Lecturer account has been created';
    $message = "Dear {$user->firstname},\n\n"
        . "An account has been created for you as a lecturer in the training system.\n\n"
        . "Username: {$user->username}\n"
        . "Password: {$password}\n\n"
        . "You can log in at: {$CFG->wwwroot}\n\n"
        . "Thank you.\n";

    email_to_user($user, $supportuser, $subject, $message);

    return $user;
}

/**
 * After a faculty is created, create:
 *  - course subcategory under the OU category
 *  - dean user (if not exists)
 *  - Dean role (category, archetype manager)
 *  - role assignment for dean at that category
 *
 * @param stdClass $faculty record from local_ouadmin_faculty
 * @return stdClass updated faculty record
 */
function local_ouadmin_after_faculty_created(stdClass $faculty): stdClass {
    global $DB;

    // 1. Find OU parent category using local_organization_ou.coursecatid.
    $parentcat = local_ouadmin_get_ou_category((int)$faculty->ouid);
    $parentid  = $parentcat ? (int)$parentcat->id : 0;

    // 2. Create course subcategory for faculty.
    $catdata = (object)[
        'name'     => $faculty->name,
        'idnumber' => 'FAC-' . $faculty->id,
        'parent'   => $parentid,
    ];
    $newcat = \core_course_category::create($catdata);
    $faculty->categoryid = $newcat->id;

    // 3. Create/get dean user.
    $deanuser = null;
    if (!empty($faculty->deanemail)) {
        $deanuser = local_ouadmin_create_or_get_dean_user(
            $faculty->deanfirstname ?? '',
            $faculty->deanlastname ?? '',
            $faculty->deanemail
        );
        $faculty->deanuserid = $deanuser->id;
    }

    // 4. Ensure Dean role and assign to category.
    if ($deanuser) {
        $roleid  = local_ouadmin_ensure_dean_role();
        $context = context_coursecat::instance($faculty->categoryid);
        role_assign($roleid, $deanuser->id, $context->id);
    }

    // 5. Save faculty record with categoryid & deanuserid.
    $DB->update_record('local_ouadmin_faculty', $faculty);

    return $faculty;
}

/**
 * Create cohort for an intake.
 * Cohort name format: oushortname-facultyid-intakecode
 *
 * Uses:
 *  - local_organization_ou.shortname  (via intake->ouid)
 *  - intake->facultyid
 *  - intake->code
 *
 * Cohort is system-level (context_system).
 *
 * @param stdClass $intake record from local_ouadmin_intake
 * @return stdClass intake with ->cohortid set
 */
function local_ouadmin_create_intake_cohort(stdClass $intake): stdClass {
    global $DB;

    $manager = $DB->get_manager();
    $oushort = 'ou' . $intake->ouid;

    if ($manager->table_exists('local_organization_ou')) {
        $ou = $DB->get_record('local_organization_ou',
            ['id' => $intake->ouid], 'id, shortname', IGNORE_MISSING);
        if ($ou && !empty($ou->shortname)) {
            $oushort = $ou->shortname;
        }
    }

    $code = trim((string)$intake->code);
    if ($code === '') {
        $code = 'INT' . $intake->id;
    }

    $name = $oushort . '-' . $intake->facultyid . '-' . $code;

    // Avoid duplicate cohorts with same idnumber.
    if ($existing = $DB->get_record('cohort', ['idnumber' => $name], '*', IGNORE_MISSING)) {
        $intake->cohortid = $existing->id;
        $DB->update_record('local_ouadmin_intake', $intake);
        return $intake;
    }

    $syscontext = context_system::instance();

    $cohort = new stdClass();
    $cohort->contextid           = $syscontext->id;
    $cohort->name                = $name;
    $cohort->idnumber            = $name;
    $cohort->description         = 'Auto-created cohort for intake ' . $code;
    $cohort->descriptionformat   = FORMAT_HTML;
    $cohort->visible             = 1;
    $cohort->component           = 'local_ouadmin';

    $cohortid = cohort_add_cohort($cohort);

    $intake->cohortid = $cohortid;
    $DB->update_record('local_ouadmin_intake', $intake);

    return $intake;
}

/**
 * After an intake is created, create:
 *  - subcategory under the faculty category
 *  - cohort with name oushortname-facultyid-intakecode
 *
 * Structure:
 *   OU category (from local_organization_ou.coursecatid)
 *     -> Faculty category (local_ouadmin_faculty.categoryid)
 *       -> Intake category (created here)
 *
 * @param stdClass $intake record from local_ouadmin_intake
 * @return stdClass updated intake record
 */
function local_ouadmin_after_intake_created(stdClass $intake): stdClass {
    global $DB;

    // Get faculty to know its category.
    $faculty = $DB->get_record('local_ouadmin_faculty',
        ['id' => $intake->facultyid], 'id, name, categoryid, ouid', MUST_EXIST);

    $parentid = (int)$faculty->categoryid;

    // 1. Create course subcategory for intake.
    $catdata = (object)[
        'name'     => $intake->name,
        'idnumber' => 'INT-' . $intake->id,
        'parent'   => $parentid,
    ];
    $newcat = \core_course_category::create($catdata);
    $intake->categoryid = $newcat->id;

    // 2. Create cohort.
    $intake->ouid = $faculty->ouid; // ensure ouid present.
    $intake = local_ouadmin_create_intake_cohort($intake);

    return $intake;
}
