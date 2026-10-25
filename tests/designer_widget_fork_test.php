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

// Whether Node runs here. Only this skips a case: what Node prints is
// checked by designer_widget_fork_test_run().
function designer_widget_fork_test_has_node()
{
	static $has = null;

	if ($has === null) {
		$out = array();
		$code = 1;
		@exec('node --version 2>&1', $out, $code);
		$has = ($code === 0);
	}

	return $has;
}

// One function of the designer, from its declaration to the closing brace
// at its own indentation.
function designer_widget_fork_test_fn($source, $name)
{
	$from = strpos($source, "\n    function " . $name . '(');
	pg_assert_true($from !== false, 'the designer has ' . $name . '()');

	if ($from === false) {
		return '';
	}

	$to = strpos($source, "\n    }\n", $from + 1);
	pg_assert_true($to !== false, $name . '() ends');

	return substr($source, $from, $to + 7 - $from);
}

// Runs $body (statements ending in `return <value>;`) against the helpers
// with a design of two pages: Home (page 1, the active tab, key "a") and
// About (page 2, key "b"). Widget 7 is a record view placed twice on Home
// and once on About, its field holding a row id for each page; component 8
// is a header holding widget 7. `hold = true` keeps the server's answer to
// `create` in `answer()` until the body calls it. Returns what the body
// returned; output that is not JSON fails the case.
function designer_widget_fork_test_run($body)
{
	$source = designer_widget_fork_test_source();
	$script = 'var _sharedCache = {}, _sharedDirty = {}, _sharedSnapshots = {}, _sharedUsage = {};'
		. 'var contentLevel = false, created = [], toasts = [], prefetched = [], calls = { saveState: 0, render: 0, dirty: 0 }, nextId = 0;'
		. 'var hold = false, answer = null;'
		. 'var _design = { styleId: 5 }, _activeKey = "a";'
		. 'function _sdIsContentLevel() { return contentLevel; }'
		. 'function esc(s) { return String(s); }'
		. 'function sdToast(m) { toasts.push(m); }'
		. 'function gid() { return "n" + (++nextId); }'
		. 'function cloneTree(n) { return JSON.parse(JSON.stringify(n)); }'
		. 'function saveState() { calls.saveState++; }'
		. 'function render() { calls.render++; }'
		. 'function _refreshSystemTab() {}'
		. 'function _pgTabsRefreshDirty() { calls.dirty++; }'
		. 'function _pgActivePage() { return _pgPageByKey(_activeKey); }'
		. 'function prefetchShared(ids, cb) { prefetched.push(ids); if (cb) cb(); }'
		. 'function _swAutoName(t, p) { return (p ? p.page_name.toLowerCase() : "page") + (t ? "-" + t : "") + "-widget"; }'
		. 'function _swTypeDefaults(cfg, t) { cfg.regionType = t; return cfg; }'
		. 'function _buildStarterTree(t) { return { _id: "s", type: "root", props: { starter: t }, children: [] }; }'
		. 'function _swBlankTree() { return { _id: "blank", type: "root", props: {}, children: [] }; }'
		. 'function _swCreateRow(name, cfg, t, cb) { created.push({ name: name, cfg: cfg, tree: t });'
		. ' var reply = function () { cb(42, name + " [1]"); }; if (hold) answer = reply; else reply(); }'
		. 'function findParent(node, root) { if (!root || !root.children) return null;'
		. ' for (var i = 0; i < root.children.length; i++) { if (root.children[i] === node) return root; var f = findParent(node, root.children[i]); if (f) return f; } return null; }'
		. 'function ref(sid, id) { return { _id: id, type: "shared_ref", props: { sharedId: sid, sharedName: "w" + sid }, children: [] }; }'
		. 'var homeA = ref(7, "h1"), homeB = ref(7, "h2"), headerHome = ref(8, "h3"), about = ref(7, "a1"), headerAbout = ref(8, "a2");'
		. 'var inHeader = ref(7, "c1");'
		. 'var tree = { _id: "r", type: "root", props: {}, children: [headerHome, { _id: "box", type: "container", props: {}, children: [homeA] }, homeB] };'
		. 'var _pages = [{ key: "a", page_name: "Home", page_id: 1 }, { key: "b", page_name: "About", page_id: 2, tree: { _id: "r2", type: "root", props: {}, children: [headerAbout, about] } }];'
		. '_sharedCache[7] = { name: "home-form-item-view-widget", system_region_config: JSON.stringify({ regionType: "form_item_view", custom_form_page_id: 9 }),'
		. ' tree: { _id: "w", type: "root", props: {}, children: [{ _id: "lp", type: "loop_area", props: { _cf: { ids: { "1": 3, "2": 4 }, label: "Name" } }, children: [] }] } };'
		. '_sharedCache[8] = { name: "Header", tree: { _id: "hd", type: "root", props: {}, children: [inHeader] } };'
		. "\n"
		. designer_widget_fork_test_fn($source, '_sdT')
		. designer_widget_fork_test_fn($source, '_sdNodeObjects')
		. designer_widget_fork_test_fn($source, '_backfillIds')
		. designer_widget_fork_test_fn($source, '_pgPageByKey')
		. designer_widget_fork_test_fn($source, '_pgTreeOf')
		. designer_widget_fork_test_fn($source, '_pgAllPageTrees')
		. designer_widget_fork_test_fn($source, '_sharedUsageText')
		. designer_widget_fork_test_fn($source, '_sharedRefUsageInDesign')
		. designer_widget_fork_test_fn($source, '_swOtherPlacements')
		. designer_widget_fork_test_fn($source, '_pgBaselineTreeKey')
		. designer_widget_fork_test_fn($source, '_sdSharedTreeJSON')
		. designer_widget_fork_test_fn($source, '_sdSharedRevert')
		. designer_widget_fork_test_fn($source, '_swKindOf')
		. designer_widget_fork_test_fn($source, '_swNameKind')
		. designer_widget_fork_test_fn($source, '_swKeepPageFieldIds')
		. designer_widget_fork_test_fn($source, '_swForkOffer')
		. designer_widget_fork_test_fn($source, '_swForkForThisPage')
		. "\n" . 'console.log(JSON.stringify((function () {' . $body . "\n" . '})()));' . "\n";

	$file = tempnam(sys_get_temp_dir(), 'pgwidgetfork');
	file_put_contents($file, $script);
	$out = array();
	$code = 1;
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);

	$answer = json_decode($printed, true);
	pg_assert_true(($printed !== '') && (json_last_error() === JSON_ERROR_NONE), 'Node printed JSON: ' . (($printed === '') ? '(nothing)' : $printed));

	return $answer;
}

