<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the texts and pictures of an agent's SVG as layers of their
 * own when the SVG is taken apart (canvas_split.php): a text element the
 * image editor's text layer can hold becomes a text layer (words, size,
 * weight, colour, alignment, the font when the editor offers it); an
 * image naming a file of the site the agent may read becomes a picture
 * layer. Anything else stays an svg layer, as before.
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
 * The families of the editor's Google Fonts catalogue (pg_google_fonts.js,
 * the one list the Text tool and the designer share), read from the file
 * once a request.
 *
 * @return string[] lower case family => family
 */
function ws_canvas_split_google_fonts()
{
    static $fonts = null;

    if ($fonts !== null) {
        return $fonts;
    }

    $fonts = array();
    $text = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_google_fonts.js');
    $start = strpos($text, 'var CATEGORIES');
    $end = ($start === false) ? false : strpos($text, '};', $start);

    if ($end !== false) {
        preg_match_all("/'([A-Za-z0-9 _\\-.]+)'/", substr($text, $start, $end - $start), $m);

        foreach ($m[1] as $family) {
            $fonts[strtolower($family)] = $family;
        }
    }

    return $fonts;
}

/**
 * The families the site's styles load (the editor's text_fonts action).
 *
 * @return string[] lower case family => family
 */
function ws_canvas_split_site_fonts()
{
    static $fonts = null;

    if ($fonts !== null) {
        return $fonts;
    }

    $fonts = array();

    if (function_exists('pg_schema_has') && function_exists('pg_image_editor_text_families') && pg_schema_has('style', 'style_custom_fonts')) {
        $rows = db_values("SELECT DISTINCT style_custom_fonts FROM style WHERE style_custom_fonts != ''");

        foreach (is_array($rows) ? $rows : array() as $json) {
            foreach (pg_image_editor_text_families($json) as $family) {
                $fonts[strtolower($family)] = $family;
            }
        }
    }

    return $fonts;
}

/**
 * A text layer's font for a CSS family list: the first family the editor
 * offers. A generic family or a common system face is one of the editor's
 * stacks; a family of the Google catalogue or of the site's styles is
 * g:<family>. Pure but for the catalogues given.
 *
 * @param string   $list    font-family as written
 * @param string[] $catalog lower case family => family
 * @return string|null null when no family is offered
 */
function ws_canvas_split_font($list, $catalog)
{
    $stacks = array(
        'sans-serif' => 'sans', 'system-ui' => 'sans', 'arial' => 'sans', 'helvetica' => 'sans', 'helvetica neue' => 'sans',
        'segoe ui' => 'sans', '-apple-system' => 'sans', 'verdana' => 'sans', 'tahoma' => 'sans',
        'serif' => 'serif', 'georgia' => 'serif', 'times' => 'serif', 'times new roman' => 'serif',
        'monospace' => 'mono', 'ui-monospace' => 'mono', 'courier' => 'mono', 'courier new' => 'mono', 'menlo' => 'mono', 'consolas' => 'mono',
        'cursive' => 'hand', 'comic sans ms' => 'hand', 'fantasy' => 'display', 'impact' => 'display',
    );

    if (trim((string) $list) === '') {
        return 'sans';
    }

    foreach (explode(',', (string) $list) as $family) {
        $family = strtolower(trim(trim($family), '"\''));

        if (isset($catalog[$family])) {
            return 'g:' . $catalog[$family];
        }

        if (isset($stacks[$family])) {
            return $stacks[$family];
        }
    }

    return null;
}

/**
 * A paint as the editor's colour fields keep it: #rrggbb from #rgb,
 * #rrggbb, rgb() or a basic colour name; null for anything else (none, a
 * gradient, currentColor, a colour with alpha). Pure.
 *
 * @param mixed $value
 * @return string|null
 */
function ws_canvas_split_color($value)
{
    $names = array(
        'black' => '#000000', 'white' => '#ffffff', 'red' => '#ff0000', 'green' => '#008000', 'blue' => '#0000ff',
        'yellow' => '#ffff00', 'orange' => '#ffa500', 'purple' => '#800080', 'gray' => '#808080', 'grey' => '#808080',
        'silver' => '#c0c0c0', 'navy' => '#000080', 'teal' => '#008080', 'maroon' => '#800000', 'olive' => '#808000',
        'lime' => '#00ff00', 'aqua' => '#00ffff', 'cyan' => '#00ffff', 'fuchsia' => '#ff00ff', 'magenta' => '#ff00ff',
    );
    $value = strtolower(trim((string) $value));

    if (isset($names[$value])) {
        return $names[$value];
    }

    if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $value, $m)) {
        return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
    }

    if (preg_match('/^#[0-9a-f]{6}$/', $value)) {
        return $value;
    }

    if (preg_match('/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/', $value, $m) && (max($m[1], $m[2], $m[3]) <= 255)) {
        return sprintf('#%02x%02x%02x', $m[1], $m[2], $m[3]);
    }

    return null;
}

