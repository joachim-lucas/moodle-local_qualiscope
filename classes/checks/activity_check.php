<?php

namespace local_qualiscope\checks;

defined('MOODLE_INTERNAL') || die();

class activity_check extends base_check {

    public function execute(int $courseid, object $check): array {
        global $DB;

        $count = (int) $DB->get_field_sql(
            "SELECT COUNT(*)
             FROM {course_modules} cm
             JOIN {modules} m ON m.id = cm.module
             WHERE cm.course = :courseid AND cm.visible = 1
                 AND m.name IN ('assign','quiz','workshop','choice','data','lesson','lti','survey','bigbluebuttonbn','jitsi','h5pactivity','forum','glossary','feedback')",
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
