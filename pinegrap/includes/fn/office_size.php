<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Column widths, row heights and a frozen top row of an Excel workbook,
 * changed on the server in the file itself (the file window and a channel's
 * preview send such changes in a save's ordered list, through
 * pg_office_xlsx_save()), and read back for the grid.
 *
 * A width is written into the sheet's <cols> list, an entry covering more
 * columns split around the one changed; a height is a row's ht with
 * customHeight; "Freeze top row" is a frozen pane under row 1 in each of the
 * sheet's views. Only the sheet part changes; values, styles and formula
 * results stay as they are.
 *
 * Refused with its reason, the file staying as it was: a signed file, a
 * password to change it, a chart sheet, a protected sheet that does not
 * allow formatting columns (a width) or rows (a height), a workbook whose
 * windows are locked (a frozen row).
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
 * The largest column width (in characters) and row height (in points)
 * Excel takes.
 */
define('PG_OFFICE_SIZE_MAX_WIDTH', 255);
define('PG_OFFICE_SIZE_MAX_HEIGHT', 409);

/* ---------------------------------------------------------------------------
   The request
   --------------------------------------------------------------------------- */

/**
 * A size change from the save's list, checked and made plain, or null when
 * the entry is not one.
 *
 * The entries: {op: width, sheet, name, column: "C", width: 12.5} (in
 * characters, above 0 and up to 255; null gives the column back its default
 * width), {op: height, sheet, name, row: 7, height: 20} (in points, above 0
 * and up to 409; null gives the row back its own height) and {op: freeze,
 * sheet, name, on: true|false} (row 1 kept in view, or no frozen pane).
 *
 * @param mixed $change
 * @return array|null kind (width|height|freeze), sheet, name, and column
 *                    (0-based) and width, row and height, or on
 */
function pg_office_size_request($change)
{
    if (!is_array($change) || !isset($change['op'], $change['sheet']) || !in_array($change['op'], array('width', 'height', 'freeze'), true)
        || !is_scalar($change['sheet']) || !preg_match('/^\d{1,5}$/', (string) $change['sheet'])) {
        return null;
    }

    $out = array('kind' => $change['op'], 'sheet' => (int) $change['sheet'], 'name' => (isset($change['name']) && is_string($change['name'])) ? $change['name'] : null);

    // A size given as a number, to two decimals, inside its limits; null
    // takes it off.
    $size = function ($key, $max) use ($change) {
        if (!array_key_exists($key, $change)) {
            return false;
        }

        $value = $change[$key];

        if ($value === null) {
            return null;
        }

        if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) {
            return false;
        }

        $value = round((float) $value, 2);

        return (($value > 0) && ($value <= $max)) ? $value : false;
    };

    switch ($change['op']) {
        case 'width':
            if (!isset($change['column']) || !is_string($change['column']) || !preg_match('/^[A-Za-z]{1,3}$/', $change['column'])) {
                return null;
            }

            $column = pg_office_xlsx_column(strtoupper($change['column']));
            $width = $size('width', PG_OFFICE_SIZE_MAX_WIDTH);

            if (($column >= PG_OFFICE_MAX_COLUMNS) || ($width === false)) {
                return null;
            }

            return $out + array('column' => $column, 'width' => $width);

        case 'height':
            if (!isset($change['row']) || !is_int($change['row']) || ($change['row'] < 1) || ($change['row'] > 1048576)) {
                return null;
            }

            $height = $size('height', PG_OFFICE_SIZE_MAX_HEIGHT);

            if ($height === false) {
                return null;
            }

            return $out + array('row' => $change['row'], 'height' => $height);
    }

    if (!isset($change['on']) || !is_bool($change['on'])) {
        return null;
    }

    return $out + array('on' => $change['on']);
}

/**
 * A size as an attribute value: no trailing zeros, a point for decimals.
 *
 * @param float $value
 * @return string
 */
function pg_office_size_number($value)
{
    $text = rtrim(rtrim(sprintf('%.2F', (float) $value), '0'), '.');

    return ($text === '') ? '0' : $text;
}

/* ---------------------------------------------------------------------------
   The sheet
   --------------------------------------------------------------------------- */

/**
 * The parts of a sheet given as text: the root's prefix and where its
 * sheetData starts and ends. Null for a part the size writer does not take
 * (comments, CDATA, a DOCTYPE, not UTF-8, no worksheet root or sheetData).
 *
 * @param string $xml
 * @return array|null prefix, start (of the sheetData tag), open (its tag), end (of the element, after its end tag)
 */
