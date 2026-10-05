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
class create_action extends \core_external\external_api {
    /**
     * Declares the function parameters.
     *
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters() {
        return new \core_external\external_function_parameters([
            'resultid' => new \core_external\external_value(PARAM_INT, 'Result ID', VALUE_DEFAULT, 0),
            'campaignid' => new \core_external\external_value(PARAM_INT, 'Campaign ID'),
            'courseid' => new \core_external\external_value(PARAM_INT, 'Course ID'),
            'title' => new \core_external\external_value(PARAM_TEXT, 'Action title'),
            'responsible' => new \core_external\external_value(PARAM_TEXT, 'Responsible person', VALUE_DEFAULT, ''),
            'duedate' => new \core_external\external_value(PARAM_INT, 'Due date as a timestamp, 0 for none', VALUE_DEFAULT, 0),
            'priority' => new \core_external\external_value(PARAM_ALPHA, 'Priority', VALUE_DEFAULT, 'medium'),
        ]);
    }

    /**
     * Declares the function return values.
     *
     * @return \core_external\external_single_structure
     */
    public static function execute_returns() {
        return new \core_external\external_single_structure([
            'success' => new \core_external\external_value(PARAM_BOOL, 'Success status'),
            'actionid' => new \core_external\external_value(PARAM_INT, 'Action ID'),
        ]);
    }

    /**
     * Creates the corrective action record.
     *
     * @param int $resultid Optional linked analysis result id.
     * @param int $campaignid The campaign id.
     * @param int $courseid The course id.
     * @param string $title Title of the action.
     * @param string $responsible Responsible person (free text).
     * @param int $duedate Due date as a timestamp, 0 for no due date.
     * @param string $priority Priority (low, medium, high).
     * @return array Success status and created action id.
     */
    public static function execute(
        int $resultid,
        int $campaignid,
        int $courseid,
        string $title,
        string $responsible = '',
        int $duedate = 0,
        string $priority = 'medium'
    ): array {
        global $DB, $USER;

        $context = \context_course::instance($courseid);

        self::validate_parameters(self::execute_parameters(), [
            'resultid' => $resultid,
            'campaignid' => $campaignid,
            'courseid' => $courseid,
            'title' => $title,
            'responsible' => $responsible,
            'duedate' => $duedate,
            'priority' => $priority,
        ]);
        self::validate_context($context);

        require_capability('local/qualiscope:manageactions', $context);

        $action = new \stdClass();
        $action->result_id = $resultid;
        $action->campaign_id = $campaignid;
        $action->courseid = $courseid;
        $action->title = $title;
        $action->responsible = $responsible;
        $action->duedate = $duedate;
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
