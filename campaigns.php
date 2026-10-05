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

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/qualiscope/campaigns.php'));

require_login();
require_capability('local/qualiscope:managecampaigns', $context);

$PAGE->set_title(get_string('campaign_title', 'local_qualiscope'));
$PAGE->set_heading(get_string('campaign_title', 'local_qualiscope'));
$PAGE->requires->js_call_amd('local_qualiscope/forms', 'init');

$output = $PAGE->get_renderer('local_qualiscope');

$campaigns = $DB->get_records('local_qualiscope_campaigns', [], 'timecreated DESC');
$referentials = $DB->get_records('local_qualiscope_referentials', ['active' => 1], 'name ASC');

// The category picker needs an id => label map, not the records themselves.
$categoryoptions = [];
foreach ($DB->get_records('course_categories', [], 'name ASC') as $category) {
    $categoryoptions[$category->id] = $category->name;
}

// Average coverage per campaign, computed by the database: a campaign stores one
// row per course and check, so reading its results to group them here would cost
// campaigns * courses * checks rows on every visit.
$coverages = \local_qualiscope\campaign_coverage::for_all_campaigns();

$scopelabels = [
    'all' => get_string('campaign_scope_all', 'local_qualiscope'),
    'category' => get_string('campaign_scope_category', 'local_qualiscope'),
    'selected' => get_string('campaign_scope_selected', 'local_qualiscope'),
];

$campaignsdata = [];
foreach ($campaigns as $c) {
    $ref = $DB->get_record('local_qualiscope_referentials', ['id' => $c->referential_id]);
    $coverage = $coverages[(int) $c->id] ?? null;
    $avgcoverage = $coverage ? $coverage['avgcoverage'] : null;
    $coverageclass = 'bg-secondary';

    if ($avgcoverage !== null) {
        $coverageclass = $avgcoverage >= 75 ? 'bg-success' : ($avgcoverage >= 50 ? 'bg-warning' : 'bg-danger');
    }

    $campaignsdata[] = [
        'id' => $c->id,
        'name' => \local_qualiscope\helper::plain($c->name, $context),
        'referential' => $ref ? \local_qualiscope\helper::localized($ref, 'name') . ' ' . $ref->version : '—',
        'scopelabel' => $scopelabels[$c->scope] ?? $c->scope,
        'dateformatted' => userdate($c->timecreated),
        'timecompleted' => (int) $c->timecompleted,
        'course_count' => $coverage ? $coverage['courses'] : 0,
        'has_coverage' => $avgcoverage !== null,
        'avg_coverage' => $avgcoverage !== null ? $avgcoverage . '%' : '—',
        'coverage_class' => $coverageclass,
        'viewurl' => new moodle_url('/local/qualiscope/view_campaign.php', ['id' => $c->id]),
        'runurl' => new moodle_url('/local/qualiscope/run.php', ['campaignid' => $c->id, 'sesskey' => sesskey()]),
        'rerunurl' => new moodle_url('/local/qualiscope/run.php', ['campaignid' => $c->id, 'rerun' => 1, 'sesskey' => sesskey()]),
    ];
}

$form = new \local_qualiscope\form\campaign_create($PAGE->url, [
    'referentials' => array_map(
        function ($ref) {
            return \local_qualiscope\helper::localize_record($ref);
        },
        array_values($referentials)
    ),
    'categories' => $categoryoptions,
]);

if ($data = $form->get_data()) {
    $scopeids = ($data->scope === 'category')
        ? array_values(array_map('intval', (array) $data->categoryids))
        : \local_qualiscope\form\campaign_create::selected_course_ids($data);

    $campaign = new stdClass();
    $campaign->name = $data->name;
    $campaign->referential_id = $data->referential_id;
    $campaign->scope = $data->scope;
    $campaign->scopeids = json_encode($scopeids);
    $campaign->userid = $USER->id;
    $campaign->timecreated = time();
    $campaign->timemodified = time();
    $campaign->timecompleted = 0;

    $DB->insert_record('local_qualiscope_campaigns', $campaign);

    redirect($PAGE->url);
}

echo $output->header();
echo \local_qualiscope\quota::banner($output);
echo $output->render_campaign_list([
    'campaigns' => $campaignsdata,
    'campaignform' => $form->render(),
    'compareurl' => new moodle_url('/local/qualiscope/compare_campaigns.php'),
]);
echo $OUTPUT->footer();
