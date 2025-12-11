<?php
defined('MOODLE_INTERNAL') || die();

class block_multicollege_dashboard extends block_base {

    public function init(): void {
        $this->title = get_string('multicollege_dashboard', 'block_multicollege_dashboard');
    }

    public function applicable_formats(): array {
        return ['all' => true];
    }

    public function instance_allow_multiple(): bool {
        return false;
    }

    public function has_config(): bool {
        return false;
    }

    public function get_content(): stdClass {
        global $CFG, $OUTPUT, $DB, $PAGE, $USER;

        if ($this->content !== null) return $this->content;
        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        $cfg = (object)($this->config ?? []);
        $show_viewdashboard   = $cfg->show_viewdashboard   ?? true;
        $show_managedashboard = $cfg->show_managedashboard ?? true;
        $show_managecolleges  = $cfg->show_managecolleges  ?? true;
        $show_assignusers     = $cfg->show_assignusers     ?? true;
        $show_reports         = $cfg->show_reports         ?? true;

        $sysctx  = context_system::instance();
        $canhq   = has_capability('local/multicollege:manage', $sysctx);
        $canadmin= has_capability('local/multicollege:collegeadmin', $sysctx);
        $canview = has_capability('local/multicollege:viewreports', $sysctx);

        $college = null;
        $collegeid = 0;
        @include_once($CFG->dirroot.'/local/multicollege/lib.php');
        @include_once($CFG->dirroot.'/local/multicollege/classes/api.php');
        if (function_exists('local_multicollege_get_current_collegeid')) {
            $collegeid = (int)local_multicollege_get_current_collegeid();
            if ($collegeid && class_exists('local_multicollege\api')) {
                $college = \local_multicollege\api::college($collegeid);
            }
        }

        $tiles = [];
        if ($show_viewdashboard) {
            $tiles[] = ['label'=>get_string('viewdashboard', 'block_multicollege_dashboard'),
                        'url'=> new moodle_url('/local/multicollege/index.php'),
                        'icon'=>'i/course', 'show'=>true];
        }
        if ($show_managedashboard) {
            $tiles[] = ['label'=>get_string('managedashboard', 'block_multicollege_dashboard'),
                        'url'=> new moodle_url('/local/multicollege/dashboard_manage.php'),
                        'icon'=>'i/settings', 'show'=>($canhq || $canadmin)];
        }
        if ($show_managecolleges) {
            $tiles[] = ['label'=>get_string('managecolleges', 'block_multicollege_dashboard'),
                        'url'=> new moodle_url('/local/multicollege/manage.php'),
                        'icon'=>'i/site', 'show'=>$canhq];
        }
        if ($show_assignusers) {
            $tiles[] = ['label'=>get_string('assignusers', 'local_multicollege'),
                        'url'=> new moodle_url('/local/multicollege/assign_users.php'),
                        'icon'=>'i/users', 'show'=>$canhq];
        }
        if ($show_reports) {
            $tiles[] = ['label'=>get_string('reports', 'local_multicollege'),
                        'url'=> new moodle_url('/local/multicollege/report.php'),
                        'icon'=>'i/report', 'show'=>$canview];
        }
        $tiles = array_values(array_filter($tiles, fn($t) => !empty($t['show'])));

        $html = html_writer::start_div('mcdb-tiles');

        if ($canhq) {
            $colopts = $DB->get_records_menu('local_mc_colleges', null, 'name ASC', 'id, name');
            if ($colopts) {
                $html .= html_writer::start_tag('form', [
                    'method'=>'post',
                    'action'=>(new moodle_url('/local/multicollege/switch_college.php'))->out(false),
                    'class'=>'mcdb-switcher'
                ]);
                $html .= html_writer::empty_tag('input', ['type'=>'hidden', 'name'=>'sesskey', 'value'=>sesskey()]);
                $html .= html_writer::label(get_string('viewas','block_multicollege_dashboard'), 'mcdb-collegeid', ['class'=>'mr-2']);
                $html .= html_writer::select($colopts, 'collegeid', $collegeid, null, ['id'=>'mcdb-collegeid']);
                $html .= html_writer::empty_tag('input', ['type'=>'hidden', 'name'=>'returnurl', 'value'=>$PAGE->url->out_as_local_url(false)]);
                $html .= html_writer::empty_tag('input', ['type'=>'submit', 'class'=>'btn btn-secondary btn-sm ml-2', 'value'=>get_string('switch','block_multicollege_dashboard')]);
                $html .= html_writer::end_tag('form');
            }
        }

        if ($college) {
            $html .= html_writer::div(format_string($college->name), 'mcdb-college-name');
        } else {
            $html .= html_writer::div(get_string('nocollege', 'block_multicollege_dashboard'), 'mcdb-college-name muted');
        }

        foreach ($tiles as $t) {
            $html .= html_writer::start_div('mcdb-tile');
            $html .= html_writer::link($t['url'],
                $OUTPUT->pix_icon($t['icon'], '') . html_writer::span($t['label'], 'mcdb-label'),
                ['class' => 'mcdb-tile-link']
            );
            $html .= html_writer::end_div();
        }
        $html .= html_writer::end_div();

        $css = <<<CSS
        .mcdb-tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px}
        .mcdb-college-name{font-weight:600;margin:8px 0}
        .mcdb-tile{border:1px solid #e9ecef;border-radius:10px;background:#fff}
        .mcdb-tile-link{display:flex;gap:10px;align-items:center;padding:12px 14px;text-decoration:none}
        .mcdb-tile-link:hover{background:#f8f9fa;text-decoration:none}
        .mcdb-label{font-weight:500}
        .mcdb-switcher{display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap}
        CSS;
        $html .= html_writer::tag('style', $css);

        $this->content->text = $html;
        return $this->content;
    }
}
