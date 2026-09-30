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

$PAGE->set_url(new moodle_url('/local/qualiscope/print_campaign.php', ['id' => $campaignid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('print');
$PAGE->set_title($campaign->name);
$PAGE->set_heading($campaign->name);

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

$coursesrows = [];foreach ($coursesdata as $course) {
    $coursesrows[] = [
        'coursename' => $course['coursename'],
        'percentage' => (int) $course['percentage'],
        'detected' => (int) $course['detected'],
        'verify' => (int) $course['verify'],
        'missing' => (int) $course['missing'],
        'na' => (int) $course['na'],
    ];
}

$criteriatables = [];
foreach ($criteriadata as $crit) {
    $rows = [];
    foreach ($crit['indicators'] as $ind) {
        $rows[] = [
            'number' => (int) $ind['number'],
            'title' => $ind['title'],
            'percentagelabel' => $ind['haspercentage'] ? (int) $ind['percentage'] . ' %' : '—',
            'detected' => (int) $ind['detected'],
            'verify' => (int) $ind['verify'],
            'missing' => (int) $ind['missing'],
            'na' => (int) $ind['na'],
        ];
    }

    $criteriatables[] = [
        'title' => 'C' . (int) $crit['number'] . ' — ' . $crit['title'],
        'percentagelabel' => $crit['percentage'] !== null ? (int) $crit['percentage'] . ' %' :
            get_string('dashboard_manual_only', 'local_qualiscope'),
        'indicatorlabel' => get_string('campaign_indicator', 'local_qualiscope'),
        'descriptionlabel' => $descriptionlabel,
        'compliancelabel' => $compliancelabel,
        'rows' => $rows,
    ];
}

$weakpointrows = [];
foreach ($weakpointsfiltered as $wp) {
    $weakpointrows[] = [
        'label' => 'I' . (int) $wp['number'] . ' (C' . (int) $wp['criterion_number'] . ')',
        'title' => $wp['title'],
        'percentage' => (int) $wp['percentage'],
        'failingcourses' => (int) $wp['failingcourses'],
    ];
}

$output = $PAGE->get_renderer('local_qualiscope');

$subtitlelabel = get_string('campaign_report_subtitle', 'local_qualiscope');
$descriptionlabel = get_string('description', 'core');
$compliancelabel = get_string('campaign_compliance_rate', 'local_qualiscope');

echo $OUTPUT->header();

echo $output->render_campaign_print([
    'pluginname' => get_string('pluginname', 'local_qualiscope'),
    'subtitle' => $subtitlelabel,
    'printlabel' => get_string('campaign_print_btn', 'local_qualiscope'),
    'backlabel' => get_string('back_to_dashboard', 'local_qualiscope'),
    'backurl' => (new moodle_url('/local/qualiscope/view_campaign.php', ['id' => $campaignid]))->out(false),
    'generatedlabel' => get_string('export_generated', 'local_qualiscope'),
    'generateddate' => userdate(time(), get_string('strftimedateshort', 'langconfig')),
    'namelabel' => get_string('campaign_name', 'local_qualiscope'),
    'campaignname' => $campaign->name,
    'referentiallabel' => get_string('campaign_referential', 'local_qualiscope'),
    'referential' => \local_qualiscope\helper::localized($referential, 'name') . ' ' . $referential->version,
    'scopelabel' => get_string('campaign_scope', 'local_qualiscope'),
    'scope' => $scopelabels[$campaign->scope] ?? $campaign->scope,
    'globalratelabel' => get_string('export_global_rate', 'local_qualiscope'),
    'globalpercentage' => (int) $globalpercentage,
    'coursesanalysedlabel' => get_string('campaign_courses_analysed', 'local_qualiscope'),
    'coursecount' => count($coursesdata),
    'detectedlabel' => get_string('dashboard_detected', 'local_qualiscope'),
    'detected' => (int) $totals['detected'],
    'verifylabel' => get_string('dashboard_verify', 'local_qualiscope'),
    'verify' => (int) $totals['verify'],
    'missinglabel' => get_string('dashboard_missing', 'local_qualiscope'),
    'missing' => (int) $totals['missing'],
    'nalabel' => get_string('dashboard_na', 'local_qualiscope'),
    'na' => (int) $totals['na'],
    'coursestable' => [
        'title' => get_string('campaign_tab_courses', 'local_qualiscope'),
        'courselabel' => get_string('campaign_course', 'local_qualiscope'),
        'scorelabel' => get_string('dashboard_score', 'local_qualiscope'),
        'rows' => $coursesrows,
    ],
    'macrotitle' => get_string('campaign_tab_macro', 'local_qualiscope'),
    'criteriatables' => $criteriatables,
    'weakpointstable' => empty($weakpointrows) ? null : [
        'title' => get_string('campaign_tab_weakpoints', 'local_qualiscope'),
        'indicatorlabel' => get_string('campaign_indicator', 'local_qualiscope'),
        'descriptionlabel' => $descriptionlabel,
        'compliancelabel' => $compliancelabel,
        'failingcourseslabel' => get_string('campaign_non_compliant_count', 'local_qualiscope'),
        'rows' => $weakpointrows,
    ],
]);

echo $OUTPUT->footer();
