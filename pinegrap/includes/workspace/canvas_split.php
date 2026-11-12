<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - an agent's SVG taken apart into a layer per top-level
 * element, as the image editor's Break apart does after an Import
 * (svgLayers() and svgBreakApart() in pg_image_editor_svg.js, with
 * M.svgTransform(), M.svgOwnBox(), M.svgPlace() and M.svgMatrix() of
 * pg_image_editor_model.js ported here). Each layer is placed by the root's
 * matrix times its own; its definitions and style rules go to the
 * document's svgDefs under the document's root id, as Break apart puts
 * them. The browser measures an element with getBBox(); here the box is
 * worked out from the element's geometry (paths exactly, text estimated
 * from its size and letters).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_edit.php');

/**
 * How an SVG becomes layers: split (a layer per top-level element) or one
 * (one imported layer, the default when nothing is given); null for a
 * word that is neither. Pure.
 *
 * @param mixed $value
 * @return string|null
 */
function ws_canvas_split_mode($value)
{
    if (($value === null) || ($value === '')) {
        return 'one';
    }

    return (is_string($value) && in_array($value, array('split', 'one'), true)) ? $value : null;
}

/**
 * Elements that become editable layers (EDITABLE of pg_image_editor_svg.js);
 * any other drawing element becomes a locked layer of kind kept.
 *
 * @return string[]
 */
