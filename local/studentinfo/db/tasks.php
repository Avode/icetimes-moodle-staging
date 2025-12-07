<?php
defined('MOODLE_INTERNAL') || die();

$tasks = $tasks ?? []; // if merging

$tasks[] = [
    'classname' => '\\local_studentinfo\\task\\intake_cohort_sync_task',
    'blocking' => 0,
    'minute' => '30',
    'hour' => '2',   // 02:30 daily
    'day' => '*',
    'dayofweek' => '*',
    'month' => '*'
];

$tasks[] = [
    'classname' => '\\local_studentinfo\\task\\gpa_snapshot_task',
    'blocking' => 0,
    'minute' => '15', 'hour' => '3', 'day' => '*', 'dayofweek' => '*', 'month' => '*'
];
$tasks[] = [
    'classname' => '\\local_studentinfo\\task\\attendance_rollup_task',
    'blocking' => 0,
    'minute' => '*/30', 'hour' => '*', 'day' => '*', 'dayofweek' => '*', 'month' => '*'
];
