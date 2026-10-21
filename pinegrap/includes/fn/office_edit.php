<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Cell values of an Excel workbook changed on the server, in the file itself.
 *
 * The preview (includes/fn/office.php) draws a workbook as tables; this
 * writes the values a person changed in those tables back into the package
 * without rebuilding it. Only the rows that carry a change are rewritten,
 * and every other part of the zip (styles, column widths, charts, pictures,
 * macros, the shared strings) is copied across untouched. A changed text goes
 * in as an inline string, so the shared string table and the cells that
 * point into it are never renumbered.
 *
 * Formulas are not worked out here. The results they cached may depend on
 * what changed, so those results are taken out of every sheet and the
 * workbook is marked to calculate on opening: Excel honours the mark, and
 * LibreOffice, which trusts a cached result whatever the mark says, works out
 * every formula that has none. The preview shows such a cell as one waiting
 * to be calculated.
 *
 * What is never changed here, and what the preview is told so it can say
 * why: formulas, values linked by the spreadsheet program (pictures in a
 * cell, data types), cells hidden under a merge, the header and totals rows
 * of a table, pivot tables, array formulas, the locked cells of a protected
 * sheet, and sheets whose cells carry no address. A package that is
 * digitally signed is not changed at all.
 *
 * The file is replaced in one step at the end: the new package is built and
 * checked beside it, and only a package that reads back is put in its place.
 * The version the person opened (a hash of the bytes) must still be the one
 * on disk, so an edit made meanwhile is never overwritten.
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
 * The most cells one save may change.
 */
define('PG_OFFICE_EDIT_MAX_CHANGES', 5000);

/**
 * The longest text a spreadsheet cell holds (Excel's own limit).
 */
define('PG_OFFICE_EDIT_MAX_TEXT', 32767);

/**
 * The largest sheet part rewritten in memory, in bytes unpacked. A sheet is
 * read whole into a string to be patched; past this size it is shown but
 * not offered for editing.
 */
define('PG_OFFICE_EDIT_MAX_PART', 32 * 1024 * 1024);

/**
 * The longest formula text handed to the preview for a cell's tooltip, and
 * how much formula text one sheet hands out in all, in bytes; past that the
 * cells are still locked, with the plain reason.
 */
define('PG_OFFICE_EDIT_FORMULA_LABEL', 255);
define('PG_OFFICE_EDIT_FORMULA_TEXT', 1000000);

/**
 * The most merged areas and array formula ranges a sheet may hold, together,
 * and the most tables and pivot tables, to be offered for editing. Each one
 * is looked at for every cell, here and in the preview; a sheet with more is
 * shown but not changed.
 */
define('PG_OFFICE_EDIT_MAX_RANGES', 10000);
define('PG_OFFICE_EDIT_MAX_TABLES', 1000);

/* ---------------------------------------------------------------------------
   Addresses
   --------------------------------------------------------------------------- */

/**
 * A column number as its letters: 0 is A, 25 is Z, 26 is AA.
 *
 * @param int $index
 * @return string
 */
function pg_office_xlsx_column_name($index)
{
    $name = '';
    $index = (int) $index + 1;

    while ($index > 0) {
        $rest = ($index - 1) % 26;
        $name = chr(65 + $rest) . $name;
        $index = (int) (($index - 1) / 26);
    }

    return $name;
}

/**
 * A cell address as its row and column: "B7" is [7, 1]. The dollar signs of
 * an absolute address are allowed; anything outside a sheet's grid (16384
 * columns, 1048576 rows) is not an address.
 *
 * @param string $reference
 * @return array|null [row, column]
 */
function pg_office_xlsx_cell($reference)
{
    if (!preg_match('/^\$?([A-Z]{1,3})\$?([1-9][0-9]{0,6})$/', (string) $reference, $match)) {
        return null;
    }

    $column = 0;

    for ($index = 0; $index < strlen($match[1]); $index++) {
        $column = ($column * 26) + (ord($match[1][$index]) - 64);
    }

    $row = (int) $match[2];

    if (($column > 16384) || ($row > 1048576)) {
        return null;
    }

    return array($row, $column - 1);
}

/**
 * A range ("A1:C3", or a single cell) as its corners, top left first:
 * [first row, first column, last row, last column].
 *
 * @param string $reference
 * @return array|null
 */
function pg_office_xlsx_range($reference)
{
    $parts = explode(':', trim((string) $reference));

    if (count($parts) > 2) {
        return null;
    }

    $from = pg_office_xlsx_cell($parts[0]);
    $to = (count($parts) === 2) ? pg_office_xlsx_cell($parts[1]) : $from;

    if (($from === null) || ($to === null)) {
        return null;
    }

    return array(min($from[0], $to[0]), min($from[1], $to[1]), max($from[0], $to[0]), max($from[1], $to[1]));
}

/**
 * Whether a cell lies inside a range.
 *
 * @param int   $row
 * @param int   $column
 * @param array $range pg_office_xlsx_range()
 * @return bool
 */
function pg_office_xlsx_in_range($row, $column, $range)
{
    return ($row >= $range[0]) && ($row <= $range[2]) && ($column >= $range[1]) && ($column <= $range[3]);
}

/* ---------------------------------------------------------------------------
   Values
   --------------------------------------------------------------------------- */

/**
 * The text a person typed, made fit for a cell: line breaks as the
 * spreadsheet stores them, and the characters XML cannot carry taken out.
 *
 * @param string $text
 * @return string|null null when it is not UTF-8 text
 */
function pg_office_xlsx_clean_text($text)
{
    if (!is_string($text) || !mb_check_encoding($text, 'UTF-8')) {
        return null;
    }

    $text = str_replace(array("\r\n", "\r"), "\n", $text);

    return (string) preg_replace('/[^\x{9}\x{A}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text);
}

/**
 * A typed number as the sheet stores it, or null when the text is not one.
 * A comma is taken as the decimal mark when it is the only mark (Turkish
 * input); grouping marks are not read, so "1.234,5" stays text. Text with a
 * leading zero ("007", a phone number) and numbers longer than a
 * spreadsheet keeps exactly (15 digits) stay text too, as they would lose
 * what makes them what they are.
 *
 * @param string $text
 * @return string|null
 */
function pg_office_xlsx_parse_number($text)
{
    $text = trim((string) $text);

    if (!preg_match('/^([+-]?)(\d*)(?:[.,](\d+))?(?:[eE]([+-]?\d{1,3}))?$/', $text, $match)) {
        return null;
    }

    $sign = ($match[1] === '-') ? '-' : '';
    $whole = $match[2];
    $fraction = isset($match[3]) ? $match[3] : '';
    $exponent = isset($match[4]) ? $match[4] : '';

    if (($whole === '') && ($fraction === '')) {
        return null;
    }

    if ((strlen($whole) > 1) && ($whole[0] === '0')) {
        return null;
    }

    $fraction = rtrim($fraction, '0');

    if (strlen(ltrim($whole . $fraction, '0')) > 15) {
        return null;
    }

    $number = (($whole === '') ? '0' : $whole) . (($fraction !== '') ? '.' . $fraction : '');

    if ($exponent !== '') {
        $value = (float) ($number . 'E' . $exponent);

        if (is_infinite($value) || is_nan($value)) {
            return null;
        }

        $number .= 'E' . $exponent;
    }

    if (($number === '0') || (preg_match('/^0(\.0*)?(E[+-]?\d+)?$/', $number))) {
        return '0';
    }

    return $sign . $number;
}

/**
 * A typed date as the day count the sheet stores, or null. The forms read
 * are the one the preview shows (2026-10-19, with an optional time) and the
 * Turkish one (19.10.2026). Day 0 is 30 December 1899, or 1 January 1904 in
 * a workbook that counts from there; days before 1 March 1900 are not
 * written, since the 1900 count of spreadsheets holds a day that never was.
 *
 * @param string $text
 * @param bool   $date1904
 * @return string|null
 */
function pg_office_xlsx_parse_date($text, $date1904)
{
    $text = trim((string) $text);

    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?$/', $text, $match)) {
        $year = (int) $match[1];
        $month = (int) $match[2];
        $day = (int) $match[3];
    } elseif (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})(?: (\d{1,2}):(\d{2})(?::(\d{2}))?)?$/', $text, $match)) {
        $year = (int) $match[3];
        $month = (int) $match[2];
        $day = (int) $match[1];
    } else {
        return null;
    }

    $hour = isset($match[4]) ? (int) $match[4] : 0;
    $minute = isset($match[5]) ? (int) $match[5] : 0;
    $second = isset($match[6]) ? (int) $match[6] : 0;

    if (!checkdate($month, $day, $year) || ($hour > 23) || ($minute > 59) || ($second > 59)) {
        return null;
    }

    $days = (int) floor(gmmktime(0, 0, 0, $month, $day, $year) / 86400) + ($date1904 ? 24107 : 25569);

    if ($days < ($date1904 ? 0 : 61)) {
        return null;
    }

    $seconds = ($hour * 3600) + ($minute * 60) + $second;

    if ($seconds === 0) {
        return (string) $days;
    }

    return rtrim(rtrim(sprintf('%.10F', $days + ($seconds / 86400)), '0'), '.');
}

/**
 * A typed yes or no, as the sheet stores a boolean ('1' or '0'), or null.
 *
 * @param string $text
 * @return string|null
 */
function pg_office_xlsx_parse_bool($text)
{
    $text = mb_strtoupper(trim((string) $text), 'UTF-8');

    if (in_array($text, array('TRUE', 'DOĞRU', '1'), true)) {
        return '1';
    }

    if (in_array($text, array('FALSE', 'YANLIŞ', '0'), true)) {
        return '0';
    }

    return null;
}

/**
 * What a typed text becomes in a cell. A cell keeps the kind it has when the
 * text fits it: a text cell stays text whatever is typed, a yes/no cell
 * stays one for TRUE/FALSE, a cell formatted as a date takes a date. An
 * empty cell, or one holding a number, takes a number when the text is one.
 * A cell formatted as text (@) always takes text. A leading "=" is kept as
 * text: no formula is written here.
 *
 * @param string $text     the cleaned text (pg_office_xlsx_clean_text())
 * @param string $was      the cell's kind now: empty, number, string, bool, error
 * @param string $format   the cell's number format kind: date, text or ''
 * @param bool   $date1904
 * @return array kind (empty, number, string, bool), value
 */
function pg_office_xlsx_typed($text, $was, $format, $date1904)
{
    if ($text === '') {
        return array('kind' => 'empty', 'value' => '');
    }

    if (($format === 'text') || ($was === 'string')) {
        return array('kind' => 'string', 'value' => $text);
    }

    if ($was === 'bool') {
        $bool = pg_office_xlsx_parse_bool($text);

        if ($bool !== null) {
            return array('kind' => 'bool', 'value' => $bool);
        }
    }

    if ($format === 'date') {
        $serial = pg_office_xlsx_parse_date($text, $date1904);

        if ($serial !== null) {
            return array('kind' => 'number', 'value' => $serial);
        }
    }

    $number = pg_office_xlsx_parse_number($text);

    if ($number !== null) {
        return array('kind' => 'number', 'value' => $number);
    }

    return array('kind' => 'string', 'value' => $text);
}

/* ---------------------------------------------------------------------------
   The workbook around the sheets
   --------------------------------------------------------------------------- */

/**
 * Does the workbook count its days from 1904 (old Mac files)?
 *
 * @param string $workbook_xml xl/workbook.xml
 * @return bool
 */
function pg_office_xlsx_date1904($workbook_xml)
{
    return (bool) preg_match('/<(?:[A-Za-z_][\w.\-]*:)?workbookPr\b[^>]*\sdate1904\s*=\s*["\'](?:1|true)["\']/', (string) $workbook_xml);
}

