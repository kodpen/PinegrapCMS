<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Sketch tool of the designer's Prototype view
 * (style_designer.src.js, _sdSketch*): its place in the canvas bar, how
 * the view's adapter hands it the presses and keys inside a sketch frame,
 * a stored sketch made safe to draw, and a box's rectangle lined up and
 * kept inside its frame.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_sketch_test_source()
{
	return str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js'));
}

// One function or var block of style_designer.src.js, from its
// declaration to its closing line at the same indent.
function designer_sketch_test_js($head, $close = "    }\n")
{
	$js = designer_sketch_test_source();
	$start = strpos($js, "\n    " . $head);
	$end = ($start === false) ? false : strpos($js, "\n" . $close, $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + strlen($close) + 1);
}

function designer_sketch_test_node($script)
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

	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_graph_canvas.js');
	$js = designer_sketch_test_source();
	$at = strpos($js, "\n    var SD_SKETCH = {");
	$parts = (($at === false) ? '' : substr($js, $at, strpos($js, "\n", $at + 1) - $at))
		. designer_sketch_test_js('var SD_SKETCH_KINDS = [', "    ];\n")
		. designer_sketch_test_js('function _sdSketchKind(')
		. designer_sketch_test_js('function _sdSketchClean(')
		. designer_sketch_test_js('function _sdSketchBodyH(')
		. designer_sketch_test_js('function _sdSketchRect(');
	$file = tempnam(sys_get_temp_dir(), 'pgsk') . '.js';
	file_put_contents($file, "var window = {}; var document = {};\n" . $core . "\nfunction _sdT(text) { return text; }\nvar _sdProto = { view: { x: 0, y: 0, k: 1 } };\n" . $parts . "\n" . $script);
	$lines = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);
	pg_assert_same(0, $code, 'node ran: ' . implode("\n", $lines));

	return ($code === 0) ? json_decode(implode("\n", $lines), true) : null;
}

// The Sketch switch and New page lead the tools zone of the canvas bar's
// Prototype mode; the switch is one like All arrows.
function test_designer_sketch_bar_controls()
{
	$build = designer_sketch_test_js('function _sdProtoBuild(');

	pg_assert_contains("ui.sketchBtn = _sdProtoButton('bi-pencil-square', _sdT('Sketch'), function () { _sdSketchSwitch(!_sdProto.sketchOn); });", $build, 'the Sketch switch');
	pg_assert_contains("ui.sketchBtn.setAttribute('role', 'switch');", $build, 'a switch');
	pg_assert_contains("ui.sketchNew = _sdProtoButton('bi-file-earmark-plus', _sdT('New sketch page'), function () { _sdSketchNewPage(); });", $build, 'New page');
	pg_assert_true(strpos($build, 'tools.appendChild(ui.sketchBtn);') < strpos($build, 'tools.appendChild(ui.searchBtn);'), 'first in the tools zone');
}

// Inside a sketch frame the adapter hands the press, its move, release and
// cancel, and the keys to the sketch first; a chip and the heading input
// keep their own clicks; no arrow goes to a sketch page; a moved sketch
// frame is kept with the sketch, the redraw puts the sketch pages back.
function test_designer_sketch_adapter_wiring()
{
	$build = designer_sketch_test_js('function _sdProtoBuild(');
	$draw = designer_sketch_test_js('function _sdProtoDraw(');

	pg_assert_contains("press: function (event, next) {\n                if (_sdSketchPress(event, next)) return;", $build, 'a press is the sketch\'s first');
	pg_assert_contains("if (/^sk/.test(drag.kind || '')) { _sdSketchMove(event, drag, dx, dy); return; }", $build, 'its moves');
	pg_assert_contains("if (/^sk/.test(done.kind || '')) { _sdSketchRelease(event, done); return; }", $build, 'its release');
	pg_assert_contains("if (done && /^sk/.test(done.kind || '')) _sdSketchMount(false);", $build, 'a cancelled drag draws the sketch back');
	pg_assert_contains("key: function (event) {\n                if (_sdSketchKey(event)) return true;", $build, 'the keys go to the sketch first');
	pg_assert_contains(".sd-sketch-chip, .sd-sketch-input'", $build, 'chips and the input keep their clicks');
	pg_assert_contains("return !!to && to.kind !== 'sketch' && _sdProtoArrowHref(from, to, port) !== null;", $build, 'no arrow to a sketch page');
	pg_assert_contains("if (item.kind === 'sketch') _sdSketchPlaced(item);", $build, 'a moved sketch frame is kept with the sketch');
	pg_assert_contains('_sdSketchMount(false);', $draw, 'the redraw puts the sketch pages back');
	pg_assert_true(strpos($draw, '_sdSketchMount(false);') < strpos($draw, 'var focusOn = '), 'before the selection is put back');

	$key = designer_sketch_test_js('function _sdSketchKey(');

	pg_assert_contains('if (!_sdProto.sketchOn) return false;', $key, 'the keys are the sketch\'s only while the switch is on');
	pg_assert_contains('return _sdSketchUndo(', $key, 'Ctrl+Z and Ctrl+Y undo the sketch');
}

