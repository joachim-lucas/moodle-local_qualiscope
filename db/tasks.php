<?php

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => '\local_qualiscope\task\audit_task',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '2',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
