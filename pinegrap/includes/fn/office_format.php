<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The format of selected cells of an Excel workbook changed on the server,
 * in the file itself (the file window and a channel's preview send one such
 * change as their whole save, through pg_office_xlsx_save()): bold, italic,
 * text colour, fill, horizontal alignment and the number format (general,
 * number, money, percent, date).
 *
 * A cell's style is never changed in place, as other cells share it: each
 * style the range uses gets a new cell format with the asked changes, built
 * from a copy of its own (its border, its protection, its other alignment
 * kept), and the new fonts, fills and number formats it needs are added
 * after the existing ones. An existing entry that is already what is needed
 * is used instead of a copy. The cells of the range point to their new
 * style; a cell or a row the range covers and the sheet does not hold is
 * made with no value. Values and formula results stay as they are.
 *
 * Refused with its reason, the file staying as it was: a signed file, a
 * password to change it, a protected sheet that does not allow formatting,
 * a workbook without a styles part or with one too large to read whole.
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
 * The first number a workbook's own number formats take; below it are
 * Excel's built-in formats.
 */
define('PG_OFFICE_FORMAT_FIRST_CUSTOM', 164);

/* ---------------------------------------------------------------------------
   The request
   --------------------------------------------------------------------------- */

/**
 * A format change from the save's list, checked and made plain, or null
 * when the entry is not one.
 *
 * The entry: {op: format, sheet, name, range: "B2:D5", set: {...}}, where
 * set holds one or more of bold and italic (true or false), color and fill
 * ("RRGGBB" or "#RRGGBB"; "" takes it off), align ("", left, center, right)
 * and number (general, number, money, percent, date; money with an optional
 * symbol).
 *
 * @param mixed $change
 * @return array|null sheet, name, range (pg_office_xlsx_range()), set (colours as AARRGGBB)
 */
function pg_office_format_request($change)
{
    if (!is_array($change) || !isset($change['op'], $change['sheet'], $change['range'], $change['set']) || ($change['op'] !== 'format')
        || !is_scalar($change['sheet']) || !preg_match('/^\d{1,5}$/', (string) $change['sheet'])
        || !is_string($change['range']) || !is_array($change['set'])) {
        return null;
    }

    $range = pg_office_xlsx_range(strtoupper($change['range']));

    if (($range === null) || ($range[3] >= PG_OFFICE_MAX_COLUMNS) || ($range[2] > 1048576)) {
        return null;
    }

    $set = array();

    foreach ($change['set'] as $key => $value) {
        switch ($key) {
            case 'bold':
            case 'italic':
                if (!is_bool($value)) {
                    return null;
                }

                $set[$key] = $value;
                break;
            case 'color':
            case 'fill':
                if (!is_string($value) || !preg_match('/^(?:#?([0-9A-Fa-f]{6}))?$/', $value, $match)) {
                    return null;
                }

                $set[$key] = isset($match[1]) ? 'FF' . strtoupper($match[1]) : '';
                break;
            case 'align':
                if (!in_array($value, array('', 'left', 'center', 'right'), true)) {
                    return null;
                }

                $set[$key] = $value;
                break;
            case 'number':
                if (!in_array($value, array('general', 'number', 'money', 'percent', 'date'), true)) {
                    return null;
                }

                $set[$key] = $value;
                break;
            case 'symbol':
                if (!is_string($value) || (mb_strlen($value, 'UTF-8') > 8) || !mb_check_encoding($value, 'UTF-8')) {
                    return null;
                }

                $set[$key] = $value;
                break;
            default:
                return null;
        }
    }

    unset($set['symbol']);

    if (empty($set)) {
        return null;
    }

    if (($set['number'] ?? '') === 'money') {
        $set['symbol'] = isset($change['set']['symbol']) ? $change['set']['symbol'] : pg_order_log_currency_symbol();
    }

    return array('sheet' => (int) $change['sheet'], 'name' => isset($change['name']) && is_string($change['name']) ? $change['name'] : null, 'range' => $range, 'set' => $set);
}

