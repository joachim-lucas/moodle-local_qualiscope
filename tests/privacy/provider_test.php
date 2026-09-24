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
     * Insert a minimal campaign row for the given user.
     *
     * @param int $userid The user id.
     * @return int The record id.
     */
    private function insertcampaign(int $userid): int {
        global $DB;

        $time = time();
        return $DB->insert_record('local_qualiscope_campaigns', [
            'name' => 'Audit test',
            'referential_id' => 0,
            'scope' => 'all',
            'scopeids' => '',
            'userid' => $userid,
            'timecreated' => $time,
            'timemodified' => $time,
            'timecompleted' => 0,
        ]);
    }

    /**
     * Insert a minimal evidence row for the given user.
     *
     * @param int $userid The user id.
     * @return int The record id.
     */
    private function insertevidence(int $userid): int {
        global $DB;

        $time = time();
        return $DB->insert_record('local_qualiscope_evidences', [
            'result_id' => 0,
            'type' => 'external',
            'source' => '',
            'title' => 'Proof',
            'description' => '',
            'filepath' => '',
            'filename' => '',
            'externalurl' => '',
            'annotation' => 'Annotation',
            'userid' => $userid,
            'timecreated' => $time,
            'timemodified' => $time,
        ]);
    }

    /**
     * Insert a minimal action row for the given user.
     *
     * @param int $userid The user id.
     * @return int The record id.
     */
    private function insertaction(int $userid): int {
        global $DB;

        $time = time();
        return $DB->insert_record('local_qualiscope_actions', [
            'result_id' => 0,
            'campaign_id' => 0,
            'courseid' => 0,
            'title' => 'Action',
            'description' => '',
            'responsible' => '',
            'duedate' => 0,
            'priority' => 'medium',
            'status' => 'todo',
            'userid' => $userid,
            'timecreated' => $time,
            'timemodified' => $time,
            'timeclosed' => 0,
        ]);
    }

    /**
     * Test get_metadata lists the three user-facing tables.
     *
     * @covers \local_qualiscope\privacy\provider::get_metadata
     * @return void
     */
    public function test_get_metadata(): void {
        $collection = new \core_privacy\local\metadata\collection('local_qualiscope');
        $collection = provider::get_metadata($collection);

        $tables = $collection->get_collection();
        $this->assertCount(3, $tables);

        $names = array_map(function ($table) {
            return $table->get_name();
        }, $tables);
        $this->assertContains('local_qualiscope_campaigns', $names);
        $this->assertContains('local_qualiscope_evidences', $names);
        $this->assertContains('local_qualiscope_actions', $names);
    }

    /**
     * Test get_contexts_for_userid with no stored data.
     *
     * @covers \local_qualiscope\privacy\provider::get_contexts_for_userid
     * @return void
     */
    public function test_get_contexts_for_userid_without_data(): void {
        $user = $this->getDataGenerator()->create_user();

        $contextlist = provider::get_contexts_for_userid($user->id);

        $this->assertEmpty($contextlist->get_contextids());
    }

    /**
     * Test get_contexts_for_userid with stored data.
     *
     * @covers \local_qualiscope\privacy\provider::get_contexts_for_userid
     * @return void
     */
    public function test_get_contexts_for_userid_with_data(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->insertevidence($user->id);

        $contextlist = provider::get_contexts_for_userid($user->id);

        $this->assertContains(\context_system::instance()->id, $contextlist->get_contextids());
    }

    /**
     * Test export_user_data exports the stored rows.
     *
     * @covers \local_qualiscope\privacy\provider::export_user_data
     * @return void
     */
    public function test_export_user_data(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->insertcampaign($user->id);
        $this->insertevidence($user->id);
        $this->insertaction($user->id);

        $approved = new \core_privacy\local\request\approved_contextlist(
            $user,
            'local_qualiscope',
            [\context_system::instance()->id]
        );

        provider::export_user_data($approved);

        $writer = \core_privacy\local\request\writer::with_context(\context_system::instance());
        $this->assertTrue($writer->has_any_data());
    }

    /**
     * Test delete_data_for_user removes the rows of that user only.
     *
     * @covers \local_qualiscope\privacy\provider::delete_data_for_user
     * @return void
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->insertcampaign($user1->id);
        $this->insertevidence($user1->id);
        $this->insertaction($user1->id);
        $this->insertcampaign($user2->id);

        $approved = new \core_privacy\local\request\approved_contextlist(
            $user1,
            'local_qualiscope',
            [\context_system::instance()->id]
        );

        provider::delete_data_for_user($approved);

        $this->assertFalse($DB->record_exists('local_qualiscope_campaigns', ['userid' => $user1->id]));
        $this->assertFalse($DB->record_exists('local_qualiscope_evidences', ['userid' => $user1->id]));
        $this->assertFalse($DB->record_exists('local_qualiscope_actions', ['userid' => $user1->id]));
        $this->assertTrue($DB->record_exists('local_qualiscope_campaigns', ['userid' => $user2->id]));
    }

    /**
     * Test delete_data_for_all_users_in_context purges everything.
     *
     * @covers \local_qualiscope\privacy\provider::delete_data_for_all_users_in_context
     * @return void
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->insertcampaign($user->id);
        $this->insertevidence($user->id);
        $this->insertaction($user->id);

        provider::delete_data_for_all_users_in_context(\context_system::instance());

        $this->assertEquals(0, $DB->count_records('local_qualiscope_campaigns'));
        $this->assertEquals(0, $DB->count_records('local_qualiscope_evidences'));
        $this->assertEquals(0, $DB->count_records('local_qualiscope_actions'));
    }
}