// A widget another page of the design places is offered, with the pages it
// is on; it is not offered at content level or once this page is its only
// place.
function test_designer_widget_fork_offered_for_other_placements()
{
	if (!designer_widget_fork_test_has_node()) {
		return;
	}

	$answer = designer_widget_fork_test_run(
		'var o = _swForkOffer(homeA);'
		. 'var r = [o && o.sid, o && o.others, o && o.unsaved];'
		. 'contentLevel = true; r.push(_swForkOffer(homeA)); contentLevel = false;'
		. '_pages[1].tree.children = [];'
		. 'r.push(_swForkOffer(homeA));'
		. 'return r;'
	);

	pg_assert_same(array(7, array('About'), false, null, null), $answer);
}

// Another design placing it counts too; a placement inside another shared
// component (on every page that places the header) and a plain shared
// component are never offered.
function test_designer_widget_fork_offer_limits()
{
	if (!designer_widget_fork_test_has_node()) {
		return;
	}

	$answer = designer_widget_fork_test_run(
		'_pages[1].tree.children = []; _sharedCache[8].tree.children = [];'
		. '_sharedUsage[7] = [{ page_id: 30, page_name: "Shop", style_id: 9, style_name: "Store" }, { page_id: 1, page_name: "Home", style_id: 5, style_name: "Site" }];'
		. 'var r = [_swForkOffer(homeA).others];'
		. '_sharedCache[8].tree.children = [inHeader];'
		. 'r.push(_swForkOffer(inHeader), _swForkOffer(headerHome));'
		. 'return r;'
	);

	pg_assert_same(array(array('Shop (Store)'), null, null), $answer);
}

// `unsaved` is a layout change the row does not hold: a selection alone
// marks the widget dirty without one, an edit after the snapshot is one,
// and a dirty widget without a snapshot counts as changed.
function test_designer_widget_fork_offer_unsaved()
{
	if (!designer_widget_fork_test_has_node()) {
		return;
	}

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

	pg_assert_same(array(false, true, true, false), $answer);
}