/**
 * The number format code of "money": two decimals with thousands, the
 * currency symbol after the amount, quoted so that its letters are not
 * taken for format characters.
 *
 * @param string $symbol
 * @return string
 */
function pg_office_format_money_code($symbol)
{
    $symbol = trim(preg_replace('/["\\\\\x00-\x1F]/u', '', (string) $symbol));

    return ($symbol === '') ? '#,##0.00' : '#,##0.00\ "' . $symbol . '"';
}

/* ---------------------------------------------------------------------------
   The styles part
   --------------------------------------------------------------------------- */

/**
 * The direct child elements of a node with this local name.
 *
 * @param DOMNode $node
 * @param string  $local
 * @return DOMElement[]
 */
function pg_office_format_children($node, $local)
{
    $out = array();

    foreach ($node->childNodes as $child) {
        if (($child->nodeType === XML_ELEMENT_NODE) && ($child->localName === $local)) {
            $out[] = $child;
        }
    }

    return $out;
}

/**
 * An element as it is compared with another: its name, its attributes in
 * name order and its child elements, whatever the prefixes and spacing.
 *
 * @param DOMElement $element
 * @return string
 */
function pg_office_format_key($element)
{
    $attributes = array();

    foreach ($element->attributes as $attribute) {
        $attributes[$attribute->namespaceURI . '|' . $attribute->localName] = $attribute->value;
    }

    ksort($attributes);
    $key = $element->namespaceURI . '|' . $element->localName . json_encode($attributes);

    foreach ($element->childNodes as $child) {
        if ($child->nodeType === XML_ELEMENT_NODE) {
            $key .= '(' . pg_office_format_key($child) . ')';
        }
    }

    return $key;
}

/**
 * The style writer of one styles part, loaded as $document: restyle(style
 * number, set) gives the number of the style that is that style with the
 * set's changes, adding what it needs, or null when the part has no such
 * style or lacks a list it needs. Answers are remembered within the save.
 *
 * @param DOMDocument $document
 * @return array restyle (callable)
 */
