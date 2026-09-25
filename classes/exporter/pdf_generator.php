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
 * QualiScope Pdf Generator class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_qualiscope\exporter;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/pdflib.php');

/**
 * High-definition PDF report generator for QualiScope course audits (Moodle / Qualiopi).
 *
 * @package local_qualiscope
 */
class pdf_generator {
    /** @var \pdf */
    private $pdf;

    /** @var object Course record */
    private $course;

    /** @var object Referential record */
    private $referential;

    /** @var array Summary statistics */
    private $summary;

    /** @var array Criteria summary */
    private $criteriasummary;

    /** @var array Results list */
    private $results;

    /**
     * Constructor.
     *
     * @param object $course The course record.
     * @param object $referential The referential record.
     * @param array $summary Global summary statistics.
     * @param array $criteriasummary Per-criterion summary data.
     * @param array $results List of check results.
     */
    public function __construct(object $course, object $referential, array $summary, array $criteriasummary, array $results) {
        $this->course = $course;
        $this->referential = $referential;
        $this->summary = $summary;
        $this->criteriasummary = $criteriasummary;
        $this->results = $results;

        $this->pdf = new \pdf();
        $this->pdf->SetCreator('QualiScope - Moodle Quality Engine');
        $this->pdf->SetAuthor('QualiScope');
        $this->pdf->SetTitle(get_string('export_report_title', 'local_qualiscope') . ' - ' . $this->course->fullname);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(true);
        $this->pdf->SetMargins(15, 15, 15);
        $this->pdf->SetAutoPageBreak(true, 15);
    }