// The copy keeps the kind, the settings and the layout under new node ids;
// every placement in this page's tree moves to it, the header's placement
// and the other page keep the original, and the original's cached tree is
// left as it was.
function test_designer_widget_fork_copy_keeps_kind_and_other_pages()
{
	if (!designer_widget_fork_test_has_node()) {
		return;
	}

	$answer = designer_widget_fork_test_run(
		'var done = null;'
		. '_swForkForThisPage(7, {}, function (id, name) { done = [id, name]; });'
		. 'var c = created[0], loop = c.tree.children[0];'
		. 'return {'
		. ' done: done, name: c.name, cfg: c.cfg,'
		. ' freshIds: c.tree._id !== "w" && loop._id !== "lp",'
		. ' label: loop.props._cf.label,'
		. ' original: _sharedCache[7].tree.children[0].props._cf.ids,'
		. ' home: [homeA.props.sharedId, homeA.props.sharedName, homeB.props.sharedId, headerHome.props.sharedId],'
		. ' header: inHeader.props.sharedId, about: about.props.sharedId,'
		. ' calls: calls, toasts: toasts'
		. '};'
	);

	pg_assert_same(array(42, 'home-form_item_view-widget [1]'), $answer['done']);
	pg_assert_same('home-form_item_view-widget', $answer['name'], 'named after this page and the kind');
	pg_assert_same(array('regionType' => 'form_item_view', 'custom_form_page_id' => 9), $answer['cfg'], 'the settings are copied, the kind kept');
	pg_assert_true($answer['freshIds'], 'the copy has its own node ids');
	pg_assert_same('Name', $answer['label']);
	pg_assert_same(array('1' => 3, '2' => 4), $answer['original'], 'the original tree is untouched');
	pg_assert_same(array(42, 'home-form_item_view-widget [1]', 42, 8), $answer['home'], 'this page points at the copy');
	pg_assert_same(7, $answer['header'], 'the header keeps the original');
	pg_assert_same(7, $answer['about'], 'the other page keeps the original');
	pg_assert_same(array('saveState' => 1, 'render' => 1, 'dirty' => 1), $answer['calls'], 'one undo step, one redraw, the tab dots repainted');
	pg_assert_same(array('A copy was made for this page: <strong>home-form_item_view-widget [1]</strong> (2 placements)'), $answer['toasts']);
}

// A field keeps this page's row id in the copy, which stands on this page
// only: a field renamed since the last save still keeps its row and its
// submitted values (the page save matches an id before a name). The other
// pages' ids go with the original. A page never saved has no rows yet.
function test_designer_widget_fork_copy_keeps_this_pages_field_ids()
{
	if (!designer_widget_fork_test_has_node()) {
		return;
	}

	$answer = designer_widget_fork_test_run(
		'_sharedCache[7].tree.children[0].props._attrs = [{ name: "name", value: "e_mail" }];'
		. '_swForkForThisPage(7, {});'
		. 'var r = [created[0].tree.children[0].props._cf.ids, created[0].tree.children[0].props._attrs];'
		. '_pages[0].page_id = 0;'
		. '_swForkForThisPage(7, {});'
		. 'r.push("ids" in created[1].tree.children[0].props._cf);'
		. 'return r;'
	);

	pg_assert_same(array('1' => 3), $answer[0], "only this page's row id is kept");
	pg_assert_same(array(array('name' => 'name', 'value' => 'e_mail')), $answer[1], 'the renamed field travels as it is');
	pg_assert_false($answer[2], 'a page never saved keeps no row id');
}

// The answer to `create` may come after another tab was opened: the page
// that asked gets the copy (its parked tree, no undo step on the open
// page, its dirty dot repainted), the open page keeps the original.
function test_designer_widget_fork_copy_goes_to_the_page_that_asked()
{
	if (!designer_widget_fork_test_has_node()) {
		return;
	}

	$answer = designer_widget_fork_test_run(
		'hold = true;'
		. '_swForkForThisPage(7, {});'
		. '_pages[0].tree = tree; _activeKey = "b"; tree = _pages[1].tree;'
		. 'answer();'
		. 'return {'
		. ' name: created[0].name,'
		. ' home: [homeA.props.sharedId, homeB.props.sharedId], about: about.props.sharedId,'
		. ' calls: calls, toasts: toasts'
		. '};'
	);

	pg_assert_same('home-form_item_view-widget', $answer['name'], 'named after the page that asked');
	pg_assert_same(array(42, 42), $answer['home'], 'the page that asked points at the copy');
	pg_assert_same(7, $answer['about'], 'the open page keeps the original');
	pg_assert_same(array('saveState' => 0, 'render' => 1, 'dirty' => 1), $answer['calls'], 'no undo step on the open page');
	pg_assert_same(array('A copy was made for Home: <strong>home-form_item_view-widget [1]</strong> (2 placements)'), $answer['toasts']);
}