function pg_office_format_styles($document)
{
    $root = $document->documentElement;
    $namespace = $root->namespaceURI;
    $prefix = ($root->prefix !== '') ? $root->prefix . ':' : '';
    $remembered = array();

    $element = function ($local) use ($document, $namespace, $prefix) {
        return ($namespace !== null) ? $document->createElementNS($namespace, $prefix . $local) : $document->createElement($local);
    };

    // The schema's order of the lists a styles part holds.
    $order = array('numFmts', 'fonts', 'fills', 'borders', 'cellStyleXfs', 'cellXfs', 'cellStyles', 'dxfs', 'tableStyles', 'colors', 'extLst');

    $list = function ($local, $create) use ($root, $element, $order) {
        $found = pg_office_format_children($root, $local);

        if (!empty($found) || !$create) {
            return empty($found) ? null : $found[0];
        }

        $new = $element($local);
        $after = array_slice($order, array_search($local, $order, true) + 1);

        foreach ($root->childNodes as $child) {
            if (($child->nodeType === XML_ELEMENT_NODE) && in_array($child->localName, $after, true)) {
                $root->insertBefore($new, $child);

                return $new;
            }
        }

        $root->appendChild($new);

        return $new;
    };

    // The place of an entry equal to $entry in the list, or its own place
    // after the others when there is none.
    $place = function ($parent, $local, $entry) {
        $key = pg_office_format_key($entry);
        $entries = pg_office_format_children($parent, $local);

        foreach ($entries as $index => $existing) {
            if (pg_office_format_key($existing) === $key) {
                return $index;
            }
        }

        $parent->appendChild($entry);
        $parent->setAttribute('count', (string) (count($entries) + 1));

        return count($entries);
    };

    $set_id = function ($xf, $attribute, $apply, $value) {
        if ((int) $xf->getAttribute($attribute) === (int) $value) {
            return;
        }

        $xf->setAttribute($attribute, (string) $value);
        $xf->setAttribute($apply, '1');
    };

    $restyle = function ($style, $set) use ($document, $element, $list, $place, $set_id, &$remembered) {
        $style = (int) $style;
        $memo = $style . '|' . json_encode($set);

        if (array_key_exists($memo, $remembered)) {
            return $remembered[$memo];
        }

        $remembered[$memo] = null;
        $cell_xfs = $list('cellXfs', false);
        $xfs = ($cell_xfs === null) ? array() : pg_office_format_children($cell_xfs, 'xf');

        if (!isset($xfs[$style])) {
            return null;
        }

        $xf = $xfs[$style]->cloneNode(true);

        if (isset($set['bold']) || isset($set['italic']) || isset($set['color'])) {
            $fonts = $list('fonts', false);

            if ($fonts === null) {
                return null;
            }

            $existing = pg_office_format_children($fonts, 'font');
            $id = (int) $xf->getAttribute('fontId');
            $font = isset($existing[$id]) ? $existing[$id]->cloneNode(true) : $element('font');

            foreach (array('bold' => 'b', 'italic' => 'i', 'color' => 'color') as $key => $local) {
                if (!isset($set[$key])) {
                    continue;
                }

                foreach (pg_office_format_children($font, $local) as $old) {
                    $font->removeChild($old);
                }

                if (($set[$key] === false) || ($set[$key] === '')) {
                    continue;
                }

                $new = $element($local);

                if ($local === 'color') {
                    // Excel writes the colour before the font's name.
                    $new->setAttribute('rgb', $set[$key]);
                    $before = array_merge(pg_office_format_children($font, 'name'), pg_office_format_children($font, 'family'), pg_office_format_children($font, 'scheme'));
                } else {
                    // Bold first, italic after it, before the size.
                    $before = array();

                    foreach ($font->childNodes as $child) {
                        if (($child->nodeType === XML_ELEMENT_NODE) && (($local === 'b') || ($child->localName !== 'b'))) {
                            $before[] = $child;
                            break;
                        }
                    }
                }

                if (!empty($before)) {
                    $font->insertBefore($new, $before[0]);
                } else {
                    $font->appendChild($new);
                }
            }

            $set_id($xf, 'fontId', 'applyFont', $place($fonts, 'font', $font));
        }

        if (isset($set['fill'])) {
            $fills = $list('fills', false);

            if ($fills === null) {
                return null;
            }

            $fill = $element('fill');
            $pattern = $element('patternFill');
            $fill->appendChild($pattern);

            if ($set['fill'] === '') {
                $pattern->setAttribute('patternType', 'none');
            } else {
                $pattern->setAttribute('patternType', 'solid');
                $color = $element('fgColor');
                $color->setAttribute('rgb', $set['fill']);
                $pattern->appendChild($color);
                $back = $element('bgColor');
                $back->setAttribute('indexed', '64');
                $pattern->appendChild($back);
            }

            $set_id($xf, 'fillId', 'applyFill', $place($fills, 'fill', $fill));
        }

        if (isset($set['align'])) {
            $alignments = pg_office_format_children($xf, 'alignment');
            $alignment = empty($alignments) ? null : $alignments[0];
            $was = ($alignment === null) ? '' : (string) $alignment->getAttribute('horizontal');

            if ($was !== $set['align']) {
                if ($alignment === null) {
                    $alignment = $element('alignment');
                    $xf->insertBefore($alignment, $xf->firstChild);
                }

                if ($set['align'] === '') {
                    $alignment->removeAttribute('horizontal');
                } else {
                    $alignment->setAttribute('horizontal', $set['align']);
                }

                if ($alignment->attributes->length === 0) {
                    $xf->removeChild($alignment);
                }

                $xf->setAttribute('applyAlignment', '1');
            }
        }

        if (isset($set['number'])) {
            $builtin = array('general' => 0, 'number' => 4, 'percent' => 10, 'date' => 14);
            $id = isset($builtin[$set['number']]) ? $builtin[$set['number']] : null;

            if ($id === null) {
                $code = pg_office_format_money_code(isset($set['symbol']) ? $set['symbol'] : '');
                $formats = $list('numFmts', true);
                $next = PG_OFFICE_FORMAT_FIRST_CUSTOM;

                foreach (pg_office_format_children($formats, 'numFmt') as $format) {
                    if ((string) $format->getAttribute('formatCode') === $code) {
                        $id = (int) $format->getAttribute('numFmtId');
                        break;
                    }

                    $next = max($next, (int) $format->getAttribute('numFmtId') + 1);
                }

                if ($id === null) {
                    $format = $element('numFmt');
                    $format->setAttribute('numFmtId', (string) $next);
                    $format->setAttribute('formatCode', $code);
                    $formats->appendChild($format);
                    $formats->setAttribute('count', (string) count(pg_office_format_children($formats, 'numFmt')));
                    $id = $next;
                }
            }

            $set_id($xf, 'numFmtId', 'applyNumberFormat', $id);
        }

        $remembered[$memo] = $place($cell_xfs, 'xf', $xf);

        return $remembered[$memo];
    };

    return array('restyle' => $restyle);
}

