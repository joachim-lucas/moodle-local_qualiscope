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
 * Evidence analyser tests for local_qualiscope.
 *
 * @package    local_qualiscope
 * @category   test
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qualiscope\tests;

use local_qualiscope\analyser\evidence_analyser;
use local_qualiscope\referential_seeder;

/**
 * Evidence analyser testcase.
 *
 * @package local_qualiscope
 */
final class evidence_analyser_test extends \advanced_testcase {
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
     * Seed a minimal referential and return the id of its single indicator.
     *
     * @return int The indicator id.
     */
    private function seedindicator(): int {
        global $DB;

        referential_seeder::seed_referential([
            'name' => 'TestRef',
            'version' => '1.0',
            'description' => '',
            'criteria' => [[
                'number' => 1,
                'title' => 'Criterion one',
                'indicators' => [[
                    'number' => 1,
                    'title' => 'Indicator one',
                ]],
            ]],
        ]);

        $ref = $DB->get_record('local_qualiscope_referentials', ['name' => 'TestRef']);
        $crit = $DB->get_record('local_qualiscope_criteria', ['referential_id' => $ref->id]);
        $ind = $DB->get_record('local_qualiscope_indicators', ['criterion_id' => $crit->id]);

        return (int) $ind->id;
    }

    /**
     * Insert an automatic check record for the given indicator.
     *
     * @param int $indicatorid The indicator id.
     * @param string $type The check type.
     * @return int The record id.
     */
    private function insertcheck(int $indicatorid, string $type): int {
        global $DB;

        $time = time();
        return $DB->insert_record('local_qualiscope_checks', [
            'indicator_id' => $indicatorid,
            'name' => $type,
            'description' => '',
            'type' => $type,
            'automatic' => 1,
            'weight' => 1,
            'timecreated' => $time,
            'timemodified' => $time,
        ]);
    }

    /**
     * Test get_external_evidences only returns external evidences of the result.
     *
     * @covers \local_qualiscope\analyser\evidence_analyser::get_external_evidences
     * @return void
     */
    public function test_get_external_evidences_filters_type(): void {
        global $DB;

        $time = time();
        $DB->insert_record('local_qualiscope_evidences', [
            'result_id' => 0,
            'type' => 'external',
            'source' => '',
            'title' => 'External proof',
            'description' => '',
            'filepath' => '',
            'filename' => '',
            'externalurl' => '',
            'annotation' => '',
            'userid' => 0,
            'timecreated' => $time,
            'timemodified' => $time,
        ]);
        $DB->insert_record('local_qualiscope_evidences', [
            'result_id' => 0,
            'type' => 'moodle',
            'source' => 'course',
            'title' => 'Automatic trace',
            'description' => '',
            'filepath' => '',
            'filename' => '',
            'externalurl' => '',
            'annotation' => '',
            'userid' => 0,
            'timecreated' => $time,
            'timemodified' => $time,
        ]);

        $result = evidence_analyser::get_external_evidences(0);

        $this->assertCount(1, $result);
        $this->assertEquals('external', reset($result)->type);
    }

    /**
     * Test get_moodle_evidences finds nothing for an empty course.
     *
     * @covers \local_qualiscope\analyser\evidence_analyser::get_moodle_evidences
     * @return void
     */
    public function test_get_moodle_evidences_empty_course(): void {
        $course = $this->getDataGenerator()->create_course();
        $indicatorid = $this->seedindicator();
        $this->insertcheck($indicatorid, 'activity_exists');

        $this->assertEmpty(evidence_analyser::get_moodle_evidences($course->id, $indicatorid));
    }

    /**
     * Test get_moodle_evidences collects a course summary for field_exists.
     *
     * @covers \local_qualiscope\analyser\evidence_analyser::get_moodle_evidences
     * @return void
     */
    public function test_get_moodle_evidences_course_summary(): void {
        $course = $this->getDataGenerator()->create_course(['summary' => 'A short course description']);
        $indicatorid = $this->seedindicator();
        $this->insertcheck($indicatorid, 'field_exists');

        $evidences = evidence_analyser::get_moodle_evidences($course->id, $indicatorid);

        $this->assertCount(1, $evidences);
        $this->assertEquals('course', $evidences[0]['source']);
    }
}
