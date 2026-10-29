<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Word and Excel files read on the server for a preview in the browser.
 *
 * A .docx and a .xlsx are zips of XML. Nothing here needs a library: the
 * worksheet is walked with XMLReader a row at a time (the way the product
 * importer reads one), the document with DOMDocument, and what comes out is
 * plain data - rows of text for a workbook, a small safe subset of HTML for
 * a document - that the file manager and the Workspace draw. The files are
 * never written to here; changing a workbook's cells is the business of
 * includes/fn/office_edit.php, which the preview asks which cells it may
 * offer.
 *
 * Every part of the zip is measured before it is opened, so a file built to
 * expand into gigabytes is refused rather than read, and the output is cut
 * at a size a page can show. A part built into a DOM has a smaller ceiling
 * of its own, as libxml's memory for the tree is not counted by
 * memory_limit, and the rows and strings of a workbook are read node by node,
 * never built as a tree.
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
 * The most a single XML part of the package may unpack to, and the most the
 * whole package may unpack to, in bytes.
 */
define('PG_OFFICE_MAX_PART', 64 * 1024 * 1024);
define('PG_OFFICE_MAX_PACKAGE', 256 * 1024 * 1024);

/**
 * The most an XML part read whole into a DOM may unpack to, in bytes. The
 * tree takes many times the part's size in libxml's memory, which
 * memory_limit does not count: 8 MB of a document's paragraphs took about
 * 150 MB, 60 MB took 860 MB. A document's main part past this is read from
 * its beginning and shown cut (the preview cuts its HTML well before);
 * any other part past this makes the file too large to show.
 */
define('PG_OFFICE_MAX_DOM', 8 * 1024 * 1024);

/**
 * How much of a workbook the preview shows: rows and columns a sheet, cells
 * across every sheet.
 */
define('PG_OFFICE_MAX_ROWS', 2000);
define('PG_OFFICE_MAX_COLUMNS', 200);
define('PG_OFFICE_MAX_CELLS', 120000);

/**
 * How much of a workbook's shared string table is kept: strings, and their
 * bytes together. The table is read whole before any sheet, since a cell
 * names its text by number, so these bound the memory a table within the
 * part limit can take.
 */
define('PG_OFFICE_MAX_STRINGS', 500000);
define('PG_OFFICE_MAX_STRING_BYTES', 32 * 1024 * 1024);

/**
 * How much of a document the preview shows: characters of HTML, and the
 * pictures inlined (one, and all together), in bytes.
 */
define('PG_OFFICE_MAX_HTML', 600000);
define('PG_OFFICE_MAX_IMAGE', 2 * 1024 * 1024);
define('PG_OFFICE_MAX_IMAGES', 8 * 1024 * 1024);

/**
 * The kind of office file a name is, by its extension: 'docx', 'xlsx' or ''.
 * Macro-enabled and template variants are read the same way, as are the
 * strict OOXML ones; .doc and .xls (the binary formats) are not read.
 *
 * @param string $name
 * @return string
 */
function pg_office_kind($name)
{
    $extension = strtolower((string) pathinfo((string) $name, PATHINFO_EXTENSION));

    if (in_array($extension, array('docx', 'docm', 'dotx', 'dotm'), true)) {
        return 'docx';
    }

    if (in_array($extension, array('xlsx', 'xlsm', 'xltx', 'xltm'), true)) {
        return 'xlsx';
    }

    return '';
}

/**
 * Can this server read the packages at all?
 *
 * @return bool
 */
function pg_office_ready()
{
    return class_exists('ZipArchive') && class_exists('XMLReader') && class_exists('DOMDocument');
}

/**
 * Opens a package and measures it. A zip that is not an office package, or
 * one that would unpack past the limits, is refused.
 *
 * @param string $path
 * @param string $error why not, when null comes back
 * @return ZipArchive|null
 */
function pg_office_open($path, &$error)
{
    $error = '';

    if (!pg_office_ready()) {
        $error = lang('This server cannot show office files (the PHP zip or xml extension is missing).');

        return null;
    }

    if (!is_file($path)) {
        $error = lang('The file could not be read.');

        return null;
    }

    $zip = new ZipArchive();

    if ($zip->open($path) !== true) {
        $error = lang('The file could not be read.');

        return null;
    }

    $total = 0;

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $stat = $zip->statIndex($index);

        if ($stat === false) {
            continue;
        }

        $total += (int) $stat['size'];

        if (((int) $stat['size'] > PG_OFFICE_MAX_PART) || ($total > PG_OFFICE_MAX_PACKAGE)) {
            $zip->close();
            $error = lang('This file is too large to show here. Download it to open it.');

            return null;
        }
    }

    if ($zip->locateName('[Content_Types].xml') === false) {
        $zip->close();
        $error = lang('The file could not be read.');

        return null;
    }

    return $zip;
}

/**
 * One XML part of the package as a DOMDocument, with nothing fetched from
 * outside it (no network, no external entities), or null.
 *
 * The part is read whole through pg_zip_entry_contents(): only when it
 * declares no more than PG_OFFICE_MAX_DOM, and no further than it declares.
 * With $head, a part past the ceiling is read from its beginning instead:
 * its first PG_OFFICE_MAX_DOM bytes up to the end of the last tag in them,
 * the elements still open there closed by libxml's recovery, so the tree
 * holds the part's first whole elements.
 *
 * @param ZipArchive $zip
 * @param string     $name
 * @param string     $status '' read whole; 'cut' only its beginning (with
 *                           $head); with null: 'missing' (no such part, or an
 *                           empty one), 'large', 'damaged' (not the size it
 *                           declares) or 'xml' (not XML)
 * @param bool       $head   read a part past the ceiling from its beginning
 * @return DOMDocument|null
 */
function pg_office_xml($zip, $name, &$status = null, $head = false)
{
    $status = '';
    $name = (string) $name;
    $stat = $head ? $zip->statName($name) : false;
    $cut = ($stat !== false) && isset($stat['size'], $stat['index']) && ((float) $stat['size'] > PG_OFFICE_MAX_DOM);

    if ($cut) {
        $xml = $zip->getFromIndex((int) $stat['index'], PG_OFFICE_MAX_DOM);

        // Fewer bytes than asked for from a part that declares more.
        if (!is_string($xml) || (strlen($xml) !== PG_OFFICE_MAX_DOM)) {
            $status = 'damaged';

            return null;
        }

        $end = strrpos($xml, '>');
        $xml = ($end === false) ? '' : substr($xml, 0, $end + 1);
    } else {
        $xml = pg_zip_entry_contents($zip, $name, PG_OFFICE_MAX_DOM, $refused);

        if ($xml === null) {
            $status = $refused;

            return null;
        }
    }

    if ($xml === '') {
        $status = $cut ? 'xml' : 'missing';

        return null;
    }

    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->recover = $cut;
    $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
    unset($xml);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded || ($dom->documentElement === null)) {
        $status = 'xml';

        return null;
    }

    $status = $cut ? 'cut' : '';

    return $dom;
}

/**
 * Keeps the first reason a part read whole stops a file being shown: one
 * past PG_OFFICE_MAX_DOM, or one that does not unpack to the size it
 * declares. A part that is missing, empty or not XML is read as absent.
 *
 * @param string $refused '' so far, or the first reason (large, damaged), kept
 * @param string $status  the part's status from pg_office_xml()
 */
