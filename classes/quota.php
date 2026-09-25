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
 * QualiScope Quota class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_qualiscope;


/**
 * Tracks the free plan quota of audited courses.
 *
 * The free plan allows a fixed number of distinct courses to be audited
 * across the whole site, forever (records are never purged). An active
 * licence removes the limit. A course that has already been audited is
 * always re-auditable for free (the quota only counts distinct courses).
 *
 * @package local_qualiscope
 */
class quota {
    /**
     * Number of courses that can be audited for free.
     *
     * @var int
     */
    public const FREE_COURSES = 5;

    /**
     * Table that records audited course ids.
     *
     * @var string
     */
    public const TABLE = 'local_qualiscope_auditedcourses';

    /**
     * Whether the site has an active licence (limit removed).
     *
     * @return bool True when licensed.
     */
    public static function is_licensed(): bool {
        return license::is_licensed();
    }

    /**
     * Maximum number of auditable courses, or null when licensed (unlimited).
     *
     * @return int|null The maximum, or null when unlimited.
     */
    public static function max_courses(): ?int {
        return self::is_licensed() ? null : self::FREE_COURSES;
    }

    /**
     * Number of distinct courses already audited on this site.
     *
     * @return int The used count.
     */
    public static function used(): int {
        global $DB;
        return (int) $DB->count_records(self::TABLE);
    }

    /**
     * Remaining auditable courses in the free plan, or null when unlimited.
     *
     * @return int|null The remaining count (0 when exhausted), null when licensed.
     */
    public static function remaining(): ?int {
        $max = self::max_courses();
        return $max === null ? null : max(0, $max - self::used());
    }

    /**
     * Whether the given course has already been audited.
     *
     * @param int $courseid Course id.
     * @return bool True when already audited.
     */
    public static function course_audited(int $courseid): bool {
        global $DB;
        return $DB->record_exists(self::TABLE, ['courseid' => $courseid]);
    }

    /**
     * Whether the given course may be audited (within or beyond the quota).
     *
     * @param int $courseid Course id.
     * @return bool True when allowed.
     */
    public static function can_audit(int $courseid): bool {
        if (self::course_audited($courseid)) {
            return true;
        }
        $max = self::max_courses();
        return $max === null || self::used() < $max;
    }

    /**
     * Records that the given course has been audited. Idempotent: a course
     * is only counted once, no matter how many times it is re-analysed.
     *
     * @param int $courseid Course id.
     * @return void
     */
    public static function record(int $courseid): void {
        global $DB;

        if (self::course_audited($courseid)) {
            return;
        }

        $transaction = $DB->start_delegated_transaction();
        if (!self::course_audited($courseid)) {
            $DB->insert_record(self::TABLE, ['courseid' => $courseid, 'timeaudited' => time()]);
        }
        $transaction->allow_commit();
    }

    /**
     * Renders the freemium status banner for the given page renderer.
     * Shows nothing when a valid licence is active.
     *
     * @param \core\output\renderer_base $output Page renderer.
     * @return string The rendered banner HTML (empty when licensed).
     */
    public static function banner(\core\output\renderer_base $output): string {
        if (license::is_licensed()) {
            return '';
        }

        $url = new \moodle_url('/admin/settings.php', ['section' => 'local_qualiscope_settings']);
        $link = is_siteadmin() ? \html_writer::link($url, get_string('licence_activate', 'local_qualiscope')) : '';

        if (self::remaining() === 0) {
            $message = get_string('quota_exhausted', 'local_qualiscope', ['max' => self::FREE_COURSES]) . ' ' . $link;
            return $output->notification($message, \core\output\notification::NOTIFY_ERROR);
        }

        $message = get_string('quota_usage', 'local_qualiscope', [
            'used' => self::used(),
            'max' => self::FREE_COURSES,
        ]) . ' ' . $link;
        return $output->notification($message, \core\output\notification::NOTIFY_WARNING);
    }
}