    /**
     * Builds and renders the full PDF document.
     *
     * @return string Raw PDF bytes.
     */
    public function generate(): string {
        $this->pdf->AddPage();

        $primarycolor = '#1e3a8a';
        $accentcolor = '#2563eb';
        $graybg = '#f8fafc';
        $bordercolor = '#e2e8f0';

        // Header / Cover section.
        $html = '
        <style>
            h1 { color: ' . $primarycolor . '; font-size: 20pt; font-weight: bold; margin-bottom: 4px; }
            h2 { color: ' . $accentcolor . '; font-size: 14pt; font-weight: bold; margin-top: 15px;
                margin-bottom: 6px; border-bottom: 1px solid ' . $bordercolor . '; }
            h3 { color: #1e293b; font-size: 11pt; font-weight: bold; margin-top: 10px; margin-bottom: 4px; }
            .meta-box { background-color: ' . $graybg . '; border: 1px solid ' . $bordercolor . ';
                padding: 10px; border-radius: 6px; margin-bottom: 15px; }
            .badge-score { font-size: 18pt; font-weight: bold; color: ' . $primarycolor . '; }
            table.grid { width: 100%; border-collapse: collapse; margin-top: 8px; margin-bottom: 12px; }
            table.grid th { background-color: #f1f5f9; color: #334155; font-weight: bold; font-size: 9pt;
                padding: 6px; border: 1px solid ' . $bordercolor . '; }
            table.grid td { font-size: 8.5pt; padding: 5px; border: 1px solid ' . $bordercolor . '; vertical-align: top; }
            .status-detected { color: #15803d; font-weight: bold; }
            .status-verify { color: #b45309; font-weight: bold; }
            .status-missing { color: #b91c1c; font-weight: bold; }
            .status-manual { color: #475569; font-style: italic; }
            .progress-bar-container { background-color: #e2e8f0; height: 10px; border-radius: 5px; }
        </style>

        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td width="70%">
                    <h1>QualiScope</h1>
                    <div style="font-size: 12pt; color: #475569;">' . s(get_string(
                'export_pdf_subtitle',
                'local_qualiscope'
            )) . '</div>
                </td>
                <td width="30%" align="right">
                    <div style="font-size: 9pt; color: #64748b;">' . s(get_string('export_generated', 'local_qualiscope')) . '</div>
                    <div style="font-size: 10pt; font-weight: bold; color: #1e293b;">' . userdate(
                        time(),
                        get_string('strftimedateshort', 'langconfig')
                    ) . '</div>
                </td>
            </tr>
        </table>
        <hr style="color: ' . $primarycolor . '; height: 2px; margin-top: 8px; margin-bottom: 12px;" />

        <div class="meta-box">
            <table width="100%" cellpadding="4" cellspacing="0">
                <tr>
                    <td width="65%">
                        <strong>' . s(get_string('export_course', 'local_qualiscope')) . '</strong>
                            ' . s($this->course->fullname) . ' (' . s($this->course->shortname) . ')<br/>
                        <strong>' . s(get_string('export_referential', 'local_qualiscope')) . '</strong>
                            ' . s(\local_qualiscope\helper::localized($this->referential, 'name')) . ' ' .
                                s($this->referential->version) . '<br/>
                        <strong>' . s(get_string('export_global_rate', 'local_qualiscope')) . '</strong>
                            <span class="badge-score">' . (int) $this->summary['percentage'] . ' %</span>
                    </td>
                    <td width="35%" style="border-left: 1px solid #cbd5e1; padding-left: 10px;">
                        <strong>' . s(get_string('export_breakdown', 'local_qualiscope')) . '</strong><br/>
                        <span class="status-detected">● ' . s(get_string(
                            'dashboard_detected',
                            'local_qualiscope'
                        )) . ' : ' . (int) $this->summary['detected'] . '</span><br/>
                        <span class="status-verify">● ' . s(get_string(
                            'dashboard_verify',
                            'local_qualiscope'
                        )) . ' : ' . (int) $this->summary['verify'] . '</span><br/>
                        <span class="status-missing">● ' . s(get_string(
                            'dashboard_missing',
                            'local_qualiscope'
                        )) . ' : ' . (int) $this->summary['missing'] . '</span><br/>
                        <span style="color: #64748b;">● ' . s(get_string('dashboard_na', 'local_qualiscope')) . ' : ' .
                            (int) $this->summary['na'] . '</span>
                    </td>
                </tr>
            </table>
        </div>

        <h2>' . s(get_string('export_pdf_criteria_summary_title', 'local_qualiscope')) . '</h2>
        <table class="grid" cellpadding="4" cellspacing="0">
            <thead>
                <tr>
                    <th width="8%">' . s(get_string('export_col_num', 'local_qualiscope')) . '</th>
                    <th width="52%">' . s(get_string('export_col_criterion', 'local_qualiscope')) . '</th>
                    <th width="15%" align="center">' . s(get_string('export_col_compliance', 'local_qualiscope')) . '</th>
                    <th width="25%" align="center">' . s(get_string('export_col_status', 'local_qualiscope')) . '</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($this->criteriasummary as $c) {
            $critobj = $c['criteria'];
            $pctstr = $c['percentage'] !== null ? $c['percentage'] . ' %' : '—';
            $breakdown = $c['detected'] . ' / ' . $c['verify'] . ' / ' . $c['missing'];
            if ($c['manualonly']) {
                $breakdown = get_string('dashboard_manual_only', 'local_qualiscope');
            }

            $html .= '
                <tr>
                    <td align="center"><strong>C' . (int) $critobj->number . '</strong></td>
                    <td><strong>' . s(\local_qualiscope\helper::localized($critobj, 'title')) . '</strong></td>
                    <td align="center"><strong>' . $pctstr . '</strong></td>
                    <td align="center">' . $breakdown . '</td>
                </tr>';
        }

        $html .= '
            </tbody>
        </table>

        <h2>' . s(get_string('export_pdf_detail_title', 'local_qualiscope')) . '</h2>';

        // Map results by check ID.
        $resultmap = [];
        foreach ($this->results as $res) {
            $resultmap[$res['check']->id] = $res;
        }

        global $DB;
        $criteria = $DB->get_records('local_qualiscope_criteria', ['referential_id' => $this->referential->id], 'number ASC');
        $indicators = $DB->get_records_sql(
            "SELECT i.* FROM {local_qualiscope_indicators} i
             JOIN {local_qualiscope_criteria} c ON c.id = i.criterion_id
             WHERE c.referential_id = :refid ORDER BY c.number ASC, i.number ASC",
            ['refid' => $this->referential->id]
        );
        $checks = $DB->get_records_sql(
            "SELECT ch.* FROM {local_qualiscope_checks} ch
             JOIN {local_qualiscope_indicators} i ON i.id = ch.indicator_id
             JOIN {local_qualiscope_criteria} c ON c.id = i.criterion_id
             WHERE c.referential_id = :refid ORDER BY c.number ASC, i.number ASC, ch.id ASC",
            ['refid' => $this->referential->id]
        );

        $indicatorsbycriterion = [];
        foreach ($indicators as $ind) {
            $indicatorsbycriterion[$ind->criterion_id][] = $ind;
        }
        $checksbyindicator = [];
        foreach ($checks as $chk) {
            $checksbyindicator[$chk->indicator_id][] = $chk;
        }

        foreach ($criteria as $criterion) {
            $html .= '
            <h3>Critère ' . (int) $criterion->number . ' — ' . s(\local_qualiscope\helper::localized($criterion, 'title')) . '</h3>
            <table class="grid" cellpadding="4" cellspacing="0">
                <thead>
                    <tr>
                        <th width="12%">' . s(get_string('export_col_indicator', 'local_qualiscope')) . '</th>
                        <th width="30%">' . s(get_string('export_col_check', 'local_qualiscope')) . '</th>
                        <th width="16%">' . s(get_string('export_col_status', 'local_qualiscope')) . '</th>
                        <th width="12%" align="center">' . s(get_string('export_col_compliance', 'local_qualiscope')) . '</th>
                        <th width="30%">' . s(get_string('export_col_detail', 'local_qualiscope')) . '</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($indicatorsbycriterion[$criterion->id] ?? [] as $indicator) {
                $indchecks = $checksbyindicator[$indicator->id] ?? [];
                if (empty($indchecks)) {
                    $html .= '
                    <tr>
                        <td><strong>Ind. ' . (int) $indicator->number . '</strong></td>
                        <td colspan="4"><span class="status-manual">' . s(get_string(
                            'criteria_manual_only',
                            'local_qualiscope'
                        )) . '</span> — ' . s(\local_qualiscope\helper::localized($indicator, 'description')) . '</td>
                    </tr>';
                    continue;
                }

                foreach ($indchecks as $check) {
                    $res = $resultmap[$check->id] ?? null;
                    if ($check->automatic && $res) {
                        $stclass = 'status-' . $res['status'];
                        $stlabel = get_string('status_' . $res['status'], 'local_qualiscope');
                        if ($res['status'] === 'na') {
                            $compliancestr = '—';
                        } else {
                            $ratio = $res['ratio'] ?? ($res['status'] === 'detected' ? 1.0 : 0.0);
                            $compliancestr = (int) round($ratio * 100) . ' %';
                        }
                        $detailstr = s($res['detail'] ?? '');
                    } else if ($check->automatic) {
                        $stclass = 'status-verify';
                        $stlabel = '—';
                        $compliancestr = '—';
                        $detailstr = s(\local_qualiscope\helper::localized($check, 'description'));
                    } else {
                        $stclass = 'status-manual';
                        $stlabel = get_string('dashboard_manual_only', 'local_qualiscope');
                        $compliancestr = '—';
                        $detailstr = s(\local_qualiscope\helper::localized($check, 'description'));
                    }

                    $html .= '
                    <tr>
                        <td><strong>Ind. ' . (int) $indicator->number . '</strong><br/><span style="font-size: 7.5pt;
                                color: #64748b;">' . s(\local_qualiscope\helper::localized($indicator, 'title')) . '</span></td>
                        <td><strong>' . s(\local_qualiscope\helper::localized($check, 'name')) . '</strong></td>
                        <td><span class="' . $stclass . '">' . s($stlabel) . '</span></td>
                        <td align="center"><strong>' . $compliancestr . '</strong></td>
                        <td>' . $detailstr . '</td>
                    </tr>';
                }
            }

            $html .= '
                </tbody>
            </table>';
        }

        $this->pdf->writeHTML($html, true, false, true, false, '');

        return $this->pdf->Output('', 'S');
    }
}
