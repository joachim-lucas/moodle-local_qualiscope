<?php

namespace local_qualiscope\checks;

defined('MOODLE_INTERNAL') || die();

class competency_check extends base_check {

    public function execute(int $courseid, object $check): array {
        global $DB;

        // Check course competencies
        $coursecompetencies = $DB->get_records('competency_coursecomp', ['courseid' => $courseid]);
        $count = count($coursecompetencies);

        if ($count > 0) {
            // Check if any modules have linked competencies
            $modulecompetencies = $DB->get_field_sql(
                "SELECT COUNT(*) FROM {competency_modulecomp} mc
                 JOIN {course_modules} cm ON cm.id = mc.cmid
                 WHERE cm.course = :courseid",
                ['courseid' => $courseid]
            );

            $detail = get_string('check_competency_found', 'local_qualiscope', [
                'count' => $count,
                'linked' => (int) $modulecompetencies,
            ]);

            return $this->build_result('detected', $detail, 1.0);
        }

        // Check if competencies exist in objectives/summary or outcomes
        $course = $DB->get_record('course', ['id' => $courseid]);
        if ($course && (stripos($course->summary, 'compétence') !== false || stripos($course->summary, 'objectif') !== false)) {
            return $this->build_result('verify', get_string('check_competency_in_summary', 'local_qualiscope'), 0.5);
        }

        return $this->build_result('verify', get_string('check_competency_none', 'local_qualiscope'), 0.0);
    }
}
