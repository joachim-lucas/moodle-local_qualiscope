<?php

require_once('../../config.php');
require_once($CFG->dirroot . '/local/qualiscope/lib.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$campaignid = optional_param('campaignid', 0, PARAM_INT);
$referentialid = optional_param('referentialid', 0, PARAM_INT);

if ($sesskey = optional_param('sesskey', '', PARAM_RAW)) {
    require_sesskey($sesskey);
}

require_login();

if ($campaignid) {
    $campaign = $DB->get_record('local_qualiopi_campaigns', ['id' => $campaignid], '*', MUST_EXIST);
    $context = context_system::instance();
    require_capability('local/qualiscope:managecampaigns', $context);

    $referentialid = (int) $campaign->referential_id;
    $courseids = \local_qualiscope\analyser\course_analyser::get_campaign_course_ids($campaign);
    foreach ($courseids as $cid) {
        $analyser = new \local_qualiscope\analyser\course_analyser($cid, $campaignid, $referentialid);
        $analyser->run();
        $analyser->save_results($campaignid);
    }

    $campaign->timecompleted = time();
    $campaign->timemodified = time();
    $DB->update_record('local_qualiopi_campaigns', $campaign);

    redirect(new moodle_url('/local/qualiscope/view_campaign.php', ['id' => $campaignid]));
}

if (!$courseid) {
    throw new \moodle_exception('missingparam', 'core', '', 'courseid');
}

require_login($courseid);
$context = context_system::instance();
require_capability('local/qualiscope:managecampaigns', $context);

$analyser = new \local_qualiscope\analyser\course_analyser($courseid, 0, $referentialid ?: null);
$analyser->run();

redirect(new moodle_url('/local/qualiscope/dashboard.php', ['courseid' => $courseid, 'referentialid' => $referentialid]));