/**
 * Does the workbook ask for a password before it is changed? Excel's
 * "password to modify" is a fileSharing element with the password's hash
 * (hashValue, or reservationPassword in older files); read-only recommended
 * alone is advice and no password. A workbook so reserved is not changed
 * here, as a document with writeProtection is not
 * (pg_office_docx_restriction()).
 *
 * @param string $workbook_xml xl/workbook.xml
 * @return bool
 */
function pg_office_xlsx_write_reserved($workbook_xml)
{
    return (bool) preg_match('/<(?:[A-Za-z_][\w.\-]*:)?fileSharing\b[^>]*\s(?:reservationPassword|hashValue)\s*=\s*["\'][^"\']/', (string) $workbook_xml);
}

/**
 * The cell styles that matter to an edit, by style number: those whose
 * number format shows a date, those formatted as text (@), and those not
 * locked by a sheet's protection.
 *
 * @param ZipArchive $zip
 * @param string     $refused set to large or damaged when the style part could not be read whole; a save does not
 *                            guess the kinds then
 * @return array date, text, open: style number => true
 */
function pg_office_xlsx_style_kinds($zip, &$refused = null)
{
    $out = array('date' => pg_office_xlsx_date_styles($zip, $refused), 'text' => array(), 'open' => array());
    $styles = pg_office_xml($zip, 'xl/styles.xml', $status);
    pg_office_refuse($refused, $status);

    if ($styles === null) {
        return $out;
    }

    $text_formats = array(49 => true);

    foreach ($styles->getElementsByTagNameNS('*', 'numFmt') as $format) {
        $code = preg_replace('/"[^"]*"/', '', (string) pg_office_attr($format, 'formatCode'));

        if (strpos((string) $code, '@') !== false) {
            $text_formats[(int) pg_office_attr($format, 'numFmtId')] = true;
        }
    }

    foreach ($styles->getElementsByTagNameNS('*', 'cellXfs') as $xfs) {
        $index = 0;

        foreach ($xfs->childNodes as $xf) {
            if (($xf->nodeType !== XML_ELEMENT_NODE) || ($xf->localName !== 'xf')) {
                continue;
            }

            if (isset($text_formats[(int) pg_office_attr($xf, 'numFmtId')])) {
                $out['text'][$index] = true;
            }

            foreach ($xf->childNodes as $child) {
                if (($child->nodeType === XML_ELEMENT_NODE) && ($child->localName === 'protection')
                    && in_array(strtolower(pg_office_attr($child, 'locked')), array('0', 'false'), true)) {
                    $out['open'][$index] = true;
                }
            }

            $index++;
        }

        break;
    }

    return $out;
}

/**
 * Marks the workbook to calculate every formula when it is next opened: the
 * values the formulas cached were worked out from cells that may have
 * changed, and a spreadsheet program that trusts them would show the old
 * totals. The calculation settings element gains the flag, or is added in
 * its place in the workbook's element order when there is none.
 *
 * The part is small, so it is changed with DOM; only its direct children are
 * looked at (a workbook view may carry an extension list of its own). One
 * past PG_OFFICE_MAX_DOM is not built as a tree.
 *
 * @param string $xml xl/workbook.xml
 * @return string|null null when it is not a workbook, or too large
 */
