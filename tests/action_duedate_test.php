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
 * Due date parsing tests for local_qualiscope.
 *
 * @package    local_qualiscope
 * @category   test
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qualiscope\tests;

use local_qualiscope\action_duedate;

/**
 * Due date parsing testcase.
 *
 * @package local_qualiscope
 */
final class action_duedate_test extends \advanced_testcase {
    /**
     * Test an empty value means no due date.
     *
     * @covers \local_qualiscope\action_duedate::from_input
     * @return void
     */
    public function test_empty_value_means_no_due_date(): void {
        $this->assertEquals(0, action_duedate::from_input(''));
        $this->assertEquals(0, action_duedate::from_input('   '));
    }

    /**
     * Test a real calendar date is converted to midnight of that day.
     *
     * @covers \local_qualiscope\action_duedate::from_input
     * @return void
     */
    public function test_iso_date_becomes_midnight_of_that_day(): void {
        $this->assertEquals(
            make_timestamp(2026, 10, 1),
            action_duedate::from_input('2026-10-01')
        );
    }

    /**
     * Test surrounding whitespace is tolerated.
     *
     * @covers \local_qualiscope\action_duedate::from_input
     * @return void
     */
    public function test_surrounding_whitespace_is_ignored(): void {
        $this->assertEquals(
            make_timestamp(2026, 10, 1),
            action_duedate::from_input("  2026-10-01\n")
        );
    }

    /**
     * Test a date that does not exist is rejected.
     *
     * strtotime() would have rolled 30 February over to 2 March.
     *
     * @covers \local_qualiscope\action_duedate::from_input
     * @return void
     */
    public function test_impossible_date_is_rejected(): void {
        $this->expectException(\moodle_exception::class);
        action_duedate::from_input('2026-02-30');
    }

    /**
     * Test a leap day that does exist is accepted.
     *
     * @covers \local_qualiscope\action_duedate::from_input
     * @return void
     */
    public function test_real_leap_day_is_accepted(): void {
        $this->assertEquals(
            make_timestamp(2028, 2, 29),
            action_duedate::from_input('2028-02-29')
        );
    }

    /**
     * Test the free text strtotime() used to accept is now rejected.
     *
     * @covers \local_qualiscope\action_duedate::from_input
     * @return void
     */
    public function test_free_text_is_rejected(): void {
        $invalid = ['next friday', 'tomorrow', '2026', '01/10/2026', '2026-10-01T10:00:00', 'oops'];
        foreach ($invalid as $value) {
            try {
                action_duedate::from_input($value);
                $this->fail("Expected '{$value}' to be rejected as a due date.");
            } catch (\moodle_exception $e) {
                $this->assertInstanceOf(\moodle_exception::class, $e);
            }
        }
    }
}
