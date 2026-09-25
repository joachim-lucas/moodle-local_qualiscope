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
 * QualiScope Create Action class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_qualiscope\external;


/**
 * External service to create a corrective action (CAPA).
 *
 * @package local_qualiscope
 */
class create_action extends \external_api {
    /**
     * Declares the function parameters.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters() {
        return new \external_function_parameters([
            'resultid' => new \external_value(PARAM_INT, 'Result ID', VALUE_DEFAULT, 0),
            'campaignid' => new \external_value(PARAM_INT, 'Campaign ID'),
            'courseid' => new \external_value(PARAM_INT, 'Course ID'),
            'title' => new \external_value(PARAM_TEXT, 'Action title'),
            'responsible' => new \external_value(PARAM_RAW, 'Responsible person', VALUE_DEFAULT, ''),
            'duedate' => new \external_value(PARAM_RAW, 'Due date (timestamp)', VALUE_DEFAULT, '0'),
            'priority' => new \external_value(PARAM_ALPHA, 'Priority', VALUE_DEFAULT, 'medium'),
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
            'actionid' => new \external_value(PARAM_INT, 'Action ID'),
        ]);
    }

    /**
     * Creates the corrective action record.
     *
     * @param int $campaignid The campaign id.
     * @param int $courseid The course id.
     * @param string $title Title of the action.
     * @param string $responsible Responsible person (free text).
     * @param string $duedate Due date as timestamp string.
     * @param string $priority Priority (low, medium, high).
     * @param int $resultid Optional linked analysis result id.
     * @return array Success status and created action id.
     */
    public static function execute(
        int $campaignid,
        int $courseid,
        string $title,
        string $responsible = '',
        string $duedate = '0',
        string $priority = 'medium',
        int $resultid = 0
    ): array {
        global $DB, $USER;

        $context = \context_course::instance($courseid);
        require_capability('local/qualiscope:manageactions', $context);

        $action = new \stdClass();
        $action->result_id = $resultid;
        $action->campaign_id = $campaignid;
        $action->courseid = $courseid;
        $action->title = $title;
        $action->responsible = $responsible;
        $action->duedate = (int) $duedate;
        $action->priority = $priority;
        $action->status = 'todo';
        $action->userid = $USER->id;
        $action->timecreated = time();
        $action->timemodified = time();

        $actionid = $DB->insert_record('local_qualiscope_actions', $action);

        return [
            'success' => true,
            'actionid' => $actionid,
        ];
    }
}
