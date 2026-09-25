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
 * QualiScope Accessibility Check class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_qualiscope\checks;

use local_qualiscope\analyser\accessibility_analyser;


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
