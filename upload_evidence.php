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

$resultid = required_param('resultid', PARAM_INT);

$PAGE->set_url(new moodle_url('/local/qualiscope/upload_evidence.php', ['resultid' => $resultid]));

require_login();

$result = $DB->get_record('local_qualiscope_results', ['id' => $resultid], '*', MUST_EXIST);
$course = get_course($result->courseid);

require_login($course);

$context = context_course::instance($course->id);
require_capability('local/qualiscope:editproofs', $context);

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

$returnurl = new moodle_url('/local/qualiscope/indicator.php', [
    'courseid' => $result->courseid,
    'indicatorid' => $result->indicator_id,
    'campaignid' => $result->campaign_id,
    'referentialid' => $referentialid,
]);

$PAGE->set_context($context);
$PAGE->set_title(get_string('upload_evidence', 'local_qualiscope'));
$PAGE->set_heading(get_string('upload_evidence', 'local_qualiscope'));

$form = new \local_qualiscope\form\evidence_upload($PAGE->url, [
    'resultid' => $resultid,
    'maxbytes' => $course->maxbytes,
]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    \local_qualiscope\evidence::create(
        (int) $resultid,
        $data,
        (int) $USER->id,
        $context,
        (int) $course->maxbytes
    );

    $result->evidence_count = $DB->count_records('local_qualiscope_evidences', ['result_id' => $resultid]);
    $result->timemodified = time();
    $DB->update_record('local_qualiscope_results', $result);

    redirect($returnurl);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('upload_evidence', 'local_qualiscope'));
$form->display();
echo $OUTPUT->footer();
