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
 * QualiScope Save Campaign page.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once('../../config.php');
require_once($CFG->dirroot . '/local/qualiscope/lib.php');

require_login();
require_sesskey();
require_capability('local/qualiscope:managecampaigns', context_system::instance());

$name = required_param('name', PARAM_TEXT);
$referentialid = required_param('referential_id', PARAM_INT);
$scope = required_param('scope', PARAM_ALPHA);
$scopeids = optional_param_array('scopeids', [], PARAM_INT);

$campaign = new stdClass();
$campaign->name = $name;
$campaign->referential_id = $referentialid;
$campaign->scope = $scope;
$campaign->scopeids = $scope ? json_encode($scopeids) : '';
$campaign->userid = $USER->id;
$campaign->timecreated = time();
$campaign->timemodified = time();
$campaign->timecompleted = 0;

$DB->insert_record('local_qualiscope_campaigns', $campaign);

redirect(new moodle_url('/local/qualiscope/campaigns.php'));
