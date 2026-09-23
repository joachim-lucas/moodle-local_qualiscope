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
 * QualiScope Indicator page.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once('../../config.php');
require_once($CFG->dirroot . '/local/qualiscope/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$indicatorid = required_param('indicatorid', PARAM_INT);
$campaignid = optional_param('campaignid', 0, PARAM_INT);
$referentialid = optional_param('referentialid', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/qualiscope/indicator.php', [
    'courseid' => $courseid,
    'indicatorid' => $indicatorid,
    'campaignid' => $campaignid,
    'referentialid' => $referentialid,
]));

require_login($courseid);
$context = context_course::instance($courseid);
require_capability('local/qualiscope:viewaudit', $context);

$PAGE->set_title(get_string('indicator_title', 'local_qualiscope'));
$PAGE->set_heading(get_string('indicator_title', 'local_qualiscope'));
$PAGE->set_context($context);
$PAGE->requires->js_call_amd('local_qualiscope/forms', 'init');

$output = $PAGE->get_renderer('local_qualiscope');

$indicator = $DB->get_record('local_qualiscope_indicators', ['id' => $indicatorid], '*', MUST_EXIST);

$analyser = new \local_qualiscope\analyser\course_analyser($courseid, $campaignid, $referentialid ?: null);
$results = $analyser->run();

$indicatorresults = array_filter($results, function ($r) use ($indicatorid) {
    return $r['indicator']->id == $indicatorid;
});

$checkresults = [];
foreach ($indicatorresults as $r) {
    $checkresults[] = [
        'checkname' => $r['check']->name,
        'detail' => $r['detail'] ?? '',
        'status' => $r['status'],
        'statuslabel' => \local_qualiscope\analyser\indicator_analyser::get_status_label($r['status']),
        'statusdetected' => $r['status'] === 'detected',
        'statusverify' => $r['status'] === 'verify',
        'statusmissing' => $r['status'] === 'missing',
        'statusna' => $r['status'] === 'na',
    ];
}

$resultid = 0;
foreach ($indicatorresults as $r) {
    $resultid = $analyser->save_result($r);
    break;
}

$moodleevidences = \local_qualiscope\analyser\evidence_analyser::get_moodle_evidences($courseid, $indicatorid);
$externalevidences = $resultid ? \local_qualiscope\analyser\evidence_analyser::get_external_evidences($resultid) : [];

foreach ($moodleevidences as &$ev) {
    $ev['source'] = $ev['source'] ?? '';
}
foreach ($externalevidences as &$ev) {
    $ev->timecreated = userdate($ev->timecreated);
    if (!empty($ev->filename)) {
        $ev->fileurl = moodle_url::make_pluginfile_url(
            $context->id,
            'local_qualiscope',
            'evidence',
            $ev->result_id,
            $ev->filepath,
            $ev->filename
        );
    }
}

echo $output->header();
echo $output->render_indicator_detail([
    'indicator' => $indicator,
    'checkresults' => $checkresults,
    'moodleevidences' => array_values($moodleevidences),
    'externalevidences' => array_values($externalevidences),
    'resultid' => $resultid,
    'sesskey' => sesskey(),
    'backurl' => new moodle_url('/local/qualiscope/dashboard.php', ['courseid' => $courseid, 'referentialid' => $referentialid]),
    'helpurl' => (new moodle_url('/local/qualiscope/help.php', [
        'courseid' => $courseid,
        'referentialid' => $referentialid,
        'indicatorid' => $indicatorid,
    ]))->out(false) . '#indicator-' . $indicatorid,
    'uploadurl' => new moodle_url('/local/qualiscope/upload_evidence.php'),
    'actionurl' => new moodle_url('/local/qualiscope/actions.php', [
        'courseid' => $courseid,
        'campaignid' => $campaignid,
        'referentialid' => $referentialid,
        'indicatorid' => $indicatorid,
    ]),
]);
echo $output->footer();
