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
 * QualiScope Print Campaign page.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/local/qualiscope/lib.php');

$campaignid = required_param('id', PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/qualiscope:managecampaigns', $context);

$campaign = $DB->get_record('local_qualiscope_campaigns', ['id' => $campaignid], '*', MUST_EXIST);
$referential = $DB->get_record('local_qualiscope_referentials', ['id' => $campaign->referential_id], '*', MUST_EXIST);

$scopelabels = [
    'all' => get_string('campaign_scope_all', 'local_qualiscope'),
    'category' => get_string('campaign_scope_category', 'local_qualiscope'),
    'selected' => get_string('campaign_scope_selected', 'local_qualiscope'),
];

$results = $DB->get_records('local_qualiscope_results', ['campaign_id' => $campaignid], 'courseid ASC');

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
        case 'detected':
            $bycourse[$r->courseid]['detected']++;
            break;
        case 'verify':
            $bycourse[$r->courseid]['verify']++;
            break;
        case 'missing':
            $bycourse[$r->courseid]['missing']++;
            break;
        case 'na':
            $bycourse[$r->courseid]['na']++;
            break;
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

$criteriarecords = $DB->get_records('local_qualiscope_criteria', [
    'referential_id' => $campaign->referential_id,
], 'number ASC');
$indicatorsrecords = $DB->get_records_sql(
    "SELECT i.* FROM {local_qualiscope_indicators} i
     JOIN {local_qualiscope_criteria} c ON c.id = i.criterion_id
     WHERE c.referential_id = :refid
     ORDER BY c.number ASC, i.number ASC",
    ['refid' => $campaign->referential_id]
);

$resultsbyindicator = [];
foreach ($results as $r) {
    $resultsbyindicator[$r->indicator_id][] = $r;
}

$criteriadata = [];
$weakpoints = [];

foreach ($criteriarecords as $crit) {
    $criteriadata[$crit->id] = [
        'id' => $crit->id,
        'number' => $crit->number,
        'title' => \local_qualiscope\helper::localized($crit, 'title'),
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

foreach ($indicatorsrecords as $ind) {
    $indicatorresults = $resultsbyindicator[$ind->id] ?? [];
    $total = count($indicatorresults);
    $detected = 0;
    $verify = 0;
    $missing = 0;
    $na = 0;
    $weighted = 0.0;

    foreach ($indicatorresults as $r) {
        switch ($r->status) {
            case 'detected':
                $detected++;
                break;
            case 'verify':
                $verify++;
                break;
            case 'missing':
                $missing++;
                break;
            case 'na':
                $na++;
                break;
        }
        if ($r->status !== 'na') {
            $weighted += (float) $r->ratio > 0 ? (float) $r->ratio : ($r->status === 'detected' ? 1.0 : 0.0);
        }
    }

    $app = $total - $na;
    $percentage = $app > 0 ? (int) round(($weighted * 100) / $app) : null;
    $failingcourses = $missing + $verify;

    $indicatoritem = [
        'id' => $ind->id,
        'number' => $ind->number,
        'title' => \local_qualiscope\helper::localized($ind, 'title'),
        'total' => $total,
        'detected' => $detected,
        'verify' => $verify,
        'missing' => $missing,
        'na' => $na,
        'applicable' => $app,
        'percentage' => $percentage !== null ? $percentage : 0,
        'haspercentage' => $percentage !== null,
        'failingcourses' => $failingcourses,
        'criterion_number' => $criteriarecords[$ind->criterion_id]->number ?? '',
        'criterion_title' => isset($criteriarecords[$ind->criterion_id]) ?
            \local_qualiscope\helper::localized($criteriarecords[$ind->criterion_id], 'title') : '',
    ];

    if (isset($criteriadata[$ind->criterion_id])) {
        $criteriadata[$ind->criterion_id]['indicators'][] = $indicatoritem;
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
        $weakpoints[] = $indicatoritem;
    }
}

foreach ($criteriadata as &$cdata) {
    $capp = $cdata['total'] - $cdata['na'];
    if ($capp > 0) {
        $cdata['percentage'] = (int) round(($cdata['weighted'] * 100) / $capp);
    }
}
unset($cdata);

usort($weakpoints, function ($a, $b) {
    if ($a['percentage'] === $b['percentage']) {
        return $b['failingcourses'] <=> $a['failingcourses'];
    }
    return $a['percentage'] <=> $b['percentage'];
});

$weakpointsfiltered = array_values(array_filter($weakpoints, function ($item) {
    return $item['percentage'] < 100;
}));

$html = '<!DOCTYPE html>
<html lang="' . current_language() . '">
<head>
    <meta charset="utf-8">
    <title>' . s($campaign->name) . ' — ' . s(get_string('campaign_report_subtitle', 'local_qualiscope')) . '</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial,
            sans-serif; color: #1e293b; background: #fff; margin: 20px 40px; font-size: 14px; line-height: 1.5; }
        .no-print { margin-bottom: 20px; }
        .btn { display: inline-block; padding: 8px 16px; background: #2563eb; color: #fff; text-decoration: none;
            border-radius: 4px; font-weight: bold; border: none; cursor: pointer; }
        .btn-outline { background: #fff; color: #475569; border: 1px solid #cbd5e1; }
        .header { border-bottom: 3px solid #1e3a8a; padding-bottom: 12px; margin-bottom: 20px; display: flex;
            justify-content: space-between; align-items: flex-start; }
        .header h1 { margin: 0 0 4px; color: #1e3a8a; font-size: 24px; }
        .header .subtitle { color: #64748b; font-size: 14px; }
        .meta-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 15px; background: #f8fafc;
            border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 25px; }
        .score-box { font-size: 28px; font-weight: bold; color: #1e3a8a; }
        h2 { color: #2563eb; font-size: 18px; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px;
            margin-top: 30px; margin-bottom: 12px; }
        h3 { color: #334155; font-size: 15px; margin-top: 15px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #f1f5f9; color: #334155; text-align: left; padding: 8px; border: 1px solid #e2e8f0;
            font-size: 13px; }
        td { padding: 6px 8px; border: 1px solid #e2e8f0; font-size: 13px; vertical-align: top; }
        .text-center { text-align: center; }
        .text-success { color: #15803d; font-weight: bold; }
        .text-warning { color: #b45309; font-weight: bold; }
        .text-danger { color: #b91c1c; font-weight: bold; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;
            color: #fff; }
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
        <button class="btn" onclick="window.print()">🖨 ' . s(get_string('campaign_print_btn', 'local_qualiscope')) . '</button>
        <a class="btn btn-outline" href="view_campaign.php?id=' . (int) $campaignid . '">← ' .
            s(get_string('back_to_dashboard', 'local_qualiscope')) . '</a>
    </div>

    <div class="header">
        <div>
            <h1>QualiScope</h1>
            <div class="subtitle">' . s(get_string('campaign_report_subtitle', 'local_qualiscope')) . '</div>
        </div>
        <div style="text-align: right; color: #64748b; font-size: 13px;">
            <div>' . s(get_string('export_generated', 'local_qualiscope')) . '</div>
            <strong>' . userdate(time(), get_string('strftimedateshort', 'langconfig')) . '</strong>
        </div>
    </div>

    <div class="meta-grid">
        <div>
            <div><strong>' . s(get_string('campaign_name', 'local_qualiscope')) . ' :</strong> ' .
                s($campaign->name) . '</div>
            <div><strong>' . s(get_string('campaign_referential', 'local_qualiscope')) . ' :</strong> ' .
                s(\local_qualiscope\helper::localized($referential, 'name') . ' ' . $referential->version) . '</div>
            <div><strong>' . s(get_string('campaign_scope', 'local_qualiscope')) . ' :</strong> ' .
                s($scopelabels[$campaign->scope] ?? $campaign->scope) . '</div>
            <div style="margin-top: 8px;">
                <strong>' . s(get_string('export_global_rate', 'local_qualiscope')) . '</strong>
                <span class="score-box">' . (int) $globalpercentage . ' %</span>
            </div>
        </div>
        <div style="border-left: 1px solid #cbd5e1; padding-left: 15px;">
            <div><strong>' . s(get_string('campaign_courses_analysed', 'local_qualiscope')) . ' :</strong> ' .
                count($coursesdata) . '</div>
            <div class="text-success">● ' . s(get_string('dashboard_detected', 'local_qualiscope')) . ' : ' .
                (int) $totals['detected'] . '</div>
            <div class="text-warning">● ' . s(get_string('dashboard_verify', 'local_qualiscope')) . ' : ' .
                (int) $totals['verify'] . '</div>
            <div class="text-danger">● ' . s(get_string('dashboard_missing', 'local_qualiscope')) . ' : ' .
                (int) $totals['missing'] . '</div>
            <div style="color: #64748b;">● ' . s(get_string('dashboard_na', 'local_qualiscope')) . ' : ' .
                (int) $totals['na'] . '</div>
        </div>
    </div>
';

$html .= '<h2>' . s(get_string('campaign_tab_courses', 'local_qualiscope')) . '</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 50%;">' . s(get_string('campaign_course', 'local_qualiscope')) . '</th>
                <th class="text-center" style="width: 15%;">' .
                    s(get_string('dashboard_score', 'local_qualiscope')) . '</th>
                <th class="text-center" style="width: 8%;">✓</th>
                <th class="text-center" style="width: 8%;">⚠</th>
                <th class="text-center" style="width: 8%;">✗</th>
                <th class="text-center" style="width: 8%;">—</th>
            </tr>
        </thead>
        <tbody>';

foreach ($coursesdata as $course) {
    $html .= '<tr>
            <td><strong>' . s($course['coursename']) . '</strong></td>
            <td class="text-center"><strong>' . (int) $course['percentage'] . ' %</strong></td>
            <td class="text-center text-success">' . (int) $course['detected'] . '</td>
            <td class="text-center text-warning">' . (int) $course['verify'] . '</td>
            <td class="text-center text-danger">' . (int) $course['missing'] . '</td>
            <td class="text-center" style="color: #64748b;">' . (int) $course['na'] . '</td>
        </tr>';
}

$html .= '</tbody>
    </table>

    <h2>' . s(get_string('campaign_tab_macro', 'local_qualiscope')) . '</h2>';

foreach ($criteriadata as $crit) {
    $critpct = $crit['percentage'] !== null ? (int) $crit['percentage'] . ' %' :
        s(get_string('dashboard_manual_only', 'local_qualiscope'));
    $html .= '<h3>C' . (int) $crit['number'] . ' — ' . s($crit['title']) . ' (' . $critpct . ')</h3>
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">' . s(get_string('campaign_indicator', 'local_qualiscope')) . '</th>
                    <th style="width: 45%;">' . s(get_string('description', 'core')) . '</th>
                    <th class="text-center" style="width: 15%;">' .
                        s(get_string('campaign_compliance_rate', 'local_qualiscope')) . '</th>
                    <th class="text-center" style="width: 7%;">✓</th>
                    <th class="text-center" style="width: 7%;">⚠</th>
                    <th class="text-center" style="width: 8%;">✗</th>
                    <th class="text-center" style="width: 8%;">—</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($crit['indicators'] as $ind) {
        $indpct = $ind['haspercentage'] ? (int) $ind['percentage'] . ' %' : '—';
        $html .= '<tr>
                <td class="text-center"><strong>I' . (int) $ind['number'] . '</strong></td>
                <td>' . s($ind['title']) . '</td>
                <td class="text-center"><strong>' . $indpct . '</strong></td>
                <td class="text-center text-success">' . (int) $ind['detected'] . '</td>
                <td class="text-center text-warning">' . (int) $ind['verify'] . '</td>
                <td class="text-center text-danger">' . (int) $ind['missing'] . '</td>
                <td class="text-center" style="color: #64748b;">' . (int) $ind['na'] . '</td>
            </tr>';
    }

    $html .= '</tbody>
        </table>';
}

if (!empty($weakpointsfiltered)) {
    $html .= '<h2>' . s(get_string('campaign_tab_weakpoints', 'local_qualiscope')) . '</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 12%;">' . s(get_string('campaign_indicator', 'local_qualiscope')) . '</th>
                    <th style="width: 48%;">' . s(get_string('description', 'core')) . '</th>
                    <th class="text-center" style="width: 18%;">' .
                        s(get_string('campaign_compliance_rate', 'local_qualiscope')) . '</th>
                    <th class="text-center" style="width: 22%;">' .
                        s(get_string('campaign_non_compliant_count', 'local_qualiscope')) . '</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($weakpointsfiltered as $wp) {
        $html .= '<tr>
                <td class="text-center"><strong>I' . (int) $wp['number'] . ' (C' .
                    (int) $wp['criterion_number'] . ')</strong></td>
                <td>' . s($wp['title']) . '</td>
                <td class="text-center text-danger"><strong>' .
                    (int) $wp['percentage'] . ' %</strong></td>
                <td class="text-center text-danger">' . (int) $wp['failingcourses'] . '</td>
            </tr>';
    }

    $html .= '</tbody>
        </table>';
}

$html .= '</body>
</html>';

echo $html;