// A stored sketch made safe: unknown kinds and repeated ids dropped, boxes
// inside the frame's width and at least the smallest size, headings short
// and on one line, the next id past every one used.
function test_designer_sketch_clean()
{
	$result = designer_sketch_test_node(<<<'JS'
var out = {};
out.none = _sdSketchClean(null);
out.bad = _sdSketchClean({ pages: 'x' });
out.one = _sdSketchClean({ pages: [
    { id: 3, name: 'Home', x: 10.6, y: 'a', boxes: [
        { id: 7, x: 470, y: -5, w: 900, h: 2, kind: 'header', text: '  Hello \n there ' },
        { id: 7, x: 0, y: 0, w: 50, h: 50, kind: 'x' },
        { id: 8, x: 20, y: 30, w: 40, h: 50, kind: 'nope', text: new Array(200).join('a') }
    ] },
    { id: 3, boxes: [] },
    { id: 0 }
] });
out.kinds = SD_SKETCH_KINDS.map(function (k) { return k.key; });
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('n' => 1, 'pages' => array()), $result['none'], 'nothing stored');
	pg_assert_same(array('n' => 1, 'pages' => array()), $result['bad'], 'no list of pages');
	pg_assert_same(1, count($result['one']['pages']), 'a repeated page id and one without an id are dropped');
	$page = $result['one']['pages'][0];
	pg_assert_same(array(3, 'Home', 11, 0), array($page['id'], $page['name'], $page['x'], $page['y']), 'whole numbers, a bad one 0');
	pg_assert_same(2, count($page['boxes']), 'a repeated box id is dropped');
	pg_assert_same(array('id' => 7, 'x' => 0, 'y' => 0, 'w' => 480, 'h' => 24, 'kind' => 'header', 'text' => 'Hello there'), $page['boxes'][0], 'inside the frame, at least the smallest size, one line');
	pg_assert_same('', $page['boxes'][1]['kind'], 'an unknown kind is left to the reader');
	pg_assert_same(80, strlen($page['boxes'][1]['text']), 'a heading of 80 characters at most');
	pg_assert_same(9, $result['one']['n'], 'the next id after the last used');
	pg_assert_same(array('header', 'intro', 'features', 'cards', 'text', 'picture', 'gallery', 'form', 'cta', 'footer', 'catalog', 'blog', 'booking'), $result['kinds'], 'the kinds a box can be');
}

// A box's rectangle: a move lines it up with another box and stays inside
// the frame, a resize from the left stops at the frame's edge, a drawing
// goes either way, the zoom scales the drag.
function test_designer_sketch_rect()
{
	$result = designer_sketch_test_node(<<<'JS'
var out = {};
var other = { id: 1, x: 40, y: 40, w: 200, h: 80 };
var mine = { id: 2, x: 300, y: 300, w: 100, h: 60 };
var page = { boxes: [other, mine] };
var ev = { altKey: false };
out.move = _sdSketchRect(ev, { kind: 'skmove', page: page, box: mine, start: { x: 300, y: 300, w: 100, h: 60 } }, -257, 3);
out.right = _sdSketchRect(ev, { kind: 'skmove', page: page, box: mine, start: { x: 300, y: 300, w: 100, h: 60 } }, 500, 0);
out.west = _sdSketchRect(ev, { kind: 'skresize', side: 'w', page: page, box: mine, start: { x: 300, y: 300, w: 100, h: 60 } }, -400, 0);
out.draw = _sdSketchRect(ev, { kind: 'skdraw', page: page, local: { x: 200, y: 400 } }, -100, -50);
_sdProto.view.k = 2;
out.zoomed = _sdSketchRect(ev, { kind: 'skmove', page: page, box: mine, start: { x: 300, y: 300, w: 100, h: 60 } }, 32, 0);
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(40, 304), array($result['move']['x'], $result['move']['y']), 'lined up with the box\'s left edge, the top on the grid');
	pg_assert_same('x', $result['move']['guides'][0]['axis'], 'with its guide');
	pg_assert_same(380, $result['right']['x'], 'kept inside the frame\'s width');
	pg_assert_same(array(0, 400), array($result['west']['x'], $result['west']['w']), 'a left side stops at the frame');
	pg_assert_same(array(104, 352, 96, 48), array($result['draw']['x'], $result['draw']['y'], $result['draw']['w'], $result['draw']['h']), 'drawn up and to the left, on the grid');
	pg_assert_same(320, $result['zoomed']['x'], 'a drag on a zoomed map moves the box by the map\'s units');
}
