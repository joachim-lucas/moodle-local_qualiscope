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
 * QualiScope Campaigns page.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once('../../config.php');
require_once($CFG->dirroot . '/local/qualiscope/lib.php');

$PAGE->set_url(new moodle_url('/local/qualiscope/campaigns.php'));

require_login();
$context = context_system::instance();
require_capability('local/qualiscope:managecampaigns', $context);

$PAGE->set_title(get_string('campaign_title', 'local_qualiscope'));
$PAGE->set_heading(get_string('campaign_title', 'local_qualiscope'));
$PAGE->set_context($context);
$PAGE->requires->js_call_amd('local_qualiscope/forms', 'init');

$output = $PAGE->get_renderer('local_qualiscope');

$campaigns = $DB->get_records('local_qualiscope_campaigns', [], 'timecreated DESC');
$referentials = $DB->get_records('local_qualiscope_referentials', ['active' => 1]);
$categories = $DB->get_records('course_categories', [], 'name ASC');
$courses = $DB->get_records('course', ['visible' => 1], 'fullname ASC');

$scopelabels = [
    'all' => get_string('campaign_scope_all', 'local_qualiscope'),
    'category' => get_string('campaign_scope_category', 'local_qualiscope'),
    'selected' => get_string('campaign_scope_selected', 'local_qualiscope'),
];

$campaignsdata = [];
foreach ($campaigns as $c) {
    $ref = $DB->get_record('local_qualiscope_referentials', ['id' => $c->referential_id]);

    // Calculate average coverage percentage across all audited courses in this campaign.
    $results = $DB->get_records('local_qualiscope_results', ['campaign_id' => $c->id]);
    $coursecount = 0;
    $avgcoverage = null;
    $coverageclass = 'bg-secondary';

    if (!empty($results)) {
        $coursesresults = [];
        foreach ($results as $r) {
            $coursesresults[$r->courseid][] = $r;
        }
        $coursecount = count($coursesresults);

        $coursepercentages = [];
        foreach ($coursesresults as $cid => $cresults) {
            $applicable = 0;
            $weighted = 0.0;
            foreach ($cresults as $r) {
                if ($r->status !== 'na') {
                    $applicable++;
                    $weighted += (float) $r->ratio > 0
                        ? (float) $r->ratio
                        : ($r->status === 'detected' ? 1.0 : 0.0);
                }
            }
            if ($applicable > 0) {
                $coursepercentages[] = ($weighted * 100) / $applicable;
            }
        }

        if (!empty($coursepercentages)) {
            $avgcoverage = (int) round(array_sum($coursepercentages) / count($coursepercentages));
            $coverageclass = $avgcoverage >= 75 ? 'bg-success' : ($avgcoverage >= 50 ? 'bg-warning' : 'bg-danger');
        }
    }

    $campaignsdata[] = [
        'id' => $c->id,
        'name' => $c->name,
        'referential' => $ref ? \local_qualiscope\helper::localized($ref, 'name') . ' ' . $ref->version : '—',
        'scopelabel' => $scopelabels[$c->scope] ?? $c->scope,
        'dateformatted' => userdate($c->timecreated),
        'timecompleted' => (int) $c->timecompleted,
        'course_count' => $coursecount,
        'has_coverage' => $avgcoverage !== null,
        'avg_coverage' => $avgcoverage !== null ? $avgcoverage . '%' : '—',
        'coverage_class' => $coverageclass,
        'viewurl' => new moodle_url('/local/qualiscope/view_campaign.php', ['id' => $c->id]),
        'runurl' => new moodle_url('/local/qualiscope/run.php', ['campaignid' => $c->id, 'sesskey' => sesskey()]),
        'rerunurl' => new moodle_url('/local/qualiscope/run.php', ['campaignid' => $c->id, 'rerun' => 1, 'sesskey' => sesskey()]),
    ];
}

echo $output->header();
echo \local_qualiscope\quota::banner($output);
echo $output->render_campaign_list([
    'campaigns' => $campaignsdata,
    'referentials' => array_map(function ($ref) {
        return \local_qualiscope\helper::localize_record($ref);
    }, array_values($referentials)),
    'categories' => array_values($categories),
    'courses' => array_values($courses),
    'sesskey' => sesskey(),
    'savecampaignurl' => new moodle_url('/local/qualiscope/save_campaign.php'),
    'compareurl' => new moodle_url('/local/qualiscope/compare_campaigns.php'),
]);
echo $output->footer();
