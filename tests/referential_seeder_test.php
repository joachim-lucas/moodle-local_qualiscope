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
 * Referential seeder tests for local_qualiscope.
 *
 * @package    local_qualiscope
 * @category   test
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qualiscope\tests;

use local_qualiscope\referential_seeder;

/**
 * Referential seeder testcase.
 *
 * @package local_qualiscope
 */
final class referential_seeder_test extends \advanced_testcase {
    /**
     * Set up.
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Build a sample referential definition.
     *
     * @return array The definition.
     */
    private function sampledef(): array {
        return [
            'name' => 'TestRef',
            'version' => '1.0',
            'description' => 'A test referential',
            'description_en' => 'A test referential (EN)',
            'criteria' => [[
                'number' => 1,
                'title' => 'Criterion one',
                'description' => 'First criterion',
                'title_en' => 'Criterion one (EN)',
                'description_en' => 'First criterion (EN)',
                'indicators' => [[
                    'number' => 1,
                    'title' => 'Indicator one',
                    'description' => 'First indicator',
                    'title_en' => 'Indicator one (EN)',
                    'description_en' => 'First indicator (EN)',
                    'scope' => 'course',
                    'checks' => [[
                        'name' => 'activity_exists',
                        'description' => 'An activity is present',
                        'name_en' => 'Activity exists (EN)',
                        'description_en' => 'An activity is present (EN)',
                        'type' => 'activity_exists',
                        'automatic' => true,
                        'weight' => 2,
                    ]],
                ]],
            ]],
        ];
    }

    /**
     * Test seed_referential creates the whole tree.
     *
     * @covers \local_qualiscope\referential_seeder::seed_referential
     * @return void
     */
    public function test_seed_referential_creates_tree(): void {
        global $DB;

        $before = [
            'local_qualiscope_referentials' => $DB->count_records('local_qualiscope_referentials'),
            'local_qualiscope_criteria' => $DB->count_records('local_qualiscope_criteria'),
            'local_qualiscope_indicators' => $DB->count_records('local_qualiscope_indicators'),
            'local_qualiscope_checks' => $DB->count_records('local_qualiscope_checks'),
        ];

        referential_seeder::seed_referential($this->sampledef());

        $this->assertEquals($before['local_qualiscope_referentials'] + 1, $DB->count_records('local_qualiscope_referentials'));
        $this->assertEquals($before['local_qualiscope_criteria'] + 1, $DB->count_records('local_qualiscope_criteria'));
        $this->assertEquals($before['local_qualiscope_indicators'] + 1, $DB->count_records('local_qualiscope_indicators'));
        $this->assertEquals($before['local_qualiscope_checks'] + 1, $DB->count_records('local_qualiscope_checks'));

        $referential = $DB->get_record('local_qualiscope_referentials', ['name' => 'TestRef']);
        $this->assertNotFalse($referential);
        $this->assertEquals('A test referential (EN)', $referential->description_en);

        $criterion = $DB->get_record('local_qualiscope_criteria', ['referential_id' => $referential->id]);
        $this->assertNotFalse($criterion);
        $this->assertEquals('Criterion one (EN)', $criterion->title_en);
        $this->assertEquals('First criterion (EN)', $criterion->description_en);

        $indicator = $DB->get_record('local_qualiscope_indicators', ['criterion_id' => $criterion->id]);
        $this->assertNotFalse($indicator);
        $this->assertEquals('Indicator one (EN)', $indicator->title_en);
        $this->assertEquals('First indicator (EN)', $indicator->description_en);

        $check = $DB->get_record('local_qualiscope_checks', ['indicator_id' => $indicator->id]);
        $this->assertNotFalse($check);
        $this->assertEquals('Activity exists (EN)', $check->name_en);
        $this->assertEquals('An activity is present (EN)', $check->description_en);
    }

    /**
     * Test seed_referential is idempotent by name.
     *
     * @covers \local_qualiscope\referential_seeder::seed_referential
     * @return void
     */
    public function test_seed_referential_idempotent(): void {
        global $DB;

        $before = [
            'local_qualiscope_referentials' => $DB->count_records('local_qualiscope_referentials'),
            'local_qualiscope_criteria' => $DB->count_records('local_qualiscope_criteria'),
            'local_qualiscope_indicators' => $DB->count_records('local_qualiscope_indicators'),
            'local_qualiscope_checks' => $DB->count_records('local_qualiscope_checks'),
        ];

        referential_seeder::seed_referential($this->sampledef());
        referential_seeder::seed_referential($this->sampledef());

        $this->assertEquals($before['local_qualiscope_referentials'] + 1, $DB->count_records('local_qualiscope_referentials'));
        $this->assertEquals($before['local_qualiscope_criteria'] + 1, $DB->count_records('local_qualiscope_criteria'));
        $this->assertEquals($before['local_qualiscope_indicators'] + 1, $DB->count_records('local_qualiscope_indicators'));
        $this->assertEquals($before['local_qualiscope_checks'] + 1, $DB->count_records('local_qualiscope_checks'));
    }

    /**
     * Test seed_referential updates existing definitions.
     *
     * @covers \local_qualiscope\referential_seeder::seed_referential
     * @return void
     */
    public function test_seed_referential_updates_existing(): void {
        global $DB;

        referential_seeder::seed_referential($this->sampledef());
        $referential = $DB->get_record('local_qualiscope_referentials', ['name' => 'TestRef']);
        $this->assertNotFalse($referential);

        $def = $this->sampledef();
        $def['criteria'][0]['title'] = 'Criterion updated';
        $def['criteria'][0]['title_en'] = 'Criterion updated (EN)';
        referential_seeder::seed_referential($def);

        $criteria = $DB->get_records('local_qualiscope_criteria', ['referential_id' => $referential->id]);
        $this->assertCount(1, $criteria);
        $this->assertEquals('Criterion updated', reset($criteria)->title);
        $this->assertEquals('Criterion updated (EN)', reset($criteria)->title_en);
    }

    /**
     * Test read_referential_file returns null for a missing file.
     *
     * @covers \local_qualiscope\referential_seeder::read_referential_file
     * @return void
     */
    public function test_read_referential_file_missing(): void {
        $this->assertNull(referential_seeder::read_referential_file('phpunit_missing_file.json'));
    }

    /**
     * Test read_referential_file decodes a valid definition.
     *
     * @covers \local_qualiscope\referential_seeder::read_referential_file
     * @return void
     */
    public function test_read_referential_file_valid(): void {
        $tempdir = make_temp_directory('qualiscope_unit');
        $file = $tempdir . '/sample.json';
        file_put_contents($file, json_encode($this->sampledef()));

        $def = referential_seeder::read_referential_file($file);
        unlink($file);

        $this->assertNotNull($def);
        $this->assertEquals('TestRef', $def['name']);
    }
}
