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
 * QualiScope Run page.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/local/qualiscope/lib.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$campaignid = optional_param('campaignid', 0, PARAM_INT);
$referentialid = optional_param('referentialid', 0, PARAM_INT);

require_login();
require_sesskey();

if ($campaignid) {
    $campaign = $DB->get_record('local_qualiscope_campaigns', ['id' => $campaignid], '*', MUST_EXIST);
    $context = context_system::instance();
    require_capability('local/qualiscope:managecampaigns', $context);

    $referentialid = (int) $campaign->referential_id;
    $direct = optional_param('direct', 0, PARAM_INT);

    if ($direct) {
        $courseids = \local_qualiscope\analyser\course_analyser::get_campaign_course_ids($campaign);
        foreach ($courseids as $cid) {
            if (!\local_qualiscope\quota::can_audit($cid)) {
                continue;
            }
            $analyser = new \local_qualiscope\analyser\course_analyser($cid, $campaignid, $referentialid);
            $analyser->run();
            $analyser->save_results($campaignid);
            \local_qualiscope\quota::record($cid);
        }
        $campaign->timecompleted = time();
        $campaign->timemodified = time();
        $DB->update_record('local_qualiscope_campaigns', $campaign);
        redirect(new moodle_url('/local/qualiscope/view_campaign.php', ['id' => $campaignid]));
    }

    // Interactive Real-time Progress Page.
    $output = $PAGE->get_renderer('local_qualiscope');

    $courseids = \local_qualiscope\analyser\course_analyser::get_campaign_course_ids($campaign);
    $coursesinfo = [];
    if (!empty($courseids)) {
        [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
        $coursesrecords = $DB->get_records_select('course', "id $insql", $inparams, 'fullname ASC', 'id,fullname');
        foreach ($courseids as $cid) {
            if (isset($coursesrecords[$cid])) {
                $coursesinfo[] = [
                    'id' => $cid,
                    'fullname' => \local_qualiscope\helper::plain($coursesrecords[$cid]->fullname, $context),
                ];
            }
        }
    }

    $campaignname = \local_qualiscope\helper::plain($campaign->name, $context);

    $PAGE->set_url(new moodle_url('/local/qualiscope/run.php', ['campaignid' => $campaignid]));
    $PAGE->set_title(get_string('campaign_running_progress_title', 'local_qualiscope') . ' - ' . $campaignname);
    $PAGE->set_heading($campaignname);
    $PAGE->set_context($context);
    $PAGE->requires->js_call_amd('local_qualiscope/campaign_run', 'init', [[
        'campaignid' => $campaignid,
        'courses' => $coursesinfo,
        'targeturl' => (new moodle_url('/local/qualiscope/view_campaign.php', ['id' => $campaignid]))->out(false),
        'strings' => [
            'finished' => get_string('campaign_run_finished', 'local_qualiscope'),
            'redirecting' => get_string('campaign_run_redirecting', 'local_qualiscope'),
            'courseprogress' => get_string('campaign_run_progress_course', 'local_qualiscope', [
                'current' => '{$current}',
                'total' => '{$total}',
                'percentage' => '{$percentage}',
            ]),
        ],
    ]]);

    echo $OUTPUT->header();
    echo $output->render_campaign_run_progress([
        'campaignname' => $campaignname,
        'coursecount' => count($coursesinfo),
        'runningtitle' => get_string('campaign_running_progress_title', 'local_qualiscope'),
        'progresslabel' => get_string('campaign_courses_analysed', 'local_qualiscope'),
        'waitlabel' => get_string('campaign_running_wait', 'local_qualiscope'),
    ]);
    echo $OUTPUT->footer();
    exit;
}

if (!$courseid) {
    throw new \moodle_exception('missingparam', 'core', '', 'courseid');
}

require_login($courseid);
$context = context_system::instance();
require_capability('local/qualiscope:managecampaigns', $context);

if (!\local_qualiscope\quota::can_audit($courseid)) {
    redirect(
        new moodle_url('/local/qualiscope/campaigns.php'),
        get_string('licencerequired', 'local_qualiscope'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$analyser = new \local_qualiscope\analyser\course_analyser($courseid, 0, $referentialid ?: null);
$analyser->run();
\local_qualiscope\quota::record($courseid);

redirect(new moodle_url('/local/qualiscope/dashboard.php', ['courseid' => $courseid, 'referentialid' => $referentialid]));
