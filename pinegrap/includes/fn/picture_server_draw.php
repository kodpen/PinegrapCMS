<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A translated Design Canvas picture drawn by the server with nobody in the
 * panel. While the waiting mark is set (includes/fn/picture_translation.php)
 * the scheduled translation job draws the queue's rows of every folder
 * outside the Recycle Bin whose picture is a canvas this server draws whole
 * (includes/fn/image_draw.php): the language's words are put into the
 * layers and each changed text is fitted to its source's box as the Design
 * Canvas fits it (M.languageDoc(), M.languageFit() of
 * assets/js/pg_image_editor_model.js, twinned here on shared fixtures), with
 * the server's own letter widths. A row it cannot draw whole (a sticker, an
 * effect, a picture layer, a font it has no file of, a photo) is left as it
 * is for an open panel. A picture drawn here is kept as a browser's is, with
 * drawn_by 0.
 *
 * Loaded where it is used (translation_job.php), not by functions.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/image_draw.php');

/**
 * A number rounded as the editor's Math.round(n * k) / k rounds it (half
 * up, also below zero). Pure.
 *
 * @param float $value
 * @param int   $places
 * @return float
 */
function pg_picture_tr_js_round($value, $places)
{
    $k = pow(10, $places);

    return floor((float) $value * $k + 0.5) / $k;
}

/**
 * The smallest share of a source text's size its translation is drawn at:
 * 70% for a text up to 4% of the picture's shorter side, 50% from 8% of
 * it, on a straight line between; rounded to four places, 70% without a
 * size or a side. Twin of M.languageFloor(). Pure.
 *
 * @param float $size the source text's size, px
 * @param float $side the picture's shorter side, px
 * @return float
 */
function pg_picture_tr_language_floor($size, $side)
{
    // LANGUAGE_FLOOR, LANGUAGE_FLOOR_MIN, LANGUAGE_FLOOR_SMALL and
    // LANGUAGE_FLOOR_LARGE of the editor's model.
    $floor = 0.7;
    $min = 0.5;
    $small = 0.04;
    $large = 0.08;
    $size = is_numeric($size) ? (float) $size : 0.0;
    $side = is_numeric($side) ? (float) $side : 0.0;

    if (($size <= 0) || ($side <= 0)) {
        return $floor;
    }

    $t = max(0.0, min(1.0, ($size / $side - $small) / ($large - $small)));

    return pg_picture_tr_js_round($floor - ($floor - $min) * $t, 4);
}

/**
 * How much a translated text (w1 x h1 at the source's size) shrinks to fit
 * its source's box (w0 x h0), or its room when one is given: never grown, never under the floor of the
 * source's size on the picture's shorter side
 * (pg_picture_tr_language_floor()); marked when it still does not fit
 * there. Twin of M.languageFit(), run on the same fixtures
 * (tests/fixtures/picture_language/fit.json). Pure.
 *
 * @param float $w0
 * @param float $h0
 * @param float $w1
 * @param float $h1
 * @param float $size  the source text's size, px (0: the 70% floor)
 * @param float $side  the picture's shorter side, px (0: the 70% floor)
 * @param float $roomW the room's width (pg_picture_tr_language_room() less
 *                     what the layout and the box differ by), 0 for none:
 *                     the text grows into it and shrinks only past it
 * @param float $roomH the room's height, as $roomW
 * @return array scale, mark
 */
function pg_picture_tr_language_fit($w0, $h0, $w1, $h1, $size = 0.0, $side = 0.0, $roomW = 0.0, $roomH = 0.0)
{
    $floor = pg_picture_tr_language_floor($size, $side);
    $scale = 1;
    $w0 = max(is_numeric($w0) ? (float) $w0 : 0.0, is_numeric($roomW) ? (float) $roomW : 0.0);
    $h0 = max(is_numeric($h0) ? (float) $h0 : 0.0, is_numeric($roomH) ? (float) $roomH : 0.0);

    if (($w0 > 0) && ($w1 > $w0)) {
        $scale = min($scale, $w0 / $w1);
    }

    if (($h0 > 0) && ($h1 > $h0)) {
        $scale = min($scale, $h0 / $h1);
    }

    if ($scale < $floor - 1e-9) {
        return array('scale' => $floor, 'mark' => true);
    }

    return array('scale' => pg_picture_tr_js_round($scale, 4), 'mark' => false);
}

/**
 * The balanced breaks of a line's words: best[k][j] the narrowest widest
 * line of the first j words in k lines, from[k][j] where its last line
 * starts; of two breaks as narrow the later is kept, so the last line is
 * the shorter. Twin of the editor's wrapTable(). Pure but for $measure.
 *
 * @param array    $words
 * @param callable $measure a string => its width
 * @return array n, best, from, words
 */
