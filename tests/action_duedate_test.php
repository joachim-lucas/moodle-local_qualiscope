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

    /**
     * Test a missing due date prefills an empty input.
     *
     * @covers \local_qualiscope\action_duedate::to_input
     * @return void
     */
    public function test_no_due_date_gives_an_empty_input(): void {
        $this->assertSame('', action_duedate::to_input(0));
    }

    /**
     * Test the day is zero padded, which userdate() does not do by default.
     *
     * userdate() strips the leading zero of %d unless $fixday is false. A date
     * input would reject 2026-10-1, and from_input() throws on it as well, so the
     * recommendation of userdate($duedate, '%Y-%m-%d') cannot be followed as it
     * stands.
     *
     * @covers \local_qualiscope\action_duedate::to_input
     * @return void
     */
    public function test_day_is_zero_padded(): void {
        $this->resetAfterTest(true);
        set_config('timezone', 'Europe/Paris');

        $user = $this->getDataGenerator()->create_user(['timezone' => '99']);
        $this->setUser($user);

        foreach (['2026-10-01', '2026-01-05', '2026-12-09'] as $day) {
            $value = action_duedate::to_input(action_duedate::from_input($day));
            $this->assertSame($day, $value);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $value);
        }
    }

    /**
     * Test a user east of the server used to see the day before, and to lose a day per save.
     *
     * The input was prefilled with date(), which formats in the timezone of the
     * server, while from_input() stores midnight in the timezone of the user. A
     * user east of the server therefore read a day early, and saving that value
     * back walked the due date one day earlier on every save.
     *
     * @covers \local_qualiscope\action_duedate::to_input
     * @covers \local_qualiscope\action_duedate::from_input
     * @return void
     */
    public function test_user_east_of_the_server_keeps_the_day_and_stops_drifting(): void {
        $this->resetAfterTest(true);
        set_config('timezone', 'Europe/Paris');

        $user = $this->getDataGenerator()->create_user(['timezone' => 'Asia/Tokyo']);
        $this->setUser($user);

        $stored = action_duedate::from_input('2026-10-01');

        // The server timezone does read it as the day before, which is the defect.
        $this->assertSame('2026-09-30', date('Y-m-d', $stored));

        // The form shows the day that was picked, and saving it back is a no-op.
        $this->assertSame('2026-10-01', action_duedate::to_input($stored));
        $this->assertSame($stored, action_duedate::from_input(action_duedate::to_input($stored)));

        // Reformatting as many times as the action gets saved never moves the date.
        $timestamp = $stored;
        for ($i = 0; $i < 5; $i++) {
            $timestamp = action_duedate::from_input(action_duedate::to_input($timestamp));
        }
        $this->assertSame($stored, $timestamp);
    }

    /**
     * Test the round trip holds for a user west of the server too.
     *
     * @covers \local_qualiscope\action_duedate::to_input
     * @covers \local_qualiscope\action_duedate::from_input
     * @return void
     */
    public function test_user_west_of_the_server_keeps_the_day(): void {
        $this->resetAfterTest(true);
        set_config('timezone', 'Europe/Paris');

        $user = $this->getDataGenerator()->create_user(['timezone' => 'America/Los_Angeles']);
        $this->setUser($user);

        $stored = action_duedate::from_input('2026-10-01');
        $this->assertSame('2026-10-01', action_duedate::to_input($stored));
        $this->assertSame($stored, action_duedate::from_input(action_duedate::to_input($stored)));
    }

    /**
     * Test the round trip holds for a user following the server timezone.
     *
     * @covers \local_qualiscope\action_duedate::to_input
     * @covers \local_qualiscope\action_duedate::from_input
     * @return void
     */
    public function test_user_on_the_server_timezone_keeps_the_day(): void {
        $this->resetAfterTest(true);
        set_config('timezone', 'Europe/Paris');

        $user = $this->getDataGenerator()->create_user(['timezone' => '99']);
        $this->setUser($user);

        $stored = action_duedate::from_input('2026-10-01');
        $this->assertSame('2026-10-01', action_duedate::to_input($stored));
        $this->assertSame($stored, action_duedate::from_input(action_duedate::to_input($stored)));
    }

    /**
     * Test the round trip survives a daylight saving change between the dates.
     *
     * Europe/Paris moves on the last Sunday of March, so a due date before and one
     * after the switch are stored with different UTC offsets while the local
     * midnight stays the same hour of the day.
     *
     * @covers \local_qualiscope\action_duedate::to_input
     * @covers \local_qualiscope\action_duedate::from_input
     * @return void
     */
    public function test_round_trip_across_a_daylight_saving_change(): void {
        $this->resetAfterTest(true);
        set_config('timezone', 'Europe/Paris');

        $user = $this->getDataGenerator()->create_user(['timezone' => 'Europe/Paris']);
        $this->setUser($user);

        foreach (['2026-03-28', '2026-03-30', '2026-10-24', '2026-10-26'] as $day) {
            $stored = action_duedate::from_input($day);
            $this->assertSame($day, action_duedate::to_input($stored));
            $this->assertSame($stored, action_duedate::from_input(action_duedate::to_input($stored)));
        }
    }
}
