<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Letting the product importer take a spreadsheet.
//
// import_products.php reads one format and reads it well: a comma separated
// file, a header row, then a row per product, with a column list that has
// grown for twenty years and a hundred edge cases resolved inside it. None of
// that is worth rewriting to accept a second format.
//
// So nothing here touches the import. A spreadsheet is turned into exactly the
// file the importer already expects, written to the system temp directory, and
// handed over in place of the upload. The importer never learns the difference,
// and a bad spreadsheet fails at this door rather than halfway through a run.

// Is this upload a spreadsheet rather than a text file?
//
// Judged by the name the operator's own machine gave it. Sniffing the bytes
// would be stricter, but a .csv that begins with a zip signature is not a case
// worth designing for, and a correctly named .xlsx that fails to open is
// reported below anyway.
function pg_products_import_is_spreadsheet($file_name)
{
    $extension = strtolower((string) pathinfo((string) $file_name, PATHINFO_EXTENSION));

    return in_array($extension, array('xlsx', 'xlsm', 'xls', 'ods'));
}

// Turn an uploaded spreadsheet into a CSV file and return its path.
//
// $sheet is the worksheet to read, 0 for the first: a workbook with more
// than one sheet asks which (pg_sheet_import_workbook_ask(),
// includes/fn/sheet_import.php), counted as pg_sheet_names() counts them.
//
// Returns '' and fills $error when the file cannot be read. The caller owns
// the returned file and should unlink() it when the import is over.
function pg_products_import_to_csv($source_path, $file_name, &$error, $sheet = 0)
{
    $error = '';

    $kind = pg_sheet_kind($file_name);

    if ($kind == '') {
        $error = lang('The file was empty.');
        return '';
    }

    // A .xlsx keeps its own walk below: it is the format this software
    // exports, so an operator who takes the catalog out, edits it and brings
    // it back never leaves that path, and its rows are written as they go
    // past. The older Excel format and OpenDocument are read by the sheet
    // reader (includes/fn/sheet_read.php).
    if (($kind == 'xlsx') && class_exists('ZipArchive')) {
        return pg_products_import_xlsx_to_csv($source_path, $error, $sheet);
    }

    return pg_products_import_sheet_to_csv($source_path, $kind, $error, $sheet);
}

// Turn one sheet of a workbook (0 for the first) into the CSV the importer
// reads, through the sheet reader, and return its path.
//
// The rows are held until the sheet has been read to its end: a reader error
// late in the file drops what came before it, and every row is squared off
// to the widest one in the sheet, so a short line never reads the next
// column's value. An .ods is held to the reader's cell ceilings and an .xls
// is read whole by the reader anyway, so this costs no more than the file.
function pg_products_import_sheet_to_csv($source_path, $kind, &$error, $sheet = 0)
{
    $error = '';
    $reader_error = '';
    $rows = array();
    $width = 0;

    foreach (pg_sheet_rows($source_path, $kind, $reader_error, $sheet) as $cells) {

        $row = array();
        $filled = false;

        foreach ($cells as $cell) {

            $value = pg_sheet_cell_text($cell);

            if ($value !== '') {
                $filled = true;
            }

            $row[] = $value;
        }

        if (count($row) > $width) {
            $width = count($row);
        }

        // A row of empty cells is what a spreadsheet leaves behind when
        // somebody clears one, and the importer would read it as a product
        // with no name.
        if ($filled) {
            $rows[] = $row;
        }
    }

    if ($reader_error !== '') {
        $error = ($reader_error === 'large') ? lang('The file is too large.')
            : (($reader_error === 'sheet') ? lang('The file was empty.') : lang('The file could not be read.'));
        return '';
    }

    if (empty($rows)) {
        $error = lang('The file was empty.');
        return '';
    }

    $path = tempnam(sys_get_temp_dir(), 'pg_import_');

    if ($path === false) {
        $error = lang('The file could not be read.');
        return '';
    }

    $handle = fopen($path, 'w');

    if ($handle === false) {
        unlink($path);
        $error = lang('The file could not be read.');
        return '';
    }

    foreach ($rows as $row) {

        while (count($row) < $width) {
            $row[] = '';
        }

        fputcsv($handle, $row, ',', '"', '\\');
    }

    fclose($handle);

    return $path;
}