function pg_office_refuse(&$refused, $status)
{
    if (((string) $refused === '') && in_array($status, array('large', 'damaged'), true)) {
        $refused = $status;
    }
}

/**
 * The message for a file refused by pg_office_refuse()'s reason.
 *
 * @param string $refused large or damaged
 * @return string
 */
function pg_office_refusal_error($refused)
{
    return ($refused === 'large')
        ? lang('This file is too large to show here. Download it to open it.')
        : lang('The file could not be read.');
}

/**
 * The relationships of a part: id => [target, type word, external].
 *
 * @param ZipArchive $zip
 * @param string     $name    the .rels part
 * @param string     $refused set to large or damaged when the part could not be read whole (pg_office_refuse())
 * @return array
 */
function pg_office_rels($zip, $name, &$refused = null)
{
    $out = array();
    $refused = '';
    $dom = pg_office_xml($zip, $name, $status);
    pg_office_refuse($refused, $status);

    if ($dom === null) {
        return $out;
    }

    foreach ($dom->getElementsByTagName('Relationship') as $relationship) {
        $type = (string) $relationship->getAttribute('Type');

        $out[(string) $relationship->getAttribute('Id')] = array(
            'target'   => (string) $relationship->getAttribute('Target'),
            'type'     => strtolower((string) substr($type, (int) strrpos($type, '/') + 1)),
            'external' => (strtolower((string) $relationship->getAttribute('TargetMode')) === 'external'),
        );
    }

    return $out;
}

/**
 * An attribute of an element by its local name, whatever prefix or namespace
 * the file wrote it with (w:val in a transitional document, a bare val in a
 * strict one).
 *
 * @param DOMElement $node
 * @param string     $local
 * @return string '' when there is none
 */
function pg_office_attr($node, $local)
{
    if (!$node->hasAttributes()) {
        return '';
    }

    foreach ($node->attributes as $attribute) {
        if ($attribute->localName === $local) {
            return (string) $attribute->value;
        }
    }

    return '';
}

/**
 * A relationship target as a part name: written relative to the part's
 * folder, or absolutely.
 *
 * @param string $folder 'xl/' or 'word/'
 * @param string $target
 * @return string
 */
function pg_office_part_name($folder, $target)
{
    $target = str_replace('\\', '/', (string) $target);

    if (($target !== '') && ($target[0] === '/')) {
        return ltrim($target, '/');
    }

    // Steps up (../) are folded, never allowed to leave the package root.
    $parts = array();

    foreach (explode('/', rtrim($folder, '/') . '/' . $target) as $piece) {
        if (($piece === '') || ($piece === '.')) {
            continue;
        }

        if ($piece === '..') {
            array_pop($parts);
            continue;
        }

        $parts[] = $piece;
    }

    return implode('/', $parts);
}

/* ---------------------------------------------------------------------------
   Workbooks
   --------------------------------------------------------------------------- */

/**
 * The sheets of a workbook in their order: name, part, hidden, and type
 * (worksheet; a chart sheet, a dialog sheet or a macro sheet has no cells to
 * change).
 *
 * @param ZipArchive $zip
 * @param string     $refused set to large or damaged when the workbook part or its relationships could not be read whole
 * @return array[]
 */
function pg_office_xlsx_sheets($zip, &$refused = null)
{
    $out = array();
    $refused = '';
    $book = pg_office_xml($zip, 'xl/workbook.xml', $status);
    pg_office_refuse($refused, $status);

    if ($book === null) {
        return $out;
    }

    $rels = pg_office_rels($zip, 'xl/_rels/workbook.xml.rels', $rels_refused);
    pg_office_refuse($refused, $rels_refused);

    foreach ($book->getElementsByTagName('sheet') as $sheet) {
        $id = pg_office_attr($sheet, 'id');

        if (($id === '') || !isset($rels[$id])) {
            continue;
        }

        $part = pg_office_part_name('xl/', $rels[$id]['target']);

        if ($zip->locateName($part) === false) {
            continue;
        }

        $out[] = array(
            'name'   => (string) $sheet->getAttribute('name'),
            'part'   => $part,
            'hidden' => in_array(strtolower((string) $sheet->getAttribute('state')), array('hidden', 'veryhidden'), true),
            'type'   => $rels[$id]['type'],
        );
    }

    return $out;
}

/**
 * The built-in zip:// stream, which XMLReader walks a sheet through. PHPExcel
 * (the .xls and .ods importers) replaces that wrapper with its own for the
 * rest of the request and switches libxml's loader off, and XMLReader cannot
 * open a part through either; both are put back once, when first needed.
 * Nothing here asks libxml to resolve entities (no LIBXML_NOENT, always
 * LIBXML_NONET), so the loader being on changes nothing else.
 */
function pg_office_zip_stream()
{
    static $restored = false;

    if (!$restored) {
        $restored = true;
        @stream_wrapper_restore('zip');

        if (function_exists('libxml_disable_entity_loader')) {
            @libxml_disable_entity_loader(false);
        }
    }
}

/**
 * The text of the element the reader is on, as XMLReader::readString()
 * gives it (every text and CDATA node inside it, whitespace included), read
 * node by node. readString(), readOuterXml() and expand() first build the
 * whole element as a tree in libxml's memory, which memory_limit does not
 * count: one string or row a few kilobytes long on disk can unpack to
 * millions of nodes. The reader is left on the element's last node; text past
 * $limit bytes is passed over, not kept.
 *
 * @param XMLReader $reader on an element
 * @param int       $limit  the most bytes kept
 * @param bool      $over   set when the text is longer than $limit
 * @return string
 */
function pg_office_reader_text($reader, $limit, &$over = null)
{
    $over = false;
    $text = '';

    if ($reader->isEmptyElement) {
        return $text;
    }

    $depth = $reader->depth;
    $kinds = array(XMLReader::TEXT, XMLReader::CDATA, XMLReader::WHITESPACE, XMLReader::SIGNIFICANT_WHITESPACE);

    while ($reader->read() && ($reader->depth > $depth)) {
        if ($over || !in_array($reader->nodeType, $kinds, true)) {
            continue;
        }

        $text .= $reader->value;

        if (strlen($text) > $limit) {
            $text = substr($text, 0, max(0, (int) $limit));
            $over = true;
        }
    }

    return $text;
}

/**
 * The shared string table, as a list.
 *
 * Read no further than the size the package declares for the table
 * (pg_zip_entry_uri()): a stream would otherwise inflate a table that
 * declares a kilobyte to whatever it holds. A table that unpacks past its
 * declared size is damaged; one past PG_OFFICE_MAX_STRINGS strings or
 * PG_OFFICE_MAX_STRING_BYTES bytes is too large to show. Each string is read
 * node by node (pg_office_reader_text()).
 *
 * @param string $path the package on disk
 * @param string $part '' when the workbook has none
 * @return string[]|false|null false when damaged, null when too large
 */
