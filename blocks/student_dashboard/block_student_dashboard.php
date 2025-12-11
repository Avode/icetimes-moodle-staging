<?php
defined('MOODLE_INTERNAL') || die();

class block_student_dashboard extends block_base {
    public function init() {
        // Suppress the default title; we'll override in get_content().
        $this->title = '';
    }

    public function applicable_formats() {
        return ['all' => true, 'mod' => false];
    }

    public function get_content() {
        global $USER, $PAGE;
        if ($this->content !== null) { return $this->content; }

        $this->content = new stdClass();

        /** @var \block_student_dashboard\output\renderer $renderer */
        $renderer = $PAGE->get_renderer('block_student_dashboard');
        $renderable = new \block_student_dashboard\output\dashboard((int)$USER->id);

        // Render dashboard body
        $body = $renderer->render($renderable);

        // Add custom header with student's first name
        $firstname = format_string($USER->firstname, true);
        $header = html_writer::tag('h2', $firstname, ['class' => 'bsd-header']);

        $this->content->text = $header . $body;
        $this->content->footer = '';
        return $this->content;
    }
}
