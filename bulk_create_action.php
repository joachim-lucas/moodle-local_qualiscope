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
 * QualiScope Bulk Create Action page.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once('../../config.php');
require_once($CFG->dirroot . '/local/qualiscope/lib.php');

$campaignid = required_param('campaignid', PARAM_INT);
$indicatorid = optional_param('indicatorid', 0, PARAM_INT);
$title = required_param('title', PARAM_TEXT);
$responsible = optional_param('responsible', '', PARAM_TEXT);
$duedate = optional_param('duedate', '', PARAM_RAW);
$priority = optional_param('priority', 'medium', PARAM_ALPHA);

require_login();
require_sesskey();
$context = context_system::instance();
require_capability('local/qualiscope:managecampaigns', $context);

$campaign = $DB->get_record('local_qualiscope_campaigns', ['id' => $campaignid], '*', MUST_EXIST);

$parsedduedate = $duedate ? strtotime($duedate) : 0;
$createdcount = 0;

if ($indicatorid > 0) {
    $results = $DB->get_records('local_qualiscope_results', [
        'campaign_id' => $campaignid,
        'indicator_id' => $indicatorid,
    ]);

    foreach ($results as $res) {
        if ($res->status === 'missing' || $res->status === 'verify') {
            $action = new stdClass();
            $action->result_id = $res->id;
            $action->campaign_id = $campaignid;
            $action->courseid = $res->courseid;
            $action->title = $title;
            $action->responsible = $responsible;
            $action->duedate = $parsedduedate;
            $action->priority = $priority;
            $action->status = 'todo';
            $action->userid = $USER->id;
            $action->timecreated = time();
            $action->timemodified = time();
            $DB->insert_record('local_qualiscope_actions', $action);
            $createdcount++;
        }
    }
} else {
    $results = $DB->get_records('local_qualiscope_results', ['campaign_id' => $campaignid]);
    $courseids = [];
    foreach ($results as $res) {
        $courseids[$res->courseid] = $res->courseid;
    }
    foreach ($courseids as $cid) {
        $action = new stdClass();
        $action->result_id = 0;
        $action->campaign_id = $campaignid;
        $action->courseid = $cid;
        $action->title = $title;
        $action->responsible = $responsible;
        $action->duedate = $parsedduedate;
        $action->priority = $priority;
        $action->status = 'todo';
        $action->userid = $USER->id;
        $action->timecreated = time();
        $action->timemodified = time();
        $DB->insert_record('local_qualiscope_actions', $action);
        $createdcount++;
    }
}

redirect(
    new moodle_url('/local/qualiscope/view_campaign.php', ['id' => $campaignid]),
    get_string('campaign_bulk_action_success', 'local_qualiscope', $createdcount),
    \core\output\notification::NOTIFY_SUCCESS
);
