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
 * Privacy provider tests for local_qualiscope.
 *
 * @package    local_qualiscope
 * @category   test
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qualiscope\tests;

use local_qualiscope\privacy\provider;

/**
 * Privacy provider testcase.
 *
 * @package local_qualiscope
 * @covers \local_qualiscope\privacy\provider
 */
final class provider_test extends \advanced_testcase {
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
     * Creates a campaign, a result for a course, an evidence with a stored file and an action.
     *
     * @param \stdClass $user The owner of the stored data.
     * @param \stdClass $course The course the data belongs to.
     * @param string $filename Name of the evidence file, empty to skip the file.
     * @return array The inserted ids.
     */
    private function insertdata(\stdClass $user, \stdClass $course, string $filename = 'proof.txt'): array {
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
            'evidence_count' => 0,
            'timecreated' => $time,
            'timemodified' => $time,
        ]);

        $evidenceid = $DB->insert_record('local_qualiscope_evidences', [
            'result_id' => $resultid,
            'type' => 'external',
            'source' => 'quality manager',
            'title' => 'Proof',
            'description' => 'Official proof',
            'filepath' => '',
            'filename' => $filename,
            'externalurl' => 'https://example.com/proof',
            'annotation' => 'Annotation',
            'userid' => $user->id,
            'timecreated' => $time,
            'timemodified' => $time,
        ]);

        if ($filename !== '') {
            $fs = get_file_storage();
            $fs->create_file_from_string([
                'contextid' => \context_course::instance($course->id)->id,
                'component' => 'local_qualiscope',
                'filearea' => 'evidence',
                'itemid' => $resultid,
                'filepath' => '/',
                'filename' => $filename,
            ], 'proof content');
        }

        $actionid = $DB->insert_record('local_qualiscope_actions', [
            'result_id' => $resultid,
            'campaign_id' => $campaignid,
            'courseid' => $course->id,
            'title' => 'Action',
            'description' => 'Action description',
            'responsible' => 'Jane Doe',
            'duedate' => 0,
            'priority' => 'medium',
            'status' => 'todo',
            'userid' => $user->id,
            'timecreated' => $time,
            'timemodified' => $time,
            'timeclosed' => 0,
        ]);

        $DB->set_field('local_qualiscope_results', 'evidence_count', 1, ['id' => $resultid]);

        return [
            'campaign' => $campaignid,
            'result' => $resultid,
            'evidence' => $evidenceid,
            'action' => $actionid,
        ];
    }

    /**
     * Test get_metadata lists the three user-facing tables and the file area.
     *
     * @return void
     */
    public function test_get_metadata(): void {
        $collection = new \core_privacy\local\metadata\collection('local_qualiscope');
        $collection = provider::get_metadata($collection);

        $tables = $collection->get_collection();
        $this->assertCount(4, $tables);

        $names = array_map(function ($table) {
            return $table->get_name();
        }, $tables);
        $this->assertContains('local_qualiscope_campaigns', $names);
        $this->assertContains('local_qualiscope_evidences', $names);
        $this->assertContains('local_qualiscope_actions', $names);
        $this->assertContains('core_files', $names);
    }

    /**
     * Test get_contexts_for_userid with no stored data.
     *
     * @return void
     */
    public function test_get_contexts_for_userid_without_data(): void {
        $user = $this->getDataGenerator()->create_user();

        $contextlist = provider::get_contexts_for_userid($user->id);

        $this->assertEmpty($contextlist->get_contextids());
    }

    /**
     * Test get_contexts_for_userid maps evidence and action rows to the course contexts.
     *
     * @return void
     */
    public function test_get_contexts_for_userid_with_data(): void {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->insertdata($user, $course);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $contextids = $contextlist->get_contextids();

        $this->assertCount(2, $contextids);
        $this->assertEqualsCanonicalizing(
            [
                \context_system::instance()->id,
                \context_course::instance($course->id)->id,
            ],
            $contextids
        );
    }

    /**
     * Test export_user_data writes into the system and course contexts.
     *
     * @return void
     */
    public function test_export_user_data(): void {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ids = $this->insertdata($user, $course);

        $approved = new \core_privacy\local\request\approved_contextlist(
            $user,
            'local_qualiscope',
            [\context_system::instance()->id, \context_course::instance($course->id)->id]
        );

        provider::export_user_data($approved);

        $this->assertTrue(
            \core_privacy\local\request\writer::with_context(\context_system::instance())->has_any_data()
        );

        $context = \context_course::instance($course->id);
        $writer = \core_privacy\local\request\writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        $path = [get_string('privacy:metadata:evidences', 'local_qualiscope'), '#' . $ids['evidence']];

        $exported = $writer->get_data($path);
        $this->assertEquals('Proof', $exported->title);
        $this->assertEquals('https://example.com/proof', $exported->externalurl);
        $this->assertEquals('proof.txt', $exported->filename);
        $this->assertEquals('Annotation', $exported->annotation);

        $files = $writer->get_files($path);
        $this->assertCount(1, $files);
        $file = reset($files);
        $this->assertInstanceOf(\stored_file::class, $file);
        $this->assertEquals('proof.txt', $file->get_filename());

        $actionpath = [get_string('privacy:metadata:actions', 'local_qualiscope'), '#' . $ids['action']];
        $action = $writer->get_data($actionpath);
        $this->assertEquals('Action description', $action->description);
        $this->assertEquals('Jane Doe', $action->responsible);
    }

    /**
     * Test delete_data_for_user only removes the data of the given user.
     *
     * @return void
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->insertdata($user1, $course);
        $this->insertdata($user2, $course);

        $approved = new \core_privacy\local\request\approved_contextlist(
            $user1,
            'local_qualiscope',
            [\context_system::instance()->id, \context_course::instance($course->id)->id]
        );

        provider::delete_data_for_user($approved);

        $this->assertFalse($DB->record_exists('local_qualiscope_campaigns', ['userid' => $user1->id]));
        $this->assertFalse($DB->record_exists('local_qualiscope_evidences', ['userid' => $user1->id]));
        $this->assertFalse($DB->record_exists('local_qualiscope_actions', ['userid' => $user1->id]));

        $this->assertTrue($DB->record_exists('local_qualiscope_campaigns', ['userid' => $user2->id]));
        $this->assertTrue($DB->record_exists('local_qualiscope_evidences', ['userid' => $user2->id]));
        $this->assertTrue($DB->record_exists('local_qualiscope_actions', ['userid' => $user2->id]));
    }

    /**
     * Test delete_data_for_user also removes the evidence files.
     *
     * @return void
     */
    public function test_delete_data_for_user_removes_files(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $ids = $this->insertdata($user, $course);

        $fs = get_file_storage();
        $context = \context_course::instance($course->id);
        $this->assertNotFalse($fs->get_file($context->id, 'local_qualiscope', 'evidence', $ids['result'], '/', 'proof.txt'));

        $approved = new \core_privacy\local\request\approved_contextlist(
            $user,
            'local_qualiscope',
            [\context_system::instance()->id, \context_course::instance($course->id)->id]
        );

        provider::delete_data_for_user($approved);

        $this->assertFalse($DB->record_exists('local_qualiscope_evidences', ['id' => $ids['evidence']]));
        $this->assertFalse($fs->get_file($context->id, 'local_qualiscope', 'evidence', $ids['result'], '/', 'proof.txt'));
    }

    /**
     * Test the course context purge only touches the data of that course.
     *
     * @return void
     */
    public function test_delete_data_for_all_users_in_course_context(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $this->insertdata($user, $course1);
        $this->insertdata($user, $course2);

        $fs = get_file_storage();
        $context1 = \context_course::instance($course1->id);

        provider::delete_data_for_all_users_in_context($context1);

        $this->assertEquals(0, $DB->count_records('local_qualiscope_results', ['courseid' => $course1->id]));
        $this->assertEquals(0, $DB->count_records('local_qualiscope_actions', ['courseid' => $course1->id]));
        $this->assertEquals(
            0,
            $DB->count_records_sql(
                'SELECT COUNT(e.id)
                   FROM {local_qualiscope_evidences} e
                   JOIN {local_qualiscope_results} r ON r.id = e.result_id
                  WHERE r.courseid = :courseid',
                ['courseid' => $course1->id]
            )
        );
        $this->assertEmpty($fs->get_area_files($context1->id, 'local_qualiscope', 'evidence', 0, 'filename'));

        // The other course and the campaigns must survive.
        $this->assertEquals(1, $DB->count_records('local_qualiscope_results', ['courseid' => $course2->id]));
        $this->assertEquals(1, $DB->count_records('local_qualiscope_actions', ['courseid' => $course2->id]));
        $this->assertEquals(2, $DB->count_records('local_qualiscope_campaigns'));
    }

    /**
     * Test the system context purge removes every campaign and its results.
     *
     * @return void
     */
    public function test_delete_data_for_all_users_in_system_context(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->insertdata($user, $course);

        $fs = get_file_storage();
        $context = \context_course::instance($course->id);

        provider::delete_data_for_all_users_in_context(\context_system::instance());

        $this->assertEquals(0, $DB->count_records('local_qualiscope_campaigns'));
        $this->assertEquals(0, $DB->count_records('local_qualiscope_results'));
        $this->assertEquals(0, $DB->count_records('local_qualiscope_evidences'));
        $this->assertEquals(0, $DB->count_records('local_qualiscope_actions'));
        $this->assertEmpty($fs->get_area_files($context->id, 'local_qualiscope', 'evidence', 0, 'filename'));
    }

    /**
     * Test get_users_in_context in a course context.
     *
     * @return void
     */
    public function test_get_users_in_course_context(): void {
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->insertdata($user1, $course);
        $this->insertdata($user2, $course);

        $userlist = new \core_privacy\local\request\userlist(
            \context_course::instance($course->id),
            'local_qualiscope'
        );
        provider::get_users_in_context($userlist);

        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], $userlist->get_userids());
    }

    /**
     * Test get_users_in_context in the system context.
     *
     * @return void
     */
    public function test_get_users_in_system_context(): void {
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->insertdata($user1, $course);
        $this->insertdata($user2, $course);

        $userlist = new \core_privacy\local\request\userlist(
            \context_system::instance(),
            'local_qualiscope'
        );
        provider::get_users_in_context($userlist);

        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], $userlist->get_userids());
    }

    /**
     * Test delete_data_for_users in a course context.
     *
     * @return void
     */
    public function test_delete_data_for_users_in_course_context(): void {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->insertdata($user1, $course);
        $this->insertdata($user2, $course);

        $userlist = new \core_privacy\local\request\approved_userlist(
            \context_course::instance($course->id),
            'local_qualiscope',
            [$user1->id]
        );
        provider::delete_data_for_users($userlist);

        $this->assertFalse($DB->record_exists('local_qualiscope_evidences', ['userid' => $user1->id]));
        $this->assertFalse($DB->record_exists('local_qualiscope_actions', ['userid' => $user1->id]));
        $this->assertTrue($DB->record_exists('local_qualiscope_evidences', ['userid' => $user2->id]));
        $this->assertTrue($DB->record_exists('local_qualiscope_actions', ['userid' => $user2->id]));
    }

    /**
     * Test delete_data_for_users in the system context.
     *
     * @return void
     */
    public function test_delete_data_for_users_in_system_context(): void {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->insertdata($user1, $course);
        $this->insertdata($user2, $course);

        $userlist = new \core_privacy\local\request\approved_userlist(
            \context_system::instance(),
            'local_qualiscope',
            [$user1->id]
        );
        provider::delete_data_for_users($userlist);

        $this->assertFalse($DB->record_exists('local_qualiscope_campaigns', ['userid' => $user1->id]));
        $this->assertTrue($DB->record_exists('local_qualiscope_campaigns', ['userid' => $user2->id]));
        $this->assertEquals(1, $DB->count_records('local_qualiscope_results'));
        $this->assertEquals(1, $DB->count_records('local_qualiscope_evidences'));
        $this->assertEquals(1, $DB->count_records('local_qualiscope_actions'));
    }
}