/**
 * The scale a matrix gives both sides alike; null when it stretches one
 * more than the other, mirrors or skews, as a text or picture layer
 * cannot. Pure.
 *
 * @param float[] $m
 * @return float|null
 */
function ws_canvas_split_uniform($m)
{
    $sx = hypot($m[0], $m[1]);
    $det = $m[0] * $m[3] - $m[1] * $m[2];

    if (($sx < 1e-9) || ($det <= 0)) {
        return null;
    }

    $sy = $det / $sx;

    return (abs($sx - $sy) <= 1e-6 * $sx) && (abs($m[0] * $m[2] + $m[1] * $m[3]) <= 1e-6 * $sx * hypot($m[2], $m[3])) ? $sx : null;
}

/**
 * The words of a text element as the text layer's lines: its own words, or
 * one tspan a line stacked under the first (same x, moved down by dy or a
 * y). Null for anything a text layer cannot keep: a styled or nested
 * tspan, words beside tspans, letters placed one by one.
 *
 * @param DOMElement $node
 * @return array|null lines, x, y, step (the distance between lines, or 0)
 */
function ws_canvas_split_text_lines($node)
{
    $number = function ($el, $name) {
        $n = ws_canvas_svg_numbers($el->getAttribute($name));

        return (count($n) === 1) ? $n[0] : null;
    };

    foreach (array('rotate', 'textLength', 'dx', 'dy') as $name) {
        if ($node->hasAttribute($name)) {
            return null;
        }
    }

    $x = $node->hasAttribute('x') ? $number($node, 'x') : 0.0;
    $y = $node->hasAttribute('y') ? $number($node, 'y') : 0.0;
    $spans = array();
    $words = '';

    foreach ($node->childNodes as $child) {
        if ($child instanceof DOMText) {
            $words .= $child->data;
        } elseif (($child instanceof DOMElement) && ($child->localName === 'tspan')) {
            $spans[] = $child;
        } elseif ($child instanceof DOMElement) {
            return null;
        }
    }

    $clean = function ($text) {
        return trim(preg_replace('/\s+/u', ' ', $text));
    };

    if ($x === null || $y === null) {
        return null;
    }

    if (!$spans) {
        $text = $clean($words);

        return ($text === '') ? null : array('lines' => array($text), 'x' => $x, 'y' => $y, 'step' => 0);
    }

    if ($clean($words) !== '') {
        return null;
    }

    $lines = array();
    $step = 0;
    $at = null;

    foreach ($spans as $i => $span) {
        foreach ($span->attributes as $a) {
            if (!in_array($a->nodeName, array('x', 'y', 'dy'), true)) {
                return null;
            }
        }

        foreach ($span->childNodes as $c) {
            if (!($c instanceof DOMText)) {
                return null;
            }
        }

        $sx = $span->hasAttribute('x') ? $number($span, 'x') : $x;
        $sy = $span->hasAttribute('y') ? $number($span, 'y') : (($at !== null) ? $at : $y);
        $sy += $span->hasAttribute('dy') ? (float) $number($span, 'dy') : 0;

        if (($sx === null) || ($sy === null) || (($i > 0) && (abs($sx - $x) > 1e-6))) {
            return null;
        }

        if ($i === 0) {
            $x = $sx;
            $y = $sy;
        } else {
            $gap = $sy - $at;

            if (($gap <= 0) || ($step && (abs($gap - $step) > 1e-6))) {
                return null;
            }

            $step = $gap;
        }

        $at = $sy;
        $lines[] = $clean($span->textContent);
    }

    return (implode('', $lines) === '') ? null : array('lines' => $lines, 'x' => $x, 'y' => $y, 'step' => $step);
}

/**
 * A text element as the editor's text layer, placed through matrix m;
 * null when the layer could not hold it and it stays an svg layer. The box
 * is the editor's layout with estimated widths (half the size a letter):
 * the editor fits the width to the font when the layer is drawn, keeping
 * the side the text is aligned to.
 *
 * @param DOMElement $node
 * @param float[]    $m       the root's matrix times the element's own
 * @param string[]   $catalog lower case family => family
 * @return array|null
 */
