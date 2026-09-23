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
 * QualiScope Save Action page.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once('../../config.php');
require_once($CFG->dirroot . '/local/qualiscope/lib.php');

require_login();
require_sesskey();

$resultid = optional_param('resultid', 0, PARAM_INT);
$campaignid = required_param('campaignid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$title = required_param('title', PARAM_TEXT);
$responsible = optional_param('responsible', '', PARAM_TEXT);
$duedate = optional_param('duedate', '', PARAM_RAW);
$priority = optional_param('priority', 'medium', PARAM_ALPHA);

$action = new stdClass();
$action->result_id = $resultid;
$action->campaign_id = $campaignid;
$action->courseid = $courseid;
$action->title = $title;
$action->responsible = $responsible;
$action->duedate = $duedate ? strtotime($duedate) : 0;
$action->priority = $priority;
$action->status = 'todo';
$action->userid = $USER->id;
$action->timecreated = time();
$action->timemodified = time();

$DB->insert_record('local_qualiscope_actions', $action);

$redirecturl = new moodle_url('/local/qualiscope/actions.php', [
    'courseid' => $courseid,
    'campaignid' => $campaignid,
]);

redirect($redirecturl);
