<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Design Canvas's Follow tracker (assets/js/pg_image_editor_track.js,
 * run under Node): frames drawn here (a textured square on a textured
 * ground, with noise and a slow change of brightness) moving straight and
 * on a curve, the centre found within 1.5 px; the square covered, gone or
 * leaving the frame: lost at that frame; the sub-pixel peak; the message
 * session in a worker and on the page in slices; the bundle and address;
 * the FOLLOW section of the model: the clip under the box, source pixels
 * to document units for a turned and a mirrored clip, the span, the
 * frames' times, and the samples made into keys.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the tracker as T and the frame drawer and answers its
// JSON; null without Node.
function image_editor_follow_node($script)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgiefollow');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var T = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_track.js') . ');' . "\n"
		. 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'var r2 = function (v) { return Math.round(v * 100) / 100; };' . "\n"
		// A seeded random number in [0, 1).
		. 'var seed = 11; var rnd = function () { seed = (seed * 1103515245 + 12345) & 0x7fffffff; return seed / 0x7fffffff; };' . "\n"
		// A smooth texture: a sum of n waves with frequencies in [f0, f1)
		// cycles a pixel, about -amp..amp.
		. 'var texture = function (n, f0, f1, amp) { var w = [], k; for (k = 0; k < n; k++) { var a = rnd() * Math.PI * 2, f = (f0 + rnd() * (f1 - f0)) * Math.PI * 2; w.push([Math.cos(a) * f, Math.sin(a) * f, rnd() * Math.PI * 2]); }'
		. ' return function (x, y) { var v = 0; for (var i = 0; i < n; i++) { v += Math.sin(w[i][0] * x + w[i][1] * y + w[i][2]); } return v * amp / Math.sqrt(n); }; };' . "\n"
		. 'var ground = texture(8, 0.02, 0.08, 40), skin = texture(8, 0.08, 0.2, 55);' . "\n"
		// A w x h grey frame with the size-px square's top left at sx, sy
		// (null: no square), brightness times gain, noise up to +-noise / 2,
		// and an optional flat cover [x0, y0, x1, y1].
		. 'var frame = function (w, h, sx, sy, size, gain, noise, cover) { var f = new Uint8Array(w * h), x, y;'
		. ' for (y = 0; y < h; y++) { for (x = 0; x < w; x++) { var px = x + 0.5, py = y + 0.5, u = px - sx, v = py - sy, g;'
		. ' if (cover && px >= cover[0] && px < cover[2] && py >= cover[1] && py < cover[3]) { g = 120; }'
		. ' else if (sx !== null && u >= 0 && u < size && v >= 0 && v < size) { g = 130 + skin(u, v); } else { g = 110 + ground(px, py); }'
		. ' f[y * w + x] = Math.max(0, Math.min(255, Math.round(g * gain + (rnd() - 0.5) * noise))); } } return f; };' . "\n"
		// Tracks the frames from box b and answers the largest miss from
		// the truth (centres) and the result.
		. 'var run = function (w, h, frames, truth, size) { var b = [truth[0][0] - size / 2, truth[0][1] - size / 2, truth[0][0] + size / 2, truth[0][1] + size / 2];'
		. ' var r = T.track(frames, w, h, b), miss = 0; r.points.forEach(function (p) { miss = Math.max(miss, Math.hypot(p.x - truth[p.i][0], p.y - truth[p.i][1])); });'
		. ' return { n: r.points.length, last: r.points.length ? r.points[r.points.length - 1].i : -1, miss: Math.round(miss * 100) / 100, lost: r.lost }; };' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// F6, F7: a square moving straight at a fractional speed while the light
