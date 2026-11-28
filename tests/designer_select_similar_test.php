<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the matcher of "Select similar" in the visual designer
 * (assets/js/pg_select_similar.js, window.PGSelectSimilar): the four ways of
 * alike, the three places, the next wider place when nothing is found, and
 * what is left out with its reason (an element the operator may not edit,
 * the inside of a placed shared component, a system widget's kept slot).
 * Times it on the big fixture page. Runs the module under Node; skipped
 * where there is none.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs `$code` in Node with window.PGSelectSimilar loaded as S and a small
// page as T; its `out` comes back decoded. Null without Node.
function select_similar_test_node($code)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	// Two sections of cards, a heading in each, a placed shared component
	// twice, one expanded in memory, and a system widget slot the server
	// fills beside one its children print in.
	$page = <<<'JS'
var S = window.PGSelectSimilar, out = null;
function n(id, type, props, kids) { return { _id: id, type: type, props: props || {}, children: kids || [] }; }
function card(id, cls) { return n(id, 'component', { componentType: 'card', cssClass: cls }); }
function h(id, cls) { return n(id, 'content', { contentType: 'heading', tag: 'h2', cssClass: cls }); }
var T = n('r', 'root', {}, [
    n('s1', 'semantic', { tag: 'section', cssClass: 'py-5' }, [
        h('h1', 'fw-bold mb-3'),
        n('row1', 'row', {}, [
            n('c1', 'col', {}, [card('k1', 'card shadow-sm')]),
            n('c2', 'col', {}, [card('k2', 'shadow-sm card')]),
            n('c3', 'col', {}, [card('k3', 'card')]),
            n('c4', 'col', {}, [card('k4', 'card shadow-sm')])
        ]),
        n('sr1', 'shared_ref', { sharedId: 7, sharedName: 'Promo' }, [card('kin', 'card shadow-sm')])
    ]),
    n('s2', 'semantic', { tag: 'section', cssClass: 'py-5 bg-light' }, [
        h('h2', 'mb-3 fw-bold'),
        n('row2', 'row', {}, [
            n('c5', 'col', {}, [card('k5', 'card shadow-sm')]),
            n('c6', 'col', {}, [card('k6', 'card shadow-sm')])
        ]),
        n('sr2', 'shared_ref', { sharedId: 7, sharedName: 'Promo' }),
        n('sr3', 'shared_ref', { sharedId: 9, sharedName: 'Other' }),
        n('w1', 'semantic', { tag: 'div', _bindings: { section: 'payment_methods' } }, [card('kw', 'card shadow-sm')]),
        n('w2', 'semantic', { tag: 'div', _bindings: { section: 'form_step_nav' } }, [card('kn', 'card shadow-sm')])
    ]),
    n('lone', 'content', { contentType: 'paragraph', cssClass: 'lead' })
]);
JS;

	$script = "var window = {};\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_select_similar.js') . "\n"
		. $page . "\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pgsimilar');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);

	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

function test_select_similar_default_is_same_kind_and_classes_in_the_section()
{
	$res = select_similar_test_node(
		'var r = S.find(T, "k1");'
		. 'out = { ids: r.ids, by: r.by, where: r.where, left: r.leftOut, total: r.leftOutTotal, wider: r.wider,'
		. ' heading: S.find(T, "h1", { where: "page" }).ids,'
		. ' kinds: [S.kind(T.children[0]), S.kind(T.children[0].children[0]), S.kind(T.children[2]), S.kind(T)],'
		. ' classes: S.classes(card("x", "  card  card shadow ")) };'
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(array('k2', 'k4'), $res['ids'], 'the cards with the same classes in its section, in any order, not itself, not the one with fewer classes');
	pg_assert_same('kindClasses', $res['by'], 'same kind and classes is the start');
	pg_assert_same('section', $res['where'], 'its section is the start');
	pg_assert_same(array('locked' => 0, 'shared' => 1, 'kept' => 0), $res['left'], 'the card inside the placed component is left out and counted');
	pg_assert_same(1, $res['total'], 'the total of what was left out');
	pg_assert_same('', $res['wider'], 'something was found: no wider place offered');
	pg_assert_same(array('h2'), $res['heading'], 'a heading with the same classes in another order, on the page');
	pg_assert_same(array('semantic:section', 'content:heading', 'content:paragraph', 'root'), $res['kinds'], 'the kind carries the sub-kind');
	pg_assert_same(array('card', 'shadow'), $res['classes'], 'classes once each');
}

function test_select_similar_the_four_ways()
{
	$res = select_similar_test_node(
		'out = {'
		. ' kind: S.find(T, "k1", { by: "kind" }).ids,'
		. ' any: S.find(T, "h1", { by: "oneClass", where: "page" }).ids,'
		. ' one: S.find(T, "k3", { by: "oneClass", where: "page" }).ids,'
		. ' cls: S.find(T, "k1", { by: "oneClass", cls: "shadow-sm", where: "page" }).ids,'
		. ' notHers: S.find(T, "k3", { by: "oneClass", cls: "shadow-sm" }).ids,'
		. ' comp: S.find(T, "sr1", { by: "component", where: "page" }).ids,'
		. ' compNone: S.find(T, "k1", { by: "component" }).ids,'
		. ' odd: S.find(T, "k1", { by: "nonsense" }).by,'
		. ' ways: [S.ways(T.children[2]), S.ways(T.children[0].children[2]), S.ways(null)]'
		. '};'
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(array('k2', 'k3', 'k4'), $res['kind'], 'same kind: every card of its section, whatever its classes');
	pg_assert_same(array('h2'), $res['any'], 'one of its classes: anything sharing a class, any kind');
	pg_assert_same(array('k1', 'k2', 'k4', 'k5', 'k6', 'kn'), $res['one'], 'a card with the class "card" finds every card');
	pg_assert_same(array('k2', 'k4', 'k5', 'k6', 'kn'), $res['cls'], 'a class named: only what has it');
	pg_assert_same(array(), $res['notHers'], 'a class the chosen one does not have finds nothing');
	pg_assert_same(array('sr2'), $res['comp'], 'the same shared component, not another one');
	pg_assert_same(array(), $res['compNone'], 'not a placed component: the way finds nothing');
	pg_assert_same('kindClasses', $res['odd'], 'an unknown way falls back to the start');
	pg_assert_same(array(array('kindClasses', 'kind', 'oneClass'), array('kindClasses', 'kind', 'component'), array()), $res['ways'], 'the ways that apply, in menu order');
}

function test_select_similar_the_three_places_and_the_wider_one()
{
	$res = select_similar_test_node(
		'var none = S.find(T, "k3", { where: "siblings" }), sec = S.find(T, "k3"), lone = S.find(T, "lone");'
		. 'out = {'
		. ' beside: S.find(T, "c1", { by: "kind", where: "siblings" }).ids,'
		. ' page: S.find(T, "k1", { where: "page" }).ids,'
		. ' none: [none.ids, none.wider], sec: [sec.ids, sec.wider],'
		. ' lone: [lone.ids, lone.wider],'
		. ' sections: S.find(T, "s1", { by: "kind" }).ids,'
		. ' missing: S.find(T, "nope"),'
		. ' byNode: S.find(T, T.children[1].children[0], { where: "page" }).ids'
		. '};'
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(array('c2', 'c3', 'c4'), $res['beside'], 'beside it: its siblings only');
	pg_assert_same(array('k2', 'k4', 'k5', 'k6', 'kn'), $res['page'], 'on this page: every section, and the slot that prints its children');
	pg_assert_same(array(array(), 'section'), $res['none'], 'nothing beside it: the section is offered');
	pg_assert_same(array(array(), 'page'), $res['sec'], 'nothing in the section: the page is offered');
	pg_assert_same(array(array(), ''), $res['lone'], 'a paragraph in no section: its section is the page, nothing wider');
	pg_assert_same(array('s2'), $res['sections'], 'a section is matched in the place around it, not inside itself');
	pg_assert_same(null, $res['missing'], 'a node not in the tree: null');
	pg_assert_same(array('h1'), $res['byNode'], 'the chosen one given as a node');
}

function test_select_similar_leaves_out_what_may_not_be_chosen()
{
	$res = select_similar_test_node(
		'var r = S.find(T, "k5", { mayEdit: function (n) { return n._id !== "k6" && n._id !== "kn"; } });'
		. 'var p = S.find(T, "k1", { where: "page", mayEdit: function (n) { return n._id !== "k4"; } });'
		. 'var keep = S.find(T, "k1", { where: "page", keepsChildren: {} });'
		. 'var inside = S.find(T.children[0].children[2], "kin", { where: "page" });'
		. 'var slot = S.find(T, "kw", { where: "siblings" });'
		. 'out = { r: [r.ids, r.leftOut, r.wider], p: [p.ids, p.leftOut, p.leftOutTotal], keep: [keep.ids, keep.leftOut],'
		. ' inside: [inside.ids, inside.leftOut], slot: slot.leftOut };'
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(array(array(), array('locked' => 2, 'shared' => 0, 'kept' => 1), 'page'), $res['r'], 'an element the operator may not edit is counted, not chosen');
	pg_assert_same(array(array('k2', 'k5', 'k6', 'kn'), array('locked' => 1, 'shared' => 1, 'kept' => 1), 3), $res['p'], 'on the page: locked, inside a component and in a kept slot each counted; a slot that prints its children is open');
	pg_assert_same(array(array('k2', 'k4', 'k5', 'k6'), array('locked' => 0, 'shared' => 1, 'kept' => 2)), $res['keep'], 'the caller names the slots that print their children');
	pg_assert_same(array(array(), array('locked' => 0, 'shared' => 0, 'kept' => 0)), $res['inside'], 'a shared component\'s own tree is walked as its tree');
	pg_assert_same(array('locked' => 0, 'shared' => 0, 'kept' => 0), $res['slot'], 'alone in its slot: nothing beside it');
}

// The slots the editor draws as designed are the matcher's: the guest
// slot and the booking widget's steps and form print their children, so a
// button in a booking step is chosen, not left out as a kept part.
function test_select_similar_open_slots_match_the_editor()
{
	$res = select_similar_test_node(
		'function slot(id, sec, kids) { return n(id, "semantic", { tag: "div", _bindings: { section: sec } }, kids); }'
		. 'var B = n("r", "root", {}, [slot("g", "guest", [card("g1", "card")]), slot("bs", "booking_step_service", [card("b1", "card")]),'
		. ' slot("bf", "booking_form", [card("b2", "card")]), slot("bc", "booking_calendar", [card("b3", "card")]), card("top", "card")]);'
		. 'var r = S.find(B, "top", { where: "page" }); out = { ids: r.ids, left: r.leftOut };'
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(array('g1', 'b1', 'b2'), $res['ids'], 'the guest slot, a booking step and the booking form print their children');
	pg_assert_same(array('locked' => 0, 'shared' => 0, 'kept' => 1), $res['left'], 'a part the booking script fills stays kept');

	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_select_similar.js');
	$editor = array();

	foreach (array('_SD_SECTION_KEEPS_CHILDREN', '_SD_BOOKING_KEEPS_CHILDREN') as $name) {
		pg_assert_true(preg_match('/var ' . $name . ' = \{([^}]*)\}/', $src, $m) === 1, 'the editor\'s ' . $name);
		preg_match_all('/([a-z_]+):\s*1/', $m[1], $keys);
		$editor = array_merge($editor, $keys[1]);
	}

	pg_assert_true(preg_match('/var KEEPS_CHILDREN = \{([^}]*)\}/', $js, $m) === 1, 'the matcher\'s list');
	preg_match_all('/([a-z_]+):\s*1/', $m[1], $keys);
	sort($editor);
	$mine = $keys[1];
	sort($mine);
	pg_assert_same($editor, $mine, 'the matcher names the slots the editor draws as designed, no more and no fewer');
}

function test_select_similar_is_quick_on_the_fixture_page()
{
	$fixture = dirname(__FILE__) . '/fixtures/designer/big_page.json';

	if (!is_file($fixture)) {
		return;
	}

	$res = select_similar_test_node(
		'var F = ' . file_get_contents($fixture) . ';'
		. 'var last = null; (function walk(x) { last = x; (x.children || []).forEach(walk); })(F);'
		. 'var times = [], r = null;'
		. 'for (var i = 0; i < 25; i++) { var t0 = process.hrtime.bigint(); r = S.find(F, last._id, { where: "page", by: "kind" }); times.push(Number(process.hrtime.bigint() - t0) / 1e6); }'
		. 'times.sort(function (a, b) { return a - b; });'
		. 'out = { found: r.ids.length, median: times[12] };'
	);

	if ($res === null) {
		return;
	}

	pg_assert_true($res['found'] > 10, 'the last node of the fixture has its kind elsewhere on the page');
	// A generous bound: the median is well under a millisecond on a laptop;
	// a slow CI runner must not fail it.
	pg_assert_true($res['median'] < 50, 'a walk of the 1,539-node fixture takes a few milliseconds, got ' . $res['median'] . ' ms');
}

// The `similar` part on the same page under Node, its core handed as a host:
// the press chooses the pressed one first and what is like it, says so, and
// draws the line: the count, the two words, what was left out and why; the
// count of one taken out; nothing found with the next wider place.
function select_similar_test_part($code)
{
	$part = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_similar.js');

	return select_similar_test_node(
		'var factory = null; var StyleDesigner = { definePart: function (n, f) { if (n === "similar") factory = f; } };' . "\n"
		. $part . "\n"
		. 'function fmt(s, v) { var l = Array.isArray(v) ? v : [v]; return String(s).replace(/\{var(?::(\d+))?\}/g, function (m, i) { return String(l[i ? i - 1 : 0]); }); }'
		. 'var P = factory({ _sdT: fmt, esc: function (s) { return String(s); }, _sdTipsOff: function () { return false; } });'
		. 'function find(t, id) { if (t._id === id) return t; for (var i = 0; i < t.children.length; i++) { var f = find(t.children[i], id); if (f) return f; } return null; }'
		. 'var got = { ids: null, said: "" };'
		. 'function host(id, mayEdit) { return { node: find(T, id), tree: T, mayEdit: mayEdit || function () { return true; },'
		. ' select: function (nodes) { got.ids = nodes.map(function (n) { return n._id; }); }, say: function (s) { got.said = s; } }; }'
		. "\n" . $code
	);
}

function test_select_similar_part_press_chooses_and_draws_the_line()
{
	$res = select_similar_test_part(
		'P.run(host("k1"));'
		. 'var one = { ids: got.ids, said: got.said, line: P._lineHtml(undefined, 3), less: P._lineHtml(undefined, 2) };'
		. 'P.run(host("k1", function (n) { return n._id !== "k4"; }));'
		. 'out = { one: one, locked: { ids: got.ids, line: P._lineHtml(undefined, 2) }, keys: Object.keys(P).sort() };'
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(array('k1', 'k2', 'k4'), $res['one']['ids'], 'the pressed card first, then the cards like it in its section');
	pg_assert_same('3 similar elements selected: same kind and classes, in this section.', $res['one']['said'], 'read out after the press');
	pg_assert_true(strpos($res['one']['line'], '>3 similar<') !== false, 'the count, the pressed one counted');
	pg_assert_true(strpos($res['one']['line'], 'same kind and classes <i') !== false && strpos($res['one']['line'], 'in this section <i') !== false, 'the two lists as words');
	pg_assert_true(strpos($res['one']['line'], '1 left out: 1 inside a shared component.') !== false, 'what was left out and why');
	pg_assert_true(strpos($res['one']['less'], '>2 of 3 similar<') !== false, 'one taken out with Ctrl and a click');
	pg_assert_same(array('k1', 'k2'), $res['locked']['ids'], 'an element the operator may not edit is never chosen');
	pg_assert_true(strpos($res['locked']['line'], '2 left out: 1 you may not edit, 1 inside a shared component.') !== false, 'the reasons in their order');
	pg_assert_same(array('_lineHtml', 'head', 'run'), $res['keys'], 'the part\'s entry points');
}

function test_select_similar_part_nothing_found_offers_the_wider_place()
{
	$res = select_similar_test_part(
		'P.run(host("h1")); var sec = { ids: got.ids, said: got.said, line: P._lineHtml(undefined, 1) };'
		. 'P.run(host("lone")); out = { sec: sec, page: { ids: got.ids, line: P._lineHtml(undefined, 1) } };'
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(array('h1'), $res['sec']['ids'], 'nothing found: the element stays chosen alone');
	pg_assert_same('No other element like this one here', $res['sec']['said'], 'read out');
	pg_assert_true(strpos($res['sec']['line'], 'data-sim-wider="page">Look on this page<') !== false, 'the next wider place offered');
	pg_assert_true(strpos($res['page']['line'], 'No other element like this one on this page') !== false, 'nothing on the page either');
	pg_assert_false(strpos($res['page']['line'], 'data-sim-wider') !== false, 'no button then');
	pg_assert_false(strpos($res['page']['line'], 'sd-sim-why') !== false, 'nothing left out: no second row');
}

// The core offers the part where the drawing puts it: under "Select Parent"
// in the canvas menu, before "Move…" in the Overview's, the command palette
// while one element is chosen, the S key on the canvas and in the editor,
// the Keyboard shortcuts row; and the line drawn from the panel's head.
function test_select_similar_core_rows_and_key()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$row = "{ icon: 'bi-ui-checks-grid', label: _sdT('Select similar'), key: 'S', handler: function () { _sdSimOpen(node); } }";
	pg_assert_same(2, substr_count($js, $row), 'one row in the canvas menu and one in the Overview row menu');
	pg_assert_true(strpos($js, "scrollTreeToNode(parent);\n            }},\n            (!multi && _sdSimOffered(node)") !== false, 'the canvas row sits under Select Parent');
	pg_assert_true(strpos($js, $row . ");\n            }\n            items.push(_sdGuardItem({ icon: 'bi-arrows-move', label: _sdT('Move…')") !== false, 'the Overview row is first in the group of Move…');
	pg_assert_true(strpos($js, "id: 'act_select_similar'") !== false, 'a command palette row');
	pg_assert_same(2, substr_count($js, '_sdSimKey(e))'), 'the S key in the editor and on the canvas');
	pg_assert_true(strpos($js, "['S',                   _sdT('Select the elements like the selected one')]") !== false, 'the Keyboard shortcuts row');
	pg_assert_true(strpos($js, 'if (_sdSim) _sdSimHead(head);') !== false, 'the line is drawn with the Options panel\'s head');
	pg_assert_true(strpos($js, "selectedNodes.length < 2") !== false && strpos($js, 'function _sdSimOffered(node)') !== false, 'only for one element chosen');
}
