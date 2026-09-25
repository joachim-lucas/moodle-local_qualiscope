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
 * QualiScope Renderer class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_qualiscope\output;


/**
 * Plugin renderer that delegates each view to its Mustache template.
 *
 * @package local_qualiscope
 */
class renderer extends \plugin_renderer_base {
    /**
     * Renders the course dashboard.
     *
     * @param array $data Template context.
     * @return string
     */
    public function render_dashboard(array $data): string {
        return $this->render_from_template('local_qualiscope/dashboard', $data);
    }

    /**
     * Renders the indicator detail view.
     *
     * @param array $data Template context.
     * @return string
     */
    public function render_indicator_detail(array $data): string {
        return $this->render_from_template('local_qualiscope/indicator', $data);
    }

    /**
     * Renders the evidence panel.
     *
     * @param array $data Template context.
     * @return string
     */
    public function render_evidence_panel(array $data): string {
        return $this->render_from_template('local_qualiscope/evidence', $data);
    }

    /**
     * Renders the corrective action form.
     *
     * @param array $data Template context.
     * @return string
     */
    public function render_action_form(array $data): string {
        return $this->render_from_template('local_qualiscope/action', $data);
    }

    /**
     * Renders the campaign list.
     *
     * @param array $data Template context.
     * @return string
     */
    public function render_campaign_list(array $data): string {
        return $this->render_from_template('local_qualiscope/campaign', $data);
    }

    /**
     * Renders the campaign dashboard.
     *
     * @param array $data Template context.
     * @return string
     */
    public function render_campaign_dashboard(array $data): string {
        return $this->render_from_template('local_qualiscope/campaign_dashboard', $data);
    }

    /**
     * Renders the campaign comparison view.
     *
     * @param array $data Template context.
     * @return string
     */
    public function render_compare_campaigns(array $data): string {
        return $this->render_from_template('local_qualiscope/compare_campaigns', $data);
    }

    /**
     * Renders the help/methodology view.
     *
     * @param array $data Template context.
     * @return string
     */
    public function render_help(array $data): string {
        return $this->render_from_template('local_qualiscope/help', $data);
    }

    /**
     * Builds an HTML progress bar.
     *
     * @param int $percentage Score between 0 and 100.
     * @param string $label Optional label under the bar.
     * @return string
     */
    public function render_progress_bar(int $percentage, string $label = ''): string {
        $class = 'success';
        if ($percentage < 50) {
            $class = 'danger';
        } else if ($percentage < 75) {
            $class = 'warning';
        }

        $html = '<div class="qualiscope-progress">';
        $html .= '<div class="progress" style="height: 24px;">';
        $html .= '<div class="progress-bar bg-' . $class . '" role="progressbar" ';
        $html .= 'style="width: ' . $percentage . '%;" ';
        $html .= 'aria-valuenow="' . $percentage . '" aria-valuemin="0" aria-valuemax="100">';
        $html .= $percentage . '%';
        $html .= '</div></div>';
        if ($label) {
            $html .= '<small class="text-muted">' . $label . '</small>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Builds an HTML status badge for a check status.
     *
     * @param string $status Status code (detected, verify, missing, na).
     * @return string
     */
    public function render_status_badge(string $status): string {
        $map = [
            'detected' => ['class' => 'success', 'icon' => '✓', 'label' => get_string('status_detected', 'local_qualiscope')],
            'verify'   => ['class' => 'warning', 'icon' => '⚠', 'label' => get_string('status_verify', 'local_qualiscope')],
            'missing'  => ['class' => 'danger', 'icon' => '✗', 'label' => get_string('status_missing', 'local_qualiscope')],
            'na'       => ['class' => 'secondary', 'icon' => '—', 'label' => get_string('status_na', 'local_qualiscope')],
        ];

        $data = $map[$status] ?? ['class' => 'secondary', 'icon' => '?', 'label' => $status];

        return '<span class="badge bg-' . $data['class'] . '">' . $data['icon'] . ' ' . $data['label'] . '</span>';
    }
}
