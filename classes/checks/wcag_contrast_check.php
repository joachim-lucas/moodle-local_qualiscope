<?php

namespace local_qualiscope\checks;

use local_qualiscope\analyser\accessibility_analyser;

defined('MOODLE_INTERNAL') || die();

/**
 * WCAG / RGAA Check: Color contrast readability in rich-text content (WCAG AA >= 4.5:1).
 *
 * @package local_qualiscope
 */
class wcag_contrast_check extends base_check {

    /**
     * Executes the color contrast accessibility check on the course.
     *
     * @param int $courseid The course id to audit.
     * @param object $check The check record.
     * @return array Result with status, detail and ratio keys.
     */
    public function execute(int $courseid, object $check): array {
        $contents = accessibility_analyser::get_course_rich_text_contents($courseid);
        $result = accessibility_analyser::analyse_contrast($contents);

        return $this->build_result($result['status'], $result['summary'], $result['ratio']);
    }
}
