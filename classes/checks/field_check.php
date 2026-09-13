<?php

namespace local_qualiscope\checks;

defined('MOODLE_INTERNAL') || die();

class field_check extends base_check {

    public function execute(int $courseid, object $check): array {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            return $this->build_result('missing', get_string('check_course_notfound', 'local_qualiscope'));
        }

        $checks = [];

        if (!empty($course->summary)) {
            $checks[] = get_string('check_field_summary_found', 'local_qualiscope');
        }

        $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');
        $sectionswithcontent = 0;
        foreach ($sections as $section) {
            if (!empty($section->summary) || $section->sequence) {
                $sectionswithcontent++;
            }
        }

        if ($sectionswithcontent > 1) {
            $checks[] = get_string('check_field_sections_found', 'local_qualiscope', $sectionswithcontent);
        }

        if (!empty($course->startdate) && !empty($course->enddate)) {
            $checks[] = get_string('check_field_dates_found', 'local_qualiscope');
        } elseif (empty($course->startdate)) {
            return $this->build_result('verify', get_string('check_field_dates_missing', 'local_qualiscope'));
        }

        if (count($checks) >= 2) {
            return $this->build_result('detected', implode(' | ', $checks));
        }

        return $this->build_result('verify', get_string('check_field_partial', 'local_qualiscope'));
    }
}
