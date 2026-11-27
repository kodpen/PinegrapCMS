<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A slide read by the design file reader (pg_pptx_design_read()) turned
 * into an Artifact Canvas document: the slide's size as the board, its
 * background as the board's colour or a picture layer, a text box as a text
 * layer, a picture as a picture layer, a line or a shape as a drawn layer.
 * Pure: nothing is read or written; a picture stays named by its part, which
 * the caller turns into a file of the site and points the layer at.
 *
 * Sizes follow a slide 1920 pixels wide at 9525 EMU a pixel: a text's size
 * in pixels is its size in points times 4/3, its line height its spacing in
 * points over its size, its letter spacing in em its spacing over its size.
 * A family is looked up in the font catalogue with its style word (Bold,
 * Italic, Light and the like) cut off into the layer's weight and italic; a
 * family the catalogue lacks is replaced and named in the report.
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
 * EMU in one canvas pixel (96 a inch).
 */
define('PG_PPTX_CANVAS_EMU', 9525);

/**
 * A length in EMU as canvas pixels, to two decimals.
 *
 * @param int|float $emu
 * @return float
 */
function pg_pptx_canvas_px($emu)
{
    return round($emu / PG_PPTX_CANVAS_EMU, 2) + 0.0;
}

/**
 * The family names of the Google Fonts catalogue the canvas offers, read
 * once from the catalogue the pickers ship (assets/js/pg_google_fonts.js).
 *
 * @return string[]
 */
function pg_pptx_canvas_catalogue()
{
    static $families = null;

    if ($families === null) {
        $source = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_google_fonts.js');
        $families = pg_pptx_canvas_catalogue_parse($source);
    }

    return $families;
}

/**
 * The family names written in the catalogue's CATEGORIES block, each once.
 * Pure.
 *
 * @param string $source the catalogue script
 * @return string[]
 */
function pg_pptx_canvas_catalogue_parse($source)
{
    $start = strpos($source, 'CATEGORIES');
    $end = ($start === false) ? false : strpos($source, '};', $start);

    if (($start === false) || ($end === false)) {
        return array();
    }

    preg_match_all("/'([^'\\\\]+)'/", substr($source, $start, $end - $start), $matches);

    return array_values(array_unique($matches[1]));
}

/**
 * The style words a family's name may end in, with the weight each gives
 * (0 for none) and whether it is italic.
 *
 * @return array lower-case word => [weight, italic]
 */
function pg_pptx_canvas_style_words()
{
    return array(
        'thin'       => array(100, false), 'hairline'   => array(100, false),
        'extralight' => array(200, false), 'ultralight' => array(200, false),
        'light'      => array(300, false),
        'regular'    => array(400, false), 'normal'     => array(400, false), 'book' => array(400, false), 'roman' => array(400, false),
        'medium'     => array(500, false),
        'semibold'   => array(600, false), 'demibold'   => array(600, false),
        'bold'       => array(700, false),
        'extrabold'  => array(800, false), 'ultrabold'  => array(800, false),
        'black'      => array(900, false), 'heavy'      => array(900, false),
        'italic'     => array(0, true), 'italics' => array(0, true), 'oblique' => array(0, true),
    );
}

/**
 * A family's name with its style words cut from the end: "Aileron Bold"
 * is Aileron at 700, "Cormorant Garamond Italics" Cormorant Garamond in
 * italic. "Extra Bold" and "Semi Bold" are read as one word. Pure.
 *
 * @param string $face
 * @return array family, weight (0 when none is named), italic
 */
function pg_pptx_canvas_font_name($face)
{
    $words = preg_split('/[\s_-]+/', trim((string) $face), -1, PREG_SPLIT_NO_EMPTY);
    $styles = pg_pptx_canvas_style_words();
    $weight = 0;
    $italic = false;

    while (count($words) > 1) {
        $last = strtolower($words[count($words) - 1]);
        $before = strtolower($words[count($words) - 2]);

        if (in_array($before, array('extra', 'ultra', 'semi', 'demi'), true) && isset($styles[$before . $last]) && (count($words) > 2)) {
            array_pop($words);
            $last = $before . $last;
        } elseif (!isset($styles[$last])) {
            break;
        }

        array_pop($words);
        $weight = $weight ?: $styles[$last][0];
        $italic = $italic || $styles[$last][1];
    }

    return array('family' => implode(' ', $words), 'weight' => $weight, 'italic' => $italic);
}

