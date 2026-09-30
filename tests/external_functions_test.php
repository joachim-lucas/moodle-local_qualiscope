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
 * External function tests for local_qualiscope.
 *
 * @package    local_qualiscope
 * @category   test
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qualiscope\tests;

use local_qualiscope\external\add_evidence;
use local_qualiscope\external\create_action;
use local_qualiscope\external\run_analysis;

/**
 * External function testcase.
 *
 * lib/externallib.php refuses to be loaded outside an isolated process. The annotation is used
 * instead of the PHP attribute because Moodle 4.5 still runs PHPUnit 9, which ignores attributes.
 *
 * @package local_qualiscope
 * @runTestsInSeparateProcesses
 */
final class external_functions_test extends \advanced_testcase {
    /**
     * Set up.
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        global $CFG;
        require_once($CFG->libdir . '/externallib.php');
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    /**
     * Creates a campaign and an analysis result for a course.
     *
     * @param int $courseid The course id.
     * @param int $userid The owning user id.
     * @return array The campaign and result ids.
     */
    private function insertresult(int $courseid, int $userid): array {
        global $DB;

        $time = time();

        $campaignid = $DB->insert_record('local_qualiscope_campaigns', [
            'name' => 'Audit test',
            'referential_id' => 0,
            'scope' => 'all',
            'scopeids' => '',
            'userid' => $userid,
            'timecreated' => $time,
            'timemodified' => $time,
            'timecompleted' => 0,
        ]);

        $resultid = $DB->insert_record('local_qualiscope_results', [
            'campaign_id' => $campaignid,
            'courseid' => $courseid,
            'check_id' => 0,
            'indicator_id' => 0,
            'status' => 'missing',
            'ratio' => 0,
            'detail' => '',
            'evidence_count' => 0,
            'timecreated' => $time,
            'timemodified' => $time,
        ]);

        return ['campaign' => $campaignid, 'result' => $resultid];
    }

    /**
     * The declared parameter order must match the execute() signature, because the web service
     * layer passes the arguments by position.
     *
     * @covers \local_qualiscope\external\create_action
     * @covers \local_qualiscope\external\add_evidence
     * @covers \local_qualiscope\external\run_analysis
     * @return void
     */
    public function test_execute_parameter_order_matches_declaration(): void {
        $classes = [
            add_evidence::class,
            create_action::class,
            run_analysis::class,
        ];

        foreach ($classes as $classname) {
            $declared = array_keys($classname::execute_parameters()->keys);
            $method = new \ReflectionMethod($classname, 'execute');
            $signature = array_map(function ($parameter) {
                return $parameter->getName();
            }, $method->getParameters());

            $this->assertEquals($declared, $signature, "{$classname}::execute() has a different parameter order");
        }
    }

    /**
     * Test create_action stores the values in the columns they belong to.
     *
     * @covers \local_qualiscope\external\create_action
     * @return void
     */
    public function test_create_action_maps_arguments_to_columns(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $ids = $this->insertresult($course->id, get_admin()->id);

        $return = create_action::execute($ids['result'], $ids['campaign'], $course->id, 'Write a summary', 'Jane', '0', 'low');

        $this->assertTrue($return['success']);

        $action = $DB->get_record('local_qualiscope_actions', ['id' => $return['actionid']], '*', MUST_EXIST);
        $this->assertEquals('Write a summary', $action->title);
        $this->assertEquals('Jane', $action->responsible);
        $this->assertEquals('low', $action->priority);
        $this->assertEquals($course->id, $action->courseid);
        $this->assertEquals($ids['campaign'], $action->campaign_id);
        $this->assertEquals($ids['result'], $action->result_id);
    }

    /**
     * Test add_evidence attaches the evidence to the result of the stored course.
     *
     * @covers \local_qualiscope\external\add_evidence
     * @return void
     */
    public function test_add_evidence_attaches_to_result(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $ids = $this->insertresult($course->id, get_admin()->id);

        $return = add_evidence::execute($ids['result'], 'Invoice', 'Checked', 'https://example.com/invoice.pdf');

        $this->assertTrue($return['success']);

        $evidence = $DB->get_record('local_qualiscope_evidences', ['id' => $return['evidenceid']], '*', MUST_EXIST);
        $this->assertEquals($ids['result'], $evidence->result_id);
        $this->assertEquals('Invoice', $evidence->title);
        $this->assertEquals('Checked', $evidence->annotation);
        $this->assertEquals('https://example.com/invoice.pdf', $evidence->externalurl);
    }

    /**
     * Test run_analysis answers with a success flag.
     *
     * @covers \local_qualiscope\external\run_analysis
     * @return void
     */
    public function test_run_analysis_returns_success(): void {
        $course = $this->getDataGenerator()->create_course();

        $return = run_analysis::execute($course->id, 0);

        $this->assertTrue($return['success']);
        $this->assertNotEmpty($return['message']);
    }
}
