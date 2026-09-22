<?php

require_once('../../config.php');
require_once($CFG->dirroot . '/local/qualiscope/lib.php');

$campaignid = required_param('id', PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/qualiscope:managecampaigns', $context);

$campaign = $DB->get_record('local_qualiopi_campaigns', ['id' => $campaignid], '*', MUST_EXIST);
$referential = $DB->get_record('local_qualiopi_referentials', ['id' => $campaign->referential_id], '*', MUST_EXIST);

$scopelabels = [
    'all' => get_string('campaign_scope_all', 'local_qualiscope'),
    'category' => get_string('campaign_scope_category', 'local_qualiscope'),
    'selected' => get_string('campaign_scope_selected', 'local_qualiscope'),
];

$results = $DB->get_records('local_qualiopi_results', ['campaign_id' => $campaignid], 'courseid ASC');

$bycourse = [];
foreach ($results as $r) {
    if (!isset($bycourse[$r->courseid])) {
        $c = $DB->get_record('course', ['id' => $r->courseid], 'id,fullname,shortname');
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

    $totals['total'] += $entry['total'];
    $totals['detected'] += $entry['detected'];
    $totals['verify'] += $entry['verify'];
    $totals['missing'] += $entry['missing'];
    $totals['na'] += $entry['na'];
    $totals['weighted'] += $entry['weighted'];

    $coursesdata[] = [
        'coursename' => $entry['course']->fullname,
        'percentage' => $entry['percentage'],
        'detected' => $entry['detected'],
        'verify' => $entry['verify'],
        'missing' => $entry['missing'],
        'na' => $entry['na'],
    ];
}
unset($entry);

$applicable = $totals['total'] - $totals['na'];
$globalpercentage = $applicable > 0 ? (int) round(($totals['weighted'] * 100) / $applicable) : 0;

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
        'indicators' => [],
        'total' => 0,
        'detected' => 0,
        'verify' => 0,
        'missing' => 0,
        'na' => 0,
        'weighted' => 0.0,
        'percentage' => null,
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
    $failing_courses = $missing + $verify;

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
        'failingcourses' => $failing_courses,
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

?><!DOCTYPE html>
<html lang="<?php echo current_language(); ?>">
<head>
    <meta charset="utf-8">
    <title><?php echo s($campaign->name); ?> — <?php echo s(get_string('campaign_report_subtitle', 'local_qualiscope')); ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; color: #1e293b; background: #fff; margin: 20px 40px; font-size: 14px; line-height: 1.5; }
        .no-print { margin-bottom: 20px; }
        .btn { display: inline-block; padding: 8px 16px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 4px; font-weight: bold; border: none; cursor: pointer; }
        .btn-outline { background: #fff; color: #475569; border: 1px solid #cbd5e1; }
        .header { border-bottom: 3px solid #1e3a8a; padding-bottom: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; }
        .header h1 { margin: 0 0 4px; color: #1e3a8a; font-size: 24px; }
        .header .subtitle { color: #64748b; font-size: 14px; }
        .meta-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 25px; }
        .score-box { font-size: 28px; font-weight: bold; color: #1e3a8a; }
        h2 { color: #2563eb; font-size: 18px; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin-top: 30px; margin-bottom: 12px; }
        h3 { color: #334155; font-size: 15px; margin-top: 15px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #f1f5f9; color: #334155; text-align: left; padding: 8px; border: 1px solid #e2e8f0; font-size: 13px; }
        td { padding: 6px 8px; border: 1px solid #e2e8f0; font-size: 13px; vertical-align: top; }
        .text-center { text-align: center; }
        .text-success { color: #15803d; font-weight: bold; }
        .text-warning { color: #b45309; font-weight: bold; }
        .text-danger { color: #b91c1c; font-weight: bold; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; color: #fff; }
        .bg-success { background-color: #16a34a; }
        .bg-warning { background-color: #f59e0b; color: #000; }
        .bg-danger { background-color: #dc2626; }
        .bg-secondary { background-color: #64748b; }
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; font-size: 12px; }
            h2 { page-break-before: auto; }
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn" onclick="window.print()">🖨 <?php echo s(get_string('campaign_print_btn', 'local_qualiscope')); ?></button>
        <a class="btn btn-outline" href="view_campaign.php?id=<?php echo (int) $campaignid; ?>">← <?php echo s(get_string('back_to_dashboard', 'local_qualiscope')); ?></a>
    </div>

    <div class="header">
        <div>
            <h1>QualiScope</h1>
            <div class="subtitle"><?php echo s(get_string('campaign_report_subtitle', 'local_qualiscope')); ?></div>
        </div>
        <div style="text-align: right; color: #64748b; font-size: 13px;">
            <div><?php echo s(get_string('export_generated', 'local_qualiscope')); ?></div>
            <strong><?php echo userdate(time(), get_string('strftimedateshort', 'langconfig')); ?></strong>
        </div>
    </div>

    <div class="meta-grid">
        <div>
            <div><strong><?php echo s(get_string('campaign_name', 'local_qualiscope')); ?> :</strong> <?php echo s($campaign->name); ?></div>
            <div><strong><?php echo s(get_string('campaign_referential', 'local_qualiscope')); ?> :</strong> <?php echo s($referential->name . ' ' . $referential->version); ?></div>
            <div><strong><?php echo s(get_string('campaign_scope', 'local_qualiscope')); ?> :</strong> <?php echo s($scopelabels[$campaign->scope] ?? $campaign->scope); ?></div>
            <div style="margin-top: 8px;">
                <strong><?php echo s(get_string('export_global_rate', 'local_qualiscope')); ?></strong>
                <span class="score-box"><?php echo (int) $globalpercentage; ?> %</span>
            </div>
        </div>
        <div style="border-left: 1px solid #cbd5e1; padding-left: 15px;">
            <div><strong><?php echo s(get_string('campaign_courses_analysed', 'local_qualiscope')); ?> :</strong> <?php echo count($coursesdata); ?></div>
            <div class="text-success">● <?php echo s(get_string('dashboard_detected', 'local_qualiscope')); ?> : <?php echo (int) $totals['detected']; ?></div>
            <div class="text-warning">● <?php echo s(get_string('dashboard_verify', 'local_qualiscope')); ?> : <?php echo (int) $totals['verify']; ?></div>
            <div class="text-danger">● <?php echo s(get_string('dashboard_missing', 'local_qualiscope')); ?> : <?php echo (int) $totals['missing']; ?></div>
            <div style="color: #64748b;">● <?php echo s(get_string('dashboard_na', 'local_qualiscope')); ?> : <?php echo (int) $totals['na']; ?></div>
        </div>
    </div>

    <h2><?php echo s(get_string('campaign_tab_courses', 'local_qualiscope')); ?></h2>
    <table>
        <thead>
            <tr>
                <th style="width: 50%;"><?php echo s(get_string('campaign_course', 'local_qualiscope')); ?></th>
                <th class="text-center" style="width: 15%;"><?php echo s(get_string('dashboard_score', 'local_qualiscope')); ?></th>
                <th class="text-center" style="width: 8%;">✓</th>
                <th class="text-center" style="width: 8%;">⚠</th>
                <th class="text-center" style="width: 8%;">✗</th>
                <th class="text-center" style="width: 8%;">—</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($coursesdata as $course): ?>
            <tr>
                <td><strong><?php echo s($course['coursename']); ?></strong></td>
                <td class="text-center"><strong><?php echo (int) $course['percentage']; ?> %</strong></td>
                <td class="text-center text-success"><?php echo (int) $course['detected']; ?></td>
                <td class="text-center text-warning"><?php echo (int) $course['verify']; ?></td>
                <td class="text-center text-danger"><?php echo (int) $course['missing']; ?></td>
                <td class="text-center" style="color: #64748b;"><?php echo (int) $course['na']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2><?php echo s(get_string('campaign_tab_macro', 'local_qualiscope')); ?></h2>
    <?php foreach ($criteriadata as $crit): ?>
    <h3>C<?php echo (int) $crit['number']; ?> — <?php echo s($crit['title']); ?> (<?php echo $crit['percentage'] !== null ? (int) $crit['percentage'] . ' %' : s(get_string('dashboard_manual_only', 'local_qualiscope')); ?>)</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 10%;"><?php echo s(get_string('campaign_indicator', 'local_qualiscope')); ?></th>
                <th style="width: 45%;"><?php echo s(get_string('description', 'core')); ?></th>
                <th class="text-center" style="width: 15%;"><?php echo s(get_string('campaign_compliance_rate', 'local_qualiscope')); ?></th>
                <th class="text-center" style="width: 7%;">✓</th>
                <th class="text-center" style="width: 7%;">⚠</th>
                <th class="text-center" style="width: 8%;">✗</th>
                <th class="text-center" style="width: 8%;">—</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($crit['indicators'] as $ind): ?>
            <tr>
                <td class="text-center"><strong>I<?php echo (int) $ind['number']; ?></strong></td>
                <td><?php echo s($ind['title']); ?></td>
                <td class="text-center"><strong><?php echo $ind['haspercentage'] ? (int) $ind['percentage'] . ' %' : '—'; ?></strong></td>
                <td class="text-center text-success"><?php echo (int) $ind['detected']; ?></td>
                <td class="text-center text-warning"><?php echo (int) $ind['verify']; ?></td>
                <td class="text-center text-danger"><?php echo (int) $ind['missing']; ?></td>
                <td class="text-center" style="color: #64748b;"><?php echo (int) $ind['na']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endforeach; ?>

    <?php if (!empty($weakpoints_filtered)): ?>
    <h2><?php echo s(get_string('campaign_tab_weakpoints', 'local_qualiscope')); ?></h2>
    <table>
        <thead>
            <tr>
                <th style="width: 12%;"><?php echo s(get_string('campaign_indicator', 'local_qualiscope')); ?></th>
                <th style="width: 48%;"><?php echo s(get_string('description', 'core')); ?></th>
                <th class="text-center" style="width: 18%;"><?php echo s(get_string('campaign_compliance_rate', 'local_qualiscope')); ?></th>
                <th class="text-center" style="width: 22%;"><?php echo s(get_string('campaign_non_compliant_count', 'local_qualiscope')); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($weakpoints_filtered as $wp): ?>
            <tr>
                <td class="text-center"><strong>I<?php echo (int) $wp['number']; ?> (C<?php echo (int) $wp['criterion_number']; ?>)</strong></td>
                <td><?php echo s($wp['title']); ?></td>
                <td class="text-center text-danger"><strong><?php echo (int) $wp['percentage']; ?> %</strong></td>
                <td class="text-center text-danger"><?php echo (int) $wp['failingcourses']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</body>
</html>
