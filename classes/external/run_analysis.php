<?php

namespace local_qualiscope\external;

defined('MOODLE_INTERNAL') || die();

class run_analysis extends \external_api {

    public static function execute_parameters() {
        return new \external_function_parameters([
            'courseid' => new \external_value(PARAM_INT, 'Course ID'),
            'campaignid' => new \external_value(PARAM_INT, 'Campaign ID', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute_returns() {
        return new \external_single_structure([
            'success' => new \external_value(PARAM_BOOL, 'Success status'),
            'message' => new \external_value(PARAM_RAW, 'Status message'),
        ]);
    }

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
