<?php

namespace local_qualiscope\checks;

defined('MOODLE_INTERNAL') || die();

class gradebook_check extends base_check {

    public function execute(int $courseid, object $check): array {
        global $DB;

        $count = $DB->get_field_sql(
            "SELECT COUNT(*)
             FROM {grade_items}
             WHERE courseid = :courseid AND itemtype != 'course'",
            ['courseid' => $courseid]
        );

        if ($count > 0) {
            return $this->build_result('detected', get_string('check_gradebook_found', 'local_qualiscope', $count));
        }

        return $this->build_result('missing', get_string('check_gradebook_none', 'local_qualiscope'));
    }
}