function pg_picture_tr_wrap_table($words, $measure)
{
    $n = count($words);
    $seen = array();
    $best = array(1 => array());
    $from = array(1 => array());
    $seg = function ($i, $j) use (&$seen, $words, $measure) {
        $key = $i . ':' . $j;

        if (!isset($seen[$key])) {
            $w = call_user_func($measure, implode(' ', array_slice($words, $i, $j - $i)));
            $seen[$key] = is_numeric($w) ? (float) $w : 0.0;
        }

        return $seen[$key];
    };

    for ($j = 1; $j <= $n; $j++) {
        $best[1][$j] = $seg(0, $j);
        $from[1][$j] = 0;
    }

    for ($k = 2; $k <= $n; $k++) {
        $best[$k] = array();
        $from[$k] = array();

        for ($j = $k; $j <= $n; $j++) {
            for ($i = $k - 1; $i < $j; $i++) {
                $v = max($best[$k - 1][$i], $seg($i, $j));

                if (!isset($best[$k][$j]) || ($v <= $best[$k][$j])) {
                    $best[$k][$j] = $v;
                    $from[$k][$j] = $i;
                }
            }
        }
    }

    return array('n' => $n, 'best' => $best, 'from' => $from, 'words' => $words);
}

/**
 * The words of a line in $k lines, as the table broke them. Pure.
 *
 * @param array $t pg_picture_tr_wrap_table()
 * @param int   $k
 * @return string
 */
function pg_picture_tr_wrap_lines($t, $k)
{
    $out = array();
    $j = $t['n'];

    for (; $k >= 1; $k--) {
        $i = $t['from'][$k][$j];
        array_unshift($out, implode(' ', array_slice($t['words'], $i, $j - $i)));
        $j = $i;
    }

    return implode("\n", $out);
}

/**
 * A line's words, null when it is not broken: one word, or more than 40
 * (the balanced break measures every run of its words). Pure.
 *
 * @param string $line
 * @return array|null
 */
function pg_picture_tr_wrap_words($line)
{
    $words = array_values(array_filter(explode(' ', $line), function ($w) {
        return $w !== '';
    }));

    return ((count($words) < 2) || (count($words) > 40)) ? null : $words;
}

/**
 * A translated text wrapped to $max: each of its own lines wider than $max
 * is broken between words into the fewest lines that fit, balanced (the
 * narrowest widest line); a word is never broken, so a word wider than
 * $max sets the width instead. Twin of M.languageWrap(), run on the same
 * fixtures (tests/fixtures/picture_language/wrap.json). Pure but for
 * $measure.
 *
 * @param string   $text
 * @param float    $max
 * @param callable $measure a string => its width
 * @param array    $tables  a cache of pg_picture_tr_wrap_table() by line
 * @return string
 */
function pg_picture_tr_language_wrap($text, $max, $measure, &$tables = array())
{
    $out = array();

    foreach (explode("\n", (string) $text) as $line) {
        $words = pg_picture_tr_wrap_words($line);
        $w = call_user_func($measure, $line);

        if (!$words || ((is_numeric($w) ? (float) $w : 0.0) <= $max + 1e-9)) {
            $out[] = $line;

            continue;
        }

        if (!isset($tables['=' . $line])) {
            $tables['=' . $line] = pg_picture_tr_wrap_table($words, $measure);
        }

        $t = $tables['=' . $line];
        // Every word on a line of its own: the widest word.
        $limit = max($max, $t['best'][$t['n']][$t['n']]);
        $k = 1;

        while (($k < $t['n']) && ($t['best'][$k][$t['n']] > $limit + 1e-9)) {
            $k++;
        }

        $out[] = pg_picture_tr_wrap_lines($t, $k);
    }

    return implode("\n", $out);
}

/**
 * The wraps a translated text may be drawn in, the text as it is first:
 * its widest own line balanced in 2, 3 ... lines, each width the whole
 * text wrapped to by pg_picture_tr_language_wrap(); each wrap once. Twin of
 * M.languageWraps(). Pure but for $measure.
 *
 * @param string   $text
 * @param callable $measure a string => its width
 * @return array strings
 */
function pg_picture_tr_language_wraps($text, $measure)
{
    $text = (string) $text;
    $out = array($text);
    $tables = array();
    $widest = null;
    $width = -1.0;

    foreach (explode("\n", $text) as $line) {
        $w = call_user_func($measure, $line);
        $w = is_numeric($w) ? (float) $w : 0.0;

        if ($w > $width) {
            $width = $w;
            $widest = $line;
        }
    }

    $words = ($widest === null) ? null : pg_picture_tr_wrap_words($widest);

    if (!$words) {
        return $out;
    }

    $t = $tables['=' . $widest] = pg_picture_tr_wrap_table($words, $measure);

    for ($k = 2; $k <= $t['n']; $k++) {
        $next = pg_picture_tr_language_wrap($text, $t['best'][$k][$t['n']], $measure, $tables);

        if (!in_array($next, $out, true)) {
            $out[] = $next;
        }
    }

    return $out;
}

