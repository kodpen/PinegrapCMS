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

/**
 * A number as the text of a <v> element: up to ten decimals, no trailing
 * zeros, never the exponent form.
 *
 * @param int|float $value
 * @return string
 */
function pg_xlsx_number_text($value)
{
    if (is_int($value)) {
        return (string) $value;
    }

    $text = rtrim(rtrim(number_format((float) $value, 10, '.', ''), '0'), '.');

    return (($text === '') || ($text === '-0')) ? '0' : $text;
}

/**
 * A one-sheet workbook with fixed rows above the data: a help note, a
 * heading row, column widths and merged cells, the way an import sheet of
 * another program is laid out. The rows are data in code, so no template
 * file is needed.
 *
 * $layout:
 *   title      the sheet name
 *   first_row  the row number of the first data row
 *   columns    how many data columns: every one gets a bordered cell
 *   widths     column index => width
 *   head       row number => array('cells' => column index => text,
 *              'style' => 'note' | 'heading', 'height' => points or 0,
 *              'size' => font size of a note)
 *   merge      cell ranges merged in the head, e.g. 'A1:F1'
 *   formats    column index => number format code of the data cells
 *
 * A data row is an array keyed by column index. An int or a float is a
 * number, array('date' => 'Y-m-d' or 'Y-m-d H:i') a date (pg_xlsx_date_serial())
 * in its column's format, anything else inline text, never a formula. An
 * empty value leaves the bordered cell empty.
 *
 * @param array  $layout
 * @param array  $rows
 * @param string $path  Where to write the .xlsx
 * @return bool
 */