function pg_office_xlsx_patch_workbook($xml)
{
    if (strlen((string) $xml) > PG_OFFICE_MAX_DOM) {
        return null;
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadXML((string) $xml, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded || ($document->documentElement->localName !== 'workbook')) {
        return null;
    }

    $root = $document->documentElement;
    $calculation = pg_office_xlsx_child($root, 'calcPr');

    if ($calculation === null) {
        // Named with the root's own prefix and no namespace of its own: the
        // part is written out as text, where the prefix (or the default
        // namespace) the root declares is what gives it its meaning.
        $calculation = $document->createElement((($root->prefix !== '') ? $root->prefix . ':' : '') . 'calcPr');

        // The elements that follow calcPr in a workbook, in the schema's
        // order; the new one goes before the first of them the file has.
        $after = array('oleSize', 'customWorkbookViews', 'pivotCaches', 'smartTagPr', 'smartTagTypes', 'webPublishing',
            'fileRecoveryPr', 'webPublishObjects', 'extLst');
        $next = null;

        foreach ($root->childNodes as $child) {
            if (($child->nodeType === XML_ELEMENT_NODE) && in_array($child->localName, $after, true)) {
                $next = $child;
                break;
            }
        }

        if ($next !== null) {
            $root->insertBefore($calculation, $next);
        } else {
            $root->appendChild($calculation);
        }
    }

    $calculation->setAttribute('fullCalcOnLoad', '1');

    if ((string) $document->encoding === '') {
        $document->encoding = 'UTF-8';
    }

    return $document->saveXML();
}

/* ---------------------------------------------------------------------------
   What may not be changed
   --------------------------------------------------------------------------- */

/**
 * Walks one sheet for what decides which of its cells may be changed: the
 * formulas, the linked values, the merges, the array formulas, whether the
 * sheet is protected, and whether every row and cell carries its address
 * (a writer may leave them out; such a sheet is shown but not edited).
 *
 * Cells are listed one by one only when asked, and only up to a row: the
 * preview needs them for the part of the sheet it shows, a save only needs
 * the sheet-wide facts (it looks at the cells it changes itself).
 *
 * A cell the file does not hold takes the row's own style (customFormat)
 * when there is one, else its column's (pg_office_xlsx_new_cell_style()):
 * so that the preview can tell which empty cells of a protected sheet are
 * open, the rows with a style of their own, the columns whose style is
 * unlocked and the cells in the file that keep a locked style although
 * that rule would open them are listed too.
 *
 * @param XMLReader $reader     opened on the sheet
 * @param int       $until_row  list the cells of rows up to this one; 0 for none
 * @param array     $open_styles style number => true for unlocked styles
 * @return array addressed, unusual, crowded (more ranges than are kept), protected, merges, arrays, locked (ref => formula|value),
 *               formulas (ref => text), open (ref => true), closed (ref => true), rows (row => open?), columns ([first, last] 0-based)
 */
function pg_office_xlsx_scan($reader, $until_row, $open_styles)
{
    $out = array(
        'addressed' => true,
        'unusual' => false,
        'crowded' => false,
        'protected' => false,
        'merges' => array(),
        'arrays' => array(),
        'locked' => array(),
        'formulas' => array(),
        'open' => array(),
        'closed' => array(),
        'rows' => array(),
        'columns' => array(),
    );

    $row = 0;
    $row_open = null;
    $open_columns = array();
    $cell = '';
    $record = false;
    $ranges = 0;
    $formula_text = 0;
    $previous = libxml_use_internal_errors(true);
    libxml_clear_errors();

    while (@$reader->read()) {
        $type = $reader->nodeType;

        if (in_array($type, array(XMLReader::COMMENT, XMLReader::CDATA, XMLReader::DOC_TYPE, XMLReader::PI), true)) {
            // The row splice reads the sheet as text and does not know
            // these (a comment or an instruction may hold "<row" of its
            // own); a sheet that has them is shown, not changed.
            $out['unusual'] = true;
            continue;
        }

        if ($type === XMLReader::END_ELEMENT) {
            if ($reader->localName === 'c') {
                $cell = '';
            }
            continue;
        }

        if ($type !== XMLReader::ELEMENT) {
            continue;
        }

        switch ($reader->localName) {
            case 'row':
                $number = (string) $reader->getAttribute('r');

                if (!ctype_digit($number)) {
                    $out['addressed'] = false;
                }

                $row = (int) $number;
                $cell = '';
                $row_open = null;

                if (in_array(strtolower((string) $reader->getAttribute('customFormat')), array('1', 'true'), true) && ((string) $reader->getAttribute('s') !== '')) {
                    $row_open = isset($open_styles[(int) $reader->getAttribute('s')]);

                    if (($until_row > 0) && ($row <= $until_row)) {
                        $out['rows'][$row] = $row_open;
                    }
                }
                break;

            case 'col':
                // The styles of whole columns come before the rows.
                $style = (int) $reader->getAttribute('style');
                $first = (int) $reader->getAttribute('min');
                $last = min((int) $reader->getAttribute('max'), PG_OFFICE_MAX_COLUMNS);

                if (($first < 1) || ($last < $first) || !isset($open_styles[$style])) {
                    break;
                }

                $out['columns'][] = array($first - 1, $last - 1);

                for ($column = $first - 1; $column < $last; $column++) {
                    $open_columns[$column] = true;
                }
                break;

            case 'c':
                $reference = (string) $reader->getAttribute('r');
                $position = pg_office_xlsx_cell($reference);

                if (($position === null) || ($position[0] !== $row)) {
                    $out['addressed'] = false;
                    $cell = '';
                    break;
                }

                $record = ($until_row > 0) && ($row <= $until_row) && ($position[1] < PG_OFFICE_MAX_COLUMNS);
                $cell = $reader->isEmptyElement ? '' : $reference;

                if ($record) {
                    if (((string) $reader->getAttribute('vm') !== '') || ((string) $reader->getAttribute('t') === 'd')) {
                        $out['locked'][$reference] = 'value';
                    } elseif (isset($open_styles[(int) $reader->getAttribute('s')])) {
                        $out['open'][$reference] = true;
                    } elseif (($row_open === null) ? isset($open_columns[$position[1]]) : $row_open) {
                        $out['closed'][$reference] = true;
                    }
                }
                break;

            case 'f':
                if ($cell === '') {
                    break;
                }

                if ((string) $reader->getAttribute('t') === 'array') {
                    $range = pg_office_xlsx_range((string) $reader->getAttribute('ref'));

                    if (($range !== null) && (++$ranges <= PG_OFFICE_EDIT_MAX_RANGES)) {
                        $out['arrays'][] = $range;
                    }
                }

                if ($record) {
                    $out['locked'][$cell] = 'formula';
                    unset($out['open'][$cell], $out['closed'][$cell]);

                    // Read node by node, and only as much as the label
                    // shows (four bytes a character at most).
                    $formula = ($reader->isEmptyElement || ($formula_text >= PG_OFFICE_EDIT_FORMULA_TEXT)) ? '' : pg_office_reader_text($reader, PG_OFFICE_EDIT_FORMULA_LABEL * 4);

                    if ($formula !== '') {
                        $out['formulas'][$cell] = mb_substr($formula, 0, PG_OFFICE_EDIT_FORMULA_LABEL, 'UTF-8');
                        $formula_text += strlen($out['formulas'][$cell]);
                    }
                }
                break;

            case 'mergeCell':
                $range = pg_office_xlsx_range((string) $reader->getAttribute('ref'));

                if (($range !== null) && (++$ranges <= PG_OFFICE_EDIT_MAX_RANGES)) {
                    $out['merges'][] = $range;
                }
                break;

            case 'sheetProtection':
                $out['protected'] = in_array(strtolower((string) $reader->getAttribute('sheet')), array('1', 'true'), true);
                break;
        }
    }

    // A sheet that stops being XML part of the way through was not read to
    // its end, so its merges and its protection are not known.
    if (count(libxml_get_errors()) > 0) {
        $out['unusual'] = true;
    }

    $out['crowded'] = ($ranges > PG_OFFICE_EDIT_MAX_RANGES);

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return $out;
}

/**
 * The areas of a sheet that belong to something the spreadsheet program
 * keeps in step with the cells: the header and totals rows of each table
 * (their text is also the column's name inside the table part, and a
 * mismatch is reported as a damaged file) and each pivot table.
 *
 * @param ZipArchive $zip
 * @param string     $sheet_part
 * @param string     $refused    set to large or damaged when the sheet's relationships or one of its table parts
 *                               could not be read whole
 * @return array[]|null [first row, first column, last row, last column, table|pivot]; null when the
 *                      sheet has more tables than are read (PG_OFFICE_EDIT_MAX_TABLES), or when $refused
 */
function pg_office_xlsx_bound_ranges($zip, $sheet_part, &$refused = null)
{
    $out = array();
    $folder = substr($sheet_part, 0, (int) strrpos($sheet_part, '/') + 1);
    $rels = pg_office_rels($zip, $folder . '_rels/' . substr($sheet_part, strlen($folder)) . '.rels', $refused);
    $tables = 0;

    if ($refused !== '') {
        return null;
    }

    foreach ($rels as $relationship) {
        if ($relationship['external'] || !in_array($relationship['type'], array('table', 'pivottable'), true)) {
            continue;
        }

        if (++$tables > PG_OFFICE_EDIT_MAX_TABLES) {
            return null;
        }

        $part = pg_office_xml($zip, pg_office_part_name($folder, $relationship['target']), $status);
        pg_office_refuse($refused, $status);

        // A table whose area cannot be read leaves no cell of the sheet
        // open to a change.
        if ($refused !== '') {
            return null;
        }

        if ($part === null) {
            continue;
        }

        if ($relationship['type'] === 'table') {
            $range = pg_office_xlsx_range(pg_office_attr($part->documentElement, 'ref'));

            if ($range === null) {
                continue;
            }

            $header = pg_office_attr($part->documentElement, 'headerRowCount');
            $header = ($header === '') ? 1 : (int) $header;
            $totals = (int) pg_office_attr($part->documentElement, 'totalsRowCount');

            if ($header > 0) {
                $out[] = array($range[0], $range[1], min($range[2], $range[0] + $header - 1), $range[3], 'table');
            }

            if ($totals > 0) {
                $out[] = array(max($range[0], $range[2] - $totals + 1), $range[1], $range[2], $range[3], 'table');
            }

            continue;
        }

        foreach ($part->getElementsByTagNameNS('*', 'location') as $location) {
            $range = pg_office_xlsx_range(pg_office_attr($location, 'ref'));

            if ($range !== null) {
                $out[] = array($range[0], $range[1], $range[2], $range[3], 'pivot');
            }

            break;
        }
    }

    return $out;
}

/**
 * Why a cell may not be changed, from the sheet-wide facts: '' when it may.
 * The cell's own content (a formula, a linked value) is looked at by the
 * caller, which has the cell in hand.
 *
 * @param int   $row
 * @param int   $column
 * @param array $scan   pg_office_xlsx_scan()
 * @param array $bound  pg_office_xlsx_bound_ranges()
 * @return string merged, array, table, pivot or ''
 */
function pg_office_xlsx_range_lock($row, $column, $scan, $bound)
{
    // The top left cell of a merge is the one that shows; the rest are kept
    // under it and only come back when the merge is undone.
    foreach ($scan['merges'] as $range) {
        if (pg_office_xlsx_in_range($row, $column, $range) && (($row !== $range[0]) || ($column !== $range[1]))) {
            return 'merged';
        }
    }

    foreach ($scan['arrays'] as $range) {
        if (pg_office_xlsx_in_range($row, $column, $range)) {
            return 'array';
        }
    }

    foreach ($bound as $range) {
        if (pg_office_xlsx_in_range($row, $column, $range)) {
            return $range[4];
        }
    }

    return '';
}

/**
 * Is the package digitally signed? Changing any part of it would break the
 * signature, so such a file is not changed here.
 *
 * @param ZipArchive $zip
 * @return bool
 */
function pg_office_signed($zip)
{
    for ($index = 0; $index < $zip->numFiles; $index++) {
        if (strpos((string) $zip->getNameIndex($index), '_xmlsignatures/') === 0) {
            return true;
        }
    }

    return false;
}

/**
 * What the preview needs to offer a sheet's cells for editing: whether the
 * sheet may be changed at all (and why not), and which of its shown cells
 * may not, with the reason.
 *
 * @param ZipArchive $zip       open on the package
 * @param array      $sheet     pg_office_xlsx_sheets() entry
 * @param int        $until_row the last row number the preview shows
 * @param array      $styles    pg_office_xlsx_style_kinds()
 * @return array ok, why, protected, locked, formulas, open, closed, rows, columns, ranges
 */
function pg_office_xlsx_sheet_edit_info($zip, $sheet, $until_row, $styles)
{
    $out = array('ok' => false, 'why' => '', 'protected' => false, 'locked' => array(), 'formulas' => array(), 'open' => array(), 'closed' => array(), 'rows' => array(), 'columns' => array(), 'ranges' => array());

    if ($sheet['type'] !== 'worksheet') {
        $out['why'] = 'kind';
        return $out;
    }

    $stat = $zip->statName($sheet['part']);

    if (($stat === false) || ((int) $stat['size'] > PG_OFFICE_EDIT_MAX_PART)) {
        $out['why'] = 'large';
        return $out;
    }

    // Read whole, as the save reads it, and only when it unpacks to exactly
    // the size its entry declares (a stream through zip:// would go on).
    $xml = (string) pg_zip_entry_contents($zip, $sheet['part'], PG_OFFICE_EDIT_MAX_PART, $unread);

    if (!pg_office_xlsx_utf8_part(substr($xml, 0, 200))) {
        $out['why'] = 'unusual';
        return $out;
    }

    $reader = new XMLReader();

    if (($xml === '') || !@$reader->XML($xml, null, LIBXML_NONET)) {
        $out['why'] = 'unusual';
        return $out;
    }

    $scan = pg_office_xlsx_scan($reader, max(0, (int) $until_row), $styles['open']);
    $reader->close();
    unset($xml);

    if (!$scan['addressed']) {
        $out['why'] = 'layout';
        return $out;
    }

    if ($scan['unusual']) {
        $out['why'] = 'unusual';
        return $out;
    }

    $bound = pg_office_xlsx_bound_ranges($zip, $sheet['part'], $bound_refused);

    if ($bound_refused === 'damaged') {
        $out['why'] = 'unusual';
        return $out;
    }

    if ($scan['crowded'] || ($bound === null)) {
        $out['why'] = 'large';
        return $out;
    }

    $out['ok'] = true;
    $out['protected'] = $scan['protected'];
    $out['locked'] = $scan['locked'];
    $out['formulas'] = $scan['formulas'];
    // What opens a cell matters only under protection.
    foreach (array('open', 'closed', 'rows', 'columns') as $name) {
        $out[$name] = $scan['protected'] ? $scan[$name] : array();
    }

    $ranges = array();

    foreach ($scan['merges'] as $range) {
        $ranges[] = array($range[0], $range[1], $range[2], $range[3], 'merged');
    }

    foreach ($scan['arrays'] as $range) {
        $ranges[] = array($range[0], $range[1], $range[2], $range[3], 'array');
    }

    // Only what reaches the rows and columns the preview shows goes to it,
    // cut to them; a save looks at the sheet's own again.
    $last_row = max(0, (int) $until_row);

    foreach (array_merge($ranges, $bound) as $range) {
        if (($range[0] > $last_row) || ($range[1] >= PG_OFFICE_MAX_COLUMNS)) {
            continue;
        }

        $out['ranges'][] = array($range[0], $range[1], min($range[2], $last_row), min($range[3], PG_OFFICE_MAX_COLUMNS - 1), $range[4]);
    }

    return $out;
}

/**
 * Is an XML part declared as UTF-8 (or without a declaration, which means
 * the same)? The row splice works on the text as UTF-8.
 *
 * @param string $head the first bytes of the part
 * @return bool
 */
function pg_office_xlsx_utf8_part($head)
{
    $head = (string) $head;

    if ((strncmp($head, "\xFE\xFF", 2) === 0) || (strncmp($head, "\xFF\xFE", 2) === 0)) {
        return false;
    }

    if (preg_match('/^(?:\xEF\xBB\xBF)?<\?xml[^>]*\sencoding\s*=\s*["\']([^"\']+)["\']/', $head, $match)) {
        return in_array(strtolower($match[1]), array('utf-8', 'utf8'), true);
    }

    return true;
}

/* ---------------------------------------------------------------------------
   Rewriting a sheet
   --------------------------------------------------------------------------- */

/**
 * The kind of value a cell holds now, as pg_office_xlsx_typed() reads it.
 *
 * @param DOMElement|null $cell
 * @return string empty, number, string, bool, error
 */
function pg_office_xlsx_cell_kind($cell)
{
    if ($cell === null) {
        return 'empty';
    }

    $type = (string) $cell->getAttribute('t');

    if (in_array($type, array('s', 'inlineStr', 'str'), true)) {
        return 'string';
    }

    if ($type === 'b') {
        return 'bool';
    }

    if ($type === 'e') {
        return 'error';
    }

    return (pg_office_xlsx_child($cell, 'v') !== null) ? 'number' : 'empty';
}

/**
 * The first child element of a node with this local name.
 *
 * @param DOMNode $node
 * @param string  $local
 * @return DOMElement|null
 */
function pg_office_xlsx_child($node, $local)
{
    foreach ($node->childNodes as $child) {
        if (($child->nodeType === XML_ELEMENT_NODE) && ($child->localName === $local)) {
            return $child;
        }
    }

    return null;
}

/**
 * Puts a new child into a cell or a row in its place in the schema's order:
 * before the trailing extension list when there is one, else last.
 *
 * @param DOMElement $parent
 * @param DOMNode    $child
 */
function pg_office_xlsx_append($parent, $child)
{
    $extension = pg_office_xlsx_child($parent, 'extLst');

    if ($extension !== null) {
        $parent->insertBefore($child, $extension);
    } else {
        $parent->appendChild($child);
    }
}

/**
 * The style a new cell takes: the row's when the row carries one of its own,
 * else its column's, else none (the default style).
 *
 * @param DOMElement $row
 * @param int        $column
 * @param array      $column_styles column number => style number
 * @return string
 */
function pg_office_xlsx_new_cell_style($row, $column, $column_styles)
{
    if (in_array(strtolower((string) $row->getAttribute('customFormat')), array('1', 'true'), true) && ((string) $row->getAttribute('s') !== '')) {
        return (string) (int) $row->getAttribute('s');
    }

    return isset($column_styles[$column]) ? (string) (int) $column_styles[$column] : '';
}

/**
 * The column styles a sheet declares (its <cols> list), by column number.
 *
 * @param string $xml    the sheet
 * @param string $prefix the sheet's own namespace prefix, with its colon
 * @return array column number => style number
 */
function pg_office_xlsx_column_styles($xml, $prefix)
{
    $out = array();
    $quoted = preg_quote($prefix, '/');

    if (!preg_match('/<' . $quoted . 'cols(?=[\s>])[^>]*>(.*?)<\/' . $quoted . 'cols>/s', $xml, $block)) {
        return $out;
    }

    preg_match_all('/<' . $quoted . 'col(?=[\s\/>])([^>]*)>/', $block[1], $columns);

    foreach ($columns[1] as $attributes) {
        $values = array();

        foreach (array('min', 'max', 'style') as $name) {
            $values[$name] = preg_match('/\s' . $name . '\s*=\s*["\'](\d+)["\']/', $attributes, $found) ? (int) $found[1] : 0;
        }

        if (($values['style'] <= 0) || ($values['min'] <= 0) || ($values['max'] < $values['min'])) {
            continue;
        }

        // A run over the rest of the sheet (min 1, max 16384) need not
        // be spelt out cell by cell: only the columns a preview can show
        // ever take a new cell.
        for ($column = $values['min']; $column <= min($values['max'], PG_OFFICE_MAX_COLUMNS); $column++) {
            $out[$column - 1] = $values['style'];
        }
    }

    return $out;
}

/**
 * Writes the changed values into one row.
 *
 * The row is parsed on its own, inside an element that carries the sheet's
 * namespace declarations so its prefixes resolve, changed with DOM and
 * written back as text. Cells keep their style; a cell that did not exist is
 * made in its column's place with the style a spreadsheet program would
 * give it. A formula, a linked value or a locked cell of a protected sheet
 * is refused here too, whatever the caller checked before.
 *
 * @param string $row_xml the row as the sheet has it
 * @param int    $number  the row's number
 * @param array  $changes column number => typed text (cleaned)
 * @param array  $context declarations, prefix, styles, date1904, protected, column_styles
 * @return array ok, xml, error (layout, locked, xml, large), cell
 */
function pg_office_xlsx_patch_row($row_xml, $number, $changes, $context)
{
    $fail = function ($error, $cell = '') {
        return array('ok' => false, 'xml' => '', 'error' => $error, 'cell' => $cell);
    };

    // The row is built as a tree to be changed; one past the DOM ceiling
    // (pg_office_xml()) is not.
    if (strlen((string) $row_xml) > PG_OFFICE_MAX_DOM) {
        return $fail('large');
    }

    $prefix = (string) $context['prefix'];
    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadXML('<?xml version="1.0" encoding="UTF-8"?><pg-row ' . $context['declarations'] . '>' . $row_xml . '</pg-row>', LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
        return $fail('xml');
    }

    $row = pg_office_xlsx_child($document->documentElement, 'row');

    if ($row === null) {
        return $fail('xml');
    }

    $cells = array();

    foreach ($row->childNodes as $child) {
        if (($child->nodeType !== XML_ELEMENT_NODE) || ($child->localName !== 'c')) {
            continue;
        }

        $position = pg_office_xlsx_cell($child->getAttribute('r'));

        if (($position === null) || ($position[0] !== (int) $number)) {
            return $fail('layout');
        }

        $cells[$position[1]] = $child;
    }

    $styles = $context['styles'];
    $added = array();
    ksort($changes);

    foreach ($changes as $column => $text) {
        $reference = pg_office_xlsx_column_name($column) . $number;
        $cell = isset($cells[$column]) ? $cells[$column] : null;

        if (($cell !== null) && ((pg_office_xlsx_child($cell, 'f') !== null) || ((string) $cell->getAttribute('vm') !== '') || ((string) $cell->getAttribute('t') === 'd'))) {
            return $fail('locked', $reference);
        }

        $style = ($cell !== null) ? (string) $cell->getAttribute('s') : pg_office_xlsx_new_cell_style($row, $column, $context['column_styles']);
        $style_number = (int) $style;

        if ($context['protected'] && !isset($styles['open'][$style_number])) {
            return $fail('locked', $reference);
        }

        $format = isset($styles['text'][$style_number]) ? 'text' : (isset($styles['date'][$style_number]) ? 'date' : '');
        $typed = pg_office_xlsx_typed($text, pg_office_xlsx_cell_kind($cell), $format, $context['date1904']);

        if ($typed['kind'] === 'empty') {
            if ($cell === null) {
                continue;
            }

            pg_office_xlsx_clear_cell($cell);

            // A cell left with nothing but its address is no cell at all;
            // one with a style keeps it, so its borders and fill stay.
            if (($cell->attributes->length === 1) && (pg_office_xlsx_child($cell, 'extLst') === null)) {
                $row->removeChild($cell);
                unset($cells[$column]);
            }

            continue;
        }

        if ($cell === null) {
            $cell = $document->createElement($prefix . 'c');
            $cell->setAttribute('r', $reference);

            if (($style !== '') && ($style !== '0')) {
                $cell->setAttribute('s', $style);
            }

            // Cells stand in column order; the new one goes before the
            // first that comes after it.
            $next = null;

            foreach ($cells as $other_column => $other) {
                if ($other_column > $column) {
                    $next = $other;
                    break;
                }
            }

            if ($next !== null) {
                $row->insertBefore($cell, $next);
            } else {
                pg_office_xlsx_append($row, $cell);
            }

            $cells[$column] = $cell;
            ksort($cells);
            $added[] = $column;
        }

        pg_office_xlsx_clear_cell($cell);

        if ($typed['kind'] === 'string') {
            $cell->setAttribute('t', 'inlineStr');
            $inline = $document->createElement($prefix . 'is');
            $text_node = $document->createElement($prefix . 't');

            // Spaces at either end, and line breaks, are dropped by a
            // reader unless the text says they belong to it.
            if ((trim($typed['value']) !== $typed['value']) || (strpbrk($typed['value'], "\n\t") !== false)) {
                $text_node->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            }

            $text_node->appendChild($document->createTextNode($typed['value']));
            $inline->appendChild($text_node);
            pg_office_xlsx_append($cell, $inline);
            continue;
        }

        if ($typed['kind'] === 'bool') {
            $cell->setAttribute('t', 'b');
        }

        $value = $document->createElement($prefix . 'v');
        $value->appendChild($document->createTextNode($typed['value']));
        pg_office_xlsx_append($cell, $value);
    }

    // The span a row declares is a hint for the reader's buffers; a cell
    // outside it widens it, and a span list it cannot read is dropped.
    if (!empty($added) && $row->hasAttribute('spans')) {
        if (preg_match('/^(\d+):(\d+)$/', (string) $row->getAttribute('spans'), $span)) {
            $row->setAttribute('spans', min((int) $span[1], min($added) + 1) . ':' . max((int) $span[2], max($added) + 1));
        } else {
            $row->removeAttribute('spans');
        }
    }

    return array('ok' => true, 'xml' => $document->saveXML($row), 'error' => '', 'cell' => '');
}

/**
 * Takes a cell's value away, keeping the cell, its address and its style.
 *
 * @param DOMElement $cell
 */
function pg_office_xlsx_clear_cell($cell)
{
    foreach (array('v', 'is') as $local) {
        while (($child = pg_office_xlsx_child($cell, $local)) !== null) {
            $cell->removeChild($child);
        }
    }

    $cell->removeAttribute('t');
}

/**
 * Writes the changed values into a sheet, given as text.
 *
 * The rows that carry a change are found in the text and rewritten one by
 * one (pg_office_xlsx_patch_row()); everything between them is copied as it
 * was, byte for byte. Rows stand in number order in a sheet, so one pass
 * from the top finds them all. A row that is not in the sheet is not made:
 * the preview only offers the rows the sheet has.
 *
 * @param string $xml     the sheet part
 * @param array  $changes row number => [column number => cleaned text]
 * @param array  $context styles, date1904, protected
 * @return array ok, xml, error (unusual, layout, missing, locked, xml, large), cell
 */
function pg_office_xlsx_patch_sheet($xml, $changes, $context)
{
    $fail = function ($error, $cell = '') {
        return array('ok' => false, 'xml' => '', 'error' => $error, 'cell' => $cell);
    };

    $xml = (string) $xml;

    // The splice reads the sheet as text, which is only safe on the markup
    // spreadsheet programs write: no comments, no CDATA, no document type.
    if ((strpos($xml, '<!--') !== false) || (strpos($xml, '<![CDATA[') !== false) || (stripos($xml, '<!DOCTYPE') !== false) || !pg_office_xlsx_utf8_part(substr($xml, 0, 200))) {
        return $fail('unusual');
    }

    if (!preg_match('/<([A-Za-z_][\w.\-]*:)?worksheet(?=[\s>])([^>]*)>/', $xml, $root)) {
        return $fail('unusual');
    }

    $prefix = $root[1];
    $quoted = preg_quote($prefix, '/');
    preg_match_all('/\sxmlns(?::[A-Za-z_][\w.\-]*)?\s*=\s*(?:"[^"]*"|\'[^\']*\')/', $root[2], $declarations);

    $context['prefix'] = $prefix;
    $context['declarations'] = implode('', $declarations[0]);
    $context['column_styles'] = pg_office_xlsx_column_styles($xml, $prefix);

    if (!preg_match('/<' . $quoted . 'sheetData(?=[\s>\/])[^>]*>/', $xml, $data, PREG_OFFSET_CAPTURE) || (substr($data[0][0], -2) === '/>')) {
        return $fail('missing');
    }

    $position = $data[0][1] + strlen($data[0][0]);
    $data_end = strpos($xml, '</' . $prefix . 'sheetData>', $position);

    if ($data_end === false) {
        return $fail('unusual');
    }

    ksort($changes);
    $targets = array_keys($changes);
    $target = 0;
    $out = '';
    $copied = 0;
    $close = '</' . $prefix . 'row>';
    $pattern = '/<' . $quoted . 'row(?=[\s>\/])[^>]*>/';

    while ($target < count($targets)) {
        if (!preg_match($pattern, $xml, $tag, PREG_OFFSET_CAPTURE, $position) || ($tag[0][1] > $data_end)) {
            return $fail('missing', pg_office_xlsx_column_name(key($changes[$targets[$target]])) . $targets[$target]);
        }

        $start = $tag[0][1];

        if (!preg_match('/\sr\s*=\s*["\'](\d+)["\']/', $tag[0][0], $number)) {
            return $fail('layout');
        }

        if (substr($tag[0][0], -2) === '/>') {
            $finish = $start + strlen($tag[0][0]);
        } else {
            $end = strpos($xml, $close, $start + strlen($tag[0][0]));

            if (($end === false) || ($end > $data_end)) {
                return $fail('unusual');
            }

            $finish = $end + strlen($close);
        }

        $row_number = (int) $number[1];

        if ($row_number > $targets[$target]) {
            return $fail('missing', pg_office_xlsx_column_name(key($changes[$targets[$target]])) . $targets[$target]);
        }

        if ($row_number === $targets[$target]) {
            $patched = pg_office_xlsx_patch_row(substr($xml, $start, $finish - $start), $row_number, $changes[$row_number], $context);

            if (!$patched['ok']) {
                return $fail($patched['error'], $patched['cell']);
            }

            $out .= substr($xml, $copied, $start - $copied) . $patched['xml'];
            $copied = $finish;
            $target++;
        }

        $position = $finish;
    }

    return array('ok' => true, 'xml' => $out . substr($xml, $copied), 'error' => '', 'cell' => '');
}

/**
 * Takes the cached results out of a sheet's formula cells, leaving the
 * formulas, so that whatever opens the file next works them out from the
 * cells as they are now.
 *
 * Done on the text, cell by cell: a formula and a value never contain a
 * "<" of their own (it is escaped), so a cell's formula and the value after
 * it are matched exactly. The value's type goes with the value. A cell whose
 * value is linked to more than its text (a picture from IMAGE(), a data
 * type: the vm attribute) keeps it. A sheet with markup the match does not
 * read (comments, CDATA) is left as it was.
 *
 * @param string $xml the sheet part
 * @return array ok, xml, count (cells cleared)
 */
function pg_office_xlsx_strip_formula_values($xml)
{
    $xml = (string) $xml;
    $count = 0;

    if ((strpos($xml, '<!--') !== false) || (strpos($xml, '<![CDATA[') !== false) || (stripos($xml, '<!DOCTYPE') !== false)
        || !preg_match('/<([A-Za-z_][\w.\-]*:)?worksheet(?=[\s>])/', $xml, $root)) {
        return array('ok' => false, 'xml' => $xml, 'count' => 0);
    }

    $prefix = isset($root[1]) ? $root[1] : '';
    $quoted = preg_quote($prefix, '/');

    // <c ...>, the formula (empty or with its text), then the value. The
    // attributes never swallow the slash of a tag that closes itself.
    $attributes = '(?:[^>\/]|\/(?!>))*+';
    $pattern = '/<' . $quoted . 'c(?=[\s>])(' . $attributes . ')>(\s*+)'
        . '(<' . $quoted . 'f(?=[\s>\/])' . $attributes . '(?:\/>|>[^<]*+<\/' . $quoted . 'f>))(\s*+)'
        . '<' . $quoted . 'v(?=[\s>\/])' . $attributes . '(?:\/>|>[^<]*+<\/' . $quoted . 'v>)/';

    $out = preg_replace_callback($pattern, function ($match) use ($prefix, &$count) {
        if (preg_match('/\svm\s*=/', $match[1])) {
            return $match[0];
        }

        $count++;

        return '<' . $prefix . 'c' . preg_replace('/\st\s*=\s*(?:"[^"]*"|\'[^\']*\')/', '', $match[1]) . '>' . $match[2] . $match[3] . $match[4];
    }, $xml);

    if (!is_string($out)) {
        return array('ok' => false, 'xml' => $xml, 'count' => 0);
    }

    return array('ok' => true, 'xml' => $out, 'count' => $count);
}

/**
 * Whether a part is well-formed XML, read the way a reader would.
 *
 * @param string $xml
 * @return bool
 */
function pg_office_xml_well_formed($xml)
{
    $reader = new XMLReader();
    $previous = libxml_use_internal_errors(true);
    libxml_clear_errors();
    $ok = (bool) @$reader->XML((string) $xml, null, LIBXML_NONET);

    if ($ok) {
        while (@$reader->read()) {
        }

        $ok = (count(libxml_get_errors()) === 0);
        $reader->close();
    }

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return $ok;
}

/* ---------------------------------------------------------------------------
   The package
   --------------------------------------------------------------------------- */

/**
 * The version of a file as the preview hands it out and a save asks for it
 * back: a hash of its bytes.
 *
 * @param string $path
 * @return string '' when it cannot be read
 */
function pg_office_version($path)
{
    $hash = @sha1_file($path);

    return is_string($hash) ? $hash : '';
}

/**
 * Where the new package is built before it takes the old one's place.
 *
 * @return string
 */
function pg_office_edit_temp_directory()
{
    return PG_FUNCTIONS_DIR . '/data/temp';
}

/**
 * Holds a lock on one file's saves, so two saves that both read the same
 * version cannot both write. Waits up to five seconds for a save under way.
 *
 * The lock file goes when its holder lets go (pg_office_edit_unlock()), so
 * a lock taken on a file that is no longer the one at the path guards
 * nothing: the file is opened again and the lock asked for again.
 *
 * @param string $path      the file
 * @param string $directory where the lock file lives
 * @return array|null handle, file
 */
function pg_office_edit_lock($path, $directory)
{
    $file = rtrim($directory, '/\\') . '/office_edit_' . sha1($path) . '.lock';

    for ($attempt = 0; $attempt < 20; $attempt++) {
        $handle = @fopen($file, 'c');

        if ($handle === false) {
            return null;
        }

        if (@flock($handle, LOCK_EX | LOCK_NB)) {
            clearstatcache(true, $file);
            $held = @fstat($handle);
            $now = @stat($file);

            if (($held !== false) && ($now !== false) && ($held['ino'] === $now['ino']) && ($held['dev'] === $now['dev'])) {
                return array('handle' => $handle, 'file' => $file);
            }

            @flock($handle, LOCK_UN);
        }

        fclose($handle);
        usleep(250000);
    }

    return null;
}

/**
 * Lets go of pg_office_edit_lock() and takes its file away, while it is
 * still held, so nothing is left behind in the temporary folder.
 *
 * @param array|null $lock
 */
function pg_office_edit_unlock($lock)
{
    if (is_array($lock) && is_resource($lock['handle'])) {
        @unlink($lock['file']);
        @flock($lock['handle'], LOCK_UN);
        fclose($lock['handle']);
    }
}

/**
 * Puts the new package in the old one's place. A rename is one step; on
 * Windows a file another process holds open cannot be renamed over, and
 * writing into it in place is the way left. The old bytes are put aside
 * first then, so a copy that stops half way is undone; and when even that
 * fails, both copies stay in the temporary folder rather than the only
 * whole one being deleted.
 *
 * @param string $new
 * @param string $path
 * @return bool
 */
function pg_office_replace_file($new, $path)
{
    $mode = @fileperms($path);
    $done = @rename($new, $path);

    if (!$done) {
        $old = $new . '.old';

        if (!@copy($path, $old)) {
            @unlink($old);
            @unlink($new);

            return false;
        }

        if (@copy($new, $path)) {
            @unlink($new);
            @unlink($old);
            $done = true;
        } elseif (@copy($old, $path)) {
            @unlink($old);
            @unlink($new);
        }
    }

    clearstatcache(true, $path);

    if ($done && ($mode !== false)) {
        @chmod($path, $mode & 0777);
    }

    return $done;
}

/**
 * The message for a refused save, by its reason.
 *
 * @param string $code
 * @param string $cell  the cell concerned, when there is one
 * @param string $sheet the sheet's name, when there is one
 * @return string
 */
function pg_office_edit_message($code, $cell = '', $sheet = '')
{
    switch ($code) {
        case 'ready':
            return lang('This server cannot change spreadsheets (the PHP zip or xml extension is missing).');
        case 'changed':
            return lang('The file was changed after you opened it. Close the window and open the file again, then make your changes.');
        case 'busy':
            return lang('The file is being saved by someone else. Try again in a moment.');
        case 'signed':
            return lang('The file is digitally signed. Changing it here would break the signature.');
        case 'password':
            return lang('The file has a password to change it, so its cells are not changed here.');
        case 'many':
            return lang(array('string' => 'Too many cells were changed at once. Save at most {var:1} cells at a time.', 'vars' => array(PG_OFFICE_EDIT_MAX_CHANGES)));
        case 'long':
            return lang(array('string' => 'The text in cell {var:1} is longer than a spreadsheet cell can hold.', 'vars' => array($cell)));
        case 'text':
            return lang(array('string' => 'The text in cell {var:1} could not be read.', 'vars' => array($cell)));
        case 'locked':
            return lang(array('string' => 'Cell {var:1} on sheet {var:2} cannot be changed here.', 'vars' => array($cell, $sheet)));
        case 'sheet':
            return lang(array('string' => 'Sheet {var:1} cannot be changed here.', 'vars' => array($sheet)));
        case 'missing':
            return lang(array('string' => 'Cell {var:1} on sheet {var:2} is not in the file.', 'vars' => array($cell, $sheet)));
        case 'large':
            return lang(array('string' => 'Sheet {var:1} is too large to change here.', 'vars' => array($sheet)));
    }

    return lang('The file could not be saved.');
}

/**
 * Writes the changed cells into a workbook on disk.
 *
 * @param string $path      the workbook
 * @param array  $changes   list of [sheet => position in the workbook, name => the sheet's name, cell => "B7", value => text]
 * @param string $version   what the person opened (pg_office_version())
 * @param string $directory where the new package is built; data/temp when ''
 * @return array ok, code, error, size, version, changed
 */
function pg_office_xlsx_save($path, $changes, $version, $directory = '')
{
    $fail = function ($code, $cell = '', $sheet = '') {
        return array('ok' => false, 'code' => $code, 'error' => pg_office_edit_message($code, $cell, $sheet), 'size' => 0, 'version' => '', 'changed' => 0);
    };

    if (!pg_office_ready()) {
        return $fail('ready');
    }

    if (!is_array($changes) || empty($changes)) {
        return $fail('none');
    }

    if (count($changes) > PG_OFFICE_EDIT_MAX_CHANGES) {
        return $fail('many');
    }

    // What was asked, by sheet, row and column; a cell named twice takes
    // the last value.
    $wanted = array();

    foreach ($changes as $change) {
        if (!is_array($change) || !isset($change['sheet'], $change['cell']) || !is_scalar($change['sheet']) || !is_string($change['cell'])) {
            return $fail('none');
        }

        $position = pg_office_xlsx_cell(strtoupper($change['cell']));
        $value = isset($change['value']) ? $change['value'] : '';

        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        $text = pg_office_xlsx_clean_text($value);

        if (($position === null) || ($position[1] >= PG_OFFICE_MAX_COLUMNS)) {
            return $fail('none');
        }

        $reference = pg_office_xlsx_column_name($position[1]) . $position[0];

        if ($text === null) {
            return $fail('text', $reference);
        }

        if (mb_strlen($text, 'UTF-8') > PG_OFFICE_EDIT_MAX_TEXT) {
            return $fail('long', $reference);
        }

        $sheet = (int) $change['sheet'];
        $wanted[$sheet]['name'] = isset($change['name']) ? (string) $change['name'] : null;
        $wanted[$sheet]['rows'][$position[0]][$position[1]] = $text;
    }

    $directory = ($directory !== '') ? $directory : pg_office_edit_temp_directory();

    if (!is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }

    $lock = pg_office_edit_lock($path, $directory);

    if ($lock === null) {
        return $fail('busy');
    }

    $result = pg_office_xlsx_save_locked($path, $wanted, (string) $version, $directory, $fail);
    pg_office_edit_unlock($lock);

    return $result;
}

/**
 * The part of pg_office_xlsx_save() that runs under the file's lock.
 *
 * @param string   $path
 * @param array    $wanted    sheet position => [name, rows => [row => [column => text]]]
 * @param string   $version
 * @param string   $directory
 * @param callable $fail
 * @return array
 */
function pg_office_xlsx_save_locked($path, $wanted, $version, $directory, $fail)
{
    return pg_office_save_package($path, $version, $directory, $fail, function ($zip, $spool) use ($wanted, $fail) {
        return pg_office_xlsx_save_parts($zip, $wanted, $fail, $spool);
    });
}

/**
 * Writes the parts a save changed into the package, for a workbook and a
 * document alike, under the file's lock: the version the person opened must
 * still be the file's, $prepare works out the changed parts from the open
 * package and writes each to a file of its own (so only one part is held in
 * memory at a time), and the new package is built beside the old one as a
 * copy with those parts put in, read back, and only then put in its place.
 * The part files are removed afterwards, whatever happened.
 *
 * @param string   $path
 * @param string   $version   what the person opened (pg_office_version())
 * @param string   $directory where the copy and the part files are written
 * @param callable $fail      the refusal builder of the caller
 * @param callable $prepare   function (ZipArchive $zip, callable $spool): ok, parts (part name => file), changed; or a refusal. $spool(string $xml) writes a part and gives its file, or null.
 * @return array ok, code, error, size, version, changed
 */
function pg_office_save_package($path, $version, $directory, $fail, $prepare)
{
    if (($version === '') || !hash_equals(pg_office_version($path), $version)) {
        return $fail('changed');
    }

    $zip = pg_office_open($path, $error);

    if ($zip === null) {
        return array('ok' => false, 'code' => 'read', 'error' => $error, 'size' => 0, 'version' => '', 'changed' => 0);
    }

    $spooled = array();

    $spool = function ($xml) use ($directory, &$spooled) {
        $file = rtrim($directory, '/\\') . '/office_edit_' . bin2hex(random_bytes(8)) . '.part';
        $spooled[] = $file;

        return (@file_put_contents($file, $xml) === strlen($xml)) ? $file : null;
    };

    try {
        $result = $prepare($zip, $spool);
        $zip->close();

        if (!$result['ok']) {
            return $result;
        }

        // The new package is a copy of the old one with the changed parts
        // put in; every other entry is carried across as it was stored.
        $new = rtrim($directory, '/\\') . '/office_edit_' . bin2hex(random_bytes(8)) . '.tmp';

        if (!@copy($path, $new)) {
            return $fail('save');
        }

        $package = new ZipArchive();

        if ($package->open($new) !== true) {
            @unlink($new);
            return $fail('save');
        }

        foreach ($result['parts'] as $name => $file) {
            if (!$package->addFile($file, $name)) {
                $package->close();
                @unlink($new);
                return $fail('save');
            }

            if (method_exists($package, 'setCompressionName')) {
                $package->setCompressionName($name, ZipArchive::CM_DEFLATE);
            }
        }

        if (!$package->close()) {
            @unlink($new);
            return $fail('save');
        }

        // Only a package that reads back takes the old one's place.
        $check = pg_office_open($new, $error);

        if ($check === null) {
            @unlink($new);
            return $fail('save');
        }

        $check->close();

        if (!pg_office_replace_file($new, $path)) {
            return $fail('save');
        }

        return array('ok' => true, 'code' => '', 'error' => '', 'size' => (int) @filesize($path), 'version' => pg_office_version($path), 'changed' => $result['changed']);
    } finally {
        foreach ($spooled as $file) {
            @unlink($file);
        }
    }
}

/**
 * Works out every part a save changes and writes each to a file: the sheets
 * with changed cells, every worksheet whose formulas cached results, and the
 * workbook with its recalculation mark.
 *
 * @param ZipArchive $zip    open on the workbook
 * @param array      $wanted sheet position => [name, rows => [row => [column => text]]]
 * @param callable   $fail
 * @param callable   $spool  writes a part to a file (pg_office_save_package())
 * @return array ok, parts (part name => file), changed; or a refusal
 */
function pg_office_xlsx_save_parts($zip, $wanted, $fail, $spool)
{
    if (pg_office_signed($zip)) {
        return $fail('signed');
    }

    $sheets = pg_office_xlsx_sheets($zip, $refused);
    $styles = pg_office_xlsx_style_kinds($zip, $styles_refused);
    pg_office_refuse($refused, $styles_refused);

    // Without the styles a typed value cannot be stored as its cell is
    // formatted; without the workbook part there is nothing to mark.
    if ($refused !== '') {
        return array('ok' => false, 'code' => 'read', 'error' => pg_office_refusal_error($refused), 'size' => 0, 'version' => '', 'changed' => 0);
    }

    $workbook = (string) pg_zip_entry_contents($zip, 'xl/workbook.xml', PG_OFFICE_MAX_DOM, $unread);

    if (pg_office_xlsx_write_reserved($workbook)) {
        return $fail('password');
    }

    $date1904 = pg_office_xlsx_date1904($workbook);
    $parts = array();
    $changed = 0;

    foreach ($wanted as $index => $sheet_changes) {
        if (!isset($sheets[$index]) || (($sheet_changes['name'] !== null) && ($sheet_changes['name'] !== $sheets[$index]['name']))) {
            return $fail('changed');
        }

        $sheet = $sheets[$index];

        if ($sheet['type'] !== 'worksheet') {
            return $fail('sheet', '', $sheet['name']);
        }

        $stat = $zip->statName($sheet['part']);

        if (($stat === false) || ((int) $stat['size'] > PG_OFFICE_EDIT_MAX_PART)) {
            return $fail('large', '', $sheet['name']);
        }

        $xml = (string) pg_zip_entry_contents($zip, $sheet['part'], PG_OFFICE_EDIT_MAX_PART, $unread);
        $reader = new XMLReader();

        if (($xml === '') || !@$reader->XML($xml, null, LIBXML_NONET)) {
            return $fail('sheet', '', $sheet['name']);
        }

        $scan = pg_office_xlsx_scan($reader, 0, array());
        $reader->close();

        if (!$scan['addressed'] || $scan['unusual']) {
            return $fail('sheet', '', $sheet['name']);
        }

        $bound = pg_office_xlsx_bound_ranges($zip, $sheet['part'], $bound_refused);

        if ($bound_refused === 'damaged') {
            return $fail('sheet', '', $sheet['name']);
        }

        if ($scan['crowded'] || ($bound === null)) {
            return $fail('large', '', $sheet['name']);
        }

        foreach ($sheet_changes['rows'] as $row => $columns) {
            foreach ($columns as $column => $text) {
                if (pg_office_xlsx_range_lock($row, $column, $scan, $bound) !== '') {
                    return $fail('locked', pg_office_xlsx_column_name($column) . $row, $sheet['name']);
                }

                $changed++;
            }
        }

        $patched = pg_office_xlsx_patch_sheet($xml, $sheet_changes['rows'], array(
            'styles' => $styles,
            'date1904' => $date1904,
            'protected' => $scan['protected'],
        ));

        unset($xml);

        if (!$patched['ok']) {
            if (in_array($patched['error'], array('locked', 'missing'), true)) {
                return $fail($patched['error'], $patched['cell'], $sheet['name']);
            }

            return $fail(($patched['error'] === 'large') ? 'large' : 'sheet', '', $sheet['name']);
        }

        $stripped = pg_office_xlsx_strip_formula_values($patched['xml']);
        $xml = $stripped['ok'] ? $stripped['xml'] : $patched['xml'];
        unset($patched, $stripped);

        if (!pg_office_xml_well_formed($xml)) {
            return $fail('sheet', '', $sheet['name']);
        }

        $file = $spool($xml);
        unset($xml);

        if ($file === null) {
            return $fail('save');
        }

        $parts[$sheet['part']] = $file;
    }

    // A formula on any sheet may read a changed cell, so the results the
    // formulas cached come out of the other worksheets too. A sheet too
    // large to read whole here keeps its own (Excel still works them out
    // again on opening, as the workbook is marked to).
    foreach ($sheets as $sheet) {
        if (($sheet['type'] !== 'worksheet') || isset($parts[$sheet['part']])) {
            continue;
        }

        $stat = $zip->statName($sheet['part']);

        if (($stat === false) || ((int) $stat['size'] > PG_OFFICE_EDIT_MAX_PART)) {
            continue;
        }

        $stripped = pg_office_xlsx_strip_formula_values((string) pg_zip_entry_contents($zip, $sheet['part'], PG_OFFICE_EDIT_MAX_PART, $unread));

        if ($stripped['ok'] && ($stripped['count'] > 0) && pg_office_xml_well_formed($stripped['xml'])) {
            $file = $spool($stripped['xml']);

            if ($file === null) {
                return $fail('save');
            }

            $parts[$sheet['part']] = $file;
        }

        unset($stripped);
    }

    $workbook = pg_office_xlsx_patch_workbook($workbook);

    if (($workbook === null) || !pg_office_xml_well_formed($workbook)) {
        return $fail('save');
    }

    $file = $spool($workbook);

    if ($file === null) {
        return $fail('save');
    }

    $parts['xl/workbook.xml'] = $file;

    return array('ok' => true, 'parts' => $parts, 'changed' => $changed);
}

/* ---------------------------------------------------------------------------
   Documents: the text of a paragraph
   --------------------------------------------------------------------------- */

/**
 * The largest main document part changed here, in bytes unpacked: it is
 * read whole with DOM, which takes several times its size in memory. No more
 * than PG_OFFICE_MAX_DOM, past which the preview reads only its beginning.
 */
define('PG_OFFICE_EDIT_MAX_DOCUMENT', 8 * 1024 * 1024);

/**
 * The most paragraphs one save may change, and the longest text a paragraph
 * may be given, in characters.
 */
define('PG_OFFICE_EDIT_MAX_PARAGRAPHS', 2000);
define('PG_OFFICE_EDIT_MAX_PARAGRAPH', 100000);

/**
 * The paragraphs of a document in document order: every paragraph element
 * of the main part's own namespace, those in tables and text boxes too. A
 * paragraph's position in this list is how the preview and a save name it,
 * so both sides count the same way.
 *
 * @param DOMDocument $document word/document.xml
 * @return DOMElement[]
 */
function pg_office_docx_paragraphs($document)
{
    $out = array();

    if (!$document->documentElement) {
        return $out;
    }

    foreach ($document->getElementsByTagNameNS((string) $document->documentElement->namespaceURI, 'p') as $paragraph) {
        $out[] = $paragraph;
    }

    return $out;
}

/**
 * Why an element inside a paragraph keeps the paragraph from being changed
 * here, by its local name.
 *
 * @param string $local
 * @return string tracked, field, comment, note, object, control or other
 */
function pg_office_docx_lock_reason($local)
{
    if (in_array($local, array('ins', 'del', 'moveFrom', 'moveTo', 'rPrChange', 'delText', 'delInstrText', 'moveFromRangeStart', 'moveFromRangeEnd', 'moveToRangeStart', 'moveToRangeEnd'), true)) {
        return 'tracked';
    }

    if (in_array($local, array('fldChar', 'instrText', 'fldSimple', 'fldData'), true)) {
        return 'field';
    }

    if (in_array($local, array('commentRangeStart', 'commentRangeEnd', 'commentReference', 'annotationRef'), true)) {
        return 'comment';
    }

    if (in_array($local, array('footnoteReference', 'endnoteReference', 'footnoteRef', 'endnoteRef', 'separator', 'continuationSeparator'), true)) {
        return 'note';
    }

    if (in_array($local, array('sdt', 'customXml', 'smartTag'), true)) {
        return 'control';
    }

    if (in_array($local, array('drawing', 'pict', 'object', 'sym', 'ptab', 'oMath', 'oMathPara', 'AlternateContent', 'contentPart', 'ruby', 'pgNum', 'subDoc',
        'dayShort', 'dayLong', 'monthShort', 'monthLong', 'yearShort', 'yearLong'), true)) {
        return 'object';
    }

    return 'other';
}

/**
 * Whether formatting holds a tracked change: a run's or a paragraph mark's
 * properties recorded as inserted, deleted or moved, or an earlier version
 * of them kept for review (rPrChange, pPrChange and the like).
 *
 * @param DOMElement $properties rPr or pPr
 * @return bool
 */
function pg_office_docx_tracked_props($properties)
{
    foreach ($properties->getElementsByTagNameNS('*', '*') as $node) {
        if (in_array($node->localName, array('ins', 'del', 'moveFrom', 'moveTo'), true) || (substr($node->localName, -6) === 'Change')) {
            return true;
        }
    }

    return false;
}

/**
 * Why the place a paragraph sits in keeps it from being changed here: a
 * content control or custom XML (whose text a form or a data binding owns,
 * and which may be locked), a text box (written twice, for new and for old
 * readers), or a table cell hidden under merged cells.
 *
 * @param DOMElement $paragraph
 * @return string '' when nothing; control, object or merged
 */
function pg_office_docx_place_lock($paragraph)
{
    for ($node = $paragraph->parentNode; $node instanceof DOMElement; $node = $node->parentNode) {
        switch ($node->localName) {
            case 'sdt':
            case 'sdtContent':
            case 'customXml':
                return 'control';

            case 'txbxContent':
            case 'AlternateContent':
            case 'drawing':
            case 'pict':
                return 'object';

            case 'tc':
                foreach ($node->childNodes as $properties) {
                    if (!($properties instanceof DOMElement) || ($properties->localName !== 'tcPr')) {
                        continue;
                    }

                    foreach ($properties->childNodes as $merge) {
                        if (($merge instanceof DOMElement) && in_array($merge->localName, array('vMerge', 'hMerge'), true)
                            && (strtolower(pg_office_attr($merge, 'val')) !== 'restart')) {
                            return 'merged';
                        }
                    }
                }
                break;
        }
    }

    return '';
}

/**
 * Reads one run for an edit: adds its text to the paragraph's and the run to
 * the paragraph's list, or says why the paragraph cannot be changed.
 *
 * The text is what a person types: a tab is a tab, a line break inside the
 * paragraph a new line, a non-breaking and an optional hyphen their own
 * characters. A page or column break, a picture, a field, a footnote mark
 * and the like are more than text.
 *
 * @param DOMElement $run
 * @param array      $out pg_office_docx_paragraph_edit() being built
 */
function pg_office_docx_run_edit($run, &$out)
{
    $text = '';

    foreach ($run->childNodes as $child) {
        if (!($child instanceof DOMElement)) {
            continue;
        }

        switch ($child->localName) {
            case 'rPr':
                if (pg_office_docx_tracked_props($child)) {
                    $out['lock'] = ($out['lock'] !== '') ? $out['lock'] : 'tracked';
                }
                break;

            case 't':
                // A line end written inside the text itself is white space
                // to a word processor, not a line break (that is w:br).
                $text .= str_replace(array("\r\n", "\r", "\n"), ' ', $child->textContent);
                break;

            case 'tab':
                $text .= "\t";
                break;

            case 'br':
                if (in_array(strtolower(pg_office_attr($child, 'type')), array('', 'textwrapping'), true)) {
                    $text .= "\n";
                } else {
                    $out['lock'] = ($out['lock'] !== '') ? $out['lock'] : 'break';
                }
                break;

            case 'cr':
                $text .= "\n";
                break;

            case 'noBreakHyphen':
                $text .= "\u{2011}";
                break;

            case 'softHyphen':
                $text .= "\u{00AD}";
                break;

            case 'lastRenderedPageBreak':
                break;

            default:
                $out['lock'] = ($out['lock'] !== '') ? $out['lock'] : pg_office_docx_lock_reason($child->localName);
        }
    }

    $out['runs'][] = array('run' => $run, 'text' => $text);
    $out['text'] .= $text;
}

/**
 * Whether the text of a paragraph may be changed here, its text as a person
 * reads and types it, and the runs that hold it.
 *
 * Only a paragraph of text is changed: runs, links and the markers that sit
 * between runs (bookmarks, proofing marks, permission ranges). A paragraph
 * with tracked changes, a field (a page number, a table of contents, a
 * date), a comment, a footnote, a picture or an equation, a content control
 * or a page break is shown and left as it is, and so is one in a content
 * control, a text box or a cell hidden under merged cells.
 *
 * @param DOMElement $paragraph
 * @return array lock ('' when it may change; else the reason), text, runs ([run, text] in order)
 */
function pg_office_docx_paragraph_edit($paragraph)
{
    $out = array('lock' => pg_office_docx_place_lock($paragraph), 'text' => '', 'runs' => array());
    $markers = array('bookmarkStart', 'bookmarkEnd', 'proofErr', 'permStart', 'permEnd');

    if ($out['lock'] !== '') {
        return $out;
    }

    foreach ($paragraph->childNodes as $child) {
        if (!($child instanceof DOMElement)) {
            continue;
        }

        switch ($child->localName) {
            case 'pPr':
                // A paragraph mark inserted or deleted with tracking on, or
                // formatting changed so: the paragraph is part of a change
                // waiting for review.
                if (pg_office_docx_tracked_props($child)) {
                    $out['lock'] = ($out['lock'] !== '') ? $out['lock'] : 'tracked';
                }
                break;

            case 'r':
                pg_office_docx_run_edit($child, $out);
                break;

            case 'hyperlink':
                foreach ($child->childNodes as $inner) {
                    if (!($inner instanceof DOMElement)) {
                        continue;
                    }

                    if ($inner->localName === 'r') {
                        pg_office_docx_run_edit($inner, $out);
                    } elseif (!in_array($inner->localName, $markers, true)) {
                        $out['lock'] = ($out['lock'] !== '') ? $out['lock'] : pg_office_docx_lock_reason($inner->localName);
                    }
                }
                break;

            default:
                if (!in_array($child->localName, $markers, true)) {
                    $out['lock'] = ($out['lock'] !== '') ? $out['lock'] : pg_office_docx_lock_reason($child->localName);
                }
        }
    }

    return $out;
}

/**
 * Splits a text into the pieces a word-level comparison works on: words,
 * numbers, runs of white space, and every other character on its own.
 *
 * @param string $text
 * @return string[]
 */
function pg_office_text_tokens($text)
{
    if ($text === '') {
        return array();
    }

    preg_match_all('/[\p{L}\p{M}][\p{L}\p{M}\p{N}_]*|\p{N}+|\s+|./us', $text, $matches);

    return $matches[0];
}

/**
 * Where two texts differ, as changes to the first: [start, end, insert] in
 * characters of the old text, in order and not overlapping.
 *
 * The texts are compared as words (pg_office_text_tokens()), never as
 * letters: the common beginning and end are set aside whole words at a
 * time, so a word added before one that starts with the same letters
 * ("BIG" before "BOLD") goes before that word, not into it and its
 * formatting, and a word changed in part is changed whole. What lies
 * between is compared word by word, so two separate changes come out as
 * two and the runs between them (with their formatting) are left alone. A
 * middle too long to compare word by word (the product of the two word
 * counts above $limit) is one change.
 *
 * @param string $old
 * @param string $new
 * @param int    $limit
 * @return array[]
 */
function pg_office_text_hunks($old, $new, $limit = 250000)
{
    if ($old === $new) {
        return array();
    }

    $a = pg_office_text_tokens($old);
    $b = pg_office_text_tokens($new);
    $n = count($a);
    $m = count($b);
    $prefix = 0;

    while (($prefix < $n) && ($prefix < $m) && ($a[$prefix] === $b[$prefix])) {
        $prefix++;
    }

    $suffix = 0;

    while (($suffix < $n - $prefix) && ($suffix < $m - $prefix) && ($a[$n - 1 - $suffix] === $b[$m - 1 - $suffix])) {
        $suffix++;
    }

    $old_middle = array_slice($a, $prefix, $n - $prefix - $suffix);
    $new_middle = array_slice($b, $prefix, $m - $prefix - $suffix);
    $rows = count($old_middle);
    $columns = count($new_middle);

    // Positions are in characters of the old text.
    $prefix = mb_strlen(implode('', array_slice($a, 0, $prefix)), 'UTF-8');

    if (($rows * $columns) > $limit) {
        return array(array($prefix, $prefix + mb_strlen(implode('', $old_middle), 'UTF-8'), implode('', $new_middle)));
    }

    // The longest run of words both share, filled in from the end.
    $table = array_fill(0, $rows + 1, array_fill(0, $columns + 1, 0));

    for ($i = $rows - 1; $i >= 0; $i--) {
        for ($j = $columns - 1; $j >= 0; $j--) {
            $table[$i][$j] = ($old_middle[$i] === $new_middle[$j]) ? ($table[$i + 1][$j + 1] + 1) : max($table[$i + 1][$j], $table[$i][$j + 1]);
        }
    }

    $hunks = array();
    $position = $prefix;
    $i = 0;
    $j = 0;
    $open = null;

    while (($i < $rows) || ($j < $columns)) {
        if (($i < $rows) && ($j < $columns) && ($old_middle[$i] === $new_middle[$j])) {
            if ($open !== null) {
                $hunks[] = $open;
                $open = null;
            }

            $position += mb_strlen($old_middle[$i], 'UTF-8');
            $i++;
            $j++;
            continue;
        }

        if ($open === null) {
            $open = array($position, $position, '');
        }

        if (($j < $columns) && (($i >= $rows) || ($table[$i][$j + 1] >= $table[$i + 1][$j]))) {
            $open[2] .= $new_middle[$j];
            $j++;
        } else {
            $position += mb_strlen($old_middle[$i], 'UTF-8');
            $open[1] = $position;
            $i++;
        }
    }

    if ($open !== null) {
        $hunks[] = $open;
    }

    return $hunks;
}

/**
 * Applies one change to the texts of a paragraph's runs: the characters from
 * $start to $end go, and $insert comes in where they were, in the run the
 * change starts in (or, for text only added, the run of the character before
 * it, whose formatting carries on as it does when typing; at the very start,
 * the first run with text). Text added right after a link goes into the run
 * after it, as a word processor types it, rather than into the link; when
 * the link ends the paragraph, into a new run after it, formatted like the
 * paragraph mark. Runs the change does not reach keep their text.
 *
 * @param array  $runs   [run, text] in order, the texts updated (a run may be added)
 * @param int    $start  in characters of the paragraph's text
 * @param int    $end
 * @param string $insert
 * @return bool false when there is no run to put added text in
 */
function pg_office_docx_apply_hunk(&$runs, $start, $end, $insert)
{
    $target = -1;
    $offset = 0;
    $first_with_text = -1;
    $keys = array_keys($runs);

    foreach ($keys as $place => $index) {
        $run = $runs[$index];
        $length = mb_strlen($run['text'], 'UTF-8');

        if (($first_with_text < 0) && ($length > 0)) {
            $first_with_text = $index;
        }

        if ($target < 0) {
            if ($start < $end) {
                if (($start >= $offset) && ($start < $offset + $length)) {
                    $target = $index;
                }
            } elseif (($start > 0) && ($start > $offset) && ($start <= $offset + $length)) {
                $target = $index;
                $next = isset($keys[$place + 1]) ? $runs[$keys[$place + 1]]['run'] : null;

                if (($start === $offset + $length) && pg_office_docx_in_link($run['run'])) {
                    if ($next === null) {
                        $paragraph = $run['run']->parentNode;

                        while (($paragraph instanceof DOMElement) && ($paragraph->localName !== 'p')) {
                            $paragraph = $paragraph->parentNode;
                        }

                        if ($paragraph instanceof DOMElement) {
                            $runs[] = array('run' => pg_office_docx_new_run($paragraph), 'text' => '', 'changed' => true);
                            end($runs);
                            $target = key($runs);
                        }
                    } elseif (!pg_office_docx_in_link($next)) {
                        $target = $keys[$place + 1];
                    }
                }
            }
        }

        $offset += $length;
    }

    if ($target < 0) {
        $target = ($first_with_text >= 0) ? $first_with_text : (empty($runs) ? -1 : 0);
    }

    if (($target < 0) && ($insert !== '')) {
        return false;
    }

    $offset = 0;

    foreach ($runs as $index => $run) {
        $length = mb_strlen($run['text'], 'UTF-8');
        $from = max(0, min($length, $start - $offset));
        $to = max(0, min($length, $end - $offset));
        $offset += $length;

        if (($from === $to) && ($index !== $target)) {
            continue;
        }

        $runs[$index]['text'] = mb_substr($run['text'], 0, $from, 'UTF-8') . (($index === $target) ? $insert : '') . mb_substr($run['text'], $to, null, 'UTF-8');
        $runs[$index]['changed'] = true;
    }

    return true;
}

/**
 * Whether a run is the text of a link.
 *
 * @param DOMElement $run
 * @return bool
 */
function pg_office_docx_in_link($run)
{
    return ($run->parentNode instanceof DOMElement) && ($run->parentNode->localName === 'hyperlink');
}

/**
 * Writes a text into a run, keeping its formatting (rPr): what it held
 * before goes, and the text comes in as text, tabs, line breaks and hyphens.
 *
 * @param DOMElement $run
 * @param string     $text
 */
function pg_office_docx_run_write($run, $text)
{
    $document = $run->ownerDocument;
    $namespace = (string) $run->namespaceURI;
    $prefix = ((string) $run->prefix !== '') ? $run->prefix . ':' : '';

    foreach (iterator_to_array($run->childNodes) as $child) {
        if (!($child instanceof DOMElement) || ($child->localName !== 'rPr')) {
            $run->removeChild($child);
        }
    }

    $buffer = '';

    $flush = function () use (&$buffer, $run, $document, $namespace, $prefix) {
        if ($buffer === '') {
            return;
        }

        $node = $document->createElementNS($namespace, $prefix . 't');

        // Spaces at either end, or several in a row, are dropped by a
        // reader unless the text says they belong to it.
        if ((trim($buffer) !== $buffer) || (strpos($buffer, '  ') !== false)) {
            $node->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        }

        $node->appendChild($document->createTextNode($buffer));
        $run->appendChild($node);
        $buffer = '';
    };

    $marks = array("\t" => 'tab', "\n" => 'br', "\u{2011}" => 'noBreakHyphen', "\u{00AD}" => 'softHyphen');

    foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $character) {
        if (isset($marks[$character])) {
            $flush();
            $run->appendChild($document->createElementNS($namespace, $prefix . $marks[$character]));
        } else {
            $buffer .= $character;
        }
    }

    $flush();
}

