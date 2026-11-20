<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a Design Canvas group's own size, turn, opacity and Animate
 * keys (the GROUP section of assets/js/pg_image_editor_model.js, the
 * layers, keyframes and actions parts, run under Node): the group's look
 * kept on its members (gfx), its box resized and turned as one, its keys
 * composed over each member's own, Group and Ungroup on the bar over the
 * object and the phone's bar; the server's draw taking a group's opacity
 * as the same opacity on its layers. Skipped where there is no Node.
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

// Runs $script with the model as M, L(id, grp, n) a 10 x 10 box at x =
// 10 * n and near(v) a number rounded to 3 places; with $editor the core,
// layers, actions and keyframes parts loaded and fake() a bare editor on
// those layers. Answers the script's JSON, null without Node.
function image_editor_groups_fx_node($script, $editor = false)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiegfx');

	if ($file === false) {
		return null;
	}

	$js = PG_FUNCTIONS_DIR . '/assets/js/';
	$head = 'global.window = global; global.document = { activeElement: null, addEventListener: function () {} }; global.navigator = { platform: "Linux" };' . "\n"
		. 'var M = require(' . json_encode($js . 'pg_image_editor_model.js') . '); window.PgImageEditorModel = window.PgImageEditorModel || M;' . "\n"
		. 'function L(id, grp, n) { var o = { id: id, type: "rect", x: 10 * (n || 0), y: 10, w: 10, h: 10, rot: 0, opacity: 1 }; if (grp) { o.grp = grp; } return o; }' . "\n"
		. 'function near(v) { return Math.round(v * 1000) / 1000; }' . "\n"
		. 'function box(l) { return [near(l.x), near(l.y), near(l.w), near(l.h), near(l.rot || 0)]; }' . "\n";

	if ($editor) {
		foreach (array('', '_layers', '_menu', '_actions', '_motion', '_tracks', '_keyframes') as $part) {
			$head .= 'require(' . json_encode($js . 'pg_image_editor' . $part . '.js') . ');' . "\n";
		}

		$head .= 'var Editor = window.PgImageEditor.parts.Editor, none = function () {};' . "\n"
			. 'Editor.layerTypes.rect = Editor.layerTypes.rect || { node: none, paint: none };' . "\n"
			. 'function fake(layers) {' . "\n"
			. ' var e = Object.create(Editor.prototype);' . "\n"
			. ' Editor.inits.forEach(function (fn) { try { fn.call(e); } catch (err) {} });' . "\n"
			. ' e.doc = M.newDocument({ width: 200, height: 100 }); e.doc.layers = layers;' . "\n"
			. ' e.tool = "select"; e.sel = []; e.pointers = {}; e.view = { k: 1, x: 0, y: 0 }; e.history = {}; e.ready = true; e.steps = []; e.time = null;' . "\n"
			. ' e.stage = { setPointerCapture: none }; e.toDoc = function (x, y) { return [x, y]; }; e.tools = { list: ["select"] };' . "\n"
			. ' e.render = e.renderView = e.renderPanel = e.applyMotion = e.drawKeyPath = none; e.motionEnd = function () { return 5000; };' . "\n"
			. ' e.change = function (fn, key) { if (!key || (this.steps[this.steps.length - 1] !== key)) { this.steps.push(key || "step"); } fn(this.doc); };' . "\n"
			. ' return e;' . "\n"
			. '}' . "\n"
			. 'function ev(x, y, h, keys) {' . "\n"
			. ' var o = { clientX: x, clientY: y, button: 0, pointerId: 1, pointerType: "mouse", shiftKey: false, altKey: false, ctrlKey: false, metaKey: false, target: { getAttribute: function () { return h || null; } }, preventDefault: none };' . "\n"
			. ' Object.keys(keys || {}).forEach(function (k) { o[k] = keys[k]; });' . "\n"
			. ' return o;' . "\n"
			. '}' . "\n"
			. 'function drag(e, h, from, to) { e.startDrag(ev(from[0], from[1], h)); e.moveDrag(ev(to[0], to[1], h)); var u = ev(to[0], to[1], h); u.type = "pointerup"; e.endDrag(u); }' . "\n";
	}

	file_put_contents($file, $head . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// A group's own opacity and keys live on its members (gfx), the same on
// each; an opacity of 1 and no keys leave nothing; nested groups multiply;
// Ungroup, a group's end and a copy carry gfx with the group.
function test_image_editor_groups_fx_store()
{
	$r = image_editor_groups_fx_node(
		'var g = [L("b"), L("a", ["g2", "g1"]), L("c", ["g2", "g1"]), L("d", ["g2"]), L("e")], out = {};' . "\n"
		. 'out.none = M.groupFx(g, "g1");' . "\n"
		. 'var h = M.groupFxSet(M.groupFxSet(g, "g1", { opacity: 0.5 }), "g2", { opacity: 0.4 }); out.gfx = h.map(function (l) { return l.gfx || null; });' . "\n"
		. 'out.fx = M.groupFx(h, "g1"); out.alpha = h.map(function (l) { return near(M.groupAlpha(l)); });' . "\n"
		. 'out.look = [near(M.groupLook(h[1]).opacity), M.groupLook(h[0]) === h[0], h[1].opacity];' . "\n"
		. 'out.back = M.groupFxSet(h, "g1", { opacity: 1 }).map(function (l) { return l.gfx || null; });' . "\n"
		. 'out.broken = M.groupBreak(h, "g2").map(function (l) { return l.gfx || null; });' . "\n"
		. 'out.ended = M.groupTidy(h.filter(function (l) { return l.id !== "c"; })).map(function (l) { return l.gfx || null; });' . "\n"
		. 'out.copy = M.groupCopy({ layers: h }, h.slice(1, 3)).map(function (l) { return [l.grp, l.gfx || null]; });' . "\n"
		. 'out.picked = [M.groupPicked(h, ["a", "c", "d"]), M.groupPicked(h, ["a", "c"]), M.groupPicked(h, ["a"]), M.groupPicked(h, ["a", "c", "d", "e"])];' . "\n"
		. 'out.keyed = M.groupKeySet(h, "g1", 1000, { x: 30 }, 5000)[1].gfx.g1.keys.map(function (k) { return [k.t, k.x]; });' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('opacity' => 1, 'keys' => array()), $r['none'], 'a group without a look of its own');
	pg_assert_same(array(null, array('g1' => array('opacity' => 0.5), 'g2' => array('opacity' => 0.4)), array('g1' => array('opacity' => 0.5), 'g2' => array('opacity' => 0.4)), array('g2' => array('opacity' => 0.4)), null), $r['gfx'], 'every member carries its groups');
	pg_assert_same(array('opacity' => 0.5, 'keys' => array()), $r['fx'], 'the group read back');
	pg_assert_same(array(1, 0.2, 0.2, 0.4, 1), $r['alpha'], 'nested groups multiply');
	pg_assert_same(array(0.2, true, 1), $r['look'], 'the drawn copy fades; the layer keeps its own opacity');
	pg_assert_same(array(null, array('g2' => array('opacity' => 0.4)), array('g2' => array('opacity' => 0.4)), array('g2' => array('opacity' => 0.4)), null), $r['back'], 'an opacity of 1 leaves nothing');
	pg_assert_same(array(null, array('g1' => array('opacity' => 0.5)), array('g1' => array('opacity' => 0.5)), null, null), $r['broken'], 'Ungroup drops the group\'s look');
	pg_assert_same(array(null, array('g2' => array('opacity' => 0.4)), array('g2' => array('opacity' => 0.4)), null), $r['ended'], 'an ended group leaves no look');
	pg_assert_same(array(array(array('g3'), array('g3' => array('opacity' => 0.5))), array(array('g3'), array('g3' => array('opacity' => 0.5)))), $r['copy'], 'a copy takes the look under the new id');
	pg_assert_same(array('g2', 'g1', '', ''), $r['picked'], 'a selection that is one whole group');
	pg_assert_same(array(array(0, 0), array(1000, 30)), $r['keyed'], 'a group key, from rest at 0');
}

// A group's box resized from a corner scales every member round the
// opposite corner, proportions kept; a turn moves their centres round the
// box's centre and adds to their own turns.
function test_image_editor_groups_fx_size_turn()
{
	$r = image_editor_groups_fx_node(
		'var g = [L("a", ["g1"], 0), L("b", ["g1"], 3)], out = {};' . "\n"
		. 'out.box = M.groupBox(g, "g1");' . "\n"
		. 'out.scaled = M.groupScale(g, M.groupBox(g, "g1"), { x: 0, y: 10, w: 80, h: 20 }).map(box);' . "\n"
		. 'out.turned = M.groupTurn(g, 20, 15, 90).map(box);' . "\n"
		. 'out.turnBack = M.groupTurn(M.groupTurn(g, 20, 15, 170), 20, 15, 30).map(function (l) { return near(l.rot); });' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('x' => 0, 'y' => 10, 'w' => 40, 'h' => 10), $r['box'], 'the box round the members');
	pg_assert_same(array(array(0, 10, 20, 20, 0), array(60, 10, 20, 20, 0)), $r['scaled'], 'twice the size from the top left');
	pg_assert_same(array(array(15, -5, 10, 10, 90), array(15, 25, 10, 10, 90)), $r['turned'], 'a quarter turn round the box\'s centre');
	pg_assert_same(array(-160, -160), $r['turnBack'], 'turns stay within -180..180');
}

// A group's keys move, turn, scale and fade its members round the group's
// centre, over each member's own keys; at rest nothing moves; nested
// groups compose; a group key counts as motion.
function test_image_editor_groups_fx_motion()
{
	$r = image_editor_groups_fx_node(
		'var g = [L("a", ["g1"], 0), L("b", ["g1"], 3), L("c")], out = {};' . "\n"
		. 'var k = M.groupFxSet(g, "g1", { keys: [{ t: 0, x: 0, y: 0, scale: 1, rot: 0, opacity: 1 }, { t: 1000, x: 0, y: 0, scale: 2, rot: 90, opacity: 0.5 }] });' . "\n"
		. 'var st = function (layers, l, t) { var s = M.groupMotionAt(layers, l, t, 5000); return [near(s.x), near(s.y), near(s.scale), near(s.rot), near(s.opacity)]; };' . "\n"
		. 'out.rest = st(k, k[0], null); out.end = [st(k, k[0], 1000), st(k, k[1], 1000), st(k, k[2], 1000)];' . "\n"
		. 'var own = M.groupFxSet(g, "g1", { keys: [{ t: 0, x: 0, y: 0 }, { t: 1000, x: 10, y: 0 }] }); own[0].anim = { keys: [{ t: 0, x: 0, y: 0 }, { t: 1000, x: 0, y: 5 }] };' . "\n"
		. 'out.own = st(own, own[0], 1000);' . "\n"
		. 'var nest = M.groupFxSet(M.groupMake(k, ["a", "b", "c"], "g2"), "g2", { keys: [{ t: 0, x: 0, y: 0 }, { t: 1000, x: 0, y: 7 }] });' . "\n"
		. 'out.nest = st(nest, nest.filter(function (l) { return l.id === "a"; })[0], 1000);' . "\n"
		. 'out.has = [M.hasMotion({ layers: g }), M.hasMotion({ layers: k })];' . "\n"
		. 'var kl = M.groupKeyLayer(k, "g1"); out.keyLayer = [kl.id, kl.x, kl.y, kl.w, kl.h, M.keyList(kl).length];' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(0, 0, 1, 0, 1), $r['rest'], 'at rest a group key does nothing');
	pg_assert_same(array(array(15, -30, 2, 90, 0.5), array(-15, 30, 2, 90, 0.5), array(0, 0, 1, 0, 1)), $r['end'], 'members turn and grow round the group\'s centre, fade with it; others stay');
	pg_assert_same(array(10, 5, 1, 0, 1), $r['own'], 'a member keeps its own keys inside the group');
	pg_assert_same(array(15, -23, 2, 90, 0.5), $r['nest'], 'the outer group\'s keys go over the inner one\'s');
	pg_assert_same(array(false, true), $r['has'], 'a group key is motion');
	pg_assert_same(array('group:g1', 0, 10, 40, 10, 2), $r['keyLayer'], 'the group as the Path tab reads it');
}

// The bar over the object and the phone's bar offer Group for several
// layers and Ungroup for a picked group.
function test_image_editor_groups_fx_bars()
{
	$r = image_editor_groups_fx_node(
		'var names = function (s) { return M.actionList(s).map(function (a) { return a.name; }); }, out = {};' . "\n"
		. 'out.object = names({ place: "object", layers: 2, free: 2, group: true });' . "\n"
		. 'out.objectUn = names({ place: "object", layers: 2, free: 2, ungroup: true });' . "\n"
		. 'out.one = names({ place: "object", layers: 1, free: 1, kind: "rect" });' . "\n"
		. 'out.phone = names({ place: "stage", layers: 2, free: 2, move: 2, group: true });' . "\n"
		. 'out.phoneUn = names({ place: "stage", layers: 3, free: 3, move: 3, ungroup: true, sheet: true });' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('group', 'duplicate', 'remove', 'lock', 'more'), $r['object'], 'Group over two layers');
	pg_assert_same(array('ungroup', 'duplicate', 'remove', 'lock', 'more'), $r['objectUn'], 'Ungroup over a group');
	pg_assert_same(array('duplicate', 'remove', 'lock', 'more'), $r['one'], 'nothing new for one layer');
	pg_assert_same(array('group', 'duplicate', 'remove', 'nudgeLeft', 'nudgeUp', 'nudgeDown', 'nudgeRight'), $r['phone'], 'Group on the phone\'s bar');
	pg_assert_same(array('options', 'ungroup', 'duplicate', 'remove', 'nudgeLeft', 'nudgeUp', 'nudgeDown', 'nudgeRight'), $r['phoneUn'], 'Ungroup on the phone\'s bar');
}

// On the editor: a picked group's corner resizes its members as one and its
// turning handle turns them, one undo step each; its opacity goes to the
// group; the Path tab's key and a drag at a moment key the group; the bar's
// state offers Group and Ungroup.
function test_image_editor_groups_fx_editor()
{
	$r = image_editor_groups_fx_node(
		'var e = fake([L("a", ["g1"], 0), L("b", ["g1"], 3), L("c", null, 8)]), out = {};' . "\n"
		. 'e.sel = ["a", "b"]; drag(e, "gse", [40, 20], [80, 30]); out.sized = e.doc.layers.map(box); out.sizeSteps = e.steps.slice();' . "\n"
		. 'e.steps = []; drag(e, "grot", [40, -10], [100, 20]); out.turned = e.doc.layers.map(function (l) { return near(l.rot); }); out.turnSteps = e.steps.slice();' . "\n"
		. 'e.steps = []; e.setOpacity(e.selected(), 0.5); out.op = e.doc.layers.map(function (l) { return [l.opacity, l.gfx || null]; });' . "\n"
		. 'e.sel = ["c"]; e.setOpacity(e.selected(), 0.3); out.opOne = e.doc.layers[2].opacity;' . "\n"
		. 'e.sel = ["a", "b"]; out.target = e.keyTarget(e.selected()).id;' . "\n"
		. 'e.setKey("group:g1", 1000, { y: 12 }); out.keys = M.groupFx(e.doc.layers, "g1").keys.map(function (k) { return [k.t, k.y]; });' . "\n"
		. 'e.editKeys("group:g1", function () { return []; }); out.cleared = e.doc.layers.map(function (l) { return l.gfx || null; });' . "\n"
		. 'e.editKeys("group:g1", function () { return []; }); e.tool = "motion"; e.time = 1000; e.playing = false; e.motionNow = function () { return 1000; }; e.motionStart = function () { return 0; };' . "\n"
		. 'e.sel = ["a", "b"]; e.steps = []; e.keyDown(ev(40, -10)); e.keyMoveDrag(ev(44, -7)); e.keyEndDrag(); out.dragKeys = M.groupFx(e.doc.layers, "g1").keys.map(function (k) { return [k.t, k.x, k.y]; }); out.dragOwn = e.doc.layers.map(function (l) { return !!l.anim; }); out.dragSteps = e.steps.slice();' . "\n"
		. 'e.tool = "select"; e.time = null; e.sel = ["a", "b"]; var s = e.actionState(); out.state = [s.group, s.ungroup]; e.sel = ["b", "c"]; s = e.actionState(); out.state2 = [s.group, s.ungroup];' . "\n"
		. 'console.log(JSON.stringify(out));',
		true
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(array(0, 10, 20, 20, 0), array(60, 10, 20, 20, 0), array(80, 10, 10, 10, 0)), $r['sized'], 'the corner doubles the group, proportions kept');
	pg_assert_same(array('drag'), $r['sizeSteps'], 'one undo step');
	pg_assert_same(array(90, 90, 0), $r['turned'], 'the turning handle turns the group');
	pg_assert_same(array('drag'), $r['turnSteps'], 'one undo step');
	pg_assert_same(array(array(1, array('g1' => array('opacity' => 0.5))), array(1, array('g1' => array('opacity' => 0.5))), array(1, null)), $r['op'], 'a group\'s opacity is its own');
	pg_assert_same(0.3, $r['opOne'], 'a layer\'s opacity stays its own');
	pg_assert_same('group:g1', $r['target'], 'the Path tab keys the group');
	pg_assert_same(array(array(0, 0), array(1000, 12)), $r['keys'], 'a key on the group');
	pg_assert_same(array(array('g1' => array('opacity' => 0.5)), array('g1' => array('opacity' => 0.5)), null), $r['cleared'], 'clearing the keys keeps the opacity');
	pg_assert_same(array(array(0, 0, 0), array(1000, 4, 3)), $r['dragKeys'], 'a drag at a moment keys the picked group');
	pg_assert_same(array(false, false, false), $r['dragOwn'], 'the members\' own keys are left alone');
	pg_assert_same(array('drag'), $r['dragSteps'], 'one undo step');
	pg_assert_same(array(false, true), $r['state'], 'Ungroup for a picked group');
	pg_assert_same(array(true, false), $r['state2'], 'Group for a layer and another');
}

// The server draws a group's opacity as the same opacity on its layers.
function test_image_editor_groups_fx_server_draw()
{
	$rect = array('type' => 'rect', 'x' => 10, 'y' => 10, 'w' => 30, 'h' => 20, 'color' => '#ff0000', 'fill' => 'solid', 'stroke' => 0);
	$can = array('shapes' => true, 'text' => true, 'icons' => true, 'svg' => true, 'pictures' => array('png', 'jpeg', 'webp'), 'paused' => 0, 'engine' => 'test');
	$doc = function ($layers) {
		return array('artboard' => array('width' => 100, 'height' => 60, 'background' => '#ffffff'), 'layers' => $layers);
	};
	$fx = array('g1' => array('opacity' => 0.5), 'g2' => array('opacity' => 0.5, 'keys' => array()));
	$plain = pg_image_draw_plan($doc(array(array_merge($rect, array('opacity' => 0.2)), array_merge($rect, array('x' => 50, 'opacity' => 0.5)))), $can);
	$grouped = pg_image_draw_plan($doc(array(
		array_merge($rect, array('opacity' => 0.8, 'grp' => array('g2', 'g1'), 'gfx' => $fx)),
		array_merge($rect, array('x' => 50, 'grp' => array('g2'), 'gfx' => array('g2' => $fx['g2'], 'g9' => array('opacity' => 0)))),
	)), $can);

	pg_assert_same('', $grouped['error'], 'a group with a look is drawn');
	pg_assert_same($plain, $grouped, 'the group\'s opacity multiplies its layers\' own, a stale entry ignored');
	pg_assert_true(pg_image_draw_known(array('id' => 'l1', 'grp' => array('g1'), 'gfx' => $fx), array()), 'gfx is a known field');
}
