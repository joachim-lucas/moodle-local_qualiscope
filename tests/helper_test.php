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
 * Helper tests for local_qualiscope.
 *
 * @package    local_qualiscope
 * @category   test
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qualiscope\tests;

use local_qualiscope\helper;

/**
 * Helper testcase.
 *
 * @package local_qualiscope
 */
final class helper_test extends \advanced_testcase {
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
     * Test the multilang markup is resolved to the current language.
     *
     * @covers \local_qualiscope\helper::plain
     * @return void
     */
    public function test_plain_resolves_multilang_for_current_language(): void {
        $raw = '<span lang="en" class="multilang">Test course</span>' .
            '<span lang="fr" class="multilang">Cours de test</span>';

        $this->assertSame('Test course', helper::plain($raw));

        $this->assertSame('Cours de test', $this->plaininlang($raw, 'fr'));
        $this->assertSame('Test course', $this->plaininlang($raw, 'en'));
    }

    /**
     * Test the translation of the current language is used even when the
     * multilang filter is disabled in the context.
     *
     * @covers \local_qualiscope\helper::plain
     * @return void
     */
    public function test_plain_resolves_multilang_with_disabled_filter(): void {
        filter_set_global_state('multilang', \TEXTFILTER_DISABLED);
        \filter_manager::reset_caches();

        $raw = '<span lang="en" class="multilang">Test course</span>' .
            '<span lang="fr" class="multilang">Cours de test</span>';

        $this->assertSame('Cours de test', $this->plaininlang($raw, 'fr'));
    }

    /**
     * Runs helper::plain() in a given language.
     *
     * force_current_language() is a no-op for a language pack that is not
     * installed, which is the case of the French pack in the test
     * environment, so the session is set directly instead.
     *
     * @param string $value Raw value.
     * @param string $lang Language code to run in.
     * @return string
     */
    private function plaininlang(string $value, string $lang): string {
        global $SESSION;

        $previous = $SESSION->forcelang ?? null;
        $SESSION->forcelang = $lang;
        try {
            return helper::plain($value);
        } finally {
            $SESSION->forcelang = $previous;
        }
    }

    /**
     * Test a name saved with a single multilang span keeps its text.
     *
     * @covers \local_qualiscope\helper::plain
     * @return void
     */
    public function test_plain_keeps_single_span_text(): void {
        $this->assertSame(
            'TESTS02',
            helper::plain('<span lang="fr" class="multilang">TESTS02</span>')
        );
    }

    /**
     * Test remaining markup and entities are removed.
     *
     * @covers \local_qualiscope\helper::plain
     * @return void
     */
    public function test_plain_strips_markup_and_entities(): void {
        $this->assertSame('R&D team', helper::plain('<b>R&amp;D</b> team'));
    }

    /**
     * Test an empty value stays empty.
     *
     * @covers \local_qualiscope\helper::plain
     * @return void
     */
    public function test_plain_handles_empty_values(): void {
        $this->assertSame('', helper::plain(null));
        $this->assertSame('', helper::plain(''));
    }
}
