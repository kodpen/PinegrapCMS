<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a group recorded and shown as one in the Animate tool (run
 * under Node): Record with a group picked writes the group's keys and none
 * on its members, the tracks' group row above its members with the
 * group's diamonds (dragged and deleted as a layer's), and the Path tab's
 * line and button that remove the members' own paths in one undo step.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once __DIR__ . '/image_editor_groups_fx_test.php';

// A small stand-in for the DOM the parts draw with: nodes keep their
// children, attributes and listeners; find(n, cls) lists the nodes with a
// class under n.
function image_editor_group_record_dom()
{
	return 'function N(tag) {' . "\n"
		. ' var n = { tag: tag, kids: [], attrs: {}, className: "", textContent: "", style: { setProperty: none }, on: {},' . "\n"
		. '  appendChild: function (c) { this.kids.push(c); c.parentNode = this; return c; }, insertBefore: function (c) { return this.appendChild(c); },' . "\n"
		. '  setAttribute: function (k, v) { this.attrs[k] = v; if (k === "class") { this.className = v; } }, getAttribute: function (k) { return (k in this.attrs) ? this.attrs[k] : null; },' . "\n"
		. '  addEventListener: function (k, f) { (this.on[k] = this.on[k] || []).push(f); }, removeEventListener: none, setPointerCapture: none, remove: none,' . "\n"
		. '  querySelector: function () { return null; }, getBoundingClientRect: function () { return { left: 0, top: 0, width: 500, height: 20 }; } };' . "\n"
		. ' n.classList = { add: function (c) { n.className += " " + c; }, remove: none, toggle: none, contains: function (c) { return (" " + n.className + " ").indexOf(" " + c + " ") >= 0; } };' . "\n"
		. ' return n;' . "\n"
		. '}' . "\n"
		. 'document.createElement = N; document.createElementNS = function (ns, tag) { return N(tag); }; document.createTextNode = function () { return N("#text"); };' . "\n"
		. 'function find(n, cls, out) { out = out || []; if (n && n.classList && n.classList.contains(cls)) { out.push(n); } ((n && n.kids) || []).forEach(function (k) { find(k, cls, out); }); return out; }' . "\n"
		. 'function fire(n, type, o) { (n.on[type] || []).forEach(function (f) { f(Object.assign({ button: 0, pointerId: 1, clientX: 0, preventDefault: none, stopPropagation: none }, o || {})); }); }' . "\n"
		. 'window.performance = { now: function () { return 0; } }; window.requestAnimationFrame = function () { return 1; }; window.cancelAnimationFrame = none;' . "\n"
		. 'function anim(e) {' . "\n"
		. ' e.tool = "motion"; e.playing = false; e.dialog = { open: true }; e.root = N("div"); e.pushed = 0;' . "\n"
		. ' e.history = { push: function () { e.pushed++; } }; e.motionStart = function () { return 0; }; e.motionNow = function () { return (this.time === null) ? 0 : this.time; };' . "\n"
		. ' e.motionTotal = function () { return 5000; }; e.renderMotion = e.updateBar = e.settleDirty = e.renderMeta = none; e.seek = function (t) { this.time = t; };' . "\n"
		. ' return e;' . "\n"
		. '}' . "\n";
}

