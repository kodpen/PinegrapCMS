<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - an agent's edits to a canvas, in place: operations on the
 * newest version's document (add an SVG as a layer, replace an svg layer's
 * markup, remove a layer, change a text layer's words or a layer's box, or
 * draw the whole canvas anew). Each edit is a new version (source agent),
 * its picture pending again until a member's browser draws it. The person
 * whose canvas an agent edited is told.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_svg.php');

/**
 * The operations an edit takes.
 *
 * @return string[]
 */
function ws_canvas_edit_ops()
{
    return array('add', 'replace', 'remove', 'text', 'box', 'svg');
}

/**
 * The most operations one edit carries.
 *
 * @return int
 */
function ws_canvas_edit_max_ops()
{
    return 20;
}

/**
 * Every id a document's svg layers and definitions hold, which a new layer
 * must not take again (takenIds() of pg_image_editor_svg.js). Pure.
 *
 * @param array  $doc        decoded as arrays
 * @param string $except_id  a layer left out (the one being replaced)
 * @return string[]
 */
function ws_canvas_taken_ids($doc, $except_id = '')
{
    $text = (string) ($doc['svgDefs'] ?? '');

    foreach ((array) ($doc['layers'] ?? array()) as $layer) {
        if (is_array($layer) && (($layer['type'] ?? '') === 'svg') && ((string) ($layer['id'] ?? '') !== $except_id)) {
            $text .= (string) ($layer['markup'] ?? '');
        }
    }

    preg_match_all('/\sid="([^"]*)"/', $text, $m);
    $ids = $m[1];

    if (!empty($doc['svgRootId']) && is_string($doc['svgRootId'])) {
        $ids[] = $doc['svgRootId'];
    }

    return $ids;
}

/**
 * A layer id not yet in the document (M.newId()). Pure.
 *
 * @param array $doc decoded as arrays
 * @return string
 */
function ws_canvas_new_layer_id($doc)
{
    $taken = array();

    foreach ((array) ($doc['layers'] ?? array()) as $layer) {
        if (is_array($layer) && isset($layer['id'])) {
            $taken[(string) $layer['id']] = true;
        }
    }

    $n = count((array) ($doc['layers'] ?? array()));

    while (isset($taken['l' . $n])) {
        $n++;
    }

    return 'l' . $n;
}

/**
 * The index of a layer in the document by its id, or -1. Pure.
 *
 * @param array $doc
 * @param mixed $id
 * @return int
 */
function ws_canvas_layer_index($doc, $id)
{
    if (!is_string($id) || ($id === '')) {
        return -1;
    }

    foreach ((array) ($doc['layers'] ?? array()) as $i => $layer) {
        if (is_array($layer) && ((string) ($layer['id'] ?? '') === $id)) {
            return (int) $i;
        }
    }

    return -1;
}

/**
 * A box an operation gives (x, y, w, h, rot; each optional), checked onto a
 * layer: numbers, a width and height above 0, nothing beyond 100000. Pure.
 *
 * @param array $layer
 * @param array $op
 * @return array|null the layer with its new box, null when a value is refused
 */
function ws_canvas_box_apply($layer, $op)
{
    foreach (array('x', 'y', 'w', 'h', 'rot') as $key) {
        if (!array_key_exists($key, $op)) {
            continue;
        }

        $value = $op[$key];

        if (!is_int($value) && !is_float($value)) {
            return null;
        }

        if ((abs($value) > 100000) || ((($key === 'w') || ($key === 'h')) && ($value <= 0))) {
            return null;
        }

        $layer[$key] = $value + 0;
    }

    return $layer;
}

/**
 * The box a new svg layer takes when the operation gives none: its own size,
 * made smaller to fit the artboard, centred. Pure.
 *
 * @param int   $w the SVG's size
 * @param int   $h
 * @param array $board width, height
 * @return array x, y, w, h
 */
function ws_canvas_fit_box($w, $h, $board)
{
    $bw = max(1, (int) ($board['width'] ?? 1));
    $bh = max(1, (int) ($board['height'] ?? 1));
    $k = min(1, $bw / max(1, $w), $bh / max(1, $h));
    $number = function ($n) {
        $n = round($n, 2);

        return ($n == floor($n)) ? (int) $n : $n;
    };
    $fw = $number($w * $k);
    $fh = $number($h * $k);

    return array('x' => $number(($bw - $fw) / 2), 'y' => $number(($bh - $fh) / 2), 'w' => $fw, 'h' => $fh);
}

