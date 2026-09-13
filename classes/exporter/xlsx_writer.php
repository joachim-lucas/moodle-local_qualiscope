<?php

namespace local_qualiscope\exporter;

defined('MOODLE_INTERNAL') || die();

/**
 * Minimal XLSX workbook writer (OGC OOXML) using ZipArchive.
 *
 * Generates a valid .xlsx file without any third-party dependency.
 * Strings are written as inline strings, numbers as numeric cells.
 */
class xlsx_writer {

    /** @var string Sheet title. */
    private $title;

    /** @var array Column widths (float). */
    private $widths;

    /** @var array[] Each row is ['values' => array, 'header' => bool]. */
    private $rows = [];

    /**
     * Constructor.
     *
     * @param string $title Sheet title (max 31 chars, no forbidden characters).
     * @param array $widths Optional per-column widths.
     */
    public function __construct(string $title = 'Rapport', array $widths = []) {
        $this->title = self::clean_sheet_title($title);
        $this->widths = $widths;
    }

    /**
     * Add a row of values.
     *
     * @param array $values
     * @param bool $header Whether the row should use the bold header style.
     */
    public function add_row(array $values, bool $header = false): void {
        $this->rows[] = ['values' => array_values($values), 'header' => $header];
    }

    /**
     * Returns the complete .xlsx file content.
     *
     * @return string
     */
    public function get_bytes(): string {
        $zip = new \ZipArchive();
        $tmp = tempnam(sys_get_temp_dir(), 'qsxlsx');
        if ($tmp === false || !$zip->open($tmp, \ZipArchive::OVERWRITE)) {
            throw new \moodle_exception('xlsx_write_failed', 'local_qualiscope');
        }

        $zip->addFromString('[Content_Types].xml', $this->content_types_xml());
        $zip->addFromString('_rels/.rels', $this->rels_xml());
        $zip->addFromString('xl/workbook.xml', $this->workbook_xml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbook_rels_xml());
        $zip->addFromString('xl/styles.xml', $this->styles_xml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet_xml());
        $zip->close();

        $bytes = file_get_contents($tmp);
        unlink($tmp);
        return $bytes;
    }

    private function content_types_xml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
            '</Types>';
    }

    private function rels_xml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>';
    }

    private function workbook_xml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" ' .
            'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets><sheet name="' . self::xml_escape($this->title) . '" sheetId="1" r:id="rId1"/></sheets>' .
            '</workbook>';
    }

    private function workbook_rels_xml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
            '</Relationships>';
    }

    private function styles_xml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<fonts count="2">' .
            '<font><sz val="11"/><name val="Calibri"/></font>' .
            '<font><b/><sz val="11"/><name val="Calibri"/></font>' .
            '</fonts>' .
            '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>' .
            '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' .
            '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
            '<cellXfs count="2">' .
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' .
            '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>' .
            '</cellXfs>' .
            '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>' .
            '</styleSheet>';
    }

    private function sheet_xml(): string {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<sheetViews><sheetView workbookViewId="0"/></sheetViews>' .
            '<sheetFormatPr defaultRowHeight="15"/>';

        if ($this->widths) {
            $xml .= '<cols>';
            $col = 1;
            foreach ($this->widths as $width) {
                $xml .= '<col min="' . $col . '" max="' . $col . '" width="' . ((float) $width) . '" customWidth="1"/>';
                $col++;
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';

        $rownum = 1;
        foreach ($this->rows as $row) {
            $values = $row['values'];
            $header = $row['header'];
            $xml .= '<row r="' . $rownum . '">';
            $colindex = 1;
            foreach ($values as $value) {
                $ref = self::col_letter($colindex) . $rownum;
                $style = $header ? ' s="1"' : '';
                if (is_int($value) || is_float($value)) {
                    $xml .= '<c r="' . $ref . '"' . $style . '><v>' . $value . '</v></c>';
                } else {
                    $text = self::xml_escape((string) $value);
                    $preserve = (strpos((string) $value, "\n") !== false) ? ' xml:space="preserve"' : '';
                    $xml .= '<c r="' . $ref . '" t="inlineStr"' . $style . '><is><t' . $preserve . '>' . $text . '</t></is></c>';
                }
                $colindex++;
            }
            $xml .= '</row>';
            $rownum++;
        }

        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private static function clean_sheet_title(string $title): string {
        $title = preg_replace('/[\[\]:*?\/\\\\]/', ' ', $title);
        $title = preg_replace('/\s+/', ' ', $title);
        $title = \core_text::substr(trim($title), 0, 31);
        return $title === '' ? 'Rapport' : $title;
    }

    private static function col_letter(int $index): string {
        $result = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $result = chr(65 + $mod) . $result;
            $index = intdiv($index - 1, 26);
        }
        return $result;
    }

    private static function xml_escape(string $value): string {
        $value = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value);
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}