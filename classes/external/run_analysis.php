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
 * QualiScope Run Analysis class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_qualiscope\external;


/**
 * External service to run the audit analysis of a course.
 *
 * @package local_qualiscope
 */
class run_analysis extends \external_api {
    /**
     * Declares the function parameters.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters() {
        return new \external_function_parameters([
            'courseid' => new \external_value(PARAM_INT, 'Course ID'),
            'campaignid' => new \external_value(PARAM_INT, 'Campaign ID', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Declares the function return values.
     *
     * @return \external_single_structure
     */
    public static function execute_returns() {
        return new \external_single_structure([
            'success' => new \external_value(PARAM_BOOL, 'Success status'),
            'message' => new \external_value(PARAM_RAW, 'Status message'),
        ]);
    }

    /**
     * Runs the course analysis and optionally persists the results to a campaign.
     *
     * @param int $courseid The course id.
     * @param int $campaignid Optional campaign id to save results under.
     * @return array Success status and message.
     */
    public static function execute(int $courseid, int $campaignid = 0): array {
        global $USER;

        $context = \context_system::instance();
        require_capability('local/qualiscope:managecampaigns', $context);

        $analyser = new \local_qualiscope\analyser\course_analyser($courseid, $campaignid);
        $results = $analyser->run();

        if ($campaignid) {
            $analyser->save_results($campaignid);
        }

        return [
            'success' => true,
            'message' => get_string('analysis_complete', 'local_qualiscope'),
        ];
    }
}