/**
 * The family a missing one is replaced by: a serif, a monospace or a
 * handwriting family when its name says so, else a plain sans. Each is one
 * of the catalogue's. Pure.
 *
 * @param string $family
 * @return string
 */
function pg_pptx_canvas_font_stand_in($family)
{
    $name = strtolower($family);

    if (preg_match('/\b(mono|code|courier|consol)/', $name)) {
        return 'Roboto Mono';
    }

    if (preg_match('/(script|hand|brush|marker|signature)/', $name)) {
        return 'Caveat';
    }

    if (preg_match('/(serif|garamond|times|georgia|baskerville|bodoni|didot|caslon|playfair|cormorant|libre|book|antiqua|minion|cambria)/', $name) && !preg_match('/sans/', $name)) {
        return 'Noto Serif';
    }

    return 'Roboto';
}

/**
 * A text's font as the canvas holds it: the catalogue's family as
 * 'g:<family>', its weight (300-900) and italic, and the family it
 * replaced when the catalogue lacks the file's. Pure.
 *
 * @param string|null $face    the run's typeface
 * @param bool        $bold    the run's bold
 * @param bool        $italic  the run's italic
 * @param string[]    $catalogue family names
 * @return array font, weight, italic, replaced ('' when the family was found)
 */
function pg_pptx_canvas_font($face, $bold, $italic, $catalogue)
{
    $name = pg_pptx_canvas_font_name((string) $face);
    $weight = $name['weight'] ?: 400;

    if ($bold && ($weight < 700)) {
        $weight = 700;
    }

    $weight = (int) min(900, max(300, $weight));
    $found = '';

    foreach ($catalogue as $family) {
        if (strcasecmp($family, $name['family']) === 0) {
            $found = $family;
            break;
        }
    }

    $replaced = '';

    if ($found === '') {
        $replaced = ($name['family'] !== '') ? $name['family'] : '';
        $found = pg_pptx_canvas_font_stand_in($name['family']);
    }

    return array('font' => 'g:' . $found, 'weight' => $weight, 'italic' => $italic || $name['italic'], 'replaced' => $replaced);
}

/**
 * A text's width in pixels estimated from its letters, for breaking a
 * paragraph where its box ends when no font metrics are at hand: narrow,
 * wide, capital and other letters by share of the size, plus the letter
 * spacing. Kept a little short, so a line that fits its box in the file is
 * not broken. Pure.
 *
 * @param string $text
 * @param array  $layer size, spacing (em), weight
 * @return float
 */
