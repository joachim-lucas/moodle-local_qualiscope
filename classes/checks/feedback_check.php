<?php

namespace local_qualiscope\checks;

defined('MOODLE_INTERNAL') || die();

class feedback_check extends base_check {

    private const COVERAGE_TARGET = 0.33;

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

        $fids = array_map(function ($i) { return $i->fid; }, $instances);
        list($fidsql, $fidparams) = $DB->get_in_or_equal($fids, SQL_PARAMS_NAMED, 'fid');

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

        list($fidsql2, $fidparams2) = $DB->get_in_or_equal($fidswithitems, SQL_PARAMS_NAMED, 'fid');

        $responsecount = $DB->get_field_sql(
            "SELECT COUNT(DISTINCT fc.id)
             FROM {feedback_completed} fc
             WHERE fc.feedback $fidsql2",
            $fidparams2
        );

        if ($responsecount == 0) {
            return $this->build_result(
                'verify',
                get_string('check_feedback_no_responses', 'local_qualiscope', ['count' => $itemcount, 'activities' => count($fidswithitems)]),
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