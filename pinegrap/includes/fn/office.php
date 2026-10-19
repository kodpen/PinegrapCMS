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
 * at a size a page can show.
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
 * How much of a workbook the preview shows: rows and columns a sheet, cells
 * across every sheet.
 */
define('PG_OFFICE_MAX_ROWS', 2000);
define('PG_OFFICE_MAX_COLUMNS', 200);
define('PG_OFFICE_MAX_CELLS', 120000);

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
 * @param ZipArchive $zip
 * @param string     $name
 * @return DOMDocument|null
 */
function pg_office_xml($zip, $name)
{
    $xml = $zip->getFromName($name);

    if (($xml === false) || ($xml === '')) {
        return null;
    }

    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return $loaded ? $dom : null;
}

/**
 * The relationships of a part: id => [target, type word, external].
 *
 * @param ZipArchive $zip
 * @param string     $name the .rels part
 * @return array
 */
function pg_office_rels($zip, $name)
{
    $out = array();
    $dom = pg_office_xml($zip, $name);

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
 * @return array[]
 */
function pg_office_xlsx_sheets($zip)
{
    $out = array();
    $book = pg_office_xml($zip, 'xl/workbook.xml');

    if ($book === null) {
        return $out;
    }

    $rels = pg_office_rels($zip, 'xl/_rels/workbook.xml.rels');

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
 * The shared string table, as a list.
 *
 * @param string $path the package on disk
 * @param string $part '' when the workbook has none
 * @return string[]
 */
function pg_office_xlsx_strings($path, $part)
{
    $strings = array();

    if ($part === '') {
        return $strings;
    }

    pg_office_zip_stream();

    $reader = new XMLReader();

    if (!$reader->open('zip://' . $path . '#' . $part, null, LIBXML_NONET)) {
        return $strings;
    }

    while ($reader->read()) {
        if (($reader->nodeType === XMLReader::ELEMENT) && ($reader->name === 'si')) {
            $strings[] = $reader->readString();
        }
    }

    $reader->close();

    return $strings;
}

/**
 * The cell style numbers that mean "a date": the built-in date and time
 * formats, and the operator's own whose pattern names a day, month or year.
 *
 * @param ZipArchive $zip
 * @return array style index => true
 */
function pg_office_xlsx_date_styles($zip)
{
    $dates = array();
    $styles = pg_office_xml($zip, 'xl/styles.xml');

    if ($styles === null) {
        return $dates;
    }

    $custom = array();

    foreach ($styles->getElementsByTagName('numFmt') as $format) {
        $code = strtolower((string) $format->getAttribute('formatCode'));
        $code = preg_replace('/"[^"]*"/', '', $code);
        $code = preg_replace('/\[[^\]]*\]/', '', $code);

        if (preg_match('/[dmyhs]/', $code)) {
            $custom[(int) $format->getAttribute('numFmtId')] = true;
        }
    }

    $index = 0;

    foreach ($styles->getElementsByTagName('cellXfs') as $xfs) {
        foreach ($xfs->getElementsByTagName('xf') as $xf) {
            $format_id = (int) $xf->getAttribute('numFmtId');

            if ((($format_id >= 14) && ($format_id <= 22)) || (($format_id >= 45) && ($format_id <= 47)) || isset($custom[$format_id])) {
                $dates[$index] = true;
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
 * The rows of one sheet, as lists of strings, cut at the limits.
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

    if (!$reader->open('zip://' . $path . '#' . $part, null, LIBXML_NONET)) {
        return $rows;
    }

    $previous = libxml_use_internal_errors(true);

    while ($reader->read()) {
        if (($reader->nodeType !== XMLReader::ELEMENT) || ($reader->name !== 'row')) {
            continue;
        }

        if ((count($rows) >= PG_OFFICE_MAX_ROWS) || ($cells_left <= 0)) {
            $truncated = true;
            break;
        }

        $row_xml = simplexml_load_string($reader->readOuterXml());

        if ($row_xml === false) {
            continue;
        }

        $row = array();
        $waiting = array();

        foreach ($row_xml->c as $cell) {
            $column = pg_office_xlsx_column((string) $cell['r']);

            if ($column >= PG_OFFICE_MAX_COLUMNS) {
                $truncated = true;
                break;
            }

            $type = (string) $cell['t'];
            $value = '';

            switch ($type) {
                case 's':
                    $index = (int) $cell->v;
                    $value = isset($strings[$index]) ? $strings[$index] : '';
                    break;

                case 'inlineStr':
                    $value = isset($cell->is) ? (string) $cell->is->t : '';

                    if (($value === '') && isset($cell->is->r)) {
                        foreach ($cell->is->r as $run) {
                            $value .= (string) $run->t;
                        }
                    }
                    break;

                case 'b':
                    $value = (((string) $cell->v) === '1') ? 'TRUE' : 'FALSE';
                    break;

                case 'str':
                case 'e':
                    $value = (string) $cell->v;
                    break;

                default:
                    $value = (string) $cell->v;

                    if (($value !== '') && is_numeric($value)) {
                        $value = isset($date_styles[(int) $cell['s']]) ? pg_office_xlsx_date($value, $date1904) : pg_office_xlsx_number($value);
                    }
            }

            while (count($row) < $column) {
                $row[] = '';
            }

            $row[$column] = $value;

            // A formula with no result stored: the program that wrote the
            // file did not work it out (or an edit here took the stale one
            // away), and a spreadsheet program will when it opens the file.
            if (($value === '') && isset($cell->f)) {
                $waiting[] = $column;
            }
        }

        // The row number the sheet gives, so an empty stretch stays visible
        // as a gap rather than closing up.
        $entry = array('n' => (int) $row_xml['r'], 'cells' => $row);

        if (!empty($waiting)) {
            $entry['calc'] = $waiting;
        }

        $rows[] = $entry;
        $cells_left -= max(1, count($row));
    }

    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $reader->close();

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
    $zip = pg_office_open($path, $error);

    if ($zip === null) {
        return array('ok' => false, 'error' => $error, 'sheets' => array(), 'truncated' => false);
    }

    $sheets = pg_office_xlsx_sheets($zip);
    $date_styles = pg_office_xlsx_date_styles($zip);
    $strings_part = ($zip->locateName('xl/sharedStrings.xml') !== false) ? 'xl/sharedStrings.xml' : '';
    $date1904 = function_exists('pg_office_xlsx_date1904') && pg_office_xlsx_date1904((string) $zip->getFromName('xl/workbook.xml'));

    if (empty($sheets)) {
        $zip->close();

        return array('ok' => false, 'error' => lang('The file could not be read.'), 'sheets' => array(), 'truncated' => false);
    }

    $strings = pg_office_xlsx_strings($path, $strings_part);
    $cells_left = PG_OFFICE_MAX_CELLS;
    $out = array();
    $any_cut = false;
    $edit = $edit && function_exists('pg_office_xlsx_sheet_edit_info');
    $signed = $edit && pg_office_signed($zip);
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
            $entry['edit'] = $signed
                ? array('ok' => false, 'why' => 'signed')
                : pg_office_xlsx_sheet_edit_info($path, $zip, $sheet, $last_row, $styles);
        }

        $out[] = $entry;
    }

    $zip->close();

    $book = array('ok' => true, 'error' => '', 'sheets' => $out, 'truncated' => $any_cut);

    if ($edit) {
        $book['version'] = pg_office_version($path);
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
 * @return array
 */
function pg_office_docx_state($zip)
{
    $lists = array();
    $abstract = array();
    $numbering = pg_office_xml($zip, 'word/numbering.xml');

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
    $style_sheet = pg_office_xml($zip, 'word/styles.xml');

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

    return array(
        'zip'         => $zip,
        'rels'        => pg_office_rels($zip, 'word/_rels/document.xml.rels'),
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

    $bytes = $state['zip']->getFromName($part);

    if (($bytes === false) || ($bytes === '') || (@getimagesizefromstring($bytes) === false)) {
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

                if (($props['list'] === '') && isset($state['styles'][$props['style']])) {
                    $props['list'] = $state['styles'][$props['style']]['list'];
                    $props['level'] = $state['styles'][$props['style']]['level'];
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
                        $html .= '<' . $format . ' class="office-list"><li class="office-lvl-' . (int) $props['level'] . '">';
                    } else {
                        $html .= '</li><li class="office-lvl-' . (int) $props['level'] . '">';
                    }

                    $html .= ($inner === '') ? '&nbsp;' : $inner;
                    break;
                }

                $close_list();

                $tag = pg_office_docx_block_tag($props['style']);
                $style = ($props['align'] !== '') ? ' style="text-align:' . $props['align'] . '"' : '';

                $html .= '<' . $tag . $style . '>' . (($inner === '') ? '&nbsp;' : $inner) . '</' . $tag . '>';
                break;

            case 'tbl':
                $close_list();
                $html .= '<table class="office-table">';

                foreach ($child->childNodes as $row) {
                    if (!($row instanceof DOMElement) || ($row->localName !== 'tr')) {
                        continue;
                    }

                    $html .= '<tr>';

                    foreach ($row->childNodes as $cell) {
                        if (!($cell instanceof DOMElement) || ($cell->localName !== 'tc')) {
                            continue;
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
 * A document as the preview draws it: a safe subset of HTML (headings,
 * paragraphs, lists, tables, bold and the like, links, inlined pictures),
 * made here from the package alone, with nothing the file wrote passing
 * through unescaped.
 *
 * @param string $path
 * @return array ok, error, html, truncated, images_lost
 */
function pg_office_docx_read($path)
{
    $zip = pg_office_open($path, $error);

    if ($zip === null) {
        return array('ok' => false, 'error' => $error, 'html' => '', 'truncated' => false, 'images_lost' => 0);
    }

    $document = pg_office_xml($zip, 'word/document.xml');

    if ($document === null) {
        $zip->close();

        return array('ok' => false, 'error' => lang('The file could not be read.'), 'html' => '', 'truncated' => false, 'images_lost' => 0);
    }

    $state = pg_office_docx_state($zip);
    $html = '';

    foreach ($document->getElementsByTagNameNS('*', 'body') as $body) {
        pg_office_docx_blocks($state, $body, $html);
        break;
    }

    $zip->close();

    return array('ok' => true, 'error' => '', 'html' => $html, 'truncated' => $state['cut'], 'images_lost' => $state['images_lost']);
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
 * and those of a workbook offered for editing (why a cell or a sheet is not
 * offered, the hint, the count of changes).
 *
 * @return array
 */
function pg_office_view_strings()
{
    return array(
        'hidden'            => lang('Hidden sheet'),
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
        'sheet_kind'        => lang('This sheet has no cells to change.'),
        'sheet_large'       => lang('This sheet is too large to change here.'),
        'sheet_unusual'     => lang('This sheet cannot be changed here.'),
    );
}

/**
 * Whichever kind the file is, read for the preview.
 *
 * @param string $path
 * @param string $name the file's name (for the kind)
 * @param bool   $edit a workbook also says which cells may be changed
 * @return array ok, error, kind, and the kind's own fields
 */
function pg_office_read($path, $name, $edit = false)
{
    $kind = pg_office_kind($name);

    if ($kind === 'xlsx') {
        return array('kind' => 'xlsx') + pg_office_xlsx_read($path, $edit);
    }

    if ($kind === 'docx') {
        return array('kind' => 'docx') + pg_office_docx_read($path);
    }

    return array('ok' => false, 'error' => lang('There is no preview for this kind of file.'), 'kind' => '');
}
