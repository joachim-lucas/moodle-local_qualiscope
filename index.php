<?php

require_once('../../config.php');

require_login();

$PAGE->set_url(new moodle_url('/local/qualiscope/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_qualiscope'));
$PAGE->set_heading(get_string('pluginname', 'local_qualiscope'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_qualiscope'));

$systemcontext = context_system::instance();
if (has_capability('local/qualiscope:managecampaigns', $systemcontext)) {
    echo html_writer::link(
        new moodle_url('/local/qualiscope/campaigns.php'),
        get_string('nav_campaigns', 'local_qualiscope')
    ) . '<br>';
}

echo $OUTPUT->footer();
