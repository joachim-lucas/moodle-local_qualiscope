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

namespace local_qualiscope;

/**
 * Parses the due date posted by the corrective action forms.
 *
 * The action forms use an HTML date input, which always submits an ISO
 * Y-m-d value. That has to be checked rather than handed to strtotime(),
 * which would happily accept anything from "next friday" to a bare year
 * and silently store a surprising timestamp.
 *
 * There is no PARAM_* type for a calendar date, so the form posts are still
 * read as PARAM_RAW at the HTTP boundary. The value is validated here rather
 * than being widened into a date guess. The web service, whose contract is a
 * timestamp, declares PARAM_INT instead.
 *
 * @package local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class action_duedate {
    /**
     * Converts a posted due date into the timestamp stored in the database.
     *
     * An empty value means "no due date" and yields 0. Anything that is not a
     * real calendar date in Y-m-d form is rejected instead of being guessed.
     *
     * @param string $raw Raw value of the date input.
     * @return int The due date as a timestamp, 0 when there is none.
     * @throws \moodle_exception When the value is not a valid Y-m-d date.
     */
    public static function from_input(string $raw): int {
        $raw = trim($raw);

        if ($raw === '') {
            return 0;
        }

        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $matches)) {
            throw new \moodle_exception('invaliddata', 'error');
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];

        if (!checkdate($month, $day, $year)) {
            throw new \moodle_exception('invaliddata', 'error');
        }

        // Midnight of that day, in the timezone of the user filling the form.
        return make_timestamp($year, $month, $day);
    }
}
