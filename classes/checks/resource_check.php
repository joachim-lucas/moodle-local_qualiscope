<?php

namespace local_qualiscope\checks;

defined('MOODLE_INTERNAL') || die();

/**
 * Checks for the presence of learning resources and documents in the course.
 *
 * @package local_qualiscope
 */
class resource_check extends base_check {

    /**
     * Executes the resources check on the course.
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
                 AND m.name IN ('url','folder','resource','file','page','imscp','book','wiki','h5pactivity')",
            ['courseid' => $courseid]
        );

        if ($count >= 6) {
            return $this->build_result('detected', get_string('check_resource_found', 'local_qualiscope', $count), 1.0);
        } else if ($count >= 3) {
            $ratio = round(0.5 + (($count - 3) / 6), 2);
            return $this->build_result('detected', get_string('check_resource_found', 'local_qualiscope', $count), $ratio);
        } else if ($count > 0) {
            return $this->build_result('verify', get_string('check_resource_found', 'local_qualiscope', $count), 0.4);
        }

        return $this->build_result('missing', get_string('check_resource_none', 'local_qualiscope'), 0.0);
    }
}