function ws_canvas_split_editable()
{
    return array('path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'text', 'g', 'image');
}

/**
 * Top-level elements that are definitions (DEFS of pg_image_editor_svg.js).
 *
 * @return string[]
 */
function ws_canvas_split_defs()
{
    return array('defs', 'style', 'linearGradient', 'radialGradient', 'pattern', 'clipPath', 'mask', 'marker', 'filter', 'symbol');
}

/**
 * A point through a matrix. Pure; M.apply().
 *
 * @param float[] $m
 * @param float   $x
 * @param float   $y
 * @return float[]
 */
function ws_canvas_split_apply($m, $x, $y)
{
    return array($m[0] * $x + $m[2] * $y + $m[4], $m[1] * $x + $m[3] * $y + $m[5]);
}

/**
 * A rotation by degrees clockwise on screen. Pure; M.rotate().
 *
 * @param float $deg
 * @return float[]
 */
function ws_canvas_split_rotate($deg)
{
    $r = $deg * M_PI / 180;

    return array(cos($r), sin($r), -sin($r), cos($r), 0, 0);
}

/**
 * A transform attribute as a matrix. Pure; M.svgTransform().
 *
 * @param mixed $value
 * @return float[]
 */
function ws_canvas_split_transform($value)
{
    $m = array(1, 0, 0, 1, 0, 0);

    preg_match_all('/(matrix|translate|scale|rotate|skewX|skewY)\s*\(([^)]*)\)/', (string) $value, $found, PREG_SET_ORDER);

    foreach ($found as $f) {
        $n = ws_canvas_svg_numbers($f[2]);
        $step = null;

        if (($f[1] === 'matrix') && (count($n) === 6)) {
            $step = $n;
        } elseif ($f[1] === 'translate') {
            $step = array(1, 0, 0, 1, $n[0] ?? 0, $n[1] ?? 0);
        } elseif ($f[1] === 'scale') {
            $sx = $n[0] ?? 1;
            $step = array($sx, 0, 0, $n[1] ?? $sx, 0, 0);
        } elseif ($f[1] === 'rotate') {
            $step = (count($n) >= 3)
                ? ws_canvas_svg_mul(array(1, 0, 0, 1, $n[1], $n[2]), ws_canvas_svg_mul(ws_canvas_split_rotate($n[0]), array(1, 0, 0, 1, -$n[1], -$n[2])))
                : ws_canvas_split_rotate($n[0] ?? 0);
        } elseif ($f[1] === 'skewX') {
            $step = array(1, 0, tan(($n[0] ?? 0) * M_PI / 180), 1, 0, 0);
        } elseif ($f[1] === 'skewY') {
            $step = array(1, tan(($n[0] ?? 0) * M_PI / 180), 0, 1, 0, 0);
        }

        if ($step) {
            $m = ws_canvas_svg_mul($m, $step);
        }
    }

    return $m;
}

/**
 * A box of an element's own space at least a unit wide and high, so a
 * straight line still has a box to scale. Pure; M.svgOwnBox().
 *
 * @param array $b x, y, w, h
 * @return array
 */
function ws_canvas_split_own_box($b)
{
    $box = array('x' => $b['x'], 'y' => $b['y'], 'w' => $b['w'], 'h' => $b['h']);

    if (!($box['w'] >= 1)) {
        $box['x'] -= (1 - ($box['w'] ?: 0)) / 2;
        $box['w'] = 1;
    }

    if (!($box['h'] >= 1)) {
        $box['y'] -= (1 - ($box['h'] ?: 0)) / 2;
        $box['h'] = 1;
    }

    return $box;
}

/**
 * A layer's box (x, y, w, h, rot, mirror) for an element whose own box vb
 * is drawn through matrix m; null when m skews. Pure; M.svgPlace().
 *
 * @param array   $vb x, y, w, h
 * @param float[] $m
 * @return array|null
 */
function ws_canvas_split_place($vb, $m)
{
    list($a, $b, $c, $d) = $m;
    $sx = hypot($a, $b);
    $det = $a * $d - $b * $c;

    if (($sx < 1e-9) || (abs($det) < 1e-12) || (abs($a * $c + $b * $d) > 1e-6 * $sx * hypot($c, $d))) {
        return null;
    }

    $rot = atan2($b, $a) * 180 / M_PI;
    $mirror = $det < 0;
    $sy = abs($det) / $sx;

    if ($mirror) {
        $rot += 180;
    }

    // JavaScript's % keeps the sign of the dividend, as fmod() does.
    $rot = fmod($rot + 540, 360) - 180;
    $w = $vb['w'] * $sx;
    $h = $vb['h'] * $sy;
    $centre = ws_canvas_split_apply($m, $vb['x'] + $vb['w'] / 2, $vb['y'] + $vb['h'] / 2);
    $box = array('x' => $centre[0] - $w / 2, 'y' => $centre[1] - $h / 2, 'w' => $w, 'h' => $h, 'rot' => floor($rot * 1e6 + 0.5) / 1e6);

    if ($mirror) {
        $box['mirror'] = true;
    }

    return $box;
}

/**
 * The matrix that draws a layer's own box vb into its box: turned around
 * the box's centre, mirrored inside it. Pure; M.svgMatrix().
 *
 * @param array $l x, y, w, h, rot, mirror, vb
 * @return float[]
 */
function ws_canvas_split_layer_matrix($l)
{
    $vb = $l['vb'];
    $m = array(1, 0, 0, 1, $l['x'], $l['y']);

    if (!empty($l['rot'])) {
        $m = ws_canvas_svg_mul($m, ws_canvas_svg_mul(array(1, 0, 0, 1, $l['w'] / 2, $l['h'] / 2), ws_canvas_svg_mul(ws_canvas_split_rotate($l['rot']), array(1, 0, 0, 1, -$l['w'] / 2, -$l['h'] / 2))));
    }

    if (!empty($l['mirror'])) {
        $m = ws_canvas_svg_mul($m, array(-1, 0, 0, 1, $l['w'], 0));
    }

    return ws_canvas_svg_mul($m, ws_canvas_svg_mul(array($l['w'] / $vb['w'], 0, 0, $l['h'] / $vb['h'], 0, 0), array(1, 0, 0, 1, -$vb['x'], -$vb['y'])));
}

/**
 * Bounds (x0, y0, x1, y1) grown to hold a point; null bounds start them.
 *
 * @param array|null $b
 * @param float      $x
 * @param float      $y
 * @return array
 */
function ws_canvas_split_grow($b, $x, $y)
{
    return $b ? array(min($b[0], $x), min($b[1], $y), max($b[2], $x), max($b[3], $y)) : array($x, $y, $x, $y);
}

/**
 * Two bounds joined; either may be null. Pure.
 *
 * @param array|null $a
 * @param array|null $b
 * @return array|null
 */
function ws_canvas_split_union($a, $b)
{
    if (!$a || !$b) {
        return $a ?: $b;
    }

    return array(min($a[0], $b[0]), min($a[1], $b[1]), max($a[2], $b[2]), max($a[3], $b[3]));
}

/**
 * Bounds through a matrix: the bounds of their four corners moved. Pure.
 *
 * @param array|null $b
 * @param float[]    $m
 * @return array|null
 */
function ws_canvas_split_through($b, $m)
{
    if (!$b) {
        return null;
    }

    $out = null;

    foreach (array(array($b[0], $b[1]), array($b[2], $b[1]), array($b[0], $b[3]), array($b[2], $b[3])) as $p) {
        $q = ws_canvas_split_apply($m, $p[0], $p[1]);
        $out = ws_canvas_split_grow($out, $q[0], $q[1]);
    }

    return $out;
}

/**
 * A coordinate attribute's number (signed, px or unitless); 0 for a
 * percentage or nothing. Pure.
 *
 * @param mixed $value
 * @return float
 */
function ws_canvas_split_number($value)
{
    if (!preg_match('/^\s*([-+]?(?:[0-9]*\.[0-9]+|[0-9]+\.?)(?:e[-+]?[0-9]+)?)\s*(px)?\s*$/i', (string) $value, $m)) {
        return 0.0;
    }

    return (float) $m[1];
}

/**
 * The bounds of a path's outline (its fill, as getBBox() gives it): lines,
 * curves to their turning points and arcs to their extremes. Pure.
 *
 * @param mixed $d
 * @return array|null x0, y0, x1, y1; null for no drawing
 */
function ws_canvas_split_path_box($d)
{
    preg_match_all('/([MmLlHhVvCcSsQqTtAaZz])|([-+]?(?:[0-9]*\.[0-9]+|[0-9]+\.?)(?:e[-+]?[0-9]+)?)/', (string) $d, $tokens, PREG_SET_ORDER);

    $box = null;
    $x = 0.0;
    $y = 0.0;
    $sx = 0.0;
    $sy = 0.0;
    $cmd = '';
    $prev = '';
    $cx = 0.0;
    $cy = 0.0;
    $nums = array();
    $count = array('M' => 2, 'L' => 2, 'H' => 1, 'V' => 1, 'C' => 6, 'S' => 4, 'Q' => 4, 'T' => 2, 'A' => 7, 'Z' => 0);
    $i = 0;
    $n = count($tokens);

    // An arc's flags may stand together without spaces ("0110 10"): the
    // fourth and fifth numbers of an arc are each one digit.
    $take = function () use (&$tokens, &$i, &$n) {
        return ($i < $n) && ($tokens[$i][1] === '') ? (float) $tokens[$i++][2] : null;
    };

    $cubic = function ($p0, $p1, $p2, $p3) {
        $out = array(0.0, 1.0);
        $a = -$p0 + 3 * $p1 - 3 * $p2 + $p3;
        $b = 2 * ($p0 - 2 * $p1 + $p2);
        $c = $p1 - $p0;

        if (abs($a) < 1e-12) {
            if (abs($b) > 1e-12) {
                $out[] = -$c / $b;
            }
        } else {
            $disc = $b * $b - 4 * $a * $c;

            if ($disc >= 0) {
                $out[] = (-$b + sqrt($disc)) / (2 * $a);
                $out[] = (-$b - sqrt($disc)) / (2 * $a);
            }
        }

        $values = array();

        foreach ($out as $t) {
            if (($t >= 0) && ($t <= 1)) {
                $u = 1 - $t;
                $values[] = $u * $u * $u * $p0 + 3 * $u * $u * $t * $p1 + 3 * $u * $t * $t * $p2 + $t * $t * $t * $p3;
            }
        }

        return array(min($values), max($values));
    };

    $quad = function ($p0, $p1, $p2) {
        $values = array($p0, $p2);
        $den = $p0 - 2 * $p1 + $p2;

        if (abs($den) > 1e-12) {
            $t = ($p0 - $p1) / $den;

            if (($t > 0) && ($t < 1)) {
                $values[] = (1 - $t) * (1 - $t) * $p0 + 2 * (1 - $t) * $t * $p1 + $t * $t * $p2;
            }
        }

        return array(min($values), max($values));
    };

    while ($i < $n) {
        if ($tokens[$i][1] !== '') {
            $cmd = $tokens[$i++][1];

            if (strtoupper($cmd) === 'Z') {
                $x = $sx;
                $y = $sy;
                $prev = 'Z';
                continue;
            }
        } elseif ($cmd === '') {
            return $box;
        }

        $upper = strtoupper($cmd);
        $rel = ($cmd !== $upper);
        $nums = array();

        for ($k = 0; $k < $count[$upper]; $k++) {
            if (($upper === 'A') && (($k === 3) || ($k === 4)) && ($i < $n) && ($tokens[$i][1] === '') && preg_match('/^[01][0-9.]/', $tokens[$i][2])) {
                // A flag glued to what follows: split its first digit off.
                $nums[] = (float) $tokens[$i][2][0];
                $tokens[$i][2] = substr($tokens[$i][2], 1);
                continue;
            }

            $v = $take();

            if ($v === null) {
                return $box;
            }

            $nums[] = $v;
        }

        $ox = $rel ? $x : 0;
        $oy = $rel ? $y : 0;

        if ($upper === 'M') {
            $x = $ox + $nums[0];
            $y = $oy + $nums[1];
            $sx = $x;
            $sy = $y;
            $box = ws_canvas_split_grow($box, $x, $y);
            // Pairs after a move are lines.
            $cmd = $rel ? 'l' : 'L';
            $prev = 'M';
            continue;
        }

        if ($upper === 'L' || $upper === 'T') {
            $nx = $ox + $nums[0];
            $ny = $oy + $nums[1];

            if ($upper === 'T') {
                $qx = in_array($prev, array('Q', 'T'), true) ? 2 * $x - $cx : $x;
                $qy = in_array($prev, array('Q', 'T'), true) ? 2 * $y - $cy : $y;
                list($a0, $a1) = $quad($x, $qx, $nx);
                list($b0, $b1) = $quad($y, $qy, $ny);
                $box = ws_canvas_split_grow(ws_canvas_split_grow($box, $a0, $b0), $a1, $b1);
                $cx = $qx;
                $cy = $qy;
            }

            $box = ws_canvas_split_grow($box, $nx, $ny);
            $x = $nx;
            $y = $ny;
        } elseif ($upper === 'H') {
            $x = $ox + $nums[0];
            $box = ws_canvas_split_grow($box, $x, $y);
        } elseif ($upper === 'V') {
            $y = $oy + $nums[0];
            $box = ws_canvas_split_grow($box, $x, $y);
        } elseif ($upper === 'C' || $upper === 'S') {
            if ($upper === 'C') {
                $x1 = $ox + $nums[0];
                $y1 = $oy + $nums[1];
                $rest = array_slice($nums, 2);
            } else {
                $x1 = in_array($prev, array('C', 'S'), true) ? 2 * $x - $cx : $x;
                $y1 = in_array($prev, array('C', 'S'), true) ? 2 * $y - $cy : $y;
                $rest = $nums;
            }

            $x2 = $ox + $rest[0];
            $y2 = $oy + $rest[1];
            $nx = $ox + $rest[2];
            $ny = $oy + $rest[3];
            list($a0, $a1) = $cubic($x, $x1, $x2, $nx);
            list($b0, $b1) = $cubic($y, $y1, $y2, $ny);
            $box = ws_canvas_split_grow(ws_canvas_split_grow($box, $a0, $b0), $a1, $b1);
            $cx = $x2;
            $cy = $y2;
            $x = $nx;
            $y = $ny;
        } elseif ($upper === 'Q') {
            $qx = $ox + $nums[0];
            $qy = $oy + $nums[1];
            $nx = $ox + $nums[2];
            $ny = $oy + $nums[3];
            list($a0, $a1) = $quad($x, $qx, $nx);
            list($b0, $b1) = $quad($y, $qy, $ny);
            $box = ws_canvas_split_grow(ws_canvas_split_grow($box, $a0, $b0), $a1, $b1);
            $cx = $qx;
            $cy = $qy;
            $x = $nx;
            $y = $ny;
        } else {
            $nx = $ox + $nums[5];
            $ny = $oy + $nums[6];
            $box = ws_canvas_split_union($box, ws_canvas_split_arc_box($x, $y, $nums[0], $nums[1], $nums[2], $nums[3] != 0, $nums[4] != 0, $nx, $ny));
            $x = $nx;
            $y = $ny;
        }

        $prev = $upper;
    }

    return $box;
}

/**
 * The bounds of an elliptical arc from its end points (SVG's arc
 * parameters, the radii grown when too small). Pure.
 *
 * @return array x0, y0, x1, y1
 */
function ws_canvas_split_arc_box($x1, $y1, $rx, $ry, $angle, $large, $sweep, $x2, $y2)
{
    $box = ws_canvas_split_grow(ws_canvas_split_grow(null, $x1, $y1), $x2, $y2);
    $rx = abs($rx);
    $ry = abs($ry);

    if (($rx < 1e-12) || ($ry < 1e-12) || ((abs($x1 - $x2) < 1e-12) && (abs($y1 - $y2) < 1e-12))) {
        return $box;
    }

    $phi = $angle * M_PI / 180;
    $cos = cos($phi);
    $sin = sin($phi);
    $dx = ($x1 - $x2) / 2;
    $dy = ($y1 - $y2) / 2;
    $px = $cos * $dx + $sin * $dy;
    $py = -$sin * $dx + $cos * $dy;
    $grow = ($px * $px) / ($rx * $rx) + ($py * $py) / ($ry * $ry);

    if ($grow > 1) {
        $rx *= sqrt($grow);
        $ry *= sqrt($grow);
    }

    $num = $rx * $rx * $ry * $ry - $rx * $rx * $py * $py - $ry * $ry * $px * $px;
    $den = $rx * $rx * $py * $py + $ry * $ry * $px * $px;
    $k = ($den > 0) ? sqrt(max(0, $num / $den)) : 0;

    if ($large === $sweep) {
        $k = -$k;
    }

    $qx = $k * $rx * $py / $ry;
    $qy = -$k * $ry * $px / $rx;
    $ccx = $cos * $qx - $sin * $qy + ($x1 + $x2) / 2;
    $ccy = $sin * $qx + $cos * $qy + ($y1 + $y2) / 2;
    $start = atan2(($py - $qy) / $ry, ($px - $qx) / $rx);
    $end = atan2((-$py - $qy) / $ry, (-$px - $qx) / $rx);
    $span = $end - $start;

    if ($sweep && ($span < 0)) {
        $span += 2 * M_PI;
    } elseif (!$sweep && ($span > 0)) {
        $span -= 2 * M_PI;
    }

    // The angles where x and where y turn, each twice round the ellipse.
    $tx = atan2(-$ry * $sin, $rx * $cos);
    $ty = atan2($ry * $cos, $rx * $sin);

    foreach (array($tx, $tx + M_PI, $ty, $ty + M_PI) as $t) {
        $along = fmod($sweep ? $t - $start : $start - $t, 2 * M_PI);

        if ($along < 0) {
            $along += 2 * M_PI;
        }

        if ($along <= abs($span)) {
            $box = ws_canvas_split_grow($box, $ccx + $rx * cos($t) * $cos - $ry * sin($t) * $sin, $ccy + $rx * cos($t) * $sin + $ry * sin($t) * $cos);
        }
    }

    return $box;
}

/**
 * A property of an element, its own or the nearest ancestor's: an
 * attribute or a declaration of its style attribute.
 *
 * @param DOMElement $node
 * @param string     $name
 * @return string '' when nothing sets it
 */
function ws_canvas_split_inherited($node, $name)
{
    for ($n = $node; $n instanceof DOMElement; $n = $n->parentNode) {
        if (preg_match('/(?:^|;)\s*' . preg_quote($name, '/') . '\s*:\s*([^;]+)/i', $n->getAttribute('style'), $m)) {
            return trim($m[1]);
        }

        if ($n->hasAttribute($name)) {
            return trim($n->getAttribute($name));
        }
    }

    return '';
}

/**
 * The bounds of a text element: its runs of letters, each as wide as its
 * letters at an average width of their size, as high as a sans serif's
 * ascent and descent, moved by text-anchor. An estimate: the browser
 * measures the font itself.
 *
 * @param DOMElement $node
 * @return array|null
 */
function ws_canvas_split_text_box($node)
{
    $box = null;
    $x = ws_canvas_split_number(ws_canvas_svg_numbers($node->getAttribute('x'))[0] ?? 0);
    $y = ws_canvas_split_number(ws_canvas_svg_numbers($node->getAttribute('y'))[0] ?? 0);
    $runs = array();
    $walk = function ($n, $x, $y) use (&$walk, &$runs) {
        foreach ($n->childNodes as $child) {
            if (($child instanceof DOMText) && (trim($child->data) !== '')) {
                $runs[] = array('node' => $n, 'x' => $x, 'y' => $y, 'text' => preg_replace('/\s+/', ' ', trim($child->data)));
                $x = null;
                $y = null;
            } elseif (($child instanceof DOMElement) && ($child->localName === 'tspan')) {
                $tx = $child->hasAttribute('x') ? ws_canvas_split_number(ws_canvas_svg_numbers($child->getAttribute('x'))[0] ?? 0) : null;
                $ty = $child->hasAttribute('y') ? ws_canvas_split_number(ws_canvas_svg_numbers($child->getAttribute('y'))[0] ?? 0) : null;
                $walk($child, $tx, $ty);
            }
        }
    };

    $walk($node, $x, $y);

    $at = array($x, $y);

    foreach ($runs as $run) {
        $size = ws_canvas_split_number(ws_canvas_split_inherited($run['node'], 'font-size')) ?: 16;
        $anchor = ws_canvas_split_inherited($run['node'], 'text-anchor');
        $sx = ($run['x'] !== null) ? $run['x'] : $at[0];
        $sy = ($run['y'] !== null) ? $run['y'] : $at[1];
        $w = mb_strlen($run['text']) * 0.5 * $size;
        $left = ($anchor === 'middle') ? $sx - $w / 2 : (($anchor === 'end') ? $sx - $w : $sx);

        $box = ws_canvas_split_union($box, array($left, $sy - 0.905 * $size, $left + $w, $sy + 0.212 * $size));
        $at = array($left + $w, $sy);
    }

    return $box;
}

/**
 * The bounds of an element in its own space (its transform not applied),
 * as getBBox() gives them: the geometry's fill, no stroke. A group joins
 * its children through their transforms; a use is the element it names.
 *
 * @param DOMElement   $node
 * @param DOMElement[] $ids  elements by id, for use
 * @param int          $depth
 * @return array|null x0, y0, x1, y1; null when it draws nothing
 */
function ws_canvas_split_bbox($node, $ids, $depth = 0)
{
    if (($depth > 32) || (strtolower(ws_canvas_split_inherited($node, 'display')) === 'none')) {
        return null;
    }

    $num = function ($name) use ($node) {
        return ws_canvas_split_number($node->getAttribute($name));
    };

    switch ($node->localName) {
        case 'rect':
        case 'image':
            $w = $num('width');
            $h = $num('height');

            return (($w < 0) || ($h < 0)) ? null : array($num('x'), $num('y'), $num('x') + $w, $num('y') + $h);
        case 'circle':
            $r = max(0, $num('r'));

            return array($num('cx') - $r, $num('cy') - $r, $num('cx') + $r, $num('cy') + $r);
        case 'ellipse':
            $rx = $node->hasAttribute('rx') ? $num('rx') : $num('ry');
            $ry = $node->hasAttribute('ry') ? $num('ry') : $rx;

            return array($num('cx') - $rx, $num('cy') - $ry, $num('cx') + $rx, $num('cy') + $ry);
        case 'line':
            return array(min($num('x1'), $num('x2')), min($num('y1'), $num('y2')), max($num('x1'), $num('x2')), max($num('y1'), $num('y2')));
        case 'polyline':
        case 'polygon':
            $p = ws_canvas_svg_numbers($node->getAttribute('points'));
            $box = null;

            for ($k = 0; $k + 1 < count($p); $k += 2) {
                $box = ws_canvas_split_grow($box, $p[$k], $p[$k + 1]);
            }

            return $box;
        case 'path':
            return ws_canvas_split_path_box($node->getAttribute('d'));
        case 'text':
            return ws_canvas_split_text_box($node);
        case 'use':
            $href = $node->getAttribute('href') . $node->getAttributeNS('http://www.w3.org/1999/xlink', 'href');
            $target = ((substr($href, 0, 1) === '#') && isset($ids[substr($href, 1)])) ? $ids[substr($href, 1)] : null;

            if (!$target) {
                return null;
            }

            $inner = ($target->localName === 'symbol') ? ws_canvas_split_children_box($target, $ids, $depth + 1)
                : ws_canvas_split_through(ws_canvas_split_bbox($target, $ids, $depth + 1), ws_canvas_split_transform($target->getAttribute('transform')));

            return ws_canvas_split_through($inner, array(1, 0, 0, 1, $num('x'), $num('y')));
        default:
            return in_array($node->localName, ws_canvas_split_defs(), true) ? null : ws_canvas_split_children_box($node, $ids, $depth + 1);
    }
}

/**
 * A container's children joined through their transforms.
 *
 * @param DOMElement   $node
 * @param DOMElement[] $ids
 * @param int          $depth
 * @return array|null
 */
function ws_canvas_split_children_box($node, $ids, $depth)
{
    $box = null;

    foreach ($node->childNodes as $child) {
        if ($child instanceof DOMElement) {
            $box = ws_canvas_split_union($box, ws_canvas_split_through(ws_canvas_split_bbox($child, $ids, $depth), ws_canvas_split_transform($child->getAttribute('transform'))));
        }
    }

    return $box;
}

/**
 * A number of a layer as the document keeps it, to six places.
 *
 * @param float $n
 * @return float|int
 */
function ws_canvas_split_round($n)
{
    $n = floor($n * 1e6 + 0.5) / 1e6;

    return ($n == floor($n)) ? (int) $n : $n;
}

/**
 * The layers of a sanitized SVG, a layer per top-level element as the
 * editor's svgLayers() makes them, placed on an artboard of the SVG's own
 * size: ids renamed against the ids already taken (M.svgIdMap()), the
 * root's inherited paint on each element, each placed by the root's matrix
 * times its own (or kept turned inside a group where that would skew), an
 * element that is no drawing locked as kept. A layer whose element had an
 * id is named by it.
 *
 * @param string   $svg   sanitized (pg_svg_sanitize())
 * @param string[] $taken ids the document already holds
 * @return array|null w, h, id (the root's renamed id), defs (one <defs> or
 *                    ''), layers (without ids); null when it does not parse
 *                    or draws nothing
 */
function ws_canvas_split_layers($svg, $taken = array())
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

    $by_id = array();

    foreach ($root->getElementsByTagName('*') as $node) {
        if ($node->hasAttribute('id')) {
            $by_id[$node->getAttribute('id')] = $node;
        }
    }

    $size = ws_canvas_svg_root($root->getAttribute('width'), $root->getAttribute('height'), $root->getAttribute('viewBox'));
    $out = new DOMDocument('1.0', 'UTF-8');
    $defs = array();
    $layers = array();

    foreach ($root->childNodes as $child) {
        if (!($child instanceof DOMElement) || in_array($child->localName, array('title', 'desc', 'metadata'), true)) {
            continue;
        }

        $name = $child->localName;

        if (in_array($name, ws_canvas_split_defs(), true)) {
            if ($name === 'defs') {
                foreach ($child->childNodes as $def) {
                    if ($def instanceof DOMElement) {
                        $defs[] = $def;
                    }
                }
            } else {
                $defs[] = $child;
            }

            continue;
        }

        // A saved file's motion group gives back its one element, the
        // group's transform before the element's own (M.svgMotionGroup()).
        $elements = array();

        foreach ($child->childNodes as $c) {
            if ($c instanceof DOMElement) {
                $elements[] = $c;
            }
        }

        $attributes = array();

        foreach ($child->attributes as $a) {
            $attributes[] = $a->nodeName;
        }

        if (($name === 'g') && preg_match('/^\s*pgm-\d+\s*$/', $child->getAttribute('class')) && (count($elements) === 1) && !array_diff($attributes, array('class', 'transform'))) {
            $inner = $elements[0];
            $moved = trim($child->getAttribute('transform') . ' ' . $inner->getAttribute('transform'));

            if ($moved !== '') {
                $inner->setAttribute('transform', $moved);
            }

            $child = $inner;
            $name = $child->localName;

            if (in_array($name, array_merge(array('title', 'desc', 'metadata'), ws_canvas_split_defs()), true)) {
                continue;
            }
        }

        // A layer is named by its element's id as written: without the
        // prefix the import gives it, nor the one the sanitizer gives a file
        // with style rules.
        $label = preg_replace('/^i[0-9]+-(?:pgsvg-[0-9a-f]{10}-)?/', '', $child->getAttribute('id'));
        $own = ws_canvas_split_transform($child->getAttribute('transform'));
        $b = ws_canvas_split_bbox($child, $by_id);
        $node = $out->importNode($child, true);

        foreach (ws_canvas_svg_inherited() as $attribute) {
            if ($root->hasAttribute($attribute) && !$node->hasAttribute($attribute)) {
                $node->setAttribute($attribute, $root->getAttribute($attribute));
            }
        }

        $node->removeAttribute('transform');
        $vb = $b ? ws_canvas_split_own_box(array('x' => $b[0], 'y' => $b[1], 'w' => $b[2] - $b[0], 'h' => $b[3] - $b[1])) : null;
        $box = $vb ? ws_canvas_split_place($vb, ws_canvas_svg_mul($size['m'], $own)) : null;

        // A skewed element keeps its own transform inside a group, which the
        // box then moves.
        if ($vb && !$box) {
            $wrap = $out->createElementNS('http://www.w3.org/2000/svg', 'g');
            $node->setAttribute('transform', ws_canvas_svg_matrix_text($own));
            $wrap->appendChild($node);
            $node = $wrap;
            $g = ws_canvas_split_through($b, $own);
            $vb = ws_canvas_split_own_box(array('x' => $g[0], 'y' => $g[1], 'w' => $g[2] - $g[0], 'h' => $g[3] - $g[1]));
            $box = ws_canvas_split_place($vb, $size['m']);
        }

        if (!$box) {
            $vb = array('x' => 0, 'y' => 0, 'w' => 1, 'h' => 1);
            $box = array('x' => 0, 'y' => 0, 'w' => 1, 'h' => 1, 'rot' => 0);
        }

        $editable = in_array($name, ws_canvas_split_editable(), true);
        $layer = array(
            'type'    => 'svg',
            'kind'    => $editable ? $name : 'kept',
            'markup'  => (string) $out->saveXML($node),
            'vb'      => array_map('ws_canvas_split_round', $vb),
            'opacity' => 1,
        );

        foreach ($box as $key => $value) {
            $layer[$key] = is_bool($value) ? $value : ws_canvas_split_round($value);
        }

        if (!$editable) {
            $layer['locked'] = true;
        }

        if ($label !== '') {
            $layer['name'] = $label;
        }

        $layers[] = $layer;
    }

    if (!$layers) {
        return null;
    }

    $text = '';

    foreach ($defs as $def) {
        $text .= (string) $out->saveXML($out->importNode($def, true));
    }

    return array(
        'w'      => $size['w'],
        'h'      => $size['h'],
        'id'     => $root->getAttribute('id'),
        'defs'   => ($text !== '') ? '<defs>' . $text . '</defs>' : '',
        'layers' => $layers,
    );
}