// With `keepOriginal` the unsaved layout edits stay in the copy and the
// original goes back to what its row holds: the snapshot when it has one,
// else the row read again. Without it the original keeps them for the save.
function test_designer_widget_fork_keep_original_as_saved()
{
	if (!designer_widget_fork_test_has_node()) {
		return;
	}

	$answer = designer_widget_fork_test_run(
		'var edit = function () { _sharedCache[7].tree.children[0].props.text = "Edited"; _sharedDirty[7] = true; };'
		. '_sharedSnapshots[7] = _sdSharedTreeJSON(_sharedCache[7].tree); edit();'
		. '_swForkForThisPage(7, { keepOriginal: true });'
		. 'var o = _sharedCache[7].tree.children[0];'
		. 'var r = { copy: created[0].tree.children[0].props.text, original: o.props.text || null, originalId: o._id,'
		. ' dirty: !!_sharedDirty[7], snapshot: !!_sharedSnapshots[7], prefetched: prefetched.slice() };'
		. 'edit(); delete _sharedSnapshots[7];'
		. '_swForkForThisPage(7, { keepOriginal: true });'
		. 'r.reread = { dirty: !!_sharedDirty[7], snapshot: !!_sharedSnapshots[7], prefetched: prefetched.slice() };'
		. '_sharedSnapshots[7] = "{}"; edit(); prefetched = [];'
		. '_swForkForThisPage(7, {});'
		. 'r.both = { original: _sharedCache[7].tree.children[0].props.text, dirty: !!_sharedDirty[7], prefetched: prefetched };'
		. 'return r;'
	);

	pg_assert_same('Edited', $answer['copy'], 'the copy takes the edits');
	pg_assert_same(null, $answer['original'], 'the original is back as its snapshot holds it');
	pg_assert_same('lp', $answer['originalId'], 'with its own node ids');
	pg_assert_false($answer['dirty'], 'nothing is left for the save to send to the original');
	pg_assert_true($answer['snapshot'], 'the snapshot stays, it is what the row holds');
	pg_assert_same(array(), $answer['prefetched']);
	pg_assert_same(array('dirty' => false, 'snapshot' => false, 'prefetched' => array(array(7))), $answer['reread'], 'without a snapshot the row is read again');
	pg_assert_same(array('original' => 'Edited', 'dirty' => true, 'prefetched' => array()), $answer['both'], 'without keepOriginal the save still sends the edits to the original');
}

// The name follows the kind as the panel reads it: a row from before the
// kind was stored and the old form-list aliases are form list views, an
// empty kind is the empty widget's name.
function test_designer_widget_fork_name_kind()
{
	if (!designer_widget_fork_test_has_node()) {
		return;
	}

	$answer = designer_widget_fork_test_run(
		'var r = [_swNameKind({}), _swNameKind(null), _swNameKind({ regionType: 3 }), _swNameKind({ regionType: "submitted_forms_list" }),'
		. ' _swNameKind({ regionType: "form_list" }), _swNameKind({ regionType: "blog_list" }), _swNameKind({ regionType: "" }), _swNameKind({ regionType: "custom_form" }),'
		. ' _swNameKind({ regionType: "comments", mode: "recent" }), _swNameKind({ regionType: "comments", mode: "thread" })];'
		. '_sharedCache[7].system_region_config = JSON.stringify({ custom_form_page_id: 9 });'
		. '_swForkForThisPage(7, {});'
		. '_sharedCache[7].system_region_config = JSON.stringify({ regionType: "blog_list" });'
		. '_swForkForThisPage(7, {});'
		. '_sharedCache[7].system_region_config = JSON.stringify({ regionType: "" });'
		. '_swForkForThisPage(7, {});'
		. 'r.push(created.map(function (c) { return [c.name, c.cfg.regionType === undefined ? null : c.cfg.regionType]; }));'
		. 'return r;'
	);

	pg_assert_same(array('form_list_view', 'form_list_view', 'form_list_view', 'form_list_view', 'form_list_view', 'form_list_view', '', 'custom_form',
		'comments_recent', 'comments'), array_slice($answer, 0, 10), 'a comments widget in the recent mode is named as a list of recent comments');
	pg_assert_same(array(
		array('home-form_list_view-widget', null),
		array('home-form_list_view-widget', 'blog_list'),
		array('home-widget', ''),
	), $answer[10], 'the legacy row is named as a form list, its settings copied as they are');
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
	pg_assert_contains("{ value: 'keep', label: _sdT('Make a copy, keep the others as saved')", $wire, 'unsaved edits can stay in the copy alone');
	pg_assert_contains("{ value: 'both', label: _sdT('Make a copy, change the others too')", $wire, 'or reach the original too');
	pg_assert_contains("_swForkForThisPage(offer.sid, { keepOriginal: choice === 'keep' }, function () { renderProperties(); });", $wire, 'the copy keeps the kind');
}
