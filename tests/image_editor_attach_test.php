<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a Design Canvas layer attached to another layer or a group
 * (the ATTACH section of assets/js/pg_image_editor_model.js, the layers,
 * menu and keyframes parts, run under Node): Attach to and Detach, the
 * circle refused, the target's move, turn and size at every moment with
 * the follower's own keys on top, a group as target, a deleted target
 * letting go in place, the target's rest move carrying its followers;
 * the server's draw reading att as a known field. Skipped where there is
 * no Node.
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
// 10 * n, near(v) a number rounded to 3 places, box(l) its place and K(t,
// patch) layer keys from rest at 0 to patch at t; with $editor the core,
// layers, menu, actions and keyframes parts loaded and fake() a bare editor
// on those layers. Answers the script's JSON, null without Node.
function image_editor_attach_node($script, $editor = false)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgieatt');

	if ($file === false) {
		return null;
	}

	$js = PG_FUNCTIONS_DIR . '/assets/js/';
	$head = 'global.window = global; global.document = { activeElement: null, addEventListener: function () {} }; global.navigator = { platform: "Linux" };' . "\n"
		. 'var M = require(' . json_encode($js . 'pg_image_editor_model.js') . '); window.PgImageEditorModel = window.PgImageEditorModel || M;' . "\n"
		. 'function L(id, grp, n) { var o = { id: id, type: "rect", x: 10 * (n || 0), y: 10, w: 10, h: 10, rot: 0, opacity: 1 }; if (grp) { o.grp = grp; } return o; }' . "\n"
		. 'function near(v) { return Math.round(v * 1000) / 1000; }' . "\n"
		. 'function box(l) { return [near(l.x), near(l.y), near(l.w), near(l.h), near(l.rot || 0)]; }' . "\n"
		. 'function K(t, patch) { return { keys: [{ t: 0, x: 0, y: 0, scale: 1, rot: 0, opacity: 1 }, Object.assign({ t: t, x: 0, y: 0, scale: 1, rot: 0, opacity: 1 }, patch)] }; }' . "\n"
		. 'function st(layers, id, t) { var l = layers.filter(function (o) { return o.id === id; })[0], s = M.groupMotionAt(layers, l, t, 5000); return [near(s.x), near(s.y), near(s.scale), near(s.rot), near(s.opacity)]; }' . "\n"
		. 'function by(layers, id) { return layers.filter(function (o) { return o.id === id; })[0]; }' . "\n";

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

// Attach to sets l.att, Detach drops it; a layer never attaches to itself,
// to a group it is in, to a gone target or round a circle; the targets
// list in the layers list's order.
function test_image_editor_attach_commands()
{
	$r = image_editor_attach_node(
		'var g = [L("a", null, 0), L("b", null, 1), L("c", null, 2), L("d", ["g1"], 3), L("e", ["g1"], 4)], out = {};' . "\n"
		. 'var h = M.attachSet(g, "b", "a"); out.set = h.map(function (l) { return l.att || null; }); out.same = [h[0] === g[0], g[1].att || null];' . "\n"
		. 'h = M.attachSet(h, "c", "b");' . "\n"
		. 'out.circle = [M.attachCan(h, "a", "b"), M.attachCan(h, "a", "c"), M.attachSet(h, "a", "c")];' . "\n"
		. 'out.refused = [M.attachCan(h, "a", "a"), M.attachCan(h, "d", "group:g1"), M.attachCan(h, "a", "zz"), M.attachCan(h, "a", "group:g9"), M.attachCan(h, "zz", "a")];' . "\n"
		. 'out.group = [M.attachCan(h, "a", "group:g1"), M.attachCan(M.attachSet(h, "d", "a"), "a", "group:g1")];' . "\n"
		. 'out.targets = M.attachTargets(h, "a"); out.targetsD = M.attachTargets(h, "d");' . "\n"
		. 'out.detach = M.attachSet(h, "b", "").map(function (l) { return l.att || null; }); out.detachBox = box(M.attachSet(h, "b", "")[1]);' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(null, 'a', null, null, null), $r['set'], 'Attach to names the target on the layer');
	pg_assert_same(array(true, null), $r['same'], 'other layers and the given list are left alone');
	pg_assert_same(array(false, false, null), $r['circle'], 'a circle of attachments is refused');
	pg_assert_same(array(false, false, false, false, false), $r['refused'], 'itself, its own group, a gone layer or group, a gone follower');
	pg_assert_same(array(true, false), $r['group'], 'a group as target, unless a member moves with the follower');
	pg_assert_same(array('group:g1', 'e', 'd'), $r['targets'], 'the targets, the top first, a group before its layers, circles left out');
	pg_assert_same(array('e', 'c', 'b', 'a'), $r['targetsD'], 'never the follower\'s own group');
	pg_assert_same(array(null, null, 'b', null, null), $r['detach'], 'Detach lets go');
	pg_assert_same(array(10, 10, 10, 10, 0), $r['detachBox'], 'where it stands');
}

