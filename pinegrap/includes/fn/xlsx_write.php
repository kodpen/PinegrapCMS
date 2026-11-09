<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A one-sheet Excel workbook written from rows, without a library.
 *
 * A .xlsx is a zip of XML parts. The writer builds the few parts a reader
 * needs - the workbook, one worksheet, a style sheet with a bold heading
 * and two date formats - and puts every cell inline, so no shared strings
 * table has to be kept in step. A value decides its cell: an int or a float
 * is a number, array('date' => 'Y-m-d' or 'Y-m-d H:i') is a date the
 * spreadsheet can sort and sum, anything else is text.
 *
 * The ERP export (includes/erp/export.php) and the workflow table download
 * (workspace_wtables.php) both write through it. Reading a workbook is the
 * business of includes/fn/office.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The column letters of a zero-based index: 0 => A, 26 => AA.
 *
 * @param int $index
 * @return string
 */
function pg_xlsx_column_letter($index)
{
    $letters = '';

    for ($number = $index + 1; $number > 0; $number = (int) (($number - 1) / 26)) {
        $letters = chr(65 + (($number - 1) % 26)) . $letters;
    }

    return $letters;
}

/**
 * One value as XML text. Control characters are dropped: legal in a CSV,
 * fatal to a workbook.
 *
 * @param string $value
 * @return string
 */
function pg_xlsx_xml($value)
{
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $value);

    return str_replace(array('&', '<', '>', '"'), array('&amp;', '&lt;', '&gt;', '&quot;'), $value);
}

/**
 * A date (Y-m-d) or a date and time (Y-m-d H:i) as the spreadsheet's serial
 * number: days since 1899-12-30, the time as the fraction of a day.
 *
 * @param string $value
 * @return float|int|null null when it is no such date
 */
function pg_xlsx_date_serial($value)
{
    $value = (string) $value;

    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(?: (\d{2}):(\d{2}))?$/', $value, $parts) || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
        return null;
    }

    $epoch = new DateTime('1899-12-30', new DateTimeZone('UTC'));
    $day = new DateTime($parts[1] . '-' . $parts[2] . '-' . $parts[3], new DateTimeZone('UTC'));
    $days = (int) $epoch->diff($day)->days;

    if (!isset($parts[4])) {
        return $days;
    }

    $minutes = ((int) $parts[4] * 60) + (int) $parts[5];

    return ($minutes > 0) ? round($days + ($minutes / 1440), 10) : $days;
}

/**
 * The <row> elements for a block of rows: numbers as numeric cells, dates
 * (array('date' => …)) as dated numeric cells, everything else as inline
 * text. Empty values leave the cell out.
 *
 * @param array $rows
 * @param int   $first_row  Row number of the first row
 * @param int   $style      Cell style index for text and numbers, 0 for the workbook default
 * @return string
 */
