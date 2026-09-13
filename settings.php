<?php

defined('MOODLE_INTERNAL') || die();

$ADMIN->add('localplugins', new admin_category('local_qualiscope', get_string('pluginname', 'local_qualiscope')));

$settings = new admin_settingpage('local_qualiscope_settings', get_string('settings', 'local_qualiscope'));

$settings->add(new admin_setting_configcheckbox(
    'local_qualiscope/autorefresh',
    get_string('autorefresh', 'local_qualiscope'),
    get_string('autorefresh_desc', 'local_qualiscope'),
    0
));

$referentialoptions = [0 => get_string('defaultreferential_first', 'local_qualiscope')];
if ($DB->get_manager()->table_exists('local_qualiopi_referentials')) {
    $refs = $DB->get_records('local_qualiopi_referentials', ['active' => 1], 'id ASC');
    foreach ($refs as $ref) {
        $referentialoptions[$ref->id] = $ref->name . ' ' . $ref->version;
    }
}

$settings->add(new admin_setting_configselect(
    'local_qualiscope/defaultreferential',
    get_string('defaultreferential', 'local_qualiscope'),
    get_string('defaultreferential_desc', 'local_qualiscope'),
    0,
    $referentialoptions
));

$settings->add(new admin_setting_configcheckbox(
    'local_qualiscope/enableai',
    get_string('enableai', 'local_qualiscope'),
    get_string('enableai_desc', 'local_qualiscope'),
    0
));

$ADMIN->add('local_qualiscope', $settings);
