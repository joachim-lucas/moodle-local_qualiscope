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
 * XLSX writer tests for local_qualiscope.
 *
 * @package    local_qualiscope
 * @category   test
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_qualiscope\tests;

use local_qualiscope\exporter\xlsx_writer;

/**
 * XSLX writer testcase.
 *
 * @package local_qualiscope
 */
final class xlsx_writer_test extends \advanced_testcase {
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
     * Test get_bytes produces a readable xlsx archive with the row values.
     *
     * @covers \local_qualiscope\exporter\xlsx_writer::get_bytes
     * @covers \local_qualiscope\exporter\xlsx_writer::add_row
     * @return void
     */
    public function test_get_bytes_valid_workbook(): void {
        $writer = new xlsx_writer('Rapport', [30, 50]);
        $writer->add_row(['Titre', 'Description'], true);
        $writer->add_row(['Cours A', 'Un cours de test']);

        $bytes = $writer->get_bytes();

        $this->assertNotEmpty($bytes);

        $tempdir = make_temp_directory('qualiscope_unit');
        $file = $tempdir . '/workbook.xlsx';
        file_put_contents($file, $bytes);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($file));
        $this->assertNotFalse($zip->getStream('[Content_Types].xml'));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($file);

        $this->assertStringContainsString('Un cours de test', $sheet);
    }

    /**
     * Test the constructor sanitises illegal sheet title characters.
     *
     * @covers \local_qualiscope\exporter\xlsx_writer::__construct
     * @return void
     */
    public function test_constructor_sanitises_title(): void {
        $writer = new xlsx_writer('Bad:title*with?chars', []);

        $bytes = $writer->get_bytes();

        $this->assertNotEmpty($bytes);
    }
}
