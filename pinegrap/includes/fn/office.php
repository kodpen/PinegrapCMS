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
 * offer. A .csv or .tsv is read as one sheet of the same rows, its separator
 * and character set guessed, and is only looked at. An .ods is read as a
 * workbook of the same rows through the importers' sheet reader
 * (includes/fn/sheet_read.php), and is only looked at too; so is an .xls,
 * which may be saved as a copy in .xlsx (pg_office_xls_copy()), written by
 * the shared writer (includes/fn/xlsx_write.php), to be changed there. A
 * .pptx is read as a list of its slides' titles, text, pictures and speaker
 * notes, and is only looked at.
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
 * How much of a .csv or .tsv the preview reads, from its beginning, in
 * bytes. The rows past it are not read: the sheet shows the whole records
 * within it and says it was cut, as it does past the row and cell limits,
 * which an ordinary file reaches long before.
 */
define('PG_OFFICE_MAX_CSV', 8 * 1024 * 1024);

/**
 * How much of an .ods file's content.xml the preview walks, its sheets
 * together, in bytes. The sheet reader walks the part from its beginning
 * for each sheet, so the sheets are read while their count times the
 * part's size stays within this (8 of an 8 MB part, 64 of a 1 MB one), and
 * a part of thousands of small sheets is not walked thousands of times.
 */
define('PG_OFFICE_MAX_ODS_WALK', 64 * 1024 * 1024);

/**
 * The most sheets an .ods may have to be shown. A sheet's tab is a few bytes
 * of content.xml, so the part's limit alone lets one ask for half a million.
 */
define('PG_OFFICE_MAX_ODS_SHEETS', 1000);

/**
 * The largest .xls file shown or copied, in bytes. The reader holds the
 * whole file, and a sheet's cells as the library's arrays, in memory.
 */
define('PG_OFFICE_MAX_XLS', 8 * 1024 * 1024);

/**
 * How much of an .xls file the preview parses, its sheets together, in
 * bytes: the reader parses the whole file again for each sheet, so sheets
 * are read while their count times the file's size stays within this.
 */
define('PG_OFFICE_MAX_XLS_WALK', 64 * 1024 * 1024);

/**
 * The same for a copy as .xlsx, which takes every sheet whole; a workbook
 * past it is refused rather than copied in part.
 */
define('PG_OFFICE_MAX_XLS_COPY_WALK', 256 * 1024 * 1024);

/**
 * The most slides a presentation's preview lists; the rest are left out and
 * the list says it was cut. Each slide is a part built into a DOM in turn.
 */
define('PG_OFFICE_MAX_SLIDES', 500);

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
 * The kind of preview a name gets: a Word or Excel kind (pg_office_kind()),
 * or 'csv' for a .csv or .tsv, drawn as a sheet and only looked at. The
 * edits ask pg_office_kind(), which leaves the two out, so neither is ever
 * offered for editing here; a channel and the file window still open them
 * as text. An .ods ('ods') or .xls ('xls') is drawn as a workbook and only
 * looked at too, as is a presentation ('pptx': its show, macro and template
 * forms too), drawn as a list of slides.
 *
 * @param string $name
 * @return string docx | xlsx | csv | ods | xls | pptx | ''
 */
