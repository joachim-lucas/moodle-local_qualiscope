<?php

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

$campaigns = $DB->get_records('local_qualiopi_campaigns', [], 'timecreated DESC');
$referentials = $DB->get_records('local_qualiopi_referentials', ['active' => 1]);
$categories = $DB->get_records('course_categories', [], 'name ASC');
$courses = $DB->get_records('course', ['visible' => 1], 'fullname ASC');

$scopelabels = [
    'all' => get_string('campaign_scope_all', 'local_qualiscope'),
    'category' => get_string('campaign_scope_category', 'local_qualiscope'),
    'selected' => get_string('campaign_scope_selected', 'local_qualiscope'),
];

$campaignsdata = [];
foreach ($campaigns as $c) {
    $ref = $DB->get_record('local_qualiopi_referentials', ['id' => $c->referential_id]);
    
    // Calculate average coverage percentage across all audited courses in this campaign.
    $results = $DB->get_records('local_qualiopi_results', ['campaign_id' => $c->id]);
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
        'referential' => $ref ? $ref->name . ' ' . $ref->version : '—',
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
echo $output->render_campaign_list([
    'campaigns' => $campaignsdata,
    'referentials' => array_values($referentials),
    'categories' => array_values($categories),
    'courses' => array_values($courses),
    'sesskey' => sesskey(),
    'savecampaignurl' => new moodle_url('/local/qualiscope/save_campaign.php'),
    'compareurl' => new moodle_url('/local/qualiscope/compare_campaigns.php'),
]);
echo $output->footer();