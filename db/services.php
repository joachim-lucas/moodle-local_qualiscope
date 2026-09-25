<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Defines the external services and functions exposed by the local_qualiscope plugin.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


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
