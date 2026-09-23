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

$PAGE->set_url(new moodle_url('/local/qualiscope/edit_action.php', ['id' => $id, 'return' => $return]));
$PAGE->set_title(get_string('action_title', 'local_qualiscope'));
$PAGE->set_heading(get_string('action_title', 'local_qualiscope'));
$PAGE->set_context($context);

if (data_submitted() && confirm_sesskey()) {
    $action->title = required_param('title', PARAM_TEXT);
    $action->responsible = optional_param('responsible', '', PARAM_RAW);
    $action->duedate = optional_param('duedate', '', PARAM_RAW);
    $action->duedate = $action->duedate ? strtotime($action->duedate) : 0;
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

$formhtml = '<form method="post">';
$formhtml .= '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
$formhtml .= '<input type="hidden" name="return" value="' . s($return) . '">';

$formhtml .= '<div class="mb-3"><label class="form-label">' . get_string('action_name', 'local_qualiscope') . '</label>';
$formhtml .= '<input type="text" class="form-control" name="title" value="' . s($action->title) . '" required></div>';

$formhtml .= '<div class="mb-3"><label class="form-label">' . get_string('action_responsible', 'local_qualiscope') . '</label>';
$formhtml .= '<input type="text" class="form-control" name="responsible" value="' . s($action->responsible) . '"></div>';

$formhtml .= '<div class="mb-3"><label class="form-label">' . get_string('action_duedate', 'local_qualiscope') . '</label>';
$formhtml .= '<input type="date" class="form-control" name="duedate" value="' .
    ($action->duedate ? date('Y-m-d', $action->duedate) : '') . '"></div>';

$formhtml .= '<div class="mb-3"><label class="form-label">' . get_string('action_priority', 'local_qualiscope') . '</label>';
$formhtml .= '<select class="form-select" name="priority">';
foreach (['high', 'medium', 'low'] as $p) {
    $sel = $action->priority === $p ? ' selected' : '';
    $label = get_string('action_priority_' . $p, 'local_qualiscope');
    $formhtml .= '<option value="' . $p . '"' . $sel . '>' . $label . '</option>';
}
$formhtml .= '</select></div>';

$formhtml .= '<div class="mb-3"><label class="form-label">' . get_string('action_status', 'local_qualiscope') . '</label>';
$formhtml .= '<select class="form-select" name="status">';
foreach (['todo', 'inprogress', 'proved', 'verified', 'closed'] as $s) {
    $sel = $action->status === $s ? ' selected' : '';
    $label = get_string('action_status_' . $s, 'local_qualiscope');
    $formhtml .= '<option value="' . $s . '"' . $sel . '>' . $label . '</option>';
}
$formhtml .= '</select></div>';

$formhtml .= '<button type="submit" class="btn btn-primary">' . get_string('action_submit', 'local_qualiscope') . '</button>';
$formhtml .= '</form>';

echo $formhtml;
echo $OUTPUT->footer();
