<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for where a palette item added without a drag (a double-click in
 * the element, component and widget palettes, or the command palette)
 * lands in the visual designer: into the selection when it holds the item,
 * else beside the selection in the nearest ancestor that takes it, in the
 * page tree or inside a widget; a component or widget never goes into a
 * system widget's own tree, but beside its placement on the open page; the
 * place has to be one the operator may edit, and the item is wrapped as a
 * drop there would wrap it. _sdInsertSpot(), _sdInsertAt(),
 * _sdWrapForParent() and _sdPlacementOnPage() are cut out of
 * assets/js/style_designer.src.js and run under Node with stubs for what they
 * call; on a machine without Node the test is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The two helpers over a fixture design, then `$steps`; the JSON they print,
// or null without Node.
//
// Page:       page > ref9 (placement of header 9)
//                  > s1 (section) > h1, ul1 > li1, p1
//                  > r1 (row) > c1, c2 (columns)
//                  > ref7 (placement of widget 7), foot
// Widget 7:   w7root > loop > card > ch (the card's heading)
// Header 9:   h9root > nav > link, ref8 (placement of widget 8)
// Header 5:   h5root > nav5 > ref8x (widget 8 again; header 5 is cached
//             but sits on another page, not on this one)
// Widget 8:   w8root > w8leaf
//
// canDrop() is a rule table: a node takes the payload types in its `acc`
// list; a 'root' node takes everything unless it has `refuse`. Widgets 7
// and 8 are system widgets, headers 5 and 9 plain shared components. A node
// with `props.ro` may not be edited (_sdCanEditNode()); _sdMayEdit() notes
// every node it is asked about in `nudged`. A `props.tag` of button, a or
// select is interactive, one of p or h1 holds text only.
function designer_palette_insert_test_run($steps)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$from = strpos($source, 'function _sdInsertSpot(');
	$to = strpos($source, '// One choice of a checkbox/radio group', $from);
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has the insert helpers');

	$script = <<<'JS'
function N(id, type, acc, kids, props) { return { id: id, type: type, acc: acc || [], children: kids || [], props: props || {} }; }
function ref(id, sid) { return N(id, 'shared_ref', [], [], { sharedId: sid }); }
var tree = N('page', 'root', [], [
    ref('ref9', 9),
    N('s1', 'semantic', ['p', 'div', 'shared_ref'], [
        N('h1', 'semantic'),
        N('ul1', 'semantic', ['li'], [N('li1', 'semantic')]),
        N('p1', 'content')
    ]),
    N('r1', 'row', ['col'], [N('c1', 'col', ['p']), N('c2', 'col', ['p'])]),
    ref('ref7', 7),
    N('foot', 'semantic', ['p'])
]);
var _sharedCache = {
    7: { system: true, tree: N('w7root', 'root', [], [
        N('loop', 'loop_area', ['p', 'div', 'shared_ref'], [
            N('card', 'semantic', ['p', 'div', 'shared_ref'], [N('ch', 'semantic')])
        ])
    ]) },
    8: { system: true, tree: N('w8root', 'root', [], [N('w8leaf', 'content')]) },
    9: { tree: N('h9root', 'root', [], [N('nav', 'semantic', ['p', 'shared_ref'], [N('link', 'semantic'), ref('ref8', 8)])]) },
    5: { tree: N('h5root', 'root', [], [N('nav5', 'semantic', ['p', 'shared_ref'], [ref('ref8x', 8)])]) }
};
var _sharedDirty = {};
var selectedNode = null;
var expanded = [];

