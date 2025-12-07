<?php
defined('MOODLE_INTERNAL') || die();

function local_studentinfo_pluginfile($course,
                                      $cm,
                                      $context,
                                      $filearea,
                                      $args,
                                      $forcedownload,
                                      array $options = []) {

    global $USER;

    require_login();

    // We only stored files in USER context.
    if ($context->contextlevel !== CONTEXT_USER) {
        send_file_not_found();
    }

    // In this test version, allow the owner or any user with moodle/site:config
    // (site admin) to see the files.
    if ($USER->id != $context->instanceid && !has_capability('moodle/site:config', context_system::instance())) {
        send_file_not_found();
    }

    // Areas we expect files in.
    $validareas = [
        'photoself',
        'photospouse',
        'courseletter',
        'coletter',
        'personalpart',
        'healthbmi',
        'bat118a',
        'mytentera',
        'pregnancy',
        'joiningproforma',   // ✅ new
        'enclosure1',        // ✅ new
    ];

    if (!in_array($filearea, $validareas, true)) {
        send_file_not_found();
    }

    // We expect URL like: /pluginfile.php/contextid/local_studentinfo/filearea/itemid/path/filename
    if (empty($args)) {
        send_file_not_found();
    }

    $itemid   = (int)array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? ('/' . implode('/', $args) . '/') : '/';

    $fs = get_file_storage();
    $file = $fs->get_file(
        $context->id,
        'local_studentinfo',
        $filearea,
        $itemid,
        $filepath,
        $filename
    );

    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}