function ws_canvas_split_text_layer($node, $m, $catalog)
{
    $prop = function ($name) use ($node) {
        return strtolower(ws_canvas_split_inherited($node, $name));
    };

    // Rules of a style sheet reach it by class: what they set is unknown.
    if (($node->getAttribute('class') !== '') || ($prop('display') === 'none')) {
        return null;
    }

    foreach (array('filter', 'clip-path', 'mask', 'writing-mode', 'text-decoration', 'baseline-shift') as $name) {
        if (!in_array($prop($name), array('', 'none', 'normal', 'horizontal-tb', 'lr-tb', 'lr'), true)) {
            return null;
        }
    }

    if (!in_array($prop('stroke'), array('', 'none'), true) || !in_array($prop('dominant-baseline'), array('', 'auto', 'alphabetic'), true)) {
        return null;
    }

    $scale = ws_canvas_split_uniform($m);
    $lines = ws_canvas_split_text_lines($node);
    $color = ws_canvas_split_color(($prop('fill') === '') ? 'black' : $prop('fill'));
    $font = ws_canvas_split_font(ws_canvas_split_inherited($node, 'font-family'), $catalog);
    $size_text = $prop('font-size');
    $size = ($size_text === '') ? 16.0 : ws_canvas_split_number($size_text);
    $spacing_text = $prop('letter-spacing');
    $spacing = in_array($spacing_text, array('', 'normal'), true) ? 0.0 : ws_canvas_split_number($spacing_text);

    if (!$scale || !$lines || !$color || ($font === null) || ($size <= 0) || (($spacing === 0.0) && !in_array($spacing_text, array('', 'normal', '0', '0px'), true))) {
        return null;
    }

    $text = implode("\n", $lines['lines']);

    if (mb_strlen($text) > 2000) {
        return null;
    }

    $weight_text = $prop('font-weight');
    $weight = ($weight_text === 'bold' || $weight_text === 'bolder') ? 700 : (is_numeric($weight_text) ? (int) $weight_text : 400);
    $nearest = 400;

    foreach (array(300, 400, 600, 700, 900) as $w) {
        if (abs($w - $weight) < abs($nearest - $weight)) {
            $nearest = $w;
        }
    }

    $anchor = $prop('text-anchor');
    $align = ($anchor === 'middle') ? 'center' : (($anchor === 'end') ? 'right' : 'left');
    $line_height = $lines['step'] ? $lines['step'] / $size : 1.25;
    $lh = $size * $line_height;
    $widest = 0;

    foreach ($lines['lines'] as $line) {
        $widest = max($widest, mb_strlen($line) * 0.5 * $size + max(0, mb_strlen($line) - 1) * $spacing);
    }

    $w = max($widest, $size * 0.6);
    $h = count($lines['lines']) * $lh;
    $left = $lines['x'] - (($align === 'center') ? $w / 2 : (($align === 'right') ? $w : 0));
    // The first baseline of the editor's layout: the line's spare height
    // shared above and below, then the ascent (0.8 and 0.2 of the size).
    $top = $lines['y'] - (($lh - $size) / 2 + 0.8 * $size);
    $box = ws_canvas_split_place(array('x' => $left, 'y' => $top, 'w' => $w, 'h' => $h), $m);

    if (!$box || !empty($box['mirror'])) {
        return null;
    }

    $opacity = (float) (($prop('opacity') === '') ? 1 : ws_canvas_split_number($prop('opacity')))
        * (float) (($prop('fill-opacity') === '') ? 1 : ws_canvas_split_number($prop('fill-opacity')));
    $round = function ($n) {
        return floor($n * 100 + 0.5) / 100;
    };

    return array(
        'type'       => 'text',
        'text'       => $text,
        'font'       => $font,
        'size'       => ws_canvas_split_round($round($size * $scale)),
        'weight'     => $nearest,
        'italic'     => in_array($prop('font-style'), array('italic', 'oblique'), true),
        'align'      => $align,
        'lineHeight' => ws_canvas_split_round($round($line_height)),
        'spacing'    => ws_canvas_split_round(floor($spacing / $size * 1000 + 0.5) / 1000),
        'color'      => $color,
        'bg'         => '',
        'pad'        => 0.3,
        'radius'     => 0.16,
        'outline'    => null,
        'shadow'     => null,
        'x'          => ws_canvas_split_round($box['x']),
        'y'          => ws_canvas_split_round($box['y']),
        'w'          => ws_canvas_split_round($box['w']),
        'h'          => ws_canvas_split_round($box['h']),
        'rot'        => ws_canvas_split_round($box['rot']),
        'opacity'    => ws_canvas_split_round(max(0, min(1, $opacity))),
    );
}

