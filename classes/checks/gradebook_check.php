<?php

namespace local_qualiscope\checks;

defined('MOODLE_INTERNAL') || die();

class gradebook_check extends base_check {

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
