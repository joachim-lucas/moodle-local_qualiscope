<?php

namespace local_qualiscope\checks;

use local_qualiscope\analyser\accessibility_analyser;

defined('MOODLE_INTERNAL') || die();

/**
 * WCAG / RGAA Check: HTML heading hierarchy (<h1> to <h6>) without skipping levels.
 *
 * @package local_qualiscope
 */
class wcag_headings_check extends base_check {

    /**
     * Executes the heading structure accessibility check on the course.
     *
     * @param int $courseid The course id to audit.
     * @param object $check The check record.
     * @return array Result with status, detail and ratio keys.
     */
    public function execute(int $courseid, object $check): array {
        $contents = accessibility_analyser::get_course_rich_text_contents($courseid);
        $result = accessibility_analyser::analyse_headings($contents);

        return $this->build_result($result['status'], $result['summary'], $result['ratio']);
    }
}
