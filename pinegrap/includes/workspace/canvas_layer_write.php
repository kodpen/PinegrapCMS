<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - an agent writes a canvas's layers one by one (canvas_edit.php):
 * a text, a picture of the site's files, a drawn shape (rectangle, ellipse,
 * line) or a Bootstrap icon added as the image editor's own layer kinds,
 * and a layer's style, order, name and lock changed. The layers are the
 * ones the editor's tools make (Text, Draw, Sticker's icons), so a person
 * opens them as if they had drawn them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_split.php');

/**
 * The layer kinds an add makes besides svg.
 *
 * @return string[]
 */
function ws_canvas_write_types()
{
    return array('svg', 'text', 'picture', 'shape', 'icon');
}

/**
 * The editor's system font stacks (TEXT_FONTS of the model).
 *
 * @return string[]
 */
function ws_canvas_write_stacks()
{
    return array('sans', 'serif', 'hand', 'display', 'mono');
}

/**
 * The fonts an agent may name for a text: the editor's system stacks, then
 * the site's style fonts and the Google Fonts catalogue the Text tool
 * offers, as g:<family>.
 *
 * @return array[] font, family, source (system, site, google)
 */
function ws_canvas_write_fonts()
{
    $out = array();

    foreach (ws_canvas_write_stacks() as $stack) {
        $out[] = array('font' => $stack, 'family' => $stack, 'source' => 'system');
    }

    $seen = array();

    foreach (array('site' => ws_canvas_split_site_fonts(), 'google' => ws_canvas_split_google_fonts()) as $source => $families) {
        foreach ($families as $key => $family) {
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $out[] = array('font' => 'g:' . $family, 'family' => $family, 'source' => $source);
            }
        }
    }

    return $out;
}

/**
 * A font as a text layer keeps it, when the editor offers it: a stack, or
 * g:<family> (a family alone is read as g:<family>) of the catalogue given.
 * Pure.
 *
 * @param mixed    $value
 * @param string[] $catalog lower case family => family
 * @return string|null
 */
function ws_canvas_write_font($value, $catalog)
{
    if (!is_string($value)) {
        return null;
    }

    $value = trim($value);

    if (in_array($value, ws_canvas_write_stacks(), true)) {
        return $value;
    }

    $family = strtolower((substr($value, 0, 2) === 'g:') ? substr($value, 2) : $value);

    return isset($catalog[$family]) ? 'g:' . $catalog[$family] : null;
}

/**
 * The code point of a Bootstrap icon by name (the map the editor's Sticker
 * tool reads, assets/fonts/bootstrap-icons/bootstrap-icons.json).
 *
 * @param mixed $name
 * @return int 0 when there is no such icon
 */
function ws_canvas_write_icon($name)
{
    static $map = null;

    if ($map === null) {
        $map = json_decode((string) @file_get_contents(PG_FUNCTIONS_DIR . '/assets/fonts/bootstrap-icons/bootstrap-icons.json'), true);
        $map = is_array($map) ? $map : array();
    }

    $name = is_string($name) ? strtolower(trim(preg_replace('/^bi-/', '', trim($name)))) : '';

    return isset($map[$name]) ? (int) $map[$name] : 0;
}

/**
 * A number of an operation within limits; the default when it is missing,
 * false when it is no number or out of them. Pure.
 *
 * @return float|int|false|null
 */
function ws_canvas_write_number($op, $key, $min, $max, $default = null)
{
    if (!array_key_exists($key, $op)) {
        return $default;
    }

    $v = $op[$key];

    return ((is_int($v) || is_float($v)) && ($v >= $min) && ($v <= $max)) ? $v + 0 : false;
}

/**
 * One of a list of words; the default when missing, false otherwise. Pure.
 *
 * @return string|false|null
 */
function ws_canvas_write_word($op, $key, $words, $default = null)
{
    if (!array_key_exists($key, $op)) {
        return $default;
    }

    return (is_string($op[$key]) && in_array($op[$key], $words, true)) ? $op[$key] : false;
}

/**
 * A colour #rrggbb (lower case); the default when missing, false otherwise.
 * Pure.
 *
 * @return string|false|null
 */
function ws_canvas_write_colour($op, $key, $default = null)
{
    if (!array_key_exists($key, $op)) {
        return $default;
    }

    return (is_string($op[$key]) && preg_match('/^#[0-9a-fA-F]{6}$/', $op[$key])) ? strtolower($op[$key]) : false;
}