/**
 * A translated text layer (laid out as $wide at the source's size) wider
 * than its room ($w x $h): of its wraps (pg_picture_tr_language_wraps(),
 * measured by $layout) the one that needs the least shrink, the fewer
 * lines of two alike. Sets the layer's text and answers its layout. Twin
 * of the editor's languageWrapped(). Pure but for $layout.
 *
 * @param array    $layer
 * @param array    $wide   w, h
 * @param callable $layout a text layer => its laid out box (w, h)
 * @param float    $w
 * @param float    $h
 * @return array w, h
 */
function pg_picture_tr_language_wrapped(&$layer, $wide, $layout, $w, $h)
{
    $share = function ($b) use ($w, $h) {
        return min(1.0, $w / $b['w'], $h / $b['h']);
    };
    $text = (string) $layer['text'];
    $best = $share($wide);
    $measure = function ($s) use ($layer, $layout) {
        $layer['text'] = $s;
        $b = call_user_func($layout, $layer);

        return $b ? $b['w'] : INF;
    };

    foreach (array_slice(pg_picture_tr_language_wraps($text, $measure), 1) as $t) {
        $copy = $layer;
        $copy['text'] = $t;
        $b = call_user_func($layout, $copy);

        if ($b && ($share($b) > $best + 1e-9)) {
            $best = $share($b);
            $wide = $b;
            $text = $t;
        }
    }

    $layer['text'] = $text;

    return $wide;
}

/**
 * A layer's box as numbers. Pure.
 *
 * @param mixed $l
 * @return array|null x, y, w, h, rot; null without one
 */
function pg_picture_tr_room_box($l)
{
    if (!is_array($l)) {
        return null;
    }

    $b = array('rot' => (isset($l['rot']) && is_numeric($l['rot'])) ? (float) $l['rot'] : 0.0);

    foreach (array('x', 'y', 'w', 'h') as $key) {
        if (!isset($l[$key]) || !is_numeric($l[$key]) || is_bool($l[$key])) {
            return null;
        }

        $b[$key] = (float) $l[$key];
    }

    return (($b['w'] < 0) || ($b['h'] < 0)) ? null : $b;
}

/**
 * A box's corners turned by its angle around its middle, clockwise on the
 * screen from the top left. Pure.
 *
 * @param array $b pg_picture_tr_room_box()
 * @return array
 */
function pg_picture_tr_room_corners($b)
{
    $a = $b['rot'] * M_PI / 180;
    $cos = cos($a);
    $sin = sin($a);
    $cx = $b['x'] + $b['w'] / 2;
    $cy = $b['y'] + $b['h'] / 2;
    $out = array();

    foreach (array(array(-1, -1), array(1, -1), array(1, 1), array(-1, 1)) as $k) {
        $ox = $k[0] * $b['w'] / 2;
        $oy = $k[1] * $b['h'] / 2;
        $out[] = array($cx + $ox * $cos - $oy * $sin, $cy + $ox * $sin + $oy * $cos);
    }

    return $out;
}

/**
 * Whether point $p is inside the four corners $q (on an edge counts). Pure.
 *
 * @param array $q
 * @param array $p
 * @return bool
 */
function pg_picture_tr_room_inside($q, $p)
{
    for ($k = 0; $k < 4; $k++) {
        $a = $q[$k];
        $b = $q[($k + 1) % 4];
        $dx = $b[0] - $a[0];
        $dy = $b[1] - $a[1];
        $len = sqrt($dx * $dx + $dy * $dy);

        if (($len > 0) && (($dx * ($p[1] - $a[1]) - $dy * ($p[0] - $a[0])) / $len < -1e-6)) {
            return false;
        }
    }

    return true;
}

/**
 * The edges of the four corners $q as limits of the room in the text's
 * frame: each edge less a margin, the text's own distance to it when it
 * leans on it (nearer than a tenth of the shorter side), else 4% of that
 * side. Pure.
 *
 * @param array    $q
 * @param float    $shorter
 * @param array    $mine the text's corners
 * @param callable $turn a point to the text's frame
 * @param float    $cos
 * @param float    $sin
 * @param array    $out  the limits, added to
 * @return void
 */
function pg_picture_tr_room_planes($q, $shorter, $mine, $turn, $cos, $sin, &$out)
{
    for ($k = 0; $k < 4; $k++) {
        $a = $q[$k];
        $b = $q[($k + 1) % 4];
        $dx = $b[0] - $a[0];
        $dy = $b[1] - $a[1];
        $len = sqrt($dx * $dx + $dy * $dy);

        if ($len <= 0) {
            continue;
        }

        $nx = -$dy / $len;
        $ny = $dx / $len;
        $d = INF;
        $p = $turn($a);

        foreach ($mine as $m) {
            $d = min($d, ($m[0] - $a[0]) * $nx + ($m[1] - $a[1]) * $ny);
        }

        $out[] = array('px' => $p[0], 'py' => $p[1], 'nx' => $nx * $cos + $ny * $sin, 'ny' => -$nx * $sin + $ny * $cos, 'm' => ($d < $shorter / 10) ? $d : 0.04 * $shorter);
    }
}

