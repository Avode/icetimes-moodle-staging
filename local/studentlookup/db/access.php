<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/studentlookup:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW
        ]
    ],
    'local/studentlookup:manage' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW
        ]
    ],
];

