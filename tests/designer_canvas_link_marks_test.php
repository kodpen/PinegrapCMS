<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the link marks on the visual designer's canvas: a broken link
 * or a link without a target of the open page gets a mark at its element,
 * and the mark opens the link field of the Options panel. The marks come
 * from the Prototype view's own data (PGProtoHotspots design(), the
 * prototype_map read, mark(), canvasMarks()), asked once per set of
 * addresses; a later run drops the answer of an earlier one. They are drawn
 * over the canvas in the editor's document, never inside the canvas.
 * _sdLinkMarksRun(), _sdLinkMarkAt() and _sdLinkFieldOf() are cut out of
 * assets/js/style_designer.src.js and run under Node (skipped on a machine
 * without Node); the rest is read from the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!function_exists('pg_link_maps_fold')) {
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/link_resolve.php');
}

function designer_canvas_link_marks_test_source()
{
	return str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js'));
}

// The body of the named function, up to the next function at the same indentation.
function designer_canvas_link_marks_test_function($source, $name)
{
	$from = strpos($source, "\n    function " . $name . '(');
	pg_assert_true($from !== false, 'the designer has ' . $name . '()');

	if ($from === false) {
		return '';
	}

	$to = strpos($source, "\n    function ", $from + 10);
	pg_assert_true($to !== false, $name . '() has an end');

	return substr($source, $from, $to - $from);
}

// The literal assigned to `var <name> = …;` on one line.
function designer_canvas_link_marks_test_var($source, $name)
{
	$found = preg_match('/\n    var ' . $name . ' = [^\n]*;/', $source, $m);
	pg_assert_same(1, $found, 'the designer has ' . $name);

	return $found ? trim($m[0]) : '';
}

// Runs $code under Node with the canvas core, the resolver and the
// collector loaded (window.PGProtoHotspots); its `out` comes back decoded.
// Null without Node.
function designer_canvas_link_marks_test_node($code)
{
	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$script = "var window = {};\n";

	foreach (array('pg_graph_canvas', 'pg_link_resolve', 'pg_proto_hotspots') as $name) {
		$script .= file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/' . $name . '.js') . "\n";
	}

	$file = tempnam(sys_get_temp_dir(), 'pglm');
	file_put_contents($file, $script . "var out = null;\n" . $code . "\nconsole.log(JSON.stringify(out));");
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	unlink($file);
	pg_assert_same(0, $status, implode("\n", array_slice($out, -8)));

	return json_decode(implode("\n", $out), true);
}

function designer_canvas_link_marks_test_link($id, $href)
{
	return array('_id' => $id, 'type' => 'content', 'props' => array('contentType' => 'link', 'text' => 'L' . $id, 'href' => $href), 'children' => array());
}

