<?php

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
