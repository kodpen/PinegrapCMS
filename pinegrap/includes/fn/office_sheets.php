<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The sheets of an Excel workbook added, renamed, deleted and moved on the
 * server, in the file itself (the file window and a channel's preview send
 * one such change as their whole save, through pg_office_xlsx_save()).
 *
 * A new sheet is an empty worksheet put at the end. A rename rewrites the
 * sheet's name wherever a formula, a defined name, a chart, a table or a
 * link location writes it. A delete takes the sheet's part, its relationship,
 * its content type, its defined names and its calculation chain entries out,
 * and is refused while another part points to the sheet. A move changes the
 * order of the workbook's sheets, the defined names local to a sheet and the
 * open sheet following their sheets; it is refused while a formula reaches
 * across several sheets (Sheet1:Sheet3!A1), whose meaning the order sets.
 *
 * What cannot be done safely is refused with its reason and the file stays
 * as it was: a signed file, a password to change it, a protected workbook
 * structure, a sheet holding pictures, comments, tables or other parts on
 * delete, and the last visible sheet. Every other part of the package is
 * carried over as it is.
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
 * The longest name a sheet may have, in characters (Excel's own limit).
 */
define('PG_OFFICE_SHEET_NAME_MAX', 31);

/* ---------------------------------------------------------------------------
   The request and the name
   --------------------------------------------------------------------------- */

/**
 * The sheet change a save asks for, read from the preview's request:
 * [op => sheet_add, to => name], [op => sheet_rename, sheet, name, to => name],
 * [op => sheet_delete, sheet, name] or [op => sheet_move, sheet, name, to => place].
 *
 * @param mixed $change
 * @return array|null action (add|rename|delete|move), sheet, name, to
 */
function pg_office_sheets_request($change)
{
    $actions = array('sheet_add' => 'add', 'sheet_rename' => 'rename', 'sheet_delete' => 'delete', 'sheet_move' => 'move');

    if (!is_array($change) || !isset($change['op']) || !is_string($change['op']) || !isset($actions[$change['op']])) {
        return null;
    }

    $out = array('action' => $actions[$change['op']], 'sheet' => null, 'name' => null, 'to' => null);

    if (in_array($out['action'], array('add', 'rename'), true)) {
        if (!isset($change['to']) || !is_string($change['to'])) {
            return null;
        }

        $out['to'] = $change['to'];
    }

    if ($out['action'] === 'add') {
        return $out;
    }

    if (!isset($change['sheet']) || !is_scalar($change['sheet']) || !preg_match('/^\d{1,5}$/', (string) $change['sheet'])) {
        return null;
    }

    $out['sheet'] = (int) $change['sheet'];
    $out['name'] = (isset($change['name']) && is_string($change['name'])) ? $change['name'] : null;

    if ($out['action'] === 'move') {
        if (!isset($change['to']) || !is_scalar($change['to']) || !preg_match('/^\d{1,5}$/', (string) $change['to'])) {
            return null;
        }

        $out['to'] = (int) $change['to'];
    }

    return $out;
}

/**
 * A sheet name as names are compared: Excel takes Sheet1 and SHEET1 for the
 * same sheet.
 *
 * @param string $name
 * @return string
 */
function pg_office_sheet_name_key($name)
{
    return mb_strtolower((string) $name, 'UTF-8');
}

/**
 * Why a name cannot be given to a sheet, or '' when it can.
 *
 * @param mixed    $name
 * @param string[] $taken the names the workbook's other sheets have
 * @return string '', sheets_name (empty, too long, a character Excel does not take) or sheets_taken
 */
function pg_office_sheet_name_error($name, $taken)
{
    if (!is_string($name) || !mb_check_encoding($name, 'UTF-8')) {
        return 'sheets_name';
    }

    $length = mb_strlen($name, 'UTF-8');

    if (($length < 1) || ($length > PG_OFFICE_SHEET_NAME_MAX) || (trim($name) === '')
        || preg_match('/[\\\\\/?*\[\]:\x00-\x1F\x7F]/', $name)
        || ($name[0] === '\'') || (substr($name, -1) === '\'')
        || (strtolower($name) === 'history')) {
        return 'sheets_name';
    }

    $key = pg_office_sheet_name_key($name);

    foreach ($taken as $other) {
        if (pg_office_sheet_name_key($other) === $key) {
            return 'sheets_taken';
        }
    }

    return '';
}

/**
 * A sheet name (or a First:Last span) as a formula writes it before "!":
 * bare when Excel writes it bare, otherwise in quotes with its quotes
 * doubled. Quotes are always valid; bare is kept for the names that need
 * none, so a renamed formula reads as Excel would have written it.
 *
 * @param string $name
 * @return string
 */
function pg_office_sheet_quote($name)
{
    $bare = preg_match('/^[\p{L}_][\p{L}\p{N}_]*(?::[\p{L}_][\p{L}\p{N}_]*)?$/u', $name);

    foreach (explode(':', $name) as $part) {
        // A name that reads as a cell (A1, R1C1) or a truth value.
        if (preg_match('/^(?:[A-Za-z]{1,3}\d+|[RrCc]\d*|[Rr]\d*[Cc]\d*|true|false)$/i', $part)) {
            $bare = false;
        }
    }

    return $bare ? $name : '\'' . str_replace('\'', '\'\'', $name) . '\'';
}

/* ---------------------------------------------------------------------------
   References to a sheet
   --------------------------------------------------------------------------- */

/**
 * The sheet names a formula writes before "!", looked at one by one: with
 * $new, every one that is $old is renamed. Text in quotes, structured
 * references and other workbooks' sheets ([1]Sheet!, 'C:\[Book.xlsx]Sheet'!)
 * are left as they are.
 *
 * @param string      $formula
 * @param string|null $old   the sheet looked for; null looks only for spans
 * @param string|null $new   its new name; null only looks
 * @param bool        $found set when the formula names $old
 * @param bool        $span  set when the formula reaches across sheets (First:Last!)
 * @return string the formula, renamed
 */
function pg_office_sheets_formula($formula, $old, $new, &$found = null, &$span = null)
{
    $found = false;
    $span = false;
    $letter = '\p{L}\p{N}_.';
    $pattern = '/"(?:[^"]|"")*"'
        . '|(?<![' . $letter . '$\'#])(\'(?:[^\']|\'\')+\'|(?:\[\d+\])?[\p{L}_\\\\][' . $letter . ']*(?::[\p{L}_\\\\][' . $letter . ']*)?)!'
        . '|\[(?:[^\[\]]|\[[^\]]*\])*\]/u';
    $key = ($old === null) ? null : pg_office_sheet_name_key($old);

    $out = preg_replace_callback($pattern, function ($m) use ($key, $new, &$found, &$span) {
        if (!isset($m[1]) || ($m[1] === '')) {
            return $m[0];
        }

        $named = $m[1];

        if ($named[0] === '\'') {
            $named = str_replace('\'\'', '\'', substr($named, 1, -1));
        }

        if (($named === '') || ($named[0] === '[') || (strpos($named, ']') !== false)) {
            return $m[0];
        }

        $names = explode(':', $named);
        $span = $span || (count($names) > 1);
        $hit = false;

        foreach ($names as $index => $one) {
            if (($key !== null) && (pg_office_sheet_name_key($one) === $key)) {
                $hit = true;
                $names[$index] = ($new !== null) ? $new : $one;
            }
        }

        $found = $found || $hit;

        return ($hit && ($new !== null)) ? pg_office_sheet_quote(implode(':', $names)) . '!' : $m[0];
    }, (string) $formula);

    return is_string($out) ? $out : (string) $formula;
}

/**
 * The places a part writes a sheet's name, looked at or renamed: formulas
 * (a cell's, a validation's, a chart's series, a table column's), link
 * locations and a pivot cache's source sheet.
 *
 * @param string      $xml
 * @param string|null $old
 * @param string|null $new
 * @param bool        $found set when the part names $old
 * @param bool        $span  set when one of its formulas reaches across sheets
 * @return string|null the part, renamed; null when it is too large to walk
 */
function pg_office_sheets_part($xml, $old, $new, &$found = null, &$span = null)
{
    $found = false;
    $span = false;

    $formula = function ($text) use ($old, $new, &$found, &$span) {
        $out = pg_office_sheets_formula($text, $old, $new, $hit, $spans);
        $found = $found || $hit;
        $span = $span || $spans;

        return $out;
    };

    $xml = preg_replace_callback('/(<(?:[\w.\-]+:)?(?:f|formula|formula1|formula2|calculatedColumnFormula|totalsRowFormula)(?=[\s>])[^>]*(?<!\/)>)([^<]*)(<\/)/', function ($m) use ($formula) {
        $text = html_entity_decode($m[2], ENT_QUOTES | ENT_XML1, 'UTF-8');
        $out = $formula($text);

        return ($out === $text) ? $m[0] : $m[1] . htmlspecialchars($out, ENT_NOQUOTES | ENT_XML1, 'UTF-8') . $m[3];
    }, (string) $xml);

    if ($xml !== null) {
        $xml = preg_replace_callback('/(\slocation\s*=\s*)(["\'])(.*?)\2/', function ($m) use ($formula) {
            $text = html_entity_decode($m[3], ENT_QUOTES | ENT_XML1, 'UTF-8');
            $out = $formula($text);

            return ($out === $text) ? $m[0] : $m[1] . $m[2] . htmlspecialchars($out, (($m[2] === '"') ? ENT_COMPAT : ENT_QUOTES) | ENT_XML1, 'UTF-8') . $m[2];
        }, $xml);
    }

    if (($xml !== null) && ($old !== null)) {
        $key = pg_office_sheet_name_key($old);

        $xml = preg_replace_callback('/(<(?:[\w.\-]+:)?worksheetSource\b[^>]*?\ssheet\s*=\s*)(["\'])(.*?)\2/', function ($m) use ($key, $new, &$found) {
            if (pg_office_sheet_name_key(html_entity_decode($m[3], ENT_QUOTES | ENT_XML1, 'UTF-8')) !== $key) {
                return $m[0];
            }

            $found = true;

            return ($new === null) ? $m[0] : $m[1] . $m[2] . htmlspecialchars($new, (($m[2] === '"') ? ENT_COMPAT : ENT_QUOTES) | ENT_XML1, 'UTF-8') . $m[2];
        }, $xml);
    }

    return $xml;
}

/**
 * Walks every part that may write a sheet's name (worksheets, chart sheets,
 * charts, pivot caches, tables), but $skip: renames in each with $new, or
 * only looks.
 *
 * @param ZipArchive  $zip
 * @param string|null $old
 * @param string|null $new
 * @param string      $skip  a part left out
 * @param bool        $found set when a part names $old
 * @param bool        $span  set when a formula reaches across sheets
 * @return array|null part name => renamed xml, for the parts that changed; null when one is too large
 */
function pg_office_sheets_walk($zip, $old, $new, $skip, &$found = null, &$span = null)
{
    $found = false;
    $span = false;
    $changed = array();

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $entry = (string) $zip->getNameIndex($index);

        if (($entry === $skip) || !preg_match('#^xl/(?:worksheets|chartsheets|charts|pivotCache|tables)/[^/]+\.xml$#', $entry)) {
            continue;
        }

        $stat = $zip->statIndex($index);

        if (($stat === false) || ((int) $stat['size'] > PG_OFFICE_EDIT_MAX_PART)) {
            return null;
        }

        $xml = (string) pg_zip_entry_contents($zip, $entry, PG_OFFICE_EDIT_MAX_PART, $unread);
        $out = pg_office_sheets_part($xml, $old, $new, $hit, $spans);

        if ($out === null) {
            return null;
        }

        $found = $found || $hit;
        $span = $span || $spans;

        if ($out !== $xml) {
            $changed[$entry] = $out;
        }
    }

    return $changed;
}

