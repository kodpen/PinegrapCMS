<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for where the Artifact Canvas offers Detach sound (run under
 * Node): the reasons it is refused (M.clipDetachWhy()), the bar over one
 * clip in the Animate tool, a clip row's menu on the tracks starting with
 * Split clip and Detach sound, and Detach from a video under Add sound on
 * the sound rows and in the panel's Sounds list.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once __DIR__ . '/image_editor_attach_place_test.php';

// The attach place test's DOM stand-in with closest() by [data-clip], a
// connected flag, and C(id, o) a clip with a sound track at 1×.
function image_editor_detach_place_dom()
{
	return image_editor_attach_place_dom()
		. 'var N1 = N; N = function (tag) { var n = N1(tag); n.isConnected = true; n.closest = function (s) { var p = n; while (p) { if ((s === "[data-clip]") && p.attrs && ("data-clip" in p.attrs)) { return p; } p = p.parentNode; } return null; }; return n; };' . "\n"
		. 'document.createElement = N; document.createElementNS = function (ns, tag) { return N(tag); };' . "\n"
		. 'function C(id, o) { return Object.assign({ id: id, type: "video", src: "/v.mp4", name: "v.mp4", fileId: 3, length: 20000, width: 192, height: 108, sound: true, from: 0, to: 4000, start: 0, speed: 1, volume: 1, muted: false, x: 0, y: 0, w: 192, h: 108, rot: 0 }, o || {}); }' . "\n"
		. 'function clipEditor(layers) { var e = anim(fake(layers)), said = []; e.said = said; e.showMessage = function (m) { said.push(m); }; e.stage = N("div"); e.stage.contains = function () { return true; }; e.selEl = null; e.cropping = function () { return false; }; e.select = function (ids) { this.sel = ids.slice(); }; e.layer = function (id) { return this.doc.layers.find(function (l) { return l.id === id; }) || null; }; return e; }' . "\n";
}

// Refused while locked, without a sound track, off 1× and with the sounds
// full; the clips Detach from a video offers, top first.
function test_image_editor_detach_place_model()
{
	$r = image_editor_groups_fx_node(
		image_editor_detach_place_dom()
		. 'var out = {};' . "\n"
		. 'out.why = [M.clipDetachWhy(C("a"), 0), M.clipDetachWhy(C("a", { locked: true }), 0), M.clipDetachWhy(C("a", { sound: false }), 0), M.clipDetachWhy(C("a", { speed: 2 }), 0), M.clipDetachWhy(C("a"), M.SOUND_MAX)];' . "\n"
		. 'out.list = M.clipDetachList([C("a"), L("r", null, 0), C("b", { sound: false }), C("c", { locked: true }), C("d", { speed: 2 })]).map(function (l) { return l.id; });' . "\n"
		. 'out.bar = M.actionList({ place: "object", layers: 1, free: 1, kind: "video", animate: true, sound: true }).map(function (a) { return a.name; });' . "\n"
		. 'out.barSilent = M.actionList({ place: "object", layers: 1, free: 1, kind: "video", animate: true, sound: false }).map(function (a) { return a.name; });' . "\n"
		. 'out.barRest = M.actionList({ place: "object", layers: 1, free: 1, kind: "video", animate: false, sound: true }).map(function (a) { return a.name; });' . "\n"
		. 'out.phone = M.actionList({ layers: 1, free: 1, kind: "video", animate: true, split: true, sound: true, sheet: true }).map(function (a) { return a.name; });' . "\n"
		. 'out.row = M.menuList({ at: "stage", layers: 1, free: 1, clipRow: { sound: true } }).slice(0, 4).map(function (it) { return it.sep ? "|" : (it.off ? "-" : "") + it.name; });' . "\n"
		. 'out.rowSilent = M.menuList({ at: "stage", layers: 1, free: 1, clipRow: { sound: false } }).slice(0, 3).map(function (it) { return it.sep ? "|" : it.name; });' . "\n"
		. 'out.rowLocked = M.menuList({ at: "stage", layers: 1, free: 0, clipRow: { sound: true } }).slice(0, 2).map(function (it) { return (it.off ? "-" : "") + it.name; });' . "\n"
		. 'out.from = M.menuList({ at: "detachFrom", clips: ["d", "a"] });' . "\n"
		. 'console.log(JSON.stringify(out));',
		true
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('', 'locked', 'sound', 'speed', 'full'), $r['why'], 'the reasons, in order');
	pg_assert_same(array('d', 'a'), $r['list'], 'unlocked clips with a sound track, the top first; off 1× still listed for its reason');
	pg_assert_same(array('split', 'detachSound', 'motion', 'duplicate', 'remove', 'lock', 'more'), $r['bar'], 'the bar over a clip: Detach sound after Split');
	pg_assert_same(array('split', 'motion', 'duplicate', 'remove', 'lock', 'more'), $r['barSilent'], 'no sound track: no Detach sound');
	pg_assert_same(array('duplicate', 'remove', 'lock', 'more'), $r['barRest'], 'outside Animate: neither');
	pg_assert_same(array('options', 'split', 'duplicate', 'remove'), $r['phone'], 'the phone\'s list unchanged');
	pg_assert_same(array('split', 'detachSound', '|', 'cut'), $r['row'], 'a clip row\'s menu starts with Split clip and Detach sound');
	pg_assert_same(array('split', '|', 'cut'), $r['rowSilent'], 'no sound track: Split clip only');
	pg_assert_same(array('-split', '-detachSound'), $r['rowLocked'], 'a locked clip: both off');
	pg_assert_same(array(array('name' => 'detachClip', 'clip' => 'd', 'off' => false), array('name' => 'detachClip', 'clip' => 'a', 'off' => false)), $r['from'], 'Detach from a video: one item per clip');
}

