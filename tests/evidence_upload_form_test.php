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

    /**
     * Test a file only evidence is accepted without an external URL.
     *
     * A filemanager never fills the $files array of moodleform, so checking it rejected
     * every evidence carrying only a document.
     *
     * @covers \local_qualiscope\form\evidence_upload
     * @return void
     */
    public function test_file_only_evidence_is_valid(): void {
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($user->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => 'proof.pdf',
        ], '%PDF-1.4');

        $errors = $this->validate($draftitemid, '');

        $this->assertArrayNotHasKey('evidencefile', $errors);
    }

    /**
     * Test an external URL alone is enough, even without a document.
     *
     * @covers \local_qualiscope\form\evidence_upload
     * @return void
     */
    public function test_external_url_only_evidence_is_valid(): void {
        $this->resetAfterTest(true);

        $this->setUser($this->getDataGenerator()->create_user());

        $errors = $this->validate(file_get_unused_draft_itemid(), 'https://example.com/proof');

        $this->assertArrayNotHasKey('evidencefile', $errors);
    }

    /**
     * Test an evidence with neither a document nor an external URL is rejected.
     *
     * @covers \local_qualiscope\form\evidence_upload
     * @return void
     */
    public function test_empty_evidence_is_rejected(): void {
        $this->resetAfterTest(true);

        $this->setUser($this->getDataGenerator()->create_user());

        // The draft area id is never empty, so an empty area has to be refused.
        $errors = $this->validate(file_get_unused_draft_itemid(), '');

        $this->assertArrayHasKey('evidencefile', $errors);
        $this->assertSame(
            get_string('upload_evidence_required', 'local_qualiscope'),
            $errors['evidencefile']
        );
    }

    /**
     * Runs the form validation on a submission of the evidencefile and externalurl fields.
     *
     * @param int $draftitemid Submitted draft area id.
     * @param string $externalurl Submitted external URL.
     * @return array The validation errors.
     */
    private function validate(int $draftitemid, string $externalurl): array {
        $form = new class ('/local/qualiscope/upload_evidence.php', [
            'resultid' => 1,
            'maxbytes' => 1024,
        ]) extends evidence_upload {
            /**
             * Runs the validation on a submission built by the caller.
             *
             * @param array $data Submitted data.
             * @param array $files Submitted files.
             * @return array The validation errors.
             */
            public function check(array $data, array $files): array {
                return $this->validation($data, $files);
            }
        };

        return $form->check([
            'resultid' => 1,
            'title' => 'Proof',
            'evidencefile' => $draftitemid,
            'externalurl' => $externalurl,
            'annotation' => '',
        ], []);
    }
}
