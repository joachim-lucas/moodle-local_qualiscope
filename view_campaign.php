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
$PAGE->requires->js_call_amd('local_qualiscope/forms', 'init');

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

// Macro analysis by criteria & indicators.
$criteria_records = $DB->get_records('local_qualiopi_criteria', ['referential_id' => $campaign->referential_id], 'number ASC');
$indicators_records = $DB->get_records_sql(
    "SELECT i.* FROM {local_qualiopi_indicators} i
     JOIN {local_qualiopi_criteria} c ON c.id = i.criterion_id
     WHERE c.referential_id = :refid
     ORDER BY c.number ASC, i.number ASC",
    ['refid' => $campaign->referential_id]
);

$results_by_indicator = [];
foreach ($results as $r) {
    $results_by_indicator[$r->indicator_id][] = $r;
}

$criteriadata = [];
$weakpoints = [];

foreach ($criteria_records as $crit) {
    $criteriadata[$crit->id] = [
        'id' => $crit->id,
        'number' => $crit->number,
        'title' => $crit->title,
        'shorttitle' => core_text::strlen($crit->title) > 60 ? core_text::substr($crit->title, 0, 60) . '…' : $crit->title,
        'indicators' => [],
        'total' => 0,
        'detected' => 0,
        'verify' => 0,
        'missing' => 0,
        'na' => 0,
        'weighted' => 0.0,
        'percentage' => null,
        'percentageclass' => 'bg-secondary',
        'manualonly' => true,
    ];
}

foreach ($indicators_records as $ind) {
    $ind_results = $results_by_indicator[$ind->id] ?? [];
    $total = count($ind_results);
    $detected = 0;
    $verify = 0;
    $missing = 0;
    $na = 0;
    $weighted = 0.0;

    foreach ($ind_results as $r) {
        switch ($r->status) {
            case 'detected': $detected++; break;
            case 'verify':   $verify++; break;
            case 'missing':  $missing++; break;
            case 'na':       $na++; break;
        }
        if ($r->status !== 'na') {
            $weighted += (float) $r->ratio > 0 ? (float) $r->ratio : ($r->status === 'detected' ? 1.0 : 0.0);
        }
    }

    $app = $total - $na;
    $percentage = $app > 0 ? (int) round(($weighted * 100) / $app) : null;
    $non_compliance = $percentage !== null ? (100 - $percentage) : 0;
    $failing_courses = $missing + $verify;

    $class = 'bg-secondary';
    if ($percentage !== null) {
        $class = $percentage >= 75 ? 'bg-success' : ($percentage >= 50 ? 'bg-warning' : 'bg-danger');
    }

    $ind_item = [
        'id' => $ind->id,
        'number' => $ind->number,
        'title' => $ind->title,
        'total' => $total,
        'detected' => $detected,
        'verify' => $verify,
        'missing' => $missing,
        'na' => $na,
        'applicable' => $app,
        'percentage' => $percentage !== null ? $percentage : 0,
        'haspercentage' => $percentage !== null,
        'percentageclass' => $class,
        'noncompliance' => $non_compliance,
        'failingcourses' => $failing_courses,
        'failingmessage' => get_string('campaign_courses_failing', 'local_qualiscope', $failing_courses),
        'criterion_number' => $criteria_records[$ind->criterion_id]->number ?? '',
        'criterion_title' => $criteria_records[$ind->criterion_id]->title ?? '',
    ];

    if (isset($criteriadata[$ind->criterion_id])) {
        $criteriadata[$ind->criterion_id]['indicators'][] = $ind_item;
        if ($total > 0) {
            $criteriadata[$ind->criterion_id]['manualonly'] = false;
        }
        $criteriadata[$ind->criterion_id]['total'] += $total;
        $criteriadata[$ind->criterion_id]['detected'] += $detected;
        $criteriadata[$ind->criterion_id]['verify'] += $verify;
        $criteriadata[$ind->criterion_id]['missing'] += $missing;
        $criteriadata[$ind->criterion_id]['na'] += $na;
        $criteriadata[$ind->criterion_id]['weighted'] += $weighted;
    }

    if ($app > 0) {
        $weakpoints[] = $ind_item;
    }
}

foreach ($criteriadata as &$cdata) {
    $capp = $cdata['total'] - $cdata['na'];
    if ($capp > 0) {
        $cdata['percentage'] = (int) round(($cdata['weighted'] * 100) / $capp);
        $cdata['percentageclass'] = $cdata['percentage'] >= 75 ? 'bg-success' : ($cdata['percentage'] >= 50 ? 'bg-warning' : 'bg-danger');
    }
}
unset($cdata);

