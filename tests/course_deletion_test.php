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
 * Course deletion tests for local_qualiscope.
 *
 * @package    local_qualiscope
 * @category   test
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qualiscope\tests;

use local_qualiscope\quota;

/**
 * Tests that the audit data of a course is removed when the course is deleted.
 *
 * @package local_qualiscope
 * @covers \local_qualiscope\hooks\before_course_deleted
 */
final class course_deletion_test extends \advanced_testcase {
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
     * Seeds a campaign, a result with an evidence and an action for a course.
     *
     * @param \stdClass $course The course the data belongs to.
     * @param \stdClass $user The owner of the data.
     * @return array The inserted ids.
     */
    private function seed(\stdClass $course, \stdClass $user): array {
        global $DB;

        $time = time();

        $campaignid = $DB->insert_record('local_qualiscope_campaigns', [
            'name' => 'Audit test',
            'referential_id' => 0,
            'scope' => 'all',
            'scopeids' => '',
            'userid' => $user->id,
            'timecreated' => $time,
            'timemodified' => $time,
            'timecompleted' => 0,
        ]);

        $resultid = $DB->insert_record('local_qualiscope_results', [
            'campaign_id' => $campaignid,
            'courseid' => $course->id,
            'check_id' => 0,
            'indicator_id' => 0,
            'status' => 'missing',
            'ratio' => 0,
            'detail' => '',
            'evidence_count' => 1,
            'timecreated' => $time,
            'timemodified' => $time,
        ]);

        $evidenceid = $DB->insert_record('local_qualiscope_evidences', [
            'result_id' => $resultid,
            'type' => 'external',
            'source' => '',
            'title' => 'Proof',
            'description' => '',
            'filepath' => '',
            'filename' => 'proof.txt',
            'externalurl' => '',
            'annotation' => '',
            'userid' => $user->id,
            'timecreated' => $time,
            'timemodified' => $time,
        ]);

        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => \context_course::instance($course->id)->id,
            'component' => 'local_qualiscope',
            'filearea' => 'evidence',
            'itemid' => $resultid,
            'filepath' => '/',
            'filename' => 'proof.txt',
        ], 'proof content');

        $actionid = $DB->insert_record('local_qualiscope_actions', [
            'result_id' => $resultid,
            'campaign_id' => $campaignid,
            'courseid' => $course->id,
            'title' => 'Action',
            'description' => '',
            'responsible' => 'Jane Doe',
            'duedate' => 0,
            'priority' => 'medium',
            'status' => 'todo',
            'userid' => $user->id,
            'timecreated' => $time,
            'timemodified' => $time,
            'timeclosed' => 0,
        ]);

        $DB->insert_record(quota::TABLE, ['courseid' => $course->id, 'timeaudited' => $time]);

        return [
            'campaign' => $campaignid,
            'result' => $resultid,
            'evidence' => $evidenceid,
            'action' => $actionid,
        ];
    }

    /**
     * Test that deleting a course removes its results, actions, evidences and quota row, and
     * leaves the other courses and the campaigns alone.
     *
     * @return void
     */
    public function test_course_deletion_removes_course_data(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();

        $ids = $this->seed($course, $user);
        $otherids = $this->seed($other, $user);

        $campaignid = $ids['campaign'];
        $courseid = (int) $course->id;

        $context = \context_course::instance($courseid);
        $fs = get_file_storage();
        $this->assertNotFalse(
            $fs->get_file($context->id, 'local_qualiscope', 'evidence', $ids['result'], '/', 'proof.txt')
        );

        $this->setAdminUser();
        // The second argument keeps core quiet: it is $showfeedback on 5.x and $async on 4.x.
        delete_course($courseid, false);

        // The course scope is gone: results, actions, evidences and quota row.
        $this->assertFalse($DB->record_exists('local_qualiscope_results', ['courseid' => $courseid]));
        $this->assertFalse($DB->record_exists('local_qualiscope_actions', ['courseid' => $courseid]));
        $this->assertFalse($DB->record_exists('local_qualiscope_evidences', ['id' => $ids['evidence']]));
        $this->assertFalse($DB->record_exists(quota::TABLE, ['courseid' => $courseid]));

        // The campaign is site wide and must survive.
        $this->assertTrue($DB->record_exists('local_qualiscope_campaigns', ['id' => $campaignid]));

        // The other course is untouched.
        $this->assertEquals(1, $DB->count_records('local_qualiscope_results', ['courseid' => $other->id]));
        $this->assertEquals(1, $DB->count_records('local_qualiscope_actions', ['courseid' => $other->id]));
        $this->assertTrue($DB->record_exists(quota::TABLE, ['courseid' => $other->id]));
        $this->assertTrue($DB->record_exists('local_qualiscope_evidences', ['id' => $otherids['evidence']]));
    }

    /**
     * Test that the quota slot of a deleted course is given back to the free plan.
     *
     * @return void
     */
    public function test_quota_slot_is_released(): void {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $this->seed($course, $user);
        $this->assertEquals(1, quota::used());

        $this->setAdminUser();
        // The second argument keeps core quiet: it is $showfeedback on 5.x and $async on 4.x.
        delete_course((int) $course->id, false);

        $this->assertEquals(0, quota::used());
    }
}