function pg_office_size_layout($xml)
{
    $xml = (string) $xml;

    if ((strpos($xml, '<!--') !== false) || (strpos($xml, '<![CDATA[') !== false) || (stripos($xml, '<!DOCTYPE') !== false) || !pg_office_xlsx_utf8_part(substr($xml, 0, 200))
        || !preg_match('/<([A-Za-z_][\w.\-]*:)?worksheet(?=[\s>])[^>]*>/', $xml, $root)) {
        return null;
    }

    $prefix = isset($root[1]) ? $root[1] : '';

    if (!preg_match('/<' . preg_quote($prefix, '/') . 'sheetData(?=[\s>\/])[^>]*>/', $xml, $data, PREG_OFFSET_CAPTURE)) {
        return null;
    }

    $start = $data[0][1];
    $after = $start + strlen($data[0][0]);

    if (substr($data[0][0], -2) === '/>') {
        $end = $after;
    } else {
        $close = strpos($xml, '</' . $prefix . 'sheetData>', $after);

        if ($close === false) {
            return null;
        }

        $end = $close + strlen('</' . $prefix . 'sheetData>');
    }

    return array('prefix' => $prefix, 'start' => $start, 'open' => $data[0][0], 'end' => $end);
}

/**
 * A sheet part with one column's width set, or taken off (null). Entries of
 * <cols> covering more columns are split around it, so the others keep
 * theirs; an entry left with nothing but its columns is taken out, and so
 * is a list left empty. A column no entry names gets one of its own, in its
 * place by column.
 *
 * @param string     $xml
 * @param int        $column 0-based
 * @param float|null $width  characters
 * @return array ok, xml, error (unusual)
 */
function pg_office_xlsx_size_width($xml, $column, $width)
{
    $layout = pg_office_size_layout($xml);

    if ($layout === null) {
        return array('ok' => false, 'xml' => '', 'error' => 'unusual');
    }

    $prefix = $layout['prefix'];
    $head = substr($xml, 0, $layout['start']);
    $number = $column + 1;
    $named = false;
    $value = ($width === null) ? null : pg_office_size_number($width);

    // One <col> start tag with its columns and a width (or none).
    $entry = function ($tag, $min, $max, $width) use ($prefix) {
        $tag = pg_office_shift_attr_set(pg_office_shift_attr_set($tag, 'min', (string) $min), 'max', (string) $max);

        foreach (array('width' => $width, 'customWidth' => ($width === null) ? null : '1') as $name => $set) {
            if (pg_office_shift_attr($tag, $name) !== null) {
                $tag = pg_office_shift_attr_set($tag, $name, $set);
            } elseif ($set !== null) {
                $tag = preg_replace('/\s*\/?>$/', ' ' . $name . '="' . $set . '"/>', $tag, 1);
            }
        }

        $tag = preg_replace('/\s*>$/', '/>', preg_replace('/\s*\/>$/', '>', $tag, 1), 1);

        // Nothing left but its columns: the entry says nothing.
        $rest = preg_replace('/\s(?:min|max)\s*=\s*(?:"[^"]*"|\'[^\']*\')/', '', $tag);

        return preg_match('/^<[^\s\/>]+\s*\/>$/', $rest) ? '' : $tag;
    };

    $new_head = pg_office_shift_elements($head, $prefix, 'cols', function ($block, $tag) use ($prefix, $number, $value, $entry, &$named) {
        if (substr($tag, -2) === '/>') {
            return null;
        }

        $quoted = preg_quote($prefix . 'col', '/');
        $inner = preg_match('/^<[^>]*>(.*)<\/' . preg_quote($prefix, '/') . 'cols>$/s', $block, $found) ? $found[1] : '';
        $out = '';
        $placed = false;

        preg_match_all('/<' . $quoted . '(?=[\s\/>])[^>]*?(?:\/>|>\s*<\/' . $quoted . '>)/', $inner, $columns);

        foreach ($columns[0] as $element) {
            $start = preg_replace('/>\s*<\/' . $quoted . '>$/', '/>', $element);
            $min = (int) pg_office_shift_attr($start, 'min');
            $max = (int) pg_office_shift_attr($start, 'max');

            if (($min > 0) && ($max >= $min) && ($min <= $number) && ($number <= $max)) {
                $named = true;
                $placed = true;

                if ($min < $number) {
                    $out .= $entry($start, $min, $number - 1, pg_office_shift_attr($start, 'width'));
                }

                $out .= $entry($start, $number, $number, $value);

                if ($max > $number) {
                    $out .= $entry($start, $number + 1, $max, pg_office_shift_attr($start, 'width'));
                }

                continue;
            }

            $out .= $element;
        }

        return ($out === '') ? '' : $tag . $out . '</' . $prefix . 'cols>';
    });

    if ($new_head === null) {
        return array('ok' => false, 'xml' => '', 'error' => 'unusual');
    }

    if (!$named && ($value !== null)) {
        $col = '<' . $prefix . 'col min="' . $number . '" max="' . $number . '" width="' . $value . '" customWidth="1"/>';
        $quoted = preg_quote($prefix, '/');

        if (preg_match('/<' . $quoted . 'cols(?=[\s>])[^>]*>.*<\/' . $quoted . 'cols>/s', $new_head, $last, PREG_OFFSET_CAPTURE)) {
            // Into the last list, before the first entry that starts after it.
            $block = $last[0][0];
            $at = strlen($block) - strlen('</' . $prefix . 'cols>');

            if (preg_match_all('/<' . preg_quote($prefix . 'col', '/') . '(?=[\s\/>])[^>]*>/', $block, $tags, PREG_OFFSET_CAPTURE)) {
                foreach ($tags[0] as $found) {
                    if ((int) pg_office_shift_attr($found[0], 'min') > $number) {
                        $at = $found[1];
                        break;
                    }
                }
            }

            $new_head = substr($new_head, 0, $last[0][1]) . substr($block, 0, $at) . $col . substr($block, $at) . substr($new_head, $last[0][1] + strlen($block));
        } else {
            // A new list goes right before sheetData, where the schema has it.
            $new_head .= '<' . $prefix . 'cols>' . $col . '</' . $prefix . 'cols>';
        }
    }

    return array('ok' => true, 'xml' => $new_head . substr($xml, $layout['start']), 'error' => '');
}