function pg_xlsx_write_layout($layout, $rows, $path)
{
    if (!class_exists('ZipArchive')) {
        return false;
    }

    $first_row = max(1, (int) ($layout['first_row'] ?? 2));
    $columns = max(1, (int) ($layout['columns'] ?? 1));
    $formats = isset($layout['formats']) ? (array) $layout['formats'] : array();

    // Fonts, number formats and cell formats are collected as the cells ask
    // for them; a cell names its format by index.
    $fonts = array('<font><sz val="11"/><name val="Arial"/><family val="2"/></font>');
    $num_fmts = array();
    $xfs = array('<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>');
    $xf_index = array();

    $style = function ($bold, $size, $border, $wrap, $format) use (&$fonts, &$num_fmts, &$xfs, &$xf_index) {
        $key = serialize(array($bold, $size, $border, $wrap, $format));

        if (isset($xf_index[$key])) {
            return $xf_index[$key];
        }

        $font = '<font>' . ($bold ? '<b/>' : '') . '<sz val="' . (float) $size . '"/><name val="Arial"/><family val="2"/></font>';
        $font_id = array_search($font, $fonts, true);
        if ($font_id === false) {
            $fonts[] = $font;
            $font_id = count($fonts) - 1;
        }

        // 0 General, 4 #,##0.00, 14 a short date; anything else is the
        // workbook's own from 164 up.
        $builtin = array('' => 0, '#,##0.00' => 4, 'mm-dd-yy' => 14);
        if (isset($builtin[$format])) {
            $fmt_id = $builtin[$format];
        } else {
            if (!isset($num_fmts[$format])) {
                $num_fmts[$format] = 164 + count($num_fmts);
            }
            $fmt_id = $num_fmts[$format];
        }

        $alignment = $wrap ? '<alignment vertical="top" wrapText="1"/>' : '<alignment horizontal="left" vertical="center"/>';

        $xfs[] = '<xf numFmtId="' . $fmt_id . '" fontId="' . $font_id . '" fillId="0" borderId="' . ($border ? 1 : 0) . '" xfId="0"' .
            (($fmt_id > 0) ? ' applyNumberFormat="1"' : '') . ' applyFont="1"' . ($border ? ' applyBorder="1"' : '') . ' applyAlignment="1">' . $alignment . '</xf>';
        $xf_index[$key] = count($xfs) - 1;

        return $xf_index[$key];
    };

    $data = '';
    $last_row = $first_row - 1;
    $head = isset($layout['head']) ? (array) $layout['head'] : array();
    ksort($head);

    foreach ($head as $number => $row) {
        $number = (int) $number;
        if (($number < 1) || ($number >= $first_row)) {
            continue;
        }

        $note = (($row['style'] ?? '') === 'note');
        $s = $note ? $style(true, $row['size'] ?? 11, false, true, '') : $style(true, 11, true, false, '');
        $height = (float) ($row['height'] ?? 0);

        $data .= '<row r="' . $number . '"' . (($height > 0) ? ' ht="' . $height . '" customHeight="1"' : '') . '>';
        $cells = isset($row['cells']) ? (array) $row['cells'] : array();
        ksort($cells);
        foreach ($cells as $index => $text) {
            $data .= '<c r="' . pg_xlsx_column_letter((int) $index) . $number . '" s="' . $s . '" t="inlineStr"><is><t xml:space="preserve">' . pg_xlsx_xml($text) . '</t></is></c>';
        }
        $data .= '</row>';
    }

    $number = $first_row - 1;

    foreach ($rows as $row) {
        $number++;
        $row = (array) $row;
        $width = max($columns, empty($row) ? 0 : max(array_keys($row)) + 1);
        $data .= '<row r="' . $number . '">';

        for ($index = 0; $index < $width; $index++) {
            $value = $row[$index] ?? null;
            $format = (string) ($formats[$index] ?? '');
            $reference = pg_xlsx_column_letter($index) . $number;

            if (is_array($value)) {
                $serial = isset($value['date']) ? pg_xlsx_date_serial($value['date']) : null;
                $value = ($serial === null) ? (string) ($value['date'] ?? '') : $serial;
                if ($serial !== null) {
                    $data .= '<c r="' . $reference . '" s="' . $style(false, 11, true, false, ($format === '') ? 'mm-dd-yy' : $format) . '"><v>' . pg_xlsx_number_text($serial) . '</v></c>';
                    continue;
                }
            }

            $s = $style(false, 11, true, false, $format);

            if (($value === null) || ($value === '') || is_bool($value)) {
                $data .= '<c r="' . $reference . '" s="' . $s . '"/>';
            } elseif (is_int($value) || (is_float($value) && is_finite($value))) {
                $data .= '<c r="' . $reference . '" s="' . $s . '"><v>' . pg_xlsx_number_text($value) . '</v></c>';
            } else {
                $data .= '<c r="' . $reference . '" s="' . $s . '" t="inlineStr"><is><t xml:space="preserve">' . pg_xlsx_xml($value) . '</t></is></c>';
            }
        }

        $data .= '</row>';
        $last_row = $number;
    }

    $cols = '';
    $widths = isset($layout['widths']) ? (array) $layout['widths'] : array();
    ksort($widths);
    foreach ($widths as $index => $width) {
        $cols .= '<col min="' . ((int) $index + 1) . '" max="' . ((int) $index + 1) . '" width="' . (float) $width . '" customWidth="1"/>';
    }

    $merges = '';
    foreach ((array) ($layout['merge'] ?? array()) as $range) {
        if (preg_match('/^[A-Z]{1,3}[0-9]+:[A-Z]{1,3}[0-9]+$/', (string) $range)) {
            $merges .= '<mergeCell ref="' . $range . '"/>';
        }
    }

    $ns = 'http://schemas.openxmlformats.org/';
    $head_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";

    $sheet = $head_xml .
        '<worksheet xmlns="' . $ns . 'spreadsheetml/2006/main">' .
        '<dimension ref="A1:' . pg_xlsx_column_letter($columns - 1) . max(1, $last_row) . '"/>' .
        '<sheetViews><sheetView showGridLines="0" workbookViewId="0"/></sheetViews>' .
        '<sheetFormatPr defaultRowHeight="15"/>' .
        (($cols !== '') ? '<cols>' . $cols . '</cols>' : '') .
        '<sheetData>' . $data . '</sheetData>' .
        (($merges !== '') ? '<mergeCells count="' . substr_count($merges, '<mergeCell ') . '">' . $merges . '</mergeCells>' : '') .
        '</worksheet>';

    $fmt_xml = '';
    foreach ($num_fmts as $code => $id) {
        $fmt_xml .= '<numFmt numFmtId="' . $id . '" formatCode="' . pg_xlsx_xml($code) . '"/>';
    }

    $styles = $head_xml .
        '<styleSheet xmlns="' . $ns . 'spreadsheetml/2006/main">' .
        (($fmt_xml !== '') ? '<numFmts count="' . count($num_fmts) . '">' . $fmt_xml . '</numFmts>' : '') .
        '<fonts count="' . count($fonts) . '">' . implode('', $fonts) . '</fonts>' .
        '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>' .
        '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>' .
        '<border><left style="thin"><color rgb="FFB8B8B8"/></left><right style="thin"><color rgb="FFB8B8B8"/></right><top style="thin"><color rgb="FFB8B8B8"/></top><bottom style="thin"><color rgb="FFB8B8B8"/></bottom><diagonal/></border></borders>' .
        '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
        '<cellXfs count="' . count($xfs) . '">' . implode('', $xfs) . '</cellXfs>' .
        '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>' .
        '</styleSheet>';

    $parts = array(
        '[Content_Types].xml' => $head_xml .
            '<Types xmlns="' . $ns . 'package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
            '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>' .
            '</Types>',
        '_rels/.rels' => $head_xml .
            '<Relationships xmlns="' . $ns . 'package/2006/relationships">' .
            '<Relationship Id="rId1" Type="' . $ns . 'officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>',
        'xl/workbook.xml' => $head_xml .
            '<workbook xmlns="' . $ns . 'spreadsheetml/2006/main" xmlns:r="' . $ns . 'officeDocument/2006/relationships">' .
            '<bookViews><workbookView activeTab="0"/></bookViews>' .
            '<sheets><sheet name="' . pg_xlsx_xml(pg_xlsx_sheet_title($layout['title'] ?? '')) . '" sheetId="1" r:id="rId1"/></sheets>' .
            '</workbook>',
        'xl/_rels/workbook.xml.rels' => $head_xml .
            '<Relationships xmlns="' . $ns . 'package/2006/relationships">' .
            '<Relationship Id="rId1" Type="' . $ns . 'officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '<Relationship Id="rId2" Type="' . $ns . 'officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
            '<Relationship Id="rId3" Type="' . $ns . 'officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>' .
            '</Relationships>',
        'xl/sharedStrings.xml' => $head_xml .
            '<sst xmlns="' . $ns . 'spreadsheetml/2006/main" count="0" uniqueCount="0"/>',
        'xl/styles.xml' => $styles,
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