/**
 * How far a side of the room may go ($axis 0 across, 1 upright; $up: the
 * right or the bottom side) inside the limits, its two corners at $ends on
 * the other axis. Pure.
 *
 * @param array $planes
 * @param int   $axis
 * @param bool  $up
 * @param array $ends
 * @return float
 */
function pg_picture_tr_room_limit($planes, $axis, $up, $ends)
{
    $v = $up ? INF : -INF;

    foreach ($planes as $p) {
        $k = $axis ? $p['ny'] : $p['nx'];

        if ($up ? ($k >= -1e-9) : ($k <= 1e-9)) {
            continue;
        }

        foreach ($ends as $o) {
            $lim = ($axis ? $p['py'] : $p['px']) + ($p['m'] - ($axis ? ($o - $p['px']) * $p['nx'] : ($o - $p['py']) * $p['ny'])) / $k;
            $v = $up ? min($v, $lim) : max($v, $lim);
        }
    }

    return $v;
}

/**
 * A side's limit from the layers beside the text on one axis: a layer in
 * the way less a gap, the smaller of the text's distance to it and half its
 * size; toward another text the middle between the two, less half that
 * gap. Pure.
 *
 * @param array $blocks the layers' boxes in the text's frame
 * @param int   $axis
 * @param float $lo     the room's start on the other axis
 * @param float $hi     its end
 * @param float $from   the text's start on this axis
 * @param float $to     its end
 * @param float $gap
 * @param array $room   the two limits, changed
 * @return void
 */
function pg_picture_tr_room_blocks($blocks, $axis, $lo, $hi, $from, $to, $gap, &$room)
{
    $a0 = $axis ? 'y0' : 'x0';
    $a1 = $axis ? 'y1' : 'x1';
    $o0 = $axis ? 'x0' : 'y0';
    $o1 = $axis ? 'x1' : 'y1';

    foreach ($blocks as $b) {
        if (($b[$o0] >= $hi - 1e-6) || ($b[$o1] <= $lo + 1e-6)) {
            continue;
        }

        if ($b[$a0] >= $to - 1e-6) {
            $g = max(0.0, min($b[$a0] - $to, $gap));
            $room[1] = min($room[1], $b['text'] ? ($to + $b[$a0]) / 2 - $g / 2 : $b[$a0] - $g);
        } elseif ($b[$a1] <= $from + 1e-6) {
            $g = max(0.0, min($from - $b[$a1], $gap));
            $room[0] = max($room[0], $b['text'] ? ($from + $b[$a1]) / 2 + $g / 2 : $b[$a1] + $g);
        }
    }
}

/**
 * A translated text's room: the rectangle it may use, in its own frame (x,
 * y, w, h as the layer's box before its angle). It starts as the text's
 * box and goes out, first across then upright, to the first thing in its
 * way: the picture's edge less a margin, a card under the text that holds
 * it (smaller than the picture) less the same margin, a layer beside it
 * less a gap, the middle toward another text. A hidden layer, one that
 * covers the whole picture under the text and one above that covers the
 * text are not read. Twin of M.languageRoom(), run on the same fixtures
 * (tests/fixtures/picture_language/room.json). Pure.
 *
 * @param array $doc   the document, arrays
 * @param string $id   the text layer's id
 * @param array $board the picture's w, h
 * @return array|null x, y, w, h; null where it cannot be read (no picture
 *                    size, no box): the old rule's box stays
 */
