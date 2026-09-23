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
 * QualiScope Compare Campaigns page.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once('../../config.php');
require_once($CFG->dirroot . '/local/qualiscope/lib.php');

$ida = optional_param('id_a', 0, PARAM_INT);
$idb = optional_param('id_b', 0, PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/qualiscope:managecampaigns', $context);

$PAGE->set_url(new moodle_url('/local/qualiscope/compare_campaigns.php', ['id_a' => $ida, 'id_b' => $idb]));
$PAGE->set_title(get_string('campaign_compare_title', 'local_qualiscope'));
$PAGE->set_heading(get_string('campaign_compare_title', 'local_qualiscope'));
$PAGE->set_context($context);

$output = $PAGE->get_renderer('local_qualiscope');

$allcampaigns = $DB->get_records('local_qualiscope_campaigns', [], 'timecreated DESC');

$campaignsoptionsa = [];
$campaignsoptionsb = [];
foreach ($allcampaigns as $c) {
    $ref = $DB->get_record('local_qualiscope_referentials', ['id' => $c->referential_id]);
    $label = $c->name . ' (' . ($ref ? $ref->name . ' ' . $ref->version : '') . ' - ' .
    userdate($c->timecreated, get_string('strftimedateshort', 'langconfig')) . ')';
    $campaignsoptionsa[] = [
        'id' => $c->id,
        'name' => $label,
        'selected' => $c->id == $ida,
    ];
    $campaignsoptionsb[] = [
        'id' => $c->id,
        'name' => $label,
        'selected' => $c->id == $idb,
    ];
}

$hascomparison = false;
$comparisonerror = '';
$campaignadata = null;
$campaignbdata = null;
$deltaglobal = 0;
$deltaglobalclass = '';
$deltaglobalsign = '';
$comparedcriteria = [];
$comparedindicators = [];
$comparedcourses = [];

if ($ida && $idb) {
    if ($ida == $idb) {
        $comparisonerror = get_string('campaign_same_campaign_warning', 'local_qualiscope');
    } else {
        $campaigna = $DB->get_record('local_qualiscope_campaigns', ['id' => $ida]);
        $campaignb = $DB->get_record('local_qualiscope_campaigns', ['id' => $idb]);

        if ($campaigna && $campaignb) {
            $hascomparison = true;

            $processcampaignstats = function ($campaign) use ($DB) {
                $ref = $DB->get_record('local_qualiscope_referentials', ['id' => $campaign->referential_id]);
                $results = $DB->get_records('local_qualiscope_results', ['campaign_id' => $campaign->id], 'courseid ASC');

                $bycourse = [];
                $byindicator = [];
                $totals = ['total' => 0, 'detected' => 0, 'verify' => 0, 'missing' => 0, 'na' => 0, 'weighted' => 0.0];

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

                    $byindicator[$r->indicator_id][] = $r;

                    $totals['total']++;
                    switch ($r->status) {
                        case 'detected':
                            $totals['detected']++;
                            break;
                        case 'verify':
                            $totals['verify']++;
                            break;
                        case 'missing':
                            $totals['missing']++;
                            break;
                        case 'na':
                            $totals['na']++;
                            break;
                    }
                    if ($r->status !== 'na') {
                        $totals['weighted'] += (float) $r->ratio > 0
                            ? (float) $r->ratio
                            : ($r->status === 'detected' ? 1.0 : 0.0);
                    }
                }

                $coursesdata = [];
                foreach ($bycourse as $cid => $entry) {
                    $applicable = $entry['total'] - $entry['na'];
                    $pct = $applicable > 0 ? (int) round(($entry['weighted'] * 100) / $applicable) : 0;
                    $coursesdata[$cid] = [
                        'courseid' => $cid,
                        'coursename' => $entry['course']->fullname,
                        'percentage' => $pct,
                    ];
                }

                $applicable = $totals['total'] - $totals['na'];
                $globalpct = $applicable > 0 ? (int) round(($totals['weighted'] * 100) / $applicable) : 0;

                // Indicators & Criteria.
                $criteria = $DB->get_records('local_qualiscope_criteria', [
                    'referential_id' => $campaign->referential_id,
                ], 'number ASC');
                $indicators = $DB->get_records_sql(
                    "SELECT i.* FROM {local_qualiscope_indicators} i
                     JOIN {local_qualiscope_criteria} c ON c.id = i.criterion_id
                     WHERE c.referential_id = :refid ORDER BY c.number ASC, i.number ASC",
                    ['refid' => $campaign->referential_id]
                );

                $indstats = [];
                $critstats = [];

                foreach ($criteria as $crit) {
                    $critstats[$crit->id] = [
                        'id' => $crit->id,
                        'number' => $crit->number,
                        'title' => $crit->title,
                        'total' => 0,
                        'na' => 0,
                        'weighted' => 0.0,
                        'percentage' => null,
                    ];
                }

                foreach ($indicators as $ind) {
                    $indresults = $byindicator[$ind->id] ?? [];
                    $indtotal = count($indresults);
                    $indna = 0;
                    $indweighted = 0.0;

                    foreach ($indresults as $ir) {
                        if ($ir->status === 'na') {
                            $indna++;
                        } else {
                            $indweighted += (float) $ir->ratio > 0 ? (float) $ir->ratio : ($ir->status === 'detected' ? 1.0 : 0.0);
                        }
                    }

                    $indapp = $indtotal - $indna;
                    $indpct = $indapp > 0 ? (int) round(($indweighted * 100) / $indapp) : null;

                    $indstats[$ind->number] = [
                        'id' => $ind->id,
                        'number' => $ind->number,
                        'title' => $ind->title,
                        'criterion_id' => $ind->criterion_id,
                        'percentage' => $indpct,
                    ];

                    if (isset($critstats[$ind->criterion_id])) {
                        $critstats[$ind->criterion_id]['total'] += $indtotal;
                        $critstats[$ind->criterion_id]['na'] += $indna;
                        $critstats[$ind->criterion_id]['weighted'] += $indweighted;
                    }
                }

                foreach ($critstats as &$cs) {
                    $capp = $cs['total'] - $cs['na'];
                    if ($capp > 0) {
                        $cs['percentage'] = (int) round(($cs['weighted'] * 100) / $capp);
                    }
                }
                unset($cs);

                return [
                    'campaign' => $campaign,
                    'referential' => $ref ? $ref->name . ' ' . $ref->version : '—',
                    'dateformatted' => userdate($campaign->timecreated, get_string('strftimedateshort', 'langconfig')),
                    'coursescount' => count($coursesdata),
                    'coursesdata' => $coursesdata,
                    'globalpercentage' => $globalpct,
                    'criteria' => $critstats,
                    'indicators' => $indstats,
                ];
            };

            $campaignadata = $processcampaignstats($campaigna);
            $campaignbdata = $processcampaignstats($campaignb);

            $deltaglobal = $campaignbdata['globalpercentage'] - $campaignadata['globalpercentage'];
            $deltaglobalsign = $deltaglobal > 0 ? '+' : '';
            $deltaglobalclass = $deltaglobal > 0 ? 'text-success' : ($deltaglobal < 0 ? 'text-danger' : 'text-muted');

            // Compare criteria.
            foreach ($campaignbdata['criteria'] as $cid => $critb) {
                $crita = $campaignadata['criteria'][$cid] ?? null;
                $pcta = $crita && $crita['percentage'] !== null ? $crita['percentage'] : null;
                $pctb = $critb['percentage'] !== null ? $critb['percentage'] : null;
                $delta = ($pcta !== null && $pctb !== null) ? ($pctb - $pcta) : null;

                $comparedcriteria[] = [
                    'number' => $critb['number'],
                    'title' => $critb['title'],
                    'score_a' => $pcta !== null ? $pcta . '%' : '—',
                    'score_b' => $pctb !== null ? $pctb . '%' : '—',
                    'delta' => $delta !== null ? ($delta > 0 ? '+' . $delta . '%' : $delta . '%') : '—',
                    'deltaclass' => $delta !== null ? ($delta > 0 ? 'text-success' :
                        ($delta < 0 ? 'text-danger' : 'text-muted')) : 'text-muted',
                    'has_progression' => $delta !== null && $delta > 0,
                    'has_regression' => $delta !== null && $delta < 0,
                ];
            }

            // Compare indicators.
            foreach ($campaignbdata['indicators'] as $indnum => $indb) {
                $inda = $campaignadata['indicators'][$indnum] ?? null;
                $pcta = $inda && $inda['percentage'] !== null ? $inda['percentage'] : null;
                $pctb = $indb['percentage'] !== null ? $indb['percentage'] : null;
                $delta = ($pcta !== null && $pctb !== null) ? ($pctb - $pcta) : null;

                $comparedindicators[] = [
                    'number' => $indb['number'],
                    'title' => $indb['title'],
                    'score_a' => $pcta !== null ? $pcta . '%' : '—',
                    'score_b' => $pctb !== null ? $pctb . '%' : '—',
                    'delta' => $delta !== null ? ($delta > 0 ? '+' . $delta . '%' : $delta . '%') : '—',
                    'deltaclass' => $delta !== null ? ($delta > 0 ? 'text-success' :
                        ($delta < 0 ? 'text-danger' : 'text-muted')) : 'text-muted',
                    'has_progression' => $delta !== null && $delta > 0,
                    'has_regression' => $delta !== null && $delta < 0,
                ];
            }

            // Compare courses.
            $allcourseids = array_unique(array_merge(
                array_keys($campaignadata['coursesdata']),
                array_keys($campaignbdata['coursesdata'])
            ));

            foreach ($allcourseids as $cid) {
                $ca = $campaignadata['coursesdata'][$cid] ?? null;
                $cb = $campaignbdata['coursesdata'][$cid] ?? null;
                $coursename = $cb['coursename'] ?? ($ca['coursename'] ?? 'Course #' . $cid);
                $pcta = $ca ? $ca['percentage'] : null;
                $pctb = $cb ? $cb['percentage'] : null;
                $delta = ($pcta !== null && $pctb !== null) ? ($pctb - $pcta) : null;

                $comparedcourses[] = [
                    'coursename' => $coursename,
                    'score_a' => $pcta !== null ? $pcta . '%' : '—',
                    'score_b' => $pctb !== null ? $pctb . '%' : '—',
                    'delta' => $delta !== null ? ($delta > 0 ? '+' . $delta . '%' : $delta . '%') : '—',
                    'deltaclass' => $delta !== null ? ($delta > 0 ? 'text-success' :
                        ($delta < 0 ? 'text-danger' : 'text-muted')) : 'text-muted',
                    'has_progression' => $delta !== null && $delta > 0,
                    'has_regression' => $delta !== null && $delta < 0,
                ];
            }
        }
    }
}

echo $output->header();
echo $output->render_compare_campaigns([
    'campaignsoptionsa' => $campaignsoptionsa,
    'campaignsoptionsb' => $campaignsoptionsb,
    'hascomparison' => $hascomparison,
    'comparisonerror' => $comparisonerror,
    'campaigndata_a' => $campaignadata,
    'campaigndata_b' => $campaignbdata,
    'deltaglobal' => $deltaglobalsign . $deltaglobal . '%',
    'deltaglobalclass' => $deltaglobalclass,
    'has_global_progression' => $deltaglobal > 0,
    'has_global_regression' => $deltaglobal < 0,
    'comparedcriteria' => $comparedcriteria,
    'comparedindicators' => $comparedindicators,
    'comparedcourses' => $comparedcourses,
    'backurl' => new moodle_url('/local/qualiscope/campaigns.php'),
]);
echo $output->footer();
