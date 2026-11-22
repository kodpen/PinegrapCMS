<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for where the Artifact Canvas offers Attach to (run under Node):
 * first on one layer's Path tab, above Record; with a group picked the
 * Path tab's line on how a layer follows it; the More menu of the bar over
 * one object holding Attach to with its targets; the layers list marking a
 * follower with a link and its target's name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once __DIR__ . '/image_editor_group_record_test.php';

// The group record test's DOM stand-in, with querySelector and
// querySelectorAll by one class, insertBefore in place and nextSibling, as
// the record and follow parts call them.
function image_editor_attach_place_dom()
{
	return image_editor_group_record_dom()
		. 'var N0 = N; N = function (tag) { var n = N0(tag); n.querySelector = function (s) { return find(n, s.slice(1))[0] || null; }; n.querySelectorAll = function (s) { return find(n, s.slice(1)); }; n.insertBefore = function (c, ref) { var i = ref ? n.kids.indexOf(ref) : -1; if (i < 0) { n.kids.push(c); } else { n.kids.splice(i, 0, c); } c.parentNode = n; return c; }; Object.defineProperty(n, "nextSibling", { get: function () { var p = n.parentNode; return p ? (p.kids[p.kids.indexOf(n) + 1] || null) : null; } }); return n; };' . "\n"
		. 'document.createElement = N; document.createElementNS = function (ns, tag) { return N(tag); };' . "\n"
		. 'function cls(n) { return n.kids.map(function (k) { return k.className; }); }' . "\n"
		. 'function path(e) { e.motionTab = "keys"; e.motionLayers = function () { return this.selected(); }; e.motionWhole = e.motionEmpty = none; var b = N("div"); e.motionPanel(b); return b; }' . "\n";
}

// One layer: Attach to is the tab's first section, above Record and its
// note; Follow's note still sits under Record's. A group picked: no Attach
// to, one line saying how a layer follows the group; none for one layer.
function test_image_editor_attach_place_path_tab()
{
	$r = image_editor_groups_fx_node(
		image_editor_attach_place_dom()
		. 'var e = anim(fake([L("a", null, 0), L("b", ["g1"], 3), L("c", ["g1"], 6)])), out = {}, b, tabs;' . "\n"
		. 'e.followOffered = function () { return false; };' . "\n"
		. 'e.sel = ["a"]; b = path(e); tabs = cls(b).indexOf("pgie-seg");' . "\n"
		. 'out.one = cls(b).slice(tabs + 1, tabs + 5);' . "\n"
		. 'out.select = find(b.kids[tabs + 1], "pgie-select").map(function (s) { return s.getAttribute("aria-label"); });' . "\n"
		. 'out.follow = cls(b).indexOf("pgie-note pgie-follownote") - cls(b).indexOf("pgie-note pgie-recnote");' . "\n"
		. 'out.lineOne = find(b, "pgie-attgrp").length;' . "\n"
		. 'e.sel = ["b", "c"]; b = path(e);' . "\n"
		. 'out.group = [find(b, "pgie-select").filter(function (s) { return s.getAttribute("aria-label") === "attachTo"; }).length, find(b, "pgie-attgrp").map(function (p) { return p.textContent; })];' . "\n"
		. 'console.log(JSON.stringify(out));',
		true,
		array('_record', '_follow')
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('pgie-sec', 'pgie-note', 'pgie-mwhole', 'pgie-actions pgie-recact'), $r['one'], 'Attach to and its note first, then Record');
	pg_assert_same(array('attachTo'), $r['select'], 'the first section is Attach to');
	pg_assert_same(1, $r['follow'], 'Follow\'s note right under Record\'s');
	pg_assert_same(0, $r['lineOne'], 'no group line for one layer');
	pg_assert_same(array(0, array('attachGroupNote')), $r['group'], 'a picked group: no Attach to, one line on how a layer follows it');
}

// The bar over one object: More opens the stage's menu at the bar, which
// holds Attach to with the layer's targets, and Detach once attached.
function test_image_editor_attach_place_more_menu()
{
	$r = image_editor_groups_fx_node(
		image_editor_attach_place_dom()
		. 'window.matchMedia = function () { return { matches: false }; };' . "\n"
		. 'var e = fake([L("a", null, 0), L("b", null, 3), L("c", ["g1"], 6), L("d", ["g1"], 8)]), out = {}, more, opened = null;' . "\n"
		. 'e.stage = N("div"); e.selEl = null; e.cropping = function () { return false; }; e.openMenu = function (ctx) { opened = ctx; };' . "\n"
		. 'var items = function () { more = find(e.actBar, "pgie-act").filter(function (n) { return n.getAttribute("data-act") === "more"; })[0]; opened = null; if (more) { fire(more, "click"); } return opened ? M.menuList(e.menuState(opened)).filter(function (it) { return (it.name === "attach") || (it.name === "detach"); }).map(function (it) { return [it.name, (it.sub || []).map(function (s) { return s.att; })]; }) : null; };' . "\n"
		. 'e.sel = ["b"]; e.renderActions(); out.object = e.actObject(); out.menu = items(); out.at = opened && opened.at;' . "\n"
		. 'e.attachLayer("b", "a"); e.actKey = null; e.renderActions(); out.menu2 = items();' . "\n"
		. 'console.log(JSON.stringify(out));',
		true
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_true($r['object'], 'the bar sits over the object');
	pg_assert_same(array(array('attach', array('group:g1', 'd', 'c', 'a'))), $r['menu'], 'More holds Attach to with the targets');
	pg_assert_same('stage', $r['at'], 'the stage\'s menu, opened at the bar');
	pg_assert_same(array(array('attach', array('group:g1', 'd', 'c', 'a')), array('detach', array())), $r['menu2'], 'and Detach once attached');
}

// The layers list: a follower's row carries a link and its target's name
// (a layer's or "Group n"), titled "Attached to …"; a layer not attached
// and one whose target is gone carry none.
function test_image_editor_attach_place_layers_list()
{
	$r = image_editor_groups_fx_node(
		image_editor_attach_place_dom()
		. 'var e = fake([L("a", null, 0), Object.assign(L("b", null, 3), { att: "a" }), L("c", ["g1"], 6), Object.assign(L("d", null, 8), { att: "group:g1" }), Object.assign(L("f", null, 9), { att: "zz" })]), out = {};' . "\n"
		. 'out.rows = e.doc.layers.map(function (l) { return find(e.layerRow(l, 0), "pgie-lfollow").map(function (m) { return [m.getAttribute("title"), m.kids.map(function (k) { return k.className || k.textContent; })]; }); });' . "\n"
		. 'console.log(JSON.stringify(out));',
		true
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(), $r['rows'][0], 'a layer not attached: no mark');
	pg_assert_same(array(array('attachedTo', array('pgie-ic', 'layer_rect 1'))), $r['rows'][1], 'a follower: the link and its target\'s name');
	pg_assert_same(array(), $r['rows'][2], 'the group\'s member: no mark');
	pg_assert_same(array(array('attachedTo', array('pgie-ic', 'groupName'))), $r['rows'][3], 'a group as target, by its name');
	pg_assert_same(array(), $r['rows'][4], 'a gone target: no mark');
}
