<?php

require_once('../../config.php');
require_once($CFG->dirroot . '/local/qualiscope/lib.php');

$campaignid = required_param('id', PARAM_INT);

$campaign = $DB->get_record('local_qualiopi_campaigns', ['id' => $campaignid], '*', MUST_EXIST);

require_login();
$context = context_system::instance();
require_capability('local/qualiscope:managecampaigns', $context);

$PAGE->set_url(new moodle_url('/local/qualiscope/view_campaign.php', ['id' => $campaignid]));
$PAGE->set_title($campaign->name);
$PAGE->set_heading($campaign->name);
$PAGE->set_context($context);

$output = $PAGE->get_renderer('local_qualiscope');

$referential = $DB->get_record('local_qualiopi_referentials', ['id' => $campaign->referential_id]);

$scopelabels = [
    'all' => get_string('campaign_scope_all', 'local_qualiscope'),
    'category' => get_string('campaign_scope_category', 'local_qualiscope'),
    'selected' => get_string('campaign_scope_selected', 'local_qualiscope'),
];

$results = $DB->get_records('local_qualiopi_results', ['campaign_id' => $campaignid], 'courseid ASC');

$bycourse = [];
foreach ($results as $r) {
    if (!isset($bycourse[$r->courseid])) {
        $c = $DB->get_record('course', ['id' => $r->courseid], 'id,fullname');
        if (!$c) {
            continue;
        }
        $bycourse[$r->courseid] = [
            'course' => $c,
            'total' => 0,
            'detected' => 0,
            'verify' => 0,
            'missing' => 0,
            'na' => 0,
            'weighted' => 0.0,
        ];
    }
    $bycourse[$r->courseid]['total']++;
    switch ($r->status) {
        case 'detected': $bycourse[$r->courseid]['detected']++; break;
        case 'verify':   $bycourse[$r->courseid]['verify']++; break;
        case 'missing':  $bycourse[$r->courseid]['missing']++; break;
        case 'na':       $bycourse[$r->courseid]['na']++; break;
    }
    if ($r->status !== 'na') {
        $bycourse[$r->courseid]['weighted'] += (float) $r->ratio > 0
            ? (float) $r->ratio
            : ($r->status === 'detected' ? 1.0 : 0.0);
    }
}

$totals = ['total' => 0, 'detected' => 0, 'verify' => 0, 'missing' => 0, 'na' => 0, 'weighted' => 0.0];
$coursesdata = [];
foreach ($bycourse as &$entry) {
    $applicable = $entry['total'] - $entry['na'];
    $entry['percentage'] = $applicable > 0 ? (int) round(($entry['weighted'] * 100) / $applicable) : 0;

    $class = $entry['percentage'] >= 75 ? 'bg-success' : ($entry['percentage'] >= 50 ? 'bg-warning' : 'bg-danger');
    if ($entry['total'] === 0) {
        $class = 'bg-secondary';
    }

    $totals['total'] += $entry['total'];
    $totals['detected'] += $entry['detected'];
    $totals['verify'] += $entry['verify'];
    $totals['missing'] += $entry['missing'];
    $totals['na'] += $entry['na'];
    $totals['weighted'] += $entry['weighted'];

    $coursesdata[] = [
        'coursename' => $entry['course']->fullname,
        'percentage' => $entry['percentage'],
        'percentageclass' => $class,
        'detected' => $entry['detected'],
        'verify' => $entry['verify'],
        'missing' => $entry['missing'],
        'na' => $entry['na'],
        'viewurl' => new moodle_url('/local/qualiscope/dashboard.php', [
            'courseid' => $entry['course']->id,
            'referentialid' => $campaign->referential_id,
        ]),
    ];
}

$applicable = $totals['total'] - $totals['na'];
$globalpercentage = $applicable > 0 ? (int) round(($totals['weighted'] * 100) / $applicable) : 0;

$scopecourses = count(\local_qualiscope\analyser\course_analyser::get_campaign_course_ids($campaign));
$coursesanalysed = count($coursesdata);

$summaryitems = [
    [
        'key' => 'courses',
        'label' => get_string('campaign_courses_analysed', 'local_qualiscope'),
        'value' => $coursesanalysed . ($scopecourses ? ' / ' . $scopecourses : ''),
        'bgclass' => 'bg-secondary-subtle',
    ],
    [
        'key' => 'score',
        'label' => get_string('dashboard_score', 'local_qualiscope'),
        'value' => $globalpercentage . '%',
        'bgclass' => 'bg-primary-subtle',
    ],
    [
        'key' => 'detected',
        'label' => get_string('dashboard_detected', 'local_qualiscope'),
        'value' => $totals['detected'],
        'bgclass' => 'bg-success-subtle',
    ],
    [
        'key' => 'verify',
        'label' => get_string('dashboard_verify', 'local_qualiscope'),
        'value' => $totals['verify'],
        'bgclass' => 'bg-warning-subtle',
    ],
    [
        'key' => 'missing',
        'label' => get_string('dashboard_missing', 'local_qualiscope'),
        'value' => $totals['missing'],
        'bgclass' => 'bg-danger-subtle',
    ],
    [
        'key' => 'na',
        'label' => get_string('dashboard_na', 'local_qualiscope'),
        'value' => $totals['na'],
        'bgclass' => 'bg-secondary-subtle',
    ],
];

echo $output->header();
echo $output->render_campaign_dashboard([
    'campaignname' => $campaign->name,
    'completed' => (bool) $campaign->timecompleted,
    'referential' => $referential ? $referential->name . ' ' . $referential->version : '—',
    'scopelabel' => $scopelabels[$campaign->scope] ?? $campaign->scope,
    'dateformatted' => userdate($campaign->timecreated),
    'runurl' => new moodle_url('/local/qualiscope/run.php', ['campaignid' => $campaignid, 'sesskey' => sesskey()]),
    'rerunurl' => new moodle_url('/local/qualiscope/run.php', ['campaignid' => $campaignid, 'rerun' => 1, 'sesskey' => sesskey()]),
    'backurl' => new moodle_url('/local/qualiscope/campaigns.php'),
    'summaryitems' => $summaryitems,
    'hasresults' => !empty($coursesdata),
    'courses' => $coursesdata,
]);
echo $output->footer();