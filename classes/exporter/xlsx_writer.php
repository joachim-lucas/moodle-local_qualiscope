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
 * QualiScope Xlsx Writer class.
 *
 * @package    local_qualiscope
 * @copyright  2026 QualiScope contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_qualiscope\exporter;


/**
 * Thin adapter over core's MoodleExcelWorkbook, which wraps PhpSpreadsheet.
 *
 * Core writes the workbook straight to php://output, so no working file is
 * created in the system temp directory and Moodle never has to clean one up.
 * This class only keeps the row buffering and the title sanitation that the
 * export screens rely on.
 *
 * @package local_qualiscope
 */
class xlsx_writer {
    /** @var \MoodleExcelWorkbook The underlying core workbook. */
    private $workbook;

    /** @var \MoodleExcelWorksheet The single sheet every row is written to. */
    private $worksheet;

    /** @var \MoodleExcelFormat Bold style applied to the header rows. */
    private $headerformat;

    /** @var int Zero indexed row the next add_row() call writes to. */
    private $rowindex = 0;

    /**
     * Constructor.
     *
     * @param string $title Sheet title (max 31 chars, no forbidden characters).
     * @param array $widths Optional per-column widths.
     */
    public function __construct(string $title = 'Rapport', array $widths = []) {
        global $CFG;

        require_once($CFG->libdir . '/excellib.class.php');

        $this->workbook = new \MoodleExcelWorkbook('qualiscope.xlsx');
        $this->worksheet = $this->workbook->add_worksheet(self::clean_sheet_title($title));
        $this->headerformat = $this->workbook->add_format(['bold' => 1]);

        foreach (array_values($widths) as $column => $width) {
            $this->worksheet->set_column($column, $column, (float) $width);
        }
    }

    /**
     * Add a row of values.
     *
     * @param array $values
     * @param bool $header Whether the row should use the bold header style.
     */
    public function add_row(array $values, bool $header = false): void {
        $format = $header ? $this->headerformat : null;
        $column = 0;

        foreach ($values as $value) {
            if ($value === null) {
                $this->worksheet->write_blank($this->rowindex, $column, $format);
            } else if (is_int($value) || is_float($value)) {
                $this->worksheet->write_number($this->rowindex, $column, $value, $format);
            } else if (is_bool($value)) {
                $this->worksheet->write($this->rowindex, $column, $value ? '1' : '0', $format);
            } else {
                $this->worksheet->write($this->rowindex, $column, (string) $value, $format);
            }
            $column++;
        }

        $this->rowindex++;
    }

    /**
     * Sends the workbook to the browser as a download and ends the response.
     *
     * Core owns the response from here: it sets the content type, the
     * attachment disposition and the cache headers itself.
     *
     * @param string $filename Download file name, with or without the .xlsx suffix.
     */
    public function send(string $filename): void {
        $this->workbook->send($filename);
        $this->workbook->close();
    }

    /**
     * Cleans a sheet title for XLSX constraints (max 31 chars, no forbidden characters).
     *
     * @param string $title Raw title.
     * @return string
     */
    private static function clean_sheet_title(string $title): string {
        $title = preg_replace('/[\[\]:*?\/\\\\]/', ' ', $title);
        $title = preg_replace('/\s+/', ' ', $title);
        $title = \core_text::substr(trim($title), 0, 31);
        return $title === '' ? 'Rapport' : $title;
    }
}