/**
 * An image element naming a file the agent may use, as a picture layer
 * placed through matrix m: its box, the file's picture fitted inside as
 * preserveAspectRatio's default (meet) draws it, stretched with none. Null
 * when it stays an svg layer.
 *
 * @param DOMElement $node
 * @param float[]    $m
 * @param array      $file id, url, width, height
 * @return array|null
 */
function ws_canvas_split_picture_layer($node, $m, $file)
{
    $w = ws_canvas_split_number($node->getAttribute('width'));
    $h = ws_canvas_split_number($node->getAttribute('height'));
    $fit = trim($node->getAttribute('preserveAspectRatio'));

    if (($w <= 0) || ($h <= 0) || !ws_canvas_split_uniform($m) || ($node->getAttribute('class') !== '')) {
        return null;
    }

    if (!in_array($fit, array('', 'none', 'xMidYMid', 'xMidYMid meet'), true)) {
        return null;
    }

    $x = ws_canvas_split_number($node->getAttribute('x'));
    $y = ws_canvas_split_number($node->getAttribute('y'));
    $fw = (int) ($file['width'] ?? 0);
    $fh = (int) ($file['height'] ?? 0);

    if (($fit !== 'none') && ($fw > 0) && ($fh > 0)) {
        $k = min($w / $fw, $h / $fh);
        $x += ($w - $fw * $k) / 2;
        $y += ($h - $fh * $k) / 2;
        $w = $fw * $k;
        $h = $fh * $k;
    }

    $box = ws_canvas_split_place(array('x' => $x, 'y' => $y, 'w' => $w, 'h' => $h), $m);
    $opacity = ($node->getAttribute('opacity') === '') ? 1 : ws_canvas_split_number($node->getAttribute('opacity'));

    if (!$box || !empty($box['mirror'])) {
        return null;
    }

    return array(
        'type'    => 'image',
        'src'     => (string) $file['url'],
        'fileId'  => (int) $file['id'],
        'x'       => ws_canvas_split_round($box['x']),
        'y'       => ws_canvas_split_round($box['y']),
        'w'       => ws_canvas_split_round($box['w']),
        'h'       => ws_canvas_split_round($box['h']),
        'rot'     => ws_canvas_split_round($box['rot']),
        'opacity' => ws_canvas_split_round(max(0, min(1, (float) $opacity))),
    );
}

/**
 * Marks the top-level pictures of a raw SVG that name a file the agent may
 * use, before the sanitizer drops every address: each such href becomes a
 * reference #pgfile-<token>-<n>, which the sanitizer keeps and the split
 * turns into a picture layer. Other addresses are left for the sanitizer
 * to drop, as for any SVG.
 *
 * @param string   $svg     as the agent sent it
 * @param callable $resolve href => file (id, url, width, height) or null
 * @return array svg, files (n => file), token
 */
