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
 * QualiScope Feedback Check class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_qualiscope\checks;


/**
 * Checks feedback activities and the real response rate of enrolled learners.
 *
 * @package local_qualiscope
 */
class feedback_check extends base_check {
    /** @var float Minimum response coverage ratio required to consider the check detected. */
    private const COVERAGE_TARGET = 0.33;

    /**
     * Executes the feedback/satisfaction check on the course.
     *
     * @param int $courseid The course id to audit.
     * @param object $check The check record.
     * @return array Result with status, detail and optional ratio keys.
     */
    public function execute(int $courseid, object $check): array {
        global $DB;

        $instances = $DB->get_records_sql(
            "SELECT cm.id AS cmid, f.id AS fid, f.name
             FROM {course_modules} cm
             JOIN {modules} m ON m.id = cm.module
             JOIN {feedback} f ON f.id = cm.instance
             WHERE cm.course = :courseid AND cm.visible = 1 AND m.name = 'feedback'
             ORDER BY cm.id ASC",
            ['courseid' => $courseid]
        );

        if (empty($instances)) {
            return $this->build_result('missing', get_string('check_feedback_none', 'local_qualiscope'), 0.0);
        }

        $fids = array_map(function ($i) {
            return $i->fid;
        }, $instances);
        [$fidsql, $fidparams] = $DB->get_in_or_equal($fids, SQL_PARAMS_NAMED, 'fid');

        $items = $DB->get_records_sql(
            "SELECT feedback, COUNT(*) AS cnt
             FROM {feedback_item}
             WHERE feedback $fidsql
             GROUP BY feedback",
            $fidparams
        );

        $itemcount = 0;
        $fidswithitems = [];
        foreach ($items as $fid => $row) {
            $itemcount += $row->cnt;
            $fidswithitems[] = (int) $fid;
        }

        if ($itemcount == 0) {
            return $this->build_result(
                'verify',
                get_string('check_feedback_no_items', 'local_qualiscope', count($instances)),
                0.15
            );
        }

        [$fidsql2, $fidparams2] = $DB->get_in_or_equal($fidswithitems, SQL_PARAMS_NAMED, 'fid');

        $responsecount = $DB->get_field_sql(
            "SELECT COUNT(DISTINCT fc.id)
             FROM {feedback_completed} fc
             WHERE fc.feedback $fidsql2",
            $fidparams2
        );

        if ($responsecount == 0) {
            return $this->build_result(
                'verify',
                get_string('check_feedback_no_responses', 'local_qualiscope', [
                    'count' => $itemcount,
                    'activities' => count($fidswithitems),
                ]),
                0.15
            );
        }

        $enrolled = count_enrolled_users(\context_course::instance($courseid));
        $target = (int) ceil(max(1, $enrolled) * self::COVERAGE_TARGET);

        if ($responsecount >= $target) {
            return $this->build_result(
                'detected',
                get_string('check_feedback_found', 'local_qualiscope', [
                    'activities' => count($fidswithitems),
                    'items' => $itemcount,
                    'responses' => $responsecount,
                    'target' => $target,
                ]),
                1.0
            );
        }

        return $this->build_result(
            'verify',
            get_string('check_feedback_insufficient', 'local_qualiscope', [
                'count' => $itemcount,
                'activities' => count($fidswithitems),
                'responses' => $responsecount,
                'target' => $target,
            ]),
            $responsecount / $target
        );
    }
}