// A follower takes its target's move, turn and size at every moment round
// the target's centre, its own keys on top, never its opacity; a group as
// target moves it by the group's keys; chains compose; a group the two
// share counts once; at rest nothing moves.
function test_image_editor_attach_motion()
{
	$r = image_editor_attach_node(
		'var out = {}, mk = function (anim) { var a = L("a", null, 0), b = L("b", null, 3); a.anim = anim; b.att = "a"; return [a, b]; };' . "\n"
		. 'out.rest = [st(mk(K(1000, { x: 20 })), "b", null), st(mk(K(1000, { x: 20 })), "b", 0)];' . "\n"
		. 'out.move = st(mk(K(1000, { x: 20 })), "b", 1000); out.half = st(mk(K(1000, { x: 20 })), "b", 500);' . "\n"
		. 'out.turn = st(mk(K(1000, { rot: 90 })), "b", 1000);' . "\n"
		. 'out.size = st(mk(K(1000, { scale: 2 })), "b", 1000);' . "\n"
		. 'out.fade = st(mk(K(1000, { opacity: 0.5 })), "b", 1000);' . "\n"
		. 'var own = mk(K(1000, { x: 20 })); own[1].anim = K(1000, { y: 5, rot: 10 }); out.own = st(own, "b", 1000);' . "\n"
		. 'var grp = M.groupFxSet([L("a", ["g1"], 0), L("c", ["g1"], 1), L("b", null, 3)], "g1", K(1000, { rot: 90 })); grp[2].att = "group:g1";' . "\n"
		. 'out.group = st(grp, "b", 1000); out.groupAlone = st(grp, "a", 1000);' . "\n"
		. 'var chain = mk(K(1000, { x: 20 })); chain.push(Object.assign(L("d", null, 5), { att: "b" })); out.chain = st(chain, "d", 1000);' . "\n"
		. 'var shared = M.groupFxSet([L("a", ["g1"], 0), L("b", ["g1"], 3)], "g1", K(1000, { x: 10 })); shared[0].anim = K(1000, { x: 20 }); shared[1].att = "a"; out.shared = st(shared, "b", 1000);' . "\n"
		. 'var loop = mk(K(1000, { x: 20 })); loop[0].att = "b"; out.loop = st(loop, "b", 1000);' . "\n"
		. 'out.moves = [M.attachMoves(chain, chain[2]), M.attachMoves(grp, grp[2]), M.attachMoves([L("a"), Object.assign(L("b"), { att: "a" })], Object.assign(L("b"), { att: "a" }))];' . "\n"
		. 'var svg = M.svgMotion({ motion: { duration: 1000 } }, mk(K(1000, { x: 20 }))); out.svg = svg ? svg.layers.map(function (s) { return s.n; }) : null;' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(array(0, 0, 1, 0, 1), array(0, 0, 1, 0, 1)), $r['rest'], 'at rest and at 0 the follower stands at its own place');
	pg_assert_same(array(20, 0, 1, 0, 1), $r['move'], 'the target\'s move');
	pg_assert_same(array(10, 0, 1, 0, 1), $r['half'], 'at every moment, eased as the target');
	pg_assert_same(array(-30, 30, 1, 90, 1), $r['turn'], 'the target\'s turn, round the target\'s centre');
	pg_assert_same(array(30, 0, 2, 0, 1), $r['size'], 'the target\'s size, its offset grown with it');
	pg_assert_same(array(0, 0, 1, 0, 1), $r['fade'], 'never the target\'s opacity');
	pg_assert_same(array(20, 5, 1, 10, 1), $r['own'], 'the follower\'s own keys on top');
	pg_assert_same(array(-25, 25, 1, 90, 1), $r['group'], 'a group\'s keys round the group\'s centre');
	pg_assert_same(array(5, -5, 1, 90, 1), $r['groupAlone'], 'the group\'s member as before');
	pg_assert_same(array(20, 0, 1, 0, 1), $r['chain'], 'a follower of a follower');
	pg_assert_same(array(30, 0, 1, 0, 1), $r['shared'], 'a group both are in moves the follower once');
	pg_assert_same(array(20, 0, 1, 0, 1), $r['loop'], 'a circle in a hand-made document stops');
	pg_assert_same(array(true, true, false), $r['moves'], 'a follower moves when its target does');
	pg_assert_same(array(0, 1), $r['svg'], 'a saved SVG moves the follower too');
}

