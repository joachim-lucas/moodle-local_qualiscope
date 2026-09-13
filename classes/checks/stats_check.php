<?php

namespace local_qualiscope\checks;

defined('MOODLE_INTERNAL') || die();

class stats_check extends base_check {

    public function execute(int $courseid, object $check): array {
        global $DB;

        // Calculate key metrics: enrolled, completion rate, grade average
        $context = \context_course::instance($courseid);
        $enrolled = count_enrolled_users($context);

        if ($enrolled === 0) {
            return $this->build_result('verify', get_string('check_stats_no_enrolments', 'local_qualiscope'), 0.2);
        }

        // Completion count
        $completedcount = $DB->get_field_sql(
            "SELECT COUNT(DISTINCT userid)
             FROM {course_completions}
             WHERE course = :courseid AND timecompleted > 0",
            ['courseid' => $courseid]
        );

        $completionrate = $enrolled > 0 ? round(($completedcount / $enrolled) * 100, 1) : 0;

        // Grades count
        $gradescount = $DB->get_field_sql(
            "SELECT COUNT(gg.id)
             FROM {grade_grades} gg
             JOIN {grade_items} gi ON gi.id = gg.itemid
             WHERE gi.courseid = :courseid AND gg.finalgrade IS NOT NULL",
            ['courseid' => $courseid]
        );

        $detail = get_string('check_stats_calculated', 'local_qualiscope', [
            'enrolled' => $enrolled,
            'completed' => $completedcount,
            'rate' => $completionrate,
            'grades' => $gradescount,
        ]);

        return $this->build_result('detected', $detail, 1.0);
    }
}
