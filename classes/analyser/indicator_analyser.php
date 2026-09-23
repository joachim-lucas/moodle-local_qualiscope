<?php

namespace local_qualiscope\analyser;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper providing human-facing representation and scoring of indicator statuses.
 *
 * @package local_qualiscope
 */
class indicator_analyser {

    /**
     * Maps a status code to its localised label.
     *
     * @param string $status Status code (detected, verify, missing, na).
     * @return string
     */
    public static function get_status_label(string $status): string {
        $labels = [
            'detected' => get_string('status_detected', 'local_qualiscope'),
            'verify'   => get_string('status_verify', 'local_qualiscope'),
            'missing'  => get_string('status_missing', 'local_qualiscope'),
            'na'       => get_string('status_na', 'local_qualiscope'),
        ];
        return $labels[$status] ?? $status;
    }

    /**
     * Maps a status code to its display icon.
     *
     * @param string $status Status code (detected, verify, missing, na).
     * @return string
     */
    public static function get_status_icon(string $status): string {
        $icons = [
            'detected' => '✓',
            'verify'   => '⚠',
            'missing'  => '✗',
            'na'       => '—',
        ];
        return $icons[$status] ?? '?';
    }

    /**
     * Maps a status code to a CSS class used for badges and tables.
     *
     * @param string $status Status code (detected, verify, missing, na).
     * @return string
     */
    public static function get_status_class(string $status): string {
        $classes = [
            'detected' => 'success',
            'verify'   => 'warning',
            'missing'  => 'danger',
            'na'       => 'secondary',
        ];
        return $classes[$status] ?? 'secondary';
    }

    /**
     * Computes the weighted compliance score for a set of check results.
     *
     * @param array $results Results arrays containing check and status keys.
     * @return array Associative array with total, applicable, weighted and percentage.
     */
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