/* ---------------------------------------------------------------------------
   The workbook part
   --------------------------------------------------------------------------- */

/**
 * The workbook's sheet elements in their order, with where they are written.
 *
 * @param string $xml xl/workbook.xml
 * @return array|null prefix, start, length (of the sheets element's content), tags, names; null when it has none
 */
function pg_office_sheets_book($xml)
{
    if (!preg_match('/<([A-Za-z_][\w.\-]*:)?workbook(?=[\s>])/', $xml, $root)) {
        return null;
    }

    $prefix = isset($root[1]) ? $root[1] : '';
    $quoted = preg_quote($prefix, '/');

    if (!preg_match('/<' . $quoted . 'sheets(?=[\s>])[^>]*>(.*?)<\/' . $quoted . 'sheets>/s', $xml, $block, PREG_OFFSET_CAPTURE)) {
        return null;
    }

    preg_match_all('/<' . $quoted . 'sheet(?=[\s\/>])(?:[^>"\']|"[^"]*"|\'[^\']*\')*?(?:\/>|>.*?<\/' . $quoted . 'sheet>)/s', $block[1][0], $found);

    $names = array();

    foreach ($found[0] as $tag) {
        $names[] = (string) pg_office_shift_attr($tag, 'name');
    }

    return array('prefix' => $prefix, 'start' => $block[1][1], 'length' => strlen($block[1][0]), 'tags' => $found[0], 'names' => $names);
}

