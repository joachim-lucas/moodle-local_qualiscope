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
 * Evidence tests for local_qualiscope.
 *
 * @package    local_qualiscope
 * @category   test
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qualiscope\tests;

use local_qualiscope\evidence;

/**
 * Evidence testcase.
 *
 * @package local_qualiscope
 * @covers \local_qualiscope\evidence
 */
final class evidence_test extends \advanced_testcase {
    /** @var \stdClass The course the evidences are attached to. */
    private $course;

    /** @var \stdClass The user attaching the evidences. */
    private $user;

    /** @var int The result the evidences document. */
    private $resultid;

    /**
     * Set up.
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        $this->user = $this->getDataGenerator()->create_user();
        $this->course = $this->getDataGenerator()->create_course();

        global $DB;
        $this->resultid = (int) $DB->insert_record('local_qualiscope_results', [
            'campaign_id' => 0,
            'courseid' => $this->course->id,
            'check_id' => 0,
            'indicator_id' => 0,
            'status' => 'missing',
            'ratio' => 0,
            'detail' => '',
            'evidence_count' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Stages a file in a draft area the way the filemanager element does.
     *
     * @param string $filename Name of the file to stage.
     * @param string $content Content of the file.
     * @return int The draft area id.
     */
    private function stage(string $filename, string $content): int {
        $draftitemid = file_get_unused_draft_itemid();

        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => \context_user::instance($this->user->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);

        return $draftitemid;
    }

    /**
     * Returns the names of the files stored for the given evidences.
     *
     * @param \stdClass[] $evidences Evidence rows.
     * @return string[] The stored filenames, sorted.
     */
    private function stored_filenames(array $evidences): array {
        $fs = get_file_storage();

        $names = [];
        foreach ($evidences as $row) {
            $row->courseid = $this->course->id;
            $file = evidence::get_file($fs, $row);
            $names[] = $file === null ? '' : $file->get_filename();
        }

        sort($names);

        return $names;
    }

    /**
     * Builds the cleaned form data an evidence submission produces.
     *
     * @param string $title Evidence title.
     * @param int $draftitemid Draft area id, 0 when no file was picked.
     * @param string $externalurl External URL, empty for a file only evidence.
     * @return \stdClass
     */
    private function submitted(string $title, int $draftitemid, string $externalurl = ''): \stdClass {
        $data = new \stdClass();
        $data->title = $title;
        $data->annotation = '';
        $data->externalurl = $externalurl;
        $data->evidencefile = $draftitemid;

        return $data;
    }

    /**
     * Attaches an evidence the way upload_evidence.php does.
     *
     * @param string $title Evidence title.
     * @param int $draftitemid Draft area id, 0 when no file was picked.
     * @param string $externalurl External URL, empty for a file only evidence.
     * @return int The created evidence id.
     */
    private function attach(string $title, int $draftitemid, string $externalurl = ''): int {
        \core\session\manager::set_user($this->user);

        return evidence::create(
            $this->resultid,
            $this->submitted($title, $draftitemid, $externalurl),
            (int) $this->user->id,
            \context_course::instance($this->course->id),
            1024 * 1024
        );
    }

    /**
     * Test two files attached to the same result both survive.
     *
     * Storing every evidence of a result in the area of the result made
     * file_save_draft_area_files() drop the files it did not recognise, so the second
     * upload deleted the document of the first one.
     *
     * @return void
     */
    public function test_two_files_on_the_same_result_are_both_kept(): void {
        global $DB;

        $first = $this->attach('First proof', $this->stage('first.txt', 'first content'));
        $second = $this->attach('Second proof', $this->stage('second.txt', 'second content'));

        $evidences = $DB->get_records('local_qualiscope_evidences', [
            'result_id' => $this->resultid,
        ], 'id ASC');

        $this->assertCount(2, $evidences);
        $this->assertEquals(['first.txt', 'second.txt'], $this->stored_filenames($evidences));

        // Each file sits in the area of its own evidence.
        $fs = get_file_storage();
        $context = \context_course::instance($this->course->id);
        $this->assertNotFalse($fs->get_file($context->id, 'local_qualiscope', 'evidence', $first, '/', 'first.txt'));
        $this->assertNotFalse($fs->get_file($context->id, 'local_qualiscope', 'evidence', $second, '/', 'second.txt'));
    }

    /**
     * Test a link only evidence does not remove the files attached before it.
     *
     * The draft area of the form is never empty, so the old code saved the area on every
     * submission: an evidence with only a link wiped the documents of the result.
     *
     * @return void
     */
    public function test_link_only_evidence_keeps_the_previous_files(): void {
        global $DB;

        $this->attach('Proof', $this->stage('proof.txt', 'proof content'));
        $this->attach('External source', 0, 'https://example.com/proof');

        $evidences = $DB->get_records('local_qualiscope_evidences', [
            'result_id' => $this->resultid,
        ], 'id ASC');

        $this->assertCount(2, $evidences);
        $this->assertEquals(['', 'proof.txt'], $this->stored_filenames($evidences));

        $fs = get_file_storage();
        $context = \context_course::instance($this->course->id);
        // itemid false is the wildcard; itemid 0 would filter on a literal 0 and match nothing.
        $this->assertCount(
            1,
            $fs->get_area_files($context->id, 'local_qualiscope', 'evidence', false, 'filename', false)
        );
    }

    /**
     * Test a file only evidence records the file name of the submitted draft.
     *
     * @return void
     */
    public function test_file_only_evidence_records_its_filename(): void {
        global $DB;

        $evidenceid = $this->attach('Proof', $this->stage('proof.txt', 'proof content'));

        $record = $DB->get_record('local_qualiscope_evidences', ['id' => $evidenceid], '*', MUST_EXIST);

        $this->assertSame('proof.txt', $record->filename);
        $this->assertSame('/', $record->filepath);
        $this->assertEmpty($record->externalurl);
    }

    /**
     * Test an empty draft area holds no file, so a file only submission can be detected.
     *
     * The draft area id of a filemanager element is never empty, which is why the draft
     * area itself has to be read to tell a file only evidence from an empty one.
     *
     * @return void
     */
    public function test_empty_draft_area_holds_no_file(): void {
        $draftitemid = file_get_unused_draft_itemid();

        $this->assertGreaterThan(0, $draftitemid);
        $this->assertNull(evidence::get_draft_file($draftitemid));
        $this->assertNull(evidence::get_draft_file(0));
    }

    /**
     * Test a staged draft file is found whatever its name.
     *
     * @return void
     */
    public function test_draft_file_is_found(): void {
        $draftitemid = $this->stage('report.pdf', '%PDF-1.4');

        $file = evidence::get_draft_file($draftitemid);

        $this->assertNotNull($file);
        $this->assertSame('report.pdf', $file->get_filename());
    }
}
