<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Design Canvas's layer groups (the GROUP section of
 * assets/js/pg_image_editor_model.js and pg_image_editor_layers.js, run
 * under Node): Group and Ungroup, a press picking the group and a second
 * click a member, order, copy and delete keeping groups whole, the layers
 * list's rows, the help sheet and the context menu; the server's draw
 * taking a grouped layer as an ungrouped one. Skipped where there is no
 * Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/image_draw.php';

// Runs $script with the model as M, L(id, grp) a 10 x 10 box at x = 10 * n
// and ids(list) the ids of a list; with $editor the core and the layers part
// loaded and fake() a bare editor on those layers. Answers the script's
// JSON, null without Node.
function image_editor_groups_node($script, $editor = false)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiegrp');

	if ($file === false) {
		return null;
	}

	$js = PG_FUNCTIONS_DIR . '/assets/js/';
	$head = 'global.window = global; global.document = { activeElement: null, addEventListener: function () {} }; global.navigator = { platform: "Linux" };' . "\n"
		. 'var M = require(' . json_encode($js . 'pg_image_editor_model.js') . '); window.PgImageEditorModel = window.PgImageEditorModel || M;' . "\n"
		. 'function L(id, grp, n) { var o = { id: id, type: "rect", x: 10 * (n || 0), y: 10, w: 10, h: 10, rot: 0, opacity: 1 }; if (grp) { o.grp = grp; } return o; }' . "\n"
		. 'function ids(list) { return list.map(function (l) { return l.id; }); }' . "\n"
		. 'function paths(list) { return list.map(function (l) { return [l.id, l.grp || null]; }); }' . "\n";

	if ($editor) {
		foreach (array('', '_layers', '_menu') as $part) {
			$head .= 'require(' . json_encode($js . 'pg_image_editor' . $part . '.js') . ');' . "\n";
		}

		$head .= 'var Editor = window.PgImageEditor.parts.Editor, none = function () {};' . "\n"
			. 'Editor.layerTypes.rect = Editor.layerTypes.rect || { node: none, paint: none };' . "\n"
			. 'function fake(layers) {' . "\n"
			. ' var e = Object.create(Editor.prototype);' . "\n"
			. ' Editor.inits.forEach(function (fn) { try { fn.call(e); } catch (err) {} });' . "\n"
			. ' e.doc = M.newDocument({ width: 200, height: 100 }); e.doc.layers = layers;' . "\n"
			. ' e.tool = "select"; e.sel = []; e.pointers = {}; e.view = { k: 1, x: 0, y: 0 }; e.history = {}; e.ready = true; e.steps = 0;' . "\n"
			. ' e.stage = { setPointerCapture: none }; e.toDoc = function (x, y) { return [x, y]; }; e.tools = { list: ["select"] };' . "\n"
			. ' e.render = e.renderView = e.renderPanel = none; e.change = function (fn) { this.steps++; fn(this.doc); };' . "\n"
			. ' e.actionState = function () { return { layers: this.sel.length, free: this.sel.length, picture: false, area: false, can: {} }; }; e.importApi = function () { return false; };' . "\n"
			. ' return e;' . "\n"
			. '}' . "\n"
			. 'function ev(x, y, keys) {' . "\n"
			. ' var o = { clientX: x, clientY: y, button: 0, pointerId: 1, pointerType: "mouse", shiftKey: false, altKey: false, ctrlKey: false, metaKey: false, target: { getAttribute: function () { return null; } }, preventDefault: none };' . "\n"
			. ' Object.keys(keys || {}).forEach(function (k) { o[k] = keys[k]; });' . "\n"
			. ' return o;' . "\n"
			. '}' . "\n"
			. 'function press(e, x, y, keys, to) { var u = ev(to ? to[0] : x, to ? to[1] : y, keys); e.startDrag(ev(x, y, keys)); if (e.drag) { if (to) { e.moveDrag(ev(to[0], to[1], keys)); } u.type = "pointerup"; e.endDrag(u); } return e.sel.slice(); }' . "\n";
	}

	file_put_contents($file, $head . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// Group makes the picked layers one group on the place of the topmost;
// grouping a group with another layer nests it; Ungroup takes one level
// apart and the layers stay; fewer than two items make no group.
function test_image_editor_groups_make_and_break()
{
	$r = image_editor_groups_node(
		'var a = [L("a"), L("b"), L("c"), L("d")], out = {};' . "\n"
		. 'var g1 = M.groupMake(a, ["a", "c"], "g1"); out.one = paths(g1);' . "\n"
		. 'var g2 = M.groupMake(g1, ["a", "d"], "g2"); out.nest = paths(g2);' . "\n"
		. 'out.single = M.groupMake(a, ["a"], "g1"); out.sameGroup = M.groupMake(g1, ["a", "c"], "g9");' . "\n"
		. 'out.broken = paths(M.groupBreak(g2, "g2")); out.inner = paths(M.groupBreak(g2, "g1"));' . "\n"
		. 'out.of = [M.groupOfSelection(g2, ["a", "c", "d"]), M.groupOfSelection(g2, ["a", "c"]), M.groupOfSelection(g2, ["a"]), M.groupOfSelection(a, ["a", "b"])];' . "\n"
		. 'out.can = [M.groupCan(a, ["a", "b"]), M.groupCan(g1, ["a", "c"]), M.groupCan(a, ["a"])];' . "\n"
		. 'out.id = [M.groupNewId({ layers: g2 }), M.groupNewId({ layers: [], scenes: [{ layers: [L("x", ["g1"])] }] })];' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(array('b', null), array('a', array('g1')), array('c', array('g1')), array('d', null)), $r['one'], 'a and c grouped where c lay, b below');
	pg_assert_same(array(array('b', null), array('a', array('g2', 'g1')), array('c', array('g2', 'g1')), array('d', array('g2'))), $r['nest'], 'a picks its whole group: g1 nests in g2');
	pg_assert_same(null, $r['single'], 'one layer makes no group');
	pg_assert_same(null, $r['sameGroup'], 'a whole group alone is one item: no group around it');
	pg_assert_same(array(array('b', null), array('a', array('g1')), array('c', array('g1')), array('d', null)), $r['broken'], 'Ungroup of g2 keeps g1 and every place');
	pg_assert_same(array(array('b', null), array('a', array('g2')), array('c', array('g2')), array('d', array('g2'))), $r['inner'], 'Ungroup of g1 keeps its layers in g2');
	pg_assert_same(array('g2', 'g1', '', ''), $r['of'], 'Ungroup takes the group the selection holds whole');
	pg_assert_same(array(array('group' => true, 'ungroup' => false), array('group' => false, 'ungroup' => true), array('group' => false, 'ungroup' => false)), $r['can'], 'what is offered');
	pg_assert_same(array('g3', 'g2'), $r['id'], 'a new group id, the scenes looked at too');
}

// A press picks the outermost group, a click on it the item inside; inside a
// group a press picks a sibling; Shift adds or takes out a whole group; a
// box brings whole groups.
function test_image_editor_groups_pick()
{
	$r = image_editor_groups_node(
		'var g = [L("b"), L("a", ["g2", "g1"]), L("c", ["g2", "g1"]), L("d", ["g2"]), L("e")], out = {};' . "\n"
		. 'out.pick = [M.groupPick(g, [], "a"), M.groupPick(g, ["a"], "c"), M.groupPick(g, ["a"], "d"), M.groupPick(g, ["a", "c"], "d"), M.groupPick(g, ["a", "c", "d"], "e")];' . "\n"
		. 'out.deeper = [M.groupDeeper(g, ["a", "c", "d"], "a"), M.groupDeeper(g, ["a", "c"], "a"), M.groupDeeper(g, ["a"], "a"), M.groupDeeper(g, ["a", "e"], "a")];' . "\n"
		. 'out.toggle = [M.groupToggle(g, ["e"], "a"), M.groupToggle(g, ["e", "a", "c", "d"], "c")];' . "\n"
		. 'out.expand = M.groupExpand(g, ["c", "e"]);' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(array('a', 'c', 'd'), array('c'), array('d'), array('d'), array('e')), $r['pick'], 'outermost group first, then inside the group the selection sits in');
	pg_assert_same(array(array('a', 'c'), array('a'), null, null), $r['deeper'], 'a second click goes one level in');
	pg_assert_same(array(array('e', 'a', 'c', 'd'), array('e')), $r['toggle'], 'Shift adds and takes out a whole group');
	pg_assert_same(array('a', 'c', 'd', 'e'), $r['expand'], 'a box brings whole groups');
}

// Order moves a group whole and a member inside its group; a group left
// with one layer ends; members come together; copies get new groups and
// leave the groups around them; an older document stays as it is.
function test_image_editor_groups_order_tidy_copy()
{
	$r = image_editor_groups_node(
		'var g = [L("b"), L("a", ["g2", "g1"]), L("c", ["g2", "g1"]), L("d", ["g2"]), L("e")], out = {};' . "\n"
		. 'out.up = ids(M.groupReorder(g, ["a"], "up")); out.top = ids(M.groupReorder(g, ["a"], "top"));' . "\n"
		. 'out.whole = ids(M.groupReorder(g, ["a", "c", "d"], "bottom")); out.down = ids(M.groupReorder(g, ["e"], "down"));' . "\n"
		. 'out.inner = ids(M.groupReorder(g, ["a", "c"], "top"));' . "\n"
		. 'out.left = paths(M.groupTidy(g.filter(function (l) { return l.id !== "c"; })));' . "\n"
		. 'out.gather = ids(M.groupTidy([L("a", ["g1"]), L("b"), L("c", ["g1"])]));' . "\n"
		. 'var old = [L("x"), L("y")], same = M.groupTidy(old); out.old = (same[0] === old[0]) && (same[1] === old[1]) && !("grp" in same[0]);' . "\n"
		. 'out.copy = paths(M.groupCopy({ layers: g }, g.filter(function (l) { return ["a", "c"].indexOf(l.id) >= 0; })));' . "\n"
		. 'out.copyAll = paths(M.groupCopy({ layers: g }, g.slice(1, 4)));' . "\n"
		. 'out.rows = M.groupRows(g, {}); out.open = M.groupRows(g, { g2: true });' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('b', 'c', 'a', 'd', 'e'), $r['up'], 'a member goes up inside its group');
	pg_assert_same(array('b', 'c', 'a', 'd', 'e'), $r['top'], 'to the top of its group, not of the stack');
	pg_assert_same(array('a', 'c', 'd', 'b', 'e'), $r['whole'], 'a group moves whole');
	pg_assert_same(array('b', 'e', 'a', 'c', 'd'), $r['down'], 'a layer steps over a whole group');
	pg_assert_same(array('b', 'd', 'a', 'c', 'e'), $r['inner'], 'a group inside a group moves inside it');
	pg_assert_same(array(array('b', null), array('a', array('g2')), array('d', array('g2')), array('e', null)), $r['left'], 'g1 with one layer left ends');
	pg_assert_same(array('b', 'a', 'c'), $r['gather'], 'members come together where the topmost lies');
	pg_assert_true($r['old'], 'a document without groups is left as it is');
	pg_assert_same(array(array('a', array('g3')), array('c', array('g3'))), $r['copy'], 'a copied inner group is a new group, out of g2');
	pg_assert_same(array(array('a', array('g3', 'g4')), array('c', array('g3', 'g4')), array('d', array('g3'))), $r['copyAll'], 'a copied nest keeps its shape with new ids');
	pg_assert_same(array(array('id' => 'e', 'depth' => 0), array('group' => 'g2', 'depth' => 0, 'count' => 3, 'open' => false), array('id' => 'b', 'depth' => 0)), $r['rows'], 'a closed group is one row');
	pg_assert_same(array(array('id' => 'e', 'depth' => 0), array('group' => 'g2', 'depth' => 0, 'count' => 3, 'open' => true), array('id' => 'd', 'depth' => 1),
		array('group' => 'g1', 'depth' => 1, 'count' => 2, 'open' => false), array('id' => 'b', 'depth' => 0)), $r['open'], 'an open group shows its members');
}

// On the editor: a press picks the group and a drag moves it, a click picks
// a member; Ctrl+G and Ctrl+Shift+G are one undo step each; duplicate and
// delete keep groups whole; order keeps a member in its group.
function test_image_editor_groups_editor()
{
	$r = image_editor_groups_node(
		'var e = fake([L("a", null, 1), L("b", null, 4), L("c", null, 8)]), out = {};' . "\n"
		. 'e.sel = ["a", "b"]; e.steps = 0; out.key = e.layerKey(ev(0, 0), true, "g"); out.made = paths(e.doc.layers); out.sel = e.sel.slice(); out.steps = e.steps;' . "\n"
		. 'e.sel = []; out.press = press(e, 15, 15); out.click = press(e, 15, 15); out.again = press(e, 45, 15);' . "\n"
		. 'e.sel = []; press(e, 15, 15, {}, [25, 15]); out.moved = e.doc.layers.map(function (l) { return l.x; });' . "\n"
		. 'out.box = (function () { e.sel = []; e.startDrag(ev(5, 5)); e.moveDrag(ev(22, 22)); var u = ev(22, 22); u.type = "pointerup"; e.endDrag(u); return e.sel.slice(); }());' . "\n"
		. 'e.sel = ["a", "b"]; e.arrangeLayers("bottom"); out.order = ids(e.doc.layers);' . "\n"
		. 'e.sel = ["a", "b"]; e.addLayers(e.selected(), 10); out.dup = paths(e.doc.layers);' . "\n"
		. 'e.sel = ["a", "b"]; out.menu = M.menuList(e.menuState({ at: "stage" })).map(function (i) { return i.name ? i.name + (i.off ? "-" : "") : "|"; }).filter(function (n) { return /group/.test(n); });' . "\n"
		. 'e.steps = 0; out.unkey = e.layerKey(ev(0, 0, { shiftKey: true }), true, "G"); out.un = paths(e.doc.layers); out.unsteps = e.steps;' . "\n"
		. 'e.sel = ["l3"]; e.removeLayers(); out.del = paths(e.doc.layers);' . "\n"
		. 'console.log(JSON.stringify(out));',
		true
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_true($r['key'], 'Ctrl+G is used');
	pg_assert_same(array(array('a', array('g1')), array('b', array('g1')), array('c', null)), $r['made'], 'Ctrl+G groups');
	pg_assert_same(array('a', 'b'), $r['sel'], 'the group stays picked');
	pg_assert_same(1, $r['steps'], 'one undo step');
	pg_assert_same(array('a', 'b'), $r['press'], 'a press on a member picks the group');
	pg_assert_same(array('a'), $r['click'], 'a second click picks the member');
	pg_assert_same(array('b'), $r['again'], 'inside the group a press picks the sibling');
	pg_assert_same(array(20, 50, 80), $r['moved'], 'a drag moves the whole group');
	pg_assert_same(array('a', 'b'), $r['box'], 'a box over one member brings the group');
	pg_assert_same(array('a', 'b', 'c'), $r['order'], 'the group goes to the back whole');
	pg_assert_same(array(array('a', array('g1')), array('b', array('g1')), array('c', null), array('l3', array('g2')), array('l4', array('g2'))), $r['dup'], 'a duplicate is a new group on top');
	pg_assert_same(array('group-', 'ungroup'), $r['menu'], 'the context menu offers Ungroup for a group');
	pg_assert_true($r['unkey'], 'Ctrl+Shift+G is used');
	pg_assert_same(array(array('a', null), array('b', null), array('c', null), array('l3', array('g2')), array('l4', array('g2'))), $r['un'], 'Ungroup takes the picked group apart');
	pg_assert_same(1, $r['unsteps'], 'one undo step');
	pg_assert_same(array(array('a', null), array('b', null), array('c', null), array('l4', null)), $r['del'], 'a group left with one layer ends');
}

// The help sheet lists the two keys with the layer keys.
function test_image_editor_groups_help_sheet()
{
	$r = image_editor_groups_node(
		'var g = M.keyMap(["select"]).filter(function (x) { return x.title === "keysLayers"; })[0];' . "\n"
		. 'console.log(JSON.stringify(g.rows.slice(-2)));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array(array('keys' => array('Mod', 'G'), 'label' => 'keyGroup'), array('keys' => array('Mod', 'Shift', 'G'), 'label' => 'keyUngroup')), $r, 'Group and Ungroup on the help sheet');
}

// The server draws a grouped layer as the same layer ungrouped.
function test_image_editor_groups_server_draw()
{
	$rect = array('type' => 'rect', 'x' => 10, 'y' => 10, 'w' => 30, 'h' => 20, 'color' => '#ff0000', 'fill' => 'solid', 'stroke' => 0);
	$can = array('shapes' => true, 'text' => true, 'icons' => true, 'svg' => true, 'pictures' => array('png', 'jpeg', 'webp'), 'paused' => 0, 'engine' => 'test');
	$doc = function ($layers) {
		return array('artboard' => array('width' => 100, 'height' => 60, 'background' => '#ffffff'), 'layers' => $layers);
	};
	$plain = pg_image_draw_plan($doc(array($rect, array_merge($rect, array('x' => 50)))), $can);
	$grouped = pg_image_draw_plan($doc(array(array_merge($rect, array('grp' => array('g1'))), array_merge($rect, array('x' => 50, 'grp' => array('g1'))))), $can);

	pg_assert_same('', $grouped['error'], 'a grouped layer is drawn');
	pg_assert_same($plain, $grouped, 'the same plan as ungrouped');
	pg_assert_true(pg_image_draw_known(array('id' => 'l1', 'grp' => array('g1', 'g2')), array()), 'grp is a known field');
}
