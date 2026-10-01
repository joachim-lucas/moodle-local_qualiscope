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
 * QualiScope Before Course Deleted class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_qualiscope\hooks;


/**
 * Removes the audit data of a course that is being deleted.
 */
class before_course_deleted {
    /**
     * Callback for \core_course\hook\before_course_deleted.
     *
     * The evidence files live in the course context, so core removes them together with the
     * course. Nothing removes the plugin tables, so the results, corrective actions and
     * evidence records of the course are deleted here: they would otherwise keep the user ids
     * of the people who created them and the free text responsible names, and the evidence
     * records would point at files that no longer exist.
     *
     * The audited course row is removed as well. The free plan counts distinct courses that
     * exist on the site, and a deleted course can never be audited again, so keeping the row
     * would burn a quota slot for good. Course ids are never reused, so no other course can
     * inherit the freed slot.
     *
     * The campaigns are left untouched: they are site wide and keep whatever courses remain.
     *
     * @param \core_course\hook\before_course_deleted $hook
     */
    public static function delete_course(\core_course\hook\before_course_deleted $hook): void {
        global $DB;

        $courseid = (int) $hook->course->id;

        $resultids = $DB->get_fieldset_select(
            'local_qualiscope_results',
            'id',
            'courseid = :courseid',
            ['courseid' => $courseid]
        );

        if (!empty($resultids)) {
            [$insql, $params] = $DB->get_in_or_equal($resultids, SQL_PARAMS_NAMED, 'result');
            $DB->delete_records_select('local_qualiscope_evidences', "result_id $insql", $params);
            $DB->delete_records_select('local_qualiscope_actions', "result_id $insql", $params);
        }

        $DB->delete_records('local_qualiscope_results', ['courseid' => $courseid]);
        $DB->delete_records('local_qualiscope_actions', ['courseid' => $courseid]);
        $DB->delete_records(\local_qualiscope\quota::TABLE, ['courseid' => $courseid]);
    }
}