/**
 * A sheet part with one row's height set (ht with customHeight), or taken
 * off (null). A row the sheet lacks is made, empty, in its place.
 *
 * @param string     $xml
 * @param int        $number the row
 * @param float|null $height points
 * @return array ok, xml, error (unusual, layout)
 */
function pg_office_xlsx_size_height($xml, $number, $height)
{
    $layout = pg_office_size_layout($xml);

    if ($layout === null) {
        return array('ok' => false, 'xml' => '', 'error' => 'unusual');
    }

    $prefix = $layout['prefix'];
    $value = ($height === null) ? null : pg_office_size_number($height);
    $made = '<' . $prefix . 'row r="' . $number . '" ht="' . $value . '" customHeight="1"/>';

    if (substr($layout['open'], -2) === '/>') {
        if ($value === null) {
            return array('ok' => true, 'xml' => $xml, 'error' => '');
        }

        return array('ok' => true, 'xml' => substr($xml, 0, $layout['start']) . substr($layout['open'], 0, -2) . '>' . $made . '</' . $prefix . 'sheetData>' . substr($xml, $layout['end']), 'error' => '');
    }

    $position = $layout['start'] + strlen($layout['open']);
    $data_end = $layout['end'] - strlen('</' . $prefix . 'sheetData>');
    $pattern = '/<' . preg_quote($prefix, '/') . 'row(?=[\s>\/])[^>]*>/';
    $close = '</' . $prefix . 'row>';

    while (preg_match($pattern, $xml, $tag, PREG_OFFSET_CAPTURE, $position) && ($tag[0][1] < $data_end)) {
        $start = $tag[0][1];
        $given = pg_office_shift_attr($tag[0][0], 'r');

        if (($given === null) || !preg_match('/^\d+$/', $given)) {
            return array('ok' => false, 'xml' => '', 'error' => 'layout');
        }

        if ((int) $given > $number) {
            break;
        }

        if ((int) $given === $number) {
            $start_tag = $tag[0][0];

            foreach (array('ht' => $value, 'customHeight' => ($value === null) ? null : '1') as $name => $set) {
                if (pg_office_shift_attr($start_tag, $name) !== null) {
                    $start_tag = pg_office_shift_attr_set($start_tag, $name, $set);
                } elseif ($set !== null) {
                    $start_tag = preg_replace('/\s*(\/?)>$/', ' ' . $name . '="' . $set . '"$1>', $start_tag, 1);
                }
            }

            return array('ok' => true, 'xml' => substr($xml, 0, $start) . $start_tag . substr($xml, $start + strlen($tag[0][0])), 'error' => '');
        }

        if (substr($tag[0][0], -2) === '/>') {
            $position = $start + strlen($tag[0][0]);
        } else {
            $end = strpos($xml, $close, $start + strlen($tag[0][0]));

            if (($end === false) || ($end > $data_end)) {
                return array('ok' => false, 'xml' => '', 'error' => 'unusual');
            }

            $position = $end + strlen($close);
        }
    }

    if ($value === null) {
        return array('ok' => true, 'xml' => $xml, 'error' => '');
    }

    // Before the first row after it, or at the end of sheetData.
    $at = (isset($tag[0]) && ($tag[0][1] < $data_end) && ($tag[0][1] >= $position)) ? $tag[0][1] : $data_end;

    return array('ok' => true, 'xml' => substr($xml, 0, $at) . $made . substr($xml, $at), 'error' => '');
}