// The bar over one clip in Animate: Detach sound mutes the clip and adds
// its sound in one undo step; off 1× it says why and changes nothing.
function test_image_editor_detach_place_bar()
{
	$r = image_editor_groups_fx_node(
		image_editor_detach_place_dom()
		. 'window.matchMedia = function () { return { matches: false }; }; window.location = { origin: "" };' . "\n"
		. 'var e = clipEditor([C("a"), C("b", { speed: 2 })]), out = {}, btn = function () { return find(e.actBar, "pgie-act").filter(function (n) { return n.getAttribute("data-act") === "detachSound"; })[0] || null; };' . "\n"
		. 'e.sel = ["a"]; e.renderActions(); out.label = btn() && btn().getAttribute("title"); fire(btn(), "click");' . "\n"
		. 'out.sounds = (e.doc.sounds || []).map(function (s) { return [s.src, s.from, s.to, s.muted]; }); out.muted = e.doc.layers[0].muted; out.steps = e.steps.length;' . "\n"
		. 'e.sel = ["b"]; e.actKey = null; e.renderActions(); fire(btn(), "click");' . "\n"
		. 'out.said = e.said; out.after = (e.doc.sounds || []).length; out.bMuted = !!e.doc.layers[1].muted;' . "\n"
		. 'console.log(JSON.stringify(out));',
		true,
		array('_artboard', '_sound', '_video', '_clip')
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('clipDetach', $r['label'], 'Detach sound on the bar, the clip part\'s label');
	pg_assert_same(array(array('/v.mp4', 0, 4000, false)), $r['sounds'], 'the clip\'s sound as a row of its own');
	pg_assert_true($r['muted'], 'the clip muted');
	pg_assert_same(1, $r['steps'], 'one undo step');
	pg_assert_same(array('clipDetachSpeed'), $r['said'], 'off 1×: its reason in one line');
	pg_assert_same(1, $r['after'], 'and nothing added');
	pg_assert_false($r['bMuted'], 'nor the clip muted');
}

// A right click on a clip's bar on the tracks selects that clip and opens
// the menu with Split clip and Detach sound first; on the row's name, the
// row's selected clip.
function test_image_editor_detach_place_row_menu()
{
	$r = image_editor_groups_fx_node(
		image_editor_detach_place_dom()
		. 'window.location = { origin: "" };' . "\n"
		. 'var e = clipEditor([C("a", { row: "a" }), C("b", { row: "a", from: 4000, to: 8000, start: 4000 }), L("r", null, 0)]), out = {}, opened = null;' . "\n"
		. 'e.openMenu = function (ctx) { opened = ctx; };' . "\n"
		. 'e.clipStripFill = e.clipDraw = none; e.clipState = function () { return { shown: null, strips: [] }; }; e.clipWave = function () { return null; };' . "\n"
		. 'e.sel = ["b"]; var row = e.clipTrackRow(e.doc.layers[1], 10000), bars = find(row, "pgie-clipbar");' . "\n"
		. 'out.row = row.getAttribute("data-clip"); out.bars = bars.map(function (b) { return b.getAttribute("data-clip"); });' . "\n"
		. 'var ctx = e.menuPlace(bars[0]); out.ctx = [ctx.at, ctx.clip];' . "\n"
		. 'e.menuAt(ctx, { clientX: 5, clientY: 5 }, true); out.sel = e.sel;' . "\n"
		. 'out.items = M.menuList(e.menuState(opened)).slice(0, 3).map(function (it) { return it.sep ? "|" : it.name; });' . "\n"
		. 'out.name = e.menuPlace(find(row, "pgie-trname")[0]).clip;' . "\n"
		. 'e.sel = ["r"]; out.other = M.menuList(e.menuState({ at: "stage" })).map(function (it) { return it.name; }).filter(function (n) { return (n === "split") || (n === "detachSound"); });' . "\n"
		. 'console.log(JSON.stringify(out));',
		true,
		array('_artboard', '_sound', '_video', '_clip')
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same('b', $r['row'], 'the row names its selected clip');
	pg_assert_same(array('a', 'b'), $r['bars'], 'each bar its own');
	pg_assert_same(array('stage', 'a'), $r['ctx'], 'a press on a bar is for that clip');
	pg_assert_same(array('a'), $r['sel'], 'which is selected');
	pg_assert_same(array('split', 'detachSound', '|'), $r['items'], 'Split clip and Detach sound first');
	pg_assert_same('b', $r['name'], 'the row\'s name: the clip selected when it was drawn');
	pg_assert_same(array(), $r['other'], 'the stage\'s menu for another layer: neither');
}

// Detach from a video under Add sound: on the rows and in the Sounds list
// while a clip has a sound track; the only clip detached at once, several
// named in a menu.
function test_image_editor_detach_place_under_add_sound()
{
	$r = image_editor_groups_fx_node(
		image_editor_detach_place_dom()
		. 'window.matchMedia = function () { return { matches: false }; }; window.location = { origin: "" };' . "\n"
		. 'var out = {}, opened = null, add = function (e) { var b = N("div"); e.soundList(b); return find(b, "pgie-sndaddbtn").map(function (n) { return n.className; }); };' . "\n"
		. 'var rows = function (e) { var d = Editor.motionDocks.filter(function (d) { return d.node === Editor.prototype.soundsNode; })[0]; var box = d && d.node.call(e); return box ? find(box, "pgie-sndadd").map(function (n) { return n.className; }) : null; };' . "\n"
		. 'var e = clipEditor([C("a"), L("r", null, 0)]); e.soundRow = function () { return N("div"); }; e.soundsUpdate = none;' . "\n"
		. 'out.rows = rows(e); out.list = add(e);' . "\n"
		. 'var b = find((function () { var x = N("div"); e.soundList(x); return x; }()), "pgie-snddetach")[0]; out.label = b.kids[1].textContent; fire(b, "click"); out.one = [(e.doc.sounds || []).length, e.doc.layers[0].muted];' . "\n"
		. 'var f = clipEditor([C("a"), C("b", { sound: false }), C("c", { src: "/w.mp4", name: "w.mp4", from: 4000, to: 9000 })]); f.soundRow = function () { return N("div"); }; f.soundsUpdate = none; f.openMenu = function (ctx) { opened = ctx; };' . "\n"
		. 'var fb = find((function () { var x = N("div"); f.soundList(x); return x; }()), "pgie-snddetach")[0]; fire(fb, "click"); out.menu = [opened.at, opened.clips, f.clipMenuName("c")];' . "\n"
		. 'f.sel = ["a"]; opened = null; fire(fb, "click"); out.picked = [opened, (f.doc.sounds || []).length];' . "\n"
		. 'var g = clipEditor([C("a", { sound: false }), L("r", null, 0)]); g.soundRow = function () { return N("div"); }; g.soundsUpdate = none;' . "\n"
		. 'out.none = [rows(g), add(g)];' . "\n"
		. 'console.log(JSON.stringify(out));',
		true,
		array('_artboard', '_sound', '_video', '_clip')
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('pgie-trrow pgie-sndadd', 'pgie-trrow pgie-sndadd pgie-snddetach'), $r['rows'], 'on the rows: right under Add sound');
	pg_assert_same(array('pgie-btn pgie-sndaddbtn', 'pgie-btn pgie-sndaddbtn pgie-snddetach'), $r['list'], 'in the Sounds list: right under Add sound');
	pg_assert_same('clipDetachFrom', $r['label'], 'labelled Detach from a video');
	pg_assert_same(array(1, true), $r['one'], 'the only clip detached at once');
	pg_assert_same(array('detachFrom', array('c', 'a'), 'w.mp4 · 0:04.0 → 0:09.0'), $r['menu'], 'several: a menu naming each, as its bar does');
	pg_assert_same(array(null, 1), $r['picked'], 'the selected one detached without a menu');
	pg_assert_same(array(array('pgie-trrow pgie-sndadd'), array('pgie-btn pgie-sndaddbtn')), $r['none'], 'no sound track to detach: no button');
}
