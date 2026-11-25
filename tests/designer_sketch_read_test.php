<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for "Read the picture" of the designer's Sketch tool: the part
 * style_designer_sketchread.js (the model's answer made sketch boxes, the
 * proposal's line), its row in "More" shown only when the browser's model
 * reads pictures, and the browser-only reading in pg_ai_write.js.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_sketch_read_test_file($name)
{
	return str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/' . $name));
}

// One function of a file, from its declaration to its closing line at
// the same indent; '' when missing.
function designer_sketch_read_test_fn($js, $head)
{
	$at = strpos($js, $head);

	if ($at === false) {
		return '';
	}

	$end = strpos($js, "\n    }\n", $at);

	return ($end === false) ? '' : substr($js, $at, $end + 7 - $at);
}

// Runs a Node script after the part's pure functions; one JSON value per
// output line. Null without Node.
function designer_sketch_read_test_run($script)
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

	$js = designer_sketch_read_test_file('style_designer_sketchread.js');
	$src = "function _sdT(s, v) { var l = Array.isArray(v) ? v : [v]; return s.replace(/\\{var(?::(\\d+))?\\}/g, function (m, n) { return String(l[(n || 1) - 1]); }); }\n";

	foreach (array('function _srBoxes(', 'function _srCountLine(') as $head) {
		$fn = designer_sketch_read_test_fn($js, $head);

		if (!pg_assert_true($fn !== '', 'the part has ' . $head)) {
			return null;
		}

		$src .= $fn . "\n";
	}

	$tmp = tempnam(sys_get_temp_dir(), 'pgskread');
	file_put_contents($tmp, $src . $script . "\n");
	$out = array();
	exec('node ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
	unlink($tmp);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return array_map(function ($l) { return json_decode($l, true); }, $out);
}

/**
 * The model's percentages become frame units over the picture's height,
 * lined up on the grid, kept inside the frame; bad, tiny, outside and
 * repeated boxes are dropped, headings tidied and cut.
 */
function test_designer_sketch_read_boxes()
{
	$r = designer_sketch_read_test_run(<<<'JS'
var lim = { grid: 8, min: 24, text: 10, max: 40 };
console.log(JSON.stringify(_srBoxes({ boxes: [
    { x: 0, y: 0, w: 100, h: 10, text: '  Top   bar  ' },
    { x: 50, y: 20, w: 80, h: 20 },
    { x: 0, y: 0, w: 100, h: 10 },
    { x: 'a', y: 0, w: 10, h: 10 },
    { x: 10, y: 50, w: 1, h: 1 },
    { x: 120, y: 0, w: 10, h: 10 },
    { x: -10, y: 90, w: 30, h: 20, text: 'A very long heading' }
] }, 480, 1000, lim)));
console.log(JSON.stringify(_srBoxes(null, 480, 1000, lim)));
console.log(JSON.stringify(_srBoxes({ boxes: [{ x: 0, y: 0, w: 50, h: 50 }, { x: 50, y: 0, w: 50, h: 50 }] }, 480, 640, { grid: 8, min: 24, text: 80, max: 1 })));
JS
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array(
		array('x' => 0, 'y' => 0, 'w' => 480, 'h' => 104, 'text' => 'Top bar'),
		array('x' => 240, 'y' => 200, 'w' => 240, 'h' => 200, 'text' => ''),
		array('x' => 0, 'y' => 904, 'w' => 96, 'h' => 104, 'text' => 'A very lon'),
	), $r[0], 'converted, clipped to the picture, the bad ones dropped');
	pg_assert_same(array(), $r[1], 'no answer, no boxes');
	pg_assert_same(1, count($r[2]), 'at most max boxes');
}

function test_designer_sketch_read_count_line()
{
	$r = designer_sketch_read_test_run(<<<'JS'
console.log(JSON.stringify([_srCountLine(9, 0), _srCountLine(1, 0), _srCountLine(9, 3), _srCountLine(1, 1), _srCountLine(9, 1), _srCountLine(1, 3)]));
JS
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array(
		'9 boxes proposed from the picture',
		'1 box proposed from the picture',
		'9 boxes proposed. “Keep” replaces the 3 on the page; one Undo brings them back.',
		'1 box proposed. “Keep” replaces the 1 on the page; one Undo brings it back.',
		'9 boxes proposed. “Keep” replaces the 1 on the page; one Undo brings it back.',
		'1 box proposed. “Keep” replaces the 3 on the page; one Undo brings them back.',
	), $r[0], 'the foot counts the proposal and says what Keep replaces');
}

/**
 * The row stands under the caption "Reference picture", only when the
 * browser's model reads pictures; the part loads on first use; the
 * reading stays in the browser.
 */
function test_designer_sketch_read_wiring()
{
	$core = designer_sketch_read_test_file('style_designer.src.js');
	$rows = designer_sketch_read_test_fn($core, 'function _sdSketchMoreRows(');
	$ready = designer_sketch_read_test_fn($core, 'function _sdSketchReadReady(');
	$part = designer_sketch_read_test_file('style_designer_sketchread.js');
	$ai = designer_sketch_read_test_file('pg_ai_write.js');

	pg_assert_true(strpos($rows, "_sdT('Remove the picture')") < strpos($rows, "_sdT('Read the picture')"), 'under the picture\'s rows');
	pg_assert_contains("if (!page.pic.off && _sdProto.sk.reads === true) {", $rows, 'only when the model reads pictures');
	pg_assert_contains("_sdPartCall('sketchread', 'start', [_sdSketchReadHost(), frameId])", $rows, 'the part runs on first use');
	pg_assert_contains('ai.imageReady().then(', $ready, 'the same check as the alt text');
	pg_assert_contains('if (on) _sdSketchReadReady();', $core, 'asked when the switch goes on');
	pg_assert_same('assets/js/style_designer_sketchread.min.js', pg_designer_parts()['sketchread'] ?? '', 'the part is listed');
	pg_assert_same(array('assets/js/style_designer_sketchread.js'), pg_js_bundles()['assets/js/style_designer_sketchread.min.js'] ?? array(), 'built from its file');
	pg_assert_contains("“Read the picture” reads this picture with your browser\\'s built-in AI. It is sent nowhere.", $part, 'the first-use line');
	pg_assert_contains("ai.readImage({ image: page.pic.src", $part, 'read by the browser\'s model');
	pg_assert_true(strpos($part, '_pgApiPost') === false && strpos($part, 'fetch(') === false, 'the part sends nothing to the server');
	pg_assert_contains('cfg.readImage = readImage;', $ai, 'pg_ai_write.js reads a picture in the browser');
	pg_assert_true(strpos(designer_sketch_read_test_fn($ai, 'function readImage('), 'post(') === false, 'no server step');
}