/* ---------------------------------------------------------------------------
   The sheet
   --------------------------------------------------------------------------- */

/**
 * Points the cells of a range to their new styles, in a sheet given as
 * text. Rows of the range are rewritten one by one; a row or cell the range
 * covers and the sheet lacks is made, with no value, when its new style is
 * not the default one. Everything outside the range is copied byte for byte.
 *
 * @param string   $xml     the sheet part
 * @param array    $range   pg_office_xlsx_range()
 * @param callable $restyle function (int old style): int new style, or null
 * @return array ok, xml, error (unusual, layout, xml, large, styles)
 */
function pg_office_xlsx_format_sheet($xml, $range, $restyle)
{
    $fail = function ($error) {
        return array('ok' => false, 'xml' => '', 'error' => $error);
    };

    $xml = (string) $xml;

    if ((strpos($xml, '<!--') !== false) || (strpos($xml, '<![CDATA[') !== false) || (stripos($xml, '<!DOCTYPE') !== false) || !pg_office_xlsx_utf8_part(substr($xml, 0, 200))
        || !preg_match('/<([A-Za-z_][\w.\-]*:)?worksheet(?=[\s>])([^>]*)>/', $xml, $root)) {
        return $fail('unusual');
    }

    $prefix = $root[1];
    $quoted = preg_quote($prefix, '/');
    preg_match_all('/\sxmlns(?::[A-Za-z_][\w.\-]*)?\s*=\s*(?:"[^"]*"|\'[^\']*\')/', $root[2], $declarations);
    $declarations = implode('', $declarations[0]);
    $column_styles = pg_office_xlsx_column_styles($xml, $prefix);

    if (!preg_match('/<' . $quoted . 'sheetData(?=[\s>\/])[^>]*>/', $xml, $data, PREG_OFFSET_CAPTURE)) {
        return $fail('unusual');
    }

    $closed = (substr($data[0][0], -2) === '/>');
    $data_start = $data[0][1];
    $position = $data_start + strlen($data[0][0]);
    $data_end = $closed ? $position : strpos($xml, '</' . $prefix . 'sheetData>', $position);

    if ($data_end === false) {
        return $fail('unusual');
    }

    // The new cells of a row the sheet lacks.
    $new_row = function ($number) use ($range, $restyle, $column_styles, $prefix) {
        $cells = '';

        for ($column = $range[1]; $column <= $range[3]; $column++) {
            $style = $restyle(isset($column_styles[$column]) ? (int) $column_styles[$column] : 0);

            if ($style === null) {
                return null;
            }

            if ($style !== 0) {
                $cells .= '<' . $prefix . 'c r="' . pg_office_xlsx_column_name($column) . $number . '" s="' . $style . '"/>';
            }
        }

        return ($cells === '') ? '' : '<' . $prefix . 'row r="' . $number . '">' . $cells . '</' . $prefix . 'row>';
    };

    $out = '';
    $copied = 0;
    $next = $range[0];
    $close = '</' . $prefix . 'row>';
    $pattern = '/<' . $quoted . 'row(?=[\s>\/])[^>]*>/';

    // The missing rows before $until, written at $at.
    $fill = function ($until, $at) use (&$next, &$out, &$copied, $xml, $new_row, $range) {
        $made = '';

        for (; ($next < $until) && ($next <= $range[2]); $next++) {
            $row = $new_row($next);

            if ($row === null) {
                return false;
            }

            $made .= $row;
        }

        if ($made !== '') {
            $out .= substr($xml, $copied, $at - $copied) . $made;
            $copied = $at;
        }

        return true;
    };

    while (!$closed && ($next <= $range[2]) && preg_match($pattern, $xml, $tag, PREG_OFFSET_CAPTURE, $position) && ($tag[0][1] < $data_end)) {
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
        $position = $finish;

        if ($row_number < $next) {
            continue;
        }

        if (!$fill($row_number, $start)) {
            return $fail('styles');
        }

        if ($row_number > $range[2]) {
            break;
        }

        $patched = pg_office_xlsx_format_row(substr($xml, $start, $finish - $start), $row_number, $range, $restyle, $declarations, $prefix, $column_styles);

        if (!$patched['ok']) {
            return $fail($patched['error']);
        }

        $out .= substr($xml, $copied, $start - $copied) . $patched['xml'];
        $copied = $finish;
        $next = $row_number + 1;
    }

    if ($closed) {
        $made = '';

        for (; $next <= $range[2]; $next++) {
            $row = $new_row($next);

            if ($row === null) {
                return $fail('styles');
            }

            $made .= $row;
        }

        if ($made === '') {
            return array('ok' => true, 'xml' => $xml, 'error' => '');
        }

        $open = substr($data[0][0], 0, -2) . '>';

        return array('ok' => true, 'xml' => substr($xml, 0, $data_start) . $open . $made . '</' . $prefix . 'sheetData>' . substr($xml, $position), 'error' => '');
    }

    if (!$fill($range[2] + 1, $data_end)) {
        return $fail('styles');
    }

    return array('ok' => true, 'xml' => $out . substr($xml, $copied), 'error' => '');
}

