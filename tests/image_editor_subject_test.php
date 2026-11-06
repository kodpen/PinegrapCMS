<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's Select subject (assets/js/pg_image_editor_subject.js
 * and the SELECTION section of the model, run under Node): the max-flow
 * against a plain augmenting-path flow on small graphs, GrabCut on a disc
 * on a plain and on a noisy background, the start box, the message session,
 * the mask's rings in the selection, the Keep and Remove brushes, the soft
 * edge (guided filter), and the worker's bundle, address and strings.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the cutter as S and the model as M and answers its
// JSON; null without Node.
function image_editor_subject_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiesubj');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var S = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_subject.js') . ');' . "\n"
		. 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		// A seeded random number in [0, 1).
		. 'var seed = 7; var rnd = function () { seed = (seed * 1103515245 + 12345) & 0x7fffffff; return seed / 0x7fffffff; };' . "\n"
		// A w x h picture of a red disc (centre cx, cy, radius r) on blue,
		// each channel off by up to noise / 2, and the disc's own mask.
		. 'var disc = function (w, h, cx, cy, r, noise) { var d = new Uint8ClampedArray(w * h * 4), t = new Uint8Array(w * h), x, y, k, i, c;'
		. ' for (y = 0; y < h; y++) { for (x = 0; x < w; x++) { i = y * w + x; t[i] = (Math.hypot(x + 0.5 - cx, y + 0.5 - cy) < r) ? 1 : 0; c = t[i] ? [200, 60, 40] : [60, 120, 200];'
		. ' for (k = 0; k < 3; k++) { d[i * 4 + k] = c[k] + (rnd() - 0.5) * noise; } d[i * 4 + 3] = 255; } } return { data: d, truth: t }; };' . "\n"
		. 'var iou = function (a, b) { var i = 0, u = 0; for (var k = 0; k < a.length; k++) { i += (a[k] && b[k]) ? 1 : 0; u += (a[k] || b[k]) ? 1 : 0; } return u ? Math.round(i / u * 1000) / 1000 : 0; };' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// The max-flow of small graphs: known answers, and random graphs against a