// After a change: a deleted target lets its followers go where they stand;
// a target moved, sized or turned at rest carries them, followers of
// followers too, not one moved along with it; a group carries them only
// when moved whole; a document without att is left as it is.
function test_image_editor_attach_keep()
{
	$r = image_editor_attach_node(
		'var out = {}, base = function () { return [L("a", null, 0), Object.assign(L("b", null, 3), { att: "a" }), L("c", ["g1"], 6), L("d", ["g1"], 7), Object.assign(L("e", null, 9), { att: "b" })]; };' . "\n"
		. 'var keep = function (fn, fit) { var before = { layers: base() }, doc = { layers: M.clone(before.layers) }; fn(doc.layers); M.attachKeep(before, doc, fit); return doc.layers; };' . "\n"
		. 'var gone = keep(function (ls) { ls.splice(0, 1); }); out.gone = [by(gone, "b").att || null, box(by(gone, "b")), by(gone, "e").att];' . "\n"
		. 'var moved = keep(function (ls) { ls[0].x += 10; ls[0].y += 5; }); out.moved = [box(by(moved, "b")), box(by(moved, "e")), box(by(moved, "c"))];' . "\n"
		. 'var both = keep(function (ls) { ls[0].x += 10; ls[1].x += 10; }); out.both = box(by(both, "b"));' . "\n"
		. 'var turned = keep(function (ls) { ls[0].rot = 90; }); out.turned = box(by(turned, "b"));' . "\n"
		. 'var fits = []; var sized = keep(function (ls) { ls[0].w = 20; ls[0].h = 20; }, function (o, c) { fits.push(o.id); return c; }); out.sized = box(by(sized, "b")); out.fits = fits;' . "\n"
		. 'var wide = keep(function (ls) { ls[0].w = 20; }); out.wide = box(by(wide, "b"));' . "\n"
		. 'var gb = function () { var ls = base(); ls[1].att = "group:g1"; return ls; };' . "\n"
		. 'var gkeep = function (fn) { var before = { layers: gb() }, doc = { layers: M.clone(before.layers) }; fn(doc.layers); M.attachKeep(before, doc); return doc.layers; };' . "\n"
		. 'out.groupMoved = box(by(gkeep(function (ls) { ls[2].x += 10; ls[3].x += 10; }), "b"));' . "\n"
		. 'out.memberMoved = box(by(gkeep(function (ls) { ls[2].x += 10; }), "b"));' . "\n"
		. 'out.groupEnded = by(gkeep(function (ls) { delete ls[2].grp; delete ls[3].grp; }), "b").att || null;' . "\n"
		. 'out.ownGroup = by(gkeep(function (ls) { ls[1].grp = ["g1"]; }), "b").att || null;' . "\n"
		. 'var loopB = { layers: base() }; loopB.layers[0].att = "e"; var loopD = { layers: M.clone(loopB.layers) }; M.attachKeep(loopB, loopD); out.loop = loopD.layers.map(function (l) { return l.att || null; });' . "\n"
		. 'var plain = { layers: [L("a"), L("b", null, 2)] }, pd = { layers: plain.layers.slice() }; pd.layers[0] = Object.assign(L("a"), { x: 50 }); var pl = pd.layers; M.attachKeep(plain, pd); out.plain = [pd.layers === pl, box(pd.layers[1])];' . "\n"
		. 'var copies = M.attachRemap([{ id: "n1", att: "a" }, { id: "n2", att: "x" }, { id: "n3" }], { a: "n0" }); out.remap = copies.map(function (c) { return c.att || null; });' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(null, array(30, 10, 10, 10, 0), 'b'), $r['gone'], 'a deleted target lets go in place; the follower\'s own follower stays');
	pg_assert_same(array(array(40, 15, 10, 10, 0), array(100, 15, 10, 10, 0), array(60, 10, 10, 10, 0)), $r['moved'], 'a moved target carries its followers and theirs; others stay');
	pg_assert_same(array(40, 10, 10, 10, 0), $r['both'], 'a follower moved with its target moves once');
	pg_assert_same(array(0, 40, 10, 10, 90), $r['turned'], 'a turned target turns its followers round its centre');
	pg_assert_same(array(60, 10, 20, 20, 0), $r['sized'], 'a target sized in proportion sizes them');
	pg_assert_same(array('b', 'e'), $r['fits'], 'a type\'s own resize is asked for each scaled follower');
	pg_assert_same(array(35, 10, 10, 10, 0), $r['wide'], 'a size out of proportion moves them only');
	pg_assert_same(array(40, 10, 10, 10, 0), $r['groupMoved'], 'a group moved whole carries its followers');
	pg_assert_same(array(30, 10, 10, 10, 0), $r['memberMoved'], 'a member moved alone does not');
	pg_assert_same(null, $r['groupEnded'], 'an ended group lets go');
	pg_assert_same(null, $r['ownGroup'], 'a follower put into its target group lets go');
	pg_assert_same(array(null, null, null, null, null), $r['loop'], 'a circle in a hand-made document lets go');
	pg_assert_same(array(true, array(20, 10, 10, 10, 0)), $r['plain'], 'a document without attachments is left as it is');
	pg_assert_same(array('n0', 'x', null), $r['remap'], 'copies made together attach to each other');
}

