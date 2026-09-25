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
 * QualiScope Dashboard page.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once('../../config.php');
require_once($CFG->dirroot . '/local/qualiscope/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$referentialid = optional_param('referentialid', 0, PARAM_INT);
$PAGE->set_url(new moodle_url('/local/qualiscope/dashboard.php', ['courseid' => $courseid]));

require_login($courseid);
$context = context_course::instance($courseid);
require_capability('local/qualiscope:viewaudit', $context);

$PAGE->set_title(get_string('dashboard_title', 'local_qualiscope'));
$PAGE->set_heading(get_string('dashboard_title', 'local_qualiscope'));
$PAGE->set_context($context);

$output = $PAGE->get_renderer('local_qualiscope');
$PAGE->requires->js_call_amd('local_qualiscope/criteria', 'init');

if (!\local_qualiscope\quota::can_audit($courseid)) {
    echo $output->header();
    echo $output->notification(get_string('licencerequired', 'local_qualiscope'), \core\output\notification::NOTIFY_ERROR);
    echo $output->footer();
    exit;
}

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

$referentials = $DB->get_records('local_qualiscope_referentials', ['active' => 1], 'id ASC');
if (!$referentialid) {
    $referentialid = \local_qualiscope\analyser\course_analyser::get_default_referential_id() ?? 0;
}
$selectedreferential = $referentialid ? $DB->get_record('local_qualiscope_referentials', ['id' => $referentialid]) : null;

$analyser = new \local_qualiscope\analyser\course_analyser($courseid, 0, $referentialid ?: null);
$analyser->run();
\local_qualiscope\quota::record($courseid);
$summary = $analyser->get_summary();
$criteria = $analyser->get_criteria_summary();

$summary['percentagegt75'] = $summary['percentage'] >= 75;
$summary['percentagebetween50and75'] = $summary['percentage'] >= 50 && $summary['percentage'] < 75;
$summary['percentagelt50'] = $summary['percentage'] < 50;

foreach ($criteria as &$entry) {
    $entry['number'] = $entry['criteria']->number;
    $entry['title'] = \local_qualiscope\helper::localized($entry['criteria'], 'title');
    $entry['shorttitle'] = core_text::strlen($entry['title']) > 60 ?
    core_text::substr($entry['title'], 0, 60) . '…' : $entry['title'];
    $entry['percentagegt75'] = $entry['percentage'] !== null && $entry['percentage'] >= 75;
    $entry['percentagebetween50and75'] = $entry['percentage'] !== null && $entry['percentage'] >= 50 && $entry['percentage'] < 75;
    $entry['percentagelt50'] = $entry['percentage'] !== null && $entry['percentage'] < 50;

    foreach ($entry['results'] as &$r) {
        $r['statuslabel'] = \local_qualiscope\analyser\indicator_analyser::get_status_label($r['status']);
        $r['statusdetected'] = $r['status'] === 'detected';
        $r['statusverify'] = $r['status'] === 'verify';
        $r['statusmissing'] = $r['status'] === 'missing';
        $r['statusna'] = $r['status'] === 'na';
        $r['checkname'] = \local_qualiscope\helper::localized($r['check'], 'name');
        $r['detail'] = $r['detail'] ?? '';
        $r['indicatorurl'] = new moodle_url('/local/qualiscope/indicator.php', [
            'courseid' => $courseid,
            'campaignid' => 0,
            'referentialid' => $referentialid,
            'indicatorid' => $r['indicator']->id,
        ]);
    }
}

$referentialsdata = [];
foreach ($referentials as $ref) {
    $referentialsdata[] = [
        'id' => $ref->id,
        'label' => \local_qualiscope\helper::localized($ref, 'name') . ' ' . $ref->version,
        'selected' => $ref->id == $referentialid,
    ];
}

$fontawesome = \core\output\icon_system::instance() instanceof \core\output\icon_system_font;
$summaryitemdefs = [
    ['key' => 'detected', 'bg' => 'bg-success-subtle', 'pix' => 'i/valid', 'emoji' => '&#10003;'],
    ['key' => 'verify', 'bg' => 'bg-warning-subtle', 'pix' => 'i/warning', 'emoji' => '&#9888;'],
    ['key' => 'missing', 'bg' => 'bg-danger-subtle', 'pix' => 'i/invalid', 'emoji' => '&#10007;'],
    ['key' => 'na', 'bg' => 'bg-secondary-subtle', 'pix' => 'i/excluded', 'emoji' => '&#8212;'],
];
$summaryitems = [];
foreach ($summaryitemdefs as $def) {
    $label = get_string('dashboard_' . $def['key'], 'local_qualiscope');
    $summaryitems[] = [
        'count' => $summary[$def['key']],
        'label' => $label,
        'bgclass' => $def['bg'],
        'use_fontawesome' => $fontawesome,
        'icon' => $fontawesome ? $output->render(new \pix_icon($def['pix'], $label, 'core')) : '',
        'emoji' => $def['emoji'],
    ];
}

echo $output->header();
echo \local_qualiscope\quota::banner($output);
echo $output->render_dashboard([
    'summary' => $summary,
    'summaryitems' => $summaryitems,
    'criteria' => array_values($criteria),
    'referentials' => array_values($referentialsdata),
    'selectedreferential' => $selectedreferential ? \local_qualiscope\helper::localized($selectedreferential, 'name') . ' ' .
        $selectedreferential->version : '',
    'referentialurl' => new moodle_url('/local/qualiscope/dashboard.php', ['courseid' => $courseid]),
    'exporturl' => new moodle_url('/local/qualiscope/export.php', [
        'courseid' => $courseid,
        'referentialid' => $referentialid,
        'format' => 'xlsx',
        'sesskey' => sesskey(),
    ]),
    'exportpdfurl' => new moodle_url('/local/qualiscope/export.php', [
        'courseid' => $courseid,
        'referentialid' => $referentialid,
        'format' => 'pdf',
        'sesskey' => sesskey(),
    ]),
    'exportzipurl' => new moodle_url('/local/qualiscope/export.php', [
        'courseid' => $courseid,
        'referentialid' => $referentialid,
        'format' => 'zip',
        'sesskey' => sesskey(),
    ]),
    'helpurl' => new moodle_url('/local/qualiscope/help.php', [
        'courseid' => $courseid,
        'referentialid' => $referentialid,
    ]),
    'coursename' => $course->fullname,
    'courseid' => $courseid,
]);
echo $output->footer();
