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
 * Upgrade tests for local_qualiscope.
 *
 * @package    local_qualiscope
 * @category   test
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qualiscope\tests;

/**
 * Upgrade path testcase.
 *
 * @package local_qualiscope
 */
final class upgrade_test extends \advanced_testcase {
    /**
     * Runs the plugin upgrade against a legacy auditedcourses table whose id
     * column is not auto-incrementing and asserts it is repaired while data
     * is preserved.
     *
     * @covers \xmldb_local_qualiscope_upgrade
     */
    public function test_upgrade_2026092403_repairs_legacy_table(): void {
        global $CFG, $DB;

        $this->resetAfterTest();

        $dbman = $DB->get_manager();
        $table = new \xmldb_table('local_qualiscope_auditedcourses');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        $table->add_field('id', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timeaudited', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, 0);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('courseid', XMLDB_INDEX_UNIQUE, ['courseid']);
        $dbman->create_table($table);

        $DB->execute('INSERT INTO {local_qualiscope_auditedcourses} (id, courseid, timeaudited) VALUES (1, 99, 1000)');

        // Simulate a site still on the previous plugin version so the savepoint
        // of the 2026092403 step is a real upgrade, not a downgrade.
        $DB->set_field('config_plugins', 'value', '2026092402', ['plugin' => 'local_qualiscope', 'name' => 'version']);

        require_once($CFG->dirroot . '/lib/upgradelib.php');
        require_once($CFG->dirroot . '/local/qualiscope/db/upgrade.php');

        $this->assertTrue(xmldb_local_qualiscope_upgrade(2026092402));

        $columns = $DB->get_columns('local_qualiscope_auditedcourses');
        $this->assertTrue($columns['id']->auto_increment);
        $this->assertTrue($DB->record_exists('local_qualiscope_auditedcourses', ['courseid' => 99]));
    }

    /**
     * Tests the 2026092500 upgrade step adds the localized columns and
     * reseeds the referentials with their English values.
     *
     * @covers \xmldb_local_qualiscope_upgrade
     */
    public function test_upgrade_2026092500_adds_localized_columns(): void {
        global $CFG, $DB;

        $this->resetAfterTest();

        $dbman = $DB->get_manager();

        // Simulate a legacy install where the localized columns do not exist yet.
        $fieldsets = [
            ['local_qualiscope_referentials', 'description_en'],
            ['local_qualiscope_criteria', 'title_en'],
            ['local_qualiscope_criteria', 'description_en'],
            ['local_qualiscope_indicators', 'title_en'],
            ['local_qualiscope_indicators', 'description_en'],
            ['local_qualiscope_checks', 'name_en'],
            ['local_qualiscope_checks', 'description_en'],
        ];
        foreach ($fieldsets as [$tablename, $fieldname]) {
            $table = new \xmldb_table($tablename);
            if ($dbman->field_exists($table, $fieldname)) {
                $dbman->drop_field($table, new \xmldb_field($fieldname));
            }
        }

        // Simulate a site on the previous plugin version.
        $DB->set_field('config_plugins', 'value', '2026092404', ['plugin' => 'local_qualiscope', 'name' => 'version']);

        require_once($CFG->dirroot . '/lib/upgradelib.php');
        require_once($CFG->dirroot . '/local/qualiscope/db/upgrade.php');

        $this->assertTrue(xmldb_local_qualiscope_upgrade(2026092404));

        // The localized columns now exist.
        $this->assertTrue($DB->get_manager()->field_exists('local_qualiscope_criteria', 'title_en'));
        $this->assertTrue($DB->get_manager()->field_exists('local_qualiscope_checks', 'name_en'));

        // The reseed populated English values for the seeded criteria.
        $criteria = $DB->get_recordset_sql(
            'SELECT id FROM {local_qualiscope_criteria} WHERE title_en <> :empty',
            ['empty' => '']
        );
        $count = 0;
        foreach ($criteria as $c) {
            $count++;
        }
        $criteria->close();
        $this->assertGreaterThan(0, $count);
    }
}