/**
 * The layers word of an add or svg operation; null when it is neither
 * split nor one. Pure.
 *
 * @param array $op
 * @return string|null
 */
function ws_canvas_edit_split_mode($op)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_split.php');

    return ws_canvas_split_mode($op['layers'] ?? null);
}

/**
 * Applies an agent's operations to a canvas document, in order; all or
 * none. Pure but for the sanitizer.
 *
 *   add      svg; x, y, w, h, rot optional: a new imported svg layer on top
 *   replace  layer, svg: an svg layer's markup, its box kept
 *   remove   layer
 *   text     layer, text; color #rrggbb optional: a text layer's words
 *   box      layer; x, y, w, h, rot: move, resize or turn a layer
 *   svg      svg; background optional: the whole canvas anew
 *
 * add and svg take layers: split for a layer per top-level element
 * (canvas_split.php), one (the default) for one imported layer.
 * A locked layer is not changed. The result is marked picture: pending.
 *
 * @param array $doc        the newest version's document, decoded as arrays
 * @param mixed $operations list of operation objects, decoded as arrays
 * @param callable|null $resolve with layers split: a picture's href => the
 *                               file it names (ws_canvas_split_file())
 * @return array error ('' or the refusal), index (of the refused operation, -1), doc (array)
 */
function ws_canvas_edit_apply($doc, $operations, $resolve = null)
{
    $fail = function ($error, $index = -1) {
        return array('error' => $error, 'index' => $index, 'doc' => null);
    };

    if (!is_array($operations) || ($operations === array()) || (array_values($operations) !== $operations)) {
        return $fail('operations');
    }

    if (count($operations) > ws_canvas_edit_max_ops()) {
        return $fail('too_many');
    }

    foreach ($operations as $i => $op) {
        $kind = is_array($op) ? (string) ($op['op'] ?? '') : '';

        if (!in_array($kind, ws_canvas_edit_ops(), true)) {
            return $fail('op', $i);
        }

        $mode = in_array($kind, array('svg', 'add'), true) ? ws_canvas_edit_split_mode($op) : 'one';

        if ($mode === null) {
            return $fail('layers', $i);
        }

        if ($kind === 'svg') {
            $current = (string) ($doc['artboard']['background'] ?? '#ffffff');
            $made = ws_canvas_from_svg($op['svg'] ?? '', array_key_exists('background', $op) ? $op['background'] : $current, $mode === 'split', $resolve);

            if ($made['error'] !== '') {
                return $fail($made['error'], $i);
            }

            $doc = json_decode(json_encode($made['doc']), true);
            continue;
        }

        if ($kind === 'add') {
            $svg = $op['svg'] ?? '';

            if (!is_string($svg) || (strlen($svg) > ws_canvas_svg_max_bytes())) {
                return $fail(is_string($svg) ? 'too_large' : 'empty', $i);
            }

            if ($mode === 'split') {
                // The layers go where the one imported layer would have
                // gone, as Break apart leaves them.
                $parts = ws_canvas_split_svg($svg, ws_canvas_taken_ids($doc), $resolve);

                if ($parts === null) {
                    return $fail('empty', $i);
                }

                $frame = ws_canvas_box_apply(ws_canvas_fit_box($parts['w'], $parts['h'], (array) ($doc['artboard'] ?? array())) + array('rot' => 0), $op);

                if ($frame === null) {
                    return $fail('box', $i);
                }

                $doc = ws_canvas_split_into($doc, $parts, $frame);
                continue;
            }

            $clean = pg_svg_sanitize($svg);

            $made = ($clean === '') ? null : ws_canvas_svg_layer($clean, ws_canvas_taken_ids($doc));

            if ($made === null) {
                return $fail('empty', $i);
            }

            $layer = array_merge(array('id' => ws_canvas_new_layer_id($doc)), $made['layer'], ws_canvas_fit_box($made['w'], $made['h'], (array) ($doc['artboard'] ?? array())));
            $layer = ws_canvas_box_apply($layer, $op);

            if ($layer === null) {
                return $fail('box', $i);
            }

            $doc['layers'][] = $layer;
            continue;
        }

        $at = ws_canvas_layer_index($doc, $op['layer'] ?? null);

        if ($at < 0) {
            return $fail('layer', $i);
        }

        $layer = $doc['layers'][$at];

        if (!empty($layer['locked'])) {
            return $fail('locked', $i);
        }

        if ($kind === 'remove') {
            array_splice($doc['layers'], $at, 1);
            continue;
        }

        if ($kind === 'box') {
            $layer = ws_canvas_box_apply($layer, $op);

            if ($layer === null) {
                return $fail('box', $i);
            }
        } elseif ($kind === 'text') {
            $text = $op['text'] ?? null;
            $color = $op['color'] ?? null;

            if ((($layer['type'] ?? '') !== 'text') || !is_string($text) || (mb_strlen($text) > 2000)) {
                return $fail('text', $i);
            }

            if (($color !== null) && (!is_string($color) || !preg_match('/^#[0-9a-fA-F]{6}$/', $color))) {
                return $fail('color', $i);
            }

            $layer['text'] = $text;

            if ($color !== null) {
                $layer['color'] = strtolower($color);
            }
        } else {
            // replace: an svg layer's drawing, where it was.
            $svg = $op['svg'] ?? '';

            if (($layer['type'] ?? '') !== 'svg') {
                return $fail('not_svg', $i);
            }

            if (!is_string($svg) || (strlen($svg) > ws_canvas_svg_max_bytes())) {
                return $fail(is_string($svg) ? 'too_large' : 'empty', $i);
            }

            $clean = pg_svg_sanitize($svg);
            $made = ($clean === '') ? null : ws_canvas_svg_layer($clean, ws_canvas_taken_ids($doc, (string) $layer['id']));

            if ($made === null) {
                return $fail('empty', $i);
            }

            $layer['markup'] = $made['layer']['markup'];
            $layer['vb'] = $made['layer']['vb'];
            $layer['kind'] = 'g';
            $layer['imported'] = true;
        }

        $doc['layers'][$at] = $layer;
    }

    $doc['picture'] = 'pending';

    return array('error' => '', 'index' => -1, 'doc' => $doc);
}

