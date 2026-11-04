<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's APNG and animated WebP writers
 * (pg_image_editor_encode.js, run under Node): APNG read back by GD and
 * parsed chunk by chunk, animated WebP muxed from still WebP frames GD
 * writes, the shared message session and the dialog's formats in the model.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once __DIR__ . '/image_editor_render_test.php';

// Runs $script (which may answer through out(value), after promises) with
// the encoder as E, the model as M and frame(w, h, n, alpha); null without
// Node.
function image_editor_encode_node($script)
{
	return image_editor_render_node(
		'function out(v) { console.log(JSON.stringify(v)); }' . "\n"
		. 'function b64(u) { return Buffer.from(u).toString("base64"); }' . "\n"
		. 'function run(enc, frames) { var p = Promise.resolve(); frames.forEach(function (f) { p = p.then(function () { return enc.frame(f); }); }); return p.then(function () { return enc.end(); }); }' . "\n"
		. $script . "\n"
		. ';(typeof main === "function" ? main() : Promise.resolve()).then(null, function (e) { console.log(JSON.stringify({ error: String(e && e.stack || e) })); });'
	);
}

// The chunks of a PNG as list(type, data), with each CRC checked.
function image_editor_encode_png_chunks($png)
{
	pg_assert_same("\x89PNG\r\n\x1A\n", substr($png, 0, 8), 'PNG signature');

	$chunks = array();
	$at = 8;

	while ($at + 12 <= strlen($png)) {
		$n = unpack('N', substr($png, $at, 4));
		$type = substr($png, $at + 4, 4);
		$data = substr($png, $at + 8, $n[1]);
		$crc = unpack('N', substr($png, $at + 8 + $n[1], 4));
		pg_assert_same(crc32($type . $data), $crc[1], $type . ' CRC');
		$chunks[] = array($type, $data);
		$at += 12 + $n[1];
	}

	pg_assert_same(strlen($png), $at, 'chunks fill the file');

	return $chunks;
}