/**
 * Gives a paragraph a new text, changing only the runs the change reaches.
 *
 * @param DOMElement $paragraph
 * @param array      $edit  pg_office_docx_paragraph_edit() of it (editable)
 * @param string     $text  the new text
 * @param int        $limit how much word-by-word comparison it may take (pg_office_text_hunks())
 * @return bool
 */
function pg_office_docx_paragraph_write($paragraph, $edit, $text, $limit = 250000)
{
    $runs = $edit['runs'];
    $hunks = pg_office_text_hunks($edit['text'], $text, $limit);

    // From the end backwards, so the positions of the earlier changes hold.
    foreach (array_reverse($hunks) as $hunk) {
        if (!pg_office_docx_apply_hunk($runs, $hunk[0], $hunk[1], $hunk[2])) {
            // A paragraph with no run at all: one is made, formatted like the
            // paragraph mark.
            $runs[] = array('run' => pg_office_docx_new_run($paragraph), 'text' => $hunk[2], 'changed' => true);
        }
    }

    foreach ($runs as $run) {
        if (empty($run['changed'])) {
            continue;
        }

        if ($run['text'] === '') {
            $run['run']->parentNode->removeChild($run['run']);
            continue;
        }

        pg_office_docx_run_write($run['run'], $run['text']);
    }

    return true;
}

