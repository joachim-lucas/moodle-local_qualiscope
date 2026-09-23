<?php

namespace local_qualiscope\external;

defined('MOODLE_INTERNAL') || die();

class create_action extends \external_api {

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

    public static function execute_returns() {
        return new \external_single_structure([
            'success' => new \external_value(PARAM_BOOL, 'Success status'),
            'actionid' => new \external_value(PARAM_INT, 'Action ID'),
        ]);
    }

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