// The owner's case: two layers grouped and recorded with the group picked
// move as one: the keys are the group's, the members get none of their
// own, one undo step; a layer alone still records its own keys.
function test_image_editor_group_record_writes_group_keys()
{
	$r = image_editor_groups_fx_node(
		image_editor_group_record_dom()
		. 'var e = anim(fake([L("a", ["g1"], 0), L("b", ["g1"], 3), L("c", null, 8)])), out = {};' . "\n"
		. 'e.time = 0; e.sel = ["a", "b"]; out.target = e.recLayer() ? e.recLayer().id : null;' . "\n"
		. 'e.recArm(); out.armed = e.recArmed;' . "\n"
		. 'out.started = e.recDown(ev(5, 15));' . "\n"
		. 'if (e.recording) { e.recording.p = [25, 15]; e.recSample(1000); e.recording.p = [45, 15]; e.recSample(2000); e.recFinish(); }' . "\n"
		. 'out.keys = M.groupFx(e.doc.layers, "g1").keys.map(function (k) { return [k.t, near(k.x), near(k.y)]; });' . "\n"
		. 'out.own = e.doc.layers.map(function (l) { return !!(l.anim && l.anim.keys); });' . "\n"
		. 'out.moved = e.doc.layers.map(function (l) { return near(M.groupMotionAt(e.doc.layers, l, 2000, 5000).x); });' . "\n"
		. 'out.pushed = e.pushed; out.armedAfter = e.recArmed;' . "\n"
		. 'e.time = 0; e.sel = ["c"]; e.recArm(); e.recDown(ev(85, 15)); if (e.recording) { e.recording.p = [95, 15]; e.recSample(1000); e.recFinish(); }' . "\n"
		. 'out.alone = (M.keyList(e.doc.layers[2]) || []).map(function (k) { return [k.t, near(k.x)]; });' . "\n"
		. 'var m = M.groupRecordApply(fake([L("a", ["g1"], 0), L("b", ["g1"], 3)]).doc.layers, "g1", [{ t: 0, x: 0, y: 0 }, { t: 1000, x: 10, y: 0 }], 5000);' . "\n"
		. 'out.model = [m.end, m.layers.map(function (l) { return [!!l.anim, l.gfx.g1.keys.length]; })];' . "\n"
		. 'console.log(JSON.stringify(out));',
		true,
		array('_record')
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('group:g1', $r['target'], 'a picked group is what Record arms');
	pg_assert_true($r['armed'], 'Record arms with a group picked');
	pg_assert_true($r['started'], 'a press on a member starts the clock');
	pg_assert_same(array(array(0, 0, 0), array(2000, 40, 0)), $r['keys'], 'the group\'s keys from the recorded move');
	pg_assert_same(array(false, false, false), $r['own'], 'no keys written on the members');
	pg_assert_same(array(40, 40, 0), $r['moved'], 'both members move as one, the other layer stays');
	pg_assert_same(1, $r['pushed'], 'one undo step');
	pg_assert_false($r['armedAfter'], 'Record disarms after the take');
	pg_assert_same(array(array(0, 0), array(1000, 10)), $r['alone'], 'a single layer still records its own keys');
	pg_assert_same(array(5000, array(array(false, 2), array(false, 2))), $r['model'], 'M.groupRecordApply() keys the group on every member');
}

// The tracks: a group's row above its members, the group's diamonds on
// it and none on the members; a diamond dragged moves the group's key and
// picks the group; Delete on it removes the key.
function test_image_editor_group_record_track_row()
{
	$r = image_editor_groups_fx_node(
		image_editor_group_record_dom()
		. 'var e = anim(fake([L("a", ["g1"], 0), L("b", ["g1"], 3), L("c", null, 8)])), out = {}, box, rows, d;' . "\n"
		. 'e.doc.layers = M.groupKeySet(e.doc.layers, "g1", 1000, { x: 30 }, 5000); e.time = null;' . "\n"
		. 'box = e.tracksNode(); rows = find(box, "pgie-trrow");' . "\n"
		. 'out.kinds = rows.map(function (n) { return n.classList.contains("pgie-trgroup") ? "group" : "layer"; });' . "\n"
		. 'out.diamonds = rows.map(function (n) { return find(n, "pgie-trkey").length; });' . "\n"
		. 'out.key = e.tracksKey() !== (e.doc.layers = M.groupFxSet(e.doc.layers, "g1", { keys: [] }), e.tracksKey());' . "\n"
		. 'e.doc.layers = M.groupKeySet(e.doc.layers, "g1", 1000, { x: 30 }, 5000);' . "\n"
		. 'box = e.tracksNode(); d = find(find(box, "pgie-trgroup")[0], "pgie-trkey")[1];' . "\n"
		. 'fire(d, "pointerdown", { clientX: 100 }); fire(d, "pointermove", { clientX: 200 }); fire(d, "pointerup");' . "\n"
		. 'out.sel = e.sel.slice(); out.times = M.groupFx(e.doc.layers, "g1").keys.map(function (k) { return k.t; });' . "\n"
		. 'box = e.tracksNode(); d = find(find(box, "pgie-trgroup")[0], "pgie-trkey")[1];' . "\n"
		. 'fire(d, "keydown", { key: "Delete" }); out.left = M.groupFx(e.doc.layers, "g1").keys.map(function (k) { return k.t; });' . "\n"
		. 'out.own = e.doc.layers.map(function (l) { return !!l.anim; });' . "\n"
		. 'out.rows = M.groupTrackRows([L("a", ["g1"], 0), L("b", ["g1"], 3), L("c", null, 8)]).map(function (x) { return x.group ? "g:" + x.group : x.id; });' . "\n"
		. 'console.log(JSON.stringify(out));',
		true,
		array('_record')
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('layer', 'group', 'layer', 'layer'), $r['kinds'], 'the group\'s row above its members, the top first');
	pg_assert_same(array(0, 2, 0, 0), $r['diamonds'], 'the group\'s diamonds on its row, none on the members');
	pg_assert_true($r['key'], 'the rows are drawn again when the group\'s keys change');
	pg_assert_same(array('a', 'b'), $r['sel'], 'a press on a group diamond picks the group');
	pg_assert_same(array(0, 2000), $r['times'], 'a dragged diamond moves the group\'s key');
	pg_assert_same(array(0), $r['left'], 'Delete on a group diamond removes the key');
	pg_assert_same(array(false, false, false), $r['own'], 'the members keep no keys of their own');
	pg_assert_same(array('c', 'g:g1', 'b', 'a'), $r['rows'], 'M.groupTrackRows(): every group open, its row first');
}

// A picked group whose members hold path keys of their own: the Path tab
// says so in one line with a button removing them, one undo step; the
// group's keys and the members' other motion stay.
function test_image_editor_group_record_remove_paths()
{
	$r = image_editor_groups_fx_node(
		image_editor_group_record_dom()
		. 'var a = L("a", ["g1"], 0), b = L("b", ["g1"], 3), c = L("c", null, 8), out = {}, body, links;' . "\n"
		. 'a.anim = { keys: [{ t: 0, x: 0, y: 0 }, { t: 1000, x: 20, y: 0 }] }; b.anim = { "in": { kind: "fade", ms: 500 }, keys: [{ t: 0, x: 0, y: 0 }, { t: 800, x: 0, y: 9 }] }; c.anim = { keys: [{ t: 0, x: 0, y: 0 }, { t: 900, x: 5, y: 0 }] };' . "\n"
		. 'var e = anim(fake(M.groupKeySet([a, b, c], "g1", 1000, { x: 30 }, 5000))); e.time = null; e.sel = ["a", "b"];' . "\n"
		. 'out.own = M.groupOwnPaths(e.doc.layers, "g1");' . "\n"
		. 'body = N("div"); e.keysPanel(body, e.selected()); var line = find(body, "pgie-grpown");' . "\n"
		. 'out.line = line.length ? line[0].kids.map(function (k) { return k.textContent; }) : [];' . "\n"
		. 'links = find(line[0] || body, "pgie-link"); e.steps = []; if (links.length) { fire(links[0], "click"); }' . "\n"
		. 'out.steps = e.steps.length; out.after = e.doc.layers.map(function (l) { return l.anim ? Object.keys(l.anim).sort() : null; });' . "\n"
		. 'out.group = M.groupFx(e.doc.layers, "g1").keys.length;' . "\n"
		. 'body = N("div"); e.keysPanel(body, e.selected()); out.lineAfter = find(body, "pgie-grpown").length;' . "\n"
		. 'e.sel = ["c"]; body = N("div"); e.keysPanel(body, e.selected()); out.lineOne = find(body, "pgie-grpown").length;' . "\n"
		. 'console.log(JSON.stringify(out));',
		true,
		array('_record')
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('a', 'b'), $r['own'], 'M.groupOwnPaths() names the members with keys of their own');
	pg_assert_same(array('keysGroupOwn', 'keysGroupDrop'), $r['line'], 'one line and its button on the Path tab');
	pg_assert_same(1, $r['steps'], 'one undo step');
	pg_assert_same(array(null, array('in'), array('keys')), $r['after'], 'the members\' own keys removed, their entrance and the other layer kept');
	pg_assert_same(2, $r['group'], 'the group\'s keys stay');
	pg_assert_same(0, $r['lineAfter'], 'no line once the members have no paths');
	pg_assert_same(0, $r['lineOne'], 'no line for a single layer');
}
