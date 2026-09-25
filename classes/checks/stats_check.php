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
 * QualiScope Stats Check class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_qualiscope\checks;


/**
 * Checks real usage metrics: enrolments, completion rate and recorded grades.
 *
 * @package local_qualiscope
 */
class stats_check extends base_check {
    /**
     * Executes the usage statistics check on the course.
     *
     * @param int $courseid The course id to audit.
     * @param object $check The check record.
     * @return array Result with status, detail and optional ratio keys.
     */
    public function execute(int $courseid, object $check): array {
        global $DB;

        // Calculate key metrics: enrolled, completion rate, grade average.
        $context = \context_course::instance($courseid);
        $enrolled = count_enrolled_users($context);

        if ($enrolled === 0) {
            return $this->build_result('verify', get_string('check_stats_no_enrolments', 'local_qualiscope'), 0.2);
        }

        // Completion count.
        $completedcount = $DB->get_field_sql(
            "SELECT COUNT(DISTINCT userid)
             FROM {course_completions}
             WHERE course = :courseid AND timecompleted > 0",
            ['courseid' => $courseid]
        );

        $completionrate = $enrolled > 0 ? round(($completedcount / $enrolled) * 100, 1) : 0;

        // Grades count.
        $gradescount = $DB->get_field_sql(
            "SELECT COUNT(gg.id)
             FROM {grade_grades} gg
             JOIN {grade_items} gi ON gi.id = gg.itemid
             WHERE gi.courseid = :courseid AND gg.finalgrade IS NOT NULL",
            ['courseid' => $courseid]
        );

        $detail = get_string('check_stats_calculated', 'local_qualiscope', [
            'enrolled' => $enrolled,
            'completed' => $completedcount,
            'rate' => $completionrate,
            'grades' => $gradescount,
        ]);

        return $this->build_result('detected', $detail, 1.0);
    }
}
