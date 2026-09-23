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
 * QualiScope Field Check class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_qualiscope\checks;


/**
 * Checks the course structure and metadata (summary, sections, dates).
 *
 * @package local_qualiscope
 */
class field_check extends base_check {
    /**
     * Executes the structure/metadata check on the course.
     *
     * @param int $courseid The course id to audit.
     * @param object $check The check record.
     * @return array Result with status, detail and optional ratio keys.
     */
    public function execute(int $courseid, object $check): array {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            return $this->build_result('missing', get_string('check_course_notfound', 'local_qualiscope'));
        }

        $checks = [];

        if (!empty($course->summary)) {
            $checks[] = get_string('check_field_summary_found', 'local_qualiscope');
        }

        $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');
        $sectionswithcontent = 0;
        foreach ($sections as $section) {
            if (!empty($section->summary) || $section->sequence) {
                $sectionswithcontent++;
            }
        }

        if ($sectionswithcontent > 1) {
            $checks[] = get_string('check_field_sections_found', 'local_qualiscope', $sectionswithcontent);
        }

        if (!empty($course->startdate) && !empty($course->enddate)) {
            $checks[] = get_string('check_field_dates_found', 'local_qualiscope');
        } else if (empty($course->startdate)) {
            return $this->build_result('verify', get_string('check_field_dates_missing', 'local_qualiscope'));
        }

        $totalpossible = 3;
        $foundcount = count($checks);
        $ratio = round($foundcount / $totalpossible, 2);

        if ($foundcount >= 3) {
            return $this->build_result('detected', implode(' | ', $checks), 1.0);
        } else if ($foundcount === 2) {
            return $this->build_result('detected', implode(' | ', $checks), 0.75);
        } else if ($foundcount === 1) {
            return $this->build_result(
                'verify',
                implode(' | ', $checks) . ' (' . get_string('check_field_partial', 'local_qualiscope') . ')',
                0.40
            );
        }

        return $this->build_result('missing', get_string('check_field_partial', 'local_qualiscope'), 0.0);
    }
}
