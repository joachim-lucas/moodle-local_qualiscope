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
 * Language string tests for local_qualiscope.
 *
 * @package    local_qualiscope
 * @category   test
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qualiscope\tests;

/**
 * Language string testcase.
 *
 * The auditor dossier, the PDF report and the accessibility item titles used
 * to be written as literals, which left them untranslatable. These tests pin
 * the strings those screens build so a missing key cannot silently ship.
 *
 * @package local_qualiscope
 * @coversNothing
 */
final class lang_strings_test extends \advanced_testcase {
    /**
     * Strings the ZIP dossier, the PDF report and the accessibility titles are built from.
     *
     * @return array[] The string key and the placeholder it is called with.
     */
    public static function screen_string_provider(): array {
        return [
            'dossier title' => ['export_zip_readme_title', 'Qualiopi'],
            'dossier course' => ['export_zip_readme_course', ['fullname' => 'Course', 'shortname' => 'C1']],
            'dossier referential' => ['export_zip_readme_referential', ['name' => 'Qualiopi', 'version' => 'V9']],
            'dossier date' => ['export_zip_readme_date', '5 October 2026'],
            'dossier coverage' => ['export_zip_readme_coverage', 54],
            'dossier criteria title' => ['export_zip_readme_criteria_title', null],
            'dossier criterion line' => [
                'export_zip_criterion_line',
                ['number' => 3, 'title' => 'Title', 'percentage' => '42 %'],
            ],
            'dossier footer' => ['export_zip_readme_footer', null],
            'manual only' => ['export_zip_manual_only', null],
            'sheet title' => ['export_zip_sheet_title', 2],
            'sheet title label' => ['export_zip_sheet_title_label', 'A title'],
            'sheet requirement' => ['export_zip_sheet_requirement', 'A requirement'],
            'sheet scope' => ['export_zip_sheet_scope', 'course'],
            'sheet checks title' => ['export_zip_sheet_checks_title', null],
            'no automatic check' => ['export_zip_no_automatic_check', null],
            'check heading' => ['export_zip_check_heading', 'A check'],
            'check description' => ['export_zip_check_description', 'A description'],
            'check status' => ['export_zip_check_status', 'detected'],
            'check ratio' => ['export_zip_check_ratio', 75],
            'check detail' => ['export_zip_check_detail', 'Some detail'],
            'check manual' => ['export_zip_check_manual', null],
            'not available' => ['export_zip_not_available', null],
            'pdf criterion' => ['export_pdf_criterion', ['number' => 3, 'title' => 'Title']],
            'pdf indicator' => ['export_pdf_indicator_short', 3],
            'course title' => ['evidence_course', 'Course'],
            'section title' => ['evidence_section', 2],
            'page title' => ['evidence_page', 'Page 1'],
            'page intro title' => ['evidence_page_intro', 'Page 1'],
            'activity intro' => ['evidence_activity_intro', ['type' => 'Assignment', 'name' => 'Work 1']],
        ];
    }

    /**
     * Test every string of the audit screens resolves, in every language.
     *
     * A missing key renders as [[the_key]] and a placeholder left in place means
     * the caller passed the wrong shape, both are silent failures otherwise.
     *
     * @dataProvider screen_string_provider
     * @param string $key The language string key.
     * @param mixed $a The placeholder the screen passes.
     * @return void
     */
    public function test_screen_string_resolves_in_every_language(string $key, $a): void {
        foreach (['en', 'fr'] as $lang) {
            $string = get_string($key, 'local_qualiscope', $a);

            $this->assertStringNotContainsString(
                '[[' . $key . ']]',
                (string) $string,
                "The string {$key} is missing from the {$lang} language pack."
            );
            $this->assertStringNotContainsString(
                '{$a',
                (string) $string,
                "The string {$key} still shows a placeholder in {$lang}."
            );
            $this->assertNotSame(
                '',
                trim((string) $string),
                "The string {$key} is empty in {$lang}."
            );
        }
    }

    /**
     * Strings that are legitimately the same in both packs.
     *
     * They are pure punctuation or a word French spells like English, so an
     * identical value is the correct translation, not a forgotten one.
     *
     * @var string[]
     */
    private const IDENTICAL_IN_BOTH = [
        // A Markdown heading marker, with no word to translate.
        'export_zip_check_heading',
        // The same abbreviation in both languages.
        'export_pdf_indicator_short',
        // The same word in both languages.
        'evidence_section',
    ];

    /**
     * Test the French pack really differs from the English one.
     *
     * A copy/paste between the two files would otherwise pass every other test.
     *
     * @dataProvider screen_string_provider
     * @param string $key The language string key.
     * @return void
     */
    public function test_french_differs_from_english(string $key): void {
        if (in_array($key, self::IDENTICAL_IN_BOTH, true)) {
            $this->markTestSkipped('The string is deliberately the same in both packs.');
        }

        $this->assertNotSame(
            $this->pack_string('en', $key),
            $this->pack_string('fr', $key),
            "The string {$key} is identical in both packs."
        );
    }

    /**
     * Reads a raw value out of a language pack.
     *
     * The pack is a plain array assignment, so including it is exact and does
     * not depend on how a long value is wrapped in the file.
     *
     * @param string $lang Language pack to read.
     * @param string $key The string key.
     * @return string The raw value as written in the file.
     */
    private function pack_string(string $lang, string $key): string {
        global $CFG;

        $file = "{$CFG->dirroot}/local/qualiscope/lang/{$lang}/local_qualiscope.php";
        $this->assertFileExists($file);

        $string = [];
        include($file);

        $this->assertArrayHasKey($key, $string, "The string {$key} is missing from the {$lang} pack.");

        return $string[$key];
    }
}
