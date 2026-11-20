<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Rows and columns of an Excel workbook inserted and deleted on the server,
 * in the file itself (the file window and a channel's preview send one such
 * change as their whole save, through pg_office_xlsx_save()).
 *
 * Every row or cell past the line moves by one, and with it the references
 * in the sheet's own formulas, its merged ranges, its dimension, its views'
 * selection, its column widths and page breaks, its links and the sheet's
 * entries in the calculation chain. A reference to a deleted cell becomes
 * #REF!, as a spreadsheet program writes it. Nothing is worked out: the
 * formulas lose their stored results, as after a cell edit.
 *
 * What cannot be moved safely is refused with its reason and the file stays
 * as it was: a table or a pivot table on the sheet, a defined name or another
 * part (sheet, chart, pivot cache, table) pointing at it, a merge, an array
 * formula or a filled (shared) formula across the line, conditional
 * formatting, data validation, a filter, drawings and comments, and a
 * protected sheet. Every other part of the package is carried over as it is.
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
 * The last row and the last column a sheet has (Excel's grid).
 */
define('PG_OFFICE_SHIFT_ROWS', 1048576);
define('PG_OFFICE_SHIFT_COLUMNS', 16384);

/**
 * Children of a sheet that carry references this file does not move, by the
 * reason a refusal gives.
 */
function pg_office_shift_refused_parts()
{
    return array(
        'conditionalFormatting' => 'shift_format',
        'dataValidations' => 'shift_validation',
        'autoFilter' => 'shift_filter',
        'sortState' => 'shift_filter',
        'drawing' => 'shift_drawing',
        'legacyDrawing' => 'shift_drawing',
        'legacyDrawingHF' => 'shift_drawing',
        'drawingHF' => 'shift_drawing',
        'oleObjects' => 'shift_drawing',
        'controls' => 'shift_drawing',
        'tableParts' => 'shift_table',
        'protectedRanges' => 'shift_part',
        'scenarios' => 'shift_part',
        'dataConsolidate' => 'shift_part',
        'customSheetViews' => 'shift_part',
        'cellWatches' => 'shift_part',
        'smartTags' => 'shift_part',
        'webPublishItems' => 'shift_part',
    );
}

/* ---------------------------------------------------------------------------
   One line moved
   --------------------------------------------------------------------------- */

/**
 * The change a save asks for, read from the preview's request:
 * [sheet, name, op => insert|delete, row => 7] or [..., column => "C"].
 *
 * @param mixed $change
 * @return array|null sheet, name, axis (row|column), at (1-based), insert (bool)
 */
function pg_office_shift_request($change)
{
    if (!is_array($change) || !isset($change['sheet'], $change['op']) || !is_scalar($change['sheet'])
        || !in_array($change['op'], array('insert', 'delete'), true)) {
        return null;
    }

    if (isset($change['row']) && !isset($change['column'])) {
        $at = is_numeric($change['row']) ? (int) $change['row'] : 0;
        $axis = 'row';

        if (($at < 1) || ($at > PG_OFFICE_SHIFT_ROWS) || ((string) $at !== (string) $change['row'])) {
            return null;
        }
    } elseif (isset($change['column']) && !isset($change['row']) && is_string($change['column'])) {
        $axis = 'column';
        $at = pg_office_shift_column_number($change['column']);

        if (($at < 1) || ($at > PG_OFFICE_SHIFT_COLUMNS) || !preg_match('/^[A-Za-z]{1,3}$/', $change['column'])) {
            return null;
        }
    } else {
        return null;
    }

    return array(
        'sheet' => (int) $change['sheet'],
        'name' => isset($change['name']) ? (string) $change['name'] : null,
        'axis' => $axis,
        'at' => $at,
        'insert' => ($change['op'] === 'insert'),
    );
}

/**
 * A column's letters as its number, A being 1.
 *
 * @param string $letters
 * @return int 0 when they are no column of the grid
 */
function pg_office_shift_column_number($letters)
{
    $cell = pg_office_xlsx_cell(strtoupper((string) $letters) . '1');

    return ($cell === null) ? 0 : $cell[1] + 1;
}

/**
 * Where a row or column number goes.
 *
 * @param int   $index 1-based
 * @param array $op    axis, at, insert
 * @return int 0 when it is deleted, past the grid's end when pushed off it
 */
function pg_office_shift_index($index, $op)
{
    if ($op['insert']) {
        return ($index >= $op['at']) ? $index + 1 : $index;
    }

    if ($index === $op['at']) {
        return 0;
    }

    return ($index > $op['at']) ? $index - 1 : $index;
}

/**
 * Where a span of rows or columns goes, the way a spreadsheet program moves
 * a range: an insert inside it widens it, a delete inside it narrows it.
 *
 * @param int   $first
 * @param int   $last
 * @param array $op
 * @return array|null [first, last]; null when every row or column of it is deleted
 */
function pg_office_shift_span($first, $last, $op)
{
    $end = ($op['axis'] === 'row') ? PG_OFFICE_SHIFT_ROWS : PG_OFFICE_SHIFT_COLUMNS;

    if ($op['insert']) {
        $first = ($first >= $op['at']) ? $first + 1 : $first;
        $last = ($last >= $op['at']) ? $last + 1 : $last;

        return array(min($first, $end), min($last, $end));
    }

    $first = ($first > $op['at']) ? $first - 1 : $first;
    $last = ($last >= $op['at']) ? $last - 1 : $last;

    return ($last < $first) ? null : array($first, $last);
}

/**
 * Whether a span lies across the line: an insert between its first and
 * last row or column, a delete of one of several.
 *
 * @param int   $first
 * @param int   $last
 * @param array $op
 * @return bool
 */
function pg_office_shift_crosses($first, $last, $op)
{
    if ($op['insert']) {
        return ($first < $op['at']) && ($op['at'] <= $last);
    }

    return ($first <= $op['at']) && ($op['at'] <= $last) && ($first !== $last);
}

/**
 * A reference moved: a cell (B7, $B$7), a range of cells (A1:C9), of whole
 * columns (A:C) or of whole rows (3:9), as a sheet's attributes and formulas
 * write them.
 *
 * @param string $ref
 * @param array  $op
 * @return string|null|false the new reference; null when deleted; false when not a reference
 */
function pg_office_shift_ref($ref, $op)
{
    $part = '(\$?)([A-Za-z]{0,3})(\$?)(\d*)';

    if (!preg_match('/^' . $part . '(?::' . $part . ')?$/', $ref, $m)) {
        return false;
    }

    $ends = array(array($m[1], strtoupper($m[2]), $m[3], $m[4]));

    if (isset($m[5])) {
        $ends[] = array($m[5], strtoupper($m[6]), $m[7], $m[8]);
    }

    // Both ends of one kind: cells, columns alone or rows alone.
    $kind = (($ends[0][1] !== '') ? 'c' : '') . (($ends[0][3] !== '') ? 'r' : '');

    foreach ($ends as $end) {
        if (((($end[1] !== '') ? 'c' : '') . (($end[3] !== '') ? 'r' : '')) !== $kind) {
            return false;
        }
    }

    if (($kind === '') || (($kind !== 'cr') && (count($ends) === 1)) || (($kind === 'c') && ($ends[0][2] !== ''))) {
        return false;
    }

    $axis = ($op['axis'] === 'row') ? 3 : 1;
    $numbers = array();

    foreach ($ends as $end) {
        if ($end[$axis] === '') {
            // A range of whole columns does not move with a row, nor rows
            // with a column.
            return $ref;
        }

        $number = ($axis === 3) ? (int) $end[3] : pg_office_shift_column_number($end[1]);

        if (($number < 1) || ($number > (($axis === 3) ? PG_OFFICE_SHIFT_ROWS : PG_OFFICE_SHIFT_COLUMNS))) {
            return false;
        }

        $numbers[] = $number;
    }

    if (count($ends) === 1) {
        $moved = pg_office_shift_index($numbers[0], $op);

        if (($moved === 0) || ($moved > (($axis === 3) ? PG_OFFICE_SHIFT_ROWS : PG_OFFICE_SHIFT_COLUMNS))) {
            return null;
        }

        $span = array($moved, $moved);
    } else {
        $span = pg_office_shift_span(min($numbers), max($numbers), $op);

        if ($span === null) {
            return null;
        }

        // Written in the order the range was: B9:B3 stays upside down.
        if ($numbers[0] > $numbers[1]) {
            $span = array($span[1], $span[0]);
        }
    }

    $out = array();

    foreach ($ends as $index => $end) {
        if ($axis === 3) {
            $end[3] = (string) $span[$index];
        } else {
            $end[1] = pg_office_xlsx_column_name($span[$index] - 1);
        }

        $out[] = $end[0] . $end[1] . $end[2] . $end[3];
    }

    return implode(':', $out);
}

/**
 * A list of ranges (an sqref: "A1:B2 D4") moved; deleted ones are left out.
 *
 * @param string $list
 * @param array  $op
 * @return string|null|false '' when every range is deleted; false when one is not a reference
 */
function pg_office_shift_ref_list($list, $op)
{
    $out = array();

    foreach (preg_split('/\s+/', trim($list), -1, PREG_SPLIT_NO_EMPTY) as $ref) {
        $moved = pg_office_shift_ref($ref, $op);

        if ($moved === false) {
            return false;
        }

        if ($moved !== null) {
            $out[] = $moved;
        }
    }

    return implode(' ', $out);
}

/**
 * The first and last row or column of a range along the line's axis.
 *
 * @param string $ref
 * @param string $axis
 * @return array|null [first, last]
 */
function pg_office_shift_extent($ref, $axis)
{
    $range = pg_office_xlsx_range(strtoupper((string) $ref));

    if ($range === null) {
        return null;
    }

    // [first row, first column, last row, last column], columns 0-based.
    return ($axis === 'row') ? array($range[0], $range[2]) : array($range[1] + 1, $range[3] + 1);
}

/* ---------------------------------------------------------------------------
   Formulas
   --------------------------------------------------------------------------- */

/**
 * A formula of the sheet being changed, with its references to this sheet
 * moved. Text in quotes, structured references in brackets, references to
 * other sheets and to other workbooks are left as they are.
 *
 * $spread is how far a filled (shared) formula reaches along the axis from
 * its first cell: a relative reference then names a different row for each
 * cell, and the formula is refused when those rows lie on both sides of the
 * line, as the cells would no longer point where they did.
 *
 * @param string $formula as the cell holds it, without "="
 * @param array  $op
 * @param string $sheet   the sheet's name
 * @param int    $spread
 * @return string|null the new formula; null when it cannot be moved (a 3D reference through this sheet, a shared formula across the line)
 */
function pg_office_shift_formula($formula, $op, $sheet, $spread = 0)
{
    $letter = '\p{L}\p{N}_.';
    $name = '(?:\'(?:[^\']|\'\')+\'|(?:\[\d+\])?[\p{L}_\\\\][' . $letter . ']*(?::[\p{L}_\\\\][' . $letter . ']*)?)';
    $cell = '\$?[A-Za-z]{1,3}\$?\d+';
    $pattern = '/"(?:[^"]|"")*"'
        . '|(?<![' . $letter . '$\'])(?<prefix>' . $name . '!)?(?<ref>' . $cell . '(?::' . $cell . ')?|\$?[A-Za-z]{1,3}:\$?[A-Za-z]{1,3}|\$?\d+:\$?\d+)(?![' . $letter . '(!\[])'
        . '|\[(?:[^\[\]]|\[[^\]]*\])*\]/u';

    $refused = false;
    $own = mb_strtolower($sheet, 'UTF-8');

    $out = preg_replace_callback($pattern, function ($m) use ($op, $own, $spread, &$refused) {
        if (!isset($m['ref']) || ($m['ref'] === '')) {
            return $m[0];
        }

        $prefix = isset($m['prefix']) ? $m['prefix'] : '';

        if ($prefix !== '') {
            $named = substr($prefix, 0, -1);

            if ($named[0] === '\'') {
                $named = str_replace('\'\'', '\'', substr($named, 1, -1));
            }

            // Another workbook's sheet is never this one.
            if ($named[0] === '[') {
                return $m[0];
            }

            $names = explode(':', mb_strtolower($named, 'UTF-8'));

            if (!in_array($own, $names, true)) {
                return $m[0];
            }

            if (count($names) > 1) {
                $refused = true;
                return $m[0];
            }
        }

        $moved = pg_office_shift_ref($m['ref'], $op);

        // A name that looks like a reference but is past the grid.
        if ($moved === false) {
            return $m[0];
        }

        if ($spread > 0) {
            foreach (explode(':', $m['ref']) as $end) {
                preg_match('/^(\$?)([A-Za-z]*)(\$?)(\d*)$/', $end, $part);
                $absolute = ($op['axis'] === 'row') ? $part[3] : $part[1];
                $number = ($op['axis'] === 'row') ? (int) $part[4] : pg_office_shift_column_number($part[2]);

                if (($absolute === '') && ($number > 0) && pg_office_shift_crosses($number, $number + $spread, $op)) {
                    $refused = true;
                }
            }
        }

        return $prefix . (($moved === null) ? '#REF!' : $moved);
    }, (string) $formula);

    return ($refused || !is_string($out)) ? null : $out;
}

/* ---------------------------------------------------------------------------
   Tags and attributes
   --------------------------------------------------------------------------- */

/**
 * An attribute's value in a start tag, decoded.
 *
 * @param string $tag
 * @param string $name
 * @return string|null
 */
function pg_office_shift_attr($tag, $name)
{
    if (!preg_match('/\s' . preg_quote($name, '/') . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/', $tag, $m)) {
        return null;
    }

    return html_entity_decode(isset($m[2]) ? $m[2] : $m[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/**
 * A start tag with an attribute given a new value (null takes it out).
 *
 * @param string      $tag
 * @param string      $name
 * @param string|null $value
 * @return string
 */
function pg_office_shift_attr_set($tag, $name, $value)
{
    $pattern = '/\s' . preg_quote($name, '/') . '\s*=\s*(?:"[^"]*"|\'[^\']*\')/';
    $text = ($value === null) ? '' : ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8') . '"';

    return preg_replace_callback($pattern, function () use ($text) {
        return $text;
    }, $tag, 1);
}

/**
 * Every element of one local name in a part, given to $edit as its start
 * tag (with its content and end tag when it has them). $edit answers the new
 * markup, '' to take the element out, or null to leave it.
 *
 * @param string   $xml
 * @param string   $prefix
 * @param string   $local
 * @param callable $edit   function (string $element, string $tag): ?string
 * @return string|null null when an element is not closed
 */
function pg_office_shift_elements($xml, $prefix, $local, $edit)
{
    $quoted = preg_quote($prefix . $local, '/');
    $pattern = '/<' . $quoted . '(?=[\s>\/])(?:[^>\/]|\/(?!>))*+(?:\/>|>(?:(?!<\/' . $quoted . '>).)*+<\/' . $quoted . '>)/s';

    $out = preg_replace_callback($pattern, function ($m) use ($edit) {
        $tag = preg_match('/^<[^>]*>/', $m[0], $start) ? $start[0] : $m[0];
        $new = $edit($m[0], $tag);

        return ($new === null) ? $m[0] : $new;
    }, $xml);

    return is_string($out) ? $out : null;
}

/* ---------------------------------------------------------------------------
   The sheet
   --------------------------------------------------------------------------- */

/**
 * A sheet part with one row or column inserted or deleted.
 *
 * @param string $xml
 * @param array  $op
 * @param string $sheet the sheet's name, which its own formulas may write
 * @return array ok, xml, error (a pg_office_edit_message() code), cell
 */
function pg_office_xlsx_shift_sheet($xml, $op, $sheet)
{
    $fail = function ($error, $cell = '') {
        return array('ok' => false, 'xml' => '', 'error' => $error, 'cell' => $cell);
    };

    $xml = (string) $xml;

    if ((strpos($xml, '<!--') !== false) || (strpos($xml, '<![CDATA[') !== false) || (stripos($xml, '<!DOCTYPE') !== false)
        || !pg_office_xlsx_utf8_part(substr($xml, 0, 200))
        || !preg_match('/<([A-Za-z_][\w.\-]*:)?worksheet(?=[\s>])/', $xml, $root)) {
        return $fail('sheet');
    }

    $prefix = isset($root[1]) ? $root[1] : '';
    $quoted = preg_quote($prefix, '/');

    foreach (pg_office_shift_refused_parts() as $local => $code) {
        if (preg_match('/<' . $quoted . $local . '(?=[\s>\/])/', $xml)) {
            return $fail($code);
        }
    }

    if (preg_match('/<' . $quoted . 'sheetProtection(?=[\s>\/])[^>]*\ssheet\s*=\s*["\'](?:1|true)["\']/', $xml)) {
        return $fail('shift_protected');
    }

    // The newer formatting and validation rules live in the extension list.
    if (preg_match('/<' . $quoted . 'extLst(?=[\s>]).*?<\/' . $quoted . 'extLst>/s', $xml, $ext) && preg_match('/sqref|<[\w.\-]+:f[\s>]/', $ext[0])) {
        return $fail('shift_part');
    }

    if (!preg_match('/<' . $quoted . 'sheetData(?=[\s>\/])[^>]*>/', $xml, $data, PREG_OFFSET_CAPTURE)) {
        return $fail('sheet');
    }

    $data_start = $data[0][1] + strlen($data[0][0]);
    $data_end = (substr($data[0][0], -2) === '/>') ? $data_start : strpos($xml, '</' . $prefix . 'sheetData>', $data_start);

    if ($data_end === false) {
        return $fail('sheet');
    }

    $rows = pg_office_xlsx_shift_rows(substr($xml, $data_start, $data_end - $data_start), $op, $sheet, $prefix);

    if (!$rows['ok']) {
        return $fail($rows['error'], $rows['cell']);
    }

    $around = array(substr($xml, 0, $data_start), substr($xml, $data_end));

    foreach ($around as $key => $part) {
        $part = pg_office_xlsx_shift_around($part, $op, $prefix);

        if (!$part['ok']) {
            return $fail($part['error'], $part['cell']);
        }

        $around[$key] = $part['xml'];
    }

    return array('ok' => true, 'xml' => $around[0] . $rows['xml'] . $around[1], 'error' => '', 'cell' => '');
}

/**
 * The rows of a sheet (the inside of sheetData) with the line moved: row and
 * cell addresses, a deleted row or cell taken out, the formulas' references.
 * A row nothing changed in is carried over byte for byte.
 *
 * @param string $data
 * @param array  $op
 * @param string $sheet
 * @param string $prefix
 * @return array ok, xml, error, cell
 */
function pg_office_xlsx_shift_rows($data, $op, $sheet, $prefix)
{
    $fail = function ($error, $cell = '') {
        return array('ok' => false, 'xml' => '', 'error' => $error, 'cell' => $cell);
    };

    $quoted = preg_quote($prefix, '/');
    $row_axis = ($op['axis'] === 'row');
    $error = null;

    // Filled formulas first: every cell of a group shares its first cell's
    // text, so the group must sit on one side of the line, and so must the
    // rows its relative references name.
    if (preg_match_all('/<' . $quoted . 'c(?=[\s>])[^>]*\sr\s*=\s*["\']([A-Za-z]+\d+)["\'][^>]*>\s*<' . $quoted . 'f(?=[\s>\/])([^>]*)>([^<]*)</', $data, $masters, PREG_SET_ORDER)) {
        foreach ($masters as $master) {
            $kind = pg_office_shift_attr('<f' . $master[2] . '>', 't');
            $ref = pg_office_shift_attr('<f' . $master[2] . '>', 'ref');

            if ($kind === 'dataTable') {
                return $fail('shift_formula', strtoupper($master[1]));
            }

            if ((($kind === 'shared') || ($kind === 'array')) && ($ref !== null)) {
                $extent = pg_office_shift_extent($ref, $op['axis']);

                if (($extent === null) || pg_office_shift_crosses($extent[0], $extent[1], $op)) {
                    return $fail(($kind === 'array') ? 'shift_cross' : 'shift_formula', ($kind === 'array') ? $ref : strtoupper($master[1]));
                }

                $text = html_entity_decode($master[3], ENT_QUOTES | ENT_XML1, 'UTF-8');

                if (($kind === 'shared') && (pg_office_shift_formula($text, $op, $sheet, $extent[1] - $extent[0]) === null)) {
                    return $fail('shift_formula', strtoupper($master[1]));
                }
            }
        }
    }

    $last = $row_axis ? PG_OFFICE_SHIFT_ROWS : PG_OFFICE_SHIFT_COLUMNS;

    $out = pg_office_shift_elements($data, $prefix, 'row', function ($row, $tag) use ($op, $sheet, $prefix, $quoted, $row_axis, $last, &$error) {
        if ($error !== null) {
            return null;
        }

        $number = (int) pg_office_shift_attr($tag, 'r');

        if ($number < 1) {
            $error = array('sheet', '');
            return null;
        }

        $new_number = $row_axis ? pg_office_shift_index($number, $op) : $number;

        if ($new_number === 0) {
            return '';
        }

        if ($new_number > $last) {
            $error = array('shift_full', '');
            return null;
        }

        $new = $row;

        if (!$row_axis && (pg_office_shift_attr($tag, 'spans') !== null)) {
            $new = substr_replace($new, pg_office_shift_attr_set($tag, 'spans', null), 0, strlen($tag));
        }

        if ($new_number !== $number) {
            $new = substr_replace($new, pg_office_shift_attr_set($tag, 'r', (string) $new_number), 0, strlen($tag));
        }

        $new = pg_office_shift_elements($new, $prefix, 'c', function ($cell, $cell_tag) use ($op, $sheet, $prefix, $quoted, $last, &$error) {
            if ($error !== null) {
                return null;
            }

            $address = (string) pg_office_shift_attr($cell_tag, 'r');
            $moved = pg_office_shift_ref($address, $op);

            if (($moved === false) || (strpos($address, ':') !== false)) {
                $error = array('sheet', '');
                return null;
            }

            if ($moved === null) {
                $position = pg_office_xlsx_cell(strtoupper($address));

                // Pushed past the grid's end, or the deleted cell itself.
                if ($op['insert'] || ($position === null)) {
                    $error = array('shift_full', strtoupper($address));
                    return null;
                }

                return '';
            }

            $new_cell = ($moved !== $address) ? substr_replace($cell, pg_office_shift_attr_set($cell_tag, 'r', $moved), 0, strlen($cell_tag)) : $cell;

            $new_cell = preg_replace_callback('/(<' . $quoted . 'f(?=[\s>\/])[^>]*?)(\/>|>([^<]*)(<\/' . $quoted . 'f>))/', function ($f) use ($op, $sheet, &$error) {
                $head = $f[1];
                $ref = pg_office_shift_attr($head . '>', 'ref');

                if ($ref !== null) {
                    $moved_ref = pg_office_shift_ref($ref, $op);

                    if ($moved_ref === false) {
                        $error = array('sheet', '');
                        return $f[0];
                    }

                    // A shared group or an array wholly deleted goes with
                    // its cells, its first cell among them.
                    $moved_ref = ($moved_ref === null) ? $ref : $moved_ref;

                    $head = pg_office_shift_attr_set($head . '>', 'ref', $moved_ref);
                    $head = substr($head, 0, -1);
                }

                if (!isset($f[3])) {
                    return $head . $f[2];
                }

                $text = html_entity_decode($f[3], ENT_QUOTES | ENT_XML1, 'UTF-8');
                $shifted = pg_office_shift_formula($text, $op, $sheet);

                if ($shifted === null) {
                    $error = array('shift_formula', '');
                    return $f[0];
                }

                $body = ($shifted === $text) ? $f[3] : htmlspecialchars($shifted, ENT_NOQUOTES | ENT_XML1, 'UTF-8');

                return $head . '>' . $body . $f[4];
            }, $new_cell);

            if (($error !== null) && ($error[0] === 'shift_formula') && ($error[1] === '')) {
                $error[1] = strtoupper($moved);
            }

            return is_string($new_cell) ? $new_cell : null;
        });

        if ($new === null) {
            $error = ($error !== null) ? $error : array('sheet', '');
            return null;
        }

        return $new;
    });

    if ($error !== null) {
        return $fail($error[0], $error[1]);
    }

    if ($out === null) {
        return $fail('sheet');
    }

    return array('ok' => true, 'xml' => $out, 'error' => '', 'cell' => '');
}

/**
 * The parts of a sheet before and after its rows with the line moved: the
 * dimension, the views' top cells and selections, column widths, merges,
 * links, page breaks and ignored errors.
 *
 * @param string $xml
 * @param array  $op
 * @param string $prefix
 * @return array ok, xml, error, cell
 */
function pg_office_xlsx_shift_around($xml, $op, $prefix)
{
    $error = null;

    // A list of ranges in an attribute: what is deleted goes; an element
    // left with none goes too, when $drop says it may.
    $lists = function ($element, $tag, $attributes, $drop, $fallback = null) use ($op, &$error) {
        $new = $element;

        foreach ($attributes as $attribute) {
            $value = pg_office_shift_attr($tag, $attribute);

            if ($value === null) {
                continue;
            }

            $moved = pg_office_shift_ref_list($value, $op);

            if ($moved === false) {
                $error = array('sheet', '');
                return null;
            }

            if ($moved === '') {
                if ($drop) {
                    return '';
                }

                $moved = ($fallback !== null) ? $fallback : $value;
            }

            if ($moved !== $value) {
                $new_tag = pg_office_shift_attr_set($tag, $attribute, $moved);
                $new = substr_replace($new, $new_tag, 0, strlen($tag));
                $tag = $new_tag;
            }
        }

        return $new;
    };

    $steps = array(
        'dimension' => function ($element, $tag) use ($lists) {
            return $lists($element, $tag, array('ref'), false, 'A1');
        },
        'sheetView' => function ($element, $tag) use ($lists) {
            return $lists($element, $tag, array('topLeftCell'), false, 'A1');
        },
        'pane' => function ($element, $tag) use ($lists) {
            return $lists($element, $tag, array('topLeftCell'), false, 'A1');
        },
        'selection' => function ($element, $tag) use ($lists) {
            return $lists($element, $tag, array('activeCell', 'sqref'), false, 'A1');
        },
        'mergeCell' => function ($element, $tag) use ($op, $lists, &$error) {
            $ref = (string) pg_office_shift_attr($tag, 'ref');
            $extent = pg_office_shift_extent($ref, $op['axis']);

            if (($extent === null) || pg_office_shift_crosses($extent[0], $extent[1], $op)) {
                $error = array('shift_cross', $ref);
                return null;
            }

            return $lists($element, $tag, array('ref'), true);
        },
        'hyperlink' => function ($element, $tag) use ($lists) {
            return $lists($element, $tag, array('ref'), true);
        },
        'ignoredError' => function ($element, $tag) use ($lists) {
            return $lists($element, $tag, array('sqref'), true);
        },
    );

    foreach ($steps as $local => $step) {
        $xml = pg_office_shift_elements($xml, $prefix, $local, $step);

        if (($xml === null) || ($error !== null)) {
            return array('ok' => false, 'xml' => '', 'error' => ($error !== null) ? $error[0] : 'sheet', 'cell' => ($error !== null) ? $error[1] : '');
        }
    }

    // Column widths and styles move with a column; a span of them widens or
    // narrows as a range does.
    if ($op['axis'] === 'column') {
        $xml = pg_office_shift_elements($xml, $prefix, 'col', function ($element, $tag) use ($op) {
            $min = (int) pg_office_shift_attr($tag, 'min');
            $max = (int) pg_office_shift_attr($tag, 'max');
            $span = (($min >= 1) && ($max >= $min)) ? pg_office_shift_span($min, $max, $op) : array($min, $max);

            if ($span === null) {
                return '';
            }

            $tag_new = pg_office_shift_attr_set(pg_office_shift_attr_set($tag, 'min', (string) $span[0]), 'max', (string) $span[1]);

            return substr_replace($element, $tag_new, 0, strlen($tag));
        });
    }

    // Page breaks after a row (or column) move with it; one on the deleted
    // line goes.
    $breaks = ($op['axis'] === 'row') ? 'rowBreaks' : 'colBreaks';

    if ($xml !== null) {
        $xml = pg_office_shift_elements($xml, $prefix, $breaks, function ($element) use ($op, $prefix) {
            return pg_office_shift_elements($element, $prefix, 'brk', function ($brk, $tag) use ($op) {
                $id = (int) pg_office_shift_attr($tag, 'id');
                $moved = ($id >= 1) ? pg_office_shift_index($id, $op) : $id;

                return ($moved === 0) ? '' : substr_replace($brk, pg_office_shift_attr_set($tag, 'id', (string) $moved), 0, strlen($tag));
            });
        });
    }

    if ($xml === null) {
        return array('ok' => false, 'xml' => '', 'error' => 'sheet', 'cell' => '');
    }

    // Counts kept true, and a list left empty taken out (the schema wants
    // at least one entry in each).
    foreach (array('mergeCells' => 'mergeCell', 'hyperlinks' => 'hyperlink', 'ignoredErrors' => 'ignoredError', 'cols' => 'col', $breaks => 'brk') as $list => $item) {
        $xml = pg_office_shift_elements($xml, $prefix, $list, function ($element, $tag) use ($prefix, $item) {
            $count = preg_match_all('/<' . preg_quote($prefix . $item, '/') . '(?=[\s>\/])/', $element);

            if ($count === 0) {
                return '';
            }

            $new_tag = $tag;

            foreach (array('count', 'manualBreakCount') as $attribute) {
                if (pg_office_shift_attr($new_tag, $attribute) !== null) {
                    $new_tag = pg_office_shift_attr_set($new_tag, $attribute, (string) $count);
                }
            }

            return substr_replace($element, $new_tag, 0, strlen($tag));
        });

        if ($xml === null) {
            return array('ok' => false, 'xml' => '', 'error' => 'sheet', 'cell' => '');
        }
    }

    return array('ok' => true, 'xml' => $xml, 'error' => '', 'cell' => '');
}

/* ---------------------------------------------------------------------------
   The package
   --------------------------------------------------------------------------- */

/**
 * Whether a part's text names a sheet in a reference: Sheet!, 'Sheet'!, a 3D
 * reference through it, or a pivot cache's sheet attribute.
 *
 * @param string $text  decoded
 * @param string $sheet
 * @return bool
 */
function pg_office_shift_names_sheet($text, $sheet)
{
    $plain = preg_quote($sheet, '/');
    $doubled = preg_quote(str_replace('\'', '\'\'', $sheet), '/');

    return (bool) preg_match('/(?<![\p{L}\p{N}_.])(?:' . $plain . '|' . $doubled . ')\'?(?=[!:])|:(?:' . $plain . '|' . $doubled . ')\'?!|\ssheet\s*=\s*["\']' . $plain . '["\']/iu', $text);
}

/**
 * Works out the parts an insert or delete changes and writes each to a
 * file: the sheet, the calculation chain, the other worksheets without their
 * stored formula results, and the workbook with its recalculation mark.
 *
 * @param ZipArchive $zip
 * @param array      $op    pg_office_shift_request()
 * @param callable   $fail
 * @param callable   $spool
 * @return array ok, parts, changed; or a refusal
 */
function pg_office_xlsx_shift_parts($zip, $op, $fail, $spool)
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

    if (!isset($sheets[$op['sheet']]) || (($op['name'] !== null) && ($op['name'] !== $sheets[$op['sheet']]['name']))) {
        return $fail('changed');
    }

    $sheet = $sheets[$op['sheet']];
    $name = $sheet['name'];

    if ($sheet['type'] !== 'worksheet') {
        return $fail('sheet', '', $name);
    }

    // The sheet's place among all the workbook's sheets (a defined name
    // local to it carries that place) and its id (the calculation chain's).
    $book = pg_office_xml($zip, 'xl/workbook.xml', $status);
    $place = -1;
    $sheet_id = '';

    if ($book === null) {
        return $fail('read');
    }

    foreach ($book->getElementsByTagName('sheet') as $index => $node) {
        if ((string) $node->getAttribute('name') === $name) {
            $place = $index;
            $sheet_id = (string) $node->getAttribute('sheetId');
        }
    }

    foreach ($book->getElementsByTagName('definedName') as $defined) {
        if ((((string) $defined->getAttribute('localSheetId') === (string) $place) && ($place >= 0))
            || pg_office_shift_names_sheet((string) $defined->textContent, $name)) {
            return $fail('shift_name', '', $name);
        }
    }

    unset($book);

    $rels_name = preg_replace('#([^/]+)$#', '_rels/$1.rels', $sheet['part']);
    $rels = pg_office_rels($zip, $rels_name, $rels_refused);

    if ($rels_refused !== '') {
        return $fail('sheet', '', $name);
    }

    foreach ($rels as $rel) {
        if (in_array($rel['type'], array('table', 'pivottable'), true)) {
            return $fail(($rel['type'] === 'table') ? 'shift_table' : 'shift_pivot', '', $name);
        }
    }

    // Any other sheet, chart, pivot cache or table whose formulas point
    // here would point at the wrong cells afterwards.
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $entry = (string) $zip->getNameIndex($index);

        if (($entry === $sheet['part']) || !preg_match('#^xl/(worksheets|charts|pivotCache|tables)/[^/]+\.xml$#', $entry, $folder)) {
            continue;
        }

        $stat = $zip->statIndex($index);

        if (($stat === false) || ((int) $stat['size'] > PG_OFFICE_EDIT_MAX_PART)) {
            return $fail('large', '', $name);
        }

        $text = (string) pg_zip_entry_contents($zip, $entry, PG_OFFICE_EDIT_MAX_PART, $unread);

        // In another worksheet only its formulas and link targets count, not
        // the words its cells hold.
        if ($folder[1] === 'worksheets') {
            preg_match_all('/<(?:[\w.\-]+:)?(?:f|formula|formula1|formula2)(?=[\s>])[^>]*>([^<]*)<|\slocation\s*=\s*"([^"]*)"/', $text, $found);
            $text = implode("\n", $found[1]) . "\n" . implode("\n", $found[2]);
        }

        if (pg_office_shift_names_sheet(html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8'), $name)) {
            return $fail('shift_other', '', $name);
        }
    }

    $stat = $zip->statName($sheet['part']);

    if (($stat === false) || ((int) $stat['size'] > PG_OFFICE_EDIT_MAX_PART)) {
        return $fail('large', '', $name);
    }

    $xml = (string) pg_zip_entry_contents($zip, $sheet['part'], PG_OFFICE_EDIT_MAX_PART, $unread);
    $reader = new XMLReader();

    if (($xml === '') || !@$reader->XML($xml, null, LIBXML_NONET)) {
        return $fail('sheet', '', $name);
    }

    $scan = pg_office_xlsx_scan($reader, 0, array());
    $reader->close();

    if (!$scan['addressed'] || $scan['unusual']) {
        return $fail('sheet', '', $name);
    }

    if ($scan['crowded']) {
        return $fail('large', '', $name);
    }

    $shifted = pg_office_xlsx_shift_sheet($xml, $op, $name);
    unset($xml);

    if (!$shifted['ok']) {
        return $fail($shifted['error'], $shifted['cell'], $name);
    }

    $stripped = pg_office_xlsx_strip_formula_values($shifted['xml']);
    $xml = $stripped['ok'] ? $stripped['xml'] : $shifted['xml'];
    unset($shifted, $stripped);

    if (!pg_office_xml_well_formed($xml)) {
        return $fail('sheet', '', $name);
    }

    $file = $spool($xml);
    unset($xml);

    if ($file === null) {
        return $fail('save');
    }

    $parts = array($sheet['part'] => $file);

    $chain = pg_office_xlsx_shift_chain($zip, $op, $sheet_id);

    if ($chain === false) {
        return $fail('read');
    }

    if ($chain !== null) {
        $file = $spool($chain);

        if ($file === null) {
            return $fail('save');
        }

        $parts['xl/calcChain.xml'] = $file;
    }

    return pg_office_xlsx_recalc_parts($zip, $sheets, $parts, $workbook, $fail, $spool, 1);
}

/**
 * The calculation chain with the changed sheet's cells moved: an entry takes
 * the sheet id of the one before it when it names none, so every entry kept
 * after a dropped one that named it carries it itself.
 *
 * @param ZipArchive $zip
 * @param array      $op
 * @param string     $sheet_id
 * @return string|null|false the new part; null when there is none or nothing changed; false when it cannot be read
 */
function pg_office_xlsx_shift_chain($zip, $op, $sheet_id)
{
    if ($zip->locateName('xl/calcChain.xml') === false) {
        return null;
    }

    $xml = pg_zip_entry_contents($zip, 'xl/calcChain.xml', PG_OFFICE_EDIT_MAX_PART, $unread);

    if (!is_string($xml) || !preg_match('/<([A-Za-z_][\w.\-]*:)?calcChain(?=[\s>])/', $xml, $root)) {
        return false;
    }

    $current = '';
    $carry = false;

    $out = pg_office_shift_elements($xml, isset($root[1]) ? $root[1] : '', 'c', function ($element, $tag) use ($op, $sheet_id, &$current, &$carry) {
        $own = pg_office_shift_attr($tag, 'i');
        $current = ($own !== null) ? $own : $current;

        if ($current !== $sheet_id) {
            $carry = false;
            return null;
        }

        $moved = pg_office_shift_ref((string) pg_office_shift_attr($tag, 'r'), $op);

        if (($moved === null) || ($moved === false)) {
            $carry = $carry || ($own !== null);
            return '';
        }

        $new_tag = pg_office_shift_attr_set($tag, 'r', $moved);

        if ($carry && ($own === null)) {
            $new_tag = preg_replace('/\s*\/?>$/', ' i="' . htmlspecialchars($current, ENT_QUOTES | ENT_XML1, 'UTF-8') . '"$0', $new_tag, 1);
        }

        $carry = false;

        return substr_replace($element, $new_tag, 0, strlen($tag));
    });

    if (($out === null) || !pg_office_xml_well_formed($out)) {
        return false;
    }

    return ($out === $xml) ? null : $out;
}
