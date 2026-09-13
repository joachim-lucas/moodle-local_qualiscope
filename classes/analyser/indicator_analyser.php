<?php

namespace local_qualiscope\analyser;

defined('MOODLE_INTERNAL') || die();

class indicator_analyser {

    public static function get_status_label(string $status): string {
        $labels = [
            'detected' => get_string('status_detected', 'local_qualiscope'),
            'verify'   => get_string('status_verify', 'local_qualiscope'),
            'missing'  => get_string('status_missing', 'local_qualiscope'),
            'na'       => get_string('status_na', 'local_qualiscope'),
        ];
        return $labels[$status] ?? $status;
    }

    public static function get_status_icon(string $status): string {
        $icons = [
            'detected' => '✓',
            'verify'   => '⚠',
            'missing'  => '✗',
            'na'       => '—',
        ];
        return $icons[$status] ?? '?';
    }

    public static function get_status_class(string $status): string {
        $classes = [
            'detected' => 'success',
            'verify'   => 'warning',
            'missing'  => 'danger',
            'na'       => 'secondary',
        ];
        return $classes[$status] ?? 'secondary';
    }

    public static function compute_indicator_score(array $results): array {
        $total = 0;
        $weighted = 0;
        $applicable = 0;

        foreach ($results as $result) {
            $weight = $result['check']->weight ?? 1;
            $total += $weight;
            if ($result['status'] === 'na') {
                continue;
            }
            $applicable += $weight;
            if ($result['status'] === 'detected') {
                $weighted += $weight;
            }
        }

        $percentage = 0;
        if ($applicable > 0) {
            $percentage = (int) round(($weighted * 100) / $applicable);
        }

        return [
            'total' => $total,
            'applicable' => $applicable,
            'weighted' => $weighted,
            'percentage' => $percentage,
        ];
    }
}