function findParent(node, root) {
    for (var i = 0; i < (root.children || []).length; i++) {
        if (root.children[i] === node) return root;
        var p = findParent(node, root.children[i]);
        if (p) return p;
    }
    return null;
}
function inTree(node, root) { return root === node || !!findParent(node, root); }
function ownerSid(node) {
    for (var k in _sharedCache) { if (inTree(node, _sharedCache[k].tree)) return parseInt(k, 10); }
    return 0;
}
function byId(id, root) {
    if (root.id === id) return root;
    for (var i = 0; i < (root.children || []).length; i++) { var f = byId(id, root.children[i]); if (f) return f; }
    return null;
}
function find(id) {
    var f = byId(id, tree);
    for (var k in _sharedCache) { if (!f) f = byId(id, _sharedCache[k].tree); }
    return f;
}
function canDrop(data, target) {
    if (target.type === 'root') return !target.refuse;
    return target.acc.indexOf(data.type) !== -1;
}
function _sdAnyParent(node) {
    var p = findParent(node, tree);
    if (p) return { parent: p, sid: 0 };
    for (var k in _sharedCache) {
        p = findParent(node, _sharedCache[k].tree);
        if (p) return { parent: p, sid: parseInt(k, 10) };
    }
    return null;
}
function _sdSharedOwnerOf(node) { return ownerSid(node); }
function _isInsideSystemWidget(node) { var s = ownerSid(node); return (s && _sharedCache[s].system) ? s : 0; }
function expandAncestorsAnywhere(node) { expanded.push(node.id); }
var balanced = [];
function createNode(type, props, kids) { return N(type + '-new', type, [], kids, props); }
function autoBalanceCols(row) { balanced.push(row.id); }
var nudged = [];
function _sdCanEditNode(node) { return !(node && node.props && node.props.ro); }
function _sdMayEdit(node) { nudged.push(node.id); return _sdCanEditNode(node); }
function _isInteractiveNode(node) { return ['button', 'a', 'select'].indexOf(node.props.tag) !== -1; }
function _sdIsTextSink(node) { return ['p', 'h1'].indexOf(node.props.tag) !== -1; }
function shape(n) { return n.type + (n.props.fluid === false ? '(boxed)' : '') + (n.children.length ? '>' + n.children.map(shape).join('+') : '') + (n.id.indexOf('-new') === -1 ? ':' + n.id : ''); }
JS;

	$script .= substr($source, $from, $to - $from) . <<<'JS'
function spotOf(selId, type) {
    selectedNode = selId ? find(selId) : null;
    var s = _sdInsertSpot({ source: 'palette', type: type, extra: {} });
    return s ? { parent: s.parent.id, sibling: s.sibling ? s.sibling.id : null, after: !!s.after, sid: s.sid } : null;
}
function insert(selId, type, newId) {
    _sharedDirty = {};
    expanded = [];
    selectedNode = selId ? find(selId) : null;
    var s = _sdInsertSpot({ source: 'palette', type: type, extra: {} });
    if (!s) return null;
    _sdInsertAt(s, N(newId, 'content'));
    return { kids: s.parent.children.map(function (c) { return c.id; }), dirty: Object.keys(_sharedDirty), expanded: expanded };
}
function ids(id) { return find(id).children.map(function (c) { return c.id; }); }
JS;
	$script .= $steps;

	$file = tempnam(sys_get_temp_dir(), 'pgpalins');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// Nothing selected, or the page body selected: the end of the page.
