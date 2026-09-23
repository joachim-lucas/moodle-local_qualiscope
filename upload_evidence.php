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
 * QualiScope Upload Evidence page.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once('../../config.php');
require_once($CFG->dirroot . '/local/qualiscope/lib.php');

require_login();
require_sesskey();

$resultid = required_param('resultid', PARAM_INT);
$title = required_param('title', PARAM_TEXT);
$annotation = optional_param('annotation', '', PARAM_TEXT);
$externalurl = optional_param('externalurl', '', PARAM_URL);

$fs = get_file_storage();

$record = new stdClass();
$record->result_id = $resultid;
$record->type = 'external';
$record->title = $title;
$record->annotation = $annotation;
$record->externalurl = $externalurl;
$record->userid = $USER->id;
$record->timecreated = time();
$record->timemodified = time();

$result = $DB->get_record('local_qualiscope_results', ['id' => $resultid], '*', MUST_EXIST);

if (isset($_FILES['evidencefile']) && $_FILES['evidencefile']['error'] === UPLOAD_ERR_OK) {
    $record->type = 'external';

    $context = context_course::instance($result->courseid);
    $fileinfo = [
        'contextid' => $context->id,
        'component' => 'local_qualiscope',
        'filearea' => 'evidence',
        'itemid' => $resultid,
        'filepath' => '/',
        'filename' => $_FILES['evidencefile']['name'],
    ];

    $file = $fs->create_file_from_pathname($fileinfo, $_FILES['evidencefile']['tmp_name']);
    $record->filepath = $file->get_filepath();
    $record->filename = $file->get_filename();
}

$DB->insert_record('local_qualiscope_evidences', $record);

$result->evidence_count = $DB->count_records('local_qualiscope_evidences', ['result_id' => $resultid]);
$result->timemodified = time();
$DB->update_record('local_qualiscope_results', $result);

$referentialid = 0;
$check = $DB->get_record('local_qualiscope_checks', ['id' => $result->check_id]);
if ($check) {
    $indicator = $DB->get_record('local_qualiscope_indicators', ['id' => $check->indicator_id]);
    if ($indicator) {
        $criterion = $DB->get_record('local_qualiscope_criteria', ['id' => $indicator->criterion_id]);
        if ($criterion) {
            $referentialid = (int) $criterion->referential_id;
        }
    }
}

$redirecturl = new moodle_url('/local/qualiscope/indicator.php', [
    'courseid' => $result->courseid,
    'indicatorid' => $result->indicator_id,
    'campaignid' => $result->campaign_id,
    'referentialid' => $referentialid,
]);

redirect($redirecturl);
