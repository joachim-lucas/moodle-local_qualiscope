<?php

namespace local_qualiscope\external;

defined('MOODLE_INTERNAL') || die();

/**
 * External service to attach a manual evidence to an analysis result.
 *
 * @package local_qualiscope
 */
class add_evidence extends \external_api {

    /**
     * Declares the function parameters.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters() {
        return new \external_function_parameters([
            'resultid' => new \external_value(PARAM_INT, 'Result ID'),
            'title' => new \external_value(PARAM_TEXT, 'Evidence title'),
            'annotation' => new \external_value(PARAM_RAW, 'Annotation', VALUE_DEFAULT, ''),
            'externalurl' => new \external_value(PARAM_URL, 'External URL', VALUE_DEFAULT, ''),
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
            'evidenceid' => new \external_value(PARAM_INT, 'Evidence ID'),
        ]);
    }

    /**
     * Stores the evidence for the result and refreshes its evidence count.
     *
     * @param int $resultid The analysis result id.
     * @param string $title Title of the evidence.
     * @param string $annotation Optional annotation.
     * @param string $externalurl Optional external URL.
     * @return array Success status and created evidence id.
     */
    public static function execute(int $resultid, string $title, string $annotation = '', string $externalurl = ''): array {
        global $DB, $USER;

        $result = $DB->get_record('local_qualiscope_results', ['id' => $resultid], '*', MUST_EXIST);
        $context = \context_course::instance($result->courseid);
        require_capability('local/qualiscope:editproofs', $context);

        $record = new \stdClass();
        $record->result_id = $resultid;
        $record->type = 'external';
        $record->title = $title;
        $record->annotation = $annotation;
        $record->externalurl = $externalurl;
        $record->userid = $USER->id;
        $record->timecreated = time();
        $record->timemodified = time();

        $evidenceid = $DB->insert_record('local_qualiscope_evidences', $record);

        $result->evidence_count = $DB->count_records('local_qualiscope_evidences', ['result_id' => $resultid]);
        $result->timemodified = time();
        $DB->update_record('local_qualiscope_results', $result);

        return [
            'success' => true,
            'evidenceid' => $evidenceid,
        ];
    }
}