function pg_picture_tr_language_room($doc, $id, $board)
{
    $W = (isset($board['w']) && is_numeric($board['w'])) ? (float) $board['w'] : 0.0;
    $H = (isset($board['h']) && is_numeric($board['h'])) ? (float) $board['h'] : 0.0;
    $layers = (is_array($doc) && isset($doc['layers']) && is_array($doc['layers'])) ? array_values($doc['layers']) : array();
    $at = -1;

    foreach ($layers as $i => $l) {
        if (($at < 0) && is_array($l) && isset($l['type'], $l['id']) && ($l['type'] === 'text') && is_scalar($l['id']) && ((string) $l['id'] === (string) $id)) {
            $at = $i;
        }
    }

    $s = ($at >= 0) ? pg_picture_tr_room_box($layers[$at]) : null;

    if (($W <= 0) || ($H <= 0) || !$s || ($s['w'] <= 0) || ($s['h'] <= 0)) {
        return null;
    }

    $a = $s['rot'] * M_PI / 180;
    $cos = cos($a);
    $sin = sin($a);
    $cx = $s['x'] + $s['w'] / 2;
    $cy = $s['y'] + $s['h'] / 2;
    $size = (isset($layers[$at]['size']) && is_numeric($layers[$at]['size'])) ? (float) $layers[$at]['size'] : 0.0;
    $gap = max(0.0, $size) / 2;
    $mine = pg_picture_tr_room_corners($s);
    $picture = array(array(0.0, 0.0), array($W, 0.0), array($W, $H), array(0.0, $H));
    $planes = array();
    $blocks = array();
    $turn = function ($p) use ($cx, $cy, $cos, $sin) {
        $dx = $p[0] - $cx;
        $dy = $p[1] - $cy;

        return array($cx + $dx * $cos + $dy * $sin, $cy - $dx * $sin + $dy * $cos);
    };

    pg_picture_tr_room_planes($picture, min($W, $H), $mine, $turn, $cos, $sin, $planes);

    foreach ($layers as $i => $l) {
        $b = ($i === $at) ? null : pg_picture_tr_room_box($l);

        if (!$b || !empty($l['hidden']) || (isset($l['opacity']) && is_numeric($l['opacity']) && ((float) $l['opacity'] <= 0))) {
            continue;
        }

        $q = pg_picture_tr_room_corners($b);
        $holds = true;

        foreach ($mine as $p) {
            $holds = $holds && pg_picture_tr_room_inside($q, $p);
        }

        if ($holds) {
            $whole = true;

            foreach ($picture as $p) {
                $whole = $whole && pg_picture_tr_room_inside($q, $p);
            }

            if (($i < $at) && !$whole) {
                pg_picture_tr_room_planes($q, min($b['w'], $b['h']), $mine, $turn, $cos, $sin, $planes);
            }

            continue;
        }

        $xs = array();
        $ys = array();

        foreach ($q as $p) {
            $p = $turn($p);
            $xs[] = $p[0];
            $ys[] = $p[1];
        }

        $blocks[] = array('x0' => min($xs), 'x1' => max($xs), 'y0' => min($ys), 'y1' => max($ys), 'text' => isset($l['type']) && ($l['type'] === 'text'));
    }

    $x0 = $s['x'];
    $x1 = $s['x'] + $s['w'];
    $y0 = $s['y'];
    $y1 = $s['y'] + $s['h'];
    $across = array(pg_picture_tr_room_limit($planes, 0, false, array($y0, $y1)), pg_picture_tr_room_limit($planes, 0, true, array($y0, $y1)));

    pg_picture_tr_room_blocks($blocks, 0, $y0, $y1, $x0, $x1, $gap, $across);
    $across = array(min($across[0], $x0), max($across[1], $x1));

    $upright = array(pg_picture_tr_room_limit($planes, 1, false, $across), pg_picture_tr_room_limit($planes, 1, true, $across));

    pg_picture_tr_room_blocks($blocks, 1, $across[0], $across[1], $y0, $y1, $gap, $upright);
    $upright = array(min($upright[0], $y0), max($upright[1], $y1));

    return array(
        'x' => pg_picture_tr_js_round($across[0], 2),
        'y' => pg_picture_tr_js_round($upright[0], 2),
        'w' => pg_picture_tr_js_round($across[1] - $across[0], 2),
        'h' => pg_picture_tr_js_round($upright[1] - $upright[0], 2),
    );
}

/**
 * Where a fitted text (laid out as $after; its source laid out as $before)
 * is put: as the old rule put it while it stays inside its source's box
 * (the aligned side kept across, the middle upright); past it, the same
 * place slid inside its room toward the free side, the room's middle when
 * it is wider than the room, turned around the source's middle. Twin of
 * M.languagePlace(). Pure.
 *
 * @param array      $src    the source text layer
 * @param array|null $room   pg_picture_tr_language_room()
 * @param array      $before w, h
 * @param array      $after  w, h
 * @return array x, y
 */
function pg_picture_tr_language_place($src, $room, $before, $after)
{
    $num = function ($key) use ($src) {
        return (isset($src[$key]) && is_numeric($src[$key])) ? (float) $src[$key] : 0.0;
    };
    $w0 = $num('w');
    $h0 = $num('h');
    $w = (float) $after['w'];
    $h = (float) $after['h'];
    $align = isset($src['align']) ? $src['align'] : '';

    if (!$room || (($w <= $w0 + 1e-9) && ($h <= $before['h'] + 1e-9))) {
        $x = $num('x');

        if ($w0 && ($align === 'center')) {
            $x += ($w0 - $w) / 2;
        } elseif ($w0 && ($align === 'right')) {
            $x += $w0 - $w;
        }

        return array('x' => pg_picture_tr_js_round($x, 2), 'y' => pg_picture_tr_js_round($num('y') + ($before['h'] - $h) / 2, 2));
    }

    $x = $num('x');
    $y = $num('y') + ($h0 - $h) / 2;
    $cx = $num('x') + $w0 / 2;
    $cy = $num('y') + $h0 / 2;
    $a = $num('rot') * M_PI / 180;
    $cos = cos($a);
    $sin = sin($a);

    $x += ($align === 'center') ? ($w0 - $w) / 2 : (($align === 'right') ? $w0 - $w : 0);
    $x = ($w <= $room['w']) ? min(max($x, $room['x']), $room['x'] + $room['w'] - $w) : $room['x'] + ($room['w'] - $w) / 2;
    $y = ($h <= $room['h']) ? min(max($y, $room['y']), $room['y'] + $room['h'] - $h) : $room['y'] + ($room['h'] - $h) / 2;

    $px = $x + $w / 2 - $cx;
    $py = $y + $h / 2 - $cy;

    return array(
        'x' => pg_picture_tr_js_round($cx + $px * $cos - $py * $sin - $w / 2, 2),
        'y' => pg_picture_tr_js_round($cy + $px * $sin + $py * $cos - $h / 2, 2),
    );
}

