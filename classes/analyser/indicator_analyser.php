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
 * QualiScope Indicator Analyser class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_qualiscope\analyser;


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
