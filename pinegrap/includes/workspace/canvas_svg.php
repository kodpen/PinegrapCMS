<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a canvas made from SVG on the server, for an agent that draws
 * by writing SVG (api_canvas.php). The document is built the way the image
 * editor's Import builds its layer (svgImport() in pg_image_editor_svg.js,
 * with M.svgRoot(), M.svgIdMap(), M.svgRenameValue() and M.svgRenameCss() of
 * pg_image_editor_model.js ported here): an artboard from the root's width,
 * height and view box within the editor's sizes, one imported svg layer
 * covering it. The server never rasterises SVG: the canvas's file starts as
 * a blank picture of the artboard's fill, and the document is marked
 * picture: pending until a member's browser draws it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/svg_sanitize.php');

/**
 * The largest SVG an agent may send for a canvas, in bytes.
 *
 * @return int
 */
function ws_canvas_svg_max_bytes()
{
    return 1048576;
}

/**
 * The editor's artboard sides (SIZE_MIN, SIZE_MAX of the model).
 *
 * @return int[] min, max
 */
function ws_canvas_svg_sides()
{
    return array(16, 8000);
}

/**
 * The presentation attributes a root passes down to its children
 * (INHERITED of pg_image_editor_svg.js).
 *
 * @return string[]
 */
function ws_canvas_svg_inherited()
{
    return array('fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width', 'stroke-opacity', 'stroke-linecap', 'stroke-linejoin',
        'stroke-miterlimit', 'stroke-dasharray', 'font-family', 'font-size', 'font-weight', 'font-style', 'text-anchor', 'color');
}

/**
 * A length of an SVG root in pixels (px, unitless or pt/pc/in/cm/mm), 0 for
 * a percentage or nothing. Pure; M.svgLength().
 *
 * @param mixed $value
 * @return float
 */
function ws_canvas_svg_length($value)
{
    $units = array('px' => 1, 'pt' => 4 / 3, 'pc' => 16, 'in' => 96, 'cm' => 96 / 2.54, 'mm' => 96 / 25.4);

    if (!preg_match('/^\s*([0-9]*\.?[0-9]+(?:e[-+]?[0-9]+)?)\s*(px|pt|pc|in|cm|mm)?\s*$/i', (string) $value, $m)) {
        return 0.0;
    }

    return (float) $m[1] * (isset($m[2]) && ($m[2] !== '') ? $units[strtolower($m[2])] : 1);
}

/**
 * The numbers of a list attribute (viewBox). Pure; M.svgNumbers().
 *
 * @param mixed $value
 * @return float[]
 */
function ws_canvas_svg_numbers($value)
{
    preg_match_all('/[-+]?(?:[0-9]*\.[0-9]+|[0-9]+\.?)(?:e[-+]?[0-9]+)?/i', (string) $value, $m);

    return array_map('floatval', $m[0]);
}

/**
 * Two matrices multiplied, as M.mul() does ([a, b, c, d, e, f]). Pure.
 *
 * @param float[] $a
 * @param float[] $b
 * @return float[]
 */
function ws_canvas_svg_mul($a, $b)
{
    return array(
        $a[0] * $b[0] + $a[2] * $b[1], $a[1] * $b[0] + $a[3] * $b[1],
        $a[0] * $b[2] + $a[2] * $b[3], $a[1] * $b[2] + $a[3] * $b[3],
        $a[0] * $b[4] + $a[2] * $b[5] + $a[4], $a[1] * $b[4] + $a[3] * $b[5] + $a[5],
    );
}

/**
 * An SVG root's artboard (w, h: whole pixels within the editor's sizes) and
 * the matrix from its view box to the artboard, the view box fitted centred
 * keeping its ratio. Pure; M.svgRoot().
 *
 * @param mixed $width
 * @param mixed $height
 * @param mixed $view_box
 * @return array w, h, m
 */
function ws_canvas_svg_root($width, $height, $view_box)
{
    list($min, $max) = ws_canvas_svg_sides();
    $vb = ws_canvas_svg_numbers($view_box);
    $w = ws_canvas_svg_length($width);
    $h = ws_canvas_svg_length($height);
    $vb = ((count($vb) === 4) && ($vb[2] > 0) && ($vb[3] > 0)) ? $vb : null;

    if (!$w && !$h) {
        $w = $vb ? $vb[2] : 300;
        $h = $vb ? $vb[3] : 150;
    } elseif (!$w) {
        $w = $vb ? $h * $vb[2] / $vb[3] : $h;
    } elseif (!$h) {
        $h = $vb ? $w * $vb[3] / $vb[2] : $w;
    }

    // Math.round() rounds a half up.
    $aw = (int) floor(min(max($w, $min), $max) + 0.5);
    $ah = (int) floor(min(max($h, $min), $max) + 0.5);

    if (!$vb) {
        return array('w' => $aw, 'h' => $ah, 'm' => array($aw / $w, 0, 0, $ah / $h, 0, 0));
    }

    $k = min($aw / $vb[2], $ah / $vb[3]);
    $fit = ws_canvas_svg_mul(array($k, 0, 0, $k, 0, 0), array(1, 0, 0, 1, -$vb[0], -$vb[1]));

    return array('w' => $aw, 'h' => $ah, 'm' => ws_canvas_svg_mul(array(1, 0, 0, 1, ($aw - $vb[2] * $k) / 2, ($ah - $vb[3] * $k) / 2), $fit));
}

