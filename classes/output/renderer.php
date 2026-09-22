<?php

namespace local_qualiscope\output;

defined('MOODLE_INTERNAL') || die();

class renderer extends \plugin_renderer_base {

    public function render_dashboard(array $data): string {
        return $this->render_from_template('local_qualiscope/dashboard', $data);
    }

    public function render_indicator_detail(array $data): string {
        return $this->render_from_template('local_qualiscope/indicator', $data);
    }

    public function render_evidence_panel(array $data): string {
        return $this->render_from_template('local_qualiscope/evidence', $data);
    }

    public function render_action_form(array $data): string {
        return $this->render_from_template('local_qualiscope/action', $data);
    }

    public function render_campaign_list(array $data): string {
        return $this->render_from_template('local_qualiscope/campaign', $data);
    }

    public function render_campaign_dashboard(array $data): string {
        return $this->render_from_template('local_qualiscope/campaign_dashboard', $data);
    }

    public function render_compare_campaigns(array $data): string {
        return $this->render_from_template('local_qualiscope/compare_campaigns', $data);
    }

    public function render_help(array $data): string {
        return $this->render_from_template('local_qualiscope/help', $data);
    }

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

    public function render_status_badge(string $status): string {
        $map = [
            'detected' => ['class' => 'success', 'icon' => '✓', 'label' => get_string('status_detected', 'local_qualiscope')],
            'verify'   => ['class' => 'warning', 'icon' => '⚠', 'label' => get_string('status_verify', 'local_qualiscope')],
            'missing'  => ['class' => 'danger',  'icon' => '✗', 'label' => get_string('status_missing', 'local_qualiscope')],
            'na'       => ['class' => 'secondary', 'icon' => '—', 'label' => get_string('status_na', 'local_qualiscope')],
        ];

        $data = $map[$status] ?? ['class' => 'secondary', 'icon' => '?', 'label' => $status];

        return '<span class="badge bg-' . $data['class'] . '">' . $data['icon'] . ' ' . $data['label'] . '</span>';
    }
}