// An APNG of four frames, the third the same as the second: GD opens its
// default image; acTL, fcTL and fdAT follow the specification.
function test_image_editor_encode_apng()
{
	$r = image_editor_encode_node(
		'function main() {' . "\n"
		. ' var enc = E.create({ format: "apng", width: 20, height: 12, fps: 10, loop: 3 }), f = [frame(20, 12, 0), frame(20, 12, 1), frame(20, 12, 1), frame(20, 12, 1)];' . "\n"
		. ' f[3][4 * (5 * 20 + 7)] ^= 1;' . "\n"
		. ' f.forEach(function (a) { enc.sample(a); });' . "\n"
		. ' return Promise.resolve(enc.estimate()).then(function (e) { return run(enc, f).then(function (bytes) { out({ png: b64(bytes), mime: enc.mime, est: e }); }); });' . "\n"
		. '}'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('image/png', $r['mime'], 'mime');
	pg_assert_true($r['est']['header'] > 50 && $r['est']['frame'] > 50, 'estimate');

	$png = base64_decode($r['png']);
	$chunks = image_editor_encode_png_chunks($png);
	$types = array_map(function ($c) { return $c[0]; }, $chunks);

	pg_assert_same(array('IHDR', 'acTL', 'fcTL', 'IDAT', 'fcTL', 'fdAT', 'fcTL', 'fdAT', 'IEND'), $types, 'chunk order; the repeated frame is merged');

	$ihdr = unpack('Nw/Nh/Cdepth/Ctype', $chunks[0][1]);
	pg_assert_same(array(20, 12, 8, 2), array($ihdr['w'], $ihdr['h'], $ihdr['depth'], $ihdr['type']), 'RGB, 8 bits');

	$actl = unpack('Nframes/Nplays', $chunks[1][1]);
	pg_assert_same(array(3, 3), array($actl['frames'], $actl['plays']), 'three frames, three plays');

	$sequence = array();
	$fctl = array();

	foreach ($chunks as $c) {
		if (($c[0] === 'fcTL') || ($c[0] === 'fdAT')) {
			$s = unpack('N', substr($c[1], 0, 4));
			$sequence[] = $s[1];
		}

		if ($c[0] === 'fcTL') {
			$fctl[] = unpack('Nseq/Nw/Nh/Nx/Ny/nnum/nden/Cdispose/Cblend', $c[1]);
		}
	}

	pg_assert_same(range(0, 4), $sequence, 'sequence numbers');
	pg_assert_same(array(20, 12, 0, 0, 1, 10), array($fctl[0]['w'], $fctl[0]['h'], $fctl[0]['x'], $fctl[0]['y'], $fctl[0]['num'], $fctl[0]['den']), 'first frame whole');
	pg_assert_same(array(2, 10), array($fctl[1]['num'], $fctl[1]['den']), 'the repeated frame lengthens the one before');
	pg_assert_same(array(1, 1, 7, 5, 0, 0), array($fctl[2]['w'], $fctl[2]['h'], $fctl[2]['x'], $fctl[2]['y'], $fctl[2]['dispose'], $fctl[2]['blend']), 'only the changed pixel, replaced');

	$file = tempnam(sys_get_temp_dir(), 'pgieapng');
	file_put_contents($file, $png);
	pg_assert_true(pg_image_is_animated_png($file), 'animated');
	@unlink($file);

	$image = imagecreatefromstring($png);
	pg_assert_true($image !== false, 'GD decodes the default image');
	$colour = imagecolorsforindex($image, imagecolorat($image, 10, 5));
	pg_assert_same(array(80, 40, 0), array($colour['red'], $colour['green'], $colour['blue']), 'first frame lossless');
}

// See-through frames keep their alpha; one play is 1 in acTL.
function test_image_editor_encode_apng_alpha()
{
	$r = image_editor_encode_node(
		'function main() {' . "\n"
		. ' var enc = E.create({ format: "apng", width: 9, height: 5, fps: 25, loop: 1 });' . "\n"
		. ' enc.sample(frame(9, 5, 0, true));' . "\n"
		. ' return run(enc, [frame(9, 5, 0, true), frame(9, 5, 1, true)]).then(function (bytes) { out({ png: b64(bytes) }); });' . "\n"
		. '}'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');

	$png = base64_decode($r['png']);
	$chunks = image_editor_encode_png_chunks($png);
	$ihdr = unpack('Nw/Nh/Cdepth/Ctype', $chunks[0][1]);
	pg_assert_same(6, $ihdr['type'], 'RGBA');
	$actl = unpack('Nframes/Nplays', $chunks[1][1]);
	pg_assert_same(array(2, 1), array($actl['frames'], $actl['plays']), 'plays once');

	$image = imagecreatefromstring($png);
	$colour = imagecolorsforindex($image, imagecolorat($image, 1, 1));
	pg_assert_same(127, $colour['alpha'], 'see-through pixel');
}

// Still WebP frames from GD become one animated WebP: VP8X with the
// animation flag, ANIM with the plays, an ANMF per changed frame at an even
// offset with its duration in milliseconds.
function test_image_editor_encode_webp()
{
	if (!function_exists('imagewebp')) {
		return;
	}

	$stills = array();

	foreach (array(array(200, 30, 30), array(30, 200, 30), array(30, 30, 200)) as $rgb) {
		$image = imagecreatetruecolor(16, 16);
		imagefill($image, 0, 0, imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]));
		ob_start();
		imagewebp($image, null, 90);
		$stills[] = base64_encode(ob_get_clean());
	}

	$r = image_editor_encode_node(
		'var stills = ' . json_encode($stills) . ', calls = [];' . "\n"
		. 'function main() {' . "\n"
		. ' var k = 0, enc = E.create({ format: "webp", width: 30, height: 20, fps: 15, loop: 0, webp: function (rgba, w, h) { calls.push([w, h, rgba.length]); return Promise.resolve(new Uint8Array(Buffer.from(stills[k++ % 3], "base64"))); } });' . "\n"
		. ' var a = frame(30, 20, 0), b = frame(30, 20, 0), c = frame(30, 20, 0); b[4 * (7 * 30 + 9)] ^= 1; b[4 * (8 * 30 + 12)] ^= 1;' . "\n"
		. ' return run(enc, [a, b, b, c]).then(function (bytes) { out({ webp: b64(bytes), calls: calls, mime: enc.mime }); });' . "\n"
		. '}'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('image/webp', $r['mime'], 'mime');
	pg_assert_same(array(array(30, 20, 2400), array(5, 3, 60), array(5, 3, 60)), $r['calls'], 'whole, then even-aligned changed rectangles');

	$webp = base64_decode($r['webp']);
	pg_assert_same('RIFF', substr($webp, 0, 4), 'RIFF');
	pg_assert_same('WEBP', substr($webp, 8, 4), 'WEBP');
	$size = unpack('V', substr($webp, 4, 4));
	pg_assert_same(strlen($webp) - 8, $size[1], 'RIFF size');

	$chunks = array();
	$at = 12;

	while ($at + 8 <= strlen($webp)) {
		$n = unpack('V', substr($webp, $at + 4, 4));
		$chunks[] = array(substr($webp, $at, 4), substr($webp, $at + 8, $n[1]));
		$at += 8 + $n[1] + ($n[1] & 1);
	}

	pg_assert_same(strlen($webp), $at, 'chunks fill the file');
	pg_assert_same(array('VP8X', 'ANIM', 'ANMF', 'ANMF', 'ANMF'), array_map(function ($c) { return $c[0]; }, $chunks), 'chunk order');

	$x = $chunks[0][1];
	pg_assert_same(0x02, ord($x[0]) & 0x02, 'animation flag');
	pg_assert_same(array(29, 19), array(ord($x[4]) | (ord($x[5]) << 8) | (ord($x[6]) << 16), ord($x[7]) | (ord($x[8]) << 8) | (ord($x[9]) << 16)), 'canvas size minus one');

	$anim = unpack('Vbg/vloop', $chunks[1][1]);
	pg_assert_same(0, $anim['loop'], 'forever');

	$frames = array();

	foreach (array_slice($chunks, 2) as $c) {
		$d = $c[1];
		$u24 = function ($o) use ($d) { return ord($d[$o]) | (ord($d[$o + 1]) << 8) | (ord($d[$o + 2]) << 16); };
		$frames[] = array(2 * $u24(0), 2 * $u24(3), $u24(6) + 1, $u24(9) + 1, $u24(12), ord($d[15]) & 3, substr($d, 16, 4));
	}

	pg_assert_same(array(0, 0, 30, 20, 67, 2), array_slice($frames[0], 0, 6), 'first frame whole, not blended');
	pg_assert_same(array(8, 6, 5, 3, 133, 2), array_slice($frames[1], 0, 6), 'the changed rectangle, the repeat merged into its duration');
	pg_assert_same(array(8, 6, 5, 3, 67), array_slice($frames[2], 0, 5), 'back to the first');
	pg_assert_true(in_array($frames[0][6], array('VP8 ', 'VP8L', 'ALPH'), true), 'frame data without its VP8X');

	$file = tempnam(sys_get_temp_dir(), 'pgiewebp');
	file_put_contents($file, $webp);
	pg_assert_true(pg_image_is_animated_webp($file), 'animated');
	@unlink($file);
}

// The session answers in order whatever the encoder's pace, and only one
// error.
function test_image_editor_encode_session()
{
	$r = image_editor_encode_node(
		'function main() {' . "\n"
		. ' var got = [], done; var p = new Promise(function (r) { done = r; });' . "\n"
		. ' var s = E.session(function (m) { got.push(m.type + (m.index !== undefined ? m.index : "") + (m.mime ? ":" + m.mime : "")); if (m.type === "file") { done(); } });' . "\n"
		. ' s.receive({ type: "begin", format: "apng", width: 4, height: 4, fps: 10, loop: 0 });' . "\n"
		. ' s.receive({ type: "sample", data: frame(4, 4, 0).buffer });' . "\n"
		. ' s.receive({ type: "frame", data: frame(4, 4, 0).buffer });' . "\n"
		. ' s.receive({ type: "frame", data: frame(4, 4, 1).buffer });' . "\n"
		. ' s.receive({ type: "end" });' . "\n"
		. ' return p.then(function () { var errs = [], bad = E.session(function (m) { errs.push(m.type); }); bad.receive({ type: "frame", data: frame(4, 4, 0).buffer }); bad.receive({ type: "end" }); return new Promise(function (r) { setTimeout(r, 20); }).then(function () { out({ got: got, errs: errs }); }); });' . "\n"
		. '}'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('framed0', 'framed1', 'file:image/png'), $r['got'], 'in order');
	pg_assert_same(array('error'), $r['errs'], 'one error');
}

// The dialog offers a format where the browser can make it; switching
// keeps the size and rate inside the new format's limits.
function test_image_editor_encode_formats()
{
	$r = image_editor_encode_node(
		'out({' . "\n"
		. ' none: M.animFormats({}), deflate: M.animFormats({ deflate: true }), all: M.animFormats({ deflate: true, webp: true }),' . "\n"
		. ' edges: M.animEdges({ w: 2400, h: 1600 }, "webp"),' . "\n"
		. ' toGif: M.animSwitch({ format: "apng", edge: 1080, fps: 15, loop: 0, dither: true }, "gif"),' . "\n"
		. ' toWebp: M.animSwitch({ format: "gif", edge: 800, fps: 25, loop: 2, dither: true }, "webp"),' . "\n"
		. ' dither: [M.ANIM_FORMATS.gif.dither, !!M.ANIM_FORMATS.apng.dither, !!M.ANIM_FORMATS.webp.dither],' . "\n"
		. ' names: [M.animName("a.jpg", M.ANIM_FORMATS.apng.ext), M.animName("a.jpg", M.ANIM_FORMATS.webp.ext)]' . "\n"
		. '});'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('gif'), $r['none'], 'GIF everywhere');
	pg_assert_same(array('gif', 'apng'), $r['deflate'], 'APNG with a deflate stream');
	pg_assert_same(array('gif', 'apng', 'webp'), $r['all'], 'WebP where the browser encodes it');
	pg_assert_same(array(240, 320, 480, 640, 800, 1080), $r['edges'], 'up to 1080');
	pg_assert_same(array('format' => 'gif', 'edge' => 800, 'fps' => 15, 'loop' => 0, 'dither' => true), $r['toGif'], 'edge brought under the GIF limit');
	pg_assert_same(array('format' => 'webp', 'edge' => 800, 'fps' => 25, 'loop' => 2, 'dither' => true), $r['toWebp'], 'kept');
	pg_assert_same(array(true, false, false), $r['dither'], 'dithering is GIF only');
	pg_assert_same(array('a.png', 'a.webp'), $r['names'], 'extensions');
}