/**
 * A start tag with an attribute set, added when it has none.
 *
 * @param string $tag
 * @param string $name
 * @param string $value
 * @return string
 */
function pg_office_sheets_attr_put($tag, $name, $value)
{
    if (pg_office_shift_attr($tag, $name) !== null) {
        return pg_office_shift_attr_set($tag, $name, $value);
    }

    return preg_replace('/\s*(\/?>)$/', ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8') . '"$1', $tag, 1);
}

/**
 * The workbook part with its sheets written in the order and the form
 * given, and what points at a sheet by its place carried along: a defined
 * name local to a sheet (gone with a deleted one), the open sheet and the
 * first tab shown. With $old and $new the defined names are renamed too.
 *
 * @param string      $xml
 * @param array       $book    pg_office_sheets_book()
 * @param string[]    $tags    the sheet elements as they are to be written
 * @param array       $map     old place => new place, null for a deleted sheet
 * @param int         $instead the place the open sheet takes when it is deleted
 * @param string|null $old
 * @param string|null $new
 * @return string|null
 */
function pg_office_sheets_book_write($xml, $book, $tags, $map, $instead = 0, $old = null, $new = null)
{
    $xml = substr_replace($xml, implode('', $tags), $book['start'], $book['length']);

    $xml = pg_office_shift_elements($xml, $book['prefix'], 'definedName', function ($element, $tag) use ($map, $old, $new) {
        $local = pg_office_shift_attr($tag, 'localSheetId');
        $out = $element;

        if (($local !== null) && ctype_digit($local) && array_key_exists((int) $local, $map)) {
            if ($map[(int) $local] === null) {
                return '';
            }

            $out = pg_office_shift_attr_set($tag, 'localSheetId', (string) $map[(int) $local]) . substr($element, strlen($tag));
        }

        if (($new !== null) && preg_match('/^(<[^>]*>)(.*)(<\/[^>]*>)$/s', $out, $parts)) {
            $text = html_entity_decode($parts[2], ENT_QUOTES | ENT_XML1, 'UTF-8');
            $renamed = pg_office_sheets_formula($text, $old, $new);

            if ($renamed !== $text) {
                $out = $parts[1] . htmlspecialchars($renamed, ENT_NOQUOTES | ENT_XML1, 'UTF-8') . $parts[3];
            }
        }

        return ($out === $element) ? null : $out;
    });

    if ($xml === null) {
        return null;
    }

    // The open sheet follows its sheet; the first tab shown in the tab bar
    // is a scroll position, kept while it is still a sheet.
    $last = count($tags) - 1;

    return pg_office_shift_elements($xml, $book['prefix'], 'workbookView', function ($element, $tag) use ($map, $instead, $last) {
        $new_tag = $tag;
        $now = pg_office_shift_attr($tag, 'activeTab');
        $at = (($now !== null) && ctype_digit($now)) ? (int) $now : 0;

        if (array_key_exists($at, $map)) {
            $to = ($map[$at] === null) ? (int) $instead : (int) $map[$at];

            if ($to !== $at) {
                $new_tag = pg_office_sheets_attr_put($new_tag, 'activeTab', (string) $to);
            }
        }

        $first = pg_office_shift_attr($tag, 'firstSheet');

        if (($first !== null) && ctype_digit($first) && ((int) $first > $last)) {
            $new_tag = pg_office_shift_attr_set($new_tag, 'firstSheet', (string) max(0, $last));
        }

        return ($new_tag === $tag) ? null : $new_tag . substr($element, strlen($tag));
    });
}

/**
 * Is the workbook's structure protected (Review > Protect Workbook)? Its
 * sheets are then not added, renamed, deleted or moved.
 *
 * @param string $xml xl/workbook.xml
 * @return bool
 */
function pg_office_sheets_locked($xml)
{
    return 0 !== preg_match('/<(?:[\w.\-]+:)?workbookProtection\b[^>]*\slockStructure\s*=\s*["\'](?:1|true)["\']/', (string) $xml);
}

/**
 * Whether the preview may offer the workbook's sheets to be changed.
 *
 * @param bool   $signed
 * @param bool   $reserved a password to change the file
 * @param string $workbook xl/workbook.xml
 * @return array ok, why (signed, password, protected)
 */
function pg_office_sheets_edit_info($signed, $reserved, $workbook)
{
    $why = $signed ? 'signed' : ($reserved ? 'password' : (pg_office_sheets_locked($workbook) ? 'protected' : ''));

    return array('ok' => ($why === ''), 'why' => $why);
}

/* ---------------------------------------------------------------------------
   The package
   --------------------------------------------------------------------------- */

/**
 * A relationships or content types part with the elements of one local
 * name whose attribute is one of $values taken out.
 *
 * @param string   $xml
 * @param string   $local     Relationship or Override
 * @param string   $attribute Id or PartName
 * @param string[] $values
 * @return string|null
 */
function pg_office_sheets_drop($xml, $local, $attribute, $values)
{
    $keys = array_map('strtolower', $values);

    return preg_replace_callback('/<(?:[\w.\-]+:)?' . $local . '(?=[\s\/>])(?:[^>"\']|"[^"]*"|\'[^\']*\')*?(?:\/>|>.*?<\/(?:[\w.\-]+:)?' . $local . '>)/s', function ($m) use ($attribute, $keys) {
        $value = pg_office_shift_attr($m[0], $attribute);

        return (($value !== null) && in_array(strtolower($value), $keys, true)) ? '' : $m[0];
    }, (string) $xml);
}

/**
 * A relationships or content types part with one element added before its
 * root's end tag, in the root's prefix.
 *
 * @param string $xml
 * @param string $root    Relationships or Types
 * @param string $element without its prefix, from "<" on
 * @return string|null
 */
function pg_office_sheets_append($xml, $root, $element)
{
    if (!preg_match('/<\/([\w.\-]+:)?' . $root . '\s*>/', $xml, $end, PREG_OFFSET_CAPTURE)) {
        return null;
    }

    $prefix = isset($end[1][0]) ? $end[1][0] : '';

    return substr_replace($xml, '<' . $prefix . substr($element, 1), $end[0][1], 0);
}

/**
 * The calculation chain without a deleted sheet's entries. An entry naming
 * no sheet belongs to the one before it, so every entry kept after a dropped
 * run names its sheet itself.
 *
 * @param ZipArchive $zip
 * @param string     $sheet_id
 * @return string|null|false the new part, '' when no entry is left, null when there is none or nothing changed, false when it cannot be read
 */
function pg_office_sheets_chain($zip, $sheet_id)
{
    if ($zip->locateName('xl/calcChain.xml') === false) {
        return null;
    }

    $xml = pg_zip_entry_contents($zip, 'xl/calcChain.xml', PG_OFFICE_EDIT_MAX_PART, $unread);

    if (!is_string($xml) || !preg_match('/<([A-Za-z_][\w.\-]*:)?calcChain(?=[\s>])/', $xml, $root)) {
        return false;
    }

    $current = '';
    $left = 0;

    $out = pg_office_shift_elements($xml, isset($root[1]) ? $root[1] : '', 'c', function ($element, $tag) use ($sheet_id, &$current, &$left) {
        $own = pg_office_shift_attr($tag, 'i');
        $current = ($own !== null) ? $own : $current;

        if ($current === $sheet_id) {
            return '';
        }

        $left++;

        return null;
    });

    if (($out === null) || !pg_office_xml_well_formed($out)) {
        return false;
    }

    return ($left === 0) ? '' : (($out === $xml) ? null : $out);
}

/**
 * Works out the parts a sheet change writes and the parts it takes out.
 *
 * @param ZipArchive $zip
 * @param array      $op    pg_office_sheets_request()
 * @param callable   $fail
 * @param callable   $spool
 * @return array ok, parts, removed, changed; or a refusal
 */
function pg_office_xlsx_sheets_parts($zip, $op, $fail, $spool)
{
    if (pg_office_signed($zip)) {
        return $fail('signed');
    }

    $sheets = pg_office_xlsx_sheets($zip, $refused);

    if ($refused !== '') {
        return array('ok' => false, 'code' => 'read', 'error' => pg_office_refusal_error($refused), 'size' => 0, 'version' => '', 'changed' => 0);
    }

    $workbook = (string) pg_zip_entry_contents($zip, 'xl/workbook.xml', PG_OFFICE_MAX_DOM, $unread);

    if (pg_office_xlsx_write_reserved($workbook)) {
        return $fail('password');
    }

    if (pg_office_sheets_locked($workbook)) {
        return $fail('sheets_protected');
    }

    $book = pg_office_sheets_book($workbook);

    if (($book === null) || empty($sheets)) {
        return $fail('read');
    }

    $place = -1;

    if ($op['action'] !== 'add') {
        if (!isset($sheets[$op['sheet']]) || (($op['name'] !== null) && ($op['name'] !== $sheets[$op['sheet']]['name']))) {
            return $fail('changed');
        }

        $place = array_search($sheets[$op['sheet']]['name'], $book['names'], true);

        if ($place === false) {
            return $fail('changed');
        }
    }

    $out = array('parts' => array(), 'removed' => array());
    $texts = array();

    if ($op['action'] === 'add') {
        $done = pg_office_sheets_add($zip, $book, $workbook, $op['to'], $sheets, $texts, $out['removed']);
    } elseif ($op['action'] === 'rename') {
        $done = pg_office_sheets_rename($zip, $book, $workbook, $place, $op['to'], $texts);
    } elseif ($op['action'] === 'delete') {
        $done = pg_office_sheets_delete($zip, $book, $workbook, $place, $sheets, $texts, $out['removed']);
    } else {
        if (!isset($sheets[$op['to']])) {
            return $fail('changed');
        }

        $done = pg_office_sheets_move($zip, $book, $workbook, $place, (int) array_search($sheets[$op['to']]['name'], $book['names'], true), $texts);
    }

    if ($done !== '') {
        $named = (in_array($done, array('sheets_name', 'sheets_taken'), true) || ($place < 0)) ? (string) $op['to'] : $book['names'][$place];

        return $fail($done, '', $named);
    }

    foreach ($texts as $name => $xml) {
        if (!pg_office_xml_well_formed($xml)) {
            return $fail('save');
        }

        $file = $spool($xml);

        if ($file === null) {
            return $fail('save');
        }

        $out['parts'][$name] = $file;
    }

    return array('ok' => true, 'parts' => $out['parts'], 'removed' => $out['removed'], 'changed' => 1);
}

/**
 * An empty worksheet put after the last sheet.
 *
 * @param ZipArchive $zip
 * @param array      $book
 * @param string     $workbook
 * @param string     $name
 * @param array      $sheets  pg_office_xlsx_sheets()
 * @param array      $texts   part name => xml, filled
 * @param array      $removed filled (nothing is taken out)
 * @return string '' or a refusal's code
 */
function pg_office_sheets_add($zip, $book, $workbook, $name, $sheets, &$texts, &$removed)
{
    $error = pg_office_sheet_name_error($name, $book['names']);

    if ($error !== '') {
        return $error;
    }

    $rels = (string) pg_zip_entry_contents($zip, 'xl/_rels/workbook.xml.rels', PG_OFFICE_MAX_DOM, $unread);
    $types = (string) pg_zip_entry_contents($zip, '[Content_Types].xml', PG_OFFICE_MAX_DOM, $unread);

    // The new sheet's relationship type, content type and namespaces are
    // the ones the workbook's worksheets already have, transitional or strict.
    $part = '';

    foreach ($sheets as $sheet) {
        if ($sheet['type'] === 'worksheet') {
            $part = $sheet['part'];
            break;
        }
    }

    $type = '';
    $content = '';

    preg_match_all('/<(?:[\w.\-]+:)?(?:Relationship|Override)(?=\s)(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/', $rels . $types, $elements);

    foreach ($elements[0] as $element) {
        $kind = (string) pg_office_shift_attr($element, 'Type');
        $type = (($type === '') && (substr($kind, -10) === '/worksheet')) ? $kind : $type;

        if (($content === '') && (strtolower((string) pg_office_shift_attr($element, 'PartName')) === strtolower('/' . $part))) {
            $content = (string) pg_office_shift_attr($element, 'ContentType');
        }
    }

    preg_match('/\s([\w.\-]+):id\s*=/', implode('', $book['tags']), $id_prefix);
    preg_match('/\sxmlns(?::' . preg_quote(rtrim($book['prefix'], ':'), '/') . ')?\s*=\s*"([^"]*)"/', ($book['prefix'] === '') ? preg_replace('/\sxmlns:[\w.\-]+\s*=\s*"[^"]*"/', '', $workbook) : $workbook, $main);
    $r_namespace = empty($id_prefix) ? '' : (preg_match('/\sxmlns:' . preg_quote($id_prefix[1], '/') . '\s*=\s*"([^"]*)"/', $workbook, $r) ? $r[1] : '');

    if (($part === '') || ($type === '') || ($content === '') || empty($main) || ($r_namespace === '')) {
        return 'sheets_unusual';
    }

    $number = 1;

    while (($zip->locateName('xl/worksheets/sheet' . $number . '.xml') !== false) || (stripos($types, '/xl/worksheets/sheet' . $number . '.xml"') !== false)) {
        $number++;
    }

    preg_match_all('/\sId\s*=\s*"rId(\d+)"/', $rels, $ids);
    preg_match_all('/\ssheetId\s*=\s*"(\d+)"/', implode('', $book['tags']), $sheet_ids);
    $rid = 'rId' . (empty($ids[1]) ? 1 : max(array_map('intval', $ids[1])) + 1);
    $sheet_id = empty($sheet_ids[1]) ? 1 : max(array_map('intval', $sheet_ids[1])) + 1;
    $attr = function ($value) {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    };

    $tags = $book['tags'];
    $tags[] = '<' . $book['prefix'] . 'sheet name="' . $attr($name) . '" sheetId="' . $sheet_id . '" ' . $id_prefix[1] . ':id="' . $rid . '"/>';
    $new_part = 'xl/worksheets/sheet' . $number . '.xml';

    $texts['xl/workbook.xml'] = substr_replace($workbook, implode('', $tags), $book['start'], $book['length']);
    $texts['xl/_rels/workbook.xml.rels'] = pg_office_sheets_append($rels, 'Relationships', '<Relationship Id="' . $rid . '" Type="' . $attr($type) . '" Target="worksheets/sheet' . $number . '.xml"/>');
    $texts['[Content_Types].xml'] = pg_office_sheets_append($types, 'Types', '<Override PartName="/' . $new_part . '" ContentType="' . $attr($content) . '"/>');
    $texts[$new_part] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<worksheet xmlns="' . $attr($main[1]) . '" xmlns:r="' . $attr($r_namespace) . '"><sheetViews><sheetView workbookViewId="0"/></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="15"/><sheetData/><pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/></worksheet>';

    return in_array(null, $texts, true) ? 'sheets_unusual' : '';
}

/**
 * A sheet renamed, and every formula, defined name, chart, table and link
 * location that writes its name.
 *
 * @param ZipArchive $zip
 * @param array      $book
 * @param string     $workbook
 * @param int        $place
 * @param string     $name
 * @param array      $texts
 * @return string '' or a refusal's code
 */
function pg_office_sheets_rename($zip, $book, $workbook, $place, $name, &$texts)
{
    $old = $book['names'][$place];
    $others = $book['names'];
    unset($others[$place]);

    if ($name === $old) {
        return 'none';
    }

    $error = pg_office_sheet_name_error($name, $others);

    if ($error !== '') {
        return $error;
    }

    $changed = pg_office_sheets_walk($zip, $old, $name, '');

    if ($changed === null) {
        return 'sheets_large';
    }

    $tags = $book['tags'];
    $tags[$place] = pg_office_shift_attr_set($tags[$place], 'name', $name);
    $identity = array_keys($book['names']);
    $xml = pg_office_sheets_book_write($workbook, $book, $tags, array_combine($identity, $identity), 0, $old, $name);

    if ($xml === null) {
        return 'sheets_large';
    }

    $texts = $changed;
    $texts['xl/workbook.xml'] = $xml;

    return '';
}

/**
 * A worksheet taken out with its relationship, its content type, its
 * printer settings, its local defined names and its calculation chain
 * entries; the open sheet moves to the nearest visible one.
 *
 * @param ZipArchive $zip
 * @param array      $book
 * @param string     $workbook
 * @param int        $place
 * @param array      $sheets
 * @param array      $texts
 * @param array      $removed
 * @return string '' or a refusal's code
 */
function pg_office_sheets_delete($zip, $book, $workbook, $place, $sheets, &$texts, &$removed)
{
    $name = $book['names'][$place];
    $tag = $book['tags'][$place];
    $sheet = null;

    foreach ($sheets as $one) {
        $sheet = ($one['name'] === $name) ? $one : $sheet;
    }

    if (($sheet === null) || ($sheet['type'] !== 'worksheet')) {
        return 'sheet';
    }

    $hidden = function ($one) {
        return (bool) preg_match('/\sstate\s*=\s*["\'](?:hidden|veryHidden)["\']/i', $one);
    };
    $visible = array();

    foreach ($book['tags'] as $index => $one) {
        if (($index !== $place) && !$hidden($one)) {
            $visible[] = $index;
        }
    }

    if (empty($visible)) {
        return 'sheets_last';
    }

    // Parts of the sheet's own that go with it; any other part would be
    // left behind pointing at nothing.
    $rels_name = preg_replace('#([^/]+)$#', '_rels/$1.rels', $sheet['part']);
    $removed = array($sheet['part']);

    if ($zip->locateName($rels_name) !== false) {
        $removed[] = $rels_name;
    }

    foreach (pg_office_rels($zip, $rels_name, $rels_refused) as $rel) {
        if ($rel['external']) {
            continue;
        }

        if ($rel['type'] !== 'printersettings') {
            return 'sheets_parts';
        }

        $removed[] = pg_office_part_name(dirname($sheet['part']) . '/', $rel['target']);
    }

    if ($rels_refused !== '') {
        return 'sheet';
    }

    if (pg_office_sheets_walk($zip, $name, null, $sheet['part'], $named) === null) {
        return 'sheets_large';
    }

    // A defined name of the workbook, or one local to another sheet, that
    // points here; a name local to this sheet goes with it.
    preg_match_all('/<(?:[\w.\-]+:)?definedName(?=[\s>])([^>]*)>([^<]*)</', $workbook, $defined, PREG_SET_ORDER);

    foreach ($defined as $one) {
        $local = pg_office_shift_attr('<x' . $one[1] . '>', 'localSheetId');

        if ((string) $local !== (string) $place) {
            pg_office_sheets_formula(html_entity_decode($one[2], ENT_QUOTES | ENT_XML1, 'UTF-8'), $name, null, $hit);
            $named = $named || $hit;
        }
    }

    if ($named) {
        return 'sheets_named';
    }

    $map = array();

    foreach (array_keys($book['tags']) as $index) {
        $map[$index] = ($index < $place) ? $index : (($index === $place) ? null : $index - 1);
    }

    // The open sheet, when it is this one, becomes the visible sheet after
    // it, or failing that the one before.
    $instead = $visible[0];

    foreach ($visible as $index) {
        if ($index < $place) {
            $instead = $index;
        } else {
            $instead = $index;
            break;
        }
    }

    $tags = $book['tags'];
    unset($tags[$place]);
    $xml = pg_office_sheets_book_write($workbook, $book, array_values($tags), $map, $map[$instead]);

    if ($xml === null) {
        return 'sheets_large';
    }

    preg_match('/\s[\w.\-]+:id\s*=\s*"([^"]*)"/', $tag, $rid);
    $rels = (string) pg_zip_entry_contents($zip, 'xl/_rels/workbook.xml.rels', PG_OFFICE_MAX_DOM, $unread);
    $types = (string) pg_zip_entry_contents($zip, '[Content_Types].xml', PG_OFFICE_MAX_DOM, $unread);
    $drop_rels = array(isset($rid[1]) ? $rid[1] : '');
    $drop_types = array_map(function ($part) {
        return '/' . $part;
    }, $removed);

    $chain = pg_office_sheets_chain($zip, (string) pg_office_shift_attr($tag, 'sheetId'));

    if ($chain === false) {
        return 'read';
    }

    if ($chain === '') {
        // No entry left: the chain goes, as a workbook without formulas has none.
        $removed[] = 'xl/calcChain.xml';
        $drop_types[] = '/xl/calcChain.xml';

        foreach (pg_office_rels($zip, 'xl/_rels/workbook.xml.rels') as $id => $rel) {
            if ($rel['type'] === 'calcchain') {
                $drop_rels[] = $id;
            }
        }
    } elseif ($chain !== null) {
        $texts['xl/calcChain.xml'] = $chain;
    }

    $texts['xl/workbook.xml'] = $xml;
    $texts['xl/_rels/workbook.xml.rels'] = pg_office_sheets_drop($rels, 'Relationship', 'Id', $drop_rels);
    $texts['[Content_Types].xml'] = pg_office_sheets_drop($types, 'Override', 'PartName', $drop_types);

    // The sheet that becomes the open one is marked selected in its view.
    $active = preg_match('/<(?:[\w.\-]+:)?workbookView\b[^>]*\sactiveTab\s*=\s*["\'](\d+)["\']/', $workbook, $tab) ? (int) $tab[1] : 0;

    if ($active === $place) {
        foreach ($sheets as $one) {
            $stat = $zip->statName($one['part']);

            if (($one['name'] !== $book['names'][$instead]) || ($stat === false) || ((int) $stat['size'] > PG_OFFICE_EDIT_MAX_PART)) {
                continue;
            }

            $part = (string) pg_zip_entry_contents($zip, $one['part'], PG_OFFICE_EDIT_MAX_PART, $unread);
            $marked = preg_replace_callback('/<(?:[\w.\-]+:)?sheetView(?=[\s\/>])[^>]*>/', function ($m) {
                return pg_office_sheets_attr_put($m[0], 'tabSelected', '1');
            }, $part, 1);

            // A sheet with no view of its own gets one, in the place the
            // schema gives it (before the format, the columns and the cells).
            if (($marked === $part) && !preg_match('/<(?:[\w.\-]+:)?sheetViews?(?=[\s\/>])/', $part)) {
                $marked = preg_replace('/<([\w.\-]+:)?(?:sheetFormatPr|cols|sheetData)(?=[\s\/>])/', '<$1sheetViews><$1sheetView tabSelected="1" workbookViewId="0"/></$1sheetViews>$0', $part, 1);
            }

            if (is_string($marked) && ($marked !== $part)) {
                $texts[$one['part']] = $marked;
            }
        }
    }

    return in_array(null, $texts, true) ? 'sheets_unusual' : '';
}

/**
 * A sheet moved to another place in the workbook's order.
 *
 * @param ZipArchive $zip
 * @param array      $book
 * @param string     $workbook
 * @param int        $place
 * @param int        $to the place it takes
 * @param array      $texts
 * @return string '' or a refusal's code
 */
function pg_office_sheets_move($zip, $book, $workbook, $place, $to, &$texts)
{
    if ($place === $to) {
        return 'none';
    }

    // A reference across sheets (Sheet1:Sheet3!A1) takes in the sheets that
    // lie between its ends: a move would change what it adds up.
    if (pg_office_sheets_walk($zip, null, null, '', $named, $span) === null) {
        return 'sheets_large';
    }

    preg_match_all('/<(?:[\w.\-]+:)?definedName(?=[\s>])[^>]*>([^<]*)</', $workbook, $defined);

    foreach ($defined[1] as $text) {
        pg_office_sheets_formula(html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8'), null, null, $hit, $spans);
        $span = $span || $spans;
    }

    if ($span) {
        return 'sheets_span';
    }

    $order = array_keys($book['tags']);
    array_splice($order, $place, 1);
    array_splice($order, $to, 0, array($place));

    $tags = array();
    $map = array();

    foreach ($order as $index => $old) {
        $tags[] = $book['tags'][$old];
        $map[$old] = $index;
    }

    $xml = pg_office_sheets_book_write($workbook, $book, $tags, $map);

    if ($xml === null) {
        return 'sheets_large';
    }

    $texts['xl/workbook.xml'] = $xml;

    return '';
}

/**
 * Writes one sheet change into a workbook on disk, under the file's lock,
 * as pg_office_xlsx_save() writes cells.
 *
 * @param string   $path
 * @param array    $op        pg_office_sheets_request()
 * @param string   $version
 * @param string   $directory '' for data/temp
 * @param callable $fail
 * @return array ok, code, error, size, version, changed
 */
function pg_office_xlsx_sheets_save($path, $op, $version, $directory, $fail)
{
    $directory = ($directory !== '') ? $directory : pg_office_edit_temp_directory();

    if (!is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }

    $lock = pg_office_edit_lock($path, $directory);

    if ($lock === null) {
        return $fail('busy');
    }

    $result = pg_office_save_package($path, $version, $directory, $fail, function ($zip, $spool) use ($op, $fail) {
        return pg_office_xlsx_sheets_parts($zip, $op, $fail, $spool);
    });

    pg_office_edit_unlock($lock);

    return $result;
}

/**
 * The message for a refused sheet change.
 *
 * @param string $code
 * @param string $sheet the sheet's name, or the name asked for
 * @return string
 */
function pg_office_sheets_message($code, $sheet)
{
    $vars = array($sheet);

    switch ($code) {
        case 'sheets_protected':
            return lang('The workbook\'s structure is protected, so its sheets are not added, renamed, deleted or moved here.');
        case 'sheets_name':
            return lang('A sheet name is 1 to 31 characters long, without \\ / ? * [ ] : and not starting or ending with an apostrophe.');
        case 'sheets_taken':
            return lang(array('string' => 'Another sheet is already named {var:1}.', 'vars' => $vars));
        case 'sheets_last':
            return lang(array('string' => 'Sheet {var:1} is the last visible sheet, and a workbook keeps at least one.', 'vars' => $vars));
        case 'sheets_named':
            return lang(array('string' => 'A formula, a defined name, a chart or a pivot table points to sheet {var:1}, so it is not deleted here.', 'vars' => $vars));
        case 'sheets_parts':
            return lang(array('string' => 'Sheet {var:1} has pictures, charts, comments, a table or other parts, so it is deleted in a spreadsheet program.', 'vars' => $vars));
        case 'sheets_span':
            return lang('A formula of the workbook reaches across several sheets (such as Sheet1:Sheet3!A1), so the order of its sheets is not changed here.');
        case 'sheets_large':
            return lang('The workbook is too large for its sheets to be changed here.');
        case 'sheets_unusual':
            return lang('The workbook\'s sheets cannot be changed here.');
    }

    return lang('The file could not be saved.');
}