/**
 * A sheet part with row 1 frozen (on) or no frozen pane (off) in each of its
 * views. On, a view's pane becomes a frozen one under row 1, its scroll
 * starts at the top, and its one selection belongs to the lower pane; off,
 * a frozen pane is taken out and the selection belongs to the one pane left.
 * A sheet without views gets one when frozen.
 *
 * @param string $xml
 * @param bool   $on
 * @return array ok, xml, error (unusual)
 */
function pg_office_xlsx_size_freeze($xml, $on)
{
    $layout = pg_office_size_layout($xml);

    if ($layout === null) {
        return array('ok' => false, 'xml' => '', 'error' => 'unusual');
    }

    $prefix = $layout['prefix'];
    $head = substr($xml, 0, $layout['start']);
    $pane = '<' . $prefix . 'pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>';
    $quoted = preg_quote($prefix, '/');

    $new_head = pg_office_shift_elements($head, $prefix, 'sheetView', function ($view, $tag) use ($prefix, $quoted, $pane, $on) {
        $inner = preg_match('/^<[^>]*>(.*)<\/' . $quoted . 'sheetView>$/s', $view, $found) ? $found[1] : '';
        $old_pane = preg_match('/<' . $quoted . 'pane(?=[\s\/>])[^>]*?(?:\/>|>\s*<\/' . $quoted . 'pane>)/', $inner, $match) ? $match[0] : '';

        if ($old_pane !== '') {
            $state = (string) pg_office_shift_attr($old_pane, 'state');

            // A split that is not frozen is not this switch's to take off.
            if (!$on && !in_array($state, array('frozen', 'frozenSplit'), true)) {
                return null;
            }
        } elseif (!$on) {
            return null;
        }

        $active = ($old_pane === '') ? 'topLeft' : (string) (pg_office_shift_attr($old_pane, 'activePane') ?? 'topLeft');
        $selections = array();

        preg_match_all('/<' . $quoted . 'selection(?=[\s\/>])[^>]*?(?:\/>|>\s*<\/' . $quoted . 'selection>)/', $inner, $found_selections);

        foreach ($found_selections[0] as $selection) {
            $selections[(string) (pg_office_shift_attr($selection, 'pane') ?? 'topLeft')] = $selection;
        }

        $kept = isset($selections[$active]) ? $selections[$active] : (empty($selections) ? '' : reset($selections));

        if ($kept !== '') {
            $kept = preg_replace('/>\s*<\/' . $quoted . 'selection>$/', '/>', $kept);

            if (pg_office_shift_attr($kept, 'pane') !== null) {
                $kept = pg_office_shift_attr_set($kept, 'pane', $on ? 'bottomLeft' : null);
            } elseif ($on) {
                $kept = preg_replace('/\s*\/>$/', ' pane="bottomLeft"/>', $kept, 1);
            }
        }

        // The rest of the view (pivot selections, extensions) stays.
        $rest = preg_replace('/<' . $quoted . '(?:pane|selection)(?=[\s\/>])[^>]*?(?:\/>|>\s*<\/' . $quoted . '(?:pane|selection)>)/', '', $inner);
        $start = preg_replace('/\s*\/>$/', '>', $tag, 1);

        if ($on) {
            $start = pg_office_shift_attr_set($start, 'topLeftCell', null);
        }

        $content = ($on ? $pane : '') . $kept . $rest;

        return ($content === '') ? preg_replace('/\s*>$/', '/>', $start, 1) : $start . $content . '</' . $prefix . 'sheetView>';
    });

    if ($new_head === null) {
        return array('ok' => false, 'xml' => '', 'error' => 'unusual');
    }

    if ($on && !preg_match('/<' . $quoted . 'sheetView(?=[\s\/>])/', $new_head)) {
        $views = '<' . $prefix . 'sheetViews><' . $prefix . 'sheetView workbookViewId="0">' . $pane . '</' . $prefix . 'sheetView></' . $prefix . 'sheetViews>';
        $views_open = preg_match('/<' . $quoted . 'sheetViews(?=[\s\/>])[^>]*>/', $new_head, $open, PREG_OFFSET_CAPTURE);

        if ($views_open && (substr($open[0][0], -2) === '/>')) {
            $new_head = substr($new_head, 0, $open[0][1]) . $views . substr($new_head, $open[0][1] + strlen($open[0][0]));
        } elseif (preg_match('/<' . $quoted . '(?:sheetFormatPr|cols)(?=[\s\/>])/', $new_head, $next, PREG_OFFSET_CAPTURE)) {
            // Views come before the format and the columns, after the
            // sheet's properties and dimension.
            $new_head = substr($new_head, 0, $next[0][1]) . $views . substr($new_head, $next[0][1]);
        } else {
            $new_head .= $views;
        }
    }

    return array('ok' => true, 'xml' => $new_head . substr($xml, $layout['start']), 'error' => '');
}

