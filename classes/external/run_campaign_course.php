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
 * QualiScope Run Campaign Course class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_qualiscope\external;


/**
 * External service analysing a single course of a campaign and reporting the progress.
 *
 * This is the service behind the real-time progress page of a campaign run.
 *
 * @package local_qualiscope
 */
class run_campaign_course extends \core_external\external_api {
    /**
     * Declares the function parameters.
     *
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters() {
        return new \core_external\external_function_parameters([
            'campaignid' => new \core_external\external_value(PARAM_INT, 'Campaign ID'),
            'courseid' => new \core_external\external_value(PARAM_INT, 'Course ID'),
            'finish' => new \core_external\external_value(
                PARAM_INT,
                'Whether this is the last course of the campaign',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Declares the function return values.
     *
     * @return \core_external\external_single_structure
     */
    public static function execute_returns() {
        return new \core_external\external_single_structure([
            'success' => new \core_external\external_value(PARAM_BOOL, 'Whether the course was analysed'),
            'status' => new \core_external\external_value(PARAM_ALPHA, 'Outcome: ok or licence_required'),
            'courseid' => new \core_external\external_value(PARAM_INT, 'Analysed course id'),
            'finished' => new \core_external\external_value(PARAM_BOOL, 'Whether the campaign was closed'),
        ]);
    }

    /**
     * Analyses one course of a campaign, closing the campaign when it is the last one.
     *
     * @param int $campaignid The campaign id.
     * @param int $courseid The course id.
     * @param int $finish Whether this is the last course of the campaign.
     * @return array Outcome of the analysis.
     */
    public static function execute(int $campaignid, int $courseid, int $finish = 0): array {
        global $DB;

        $campaign = $DB->get_record('local_qualiscope_campaigns', ['id' => $campaignid], '*', MUST_EXIST);

        // A campaign spans every course of its scope, so authorisation stays at system level.
        $context = \context_system::instance();

        self::validate_parameters(self::execute_parameters(), [
            'campaignid' => $campaignid,
            'courseid' => $courseid,
            'finish' => $finish,
        ]);
        self::validate_context($context);

        require_capability('local/qualiscope:managecampaigns', $context);

        if (!\local_qualiscope\quota::can_audit($courseid)) {
            if ($finish) {
                $campaign->timecompleted = time();
                $campaign->timemodified = time();
                $DB->update_record('local_qualiscope_campaigns', $campaign);
            }

            return [
                'success' => false,
                'status' => 'licence_required',
                'courseid' => $courseid,
                'finished' => (bool) $finish,
            ];
        }

        $analyser = new \local_qualiscope\analyser\course_analyser($courseid, $campaignid, (int) $campaign->referential_id);
        $analyser->run();
        $analyser->save_results($campaignid);
        \local_qualiscope\quota::record($courseid);

        if ($finish) {
            $campaign->timecompleted = time();
            $campaign->timemodified = time();
            $DB->update_record('local_qualiscope_campaigns', $campaign);
        }

        return [
            'success' => true,
            'status' => 'ok',
            'courseid' => $courseid,
            'finished' => (bool) $finish,
        ];
    }
}
