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
 * Upgrades the local_qualiscope plugin database schema.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */



/**
 * Upgrade hook: applies schema changes and reseeds the referentials.
 *
 * @param int $oldversion The previous plugin version.
 * @return bool Always true.
 */
function xmldb_local_qualiscope_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026091201) {
        \local_qualiscope\referential_seeder::seed_all_from_files();
        upgrade_plugin_savepoint(true, 2026091201, 'local', 'qualiscope');
    }

    if ($oldversion < 2026091203) {
        $table = new xmldb_table('local_qualiscope_results');
        $field = new xmldb_field('ratio', XMLDB_TYPE_NUMBER, '5,2', null, XMLDB_NOTNULL, null, 0);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026091203, 'local', 'qualiscope');
    }

    if ($oldversion < 2026091401) {
        \local_qualiscope\referential_seeder::seed_all_from_files();
        upgrade_plugin_savepoint(true, 2026091401, 'local', 'qualiscope');
    }

    if ($oldversion < 2026092201) {
        \local_qualiscope\referential_seeder::seed_all_from_files();
        upgrade_plugin_savepoint(true, 2026092201, 'local', 'qualiscope');
    }

    return true;
}
