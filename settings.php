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
 * QualiScope Settings page.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

$ADMIN->add('localplugins', new admin_category('local_qualiscope', get_string('pluginname', 'local_qualiscope')));

$settings = new admin_settingpage('local_qualiscope_settings', get_string('settings', 'local_qualiscope'));

$licencestatus = \local_qualiscope\license::validate(\local_qualiscope\license::stored_key());
if ($licencestatus['valid']) {
    $licencestatuslabel = get_string('licence_status_ok', 'local_qualiscope', [
        'expiry' => userdate((int) $licencestatus['expiry']),
    ]);
} else {
    $licencestatuslabel = get_string('licence_status_' . $licencestatus['code'], 'local_qualiscope');
    if ($licencestatus['code'] === 'expired' && $licencestatus['expiry']) {
        $licencestatuslabel .= ' ' . get_string('licence_expired_on', 'local_qualiscope', userdate((int) $licencestatus['expiry']));
    }
}
$licencestatusdesc = get_string('licencekey_desc', 'local_qualiscope', ['max' => \local_qualiscope\quota::FREE_COURSES]);
$licencestatusdesc .= ' ' . $licencestatuslabel;
$licencestatusdesc .= ' ' . get_string('licence_sitehash', 'local_qualiscope', \local_qualiscope\license::site_hash());

$settings->add(new admin_setting_configtext(
    'local_qualiscope/licensekey',
    get_string('licencekey', 'local_qualiscope'),
    $licencestatusdesc,
    '',
    PARAM_RAW,
    80
));

$settings->add(new admin_setting_configcheckbox(
    'local_qualiscope/autorefresh',
    get_string('autorefresh', 'local_qualiscope'),
    get_string('autorefresh_desc', 'local_qualiscope'),
    0
));

$referentialoptions = [0 => get_string('defaultreferential_first', 'local_qualiscope')];
if ($DB->get_manager()->table_exists('local_qualiscope_referentials')) {
    $refs = $DB->get_records('local_qualiscope_referentials', ['active' => 1], 'id ASC');
    foreach ($refs as $ref) {
        $referentialoptions[$ref->id] = local_qualiscope_localized($ref, 'name') . ' ' . $ref->version;
    }
}

$settings->add(new admin_setting_configselect(
    'local_qualiscope/defaultreferential',
    get_string('defaultreferential', 'local_qualiscope'),
    get_string('defaultreferential_desc', 'local_qualiscope'),
    0,
    $referentialoptions
));

$ADMIN->add('local_qualiscope', $settings);
