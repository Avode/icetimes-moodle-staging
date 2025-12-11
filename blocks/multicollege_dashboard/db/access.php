<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'block/multicollege_dashboard:addinstance' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes'   => ['editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW]
    ],
    'block/multicollege_dashboard:myaddinstance' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['user' => CAP_ALLOW]
    ],
];