/**
 * Points the cells of one row inside the range to their new styles, making
 * the cells it lacks.
 *
 * @param string   $row_xml
 * @param int      $number
 * @param array    $range
 * @param callable $restyle
 * @param string   $declarations the sheet root's namespace declarations
 * @param string   $prefix
 * @param array    $column_styles
 * @return array ok, xml, error
 */
function pg_office_xlsx_format_row($row_xml, $number, $range, $restyle, $declarations, $prefix, $column_styles)
{
    $fail = function ($error) {
        return array('ok' => false, 'xml' => '', 'error' => $error);
    };

    if (strlen((string) $row_xml) > PG_OFFICE_MAX_DOM) {
        return $fail('large');
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadXML('<?xml version="1.0" encoding="UTF-8"?><pg-row ' . $declarations . '>' . $row_xml . '</pg-row>', LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $row = $loaded ? pg_office_xlsx_child($document->documentElement, 'row') : null;

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

    $added = array();

    for ($column = $range[1]; $column <= $range[3]; $column++) {
        $cell = isset($cells[$column]) ? $cells[$column] : null;
        $old = ($cell !== null) ? (int) $cell->getAttribute('s') : (int) pg_office_xlsx_new_cell_style($row, $column, $column_styles);
        $style = $restyle($old);

        if ($style === null) {
            return $fail('styles');
        }

        if ($cell === null) {
            if ($style === 0) {
                continue;
            }

            $cell = $document->createElement($prefix . 'c');
            $cell->setAttribute('r', pg_office_xlsx_column_name($column) . $number);
            $later = null;

            foreach ($cells as $other_column => $other) {
                if ($other_column > $column) {
                    $later = $other;
                    break;
                }
            }

            if ($later !== null) {
                $row->insertBefore($cell, $later);
            } else {
                pg_office_xlsx_append($row, $cell);
            }

            $cells[$column] = $cell;
            ksort($cells);
            $added[] = $column;
        }

        if ($style === 0) {
            $cell->removeAttribute('s');
        } else {
            $cell->setAttribute('s', (string) $style);
        }
    }

    if (!empty($added) && $row->hasAttribute('spans')) {
        if (preg_match('/^(\d+):(\d+)$/', (string) $row->getAttribute('spans'), $span)) {
            $row->setAttribute('spans', min((int) $span[1], min($added) + 1) . ':' . max((int) $span[2], max($added) + 1));
        } else {
            $row->removeAttribute('spans');
        }
    }

    return array('ok' => true, 'xml' => $document->saveXML($row), 'error' => '');
}

/**
 * Whether a sheet's protection keeps its cells from being formatted: a
 * protected sheet does unless its protection says formatCells="0".
 *
 * @param string $xml the sheet part
 * @return bool
 */
function pg_office_format_protected($xml)
{
    if (!preg_match('/<(?:[A-Za-z_][\w.\-]*:)?sheetProtection(?=[\s>\/])([^>]*)>/', (string) $xml, $tag)) {
        return false;
    }

    $attribute = function ($name) use ($tag) {
        return preg_match('/\s' . $name . '\s*=\s*["\']([^"\']*)["\']/', $tag[1], $match) ? strtolower($match[1]) : null;
    };

    return in_array($attribute('sheet'), array('1', 'true'), true) && !in_array($attribute('formatCells'), array('0', 'false'), true);
}

/* ---------------------------------------------------------------------------
   The save
   --------------------------------------------------------------------------- */

/**
 * Writes one format change into a workbook on disk, under the file's lock,
 * as pg_office_xlsx_save() writes cells.
 *
 * @param string   $path
 * @param array    $format    pg_office_format_request()
 * @param string   $version
 * @param string   $directory '' for data/temp
 * @param callable $fail
 * @return array ok, code, error, size, version, changed
 */
function pg_office_xlsx_format_save($path, $format, $version, $directory, $fail)
{
    $range = $format['range'];

    if ((($range[2] - $range[0] + 1) * ($range[3] - $range[1] + 1)) > PG_OFFICE_EDIT_MAX_CHANGES) {
        return $fail('many');
    }

    $directory = ($directory !== '') ? $directory : pg_office_edit_temp_directory();

    if (!is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }

    $lock = pg_office_edit_lock($path, $directory);

    if ($lock === null) {
        return $fail('busy');
    }

    $result = pg_office_save_package($path, $version, $directory, $fail, function ($zip, $spool) use ($format, $fail) {
        return pg_office_xlsx_format_parts($zip, $format, $fail, $spool);
    });

    pg_office_edit_unlock($lock);

    return $result;
}

/**
 * The two parts a format change writes: the sheet and the styles part.
 *
 * @param ZipArchive $zip
 * @param array      $format pg_office_format_request()
 * @param callable   $fail
 * @param callable   $spool
 * @return array ok, parts, changed; or a refusal
 */
function pg_office_xlsx_format_parts($zip, $format, $fail, $spool)
{
    if (pg_office_signed($zip)) {
        return $fail('signed');
    }

    $sheets = pg_office_xlsx_sheets($zip, $refused);

    if ($refused !== '') {
        return array('ok' => false, 'code' => 'read', 'error' => pg_office_refusal_error($refused), 'size' => 0, 'version' => '', 'changed' => 0);
    }

    if (pg_office_xlsx_write_reserved((string) pg_zip_entry_contents($zip, 'xl/workbook.xml', PG_OFFICE_MAX_DOM, $unread))) {
        return $fail('password');
    }

    $index = $format['sheet'];

    if (!isset($sheets[$index]) || (($format['name'] !== null) && ($format['name'] !== $sheets[$index]['name']))) {
        return $fail('changed');
    }

    $sheet = $sheets[$index];

    if ($sheet['type'] !== 'worksheet') {
        return $fail('sheet', '', $sheet['name']);
    }

    $styles_part = pg_sheet_xlsx_part($zip, 'styles', 'xl/styles.xml');
    $stat = ($styles_part === '') ? false : $zip->statName($styles_part);

    if ($stat === false) {
        return $fail('format_styles', '', $sheet['name']);
    }

    if ((int) $stat['size'] > PG_OFFICE_MAX_DOM) {
        return $fail('format_large', '', $sheet['name']);
    }

    $stat = $zip->statName($sheet['part']);

    if (($stat === false) || ((int) $stat['size'] > PG_OFFICE_EDIT_MAX_PART)) {
        return $fail('large', '', $sheet['name']);
    }

    $xml = (string) pg_zip_entry_contents($zip, $sheet['part'], PG_OFFICE_EDIT_MAX_PART, $unread);

    if (pg_office_format_protected($xml)) {
        return $fail('format_protected', '', $sheet['name']);
    }

    $styles_xml = (string) pg_zip_entry_contents($zip, $styles_part, PG_OFFICE_MAX_DOM, $unread);
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $loaded = ($styles_xml !== '') && (stripos($styles_xml, '<!DOCTYPE') === false) && $document->loadXML($styles_xml, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded || ($document->documentElement->localName !== 'styleSheet')) {
        return $fail('format_styles', '', $sheet['name']);
    }

    $writer = pg_office_format_styles($document);
    $set = $format['set'];
    $patched = pg_office_xlsx_format_sheet($xml, $format['range'], function ($style) use ($writer, $set) {
        return $writer['restyle']($style, $set);
    });

    unset($xml);

    if (!$patched['ok']) {
        if ($patched['error'] === 'styles') {
            return $fail('format_styles', '', $sheet['name']);
        }

        return $fail(($patched['error'] === 'large') ? 'large' : 'sheet', '', $sheet['name']);
    }

    $styles_xml = $document->saveXML();

    if (!pg_office_xml_well_formed($patched['xml']) || !pg_office_xml_well_formed($styles_xml)) {
        return $fail('sheet', '', $sheet['name']);
    }

    $parts = array();

    foreach (array($sheet['part'] => $patched['xml'], $styles_part => $styles_xml) as $name => $part) {
        $file = $spool($part);

        if ($file === null) {
            return $fail('save');
        }

        $parts[$name] = $file;
    }

    $range = $format['range'];

    return array('ok' => true, 'parts' => $parts, 'changed' => ($range[2] - $range[0] + 1) * ($range[3] - $range[1] + 1));
}

/**
 * The message for a refused format change.
 *
 * @param string $code
 * @param string $sheet
 * @return string
 */
function pg_office_format_message($code, $sheet)
{
    switch ($code) {
        case 'format_protected':
            return lang(array('string' => 'Sheet {var:1} is protected and does not allow formatting cells.', 'vars' => array($sheet)));
        case 'format_styles':
            return lang('The styles of this workbook could not be read, so its cells are not formatted here.');
        case 'format_large':
            return lang('The styles of this workbook are too large to change here.');
    }

    return lang('The file could not be saved.');
}