/**
 * A new, empty run at the end of a paragraph, with the formatting of the
 * paragraph mark: for a paragraph that has no run, or text typed after a
 * link that ends it.
 *
 * @param DOMElement $paragraph
 * @return DOMElement
 */
function pg_office_docx_new_run($paragraph)
{
    $document = $paragraph->ownerDocument;
    $namespace = (string) $paragraph->namespaceURI;
    $prefix = ((string) $paragraph->prefix !== '') ? $paragraph->prefix . ':' : '';
    $run = $document->createElementNS($namespace, $prefix . 'r');

    foreach ($paragraph->childNodes as $child) {
        if (($child instanceof DOMElement) && ($child->localName === 'pPr')) {
            foreach ($child->childNodes as $property) {
                if (($property instanceof DOMElement) && ($property->localName === 'rPr')) {
                    $run->appendChild($property->cloneNode(true));
                }
            }
        }
    }

    $paragraph->appendChild($run);

    return $run;
}

/**
 * What the document's settings say against an edit here: protection the
 * author switched on (read only, comments only, forms only, tracked changes
 * only, or a password to change it), or changes being tracked, which an
 * edit here would not record.
 *
 * @param ZipArchive $zip
 * @return string '' when nothing, protected or tracking; large or unusual when the settings could not be read
 *                whole, so whether the document is protected is not known
 */