/**
 * The fields of a layer's style an operation sets, checked for its type: a
 * text's font, size, weight, italic, align, colour, background, line height
 * and spacing; a drawn shape's colour, fill, stroke, dash and (rect,
 * bubble) radius; an icon's colour; any layer's opacity. Pure but for the
 * font catalogue.
 *
 * @param array    $layer   decoded as arrays
 * @param array    $op
 * @param string[] $catalog
 * @return array|string the fields, or the refusal (style, font, color)
 */
function ws_canvas_write_style_fields($layer, $op, $catalog)
{
    $type = (string) ($layer['type'] ?? '');
    $drawn = array('rect', 'ellipse', 'line', 'arrow', 'triangle', 'polygon', 'star', 'bubble', 'pen');
    $allowed = array('opacity');

    if ($type === 'text') {
        $allowed = array_merge($allowed, array('font', 'size', 'weight', 'italic', 'align', 'color', 'bg', 'lineHeight', 'spacing'));
    } elseif (in_array($type, $drawn, true)) {
        $allowed = array_merge($allowed, array('color', 'stroke', 'dash'), in_array($type, array('line', 'arrow', 'pen'), true) ? array() : array('fill'), in_array($type, array('rect', 'bubble'), true) ? array('radius') : array());
    } elseif ($type === 'glyph') {
        $allowed[] = 'color';
    }

    $out = array();
    $style = array('font', 'size', 'weight', 'italic', 'align', 'color', 'bg', 'lineHeight', 'spacing', 'stroke', 'dash', 'fill', 'radius', 'opacity');

    foreach ($style as $key) {
        if (!array_key_exists($key, $op)) {
            continue;
        }

        if (!in_array($key, $allowed, true)) {
            return 'style';
        }

        switch ($key) {
            case 'font':
                $v = ws_canvas_write_font($op['font'], $catalog);

                if ($v === null) {
                    return 'font';
                }
                break;
            case 'color':
                $v = ws_canvas_write_colour($op, 'color');
                break;
            case 'bg':
                $v = ($op['bg'] === '') ? '' : ws_canvas_write_colour($op, 'bg');
                break;
            case 'size':
                $v = ws_canvas_write_number($op, 'size', 2, 4000);
                break;
            case 'weight':
                $v = ws_canvas_write_number($op, 'weight', 100, 900);
                $v = ($v === false) ? false : ws_canvas_write_weight($v);
                break;
            case 'italic':
                if (!is_bool($op['italic'])) {
                    return 'style';
                }

                $out['italic'] = $op['italic'];
                continue 2;
            case 'align':
                $v = ws_canvas_write_word($op, 'align', array('left', 'center', 'right'));
                break;
            case 'lineHeight':
                $v = ws_canvas_write_number($op, 'lineHeight', 0.5, 4);
                break;
            case 'spacing':
                $v = ws_canvas_write_number($op, 'spacing', -0.5, 2);
                break;
            case 'stroke':
                $v = ws_canvas_write_number($op, 'stroke', 0, 1000);
                break;
            case 'dash':
                $v = ws_canvas_write_word($op, 'dash', array('solid', 'dashed', 'dotted'));
                break;
            case 'fill':
                $v = ws_canvas_write_word($op, 'fill', array('none', 'solid', 'soft'));
                break;
            case 'radius':
                $v = ws_canvas_write_number($op, 'radius', 0, 100000);
                $v = ($v === false) ? false : ws_canvas_split_round(min($v, min((float) ($layer['w'] ?? 0), (float) ($layer['h'] ?? 0)) / 2));
                break;
            default:
                $v = ws_canvas_write_number($op, 'opacity', 0, 1);
        }

        if ($v === false) {
            return ($key === 'color' || $key === 'bg') ? 'color' : 'style';
        }

        $out[$key] = $v;
    }

    return $out;
}

/**
 * A text weight snapped to the editor's (300, 400, 600, 700, 900). Pure.
 *
 * @param int|float $weight
 * @return int
 */
function ws_canvas_write_weight($weight)
{
    $best = 400;

    foreach (array(300, 400, 600, 700, 900) as $w) {
        if (abs($w - $weight) < abs($best - $weight)) {
            $best = $w;
        }
    }

    return $best;
}

/**
 * A text layer's box by the editor's layout, its widths estimated at half
 * the size a letter (the editor fits them to the font when it draws). Pure.
 *
 * @param array $l text, size, lineHeight, spacing, bg, pad
 * @return array w, h
 */
