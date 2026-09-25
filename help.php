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
 * QualiScope Help page.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once('../../config.php');
require_once($CFG->dirroot . '/local/qualiscope/lib.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$referentialid = optional_param('referentialid', 0, PARAM_INT);
$indicatorid = optional_param('indicatorid', 0, PARAM_INT);

if ($courseid) {
    require_login($courseid);
    $context = context_course::instance($courseid);
} else {
    require_login();
    $context = context_system::instance();
}
require_capability('local/qualiscope:viewaudit', $context);

$referentials = $DB->get_records('local_qualiscope_referentials', ['active' => 1], 'id ASC');
if (!$referentialid) {
    $referentialid = \local_qualiscope\analyser\course_analyser::get_default_referential_id() ?? 0;
    if (!$referentialid && !empty($referentials)) {
        $firstref = reset($referentials);
        $referentialid = $firstref->id;
    }
}

$pageparams = ['referentialid' => $referentialid];
if ($courseid) {
    $pageparams['courseid'] = $courseid;
}
if ($indicatorid) {
    $pageparams['indicatorid'] = $indicatorid;
}

$PAGE->set_url(new moodle_url('/local/qualiscope/help.php', $pageparams));
$PAGE->set_context($context);
$PAGE->set_title(get_string('help_page_title', 'local_qualiscope'));
$PAGE->set_heading(get_string('help_page_title', 'local_qualiscope'));

$output = $PAGE->get_renderer('local_qualiscope');

$guidedata = \local_qualiscope\help\referential_guide::get_referential_guide_data($referentialid);

$referentialsdata = [];
foreach ($referentials as $ref) {
    $referentialsdata[] = [
        'id' => $ref->id,
        'label' => local_qualiscope_localized($ref, 'name') . ' ' . $ref->version,
        'selected' => $ref->id == $referentialid,
    ];
}

$backurl = null;
if ($courseid) {
    $backurl = new moodle_url('/local/qualiscope/dashboard.php', ['courseid' => $courseid, 'referentialid' => $referentialid]);
}

$data = [
    'courseid' => $courseid,
    'referentialid' => $referentialid,
    'referential' => $guidedata['referential'],
    'criteria' => $guidedata['criteria'],
    'totalcriteria' => $guidedata['totalcriteria'],
    'referentials' => array_values($referentialsdata),
    'backurl' => $backurl ? $backurl->out(false) : '',
    'hasbackurl' => !empty($backurl),
    'indicatorid' => $indicatorid,
    'helpurl' => new moodle_url('/local/qualiscope/help.php', ['courseid' => $courseid]),
];

echo $output->header();
echo $output->render_help($data);
echo $output->footer();