usort($weakpoints, function($a, $b) {
    if ($a['percentage'] === $b['percentage']) {
        return $b['failingcourses'] <=> $a['failingcourses'];
    }
    return $a['percentage'] <=> $b['percentage'];
});

$weakpoints_filtered = array_values(array_filter($weakpoints, function($item) {
    return $item['percentage'] < 100;
}));

// Consolidated CAPA Actions for Campaign.
$actions = $DB->get_records_sql(
    "SELECT a.*, c.fullname AS coursename
     FROM {local_qualiopi_actions} a
     JOIN {course} c ON c.id = a.courseid
     WHERE a.campaign_id = :campaignid
     ORDER BY a.duedate ASC, a.id DESC",
    ['campaignid' => $campaignid]
);

$actionstodo = 0;
$actionsinprogress = 0;
$actionsclosed = 0;
$actionsdata = [];

$prioritylabels = [
    'high' => get_string('action_priority_high', 'local_qualiscope'),
    'medium' => get_string('action_priority_medium', 'local_qualiscope'),
    'low' => get_string('action_priority_low', 'local_qualiscope'),
];

$statuslabels = [
    'todo' => get_string('action_status_todo', 'local_qualiscope'),
    'inprogress' => get_string('action_status_inprogress', 'local_qualiscope'),
    'proved' => get_string('action_status_proved', 'local_qualiscope'),
    'verified' => get_string('action_status_verified', 'local_qualiscope'),
    'closed' => get_string('action_status_closed', 'local_qualiscope'),
];

foreach ($actions as $action) {
    if ($action->status === 'todo') {
        $actionstodo++;
    } else if ($action->status === 'inprogress') {
        $actionsinprogress++;
    } else {
        $actionsclosed++;
    }

    $actionsdata[] = [
        'id' => $action->id,
        'title' => $action->title,
        'coursename' => $action->coursename,
        'responsible' => $action->responsible ?: '—',
        'duedateformatted' => $action->duedate ? userdate($action->duedate, get_string('strftimedateshort', 'langconfig')) : '—',
        'priority' => $action->priority,
        'prioritylabel' => $prioritylabels[$action->priority] ?? $action->priority,
        'priorityhigh' => $action->priority === 'high',
        'prioritymedium' => $action->priority === 'medium',
        'prioritylow' => $action->priority === 'low',
        'status' => $action->status,
        'statuslabel' => $statuslabels[$action->status] ?? $action->status,
        'statustodo' => $action->status === 'todo',
        'statusinprogress' => $action->status === 'inprogress',
        'statusclosed' => in_array($action->status, ['proved', 'verified', 'closed']),
        'editurl' => new moodle_url('/local/qualiscope/edit_action.php', ['id' => $action->id, 'return' => 'campaign']),
    ];
}

$indicators_dropdown = [];
foreach ($criteriadata as $crit) {
    foreach ($crit['indicators'] as $ind) {
        $indicators_dropdown[] = [
            'id' => $ind['id'],
            'label' => 'C' . $crit['number'] . ' - I' . $ind['number'] . ' : ' . core_text::substr($ind['title'], 0, 60),
        ];
    }
}

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
    'exportcsvurl' => new moodle_url('/local/qualiscope/export.php', ['campaignid' => $campaignid, 'format' => 'csv', 'sesskey' => sesskey()]),
    'exportxlsxurl' => new moodle_url('/local/qualiscope/export.php', ['campaignid' => $campaignid, 'format' => 'xlsx', 'sesskey' => sesskey()]),
    'exportpdfurl' => new moodle_url('/local/qualiscope/export.php', ['campaignid' => $campaignid, 'format' => 'pdf', 'sesskey' => sesskey()]),
    'printreporturl' => new moodle_url('/local/qualiscope/print_campaign.php', ['id' => $campaignid]),
    'compareurl' => new moodle_url('/local/qualiscope/compare_campaigns.php', ['id_a' => $campaignid]),
    'backurl' => new moodle_url('/local/qualiscope/campaigns.php'),
    'summaryitems' => $summaryitems,
    'hasresults' => !empty($coursesdata),
    'courses' => $coursesdata,
    'criteria' => array_values($criteriadata),
    'weakpoints' => $weakpoints_filtered,
    'hasweakpoints' => !empty($weakpoints_filtered),
    'actions' => $actionsdata,
    'hasactions' => !empty($actionsdata),
    'actionstotal' => count($actionsdata),
    'actionstodo' => $actionstodo,
    'actionsinprogress' => $actionsinprogress,
    'actionsclosed' => $actionsclosed,
    'indicatorslist' => $indicators_dropdown,
    'bulkactionurl' => new moodle_url('/local/qualiscope/bulk_create_action.php'),
    'sesskey' => sesskey(),
    'campaignid' => $campaignid,
]);
echo $output->footer();