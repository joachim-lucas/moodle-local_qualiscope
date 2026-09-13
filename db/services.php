<?php

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_qualiscope_run_analysis' => [
        'classname'   => '\local_qualiscope\external\run_analysis',
        'methodname'  => 'execute',
        'description' => 'Run an audit analysis for a course.',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'local_qualiscope_add_evidence' => [
        'classname'   => '\local_qualiscope\external\add_evidence',
        'methodname'  => 'execute',
        'description' => 'Add an external evidence to a result.',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'local_qualiscope_create_action' => [
        'classname'   => '\local_qualiscope\external\create_action',
        'methodname'  => 'execute',
        'description' => 'Create a corrective action.',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],
];

$services = [
    'Qualiscope web service' => [
        'functions' => [
            'local_qualiscope_run_analysis',
            'local_qualiscope_add_evidence',
            'local_qualiscope_create_action',
        ],
        'enabled' => 1,
        'restrictedusers' => 0,
        'shortname' => 'qualiscope',
        'downloadfiles' => 0,
        'uploadfiles' => 0,
    ],
];