function ws_canvas_write_text_box($l)
{
    $size = (float) $l['size'];
    $pad = ($l['bg'] !== '') ? $l['pad'] * $size : 0;
    $lines = explode("\n", (string) $l['text']);
    $widest = 0;

    foreach ($lines as $line) {
        $n = mb_strlen($line);
        $widest = max($widest, $n * 0.5 * $size + max(0, $n - 1) * $l['spacing'] * $size);
    }

    $round = function ($n) {
        return ws_canvas_split_round(floor($n * 100 + 0.5) / 100);
    };

    return array('w' => $round(max($widest, $size * 0.6) + 2 * $pad), 'h' => $round(count($lines) * $size * $l['lineHeight'] + 2 * $pad));
}

/**
 * A new layer of an add operation of type text, picture, shape or icon,
 * with the editor's defaults for what the operation leaves out; centred on
 * the artboard unless x and y are given.
 *
 * @param array         $doc     decoded as arrays
 * @param array         $op
 * @param callable|null $file    a picture's file or file_id => file
 *                               (ws_canvas_write_file()) or null
 * @param string[]      $catalog fonts
 * @return array|string the layer (without id), or the refusal
 */
function ws_canvas_write_layer($doc, $op, $file, $catalog)
{
    $board = (array) ($doc['artboard'] ?? array());
    $bw = max(1, (int) ($board['width'] ?? 1));
    $bh = max(1, (int) ($board['height'] ?? 1));
    $type = (string) $op['type'];
    $at = function ($layer) use ($op, $bw, $bh) {
        $layer['x'] = array_key_exists('x', $op) ? $op['x'] : ($bw - $layer['w']) / 2;
        $layer['y'] = array_key_exists('y', $op) ? $op['y'] : ($bh - $layer['h']) / 2;

        foreach (array('x', 'y', 'w', 'h') as $key) {
            $layer[$key] = ws_canvas_split_round(floor($layer[$key] * 100 + 0.5) / 100);
        }

        return $layer;
    };

    if ($type === 'text') {
        $text = $op['text'] ?? null;

        if (!is_string($text) || (trim($text) === '') || (mb_strlen($text) > 2000)) {
            return 'text';
        }

        $layer = array('type' => 'text', 'text' => $text, 'font' => 'sans', 'size' => max(12, (int) round(min($bw, $bh) / 12)), 'weight' => 700, 'italic' => false,
            'align' => 'left', 'lineHeight' => 1.25, 'spacing' => 0, 'color' => '#000000', 'bg' => '', 'pad' => 0.3, 'radius' => 0.16, 'outline' => null, 'shadow' => null);
        $style = ws_canvas_write_style_fields($layer, $op, $catalog);

        if (!is_array($style)) {
            return $style;
        }

        $layer = array_merge($layer, $style);
        $layer += ws_canvas_write_text_box($layer);
        $layer['rot'] = 0;
        $layer['opacity'] = $layer['opacity'] ?? 1;

        return $at($layer);
    }

    if ($type === 'icon') {
        $code = ws_canvas_write_icon($op['icon'] ?? null);
        $color = ws_canvas_write_colour($op, 'color', '#212529');

        if (!$code) {
            return 'icon';
        }

        if ($color === false) {
            return 'color';
        }

        $side = ws_canvas_split_round(floor(min($bw, $bh) / 5 * 100 + 0.5) / 100);

        return $at(array('type' => 'glyph', 'set' => 'bi', 'code' => $code, 'color' => $color, 'w' => $side, 'h' => $side, 'rot' => 0, 'opacity' => 1));
    }

    if ($type === 'picture') {
        $found = $file ? call_user_func($file, $op) : null;

        if (!is_array($found)) {
            return 'picture';
        }

        $fw = max(1, (int) $found['width']);
        $fh = max(1, (int) $found['height']);
        $k = min(1, 0.4 * $bw / $fw, 0.4 * $bh / $fh);

        if (((int) $found['width'] <= 0) || ((int) $found['height'] <= 0)) {
            $fw = $fh = 1;
            $k = 0.4 * min($bw, $bh);
        }

        return $at(array('type' => 'image', 'src' => (string) $found['url'], 'fileId' => (int) $found['id'], 'w' => $fw * $k, 'h' => $fh * $k, 'rot' => 0, 'opacity' => 1));
    }

    if ($type === 'shape') {
        $kinds = array('rectangle' => 'rect', 'ellipse' => 'ellipse', 'line' => 'line');
        $kind = (is_string($op['shape'] ?? null) && isset($kinds[$op['shape']])) ? $kinds[$op['shape']] : '';

        if ($kind === '') {
            return 'shape';
        }

        // The Draw tool's medium line for the picture's size (M.strokeFor()).
        $layer = array('type' => $kind, 'color' => '#000000', 'stroke' => max(1, round(0.42 * max($bw, $bh) / 10) / 10), 'opacity' => 1, 'dash' => 'solid');

        if ($kind !== 'line') {
            $layer['fill'] = 'solid';
            $layer['w'] = $bw / 3;
            $layer['h'] = $bh / 3;
        }

        if ($kind === 'rect') {
            $layer['radius'] = 0;
        }

        if ($kind === 'line') {
            foreach (array('x1', 'y1', 'x2', 'y2') as $key) {
                $v = ws_canvas_write_number($op, $key, -100000, 100000);

                if (($v === null) || ($v === false)) {
                    return 'line';
                }
            }
        }

        $sides = array_intersect_key($op, array('w' => 0, 'h' => 0));
        $style = ws_canvas_write_style_fields(array_merge($layer, $sides), $op, $catalog);

        if (!is_array($style)) {
            return $style;
        }

        $layer = array_merge($layer, $style);

        if ($kind === 'line') {
            // The box runs along the line, as thick as its stroke
            // (M.lineBox()).
            $dx = $op['x2'] - $op['x1'];
            $dy = $op['y2'] - $op['y1'];
            $len = max(1, hypot($dx, $dy));
            $deg = floor(atan2($dy, $dx) * 180 / M_PI * 1e6 + 0.5) / 1e6;
            $thick = max(1, $layer['stroke']);
            $round = function ($n) {
                return ws_canvas_split_round(floor($n * 100 + 0.5) / 100);
            };

            return $layer + array('x' => $round(($op['x1'] + $op['x2']) / 2 - $len / 2), 'y' => $round(($op['y1'] + $op['y2']) / 2 - $thick / 2), 'w' => $round($len), 'h' => $round($thick),
                'rot' => ws_canvas_split_round(($deg == -180) ? 180 : $deg));
        }

        $layer['rot'] = 0;

        return $at($layer);
    }

    return 'type';
}

