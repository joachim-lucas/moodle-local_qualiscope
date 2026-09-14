<?php

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

$referentials = $DB->get_records('local_qualiopi_referentials', ['active' => 1], 'id ASC');
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
        'label' => $ref->name . ' ' . $ref->version,
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