function pg_office_xlsx_strings($path, $part)
{
    $strings = array();

    if ($part === '') {
        return $strings;
    }

    $size = pg_zip_entry_size($path, $part);

    if ($size < 0) {
        return $strings;
    }

    pg_office_zip_stream();

    $reader = new XMLReader();

    if (!$reader->open(pg_zip_entry_uri($path, $part, $size), null, LIBXML_NONET)) {
        return $strings;
    }

    $previous = libxml_use_internal_errors(true);
    $bytes = 0;
    $too_large = false;

    while ($reader->read()) {
        // By the local name: a workbook written with a namespace prefix
        // (Open XML SDK writes x:si, x:row, x:c) is the same workbook.
        if (($reader->nodeType === XMLReader::ELEMENT) && ($reader->localName === 'si')) {
            $string = pg_office_reader_text($reader, PG_OFFICE_MAX_STRING_BYTES - $bytes + 1, $over);
            $bytes += strlen($string);

            if ($over || (count($strings) >= PG_OFFICE_MAX_STRINGS) || ($bytes > PG_OFFICE_MAX_STRING_BYTES)) {
                $too_large = true;
                break;
            }

            $strings[] = $string;
        }
    }

    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $reader->close();

    if (pg_zip_entry_overran()) {
        return false;
    }

    return $too_large ? null : $strings;
}

/**
 * The cell style numbers that mean "a date": the built-in date and time
 * formats, and the operator's own whose pattern names a day, month or year.
 *
 * @param ZipArchive $zip
 * @param string     $refused set to large or damaged when the style part could not be read whole
 * @return array style index => pg_office_xlsx_format_kind() (date, time, time_s, hours, hours_s, minutes)
 */
function pg_office_xlsx_date_styles($zip, &$refused = null)
{
    $dates = array();
    $refused = '';
    $styles = pg_office_xml($zip, 'xl/styles.xml', $status);
    pg_office_refuse($refused, $status);

    if ($styles === null) {
        return $dates;
    }

    // The built-in formats (ECMA-376 18.8.30): 14-17 dates, 18-21 times of
    // day, 22 a date and time, 45 mm:ss, 46 [h]:mm:ss, 47 mm:ss.0.
    $builtin = array(14 => 'date', 15 => 'date', 16 => 'date', 17 => 'date', 18 => 'time', 19 => 'time_s',
        20 => 'time', 21 => 'time_s', 22 => 'date', 45 => 'minutes', 46 => 'hours_s', 47 => 'minutes');
    $custom = array();

    foreach ($styles->getElementsByTagName('numFmt') as $format) {
        $kind = pg_office_xlsx_format_kind((string) $format->getAttribute('formatCode'));

        if ($kind !== '') {
            $custom[(int) $format->getAttribute('numFmtId')] = $kind;
        }
    }

    $index = 0;

    foreach ($styles->getElementsByTagName('cellXfs') as $xfs) {
        foreach ($xfs->getElementsByTagName('xf') as $xf) {
            $format_id = (int) $xf->getAttribute('numFmtId');

            if (isset($custom[$format_id])) {
                $dates[$index] = $custom[$format_id];
            } elseif (isset($builtin[$format_id])) {
                $dates[$index] = $builtin[$format_id];
            }

            $index++;
        }
    }

    return $dates;
}

/**
 * A cell reference as a column number: A1 is 0, B1 is 1, AA1 is 26.
 *
 * @param string $reference
 * @return int
 */
function pg_office_xlsx_column($reference)
{
    $number = 0;
    $reference = (string) $reference;

    for ($index = 0; $index < strlen($reference); $index++) {
        $character = $reference[$index];

        if (($character < 'A') || ($character > 'Z')) {
            break;
        }

        $number = ($number * 26) + (ord($character) - 64);
    }

    return ($number > 0) ? ($number - 1) : 0;
}

/**
 * A day count as a date, or a date and time when it has a fraction. Day 0
 * is 30 December 1899, or 1 January 1904 in a workbook that counts from
 * there (old Mac files).
 *
 * @param float|string $serial
 * @param bool         $date1904
 * @return string
 */
function pg_office_xlsx_date($serial, $date1904 = false)
{
    $stamp = ((float) $serial - ($date1904 ? 24107 : 25569)) * 86400;

    return gmdate((((float) $serial) == floor((float) $serial)) ? 'Y-m-d' : 'Y-m-d H:i', (int) round($stamp));
}

/**
 * What a number format shows of a day count: 'date' (a day, month or year,
 * with or without a time), 'time' / 'time_s' (a time of day, with seconds),
 * 'hours' / 'hours_s' (elapsed hours, [h]), 'minutes' (minutes and seconds,
 * mm:ss), or '' for none. An m next to an h or before an s is minutes, as
 * Excel reads it.
 *
 * @param string $code a numFmt formatCode
 * @return string
 */
function pg_office_xlsx_format_kind($code)
{
    $code = strtolower((string) $code);
    $code = preg_replace('/"[^"]*"/', '', $code);
    // An escaped character, a padding (_x) or fill (*x) character, and the
    // AM/PM marker are not parts of a date.
    $code = preg_replace('/[\\\\_*]./', '', $code);
    $code = str_replace(array('am/pm', 'a/p'), '', $code);
    $elapsed = preg_match('/\[h+\]/', $code) === 1;
    $code = preg_replace('/\[[^\]]*\]/', $elapsed ? 'h' : '', $code);

    // Minutes, not a month: after an hour or before a second.
    $code = preg_replace('/(h+[^a-z0-9]*)m+/', '$1', $code);
    $code = preg_replace('/m+([^a-z0-9]*s)/', '$1', $code);

    $date = preg_match('/[dmy]/', $code) === 1;
    $seconds = preg_match('/s/', $code) === 1;
    $hours = preg_match('/h/', $code) === 1;

    if ($date) {
        return 'date';
    }

    if ($elapsed) {
        return $seconds ? 'hours_s' : 'hours';
    }

    if ($hours) {
        return $seconds ? 'time_s' : 'time';
    }

    return $seconds ? 'minutes' : '';
}

/**
 * A day count shown as a time, rounded to the unit its format shows: a time
 * of day (the days left out), elapsed hours, or minutes and seconds.
 *
 * @param float|string $serial
 * @param string       $kind time, time_s, hours, hours_s, minutes (pg_office_xlsx_format_kind())
 * @return string
 */
function pg_office_xlsx_time($serial, $kind)
{
    $seconds = (float) $serial * 86400;
    $sign = ($seconds < 0) ? '-' : '';
    $seconds = abs($seconds);

    switch ($kind) {
        case 'time':
            $minutes = ((int) round($seconds / 60)) % 1440;
            return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);

        case 'time_s':
            $whole = ((int) round($seconds)) % 86400;
            return sprintf('%02d:%02d:%02d', intdiv($whole, 3600), intdiv($whole % 3600, 60), $whole % 60);

        case 'hours':
            $minutes = (int) round($seconds / 60);
            return $sign . sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);

        case 'hours_s':
            $whole = (int) round($seconds);
            return $sign . sprintf('%02d:%02d:%02d', intdiv($whole, 3600), intdiv($whole % 3600, 60), $whole % 60);

        default:
            $whole = (int) round($seconds);
            return $sign . sprintf('%02d:%02d', intdiv($whole, 60), $whole % 60);
    }
}

/**
 * A number as the cell shows it: whole numbers plain, fractions with their
 * digits and no trailing zeros, the exponent form of very large or small
 * values written out.
 *
 * @param string $value
 * @return string
 */
function pg_office_xlsx_number($value)
{
    if (!is_numeric($value)) {
        return (string) $value;
    }

    if (strpbrk((string) $value, 'eE.') === false) {
        return (string) $value;
    }

    $text = rtrim(rtrim(number_format((float) $value, 10, '.', ''), '0'), '.');

    return ($text === '' || $text === '-0') ? '0' : $text;
}