/**
 * A canvas document with a language's words in its layers, by layer id and
 * field; a text with no translation keeps the source's words. Answers the
 * new document and, by layer id, each text layer whose words changed as it
 * was, to fit the new words to. Twin of M.languageDoc(), run on the same
 * fixtures (tests/fixtures/picture_language/doc.json). Pure.
 *
 * @param mixed $doc   the document, arrays or objects
 * @param array $texts pg_picture_tr_language_texts(): id, field, text (null
 *                     where none is translated)
 * @return array|null doc, source; null without a list of layers
 */
function pg_picture_tr_language_doc($doc, $texts)
{
    $doc = is_object($doc) ? json_decode((string) json_encode($doc), true) : $doc;
    $layers = (is_array($doc) && isset($doc['layers'])) ? $doc['layers'] : null;

    // Array.isArray(): a list, not a keyed object.
    if (!is_array($layers) || ($layers && (array_keys($layers) !== range(0, count($layers) - 1)))) {
        return null;
    }

    $by = array();
    $source = array();

    foreach ((array) $texts as $text) {
        if (is_array($text) && isset($text['text']) && is_string($text['text']) && ($text['text'] !== '')) {
            $by[(isset($text['id']) ? (string) $text['id'] : '') . "\n" . (isset($text['field']) ? (string) $text['field'] : '')] = $text['text'];
        }
    }

    foreach ($layers as $i => $layer) {
        $type = (is_array($layer) && isset($layer['type'])) ? $layer['type'] : '';
        $field = ($type === 'text') ? 'text' : (($type === 'sticker') ? 'label' : '');
        $id = (is_array($layer) && isset($layer['id']) && is_scalar($layer['id'])) ? (string) $layer['id'] : '';
        $key = $id . "\n" . $field;

        if (($field === '') || ($id === '') || !array_key_exists($key, $by) || ($by[$key] === (isset($layer[$field]) ? $layer[$field] : null))) {
            continue;
        }

        if ($field === 'text') {
            $source[$id] = $layer;
        }

        $doc['layers'][$i][$field] = $by[$key];
    }

    return array('doc' => $doc, 'source' => $source);
}

/**
 * A language's document with each changed text fitted as the Design
 * Canvas's drawLanguage() fits it (M.languageFitted()): grown into its room
 * (pg_picture_tr_language_room(), read before any text moves), wider than
 * it wrapped between words (pg_picture_tr_language_wrapped(), its lines
 * written into the layer's text, so the draw paints them), shrunk by
 * pg_picture_tr_language_fit() only past it, never under the floor of its
 * size on the artboard's shorter side, and put by
 * pg_picture_tr_language_place(). Pure but for $layout.
 *
 * @param array    $put    pg_picture_tr_language_doc()
 * @param callable $layout a text layer => its laid out box (w, h), null
 *                         when it cannot be laid out
 * @return array|null doc, fit (1 when a text did not fit at the smallest
 *                    size); null when a text cannot be laid out
 */
function pg_picture_tr_language_fitted($put, $layout)
{
    $doc = $put['doc'];
    $mark = false;
    $board = (isset($doc['artboard']) && is_array($doc['artboard'])) ? $doc['artboard'] : array();
    $size = array(
        'w' => (isset($board['width']) && is_numeric($board['width'])) ? (float) $board['width'] : 0.0,
        'h' => (isset($board['height']) && is_numeric($board['height'])) ? (float) $board['height'] : 0.0,
    );
    $side = min($size['w'], $size['h']);
    $rooms = array();

    foreach ($doc['layers'] as $layer) {
        $id = (is_array($layer) && isset($layer['id']) && is_scalar($layer['id'])) ? (string) $layer['id'] : '';

        if (($id !== '') && isset($put['source'][$id])) {
            $rooms[$id] = pg_picture_tr_language_room($put['doc'], $id, $size);
        }
    }

    foreach ($doc['layers'] as $i => $layer) {
        $id = (is_array($layer) && isset($layer['id']) && is_scalar($layer['id'])) ? (string) $layer['id'] : '';

        if (($id === '') || !isset($put['source'][$id])) {
            continue;
        }

        $src = $put['source'][$id];
        $room = $rooms[$id];
        $before = call_user_func($layout, $src);
        $wide = call_user_func($layout, $layer);

        if (!$before || !$wide) {
            return null;
        }

        $w0 = (isset($src['w']) && is_numeric($src['w'])) ? (float) $src['w'] : 0.0;
        $h0 = (isset($src['h']) && is_numeric($src['h'])) ? (float) $src['h'] : 0.0;
        $roomW = $room ? $before['w'] + $room['w'] - $w0 : 0.0;
        $roomH = $room ? $before['h'] + $room['h'] - $h0 : 0.0;

        if ($room && ($wide['w'] > $roomW + 1e-9)) {
            $wide = pg_picture_tr_language_wrapped($layer, $wide, $layout, $roomW, $roomH);
        }

        $fit = pg_picture_tr_language_fit($before['w'], $before['h'], $wide['w'], $wide['h'], isset($src['size']) ? $src['size'] : 0, $side, $roomW, $roomH);
        $scale = (isset($src['size']) && is_numeric($src['size']) && ((float) $src['size'] != 0)) ? (float) $src['size'] : 1.0;
        $layer['size'] = pg_picture_tr_js_round($scale * $fit['scale'], 2);
        $after = call_user_func($layout, $layer);

        if (!$after) {
            return null;
        }

        $at = pg_picture_tr_language_place($src, $room, $before, $after);
        $layer['x'] = $at['x'];
        $layer['y'] = $at['y'];
        $layer['w'] = $after['w'];
        $layer['h'] = $after['h'];
        $mark = $mark || $fit['mark'];
        $doc['layers'][$i] = $layer;
    }

    return array('doc' => $doc, 'fit' => $mark ? 1 : 0);
}

