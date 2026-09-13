<?php

namespace local_qualiscope\checks;

defined('MOODLE_INTERNAL') || die();

class completion_check extends base_check {

    public function execute(int $courseid, object $check): array {
        global $DB, $CFG;

        require_once($CFG->libdir . '/completionlib.php');

        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            return $this->build_result('missing', get_string('check_course_notfound', 'local_qualiscope'));
        }

        if (!$course->enablecompletion) {
            return $this->build_result('missing', get_string('check_completion_disabled', 'local_qualiscope'));
        }

        $count = $DB->get_field_sql(
            "SELECT COUNT(*)
             FROM {course_modules}
             WHERE course = :courseid AND completion > 0",
            ['courseid' => $courseid]
        );

        if ($count > 0) {
            return $this->build_result('detected', get_string('check_completion_count', 'local_qualiscope', $count));
        }

        return $this->build_result('verify', get_string('check_completion_enabled_none', 'local_qualiscope'));
    }
}