// On the editor: Attach to and Detach are one undo step each; the context
// menu offers Attach to (its targets by name) for one free layer and
// Detach for an attached one; a delete of the target lets go in place
// through change(); copies made together stay attached to each other.
function test_image_editor_attach_editor()
{
	$r = image_editor_attach_node(
		'var e = fake([L("a", null, 0), L("b", null, 3), L("c", ["g1"], 6), L("d", ["g1"], 8)]), out = {};' . "\n"
		. 'e.sel = ["b"]; out.offer = e.attachOffer(); out.labels = out.offer.map(function (att) { return e.attachLabel(att); });' . "\n"
		. 'out.menu = M.menuList(e.menuState({ at: "stage" })).filter(function (it) { return it.name === "attach" || it.name === "detach"; }).map(function (it) { return [it.name, (it.sub || []).map(function (s) { return s.att; })]; });' . "\n"
		. 'e.attachLayer("b", "a"); out.att = e.doc.layers[1].att; out.steps = e.steps.slice();' . "\n"
		. 'out.menu2 = M.menuList(e.menuState({ at: "stage" })).filter(function (it) { return it.name === "attach" || it.name === "detach"; }).map(function (it) { return it.name; });' . "\n"
		. 'out.refused = e.attachLayer("a", "b"); out.steps2 = e.steps.length;' . "\n"
		. 'e.sel = ["a", "b"]; e.addLayers(e.selected(), 10); out.copies = e.doc.layers.slice(4).map(function (l) { return [l.id, l.att || null]; });' . "\n"
		. 'e.sel = ["b"]; e.attachLayer("b", ""); out.detached = [e.doc.layers[1].att || null, box(e.doc.layers[1])];' . "\n"
		. 'e.sel = ["c", "d"]; out.group = M.menuList(e.menuState({ at: "stage" })).filter(function (it) { return it.name === "attach"; }).length;' . "\n"
		. 'var r = fake([L("a", null, 0), Object.assign(L("b", null, 3), { att: "a" })]); r.change = Editor.prototype.change; r.history = new M.History(); r.opening = M.clone(r.doc); r.renderMeta = none; r.undoBtn = {}; r.redoBtn = {};' . "\n"
		. 'r.sel = ["a"]; r.removeLayers(); out.real = [r.doc.layers.length, r.doc.layers[0].att || null, box(r.doc.layers[0]), r.history.past.length];' . "\n"
		. 'var m = fake([L("a", null, 0), Object.assign(L("b", null, 3), { att: "a" })]); m.change = Editor.prototype.change; m.history = new M.History(); m.opening = M.clone(m.doc); m.renderMeta = none; m.undoBtn = {}; m.redoBtn = {};' . "\n"
		. 'm.sel = ["a"]; m.nudge("right", true); out.nudged = box(m.doc.layers[1]);' . "\n"
		. 'console.log(JSON.stringify(out));',
		true
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('group:g1', 'd', 'c', 'a'), $r['offer'], 'the targets for the picked layer');
	pg_assert_same(array('groupName', 'layer_rect 4', 'layer_rect 3', 'layer_rect 1'), $r['labels'], 'named as the layers list names them');
	pg_assert_same(array(array('attach', array('group:g1', 'd', 'c', 'a'))), $r['menu'], 'the menu offers Attach to with its targets');
	pg_assert_same('a', $r['att'], 'attached');
	pg_assert_same(array('step'), $r['steps'], 'one undo step');
	pg_assert_same(array('attach', 'detach'), $r['menu2'], 'Detach for an attached layer');
	pg_assert_false($r['refused'], 'a circle is refused');
	pg_assert_same(1, $r['steps2'], 'a refused attach leaves no undo step');
	pg_assert_same(array(array('l4', null), array('l5', 'l4')), $r['copies'], 'a target and its follower copied together stay together');
	pg_assert_same(array(null, array(30, 10, 10, 10, 0)), $r['detached'], 'Detach leaves it where it stands');
	pg_assert_same(0, $r['group'], 'a picked group is not attached');
	pg_assert_same(array(1, null, array(30, 10, 10, 10, 0), 1), $r['real'], 'deleting the target lets go in place, in the same undo step');
	pg_assert_same(array(40, 10, 10, 10, 0), $r['nudged'], 'a nudged target carries its follower');
}

