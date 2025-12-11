<?php
declare(strict_types=1);

namespace block_student_dashboard\output;

defined('MOODLE_INTERNAL') || die();

class renderer extends \plugin_renderer_base {
    public function render_dashboard(dashboard $d): string {
        return $this->render_from_template(
            'block_student_dashboard/dashboard',
            $d->export_for_template($this)
        );
    }
}