// ── Reading a .xlsx without holding it ──────────────────────────────────────
//
// A worksheet is XML in a zip. XMLReader walks it an element at a time, so the
// memory cost is one row rather than one workbook, and the CSV is written as
// the rows go past.

// Which part of the zip holds the worksheet asked for, 0 for the first.
//
// Counted among worksheets only, a chart sheet left out, as the sheet
// question and the sheet reader count them (pg_office_xlsx_sheets()). When
// that list cannot be read, the first sheet is found below as before.
function pg_products_import_xlsx_sheet_part($zip, $sheet = 0)
{
    $worksheets = array();

    foreach (pg_office_xlsx_sheets($zip, $refused) as $entry) {
        if ($entry['type'] === 'worksheet') {
            $worksheets[] = $entry['part'];
        }
    }

    if ($worksheets && ($refused === '')) {
        return isset($worksheets[(int) $sheet]) ? $worksheets[(int) $sheet] : '';
    }

    if ((int) $sheet !== 0) {
        return '';
    }

    return pg_products_import_xlsx_first_part($zip);
}

// Which part of the zip holds the first sheet.
//
// Not assumed to be sheet1.xml: a workbook whose sheets have been reordered or
// deleted keeps the old file names, and the first tab can be sheet3.xml. The
// answer is in the workbook and its relationships, both small.
function pg_products_import_xlsx_first_part($zip)
{
    $book = $zip->getFromName('xl/workbook.xml');
    $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

    if (($book === false) || ($rels === false)) {
        return '';
    }

    $previous = libxml_use_internal_errors(true);

    $book_xml = simplexml_load_string($book);
    $rels_xml = simplexml_load_string($rels);

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (($book_xml === false) || ($rels_xml === false)) {
        return '';
    }

    $sheet_id = '';

    foreach ($book_xml->sheets->sheet as $sheet) {

        foreach ($sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships') as $name => $value) {

            if ($name == 'id') {
                $sheet_id = (string) $value;
            }
        }

        break;
    }

    if ($sheet_id == '') {
        return '';
    }

    foreach ($rels_xml->Relationship as $relationship) {

        if (((string) $relationship['Id']) != $sheet_id) {
            continue;
        }

        $target = (string) $relationship['Target'];

        // Targets are written relative to xl/, and sometimes absolutely.
        return (substr($target, 0, 1) == '/')
            ? ltrim($target, '/')
            : ('xl/' . $target);
    }

    return '';
}

// The shared string table, as a plain list, or false when the table unpacks
// past the size the package declares for it (a damaged or doctored file).
//
// Excel writes every text value once here and refers to it by number, so a
// sheet of thirty thousand products with a repeated brand name holds the name
// once. Read with XMLReader rather than SimpleXML for the same reason as the
// sheet: the table of a large workbook is itself large. The stream stops at
// the declared size (pg_zip_entry_uri()); it would otherwise read on to
// whatever the entry inflates to.
function pg_products_import_xlsx_strings($path, $part)
{
    $strings = array();

    // A workbook this software wrote carries its text inline and has no table
    // at all, so its absence is the ordinary case rather than a fault.
    if ($part == '') {
        return $strings;
    }

    $reader = new XMLReader();

    if ($reader->open(pg_zip_entry_uri($path, $part, max(0, pg_zip_entry_size($path, $part)))) == false) {
        return $strings;
    }

    $previous = libxml_use_internal_errors(true);

    while ($reader->read()) {

        // By the local name, as the preview reads a workbook: Open XML SDK
        // writes x:si with a namespace prefix.
        if (($reader->nodeType != XMLReader::ELEMENT) || ($reader->localName != 'si')) {
            continue;
        }

        // A single <si> can be split into several <r> runs when part of the
        // text is styled differently; every text node inside it is joined,
        // which is what the cell reads as. Read node by node rather than
        // with XMLReader::readString, which builds the element as a tree in
        // libxml's memory first (memory_limit does not count it). No length
        // limit, as before.
        $strings[] = pg_office_reader_text($reader, PHP_INT_MAX);
    }

    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $reader->close();

    return pg_zip_entry_overran() ? false : $strings;
}

