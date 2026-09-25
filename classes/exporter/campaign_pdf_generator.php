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
 * QualiScope Campaign Pdf Generator class.
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
 * High-definition PDF audit report generator for QualiScope campaigns.
 *
 * @package local_qualiscope
 */
class campaign_pdf_generator {
    /** @var \pdf */
    private $pdf;

    /** @var object Campaign record */
    private $campaign;

    /** @var object Referential record */
    private $referential;

    /** @var array Summary statistics */
    private $summary;

    /** @var array Courses data */
    private $courses;

    /** @var array Criteria data */
    private $criteria;

    /** @var array Weak points data */
    private $weakpoints;

    /**
     * Constructor.
     *
     * @param object $campaign The campaign record.
     * @param object $referential The referential record.
     * @param array $summary Global campaign summary statistics.
     * @param array $courses Per-course compliance data.
     * @param array $criteria Per-criterion/indicator compliance data.
     * @param array $weakpoints Weak points priority list.
     */
    public function __construct(
        object $campaign,
        object $referential,
        array $summary,
        array $courses,
        array $criteria,
        array $weakpoints
    ) {
        $this->campaign = $campaign;
        $this->referential = $referential;
        $this->summary = $summary;
        $this->courses = $courses;
        $this->criteria = $criteria;
        $this->weakpoints = $weakpoints;

        $this->pdf = new \pdf();
        $this->pdf->SetCreator('QualiScope - Moodle Quality Engine');
        $this->pdf->SetAuthor('QualiScope');
        $this->pdf->SetTitle(get_string('campaign_title', 'local_qualiscope') . ' - ' . $this->campaign->name);
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

        $scopelabels = [
            'all' => get_string('campaign_scope_all', 'local_qualiscope'),
            'category' => get_string('campaign_scope_category', 'local_qualiscope'),
            'selected' => get_string('campaign_scope_selected', 'local_qualiscope'),
        ];
        $scopestr = $scopelabels[$this->campaign->scope] ?? $this->campaign->scope;

        $html = '
        <style>
            h1 { color: ' . $primarycolor . '; font-size: 18pt; font-weight: bold; margin-bottom: 2px; }
            h2 { color: ' . $accentcolor . '; font-size: 13pt; font-weight: bold; margin-top: 14px;
                margin-bottom: 6px; border-bottom: 1px solid ' . $bordercolor . '; }
            h3 { color: #1e293b; font-size: 10.5pt; font-weight: bold; margin-top: 10px; margin-bottom: 4px; }
            .meta-box { background-color: ' . $graybg . '; border: 1px solid ' . $bordercolor . ';
                padding: 10px; border-radius: 6px; margin-bottom: 12px; }
            .badge-score { font-size: 16pt; font-weight: bold; color: ' . $primarycolor . '; }
            table.grid { width: 100%; border-collapse: collapse; margin-top: 6px; margin-bottom: 10px; }
            table.grid th { background-color: #f1f5f9; color: #334155; font-weight: bold; font-size: 8.5pt;
                padding: 5px; border: 1px solid ' . $bordercolor . '; }
            table.grid td { font-size: 8pt; padding: 4px; border: 1px solid ' . $bordercolor . '; vertical-align: middle; }
            .status-detected { color: #15803d; font-weight: bold; }
            .status-verify { color: #b45309; font-weight: bold; }
            .status-missing { color: #b91c1c; font-weight: bold; }
            .status-na { color: #64748b; }
        </style>

        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td width="70%">
                    <h1>QualiScope</h1>
                    <div style="font-size: 11pt; color: #475569;">' . s(get_string(
                'campaign_report_subtitle',
                'local_qualiscope'
            )) . '</div>
                </td>
                <td width="30%" align="right">
                    <div style="font-size: 8.5pt; color: #64748b;">' . s(get_string(
                'export_generated',
                'local_qualiscope'
            )) . '</div>
                    <div style="font-size: 9.5pt; font-weight: bold; color: #1e293b;">' . userdate(
                time(),
                get_string('strftimedateshort', 'langconfig')
            ) . '</div>
                </td>
            </tr>
        </table>
        <hr style="color: ' . $primarycolor . '; height: 2px; margin-top: 6px; margin-bottom: 10px;" />

        <div class="meta-box">
            <table width="100%" cellpadding="3" cellspacing="0">
                <tr>
                    <td width="65%">
                        <strong>' . s(get_string('campaign_name', 'local_qualiscope')) . ' :</strong> ' .
                            s($this->campaign->name) . '<br/>
                        <strong>' . s(get_string('campaign_referential', 'local_qualiscope')) . ' :</strong> ' .
                            s(\local_qualiscope_localized($this->referential, 'name')) . ' ' .
                            s($this->referential->version) . '<br/>
                        <strong>' . s(get_string('campaign_scope', 'local_qualiscope')) . ' :</strong> ' . s($scopestr) . '<br/>
                        <strong>' . s(get_string('export_global_rate', 'local_qualiscope')) . '</strong>
                            <span class="badge-score">' . (int) $this->summary['score'] . ' %</span>
                    </td>
                    <td width="35%" style="border-left: 1px solid #cbd5e1; padding-left: 8px;">
                        <strong>' . s(get_string('campaign_courses_analysed', 'local_qualiscope')) . ' :</strong> ' .
                            count($this->courses) . '<br/>
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
                        <span class="status-na">● ' . s(get_string(
                                'dashboard_na',
                                'local_qualiscope'
                            )) . ' : ' . (int) $this->summary['na'] . '</span>
                    </td>
                </tr>
            </table>
        </div>

        <h2>' . s(get_string('campaign_tab_courses', 'local_qualiscope')) . '</h2>
        <table class="grid" cellpadding="4" cellspacing="0">
            <thead>
                <tr>
                    <th width="45%">' . s(get_string('campaign_course', 'local_qualiscope')) . '</th>
                    <th width="15%" align="center">' . s(get_string('dashboard_score', 'local_qualiscope')) . '</th>
                    <th width="10%" align="center">✓</th>
                    <th width="10%" align="center">⚠</th>
                    <th width="10%" align="center">✗</th>
                    <th width="10%" align="center">—</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($this->courses as $course) {
            $html .= '
                <tr>
                    <td>' . s($course['coursename']) . '</td>
                    <td align="center"><strong>' . (int) $course['percentage'] . ' %</strong></td>
                    <td align="center" class="status-detected">' . (int) $course['detected'] . '</td>
                    <td align="center" class="status-verify">' . (int) $course['verify'] . '</td>
                    <td align="center" class="status-missing">' . (int) $course['missing'] . '</td>
                    <td align="center" class="status-na">' . (int) $course['na'] . '</td>
                </tr>';
        }

        $html .= '
            </tbody>
        </table>

        <h2>' . s(get_string('campaign_tab_macro', 'local_qualiscope')) . '</h2>';

        foreach ($this->criteria as $criterion) {
            $critpct = $criterion['percentage'] !== null ? $criterion['percentage'] . ' %' :
                get_string('dashboard_manual_only', 'local_qualiscope');
            $html .= '
            <h3>C' . (int) $criterion['number'] . ' — ' . s($criterion['title']) . ' (' . s($critpct) . ')</h3>
            <table class="grid" cellpadding="4" cellspacing="0">
                <thead>
                    <tr>
                        <th width="10%">' . s(get_string('campaign_indicator', 'local_qualiscope')) . '</th>
                        <th width="45%">' . s(get_string('description', 'core')) . '</th>
                        <th width="15%" align="center">' . s(get_string('campaign_compliance_rate', 'local_qualiscope')) . '</th>
                        <th width="7%" align="center">✓</th>
                        <th width="7%" align="center">⚠</th>
                        <th width="8%" align="center">✗</th>
                        <th width="8%" align="center">—</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($criterion['indicators'] as $indicator) {
                $indpct = $indicator['haspercentage'] ? $indicator['percentage'] . ' %' : '—';
                $html .= '
                <tr>
                    <td align="center"><strong>I' . (int) $indicator['number'] . '</strong></td>
                    <td>' . s($indicator['title']) . '</td>
                    <td align="center"><strong>' . s($indpct) . '</strong></td>
                    <td align="center" class="status-detected">' . (int) $indicator['detected'] . '</td>
                    <td align="center" class="status-verify">' . (int) $indicator['verify'] . '</td>
                    <td align="center" class="status-missing">' . (int) $indicator['missing'] . '</td>
                    <td align="center" class="status-na">' . (int) $indicator['na'] . '</td>
                </tr>';
            }

            $html .= '
                </tbody>
            </table>';
        }

        if (!empty($this->weakpoints)) {
            $html .= '
            <h2>' . s(get_string('campaign_tab_weakpoints', 'local_qualiscope')) . '</h2>
            <table class="grid" cellpadding="4" cellspacing="0">
                <thead>
                    <tr>
                        <th width="12%">' . s(get_string('campaign_indicator', 'local_qualiscope')) . '</th>
                        <th width="48%">' . s(get_string('description', 'core')) . '</th>
                        <th width="18%" align="center">' . s(get_string('campaign_compliance_rate', 'local_qualiscope')) . '</th>
                        <th width="22%" align="center">' . s(get_string(
                            'campaign_non_compliant_count',
                            'local_qualiscope'
                        )) . '</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($this->weakpoints as $wp) {
                $html .= '
                <tr>
                    <td align="center"><strong>I' . (int) $wp['number'] . ' (C' . (int) $wp['criterion_number'] . ')</strong></td>
                    <td>' . s($wp['title']) . '</td>
                    <td align="center" class="status-missing"><strong>' . (int) $wp['percentage'] . ' %</strong></td>
                    <td align="center" class="status-missing">' . (int) $wp['failingcourses'] . '</td>
                </tr>';
            }

            $html .= '
                </tbody>
            </table>';
        }

        $this->pdf->writeHTML($html, true, false, true, false, '');

        return $this->pdf->Output('', 'S');
    }
}