function pg_xlsx_sheet_rows($rows, $first_row, $style = 0)
{
    $xml = '';
    $number = $first_row - 1;
    $style_attribute = ($style > 0) ? (' s="' . (int) $style . '"') : '';

    foreach ($rows as $row) {
        $number++;
        $xml .= '<row r="' . $number . '">';

        foreach (array_values($row) as $index => $value) {
            if (($value === '') || ($value === null)) {
                continue;
            }

            $reference = pg_xlsx_column_letter($index) . $number;

            if (is_array($value)) {
                $serial = isset($value['date']) ? pg_xlsx_date_serial($value['date']) : null;

                if ($serial === null) {
                    $value = isset($value['date']) ? (string) $value['date'] : '';

                    if ($value === '') {
                        continue;
                    }
                } else {
                    // Style 2 is a date, 3 a date and time (pg_xlsx_write_plain()).
                    $xml .= '<c r="' . $reference . '" s="' . ((strlen((string) $value['date']) > 10) ? 3 : 2) . '"><v>' . (is_float($serial) ? rtrim(rtrim(number_format($serial, 10, '.', ''), '0'), '.') : (string) $serial) . '</v></c>';
                    continue;
                }
            }

            if (is_int($value) || is_float($value)) {
                $xml .= '<c r="' . $reference . '"' . $style_attribute . '><v>' . pg_xlsx_xml(is_float($value) ? rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.') : (string) $value) . '</v></c>';
            } else {
                $xml .= '<c r="' . $reference . '"' . $style_attribute . ' t="inlineStr"><is><t xml:space="preserve">' . pg_xlsx_xml($value) . '</t></is></c>';
            }
        }

        $xml .= '</row>';
    }

    return $xml;
}

/**
 * A sheet name Excel accepts: none of []*?/\: and at most 31 characters.
 *
 * @param string $title
 * @return string
 */
function pg_xlsx_sheet_title($title)
{
    $title = trim(mb_substr(preg_replace('/[\[\]\*\?\/\\\\:]/', ' ', (string) $title), 0, 31));

    return ($title === '') ? 'Sheet1' : $title;
}

/**
 * A one-sheet workbook from nothing: heading row in bold and frozen, data
 * underneath.
 *
 * @param array  $columns     The heading texts
 * @param array  $rows        Lists of values (pg_xlsx_sheet_rows())
 * @param string $path        Where to write the .xlsx
 * @param string $sheet_title
 * @return bool
 */
function pg_xlsx_write_plain($columns, $rows, $path, $sheet_title)
{
    if (!class_exists('ZipArchive')) {
        return false;
    }

    $ns = 'http://schemas.openxmlformats.org/';
    $head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";

    $last_cell = pg_xlsx_column_letter(max(0, count($columns) - 1)) . (count($rows) + 1);

    $sheet = $head .
        '<worksheet xmlns="' . $ns . 'spreadsheetml/2006/main">' .
        '<dimension ref="A1:' . $last_cell . '"/>' .
        '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' .
        '<sheetData>' .
        pg_xlsx_sheet_rows(array($columns), 1, 1) .
        pg_xlsx_sheet_rows($rows, 2) .
        '</sheetData></worksheet>';

    $parts = array(
        '[Content_Types].xml' => $head .
            '<Types xmlns="' . $ns . 'package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
            '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>' .
            '</Types>',
        '_rels/.rels' => $head .
            '<Relationships xmlns="' . $ns . 'package/2006/relationships">' .
            '<Relationship Id="rId1" Type="' . $ns . 'officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>',
        'xl/workbook.xml' => $head .
            '<workbook xmlns="' . $ns . 'spreadsheetml/2006/main" xmlns:r="' . $ns . 'officeDocument/2006/relationships">' .
            '<bookViews><workbookView activeTab="0"/></bookViews>' .
            '<sheets><sheet name="' . pg_xlsx_xml(pg_xlsx_sheet_title($sheet_title)) . '" sheetId="1" r:id="rId1"/></sheets>' .
            '</workbook>',
        'xl/_rels/workbook.xml.rels' => $head .
            '<Relationships xmlns="' . $ns . 'package/2006/relationships">' .
            '<Relationship Id="rId1" Type="' . $ns . 'officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '<Relationship Id="rId2" Type="' . $ns . 'officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
            '<Relationship Id="rId3" Type="' . $ns . 'officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>' .
            '</Relationships>',
        // Text is carried inline, so the shared strings table is empty; it is
        // present because some readers look for it before the sheet.
        'xl/sharedStrings.xml' => $head .
            '<sst xmlns="' . $ns . 'spreadsheetml/2006/main" count="0" uniqueCount="0"/>',
        // Four cell formats: 0 plain, 1 the bold heading, 2 a date (built-in
        // format 14), 3 a date and time (built-in 22). Fills and borders are
        // required by Excel even when nothing uses them.
        'xl/styles.xml' => $head .
            '<styleSheet xmlns="' . $ns . 'spreadsheetml/2006/main">' .
            '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>' .
            '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>' .
            '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' .
            '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
            '<cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>' .
            '<xf numFmtId="14" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="22" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>' .
            '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>' .
            '</styleSheet>',
        'xl/worksheets/sheet1.xml' => $sheet,
    );

    $zip = new ZipArchive();

    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }

    foreach ($parts as $name => $part) {
        $zip->addFromString($name, $part);
    }

    return $zip->close();
}
