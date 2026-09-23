<?php

namespace local_qualiscope\checks;

use local_qualiscope\analyser\accessibility_analyser;

defined('MOODLE_INTERNAL') || die();

/**
 * Comprehensive automated WCAG 2.1 / RGAA accessibility check.
 * Evaluates: image alt attributes, video subtitles, heading hierarchy, and rich-text color contrast.
 *
 * @package local_qualiscope
 */
class accessibility_check extends base_check {

    /**
     * Executes the accessibility check on the course.
     *
     * @param int $courseid The course id to audit.
     * @param object $check The check record.
     * @return array Result with status, detail and optional ratio keys.
     */
    public function execute(int $courseid, object $check): array {
        $analysis = accessibility_analyser::analyse_all($courseid);

        return $this->build_result(
            $analysis['status'],
            $analysis['summary'],
            $analysis['ratio']
        );
    }
}