// The open tab's marks follow the edits: the links without a target at
// once on another tab, the broken ones once the read answers; the read is
// asked for the addresses and the kept pages only (a menu or region draws
// no mark), once per set of addresses, never twice while one is on its
// way; an answer of an earlier set is dropped; a failed read leaves the
// empty links; nothing is asked while the Prototype view is open or for a
// page with no address.
function test_designer_canvas_link_marks_read_once_per_request()
{
	$source = designer_canvas_link_marks_test_source();
	$maps = array(
		'pages' => array(
			'home' => array('id' => 1, 'type' => 'standard', 'layout_type' => 'custom', 'folder' => 1),
			'about' => array('id' => 2, 'type' => 'standard', 'layout_type' => 'custom', 'folder' => 1),
			'old' => array('id' => 5, 'type' => 'standard', 'layout_type' => 'custom', 'folder' => 9),
		),
		'files' => array(), 'short_links' => array(), 'products' => array(), 'product_groups' => array(),
		'home_page_id' => 1,
	);
	$read = array(
		'status' => 'success',
		'env' => array('host' => 'example.com', 'path' => '/', 'software_directory' => 'pinegrap'),
		'maps' => pg_link_maps_fold($maps),
		'form_names' => new stdClass(),
		'hidden' => array(),
		'draft_folder' => 0,
		'bin_folders' => array(9),
		'menus' => new stdClass(),
		'regions' => new stdClass(),
	);
	$tree = array('_id' => 'r', 'type' => 'root', 'props' => array(), 'children' => array(
		designer_canvas_link_marks_test_link('a', '/old'),
		designer_canvas_link_marks_test_link('b', '/about'),
		designer_canvas_link_marks_test_link('c', ''),
		array('_id' => 'm', 'type' => 'region', 'props' => array('regionType' => 'menu', 'regionName' => 'Top'), 'children' => array()),
	));
	$other = array('_id' => 'r2', 'type' => 'root', 'props' => array(), 'children' => array(designer_canvas_link_marks_test_link('e', '#')));
	$code = designer_canvas_link_marks_test_var($source, 'SD_LINK_MARKS') . "\n"
		. designer_canvas_link_marks_test_var($source, '_sdLinkMarks') . "\n"
		. designer_canvas_link_marks_test_function($source, '_sdLinkMarksRun') . "\n"
		. designer_canvas_link_marks_test_function($source, '_sdLinkMarksShow') . "\n"
		. 'var read = ' . json_encode($read) . ', tree = ' . json_encode($tree) . ', other = ' . json_encode($other) . ';'
		. ' var _activeKey = "p1", _sharedCache = {}, _sdProto = { open: false }, placed = 0, asked = [];'
		. ' function _sdProtoTabs() { return [{ key: "p1", page_id: 1, page_name: "home", tree: _activeKey === "p1" ? tree : null }, { key: "p2", page_id: 2, page_name: "about", tree: _activeKey === "p2" ? other : null }]; }'
		. ' function _sdLinkMarksPlace() { placed++; }'
		. ' function _pgApiPost(sub, req) { var call = { sub: sub, req: req }; asked.push(call); return { then: function (ok, fail) { call.ok = ok; call.fail = fail; } }; }'
		. ' function list() { return _sdLinkMarks.list.map(function (m) { return m.nodeId + " " + m.mark; }); }'
		. ' var o = {};'
		. ' _sdLinkMarksRun(); o.first = list(); o.asked = asked.length; o.sub = asked[0].sub; o.req = asked[0].req;'
		. ' asked[0].ok(read); o.answered = list(); o.key = _sdLinkMarks.key;'
		. ' _sdLinkMarksRun(); o.cached = [asked.length, list()];'
		. ' tree.children[0].props.href = "/about"; _sdLinkMarksRun(); o.kept = [asked.length, list()];'
		. ' tree.children[2].props.href = "/nothing"; _sdLinkMarksRun(); _sdLinkMarksRun(); o.once = asked.length;'
		. ' asked[1].ok(read); o.dropped = list();'
		. ' asked[2].ok(read); o.latest = list();'
		. ' tree.children[1].props.href = "/gone"; _sdLinkMarksRun(); asked[3].ok({ status: "error", message: "No" }); o.failed = [asked.length, list()];'
		. ' _sdLinkMarksRun(); o.retried = asked.length; asked[4].fail(new Error("x")); o.rejected = list();'
		. ' tree.children[1].props.href = "/new"; _sdProto.open = true; _sdLinkMarksRun(); o.viewOpen = asked.length; _sdProto.open = false;'
		. ' _activeKey = "p2"; _sdLinkMarksRun(); o.other = [asked.length, _sdLinkMarks.key, list()];'
		. ' o.placed = placed > 0;'
		. ' out = o;';
	$out = designer_canvas_link_marks_test_node($code);

	if ($out === null) {
		return;
	}

	pg_assert_same(array('c empty'), $out['first'], 'another tab: its link without a target at once');
	pg_assert_same(1, $out['asked'], 'one read');
	pg_assert_same('prototype_map', $out['sub'], 'the Prototype view\'s read, no endpoint of its own');
	pg_assert_same(array('hrefs' => array('/old', '/about'), 'page_ids' => array(), 'menus' => array(), 'regions' => array()), $out['req'], 'the addresses and kept pages, no menu');
	pg_assert_same(array('a broken', 'c empty'), $out['answered'], 'the broken link once the read answered');
	pg_assert_same('p1', $out['key'], 'the marks of the open tab');
	pg_assert_same(array(1, array('a broken', 'c empty')), $out['cached'], 'the same addresses are not asked again');
	pg_assert_same(array(2, array('a broken', 'c empty')), $out['kept'], 'new addresses: asked, the marks kept meanwhile');
	pg_assert_same(3, $out['once'], 'a set on its way is not asked twice');
	pg_assert_same(array('a broken', 'c empty'), $out['dropped'], 'the answer of an earlier set is dropped');
	pg_assert_same(array('c broken'), $out['latest'], 'the latest set\'s answer drawn');
	pg_assert_same(array(4, array()), $out['failed'], 'a failed read: no link called broken');
	pg_assert_same(5, $out['retried'], 'a failed read is asked again on the next run');
	pg_assert_same(array(), $out['rejected'], 'a read that did not reach the server: the same');
	pg_assert_same(5, $out['viewOpen'], 'nothing asked while the Prototype view is open');
	pg_assert_same(array(5, 'p2', array('e empty')), $out['other'], 'a page with no address asks nothing');
	pg_assert_true($out['placed'], 'every change is drawn');
}