/**
 * What the preview takes from one cell, read node by node from the reader on
 * its element (pg_office_reader_text() says why): the text of its first
 * value, whether it has a formula, and of its first inline string the text
 * of the first text element and of each run's first text element. Each is
 * the element's own text, as SimpleXML gives it ($cell->v, $cell->f,
 * $cell->is->t, $cell->is->r->t), matched by the name the sheet writes as
 * the rest of this reader matches them. The reader is left on the cell's
 * last node.
 *
 * @param XMLReader $reader on a c element
 * @return array v, f, is, t, r (it has runs), runs (their texts joined)
 */
function pg_office_xlsx_cell_parts($reader)
{
    $out = array('v' => '', 'f' => false, 'is' => false, 't' => '', 'r' => false, 'runs' => '');

    if ($reader->isEmptyElement) {
        return $out;
    }

    $depth = $reader->depth;
    $kinds = array(XMLReader::TEXT, XMLReader::CDATA, XMLReader::WHITESPACE, XMLReader::SIGNIFICANT_WHITESPACE);

    // What the element open at each depth below the cell is to this read:
    // v (the first value), is (the first inline string), t (its first text),
    // r (one of its runs), rt (that run's first text), or ''.
    $open = array();
    $seen = array('v' => false, 'is' => false, 't' => false, 'rt' => false);

    while ($reader->read() && ($reader->depth > $depth)) {
        $level = $reader->depth - $depth;

        if ($reader->nodeType === XMLReader::ELEMENT) {
            $name = $reader->localName;
            $parent = ($level === 1) ? 'c' : (isset($open[$level - 1]) ? $open[$level - 1] : '');
            $role = '';

            if ($parent === 'c') {
                if ($name === 'f') {
                    $out['f'] = true;
                } elseif (($name === 'v') && !$seen['v']) {
                    $role = 'v';
                    $seen['v'] = true;
                } elseif (($name === 'is') && !$seen['is']) {
                    $role = 'is';
                    $seen['is'] = true;
                    $out['is'] = true;
                }
            } elseif ($parent === 'is') {
                if (($name === 't') && !$seen['t']) {
                    $role = 't';
                    $seen['t'] = true;
                } elseif ($name === 'r') {
                    $role = 'r';
                    $out['r'] = true;
                    $seen['rt'] = false;
                }
            } elseif (($parent === 'r') && ($name === 't') && !$seen['rt']) {
                $role = 'rt';
                $seen['rt'] = true;
            }

            $open[$level] = $role;
            continue;
        }

        if (!in_array($reader->nodeType, $kinds, true)) {
            continue;
        }

        // The element the text stands directly in.
        $owner = isset($open[$level - 1]) ? $open[$level - 1] : '';

        if ($owner === 'v') {
            $out['v'] .= $reader->value;
        } elseif ($owner === 't') {
            $out['t'] .= $reader->value;
        } elseif ($owner === 'rt') {
            $out['runs'] .= $reader->value;
        }
    }

    return $out;
}

/**
 * The rows of one sheet, as lists of strings, cut at the limits.
 *
 * A row is walked cell by cell and each cell node by node
 * (pg_office_xlsx_cell_parts()): a row is never built as a tree, so a sheet
 * of one row with a million cells costs no more than its cells shown.
 *
 * @param string $path        the package on disk
 * @param string $part        the sheet part
 * @param array  $strings     the shared strings
 * @param array  $date_styles pg_office_xlsx_date_styles()
 * @param int    $cells_left  how many cells may still be read, counted down
 * @param bool   $truncated   set when the sheet was cut
 * @param bool   $date1904    the workbook counts its days from 1904
 * @return array[]
 */
function pg_office_xlsx_rows($path, $part, $strings, $date_styles, &$cells_left, &$truncated, $date1904 = false)
{
    $rows = array();
    $truncated = false;

    pg_office_zip_stream();

    $reader = new XMLReader();

    // No further than the sheet's declared size: past it the read stops, and
    // the rows read until then are shown as the beginning of the sheet.
    if (!$reader->open(pg_zip_entry_uri($path, $part, max(0, pg_zip_entry_size($path, $part))), null, LIBXML_NONET)) {
        return $rows;
    }

    $previous = libxml_use_internal_errors(true);
    $last_number = 0;

    while ($reader->read()) {
        if (($reader->nodeType !== XMLReader::ELEMENT) || ($reader->localName !== 'row')) {
            continue;
        }

        if ((count($rows) >= PG_OFFICE_MAX_ROWS) || ($cells_left <= 0)) {
            $truncated = true;
            break;
        }

        // The row number the sheet gives, so an empty stretch stays visible
        // as a gap rather than closing up. The attribute is optional
        // (ECMA-376): a row without it is the one after the last.
        $given = $reader->getAttribute('r');
        $number = (($given !== null) && ($given !== '')) ? (int) $given : ($last_number + 1);
        $last_number = $number;
        $depth = $reader->depth;
        $has_cells = !$reader->isEmptyElement;
        $row = array();
        $waiting = array();
        $past_columns = false;
        $next_column = 0;

        // The row's own cells only; past the column limit the rest of the
        // row is read through and left.
        while ($has_cells && $reader->read() && ($reader->depth > $depth)) {
            if ($past_columns || ($reader->nodeType !== XMLReader::ELEMENT) || ($reader->depth !== $depth + 1) || ($reader->localName !== 'c')) {
                continue;
            }

            // A cell without its reference is the column after the last one.
            $reference = $reader->getAttribute('r');
            $column = (($reference !== null) && ($reference !== '')) ? pg_office_xlsx_column((string) $reference) : $next_column;
            $next_column = $column + 1;

            if ($column >= PG_OFFICE_MAX_COLUMNS) {
                $truncated = true;
                $past_columns = true;
                continue;
            }

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

                    if (($value === '') && $cell['r']) {
                        $value = $cell['runs'];
                    }
                    break;

                case 'b':
                    $value = ($cell['v'] === '1') ? 'TRUE' : 'FALSE';
                    break;

                case 'str':
                case 'e':
                    $value = $cell['v'];
                    break;

                default:
                    $value = $cell['v'];

                    if (($value !== '') && is_numeric($value)) {
                        $kind = isset($date_styles[$style]) ? $date_styles[$style] : '';

                        if ($kind === 'date') {
                            $value = pg_office_xlsx_date($value, $date1904);
                        } elseif ($kind !== '') {
                            $value = pg_office_xlsx_time($value, $kind);
                        } else {
                            $value = pg_office_xlsx_number($value);
                        }
                    }
            }

            while (count($row) < $column) {
                $row[] = '';
            }

            $row[$column] = $value;

            // A formula with no result stored: the program that wrote the
            // file did not work it out (or an edit here took the stale one
            // away), and a spreadsheet program will when it opens the file.
            if (($value === '') && $cell['f']) {
                $waiting[] = $column;
            }
        }

        $entry = array('n' => $number, 'cells' => $row);

        if (!empty($waiting)) {
            $entry['calc'] = $waiting;
        }

        $rows[] = $entry;
        $cells_left -= max(1, count($row));
    }

    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $reader->close();

    if (pg_zip_entry_overran()) {
        $truncated = true;
    }

    return $rows;
}

