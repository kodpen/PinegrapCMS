<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * One sheet of a workbook (.xlsx, .ods or .xls) read a row at a time, for
 * the importers.
 *
 * pg_sheet_rows() is a generator: each step gives one row of the sheet, so
 * a large .xlsx or .ods is never held whole. A .xlsx is walked with the
 * preview's node-by-node helpers (includes/fn/office.php) but without its
 * row, column and string limits; an .ods has its content.xml streamed the
 * same way and keeps the cell ceilings PHPExcel's reader was given; an .xls
 * is a binary record stream, read whole by excel_reader2.php through the
 * guarded reader in includes/sheet_read_xls.php.
 *
 * A cell comes out typed, so each importer keeps its own rules for the text
 * it makes of it (pg_sheet_cell_text() is the product importer's):
 * array('t' => type, 'v' => string), type s (text), n (a number, as the file
 * writes it), b (a boolean, v '1' or '0'), d (a date: v is the day count
 * from 30 December 1899, a fraction of a day for a time alone) or e (an
 * error such as #DIV/0!). A missing cell is null.
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
 * The most an .ods file's content.xml may declare it unpacks to, in bytes:
 * the size PHPExcel's reader was allowed to hold whole, so a file the
 * importers took before is still taken.
 */
define('PG_SHEET_ODS_MAX_PART', 8 * 1024 * 1024);

/**
 * The most value cells an .ods sheet may make, every repeat of a repeated
 * row or column counted: an office application writes a value cell in 90 to
 * 150 bytes, so an honest content.xml at PG_SHEET_ODS_MAX_PART holds well
 * under one cell per 64 bytes, while a 1 KB file can ask for millions
 * (docs/memory/platform/ods-cell-ceiling.md).
 */
define('PG_SHEET_ODS_MAX_CELLS', PG_SHEET_ODS_MAX_PART / 64);

/**
 * The most cells an .ods sheet's used range may span, its last data row
 * times its last data column: the importers square every row to the widest.
 */
define('PG_SHEET_ODS_MAX_RANGE', 1024 * 1024);

/**
 * The last column a cell reference may name (ZZZ), as PHPExcel took them: a
 * reference past it is a damaged file, not a row to pad out to millions of
 * cells.
 */
define('PG_SHEET_MAX_COLUMNS', 18278);

/**
 * The reader for a file name: xlsx (also .xlsm), ods, xls, or ''.
 *
 * @param string $file_name
 * @return string
 */
function pg_sheet_kind($file_name)
{
    $extension = strtolower((string) pathinfo((string) $file_name, PATHINFO_EXTENSION));

    if (($extension === 'xlsx') || ($extension === 'xlsm')) {
        return 'xlsx';
    }

    return in_array($extension, array('ods', 'xls'), true) ? $extension : '';
}

/**
 * The rows of one sheet, one at a time: the key is the row number (1 for
 * the first), the value the row's cells from column A, a gap as null. Rows
 * with no cell are not given.
 *
 * $error is set when the walk ends: '' when the whole sheet was read;
 * 'read' when the file could not be read (rows already given may be cut
 * short); 'large' when an .ods passed its ceilings; 'sheet' when the
 * workbook has no such sheet. A caller drops what it took when $error is
 * not ''.
 *
 * @param string $path  the file on disk
 * @param string $kind  pg_sheet_kind(): xlsx, ods or xls
 * @param string $error see above
 * @param int    $sheet which sheet, 0 for the first
 * @return Generator
 */
function pg_sheet_rows($path, $kind, &$error, $sheet = 0)
{
    $error = '';
    $sheet = max(0, (int) $sheet);

    switch ((string) $kind) {
        case 'xlsx':
        case 'xlsm':
            yield from pg_sheet_xlsx_rows((string) $path, $sheet, $error);
            break;

        case 'ods':
            yield from pg_sheet_ods_rows((string) $path, $sheet, $error);
            break;

        case 'xls':
            yield from pg_sheet_xls_rows((string) $path, $sheet, $error);
            break;

        default:
            $error = 'read';
    }
}

/**
 * A cell as the text the product importer reads out of a CSV: a boolean as
 * 1 or 0, a date as Y-m-d (with the time of day when it has one), anything
 * else as it is.
 *
 * @param array|null $cell        a cell of pg_sheet_rows()
 * @param string     $time_format the date() format of a date with a time of day
 * @return string
 */
function pg_sheet_cell_text($cell, $time_format = 'Y-m-d H:i:s')
{
    if (!is_array($cell) || !isset($cell['t'], $cell['v'])) {
        return '';
    }

    if ($cell['t'] === 'd') {
        return pg_sheet_date_text($cell['v'], $time_format);
    }

    return (string) $cell['v'];
}

/**
 * A day count as a date: Y-m-d for a whole day, $time_format with a time of
 * day. Counted in UTC, so a server west of Greenwich does not move it a day
 * back.
 *
 * @param string|float $serial      days from 30 December 1899
 * @param string       $time_format
 * @return string
 */
function pg_sheet_date_text($serial, $time_format = 'Y-m-d H:i:s')
{
    $serial = (float) $serial;
    $stamp = (int) round(($serial - 25569) * 86400);

    return gmdate(($serial == floor($serial)) ? 'Y-m-d' : (string) $time_format, $stamp);
}

/**
 * A float as the shortest text that reads back as the same float.
 *
 * @param float $value
 * @return string
 */
function pg_sheet_float_text($value)
{
    $value = (float) $value;

    for ($digits = 15; $digits < 17; $digits++) {
        $text = sprintf('%.' . $digits . 'G', $value);

        if ((float) $text === $value) {
            return $text;
        }
    }

    return sprintf('%.17G', $value);
}

/* ---------------------------------------------------------------------------
   .xlsx
   --------------------------------------------------------------------------- */

/**
 * The rows of a .xlsx sheet (pg_sheet_rows()).
 *
 * The workbook, its relationships and the styles are read through
 * pg_office_xml(), whose 8 MB DOM ceiling they keep; the shared strings and
 * the sheet are walked node by node with no limit but the size the package
 * declares for them. Dates are known by the cell's number format
 * (pg_office_xlsx_date_styles()); a workbook counting from 1904 has its
 * dates moved to the 1900 count.
 *
 * @param string $path
 * @param int    $sheet
 * @param string $error
 * @return Generator
 */
function pg_sheet_xlsx_rows($path, $sheet, &$error)
{
    if (!class_exists('ZipArchive') || !class_exists('XMLReader') || !class_exists('DOMDocument')) {
        $error = 'read';
        return;
    }

    $zip = new ZipArchive();

    if (!is_file($path) || ($zip->open($path) !== true)) {
        $error = 'read';
        return;
    }

    $sheets = array();

    foreach (pg_office_xlsx_sheets($zip, $refused) as $entry) {
        if ($entry['type'] === 'worksheet') {
            $sheets[] = $entry;
        }
    }

    $date_styles = ($refused === '') ? pg_office_xlsx_date_styles($zip, $refused) : array();
    $workbook_xml = (string) pg_zip_entry_contents($zip, 'xl/workbook.xml', PG_OFFICE_MAX_DOM, $unread);
    $date1904 = pg_office_xlsx_date1904($workbook_xml);
    $strings_part = ($zip->locateName('xl/sharedStrings.xml') !== false) ? 'xl/sharedStrings.xml' : '';
    $zip->close();
    unset($workbook_xml);

    if ($refused !== '') {
        $error = ($refused === 'large') ? 'large' : 'read';
        return;
    }

    if (empty($sheets)) {
        $error = 'read';
        return;
    }

    if (!isset($sheets[$sheet])) {
        $error = 'sheet';
        return;
    }

    $strings = pg_office_xlsx_strings($path, $strings_part, false);

    if (!is_array($strings)) {
        $error = 'read';
        return;
    }

    $part = $sheets[$sheet]['part'];

    pg_office_zip_stream();

    $reader = new XMLReader();

    // No further than the sheet's declared size (pg_zip_entry_uri()).
    if (!$reader->open(pg_zip_entry_uri($path, $part, max(0, pg_zip_entry_size($path, $part))), null, LIBXML_NONET)) {
        $error = 'read';
        return;
    }

    // A sheet cut at its declared size ends in a parser error; it is
    // answered below, not printed as a warning.
    $previous = libxml_use_internal_errors(true);
    $last_number = 0;

    while ($reader->read()) {
        if (($reader->nodeType !== XMLReader::ELEMENT) || ($reader->localName !== 'row')) {
            continue;
        }

        // The attribute is optional (ECMA-376): a row without it is the one
        // after the last.
        $given = $reader->getAttribute('r');
        $number = (($given !== null) && ($given !== '')) ? (int) $given : ($last_number + 1);
        $last_number = $number;
        $depth = $reader->depth;
        $has_cells = !$reader->isEmptyElement;
        $row = array();
        $next_column = 0;

        // The row's own cells, each read node by node
        // (pg_office_xlsx_cell_parts()): a row is never built as a tree.
        while ($has_cells && $reader->read() && ($reader->depth > $depth)) {
            if (($reader->nodeType !== XMLReader::ELEMENT) || ($reader->depth !== $depth + 1) || ($reader->localName !== 'c')) {
                continue;
            }

            // A cell without its reference is the column after the last one.
            $reference = $reader->getAttribute('r');
            $column = (($reference !== null) && ($reference !== '')) ? pg_office_xlsx_column((string) $reference) : $next_column;
            $next_column = $column + 1;

            if ($column >= PG_SHEET_MAX_COLUMNS) {
                $error = 'read';
                break 2;
            }

            $type = (string) $reader->getAttribute('t');
            $style = (int) $reader->getAttribute('s');
            $cell = pg_sheet_xlsx_cell($type, pg_office_xlsx_cell_parts($reader), $strings, isset($date_styles[$style]), $date1904);

            if ($cell === null) {
                continue;
            }

            while (count($row) < $column) {
                $row[] = null;
            }

            $row[$column] = $cell;
        }

        if (!empty($row)) {
            ksort($row);
            yield $number => $row;
        }
    }

    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $reader->close();

    // A sheet that unpacks past its declared size is a damaged or doctored
    // file.
    if (pg_zip_entry_overran()) {
        $error = 'read';
    }
}

/**
 * One .xlsx cell, typed (pg_sheet_rows()), or null when it holds nothing.
 *
 * @param string $type     its t attribute
 * @param array  $parts    pg_office_xlsx_cell_parts()
 * @param array  $strings  the shared strings
 * @param bool   $is_date  its number format shows a date or a time
 * @param bool   $date1904 the workbook counts its days from 1904
 * @return array|null
 */
function pg_sheet_xlsx_cell($type, $parts, $strings, $is_date, $date1904)
{
    $value = (string) $parts['v'];

    switch ($type) {
        case 's':
            $index = (int) $value;

            return ($value === '') ? null : array('t' => 's', 'v' => isset($strings[$index]) ? (string) $strings[$index] : '');

        case 'inlineStr':
            $text = $parts['is'] ? (string) $parts['t'] : '';

            // Styled text comes as runs: <r><t> rather than <t>.
            if (($text === '') && $parts['r']) {
                $text = (string) $parts['runs'];
            }

            return $parts['is'] ? array('t' => 's', 'v' => $text) : null;

        case 'b':
            return ($value === '') ? null : array('t' => 'b', 'v' => ($value === '1') ? '1' : '0');

        case 'str':
            return ($value === '') ? null : array('t' => 's', 'v' => $value);

        case 'e':
            return ($value === '') ? null : array('t' => 'e', 'v' => $value);

        case 'd':
            // A strict workbook's ISO 8601 date (2026-11-17T10:30:00).
            $serial = pg_sheet_iso_serial($value);

            return ($serial === null) ? (($value === '') ? null : array('t' => 's', 'v' => $value)) : array('t' => 'd', 'v' => $serial);
    }

    if ($value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        return array('t' => 's', 'v' => $value);
    }

    if ($is_date) {
        return array('t' => 'd', 'v' => $date1904 ? pg_sheet_float_text((float) $value + 1462) : $value);
    }

    return array('t' => 'n', 'v' => $value);
}

/**
 * An ISO 8601 date, with or without a time of day, as a day count from 30
 * December 1899, or null when it is not one.
 *
 * @param string $text 2026-11-17 or 2026-11-17T10:30:00(.5)(Z)
 * @return string|null
 */
function pg_sheet_iso_serial($text)
{
    if (!preg_match('/^(-?\d{4,})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2})(?::(\d{2})(\.\d+)?)?)?(?:Z|[+-]\d{2}:?\d{2})?$/', trim((string) $text), $match)) {
        return null;
    }

    $stamp = gmmktime(
        isset($match[4]) ? (int) $match[4] : 0,
        isset($match[5]) ? (int) $match[5] : 0,
        isset($match[6]) ? (int) $match[6] : 0,
        (int) $match[2],
        (int) $match[3],
        (int) $match[1]
    );

    if ($stamp === false) {
        return null;
    }

    $seconds = (float) $stamp + (isset($match[7]) ? (float) $match[7] : 0.0);

    return pg_sheet_float_text(($seconds / 86400) + 25569);
}

/* ---------------------------------------------------------------------------
   .ods
   --------------------------------------------------------------------------- */

/**
 * The rows of an .ods sheet (pg_sheet_rows()).
 *
 * content.xml is streamed node by node, never held as a tree: a run of
 * empty cells or rows (LibreOffice fills each row to column 16,384 with
 * one) costs a step, and a repeated row or cell with a value is given once
 * for every repeat, counted against PG_SHEET_ODS_MAX_CELLS; the used range
 * is held to PG_SHEET_ODS_MAX_RANGE. Either ceiling passed ends the walk
 * with 'large'.
 *
 * @param string $path
 * @param int    $sheet
 * @param string $error
 * @return Generator
 */
function pg_sheet_ods_rows($path, $sheet, &$error)
{
    if (!class_exists('ZipArchive') || !class_exists('XMLReader') || !is_file($path)) {
        $error = 'read';
        return;
    }

    $size = pg_zip_entry_size($path, 'content.xml');

    if ($size <= 0) {
        $error = 'read';
        return;
    }

    if ($size > PG_SHEET_ODS_MAX_PART) {
        $error = 'large';
        return;
    }

    pg_office_zip_stream();

    $reader = new XMLReader();

    if (!$reader->open(pg_zip_entry_uri($path, 'content.xml', $size), null, LIBXML_NONET)) {
        $error = 'read';
        return;
    }

    $table_ns = 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';
    $previous = libxml_use_internal_errors(true);
    $tables = 0;
    $found = false;
    $made = 0;
    $last_row = 0;
    $last_column = 0;
    // XMLReader::next() moves past an element's subtree to what follows
    // it; the walk then takes that node as it is, without reading on.
    $skip = false;

    while ($skip ? $reader->next() : $reader->read()) {
        $skip = false;

        if (($reader->nodeType !== XMLReader::ELEMENT) || ($reader->localName !== 'table') || ($reader->namespaceURI !== $table_ns)) {
            continue;
        }

        if ($tables++ !== $sheet) {
            // Another sheet: passed over without reading its cells.
            $skip = true;
            continue;
        }

        $found = true;

        if ($reader->isEmptyElement) {
            break;
        }

        $table_depth = $reader->depth;
        $row_number = 1;

        while (($skip ? $reader->next() : $reader->read()) && ($reader->depth > $table_depth)) {
            $skip = false;

            if ($reader->nodeType !== XMLReader::ELEMENT) {
                continue;
            }

            // Rows stand in the table or in its row groups; the columns,
            // shapes and named ranges hold no cell.
            if (($reader->namespaceURI !== $table_ns) || ($reader->localName !== 'table-row')) {
                if (($reader->namespaceURI === $table_ns) && in_array($reader->localName, array('table-row-group', 'table-header-rows', 'table-rows'), true)) {
                    continue;
                }

                $skip = !$reader->isEmptyElement;
                continue;
            }

            $rows_repeated = pg_sheet_ods_repeat($reader->getAttributeNs('number-rows-repeated', $table_ns));
            $row = array();
            $column = 0;

            if (!$reader->isEmptyElement) {
                $row_depth = $reader->depth;

                $skip_cell = false;

                while (($skip_cell ? $reader->next() : $reader->read()) && ($reader->depth > $row_depth)) {
                    $skip_cell = false;

                    if (($reader->nodeType !== XMLReader::ELEMENT) || ($reader->depth !== $row_depth + 1)) {
                        continue;
                    }

                    if (($reader->namespaceURI !== $table_ns) || !in_array($reader->localName, array('table-cell', 'covered-table-cell'), true)) {
                        $skip_cell = !$reader->isEmptyElement;
                        continue;
                    }

                    $columns_repeated = pg_sheet_ods_repeat($reader->getAttributeNs('number-columns-repeated', $table_ns));
                    $cell = pg_sheet_ods_cell($reader);

                    if ($cell !== null) {
                        $made += $columns_repeated * $rows_repeated;
                        $last_row = max($last_row, $row_number + $rows_repeated - 1);
                        $last_column = max($last_column, $column + $columns_repeated);

                        if (($made > PG_SHEET_ODS_MAX_CELLS) || ($last_row * $last_column > PG_SHEET_ODS_MAX_RANGE)) {
                            $error = 'large';
                            break 3;
                        }

                        while (count($row) < $column) {
                            $row[] = null;
                        }

                        for ($index = 0; $index < $columns_repeated; $index++) {
                            $row[] = $cell;
                        }
                    }

                    // An empty run is stepped over at once, whatever its
                    // length.
                    $column += $columns_repeated;
                }
            }

            if (!empty($row)) {
                for ($index = 0; $index < $rows_repeated; $index++) {
                    yield $row_number + $index => $row;
                }
            }

            $row_number += $rows_repeated;
        }

        break;
    }

    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $reader->close();

    if ($error !== '') {
        return;
    }

    if (pg_zip_entry_overran() || ($tables === 0)) {
        $error = 'read';
    } elseif (!$found) {
        $error = 'sheet';
    }
}

/**
 * A repeat count of an .ods row or cell: 1 when it is not given; a count
 * below 1 is read as 1.
 *
 * @param string|null $attribute
 * @return int
 */
function pg_sheet_ods_repeat($attribute)
{
    if (($attribute === null) || ($attribute === '')) {
        return 1;
    }

    return max(1, min(1073741824, (int) $attribute));
}

/**
 * One .ods cell, typed (pg_sheet_rows()), or null when it holds nothing, read
 * node by node from the reader on its element and left on its last node.
 *
 * The value comes from the cell's office attributes (a number's
 * office:value, a date's office:date-value, a boolean's
 * office:boolean-value), the text from its paragraphs, joined by a line
 * break, with text:s, text:tab and text:line-break written out and a
 * comment (office:annotation) left out. A time alone is a fraction of a
 * day.
 *
 * @param XMLReader $reader on a table:table-cell element
 * @return array|null
 */
function pg_sheet_ods_cell($reader)
{
    $office_ns = 'urn:oasis:names:tc:opendocument:xmlns:office:1.0';
    $text_ns = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';
    $type = (string) $reader->getAttributeNs('value-type', $office_ns);
    $value = $reader->getAttributeNs('value', $office_ns);
    $date = $reader->getAttributeNs('date-value', $office_ns);
    $time = $reader->getAttributeNs('time-value', $office_ns);
    $boolean = $reader->getAttributeNs('boolean-value', $office_ns);
    $string = $reader->getAttributeNs('string-value', $office_ns);
    // LibreOffice marks a formula's error result in its own namespace; the
    // cell is otherwise a string showing #DIV/0!.
    $error = ($reader->getAttributeNs('value-type', 'urn:org:documentfoundation:names:experimental:calc:xmlns:calcext:1.0') === 'error');
    $paragraphs = array();

    if (!$reader->isEmptyElement) {
        $depth = $reader->depth;
        $skip_below = -1;
        $kinds = array(XMLReader::TEXT, XMLReader::CDATA, XMLReader::WHITESPACE, XMLReader::SIGNIFICANT_WHITESPACE);

        while ($reader->read() && ($reader->depth > $depth)) {
            // Inside a comment, or a paragraph's own sub-elements of no text.
            if (($skip_below >= 0) && ($reader->depth > $skip_below)) {
                continue;
            }

            $skip_below = -1;
            $level = $reader->depth - $depth;

            if ($reader->nodeType === XMLReader::ELEMENT) {
                if ($level === 1) {
                    if (($reader->namespaceURI === $text_ns) && ($reader->localName === 'p')) {
                        $paragraphs[] = '';
                    } elseif (!$reader->isEmptyElement) {
                        // A comment, a shape or anything else that is not
                        // the cell's own text.
                        $skip_below = $reader->depth;
                    }

                    continue;
                }

                if (empty($paragraphs) || ($reader->namespaceURI !== $text_ns)) {
                    continue;
                }

                $last = count($paragraphs) - 1;

                if ($reader->localName === 's') {
                    $count = $reader->getAttributeNs('c', $text_ns);
                    $paragraphs[$last] .= str_repeat(' ', (($count === null) || ($count === '')) ? 1 : max(1, min(1000, (int) $count)));
                } elseif ($reader->localName === 'tab') {
                    $paragraphs[$last] .= "\t";
                } elseif ($reader->localName === 'line-break') {
                    $paragraphs[$last] .= "\n";
                } elseif (in_array($reader->localName, array('note', 'ruby-text'), true) && !$reader->isEmptyElement) {
                    // A footnote's body and a ruby's reading are not the
                    // cell's text.
                    $skip_below = $reader->depth;
                }

                continue;
            }

            if (($level >= 2) && !empty($paragraphs) && in_array($reader->nodeType, $kinds, true)) {
                $paragraphs[count($paragraphs) - 1] .= $reader->value;
            }
        }
    }

    $text = implode("\n", $paragraphs);

    switch ($type) {
        case 'float':
        case 'percentage':
        case 'currency':
            if (($value !== null) && is_numeric(trim($value))) {
                return array('t' => 'n', 'v' => trim($value));
            }
            break;

        case 'date':
            $serial = pg_sheet_iso_serial((string) $date);

            if ($serial !== null) {
                return array('t' => 'd', 'v' => $serial);
            }
            break;

        case 'time':
            $serial = pg_sheet_ods_time_serial((string) $time);

            if ($serial !== null) {
                return array('t' => 'd', 'v' => $serial);
            }
            break;

        case 'boolean':
            if (($boolean !== null) && ($boolean !== '')) {
                return array('t' => 'b', 'v' => in_array(strtolower(trim($boolean)), array('true', '1'), true) ? '1' : '0');
            }
            break;

        case 'string':
            if (empty($paragraphs) && ($string !== null)) {
                return array('t' => 's', 'v' => $string);
            }

            return empty($paragraphs) ? null : array('t' => $error ? 'e' : 's', 'v' => $text);

        default:
            // No value type: an empty cell, whatever it shows.
            return null;
    }

    // A value type without its value: the text it shows.
    return empty($paragraphs) ? null : array('t' => 's', 'v' => $text);
}

/**
 * An .ods duration (office:time-value, PT12H30M00S) as a fraction of a day,
 * or null when it is not one.
 *
 * @param string $text
 * @return string|null
 */
function pg_sheet_ods_time_serial($text)
{
    if (!preg_match('/^(-)?P(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+(?:\.\d+)?)S)?)?$/', trim((string) $text), $match) || (trim((string) $text) === 'P')) {
        return null;
    }

    $seconds = (isset($match[2]) ? (float) $match[2] * 86400 : 0.0)
        + (isset($match[3]) ? (float) $match[3] * 3600 : 0.0)
        + (isset($match[4]) ? (float) $match[4] * 60 : 0.0)
        + (isset($match[5]) ? (float) $match[5] : 0.0);

    return pg_sheet_float_text((($match[1] === '-') ? -1 : 1) * $seconds / 86400);
}

/* ---------------------------------------------------------------------------
   .xls
   --------------------------------------------------------------------------- */

/**
 * The rows of an .xls sheet (pg_sheet_rows()).
 *
 * excel_reader2.php reads the file, through Pg_Sheet_Xls_Reader
 * (includes/sheet_read_xls.php), which reads only the sheet asked for,
 * refuses a damaged stream before the library walks it, and gives each cell
 * its exact type: a number as PHPExcel gave it ((string) of the float), a
 * date by its cell's number format, a boolean, an error, text decoded from
 * the workbook's code page. The library holds the sheet whole: a BIFF8 sheet
 * stops at 65,536 rows and 256 columns.
 *
 * @param string $path
 * @param int    $sheet
 * @param string $error
 * @return Generator
 */
function pg_sheet_xls_rows($path, $sheet, &$error)
{
    $book = pg_sheet_xls_load($path, $sheet);

    if (!is_array($book)) {
        $error = ($book === 'sheet') ? 'sheet' : 'read';
        return;
    }

    foreach ($book as $number => $row) {
        yield $number => $row;
    }
}

/**
 * The cells of one .xls sheet, row number => row (pg_sheet_rows()), or
 * 'read' when the file could not be read, 'sheet' when it has no such sheet.
 *
 * @param string $path
 * @param int    $sheet
 * @return array|string
 */
function pg_sheet_xls_load($path, $sheet)
{
    if (!is_file($path)) {
        return 'read';
    }

    // PHP 7 reports the library's PHP 4 constructors as deprecated while it
    // compiles the file; a reported notice there stopped the load halfway,
    // before its constants. The reader never calls those constructors.
    @require_once(PG_FUNCTIONS_DIR . '/excel_reader2.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/sheet_read_xls.php');

    $reader = new Pg_Sheet_Xls_Reader((int) $sheet);

    try {
        // The library reads a damaged file with warnings at every turn; the
        // reader's own checks decide whether what it read is taken.
        @$reader->read($path);
    } catch (Throwable $exception) {
        return 'read';
    }

    return $reader->pg_rows();
}