function ws_canvas_split_mark_pictures($svg, $resolve)
{
    $token = bin2hex(random_bytes(4));
    $none = array('svg' => $svg, 'files' => array(), 'token' => $token);

    if (!is_string($svg) || (stripos($svg, '<!DOCTYPE') !== false) || (stripos($svg, '<!ENTITY') !== false) || (stripos($svg, '<image') === false)) {
        return $none;
    }

    $doc = new DOMDocument();
    $errors = libxml_use_internal_errors(true);
    $loaded = $doc->loadXML($svg, LIBXML_NONET | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($errors);
    $root = $loaded ? $doc->documentElement : null;

    if (!$root || ($root->localName !== 'svg') || ($doc->doctype !== null)) {
        return $none;
    }

    $files = array();
    $images = array();

    foreach ($root->childNodes as $child) {
        if (!($child instanceof DOMElement)) {
            continue;
        }

        // A saved file's motion group holds its one element.
        $inner = ($child->localName === 'g') ? $child->firstChild : null;

        while ($inner && !($inner instanceof DOMElement)) {
            $inner = $inner->nextSibling;
        }

        if ($child->localName === 'image') {
            $images[] = $child;
        } elseif ($inner && ($inner->localName === 'image') && preg_match('/^\s*pgm-\d+\s*$/', $child->getAttribute('class'))) {
            $images[] = $inner;
        }
    }

    foreach ($images as $image) {
        $href = $image->hasAttribute('href') ? $image->getAttribute('href') : $image->getAttributeNS('http://www.w3.org/1999/xlink', 'href');
        $file = ($href !== '') ? call_user_func($resolve, $href) : null;

        if (!is_array($file)) {
            continue;
        }

        $n = count($files);
        $files[$n] = $file;
        $image->removeAttributeNS('http://www.w3.org/1999/xlink', 'href');
        $image->setAttribute('href', '#pgfile-' . $token . '-' . $n);
    }

    if (!$files) {
        return $none;
    }

    return array('svg' => (string) $doc->saveXML($doc->documentElement), 'files' => $files, 'token' => $token);
}

/**
 * The name of a site file a picture's address gives: this site's address,
 * absolute or from the site's path, its query and fragment left off; ''
 * for an address outside the site or one inside a folder of the disk.
 * Pure.
 *
 * @param string $href
 * @param string $host this site's host name
 * @param string $path the site's path (PATH)
 * @return string
 */
function ws_canvas_split_file_name($href, $host, $path)
{
    $href = trim((string) $href);

    if (preg_match('#^https?://([^/?\#]+)(/.*)?$#i', $href, $m)) {
        if (($host === '') || (strtolower($m[1]) !== strtolower($host))) {
            return '';
        }

        $href = isset($m[2]) ? $m[2] : '/';
    } elseif (!preg_match('#^/#', $href)) {
        return '';
    }

    $href = preg_replace('/[?#].*$/', '', $href);

    if (strpos($href, $path) !== 0) {
        return '';
    }

    $name = rawurldecode(substr($href, strlen($path)));

    return (($name === '') || (strpos($name, '/') !== false) || (strpos($name, '\\') !== false)) ? '' : $name;
}

/**
 * The file a picture's address names when the agent may use it: a file of
 * the site by its address (this site's, absolute or from the root, any
 * query left off), one a message of the channel carries, or with public
 * one any reader of the site may fetch (the files API's reach). Null for
 * an address outside the site or a file out of reach.
 *
 * @param string $href
 * @param int    $channel_id
 * @param bool   $public    the agent may read Files (files:read)
 * @return array|null id, url, width, height
 */
function ws_canvas_split_file($href, $channel_id, $public)
{
    $path = defined('PATH') ? PATH : '/';
    $name = ws_canvas_split_file_name($href, defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : '', $path);

    if ($name === '') {
        return null;
    }

    return ws_canvas_split_file_row("files.name = '" . escape($name) . "'", $channel_id, $public);
}

/**
 * A file the agent may use by its id, as ws_canvas_split_file() reaches
 * one by its address.
 *
 * @param int  $id
 * @param int  $channel_id
 * @param bool $public
 * @return array|null id, url, width, height
 */
function ws_canvas_split_file_by_id($id, $channel_id, $public)
{
    return ((int) $id > 0) ? ws_canvas_split_file_row("files.id = '" . (int) $id . "'", $channel_id, $public) : null;
}

/**
 * The picture file a condition picks, when a message of the channel
 * carries it or (public) the files API would give it to any visitor.
 *
 * @param string $where SQL condition on files, already escaped
 * @param int    $channel_id
 * @param bool   $public
 * @return array|null id, url, width, height
 */
function ws_canvas_split_file_row($where, $channel_id, $public)
{
    $path = defined('PATH') ? PATH : '/';
    $sizes = (function_exists('pg_schema_has') && pg_schema_has('files', 'image_width')) ? 'files.image_width, files.image_height' : 'NULL AS image_width, NULL AS image_height';
    $row = db_item("SELECT files.id, files.name, files.type, " . $sizes . " FROM files
        LEFT JOIN folder ON folder.folder_id = files.folder
        WHERE " . $where . " AND ("
        . "files.id IN (SELECT file_id FROM ws_messages WHERE channel_id = '" . (int) $channel_id . "' AND file_id > 0 AND deleted_at = 0)"
        . ($public ? " OR ((folder.folder_access_control_type = 'public') AND (files.attachment = '0'))" : '')
        . ") LIMIT 1");

    if (!is_array($row) || !in_array(strtolower((string) $row['type']), array('png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg'), true)) {
        return null;
    }

    return array(
        'id'     => (int) $row['id'],
        'url'    => $path . encode_url_path((string) $row['name']),
        'width'  => (int) $row['image_width'],
        'height' => (int) $row['image_height'],
    );
}

/**
 * The picture resolver of an agent drawing in a channel.
 *
 * @param int  $channel_id
 * @param bool $public the agent may read Files (files:read)
 * @return callable href => file or null
 */
function ws_canvas_split_resolver($channel_id, $public)
{
    return function ($href) use ($channel_id, $public) {
        return ws_canvas_split_file($href, (int) $channel_id, (bool) $public);
    };
}