function pg_pptx_canvas_measure($text, $layer)
{
    $em = 0.0;
    $letters = preg_split('//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY);

    foreach ($letters as $letter) {
        if ($letter === ' ') {
            $em += 0.26;
        } elseif (preg_match('/[iljtfrI.,:;\'!|ı]/u', $letter)) {
            $em += 0.28;
        } elseif (preg_match('/[mwMW]/u', $letter)) {
            $em += 0.8;
        } elseif (preg_match('/\p{Lu}/u', $letter)) {
            $em += 0.62;
        } else {
            $em += 0.5;
        }

        $em += (float) ($layer['spacing'] ?? 0);
    }

    $bold = (($layer['weight'] ?? 400) >= 600) ? 1.05 : 1.0;

    return $em * (float) $layer['size'] * $bold;
}

/**
 * A paragraph broken into lines between words where it passes the width;
 * a word longer than the width keeps a line of its own. Pure.
 *
 * @param string   $text
 * @param float    $width   pixels
 * @param array    $layer   what the measure reads
 * @param callable $measure (string, array) => pixels
 * @return string[]
 */
function pg_pptx_canvas_wrap($text, $width, $layer, $measure)
{
    $words = explode(' ', (string) $text);
    $lines = array();
    $line = null;

    foreach ($words as $word) {
        $next = ($line === null) ? $word : $line . ' ' . $word;

        if (($line !== null) && ($line !== '') && (call_user_func($measure, $next, $layer) > $width)) {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $next;
        }
    }

    $lines[] = (string) $line;

    return $lines;
}

/**
 * A box placed inside an element's box, turned with it: the inner box's
 * offsets from the outer box's sides in its own frame, carried about the
 * outer box's middle by its turn. Pure.
 *
 * @param array $outer x, y, w, h, rot (pixels, degrees)
 * @param float $left  from the outer box's left side
 * @param float $top   from its top
 * @param float $w
 * @param float $h
 * @return array x, y, w, h
 */
function pg_pptx_canvas_inner($outer, $left, $top, $w, $h)
{
    $dx = $left + $w / 2 - $outer['w'] / 2;
    $dy = $top + $h / 2 - $outer['h'] / 2;
    $angle = deg2rad((float) $outer['rot']);
    $cx = $outer['x'] + $outer['w'] / 2 + $dx * cos($angle) - $dy * sin($angle);
    $cy = $outer['y'] + $outer['h'] / 2 + $dx * sin($angle) + $dy * cos($angle);

    return array('x' => round($cx - $w / 2, 2) + 0.0, 'y' => round($cy - $h / 2, 2) + 0.0, 'w' => round($w, 2) + 0.0, 'h' => round($h, 2) + 0.0);
}

/**
 * An element's box in pixels.
 *
 * @param array $element x, y, w, h (EMU), rot
 * @return array x, y, w, h, rot
 */
function pg_pptx_canvas_box($element)
{
    return array(
        'x'   => pg_pptx_canvas_px($element['x']),
        'y'   => pg_pptx_canvas_px($element['y']),
        'w'   => pg_pptx_canvas_px($element['w']),
        'h'   => pg_pptx_canvas_px($element['h']),
        'rot' => round((float) $element['rot'], 4) + 0.0,
    );
}

/**
 * An angle kept between 0 and 360. Pure.
 *
 * @param float $degrees
 * @return float
 */
function pg_pptx_canvas_angle($degrees)
{
    $degrees = fmod((float) $degrees, 360.0);

    return round(($degrees < 0) ? $degrees + 360 : $degrees, 4) + 0.0;
}

/**
 * A text element as a text layer: its paragraphs one under the other, a
 * paragraph broken into lines where its box ends (unless the box does not
 * wrap), the first run's style, the first paragraph's alignment, placed
 * inside the box's insets and by its anchor. A box flipped upside down is
 * turned half round, as its letters are drawn. Pure.
 *
 * @param array    $element   the reader's text element
 * @param string[] $catalogue family names
 * @param callable $measure   pg_pptx_canvas_measure() or the caller's
 * @return array layer, replaced (the family replaced or ''), simplified
 */
function pg_pptx_canvas_text($element, $catalogue, $measure)
{
    $style = $element['style'];
    $sz = max(1, (int) ($style['size'] ?? 1800));
    $size = round($sz / 100 * 4 / 3, 2) + 0.0;
    $font = pg_pptx_canvas_font($style['font'] ?? '', !empty($style['bold']), !empty($style['italic']), $catalogue);
    $first = $element['paragraphs'][0] ?? array('align' => 'l', 'line' => null);
    $line = $first['line'];
    $line_height = 1.2;

    if ($line && ($line['type'] === 'pts') && ($line['value'] > 0)) {
        $line_height = $line['value'] / $sz;
    } elseif ($line && ($line['type'] === 'pct') && ($line['value'] > 0)) {
        $line_height = $line['value'] * 1.2;
    }

    $aligns = array('ctr' => 'center', 'r' => 'right');
    $color = $style['color'] ?? null;
    $layer = array(
        'type'       => 'text',
        'text'       => '',
        'font'       => $font['font'],
        'size'       => $size,
        'weight'     => $font['weight'],
        'italic'     => $font['italic'],
        'align'      => $aligns[$first['align']] ?? 'left',
        'lineHeight' => round(min(3, max(0.5, $line_height)), 3) + 0.0,
        'spacing'    => round(((int) ($style['spacing'] ?? 0)) / $sz, 4) + 0.0,
        'color'      => $color ? $color['color'] : '#000000',
        'bg'         => '',
    );

    $box = pg_pptx_canvas_box($element);
    $inset = $element['inset'];
    $inner_w = max(1.0, $box['w'] - pg_pptx_canvas_px($inset['l']) - pg_pptx_canvas_px($inset['r']));
    $inner_h = max(0.0, $box['h'] - pg_pptx_canvas_px($inset['t']) - pg_pptx_canvas_px($inset['b']));
    $lines = array();
    $simplified = !empty($element['simplified']) || !empty($style['underline']);

    foreach ($element['paragraphs'] as $paragraph) {
        if (($paragraph['align'] ?? 'l') !== $first['align']) {
            $simplified = true;
        }

        $text = str_replace("\t", ' ', (string) $paragraph['text']);

        foreach (explode("\n", $text) as $part) {
            $lines = array_merge($lines, $element['wrap'] ? pg_pptx_canvas_wrap($part, $inner_w, $layer, $measure) : array($part));
        }
    }

    $layer['text'] = implode("\n", $lines);
    $text_h = round(count($lines) * $size * $layer['lineHeight'], 2);
    $top = pg_pptx_canvas_px($inset['t']);

    if ($element['anchor'] === 'ctr') {
        $top += ($inner_h - $text_h) / 2;
    } elseif ($element['anchor'] === 'b') {
        $top += $inner_h - $text_h;
    }

    $layer += pg_pptx_canvas_inner($box, pg_pptx_canvas_px($inset['l']), $top, $inner_w, $text_h);
    $layer['rot'] = pg_pptx_canvas_angle($box['rot'] + (!empty($element['flip_v']) ? 180 : 0));
    $layer['opacity'] = $color ? (float) $color['opacity'] : 1.0;

    return array('layer' => $layer, 'replaced' => $font['replaced'], 'simplified' => $simplified);
}

/**
 * A picture element (or a picture background) as a picture layer and what
 * the caller does with its part: the room a negative crop leaves around the
 * picture taken off the layer's box, what a positive crop cuts kept for the
 * caller to cut from the picture's pixels, with its flips, as a picture
 * layer has neither. Pure.
 *
 * @param array $element part, ext, crop (l, t, r, b), opacity, x, y, w, h, rot, flip_h, flip_v
 * @return array layer, picture (part, ext, crop, flip_h, flip_v)
 */
function pg_pptx_canvas_picture($element)
{
    $box = pg_pptx_canvas_box($element);
    $crop = $element['crop'];
    $cut = array();
    $room = array();

    foreach (array('l', 't', 'r', 'b') as $side) {
        $cut[$side] = max(0.0, (float) $crop[$side]);
        $room[$side] = max(0.0, -(float) $crop[$side]);
    }

    // The room is on the picture's sides; a flip shows it on the box's
    // other side.
    if (!empty($element['flip_h'])) {
        list($room['l'], $room['r']) = array($room['r'], $room['l']);
    }

    if (!empty($element['flip_v'])) {
        list($room['t'], $room['b']) = array($room['b'], $room['t']);
    }

    $span_x = max(0.0001, 1 - $crop['l'] - $crop['r']);
    $span_y = max(0.0001, 1 - $crop['t'] - $crop['b']);
    $left = $box['w'] * $room['l'] / $span_x;
    $top = $box['h'] * $room['t'] / $span_y;
    $w = max(1.0, $box['w'] * (1 - $cut['l'] - $cut['r']) / $span_x);
    $h = max(1.0, $box['h'] * (1 - $cut['t'] - $cut['b']) / $span_y);
    $part = (string) $element['part'];
    $layer = array('type' => 'image', 'src' => 'pptx:' . $part, 'fileName' => basename($part))
        + pg_pptx_canvas_inner($box, $left, $top, $w, $h)
        + array('rot' => pg_pptx_canvas_angle($box['rot']), 'opacity' => (float) ($element['opacity'] ?? 1.0));

    return array('layer' => $layer, 'picture' => array(
        'part'   => $part,
        'ext'    => (string) ($element['ext'] ?? ''),
        'crop'   => $cut,
        'flip_h' => !empty($element['flip_h']),
        'flip_v' => !empty($element['flip_v']),
    ));
}

/**
 * A stroke's dash as the canvas's line style. Pure.
 *
 * @param string $dash the file's preset dash ('' solid)
 * @return string|null null for solid
 */
function pg_pptx_canvas_dash($dash)
{
    if ($dash === '') {
        return null;
    }

    return in_array($dash, array('dot', 'sysDot'), true) ? 'dotted' : 'dashed';
}

/**
 * A line element as a drawn line or arrow: its box along the line from its
 * first end to its second, an arrow's head at the second end (its ends
 * swapped when only the first has one), its width, colour and dash. Pure.
 *
 * @param array $element x1, y1, x2, y2 (EMU), stroke
 * @return array the layer
 */
function pg_pptx_canvas_line($element)
{
    $stroke = $element['stroke'];
    $a = array(pg_pptx_canvas_px($element['x1']), pg_pptx_canvas_px($element['y1']));
    $b = array(pg_pptx_canvas_px($element['x2']), pg_pptx_canvas_px($element['y2']));
    $start = (string) $stroke['head_start'];
    $end = (string) $stroke['head_end'];

    if (($start !== '') && ($end === '')) {
        list($a, $b) = array($b, $a);
        list($start, $end) = array($end, $start);
    }

    $width = max(0.5, pg_pptx_canvas_px($stroke['width']));
    $arrow = ($end !== '');
    $head = ($end === 'oval') ? 'dot' : (in_array($end, array('arrow', 'stealth'), true) ? 'open' : 'fill');
    $dx = $b[0] - $a[0];
    $dy = $b[1] - $a[1];
    $length = hypot($dx, $dy);
    $angle = atan2($dy, $dx);
    $thickness = $width;

    // The arrow head's size as the editor's arrowHead() gives it.
    if ($arrow) {
        $head_w = ($head === 'dot') ? $width * 3.2 : min($length * 0.6, max($width * 3.4, 6)) * 0.62;
        $thickness = max($width, $head_w);
    }

    $cx = $a[0] + cos($angle) * $length / 2;
    $cy = $a[1] + sin($angle) * $length / 2;
    $layer = array(
        'type'    => $arrow ? 'arrow' : 'line',
        'x'       => round($cx - $length / 2, 2) + 0.0,
        'y'       => round($cy - $thickness / 2, 2) + 0.0,
        'w'       => round($length, 2) + 0.0,
        'h'       => round($thickness, 2) + 0.0,
        'rot'     => pg_pptx_canvas_angle(rad2deg($angle)),
        'opacity' => (float) $stroke['opacity'],
        'color'   => $stroke['color'],
        'stroke'  => $width,
    );

    if ($arrow) {
        $layer['heads'] = ($start !== '') ? 'both' : 'end';
        $layer['head'] = $head;
    }

    $dash = pg_pptx_canvas_dash((string) $stroke['dash']);

    if ($dash !== null) {
        $layer['dash'] = $dash;
    }

    return $layer;
}

/**
 * A preset shape as the canvas's drawn kind and its fields, and whether it
 * is drawn simplified (a right triangle as an even one). Pure.
 *
 * @param string $geometry the preset's name
 * @param array  $adjust   guide name => value
 * @param array  $box      w, h (pixels)
 * @return array kind, fields, simplified, quarter (turned a quarter round)
 */
function pg_pptx_canvas_kind($geometry, $adjust, $box)
{
    $short = min($box['w'], $box['h']);
    $polygons = array('diamond' => 4, 'pentagon' => 5, 'hexagon' => 6, 'heptagon' => 7, 'octagon' => 8, 'decagon' => 10, 'dodecagon' => 12);
    // Each star's inner radius as the file's default adjustment gives it.
    $stars = array('star4' => array(4, 12500), 'star5' => array(5, 19098), 'star6' => array(6, 28868), 'star7' => array(7, 34601),
        'star8' => array(8, 37500), 'star10' => array(10, 42533), 'star12' => array(12, 37500));

    if ($geometry === 'roundRect') {
        return array('rect', array('radius' => round(min(50000, $adjust['adj'] ?? 16667) / 100000 * $short, 2) + 0.0), false, false);
    }

    if ($geometry === 'ellipse') {
        return array('ellipse', array(), false, false);
    }

    if (($geometry === 'triangle') || ($geometry === 'rtTriangle')) {
        return array('triangle', array(), $geometry === 'rtTriangle', false);
    }

    if (isset($polygons[$geometry])) {
        // The file's hexagon lies on a flat side; the canvas's stands on a
        // corner, so it is turned a quarter round.
        return array('polygon', array('sides' => $polygons[$geometry]), false, $geometry === 'hexagon');
    }

    if (isset($stars[$geometry])) {
        list($points, $inner) = $stars[$geometry];
        $inner = (int) ($adjust['adj'] ?? $inner);

        return array('star', array('points' => $points, 'inner' => round(min(0.8, max(0.2, $inner / 50000)), 3) + 0.0), false, false);
    }

    if (in_array($geometry, array('wedgeRectCallout', 'wedgeRoundRectCallout', 'wedgeEllipseCallout'), true)) {
        return array('bubble', ($geometry === 'wedgeRectCallout') ? array('radius' => 0.0) : array(), false, false);
    }

    return array('rect', array('radius' => 0.0), false, false);
}

/**
 * A shape element as drawn layers: one filled in its fill's colour, and
 * one more for its outline when that has another colour; an outline alone
 * gives one unfilled layer. A flip mirrors the drawing (upside down: mirrored
 * and turned half round). Pure.
 *
 * @param array $element geometry, adjust, fill, stroke, x, y, w, h, rot, flip_h, flip_v
 * @return array layers, simplified
 */
function pg_pptx_canvas_shape($element)
{
    $box = pg_pptx_canvas_box($element);
    list($kind, $fields, $simplified, $quarter) = pg_pptx_canvas_kind($element['geometry'], $element['adjust'] ?? array(), $box);
    $place = $box;

    if ($quarter) {
        $place = array('x' => $box['x'] + ($box['w'] - $box['h']) / 2, 'y' => $box['y'] + ($box['h'] - $box['w']) / 2, 'w' => $box['h'], 'h' => $box['w'], 'rot' => $box['rot'] + 90);
    }

    $mirror = !empty($element['flip_h']) !== !empty($element['flip_v']);
    $rot = $place['rot'] + (!empty($element['flip_v']) ? 180 : 0);
    $base = array(
        'type' => $kind,
        'x'    => round($place['x'], 2) + 0.0,
        'y'    => round($place['y'], 2) + 0.0,
        'w'    => round($place['w'], 2) + 0.0,
        'h'    => round($place['h'], 2) + 0.0,
        'rot'  => pg_pptx_canvas_angle($rot),
    ) + $fields;

    if ($mirror) {
        $base['mirror'] = true;
    }

    $fill = $element['fill'];
    $stroke = $element['stroke'];
    $width = $stroke ? max(0.5, pg_pptx_canvas_px($stroke['width'])) : 0.0;
    $layers = array();

    if ($fill) {
        $same = $stroke && ($stroke['color'] === $fill['color']) && ((float) $stroke['opacity'] === (float) $fill['opacity']);
        $layers[] = $base + array('opacity' => (float) $fill['opacity'], 'color' => $fill['color'], 'fill' => 'solid', 'stroke' => $same ? $width : 0.0);

        if ($same) {
            $stroke = null;
        }
    }

    if ($stroke) {
        $layer = $base + array('opacity' => (float) $stroke['opacity'], 'color' => $stroke['color'], 'fill' => 'none', 'stroke' => $width);
        $dash = pg_pptx_canvas_dash((string) $stroke['dash']);

        if ($dash !== null) {
            $layer['dash'] = $dash;
        }

        $layers[] = $layer;
    }

    return array('layers' => $layers, 'simplified' => $simplified);
}

/**
 * A slide as a canvas document: the board of the slide's size and
 * background, its elements as layers in paint order (ids l0, l1 and on),
 * the pictures the caller turns into files, and the report's numbers.
 * Pure.
 *
 * @param array $design  pg_pptx_design_read()'s width and height (EMU)
 * @param array $slide   one of its slides
 * @param array $options fonts (family names, the catalogue by default),
 *                       measure (callable (string, layer) => pixels)
 * @return array doc, pictures (layer id => part, ext, crop, flip_h,
 *               flip_v), report (texts, pictures, shapes, fonts
 *               (family => stand-in), left (kind => count), simplified)
 */
function pg_pptx_canvas_slide($design, $slide, $options = array())
{
    $catalogue = isset($options['fonts']) ? $options['fonts'] : pg_pptx_canvas_catalogue();
    $measure = isset($options['measure']) ? $options['measure'] : 'pg_pptx_canvas_measure';
    $width = (int) min(8000, max(16, round($design['width'] / PG_PPTX_CANVAS_EMU)));
    $height = (int) min(8000, max(16, round($design['height'] / PG_PPTX_CANVAS_EMU)));
    $background = $slide['background'];
    $report = array('texts' => 0, 'pictures' => 0, 'shapes' => 0, 'fonts' => array(), 'left' => $slide['left'], 'simplified' => (int) $slide['simplified']);
    $layers = array();
    $pictures = array();
    $add = function ($layer) use (&$layers) {
        $id = 'l' . count($layers);
        $layers[] = array('id' => $id) + $layer;

        return $id;
    };

    if ($background && ($background['type'] === 'picture')) {
        $whole = array('x' => 0, 'y' => 0, 'w' => $design['width'], 'h' => $design['height'], 'rot' => 0, 'flip_h' => false, 'flip_v' => false);
        $mapped = pg_pptx_canvas_picture($whole + $background);
        $pictures[$add($mapped['layer'] + array('locked' => true))] = $mapped['picture'];
        $report['pictures']++;
    }

    foreach ($slide['elements'] as $element) {
        if ($element['type'] === 'text') {
            $mapped = pg_pptx_canvas_text($element, $catalogue, $measure);
            $add($mapped['layer']);
            $report['texts']++;

            if ($mapped['replaced'] !== '') {
                $report['fonts'][$mapped['replaced']] = substr($mapped['layer']['font'], 2);
            }

            // The reader counted a box whose runs differ already.
            if ($mapped['simplified'] && empty($element['simplified'])) {
                $report['simplified']++;
            }
        } elseif ($element['type'] === 'picture') {
            $mapped = pg_pptx_canvas_picture($element);
            $pictures[$add($mapped['layer'])] = $mapped['picture'];
            $report['pictures']++;

            if (($element['geometry'] ?? 'rect') !== 'rect') {
                $report['left']['mask'] = ($report['left']['mask'] ?? 0) + 1;
            }
        } elseif ($element['type'] === 'line') {
            $add(pg_pptx_canvas_line($element));
            $report['shapes']++;
        } elseif ($element['type'] === 'shape') {
            $mapped = pg_pptx_canvas_shape($element);

            foreach ($mapped['layers'] as $layer) {
                $add($layer);
            }

            $report['shapes']++;
            $report['simplified'] += $mapped['simplified'] ? 1 : 0;
        }
    }

    ksort($report['left']);
    ksort($report['fonts']);

    $doc = array(
        'v'        => 1,
        'source'   => null,
        'artboard' => array('width' => $width, 'height' => $height, 'background' => ($background && ($background['type'] === 'color')) ? $background['color'] : '#ffffff'),
        'geometry' => array('rot' => 0, 'flipX' => false, 'flipY' => false, 'straighten' => 0, 'crop' => null, 'cropShape' => 'rect', 'output' => null),
        'adjust'   => array(),
        'filter'   => 'none',
        'frame'    => null,
        'layers'   => $layers,
    );

    return array('doc' => $doc, 'pictures' => $pictures, 'report' => $report);
}

/**
 * The reports of several slides added up into one, for the line under the
 * top bar after many pages are opened. Pure.
 *
 * @param array[] $reports pg_pptx_canvas_slide()'s reports
 * @return array texts, pictures, shapes, fonts, left, simplified
 */
function pg_pptx_canvas_report($reports)
{
    $sum = array('texts' => 0, 'pictures' => 0, 'shapes' => 0, 'fonts' => array(), 'left' => array(), 'simplified' => 0);

    foreach ($reports as $report) {
        foreach (array('texts', 'pictures', 'shapes', 'simplified') as $key) {
            $sum[$key] += (int) $report[$key];
        }

        $sum['fonts'] += $report['fonts'];

        foreach ($report['left'] as $kind => $count) {
            $sum['left'][$kind] = ($sum['left'][$kind] ?? 0) + $count;
        }
    }

    ksort($sum['fonts']);
    ksort($sum['left']);

    return $sum;
}