function pg_office_docx_restriction($zip)
{
    $part = 'word/settings.xml';

    foreach (pg_office_rels($zip, 'word/_rels/document.xml.rels', $refused) as $relationship) {
        if (!$relationship['external'] && ($relationship['type'] === 'settings')) {
            $part = pg_office_part_name('word/', $relationship['target']);
        }
    }

    $settings = null;

    if ($refused === '') {
        $settings = pg_office_xml($zip, $part, $status);
        pg_office_refuse($refused, $status);
    }

    if ($refused !== '') {
        return ($refused === 'large') ? 'large' : 'unusual';
    }

    if ($settings === null) {
        return '';
    }

    $on = function ($value) {
        return !in_array(strtolower((string) $value), array('0', 'false', 'off'), true);
    };

    foreach ($settings->getElementsByTagNameNS('*', 'writeProtection') as $protection) {
        return 'protected';
    }

    foreach ($settings->getElementsByTagNameNS('*', 'documentProtection') as $protection) {
        $enforced = pg_office_attr($protection, 'enforcement');

        if (($enforced !== '') && $on($enforced) && !in_array(strtolower(pg_office_attr($protection, 'edit')), array('', 'none'), true)) {
            return 'protected';
        }
    }

    foreach ($settings->getElementsByTagNameNS('*', 'trackRevisions') as $tracking) {
        if ($on(pg_office_attr($tracking, 'val'))) {
            return 'tracking';
        }
    }

    return '';
}

