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
 * Check tests for local_qualiscope.
 *
 * @package    local_qualiscope
 * @category   test
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qualiscope\tests;

use local_qualiscope\checks\alignment_check;
use local_qualiscope\checks\competency_check;

/**
 * Check testcase.
 *
 * A course summary is nullable while stripos() and strip_tags() deprecate on
 * null since PHP 8.1, so every check reading it has to guard first.
 *
 * @package local_qualiscope
 * @covers \local_qualiscope\checks\competency_check
 * @covers \local_qualiscope\checks\alignment_check
 */
final class checks_test extends \advanced_testcase {
    /** @var \stdClass The course under audit. */
    private $course;

    /**
     * Set up.
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        $this->course = $this->getDataGenerator()->create_course();

        global $DB;
        // The generator fills a summary, the point of these tests is the nullable case.
        $DB->set_field('course', 'summary', null, ['id' => $this->course->id]);
    }

    /**
     * Returns the first check record of the given type.
     *
     * A type is declared once per referential that uses it, and the analyser only
     * needs any of them to exercise the check.
     *
     * @param string $type Check type.
     * @return \stdClass The check record.
     */
    private function check(string $type): \stdClass {
        global $DB;

        $records = $DB->get_records('local_qualiscope_checks', ['type' => $type], 'id ASC', '*', 0, 1);

        return reset($records);
    }

    /**
     * Test the competency check reads a null course summary without deprecating.
     *
     * @return void
     */
    public function test_competency_check_accepts_a_null_summary(): void {
        $result = (new competency_check())->execute((int) $this->course->id, $this->check('competency_exists'));

        $this->assertSame('verify', $result['status']);
        $this->assertNotEmpty($result['detail']);
    }

    /**
     * Test the alignment check reads a null course summary without deprecating.
     *
     * @return void
     */
    public function test_alignment_check_accepts_a_null_summary(): void {
        $result = (new alignment_check())->execute((int) $this->course->id, $this->check('alignment_exists'));

        $this->assertContains($result['status'], ['detected', 'verify', 'missing', 'na']);
        $this->assertNotEmpty($result['detail']);
    }

    /**
     * Test a summary mentioning the objectives is still detected.
     *
     * Guards against a cast that would silently drop a real summary.
     *
     * @return void
     */
    public function test_alignment_check_still_reads_a_real_summary(): void {
        global $DB;

        $DB->set_field(
            'course',
            'summary',
            'Programme et objectifs de la formation',
            ['id' => $this->course->id]
        );

        $result = (new alignment_check())->execute((int) $this->course->id, $this->check('alignment_exists'));
        $competency = (new competency_check())->execute((int) $this->course->id, $this->check('competency_exists'));

        // The alignment detail concatenates one entry per scored section.
        $this->assertStringContainsString(
            get_string('alignment_competencies_textual', 'local_qualiscope'),
            $result['detail']
        );
        $this->assertSame(
            get_string('check_competency_in_summary', 'local_qualiscope'),
            $competency['detail']
        );
    }

    /**
     * Test the rich text collector skips a null summary instead of warning on it.
     *
     * @return void
     */
    public function test_rich_text_contents_skip_null_summaries(): void {
        global $DB;

        $DB->set_field('course_sections', 'summary', null, ['course' => $this->course->id]);

        $contents = \local_qualiscope\analyser\accessibility_analyser::get_course_rich_text_contents(
            (int) $this->course->id
        );

        $sources = array_column($contents, 'source');
        $this->assertNotContains('course_summary', $sources);
        $this->assertNotContains('section_summary', $sources);
    }
}
