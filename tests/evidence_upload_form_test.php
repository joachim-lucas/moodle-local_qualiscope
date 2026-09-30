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
 * Evidence upload form tests for local_qualiscope.
 *
 * @package    local_qualiscope
 * @category   test
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qualiscope\tests;

use local_qualiscope\form\evidence_upload;

/**
 * Evidence upload form testcase.
 *
 * @package local_qualiscope
 */
final class evidence_upload_form_test extends \advanced_testcase {
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
     * Builds the form, exposing the clean type moodleform resolved for a field.
     *
     * getCleanType() lives on the inner quickform, and only the form itself
     * can reach it, hence the anonymous subclass.
     *
     * @param string $field Field name.
     * @param mixed $value Value that would be submitted for it.
     * @return string The PARAM_* constant the form cleans the field with.
     */
    private function clean_type(string $field, $value): string {
        $form = new class ('/local/qualiscope/upload_evidence.php', [
            'resultid' => 1,
            'maxbytes' => 1024,
        ]) extends evidence_upload {
            /**
             * Returns the clean type the form resolved for one of its fields.
             *
             * @param string $field Field name.
             * @param mixed $value Submitted value.
             * @return string The PARAM_* constant.
             */
            public function cleantype(string $field, $value): string {
                return $this->_form->getCleanType($field, $value);
            }
        };

        return $form->cleantype($field, $value);
    }

    /**
     * Test the annotation is cleaned as plain text, not raw input.
     *
     * A textarea element defaults to PARAM_RAW, which would leave any markup
     * in the stored annotation.
     *
     * @covers \local_qualiscope\form\evidence_upload
     * @return void
     */
    public function test_annotation_is_cleaned_as_plain_text(): void {
        $this->assertEquals(PARAM_TEXT, $this->clean_type('annotation', 'some text'));
    }

    /**
     * Test the result id is still cleaned as an integer.
     *
     * @covers \local_qualiscope\form\evidence_upload
     * @return void
     */
    public function test_resultid_is_cleaned_as_an_integer(): void {
        $this->assertEquals(PARAM_INT, $this->clean_type('resultid', 1));
    }

    /**
     * Test PARAM_TEXT keeps the line breaks an annotation relies on.
     *
     * @coversNothing
     * @return void
     */
    public function test_plain_text_keeps_line_breaks(): void {
        $annotation = "First line\nSecond line";

        $this->assertEquals($annotation, clean_param($annotation, PARAM_TEXT));
    }

    /**
     * Test PARAM_TEXT drops the markup a raw value would have kept.
     *
     * @coversNothing
     * @return void
     */
    public function test_plain_text_drops_markup(): void {
        $this->assertEquals(
            'bold &amp; plain',
            clean_param('<b>bold</b> &amp; <script>plain</script>', PARAM_TEXT)
        );
    }
}