// plain augmenting-path flow (Edmonds-Karp) with the cut's capacity equal
// to the flow.
function test_image_editor_subject_maxflow()
{
	$r = image_editor_subject_node(
		'var out = {};' . "\n"
		. 'var g = new S.Graph(2, 2); g.terminal(0, 3, 0); g.terminal(1, 0, 2); g.edge(0, 1, 5, 0); out.chain = g.maxflow();' . "\n"
		. 'g = new S.Graph(4, 10); g.terminal(0, 10, 0); g.terminal(1, 10, 0); g.terminal(2, 0, 10); g.terminal(3, 0, 10);'
		. ' g.edge(0, 2, 4, 0); g.edge(0, 3, 3, 0); g.edge(1, 2, 2, 0); g.edge(1, 3, 9, 0); g.edge(0, 1, 1, 1); out.square = g.maxflow();' . "\n"
		. 'g = new S.Graph(1, 0); g.terminal(0, 4, 6); out.both = g.maxflow(); out.bothSide = g.inSource(0);' . "\n"
		// Edmonds-Karp on a matrix with node 0 the source and n + 1 the sink.
		. 'var ek = function (n, c) { var N = n + 2, f = 0; for (;;) { var prev = new Array(N).fill(-1), q = [0]; prev[0] = 0;'
		. ' while (q.length && prev[N - 1] < 0) { var u = q.shift(); for (var v = 0; v < N; v++) { if (prev[v] < 0 && c[u][v] > 1e-9) { prev[v] = u; q.push(v); } } }'
		. ' if (prev[N - 1] < 0) { return f; } var b = Infinity, v2; for (v2 = N - 1; v2 !== 0; v2 = prev[v2]) { b = Math.min(b, c[prev[v2]][v2]); }'
		. ' for (v2 = N - 1; v2 !== 0; v2 = prev[v2]) { c[prev[v2]][v2] -= b; c[v2][prev[v2]] += b; } f += b; } };' . "\n"
		. 'out.random = []; for (var t = 0; t < 40; t++) { var n = 3 + Math.floor(rnd() * 10), c = [], i, j; for (i = 0; i < n + 2; i++) { c.push(new Array(n + 2).fill(0)); }'
		. ' g = new S.Graph(n, n * n * 2); var caps = [];'
		. ' for (i = 0; i < n; i++) { var s = Math.floor(rnd() * 10), k = Math.floor(rnd() * 10); g.terminal(i, s, k); c[0][i + 1] = s; c[i + 1][n + 1] = k; }'
		. ' for (i = 0; i < n; i++) { for (j = i + 1; j < n; j++) { if (rnd() < 0.5) { var ab = Math.floor(rnd() * 8), ba = Math.floor(rnd() * 8); g.edge(i, j, ab, ba); c[i + 1][j + 1] = ab; c[j + 1][i + 1] = ba; caps.push([i, j, ab, ba]); } } }'
		. ' var copy = c.map(function (row) { return row.slice(); }), want = ek(n, copy), got = g.maxflow(), cut = 0;'
		. ' for (i = 0; i < n; i++) { if (g.inSource(i)) { cut += c[i + 1][n + 1]; } else { cut += c[0][i + 1]; } }'
		. ' caps.forEach(function (e) { if (g.inSource(e[0]) && !g.inSource(e[1])) { cut += e[2]; } if (g.inSource(e[1]) && !g.inSource(e[0])) { cut += e[3]; } });'
		. ' out.random.push([want, got, cut]); }' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(2, $r['chain'], 'a chain carries its narrowest link');
	pg_assert_same(16, $r['square'], 'two sources into two sinks');
	pg_assert_same(4, $r['both'], 'a node tied to both terminals carries the smaller one at once');
	pg_assert_same(false, $r['bothSide'], 'the rest of its sink link puts it on the sink side');

	foreach ($r['random'] as $i => $case) {
		pg_assert_same($case[0], $case[1], 'random graph ' . $i . ': the flow of Edmonds-Karp');
		pg_assert_same($case[1], $case[2], 'random graph ' . $i . ': the cut costs the flow');
	}
}

// GrabCut finds a disc from the start box on a plain and on a noisy
// background (overlap over 0.9), with the box found or drawn.
function test_image_editor_subject_disc()
{
	$r = image_editor_subject_node(
		'var out = {};' . "\n"
		. 'var p = disc(160, 120, 88, 54, 30, 0), a = S.subject(p.data, 160, 120, null); out.plain = iou(a.mask, p.truth); out.box = a.box;' . "\n"
		. 'p = disc(160, 120, 70, 64, 32, 120); a = S.subject(p.data, 160, 120, null); out.noisy = iou(a.mask, p.truth);' . "\n"
		. 'p = disc(160, 120, 70, 64, 32, 120); a = S.subject(p.data, 160, 120, [30, 24, 112, 104]); out.drawn = iou(a.mask, p.truth); out.drawnBox = a.box;' . "\n"
		. 'var flat = new Uint8ClampedArray(40 * 30 * 4).fill(128); out.flat = S.startBox(flat, 40, 30);' . "\n"
		. 'out.edgeBox = S.clampBox([-20, -5, 500, 500], 40, 30); out.tiny = S.clampBox([5, 5, 6, 6], 40, 30);' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_true($r['plain'] > 0.9, 'a disc on a plain background: IoU ' . $r['plain']);
	pg_assert_true($r['noisy'] > 0.9, 'a disc on a noisy background: IoU ' . $r['noisy']);
	pg_assert_true($r['drawn'] > 0.9, 'a disc in a drawn box: IoU ' . $r['drawn']);
	pg_assert_same(array(30, 24, 112, 104), $r['drawnBox'], 'a drawn box is kept');
	pg_assert_true(($r['box'][0] <= 58) && ($r['box'][1] <= 24) && ($r['box'][2] >= 118) && ($r['box'][3] >= 84), 'the start box holds the disc');
	pg_assert_true(($r['box'][0] >= 40) && ($r['box'][2] <= 136), 'the start box stays near the disc');
	pg_assert_same(null, $r['flat'], 'a flat picture has no start box');
	pg_assert_same(array(1, 1, 39, 29), $r['edgeBox'], 'a box keeps a pixel of background round it');
	pg_assert_same(null, $r['tiny'], 'a box too small to cut');
}

// The message session answers a mask of a byte a pixel and its box; a
// broken message answers an error.
function test_image_editor_subject_session()
{
	$r = image_editor_subject_node(
		'var p = disc(80, 60, 40, 30, 16, 0), got = [];' . "\n"
		. 'S.session(function (m) { got.push(m); }).receive({ type: "cut", data: p.data.buffer, width: 80, height: 60, box: null });' . "\n"
		. 'S.session(function (m) { got.push(m); }).receive({ type: "cut", data: null, width: 80, height: 60 });' . "\n"
		. 'S.session(function (m) { got.push(m); }).receive({ type: "other" });' . "\n"
		. 'console.log(JSON.stringify({ n: got.length, type: got[0].type, bytes: got[0].mask.byteLength, iou: iou(new Uint8Array(got[0].mask), p.truth), second: got[1].type }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(2, $r['n'], 'an unknown message is not answered');
	pg_assert_same('mask', $r['type'], 'a cut answers a mask');
	pg_assert_same(4800, $r['bytes'], 'a byte a pixel');
	pg_assert_true($r['iou'] > 0.9, 'the mask is the disc: IoU ' . $r['iou']);
	pg_assert_same('error', $r['second'], 'a message without pixels answers an error');
}

// The mask's rings: specks dropped, the pixel steps smoothed, carried by the
// matrix; a subject op with several rings folds in the selection.
function test_image_editor_subject_rings()
{
	$r = image_editor_subject_node(
		'var w = 100, h = 80, mask = new Uint8Array(w * h), x, y;' . "\n"
		. 'for (y = 0; y < h; y++) { for (x = 0; x < w; x++) { mask[y * w + x] = ((Math.hypot(x + 0.5 - 30, y + 0.5 - 40) < 20) || (x >= 60 && x < 90 && y >= 20 && y < 60 && !(x >= 70 && x < 80 && y >= 35 && y < 45))) ? 1 : 0; } }' . "\n"
		. 'mask[2 * w + 2] = 1;' . "\n"
		. 'var raw = M.maskRings(mask, w, h), rings = M.subjectRings(mask, w, h, M.scale(2, 2));' . "\n"
		. 'var pts = function (rs) { return rs.reduce(function (n, r) { return n + r.length; }, 0); };' . "\n"
		. 'var sel = { ops: [{ op: "add", kind: "subject", rings: rings }, { op: "sub", kind: "rect", ring: M.areaRect([0, 0], [110, 160]) }] };' . "\n"
		. 'var sq = [[0, 0], [10, 0], [10, 0.2], [10, 10], [0, 10], [0, 5.3]];' . "\n"
		. 'console.log(JSON.stringify({ raw: raw.length, rings: rings.length, rawPts: pts(raw), pts: pts(rings), area: Math.round(M.ringsArea(rings)),'
		. ' after: Math.round(M.ringsArea(M.selectionRings(sel, null, null))), square: M.simplifyRing(sq, 0.5) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(4, $r['raw'], 'the traced mask: disc, block, its hole and the speck');
	pg_assert_same(3, $r['rings'], 'the speck goes, the hole stays');
	pg_assert_true($r['pts'] < $r['rawPts'] / 2, 'the pixel steps are smoothed: ' . $r['pts'] . ' of ' . $r['rawPts'] . ' points');
	// Disc about pi * 20^2 = 1257, block 1200 less its hole 100, all x 4.
	pg_assert_true(abs($r['area'] - 4 * (1257 + 1100)) < 4 * 40, 'the area carried by the matrix: ' . $r['area']);
	pg_assert_true(abs($r['after'] - 4 * 1100) < 4 * 20, 'a rectangle taken from a subject op leaves the block: ' . $r['after']);
	pg_assert_same(array(array(0, 0), array(10, 0), array(10, 10), array(0, 10)), $r['square'], 'points on a straight side go');
}

// The worker has its own bundle, the editor its address, the part its
// strings.
function test_image_editor_subject_wiring()
{
	$bundles = pg_js_bundles();

	pg_assert_same(array('assets/js/pg_image_editor_subject.js'), $bundles['assets/js/pg_image_editor_subject.min.js'], 'the worker bundle');
	pg_assert_contains("'subject' => pg_js_bundle_urls('assets/js/pg_image_editor_subject.min.js')", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/image_editor.php'), 'the worker address in the config');

	$strings = pg_image_editor_strings_area();

	foreach (array('subjectSelect', 'subjectBusy', 'subjectNote', 'subjectNone', 'subjectRead', 'subjectError', 'subjectRefine', 'subjectKeep', 'subjectRemove', 'subjectBrush', 'subjectBrushNote', 'subjectRefining', 'subjectEdge') as $key) {
		pg_assert_true(isset($strings[$key]), 'string ' . $key);
	}
}

// The brushes: a Keep stroke brings back a part of the subject the colours
// gave to the background, one outside the box grows the cut, a Remove
// stroke takes away a part joined to it; the labels and the session.
function test_image_editor_subject_brushes()
{
	$r = image_editor_subject_node(
		// A red disc with a background-blue patch in its middle, and a red
		// bar joined to it on the right that is not part of it.
		'var w = 160, h = 120, d = new Uint8ClampedArray(w * h * 4), truth = new Uint8Array(w * h), x, y, k;' . "\n"
		. 'for (y = 0; y < h; y++) { for (x = 0; x < w; x++) { var i = y * w + x, r = Math.hypot(x + 0.5 - 70, y + 0.5 - 60), bar = (x >= 100 && x < 140 && y >= 54 && y < 66);'
		. ' truth[i] = (r < 40) ? 1 : 0; var c = ((r < 40 && r >= 16) || (bar && r >= 40)) ? [200, 60, 40] : [60, 120, 200];'
		. ' for (k = 0; k < 3; k++) { d[i * 4 + k] = c[k] + (rnd() - 0.5) * 40; } d[i * 4 + 3] = 255; } }' . "\n"
		. 'var out = {}, box = [20, 10, 150, 110];' . "\n"
		. 'out.plain = iou(S.subject(d, w, h, box).mask, truth);' . "\n"
		. 'out.keep = iou(S.subject(d, w, h, box, [{ keep: 1, r: 5, pts: [[60, 60], [80, 60]] }]).mask, truth);' . "\n"
		. 'out.both = iou(S.subject(d, w, h, box, [{ keep: 1, r: 5, pts: [[60, 60], [80, 60]] }, { keep: 0, r: 8, pts: [[112, 60], [140, 60]] }]).mask, truth);' . "\n"
		. 'out.grow = iou(S.subject(d, w, h, [20, 10, 80, 110], [{ keep: 1, r: 5, pts: [[60, 60], [80, 60]] }, { keep: 1, r: 4, pts: [[95, 60]] }, { keep: 0, r: 8, pts: [[112, 60], [140, 60]] }]).mask, truth);' . "\n"
		. 'var L = S.strokeLabels(20, 20, [5, 5, 10, 10], [{ keep: 1, r: 1, pts: [[15.5, 15.5]] }, { keep: 0, r: 1, pts: [[7.5, 7.5]] }]);' . "\n"
		. 'out.labels = [L[0], L[6 * 20 + 6], L[15 * 20 + 15], L[7 * 20 + 7], L[17 * 20 + 17], L[19 * 20 + 19]];' . "\n"
		. 'var got = []; S.session(function (m) { got.push(m); }).receive({ type: "cut", data: d.buffer.slice(0), width: w, height: h, box: box, strokes: [{ keep: 1, r: 5, pts: [[60, 60], [80, 60]] }] });' . "\n"
		. 'out.session = iou(new Uint8Array(got[0].mask), S.subject(d, w, h, box, [{ keep: 1, r: 5, pts: [[60, 60], [80, 60]] }]).mask);' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_true($r['plain'] < 0.9, 'without brushes the patch and the bar are wrong: IoU ' . $r['plain']);
	pg_assert_true($r['keep'] > $r['plain'], 'Keep brings the patch back: IoU ' . $r['keep']);
	pg_assert_true($r['both'] > 0.95, 'Keep and Remove: IoU ' . $r['both']);
	pg_assert_true($r['grow'] > 0.95, 'a Keep stroke outside the box grows the cut: IoU ' . $r['grow']);
	// BG 0, FG 1, PR_FG 3: the box grown round the Keep stroke, the strokes hard.
	pg_assert_same(array(0, 3, 1, 0, 3, 0), $r['labels'], 'the labels of the box and the strokes');
	pg_assert_same(1, $r['session'], 'the session cuts from the strokes it is sent');
}

// The subject op in the selection, a stroke kept on it, its soft edge, and
// the guided filter: a hard mask of a picture with a soft edge comes close
// to that edge, a mask on the colour edge stays as it is, and a tile far
// from the edge is left alone.
function test_image_editor_subject_soft_edge()
{
	$r = image_editor_subject_node(
		'var out = {};' . "\n"
		. 'var sel = { ops: [{ op: "add", kind: "subject", rings: [], edge: 6 }, { op: "add", kind: "rect", ring: [] }, { op: "sub", kind: "subject", rings: [], edge: 50 }] };' . "\n"
		. 'out.index = M.subjectIndex(sel); out.matte = M.selectionMatte(sel); out.none = M.subjectIndex({ ops: [{ kind: "rect" }] });' . "\n"
		. 'sel.ops[2].edge = 0; out.hard = M.selectionMatte(sel); out.old = M.selectionMatte({ ops: [{ kind: "subject", rings: [] }] });' . "\n"
		. 'out.stroke = M.subjectStroke(true, 4, [[0, 0], [0.5, 0], [1.2, 0], [5.555, 1]]); out.empty = M.subjectStroke(false, 4, []);' . "\n"
		// A disc whose edge fades over 8 px; the hard mask cut at half.
		. 'var w = 300, h = 200, g = new Uint8ClampedArray(w * h * 4), mask = new Uint8ClampedArray(w * h * 4), soft = new Float32Array(w * h), x, y, k;' . "\n"
		. 'for (y = 0; y < h; y++) { for (x = 0; x < w; x++) { var i = y * w + x, a = Math.max(0, Math.min(1, (60 - Math.hypot(x + 0.5 - 150, y + 0.5 - 100)) / 8 + 0.5)), f = [200, 60, 40], b = [60, 120, 200];'
		. ' soft[i] = a; for (k = 0; k < 3; k++) { g[i * 4 + k] = f[k] * a + b[k] * (1 - a); } g[i * 4 + 3] = 255; mask[i * 4 + 3] = (a >= 0.5) ? 255 : 0; } }' . "\n"
		. 'var err = function () { var e = 0; for (var i = 0; i < w * h; i++) { e += Math.abs(mask[i * 4 + 3] / 255 - soft[i]); } return Math.round(e); };' . "\n"
		. 'out.before = err(); M.matteMask(mask, g, w, h, 6); out.after = err();' . "\n"
		// A sharp edge in the picture and the mask on it.
		. 'var w2 = 40, g2 = new Uint8ClampedArray(w2 * 4), p2 = new Float32Array(w2);'
		. ' for (x = 0; x < w2; x++) { var c = (x < 20) ? [200, 60, 40] : [60, 120, 200]; g2[x * 4] = c[0]; g2[x * 4 + 1] = c[1]; g2[x * 4 + 2] = c[2]; p2[x] = (x < 20) ? 1 : 0; }' . "\n"
		. 'out.sharp = Array.prototype.map.call(M.guidedFilter(g2, p2, w2, 1, 4, 1e-4), function (v) { return Math.round(v * 100) / 100; });' . "\n"
		// A mask with no edge stays as it is.
		. 'var full = new Uint8ClampedArray(600 * 4).fill(255); M.matteMask(full, g.subarray(0, 600 * 4), 600, 1, 6); out.full = full.every(function (v) { return v === 255; });' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(2, $r['index'], 'the last subject op is the one the brushes change');
	pg_assert_same(20, $r['matte'], 'the soft edge is kept to its largest');
	pg_assert_same(-1, $r['none'], 'no subject op');
	pg_assert_same(0, $r['hard'], 'an edge of 0 is hard');
	pg_assert_same(0, $r['old'], 'a subject kept without an edge stays hard');
	pg_assert_same(array('keep' => 1, 'r' => 4, 'pts' => array(array(0, 0), array(1.2, 0), array(5.56, 1))), $r['stroke'], 'a stroke thinned to a quarter of its radius');
	pg_assert_same(null, $r['empty'], 'a stroke without points');
	pg_assert_true($r['after'] < $r['before'] / 3, 'the soft edge follows the picture: error ' . $r['after'] . ' from ' . $r['before']);
	pg_assert_same(array_merge(array_fill(0, 20, 1), array_fill(0, 20, 0)), $r['sharp'], 'a mask on a sharp edge stays sharp');
	pg_assert_true($r['full'], 'a mask with no edge is left alone');
}