/**
 * A layer's place in the stack moved: to the front (top), the back
 * (bottom), or just above or below another layer. Pure.
 *
 * @param array $doc
 * @param int   $at     the layer's index
 * @param array $op     to (front, back, above, below), target for above and below
 * @return array|string the document, or the refusal (order, layer)
 */
function ws_canvas_write_order($doc, $at, $op)
{
    $to = $op['to'] ?? null;

    if (!in_array($to, array('front', 'back', 'above', 'below'), true)) {
        return 'order';
    }

    $layers = array_values((array) $doc['layers']);
    $layer = $layers[$at];
    array_splice($layers, $at, 1);

    if ($to === 'front') {
        $layers[] = $layer;
    } elseif ($to === 'back') {
        array_unshift($layers, $layer);
    } else {
        $target = ws_canvas_layer_index(array('layers' => $layers), $op['target'] ?? null);

        if ($target < 0) {
            return 'layer';
        }

        array_splice($layers, ($to === 'above') ? $target + 1 : $target, 0, array($layer));
    }

    $doc['layers'] = $layers;

    return $doc;
}

/**
 * A layer name as kept: trimmed, at most 80 letters, no control
 * characters; null when it is empty or no text. Pure.
 *
 * @param mixed $value
 * @return string|null
 */
function ws_canvas_write_name($value)
{
    if (!is_string($value)) {
        return null;
    }

    $value = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value));

    return (($value === '') || (mb_strlen($value) > 80)) ? null : $value;
}

/**
 * Is a name free for a layer: no other layer has it, nor has it as its
 * id? Pure.
 *
 * @param array  $doc
 * @param string $name
 * @param string $own_id the layer being named
 * @return bool
 */
function ws_canvas_write_name_free($doc, $name, $own_id = '')
{
    foreach ((array) ($doc['layers'] ?? array()) as $layer) {
        if (!is_array($layer) || ((string) ($layer['id'] ?? '') === $own_id)) {
            continue;
        }

        if (((string) ($layer['id'] ?? '') === $name) || ((string) ($layer['name'] ?? '') === $name)) {
            return false;
        }
    }

    return true;
}

/**
 * The file a picture operation names: file_id, or file (its address). A
 * resolver over ws_canvas_split_file() and its id twin.
 *
 * @param int  $channel_id
 * @param bool $public the agent may read Files (files:read)
 * @return callable op => file or null
 */
function ws_canvas_write_file_resolver($channel_id, $public)
{
    return function ($op) use ($channel_id, $public) {
        if (isset($op['file_id']) && is_int($op['file_id']) && ($op['file_id'] > 0)) {
            return ws_canvas_split_file_by_id($op['file_id'], (int) $channel_id, (bool) $public);
        }

        return (isset($op['file']) && is_string($op['file'])) ? ws_canvas_split_file($op['file'], (int) $channel_id, (bool) $public) : null;
    };
}
