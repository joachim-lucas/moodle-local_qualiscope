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
 * QualiScope Activity Check class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_qualiscope\checks;


/**
 * Checks the richness of learning and assessment activities in the course.
 *
 * @package local_qualiscope
 */
class activity_check extends base_check {
    /**
     * Executes the activities check on the course.
     *
     * @param int $courseid The course id to audit.
     * @param object $check The check record.
     * @return array Result with status, detail and optional ratio keys.
     */
    public function execute(int $courseid, object $check): array {
        global $DB;

        $count = (int) $DB->get_field_sql(
            "SELECT COUNT(*)
             FROM {course_modules} cm
             JOIN {modules} m ON m.id = cm.module
             WHERE cm.course = :courseid AND cm.visible = 1
                 AND m.name IN ('assign','quiz','workshop','choice','data','lesson','lti',
                                'survey','bigbluebuttonbn','jitsi','h5pactivity','forum','glossary','feedback')",
            ['courseid' => $courseid]
        );

        if ($count >= 6) {
            return $this->build_result('detected', get_string('check_activity_found', 'local_qualiscope', $count), 1.0);
        } else if ($count >= 3) {
            $ratio = round(0.5 + (($count - 3) / 6), 2);
            return $this->build_result('detected', get_string('check_activity_found', 'local_qualiscope', $count), $ratio);
        } else if ($count > 0) {
            return $this->build_result('verify', get_string('check_activity_found', 'local_qualiscope', $count), 0.4);
        }

        return $this->build_result('missing', get_string('check_activity_none', 'local_qualiscope'), 0.0);
    }
}