/**
 * A workbook as the preview draws it.
 *
 * Asked for editing, each sheet also says whether its cells may be changed
 * and which of the shown ones may not (pg_office_xlsx_sheet_edit_info()),
 * and the workbook carries its version, which a save hands back.
 *
 * @param string $path
 * @param bool   $edit
 * @return array ok, error, sheets (name, hidden, rows [n, cells], truncated[, edit]), truncated[, version]
 */
function pg_office_xlsx_read($path, $edit = false)
{
    // The version is taken before anything is read: a file changed while it
    // is read then fails the save's check, instead of passing it with cells
    // the person never saw.
    $version = ($edit && function_exists('pg_office_version')) ? pg_office_version($path) : '';
    $zip = pg_office_open($path, $error);

    if ($zip === null) {
        return array('ok' => false, 'error' => $error, 'sheets' => array(), 'truncated' => false);
    }

    $sheets = pg_office_xlsx_sheets($zip, $refused);
    $date_styles = ($refused === '') ? pg_office_xlsx_date_styles($zip, $refused) : array();

    // A part read whole that is too large or damaged: the workbook is not
    // drawn without it, its sheets missing or its dates shown as numbers.
    if ($refused !== '') {
        $zip->close();

        return array('ok' => false, 'error' => pg_office_refusal_error($refused), 'sheets' => array(), 'truncated' => false);
    }

    $strings_part = ($zip->locateName('xl/sharedStrings.xml') !== false) ? 'xl/sharedStrings.xml' : '';
    $workbook_xml = (string) pg_zip_entry_contents($zip, 'xl/workbook.xml', PG_OFFICE_MAX_DOM, $unread);
    $date1904 = function_exists('pg_office_xlsx_date1904') && pg_office_xlsx_date1904($workbook_xml);

    if (empty($sheets)) {
        $zip->close();

        return array('ok' => false, 'error' => lang('The file could not be read.'), 'sheets' => array(), 'truncated' => false);
    }

    $strings = pg_office_xlsx_strings($path, $strings_part);

    if (!is_array($strings)) {
        $zip->close();

        return array('ok' => false, 'error' => ($strings === null) ? lang('This file is too large to show here. Download it to open it.') : lang('The file could not be read.'), 'sheets' => array(), 'truncated' => false);
    }

    $cells_left = PG_OFFICE_MAX_CELLS;
    $out = array();
    $any_cut = false;
    $edit = $edit && function_exists('pg_office_xlsx_sheet_edit_info');
    $signed = $edit && pg_office_signed($zip);
    // A password to modify closes the whole workbook, whatever its sheets.
    $reserved = $edit && !$signed && pg_office_xlsx_write_reserved($workbook_xml);
    $styles = $edit ? pg_office_xlsx_style_kinds($zip) : array();

    foreach ($sheets as $sheet) {
        $cut = false;
        $rows = ($cells_left > 0) ? pg_office_xlsx_rows($path, $sheet['part'], $strings, $date_styles, $cells_left, $cut, $date1904) : array();

        if ($cells_left <= 0) {
            $cut = true;
        }

        $any_cut = $any_cut || $cut;
        $entry = array('name' => $sheet['name'], 'hidden' => $sheet['hidden'], 'rows' => $rows, 'truncated' => $cut);

        if ($edit) {
            $last_row = empty($rows) ? 0 : (int) $rows[count($rows) - 1]['n'];
            if ($signed || $reserved) {
                $entry['edit'] = array('ok' => false, 'why' => $signed ? 'signed' : 'password');
            } else {
                $entry['edit'] = pg_office_xlsx_sheet_edit_info($zip, $sheet, $last_row, $styles);
            }
        }

        $out[] = $entry;
    }

    $zip->close();

    $book = array('ok' => true, 'error' => '', 'sheets' => $out, 'truncated' => $any_cut);

    if ($edit) {
        $book['version'] = $version;
    }

    return $book;
}

/* ---------------------------------------------------------------------------
   Documents
   --------------------------------------------------------------------------- */

/**
 * The state one document conversion carries: the package, its
 * relationships, the list formats, the pictures inlined so far and the size
 * of what has been written.
 *
 * @param ZipArchive $zip
 * @param string     $refused set to large or damaged when the list, style or relationship part could not be read whole
 * @return array
 */
function pg_office_docx_state($zip, &$refused = null)
{
    $lists = array();
    $abstract = array();
    $refused = '';
    $numbering = pg_office_xml($zip, 'word/numbering.xml', $status);
    pg_office_refuse($refused, $status);

    // numId -> abstractNumId -> per level: bullet or numbered.
    if ($numbering !== null) {
        foreach ($numbering->getElementsByTagNameNS('*', 'abstractNum') as $node) {
            $id = (string) pg_office_attr($node, 'abstractNumId');
            $levels = array();

            foreach ($node->getElementsByTagNameNS('*', 'lvl') as $level) {
                $format = '';

                foreach ($level->getElementsByTagNameNS('*', 'numFmt') as $fmt) {
                    $format = (string) pg_office_attr($fmt, 'val');
                }

                $levels[(int) pg_office_attr($level, 'ilvl')] = ($format === 'bullet') ? 'ul' : 'ol';
            }

            $abstract[$id] = $levels;
        }

        foreach ($numbering->getElementsByTagNameNS('*', 'num') as $node) {
            $id = (string) pg_office_attr($node, 'numId');

            foreach ($node->getElementsByTagNameNS('*', 'abstractNumId') as $ref) {
                $lists[$id] = $abstract[(string) pg_office_attr($ref, 'val')] ?? array();
            }
        }
    }

    // A style that is itself a list (List Bullet, List Number): paragraphs
    // in it carry no numbering of their own.
    $styles = array();
    $style_sheet = pg_office_xml($zip, 'word/styles.xml', $status);
    pg_office_refuse($refused, $status);

    if ($style_sheet !== null) {
        foreach ($style_sheet->getElementsByTagNameNS('*', 'style') as $style) {
            $id = pg_office_attr($style, 'styleId');

            if ($id === '') {
                continue;
            }

            $entry = array('list' => '', 'level' => 0);

            foreach ($style->getElementsByTagNameNS('*', 'numPr') as $num) {
                foreach ($num->getElementsByTagNameNS('*', 'numId') as $num_id) {
                    $entry['list'] = pg_office_attr($num_id, 'val');
                }

                foreach ($num->getElementsByTagNameNS('*', 'ilvl') as $level) {
                    $entry['level'] = min(8, max(0, (int) pg_office_attr($level, 'val')));
                }
            }

            if ($entry['list'] !== '') {
                $styles[$id] = $entry;
            }
        }
    }

    $rels = pg_office_rels($zip, 'word/_rels/document.xml.rels', $rels_refused);
    pg_office_refuse($refused, $rels_refused);

    return array(
        'zip'         => $zip,
        'rels'        => $rels,
        'lists'       => $lists,
        'styles'      => $styles,
        'images'      => 0,
        'images_lost' => 0,
        'size'        => 0,
        'cut'         => false,
    );
}

/**
 * A picture of the document as a data: URL, or '' when it is too large,
 * missing, or not of a kind a browser shows.
 *
 * @param array  $state
 * @param string $rel_id
 * @return string
 */
