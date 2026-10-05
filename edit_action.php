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
 * QualiScope Edit Action page.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once('../../config.php');
require_once($CFG->dirroot . '/local/qualiscope/lib.php');

$id = required_param('id', PARAM_INT);
$return = optional_param('return', '', PARAM_ALPHA);

$action = $DB->get_record('local_qualiscope_actions', ['id' => $id], '*', MUST_EXIST);

require_login($action->courseid);
$context = context_course::instance($action->courseid);
require_capability('local/qualiscope:manageactions', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/qualiscope/edit_action.php', ['id' => $id, 'return' => $return]));
$PAGE->set_title(get_string('action_title', 'local_qualiscope'));
$PAGE->set_heading(get_string('action_title', 'local_qualiscope'));

$output = $PAGE->get_renderer('local_qualiscope');

if (data_submitted() && confirm_sesskey()) {
    $action->title = required_param('title', PARAM_TEXT);
    $action->responsible = optional_param('responsible', '', PARAM_TEXT);
    $action->duedate = \local_qualiscope\action_duedate::from_input(optional_param('duedate', '', PARAM_RAW));
    $action->priority = optional_param('priority', 'medium', PARAM_ALPHA);
    $action->status = optional_param('status', $action->status, PARAM_ALPHA);
    $action->timemodified = time();

    if ($action->status === 'closed' && !$action->timeclosed) {
        $action->timeclosed = time();
    }

    $DB->update_record('local_qualiscope_actions', $action);

    if ($return === 'campaign' && $action->campaign_id) {
        redirect(new moodle_url('/local/qualiscope/view_campaign.php', ['id' => $action->campaign_id]));
    }

    redirect(new moodle_url('/local/qualiscope/actions.php', [
        'courseid' => $action->courseid,
        'campaignid' => $action->campaign_id,
    ]));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('action_title', 'local_qualiscope'));

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

$priorities = [];
foreach ($prioritylabels as $value => $label) {
    $priorities[] = [
        'value' => $value,
        'label' => $label,
        'selected' => $action->priority === $value,
    ];
}

$statuses = [];
foreach ($statuslabels as $value => $label) {
    $statuses[] = [
        'value' => $value,
        'label' => $label,
        'selected' => $action->status === $value,
    ];
}

echo $output->render_action_edit_form([
    'sesskey' => sesskey(),
    'return' => $return,
    'title' => $action->title,
    'responsible' => $action->responsible,
    'duedate' => \local_qualiscope\action_duedate::to_input((int) $action->duedate),
    'priorities' => $priorities,
    'statuses' => $statuses,
    'title_label' => get_string('action_name', 'local_qualiscope'),
    'responsible_label' => get_string('action_responsible', 'local_qualiscope'),
    'duedate_label' => get_string('action_duedate', 'local_qualiscope'),
    'priority_label' => get_string('action_priority', 'local_qualiscope'),
    'status_label' => get_string('action_status', 'local_qualiscope'),
    'submitlabel' => get_string('action_submit', 'local_qualiscope'),
]);

echo $OUTPUT->footer();
