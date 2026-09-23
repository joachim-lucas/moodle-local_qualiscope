<?php

defined('MOODLE_INTERNAL') || die();

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