/**
 * The box a text layer is laid out in by this server: its font file's own
 * letter widths, as pg_image_draw_plan() lays it out for drawing (a hidden
 * or clear layer measured as shown). Null when the server would not draw
 * it (no file of its face, a letter the file lacks, a script or an effect
 * it does not draw).
 *
 * @param array $layer
 * @param array $can   pg_image_draw_can()
 * @return array|null w, h
 */
function pg_picture_tr_server_layout($layer, $can)
{
    unset($layer['hidden']);
    $layer['opacity'] = 1;

    $plan = pg_image_draw_plan(array('artboard' => array('width' => 1, 'height' => 1), 'layers' => array($layer)), $can);
    $item = (($plan['error'] === '') && (count($plan['items']) === 1)) ? $plan['items'][0] : null;

    return ($item && isset($item['layout'])) ? array('w' => (float) $item['layout']['w'], 'h' => (float) $item['layout']['h']) : null;
}

/**
 * A language's picture of a canvas as this server would draw it: the
 * language's words in the layers, each changed text fitted with the
 * server's letter widths, then pg_image_draw_plan()'s verdict on that
 * document. Draws nothing; reads font files.
 *
 * @param mixed $doc   the live canvas document, arrays or objects
 * @param array $texts pg_picture_tr_language_texts()
 * @param array $can   pg_image_draw_can()
 * @param float $until the time the budget ends, 0 for none
 * @return array error ('' | kind | untranslated | a plan's error), plan,
 *               fit (1 when a text did not fit at the smallest size)
 */
function pg_picture_tr_server_plan($doc, $texts, $can, $until = 0.0)
{
    $none = array('error' => 'kind', 'plan' => null, 'fit' => 0);

    // A photo is drawn on its base by the save's steps: a browser's work.
    if (pg_picture_tr_doc_kind($doc) !== 'canvas') {
        return $none;
    }

    if (!$texts || pg_picture_tr_untranslated($texts)) {
        return array('error' => 'untranslated') + $none;
    }

    $put = pg_picture_tr_language_doc($doc, $texts);

    if (!$put) {
        return $none;
    }

    $fitted = pg_picture_tr_language_fitted($put, function ($layer) use ($can) {
        return pg_picture_tr_server_layout($layer, $can);
    });

    if (!$fitted) {
        // The reason the whole document gives for the text it cannot lay out.
        $why = pg_image_draw_plan($put['doc'], $can)['error'];

        return array('error' => ($why !== '') ? $why : 'text') + $none;
    }

    $plan = pg_image_draw_plan($fitted['doc'], $can, null, $until);

    return array('error' => $plan['error'], 'plan' => $plan, 'fit' => $fitted['fit']);
}

/**
 * One queue row drawn by the server and kept, when its file is a canvas
 * outside the Recycle Bin and no ERP document, every text is translated and
 * the server draws the whole document. The words and the document are read
 * again before the picture is kept: a save or a translation meanwhile
 * leaves the row for the next run, as a browser's stale stamp does.
 *
 * @param array $row   the queue's row: file_id, language, name, folder
 * @param array $can   pg_image_draw_can()
 * @param float $until the time this drawing's budget ends
 * @return string '' when drawn, else why the row was left
 */
