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
 * Primary library file for the local_qualiscope plugin.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */



/**
 * Adds the QualiScope entry to the course navigation "More" menu for users with the audit capability.
 *
 * @param navigation_node $coursenode The course navigation node.
 * @param stdClass $course The course record.
 * @param context $coursecontext The course context.
 * @return void
 */
function local_qualiscope_extend_navigation_course(navigation_node $coursenode, stdClass $course, context $coursecontext) {
    if (!isloggedin() || isguestuser()) {
        return;
    }

    if (!has_capability('local/qualiscope:viewaudit', $coursecontext)) {
        return;
    }

    $coursenode->add(
        get_string('pluginname', 'local_qualiscope'),
        new moodle_url('/local/qualiscope/dashboard.php', ['courseid' => $course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'qualiscope',
        new pix_icon('i/report', '')
    );
}

/**
 * Returns the localized value of a referential record field.
 *
 * Backward-compatible wrapper delegating to \local_qualiscope\helper::localized().
 *
 * @param \stdClass $record A referential record (criterion, indicator or check).
 * @param string $field Base field name ('title', 'name' or 'description').
 * @return string The localized value, falling back to the base (French) value.
 */
function local_qualiscope_localized(\stdClass $record, string $field): string {
    return \local_qualiscope\helper::localized($record, $field);
}

/**
 * Returns a copy of a referential record with its display fields localized.
 *
 * Backward-compatible wrapper delegating to \local_qualiscope\helper::localize_record().
 *
 * @param \stdClass $record A referential record (criterion, indicator or check).
 * @return \stdClass The record copy with localized title, name and description.
 */
function local_qualiscope_localize_record(\stdClass $record): \stdClass {
    return \local_qualiscope\helper::localize_record($record);
}

/**
 * Serves evidence files stored in the course context by QualiScope.
 *
 * @param \stdClass $course Course record.
 * @param \stdClass $cm Course module record.
 * @param \context $context Activity context.
 * @param string $filearea File area, must be 'evidence'.
 * @param array $args Remaining path arguments.
 * @param bool $forcedownload Whether to force the browser to download the file.
 * @param array $options Additional download options.
 * @return void
 */
function local_qualiscope_pluginfile(
    $course,
    $cm,
    $context,
    string $filearea,
    array $args,
    bool $forcedownload,
    array $options = []
): void {
    if ($context->contextlevel != CONTEXT_COURSE) {
        send_file_not_found();
    }

    require_login($course);

    if (!has_capability('local/qualiscope:viewaudit', $context)) {
        send_file_not_found();
    }

    if ($filearea !== 'evidence') {
        send_file_not_found();
    }

    $itemid = (int) array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_qualiscope', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        send_file_not_found();
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}