/**
 * Whether a document may be edited here at all, and why not: the package
 * signed, protected, tracking changes, its main part too large, or written
 * in a way no word processor writes (not UTF-8, a DOCTYPE).
 *
 * @param ZipArchive $zip
 * @return string '' when it may; signed, protected, tracking, large or unusual
 */
function pg_office_docx_edit_refusal($zip)
{
    if (pg_office_signed($zip)) {
        return 'signed';
    }

    $restriction = pg_office_docx_restriction($zip);

    if ($restriction !== '') {
        return $restriction;
    }

    $stat = $zip->statName('word/document.xml');

    if (($stat === false) || ((int) $stat['size'] > PG_OFFICE_EDIT_MAX_DOCUMENT)) {
        return 'large';
    }

    // The declaration and a DOCTYPE stand before the first element.
    $head = (string) $zip->getFromName('word/document.xml', 1024);

    if (!pg_office_xlsx_utf8_part($head) || (stripos($head, '<!DOCTYPE') !== false)) {
        return 'unusual';
    }

    return '';
}

/**
 * Writes changed paragraph texts into a document on disk.
 *
 * @param string $path      the document
 * @param array  $changes   list of [p => the paragraph's position (pg_office_docx_paragraphs()), text => the new text]
 * @param string $version   what the person opened (pg_office_version())
 * @param string $directory where the new package is built; data/temp when ''
 * @return array ok, code, error, size, version, changed
 */
