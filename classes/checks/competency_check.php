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
 * QualiScope Competency Check class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_qualiscope\checks;


/**
 * Checks whether the course is linked to competency frameworks and outcomes.
 *
 * @package local_qualiscope
 */
class competency_check extends base_check {
    /**
     * Executes the competency check on the course.
     *
     * @param int $courseid The course id to audit.
     * @param object $check The check record.
     * @return array Result with status, detail and optional ratio keys.
     */
    public function execute(int $courseid, object $check): array {
        global $DB;

        // Check course competencies.
        $coursecompetencies = $DB->get_records('competency_coursecomp', ['courseid' => $courseid]);
        $count = count($coursecompetencies);

        if ($count > 0) {
            // Check if any modules have linked competencies.
            $modulecompetencies = $DB->get_field_sql(
                "SELECT COUNT(*) FROM {competency_modulecomp} mc
                 JOIN {course_modules} cm ON cm.id = mc.cmid
                 WHERE cm.course = :courseid",
                ['courseid' => $courseid]
            );

            $detail = get_string('check_competency_found', 'local_qualiscope', [
                'count' => $count,
                'linked' => (int) $modulecompetencies,
            ]);

            return $this->build_result('detected', $detail, 1.0);
        }

        // Check if competencies exist in objectives/summary or outcomes.
        $course = $DB->get_record('course', ['id' => $courseid]);
        if ($course && (stripos($course->summary, 'compétence') !== false || stripos($course->summary, 'objectif') !== false)) {
            return $this->build_result('verify', get_string('check_competency_in_summary', 'local_qualiscope'), 0.5);
        }

        return $this->build_result('verify', get_string('check_competency_none', 'local_qualiscope'), 0.0);
    }
}
