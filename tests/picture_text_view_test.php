<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Artifact Canvas's language view, where one language's
 * picture texts are moved and sized by hand: the view's document
 * (M.languageView()), a change in it turned into the text's correction
 * (M.languageViewSettle(), M.languageCorrectionAt()), the corrections
 * written back into the canvas document (M.languageViewCorrections()), a
 * text past the picture's edge (M.languagePast()), and the doors that open
 * the view (the Languages list's rows, the queue's Look by hand row).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * Runs $script under Node with the editor's model as M, the stub layout
 * (half the size a letter of the longest line, 1.25 the size a line) as
 * layout, a 400 x 200 picture as board and doc() as a fresh document with a
 * text "Sale" (a) and a shape (s); answers its JSON, null without Node.
 *
 * @param string $script
 * @return array|null
 */
function picture_view_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgpicview');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'var layout = function (l) { var lines = String(l.text).split("\n"), size = Number(l.size);'
		. ' return { w: Math.round(Math.max.apply(null, lines.map(function (s) { return s.length; })) * size / 2 * 100) / 100,'
		. ' h: Math.round(lines.length * size * 1.25 * 100) / 100 }; };' . "\n"
		. 'var board = { w: 400, h: 200 };' . "\n"
		. 'var doc = function () { return { v: 1, artboard: { width: 400, height: 200 }, layers: ['
		. '{ id: "s", type: "rect", x: 0, y: 0, w: 60, h: 60, fill: "#000" },'
		. '{ id: "a", type: "text", text: "Sale", size: 40, x: 150, y: 80, w: 80, h: 50, color: "#111" }] }; };' . "\n"
		. 'var texts = [{ id: "a", field: "text", text: "Grosser Sommer" }];' . "\n"
		. 'var find = function (d, id) { return d.layers.filter(function (l) { return l.id === id; })[0]; };' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

/**
 * Whether two numbers are the same within a rounding of the twins.
 *
 * @param float $a
 * @param float $b
 * @return bool
 */
function picture_view_near($a, $b)
{
	return abs((float) $a - (float) $b) <= 0.011;
}

// The place a correction is drawn from, read back from where the text is:
// the inverse of the put, for each alignment and a turned text.
function test_picture_view_correction_at()
{
	$r = picture_view_node('var out = [];'
		. '[["left", 0], ["center", 0], ["right", 0], ["right", 30], ["center", -45]].forEach(function (c) {'
		. ' var l = { id: "a", type: "text", text: "Grosser Sommer Ausverkauf", size: 30, align: c[0], rot: c[1] }, fix = { x: 20, y: 40, w: 200, size: 30 };'
		. ' M.languageCorrectedPut(l, fix, layout, board);'
		. ' out.push(M.languageCorrectionAt(l, 200, l.x, l.y, l.w)); });'
		. 'console.log(JSON.stringify(out));');

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');

		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'model loaded');

	foreach ($r as $i => $box) {
		pg_assert_true(picture_view_near($box['x'], 20) && picture_view_near($box['y'], 40), 'the place read back, case ' . $i);
		pg_assert_same(array(200, 30), array($box['w'], $box['size']), 'the width and the size kept, case ' . $i);
	}
}

// A text past the picture's edge, by its turned box, with half a px of slack.
function test_picture_view_past()
{
	$r = picture_view_node('console.log(JSON.stringify(['
		. 'M.languagePast({ x: 10, y: 10, w: 100, h: 40 }, board),'
		. 'M.languagePast({ x: 310, y: 10, w: 90.4, h: 40 }, board),'
		. 'M.languagePast({ x: 320, y: 10, w: 100, h: 40 }, board),'
		. 'M.languagePast({ x: 290, y: 10, w: 100, h: 40, rot: 45 }, board),'
		. 'M.languagePast({ x: -20, y: 10, w: 100, h: 40 }, { w: 0, h: 0 })]));');

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');

		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'model loaded');
	pg_assert_same(array(false, false, true, true, false), $r, 'inside, at the edge, past it, turned past it, no picture size');
}

// The view's document: the language's words fitted or corrected, every
// other layer locked, the automatic place of each text kept beside it.
function test_picture_view_doc()
{
	$r = picture_view_node('var plain = M.languageView(doc(), texts, "de", layout, board);'
		. 'var d = doc(); M.languageCorrect(find(d, "a"), "de", { x: 20, y: 20, w: 300, size: 30 });'
		. 'var fixed = M.languageView(d, texts, "de", layout, board);'
		. 'console.log(JSON.stringify({ plain: plain, fixed: fixed, source: d, none: M.languageView(null, texts, "de", layout, board) }));');

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');

		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'model loaded');

	$plain = $r['plain'];
	$a = $plain['doc']['layers'][1];

	pg_assert_same(true, $plain['doc']['layers'][0]['locked'], 'a layer other than a text is locked');
	pg_assert_false(isset($a['locked']), 'a text is not locked');
	pg_assert_same('Grosser Sommer', $plain['words']['a'], 'the words before the fit');
	pg_assert_same(array($a['x'], $a['y'], $a['size'], $a['text']), array($plain['auto']['a']['x'], $plain['auto']['a']['y'], $plain['auto']['a']['size'], $plain['auto']['a']['text']), 'a text with no correction stands where the fit puts it');

	$f = $r['fixed']['doc']['layers'][1];

	pg_assert_same(array(20, 20, 30, 'Grosser Sommer'), array($f['x'], $f['y'], $f['size'], $f['text']), 'a corrected text stands at its correction');
	pg_assert_same($plain['auto']['a'], $r['fixed']['auto']['a'], 'its automatic place is read without the correction');
	pg_assert_same('Sale', $r['source']['layers'][1]['text'], 'the source document keeps its words');
	pg_assert_false(isset($r['source']['layers'][0]['locked']), 'and its layers unlocked');
	pg_assert_same(null, $r['none'], 'no document, no view');
}