function pg_office_docx_save($path, $changes, $version, $directory = '')
{
    $fail = function ($code, $paragraph = '') {
        return array('ok' => false, 'code' => $code, 'error' => pg_office_docx_edit_message($code, $paragraph), 'size' => 0, 'version' => '', 'changed' => 0);
    };

    if (!pg_office_ready()) {
        return $fail('ready');
    }

    if (!is_array($changes) || empty($changes)) {
        return $fail('none');
    }

    if (count($changes) > PG_OFFICE_EDIT_MAX_PARAGRAPHS) {
        return $fail('many');
    }

    // A paragraph named twice takes the last text.
    $wanted = array();

    foreach ($changes as $change) {
        if (!is_array($change) || !isset($change['p']) || !is_scalar($change['p']) || !ctype_digit((string) $change['p'])) {
            return $fail('none');
        }

        $text = pg_office_xlsx_clean_text(isset($change['text']) ? $change['text'] : '');

        if ($text === null) {
            return $fail('text');
        }

        if (mb_strlen($text, 'UTF-8') > PG_OFFICE_EDIT_MAX_PARAGRAPH) {
            return $fail('long');
        }

        $wanted[(int) $change['p']] = $text;
    }

    $directory = ($directory !== '') ? $directory : pg_office_edit_temp_directory();

    if (!is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }

    $lock = pg_office_edit_lock($path, $directory);

    if ($lock === null) {
        return $fail('busy');
    }

    $result = pg_office_save_package($path, (string) $version, $directory, $fail, function ($zip, $spool) use ($wanted, $fail) {
        return pg_office_docx_save_parts($zip, $wanted, $fail, $spool);
    });

    pg_office_edit_unlock($lock);

    return $result;
}

/**
 * Works out the main part of a document with the changed paragraphs written
 * in, and writes it to a file.
 *
 * @param ZipArchive $zip
 * @param array      $wanted paragraph position => new text
 * @param callable   $fail
 * @param callable   $spool  writes a part to a file (pg_office_save_package())
 * @return array ok, parts (part name => file), changed; or a refusal
 */
function pg_office_docx_save_parts($zip, $wanted, $fail, $spool)
{
    $refusal = pg_office_docx_edit_refusal($zip);

    if ($refusal !== '') {
        return $fail($refusal);
    }

    $xml = pg_zip_entry_contents($zip, 'word/document.xml', PG_OFFICE_EDIT_MAX_DOCUMENT, $unread);

    if (($xml === null) || !pg_office_xlsx_utf8_part(substr($xml, 0, 200)) || (stripos($xml, '<!DOCTYPE') !== false)) {
        return $fail(($unread === 'large') ? 'large' : 'unusual');
    }

    // Read without dropping blank text: a space between two runs is text.
    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadXML($xml, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    unset($xml);

    if (!$loaded) {
        return $fail('unusual');
    }

    $paragraphs = pg_office_docx_paragraphs($document);
    $changed = 0;

    // Every paragraph is checked before any is written.
    $edits = array();

    foreach ($wanted as $position => $text) {
        if (!isset($paragraphs[$position])) {
            return $fail('missing', (string) ($position + 1));
        }

        $edit = pg_office_docx_paragraph_edit($paragraphs[$position]);

        if ($edit['lock'] !== '') {
            return $fail('locked', (string) ($position + 1));
        }

        if ($edit['text'] !== $text) {
            $edits[$position] = $edit;
        }
    }

    // Comparing word by word costs the product of the two texts' word
    // counts. One save shares a fixed amount of it; a paragraph past that
    // is written as a single change: the same text, fewer runs left alone.
    $budget = 2000000;

    foreach ($edits as $position => $edit) {
        $cost = min(250000, count(pg_office_text_tokens($edit['text'])) * count(pg_office_text_tokens($wanted[$position])));
        $limit = ($cost <= $budget) ? 250000 : 0;
        $budget -= ($cost <= $budget) ? $cost : 0;

        pg_office_docx_paragraph_write($paragraphs[$position], $edit, $wanted[$position], $limit);
        $changed++;
    }

    $xml = $document->saveXML();
    unset($document, $paragraphs, $edits);

    if (!is_string($xml) || !pg_office_xml_well_formed($xml)) {
        return $fail('save');
    }

    $file = $spool($xml);

    if ($file === null) {
        return $fail('save');
    }

    return array('ok' => true, 'parts' => array('word/document.xml' => $file), 'changed' => $changed);
}

/**
 * The message for a refused document save, by its reason.
 *
 * @param string $code
 * @param string $paragraph the paragraph concerned, counted from 1
 * @return string
 */
function pg_office_docx_edit_message($code, $paragraph = '')
{
    switch ($code) {
        case 'ready':
            return lang('This server cannot change documents (the PHP zip or xml extension is missing).');
        case 'signed':
            return lang('The file is digitally signed, so its text is not changed here.');
        case 'protected':
            return lang('The document is protected against changes.');
        case 'tracking':
            return lang('Track Changes is on for this document. Change it in a word processor, so that the changes are tracked.');
        case 'large':
            return lang('This document is too large to change here.');
        case 'many':
            return lang(array('string' => 'Too many paragraphs were changed at once. Save at most {var:1} paragraphs at a time.', 'vars' => array(PG_OFFICE_EDIT_MAX_PARAGRAPHS)));
        case 'long':
            return lang('A paragraph is too long to be saved here.');
        case 'text':
            return lang('The text of a paragraph could not be read.');
        case 'locked':
            return lang(array('string' => 'Paragraph {var:1} holds more than text and cannot be changed here.', 'vars' => array($paragraph)));
        case 'missing':
            return lang(array('string' => 'Paragraph {var:1} is not in the file.', 'vars' => array($paragraph)));
        case 'unusual':
            return lang('This document cannot be changed here.');
    }

    return pg_office_edit_message($code);
}
