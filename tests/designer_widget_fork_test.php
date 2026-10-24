<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for "Make a copy for this page" in the visual designer's widget
 * panel: when the button is offered (_swForkOffer()), and what the copy it
 * makes does to the page trees and the cache (_swForkForThisPage() with the
 * kind kept). The helpers are cut out of assets/js/style_designer.js and run
 * under Node; on a machine without Node those cases are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_widget_fork_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
}

// One function of the designer, from its declaration to the closing brace
// at its own indentation.
function designer_widget_fork_test_fn($source, $name)
{
	$from = strpos($source, '    function ' . $name . '(');
	pg_assert_true($from !== false, 'the designer has ' . $name . '()');

	if ($from === false) {
		return '';
	}

	$to = strpos($source, "\n    }\n", $from);
	pg_assert_true($to !== false, $name . '() ends');

	return substr($source, $from, $to + 7 - $from);
}

// Runs $body (statements ending in `return <value>;`) against the helpers
// with a design of two pages: Home (the active tab, key "a") and About
// (key "b"). Widget 7 is a record view placed twice on Home and once on
// About; component 8 is a header holding widget 7. Null without Node.
function designer_widget_fork_test_run($body)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = designer_widget_fork_test_source();
	$script = 'var _sharedCache = {}, _sharedDirty = {}, _sharedSnapshots = {}, _sharedUsage = {};'
		. 'var contentLevel = false, created = [], calls = { saveState: 0, render: 0, dirty: 0 }, nextId = 0;'
		. 'var _design = { styleId: 5 }, _activeKey = "a";'
		. 'function _sdIsContentLevel() { return contentLevel; }'
		. 'function _sdT(s, v) { return String(s).replace(/\{var\}/g, v); }'
		. 'function esc(s) { return String(s); }'
		. 'function sdToast() {}'
		. 'function gid() { return "n" + (++nextId); }'
		. 'function cloneTree(n) { return JSON.parse(JSON.stringify(n)); }'
		. 'function saveState() { calls.saveState++; }'
		. 'function render() { calls.render++; }'
		. 'function _refreshSystemTab() {}'
		. 'function _pgTabsRefreshDirty() { calls.dirty++; }'
		. 'function _swAutoName(t) { return "home-" + t + "-widget"; }'
		. 'function _swTypeDefaults(cfg, t) { cfg.regionType = t; return cfg; }'
		. 'function _buildStarterTree(t) { return { _id: "s", type: "root", props: { starter: t }, children: [] }; }'
		. 'function _swBlankTree() { return { _id: "blank", type: "root", props: {}, children: [] }; }'
		. 'function _swCreateRow(name, cfg, t, cb) { created.push({ name: name, cfg: cfg, tree: t }); cb(42, name + " [1]"); }'
		. 'function findParent(node, root) { if (!root || !root.children) return null;'
		. ' for (var i = 0; i < root.children.length; i++) { if (root.children[i] === node) return root; var f = findParent(node, root.children[i]); if (f) return f; } return null; }'
		. 'function ref(sid, id) { return { _id: id, type: "shared_ref", props: { sharedId: sid, sharedName: "w" + sid }, children: [] }; }'
		. 'var homeA = ref(7, "h1"), homeB = ref(7, "h2"), headerHome = ref(8, "h3"), about = ref(7, "a1"), headerAbout = ref(8, "a2");'
		. 'var inHeader = ref(7, "c1");'
		. 'var tree = { _id: "r", type: "root", props: {}, children: [headerHome, { _id: "box", type: "container", props: {}, children: [homeA] }, homeB] };'
		. 'var _pages = [{ key: "a", page_name: "Home", page_id: 1 }, { key: "b", page_name: "About", page_id: 2, tree: { _id: "r2", type: "root", props: {}, children: [headerAbout, about] } }];'
		. '_sharedCache[7] = { name: "home-form-item-view-widget", system_region_config: JSON.stringify({ regionType: "form_item_view", custom_form_page_id: 9 }),'
		. ' tree: { _id: "w", type: "root", props: {}, children: [{ _id: "lp", type: "loop_area", props: { _cf: { ids: { x: 3 }, label: "Name" } }, children: [] }] } };'
		. '_sharedCache[8] = { name: "Header", tree: { _id: "hd", type: "root", props: {}, children: [inHeader] } };'
		. designer_widget_fork_test_fn($source, '_pgTreeOf')
		. designer_widget_fork_test_fn($source, '_pgAllPageTrees')
		. designer_widget_fork_test_fn($source, '_sharedUsageText')
		. designer_widget_fork_test_fn($source, '_sharedRefUsageInDesign')
		. designer_widget_fork_test_fn($source, '_swOtherPlacements')
		. designer_widget_fork_test_fn($source, '_pgBaselineTreeKey')
		. designer_widget_fork_test_fn($source, '_sdSharedTreeJSON')
		. designer_widget_fork_test_fn($source, '_swForkOffer')
		. designer_widget_fork_test_fn($source, '_swForkForThisPage')
		. 'console.log(JSON.stringify((function () {' . $body . '})()));';

	$file = tempnam(sys_get_temp_dir(), 'pgwidgetfork');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// A widget another page of the design places is offered, with the pages it
// is on; it is not offered at content level or once this page is its only
// place.
function test_designer_widget_fork_offered_for_other_placements()
{
	$answer = designer_widget_fork_test_run(
		'var o = _swForkOffer(homeA);'
		. 'var r = [o && o.sid, o && o.others, o && o.unsaved];'
		. 'contentLevel = true; r.push(_swForkOffer(homeA)); contentLevel = false;'
		. '_pages[1].tree.children = [];'
		. 'r.push(_swForkOffer(homeA));'
		. 'return r;'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(7, array('About'), false, null, null), $answer);
}

