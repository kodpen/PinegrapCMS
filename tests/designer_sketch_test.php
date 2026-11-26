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
		. designer_sketch_test_js('function _sdSketchPicSrc(')
		. designer_sketch_test_js('function _sdSketchDetailsKeep(')
		. designer_sketch_test_js('function _sdSketchClean(')
		. designer_sketch_test_js('function _sdSketchBodyH(')
		. designer_sketch_test_js('function _sdSketchNearSide(')
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
	pg_assert_contains("if (done && /^sk/.test(done.kind || '')) { _sdProto.sk.drag = false; _sdSketchMount(false); }", $build, 'a cancelled drag ends the drag and draws the sketch back');
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

// A sketch page keeps its "Page details": the known keys only, texts
// short, numbers whole; a page never saved from the window keeps none.
function test_designer_sketch_clean_details()
{
	$result = designer_sketch_test_node(<<<'JS'
var out = _sdSketchClean({ pages: [
    { id: 1, name: 'Classes', details: { address: 'classes', typed: 1, type: 'catalog', menu: '4', after: -2, folder: 2.7, title: new Array(400).join('t'), extra: 'x' } },
    { id: 2, details: 'x' },
    { id: 3 }
] });
console.log(JSON.stringify(out.pages.map(function (p) { return p.details === undefined ? null : p.details; })));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('address' => 'classes', 'type' => 'catalog', 'menuName' => '', 'afterName' => '', 'title' => str_repeat('t', 255), 'description' => '', 'menu' => 4, 'after' => 0, 'folder' => 2, 'typed' => true), $result[0], 'known keys, short texts, whole numbers');
	pg_assert_same(null, $result[1], 'details that are not an object are dropped');
	pg_assert_same(null, $result[2], 'a page never saved from the window has none');
}

