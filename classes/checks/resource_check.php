<?php

namespace local_qualiscope\checks;

defined('MOODLE_INTERNAL') || die();

class resource_check extends base_check {

    public function execute(int $courseid, object $check): array {
        global $DB;

        $count = $DB->get_field_sql(
            "SELECT COUNT(*)
             FROM {course_modules} cm
             JOIN {modules} m ON m.id = cm.module
             WHERE cm.course = :courseid AND cm.visible = 1
                 AND m.name IN ('url','folder','file','page','imscp','book','wiki','h5pactivity')",
            ['courseid' => $courseid]
        );

        if ($count > 0) {
            return $this->build_result('detected', get_string('check_resource_found', 'local_qualiscope', $count));
        }

        return $this->build_result('missing', get_string('check_resource_none', 'local_qualiscope'));
    }
}