// The style numbers that mean "this number is a date".
//
// A date in a spreadsheet is a day count, and only the cell's number format
// says otherwise. Formats 14 to 22 and 45 to 47 are the built-in date and time
// ones; anything an operator defined themselves is judged by whether the
// pattern mentions a day, month or year.
function pg_products_import_xlsx_date_styles($zip)
{
    $dates = array();

    // Where the workbook's relationships name it (pg_sheet_xlsx_part()).
    $part = pg_sheet_xlsx_part($zip, 'styles', 'xl/styles.xml');
    $styles = ($part === '') ? false : $zip->getFromName($part);

    if ($styles === false) {
        return $dates;
    }

    $previous = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($styles);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if ($xml === false) {
        return $dates;
    }

    $custom = array();

    if (isset($xml->numFmts)) {

        foreach ($xml->numFmts->numFmt as $format) {

            $code = strtolower((string) $format['formatCode']);

            // The letters that only appear in a date or time pattern. Stripped
            // of anything quoted first, so a currency format with the word
            // "day" in its suffix is not mistaken for one.
            $code = preg_replace('/"[^"]*"/', '', $code);
            $code = preg_replace('/\[[^\]]*\]/', '', $code);

            if (preg_match('/[dmyhs]/', $code)) {
                $custom[(int) $format['numFmtId']] = true;
            }
        }
    }

    if (!isset($xml->cellXfs)) {
        return $dates;
    }

    $index = 0;

    foreach ($xml->cellXfs->xf as $xf) {

        $format_id = (int) $xf['numFmtId'];

        if ((($format_id >= 14) && ($format_id <= 22)) ||
            (($format_id >= 45) && ($format_id <= 47)) ||
            (isset($custom[$format_id]))) {

            $dates[$index] = true;
        }

        $index++;
    }

    return $dates;
}

// A cell reference as a column number: A1 is 0, B1 is 1, AA1 is 26.
function pg_products_import_xlsx_column($reference)
{
    $number = 0;

    for ($index = 0; $index < strlen($reference); $index++) {

        $character = $reference[$index];

        if (($character < 'A') || ($character > 'Z')) {
            break;
        }

        $number = ($number * 26) + (ord($character) - 64);
    }

    return ($number > 0) ? ($number - 1) : 0;
}

// A day count as the date the importer's columns are written in.
function pg_products_import_xlsx_date($serial)
{
    // Day 0 is 30 December 1899 -- the offset carries the 1900 leap year that
    // the format has been wrong about since Lotus 1-2-3.
    $stamp = ((float) $serial - 25569) * 86400;

    return gmdate(((((float) $serial) == floor((float) $serial)) ? 'Y-m-d' : 'Y-m-d H:i:s'), (int) round($stamp));
}