// falls by a quarter, and on a curve while it rises: every frame found,
// each centre within 1.5 px.
function test_image_editor_follow_moves()
{
	$r = image_editor_follow_node(
		'var w = 200, h = 150, s = 32, out = {}, frames = [], truth = [], i, x, y;' . "\n"
		. 'for (i = 0; i < 50; i++) { x = 30 + 2.3 * i; y = 40 + 1.1 * i; frames.push(frame(w, h, x, y, s, 1 - 0.25 * i / 49, 8)); truth.push([x + s / 2, y + s / 2]); }' . "\n"
		. 'out.straight = run(w, h, frames, truth, s);' . "\n"
		. 'frames = []; truth = [];' . "\n"
		. 'for (i = 0; i < 60; i++) { var a = i * 0.06; x = 100 + 40 * Math.cos(a) - s / 2; y = 75 + 35 * Math.sin(a) - s / 2; frames.push(frame(w, h, x, y, s, 1 + 0.2 * i / 59, 8)); truth.push([x + s / 2, y + s / 2]); }' . "\n"
		. 'out.curve = run(w, h, frames, truth, s);' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');

	foreach (array('straight' => 50, 'curve' => 60) as $key => $n) {
		pg_assert_same(null, $r[$key]['lost'], $key . ': not lost');
		pg_assert_same($n, $r[$key]['n'], $key . ': every frame found');
		pg_assert_true($r[$key]['miss'] <= 1.5, $key . ': within 1.5 px, the largest miss ' . $r[$key]['miss']);
	}
}

// F8: the square covered from frame 20, gone from frame 25, leaving the
// frame on the right: lost at that frame, the points stop at the frame
// before and are right up to there.
function test_image_editor_follow_lost()
{
	$r = image_editor_follow_node(
		'var w = 200, h = 150, s = 32, out = {}, frames = [], truth = [], i, x, y;' . "\n"
		. 'for (i = 0; i < 40; i++) { x = 30 + 2 * i; y = 50 + i; frames.push(frame(w, h, x, y, s, 1, 8, i >= 20 ? [x - 6, y - 6, x + s + 6, y + s + 6] : null)); truth.push([x + s / 2, y + s / 2]); }' . "\n"
		. 'out.covered = run(w, h, frames, truth, s);' . "\n"
		. 'frames = []; truth = [];' . "\n"
		. 'for (i = 0; i < 40; i++) { x = 30 + 2 * i; y = 50 + i; frames.push(frame(w, h, i >= 25 ? null : x, y, s, 1, 8)); truth.push([x + s / 2, y + s / 2]); }' . "\n"
		. 'out.gone = run(w, h, frames, truth, s);' . "\n"
		. 'frames = []; truth = [];' . "\n"
		// The centre passes the right edge (200) at frame 17.
		. 'for (i = 0; i < 30; i++) { x = 120 + 4 * i; y = 60; frames.push(frame(w, h, x, y, s, 1, 8)); truth.push([x + s / 2, y + s / 2]); }' . "\n"
		. 'out.leaves = run(w, h, frames, truth, s);' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');

	foreach (array('covered' => 20, 'gone' => 25) as $key => $at) {
		pg_assert_same($at, $r[$key]['lost']['at'], $key . ': lost at the frame it happened');
		pg_assert_same($at - 1, $r[$key]['lost']['last'], $key . ': the last good frame');
		pg_assert_same($at - 1, $r[$key]['last'], $key . ': no point after it');
		pg_assert_true($r[$key]['miss'] <= 1.5, $key . ': right up to there, the largest miss ' . $r[$key]['miss']);
	}

	pg_assert_true(is_array($r['leaves']['lost']) && ($r['leaves']['lost']['at'] <= 17) && ($r['leaves']['lost']['at'] >= 10), 'leaves: lost by the frame its centre is out: ' . json_encode($r['leaves']['lost']));
	pg_assert_true($r['leaves']['miss'] <= 1.5, 'leaves: no wrong point at the edge, the largest miss ' . $r['leaves']['miss']);
}

// F6: the score of a patch against itself, against its inverse and a flat
// one; the parabola's peak; a flat box has nothing to follow.
function test_image_editor_follow_maths()
{
	$r = image_editor_follow_node(
		'var w = 60, h = 40, f = frame(w, h, 10, 10, 20, 1, 0), inv = new Uint8Array(f.length), i;' . "\n"
		. 'for (i = 0; i < f.length; i++) { inv[i] = 255 - f[i]; }' . "\n"
		. 'var t = new T.Tracker(f, w, h, [10, 10, 30, 30]), flat = null;' . "\n"
		. 'try { new T.Tracker(new Uint8Array(w * h).fill(90), w, h, [10, 10, 30, 30]); } catch (e) { flat = e.code || e.message; }' . "\n"
		. 'console.log(JSON.stringify({ self: Math.round(t.score(f, 10, 10) * 1000) / 1000, inverse: Math.round(t.score(inv, 10, 10) * 1000) / 1000,'
		. ' flat: t.score(new Uint8Array(w * h).fill(90), 10, 10), peak: T.parabola(0.8, 1, 0.6), edge: T.parabola(1, 1, 1), flatBox: flat,'
		. ' box: t.box(), radius: t.radius }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(1, $r['self'], 'a patch matches itself fully');
	pg_assert_same(-1, $r['inverse'], 'its inverse scores -1');
	pg_assert_same(0, $r['flat'], 'a flat window scores 0');
	pg_assert_true(abs($r['peak'] + 1 / 6) < 1e-9, 'the parabola leans to the higher neighbour: ' . $r['peak']);
	pg_assert_same(0, $r['edge'], 'a flat top stays on the pixel');
	pg_assert_same('flat', $r['flatBox'], 'a flat box has nothing to follow');
	pg_assert_same(array(10, 10, 30, 30), $r['box'], 'the box in whole pixels');
	pg_assert_same(16, $r['radius'], 'the search reaches at least 16 px');
}

// F9: the message session (start, frames, lost) as the worker runs it,
// and on the page in slices: answers come after the call returns.
function test_image_editor_follow_session()
{
	$r = image_editor_follow_node(
		'var w = 120, h = 90, s = 24, frames = [], i;' . "\n"
		. 'for (i = 0; i < 14; i++) { frames.push(frame(w, h, i >= 8 ? null : 20 + 3 * i, 30, s, 1, 6)); }' . "\n"
		. 'var got = [], ses = T.session(function (m) { got.push(m); });' . "\n"
		. 'ses.receive({ type: "frame", frame: frames[1].buffer, t: 0.1 });' . "\n"
		. 'ses.receive({ type: "start", frame: frames[0].slice().buffer, width: w, height: h, box: [20, 30, 44, 54], t: 0 });' . "\n"
		. 'for (i = 1; i < 14; i++) { ses.receive({ type: "frame", frame: frames[i].slice().buffer, t: i / 10 }); }' . "\n"
		. 'ses.receive({ type: "start", frame: new ArrayBuffer(5), width: w, height: h, box: [20, 30, 44, 54] });' . "\n"
		. 'var types = got.map(function (m) { return m.type; }), page = [], ps = T.pageSession(function (m) { page.push(m.type); });' . "\n"
		. 'ps.receive({ type: "start", frame: frames[0].slice().buffer, width: w, height: h, box: [20, 30, 44, 54], t: 0 });' . "\n"
		. 'ps.receive({ type: "frame", frame: frames[1].slice().buffer, t: 0.1 });' . "\n"
		. 'var sync = page.length;' . "\n"
		. 'setTimeout(function () { setTimeout(function () { setTimeout(function () {'
		. ' var lost = got.filter(function (m) { return m.type === "lost"; })[0], pts = got.filter(function (m) { return m.type === "point"; });'
		. ' console.log(JSON.stringify({ first: types[0], ready: types[1], points: pts.length, lastT: pts[pts.length - 1].t, lost: lost ? [lost.t, lost.last] : null,'
		. ' after: types[types.length - 1], x: Math.round(pts[0].x * 10) / 10, sync: sync, page: page })); }, 0); }, 0); }, 0);'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('error', $r['first'], 'a frame before start answers an error');
	pg_assert_same('ready', $r['ready'], 'start answers ready');
	pg_assert_same(7, $r['points'], 'a point a good frame');
	pg_assert_same(0.7, $r['lastT'], 'the last point at the last good frame');
	pg_assert_same(array(0.8, 0.7), $r['lost'], 'lost at 0.8, the last good at 0.7');
	pg_assert_same('error', $r['after'], 'pixels that do not match the size answer an error');
	pg_assert_same(35.0, (float) $r['x'], 'the first point moved by 3 px');
	pg_assert_same(0, $r['sync'], 'on the page nothing is answered within the call');
	pg_assert_same(array('ready', 'point'), $r['page'], 'on the page the answers come in slices');
}

// F9: the tracker has its own bundle and the editor its address.
function test_image_editor_follow_wiring()
{
	$bundles = pg_js_bundles();

	pg_assert_same(array('assets/js/pg_image_editor_track.js'), $bundles['assets/js/pg_image_editor_track.min.js'], 'the tracker bundle');
	pg_assert_contains("'track'   => pg_js_bundle_urls('assets/js/pg_image_editor_track.min.js')", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/image_editor.php'), 'the tracker address in the config');
}

// F3, F10: the topmost visible clip showing under a point; a document point
// to the source's pixels and a source move back to document units, for a
// plain, a turned and a mirrored clip.
function test_image_editor_follow_mapping()
{
	$r = image_editor_follow_node(
		'var clip = { id: "v", type: "video", width: 640, height: 360, length: 10000, from: 0, to: 10000, start: 0, speed: 1, x: 100, y: 50, w: 320, h: 180, rot: 0 };' . "\n"
		. 'var turned = Object.assign({}, clip, { rot: 90 }), mirrored = Object.assign({}, clip, { mirror: true });' . "\n"
		. 'var top = Object.assign({}, clip, { id: "w", start: 5000 }), text = { id: "t", type: "text", x: 0, y: 0, w: 1000, h: 1000 };' . "\n"
		. 'var doc = { layers: [clip, top, text] }, hidden = { layers: [clip, Object.assign({}, top, { hidden: true })] };' . "\n"
		. 'var pt = function (a) { return [r2(a[0]), r2(a[1])]; };' . "\n"
		. 'console.log(JSON.stringify({'
		. ' at0: (M.followClip(doc, [260, 140], 0) || {}).id, at6: (M.followClip(doc, [260, 140], 6000) || {}).id,'
		. ' hidden: (M.followClip(hidden, [260, 140], 6000) || {}).id, outside: M.followClip(doc, [50, 140], 0), after: M.followClip(doc, [260, 140], 15000),'
		. ' plain: pt(M.followToSource(clip, [100, 50])), centre: pt(M.followToSource(clip, [260, 140])),'
		. ' turnedPt: pt(M.followToSource(turned, [350, 300])), mirroredPt: pt(M.followToSource(mirrored, [100, 50])),'
		. ' move: pt(M.followFromSource(clip, 20, 10)), turnedMove: pt(M.followFromSource(turned, 20, 10)), mirroredMove: pt(M.followFromSource(mirrored, 20, 10)),'
		. ' size: M.followSize(1920, 1080), small: M.followSize(200, 100) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('v', $r['at0'], 'the clip showing at 0 s');
	pg_assert_same('w', $r['at6'], 'the topmost clip showing at 6 s');
	pg_assert_same('v', $r['hidden'], 'a hidden clip is passed over');
	pg_assert_same(null, $r['outside'], 'no clip outside the boxes');
	pg_assert_same(null, $r['after'], 'no clip after their ends');
	pg_assert_same(array(0, 0), $r['plain'], 'the box corner is the source corner');
	pg_assert_same(array(320, 180), $r['centre'], 'the box centre is the source centre');
	// Turned 90 degrees clockwise: the source's top right corner, (160, -90)
	// from the box centre, turns to (90, 160) from it.
	pg_assert_same(array(640, 0), $r['turnedPt'], 'a turned clip: the source corner where it was turned to');
	pg_assert_same(array(640, 0), $r['mirroredPt'], 'a mirrored clip: the left of the box is the source right');
	pg_assert_same(array(10, 5), $r['move'], 'a source move at half size');
	pg_assert_same(array(-5, 10), $r['turnedMove'], 'a turned clip turns the move');
	pg_assert_same(array(-10, 5), $r['mirroredMove'], 'a mirrored clip mirrors the move');
	pg_assert_same(array('w' => 320, 'h' => 180, 'k' => 1 / 6), $r['size'], 'frames read at 320 px on the longer side');
	pg_assert_same(array('w' => 200, 'h' => 100, 'k' => 1), $r['small'], 'a small source is not enlarged');
}

// F4, F5: the span from the playhead to the first of the clip's end, the
// layer's own end and the document's; the frames' times at most 30 a
// second, the source times with the speed counted.
function test_image_editor_follow_span()
{
	$r = image_editor_follow_node(
		'var clip = { id: "v", type: "video", width: 640, height: 360, length: 20000, from: 2000, to: 8000, start: 1000, speed: 2, x: 0, y: 0, w: 640, h: 360 };' . "\n"
		. 'var doc = { layers: [clip], motion: { duration: 10000 } }, l = { id: "t", type: "text", x: 0, y: 0, w: 10, h: 10 };' . "\n"
		. 'var gone = Object.assign({}, l, { anim: { out: { kind: "fade", at: 2500 } } }), times = M.followTimes(clip, 1000, 4000, 1000 / 25);' . "\n"
		. 'var slow = M.followTimes(Object.assign({}, clip, { speed: 0.5 }), 1000, 2000, 1000 / 25);' . "\n"
		. 'console.log(JSON.stringify({ span: M.followSpan(doc, l, clip, 1500), gone: M.followSpan(doc, gone, clip, 1500), short: M.followSpan(doc, l, clip, 3900),'
		. ' doc: M.followSpan({ layers: [clip], motion: { duration: 3000 } }, l, clip, 1500),'
		. ' n: times.length, first: times[0], second: times[1], last: times[times.length - 1], slowStep: slow[1].t - slow[0].t, slowSrc: slow[1].src - slow[0].src }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	// The clip lasts (8000 - 2000) / 2 = 3 s from 1 s: it ends at 4 s.
	pg_assert_same(array('from' => 1500, 'to' => 4000, 'ok' => true), $r['span'], 'to the clip\'s end');
	pg_assert_same(array('from' => 1500, 'to' => 2500, 'ok' => true), $r['gone'], 'to the layer\'s own end');
	pg_assert_same(array('from' => 1500, 'to' => 3000, 'ok' => true), $r['doc'], 'to the document\'s end');
	pg_assert_same(array('from' => 3900, 'to' => 4000, 'ok' => false), $r['short'], 'under 0.2 s is too short');
	// A 25 fps source at 2x: a source frame every 20 ms of the document, so
	// 30 a second (about 33.3 ms) is the step.
	pg_assert_same(90, $r['n'], 'at most 30 frames a second');
	pg_assert_same(array('t' => 1000, 'src' => 2000), $r['first'], 'the first frame at the start');
	pg_assert_same(array('t' => 1033, 'src' => 2067), $r['second'], 'the next a thirtieth of a second on, twice as far in the source');
	pg_assert_true($r['last']['t'] < 4000, 'none at the span\'s end');
	pg_assert_same(80, $r['slowStep'], 'at 0.5x a 25 fps source gives a frame every 80 ms');
	pg_assert_same(40, $r['slowSrc'], 'one source frame each');
}

// F10, F11: the tracker's points become samples that keep the layer's place
// at the playhead and add the move, then keys through M.recordApply(): the
// keys of the span replaced, the others kept.
function test_image_editor_follow_keys()
{
	$r = image_editor_follow_node(
		'var clip = { id: "v", type: "video", width: 640, height: 360, length: 10000, from: 0, to: 10000, start: 0, speed: 1, x: 0, y: 0, w: 320, h: 180, rot: 0 };' . "\n"
		. 'var l = { id: "t", type: "text", x: 10, y: 10, w: 50, h: 20, anim: { keys: [{ t: 0, x: 5, y: 0 }, { t: 500, x: 5, y: 0 }, { t: 4000, x: 0, y: 0 }] } };' . "\n"
		. 'var pts = [], i; for (i = 0; i <= 30; i++) { pts.push({ t: 500 + i * 1000 / 30, x: 100 + i * 2 / 3, y: 50 }); }' . "\n"
		// Tracker frames at a third of the source: a point moved 2/3 px a
		// frame is 2 source px, 1 document unit at half size.
		. 'var s = M.followSamples(l, clip, 500, pts, 1 / 3), res = M.followApply(l, s, 5000, 1);' . "\n"
		. 'console.log(JSON.stringify({ first: [s[0].t, r2(s[0].x), r2(s[0].y)], last: [s[30].t, r2(s[30].x), r2(s[30].y)], n: s.length,'
		. ' keys: res.keys.map(function (k) { return [k.t, r2(k.x), r2(k.y)]; }), end: res.end, none: M.followSamples(l, clip, 500, [], 1).length }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(500, 5, 0), $r['first'], 'the layer keeps its place at the playhead');
	pg_assert_same(array(1500, 35, 0), $r['last'], 'and moves by what the patch moved: 60 source px is 30 units');
	pg_assert_same(31, $r['n'], 'a sample a point');
	pg_assert_same(array(array(0, 5, 0), array(500, 5, 0), array(1500, 35, 0), array(4000, 0, 0)), $r['keys'], 'an even move thinned to its ends, the keys outside kept');
	pg_assert_same(5000, $r['end'], 'the document keeps its length');
	pg_assert_same(0, $r['none'], 'no points, no samples');
}
