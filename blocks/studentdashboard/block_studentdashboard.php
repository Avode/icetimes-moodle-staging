<?php
defined('MOODLE_INTERNAL') || die();

class block_studentdashboard extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_studentdashboard');
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function applicable_formats() {
        return [
            'my' => true,
            'site-index' => false,
            'course-view' => true,
        ];
    }

    public function get_content() {
        global $USER, $DB, $PAGE, $OUTPUT, $CFG;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Only for logged-in, non-guest users.
        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        $userid = $USER->id;

        $lefthtml  = '';
        $righthtml = '';

        // ---------------------------------------
        // LEFT COLUMN: Service profile summary
        // ---------------------------------------
        $lefthtml .= html_writer::start_div('card mb-3');
        $lefthtml .= html_writer::div(get_string('left_heading', 'block_studentdashboard'), 'card-header fw-bold');
        $lefthtml .= html_writer::start_div('card-body');

        // User picture.
        $lefthtml .= html_writer::start_div('d-flex align-items-center mb-3');
        $lefthtml .= html_writer::div(
            $OUTPUT->user_picture($USER, ['size' => 80, 'class' => 'me-3 rounded-circle'])
        );
        $lefthtml .= html_writer::start_div('flex-grow-1');

        // Load local_studentinfo.
        $studentinfo = null;
        if ($DB->get_manager()->table_exists('local_studentinfo')) {
            $studentinfo = $DB->get_record('local_studentinfo', ['userid' => $userid]);
        }
        
        // Service & Kor names via lookup.
        $servicename = '';
        $korname     = '';
        
        if ($studentinfo && !empty($studentinfo->serviceid)
            && $DB->get_manager()->table_exists('local_studentlookup_service')) {
        
            $s = $DB->get_record('local_studentlookup_service',
                ['id' => $studentinfo->serviceid],
                'name',
                IGNORE_MISSING
            );
            if ($s && !empty($s->name)) {
                $servicename = $s->name;
            }
        }
        
        if ($studentinfo && !empty($studentinfo->korid)
            && $DB->get_manager()->table_exists('local_studentlookup_korregimen')) {
        
            $k = $DB->get_record('local_studentlookup_korregimen',
                ['id' => $studentinfo->korid],
                'name',
                IGNORE_MISSING
            );
            if ($k && !empty($k->name)) {
                $korname = $k->name;
            }
        }

        // Rank name via lookup.
        $rankname = '';
        if ($studentinfo && !empty($studentinfo->rankid)
            && $DB->get_manager()->table_exists('local_studentlookup_rank')) {

            $r = $DB->get_record('local_studentlookup_rank', ['id' => $studentinfo->rankid], 'name', IGNORE_MISSING);
            if ($r && !empty($r->name)) {
                $rankname = $r->name;
            }
        } else if ($studentinfo && !empty($studentinfo->pangkat)) {
            $rankname = $studentinfo->pangkat;
        }

        // Service number.
        $serviceno = $studentinfo->tentera_no ?? '';

        // Rank & fullname.
        $rankfullname = trim(($rankname ? $rankname . ' ' : '') . fullname($USER));

        // Service no + rank & name block.
        $serviceblock =
            html_writer::tag(
                'div',
                s($serviceno),
                ['class' => 'fw-bold']
            ) .
            html_writer::tag(
                'div',
                s($rankfullname),
                ['class' => 'fw-bold']
            );

        $lefthtml .= html_writer::tag(
            'div',
            $serviceblock,
            ['class' => 'mb-1']
        );

        $lefthtml .= html_writer::end_div(); // flex-grow-1
        $lefthtml .= html_writer::end_div(); // d-flex

        // College / Faculty / Intake.
        $college = '';
        $faculty = '';
        $intake  = '';

        if ($DB->get_manager()->table_exists('local_studentinfo_studentmap') && $studentinfo) {
            $map = $DB->get_record('local_studentinfo_studentmap', ['userid' => $userid], '*', IGNORE_MISSING);
            if ($map) {
                // College.
                if (!empty($map->ouid) && $DB->get_manager()->table_exists('local_organization_ou')) {
                    $ourec = $DB->get_record('local_organization_ou',
                        ['id' => $map->ouid], 'fullname', IGNORE_MISSING);
                    if ($ourec) {
                        $college = $ourec->fullname;
                    }
                }
                // Faculty.
                if (!empty($map->facultyid) && $DB->get_manager()->table_exists('local_ouadmin_faculty')) {
                    $fac = $DB->get_record('local_ouadmin_faculty',
                        ['id' => $map->facultyid], 'name', IGNORE_MISSING);
                    if ($fac) {
                        $faculty = $fac->name;
                    }
                }
                // Intake.
                if (!empty($map->intakeid) && $DB->get_manager()->table_exists('local_ouadmin_intake')) {
                    $int = $DB->get_record('local_ouadmin_intake',
                        ['id' => $map->intakeid], 'name', IGNORE_MISSING);
                    if ($int) {
                        $intake = $int->name;
                    }
                }
            }
        }

        $rows = [];
        $rows[] = ['label' => get_string('college', 'block_studentdashboard'), 'value' => $college ?: '-'];
        $rows[] = ['label' => get_string('faculty', 'block_studentdashboard'), 'value' => $faculty ?: '-'];
        $rows[] = ['label' => get_string('intake', 'block_studentdashboard'),  'value' => $intake ?: '-'];

        foreach ($rows as $row) {
            $lefthtml .= html_writer::tag(
                'div',
                html_writer::tag('span', $row['label'] . ': ', ['class' => 'fw-semibold']) .
                html_writer::tag('span', s($row['value'])),
                ['class' => 'mb-1']
            );
        }
        
        // ----------------------------
        // Contact details
        // ----------------------------
        $lefthtml .= html_writer::empty_tag('hr');
        
        $lefthtml .= html_writer::tag(
            'div',
            get_string('sec_contact', 'local_studentinfo'),
            ['class' => 'fw-bold mb-1']
        );
        
        // Phone & email: telefon from local_studentinfo, email from user.
        $contactrows = [];
        
        if ($studentinfo && !empty($studentinfo->telefon)) {
            $contactrows[] = [
                'label' => get_string('telefon', 'local_studentinfo'),
                'value' => $studentinfo->telefon
            ];
        }
        
        // Use core user email as primary.
        $userrecord = $DB->get_record('user', ['id' => $userid], 'id, email', IGNORE_MISSING);
        if ($userrecord && !empty($userrecord->email)) {
            $contactrows[] = [
                'label' => get_string('email', 'local_studentinfo'),
                'value' => $userrecord->email
            ];
        }
        
        foreach ($contactrows as $row) {
            $lefthtml .= html_writer::tag(
                'div',
                html_writer::tag('span', $row['label'] . ': ', ['class' => 'fw-semibold']) .
                html_writer::tag('span', s($row['value'])),
                ['class' => 'mb-1']
            );
        }
        
        // ----------------------------
        // Identity & Service details
        // ----------------------------
        $lefthtml .= html_writer::empty_tag('hr');
        
        $lefthtml .= html_writer::tag(
            'div',
            get_string('sec_identity', 'local_studentinfo'),
            ['class' => 'fw-bold mb-1']
        );
        
        // Build Identity & Service rows using local_studentinfo strings.
        $identityrows = [];
        
        if ($servicename !== '') {
            $identityrows[] = [
                'label' => get_string('perkhidmatan', 'local_studentinfo'),
                'value' => $servicename
            ];
        }
        if ($korname !== '') {
            $identityrows[] = [
                'label' => get_string('rejimen', 'local_studentinfo'),
                'value' => $korname
            ];
        }
        if ($studentinfo && !empty($studentinfo->jenis_tauliah)) {
            $identityrows[] = [
                'label' => get_string('jenis_tauliah', 'local_studentinfo'),
                'value' => $studentinfo->jenis_tauliah
            ];
        }
        if ($studentinfo && !empty($studentinfo->tarikh_masuk)) {
            $identityrows[] = [
                'label' => get_string('tarikh_masuk', 'local_studentinfo'),
                'value' => userdate($studentinfo->tarikh_masuk)
            ];
        }
        
        if ($studentinfo && !empty($studentinfo->tarikh_tauliah)) {
            $identityrows[] = [
                'label' => get_string('tarikh_tauliah', 'local_studentinfo'),
                'value' => userdate($studentinfo->tarikh_tauliah)
            ];
        }
        if ($studentinfo && !empty($studentinfo->tarikh_tamat)) {
            $identityrows[] = [
                'label' => get_string('tarikh_tamat', 'local_studentinfo'),
                'value' => userdate($studentinfo->tarikh_tamat)
            ];
        }
        
        foreach ($identityrows as $row) {
            $lefthtml .= html_writer::tag(
                'div',
                html_writer::tag('span', $row['label'] . ': ', ['class' => 'fw-semibold']) .
                html_writer::tag('span', s($row['value'])),
                ['class' => 'mb-1']
            );
        }
        
        

        // Profile completeness badge (similar to local_profileguard).
        $complete = false;
        if ($DB->get_manager()->table_exists('local_studentinfo')) {
            $complete = $this->is_profile_complete($userid);
        }

        $badgeclass = $complete ? 'badge bg-success' : 'badge bg-danger';
        $badgelabel = $complete
            ? get_string('profile_status_complete', 'block_studentdashboard')
            : get_string('profile_status_incomplete', 'block_studentdashboard');

        $lefthtml .= html_writer::tag(
            'div',
            html_writer::tag('span', $badgelabel, ['class' => $badgeclass]),
            ['class' => 'mt-2 mb-3']
        );

        // View / Edit buttons.
        $buttons = [];
        if ($DB->get_manager()->table_exists('local_studentinfo')) {
            $viewurl = new moodle_url('/local/studentinfo/view.php', ['userid' => $userid]);
            $editurl = new moodle_url('/local/studentinfo/edit.php', ['userid' => $userid]);

            $buttons[] = html_writer::link(
                $viewurl,
                get_string('profile_view', 'block_studentdashboard'),
                ['class' => 'btn btn-sm btn-outline-secondary me-2']
            );
            $buttons[] = html_writer::link(
                $editurl,
                get_string('profile_edit', 'block_studentdashboard'),
                ['class' => 'btn btn-sm btn-primary']
            );
        }
        if ($buttons) {
            $lefthtml .= html_writer::div(implode('', $buttons), 'mt-1');
        }

        $lefthtml .= html_writer::end_div(); // card-body
        $lefthtml .= html_writer::end_div(); // card

        // ---------------------------------------
        // RIGHT COLUMN: Courses & tasks
        // ---------------------------------------
        // My Courses.
        $righthtml .= html_writer::start_div('card mb-3');
        $righthtml .= html_writer::div(get_string('right_courses_heading', 'block_studentdashboard'), 'card-header fw-bold');
        $righthtml .= html_writer::start_div('card-body');

        // Courses: use enrol_get_users_courses.
        require_once($CFG->libdir . '/enrollib.php');
        require_once($CFG->dirroot . '/calendar/lib.php');   // ✅ add this
        $courses = enrol_get_users_courses($userid, true, 'id, fullname, shortname');


        if ($courses) {
            $righthtml .= html_writer::start_tag('ul', ['class' => 'list-unstyled mb-0']);
            $maxcourses = 5;
            $count = 0;
            foreach ($courses as $course) {
                $count++;
                if ($count > $maxcourses) {
                    break;
                }
                $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
                $righthtml .= html_writer::tag(
                    'li',
                    html_writer::link($courseurl, format_string($course->fullname)),
                    ['class' => 'mb-1']
                );
            }
            $righthtml .= html_writer::end_tag('ul');
        } else {
            $righthtml .= html_writer::tag(
                'div',
                get_string('no_courses', 'block_studentdashboard'),
                ['class' => 'text-muted small']
            );
        }

        $righthtml .= html_writer::end_div(); // card-body
        $righthtml .= html_writer::end_div(); // card

        // ---------------------------------------
        // Upcoming & current tasks (calendar events for enrolled courses)
        // ---------------------------------------
        $righthtml .= html_writer::start_div('card mb-3');
        $righthtml .= html_writer::div(
            get_string('right_tasks_heading', 'block_studentdashboard'),
            'card-header fw-bold'
        );
        $righthtml .= html_writer::start_div('card-body');
        
        // Build list of course IDs the user is enrolled in.
        $courseids = array_keys($courses ?? []);
        $events    = [];
        $now       = time();
        
        // We look 30 days back and 30 days ahead.
        $starttime = $now - (30 * DAYSECS);
        $endtime   = $now + (30 * DAYSECS);
        
        if ($courseids) {
            $events = calendar_get_events(
                $starttime,
                $endtime,
                [$userid],   // user
                [],          // groups
                $courseids,  // courses
                true         // withduration
            );
        }
        
        if ($events) {
            // Sort by start time ascending.
            usort($events, function($a, $b) {
                if ($a->timestart == $b->timestart) {
                    return 0;
                }
                return ($a->timestart < $b->timestart) ? -1 : 1;
            });
        
            $righthtml .= html_writer::start_tag('ul', ['class' => 'list-unstyled mb-0']);
        
            $count    = 0;
            $maxtasks = 6;
            foreach ($events as $e) {
                $count++;
                if ($count > $maxtasks) {
                    break;
                }
            
                $name = format_string($e->name);
            
                // Start and end time.
                $duration = isset($e->timeduration) ? (int)$e->timeduration : 0;
                $timestart = (int)$e->timestart;
                $timeend   = $duration > 0 ? $timestart + $duration : 0;
            
                $startstr = userdate($timestart);
                $endstr   = $timeend > 0 ? userdate($timeend) : '';
            
                // Course shortname.
                $shortname = '';
                if (isset($courses[$e->courseid])) {
                    $shortname = '[' . format_string($courses[$e->courseid]->shortname) . '] ';
                }
            
                $eventurl = new moodle_url('/calendar/event.php', ['event' => $e->id]);
            
                if ($endstr) {
                    // Has end date: [COURSE] Name – start → end
                    $label = $shortname . $name . ' – ' . $startstr . ' → ' . $endstr;
                } else {
                    // No duration: [COURSE] Name – date
                    $label = $shortname . $name . ' – ' . $startstr;
                }
            
                $righthtml .= html_writer::tag(
                    'li',
                    html_writer::link($eventurl, $label),
                    ['class' => 'mb-1']
                );
            }

        
            $righthtml .= html_writer::end_tag('ul');
        
        } else {
            // No events in the 60-day window.
            $righthtml .= html_writer::tag(
                'div',
                get_string('no_tasks', 'block_studentdashboard'),
                ['class' => 'text-muted small']
            );
        }
        
        $righthtml .= html_writer::end_div(); // card-body
        $righthtml .= html_writer::end_div(); // card





        // Wrap into two columns.
        $html = html_writer::start_div('row');
        $html .= html_writer::div($lefthtml, 'col-md-6');
        $html .= html_writer::div($righthtml, 'col-md-6');
        $html .= html_writer::end_div();

        $this->content->text = $html;

        return $this->content;
    }

    /**
     * Basic profile completeness check similar to local_profileguard, but non-blocking.
     */
    protected function is_profile_complete(int $userid): bool {
        global $DB;

        if (!$DB->get_manager()->table_exists('local_studentinfo')) {
            return false;
        }

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

        // Contact: telefon, email.
        if (empty($rec->telefon)) {
            return false;
        }
        $user = $DB->get_record('user', ['id' => $userid], 'id, email', IGNORE_MISSING);
        if (!$user || trim((string)$user->email) === '') {
            return false;
        }

        return true;
    }
}