function pg_office_docx_image(&$state, $rel_id)
{
    $rel = $state['rels'][$rel_id] ?? null;

    if (($rel === null) || $rel['external']) {
        return '';
    }

    $part = pg_office_part_name('word/', $rel['target']);
    $extension = strtolower((string) pathinfo($part, PATHINFO_EXTENSION));
    $mimes = array('png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'bmp' => 'image/bmp', 'webp' => 'image/webp');

    if (!isset($mimes[$extension])) {
        $state['images_lost']++;

        return '';
    }

    $stat = $state['zip']->statName($part);

    if (($stat === false) || ((int) $stat['size'] > PG_OFFICE_MAX_IMAGE) || (($state['images'] + (int) $stat['size']) > PG_OFFICE_MAX_IMAGES)) {
        $state['images_lost']++;

        return '';
    }

    $bytes = pg_zip_entry_contents($state['zip'], $part, PG_OFFICE_MAX_IMAGE, $unread);

    if (($bytes === null) || ($bytes === '') || (@getimagesizefromstring($bytes) === false)) {
        $state['images_lost']++;

        return '';
    }

    $state['images'] += strlen($bytes);

    return 'data:' . $mimes[$extension] . ';base64,' . base64_encode($bytes);
}

/**
 * The text of one run, escaped, with its bold, italic, underline, strike,
 * superscript and subscript as tags; breaks and tabs inside it.
 *
 * @param array      $state
 * @param DOMElement $run
 * @return string
 */
function pg_office_docx_run(&$state, $run)
{
    $html = '';
    $bold = $italic = $underline = $strike = false;
    $vertical = '';

    foreach ($run->childNodes as $child) {
        if (!($child instanceof DOMElement)) {
            continue;
        }

        switch ($child->localName) {
            case 'rPr':
                foreach ($child->childNodes as $property) {
                    if (!($property instanceof DOMElement)) {
                        continue;
                    }

                    $val = strtolower((string) pg_office_attr($property, 'val'));
                    $on = !in_array($val, array('0', 'false', 'none'), true);

                    switch ($property->localName) {
                        case 'b':
                            $bold = $on;
                            break;
                        case 'i':
                            $italic = $on;
                            break;
                        case 'u':
                            $underline = $on;
                            break;
                        case 'strike':
                        case 'dstrike':
                            $strike = $on;
                            break;
                        case 'vertAlign':
                            $vertical = ($val === 'superscript') ? 'sup' : (($val === 'subscript') ? 'sub' : '');
                            break;
                    }
                }
                break;

            case 't':
            case 'delText':
                if ($child->localName === 't') {
                    $html .= h($child->textContent);
                }
                break;

            case 'tab':
                $html .= '<span class="office-tabchar"> </span>';
                break;

            case 'br':
                $html .= (strtolower((string) pg_office_attr($child, 'type')) === 'page') ? '<hr class="office-page">' : '<br>';
                break;

            case 'cr':
                $html .= '<br>';
                break;

            case 'noBreakHyphen':
                $html .= '&#8209;';
                break;

            case 'softHyphen':
                $html .= '&shy;';
                break;

            case 'sym':
                $html .= '&#x25A1;';
                break;

            case 'drawing':
            case 'pict':
            case 'object':
                $html .= pg_office_docx_picture($state, $child);
                break;
        }
    }

    if ($html === '') {
        return '';
    }

    if ($bold) {
        $html = '<strong>' . $html . '</strong>';
    }

    if ($italic) {
        $html = '<em>' . $html . '</em>';
    }

    if ($underline) {
        $html = '<u>' . $html . '</u>';
    }

    if ($strike) {
        $html = '<s>' . $html . '</s>';
    }

    if ($vertical !== '') {
        $html = '<' . $vertical . '>' . $html . '</' . $vertical . '>';
    }

    return $html;
}

/**
 * A drawing, a legacy picture or an embedded object as an <img>, when it
 * holds a picture the browser can show; a placeholder word otherwise.
 *
 * @param array      $state
 * @param DOMElement $node
 * @return string
 */
function pg_office_docx_picture(&$state, $node)
{
    $rel_id = '';

    // DrawingML: a:blip r:embed. VML: v:imagedata r:id.
    foreach ($node->getElementsByTagNameNS('*', 'blip') as $blip) {
        $rel_id = pg_office_attr($blip, 'embed');
    }

    if ($rel_id === '') {
        foreach ($node->getElementsByTagNameNS('*', 'imagedata') as $image) {
            $rel_id = pg_office_attr($image, 'id');
        }
    }

    if ($rel_id === '') {
        return '';
    }

    $src = pg_office_docx_image($state, $rel_id);

    if ($src === '') {
        return '<span class="office-lost">[' . h(lang('picture')) . ']</span>';
    }

    $alt = '';

    foreach ($node->getElementsByTagNameNS('*', 'docPr') as $pr) {
        $alt = (string) $pr->getAttribute('descr');
    }

    return '<img class="office-img" src="' . $src . '" alt="' . h($alt) . '">';
}

/**
 * The inline content of a paragraph or a hyperlink: its runs, and the
 * containers runs sit in (hyperlinks, tracked insertions, smart tags,
 * fields, content controls).
 *
 * @param array   $state
 * @param DOMNode $parent
 * @return string
 */
function pg_office_docx_inline(&$state, $parent)
{
    $html = '';

    foreach ($parent->childNodes as $child) {
        if (!($child instanceof DOMElement)) {
            continue;
        }

        switch ($child->localName) {
            case 'r':
                $html .= pg_office_docx_run($state, $child);
                break;

            case 'hyperlink':
                $inner = pg_office_docx_inline($state, $child);
                $rel_id = pg_office_attr($child, 'id');
                $rel = $state['rels'][$rel_id] ?? null;
                $href = ($rel && $rel['external']) ? (string) $rel['target'] : '';

                if (($href !== '') && preg_match('#^(https?://|mailto:)#i', $href)) {
                    $html .= '<a href="' . h($href) . '" target="_blank" rel="noopener noreferrer">' . $inner . '</a>';
                } else {
                    $html .= $inner;
                }
                break;

            case 'ins':
            case 'smartTag':
            case 'fldSimple':
            case 'sdt':
            case 'sdtContent':
            case 'customXml':
            case 'moveTo':
                $html .= pg_office_docx_inline($state, $child);
                break;

            case 'del':
            case 'moveFrom':
            case 'proofErr':
            case 'bookmarkStart':
            case 'bookmarkEnd':
            case 'pPr':
                break;
        }
    }

    return $html;
}

/**
 * What a paragraph's properties say: its style id, its list (id and level),
 * its alignment, and whether it is a page break.
 *
 * @param DOMElement $paragraph
 * @return array style, list, level, align
 */
function pg_office_docx_paragraph_props($paragraph)
{
    $out = array('style' => '', 'list' => '', 'level' => 0, 'align' => '');

    foreach ($paragraph->getElementsByTagNameNS('*', 'pPr') as $props) {
        if ($props->parentNode !== $paragraph) {
            continue;
        }

        foreach ($props->childNodes as $property) {
            if (!($property instanceof DOMElement)) {
                continue;
            }

            switch ($property->localName) {
                case 'pStyle':
                    $out['style'] = (string) pg_office_attr($property, 'val');
                    break;

                case 'numPr':
                    foreach ($property->getElementsByTagNameNS('*', 'numId') as $id) {
                        $out['list'] = (string) pg_office_attr($id, 'val');
                    }

                    foreach ($property->getElementsByTagNameNS('*', 'ilvl') as $level) {
                        $out['level'] = min(8, max(0, (int) pg_office_attr($level, 'val')));
                    }
                    break;

                case 'jc':
                    $align = strtolower((string) pg_office_attr($property, 'val'));
                    $out['align'] = in_array($align, array('center', 'right', 'both', 'end'), true) ? (($align === 'both') ? 'justify' : (($align === 'end') ? 'right' : $align)) : '';
                    break;
            }
        }
    }

    return $out;
}

/**
 * The tag a paragraph style maps to: headings by their level, the title,
 * a quote; a plain paragraph otherwise.
 *
 * @param string $style
 * @return string
 */
function pg_office_docx_block_tag($style)
{
    $style = strtolower((string) $style);

    if (preg_match('/^(heading|balk|titre|berschrift|titulo|ttulo)\s*([1-6])$/u', $style, $match)) {
        return 'h' . $match[2];
    }

    if (preg_match('/^heading([1-6])$/', $style, $match)) {
        return 'h' . $match[1];
    }

    if (($style === 'title') || ($style === 'balk')) {
        return 'h1';
    }

    if ($style === 'subtitle') {
        return 'h2';
    }

    if (in_array($style, array('quote', 'intensequote', 'blockquote'), true)) {
        return 'blockquote';
    }

    return 'p';
}

/**
 * The children of a table or a row with the given local name (tr, tc),
 * including those inside content controls and custom XML: a repeating
 * section wraps whole rows in sdt/sdtContent, and a control or custom XML
 * element can wrap a single cell.
 *
 * @param DOMNode $parent
 * @param string  $name
 * @return DOMElement[]
 */
function pg_office_docx_wrapped($parent, $name)
{
    $found = array();

    foreach ($parent->childNodes as $child) {
        if (!($child instanceof DOMElement)) {
            continue;
        }

        if ($child->localName === $name) {
            $found[] = $child;
        } elseif (in_array($child->localName, array('sdt', 'sdtContent', 'customXml'), true)) {
            foreach (pg_office_docx_wrapped($child, $name) as $inner) {
                $found[] = $inner;
            }
        }
    }

    return $found;
}

/**
 * The blocks of a container (the body, a table cell): paragraphs, lists
 * and tables, written to $html until the size limit is met.
 *
 * @param array   $state
 * @param DOMNode $container
 * @param string  $html
 */
function pg_office_docx_blocks(&$state, $container, &$html)
{
    $list_open = '';
    $list_id = '';

    $close_list = function () use (&$html, &$list_open, &$list_id) {
        if ($list_open !== '') {
            $html .= '</li></' . $list_open . '>';
            $list_open = '';
            $list_id = '';
        }
    };

    foreach ($container->childNodes as $child) {
        if ($state['cut']) {
            break;
        }

        if (strlen($html) > PG_OFFICE_MAX_HTML) {
            $state['cut'] = true;
            break;
        }

        if (!($child instanceof DOMElement)) {
            continue;
        }

        switch ($child->localName) {
            case 'p':
                $props = pg_office_docx_paragraph_props($child);
                $inner = pg_office_docx_inline($state, $child);
                $mark = pg_office_docx_edit_mark($state, $child);

                if (($props['list'] === '') && isset($state['styles'][$props['style']])) {
                    $props['list'] = $state['styles'][$props['style']]['list'];
                    $props['level'] = $state['styles'][$props['style']]['level'];
                }

                // numId 0 is "no numbering" (ECMA-376 17.9.18): on the
                // paragraph it also takes off its style's numbering.
                if ($props['list'] === '0') {
                    $props['list'] = '';
                }

                if ($props['list'] !== '') {
                    $format = $state['lists'][$props['list']][$props['level']] ?? 'ul';

                    // Another numbering is another list.
                    if (($list_open !== '') && ($list_id !== $props['list'])) {
                        $close_list();
                    }

                    if ($list_open === '') {
                        $list_open = $format;
                        $list_id = $props['list'];
                        $html .= '<' . $format . ' class="office-list"><li class="office-lvl-' . (int) $props['level'] . '"' . $mark . '>';
                    } else {
                        $html .= '</li><li class="office-lvl-' . (int) $props['level'] . '"' . $mark . '>';
                    }

                    $html .= ($inner === '') ? '&nbsp;' : $inner;
                    break;
                }

                $close_list();

                $tag = pg_office_docx_block_tag($props['style']);
                $style = ($props['align'] !== '') ? ' style="text-align:' . $props['align'] . '"' : '';

                $html .= '<' . $tag . $style . $mark . '>' . (($inner === '') ? '&nbsp;' : $inner) . '</' . $tag . '>';
                break;

            case 'tbl':
                $close_list();
                $html .= '<table class="office-table">';

                foreach (pg_office_docx_wrapped($child, 'tr') as $row) {
                    $html .= '<tr>';

                    foreach (pg_office_docx_wrapped($row, 'tc') as $cell) {
                        $span = 1;

                        foreach ($cell->getElementsByTagNameNS('*', 'gridSpan') as $grid) {
                            if ($grid->parentNode && $grid->parentNode->parentNode === $cell) {
                                $span = max(1, (int) pg_office_attr($grid, 'val'));
                            }
                        }

                        $inner = '';
                        pg_office_docx_blocks($state, $cell, $inner);
                        $html .= '<td' . (($span > 1) ? ' colspan="' . $span . '"' : '') . '>' . $inner . '</td>';
                    }

                    $html .= '</tr>';
                }

                $html .= '</table>';
                break;

            case 'sdt':
            case 'sdtContent':
            case 'customXml':
                $close_list();
                pg_office_docx_blocks($state, $child, $html);
                break;

            case 'sectPr':
            case 'tcPr':
            case 'tblPr':
            case 'tblGrid':
                break;
        }
    }

    $close_list();
}

/**
 * The attributes a drawn paragraph carries when the document is read for
 * editing: its position among the document's paragraphs (data-p), and why
 * it cannot be changed (data-lock) when it cannot; the text of one that can
 * goes to the preview beside the HTML. Nothing when not editing.
 *
 * @param array      $state
 * @param DOMElement $paragraph
 * @return string
 */
function pg_office_docx_edit_mark(&$state, $paragraph)
{
    if (!isset($state['edit'])) {
        return '';
    }

    $path = $paragraph->getNodePath();

    if (!isset($state['edit']['index'][$path])) {
        return '';
    }

    $position = (int) $state['edit']['index'][$path];
    $edit = pg_office_docx_paragraph_edit($paragraph);

    if ($edit['lock'] !== '') {
        return ' data-p="' . $position . '" data-lock="' . h($edit['lock']) . '"';
    }

    $state['edit']['texts'][(string) $position] = $edit['text'];

    return ' data-p="' . $position . '"';
}

/**
 * A document as the preview draws it: a safe subset of HTML (headings,
 * paragraphs, lists, tables, bold and the like, links, inlined pictures),
 * made here from the package alone, with nothing the file wrote passing
 * through unescaped.
 *
 * Asked for editing, the paragraphs drawn carry their position (and the
 * reason when one cannot change), the answer carries the texts of those that
 * can and the version a save hands back (includes/fn/office_edit.php), or
 * says why the document cannot be changed at all.
 *
 * @param string $path
 * @param bool   $edit
 * @return array ok, error, html, truncated, images_lost[, edit, version]
 */
function pg_office_docx_read($path, $edit = false)
{
    // As for a workbook, the version is taken before anything is read.
    $version = ($edit && function_exists('pg_office_version')) ? pg_office_version($path) : '';
    $zip = pg_office_open($path, $error);

    if ($zip === null) {
        return array('ok' => false, 'error' => $error, 'html' => '', 'truncated' => false, 'images_lost' => 0);
    }

    // A main part past PG_OFFICE_MAX_DOM is read from its beginning: the
    // preview shows a document's beginning in any case.
    $document = pg_office_xml($zip, 'word/document.xml', $status, true);

    if ($document === null) {
        $zip->close();

        return array('ok' => false, 'error' => lang('The file could not be read.'), 'html' => '', 'truncated' => false, 'images_lost' => 0);
    }

    $cut = ($status === 'cut');
    $state = pg_office_docx_state($zip, $refused);

    if ($refused !== '') {
        $zip->close();

        return array('ok' => false, 'error' => pg_office_refusal_error($refused), 'html' => '', 'truncated' => false, 'images_lost' => 0);
    }

    $html = '';
    $edit = $edit && function_exists('pg_office_docx_edit_refusal');
    $refusal = $edit ? pg_office_docx_edit_refusal($zip) : '';

    // A document read only in part has no whole to save a change into.
    if ($edit && $cut && ($refusal === '')) {
        $refusal = 'large';
    }

    if ($edit && ($refusal === '')) {
        $state['edit'] = array('index' => array(), 'texts' => array());

        foreach (pg_office_docx_paragraphs($document) as $position => $paragraph) {
            $state['edit']['index'][$paragraph->getNodePath()] = $position;
        }
    }

    foreach ($document->getElementsByTagNameNS('*', 'body') as $body) {
        pg_office_docx_blocks($state, $body, $html);
        break;
    }

    $zip->close();

    $out = array('ok' => true, 'error' => '', 'html' => $html, 'truncated' => $state['cut'] || $cut, 'images_lost' => $state['images_lost']);

    if ($edit) {
        $out['edit'] = ($refusal !== '')
            ? array('ok' => false, 'why' => $refusal)
            : array('ok' => true, 'why' => '', 'paragraphs' => (object) $state['edit']['texts']);
        $out['version'] = $version;
    }

    return $out;
}

/**
 * The script tag of the preview's drawing code (assets/js/pg_office_view.js),
 * for the screens that load it on first use, stamped with the file's time.
 *
 * @return string
 */
function pg_office_view_includes()
{
    $file = '/assets/js/pg_office_view.js';

    return '<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . $file . '?v=' . (int) @filemtime(PG_FUNCTIONS_DIR . $file) . '"></script>';
}

/**
 * The words the preview's drawing code needs, translated: the preview's own,
 * those of a workbook offered for editing (why a cell or a sheet is not
 * offered, the hint, the count of changes) and those of a document (why a
 * paragraph or the whole document is not offered, the hint, the count).
 *
 * @return array
 */
function pg_office_view_strings()
{
    return array(
        'hidden'            => lang('Hidden sheet'),
        'sheets'            => lang('Sheets'),
        'changed_mark'      => lang('Changed'),
        'truncated'         => lang('Only the beginning is shown. Download the file to see all of it.'),
        'images_lost'       => lang('{1} picture(s) could not be shown.'),
        'empty'             => lang('The sheet is empty.'),
        'calc'              => lang('Worked out by a formula when the file is opened in a spreadsheet program.'),
        'edit_hint'         => lang('Double-click a cell, or select it and type, to change it. Enter keeps the change, Esc undoes it.'),
        'changed'           => lang('{1} cell(s) changed.'),
        'recalc'            => lang('Formulas are worked out again when the file is opened in a spreadsheet program.'),
        'formula_text'      => lang('Kept as text: formulas are not worked out here.'),
        'locked_formula'    => lang('Worked out by a formula. Change it in a spreadsheet program.'),
        'locked_formula_is' => lang('Worked out by a formula: {1}'),
        'locked_value'      => lang('A linked value or a picture in the cell. Change it in a spreadsheet program.'),
        'locked_merged'     => lang('Hidden under merged cells. Change the first cell of the area.'),
        'locked_table'      => lang('A header or totals row of a table. Change it in a spreadsheet program.'),
        'locked_pivot'      => lang('Part of a pivot table. Change it in a spreadsheet program.'),
        'locked_array'      => lang('Part of an array formula. Change it in a spreadsheet program.'),
        'locked_protected'  => lang('The sheet is protected and this cell is locked.'),
        'sheet_protected'   => lang('The sheet is protected: only its unlocked cells can be changed.'),
        'sheet_signed'      => lang('The file is digitally signed, so its cells are not changed here.'),
        'sheet_password'    => lang('The file has a password to change it, so its cells are not changed here.'),
        'sheet_kind'        => lang('This sheet has no cells to change.'),
        'sheet_large'       => lang('This sheet is too large to change here.'),
        'sheet_unusual'     => lang('This sheet cannot be changed here.'),
        'doc_hint'          => lang('Click a paragraph, then click it again (or press Enter) to change its text. Enter keeps the change, Shift+Enter starts a new line, Esc undoes it.'),
        'doc_changed'       => lang('{1} paragraph(s) changed.'),
        'doc_format'        => lang('Changed paragraphs show their formatting again once saved.'),
        'doc_editor'        => lang('Text of the paragraph'),
        'doc_link'          => lang('Hold Ctrl (Cmd on a Mac) and click to follow the link.'),
        'doc_signed'        => lang('The file is digitally signed, so its text is not changed here.'),
        'doc_protected'     => lang('The document is protected against changes.'),
        'doc_tracking'      => lang('Track Changes is on for this document. Change it in a word processor, so that the changes are tracked.'),
        'doc_large'         => lang('This document is too large to change here.'),
        'doc_unusual'       => lang('This document cannot be changed here.'),
        'para_tracked'      => lang('This paragraph has tracked changes. Change it in a word processor.'),
        'para_field'        => lang('This paragraph has a field, such as a page number, a date or a table of contents. Change it in a word processor.'),
        'para_comment'      => lang('This paragraph has a comment. Change it in a word processor.'),
        'para_note'         => lang('This paragraph has a footnote or an endnote. Change it in a word processor.'),
        'para_control'      => lang('This paragraph is part of a content control or a form. Change it in a word processor.'),
        'para_object'       => lang('This paragraph has a picture, a shape, an equation or a symbol. Change it in a word processor.'),
        'para_break'        => lang('This paragraph has a page or column break. Change it in a word processor.'),
        'para_other'        => lang('This paragraph holds more than text. Change it in a word processor.'),
    );
}

/**
 * Whichever kind the file is, read for the preview.
 *
 * @param string $path
 * @param string $name the file's name (for the kind)
 * @param bool   $edit a workbook or a document also says what may be changed in it
 * @return array ok, error, kind, and the kind's own fields
 */
function pg_office_read($path, $name, $edit = false)
{
    $kind = pg_office_kind($name);

    if ($kind === 'xlsx') {
        return array('kind' => 'xlsx') + pg_office_xlsx_read($path, $edit);
    }

    if ($kind === 'docx') {
        return array('kind' => 'docx') + pg_office_docx_read($path, $edit);
    }

    return array('ok' => false, 'error' => lang('There is no preview for this kind of file.'), 'kind' => '');
}
