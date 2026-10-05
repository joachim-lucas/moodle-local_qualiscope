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

    if ($oldversion < 2026092402) {
        $table = new xmldb_table('local_qualiscope_auditedcourses');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        $table->add_field('id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timeaudited', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, 0);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('courseid', XMLDB_INDEX_UNIQUE, ['courseid']);
        $dbman->create_table($table);
        upgrade_plugin_savepoint(true, 2026092402, 'local', 'qualiscope');
    }

    if ($oldversion < 2026092403) {
        $table = new xmldb_table('local_qualiscope_auditedcourses');
        if ($dbman->table_exists($table)) {
            $columns = $DB->get_columns('local_qualiscope_auditedcourses');
            if (!isset($columns['id']) || !$columns['id']->auto_increment) {
                $rows = $DB->get_records('local_qualiscope_auditedcourses');
                $dbman->drop_table($table);
                $table = new xmldb_table('local_qualiscope_auditedcourses');
                $table->add_field('id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
                $table->add_field('courseid', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, null);
                $table->add_field('timeaudited', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, 0);
                $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
                $table->add_index('courseid', XMLDB_INDEX_UNIQUE, ['courseid']);
                $dbman->create_table($table);
                foreach ($rows as $row) {
                    $DB->insert_record('local_qualiscope_auditedcourses', $row);
                }
            }
        }
        upgrade_plugin_savepoint(true, 2026092403, 'local', 'qualiscope');
    }

    if ($oldversion < 2026092500) {
        $fieldsets = [
            'local_qualiscope_referentials' => [
                new xmldb_field('description_en', XMLDB_TYPE_TEXT),
            ],
            'local_qualiscope_criteria' => [
                new xmldb_field('title_en', XMLDB_TYPE_CHAR, '255'),
                new xmldb_field('description_en', XMLDB_TYPE_TEXT),
            ],
            'local_qualiscope_indicators' => [
                new xmldb_field('title_en', XMLDB_TYPE_CHAR, '255'),
                new xmldb_field('description_en', XMLDB_TYPE_TEXT),
            ],
            'local_qualiscope_checks' => [
                new xmldb_field('name_en', XMLDB_TYPE_CHAR, '255'),
                new xmldb_field('description_en', XMLDB_TYPE_TEXT),
            ],
        ];
        foreach ($fieldsets as $tablename => $fields) {
            $table = new xmldb_table($tablename);
            foreach ($fields as $field) {
                if (!$dbman->field_exists($table, $field)) {
                    $dbman->add_field($table, $field);
                }
            }
        }
        \local_qualiscope\referential_seeder::seed_all_from_files();
        upgrade_plugin_savepoint(true, 2026092500, 'local', 'qualiscope');
    }

    if ($oldversion < 2026093001) {
        // The managechecks capability guarded nothing: no screen lets anyone
        // manage the referential checks, the seeder only runs at install and
        // upgrade time, and the referential is picked in the settings page,
        // which is already reserved to moodle/site:config. Removing it from
        // db/access.php would leave the row in the database, because
        // update_capabilities() only ever adds and refreshes capabilities.
        $DB->delete_records('role_capabilities', ['capability' => 'local/qualiscope:managechecks']);
        $DB->delete_records('capabilities', ['name' => 'local/qualiscope:managechecks']);
        \cache_helper::purge_by_definition('core', 'capabilities');

        upgrade_plugin_savepoint(true, 2026093001, 'local', 'qualiscope');
    }

    if ($oldversion < 2026100501) {
        // Each evidence owns its file area, itemid being its own id. Earlier releases
        // shared one area per result, which could only ever hold the file of the last
        // upload, so the surviving files have to be moved to the area of the evidence
        // that references them. Two evidence rows of a same result may name the same
        // file, in which case the first one takes it and the others keep pointing at a
        // file that was already missing before this upgrade.
        $rows = $DB->get_records_sql(
            "SELECT e.id, e.result_id, e.filepath, e.filename, r.courseid
               FROM {local_qualiscope_evidences} e
               JOIN {local_qualiscope_results} r ON r.id = e.result_id
              WHERE e.filename IS NOT NULL AND e.filename <> ''"
        );

        $fs = get_file_storage();
        $moved = [];

        foreach ($rows as $row) {
            $context = \context_course::instance((int) $row->courseid);
            $filepath = empty($row->filepath) ? '/' : $row->filepath;
            $legacy = (int) $row->result_id;

            if (isset($moved[$legacy][$row->filename])) {
                // Already moved for another evidence of the same result.
                continue;
            }

            $file = $fs->get_file($context->id, 'local_qualiscope', 'evidence', $legacy, $filepath, $row->filename);
            if (!$file) {
                continue;
            }

            if ($fs->file_exists($context->id, 'local_qualiscope', 'evidence', (int) $row->id, $filepath, $row->filename)) {
                $file->delete();
            } else {
                $fs->create_file_from_storedfile(['itemid' => (int) $row->id], $file);
                $file->delete();
            }

            $moved[$legacy][$row->filename] = true;
        }

        upgrade_plugin_savepoint(true, 2026100501, 'local', 'qualiscope');
    }

    return true;
}