/**
 * The words of a refusal of ws_canvas_edit_apply().
 *
 * @param string $error
 * @return string
 */
function ws_canvas_edit_error_text($error)
{
    switch ($error) {
        case 'operations':
            return lang('Send the changes as a list of operations.');
        case 'too_many':
            return lang(array('string' => 'At most {var:1} operations at once.', 'vars' => ws_canvas_edit_max_ops()));
        case 'op':
            return lang('An operation is one of add, replace, remove, text, box or svg.');
        case 'layer':
            return lang('That layer is not on the canvas.');
        case 'locked':
            return lang('That layer is locked.');
        case 'box':
            return lang('A box is numbers: x, y, w and h, w and h above 0, rot in degrees.');
        case 'text':
            return lang('Only a text layer takes text, at most 2000 letters.');
        case 'color':
            return lang('A colour is written as #rrggbb.');
        case 'not_svg':
            return lang('Only an svg layer takes new SVG.');
        case 'layers':
            return ws_canvas_from_svg_error_text('layers');
        default:
            return ws_canvas_from_svg_error_text($error);
    }
}

/**
 * The inbox line of an agent's edit to one's canvas (notify.php).
 *
 * @param array  $row          ws_inbox row
 * @param string $channel_name
 * @param string $base         the panel's address
 * @return array title, body, url, icon
 */
function ws_canvas_inbox_describe($row, $channel_name, $base)
{
    $message = ((int) $row['message_id'] > 0) ? ws_message($row['message_id']) : null;
    $agent = '';

    if ($message && ((int) $message['file_id'] > 0) && function_exists('pg_file_versions_ready') && pg_file_versions_ready()) {
        $doc = json_decode((string) db_value("SELECT doc_json FROM file_versions WHERE file_id = '" . (int) $message['file_id'] . "' AND source = 'agent' ORDER BY version_no DESC LIMIT 1"), true);
        $agent = is_array($doc) && isset($doc['by']['handle']) && is_string($doc['by']['handle']) ? $doc['by']['handle'] : '';
    }

    return array(
        'title' => ($agent !== '')
            ? lang(array('string' => '@{var:1} edited your canvas in {var:2}', 'vars' => array($agent, $channel_name)))
            : lang(array('string' => 'An agent edited your canvas in {var:1}', 'vars' => $channel_name)),
        'body'  => $message ? (string) $message['file_name'] : '',
        'url'   => $base . 'workspace.php?channel=' . (int) $row['channel_id'] . '&message=' . (int) $row['message_id'],
        'icon'  => 'bi-brush',
    );
}