function pg_office_preview_kind($name)
{
    $kind = pg_office_kind($name);

    if ($kind !== '') {
        return $kind;
    }

    $extension = strtolower((string) pathinfo((string) $name, PATHINFO_EXTENSION));

    if (($extension === 'ods') || ($extension === 'xls')) {
        return $extension;
    }

    if (in_array($extension, array('pptx', 'pptm', 'ppsx', 'potx'), true)) {
        return 'pptx';
    }

    return in_array($extension, array('csv', 'tsv'), true) ? 'csv' : '';
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
 * @param string $error  why not, when null comes back
 * @param string $marker the part every package of the kind holds: [Content_Types].xml for Word and Excel, content.xml for an .ods
 * @return ZipArchive|null
 */
function pg_office_open($path, &$error, $marker = '[Content_Types].xml')
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

    if ($zip->locateName((string) $marker) === false) {
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
 * change). The workbook part is the one the package names
 * (pg_sheet_xlsx_workbook()).
 *
 * @param ZipArchive $zip
 * @param string     $refused set to large or damaged when the workbook part or its relationships could not be read whole
 * @return array[]
 */
function pg_office_xlsx_sheets($zip, &$refused = null)
{
    $out = array();
    $refused = '';
    $workbook = pg_sheet_xlsx_workbook($zip);
    $book = pg_office_xml($zip, $workbook['part'], $status);
    pg_office_refuse($refused, $status);

    if ($book === null) {
        return $out;
    }

    $rels = pg_office_rels($zip, $workbook['rels'], $rels_refused);
    pg_office_refuse($refused, $rels_refused);

    foreach ($book->getElementsByTagName('sheet') as $sheet) {
        $id = pg_office_attr($sheet, 'id');

        if (($id === '') || !isset($rels[$id])) {
            continue;
        }

        $part = pg_office_part_name($workbook['folder'], $rels[$id]['target']);

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
 * node by node (pg_office_reader_text()). Unlimited, as the sheet reader of
 * the importers asks (includes/fn/sheet_read.php), the table has no count or
 * byte limit, only the declared size.
 *
 * @param string $path    the package on disk
 * @param string $part    '' when the workbook has none
 * @param bool   $limited held to the preview's limits
 * @return string[]|false|null false when damaged, null when too large
 */
function pg_office_xlsx_strings($path, $part, $limited = true)
{
    $strings = array();

    if ($part === '') {
        return $strings;
    }

    $size = pg_zip_entry_size($path, $part);

    if ($size < 0) {
        return $strings;
    }

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
            if (!$limited) {
                $strings[] = pg_office_reader_text($reader, PHP_INT_MAX);
                continue;
            }

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
 * Asked for them, it also gives what each style looks like
 * (pg_office_xlsx_style_looks()), from the same read of the part.
 *
 * @param ZipArchive $zip
 * @param string     $refused set to large or damaged when the style part could not be read whole
 * @param array|null $looks   set to pg_office_xlsx_style_looks() when not null
 * @return array style index => pg_office_xlsx_format_kind() (date, time, time_s, hours, hours_s, minutes)
 */
function pg_office_xlsx_date_styles($zip, &$refused = null, &$looks = null)
{
    $want_looks = is_array($looks);
    $looks = array();
    $dates = array();
    $refused = '';
    // Where the workbook's relationships name it (xl/Styles2.xml from some
    // writers), as Excel and PHPExcel find it; none at all is no dates.
    $part = pg_sheet_xlsx_part($zip, 'styles', 'xl/styles.xml');

    if ($part === '') {
        return $dates;
    }

    $styles = pg_office_xml($zip, $part, $status);
    pg_office_refuse($refused, $status);

    if ($styles === null) {
        return $dates;
    }

    if ($want_looks) {
        $looks = pg_office_xlsx_style_looks($styles);
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
 * What each cell format of a styles part looks like in the preview, and what
 * the format controls show as chosen: bold (b), italic (i), underline (u),
 * strikethrough (st), a font size other than the default font's (sz, in
 * points), a text colour (c) and a solid fill (bg) given as RRGGBB, a
 * horizontal alignment (a: left, center, right), wrap text (w), the border
 * sides drawn (bd: l, r, t, b in that order) and the kind of number format (n: number, money, percent,
 * date, or other for one the controls do not offer; general is left out).
 * A number, money or percent also carries its decimals (d) and grouping
 * (g), money its symbol (y) and its place (p: before or after).
 *
 * Only colours written as RGB are drawn: a theme or palette colour needs the
 * workbook's theme, which is not read, and the cell keeps the page's look.
 * A style that looks like nothing is left out.
 *
 * @param DOMDocument $styles the styles part
 * @return array cell format index => look
 */
function pg_office_xlsx_style_looks($styles)
{
    $list = function ($parent_name, $child_name) use ($styles) {
        $out = array();

        foreach ($styles->getElementsByTagNameNS('*', $parent_name) as $parent) {
            foreach ($parent->childNodes as $child) {
                if (($child->nodeType === XML_ELEMENT_NODE) && ($child->localName === $child_name)) {
                    $out[] = $child;
                }
            }

            break;
        }

        return $out;
    };

    // The first direct child of this name.
    $child = function ($node, $local) {
        foreach ($node->childNodes as $item) {
            if (($item->nodeType === XML_ELEMENT_NODE) && ($item->localName === $local)) {
                return $item;
            }
        }

        return null;
    };

    // An ARGB colour as RRGGBB; anything else (theme, indexed, auto) as ''.
    $rgb = function ($node) {
        $value = ($node === null) ? '' : strtoupper(pg_office_attr($node, 'rgb'));

        return preg_match('/^(?:[0-9A-F]{2})?([0-9A-F]{6})$/', $value, $match) ? $match[1] : '';
    };

    // On unless val says 0 or false (ECMA-376 18.8.2 b, 18.8.26 i).
    $on = function ($node) {
        return ($node !== null) && !in_array(strtolower(pg_office_attr($node, 'val')), array('0', 'false'), true);
    };

    $fonts = array();
    $base_size = null;

    foreach ($list('fonts', 'font') as $index => $font) {
        $look = array();

        if ($on($child($font, 'b'))) {
            $look['b'] = 1;
        }

        if ($on($child($font, 'i'))) {
            $look['i'] = 1;
        }

        $underline = $child($font, 'u');

        if ($on($underline) && (strtolower(pg_office_attr($underline, 'val')) !== 'none')) {
            $look['u'] = 1;
        }

        if ($on($child($font, 'strike'))) {
            $look['st'] = 1;
        }

        // A size of its own: one the first font, the workbook's default,
        // does not have.
        $size = $child($font, 'sz');
        $size = (($size !== null) && is_numeric(pg_office_attr($size, 'val'))) ? (float) pg_office_attr($size, 'val') : null;

        if ($index === 0) {
            $base_size = $size;
        } elseif (($size !== null) && ($size !== $base_size) && ($size > 0) && ($size <= 409)) {
            $look['sz'] = (floor($size) === $size) ? (int) $size : $size;
        }

        $color = $rgb($child($font, 'color'));

        if ($color !== '') {
            $look['c'] = $color;
        }

        $fonts[] = $look;
    }

    $fills = array();

    foreach ($list('fills', 'fill') as $fill) {
        $pattern = $child($fill, 'patternFill');
        $fills[] = (($pattern !== null) && (pg_office_attr($pattern, 'patternType') === 'solid')) ? $rgb($child($pattern, 'fgColor')) : '';
    }

    // The sides each border draws, as l, r, t and b.
    $borders = array();

    foreach ($list('borders', 'border') as $border) {
        $drawn = '';

        foreach (array('l' => array('left', 'start'), 'r' => array('right', 'end'), 't' => array('top'), 'b' => array('bottom')) as $letter => $names) {
            foreach ($names as $name) {
                $side = $child($border, $name);

                if ($side !== null) {
                    $style = pg_office_attr($side, 'style');
                    $drawn .= (($style !== '') && ($style !== 'none')) ? $letter : '';
                    break;
                }
            }
        }

        $borders[] = $drawn;
    }

    $codes = array();

    foreach ($list('numFmts', 'numFmt') as $format) {
        $codes[(int) pg_office_attr($format, 'numFmtId')] = pg_office_attr($format, 'formatCode');
    }

    $aligns = array('left' => 'left', 'center' => 'center', 'centerContinuous' => 'center', 'right' => 'right');
    $looks = array();

    foreach ($list('cellXfs', 'xf') as $index => $xf) {
        $font_id = (int) pg_office_attr($xf, 'fontId');
        $fill_id = (int) pg_office_attr($xf, 'fillId');
        $look = isset($fonts[$font_id]) ? $fonts[$font_id] : array();

        if (isset($fills[$fill_id]) && ($fills[$fill_id] !== '')) {
            $look['bg'] = $fills[$fill_id];
        }

        $alignment = $child($xf, 'alignment');
        $horizontal = ($alignment === null) ? '' : pg_office_attr($alignment, 'horizontal');

        if (isset($aligns[$horizontal])) {
            $look['a'] = $aligns[$horizontal];
        }

        if (($alignment !== null) && in_array(strtolower(pg_office_attr($alignment, 'wrapText')), array('1', 'true'), true)) {
            $look['w'] = 1;
        }

        $border_id = (int) pg_office_attr($xf, 'borderId');

        if (isset($borders[$border_id]) && ($borders[$border_id] !== '')) {
            $look['bd'] = $borders[$border_id];
        }

        $format_id = (int) pg_office_attr($xf, 'numFmtId');
        $look += pg_office_xlsx_number_look($format_id, isset($codes[$format_id]) ? $codes[$format_id] : null);

        if (!empty($look)) {
            $looks[$index] = $look;
        }
    }

    return $looks;
}

/**
 * The kind of a number format, as the format controls name it, with what
 * showing a number in it needs (pg_office_xlsx_style_looks() lists the
 * keys). General is an empty list.
 *
 * @param int         $id   numFmtId
 * @param string|null $code the workbook's own formatCode, or null for a built-in id
 * @return array
 */
function pg_office_xlsx_number_look($id, $code)
{
    // The built-in formats (ECMA-376 18.8.30) the controls offer; 5 to 8 and
    // 42 and 44 are the locale's currency, shown here without a symbol.
    $builtin = array(
        1 => array('number', 0, 0), 2 => array('number', 2, 0), 3 => array('number', 0, 1), 4 => array('number', 2, 1),
        9 => array('percent', 0, 0), 10 => array('percent', 2, 0),
        5 => array('money', 0, 1), 6 => array('money', 0, 1), 7 => array('money', 2, 1), 8 => array('money', 2, 1),
        37 => array('number', 0, 1), 38 => array('number', 0, 1), 39 => array('number', 2, 1), 40 => array('number', 2, 1),
        41 => array('number', 0, 1), 42 => array('money', 0, 1), 43 => array('number', 2, 1), 44 => array('money', 2, 1),
    );

    if ($code === null) {
        if ($id === 0) {
            return array();
        }

        if (isset($builtin[$id])) {
            $look = array('n' => $builtin[$id][0], 'd' => $builtin[$id][1], 'g' => $builtin[$id][2]);

            return ($look['n'] === 'money') ? $look + array('y' => '', 'p' => 'after') : $look;
        }

        return (($id >= 14) && ($id <= 17)) || ($id === 22) ? array('n' => 'date') : array('n' => 'other');
    }

    $kind = pg_office_xlsx_format_kind($code);

    if ($kind !== '') {
        return array('n' => ($kind === 'date') ? 'date' : 'other');
    }

    // The positive section only: quoted text and a bracketed currency are
    // kept apart before the format characters are looked at.
    $section = $code;

    if (preg_match('/^(?:"[^"]*"|\\\\.|\[[^\]]*\]|[^;"\\\\\[])*/u', $code, $match)) {
        $section = $match[0];
    }

    if (strtolower(trim($section)) === 'general') {
        return array();
    }

    $symbol = '';

    if (preg_match('/\[\$([^\]\-]*)/u', $section, $match)) {
        $symbol = $match[1];
    } elseif (preg_match('/"([^"]*)"/u', $section, $match)) {
        $symbol = trim($match[1]);
    }

    $bare = preg_replace('/"[^"]*"|\[[^\]]*\]|\\\\.|[_*]./u', '', $section);

    if (!preg_match('/[0#?]/', $bare)) {
        return array('n' => 'other');
    }

    if (($symbol === '') && preg_match('/[$€£¥₺]/u', $bare, $match)) {
        $symbol = $match[0];
    }

    $decimals = preg_match('/\.([0#?]+)/', $bare, $match) ? strlen($match[1]) : 0;
    $grouped = preg_match('/[0#?],[0#?]/', $bare) ? 1 : 0;

    if (strpos($bare, '%') !== false) {
        return array('n' => 'percent', 'd' => $decimals, 'g' => $grouped);
    }

    if ($symbol !== '') {
        $before = mb_strpos($section, $symbol, 0, 'UTF-8') < strcspn($section, '0#?');

        return array('n' => 'money', 'd' => $decimals, 'g' => $grouped, 'y' => $symbol, 'p' => $before ? 'before' : 'after');
    }

    return array('n' => 'number', 'd' => $decimals, 'g' => $grouped);
}

/**
 * A stored number as its number, money or percent format shows it, with the
 * site's decimal and thousands separators; '' when the look has none of
 * those formats or the value is not a number (the cell is shown as before).
 *
 * @param string $value
 * @param array  $look       pg_office_xlsx_style_looks()
 * @param array  $separators decimal, thousands (pg_number_separators())
 * @return string
 */
function pg_office_xlsx_shown_number($value, $look, $separators)
{
    $kind = isset($look['n']) ? $look['n'] : '';

    if (!in_array($kind, array('number', 'money', 'percent'), true) || !is_numeric($value)) {
        return '';
    }

    $number = (float) $value * (($kind === 'percent') ? 100 : 1);
    $decimals = min(10, max(0, (int) (isset($look['d']) ? $look['d'] : 0)));
    $text = number_format($number, $decimals, $separators['decimal'], empty($look['g']) ? '' : $separators['thousands']);

    if (preg_match('/^-0(?:\D0+)?$/', $text)) {
        $text = substr($text, 1);
    }

    if ($kind === 'percent') {
        return $text . '%';
    }

    $symbol = ($kind === 'money') ? (string) (isset($look['y']) ? $look['y'] : '') : '';

    if ($symbol === '') {
        return $text;
    }

    return (isset($look['p']) && ($look['p'] === 'before')) ? $symbol . $text : $text . ' ' . $symbol;
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
 * @param array  $looks       pg_office_xlsx_style_looks(): a row then says
 *                            which of its cells have a look (s, column =>
 *                            style) and how a number is shown in its format
 *                            (show, column => text), the value staying what
 *                            the editor changes
 * @param array|null $sizes   set to the sheet's column widths, default
 *                            sizes and frozen rows (pg_office_size_note());
 *                            a row with its own height says it (h, points)
 * @return array[]
 */
function pg_office_xlsx_rows($path, $part, $strings, $date_styles, &$cells_left, &$truncated, $date1904 = false, $looks = array(), &$sizes = null)
{
    $rows = array();
    $sizes = function_exists('pg_office_size_empty') ? pg_office_size_empty() : null;
    $in_data = false;
    $truncated = false;
    $separators = (!empty($looks) && function_exists('pg_number_separators')) ? pg_number_separators() : array('decimal' => '.', 'thousands' => ',');

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
            // The sheet's head before its rows: column widths, the default
            // sizes and the first view's frozen pane (pg_office_size_note()).
            if (($reader->nodeType === XMLReader::ELEMENT) && !$in_data && ($sizes !== null)) {
                if ($reader->localName === 'sheetData') {
                    $in_data = true;
                } elseif (in_array($reader->localName, array('col', 'sheetFormatPr', 'sheetView', 'pane'), true)) {
                    $attributes = array();

                    if ($reader->moveToFirstAttribute()) {
                        do {
                            $attributes[$reader->localName] = $reader->value;
                        } while ($reader->moveToNextAttribute());

                        $reader->moveToElement();
                    }

                    pg_office_size_note($sizes, $reader->localName, $attributes);
                }
            }

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
        $height = $reader->getAttribute('ht');
        $depth = $reader->depth;
        $has_cells = !$reader->isEmptyElement;
        $row = array();
        $waiting = array();
        $row_looks = array();
        $row_shown = array();
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
                            $shown = isset($looks[$style]) ? pg_office_xlsx_shown_number($value, $looks[$style], $separators) : '';
                            $value = pg_office_xlsx_number($value);

                            if (($shown !== '') && ($shown !== $value)) {
                                $row_shown[$column] = $shown;
                            }
                        }
                    }
            }

            if (isset($looks[$style])) {
                $row_looks[$column] = $style;
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

        // A row's own height in points (ht), as the grid draws it.
        if (is_numeric($height) && ((float) $height > 0)) {
            $entry['h'] = round((float) $height, 2);
        }

        if (!empty($waiting)) {
            $entry['calc'] = $waiting;
        }

        if (!empty($row_looks)) {
            $entry['s'] = $row_looks;
        }

        if (!empty($row_shown)) {
            $entry['show'] = $row_shown;
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
 * @return array ok, error, sheets (name, hidden, rows [n, cells[, h, s, show, calc]], truncated[, sizes][, edit]), truncated, looks (style => look)[, version]
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
    $looks = array();
    $date_styles = ($refused === '') ? pg_office_xlsx_date_styles($zip, $refused, $looks) : array();

    // A part read whole that is too large or damaged: the workbook is not
    // drawn without it, its sheets missing or its dates shown as numbers.
    if ($refused !== '') {
        $zip->close();

        return array('ok' => false, 'error' => pg_office_refusal_error($refused), 'sheets' => array(), 'truncated' => false);
    }

    $strings_part = pg_sheet_xlsx_part($zip, 'sharedstrings', 'xl/sharedStrings.xml');
    $workbook_xml = (string) pg_zip_entry_contents($zip, pg_sheet_xlsx_workbook($zip)['part'], PG_OFFICE_MAX_DOM, $unread);
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
    // The styles the shown cells use: only their looks are sent.
    $used = array();
    $edit = $edit && function_exists('pg_office_xlsx_sheet_edit_info');
    $signed = $edit && pg_office_signed($zip);
    // A password to modify closes the whole workbook, whatever its sheets.
    $reserved = $edit && !$signed && pg_office_xlsx_write_reserved($workbook_xml);
    $styles = $edit ? pg_office_xlsx_style_kinds($zip) : array();

    foreach ($sheets as $sheet) {
        $cut = false;
        $sizes = null;
        $rows = ($cells_left > 0) ? pg_office_xlsx_rows($path, $sheet['part'], $strings, $date_styles, $cells_left, $cut, $date1904, $looks, $sizes) : array();

        foreach ($rows as $row) {
            if (isset($row['s'])) {
                $used += array_flip($row['s']);
            }
        }

        if ($cells_left <= 0) {
            $cut = true;
        }

        $any_cut = $any_cut || $cut;
        $entry = array('name' => $sheet['name'], 'hidden' => $sheet['hidden'], 'rows' => $rows, 'truncated' => $cut);

        if ($sizes !== null) {
            $entry['sizes'] = pg_office_size_out($sizes);
        }

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

    $book = array('ok' => true, 'error' => '', 'sheets' => $out, 'truncated' => $any_cut, 'looks' => array_intersect_key($looks, $used));

    if ($edit) {
        $book['version'] = $version;

        // Whether the sheet tabs offer to add, rename, delete and move sheets.
        if (function_exists('pg_office_sheets_edit_info')) {
            $book['sheets_edit'] = pg_office_sheets_edit_info($signed, $reserved, $workbook_xml);
        }
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
    $heads = array();
    $style_sheet = pg_office_xml($zip, 'word/styles.xml', $status);
    pg_office_refuse($refused, $status);

    if ($style_sheet !== null) {
        foreach ($style_sheet->getElementsByTagNameNS('*', 'style') as $style) {
            $id = pg_office_attr($style, 'styleId');

            if ($id === '') {
                continue;
            }

            // A heading by its built-in name, which Word writes in English
            // whatever the id and the name it shows ("Balk 1", "a1").
            foreach ($style->childNodes as $name) {
                if (($name instanceof DOMElement) && ($name->localName === 'name')
                    && preg_match('/^heading ([1-9])$/', strtolower(trim(pg_office_attr($name, 'val'))), $level_match)) {
                    $heads[$id] = (int) $level_match[1];
                }
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
        'heads'       => $heads,
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

    return pg_office_image_url($state, pg_office_part_name('word/', $rel['target']));
}

/**
 * A picture part of a package as a data: URL, under the preview's picture
 * limits (PG_OFFICE_MAX_IMAGE one, PG_OFFICE_MAX_IMAGES together, counted in
 * $state['images']); '' when it is too large, missing, or not of a kind a
 * browser shows, counted in $state['images_lost']. A document's pictures and
 * a presentation's go through here alike.
 *
 * @param array  $state zip, images, images_lost
 * @param string $part
 * @return string
 */
function pg_office_image_url(&$state, $part)
{
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
                    $on = !in_array($val, array('0', 'false', 'off', 'none'), true);

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

    // The run's own colour, highlight and size (includes/fn/office_docx_format.php).
    $style = function_exists('pg_office_docx_look_style') ? pg_office_docx_look_style(pg_office_docx_run_look($run)) : '';

    if ($style !== '') {
        $html = '<span style="' . h($style) . '">' . $html . '</span>';
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

                if (($href !== '') && preg_match('#^(https?://|mailto:|tel:)#i', $href)) {
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

                // The kind the editor's paragraph style choice starts from
                // (includes/fn/office_docx_kind.php).
                if (isset($state['edit']) && function_exists('pg_office_docx_kind_mark')) {
                    pg_office_docx_kind_mark($state, $child, $props);
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

                $tag = isset($state['heads'][$props['style']]) ? 'h' . min(6, $state['heads'][$props['style']]) : pg_office_docx_block_tag($props['style']);
                $style = ($props['align'] !== '') ? ' style="text-align:' . $props['align'] . '"' : '';

                $html .= '<' . $tag . $style . $mark . '>' . (($inner === '') ? '&nbsp;' : $inner) . '</' . $tag . '>';
                break;

            case 'tbl':
                $close_list();
                $html .= '<table class="office-table">';

                foreach (pg_office_docx_wrapped($child, 'tr') as $row) {
                    // The limit holds inside a table too, between rows: each
                    // cell is drawn into a string of its own, which the check
                    // before each block above never measures.
                    if ($state['cut'] || (strlen($html) > PG_OFFICE_MAX_HTML)) {
                        $state['cut'] = true;
                        break;
                    }

                    $html .= '<tr>';

                    foreach (pg_office_docx_wrapped($row, 'tc') as $cell) {
                        if ($state['cut'] || (strlen($html) > PG_OFFICE_MAX_HTML)) {
                            $state['cut'] = true;
                            break;
                        }

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

    // Why it may not be deleted, and that no paragraph may be added after
    // it, each only when so (includes/fn/office_docx_paragraphs.php).
    if (function_exists('pg_office_docx_delete_refusal')) {
        $keep = pg_office_docx_delete_refusal($paragraph);

        if ($keep !== '') {
            $state['edit']['keeps'][(string) $position] = $keep;
        }

        if (!pg_office_docx_add_allowed($paragraph)) {
            $state['edit']['shut'][(string) $position] = 1;
        }
    }

    if ($edit['lock'] !== '') {
        return ' data-p="' . $position . '" data-lock="' . h($edit['lock']) . '"';
    }

    $state['edit']['texts'][(string) $position] = $edit['text'];

    // The look of the text and the paragraph's alignment when it is not
    // the start, which the editor's format bar starts from
    // (includes/fn/office_docx_format.php).
    if (function_exists('pg_office_docx_paragraph_marks')) {
        $state['edit']['marks'][(string) $position] = pg_office_docx_paragraph_marks($edit, isset($state['rels']) ? $state['rels'] : array());
        $align = pg_office_docx_paragraph_align($paragraph);

        if ($align !== 'start') {
            $state['edit']['aligns'][(string) $position] = $align;
        }
    }

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
        $state['edit'] = array('index' => array(), 'texts' => array(), 'marks' => array(), 'kinds' => array(), 'aligns' => array(), 'keeps' => array(), 'shut' => array());

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
            : array('ok' => true, 'why' => '', 'paragraphs' => (object) $state['edit']['texts'], 'marks' => (object) $state['edit']['marks'], 'kinds' => (object) $state['edit']['kinds'],
                'aligns' => (object) $state['edit']['aligns'], 'keeps' => (object) $state['edit']['keeps'], 'shut' => (object) $state['edit']['shut']);
        $out['version'] = $version;
    }

    return $out;
}

/* ---------------------------------------------------------------------------
   Comma and tab separated text
   --------------------------------------------------------------------------- */

/**
 * The separators a .csv or .tsv is read with, by the name the choice above
 * the sheet sends.
 *
 * @return string[]
 */
function pg_office_csv_delimiters()
{
    return array('comma' => ',', 'semicolon' => ';', 'tab' => "\t");
}

/**
 * The separator and character set a preview request asks for, as given;
 * pg_office_csv_read() keeps only the ones it offers and guesses the rest.
 *
 * @param array $request
 * @return array delimiter, encoding ('' when not asked for)
 */
function pg_office_csv_choice($request)
{
    $choice = array();

    foreach (array('delimiter', 'encoding') as $key) {
        $value = isset($request[$key]) ? $request[$key] : '';
        $choice[$key] = is_scalar($value) ? (string) $value : '';
    }

    return $choice;
}

/**
 * The character set of a .csv's bytes: UTF-8 with a byte order mark or
 * without one when every byte fits, else Windows-1254, the Turkish code
 * page Excel and older programs on a Turkish Windows save in.
 *
 * @param string $bytes whole lines of the file's beginning
 * @return string utf-8 | windows-1254
 */
function pg_office_csv_encoding($bytes)
{
    if (strncmp($bytes, "\xEF\xBB\xBF", 3) === 0) {
        return 'utf-8';
    }

    return pg_mail_is_utf8($bytes) ? 'utf-8' : 'windows-1254';
}

/**
 * A .csv's bytes as UTF-8 text in the character set given: the byte order
 * mark left out, a byte that is not UTF-8 read as UTF-8 shown as a
 * replacement mark, Windows-1254 converted byte by byte from the code
 * page's own table (pg_mail_single_byte_to_utf8()).
 *
 * @param string $bytes
 * @param string $encoding utf-8 | windows-1254
 * @return string
 */
function pg_office_csv_text($bytes, $encoding)
{
    static $code_page = null;

    if (strncmp($bytes, "\xEF\xBB\xBF", 3) === 0) {
        $bytes = (string) substr($bytes, 3);
    }

    if ($encoding !== 'windows-1254') {
        return pg_mail_is_utf8($bytes) ? $bytes : pg_mail_utf8_scrub($bytes);
    }

    // One table of the 128 upper bytes, so a large file is converted by
    // strtr() rather than a byte at a time.
    if ($code_page === null) {
        $code_page = array();

        for ($byte = 0x80; $byte <= 0xFF; $byte++) {
            $code_page[chr($byte)] = pg_mail_single_byte_to_utf8(chr($byte), 'windows-1254');
        }
    }

    return strtr($bytes, $code_page);
}

/**
 * The records of a .csv's text as a spreadsheet program reads them: cells
 * split at the separator, a cell that starts with a quote running to its
 * closing quote over separators and line breaks, a doubled quote inside it
 * one quote, and whatever follows the closing quote up to the separator
 * kept; CRLF, LF or a lone CR ends a record, a blank line is an empty row.
 * Each row carries its record's number in the file. A quote never closed
 * runs to the end of the text.
 *
 * The workbook preview's limits hold: PG_OFFICE_MAX_ROWS rows,
 * PG_OFFICE_MAX_COLUMNS columns and PG_OFFICE_MAX_CELLS cells; past any of
 * them the rest is left and $truncated set. When the text is not the whole
 * file ($whole false), the record it ends inside is left out too.
 *
 * Every character looked for is ASCII, which no byte of a UTF-8 or
 * Windows-1254 character above it can be, so the file's own bytes are read
 * as they are and only the cells kept are converted (pg_office_csv_read()).
 *
 * @param string $text      the file's bytes, or UTF-8
 * @param string $delimiter one character
 * @param bool   $whole     the text runs to the file's end
 * @param bool   $truncated set when something was left out
 * @param int    $max_rows  fewer rows, for a guess
 * @return array[] n, cells
 */
function pg_office_csv_records($text, $delimiter, $whole, &$truncated, $max_rows = PG_OFFICE_MAX_ROWS)
{
    $rows = array();
    $truncated = !$whole;
    $length = strlen($text);
    $stops = $delimiter . "\r\n";
    $cells_left = PG_OFFICE_MAX_CELLS;
    $at = 0;

    while ($at < $length) {
        if ((count($rows) >= $max_rows) || ($cells_left <= 0)) {
            $truncated = true;
            break;
        }

        $cells = array();
        $at_end = false;

        while (true) {
            $value = '';

            if (($at < $length) && ($text[$at] === '"')) {
                $at++;

                while (true) {
                    $quote = strpos($text, '"', $at);

                    if ($quote === false) {
                        $value .= (string) substr($text, $at);
                        $at = $length;
                        break;
                    }

                    $value .= (string) substr($text, $at, $quote - $at);
                    $at = $quote + 1;

                    if (($at < $length) && ($text[$at] === '"')) {
                        $value .= '"';
                        $at++;
                        continue;
                    }

                    break;
                }
            }

            $span = ($at < $length) ? strcspn($text, $stops, $at) : 0;
            $value .= (string) substr($text, $at, $span);
            $at += $span;

            if (count($cells) < PG_OFFICE_MAX_COLUMNS) {
                $cells[] = $value;
            } else {
                $truncated = true;
            }

            if ($at >= $length) {
                $at_end = true;
                break;
            }

            if ($text[$at] === $delimiter) {
                $at++;
                continue;
            }

            $at += (($text[$at] === "\r") && ($at + 1 < $length) && ($text[$at + 1] === "\n")) ? 2 : 1;
            break;
        }

        // The read stopped inside this record: the rest of it is not here.
        if ($at_end && !$whole) {
            break;
        }

        if ((count($cells) === 1) && ($cells[0] === '')) {
            $cells = array();
        }

        $rows[] = array('n' => count($rows) + 1, 'cells' => $cells);
        $cells_left -= max(1, count($cells));
    }

    return $rows;
}

/**
 * The separator a .csv's text is most likely written with: of the comma,
 * the semicolon and the tab, the one that splits the first records into the
 * same number of cells, two or more, most often; then the one giving more
 * cells; then the name's own (the tab for a .tsv, the comma otherwise),
 * which is also the answer for a file of one column. A separator inside
 * quotes is not counted, so "Kaya; Ali",30 is a comma file, and a comma in
 * every number of a semicolon file (Excel in Turkish) does not win.
 *
 * @param string $text the file's bytes, or UTF-8 (see pg_office_csv_records())
 * @param string $name the file's name, for its own separator
 * @return string comma | semicolon | tab
 */
function pg_office_csv_delimiter($text, $name)
{
    $sample = (string) substr($text, 0, 65536);
    $whole = (strlen($text) <= 65536);
    $own = (strtolower((string) pathinfo((string) $name, PATHINFO_EXTENSION)) === 'tsv') ? 'tab' : 'comma';
    $order = array_unique(array($own, 'comma', 'semicolon', 'tab'));
    $delimiters = pg_office_csv_delimiters();
    $best = $own;
    $best_agree = 0;
    $best_total = 1;
    $best_cells = 0;

    foreach ($order as $key) {
        $counts = array();

        foreach (pg_office_csv_records($sample, $delimiters[$key], $whole, $cut, 20) as $row) {
            if (!empty($row['cells'])) {
                $counts[] = count($row['cells']);
            }
        }

        if (empty($counts)) {
            continue;
        }

        // The number of cells most records have; on a tie, the larger.
        $tally = array_count_values($counts);
        $cells = 0;
        $agree = 0;

        foreach ($tally as $count => $records) {
            if (($records > $agree) || (($records === $agree) && ($count > $cells))) {
                $cells = (int) $count;
                $agree = $records;
            }
        }

        if ($cells < 2) {
            continue;
        }

        $total = count($counts);
        $share = $agree * $best_total - $best_agree * $total;

        if (($share > 0) || (($share === 0) && ($cells > $best_cells))) {
            $best = $key;
            $best_agree = $agree;
            $best_total = $total;
            $best_cells = $cells;
        }
    }

    return $best;
}

/**
 * A .csv or .tsv as the preview draws it: one sheet named after the file,
 * read from the file's first PG_OFFICE_MAX_CSV bytes in whole records, the
 * character set and the separator guessed unless the choice above the sheet
 * gave one it offers. Only looked at: nothing here is offered for editing.
 *
 * @param string $path
 * @param string $name   the file's name
 * @param array  $choice delimiter (comma, semicolon, tab), encoding (utf-8, windows-1254); others guessed
 * @return array ok, error, sheets, truncated, csv (delimiter, encoding: what the sheet was read with)
 */
function pg_office_csv_read($path, $name, $choice = array())
{
    $handle = is_file($path) ? @fopen($path, 'rb') : false;

    if (!$handle) {
        return array('ok' => false, 'error' => lang('The file could not be read.'), 'sheets' => array(), 'truncated' => false);
    }

    $bytes = '';

    while (!feof($handle) && (strlen($bytes) <= PG_OFFICE_MAX_CSV)) {
        $chunk = fread($handle, PG_OFFICE_MAX_CSV + 1 - strlen($bytes));

        if (($chunk === false) || ($chunk === '')) {
            break;
        }

        $bytes .= $chunk;
    }

    fclose($handle);

    // Past the limit only the whole lines are kept: a line break is never
    // inside a character, so the set is guessed on whole characters, and
    // the record cut short is left out by the reading. The search ends
    // before the one byte read past the limit.
    $whole = (strlen($bytes) <= PG_OFFICE_MAX_CSV);

    if (!$whole) {
        $line_end = max((int) strrpos($bytes, "\n", -2), (int) strrpos($bytes, "\r", -2));
        $bytes = ($line_end > 0) ? (string) substr($bytes, 0, $line_end + 1) : '';
    }

    $choice = is_array($choice) ? $choice : array();
    $delimiters = pg_office_csv_delimiters();
    $encoding = isset($choice['encoding']) ? (string) $choice['encoding'] : '';

    if (!in_array($encoding, array('utf-8', 'windows-1254'), true)) {
        $encoding = pg_office_csv_encoding($bytes);
    }

    if (strncmp($bytes, "\xEF\xBB\xBF", 3) === 0) {
        $bytes = (string) substr($bytes, 3);
    }

    $delimiter = isset($choice['delimiter']) ? (string) $choice['delimiter'] : '';

    if (!isset($delimiters[$delimiter])) {
        $delimiter = pg_office_csv_delimiter($bytes, $name);
    }

    // Read on the bytes and converted a cell at a time, so the file is never
    // held a second time in its larger UTF-8 form.
    $rows = pg_office_csv_records($bytes, $delimiters[$delimiter], $whole, $cut);
    unset($bytes);

    foreach ($rows as $index => $row) {
        foreach ($row['cells'] as $column => $cell) {
            $rows[$index]['cells'][$column] = pg_office_csv_text($cell, $encoding);
        }
    }

    return array(
        'ok'        => true,
        'error'     => '',
        'sheets'    => array(array('name' => pg_file_name_stem($name), 'hidden' => false, 'rows' => $rows, 'truncated' => $cut)),
        'truncated' => $cut,
        'csv'       => array('delimiter' => $delimiter, 'encoding' => $encoding),
    );
}

/* ---------------------------------------------------------------------------
   OpenDocument spreadsheets
   --------------------------------------------------------------------------- */

/**
 * The sheets of an .ods file in their order, counted as the sheet reader
 * counts them (pg_sheet_rows(): every table:table, one inside another not
 * being a sheet): the name, and whether it is hidden, which its table style
 * says with table:display="false" (the automatic styles come before the
 * body). content.xml is streamed once, node by node, held to the size the
 * reader takes (PG_SHEET_ODS_MAX_PART); more than PG_OFFICE_MAX_ODS_SHEETS
 * sheets make the file too large.
 *
 * @param string $path
 * @param string $refused set to large or damaged when the sheets could not be listed
 * @return array[] name, hidden
 */
function pg_office_ods_sheets($path, &$refused = null)
{
    $refused = '';
    $size = pg_zip_entry_size($path, 'content.xml');

    if ($size > PG_SHEET_ODS_MAX_PART) {
        $refused = 'large';

        return array();
    }

    $reader = new XMLReader();

    if (($size <= 0) || !$reader->open(pg_zip_entry_uri($path, 'content.xml', $size), null, LIBXML_NONET)) {
        $refused = 'damaged';

        return array();
    }

    $table_ns = 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';
    $style_ns = 'urn:oasis:names:tc:opendocument:xmlns:style:1.0';
    $previous = libxml_use_internal_errors(true);
    $out = array();
    $hidden = array();
    // The table style being read, and its depth: its properties stand
    // right under it.
    $style = null;
    $style_depth = 0;
    $skip = false;

    while ($skip ? $reader->next() : $reader->read()) {
        $skip = false;

        if ($reader->nodeType !== XMLReader::ELEMENT) {
            continue;
        }

        if (($style !== null) && ($reader->depth <= $style_depth)) {
            $style = null;
        }

        if ($reader->namespaceURI === $style_ns) {
            if ($reader->localName === 'style') {
                $style = ($reader->getAttributeNs('family', $style_ns) === 'table') ? (string) $reader->getAttributeNs('name', $style_ns) : null;
                $style_depth = $reader->depth;
            } elseif (($style !== null) && ($reader->localName === 'table-properties') && ($reader->depth === $style_depth + 1) && ($reader->getAttributeNs('display', $table_ns) === 'false')) {
                $hidden[$style] = true;
            }

            continue;
        }

        if (($reader->localName !== 'table') || ($reader->namespaceURI !== $table_ns)) {
            continue;
        }

        if (count($out) >= PG_OFFICE_MAX_ODS_SHEETS) {
            $refused = 'large';
            break;
        }

        $out[] = array(
            'name'   => (string) $reader->getAttributeNs('name', $table_ns),
            'hidden' => isset($hidden[(string) $reader->getAttributeNs('style-name', $table_ns)]),
        );

        // Its rows are the sheet reader's to walk.
        $skip = true;
    }

    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $reader->close();

    if (($refused === '') && (pg_zip_entry_overran() || empty($out))) {
        $refused = 'damaged';
    }

    return ($refused === '') ? $out : array();
}

/**
 * A cell of the sheet reader (pg_sheet_rows()) as the workbook preview shows
 * one (pg_office_xlsx_rows()): a number without its exponent, a boolean as
 * TRUE or FALSE, a date as Y-m-d (with the time of day when it has one), a
 * time alone as hh:mm (hh:mm:ss when it has seconds), text and an error as
 * they are, a missing cell as ''.
 *
 * @param array|null $cell
 * @return string
 */
function pg_office_ods_text($cell)
{
    if (!is_array($cell) || !isset($cell['t'], $cell['v'])) {
        return '';
    }

    switch ($cell['t']) {
        case 'n':
            return pg_office_xlsx_number($cell['v']);

        case 'b':
            return ($cell['v'] === '1') ? 'TRUE' : 'FALSE';

        case 'd':
            $serial = (float) $cell['v'];

            if (abs($serial) < 1) {
                return pg_office_xlsx_time($serial, (((int) round(abs($serial) * 86400)) % 60) ? 'time_s' : 'time');
            }

            return pg_office_xlsx_date($serial);
    }

    return (string) $cell['v'];
}

/**
 * The rows of one .ods sheet as the preview draws them, read through the
 * sheet reader (pg_sheet_rows()) and cut at the preview's limits, as
 * pg_office_xlsx_rows() cuts a worksheet. The reader's walk is left as soon
 * as the rows shown are read, so its cell ceilings hold on what is read.
 *
 * @param string $path
 * @param int    $sheet      the sheet, 0 for the first
 * @param int    $cells_left how many cells may still be read, counted down
 * @param bool   $truncated  set when the sheet was cut
 * @param string $error      the reader's: '' read, large past its ceilings, read or sheet
 * @param string $kind       the reader's kind (pg_sheet_kind()): ods, or xls
 * @return array[] n, cells
 */
function pg_office_ods_rows($path, $sheet, &$cells_left, &$truncated, &$error, $kind = 'ods')
{
    $rows = array();
    $truncated = false;
    // The walk puts libxml's error handling back when it ends, which a walk
    // left early does not reach.
    $previous = libxml_use_internal_errors();

    foreach (pg_sheet_rows($path, $kind, $error, $sheet) as $number => $cells) {
        if ((count($rows) >= PG_OFFICE_MAX_ROWS) || ($cells_left <= 0)) {
            $truncated = true;
            break;
        }

        $row = array();

        foreach ($cells as $column => $cell) {
            if ($column >= PG_OFFICE_MAX_COLUMNS) {
                $truncated = true;
                break;
            }

            $row[] = pg_office_ods_text($cell);
        }

        $rows[] = array('n' => (int) $number, 'cells' => $row);
        $cells_left -= max(1, count($row));
    }

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return $rows;
}

/**
 * An .ods file as the preview draws a workbook (pg_office_xlsx_read()):
 * every sheet in order, the hidden ones flagged, its rows read through the
 * importers' sheet reader under the preview's row, column and cell limits.
 * A sheet past the reader's cell ceilings (PG_SHEET_ODS_MAX_CELLS,
 * PG_SHEET_ODS_MAX_RANGE) refuses the file as too large. Only looked at:
 * nothing here is offered for editing.
 *
 * The reader walks content.xml from its beginning for each sheet, so sheets
 * are read while their count times the part's size stays within
 * PG_OFFICE_MAX_ODS_WALK; those past it are listed with no rows, cut, as a
 * workbook's sheets past its cell limit are.
 *
 * @param string $path
 * @return array ok, error, sheets (name, hidden, rows [n, cells], truncated), truncated
 */
function pg_office_ods_read($path)
{
    $zip = pg_office_open($path, $error, 'content.xml');

    if ($zip === null) {
        return array('ok' => false, 'error' => $error, 'sheets' => array(), 'truncated' => false);
    }

    $zip->close();

    $sheets = pg_office_ods_sheets($path, $refused);

    if ($refused !== '') {
        return array('ok' => false, 'error' => pg_office_refusal_error($refused), 'sheets' => array(), 'truncated' => false);
    }

    $walks = max(1, intdiv(PG_OFFICE_MAX_ODS_WALK, max(1, pg_zip_entry_size($path, 'content.xml'))));
    $cells_left = PG_OFFICE_MAX_CELLS;
    $out = array();
    $any_cut = false;

    foreach ($sheets as $index => $sheet) {
        $rows = array();
        $cut = true;

        if (($cells_left > 0) && ($index < $walks)) {
            $rows = pg_office_ods_rows($path, $index, $cells_left, $cut, $failed);

            if ($failed !== '') {
                return array('ok' => false, 'error' => pg_office_refusal_error(($failed === 'large') ? 'large' : 'damaged'), 'sheets' => array(), 'truncated' => false);
            }
        }

        if ($cells_left <= 0) {
            $cut = true;
        }

        $any_cut = $any_cut || $cut;
        $out[] = array('name' => $sheet['name'], 'hidden' => $sheet['hidden'], 'rows' => $rows, 'truncated' => $cut);
    }

    return array('ok' => true, 'error' => '', 'sheets' => $out, 'truncated' => $any_cut);
}

/* ---------------------------------------------------------------------------
   Excel 97-2003 workbooks (.xls)
   --------------------------------------------------------------------------- */

/**
 * Room for the .xls reader: it holds the file and a sheet's cells as the
 * library's arrays, about 40 bytes of memory for each byte of the file (an
 * 8 MB workbook of 260,000 cells peaked at 300 MB). The limit is raised to
 * 512 MB, as the product import raises it, unless it is higher already.
 */
function pg_office_xls_memory()
{
    $limit = trim((string) @ini_get('memory_limit'));

    if (($limit === '') || ($limit === '-1')) {
        return;
    }

    $bytes = (int) $limit;
    $unit = strtolower(substr($limit, -1));
    $bytes *= ($unit === 'g') ? 1073741824 : (($unit === 'm') ? 1048576 : (($unit === 'k') ? 1024 : 1));

    if ($bytes < 512 * 1048576) {
        @ini_set('memory_limit', '512M');
    }
}

/**
 * The worksheets of an .xls file for the preview or a copy, after its size
 * is measured: name and hidden, as the sheet reader lists them
 * (pg_sheet_xls_sheets()), or an error.
 *
 * @param string $path
 * @param string $error why not, when null comes back
 * @return array[]|null
 */
function pg_office_xls_sheets($path, &$error)
{
    $error = '';
    clearstatcache(true, $path);
    $size = is_file($path) ? (int) @filesize($path) : -1;

    if ($size > PG_OFFICE_MAX_XLS) {
        $error = pg_office_refusal_error('large');

        return null;
    }

    pg_office_xls_memory();

    $sheets = ($size > 0) ? pg_sheet_xls_sheets($path) : 'read';

    if (!is_array($sheets)) {
        $error = pg_office_refusal_error('damaged');

        return null;
    }

    if (count($sheets) > PG_OFFICE_MAX_ODS_SHEETS) {
        $error = pg_office_refusal_error('large');

        return null;
    }

    return $sheets;
}

/**
 * An .xls file as the preview draws a workbook (pg_office_xlsx_read()):
 * every worksheet in order, the hidden ones flagged, its rows read through
 * the importers' sheet reader under the preview's row, column and cell
 * limits, each cell shown as pg_office_ods_text() shows one. A formula
 * shows the result the file stores. Only looked at: nothing here is offered
 * for editing; a copy as .xlsx is (pg_office_xls_copy()).
 *
 * The reader parses the file again for each sheet, so sheets are read
 * while their count times the file's size stays within
 * PG_OFFICE_MAX_XLS_WALK; those past it are listed with no rows, cut.
 *
 * @param string $path
 * @return array ok, error, sheets (name, hidden, rows [n, cells], truncated), truncated
 */
function pg_office_xls_read($path)
{
    $sheets = pg_office_xls_sheets($path, $error);

    if ($sheets === null) {
        return array('ok' => false, 'error' => $error, 'sheets' => array(), 'truncated' => false);
    }

    $walks = max(1, intdiv(PG_OFFICE_MAX_XLS_WALK, max(1, (int) filesize($path))));
    $cells_left = PG_OFFICE_MAX_CELLS;
    $out = array();
    $any_cut = false;

    foreach ($sheets as $index => $sheet) {
        $rows = array();
        $cut = true;

        if (($cells_left > 0) && ($index < $walks)) {
            $rows = pg_office_ods_rows($path, $index, $cells_left, $cut, $failed, 'xls');

            if ($failed !== '') {
                return array('ok' => false, 'error' => pg_office_refusal_error('damaged'), 'sheets' => array(), 'truncated' => false);
            }
        }

        if ($cells_left <= 0) {
            $cut = true;
        }

        $any_cut = $any_cut || $cut;
        $out[] = array('name' => $sheet['name'], 'hidden' => $sheet['hidden'], 'rows' => $rows, 'truncated' => $cut);
    }

    return array('ok' => true, 'error' => '', 'sheets' => $out, 'truncated' => $any_cut);
}

/**
 * The name of an .xls file's copy as .xlsx: its extension changed, any
 * other name given .xlsx after it.
 *
 * @param string $name
 * @return string
 */
function pg_office_xls_copy_name($name)
{
    $name = (string) $name;

    if (preg_match('/\.xls$/i', $name)) {
        return substr($name, 0, -4) . '.xlsx';
    }

    return (($name === '') ? 'copy' : $name) . '.xlsx';
}

/**
 * An .xls file written again as an .xlsx by the shared writer
 * (pg_xlsx_write_book()): every worksheet whole, in order, hidden ones kept
 * hidden, each cell with its type (text, number, boolean, date or time,
 * error). A formula comes over as the result the file stores: the reader
 * does not read a formula's expression. Number formats other than dates
 * and times, column widths, merged cells and styles are not carried.
 *
 * @param string $path   the .xls
 * @param string $target where the .xlsx is written
 * @return array ok, error
 */
function pg_office_xls_copy($path, $target)
{
    $sheets = pg_office_xls_sheets($path, $error);

    if ($sheets === null) {
        return array('ok' => false, 'error' => $error);
    }

    if (count($sheets) * (int) filesize($path) > PG_OFFICE_MAX_XLS_COPY_WALK) {
        return array('ok' => false, 'error' => lang('This file is too large to copy here.'));
    }

    $failed = '';
    $rows = function ($index) use ($path, &$failed) {
        $error = '';

        foreach (pg_sheet_rows($path, 'xls', $error, $index) as $number => $cells) {
            yield $number => $cells;
        }

        if ($error !== '') {
            $failed = $error;
            throw new RuntimeException('The sheet could not be read.');
        }
    };

    if (!pg_xlsx_write_book($sheets, $rows, $target)) {
        @unlink($target);

        return array('ok' => false, 'error' => ($failed !== '') ? pg_office_refusal_error('damaged') : lang('The copy could not be written.'));
    }

    return array('ok' => true, 'error' => '');
}

/* ---------------------------------------------------------------------------
   Presentations (.pptx)
   --------------------------------------------------------------------------- */

/**
 * The slide parts of a presentation in the order it shows them: part and
 * relationships part. The presentation part is the one the package's root
 * relationships name (ppt/presentation.xml when they name none).
 *
 * @param ZipArchive $zip
 * @param string     $refused set to large or damaged when the presentation part or a relationships part could not be read whole
 * @param bool       $found   whether the package holds a presentation part at all
 * @param string     $main    set to the presentation part's name
 * @return array[]
 */
function pg_office_pptx_slides($zip, &$refused = null, &$found = null, &$main = null)
{
    $out = array();
    $refused = '';
    $found = false;
    $main = 'ppt/presentation.xml';

    foreach (pg_office_rels($zip, '_rels/.rels', $root_refused) as $rel) {
        if (($rel['type'] === 'officedocument') && !$rel['external']) {
            $main = pg_office_part_name('', $rel['target']);
            break;
        }
    }

    pg_office_refuse($refused, $root_refused);
    $presentation = pg_office_xml($zip, $main, $status);
    pg_office_refuse($refused, $status);

    if (($presentation === null) || ($refused !== '')) {
        return $out;
    }

    $found = true;
    $folder = pg_office_pptx_folder($main);
    $rels = pg_office_rels($zip, pg_office_pptx_rels_part($main), $rels_refused);
    pg_office_refuse($refused, $rels_refused);

    foreach ($presentation->getElementsByTagNameNS('*', 'sldId') as $slide) {
        // A slide's own number is a bare id beside the relationship's r:id.
        $id = '';

        foreach ($slide->attributes as $attribute) {
            if (($attribute->localName === 'id') && ((string) $attribute->namespaceURI !== '')) {
                $id = (string) $attribute->value;
            }
        }

        if (($id === '') || !isset($rels[$id]) || $rels[$id]['external']) {
            continue;
        }

        $part = pg_office_part_name($folder, $rels[$id]['target']);

        if ($zip->locateName($part) !== false) {
            $out[] = $part;
        }
    }

    return $out;
}

/**
 * The folder a part's relationship targets are written relative to.
 *
 * @param string $part ppt/slides/slide1.xml
 * @return string ppt/slides/
 */
function pg_office_pptx_folder($part)
{
    $end = strrpos((string) $part, '/');

    return ($end === false) ? '' : substr((string) $part, 0, $end + 1);
}

/**
 * The relationships part of a part: ppt/slides/_rels/slide1.xml.rels.
 *
 * @param string $part
 * @return string
 */
function pg_office_pptx_rels_part($part)
{
    $folder = pg_office_pptx_folder($part);

    return $folder . '_rels/' . substr((string) $part, strlen($folder)) . '.rels';
}

/**
 * The text of one paragraph of a text body (a:p): its runs and fields, a
 * line break as a new line.
 *
 * @param DOMElement $paragraph
 * @return string
 */
function pg_office_pptx_text($paragraph)
{
    $text = '';

    foreach ($paragraph->childNodes as $child) {
        if (!($child instanceof DOMElement)) {
            continue;
        }

        if ($child->localName === 'br') {
            $text .= "\n";
            continue;
        }

        if (($child->localName !== 'r') && ($child->localName !== 'fld')) {
            continue;
        }

        foreach ($child->childNodes as $part) {
            if (($part instanceof DOMElement) && ($part->localName === 't')) {
                $text .= $part->textContent;
            }
        }
    }

    return $text;
}

/**
 * The child elements of a node with a local name, in their order.
 *
 * @param DOMNode $node
 * @param string  $local
 * @return DOMElement[]
 */
function pg_office_pptx_children($node, $local)
{
    $out = array();

    foreach ($node->childNodes as $child) {
        if (($child instanceof DOMElement) && ($child->localName === $local)) {
            $out[] = $child;
        }
    }

    return $out;
}

/**
 * The placeholder type of a shape (title, ctrTitle, body, ...), '' for a
 * shape that is no placeholder; a placeholder without a type is a body.
 *
 * @param DOMElement $shape
 * @return string
 */
function pg_office_pptx_placeholder($shape)
{
    foreach ($shape->getElementsByTagNameNS('*', 'ph') as $placeholder) {
        $type = pg_office_attr($placeholder, 'type');

        return ($type === '') ? 'body' : $type;
    }

    return '';
}

/**
 * Keeps a piece of text for the slide while the presentation's text stays
 * within PG_OFFICE_MAX_HTML characters; past it the rest is cut.
 *
 * @param array  $state
 * @param string $text
 * @return bool whether it was kept
 */
function pg_office_pptx_room(&$state, $text)
{
    if ($state['cut']) {
        return false;
    }

    $state['size'] += strlen($text);

    if ($state['size'] > PG_OFFICE_MAX_HTML) {
        $state['cut'] = true;

        return false;
    }

    return true;
}

/**
 * The shapes of a slide's tree (or of a group in it) in their order, which
 * is the slide's reading order: a title placeholder's text becomes the
 * slide's title, any other shape's paragraphs, a table's rows (cells apart
 * by a tab) and pictures become its items. Groups are read in place; of
 * alternate content, the fallback a reader without the newer markup takes.
 *
 * @param array      $state zip, images, images_lost, size, cut
 * @param DOMElement $tree
 * @param array      $rels  the slide's relationships
 * @param string     $folder
 * @param array      $slide title, items
 */
function pg_office_pptx_shapes(&$state, $tree, $rels, $folder, &$slide)
{
    foreach ($tree->childNodes as $shape) {
        if (!($shape instanceof DOMElement) || $state['cut']) {
            continue;
        }

        switch ($shape->localName) {
            case 'grpSp':
                pg_office_pptx_shapes($state, $shape, $rels, $folder, $slide);
                break;

            case 'AlternateContent':
                $branch = pg_office_pptx_children($shape, 'Fallback') ?: pg_office_pptx_children($shape, 'Choice');

                if ($branch) {
                    pg_office_pptx_shapes($state, $branch[0], $rels, $folder, $slide);
                }
                break;

            case 'sp':
                $type = pg_office_pptx_placeholder($shape);
                $title = (($type === 'title') || ($type === 'ctrTitle')) && ($slide['title'] === '');
                $lines = array();

                foreach (pg_office_pptx_children($shape, 'txBody') as $body) {
                    foreach (pg_office_pptx_children($body, 'p') as $paragraph) {
                        $text = trim(pg_office_pptx_text($paragraph));

                        if ($text === '') {
                            continue;
                        }

                        $level = 0;

                        foreach (pg_office_pptx_children($paragraph, 'pPr') as $properties) {
                            $level = min(8, max(0, (int) pg_office_attr($properties, 'lvl')));
                        }

                        $lines[] = array('text' => $text, 'level' => $level);
                    }
                }

                if ($title && $lines) {
                    $text = implode(' ', array_column($lines, 'text'));

                    if (pg_office_pptx_room($state, $text)) {
                        $slide['title'] = $text;
                    }
                    break;
                }

                foreach ($lines as $line) {
                    if (pg_office_pptx_room($state, $line['text'])) {
                        $slide['items'][] = array('type' => 'text') + $line;
                    }
                }
                break;

            case 'graphicFrame':
                foreach ($shape->getElementsByTagNameNS('*', 'tr') as $row) {
                    $cells = array();

                    foreach (pg_office_pptx_children($row, 'tc') as $cell) {
                        $texts = array();

                        foreach ($cell->getElementsByTagNameNS('*', 'p') as $paragraph) {
                            $texts[] = trim(pg_office_pptx_text($paragraph));
                        }

                        $cells[] = trim(implode(' ', $texts));
                    }

                    $text = rtrim(implode("\t", $cells));

                    if (($text !== '') && pg_office_pptx_room($state, $text)) {
                        $slide['items'][] = array('type' => 'row', 'text' => $text, 'level' => 0);
                    }
                }
                break;

            case 'pic':
                $rel_id = '';

                foreach ($shape->getElementsByTagNameNS('*', 'blip') as $blip) {
                    $rel_id = pg_office_attr($blip, 'embed');
                }

                $rel = $rels[$rel_id] ?? null;

                if (($rel === null) || $rel['external']) {
                    break;
                }

                $alt = '';

                foreach ($shape->getElementsByTagNameNS('*', 'cNvPr') as $properties) {
                    $alt = trim((string) $properties->getAttribute('descr'));
                    break;
                }

                $src = pg_office_image_url($state, pg_office_part_name($folder, $rel['target']));
                $slide['items'][] = ($src === '') ? array('type' => 'lost') : array('type' => 'picture', 'src' => $src, 'alt' => $alt);
                break;
        }
    }
}

/**
 * The speaker notes of a slide: the paragraphs of its notes page's body
 * placeholder, in their order.
 *
 * @param array      $state
 * @param ZipArchive $zip
 * @param array      $rels    the slide's relationships
 * @param string     $folder  the slide's folder
 * @param string     $refused set to large or damaged when the notes page could not be read whole
 * @return string[]
 */
function pg_office_pptx_notes(&$state, $zip, $rels, $folder, &$refused)
{
    $notes = array();

    foreach ($rels as $rel) {
        if (($rel['type'] !== 'notesslide') || $rel['external']) {
            continue;
        }

        $page = pg_office_xml($zip, pg_office_part_name($folder, $rel['target']), $status);
        pg_office_refuse($refused, $status);

        if ($page === null) {
            break;
        }

        foreach ($page->getElementsByTagNameNS('*', 'sp') as $shape) {
            if (pg_office_pptx_placeholder($shape) !== 'body') {
                continue;
            }

            foreach ($shape->getElementsByTagNameNS('*', 'p') as $paragraph) {
                $text = trim(pg_office_pptx_text($paragraph));

                if (($text !== '') && pg_office_pptx_room($state, $text)) {
                    $notes[] = $text;
                }
            }
        }

        break;
    }

    return $notes;
}

/**
 * A presentation read for the preview: a list of its slides, each with its
 * title, its text and pictures in reading order and its speaker notes. No
 * layout, theme, animation or master text is read; a hidden slide is
 * flagged. Only looked at: nothing here is offered for editing.
 *
 * Pictures keep the document preview's limits (pg_office_image_url()), the
 * slides PG_OFFICE_MAX_SLIDES and their text PG_OFFICE_MAX_HTML characters;
 * past those the list is cut. A slide, notes page or relationships part past
 * PG_OFFICE_MAX_DOM, or not the size it declares, refuses the file.
 *
 * @param string $path
 * @return array ok, error, slides (n, title, hidden, items, notes), truncated, images_lost
 */
function pg_office_pptx_read($path)
{
    $zip = pg_office_open($path, $error);
    $none = array('ok' => false, 'error' => $error, 'slides' => array(), 'truncated' => false, 'images_lost' => 0);

    if ($zip === null) {
        return $none;
    }

    $parts = pg_office_pptx_slides($zip, $refused, $found);

    if (($refused !== '') || !$found) {
        $zip->close();
        $none['error'] = ($refused !== '') ? pg_office_refusal_error($refused) : lang('The file could not be read.');

        return $none;
    }

    $state = array('zip' => $zip, 'images' => 0, 'images_lost' => 0, 'size' => 0, 'cut' => false);
    $slides = array();

    foreach (array_slice($parts, 0, PG_OFFICE_MAX_SLIDES) as $index => $part) {
        $dom = pg_office_xml($zip, $part, $status);
        $rels = pg_office_rels($zip, pg_office_pptx_rels_part($part), $rels_refused);
        pg_office_refuse($refused, $status);
        pg_office_refuse($refused, $rels_refused);
        $folder = pg_office_pptx_folder($part);
        $slide = array('n' => $index + 1, 'title' => '', 'hidden' => false, 'items' => array(), 'notes' => array());

        if (($dom !== null) && ($refused === '')) {
            $show = strtolower((string) $dom->documentElement->getAttribute('show'));
            $slide['hidden'] = in_array($show, array('0', 'false'), true);

            foreach ($dom->getElementsByTagNameNS('*', 'spTree') as $tree) {
                pg_office_pptx_shapes($state, $tree, $rels, $folder, $slide);
                break;
            }

            $slide['notes'] = pg_office_pptx_notes($state, $zip, $rels, $folder, $refused);
        }

        unset($dom);

        if ($refused !== '') {
            $zip->close();
            $none['error'] = pg_office_refusal_error($refused);

            return $none;
        }

        // The slide where the text ran out is listed with what fitted.
        $slides[] = $slide;

        if ($state['cut']) {
            break;
        }
    }

    $zip->close();
    $cut = $state['cut'] || (count($parts) > PG_OFFICE_MAX_SLIDES);

    return array('ok' => true, 'error' => '', 'slides' => $slides, 'truncated' => $cut, 'images_lost' => $state['images_lost']);
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
 * offered, the hint, the count of changes, the format bar), those of a document (why a
 * paragraph or the whole document is not offered, the hint, the count) and
 * the choice above a .csv's sheet.
 *
 * @return array
 */
function pg_office_view_strings()
{
    return array(
        'hidden'            => lang('Hidden sheet'),
        'xls_copy'          => lang('Save a copy as .xlsx'),
        'xls_copy_help'     => lang('This file is only shown here. A copy as .xlsx can be changed; formulas come over as their results.'),
        'sheets'            => lang('Sheets'),
        'changed_mark'      => lang('Changed'),
        'truncated'         => lang('Only the beginning is shown. Download the file to see all of it.'),
        'images_lost'       => lang('{1} picture(s) could not be shown.'),
        'empty'             => lang('The sheet is empty.'),
        'calc'              => lang('Worked out by a formula when the file is opened in a spreadsheet program.'),
        'edit_hint'         => lang('Double-click a cell, or select it and type, to change it. Enter keeps the change, Esc undoes it.'),
        'unsaved'           => lang('{1} change(s) not saved.'),
        'recalc'            => lang('Formulas are worked out again when the file is opened in a spreadsheet program.'),
        'formula_text'      => lang('Kept as text: formulas are not worked out here.'),
        'shift_hint'        => lang('Click a row\'s number or a column\'s letter to insert or delete it.'),
        'shift_menu'        => lang('Rows and columns'),
        'head_row'          => lang('Row {1}: insert or delete'),
        'head_column'       => lang('Column {1}: sort, filter, insert or delete'),
        'head_column_view'  => lang('Column {1}: sort and filter'),
        'column_menu'       => lang('Column {1}'),
        'filtered_mark'     => lang('Filtered'),
        'sort_up'           => lang('Sort A to Z'),
        'sort_down'         => lang('Sort Z to A'),
        'sort_off'          => lang('Remove the sort'),
        'filter_values'     => lang('Filter by value'),
        'filter_menu'       => lang('Filter column {1}'),
        'filter_all'        => lang('Show all values'),
        'filter_empty'      => lang('(Empty)'),
        'filter_more'       => lang('Only the first {1} values are listed.'),
        'filter_off'        => lang('Remove the filter of column {1}'),
        'view_sorted_up'    => lang('Sorted by column {1}, A to Z.'),
        'view_sorted_down'  => lang('Sorted by column {1}, Z to A.'),
        'view_filtered'     => lang('{1} of {2} rows shown.'),
        'view_header'       => lang('Row {1} stays on top as the header.'),
        'view_clear'        => lang('Clear sort and filter'),
        'view_paused'       => lang('Clear the sort and filter to change cells.'),
        'find_label'        => lang('Find in the workbook'),
        'find_count'        => lang('{1} of {2}'),
        'find_none'         => lang('No matches'),
        'find_previous'     => lang('Previous match'),
        'find_next'         => lang('Next match'),
        'csv_download'      => lang('Download as CSV'),
        'csv_help'          => lang('Downloads the open sheet as it is shown: its rows sorted and filtered, each cell as it is shown.'),
        'csv_truncated'     => lang('The CSV holds only the rows shown here.'),
        'row_before'        => lang('Insert a row above'),
        'row_after'         => lang('Insert a row below'),
        'row_delete'        => lang('Delete row {1}'),
        'column_before'     => lang('Insert a column on the left'),
        'column_after'      => lang('Insert a column on the right'),
        'column_delete'     => lang('Delete column {1}'),
        'size_column'       => lang('Column width…'),
        'size_row'          => lang('Row height…'),
        'size_column_label' => lang('Width of column {1}, in characters'),
        'size_row_label'    => lang('Height of row {1}, in points'),
        'size_apply'        => lang('Apply'),
        'size_bad'          => lang('Enter a number above 0 and up to {1}.'),
        'freeze_on'         => lang('Freeze top row'),
        'freeze_off'        => lang('Unfreeze rows'),
        'clip_skipped'      => lang('Locked cells left as they were: {1}.'),
        'clip_room'         => lang('Not pasted: the copied cells reach past the last row or column shown. Select a cell with room for them.'),
        'clip_many'         => lang('Not changed: at most {1} cells change at once.'),
        'clip_long'         => lang('Not pasted: a cell holds at most {1} characters.'),
        'sheet_hint'        => lang('Click the open sheet\'s tab to rename, move or delete it.'),
        'sheet_menu'        => lang('Sheet'),
        'sheet_add'         => lang('Add a sheet'),
        'sheet_rename'      => lang('Rename'),
        'sheet_left'        => lang('Move left'),
        'sheet_right'       => lang('Move right'),
        'sheet_delete'      => lang('Delete sheet {1}'),
        'sheet_name'        => lang('Name of the sheet'),
        'sheet_new'         => lang('Sheet{1}'),
        'sheet_bad'         => lang('A sheet name is 1 to 31 characters long, without \\ / ? * [ ] : and not starting or ending with an apostrophe.'),
        'sheet_taken'       => lang('Another sheet is already named {1}.'),
        'format_bar'        => lang('Format of the selected cells'),
        'format_bold'       => lang('Bold'),
        'format_italic'     => lang('Italic'),
        'format_color'      => lang('Text color'),
        'format_fill'       => lang('Fill color'),
        'format_none'       => lang('None'),
        'colour_black'      => lang('Black color'),
        'colour_grey'       => lang('Grey'),
        'colour_red'        => lang('Red'),
        'colour_orange'     => lang('Orange'),
        'colour_yellow'     => lang('Yellow'),
        'colour_green'      => lang('Green'),
        'colour_teal'       => lang('Teal'),
        'colour_blue'       => lang('Blue'),
        'colour_violet'     => lang('Violet'),
        'colour_pink'       => lang('Pink'),
        'colour_bright_yellow'=> lang('Bright yellow'),
        'colour_light_grey' => lang('Light grey'),
        'colour_light_red'  => lang('Light red'),
        'colour_light_orange'=> lang('Light orange'),
        'colour_light_yellow'=> lang('Light yellow'),
        'colour_light_green'=> lang('Light green'),
        'colour_light_teal' => lang('Light teal'),
        'colour_light_blue' => lang('Light blue'),
        'colour_light_violet'=> lang('Light violet'),
        'colour_light_pink' => lang('Light pink'),
        'format_align'      => lang('Alignment'),
        'format_left'       => lang('Align left'),
        'format_center'     => lang('Align center'),
        'format_right'      => lang('Align right'),
        'format_number'     => lang('Number format'),
        'format_general_kind' => lang('General'),
        'format_number_kind'  => lang('Number'),
        'format_money_kind'   => lang('Money'),
        'format_percent_kind' => lang('Percent'),
        'format_date_kind'    => lang('Date'),
        'format_hint'       => lang('Shift+click or Shift+arrow keys select more cells; the bar above the sheet changes their format.'),
        'format_many'       => lang('Select at most 5000 cells to change their format.'),
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
        'doc_hint'          => lang('Click where you want to type. Enter starts a new paragraph, Backspace at its start joins it to the one before, Shift+Enter starts a new line, Esc undoes what you typed in the paragraph.'),
        'doc_changed'       => lang('{1} paragraph(s) changed.'),
        'doc_format'        => lang('Changed paragraphs show their formatting again once saved.'),
        'doc'               => lang('Document preview'),
        'doc_format_bar'    => lang('Format of the selected text'),
        'doc_underline'     => lang('Underline'),
        'doc_color'         => lang('Text color'),
        'doc_highlight'     => lang('Highlight color'),
        'doc_align_start'   => lang('Align left'),
        'doc_align_center'  => lang('Align center'),
        'doc_align_end'     => lang('Align right'),
        'doc_align_justify' => lang('Justify'),
        'highlight_yellow'  => lang('Yellow'),
        'highlight_green'   => lang('Bright green'),
        'highlight_cyan'    => lang('Turquoise'),
        'highlight_magenta' => lang('Pink'),
        'highlight_blue'    => lang('Blue'),
        'highlight_red'     => lang('Red'),
        'highlight_darkBlue'=> lang('Dark blue'),
        'highlight_darkCyan'=> lang('Teal'),
        'highlight_darkGreen'=> lang('Green'),
        'highlight_darkMagenta'=> lang('Violet'),
        'highlight_darkRed' => lang('Dark red'),
        'highlight_darkYellow'=> lang('Dark yellow'),
        'highlight_darkGray'=> lang('Dark grey'),
        'highlight_lightGray'=> lang('Light grey'),
        'highlight_black'   => lang('Black'),
        'doc_format_select' => lang('Select text in a paragraph to format it.'),
        'doc_link'          => lang('Link'),
        'doc_link_address'  => lang('Address'),
        'doc_link_hint'     => lang('A web address, an e-mail address or a phone number.'),
        'doc_link_bad'      => lang('Write a web address starting with http:// or https://, an e-mail address or a phone number.'),
        'doc_link_apply'    => lang('Apply'),
        'doc_link_cancel'   => lang('Cancel'),
        'doc_link_remove'   => lang('Remove link'),
        'doc_link_select'   => lang('Select text in a paragraph to link it.'),
        'doc_table'         => lang('Table'),
        'doc_table_choose'  => lang('Choose the size of the table'),
        'doc_table_size'    => lang('{1} columns × {2} rows'),
        'doc_table_row'     => lang('Insert row'),
        'doc_table_column'  => lang('Insert column'),
        'doc_table_row_delete' => lang('Delete row'),
        'doc_table_column_delete' => lang('Delete column'),
        'doc_table_delete'  => lang('Delete table'),
        'doc_table_inside'  => lang('A table is not put inside a new table, row or column. Save first, then add it.'),
        'doc_table_merged'  => lang('This table has merged or uneven cells, so its rows and columns are not changed here.'),
        'doc_table_keep'    => lang('This part of the table holds a picture, a field, a comment, a note or a content control, so it is not deleted here.'),
        'doc_table_full'    => lang('A new table has 1 to {1} rows and 1 to {2} columns.'),
        'doc_table_wide'    => lang('A table has at most {1} columns.'),
        'doc_picture'       => lang('Picture'),
        'doc_picture_choose'=> lang('Choose a picture of this channel or upload one.'),
        'doc_picture_loading'=> lang('Loading pictures...'),
        'doc_picture_none'  => lang('No PNG, JPEG or GIF picture has been posted in this channel.'),
        'doc_picture_upload'=> lang('Upload'),
        'doc_picture_failed'=> lang('The picture could not be read.'),
        'doc_picture_type'  => lang('Only a PNG, JPEG or GIF picture goes into a document.'),
        'doc_picture_large' => lang('A picture over {1} is not put into a document.'),
        'doc_picture_room'  => lang('The pictures put in before a save come to more than {2}.'),
        'doc_picture_inside'=> lang('A picture is not put inside a new table, row or column. Save first, then add it.'),
        'doc_kind'          => lang('Paragraph style'),
        'kind_normal'       => lang('Normal text'),
        'kind_heading1'     => lang('Heading 1'),
        'kind_heading2'     => lang('Heading 2'),
        'kind_heading3'     => lang('Heading 3'),
        'kind_bullet'       => lang('Bulleted list'),
        'kind_number'       => lang('Numbered list'),
        'kind_other'        => lang('Other style'),
        'doc_undo'          => lang('Undo (Ctrl+Z)'),
        'doc_redo'          => lang('Redo (Ctrl+Y)'),
        'doc_delete'        => lang('Delete the paragraph'),
        'doc_restore'       => lang('Keep the paragraph'),
        'doc_pick'          => lang('Click a paragraph first.'),
        'doc_deleted'       => lang('This paragraph is deleted when you save. Press the delete button again to keep it.'),
        'doc_cells'         => lang('Paragraphs in different table cells are not joined.'),
        'doc_shut'          => lang('No paragraph can be added after this one here: it is in a content control, a text box, a merged cell or a tracked change.'),
        'keep_picture'      => lang('This paragraph holds a drawing, a text box or an embedded object, so it is not deleted here.'),
        'keep_section'      => lang('This paragraph ends a section of the document, which holds its page layout, so it is not deleted here.'),
        'keep_cell'         => lang('This is the last paragraph of a table cell, which cannot be left without one, so it is not deleted.'),
        'keep_last'         => lang('This is the last paragraph of the document, so it is not deleted. Its text can be emptied.'),
        'keep_bookmark'     => lang('This paragraph holds one end of a bookmark that reaches into other paragraphs, so it is not deleted here.'),
        'keep_locked'       => lang('This paragraph holds more than text, so it is not deleted here.'),
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
        'csv_delimiter'     => lang('Separator'),
        'csv_comma'         => lang('Comma (,)'),
        'csv_semicolon'     => lang('Semicolon (;)'),
        'csv_tab'           => lang('Tab'),
        'csv_encoding'      => lang('Character set'),
        'csv_utf8'          => lang('UTF-8'),
        'csv_1254'          => lang('Windows-1254 (Turkish)'),
        'slides'            => lang('Slides'),
        'slide'             => lang('Slide {1}'),
        'slide_hidden'      => lang('Hidden slide'),
        'slide_notes'       => lang('Speaker notes'),
        'slide_none'        => lang('The presentation has no slides.'),
        'picture'           => lang('picture'),
    );
}

/**
 * Whichever kind the file is, read for the preview.
 *
 * @param string $path
 * @param string $name   the file's name (for the kind)
 * @param bool   $edit   a workbook or a document also says what may be changed in it
 * @param array  $choice a .csv's separator and character set (pg_office_csv_choice())
 * @return array ok, error, kind, and the kind's own fields
 */
function pg_office_read($path, $name, $edit = false, $choice = array())
{
    $kind = pg_office_preview_kind($name);

    if ($kind === 'xlsx') {
        return array('kind' => 'xlsx') + pg_office_xlsx_read($path, $edit);
    }

    if ($kind === 'docx') {
        return array('kind' => 'docx') + pg_office_docx_read($path, $edit);
    }

    // Only looked at, whether or not editing was asked for.
    if ($kind === 'csv') {
        return array('kind' => 'csv') + pg_office_csv_read($path, $name, $choice);
    }

    if ($kind === 'ods') {
        return array('kind' => 'ods') + pg_office_ods_read($path);
    }

    if ($kind === 'xls') {
        return array('kind' => 'xls') + pg_office_xls_read($path);
    }

    if ($kind === 'pptx') {
        return array('kind' => 'pptx') + pg_office_pptx_read($path);
    }

    return array('ok' => false, 'error' => lang('There is no preview for this kind of file.'), 'kind' => '');
}