// The server reads att as a known field and draws the follower where it
// stands: the same picture as without the attachment.
function test_image_editor_attach_server_draw()
{
	$rect = array('type' => 'rect', 'x' => 10, 'y' => 10, 'w' => 30, 'h' => 20, 'color' => '#ff0000', 'fill' => 'solid', 'stroke' => 0);
	$can = array('shapes' => true, 'text' => true, 'icons' => true, 'svg' => true, 'pictures' => array('png', 'jpeg', 'webp'), 'paused' => 0, 'engine' => 'test');
	$doc = function ($layers) {
		return array('artboard' => array('width' => 100, 'height' => 60, 'background' => '#ffffff'), 'layers' => $layers);
	};
	$plain = pg_image_draw_plan($doc(array(array_merge($rect, array('id' => 'a')), array_merge($rect, array('id' => 'b', 'x' => 50)))), $can);
	$attached = pg_image_draw_plan($doc(array(array_merge($rect, array('id' => 'a')), array_merge($rect, array('id' => 'b', 'x' => 50, 'att' => 'a')))), $can);

	pg_assert_same('', $attached['error'], 'an attached layer is drawn');
	pg_assert_same($plain, $attached, 'where it stands, as without the attachment');
	pg_assert_true(pg_image_draw_known(array('id' => 'l1', 'att' => 'group:g1'), array()), 'att is a known field');
}
