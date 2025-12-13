<?php
defined('MOODLE_INTERNAL') || die();

class block_locallauncher extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_locallauncher');
    }

    public function applicable_formats() {
        return [
            'site' => true,
            'my' => true,           // Dashboard
            'course-view' => true,  // Optional
        ];
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function has_config() {
        return true;
    }

    public function get_content() {
        global $CFG;

        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        $cfg = get_config('block_locallauncher');

        // Helper to build a card config from settings with sane defaults.
        $cards = [
            $this->make_card($cfg, 1, [
                'title' => 'Manage Members',
                'desc'  => 'Manage organization members & assignments.',
                'slug'  => 'orgstructure',       // /local/<slug>/
                'path'  => '/index.php',         // appended to slug unless absolute URL
                'theme' => 'blue',               // blue | red | navy
            ]),
            $this->make_card($cfg, 2, [
                'title' => 'Map College to Category',
                'desc'  => 'Map org units to Moodle contexts/capabilities.',
                'slug'  => 'orgstructure',
                'path'  => '/index.php',
                'theme' => 'red',
            ]),
            $this->make_card($cfg, 3, [
                'title' => 'Open Student Info',
                'desc'  => 'Open the Student Info module.',
                'slug'  => 'studentinfo',
                'path'  => '/index.php',
                'theme' => 'navy',
            ]),

            $this->make_card($cfg, 4, [
                'title' => 'Library Management',
                'desc'  => 'Manage Physical and Virtual Library.',
                'slug'  => 'library',
                'path'  => '/index.php',
                'theme' => 'yellow',
            ]),
        ];

        // Build HTML.
        $out = html_writer::start_tag('div', ['class' => 'orgcards']);

        foreach ($cards as $c) {
            if (!$c) { continue; }

            // Destination URL — absolute allowed; otherwise /local/<slug>/<path>
            $href = null;
            if (!empty($c['url']) && preg_match('~^https?://~i', $c['url'])) {
                $href = $c['url'];
            } else {
                $slug = trim($c['slug'] ?? '', " \t\n\r\0\x0B/");
                $path = '/' . ltrim($c['path'] ?? '/index.php', '/');
                if ($slug === '') { continue; }

                // Skip rendering if the plugin folder isn't present (keeps block clean).
                $localdir = $CFG->dirroot . '/local/' . $slug;
                if (!is_dir($localdir)) { continue; }

                $href = new moodle_url('/local/' . $slug . $path);
            }

            $classes = 'orgcard org-' . s($c['theme']);

            $out .= html_writer::start_tag('a', ['class' => $classes, 'href' => (string)$href]);
            $out .= html_writer::tag('div', format_string($c['title']), ['class' => 'orgcard-title']);
            if (!empty($c['desc'])) {
                $out .= html_writer::tag('div', format_text($c['desc'], FORMAT_PLAIN), ['class' => 'orgcard-sub']);
            }
            $out .= html_writer::tag('span', get_string('manage', 'block_locallauncher'), ['class' => 'orgcard-cta']);
            $out .= html_writer::end_tag('a');
        }

        $out .= html_writer::end_tag('div');

        if ($out === '<div class="orgcards"></div>') {
            $out = html_writer::div(get_string('noitems', 'block_locallauncher'), 'muted');
        }

        $this->content->text = $out;
        return $this->content;
    }

    /**
     * Build a card config from admin settings with fallback defaults.
     */
    private function make_card($cfg, int $idx, array $defaults): ?array {
        $title = $cfg->{'card'.$idx.'title'} ?? $defaults['title'];
        $desc  = $cfg->{'card'.$idx.'desc'}  ?? $defaults['desc'];
        $slug  = $cfg->{'card'.$idx.'slug'}  ?? $defaults['slug'];
        $path  = $cfg->{'card'.$idx.'path'}  ?? $defaults['path'];
        $url   = $cfg->{'card'.$idx.'url'}   ?? ''; // optional absolute override
        $theme = $cfg->{'card'.$idx.'theme'} ?? $defaults['theme'];

        // Sanity trims
        $title = trim((string)$title);
        if ($title === '') { return null; }

        return [
            'title' => $title,
            'desc'  => (string)$desc,
            'slug'  => (string)$slug,
            'path'  => (string)$path,
            'url'   => (string)$url,
            'theme' => in_array($theme, ['blue','red','navy','yellow']) ? $theme : 'blue',
        ];
    }
}