// Turn a .xlsx into the CSV the importer reads, and return its path.
function pg_products_import_xlsx_to_csv($source_path, &$error, $sheet = 0)
{
    $error = '';

    $zip = new ZipArchive();

    if ($zip->open($source_path) !== true) {
        $error = lang('The file could not be read.');
        return '';
    }

    $part = pg_products_import_xlsx_sheet_part($zip, $sheet);

    if (($part == '') || ($zip->locateName($part) === false)) {
        $zip->close();
        $error = lang('The file could not be read.');
        return '';
    }

    $date_styles = pg_products_import_xlsx_date_styles($zip);

    $strings_part = pg_sheet_xlsx_part($zip, 'sharedstrings', 'xl/sharedStrings.xml');

    $zip->close();

    $strings = pg_products_import_xlsx_strings($source_path, $strings_part);

    if ($strings === false) {
        $error = lang('The file could not be read.');
        return '';
    }

    $reader = new XMLReader();

    // The sheet, too, is read no further than its declared size.
    if ($reader->open(pg_zip_entry_uri($source_path, $part, max(0, pg_zip_entry_size($source_path, $part)))) == false) {
        $error = lang('The file could not be read.');
        return '';
    }

    $path = tempnam(sys_get_temp_dir(), 'pg_import_');

    if ($path === false) {
        $reader->close();
        $error = lang('The file could not be read.');
        return '';
    }

    $handle = fopen($path, 'w');

    if ($handle === false) {
        $reader->close();
        unlink($path);
        $error = lang('The file could not be read.');
        return '';
    }

    $written = 0;
    $width = 0;

    // A sheet cut at its declared size ends in a parser error; it is answered
    // below, not printed as a warning.
    $previous = libxml_use_internal_errors(true);

    while ($reader->read()) {

        if (($reader->nodeType != XMLReader::ELEMENT) || ($reader->localName != 'row')) {
            continue;
        }

        $row = array();
        $filled = false;
        $depth = $reader->depth;
        $has_cells = !$reader->isEmptyElement;

        // The row's own cells, each read node by node
        // (pg_office_xlsx_cell_parts()). A row is never built as a tree:
        // XMLReader::readOuterXml would build it in libxml's memory, which
        // memory_limit does not count, and one row a few kilobytes long on
        // disk can unpack to millions of nodes. The cells are still all
        // held for the row, because a cell can be missing and the row has
        // to line up with its heading.
        while ($has_cells && $reader->read() && ($reader->depth > $depth)) {

            if (($reader->nodeType != XMLReader::ELEMENT) || ($reader->depth != $depth + 1) || ($reader->localName != 'c')) {
                continue;
            }

            $column = pg_products_import_xlsx_column((string) $reader->getAttribute('r'));
            $type = (string) $reader->getAttribute('t');
            $style = (int) $reader->getAttribute('s');
            $cell = pg_office_xlsx_cell_parts($reader);
            $value = '';

            switch ($type) {

                case 's':
                    $index = (int) $cell['v'];
                    $value = isset($strings[$index]) ? $strings[$index] : '';
                    break;

                case 'inlineStr':
                    $value = $cell['is'] ? $cell['t'] : '';

                    // Styled runs again: the text is in <r><t> rather than <t>.
                    if (($value == '') && $cell['r']) {
                        $value = $cell['runs'];
                    }

                    break;

                case 'b':
                    $value = ($cell['v'] == '1') ? '1' : '0';
                    break;

                default:
                    $value = $cell['v'];

                    if (($value != '') && (is_numeric($value))) {

                        if (isset($date_styles[$style])) {
                            $value = pg_products_import_xlsx_date($value);
                        }
                    }

                    break;
            }

            // Missing cells are not written at all in a sparse sheet, so the
            // gaps are filled here rather than assumed away.
            while (count($row) < $column) {
                $row[] = '';
            }

            $row[$column] = $value;

            if ($value !== '') {
                $filled = true;
            }
        }

        if (!$filled) {
            continue;
        }

        // Every row is squared off to the widest one seen, which in practice
        // is the heading row: fgetcsv() gives the importer whatever the line
        // holds, and a short line would read the next column's value.
        if (count($row) > $width) {
            $width = count($row);
        }

        while (count($row) < $width) {
            $row[] = '';
        }

        ksort($row);

        fputcsv($handle, $row, ',', '"', '\\');
        $written++;
    }

    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $reader->close();
    fclose($handle);

    // A sheet that unpacks past its declared size is a damaged or doctored
    // file; what was read of it is not imported.
    if (pg_zip_entry_overran()) {
        unlink($path);
        $error = lang('The file could not be read.');
        return '';
    }

    if ($written == 0) {
        unlink($path);
        $error = lang('The file was empty.');
        return '';
    }

    return $path;
}