/**
 * A matrix as a transform attribute, each number to six places as the
 * editor writes it. Pure; M.svgMatrixText().
 *
 * @param float[] $m
 * @return string
 */
function ws_canvas_svg_matrix_text($m)
{
    $parts = array();

    foreach ($m as $n) {
        $n = floor($n * 1e6 + 0.5) / 1e6;
        $text = rtrim(rtrim(sprintf('%.6F', $n), '0'), '.');
        $parts[] = ($text === '-0') ? '0' : $text;
    }

    return 'matrix(' . implode(' ', $parts) . ')';
}

/**
 * New ids for an imported SVG's ids, none of them already taken: each gets
 * the prefix i<n>- with the smallest n that clashes with nothing. Pure;
 * M.svgIdMap().
 *
 * @param string[] $ids
 * @param string[] $taken
 * @return array id => new id
 */
function ws_canvas_svg_id_map($ids, $taken)
{
    $list = array();

    foreach ((array) $ids as $id) {
        $id = (string) $id;

        if (($id !== '') && !in_array($id, $list, true)) {
            $list[] = $id;
        }
    }

    $used = array_flip(array_map('strval', (array) $taken));
    $n = 1;

    while (true) {
        $clash = false;

        foreach ($list as $id) {
            if (isset($used['i' . $n . '-' . $id])) {
                $clash = true;
                break;
            }
        }

        if (!$clash) {
            break;
        }

        $n++;
    }

    $map = array();

    foreach ($list as $id) {
        $map[$id] = 'i' . $n . '-' . $id;
    }

    return $map;
}

/**
 * An attribute value with its references renamed: a whole "#id" (href) and
 * every url(#id). Pure; M.svgRenameValue().
 *
 * @param string $value
 * @param array  $map
 * @return string
 */
function ws_canvas_svg_rename_value($value, $map)
{
    $value = (string) $value;

    if (preg_match('/^\s*#(\S+)\s*$/', $value, $whole) && array_key_exists($whole[1], $map)) {
        return '#' . $map[$whole[1]];
    }

    return preg_replace_callback('/url\(\s*([\'"]?)#([^\'")\s]+)\1\s*\)/', function ($m) use ($map) {
        return array_key_exists($m[2], $map) ? 'url(' . $m[1] . '#' . $map[$m[2]] . $m[1] . ')' : $m[0];
    }, $value);
}

/**
 * A style sheet with its references renamed: #id in the selectors and
 * url(#id) in the declarations; a colour such as #fff inside a rule is left
 * alone. Pure; M.svgRenameCss().
 *
 * @param string $text
 * @param array  $map
 * @return string
 */
function ws_canvas_svg_rename_css($text, $map)
{
    $text = (string) $text;
    $out = '';
    $depth = 0;
    $from = 0;
    $length = strlen($text);

    for ($i = 0; $i <= $length; $i++) {
        $c = ($i < $length) ? $text[$i] : '';

        if (($i === $length) || ($c === '{') || ($c === '}')) {
            $part = (string) substr($text, $from, $i - $from);

            $out .= ($depth > 0) ? ws_canvas_svg_rename_value($part, $map) : preg_replace_callback('/#(-?[_a-zA-Z][-_a-zA-Z0-9]*)/', function ($m) use ($map) {
                return array_key_exists($m[1], $map) ? '#' . $map[$m[1]] : $m[0];
            }, $part);
            $out .= $c;
            $depth = max(0, $depth + (($c === '{') ? 1 : (($c === '}') ? -1 : 0)));
            $from = $i + 1;
        }
    }

    return $out;
}

/**
 * Renames ids and the references to them in an element and below.
 *
 * @param DOMElement $node
 * @param array      $map
 */
