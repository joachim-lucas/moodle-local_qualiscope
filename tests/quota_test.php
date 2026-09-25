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
 * Quota tests for local_qualiscope.
 *
 * @package    local_qualiscope
 * @category   test
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qualiscope\tests;

use local_qualiscope\license;
use local_qualiscope\quota;

/**
 * Free plan quota testcase.
 *
 * @package local_qualiscope
 */
final class quota_test extends \advanced_testcase {
    /**
     * Secret key used to sign licence keys in the licensed scenarios.
     *
     * @var string
     */
    private string $secretkey;

    /**
     * Set up a known keypair and override the plugin public key.
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $keypair = sodium_crypto_sign_keypair();
        $this->secretkey = sodium_crypto_sign_secretkey($keypair);
        set_config('licensepublickey', bin2hex(sodium_crypto_sign_publickey($keypair)), 'local_qualiscope');
        set_config(license::CONFIG_KEY, '', 'local_qualiscope');
    }

    /**
     * Build a valid licence key for the current site.
     *
     * @return string The licence key.
     */
    private function validkey(): string {
        $payload = json_encode([
            'v' => 1,
            'site' => license::site_hash(),
            'issue' => time(),
            'expiry' => time() + YEARSECS,
        ], JSON_UNESCAPED_SLASHES);
        $sig = sodium_crypto_sign_detached($payload, $this->secretkey);
        return license::b64u_encode($payload) . '.' . license::b64u_encode($sig);
    }

    /**
     * Create a course and return its id.
     *
     * @return int Course id.
     */
    private function createcourse(): int {
        return (int) $this->getDataGenerator()->create_course()->id;
    }

    /**
     * Test that the free plan allows exactly FREE_COURSES distinct courses.
     *
     * @covers \local_qualiscope\quota::can_audit
     * @covers \local_qualiscope\quota::record
     * @covers \local_qualiscope\quota::max_courses
     * @return void
     */
    public function test_free_plan_allows_exactly_quota_then_blocks(): void {
        $this->assertSame(quota::FREE_COURSES, quota::max_courses());

        $ids = [];
        for ($i = 0; $i < quota::FREE_COURSES; $i++) {
            $ids[] = $this->createcourse();
        }

        foreach ($ids as $id) {
            $this->assertTrue(quota::can_audit($id));
            quota::record($id);
        }

        $this->assertSame(quota::FREE_COURSES, quota::used());
        $this->assertSame(0, quota::remaining());

        $extracourse = $this->createcourse();
        $this->assertFalse(quota::can_audit($extracourse));
    }

    /**
     * Test that an already audited course stays auditable beyond the quota.
     *
     * @covers \local_qualiscope\quota::can_audit
     * @return void
     */
    public function test_already_audited_course_is_reexempt(): void {
        $ids = [];
        for ($i = 0; $i < quota::FREE_COURSES; $i++) {
            $ids[] = $this->createcourse();
            quota::record($ids[$i]);
        }

        foreach ($ids as $id) {
            $this->assertTrue(quota::can_audit($id));
        }

        $newcourse = $this->createcourse();
        $this->assertFalse(quota::can_audit($newcourse));
    }

    /**
     * Test that recording is idempotent.
     *
     * @covers \local_qualiscope\quota::record
     * @covers \local_qualiscope\quota::used
     * @return void
     */
    public function test_record_is_idempotent(): void {
        $id = $this->createcourse();

        quota::record($id);
        quota::record($id);
        quota::record($id);

        $this->assertSame(1, quota::used());
    }

    /**
     * Test that a valid licence removes the quota limit.
     *
     * @covers \local_qualiscope\quota::is_licensed
     * @covers \local_qualiscope\quota::max_courses
     * @covers \local_qualiscope\quota::can_audit
     * @return void
     */
    public function test_licensed_site_is_unlimited(): void {
        set_config(license::CONFIG_KEY, $this->validkey(), 'local_qualiscope');

        $this->assertTrue(quota::is_licensed());
        $this->assertNull(quota::max_courses());
        $this->assertNull(quota::remaining());

        $ids = [];
        for ($i = 0; $i < quota::FREE_COURSES + 10; $i++) {
            $id = $this->createcourse();
            $ids[] = $id;
            $this->assertTrue(quota::can_audit($id));
            quota::record($id);
        }

        $this->assertSame(quota::FREE_COURSES + 10, quota::used());
    }

    /**
     * Test remaining count decreases as courses get audited.
     *
     * @covers \local_qualiscope\quota::remaining
     * @return void
     */
    public function test_remaining_decreases(): void {
        $this->assertSame(quota::FREE_COURSES, quota::remaining());

        for ($i = 0; $i < 2; $i++) {
            quota::record($this->createcourse());
        }

        $this->assertSame(quota::FREE_COURSES - 2, quota::remaining());
    }
}
