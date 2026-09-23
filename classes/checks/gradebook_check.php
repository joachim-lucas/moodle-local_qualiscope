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
 * QualiScope Gradebook Check class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_qualiscope\checks;


/**
 * Checks the gradebook configuration for graded assessment items.
 *
 * @package local_qualiscope
 */
class gradebook_check extends base_check {
    /**
     * Executes the gradebook check on the course.
     *
     * @param int $courseid The course id to audit.
     * @param object $check The check record.
     * @return array Result with status, detail and optional ratio keys.
     */
    public function execute(int $courseid, object $check): array {
        global $DB;

        $count = (int) $DB->get_field_sql(
            "SELECT COUNT(*)
             FROM {grade_items}
             WHERE courseid = :courseid AND itemtype != 'course'",
            ['courseid' => $courseid]
        );

        if ($count >= 4) {
            return $this->build_result('detected', get_string('check_gradebook_found', 'local_qualiscope', $count), 1.0);
        } else if ($count >= 2) {
            return $this->build_result('detected', get_string('check_gradebook_found', 'local_qualiscope', $count), 0.75);
        } else if ($count === 1) {
            return $this->build_result('verify', get_string('check_gradebook_found', 'local_qualiscope', $count), 0.45);
        }

        return $this->build_result('missing', get_string('check_gradebook_none', 'local_qualiscope'), 0.0);
    }
}
