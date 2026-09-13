<?php

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

$result = $DB->get_record('local_qualiopi_results', ['id' => $resultid], '*', MUST_EXIST);

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

$DB->insert_record('local_qualiopi_evidences', $record);

$result->evidence_count = $DB->count_records('local_qualiopi_evidences', ['result_id' => $resultid]);
$result->timemodified = time();
$DB->update_record('local_qualiopi_results', $result);

$referentialid = 0;
$check = $DB->get_record('local_qualiopi_checks', ['id' => $result->check_id]);
if ($check) {
    $indicator = $DB->get_record('local_qualiopi_indicators', ['id' => $check->indicator_id]);
    if ($indicator) {
        $criterion = $DB->get_record('local_qualiopi_criteria', ['id' => $indicator->criterion_id]);
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
