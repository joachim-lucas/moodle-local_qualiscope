<?php

require_once('../../config.php');
require_once($CFG->dirroot . '/local/qualiscope/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$campaignid = optional_param('campaignid', 0, PARAM_INT);
$indicatorid = optional_param('indicatorid', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/qualiscope/actions.php', [
    'courseid' => $courseid,
    'campaignid' => $campaignid,
    'indicatorid' => $indicatorid,
]));

require_login($courseid);
$context = context_course::instance($courseid);
require_capability('local/qualiscope:manageactions', $context);

$PAGE->set_title(get_string('action_title', 'local_qualiscope'));
$PAGE->set_heading(get_string('action_title', 'local_qualiscope'));
$PAGE->set_context($context);
$PAGE->requires->js_call_amd('local_qualiscope/forms', 'init');

$output = $PAGE->get_renderer('local_qualiscope');

$where = ['courseid' => $courseid];
if ($campaignid) {
    $where['campaign_id'] = $campaignid;
}

$actions = $DB->get_records('local_qualiscope_actions', $where, 'duedate ASC');

$prioritylabels = [
    'high' => get_string('action_priority_high', 'local_qualiscope'),
    'medium' => get_string('action_priority_medium', 'local_qualiscope'),
    'low' => get_string('action_priority_low', 'local_qualiscope'),
];

$statuslabels = [
    'todo' => get_string('action_status_todo', 'local_qualiscope'),
    'inprogress' => get_string('action_status_inprogress', 'local_qualiscope'),
    'proved' => get_string('action_status_proved', 'local_qualiscope'),
    'verified' => get_string('action_status_verified', 'local_qualiscope'),
    'closed' => get_string('action_status_closed', 'local_qualiscope'),
];

$actionsdata = [];
foreach ($actions as $action) {
    $actionsdata[] = [
        'id' => $action->id,
        'title' => $action->title,
        'responsible' => $action->responsible,
        'duedateformatted' => $action->duedate ? userdate($action->duedate) : '—',
        'priority' => $action->priority,
        'prioritylabel' => $prioritylabels[$action->priority] ?? $action->priority,
        'priorityhigh' => $action->priority === 'high',
        'prioritymedium' => $action->priority === 'medium',
        'prioritylow' => $action->priority === 'low',
        'status' => $action->status,
        'statuslabel' => $statuslabels[$action->status] ?? $action->status,
        'statustodo' => $action->status === 'todo',
        'statusinprogress' => $action->status === 'inprogress',
        'statusproved' => $action->status === 'proved',
        'statusverified' => $action->status === 'verified',
        'statusclosed' => $action->status === 'closed',
        'editurl' => new moodle_url('/local/qualiscope/edit_action.php', ['id' => $action->id]),
    ];
}

echo $output->header();
echo $output->render_action_form([
    'actions' => $actionsdata,
    'sesskey' => sesskey(),
    'resultid' => 0,
    'campaignid' => $campaignid,
    'courseid' => $courseid,
    'saveactionurl' => new moodle_url('/local/qualiscope/save_action.php'),
]);
echo $output->footer();