// Where a mark goes: at the right edge of the link's box, 10 px under its
// top (its middle when lower), scaled with the canvas, kept 9 px inside
// the layer; none for a link out of view.
function test_designer_canvas_link_marks_place()
{
	$source = designer_canvas_link_marks_test_source();
	$out = designer_canvas_link_marks_test_node(designer_canvas_link_marks_test_function($source, '_sdLinkMarkAt')
		. ' function box(l, t, r, b) { return { left: l, top: t, right: r, bottom: b }; }'
		. ' out = ['
		. ' _sdLinkMarkAt(box(100, 50, 200, 90), 0, 0, 1, 800, 600),'
		. ' _sdLinkMarkAt(box(100, 50, 200, 56), 0, 0, 1, 800, 600),'
		. ' _sdLinkMarkAt(box(100, 50, 200, 90), 20, -30, 0.5, 800, 600),'
		. ' _sdLinkMarkAt(box(0, 0, 800, 40), 0, 0, 1, 800, 600),'
		. ' _sdLinkMarkAt(box(100, -60, 200, 40), 0, 0, 1, 800, 600),'
		. ' _sdLinkMarkAt(box(100, 700, 200, 740), 0, 0, 1, 800, 600),'
		. ' _sdLinkMarkAt(box(100, -60, 200, -10), 0, 0, 1, 800, 600)'
		. ' ];');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('x' => 200, 'y' => 60), $out[0], 'the right edge, 10 px under the top');
	pg_assert_same(array('x' => 200, 'y' => 53), $out[1], 'the middle of a low box');
	pg_assert_same(array('x' => 120, 'y' => 0 + 9), $out[2], 'scaled and moved with the canvas, kept inside');
	pg_assert_same(array('x' => 791, 'y' => 10), $out[3], 'a box as wide as the canvas: kept inside');
	pg_assert_same(array('x' => 200, 'y' => 9), $out[4], 'a box cut at the top: at the top');
	pg_assert_same(null, $out[5], 'below the view: none');
	pg_assert_same(null, $out[6], 'above the view: none');
}