function ws_canvas_svg_rename_ids($node, $map)
{
    $nodes = array($node);

    foreach ($node->getElementsByTagName('*') as $child) {
        $nodes[] = $child;
    }

    foreach ($nodes as $n) {
        $attributes = array();

        foreach ($n->attributes as $attribute) {
            $attributes[] = $attribute;
        }

        foreach ($attributes as $a) {
            $value = $a->value;
            $next = ($a->localName === 'id') && ($a->namespaceURI === null)
                ? (array_key_exists($value, $map) ? $map[$value] : $value)
                : ws_canvas_svg_rename_value($value, $map);

            if ($next === $value) {
                continue;
            }

            if ($a->namespaceURI) {
                $n->setAttributeNS($a->namespaceURI, $a->nodeName, $next);
            } else {
                $n->setAttribute($a->nodeName, $next);
            }
        }

        if ($n->localName === 'style') {
            $n->textContent = ws_canvas_svg_rename_css($n->textContent, $map);
        }
    }
}

/**
 * Is there something to see among an SVG root's children: an element that
 * is no definition, style or description?
 *
 * @param DOMElement $root
 * @return bool
 */
function ws_canvas_svg_draws($root)
{
    $quiet = array('defs', 'style', 'title', 'desc', 'metadata', 'linearGradient', 'radialGradient', 'pattern', 'clipPath', 'mask', 'marker', 'filter', 'symbol');

    foreach ($root->childNodes as $child) {
        if (($child instanceof DOMElement) && !in_array($child->localName, $quiet, true)) {
            return true;
        }
    }

    return false;
}

/**
 * The layer an SVG makes, as the editor's Import makes it, sized to cover
 * the artboard: an outer group carrying the root's id (the scope of its
 * sanitized style rules), an inner group with the view box's matrix and the
 * root's inherited paint. Ids are renamed against the ids already taken.
 *
 * @param string   $svg   sanitized (pg_svg_sanitize())
 * @param string[] $taken ids the document already holds
 * @return array|null w, h (the artboard the SVG asks for), layer; null when
 *                    it does not parse or draws nothing
 */
