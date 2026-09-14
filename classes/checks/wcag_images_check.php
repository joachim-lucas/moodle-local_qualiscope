<?php

namespace local_qualiscope\checks;

use local_qualiscope\analyser\accessibility_analyser;

defined('MOODLE_INTERNAL') || die();

/**
 * WCAG / RGAA Check: Presence and pertinence of alt attributes on images.
 */
class wcag_images_check extends base_check {

    public function execute(int $courseid, object $check): array {
        $contents = accessibility_analyser::get_course_rich_text_contents($courseid);
        $result = accessibility_analyser::analyse_images($contents);

        return $this->build_result($result['status'], $result['summary'], $result['ratio']);
    }
}