// "Page details" sits in a sketch page's own "More" menu and in the command
// palette, never in a bar; it is a part loaded on first use.
function test_designer_sketch_details_wiring()
{
	$more = designer_sketch_test_js('function _sdSketchMoreRows(');
	$open = designer_sketch_test_js('function _sdSketchDetails(');
	$cp = designer_sketch_test_js('function _getCpActionItems(');

	pg_assert_contains("text: _sdT('Page details'), run: function () { _sdSketchDetails(frameId); }", $more, 'the More menu has the Page details row');
	pg_assert_contains("_sdPartCall('sketchdetails', 'open', [_sdSketchDetailsHost(frameId, page)]).catch(_sdPartFailed);", $open, 'the window is a part');
	pg_assert_contains("id: 'act_sketch_details', label: _sdT('Page details')", $cp, 'the command palette has the row');
	pg_assert_same(false, strpos(designer_sketch_test_js('function _sdProtoBuild('), "_sdT('Page details')"), 'no bar button for it');

	$parts = pg_designer_parts();
	$bundles = pg_js_bundles();
	pg_assert_same('assets/js/style_designer_sketchdetails.min.js', $parts['sketchdetails'], 'a designer part');
	pg_assert_same(array('assets/js/pg_sketch_details.js', 'assets/js/style_designer_sketchdetails.js'), $bundles[$parts['sketchdetails']], 'its pure values, then the window');
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

// A reference picture kept with a sketch page: only an address of this
// site or of the web, its height whole and bounded, hidden or not; the
// body grows as tall as a shown picture and not for a hidden one.
function test_designer_sketch_reference_picture()
{
	$result = designer_sketch_test_node(<<<'JS'
var out = {};
out.src = ['/files/a.jpg', 'https://images.example.com/p.jpg', 'http://x.test/y.png', '//evil.test/a.jpg', 'javascript:alert(1)', 'data:image/png;base64,AA', 'files/a.jpg', '/a b.jpg', '/a".jpg', '', null].map(_sdSketchPicSrc);
var data = _sdSketchClean({ pages: [
    { id: 1, pic: { src: '/files/ref.jpg', h: 1200.4, off: 0 }, boxes: [] },
    { id: 2, pic: { src: 'javascript:x', h: 900 }, boxes: [] },
    { id: 3, pic: { src: '/files/tall.jpg', h: 1e9, off: 1 }, boxes: [] },
    { id: 4, pic: { src: '/files/short.jpg', h: 'x' }, boxes: [] }
] });
out.pages = data.pages;
out.bodyShown = _sdSketchBodyH(data.pages[0]);
out.bodyHidden = _sdSketchBodyH(data.pages[2]);
out.bodyShort = _sdSketchBodyH({ boxes: [], pic: { src: '/a.jpg', h: 100, off: false } });
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('/files/a.jpg', 'https://images.example.com/p.jpg', 'http://x.test/y.png', '', '', '', '', '', '', '', ''), $result['src'], 'a site or web address only');
	pg_assert_same(array('src' => '/files/ref.jpg', 'h' => 1200, 'off' => false), $result['pages'][0]['pic'], 'a kept picture');
	pg_assert_true(!isset($result['pages'][1]['pic']), 'a script address is dropped');
	pg_assert_same(array('src' => '/files/tall.jpg', 'h' => 20000, 'off' => true), $result['pages'][2]['pic'], 'a bounded height, hidden');
	pg_assert_same(640, $result['pages'][3]['pic']['h'], 'a height that is no number is the least body');
	pg_assert_same(1200, $result['bodyShown'], 'the body as tall as the picture');
	pg_assert_same(640, $result['bodyHidden'], 'a hidden picture does not grow it');
	pg_assert_same(640, $result['bodyShort'], 'a short picture keeps the least body');
}

// "More" in a sketch page's head holds the picture's rows: one row
// without a picture, three under their caption with one; the picture
// lies under the boxes and takes no pointer.
function test_designer_sketch_more_rows()
{
	$rows = designer_sketch_test_js('function _sdSketchMoreRows(');
	$fill = designer_sketch_test_js('function _sdSketchFill(');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css');

	pg_assert_contains("text: _sdT('Reference picture'), run: function () { _sdSketchPicChoose(frameId); }", $rows, 'one row without a picture');
	pg_assert_contains("rows.push({ caption: _sdT('Reference picture') });", $rows, 'the caption with a picture');
	pg_assert_contains("_sdT('Show the picture') : _sdT('Hide the picture')", $rows, 'hide and show');
	pg_assert_contains("_sdT('Change the picture')", $rows, 'change');
	pg_assert_contains("_sdT('Remove the picture')", $rows, 'remove');
	pg_assert_contains('if (on) head.appendChild(_sdSketchMoreBtn());', $fill, '"More" while the switch is on');
	pg_assert_true(strpos($fill, '_sdSketchPicEl(page.pic)') < strpos($fill, '_sdSketchBoxEl(b, picked, on)'), 'the picture under the boxes');
	pg_assert_contains('opacity: .33; pointer-events: none;', $css, 'dimmed, no pointer');
	pg_assert_contains('.sd-sketch-mode .pgc-selected .sd-sketch-more { display: inline-flex; }', $css, 'on the selected page only');
}

// A finger near a handle of the selected box takes that handle: the nearest
// corner or side within reach, none farther; the reach is at most a quarter
// of the box, so a small box's middle still moves it.
function test_designer_sketch_near_side()
{
	$result = designer_sketch_test_node(<<<'JS'
var b = { x: 100, y: 100, w: 200, h: 120 };
var at = function (x, y, reach) { return _sdSketchNearSide(b, { x: x, y: y }, reach); };
var out = {
    se: at(296, 216, 16), nw: at(108, 92, 16), n: at(200, 104, 16), e: at(310, 160, 16), s: at(205, 220, 16), w: at(100, 150, 16),
    middle: at(200, 160, 16), far: at(330, 250, 16), edgeNotHandle: at(150, 100, 16),
    small: _sdSketchNearSide({ x: 0, y: 0, w: 24, h: 24 }, { x: 12, y: 12 }, 16),
    smallCorner: _sdSketchNearSide({ x: 0, y: 0, w: 24, h: 24 }, { x: 22, y: 23 }, 16)
};
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('se', 'nw', 'n', 'e', 's', 'w'), array($result['se'], $result['nw'], $result['n'], $result['e'], $result['s'], $result['w']), 'the nearest handle within reach');
	pg_assert_same('', $result['middle'], 'the middle moves the box');
	pg_assert_same('', $result['far'], 'out of reach');
	pg_assert_same('', $result['edgeNotHandle'], 'an edge between two handles is no handle');
	pg_assert_same('', $result['small'], 'a small box\'s middle still moves it');
	pg_assert_same('se', $result['smallCorner'], 'its corner resizes it');
}

// The feel on a page of a hundred boxes: the guides are placed, never
// added and taken out on each pointer move; a press marks the selection
// in place; a drag let go or an arrow nudge keeps the box where it is and
// draws the frames again once the hand rests, never during a drag; a
// touch near a handle takes it; the selected box lies over the others.
function test_designer_sketch_feel()
{
	$guides = designer_sketch_test_js('function _sdSketchGuides(');
	$pick = designer_sketch_test_js('function _sdSketchPick(');
	$press = designer_sketch_test_js('function _sdSketchPress(');
	$release = designer_sketch_test_js('function _sdSketchRelease(');
	$changed = designer_sketch_test_js('function _sdSketchChanged(');
	$soon = designer_sketch_test_js('function _sdSketchMountSoon(');
	$mount = designer_sketch_test_js('function _sdSketchMount(');
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css');

	pg_assert_same(false, strpos($guides, 'removeChild'), 'no guide is taken out while a box moves');
	pg_assert_contains("line.style.display = 'none';", $guides, 'a guide not needed is hidden');
	pg_assert_contains('if (!same && !_sdSketchSelDraw(was)) _sdSketchMount(refocus);', $pick, 'a new selection is marked in place');
	pg_assert_contains('_sdProto.sk.drag = true;', $press, 'a press starts a drag');
	pg_assert_contains("if (b && !side && event.pointerType === 'touch' && boxEl.classList.contains('sd-sketch-on')) {", $press, 'a touch near a handle of the selected box takes it');
	pg_assert_contains("if (!done.moved && event.type === 'pointerup') {", $release, 'a tap draws nothing again');
	pg_assert_true(substr_count($release, '_sdSketchMountSoon();') === 2, 'a drawn, moved or resized box waits for the hand to rest');
	pg_assert_contains('if (fold && _sdSketchPlace()) _sdSketchMountSoon();', $changed, 'an arrow nudge moves the box only');
	pg_assert_contains('if (sk.drag) _sdSketchMountSoon();', $soon, 'never during a drag');
	pg_assert_contains('}, SD_SKETCH.rest);', $soon, 'after the rest');
	pg_assert_contains('if (_sdProto.sk.soon) { clearTimeout(_sdProto.sk.soon); _sdProto.sk.soon = 0; }', $mount, 'a full draw makes the waiting one needless');
	pg_assert_contains('.sd-sketch-box.sd-sketch-on { z-index: 1;', $css, 'the selected box and its handles over the others');
}
