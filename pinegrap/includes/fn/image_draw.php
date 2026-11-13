<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A canvas document of the image editor drawn by the server, for a canvas no
 * browser has drawn yet (an agent's: includes/workspace/canvas_draw.php).
 * The editor's own renderer runs in a browser; this one draws only what it
 * draws the same way and refuses the rest, which then waits for a browser:
 *
 *   - svg layers through ImageMagick's in-process librsvg reader (the RSVG
 *     format). Never through the SVG format, which asks a delegate program
 *     through a shell first, nor through MSVG, ImageMagick's own partial
 *     renderer. Only markup pg_svg_sanitize() passes is read, and of it only
 *     shapes, gradients, clip paths, markers, use and plain style rules: no
 *     text, no picture, no filter, no mask;
 *   - drawn rectangles, ellipses and lines;
 *   - text in a font the server holds the file of (data/fonts, the bundled
 *     Roboto), in letters that need no shaping and the file has;
 *   - Bootstrap icons;
 *   - pictures from the files the caller answers for, never from an address.
 *
 * pg_image_draw_can() says what this host's Imagick draws, from a fixture
 * set drawn once per ImageMagick build. A drawing runs under Imagick's
 * resource limits and a time budget; one that never comes back (a crash,
 * a PHP time limit) pauses the server's drawing for a day.
 *
 * Loaded where it is used (the Workspace's canvases, System Status), not by
 * functions.php.
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
 * The limits of a drawing. Pure.
 *
 * @return array pixels (artboard area), layers, seconds (the budget, looked
 *               at between layers), svg (bytes of one layer's document),
 *               elements (of one), file and picture (a picture's bytes and
 *               pixels), memory and disk (Imagick's, bytes), pause and stale
 *               (seconds: how long a lost drawing switches the drawing off,
 *               and after how long a drawing counts as lost), letters (of
 *               all its texts)
 */
function pg_image_draw_limits()
{
    return array(
        'pixels'   => 4200000,
        'layers'   => 150,
        'seconds'  => 8,
        'svg'      => 512 * 1024,
        'elements' => 4000,
        'letters'  => 6000,
        'file'     => 12 * 1024 * 1024,
        'picture'  => 16000000,
        'memory'   => 256 * 1024 * 1024,
        'disk'     => 1024 * 1024 * 1024,
        'pause'    => 86400,
        'stale'    => 180,
    );
}

/**
 * What the probe is kept under: the PHP, extension and ImageMagick builds
 * and whether the RSVG reader is there, so a changed server is probed
 * again. '' without Imagick.
 *
 * @return string
 */
function pg_image_draw_signature()
{
    if (!extension_loaded('imagick') || !class_exists('Imagick')) {
        return '';
    }

    try {
        $version = Imagick::getVersion();

        return '1|' . PHP_VERSION . '|' . phpversion('imagick') . '|' . $version['versionString'] . '|' . implode(',', Imagick::queryFormats('RSVG'));
    } catch (Throwable $error) {
        return '';
    }
}

/**
 * The kept state (data/temp/image_draw.json): signature, can, paused (a
 * time). With $write it is written first.
 *
 * @param array|null $write
 * @return array
 */
function pg_image_draw_state($write = null)
{
    $path = PG_FUNCTIONS_DIR . '/data/temp/image_draw.json';

    if (is_array($write)) {
        if (!is_dir(dirname($path))) {
            @mkdir(dirname($path), 0755, true);
        }

        pg_image_editor_put($path, (string) json_encode($write));

        return $write;
    }

    $state = json_decode((string) @file_get_contents($path), true);

    return is_array($state) ? $state : array();
}

/**
 * What this server draws of a canvas. The fixture set is drawn once per
 * build (pg_image_draw_signature()) and kept; a drawing that never came back
 * switches everything off until its pause is over.
 *
 * @param bool $recheck draw the fixtures again
 * @return array shapes, text, icons, svg (bool), pictures (the formats read:
 *               png, jpeg, webp), paused (the time the pause ends, 0 when
 *               none), engine (the ImageMagick build, '' without Imagick)
 */
function pg_image_draw_can($recheck = false)
{
    static $can = null;

    if (($can !== null) && !$recheck) {
        return $can;
    }

    $none = array('shapes' => false, 'text' => false, 'icons' => false, 'svg' => false, 'pictures' => array(), 'paused' => 0, 'engine' => '');
    $signature = pg_image_draw_signature();

    if ($signature === '') {
        return $can = $none;
    }

    $limits = pg_image_draw_limits();
    $state = pg_image_draw_state();
    $paused = (int) ($state['paused'] ?? 0);

    // A marker older than any drawing lasts is one whose drawing died.
    foreach ((array) glob(PG_FUNCTIONS_DIR . '/data/temp/image_draw_*.run') as $marker) {
        if ((int) @filemtime($marker) < time() - $limits['stale']) {
            @unlink($marker);
            $paused = time() + $limits['pause'];
            $state = pg_image_draw_state(array('paused' => $paused) + $state);
        }
    }

    if ($paused > time()) {
        return $can = array('paused' => $paused) + $none;
    }

    if ($recheck || (($state['signature'] ?? '') !== $signature) || !is_array($state['can'] ?? null)) {
        $probed = pg_image_draw_guarded('pg_image_draw_probe');
        $state = pg_image_draw_state(array('signature' => $signature, 'can' => is_array($probed) ? $probed : array(), 'paused' => 0));
    }

    return $can = array('paused' => 0) + array_intersect_key($state['can'], $none) + $none;
}

/**
 * Runs a drawing under the guards and answers what it returned, null when
 * it threw. Imagick's limits belong to the process, so they are lowered for
 * the call only; no time limit is set there, as ImageMagick ends the whole
 * process when it is passed. The marker file outlives the call only when
 * the call never returned (pg_image_draw_can() then pauses the drawing).
 *
 * @param callable $work
 * @return mixed
 */
function pg_image_draw_guarded($work)
{
    $limits = pg_image_draw_limits();
    $wanted = array('RESOURCETYPE_MEMORY' => $limits['memory'], 'RESOURCETYPE_MAP' => $limits['memory'], 'RESOURCETYPE_DISK' => $limits['disk'], 'RESOURCETYPE_THREAD' => 1);
    $before = array();
    $marker = PG_FUNCTIONS_DIR . '/data/temp/image_draw_' . bin2hex(random_bytes(6)) . '.run';
    $result = null;

    if (!is_dir(dirname($marker))) {
        @mkdir(dirname($marker), 0755, true);
    }

    @file_put_contents($marker, (string) time());

    try {
        foreach ($wanted as $name => $limit) {
            if (defined('Imagick::' . $name)) {
                $type = constant('Imagick::' . $name);
                $now = Imagick::getResourceLimit($type);
                // An unlimited resource is told as a number past PHP's integers.
                $now = (is_float($now) && ($now >= PHP_INT_MAX)) ? PHP_INT_MAX : (int) $now;
                $before[$type] = $now;
                Imagick::setResourceLimit($type, ($now > 0) ? min($now, $limit) : $limit);
            }
        }

        $result = call_user_func($work);
    } catch (Throwable $error) {
        $result = null;
    }

    foreach ($before as $type => $limit) {
        try {
            Imagick::setResourceLimit($type, $limit);
        } catch (Throwable $error) {
            // The lowered limit stays for the rest of the request.
        }
    }

    @unlink($marker);

    return $result;
}

/**
 * A colour of a document: #rgb or #rrggbb. Pure.
 *
 * @param mixed $value
 * @return int[]|null red, green, blue
 */
function pg_image_draw_color($value)
{
    if (!is_string($value) || !preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value, $match)) {
        return null;
    }

    $hex = (strlen($match[1]) === 3) ? $match[1][0] . $match[1][0] . $match[1][1] . $match[1][1] . $match[1][2] . $match[1][2] : $match[1];

    return array(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
}

/**
 * A number of a document within limits, the default when the field is
 * missing; null when it is no number or outside them. Pure.
 *
 * @return float|null
 */
function pg_image_draw_number($layer, $key, $min, $max, $default = null)
{
    if (!isset($layer[$key])) {
        return ($default === null) ? null : (float) $default;
    }

    $value = $layer[$key];

    return ((is_int($value) || is_float($value)) && is_finite($value) && ($value >= $min) && ($value <= $max)) ? (float) $value : null;
}

/**
 * The matrix of a layer's frame (0, 0 its box's corner) on the artboard:
 * turned around the box's centre, mirrored inside it; with vb, of the
 * layer's own box drawn into it (M.svgMatrix() of the editor). Pure.
 *
 * @param array      $l  x, y, w, h, rot, mirror
 * @param array|null $vb x, y, w, h
 * @return float[] a, b, c, d, e, f
 */
function pg_image_draw_matrix($l, $vb = null)
{
    $angle = deg2rad($l['rot']);
    $cos = cos($angle);
    $sin = sin($angle);
    $m = array($cos, $sin, -$sin, $cos,
        $l['x'] + $l['w'] / 2 - ($cos * $l['w'] / 2 - $sin * $l['h'] / 2),
        $l['y'] + $l['h'] / 2 - ($sin * $l['w'] / 2 + $cos * $l['h'] / 2));

    if (!empty($l['mirror'])) {
        $m = array(-$m[0], -$m[1], $m[2], $m[3], $m[0] * $l['w'] + $m[4], $m[1] * $l['w'] + $m[5]);
    }

    if ($vb) {
        $sx = $l['w'] / $vb['w'];
        $sy = $l['h'] / $vb['h'];
        $m = array($m[0] * $sx, $m[1] * $sx, $m[2] * $sy, $m[3] * $sy,
            $m[4] - $m[0] * $sx * $vb['x'] - $m[2] * $sy * $vb['y'],
            $m[5] - $m[1] * $sx * $vb['x'] - $m[3] * $sy * $vb['y']);
    }

    return $m;
}

/**
 * The bounds of a layer's box on the artboard, turned. Pure.
 *
 * @param array $l x, y, w, h, rot
 * @return float[] x0, y0, x1, y1
 */
function pg_image_draw_bounds($l)
{
    $m = pg_image_draw_matrix(array('mirror' => false) + $l);
    $xs = array();
    $ys = array();

    foreach (array(array(0, 0), array($l['w'], 0), array($l['w'], $l['h']), array(0, $l['h'])) as $p) {
        $xs[] = $m[0] * $p[0] + $m[2] * $p[1] + $m[4];
        $ys[] = $m[1] * $p[0] + $m[3] * $p[1] + $m[5];
    }

    return array(min($xs), min($ys), max($xs), max($ys));
}

/**
 * The whole pixels of the artboard a layer's box touches. Pure.
 *
 * @return int[]|null x, y, w, h; null when the box lies outside
 */
function pg_image_draw_region($l, $width, $height)
{
    $b = pg_image_draw_bounds($l);
    $x0 = max(0, (int) floor($b[0] + 1e-9));
    $y0 = max(0, (int) floor($b[1] + 1e-9));
    $x1 = min($width, (int) ceil($b[2] - 1e-9));
    $y1 = min($height, (int) ceil($b[3] - 1e-9));

    return (($x1 > $x0) && ($y1 > $y0)) ? array($x0, $y0, $x1 - $x0, $y1 - $y0) : null;
}

/**
 * An svg layer as the SVG document that draws its part of the artboard:
 * the region as the root's view, the layer's markup under its matrix and
 * clipped to its own box, with the document's definitions and its root id
 * (style rules are scoped to it) - the picture of its own box the editor
 * paints (svgPictureSrc()), without a second sampling. Pure.
 *
 * @param array  $l      the layer: markup, vb, x, y, w, h, rot, mirror
 * @param int[]  $region pg_image_draw_region()
 * @param string $defs   the document's svgDefs
 * @param string $root   the document's svgRootId
 * @return string
 */
function pg_image_draw_svg_document($l, $region, $defs, $root)
{
    $number = function ($value) {
        $text = rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');

        return ($text === '-0') ? '0' : $text;
    };
    $vb = $l['vb'];

    return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"' . (($root !== '') ? ' id="' . $root . '"' : '')
        . ' width="' . $region[2] . '" height="' . $region[3] . '" viewBox="' . implode(' ', $region) . '">' . $defs
        . '<defs><clipPath id="pgdraw-box"><rect x="' . $number($vb['x']) . '" y="' . $number($vb['y']) . '" width="' . $number($vb['w']) . '" height="' . $number($vb['h']) . '"/></clipPath></defs>'
        . '<g transform="matrix(' . implode(' ', array_map($number, pg_image_draw_matrix($l, $vb))) . ')"><g clip-path="url(#pgdraw-box)">' . $l['markup'] . '</g></g></svg>';
}

/**
 * Whether a sanitized SVG holds only what the fixture set covers: shapes,
 * gradients, clip paths, markers, use and style rules with plain selectors.
 * Text and pictures are the layers' own business, and a filter, a mask, a
 * blend mode or a CSS function is drawn differently by librsvg, or not at
 * all. Pure.
 *
 * @param string $svg
 * @param int    $max elements
 * @return bool
 */
function pg_image_draw_svg_plain($svg, $max)
{
    $elements = array('svg', 'g', 'defs', 'title', 'desc', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'linearGradient', 'radialGradient', 'stop', 'clipPath', 'marker', 'use', 'style');
    $properties = 'filter|mask|mix-blend-mode|isolation|paint-order|vector-effect|transform-origin|transform-box';
    $dom = new DOMDocument();
    $errors = libxml_use_internal_errors(true);
    $loaded = $dom->loadXML($svg, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($errors);

    if (!$loaded) {
        return false;
    }

    $nodes = $dom->getElementsByTagName('*');

    if ($nodes->length > $max) {
        return false;
    }

    foreach ($nodes as $node) {
        if (!in_array($node->localName, $elements, true)) {
            return false;
        }

        foreach ($node->attributes as $attribute) {
            if (preg_match('/^(?:' . $properties . ')$/i', $attribute->nodeName) || preg_match('/\b(?:var|calc|attr|env)\s*\(/i', $attribute->value)
                || (($attribute->nodeName === 'style') && preg_match('/(?:^|[;\s])(?:' . $properties . ')\s*:/i', $attribute->value))) {
                return false;
            }
        }

        if ($node->localName === 'style') {
            $css = $node->textContent;

            // Rules of ids, classes and element names only; no at-rule.
            if (preg_match('/[{;\s](?:' . $properties . ')\s*:|\b(?:var|calc|attr|env)\s*\(/i', $css)
                || !preg_match('/^[\sA-Za-z0-9_#.,>-]*$/', (string) preg_replace('/\{[^{}]*\}/', ' ', $css))) {
                return false;
            }
        }
    }

    return true;
}

/**
 * Whether a text is in letters this renderer places as a browser does: one
 * after another, left to right. Latin, Greek and Cyrillic with their
 * punctuation, currency signs, arrows and mathematical signs; no combining
 * mark, no script that is shaped or runs right to left, no emoji. Pure.
 *
 * @param mixed $text
 * @return bool
 */
function pg_image_draw_text_plain($text)
{
    return is_string($text) && (bool) preg_match('/^[\n\x{20}-\x{7E}\x{A0}-\x{AC}\x{AE}-\x{24F}\x{370}-\x{3FF}\x{400}-\x{482}\x{48A}-\x{52F}\x{1E00}-\x{1EFF}'
        . '\x{2010}-\x{2027}\x{2030}-\x{205E}\x{20A0}-\x{20BF}\x{2100}-\x{214F}\x{2190}-\x{21FF}\x{2200}-\x{22FF}]*$/u', $text);
}

/**
 * The font file of a text layer's face: a Google family ("g:" and its
 * name) in a weight and style a browser gets as a face of its own from the
 * editor's font link (upright 300, 400, 600, 700, 900; italic 400, 700),
 * from the editor's font folder (data/fonts) or, for Roboto, the bundled
 * files. '' for the system font lists (they differ by the reader's
 * machine) and for a face the server has no file of. Nothing is fetched.
 *
 * @param mixed $font
 * @param int   $weight
 * @param bool  $italic
 * @return string the file's path
 */
function pg_image_draw_font_file($font, $weight, $italic)
{
    $face = (is_string($font) && (strncmp($font, 'g:', 2) === 0)) ? pg_image_editor_font_face(substr($font, 2), $weight, $italic) : null;

    if (!$face || ($face['family'] === '') || !in_array($face['weight'], $face['italic'] ? array(400, 700) : array(300, 400, 600, 700, 900), true)) {
        return '';
    }

    $path = PG_FUNCTIONS_DIR . '/data/fonts/' . $face['file'];
    $bundled = array(300 => 'Light', 400 => 'Regular', 700 => 'Bold', 900 => 'Black');

    if (!is_file($path) && (strtolower($face['family']) === 'roboto') && isset($bundled[$face['weight']])) {
        $name = $bundled[$face['weight']] . ($face['italic'] ? 'Italic' : '');
        $path = PG_FUNCTIONS_DIR . '/assets/fonts/roboto/Roboto-' . (($name === 'RegularItalic') ? 'Italic' : $name) . '.ttf';
    }

    // The path is written into ImageMagick's drawing text between quotes.
    return (is_file($path) && !preg_match('/[\'"\\\\\x00-\x1f]/', str_replace('\\', '/', $path))) ? str_replace('\\', '/', $path) : '';
}

/**
 * The tables of a TrueType or OpenType file this renderer reads: its units
 * per em, the ascent and descent a browser lays text with (hhea, or OS/2's
 * typographic ones when the font asks for them), its weight class and
 * italic flag, and where its glyph widths and its format 4 character map
 * are. Kept for the request.
 *
 * @param string $path
 * @return array|null upem, ascent, descent (font units, descent positive),
 *                    weight, italic, bytes, map, widths, count
 */
function pg_image_draw_font_read($path)
{
    static $read = array();

    if (array_key_exists($path, $read)) {
        return $read[$path];
    }

    if (count($read) >= 8) {
        $read = array();
    }

    $bytes = (string) @file_get_contents($path, false, null, 0, 16 * 1024 * 1024);
    $tables = array();

    for ($i = 0, $count = min(64, pg_image_draw_u16($bytes, 4)); $i < $count; $i++) {
        $tables[substr($bytes, 12 + 16 * $i, 4)] = pg_image_draw_u32($bytes, 12 + 16 * $i + 8);
    }

    if (!pg_image_editor_font_bytes_ok($bytes) || !isset($tables['head'], $tables['hhea'], $tables['hmtx'], $tables['cmap'], $tables['OS/2'])) {
        return $read[$path] = null;
    }

    $map = 0;
    $cmap = $tables['cmap'];

    for ($i = 0, $count = min(32, pg_image_draw_u16($bytes, $cmap + 2)); $i < $count; $i++) {
        $platform = pg_image_draw_u16($bytes, $cmap + 4 + 8 * $i);
        $at = $cmap + pg_image_draw_u32($bytes, $cmap + 4 + 8 * $i + 4);

        if ((($platform === 0) || (($platform === 3) && (pg_image_draw_u16($bytes, $cmap + 4 + 8 * $i + 2) === 1))) && (pg_image_draw_u16($bytes, $at) === 4)) {
            $map = $at;
        }
    }

    $os2 = $tables['OS/2'];
    $signed = function ($at) use ($bytes) {
        $value = pg_image_draw_u16($bytes, $at);

        return ($value >= 32768) ? $value - 65536 : $value;
    };
    // Bit 7 of fsSelection: lay out with the typographic ascent and descent.
    $typo = (bool) (pg_image_draw_u16($bytes, $os2 + 62) & 128);
    $ascent = $typo ? $signed($os2 + 68) : $signed($tables['hhea'] + 4);
    $descent = $typo ? -$signed($os2 + 70) : -$signed($tables['hhea'] + 6);
    $upem = pg_image_draw_u16($bytes, $tables['head'] + 18);

    if (!$map || ($upem < 16) || (($ascent === 0) && ($descent === 0))) {
        return $read[$path] = null;
    }

    return $read[$path] = array(
        'upem'    => $upem,
        'ascent'  => $ascent,
        'descent' => $descent,
        'weight'  => pg_image_draw_u16($bytes, $os2 + 4),
        'italic'  => (bool) (pg_image_draw_u16($bytes, $os2 + 62) & 1),
        'bytes'   => $bytes,
        'map'     => $map,
        'widths'  => $tables['hmtx'],
        'count'   => max(1, pg_image_draw_u16($bytes, $tables['hhea'] + 34)),
    );
}

/**
 * An unsigned 16 bit number of a font file at a byte; 0 outside it. Pure.
 *
 * @param string $bytes
 * @param int    $at
 * @return int
 */
function pg_image_draw_u16($bytes, $at)
{
    return (($at >= 0) && ($at + 2 <= strlen($bytes))) ? (ord($bytes[$at]) << 8) | ord($bytes[$at + 1]) : 0;
}

/**
 * An unsigned 32 bit number of a font file at a byte. Pure.
 *
 * @param string $bytes
 * @param int    $at
 * @return int
 */
function pg_image_draw_u32($bytes, $at)
{
    return (pg_image_draw_u16($bytes, $at) << 16) | pg_image_draw_u16($bytes, $at + 2);
}

/**
 * The advance of a letter in a font's units, from its glyph's horizontal
 * metrics; null when the font has no glyph for it (a browser would draw
 * that letter from another font). Pure.
 *
 * @param array  $font pg_image_draw_font_read()
 * @param string $letter one character
 * @return int|null
 */
function pg_image_draw_font_advance($font, $letter)
{
    $bytes = $font['bytes'];
    // mb_ord() is PHP 7.2's.
    $code = unpack('N', str_pad((string) mb_convert_encoding($letter, 'UCS-4BE', 'UTF-8'), 4, "\0", STR_PAD_LEFT))[1];
    $table = $font['map'];
    $segments = pg_image_draw_u16($bytes, $table + 6);
    $ends = $table + 14;
    $starts = $ends + $segments + 2;
    $deltas = $starts + $segments;
    $offsets = $deltas + $segments;
    $glyph = 0;

    for ($i = 0; ($i < $segments) && ($code <= 0xFFFF); $i += 2) {
        if ($code <= pg_image_draw_u16($bytes, $ends + $i)) {
            $start = pg_image_draw_u16($bytes, $starts + $i);
            $offset = pg_image_draw_u16($bytes, $offsets + $i);

            if ($code >= $start) {
                $glyph = $offset ? pg_image_draw_u16($bytes, $offsets + $i + $offset + 2 * ($code - $start)) : (($code + pg_image_draw_u16($bytes, $deltas + $i)) & 0xFFFF);
            }

            break;
        }
    }

    if (!$glyph) {
        return null;
    }

    return pg_image_draw_u16($bytes, $font['widths'] + 4 * min($glyph, $font['count'] - 1));
}

/**
 * Whether a font file is the face asked (its own weight class and italic
 * flag: the editor's font folder keeps the nearest face under the name
 * asked) and has a glyph for every letter of the text.
 *
 * @param string $path
 * @param int    $weight
 * @param bool   $italic
 * @param string $text
 * @return bool
 */
function pg_image_draw_font_fits($path, $weight, $italic, $text)
{
    $font = pg_image_draw_font_read($path);

    if (!$font || ($font['weight'] !== (int) $weight) || ($font['italic'] !== (bool) $italic)) {
        return false;
    }

    foreach (array_unique((array) preg_split('//u', str_replace("\n", '', (string) $text), -1, PREG_SPLIT_NO_EMPTY)) as $letter) {
        if (pg_image_draw_font_advance($font, $letter) === null) {
            return false;
        }
    }

    return true;
}

/**
 * Whether a layer carries nothing beyond the fields this renderer knows:
 * any other field with a value (a shadow, a warp, an eraser's strokes) is
 * something the editor draws and this does not. Pure.
 *
 * @param array    $layer
 * @param string[] $own the type's fields
 * @return bool
 */
function pg_image_draw_known($layer, $own)
{
    $known = array_merge(array('id', 'type', 'name', 'locked', 'hidden', 'x', 'y', 'w', 'h', 'rot', 'opacity'), $own);

    foreach ($layer as $key => $value) {
        if (!in_array((string) $key, $known, true) && !empty($value)) {
            return false;
        }
    }

    return true;
}

/**
 * The Bootstrap Icons code points.
 *
 * @return array code => true
 */
function pg_image_draw_icon_codes()
{
    static $codes = null;

    if ($codes === null) {
        $map = json_decode((string) @file_get_contents(PG_FUNCTIONS_DIR . '/assets/fonts/bootstrap-icons/bootstrap-icons.json'), true);
        $codes = is_array($map) ? array_fill_keys(array_map('intval', $map), true) : array();
    }

    return $codes;
}

/**
 * A document checked and laid out for drawing: every visible layer as an
 * item this server draws, or the first reason it does not. Draws nothing.
 *
 * @param mixed         $doc     the canvas document, arrays or objects
 * @param array         $can     pg_image_draw_can()
 * @param callable|null $picture a picture layer's address => the local path
 *                               of a file the canvas's readers may see, or ''
 * @param float         $until   the time the budget ends
 * @return array error ('' | board | layers | kind | svg | text | font |
 *               picture | time), width, height, background ('' for a
 *               transparent one), items
 */
function pg_image_draw_plan($doc, $can, $picture = null, $until = 0.0)
{
    $limits = pg_image_draw_limits();
    $doc = is_object($doc) ? json_decode((string) json_encode($doc), true) : $doc;
    $board = (is_array($doc) && is_array($doc['artboard'] ?? null)) ? $doc['artboard'] : array();
    $width = $board['width'] ?? 0;
    $height = $board['height'] ?? 0;
    $fill = (string) ($board['background'] ?? '');
    $fill = ($fill === 'transparent') ? '' : $fill;
    $plan = array('error' => '', 'width' => $width, 'height' => $height, 'background' => strtolower($fill), 'items' => array());
    $fail = function ($why) use ($plan) {
        return array('error' => $why) + $plan;
    };

    if (!is_int($width) || !is_int($height) || ($width < 1) || ($height < 1) || ($width * $height > $limits['pixels']) || (($fill !== '') && !pg_image_draw_color($fill))) {
        return $fail('board');
    }

    $layers = $doc['layers'] ?? array();
    $defs = $doc['svgDefs'] ?? '';
    $root = $doc['svgRootId'] ?? '';

    if (!is_array($layers) || (count($layers) > $limits['layers']) || !is_string($defs) || !is_string($root) || !preg_match('/^(?:[A-Za-z][A-Za-z0-9_-]{0,63})?$/', $root)) {
        return $fail('layers');
    }

    $letters = 0;

    foreach ($layers as $layer) {
        if (($until > 0) && (microtime(true) > $until)) {
            return $fail('time');
        }

        if (!is_array($layer)) {
            return $fail('kind');
        }

        if (!empty($layer['hidden'])) {
            continue;
        }

        $type = (string) ($layer['type'] ?? '');
        $item = array(
            'kind'    => $type,
            'x'       => pg_image_draw_number($layer, 'x', -100000, 100000),
            'y'       => pg_image_draw_number($layer, 'y', -100000, 100000),
            'w'       => pg_image_draw_number($layer, 'w', 0.01, 20000),
            'h'       => pg_image_draw_number($layer, 'h', 0.01, 20000),
            'rot'     => pg_image_draw_number($layer, 'rot', -36000, 36000, 0),
            'opacity' => pg_image_draw_number($layer, 'opacity', 0, 1, 1),
            'mirror'  => !empty($layer['mirror']),
        );

        if (in_array(null, $item, true)) {
            return $fail('kind');
        }

        if ($item['opacity'] <= 0) {
            continue;
        }

        if ($type === 'svg') {
            $vb = is_array($layer['vb'] ?? null) ? $layer['vb'] : array();
            $item['vb'] = array('x' => pg_image_draw_number($vb, 'x', -1e7, 1e7), 'y' => pg_image_draw_number($vb, 'y', -1e7, 1e7),
                'w' => pg_image_draw_number($vb, 'w', 1e-6, 1e7), 'h' => pg_image_draw_number($vb, 'h', 1e-6, 1e7));
            $item['markup'] = $layer['markup'] ?? null;

            if (empty($can['svg']) || in_array(null, $item['vb'], true) || !is_string($item['markup'])
                || !pg_image_draw_known($layer, array('kind', 'imported', 'markup', 'vb', 'mirror', 'fileName'))) {
                return $fail('svg');
            }

            $region = pg_image_draw_region($item, $width, $height);

            if (!$region) {
                continue;
            }

            $svg = pg_image_draw_svg_document($item, $region, $defs, $root);
            $svg = (strlen($svg) <= $limits['svg']) ? pg_svg_sanitize($svg) : '';

            if (($svg === '') || !pg_image_draw_svg_plain($svg, $limits['elements'])) {
                return $fail('svg');
            }

            $plan['items'][] = array('kind' => 'svg', 'svg' => $svg, 'x' => $region[0], 'y' => $region[1], 'w' => $region[2], 'h' => $region[3], 'opacity' => $item['opacity']);
        } elseif (in_array($type, array('rect', 'ellipse', 'line'), true)) {
            $item['color'] = pg_image_draw_color($layer['color'] ?? '#000000');
            $item['fill'] = (($type !== 'line') && in_array($layer['fill'] ?? '', array('solid', 'soft'), true)) ? $layer['fill'] : '';
            $item['stroke'] = pg_image_draw_number($layer, 'stroke', 0, 2000, 1);
            $item['dash'] = (string) ($layer['dash'] ?? '');
            $item['radius'] = ($type === 'rect') ? pg_image_draw_number($layer, 'radius', 0, 20000, 0) : 0.0;

            if (in_array(null, $item, true) || !pg_image_draw_known($layer, array('color', 'fill', 'stroke', 'dash', 'radius', 'mirror'))) {
                return $fail('kind');
            }

            $plan['items'][] = $item;
        } elseif ($type === 'text') {
            $text = $layer['text'] ?? '';
            $item['text'] = $text;
            $item['size'] = pg_image_draw_number($layer, 'size', 0.5, 4000);
            $item['lineHeight'] = pg_image_draw_number($layer, 'lineHeight', 0.1, 10, 1.25);
            $item['spacing'] = pg_image_draw_number($layer, 'spacing', -1, 10, 0);
            $item['pad'] = pg_image_draw_number($layer, 'pad', 0, 10, 0);
            $item['radius'] = pg_image_draw_number($layer, 'radius', 0, 10, 0);
            $item['align'] = in_array($layer['align'] ?? '', array('center', 'right'), true) ? $layer['align'] : 'left';
            $item['color'] = pg_image_draw_color($layer['color'] ?? '#000000');
            $item['bg'] = empty($layer['bg']) ? array() : pg_image_draw_color($layer['bg']);
            $weight = (int) (pg_image_draw_number($layer, 'weight', 100, 900, 400) ?: 400);

            if (empty($can['text']) || in_array(null, $item, true) || !pg_image_draw_text_plain($text) || (mb_strlen($text) > 2000)
                || !pg_image_draw_known($layer, array('text', 'font', 'size', 'weight', 'italic', 'align', 'lineHeight', 'spacing', 'color', 'bg', 'pad', 'radius'))) {
                return $fail('text');
            }

            if (trim($text) === '') {
                continue;
            }

            $item['file'] = pg_image_draw_font_file($layer['font'] ?? '', $weight, !empty($layer['italic']));
            $letters += mb_strlen($text);

            if (($item['file'] === '') || !pg_image_draw_font_fits($item['file'], $weight, !empty($layer['italic']), $text) || ($letters > $limits['letters'])) {
                return $fail('font');
            }

            $font = pg_image_draw_font_read($item['file']);
            $em = max(0.5, $item['size']) / $font['upem'];
            // Chromium lays a line out with the font's ascent and descent
            // rounded to whole pixels.
            $item['layout'] = pg_image_draw_text_layout($item, function ($letter) use ($font, $em) {
                return pg_image_draw_font_advance($font, $letter) * $em;
            }, round($font['ascent'] * $em), round($font['descent'] * $em));
            $plan['items'][] = $item;
        } elseif ($type === 'glyph') {
            $item['color'] = pg_image_draw_color($layer['color'] ?? '#000000');
            $item['code'] = $layer['code'] ?? 0;

            if (empty($can['icons']) || !$item['color'] || (($layer['set'] ?? '') !== 'bi') || !is_int($item['code'])
                || !isset(pg_image_draw_icon_codes()[$item['code']]) || !pg_image_draw_known($layer, array('set', 'code', 'color'))) {
                return $fail('kind');
            }

            $plan['items'][] = $item;
        } elseif ($type === 'image') {
            $path = (is_string($layer['src'] ?? null) && $picture) ? (string) call_user_func($picture, $layer['src']) : '';
            $size = (($path !== '') && is_file($path) && ((int) @filesize($path) <= $limits['file'])) ? @getimagesize($path) : false;
            $formats = array(IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpeg', IMAGETYPE_WEBP => 'webp');
            $item['format'] = ($size && isset($formats[$size[2]])) ? $formats[$size[2]] : '';
            $item['path'] = $path;

            if (!in_array($item['format'], (array) ($can['pictures'] ?? array()), true) || ($size[0] * $size[1] > $limits['picture'])
                || !pg_image_draw_known($layer, array('src', 'fileId', 'fileName'))) {
                return $fail('picture');
            }

            $plan['items'][] = $item;
        } else {
            return $fail('kind');
        }
    }

    return $plan;
}

/**
 * The line layout of a text layer, as the editor lays it out
 * (M.textLayout()): lines break only at a new line; letter spacing goes
 * after each letter but the last. Every letter gets its x on the line (the
 * widths of the letters before it and the spacing), as this renderer places
 * letters one by one. Pure but for the widths it is given.
 *
 * @param array    $l       text, size, lineHeight, spacing, pad, align, bg
 * @param callable $advance a letter => its width in pixels
 * @param float    $ascent  the font's, in pixels
 * @param float    $descent
 * @return array w, h, lines: text, x (from the box's left), base (the
 *               baseline from its top), width, chars ([letter, x] each)
 */
function pg_image_draw_text_layout($l, $advance, $ascent, $descent)
{
    $size = max(0.5, $l['size']);
    $lh = $size * $l['lineHeight'];
    $spacing = $l['spacing'] * $size;
    $pad = $l['bg'] ? max(0, $l['pad']) * $size : 0;
    $ascent = ($ascent > 0) ? $ascent : $size * 0.8;
    $descent = ($descent >= 0) ? $descent : $size * 0.2;
    $lines = array();
    $widest = 0;

    foreach (explode("\n", $l['text']) as $i => $text) {
        $chars = array();
        $at = 0.0;

        foreach ((array) preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $j => $letter) {
            $chars[] = array($letter, round($at + $j * $spacing, 2));
            $at += (float) call_user_func($advance, $letter);
        }

        $width = max(0, $at + max(0, count($chars) - 1) * $spacing);
        $widest = max($widest, $width);
        $lines[] = array('text' => $text, 'width' => $width, 'chars' => $chars, 'base' => round($pad + $i * $lh + ($lh - $ascent - $descent) / 2 + $ascent, 2));
    }

    // An empty text keeps room for the caret.
    $inner = max($widest, $size * 0.6);

    foreach ($lines as $i => $line) {
        $free = $inner - $line['width'];
        $lines[$i]['x'] = round($pad + (($l['align'] === 'center') ? $free / 2 : (($l['align'] === 'right') ? $free : 0)), 2);
        $lines[$i]['width'] = round($line['width'], 2);
    }

    return array('w' => round($inner + 2 * $pad, 2), 'h' => round(count($lines) * $lh + 2 * $pad, 2), 'lines' => $lines);
}

/**
 * The pieces a curve of radius r is cut into over an angle, so no piece
 * strays more than a tenth of a pixel from it. Pure.
 *
 * @param float $r
 * @param float $angle radians
 * @return int
 */
function pg_image_draw_arc_steps($r, $angle)
{
    $step = ($r > 0.1) ? 2 * acos(max(-1, 1 - 0.1 / $r)) : M_PI / 2;

    return (int) max(2, min(720, ceil(abs($angle) / max($step, 0.001))));
}

/**
 * The outline of a drawn shape in its box's frame (0, 0 its corner), as
 * the editor's path runs (M.shapePath(): roundedD() from the top edge's
 * start clockwise, ellipseD() from its left end down and round, a line
 * along the box's middle), its curves cut into straight pieces; a closed
 * one ends where it started. Inset moves the outline inwards. Pure.
 *
 * @param string $kind   rect, ellipse or line
 * @param float  $w
 * @param float  $h
 * @param float  $radius a rectangle's corners
 * @param float  $inset
 * @return float[][] x, y
 */
function pg_image_draw_shape_points($kind, $w, $h, $radius = 0.0, $inset = 0.0)
{
    if ($kind === 'line') {
        return array(array(0.0, $h / 2), array((float) $w, $h / 2));
    }

    $points = array();

    if ($kind === 'ellipse') {
        $cx = max($w / 2, 0.1);
        $cy = max($h / 2, 0.1);
        $rx = $cx - $inset;
        $ry = $cy - $inset;

        if (($rx <= 0) || ($ry <= 0)) {
            return array();
        }

        $n = pg_image_draw_arc_steps(max($rx, $ry), 2 * M_PI);

        for ($i = 0; $i <= $n; $i++) {
            $t = 2 * M_PI * (($i === $n) ? 0 : $i) / $n;
            $points[] = array($cx - $rx * cos($t), $cy + $ry * sin($t));
        }

        return $points;
    }

    $x0 = $inset;
    $y0 = $inset;
    $x1 = $w - $inset;
    $y1 = $h - $inset;

    if (($x1 <= $x0) || ($y1 <= $y0)) {
        return array();
    }

    $r = min(max(0, min($radius, $w / 2, $h / 2) - $inset), ($x1 - $x0) / 2, ($y1 - $y0) / 2);

    if ($r <= 0) {
        return array(array($x0, $y0), array($x1, $y0), array($x1, $y1), array($x0, $y1), array($x0, $y0));
    }

    $n = pg_image_draw_arc_steps($r, M_PI / 2);
    $corners = array(array($x1 - $r, $y0 + $r, -90), array($x1 - $r, $y1 - $r, 0), array($x0 + $r, $y1 - $r, 90), array($x0 + $r, $y0 + $r, 180));

    $points[] = array($x0 + $r, $y0);

    foreach ($corners as $corner) {
        for ($i = 0; $i <= $n; $i++) {
            $a = deg2rad($corner[2] + 90 * $i / $n);
            $points[] = array($corner[0] + $r * cos($a), $corner[1] + $r * sin($a));
        }
    }

    $points[] = array($x0 + $r, $y0);

    return $points;
}

/**
 * The dots of a dotted line along an outline: a dash 0.01 long every
 * period, which round caps make a dot as wide as the line (M.dashFor()).
 * Pure.
 *
 * @param float[][] $points
 * @param float     $period
 * @return float[][] the dots' middles
 */
function pg_image_draw_dots($points, $period)
{
    $dots = array();
    $next = 0.005;
    $walked = 0.0;

    for ($i = 1, $count = count($points); ($i < $count) && (count($dots) < 20000); $i++) {
        $dx = $points[$i][0] - $points[$i - 1][0];
        $dy = $points[$i][1] - $points[$i - 1][1];
        $length = sqrt($dx * $dx + $dy * $dy);

        while (($length > 0) && ($next <= $walked + $length)) {
            $t = ($next - $walked) / $length;
            $dots[] = array($points[$i - 1][0] + $dx * $t, $points[$i - 1][1] + $dy * $t);
            $next += $period;
        }

        $walked += $length;
    }

    return $dots;
}

/**
 * A colour with its opacity as ImageMagick reads it.
 *
 * @param int[] $rgb
 * @param float $alpha
 * @return ImagickPixel
 */
function pg_image_draw_pixel($rgb, $alpha = 1.0)
{
    return new ImagickPixel(sprintf('rgba(%d,%d,%d,%.4F)', $rgb[0], $rgb[1], $rgb[2], max(0, min(1, $alpha))));
}

/**
 * A drawing in a layer's frame (0, 0 the corner of its w x h box, turned
 * around the box's middle, mirrored inside it), on a picture whose corner
 * lies at $origin of the artboard and that is $scale times the artboard's
 * size. Outlines (polygons, lines, circles) are placed by the middles of
 * pixels in ImageMagick where the editor's canvas names their corners:
 * half a pixel is taken off for them ($shift); letters are placed by the
 * corners already.
 *
 * @param array $l      x, y, rot
 * @param float $w
 * @param float $h
 * @param int[] $origin x, y
 * @param bool  $mirror
 * @param int   $scale
 * @param float $shift
 * @return ImagickDraw
 */
function pg_image_draw_frame($l, $w, $h, $origin, $mirror = false, $scale = 1, $shift = 0.5)
{
    $draw = new ImagickDraw();
    $draw->translate($scale * ($l['x'] + $w / 2 - $origin[0]) - $shift, $scale * ($l['y'] + $h / 2 - $origin[1]) - $shift);
    $draw->rotate($l['rot']);
    $draw->scale($mirror ? -$scale : $scale, $scale);
    $draw->translate(-$w / 2, -$h / 2);

    return $draw;
}

/**
 * A picture's opacity taken down.
 *
 * @param Imagick $tile
 * @param float   $opacity
 */
function pg_image_draw_fade($tile, $opacity)
{
    $tile->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);

    if ($opacity < 1) {
        $tile->evaluateImage(Imagick::EVALUATE_MULTIPLY, $opacity, Imagick::CHANNEL_ALPHA);
    }
}

/**
 * Draws onto the canvas as one: on a picture of the layer's region of its
 * own (its box grown by $grow) that is then laid on at the layer's
 * opacity, as the editor's canvas draws a text or a stroke with
 * globalAlpha; straight onto the canvas at full opacity. Fine: the region
 * is drawn at up to four times its size and brought down by averaging, so
 * letters, which ImageMagick sets on whole pixels, are placed to a quarter
 * of one and smoothed by their area, as a canvas does.
 *
 * @param Imagick  $canvas
 * @param array    $box     x, y, w, h, rot
 * @param float    $grow
 * @param float    $opacity
 * @param callable $paint   (origin, scale) => ImagickDraw[]
 * @param bool     $fine
 */
function pg_image_draw_group($canvas, $box, $grow, $opacity, $paint, $fine = false)
{
    if (($opacity >= 1) && !$fine) {
        foreach (call_user_func($paint, array(0, 0), 1) as $draw) {
            $canvas->drawImage($draw);
        }

        return;
    }

    $region = pg_image_draw_region(array('x' => $box['x'] - $grow, 'y' => $box['y'] - $grow, 'w' => $box['w'] + 2 * $grow, 'h' => $box['h'] + 2 * $grow, 'rot' => $box['rot']),
        $canvas->getImageWidth(), $canvas->getImageHeight());

    if (!$region) {
        return;
    }

    $scale = 1;

    foreach ($fine ? array(4, 3, 2) : array() as $try) {
        if ($region[2] * $region[3] * $try * $try <= 4000000) {
            $scale = $try;
            break;
        }
    }

    $tile = new Imagick();
    $tile->newImage($region[2] * $scale, $region[3] * $scale, new ImagickPixel('transparent'));

    foreach (call_user_func($paint, array($region[0], $region[1]), $scale) as $draw) {
        $tile->drawImage($draw);
    }

    if ($scale > 1) {
        $tile->scaleImage($region[2], $region[3]);
    }

    pg_image_draw_fade($tile, $opacity);
    $canvas->compositeImage($tile, Imagick::COMPOSITE_OVER, $region[0], $region[1]);
    $tile->clear();
}

/**
 * An SVG read by librsvg inside this process, at its own size.
 *
 * @param string $svg sanitized
 * @param int    $w
 * @param int    $h
 * @return Imagick
 */
function pg_image_draw_svg_tile($svg, $w, $h)
{
    $tile = new Imagick();
    $tile->setBackgroundColor(new ImagickPixel('transparent'));
    // The reader is named: left to ImageMagick, an SVG goes to the SVG
    // format and its delegate program first.
    $tile->setFormat('RSVG');
    $tile->readImageBlob($svg);

    if (($tile->getImageWidth() !== (int) $w) || ($tile->getImageHeight() !== (int) $h)) {
        throw new RuntimeException('The SVG came back in another size.');
    }

    return $tile;
}

/**
 * A picture layer's file read and laid on the canvas: its first bytes
 * name the reader, its first frame is taken, turned upright as its EXIF
 * says, and drawn into its box through one affine map that resamples it
 * once, at fractions of a pixel as a browser's drawImage() places it.
 *
 * @param Imagick $canvas
 * @param array   $item pg_image_draw_plan()
 */
function pg_image_draw_picture($canvas, $item)
{
    $tile = new Imagick();
    $side = (int) (2 * ceil(max($item['w'], $item['h'])));

    // A JPEG is decoded near the size it is drawn at.
    $tile->setOption('jpeg:size', $side . 'x' . $side);
    $tile->setFormat($item['format']);
    $tile->readImageBlob((string) file_get_contents($item['path']));

    // A browser shows one frame, in sRGB.
    $profiles = $tile->getImageProfiles('icc', true);

    if (($tile->getNumberImages() !== 1) || (!empty($profiles['icc']) && (stripos($profiles['icc'], 'sRGB') === false))
        || !in_array($tile->getImageColorspace(), array(Imagick::COLORSPACE_SRGB, Imagick::COLORSPACE_GRAY), true)) {
        throw new RuntimeException('A picture a browser shows another way.');
    }

    if (!function_exists('pg_image_apply_imagick_orientation')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/image_engine.php');
    }

    pg_image_apply_imagick_orientation($tile);
    $tile->setImagePage(0, 0, 0, 0);
    $tile->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);

    $sw = $tile->getImageWidth();
    $sh = $tile->getImageHeight();
    $k = max($item['w'] / $sw, $item['h'] / $sh);

    // Brought near its drawn size first: the map's filter then works over
    // a few pixels, not hundreds.
    if ($k < 0.5) {
        $tile->resizeImage(max(1, (int) round($sw * $k * 2)), max(1, (int) round($sh * $k * 2)), Imagick::FILTER_LANCZOS, 1);
        $sw = $tile->getImageWidth();
        $sh = $tile->getImageHeight();
    }

    $region = pg_image_draw_region($item, $canvas->getImageWidth(), $canvas->getImageHeight());
    $edges = Imagick::VIRTUALPIXELMETHOD_TRANSPARENT;

    // A picture that stays upright (or a quarter turn) is drawn by a
    // browser without antialiased edges: the pixels whose middles it
    // covers, its own edge pixels repeated.
    if (abs(fmod($item['rot'], 90)) < 1e-9) {
        $b = pg_image_draw_bounds($item);
        $x0 = max(0, (int) ceil($b[0] - 0.5 - 1e-9));
        $y0 = max(0, (int) ceil($b[1] - 0.5 - 1e-9));
        $x1 = min($canvas->getImageWidth(), (int) ceil($b[2] - 0.5 - 1e-9));
        $y1 = min($canvas->getImageHeight(), (int) ceil($b[3] - 0.5 - 1e-9));
        $region = (($x1 > $x0) && ($y1 > $y0)) ? array($x0, $y0, $x1 - $x0, $y1 - $y0) : null;
        $edges = Imagick::VIRTUALPIXELMETHOD_EDGE;
    }

    if (!$region) {
        $tile->clear();

        return;
    }

    $tile->setImageVirtualPixelMethod($edges);
    $tile->setImageArtifact('distort:viewport', $region[2] . 'x' . $region[3] . '+' . $region[0] . '+' . $region[1]);
    $tile->distortImage(Imagick::DISTORTION_AFFINEPROJECTION, pg_image_draw_matrix($item, array('x' => 0, 'y' => 0, 'w' => $sw, 'h' => $sh)), false);
    pg_image_draw_fade($tile, $item['opacity']);
    $canvas->compositeImage($tile, Imagick::COMPOSITE_OVER, $region[0], $region[1]);
    $tile->clear();
}

/**
 * One item of a plan onto the canvas, as the editor's layer types paint
 * (drawnPaint(), textPaint(), glyphPaint(), picturePaint(), svgPaint()).
 * ImageMagick's antialiasing reaches about half a pixel further out than a
 * canvas's: filled outlines are drawn a quarter of a pixel inside and
 * lines half a pixel thinner, which lays its edges on the canvas's.
 *
 * @param Imagick $canvas
 * @param array   $item pg_image_draw_plan()
 */
function pg_image_draw_item($canvas, $item)
{
    $kind = $item['kind'];
    $polygon = function ($points) {
        return array_map(function ($p) {
            return array('x' => $p[0], 'y' => $p[1]);
        }, $points);
    };

    if ($kind === 'svg') {
        $tile = pg_image_draw_svg_tile($item['svg'], $item['w'], $item['h']);
        pg_image_draw_fade($tile, $item['opacity']);
        $canvas->compositeImage($tile, Imagick::COMPOSITE_OVER, $item['x'], $item['y']);
        $tile->clear();
    } elseif ($kind === 'image') {
        pg_image_draw_picture($canvas, $item);
    } elseif ($kind === 'text') {
        $layout = $item['layout'];
        $size = max(0.5, $item['size']);
        $box = array('x' => $item['x'], 'y' => $item['y'], 'w' => $layout['w'], 'h' => $layout['h'], 'rot' => $item['rot']);

        // Background and letters fade as one.
        pg_image_draw_group($canvas, $box, $size, $item['opacity'], function ($origin, $scale) use ($item, $layout, $size, $box, $polygon) {
            $draws = array();

            if ($item['bg']) {
                $points = pg_image_draw_shape_points('rect', $layout['w'], $layout['h'], $item['radius'] * $size, 0.25 / $scale);

                if (count($points) > 2) {
                    $draw = pg_image_draw_frame($box, $layout['w'], $layout['h'], $origin, false, $scale);
                    $draw->setStrokeColor(new ImagickPixel('none'));
                    $draw->setFillColor(pg_image_draw_pixel($item['bg']));
                    $draw->polygon($polygon($points));
                    $draws[] = $draw;
                }
            }

            // Letter by letter, each at its place from the font's own
            // widths: ImageMagick's text runs on whole-pixel widths.
            $draw = pg_image_draw_frame(array('x' => $box['x'] * $scale, 'y' => $box['y'] * $scale) + $box, $layout['w'] * $scale, $layout['h'] * $scale,
                array($origin[0] * $scale, $origin[1] * $scale), false, 1, 0);
            $draw->setFont($item['file']);
            $draw->setFontSize($size * $scale);
            $draw->setTextAntialias(true);
            $draw->setStrokeColor(new ImagickPixel('none'));
            $draw->setFillColor(pg_image_draw_pixel($item['color']));

            foreach ($layout['lines'] as $line) {
                foreach ($line['chars'] as $char) {
                    if (!in_array($char[0], array(' ', "\xC2\xA0"), true)) {
                        $draw->annotation(($line['x'] + $char[1]) * $scale, $line['base'] * $scale, $char[0]);
                    }
                }
            }

            $draws[] = $draw;

            return $draws;
        }, true);
    } elseif ($kind === 'glyph') {
        // The icon font's letters are an em wide and stand on the baseline
        // an em high: centred, as textAlign center and textBaseline middle
        // place one.
        $size = max(1, min($item['w'], $item['h']));

        pg_image_draw_group($canvas, $item, 0, $item['opacity'], function ($origin, $scale) use ($item, $size) {
            $draw = pg_image_draw_frame(array('x' => $item['x'] * $scale, 'y' => $item['y'] * $scale) + $item, $item['w'] * $scale, $item['h'] * $scale,
                array($origin[0] * $scale, $origin[1] * $scale), false, 1, 0);
            $draw->setFont(PG_FUNCTIONS_DIR . '/assets/fonts/bootstrap-icons/fonts/bootstrap-icons.woff');
            $draw->setFontSize($size * $scale);
            $draw->setTextAntialias(true);
            $draw->setStrokeColor(new ImagickPixel('none'));
            $draw->setFillColor(pg_image_draw_pixel($item['color']));
            $draw->annotation(($item['w'] / 2 - $size / 2) * $scale, ($item['h'] / 2 + $size / 2) * $scale, html_entity_decode('&#' . $item['code'] . ';', ENT_NOQUOTES, 'UTF-8'));

            return array($draw);
        }, true);
    } else {
        $radius = ($kind === 'rect') ? $item['radius'] : 0;
        $stroke = $item['stroke'];

        // Fill and line are laid on one after the other, each at the
        // layer's opacity.
        if ($item['fill'] !== '') {
            $points = pg_image_draw_shape_points($kind, $item['w'], $item['h'], $radius, 0.25);

            if (count($points) > 2) {
                $draw = pg_image_draw_frame($item, $item['w'], $item['h'], array(0, 0), $item['mirror']);
                $draw->setStrokeColor(new ImagickPixel('none'));
                $draw->setFillColor(pg_image_draw_pixel($item['color'], $item['opacity'] * (($item['fill'] === 'soft') ? 0.28 : 1)));
                $draw->polygon($polygon($points));
                $canvas->drawImage($draw);
            }
        }

        if ($stroke > 0) {
            pg_image_draw_group($canvas, $item, $stroke, $item['opacity'], function ($origin, $scale) use ($item, $kind, $radius, $stroke, $polygon) {
                $points = pg_image_draw_shape_points($kind, $item['w'], $item['h'], $radius);
                $draw = pg_image_draw_frame($item, $item['w'], $item['h'], $origin, $item['mirror'], $scale);

                if ($item['dash'] === 'dotted') {
                    // ImageMagick draws a dash this short without its caps.
                    $draw->setStrokeColor(new ImagickPixel('none'));
                    $draw->setFillColor(pg_image_draw_pixel($item['color']));

                    foreach (pg_image_draw_dots($points, 0.01 + round($stroke * 2, 2)) as $dot) {
                        $draw->circle($dot[0], $dot[1], $dot[0] + max(0.1, $stroke / 2 - 0.25), $dot[1]);
                    }

                    return array($draw);
                }

                $draw->setFillColor(new ImagickPixel('none'));
                $draw->setStrokeColor(pg_image_draw_pixel($item['color']));
                $draw->setStrokeWidth(max($stroke - 0.5, $stroke * 0.75));
                $draw->setStrokeLineCap(Imagick::LINECAP_ROUND);
                $draw->setStrokeLineJoin(Imagick::LINEJOIN_ROUND);

                if ($item['dash'] === 'dashed') {
                    $draw->setStrokeDashArray(array(round($stroke * 2.4, 2), round($stroke * 1.9, 2)));
                }

                if ($kind === 'line') {
                    $draw->line($points[0][0], $points[0][1], $points[1][0], $points[1][1]);
                } elseif (count($points) > 2) {
                    $draw->polyline($polygon($points));
                }

                return array($draw);
            });
        }
    }
}

/**
 * A plan drawn: the artboard's fill, then each item in turn, as a PNG.
 *
 * @param array $plan  pg_image_draw_plan(), without an error
 * @param float $until the time the budget ends, 0 for none
 * @return string the PNG's bytes
 */
function pg_image_draw_paint($plan, $until = 0.0)
{
    $canvas = new Imagick();
    $canvas->newImage($plan['width'], $plan['height'], new ImagickPixel(($plan['background'] === '') ? 'transparent' : $plan['background']));

    foreach ($plan['items'] as $item) {
        if (($until > 0) && (microtime(true) > $until)) {
            throw new RuntimeException('The drawing took too long.');
        }

        pg_image_draw_item($canvas, $item);
    }

    $canvas->setImageFormat('png');
    $canvas->stripImage();
    $png = $canvas->getImageBlob();
    $canvas->clear();

    return $png;
}

/**
 * A canvas document as a PNG of its artboard, when this server draws all
 * of it as the editor would.
 *
 * @param mixed         $doc     arrays or objects
 * @param callable|null $picture see pg_image_draw_plan()
 * @return string the PNG's bytes, '' when the document waits for a browser
 */
function pg_image_draw_document($doc, $picture = null)
{
    $can = pg_image_draw_can();

    if (empty($can['shapes'])) {
        return '';
    }

    $until = microtime(true) + pg_image_draw_limits()['seconds'];
    $plan = pg_image_draw_plan($doc, $can, $picture, $until);

    if ($plan['error'] !== '') {
        return '';
    }

    $png = pg_image_draw_guarded(function () use ($plan, $until) {
        return pg_image_draw_paint($plan, $until);
    });
    $size = is_string($png) ? @getimagesizefromstring($png) : false;

    return ($size && ($size[0] === $plan['width']) && ($size[1] === $plan['height']) && ($size[2] === IMAGETYPE_PNG)) ? $png : '';
}

/**
 * The fixture set the probe draws, each a document of 100 x 60 pixels on a
 * transparent artboard with the pixels it is looked at by (x, y, red,
 * green, blue, opacity as 0 to 255): filled shapes, a letter of a site
 * font, an icon, a picture and SVG with a gradient, a clip path, a style
 * rule, a use, a marker and group opacity. Pure.
 *
 * @return array name => doc, checks
 */
function pg_image_draw_fixtures()
{
    $board = array('width' => 100, 'height' => 60, 'background' => 'transparent');
    $clear = array(0, 0, 0, 0);
    $defs = '<defs><linearGradient id="pgd-a" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#ff0000"/><stop offset="1" stop-color="#0000ff"/></linearGradient>'
        . '<clipPath id="pgd-c"><rect x="60" y="0" width="10" height="20"/></clipPath>'
        . '<marker id="pgd-d" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="10" markerHeight="10" markerUnits="userSpaceOnUse"><rect width="10" height="10" fill="#ff00ff"/></marker>'
        . '<rect id="pgd-e" width="10" height="10" fill="#00ffff"/><style>.pgd-f{fill:#00ff00}</style></defs>';

    return array(
        'shapes' => array(
            'doc'    => array('artboard' => $board, 'layers' => array(
                array('type' => 'rect', 'x' => 10, 'y' => 10, 'w' => 30, 'h' => 20, 'color' => '#ff0000', 'fill' => 'solid', 'stroke' => 0),
                array('type' => 'ellipse', 'x' => 60, 'y' => 10, 'w' => 30, 'h' => 30, 'color' => '#0000ff', 'fill' => 'solid', 'stroke' => 0, 'opacity' => 0.5),
                array('type' => 'line', 'x' => 10, 'y' => 45, 'w' => 80, 'h' => 6, 'color' => '#000000', 'stroke' => 6, 'dash' => 'solid'))),
            'checks' => array(array(11, 11, 255, 0, 0, 255), array(38, 28, 255, 0, 0, 255), array_merge(array(9, 9), $clear), array_merge(array(41, 20), $clear),
                array(75, 25, 0, 0, 255, 128), array_merge(array(61, 11), $clear), array(50, 46, 0, 0, 0, 255), array(50, 50, 0, 0, 0, 255), array_merge(array(50, 43), $clear), array_merge(array(50, 53), $clear)),
        ),
        'text' => array(
            // An "l" of the bundled Roboto at 80 px: a stem from the
            // baseline (y 57) up.
            'doc'    => array('artboard' => $board, 'layers' => array(
                array('type' => 'text', 'text' => 'l', 'font' => 'g:Roboto', 'weight' => 400, 'size' => 80, 'lineHeight' => 0.75, 'color' => '#000000', 'x' => 40, 'y' => 0, 'w' => 1, 'h' => 1))),
            'checks' => array(array(49, 30, 0, 0, 0, 255), array(49, 3, 0, 0, 0, 255), array(49, 55, 0, 0, 0, 255), array_merge(array(44, 30), $clear), array_merge(array(55, 30), $clear),
                array_merge(array(49, 58), $clear), array_merge(array(65, 10), $clear)),
        ),
        'icons' => array(
            // The filled circle of Bootstrap Icons in a 40 pixel box.
            'doc'    => array('artboard' => $board, 'layers' => array(
                array('type' => 'glyph', 'set' => 'bi', 'code' => 62087, 'color' => '#00ff00', 'x' => 30, 'y' => 10, 'w' => 40, 'h' => 40))),
            'checks' => array(array(50, 30, 0, 255, 0, 255), array(50, 12, 0, 255, 0, 255), array(50, 47, 0, 255, 0, 255), array(32, 30, 0, 255, 0, 255),
                array(67, 30, 0, 255, 0, 255), array_merge(array(31, 11), $clear), array_merge(array(50, 8), $clear), array_merge(array(72, 30), $clear)),
        ),
        'picture' => array(
            // A picture of four colours, 2 x 2, drawn into a 40 pixel box.
            'doc'    => array('artboard' => $board, 'layers' => array(
                array('type' => 'image', 'src' => 'fixture', 'x' => 30, 'y' => 10, 'w' => 40, 'h' => 40))),
            'checks' => array(array(38, 18, 255, 0, 0, 255), array(62, 18, 0, 255, 0, 255), array(38, 42, 0, 0, 255, 255), array(62, 42, 255, 255, 255, 255),
                array_merge(array(29, 30), $clear), array_merge(array(70, 30), $clear), array_merge(array(50, 9), $clear), array_merge(array(50, 50), $clear)),
        ),
        'svg' => array(
            'doc'    => array('artboard' => $board, 'svgDefs' => $defs, 'svgRootId' => 'pgd', 'layers' => array(
                array('type' => 'svg', 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 60, 'vb' => array('x' => 0, 'y' => 0, 'w' => 100, 'h' => 60), 'markup' => '<g>'
                    . '<rect x="0" y="0" width="40" height="20" fill="url(#pgd-a)"/>'
                    . '<rect x="50" y="0" width="50" height="20" fill="#000000" clip-path="url(#pgd-c)"/><rect class="pgd-f" x="0" y="20" width="20" height="20"/>'
                    . '<use xlink:href="#pgd-e" x="25" y="25"/><path d="M45 30H55" stroke="#000000" stroke-width="2" marker-end="url(#pgd-d)"/>'
                    . '<g opacity="0.5"><rect x="70" y="20" width="20" height="20" fill="#ff0000"/></g></g>'),
                // Stretched, turned half round and clipped to its own box.
                array('type' => 'svg', 'x' => 10, 'y' => 44, 'w' => 40, 'h' => 12, 'rot' => 180, 'vb' => array('x' => 0, 'y' => 0, 'w' => 10, 'h' => 10),
                    'markup' => '<g><rect x="0" y="0" width="5" height="10" fill="#ffff00"/><circle cx="14" cy="5" r="4" fill="#000000"/></g>'))),
            'checks' => array(array(4, 10, 226, 0, 29, 255), array(36, 10, 22, 0, 233, 255), array(65, 10, 0, 0, 0, 255), array_merge(array(55, 10), $clear), array_merge(array(75, 10), $clear),
                array(10, 30, 0, 255, 0, 255), array(30, 30, 0, 255, 255, 255), array(47, 30, 0, 0, 0, 255), array(57, 30, 255, 0, 255, 255), array(80, 30, 255, 0, 0, 128),
                array(40, 50, 255, 255, 0, 255), array_merge(array(20, 50), $clear), array_merge(array(5, 50), $clear), array_merge(array(95, 55), $clear)),
        ),
    );
}

/**
 * What this server's Imagick draws right: each fixture
 * (pg_image_draw_fixtures()) drawn and looked at; a part that throws or
 * comes out another way is one the server leaves to browsers. Nothing is
 * drawn when the shapes come out wrong.
 *
 * @return array shapes, text, icons, svg, pictures, engine
 */
function pg_image_draw_probe()
{
    $version = Imagick::getVersion();
    $can = array('shapes' => false, 'text' => false, 'icons' => false, 'svg' => false, 'pictures' => array(),
        'engine' => trim((string) preg_replace('/\s+https?:\S+$/', '', (string) $version['versionString'])));
    $all = array('shapes' => true, 'text' => true, 'icons' => true, 'svg' => true, 'pictures' => array('png'));
    $fixtures = pg_image_draw_fixtures();
    $passes = function ($fixture, $picture = null) use ($all) {
        try {
            $plan = pg_image_draw_plan($fixture['doc'], $all, $picture);

            if ($plan['error'] !== '') {
                return false;
            }

            $image = new Imagick();
            $image->setFormat('png');
            $image->readImageBlob(pg_image_draw_paint($plan));

            foreach ($fixture['checks'] as $check) {
                $color = $image->getImagePixelColor($check[0], $check[1])->getColor(1);
                $got = array($color['r'] * 255, $color['g'] * 255, $color['b'] * 255, $color['a'] * 255);

                // A clear pixel has no colour to look at.
                foreach (($check[5] > 0) ? array(0, 1, 2, 3) : array(3) as $i) {
                    if (abs($got[$i] - $check[2 + $i]) > 24) {
                        return false;
                    }
                }
            }

            return true;
        } catch (Throwable $error) {
            return false;
        }
    };

    $can['shapes'] = $passes($fixtures['shapes']);

    if (!$can['shapes']) {
        return $can;
    }

    $can['text'] = $passes($fixtures['text']);
    $can['icons'] = $passes($fixtures['icons']);
    $can['svg'] = in_array('RSVG', Imagick::queryFormats('RSVG'), true) && $passes($fixtures['svg']);

    // The picture is one this server writes and reads back.
    $path = PG_FUNCTIONS_DIR . '/data/temp/image_draw_fixture_' . bin2hex(random_bytes(6)) . '.png';

    try {
        $picture = new Imagick();
        $picture->newImage(2, 2, new ImagickPixel('#ffffff'));
        $picture->setImageFormat('png');
        $draw = new ImagickDraw();
        $draw->setFillColor(new ImagickPixel('#ff0000'));
        $draw->point(0, 0);
        $draw->setFillColor(new ImagickPixel('#00ff00'));
        $draw->point(1, 0);
        $draw->setFillColor(new ImagickPixel('#0000ff'));
        $draw->point(0, 1);
        $picture->drawImage($draw);

        if ($picture->writeImage($path) && $passes($fixtures['picture'], function () use ($path) {
            return $path;
        })) {
            $can['pictures'] = array_values(array_intersect(array('png', 'jpeg', 'webp'), array_map('strtolower', Imagick::queryFormats())));
        }
    } catch (Throwable $error) {
        $can['pictures'] = array();
    }

    @unlink($path);

    return $can;
}