// A change in the view becomes the text's correction for the language and
// the text is put again from it; what the view may not change comes back.
function test_picture_view_settle()
{
	$r = picture_view_node('var v = M.languageView(doc(), texts, "de", layout, board), out = {};'
		. 'var step = function (fn) { var before = M.clone(v.doc); fn(v.doc); M.languageViewSettle(before, v.doc, v, layout, board); return M.clone(v.doc); };'
		. 'var a0 = M.clone(find(v.doc, "a"));'
		. 'out.moved = step(function (d) { var a = find(d, "a"); a.x += 10; a.y += 5; a.color = "#f00"; d.layers.splice(0, 1); });'
		. 'out.wide = step(function (d) { var a = find(d, "a"); a.lvFix = { x: a.x, y: a.y, w: 60, size: a.size }; });'
		. 'out.sized = step(function (d) { var a = find(d, "a"); a.size = a.size / 2; a.w = a.w / 2; a.h = a.h / 2; });'
		. 'out.back = step(function (d) { M.languageCorrect(find(d, "a"), "de", null); });'
		. 'out.a0 = a0; out.auto = v.auto.a;'
		. 'console.log(JSON.stringify(out));');

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');

		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'model loaded');

	$a0 = $r['a0'];
	$moved = $r['moved']['layers'];
	$a = $moved[1];

	pg_assert_same(2, count($moved), 'a layer the view may not delete comes back');
	pg_assert_same('#111', $a['color'], 'nor may it change a text\'s colour');
	pg_assert_true(picture_view_near($a['x'], $a0['x'] + 10) && picture_view_near($a['y'], $a0['y'] + 5), 'the moved text stands where it was dropped');
	pg_assert_true(picture_view_near($a['corrections']['de']['x'], $a0['x'] + 10) && picture_view_near($a['corrections']['de']['w'], $a0['w']), 'its correction: the place, the width it had');

	$wide = $r['wide']['layers'][1];

	pg_assert_same("Grosser\nSommer", $wide['text'], 'a narrower width wraps the words between them');
	pg_assert_same(60, $wide['corrections']['de']['w'], 'the width is the correction\'s');
	pg_assert_false(isset($wide['lvFix']), 'the side handle\'s box is not kept on the layer');

	$sized = $r['sized']['layers'][1];

	pg_assert_true(picture_view_near($sized['corrections']['de']['size'], $wide['size'] / 2), 'a corner sizes the letters');
	pg_assert_true(picture_view_near($sized['corrections']['de']['w'], 30), 'and the width with them');

	$back = $r['back']['layers'][1];

	pg_assert_false(isset($back['corrections']), 'Back to automatic takes the correction off');
	pg_assert_same(array($r['auto']['x'], $r['auto']['y'], $r['auto']['size'], $r['auto']['text']), array($back['x'], $back['y'], $back['size'], $back['text']), 'and the text stands where the fit puts it');
}

// Done writes the view's corrections into the canvas document, and only
// them; a text taken back to automatic loses its correction there.
function test_picture_view_corrections()
{
	$r = picture_view_node('var d = doc(), v = M.languageView(d, texts, "de", layout, board), out = [];'
		. 'M.languageCorrect(find(v.doc, "a"), "de", { x: 5, y: 6, w: 100, size: 20 });'
		. 'out.push(M.languageViewCorrections(d, v.doc, "de")); out.push(M.clone(d));'
		. 'out.push(M.languageViewCorrections(d, v.doc, "de"));'
		. 'M.languageCorrect(find(v.doc, "a"), "de", null);'
		. 'out.push(M.languageViewCorrections(d, v.doc, "de")); out.push(M.clone(d));'
		. 'console.log(JSON.stringify(out));');

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');

		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'model loaded');
	pg_assert_same(true, $r[0], 'a correction written');
	pg_assert_same(array('x' => 5, 'y' => 6, 'w' => 100, 'size' => 20), $r[1]['layers'][1]['corrections']['de'], 'on the source text');
	pg_assert_same(array('Sale', 150, 40), array($r[1]['layers'][1]['text'], $r[1]['layers'][1]['x'], $r[1]['layers'][1]['size']), 'its words, place and size unchanged');
	pg_assert_false(isset($r[1]['layers'][0]['locked']), 'the other layers not locked');
	pg_assert_same(false, $r[2], 'the same again changes nothing');
	pg_assert_same(true, $r[3], 'a correction taken off');
	pg_assert_false(isset($r[4]['layers'][1]['corrections']), 'the field goes with it');
}

// The doors: the list's labels with their Turkish, the queue's Look by hand
// row opening the language view.
function test_picture_view_doors()
{
	if (function_exists('pg_image_editor_strings_artboard')) {
		$strings = pg_image_editor_strings_artboard();

		foreach (array('langCorrect', 'langCorrectedOne', 'langCorrected', 'langFixNote', 'langViewLine', 'langAuto', 'langDone', 'langSaveFirst', 'langAutoAll', 'langEditWords') as $key) {
			pg_assert_true(isset($strings[$key]) && ($strings[$key] !== ''), 'label ' . $key);
		}
	}

	$queue = (string) file_get_contents(PG_FUNCTIONS_DIR . '/translations.php');
	$editor = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/image_editor.php');

	pg_assert_true(strpos($queue, 'data-pg-image-language="') !== false, 'the queue\'s row names its language');
	pg_assert_true(strpos($editor, 'b.getAttribute("data-pg-image-language")') !== false, 'the editor opens on it');
}