/**
 * A document with an SVG's split layers put on top, placed as Break apart
 * places them: through the matrix of the frame the import would have had
 * (its box on the artboard), each layer's own matrix after it; one that
 * would skew keeps its place inside a group as large as the frame. The
 * definitions join the document's svgDefs, their scope renamed to the
 * document's root id, or the SVG's scope becomes it. Pure but for parsing.
 *
 * @param array $doc   decoded as arrays
 * @param array $split ws_canvas_split_layers()
 * @param array $frame x, y, w, h, rot (the SVG's artboard on the canvas)
 * @return array the document
 */
function ws_canvas_split_into($doc, $split, $frame)
{
    $frame['vb'] = array('x' => 0, 'y' => 0, 'w' => $split['w'], 'h' => $split['h']);
    $place = ws_canvas_split_layer_matrix($frame);

    foreach ($split['layers'] as $s) {
        $own = ws_canvas_split_layer_matrix($s);
        $box = ws_canvas_split_place($s['vb'], ws_canvas_svg_mul($place, $own));
        $layer = array('id' => ws_canvas_new_layer_id($doc)) + $s;

        unset($layer['mirror']);

        if (!$box) {
            $layer['kind'] = ($s['kind'] === 'kept') ? 'kept' : 'g';
            $layer['markup'] = '<g transform="' . ws_canvas_svg_matrix_text($own) . '">' . $s['markup'] . '</g>';
            $layer['vb'] = $frame['vb'];
            $box = array('x' => $frame['x'], 'y' => $frame['y'], 'w' => $frame['w'], 'h' => $frame['h'], 'rot' => $frame['rot'] ?? 0);
        }

        foreach ($box as $key => $value) {
            $layer[$key] = is_bool($value) ? $value : ws_canvas_split_round($value);
        }

        $doc['layers'][] = $layer;
    }

    $scope = (string) $split['id'];
    $root_id = (isset($doc['svgRootId']) && is_string($doc['svgRootId'])) ? $doc['svgRootId'] : '';

    if ($split['defs'] !== '') {
        $defs = $split['defs'];

        if (($root_id !== '') && ($scope !== '') && ($root_id !== $scope)) {
            $defs = ws_canvas_split_rename_scope($defs, $scope, $root_id);
        }

        $body = preg_replace('/^<defs[^>]*>|<\/defs>$/', '', $defs);
        $now = (isset($doc['svgDefs']) && is_string($doc['svgDefs'])) ? $doc['svgDefs'] : '';
        $doc['svgDefs'] = ($now !== '') ? preg_replace('/<\/defs>\s*$/', $body . '</defs>', $now) : '<defs>' . $body . '</defs>';
    }

    if (($root_id === '') && ($scope !== '')) {
        $doc['svgRootId'] = $scope;
    }

    return $doc;
}

/**
 * Definitions with one id renamed: the scope of their style rules.
 *
 * @param string $defs one <defs> element
 * @param string $from
 * @param string $to
 * @return string
 */
function ws_canvas_split_rename_scope($defs, $from, $to)
{
    $doc = new DOMDocument();
    $errors = libxml_use_internal_errors(true);
    $loaded = $doc->loadXML('<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">' . $defs . '</svg>', LIBXML_NONET | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($errors);
    $node = $loaded ? $doc->documentElement->firstChild : null;

    if (!($node instanceof DOMElement)) {
        return $defs;
    }

    ws_canvas_svg_rename_ids($node, array($from => $to));

    return (string) $doc->saveXML($node);
}
