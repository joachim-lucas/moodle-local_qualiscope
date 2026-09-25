<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * QualiScope Wcag Media Check class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_qualiscope\checks;

use local_qualiscope\analyser\accessibility_analyser;


/**
 * WCAG / RGAA Check: Detection of subtitles and captions (.vtt, tracks) for embedded videos and media.
 *
 * @package local_qualiscope
 */
class wcag_media_check extends base_check {
    /**
     * Executes the video subtitles accessibility check on the course.
     *
     * @param int $courseid The course id to audit.
     * @param object $check The check record.
     * @return array Result with status, detail and ratio keys.
     */
    public function execute(int $courseid, object $check): array {
        $contents = accessibility_analyser::get_course_rich_text_contents($courseid);
        $result = accessibility_analyser::analyse_videos($courseid, $contents);

        return $this->build_result($result['status'], $result['summary'], $result['ratio']);
    }
}
