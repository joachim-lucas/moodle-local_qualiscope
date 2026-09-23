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
 * QualiScope Add Evidence class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_qualiscope\external;


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