function pg_picture_tr_server_draw_row($row, $can, $until)
{
    $file_id = (int) $row['file_id'];
    $language = (string) $row['language'];

    if (pg_folder_binned((int) $row['folder']) || pg_files_include_erp_document(array($file_id))) {
        return 'folder';
    }

    $live = pg_picture_tr_live_doc($file_id, (string) $row['name']);

    if (!$live || ($live['kind'] !== 'canvas')) {
        return 'kind';
    }

    $texts = pg_picture_tr_language_texts($file_id, $language);
    $stamp = pg_picture_tr_stamp($file_id, $language, $live['hash'], $texts);
    $drawn = pg_picture_tr_server_plan($live['doc'], $texts, $can, $until);

    if ($drawn['error'] !== '') {
        return $drawn['error'];
    }

    $plan = $drawn['plan'];
    $png = pg_image_draw_guarded(function () use ($plan, $until) {
        return pg_image_draw_paint($plan, $until);
    });
    $size = is_string($png) ? @getimagesizefromstring($png) : false;

    if (!$size || ($size[0] !== $plan['width']) || ($size[1] !== $plan['height']) || ($size[2] !== IMAGETYPE_PNG)) {
        return 'paint';
    }

    $path = FILE_DIRECTORY_PATH . '/picture-language.' . (function_exists('getmypid') ? getmypid() : uniqid()) . '.' . mt_rand() . '.tmp';

    if (@file_put_contents($path, $png) !== strlen($png)) {
        @unlink($path);

        return 'write';
    }

    // Made smaller where that keeps every pixel, as a browser's picture is.
    pg_process_image_file($path);

    $now = pg_picture_tr_live_doc($file_id, (string) $row['name']);

    if (!$now || !hash_equals($stamp, pg_picture_tr_stamp($file_id, $language, $now['hash'], pg_picture_tr_language_texts($file_id, $language)))) {
        @unlink($path);

        return 'changed';
    }

    $kept = pg_picture_tr_keep(array('id' => $file_id, 'name' => (string) $row['name'], 'folder' => (int) $row['folder']), $language,
        array('path' => $path, 'extension' => 'png'), $drawn['fit'], (string) $live['hash'], 0);

    @unlink($path);

    return ($kept[0]['status'] === 'success') ? '' : 'keep';
}

/**
 * The queue's rows of every folder drawn one after another inside a time
 * budget, with what it needs handed in. A query that finds nothing clears
 * the mark (when it is still the one read before it); a row starts only
 * while one drawing's time (pg_image_draw_limits()) is left; once a row is
 * drawn the queue's query is asked again, which clears the mark when
 * nothing is left. A row left to a panel keeps the mark.
 *
 * @param array         $languages the codes a picture is drawn in
 * @param float         $budget    seconds
 * @param callable      $query     SQL => rows, false when it failed
 * @param callable      $draw      (row, until) => '' when drawn, else why
 *                                 it was left
 * @param string|null   $path      the mark's file
 * @param callable|null $clock     => the time now in seconds
 * @return array rows (found), drawn, left, stopped (the budget ended first)
 */
function pg_picture_tr_draw_due_with($languages, $budget, $query, $draw, $path = null, $clock = null)
{
    $clock = $clock ? $clock : function () {
        return microtime(true);
    };
    $summary = array('rows' => 0, 'drawn' => 0, 'left' => 0, 'stopped' => false);
    $seen = pg_picture_tr_mark_token($path);
    $sql = pg_picture_tr_queue_sql($languages, 200);
    $rows = ($sql !== '') ? call_user_func($query, $sql) : array();

    if (!is_array($rows)) {
        return $summary;
    }

    if (!$rows) {
        pg_picture_tr_mark_clear($seen, $path);

        return $summary;
    }

    $summary['rows'] = count($rows);
    $one = pg_image_draw_limits()['seconds'];
    $end = call_user_func($clock) + $budget;

    foreach ($rows as $row) {
        $now = call_user_func($clock);

        if ($now + $one > $end) {
            $summary['stopped'] = true;
            break;
        }

        if (call_user_func($draw, $row, $now + $one) === '') {
            $summary['drawn']++;
        } else {
            $summary['left']++;
        }
    }

    if ($summary['drawn'] > 0) {
        pg_picture_tr_mark_check_with($languages, $query, $path);
    }

    return $summary;
}

/**
 * The scheduled job's draw: the waiting pictures this server draws whole,
 * inside a time budget. Nothing is read while the mark is clear, and
 * nothing is drawn where the server draws no shapes or no text.
 *
 * @param float $budget seconds
 * @return array rows, drawn, left, stopped
 */
function pg_picture_tr_draw_due($budget = 20)
{
    $none = array('rows' => 0, 'drawn' => 0, 'left' => 0, 'stopped' => false);

    if (!pg_picture_tr_marked()) {
        return $none;
    }

    $can = pg_image_draw_can();

    if (empty($can['shapes']) || empty($can['text']) || !pg_picture_tr_ready() || !pg_picture_tr_store_ready()) {
        return $none;
    }

    pg_tr_load();

    return pg_picture_tr_draw_due_with(pg_picture_tr_languages(), $budget, 'db_items', function ($row, $until) use ($can) {
        return pg_picture_tr_server_draw_row($row, $can, $until);
    });
}
