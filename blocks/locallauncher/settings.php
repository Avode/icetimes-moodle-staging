<?php
defined('MOODLE_INTERNAL') || die();

if ($page = new admin_settingpage('block_locallauncher', get_string('pluginname', 'block_locallauncher'))) {

    $page->add(new admin_setting_heading(
        'block_locallauncher/header',
        get_string('settingsheader', 'block_locallauncher'), ''
    ));

    // Helper to add controls per card.
    $addcard = function(int $i, string $defTitle, string $defDesc, string $defSlug, string $defPath, string $defTheme) use ($page) {
        $page->add(new admin_setting_heading(
            "block_locallauncher/card{$i}heading",
            "Card #{$i}",
            ''
        ));

        $page->add(new admin_setting_configtext(
            "block_locallauncher/card{$i}title",
            get_string('cardtitle', 'block_locallauncher'), '', $defTitle, PARAM_TEXT
        ));
        $page->add(new admin_setting_configtext(
            "block_locallauncher/card{$i}desc",
            get_string('carddesc', 'block_locallauncher'), '', $defDesc, PARAM_TEXT
        ));
        $page->add(new admin_setting_configtext(
            "block_locallauncher/card{$i}slug",
            get_string('cardslug', 'block_locallauncher'), '', $defSlug, PARAM_ALPHANUMEXT
        ));
        $page->add(new admin_setting_configtext(
            "block_locallauncher/card{$i}path",
            get_string('cardpath', 'block_locallauncher'), '', $defPath, PARAM_RAW_TRIMMED
        ));
        $page->add(new admin_setting_configtext(
            "block_locallauncher/card{$i}url",
            get_string('cardurl', 'block_locallauncher'),
            'If provided and starts with http(s), this will be used instead of slug+path.',
            '', PARAM_RAW_TRIMMED
        ));
        $page->add(new admin_setting_configselect(
            "block_locallauncher/card{$i}theme",
            get_string('cardtheme', 'block_locallauncher'), '',
            $defTheme,
            [
                'blue' => get_string('theme_blue', 'block_locallauncher'),
                'red'  => get_string('theme_red',  'block_locallauncher'),
                'navy' => get_string('theme_navy', 'block_locallauncher'),
                'yellow' => get_string('theme_yellow', 'block_locallauncher'),
            ]
        ));
    };

    $addcard(1, 'Manage Members', 'Manage organization members & assignments.', 'orgstructure', '/index.php', 'blue');
    $addcard(2, 'Map College to Category', 'Map org units to Moodle contexts/capabilities.', 'orgstructure', '/index.php', 'red');
    $addcard(3, 'Open Student Info', 'Open the Student Info module.', 'studentinfo', '/index.php', 'navy');
    $addcard(4, 'Manage Librar', 'Open the Library Management module.', 'library', '/index.php', 'yellow');

    $ADMIN->add('blocksettings', $page);
}
