<?php

namespace local_qualiscope\checks;

defined('MOODLE_INTERNAL') || die();

/**
 * Checks whether activity completion tracking is enabled and used in the course.
 *
 * @package local_qualiscope
 */
class completion_check extends base_check {

    /**
     * Executes the completion tracking check on the course.
     *
     * @param int $courseid The course id to audit.
     * @param object $check The check record.
     * @return array Result with status, detail and optional ratio keys.
     */
    public function execute(int $courseid, object $check): array {
        global $DB, $CFG;

        require_once($CFG->libdir . '/completionlib.php');

        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            return $this->build_result('missing', get_string('check_course_notfound', 'local_qualiscope'));
        }

        if (!$course->enablecompletion) {
            return $this->build_result('missing', get_string('check_completion_disabled', 'local_qualiscope'), 0.0);
        }

        $count = (int) $DB->get_field_sql(
            "SELECT COUNT(*)
             FROM {course_modules}
             WHERE course = :courseid AND completion > 0",
            ['courseid' => $courseid]
        );

        if ($count >= 6) {
            return $this->build_result('detected', get_string('check_completion_count', 'local_qualiscope', $count), 1.0);
        } else if ($count >= 2) {
            $ratio = round(0.5 + (($count - 2) / 8), 2);
            return $this->build_result('detected', get_string('check_completion_count', 'local_qualiscope', $count), $ratio);
        } else if ($count > 0) {
            return $this->build_result('verify', get_string('check_completion_count', 'local_qualiscope', $count), 0.4);
        }

        return $this->build_result('verify', get_string('check_completion_enabled_none', 'local_qualiscope'), 0.2);
    }
}