// Another design placing it counts too; a placement inside another shared
// component (on every page that places the header) and a plain shared
// component are never offered.
function test_designer_widget_fork_offer_limits()
{
	$answer = designer_widget_fork_test_run(
		'_pages[1].tree.children = []; _sharedCache[8].tree.children = [];'
		. '_sharedUsage[7] = [{ page_id: 30, page_name: "Shop", style_id: 9, style_name: "Store" }, { page_id: 1, page_name: "Home", style_id: 5, style_name: "Site" }];'
		. 'var r = [_swForkOffer(homeA).others];'
		. '_sharedCache[8].tree.children = [inHeader];'
		. 'r.push(_swForkOffer(inHeader), _swForkOffer(headerHome));'
		. 'return r;'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(array('Shop (Store)'), null, null), $answer);
}

// `unsaved` is a layout change the row does not hold: a selection alone
// marks the widget dirty without one, an edit after the snapshot is one,
// and a dirty widget without a snapshot counts as changed.
function test_designer_widget_fork_offer_unsaved()
{
	$answer = designer_widget_fork_test_run(
		'_sharedSnapshots[7] = _sdSharedTreeJSON(_sharedCache[7].tree); _sharedDirty[7] = true;'
		. '_sharedCache[7].tree._expanded = true;'
		. 'var r = [_swForkOffer(homeA).unsaved];'
		. '_sharedCache[7].tree.children[0].props.text = "Edited";'
		. 'r.push(_swForkOffer(homeA).unsaved);'
		. 'delete _sharedSnapshots[7];'
		. 'r.push(_swForkOffer(homeA).unsaved);'
		. 'delete _sharedDirty[7];'
		. 'r.push(_swForkOffer(homeA).unsaved);'
		. 'return r;'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(false, true, true, false), $answer);
}

// The copy keeps the kind, the settings and the layout under new node ids
// and without the original's form field row ids; every placement in this
// page's tree moves to it, the header's placement and the other page keep
// the original, and the original's cached tree is left as it was.
function test_designer_widget_fork_copy_keeps_kind_and_other_pages()
{
	$answer = designer_widget_fork_test_run(
		'var done = null;'
		. '_swForkForThisPage(7, {}, function (id, name) { done = [id, name]; });'
		. 'var c = created[0], loop = c.tree.children[0];'
		. 'return {'
		. ' done: done, name: c.name, cfg: c.cfg,'
		. ' freshIds: c.tree._id !== "w" && loop._id !== "lp",'
		. ' label: loop.props._cf.label, ids: ("ids" in loop.props._cf),'
		. ' original: _sharedCache[7].tree.children[0].props._cf.ids,'
		. ' home: [homeA.props.sharedId, homeA.props.sharedName, homeB.props.sharedId, headerHome.props.sharedId],'
		. ' header: inHeader.props.sharedId, about: about.props.sharedId,'
		. ' calls: calls'
		. '};'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(42, 'home-form_item_view-widget [1]'), $answer['done']);
	pg_assert_same('home-form_item_view-widget', $answer['name'], 'named after this page and the kind');
	pg_assert_same(array('regionType' => 'form_item_view', 'custom_form_page_id' => 9), $answer['cfg'], 'the settings are copied, the kind kept');
	pg_assert_true($answer['freshIds'], 'the copy has its own node ids');
	pg_assert_same('Name', $answer['label']);
	pg_assert_false($answer['ids'], 'field row ids stay with the original');
	pg_assert_same(array('x' => 3), $answer['original'], 'the original tree is untouched');
	pg_assert_same(array(42, 'home-form_item_view-widget [1]', 42, 8), $answer['home'], 'this page points at the copy');
	pg_assert_same(7, $answer['header'], 'the header keeps the original');
	pg_assert_same(7, $answer['about'], 'the other page keeps the original');
	pg_assert_same(array('saveState' => 1, 'render' => 1, 'dirty' => 1), $answer['calls'], 'one undo step, one redraw, the tab dots repainted');
}

// Where the button and its question are wired in.
function test_designer_widget_fork_wiring()
{
	$source = designer_widget_fork_test_source();

	$panel = designer_widget_fork_test_fn($source, 'propsSharedRef');
	pg_assert_contains('if (_swForkOffer(n)) {', $panel, 'the panel asks the offer helper');
	pg_assert_contains('id="sd-sw-fork-page" data-sw-id="\' + sid + \'"', $panel, 'the button carries the widget id');
	pg_assert_contains("esc(_sdT('Make a copy for this page'))", $panel, 'the label is translated and escaped');

	$wire = designer_widget_fork_test_fn($source, '_wireSystemWidgetProps');
	pg_assert_contains("document.getElementById('sd-sw-fork-page')", $wire, 'the button is wired');
	pg_assert_contains('var offer = _swForkOffer(selectedNode);', $wire, 'the click asks again');
	pg_assert_contains('if (!_collab.mayEdit) { _sdReadOnlyNudge(); return; }', $wire, 'a page held by another user is not changed');
	pg_assert_contains('sdChoose({', $wire, 'the operator confirms first');
	pg_assert_contains('_swForkForThisPage(offer.sid, {}, function () { renderProperties(); });', $wire, 'the copy keeps the kind');
}