/**
 * Whether a sheet's protection keeps its columns (formatColumns) or rows
 * (formatRows) from being sized: a protected sheet does unless the
 * attribute is 0.
 *
 * @param string $xml  the sheet part
 * @param string $what formatColumns or formatRows
 * @return bool
 */
function pg_office_size_protected($xml, $what)
{
    if (!preg_match('/<(?:[A-Za-z_][\w.\-]*:)?sheetProtection(?=[\s>\/])[^>]*>/', (string) $xml, $tag)) {
        return false;
    }

    $sheet = strtolower((string) pg_office_shift_attr($tag[0], 'sheet'));
    $allowed = strtolower((string) pg_office_shift_attr($tag[0], $what));

    return in_array($sheet, array('1', 'true'), true) && !in_array($allowed, array('0', 'false'), true);
}

/**
 * Whether the workbook's windows are locked (workbookProtection
 * lockWindows), which keeps panes from being frozen or unfrozen.
 *
 * @param string $workbook_xml
 * @return bool
 */
function pg_office_size_windows_locked($workbook_xml)
{
    return 0 !== preg_match('/<(?:[\w.\-]+:)?workbookProtection\b[^>]*\slockWindows\s*=\s*["\'](?:1|true)["\']/', (string) $workbook_xml);
}

/* ---------------------------------------------------------------------------
   The save
   --------------------------------------------------------------------------- */

/**
 * The part a size change writes: the sheet.
 *
 * @param ZipArchive $zip
 * @param array      $size   pg_office_size_request()
 * @param callable   $fail
 * @param callable   $spool
 * @return array ok, parts, changed; or a refusal
 */
function pg_office_xlsx_size_parts($zip, $size, $fail, $spool)
{
    if (pg_office_signed($zip)) {
        return $fail('signed');
    }

    $sheets = pg_office_xlsx_sheets($zip, $refused);

    if ($refused !== '') {
        return array('ok' => false, 'code' => 'read', 'error' => pg_office_refusal_error($refused), 'size' => 0, 'version' => '', 'changed' => 0);
    }

    $workbook_xml = (string) pg_zip_entry_contents($zip, pg_sheet_xlsx_workbook($zip)['part'], PG_OFFICE_MAX_DOM, $unread);

    if (pg_office_xlsx_write_reserved($workbook_xml)) {
        return $fail('password');
    }

    $index = $size['sheet'];

    if (!isset($sheets[$index]) || (($size['name'] !== null) && ($size['name'] !== $sheets[$index]['name']))) {
        return $fail('changed');
    }

    $sheet = $sheets[$index];

    if ($sheet['type'] !== 'worksheet') {
        return $fail('sheet', '', $sheet['name']);
    }

    if (($size['kind'] === 'freeze') && pg_office_size_windows_locked($workbook_xml)) {
        return $fail('size_windows', '', $sheet['name']);
    }

    $stat = $zip->statName($sheet['part']);

    if (($stat === false) || ((int) $stat['size'] > PG_OFFICE_EDIT_MAX_PART)) {
        return $fail('large', '', $sheet['name']);
    }

    $xml = (string) pg_zip_entry_contents($zip, $sheet['part'], PG_OFFICE_EDIT_MAX_PART, $unread);

    switch ($size['kind']) {
        case 'width':
            if (pg_office_size_protected($xml, 'formatColumns')) {
                return $fail('size_columns', '', $sheet['name']);
            }

            $patched = pg_office_xlsx_size_width($xml, $size['column'], $size['width']);
            break;
        case 'height':
            if (pg_office_size_protected($xml, 'formatRows')) {
                return $fail('size_rows', '', $sheet['name']);
            }

            $patched = pg_office_xlsx_size_height($xml, $size['row'], $size['height']);
            break;
        default:
            $patched = pg_office_xlsx_size_freeze($xml, $size['on']);
    }

    unset($xml);

    if (!$patched['ok'] || !pg_office_xml_well_formed($patched['xml'])) {
        return $fail('sheet', '', $sheet['name']);
    }

    $file = $spool($patched['xml']);

    if ($file === null) {
        return $fail('save');
    }

    return array('ok' => true, 'parts' => array($sheet['part'] => $file), 'changed' => 1);
}

