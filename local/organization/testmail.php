<?php
require(__DIR__ . '/../../config.php');

require_login();

$to      = $USER;
$from    = core_user::get_noreply_user();
$subject = 'Moodle email_to_user() test';
$text    = "Hi {$to->firstname},\n\nThis is a test of Moodle email_to_user().\n";
$html    = "<p>Hi {$to->firstname},</p><p>This is a test of <code>email_to_user()</code>.</p>";

$ok = email_to_user($to, $from, $subject, $text, $html);

echo $ok ? 'email_to_user(): OK (Moodle says it sent)' : 'email_to_user(): FAILED';
