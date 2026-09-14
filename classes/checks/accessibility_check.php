<?php

namespace local_qualiscope\checks;

use local_qualiscope\analyser\accessibility_analyser;

defined('MOODLE_INTERNAL') || die();

/**
 * Comprehensive automated WCAG 2.1 / RGAA accessibility check.
 * Evaluates: image alt attributes, video subtitles, heading hierarchy, and rich-text color contrast.
 */
class accessibility_check extends base_check {

    public function execute(int $courseid, object $check): array {
        $analysis = accessibility_analyser::analyse_all($courseid);

        return $this->build_result(
            $analysis['status'],
            $analysis['summary'],
            $analysis['ratio']
        );
    }
}
