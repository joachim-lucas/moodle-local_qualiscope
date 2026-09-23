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
 * QualiScope Base Check class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_qualiscope\checks;


/**
 * Base class for automated course audit checks.
 *
 * @package local_qualiscope
 */
abstract class base_check {
    /**
     * Runs the check against the given course.
     *
     * @param int $courseid The course id to audit.
     * @param object $check The check record.
     * @return array Result with status, detail and optional ratio keys.
     */
    abstract public function execute(int $courseid, object $check): array;

    /**
     * Builds a normalised check result array, clamping the ratio between 0 and 1.
     *
     * @param string $status Status code (detected, verify, missing, na).
     * @param string $detail Human-readable detail for the result.
     * @param float|null $ratio Optional compliance ratio between 0 and 1.
     * @return array
     */
    protected function build_result(string $status, string $detail = '', ?float $ratio = null): array {
        $result = [
            'status' => $status,
            'detail' => $detail,
        ];
        if ($ratio !== null) {
            $result['ratio'] = max(0.0, min(1.0, $ratio));
        }
        return $result;
    }
}