// The link field a mark opens: a text's picked link, else the element's
// own address field, else, for a link kept by page id, its button back to
// a typed address, else the notice of a bound address; none for none.
function test_designer_canvas_link_marks_field()
{
	$source = designer_canvas_link_marks_test_source();
	$out = designer_canvas_link_marks_test_node(designer_canvas_link_marks_test_function($source, '_sdLinkFieldOf')
		. ' function panel(have) { return { querySelector: function (s) { return have.indexOf(s) >= 0 ? s : null; } }; }'
		. ' var all = ["#sd-inner-href", "input[data-prop=\"href\"]", ".sd-bound-href-note [data-sd-page-unlink]", ".sd-bound-href-note"];'
		. ' out = [all.slice(0), all.slice(1), all.slice(2), all.slice(3), []].map(function (have) { return _sdLinkFieldOf(panel(have)); }).concat([_sdLinkFieldOf(null)]);');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('#sd-inner-href', 'input[data-prop="href"]', '.sd-bound-href-note [data-sd-page-unlink]', '.sd-bound-href-note', null, null), $out, 'in this order');
}

// The marks are run after every redraw and when the Prototype view opens
// or closes, hidden while it is open; drawn in the editor's document over
// the canvas, never inside the canvas; a click selects the link (a text's
// link picked inside it) and focuses its field in the Options panel, a
// docked panel opened. The colours are the editor's tokens.
function test_designer_canvas_link_marks_wiring()
{
	$source = designer_canvas_link_marks_test_source();
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/style_designer.css');
	$render = designer_canvas_link_marks_test_function($source, 'render');
	$state = designer_canvas_link_marks_test_function($source, '_sdProtoButtonState');
	$draw = designer_canvas_link_marks_test_function($source, '_sdLinkMarksDraw');
	$open = designer_canvas_link_marks_test_function($source, '_sdLinkMarkOpen');
	$run = designer_canvas_link_marks_test_function($source, '_sdLinkMarksRun');

	pg_assert_contains("scheduleValidation();\n        _sdLinkMarksSoon();", $render, 'after every redraw');
	pg_assert_contains('_sdLinkMarksSoon();', $state, 'when the Prototype view opens or closes');
	pg_assert_contains('_sdProto.open', $draw, 'hidden while the Prototype view is open');
	pg_assert_contains("closest('.sd-canvas-wrapper')", $draw, 'the layer sits beside the canvas frame');
	pg_assert_true(strpos($draw, 'canvasDoc.createElement') === false, 'nothing is added to the canvas');
	pg_assert_contains('H.design(', $run, 'the Prototype view\'s collector');
	pg_assert_contains('H.canvasRequest(designed)', $run, 'the read asked for what can be broken');
	pg_assert_contains("_pgApiPost('prototype_map', ", $run, 'and its read');
	pg_assert_contains('_sdTextInnerSelect(node, path);', $open, 'the link selected, a text\'s link picked inside it');
	pg_assert_contains("_sdRcolShow('options');", $open, 'the Options panel shown');
	pg_assert_contains("_sdDockSetOpen('right', true);", $open, 'a docked panel opened');
	pg_assert_contains('_sdOptReveal(', $open, 'a field in a closed card or another tab drawn');
	pg_assert_contains('field.focus(', $open, 'the field focused');
	pg_assert_contains("_sdT('{var:1} “{var:2}”: open its link field', [m.text(), link.label])", $draw, 'the mark says what it does');

	foreach (array('.sd-lm-layer {', '.sd-lm {', '.sd-lm.sd-lm-empty {', '.sd-lm:focus-visible') as $selector) {
		pg_assert_contains($selector, $css, 'styled: ' . $selector);
	}

	pg_assert_same(1, preg_match('/\.sd-lm \{[^}]*background: var\(--sd-danger\)/', $css), 'a broken link red');
	pg_assert_same(1, preg_match('/\.sd-lm\.sd-lm-empty \{[^}]*background: var\(--sd-warning\)/', $css), 'a link without a target amber');
	pg_assert_same(1, preg_match('/\.sd-lm-layer \{[^}]*pointer-events: none/', $css), 'the layer lets every other click through');
}