/**
 * The message for a refused size change.
 *
 * @param string $code
 * @param string $sheet
 * @return string
 */
function pg_office_size_message($code, $sheet)
{
    switch ($code) {
        case 'size_columns':
            return lang(array('string' => 'Sheet {var:1} is protected and does not allow changing column widths.', 'vars' => array($sheet)));
        case 'size_rows':
            return lang(array('string' => 'Sheet {var:1} is protected and does not allow changing row heights.', 'vars' => array($sheet)));
        case 'size_windows':
            return lang('The windows of this workbook are protected, so its top row is not frozen or unfrozen here.');
    }

    return lang('The file could not be saved.');
}

/* ---------------------------------------------------------------------------
   The reader
   --------------------------------------------------------------------------- */

/**
 * The sizes of a sheet before any are read: no column widths, the
 * defaults unknown, no frozen row.
 *
 * @return array widths (0-based column => characters), width (default
 *               column width or null), height (default row height or
 *               null), frozen (rows kept in view)
 */
function pg_office_size_empty()
{
    return array('widths' => array(), 'width' => null, 'height' => null, 'frozen' => 0, 'view' => false);
}

/**
 * Notes one element of a sheet's head (before sheetData) in its sizes:
 * a <col> entry's width over the columns the grid shows (a later entry
 * wins), sheetFormatPr's defaults, and the first view's frozen pane.
 *
 * @param array  $sizes      pg_office_size_empty(), changed in place
 * @param string $local      the element's local name
 * @param array  $attributes name => value
 */
function pg_office_size_note(&$sizes, $local, $attributes)
{
    $number = function ($name) use ($attributes) {
        return (isset($attributes[$name]) && is_numeric($attributes[$name]) && ((float) $attributes[$name] >= 0)) ? round((float) $attributes[$name], 2) : null;
    };

    switch ($local) {
        case 'col':
            $min = isset($attributes['min']) ? (int) $attributes['min'] : 0;
            $max = isset($attributes['max']) ? (int) $attributes['max'] : 0;
            $width = $number('width');

            if (($min <= 0) || ($max < $min)) {
                return;
            }

            for ($column = $min; $column <= min($max, PG_OFFICE_MAX_COLUMNS); $column++) {
                if ($width !== null) {
                    $sizes['widths'][$column - 1] = $width;
                } else {
                    unset($sizes['widths'][$column - 1]);
                }
            }

            return;

        case 'sheetFormatPr':
            $sizes['width'] = ($number('defaultColWidth') !== null) ? $number('defaultColWidth') : (($number('baseColWidth') !== null) ? $number('baseColWidth') : null);
            $sizes['height'] = $number('defaultRowHeight');

            return;

        case 'sheetView':
            // Only the first view (the workbook's first window) counts.
            if ($sizes['view'] === false) {
                $sizes['view'] = true;
            } else {
                $sizes['view'] = 'later';
            }

            return;

        case 'pane':
            if (($sizes['view'] === true) && in_array(isset($attributes['state']) ? $attributes['state'] : '', array('frozen', 'frozenSplit'), true)) {
                $sizes['frozen'] = max(0, (int) ($number('ySplit') ?? 0));
            }
    }
}

/**
 * The sizes as the preview sends them: view's bookkeeping taken out.
 *
 * @param array $sizes pg_office_size_empty() as noted
 * @return array widths, width, height, frozen
 */
function pg_office_size_out($sizes)
{
    unset($sizes['view']);
    ksort($sizes['widths']);

    return $sizes;
}
