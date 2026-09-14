<?php

namespace local_qualiscope\checks;

use local_qualiscope\analyser\accessibility_analyser;

defined('MOODLE_INTERNAL') || die();

/**
 * WCAG / RGAA Check: Detection of subtitles and captions (.vtt, tracks) for embedded videos and media.
 */
class wcag_media_check extends base_check {

    public function execute(int $courseid, object $check): array {
        $contents = accessibility_analyser::get_course_rich_text_contents($courseid);
        $result = accessibility_analyser::analyse_videos($courseid, $contents);

        return $this->build_result($result['status'], $result['summary'], $result['ratio']);
    }
}
