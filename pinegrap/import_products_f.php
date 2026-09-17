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

// The PHPExcel reader for one extension.
//
// Named rather than auto-detected: createReaderForFile() opens the file with
// every reader in turn until one stops complaining, which on a large workbook
// means several wasted passes over a file that has already said what it is.
function pg_products_import_reader($file_name)
{
    $extension = strtolower((string) pathinfo((string) $file_name, PATHINFO_EXTENSION));

    switch ($extension) {

        case 'xlsx':
        case 'xlsm':
            return 'Excel2007';

        case 'xls':
            return 'Excel5';

        case 'ods':
            return 'OOCalc';
    }

    return '';
}

// One cell, as the text the importer would have read out of a CSV.
//
// A file this software wrote holds nothing but strings, so the common case is
// already right. A file an operator built by hand is not: a price typed into
// Excel is a number, and a date is a day count since 1900 that reaches the
// importer as "45678" unless it is turned back into a date here.
function pg_products_import_cell($cell)
{
    if ($cell === null) {
        return '';
    }

    // A formula is worth the calculation: an operator who built the sheet with
    // one meant its answer, not its text. A formula the engine cannot work out
    // falls back to what is written rather than stopping the import.
    try {
        $value = $cell->getCalculatedValue();
    } catch (Exception $exception) {
        $value = $cell->getValue();
    }

    if ($value === null) {
        return '';
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    if (PHPExcel_Shared_Date::isDateTime($cell)) {

        $stamp = PHPExcel_Shared_Date::ExcelToPHP((float) $value);

        // A whole day carries no time of day, so it is not given one: the
        // importer's date columns are days.
        return date((((float) $value) == floor((float) $value)) ? 'Y-m-d' : 'Y-m-d H:i:s', $stamp);
    }

    return (string) $value;
}

// Turn an uploaded spreadsheet into a CSV file and return its path.
//
// Returns '' and fills $error when the file cannot be read. The caller owns
// the returned file and should unlink() it when the import is over.
function pg_products_import_to_csv($source_path, $file_name, &$error)
{
    $error = '';

    $reader_name = pg_products_import_reader($file_name);

    if ($reader_name == '') {
        $error = lang('The file was empty.');
        return '';
    }

    // A .xlsx is read without PHPExcel, for the same reason it is written
    // without it: the library holds an object per cell, and a catalog of
    // thirty thousand products is two and a half million of them. It is also
    // the format this software exports, so an operator who takes the catalog
    // out, edits it and brings it back never leaves this path.
    //
    // The older Excel format and OpenDocument stay with PHPExcel. They are a
    // binary record stream and a different zip respectively, they arrive
    // rarely, and reading a format somebody else wrote is what a library is
    // worth carrying for.
    if (($reader_name == 'Excel2007') && (class_exists('ZipArchive'))) {
        return pg_products_import_xlsx_to_csv($source_path, $error);
    }

    require_once dirname(__FILE__) . '/includes/phpexcel/PHPExcel.php';

    try {

        $reader = PHPExcel_IOFactory::createReader($reader_name);

        // Formatting, column widths and merged cells are of no interest to an
        // importer that wants values, and skipping them is the difference
        // between a workbook of ten thousand products opening and not.
        $reader->setReadDataOnly(true);

        $book = $reader->load($source_path);

    } catch (Exception $exception) {
        $error = lang('The file could not be read.');
        return '';
    }

    $sheet = $book->getSheet(0);

    if (!$sheet) {
        $book->disconnectWorksheets();
        $error = lang('The file was empty.');
        return '';
    }

    $last_row = $sheet->getHighestDataRow();
    $last_column = PHPExcel_Cell::columnIndexFromString($sheet->getHighestDataColumn()) - 1;

    $path = tempnam(sys_get_temp_dir(), 'pg_import_');

    if ($path === false) {
        $book->disconnectWorksheets();
        $error = lang('The file could not be read.');
        return '';
    }

    $handle = fopen($path, 'w');

    if ($handle === false) {
        $book->disconnectWorksheets();
        unlink($path);
        $error = lang('The file could not be read.');
        return '';
    }

    $written = 0;

    for ($row_number = 1; $row_number <= $last_row; $row_number++) {

        $row = array();
        $filled = false;

        for ($column_number = 0; $column_number <= $last_column; $column_number++) {

            $value = pg_products_import_cell($sheet->getCellByColumnAndRow($column_number, $row_number, false));

            if ($value !== '') {
                $filled = true;
            }

            $row[] = $value;
        }

        // A row of empty cells is what a spreadsheet leaves behind when
        // somebody clears one, and the importer would read it as a product
        // with no name. Skipped -- except that a blank line inside the data
        // would shift nothing, so only truly empty rows go.
        if (!$filled) {
            continue;
        }

        fputcsv($handle, $row);
        $written++;
    }

    fclose($handle);
    $book->disconnectWorksheets();
    unset($book);

    if ($written == 0) {
        unlink($path);
        $error = lang('The file was empty.');
        return '';
    }

    return $path;
}

// ── Reading a .xlsx without holding it ──────────────────────────────────────
//
// A worksheet is XML in a zip. XMLReader walks it an element at a time, so the
// memory cost is one row rather than one workbook, and the CSV is written as
// the rows go past.

// Which part of the zip holds the first sheet.
//
// Not assumed to be sheet1.xml: a workbook whose sheets have been reordered or
// deleted keeps the old file names, and the first tab can be sheet3.xml. The
// answer is in the workbook and its relationships, both small.
function pg_products_import_xlsx_sheet_part($zip)
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

// The shared string table, as a plain list.
//
// Excel writes every text value once here and refers to it by number, so a
// sheet of thirty thousand products with a repeated brand name holds the name
// once. Read with XMLReader rather than SimpleXML for the same reason as the
// sheet: the table of a large workbook is itself large.
function pg_products_import_xlsx_strings($path, $part)
{
    $strings = array();

    // A workbook this software wrote carries its text inline and has no table
    // at all, so its absence is the ordinary case rather than a fault.
    if ($part == '') {
        return $strings;
    }

    $reader = new XMLReader();

    if ($reader->open('zip://' . $path . '#' . $part) == false) {
        return $strings;
    }

    while ($reader->read()) {

        if (($reader->nodeType != XMLReader::ELEMENT) || ($reader->name != 'si')) {
            continue;
        }

        // A single <si> can be split into several <r> runs when part of the
        // text is styled differently. readString() joins them, which is what
        // the cell reads as.
        $strings[] = $reader->readString();
    }

    $reader->close();

    return $strings;
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

    $styles = $zip->getFromName('xl/styles.xml');

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

    return date(((((float) $serial) == floor((float) $serial)) ? 'Y-m-d' : 'Y-m-d H:i:s'), (int) round($stamp));
}

// Turn a .xlsx into the CSV the importer reads, and return its path.
function pg_products_import_xlsx_to_csv($source_path, &$error)
{
    $error = '';

    $zip = new ZipArchive();

    if ($zip->open($source_path) !== true) {
        $error = lang('The file could not be read.');
        return '';
    }

    $part = pg_products_import_xlsx_sheet_part($zip);

    if (($part == '') || ($zip->locateName($part) === false)) {
        $zip->close();
        $error = lang('The file could not be read.');
        return '';
    }

    $date_styles = pg_products_import_xlsx_date_styles($zip);

    $strings_part = ($zip->locateName('xl/sharedStrings.xml') !== false) ? 'xl/sharedStrings.xml' : '';

    $zip->close();

    $strings = pg_products_import_xlsx_strings($source_path, $strings_part);

    $reader = new XMLReader();

    if ($reader->open('zip://' . $source_path . '#' . $part) == false) {
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

    while ($reader->read()) {

        if (($reader->nodeType != XMLReader::ELEMENT) || ($reader->name != 'row')) {
            continue;
        }

        $row = array();
        $filled = false;

        // One row, read out of the reader as its own small document. The rows
        // of a worksheet are the one place where the cells are worth having
        // all at once, because a cell can be missing and the row still has to
        // line up with its heading.
        $row_xml = simplexml_load_string($reader->readOuterXml());

        if ($row_xml === false) {
            continue;
        }

        foreach ($row_xml->c as $cell) {

            $column = pg_products_import_xlsx_column((string) $cell['r']);
            $type = (string) $cell['t'];
            $value = '';

            switch ($type) {

                case 's':
                    $index = (int) $cell->v;
                    $value = isset($strings[$index]) ? $strings[$index] : '';
                    break;

                case 'inlineStr':
                    $value = isset($cell->is) ? (string) $cell->is->t : '';

                    // Styled runs again: the text is in <r><t> rather than <t>.
                    if (($value == '') && (isset($cell->is->r))) {

                        foreach ($cell->is->r as $run) {
                            $value .= (string) $run->t;
                        }
                    }

                    break;

                case 'b':
                    $value = (((string) $cell->v) == '1') ? '1' : '0';
                    break;

                default:
                    $value = (string) $cell->v;

                    if (($value != '') && (is_numeric($value))) {

                        $style = (int) $cell['s'];

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

        fputcsv($handle, $row);
        $written++;
    }

    $reader->close();
    fclose($handle);

    if ($written == 0) {
        unlink($path);
        $error = lang('The file was empty.');
        return '';
    }

    return $path;
}