function ws_canvas_svg_layer($svg, $taken = array())
{
    $source = new DOMDocument();
    $errors = libxml_use_internal_errors(true);
    $loaded = $source->loadXML((string) $svg, LIBXML_NONET | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($errors);
    $root = $loaded ? $source->documentElement : null;

    if (!$root || ($root->localName !== 'svg') || !ws_canvas_svg_draws($root)) {
        return null;
    }

    if ($root->getAttribute('id') === '') {
        $root->setAttribute('id', 'svg');
    }

    $ids = array($root->getAttribute('id'));

    foreach ($root->getElementsByTagName('*') as $node) {
        if ($node->hasAttribute('id')) {
            $ids[] = $node->getAttribute('id');
        }
    }

    ws_canvas_svg_rename_ids($root, ws_canvas_svg_id_map($ids, $taken));

    $size = ws_canvas_svg_root($root->getAttribute('width'), $root->getAttribute('height'), $root->getAttribute('viewBox'));
    $out = new DOMDocument('1.0', 'UTF-8');
    $top = $out->createElementNS('http://www.w3.org/2000/svg', 'g');
    $inner = $out->createElementNS('http://www.w3.org/2000/svg', 'g');

    $out->appendChild($top);
    $top->setAttribute('id', $root->getAttribute('id'));
    $inner->setAttribute('transform', ws_canvas_svg_matrix_text($size['m']));

    foreach (ws_canvas_svg_inherited() as $name) {
        if ($root->hasAttribute($name)) {
            $inner->setAttribute($name, $root->getAttribute($name));
        }
    }

    foreach ($root->childNodes as $child) {
        if (($child instanceof DOMElement) && !in_array($child->localName, array('title', 'desc', 'metadata'), true)) {
            $inner->appendChild($out->importNode($child, true));
        }
    }

    $top->appendChild($inner);
    $markup = $out->saveXML($top);

    if (!is_string($markup) || ($markup === '')) {
        return null;
    }

    return array(
        'w'     => $size['w'],
        'h'     => $size['h'],
        'layer' => array(
            'type'     => 'svg',
            'kind'     => 'g',
            'imported' => true,
            'markup'   => $markup,
            'vb'       => array('x' => 0, 'y' => 0, 'w' => $size['w'], 'h' => $size['h']),
            'x'        => 0,
            'y'        => 0,
            'w'        => $size['w'],
            'h'        => $size['h'],
            'rot'      => 0,
            'opacity'  => 1,
        ),
    );
}

/**
 * An artboard's fill as a canvas keeps it: #rrggbb (lower case) or
 * transparent; white when none was given; null when it is neither. Pure.
 *
 * @param mixed $value
 * @return string|null
 */
function ws_canvas_svg_background($value)
{
    if (($value === null) || ($value === '')) {
        return '#ffffff';
    }

    if (!is_string($value)) {
        return null;
    }

    $value = strtolower(trim($value));

    return (($value === 'transparent') || preg_match('/^#[0-9a-f]{6}$/', $value)) ? $value : null;
}

/**
 * A canvas document made from an agent's SVG, as the editor's Import would
 * make it on an artboard of the SVG's size: sanitized (pg_svg_sanitize()),
 * refused when nothing is left of it, when it is over 1 MB or when the
 * document could not be kept (a picture inside it as a data: URL). The
 * document is marked picture: pending: its PNG is drawn by a member's
 * browser. Pure but for the sanitizer.
 *
 * With split the SVG becomes a layer per top-level element, as Break
 * apart makes them (canvas_split.php).
 *
 * @param mixed $svg
 * @param mixed $background #rrggbb, transparent, or empty for white
 * @param bool  $split
 * @return array error ('' or too_large, empty, background, not_kept), doc (object or null)
 */
function ws_canvas_from_svg($svg, $background = '', $split = false)
{
    $fail = function ($error) {
        return array('error' => $error, 'doc' => null);
    };

    $fill = ws_canvas_svg_background($background);

    if ($fill === null) {
        return $fail('background');
    }

    if (!is_string($svg) || (trim($svg) === '')) {
        return $fail('empty');
    }

    if (strlen($svg) > ws_canvas_svg_max_bytes()) {
        return $fail('too_large');
    }

    $clean = pg_svg_sanitize($svg);

    if ($split) {
        require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_split.php');
        $parts = ($clean === '') ? null : ws_canvas_split_layers($clean);
        $made = $parts ? array('w' => $parts['w'], 'h' => $parts['h']) : null;
    } else {
        $made = ($clean === '') ? null : ws_canvas_svg_layer($clean);
    }

    if ($made === null) {
        return $fail('empty');
    }

    $doc = array(
        'v'        => 1,
        'source'   => null,
        'artboard' => array('width' => $made['w'], 'height' => $made['h'], 'background' => $fill),
        'geometry' => array('rot' => 0, 'flipX' => false, 'flipY' => false, 'straighten' => 0, 'crop' => null, 'cropShape' => 'rect', 'output' => null),
        'adjust'   => new stdClass(),
        'filter'   => 'none',
        'frame'    => null,
        'layers'   => array(),
        'picture'  => 'pending',
    );

    if ($split) {
        $doc = ws_canvas_split_into($doc, $parts, array('x' => 0, 'y' => 0, 'w' => $made['w'], 'h' => $made['h'], 'rot' => 0));
    } else {
        $doc['layers'][] = array('id' => 'l0') + $made['layer'];
    }

    $doc = json_decode(json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    if (!is_object($doc) || (ws_canvas_doc_json($doc) === '')) {
        return $fail('not_kept');
    }

    return array('error' => '', 'doc' => $doc);
}

/**
 * The words of a refusal of ws_canvas_from_svg().
 *
 * @param string $error
 * @return string
 */
function ws_canvas_from_svg_error_text($error)
{
    switch ($error) {
        case 'too_large':
            return lang('The SVG is larger than 1 MB.');
        case 'background':
            return lang('The background is a colour such as #ffffff, or transparent.');
        case 'not_kept':
            return lang('The canvas could not be kept: a picture inside the SVG has to be a file of the site, not data.');
        case 'layers':
            return lang('layers is split (a layer per element) or one.');
        default:
            return lang('Nothing in this SVG can be drawn.');
    }
}

/**
 * A blank PNG of a canvas's size in its fill: one colour of a palette, one
 * bit a pixel, so even the largest artboard stays small and needs no image
 * library. The canvas's file until a member's browser draws the picture.
 * Pure.
 *
 * @param int    $width
 * @param int    $height
 * @param string $fill #rrggbb or transparent
 * @return string the PNG's bytes
 */
function ws_canvas_blank_png($width, $height, $fill)
{
    list($min, $max) = ws_canvas_svg_sides();
    $width = min(max((int) $width, $min), $max);
    $height = min(max((int) $height, $min), $max);
    $transparent = ($fill === 'transparent');
    $rgb = $transparent ? "\xff\xff\xff" : (string) @hex2bin(substr((string) $fill, 1, 6));

    if (strlen($rgb) !== 3) {
        $rgb = "\xff\xff\xff";
    }

    $chunk = function ($type, $data) {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    };

    // Each row: filter 0, then the bits of palette index 0.
    $rows = str_repeat("\x00" . str_repeat("\x00", (int) ceil($width / 8)), $height);

    return "\x89PNG\r\n\x1a\n"
        . $chunk('IHDR', pack('NN', $width, $height) . "\x01\x03\x00\x00\x00")
        . $chunk('PLTE', $rgb)
        . ($transparent ? $chunk('tRNS', "\x00") : '')
        . $chunk('IDAT', gzcompress($rows, 9))
        . $chunk('IEND', '');
}
