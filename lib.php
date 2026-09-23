<?php

defined('MOODLE_INTERNAL') || die();

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
function local_qualiscope_pluginfile($course, $cm, $context, string $filearea, array $args, bool $forcedownload, array $options = []): void {
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