function test_designer_palette_insert_nothing_selected()
{
	$answer = designer_palette_insert_test_run(
		'var out = {};'
		. 'out.none = spotOf(null, "p");'
		. 'selectedNode = tree; out.body = _sdInsertSpot({ type: "p" }).parent.id;'
		. 'out.added = insert(null, "p", "n1");'
		. 'console.log(JSON.stringify(out));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('parent' => 'page', 'sibling' => null, 'after' => false, 'sid' => 0), $answer['none'], 'nothing selected: into the page');
	pg_assert_same('page', $answer['body'], 'the page body selected: into the page');
	pg_assert_same(array('kids' => array('ref9', 's1', 'r1', 'ref7', 'foot', 'n1'), 'dirty' => array(), 'expanded' => array('n1')), $answer['added'], 'last on the page, no shared tree marked');
}

// A selected container that takes the item gets it last, as before.
function test_designer_palette_insert_into_container()
{
	$answer = designer_palette_insert_test_run(
		'console.log(JSON.stringify({ spot: spotOf("s1", "p"), added: insert("s1", "p", "n1") }));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('parent' => 's1', 'sibling' => null, 'after' => false, 'sid' => 0), $answer['spot'], 'into the selected section');
	pg_assert_same(array('h1', 'ul1', 'p1', 'n1'), $answer['added']['kids'], 'as its last child');
}

// A leaf in a widget's card: the new element goes right after the leaf,
// inside the card, and the widget is marked for the next save.
function test_designer_palette_insert_beside_leaf_in_widget()
{
	$answer = designer_palette_insert_test_run(
		'console.log(JSON.stringify({ spot: spotOf("ch", "p"), added: insert("ch", "p", "n1"), page: ids("page") }));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('parent' => 'card', 'sibling' => 'ch', 'after' => true, 'sid' => 7), $answer['spot'], 'beside the heading, in the card');
	pg_assert_same(array('kids' => array('ch', 'n1'), 'dirty' => array('7'), 'expanded' => array('n1')), $answer['added'], 'after the heading; widget 7 is saved with the next save');
	pg_assert_same(array('ref9', 's1', 'r1', 'ref7', 'foot'), $answer['page'], 'the page itself is left alone');
}

// A leaf whose parent refuses the item (a div into a ul): the walk goes
// further up and puts it after the branch that holds the selection.
function test_designer_palette_insert_walks_up()
{
	$answer = designer_palette_insert_test_run(
		'console.log(JSON.stringify({ spot: spotOf("li1", "div"), added: insert("li1", "div", "n1"), ul: ids("ul1") }));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('parent' => 's1', 'sibling' => 'ul1', 'after' => true, 'sid' => 0), $answer['spot'], 'the section takes it, after the list');
	pg_assert_same(array('h1', 'ul1', 'n1', 'p1'), $answer['added']['kids'], 'right after the list, before the next child');
	pg_assert_same(array('li1'), $answer['ul'], 'nothing goes into the list');
}

// A widget's placement selected on the page: after the placement, in the
// page tree (a shared_ref takes nothing).
function test_designer_palette_insert_after_selected_widget()
{
	$answer = designer_palette_insert_test_run(
		'console.log(JSON.stringify({ spot: spotOf("ref7", "p"), added: insert("ref7", "p", "n1") }));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('parent' => 'page', 'sibling' => 'ref7', 'after' => true, 'sid' => 0), $answer['spot'], 'beside the placement');
	pg_assert_same(array('ref9', 's1', 'r1', 'ref7', 'n1', 'foot'), $answer['added']['kids'], 'after it, before the next page element');
	pg_assert_same(array(), $answer['added']['dirty'], 'no shared tree marked');
}

// A component or widget with a selection inside a system widget lands
// after the widget's placement on the page, never in its card or loop,
// though they take a shared_ref; a plain shared component still takes it.
function test_designer_palette_insert_component_stops_at_widget()
{
	$answer = designer_palette_insert_test_run(
		'var out = {};'
		. 'out.leaf = spotOf("ch", "shared_ref");'
		. 'out.card = spotOf("card", "shared_ref");'
		. 'out.newWidget = spotOf("ch", "system_widget");'
		. 'out.added = insert("ch", "shared_ref", "n1");'
		. 'out.cardKids = ids("card");'
		. 'out.header = spotOf("link", "shared_ref");'
		. 'out.nested = spotOf("w8leaf", "shared_ref");'
		. 'console.log(JSON.stringify(out));'
	);

	if ($answer === null) {
		return;
	}

	$after_ref7 = array('parent' => 'page', 'sibling' => 'ref7', 'after' => true, 'sid' => 0);
	pg_assert_same($after_ref7, $answer['leaf'], 'a leaf in the card: after the widget on the page');
	pg_assert_same($after_ref7, $answer['card'], 'the card itself selected: the same');
	pg_assert_same($after_ref7, $answer['newWidget'], 'a new system widget: the same');
	pg_assert_same(array('ref9', 's1', 'r1', 'ref7', 'n1', 'foot'), $answer['added']['kids'], 'placed after the widget');
	pg_assert_same(array(), $answer['added']['dirty'], 'the widget is not marked');
	pg_assert_same(array('ch'), $answer['cardKids'], 'the card is left alone');
	pg_assert_same(array('parent' => 'nav', 'sibling' => 'link', 'after' => true, 'sid' => 9), $answer['header'], 'in a plain shared component: beside the selection inside it');
	pg_assert_same(array('parent' => 'nav', 'sibling' => 'ref8', 'after' => true, 'sid' => 9), $answer['nested'], 'a widget placed in a header on the page: after its placement there');
}

// The widget's placement comes from what the open page shows. Widget 8
// sits in header 9 (on the page) and in header 5 (on another page, cached
// first): the item goes into header 9, and header 5 is left alone. A
// placement on the page itself wins over one inside a header; a widget the
// page does not show at all sends the item to the end of the page.
function test_designer_palette_insert_widget_placement_on_page()
{
	$answer = designer_palette_insert_test_run(
		'var out = {};'
		. 'out.header = spotOf("w8leaf", "shared_ref");'
		. 'out.added = insert("w8leaf", "shared_ref", "n1");'
		. 'out.otherHeader = ids("nav5");'
		. 'tree.children.push(ref("ref8p", 8));'
		. 'out.direct = spotOf("w8leaf", "shared_ref");'
		. 'tree.children = tree.children.filter(function (c) { return c.id !== "ref9" && c.id !== "ref8p"; });'
		. 'out.absent = spotOf("w8leaf", "shared_ref");'
		. 'console.log(JSON.stringify(out));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('parent' => 'nav', 'sibling' => 'ref8', 'after' => true, 'sid' => 9), $answer['header'], 'the header on this page');
	pg_assert_same(array('kids' => array('link', 'ref8', 'n1'), 'dirty' => array('9'), 'expanded' => array('n1')), $answer['added'], 'after the widget in header 9, which is saved');
	pg_assert_same(array('ref8x'), $answer['otherHeader'], 'the header of the other page is left alone');
	pg_assert_same(array('parent' => 'page', 'sibling' => 'ref8p', 'after' => true, 'sid' => 0), $answer['direct'], 'a placement on the page itself comes first');
	pg_assert_same(array('parent' => 'page', 'sibling' => null, 'after' => false, 'sid' => 0), $answer['absent'], 'no placement on this page: the end of the page');
}

// A column added beside a leaf in a column, or into a selected column,
// goes into a new row there, as a column dropped onto a column does; a
// column into a row stays as it is, and the row's columns are balanced.
function test_designer_palette_insert_col_in_col()
{
	$answer = designer_palette_insert_test_run(
		'var out = {};'
		. 'var c1 = find("c1"); c1.acc.push("col"); c1.children.push(N("cp", "content"));'
		. 'out.spot = spotOf("cp", "col");'
		. '_sdInsertAt(_sdInsertSpot({ source: "palette", type: "col", extra: {} }), N("n1", "col"));'
		. 'out.c1 = ids("c1");'
		. 'out.row = { type: c1.children[1].type, kids: c1.children[1].children.map(function (c) { return c.id; }) };'
		. 'out.balanced = balanced.slice();'
		. 'selectedNode = find("r1");'
		. '_sdInsertAt(_sdInsertSpot({ source: "palette", type: "col", extra: {} }), N("n2", "col"));'
		. 'out.r1 = ids("r1");'
		. 'out.balancedRow = balanced.slice();'
		. 'var c2 = find("c2"); c2.acc.push("col"); selectedNode = c2;'
		. '_sdInsertAt(_sdInsertSpot({ source: "palette", type: "col", extra: {} }), N("n3", "col"));'
		. 'out.c2 = c2.children.map(function (c) { return c.type + ">" + c.children.map(function (k) { return k.id; }).join(); });'
		. 'console.log(JSON.stringify(out));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('parent' => 'c1', 'sibling' => 'cp', 'after' => true, 'sid' => 0), $answer['spot'], 'the enclosing column takes it, after the leaf');
	pg_assert_same(array('cp', 'row-new'), $answer['c1'], 'a new row after the leaf');
	pg_assert_same(array('type' => 'row', 'kids' => array('n1')), $answer['row'], 'the new column inside that row');
	pg_assert_same(array('row-new'), $answer['balanced'], 'the row is balanced');
	pg_assert_same(array('c1', 'c2', 'n2'), $answer['r1'], 'a column into a row is not wrapped');
	pg_assert_same(array('row-new', 'r1'), $answer['balancedRow'], 'the row that gets a column is balanced, as after a drop');
	pg_assert_same(array('row>n3'), $answer['c2'], 'a selected column gets the new column in a new row');
}

// Nothing on the way up takes the item: the end of the page; when the page
// refuses it too, nothing is added.
function test_designer_palette_insert_nothing_accepts()
{
	$answer = designer_palette_insert_test_run(
		'var out = {};'
		. 'find("w7root").type = "semantic";'
		. 'out.spot = spotOf("ch", "x");'
		. 'out.added = insert("ch", "x", "n1");'
		. 'tree.refuse = true;'
		. 'out.refused = spotOf("ch", "x");'
		. 'console.log(JSON.stringify(out));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('parent' => 'page', 'sibling' => null, 'after' => false, 'sid' => 0), $answer['spot'], 'the end of the page');
	pg_assert_same(array('ref9', 's1', 'r1', 'ref7', 'foot', 'n1'), $answer['added']['kids'], 'last on the page');
	pg_assert_same(null, $answer['refused'], 'nothing when the page refuses too');
}

// The old child probe still wins: a row that refuses a paragraph passes it
// to its last column that takes it, not beside the row.
function test_designer_palette_insert_child_probe()
{
	$answer = designer_palette_insert_test_run(
		'console.log(JSON.stringify({ spot: spotOf("r1", "p"), added: insert("r1", "p", "n1") }));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('parent' => 'c2', 'sibling' => null, 'after' => false, 'sid' => 0), $answer['spot'], 'into the last column');
	pg_assert_same(array('n1'), $answer['added']['kids'], 'as its child');
}

// A sibling that moved away before the insert (a widget row created on the
// server meanwhile): the node goes last in the parent.
function test_designer_palette_insert_missing_sibling()
{
	$answer = designer_palette_insert_test_run(
		'var s1 = find("s1");'
		. '_sdInsertAt({ parent: s1, sibling: { id: "gone" }, after: true, sid: 0 }, N("n1", "content"));'
		. 'console.log(JSON.stringify(ids("s1")));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('h1', 'ul1', 'p1', 'n1'), $answer, 'last in the parent');
}

// The page root takes a column, a row or a ready-made block the way a drop
// there does: a column in a new container and row, a row in a new
// container, a block in a boxed container unless it brings its own width.
// The root of a widget's tree takes them as they are.
function test_designer_palette_insert_root_wrap()
{
	$answer = designer_palette_insert_test_run(
		'var out = {}; var last = function () { return tree.children[tree.children.length - 1]; };'
		. 'var col = { source: "palette", type: "col", extra: {} };'
		. 'selectedNode = null; _sdInsertAt(_sdInsertSpot(col), N("n1", "col"), col); out.none = shape(last());'
		. 'selectedNode = find("foot"); var s = _sdInsertSpot(col); out.spot = s.parent.id + "/" + s.sibling.id;'
		. '_sdInsertAt(s, N("n2", "col"), col); out.afterFoot = shape(tree.children[tree.children.indexOf(find("foot")) + 1]);'
		. 'var row = { source: "palette", type: "row", extra: {} };'
		. 'selectedNode = null; _sdInsertAt(_sdInsertSpot(row), N("n3", "row"), row); out.row = shape(last());'
		. 'var card = { source: "palette", type: "component", extra: { componentType: "card" } };'
		. 'selectedNode = null; _sdInsertAt(_sdInsertSpot(card), N("n4", "semantic"), card); out.card = shape(last());'
		. 'var hero = { source: "palette", type: "component", extra: { componentType: "hero-centered" } };'
		. 'selectedNode = null; _sdInsertAt(_sdInsertSpot(hero), N("n5", "semantic"), hero); out.hero = shape(last());'
		. 'selectedNode = find("w8leaf"); _sdInsertAt(_sdInsertSpot(col), N("n6", "col"), col); out.widget = ids("w8root");'
		. 'out.expanded = expanded;'
		. 'console.log(JSON.stringify(out));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same('container>row>col:n1', $answer['none'], 'nothing selected: a column goes in a new container and row');
	pg_assert_same('page/foot', $answer['spot'], 'a leaf in the page body: the page takes the column, after the leaf');
	pg_assert_same('container>row>col:n2', $answer['afterFoot'], 'there too in a new container and row');
	pg_assert_same('container>row:n3', $answer['row'], 'a row goes in a new container');
	pg_assert_same('container(boxed)>semantic:n4', $answer['card'], 'a ready-made block goes in a boxed container');
	pg_assert_same('semantic:n5', $answer['hero'], 'a full-width section goes in as it is');
	pg_assert_same(array('w8leaf', 'n6'), $answer['widget'], 'a widget tree root takes the column as it is');
	pg_assert_same(array('n1', 'n2', 'n3', 'n4', 'n5', 'n6'), $answer['expanded'], 'the overview opens down to the inserted node, not its wrapper');
}

// A selection that holds inline content only (a button, a select, a
// heading) does not pass a block to its children: a span or an option in
// it would take the block. The item goes right after the selection.
function test_designer_palette_insert_inline_only_selection()
{
	$answer = designer_palette_insert_test_run(
		'var out = {}; var s1 = find("s1");'
		. 's1.children.push(N("btn", "semantic", [], [N("bspan", "semantic", ["p"])], { tag: "button" }));'
		. 's1.children.push(N("sel", "semantic", [], [N("o1", "semantic", ["p"]), N("o2", "semantic", ["p"])], { tag: "select" }));'
		. 's1.children.push(N("hd", "semantic", [], [N("hspan", "semantic", ["p"])], { tag: "h1" }));'
		. 'out.button = spotOf("btn", "p");'
		. 'out.select = spotOf("sel", "p");'
		. 'out.heading = spotOf("hd", "p");'
		. 'out.added = insert("btn", "p", "n1").kids;'
		. 'out.span = ids("bspan");'
		. 'console.log(JSON.stringify(out));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('parent' => 's1', 'sibling' => 'btn', 'after' => true, 'sid' => 0), $answer['button'], 'a button: after it, not into its span');
	pg_assert_same(array('parent' => 's1', 'sibling' => 'sel', 'after' => true, 'sid' => 0), $answer['select'], 'a select: after it, not into its last option');
	pg_assert_same(array('parent' => 's1', 'sibling' => 'hd', 'after' => true, 'sid' => 0), $answer['heading'], 'a heading: after it, not into its span');
	pg_assert_same(array('h1', 'ul1', 'p1', 'btn', 'n1', 'sel', 'hd'), $answer['added'], 'placed right after the button');
	pg_assert_same(array(), $answer['span'], 'the button\'s span is left alone');
}

// The insert asks what a drop asks (_sdMayEdit()): a place the operator may
// not edit (outside their area, another person holding the page) gets
// nothing and a word why. An editable place on the way is taken first, so
// a row whose last column is locked to them passes the item to the first.
function test_designer_palette_insert_may_edit()
{
	$answer = designer_palette_insert_test_run(
		'var out = {};'
		. '["card", "loop", "w7root", "page"].forEach(function (id) { find(id).props.ro = true; });'
		. 'nudged = [];'
		. 'out.refused = spotOf("ch", "p");'
		. 'out.nudged = nudged.slice();'
		. 'out.card = ids("card");'
		. 'find("c2").props.ro = true; nudged = [];'
		. 'out.row = spotOf("r1", "p");'
		. 'out.rowNudged = nudged.slice();'
		. 'console.log(JSON.stringify(out));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(null, $answer['refused'], 'nothing editable takes it: no spot');
	pg_assert_same(array('card'), $answer['nudged'], 'the operator is told about the place it would have gone');
	pg_assert_same(array('ch'), $answer['card'], 'the card is left alone');
	pg_assert_same(array('parent' => 'c1', 'sibling' => null, 'after' => false, 'sid' => 0), $answer['row'], 'the editable column takes it');
	pg_assert_same(array(), $answer['rowNudged'], 'without a word');
}
