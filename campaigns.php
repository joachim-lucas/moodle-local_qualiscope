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
    $campaignsdata[] = [
        'id' => $c->id,
        'name' => $c->name,
        'referential' => $ref ? $ref->name . ' ' . $ref->version : '—',
        'scopelabel' => $scopelabels[$c->scope] ?? $c->scope,
        'dateformatted' => userdate($c->timecreated),
        'timecompleted' => (int) $c->timecompleted,
        'course_count' => (int) $DB->count_records_sql(
            'SELECT COUNT(DISTINCT courseid) FROM {local_qualiopi_results} WHERE campaign_id = :cid',
            ['cid' => $c->id]
        ),
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
]);
echo $output->footer();