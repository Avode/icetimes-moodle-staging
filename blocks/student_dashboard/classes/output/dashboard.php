<?php
declare(strict_types=1);

namespace block_student_dashboard\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;
use stdClass;
use core_user;

/**
 * Renderable context for Student Dashboard.
 * PERSONAL info is wired. HOSTEL is now wired from:
 *   {log_hostel_alloc} a
 *   {log_hostel} h (a.hostelid = h.id)
 *   {log_hostel_bed} b (a.bedid   = b.id)
 */
class dashboard implements renderable, templatable {
    protected $userid;

    public function __construct(int $userid) {
        $this->userid = $userid;
    }

    /** Read custom profile fields (optional) */
    protected function get_profile_fields(int $userid): array {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $rec = \profile_user_record($userid, false);
        if (!$rec) { return []; }
        return (array)$rec;
    }

    /** Get cohort names for a user */
    protected function get_user_cohorts(int $userid): array {
        global $DB;
        $sql = "SELECT c.name
                  FROM {cohort_members} cm
                  JOIN {cohort} c ON c.id = cm.cohortid
                 WHERE cm.userid = ?
              ORDER BY c.name ASC";
        $names = $DB->get_fieldset_sql($sql, [$userid]);
        return $names ?: [];
    }

    /**
     * Fetch the current (or most recent) hostel allocation for the user.
     * Preference: active (checkout IS NULL) first, then most recent by checkin.
     */
    protected function get_current_hostel_allocation(int $userid): ?stdClass {
        global $DB;

        $sql = "SELECT
                    a.id,
                    a.orgunitid,
                    a.hostelid,
                    a.bedid,
                    a.userid,
                    a.checkin,
                    a.checkin_time,
                    a.checkout,
                    a.checkout_time,
                    a.status,
                    h.name  AS hostelname,
                    b.label AS bedlabel
                FROM {log_hostel_alloc} a
                JOIN {log_hostel}      h ON h.id = a.hostelid
                JOIN {log_hostel_bed}  b ON b.id = a.bedid
               WHERE a.userid = ?
            ORDER BY
                CASE WHEN a.checkout IS NULL THEN 0 ELSE 1 END ASC,
                a.checkin DESC";

        // Cross-DB safe pagination (no LIMIT in SQL string).
        $records = $DB->get_records_sql($sql, [$userid], 0, 1);
        if (!$records) { return null; }
        return reset($records);
    }

    /** Format either datetime (if *_time exists) or date string */
    protected function format_alloc_dt(?string $date, ?int $time): string {
        if (!empty($time)) {
            return \userdate($time, get_string('strftimedatetime', 'langconfig'));
        }
        if (!empty($date) && $date !== '0000-00-00') {
            // Render as site short date.
            return \userdate(strtotime($date), get_string('strftimedate', 'langconfig'));
        }
        return '—';
        }

    public function export_for_template(renderer_base $output): array {
        // -------- PERSONAL (wired) --------
        $user = core_user::get_user(
            $this->userid,
            'id,firstname,lastname,alternatename,firstnamephonetic,lastnamephonetic,' .
            'middlename,username,email,department,institution,idnumber,phone1,phone2,city,country'
        );
        $picturehtml = $output->user_picture($user, ['size' => 100, 'link' => false]);
        $fullname = \fullname($user, true);

        $profile = $this->get_profile_fields($this->userid);
        $program = $profile['program'] ?? $profile['programme'] ?? $user->institution ?? '';
        $cohortnames = $this->get_user_cohorts($this->userid);
        $cohortlabel = empty($cohortnames) ? '' : implode(', ', $cohortnames);
        $orgunit = $profile['orgunit'] ?? $user->department ?? $user->institution ?? '';

        $personal = [
            'fullname'    => $fullname ?: ($user->firstname . ' ' . $user->lastname),
            'studentid'   => $user->idnumber ?: '[ID not set]',
            'program'     => $program ?: '—',
            'cohort'      => $cohortlabel ?: '—',
            'orgunit'     => $orgunit ?: '—',
            'email'       => $user->email ?: '—',
            'phone'       => ($user->phone1 ?: $user->phone2) ?: '—',
            'picturehtml' => $picturehtml,
        ];

        // -------- HOSTEL (wired) --------
        $hostel = [
            'building'  => '—',
            'room'      => '—',
            'validfrom' => '—',
            'validto'   => '—',
            'status'    => '—',
        ];
        if ($alloc = $this->get_current_hostel_allocation($this->userid)) {
            $hostel = [
                'building'  => $alloc->hostelname ?: '—',
                'room'      => $alloc->bedlabel   ?: '—',
                'validfrom' => $this->format_alloc_dt($alloc->checkin,  $alloc->checkin_time),
                'validto'   => $this->format_alloc_dt($alloc->checkout, $alloc->checkout_time),
                'status'    => $alloc->status ?: '—',
            ];
        }

        // -------- OTHER SECTIONS (placeholders for now) --------
        $kpi = [
            'gpa'                => '[[GPA]]',
            'cgpa'               => '[[CGPA]]',
            'courses_inprogress' => '[[N]]',
            'avg_grade'          => '[[B+ / 78%]]',
            'attendance_rate'    => '[[92%]]',
            'badges'             => [
                ['name' => '[[Dean List]]'],
                ['name' => '[[Perfect Attendance]]'],
            ],
        ];

        $assets = [
            // Facilities → Hostel (wired above) and Weapon (placeholder)
            'hostel' => $hostel,
            'weapon' => [
                'type'    => '[[TYPE]]',
                'serial'  => '[[SERIAL]]',
                'issued'  => '[[YYYY-MM-DD]]',
                'returnby'=> '[[YYYY-MM-DD]]',
            ],
        ];

        $tasks = [
            'items' => [
                ['type' => 'Assignment', 'title' => '[[Assignment Title]]', 'duedate' => '[[YYYY-MM-DD]]', 'url' => '#'],
                ['type' => 'Quiz/Test',  'title' => '[[Quiz 1]]',           'duedate' => '[[YYYY-MM-DD]]', 'url' => '#'],
                ['type' => 'Survey',     'title' => '[[Course Survey]]',    'duedate' => '[[YYYY-MM-DD]]', 'url' => '#'],
                ['type' => 'Leave',      'title' => '[[Leave Application]]','duedate' => '[[YYYY-MM-DD]]', 'url' => '#'],
            ],
        ];

        $library = [
            'loans' => [
                ['title' => '[[Book Title A]]', 'duedate' => '[[YYYY-MM-DD]]', 'status' => '[[APPROVED/ONLOAN]]'],
                ['title' => '[[Book Title B]]', 'duedate' => '[[YYYY-MM-DD]]', 'status' => '[[APPROVED/ONLOAN]]'],
            ],
        ];

        return [
            'useplaceholders' => false,
            'personal' => $personal,
            'kpi'      => $kpi,
            'assets'   => $assets,
            'tasks'    => $tasks,
            'library'  => $library,
            'strings'  => [
                'personal' => get_string('section_personal', 'block_student_dashboard'),
                'kpi'      => get_string('section_kpi', 'block_student_dashboard'),
                'assets'   => get_string('section_assets', 'block_student_dashboard'),
                'tasks'    => get_string('section_tasks', 'block_student_dashboard'),
                'library'  => get_string('section_library', 'block_student_dashboard'),
            ],
        ];
    }
}
