<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Named variants of a shared component (includes/fn/component_overrides.php
 * and its twin assets/js/pg_component_overrides.js): the variants a root
 * keeps, a variant applied to the tree, what an edited variant keeps, a
 * place's variant name in its marker and on the site, and the save's
 * cleaning of it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once __DIR__ . '/component_overrides_render_test.php';
require_once __DIR__ . '/component_overrides_place_test.php';

// The promo card with two variants and some malformed entries.
function component_overrides_variants_tree()
{
	$tree = component_overrides_render_tree();
	$tree['props']['cssClass'] = 'card';
	$tree['props']['_variants'] = array(
		array('name' => '  Dark ', 'nodes' => array(
			'root' => array('cssClass' => 'card text-bg-dark', 'text' => 'not a class', '_open' => array()),
			'sd_t' => array('tag' => 'h3', 'level' => 3, 'bold' => true, 'list' => array('x')),
			'sd_gone' => array('cssClass' => 'p-5'),
			'sd_p' => array('src' => '/x.jpg'),
			'sd_b' => array('cssClass' => 'btn text-bg-dark'),
		)),
		array('name' => 'dark', 'nodes' => array('root' => array('cssClass' => 'taken'))),
		array('name' => '', 'nodes' => array()),
		'not a variant',
		array('name' => 'Compact', 'nodes' => array()),
	);
	return $tree;
}

function test_component_overrides_variants_cleaned()
{
	$list = pg_co_variants(component_overrides_variants_tree());

	pg_assert_same(array('Dark', 'Compact'), array_column($list, 'name'), 'trimmed names, a taken one (any case), an empty one and a non-variant dropped');
	pg_assert_same(array(
		'root' => array('cssClass' => 'card text-bg-dark'),
		'sd_t' => array('tag' => 'h3', 'level' => 3, 'bold' => true),
		'sd_gone' => array('cssClass' => 'p-5'),
		'sd_b' => array('cssClass' => 'btn text-bg-dark'),
	), $list[0]['nodes'], 'classes and options only: no text, picture, designer key or list');
	pg_assert_same(6, pg_co_variant_changes($list[0]), 'six changes');
	pg_assert_same(0, pg_co_variant_changes($list[1]), 'an empty variant changes nothing');
	pg_assert_same(array(), pg_co_variants(component_overrides_render_tree()), 'a component with no variant');
	pg_assert_same(null, pg_co_variant(component_overrides_variants_tree(), 'Gone'), 'an unknown name');
	pg_assert_same('Dark', pg_co_variant(component_overrides_variants_tree(), ' Dark')['name'], 'found by its name');
	pg_assert_same(str_repeat('é', 60), pg_co_clean_variant_name(str_repeat('é', 70)), 'at most 60 characters');
	pg_assert_same('', pg_co_clean_variant_name(array('x')), 'not a string');
}

function test_component_overrides_apply_variant()
{
	$tree = component_overrides_variants_tree();
	$dark = pg_co_apply_variant($tree, 'Dark');

	pg_assert_same('card text-bg-dark', $dark['props']['cssClass'], 'the root takes its class');
	pg_assert_same('h3', $dark['children'][0]['props']['tag'], 'a node takes its options');
	pg_assert_same('Spring sale', $dark['children'][0]['props']['text'], 'the text stays the component\'s');
	pg_assert_same('/a.jpg', $dark['children'][1]['props']['src'], 'the picture stays the component\'s');
	pg_assert_same('h2', $tree['children'][0]['props']['tag'], 'the given tree is not changed');

	pg_assert_same($tree, pg_co_apply_variant($tree, 'Compact'), 'an empty variant: the tree as given');
	pg_assert_same($tree, pg_co_apply_variant($tree, 'Gone'), 'a variant that is gone: the tree as given');
	pg_assert_same($tree, pg_co_apply_variant($tree, ''), 'no variant: the tree as given');
}

function test_component_overrides_variant_nodes_after_an_edit()
{
	$default = component_overrides_render_tree();
	$default['props']['cssClass'] = 'card';
	$default['children'][0]['props']['shadow'] = true;
	$edited = $default;
	$edited['props']['cssClass'] = 'card p-2';
	$edited['children'][0]['props']['text'] = 'typed while editing';
	$edited['children'][0]['props']['tag'] = 'h4';
	unset($edited['children'][0]['props']['shadow']);
	$edited['children'][1]['props']['_label'] = 'Photo';
	$edited['children'][] = array('_id' => 'sd_new', 'type' => 'content', 'props' => array('cssClass' => 'new'), 'children' => array());

	pg_assert_same(array(
		'root' => array('cssClass' => 'card p-2'),
		'sd_t' => array('tag' => 'h4', 'shadow' => false),
	), pg_co_variant_nodes($default, $edited), 'changed classes and options; content, designer keys and a new node not kept');
	pg_assert_same(array(), pg_co_variant_nodes($default, $default), 'nothing changed');
}

function test_component_overrides_variant_in_the_place_marker()
{
	$node = array('_id' => 'sd_r', 'type' => 'shared_ref', 'props' => array('sharedId' => 7, 'sharedName' => 'Promo card'), 'children' => array());
	$before = _render_tree_node($node, 0);

	foreach (array('', '  ', array('Dark'), null) as $i => $none) {
		$node['props']['_variant'] = $none;
		pg_assert_same($before, _render_tree_node($node, 0), 'no usable variant writes no marker, case ' . $i);
	}

	$node['props']['_variant'] = ' Dark ';
	$body = _render_tree_node($node, 0);
	pg_assert_true(preg_match('/^<!--pg-place:([A-Za-z0-9_-]+)--><!--pg-shared-ref:7-->/', $body, $m) === 1, 'a marker before the reference');
	pg_assert_same(array('_variant' => 'Dark'), pg_co_place_decode($m[1]), 'it carries the name');

	$node['props']['_place'] = array('of_title' => 'Autumn');
	$body = _render_tree_node($node, 0);
	preg_match('/<!--pg-place:([A-Za-z0-9_-]+)-->/', $body, $m);
	pg_assert_same(array('_variant' => 'Dark', 'of_title' => 'Autumn'), pg_co_place_decode($m[1]), 'with the place\'s values');
}

function test_component_overrides_variant_drawn_on_the_site()
{
	$tree = component_overrides_variants_tree();
	$own = array(7 => component_overrides_render_html($tree));
	$trees = array(7 => $tree);
	$calls = 0;
	$render = function ($sid, $t) use (&$calls) {
		$calls++;
		return component_overrides_render_html($t);
	};

	$dark = pg_co_place_marker(array(), 'Dark');
	$out = _pg_shared_refs_replace($dark . '<!--pg-shared-ref:7-->', $own, $trees, $render);
	pg_assert_contains('text-bg-dark', $out, 'the place draws its variant');
	pg_assert_contains('<h3', $out, 'with the variant\'s options');
	pg_assert_contains('Spring sale', $out, 'and the component\'s text');

	$both = pg_co_place_marker(array('of_title' => 'Autumn'), 'Dark');
	$out = _pg_shared_refs_replace($both . '<!--pg-shared-ref:7-->', $own, $trees, $render);
	pg_assert_contains('Autumn', $out, 'a place\'s own text is drawn in every variant');
	pg_assert_contains('text-bg-dark', $out, 'in its variant');

	$calls = 0;
	foreach (array('Gone', 'Compact') as $name) {
		$m = pg_co_place_marker(array(), $name);
		pg_assert_same($own[7], _pg_shared_refs_replace($m . '<!--pg-shared-ref:7-->', $own, $trees, $render), $name . ': the component\'s own, byte for byte');
	}
	pg_assert_same(0, $calls, 'and not drawn again');

	$escaped = $tree;
	$escaped['props']['_variants'] = array(array('name' => 'X', 'nodes' => array('sd_b' => array('cssClass' => '"><script>alert(1)</script>'))));
	$m = pg_co_place_marker(array(), 'X');
	$out = _pg_shared_refs_replace($m . '<!--pg-shared-ref:7-->', array(7 => component_overrides_render_html($escaped)), array(7 => $escaped), $render);
	pg_assert_false(strpos($out, '<script>') !== false, 'a variant\'s class is escaped as the component\'s own is');
}

function test_component_overrides_variant_cleaned_on_save()
{
	$page = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'shared_ref', 'props' => array('sharedId' => 7, '_variant' => '  Dark  '), 'children' => array()),
		array('type' => 'shared_ref', 'props' => array('sharedId' => 7, '_variant' => array('x')), 'children' => array()),
		array('type' => 'shared_ref', 'props' => array('sharedId' => 7, '_variant' => '   '), 'children' => array()),
	));
	pg_co_clean_tree_places($page);

	pg_assert_same('Dark', $page['children'][0]['props']['_variant'], 'a name kept trimmed');
	pg_assert_false(array_key_exists('_variant', $page['children'][1]['props']), 'not a string: dropped');
	pg_assert_false(array_key_exists('_variant', $page['children'][2]['props']), 'empty: dropped');
	pg_assert_same('Dark', pg_co_place_variant($page['children'][0]), 'the place\'s variant');
	pg_assert_same('', pg_co_place_variant($page['children'][1]), 'none');
}

function test_component_overrides_variants_browser_twin_answers_the_same()
{
	$tree = component_overrides_variants_tree();
	$default = component_overrides_render_tree();
	$edited = $default;
	$edited['props']['cssClass'] = 'card p-2';
	$edited['children'][0]['props']['text'] = 'typed';
	$edited['children'][0]['props']['tag'] = 'h4';

	$js = component_overrides_test_node('var tree = ' . json_encode($tree, JSON_UNESCAPED_UNICODE) . ', d = ' . json_encode($default) . ', e = ' . json_encode($edited) . ';'
		. ' out = {list: C.variants(tree), dark: C.applyVariant(tree, "Dark"), same: [C.applyVariant(tree, "Compact") === tree, C.applyVariant(tree, "Gone") === tree],'
		. ' changes: C.variantChanges(C.variant(tree, "Dark")), nodes: C.variantNodes(d, e), name: C.cleanVariantName("  a   b "),'
		. ' place: [C.placeVariant({props: {_variant: " Dark "}}), C.placeVariant({props: {}}), C.placeVariant(null)],'
		. ' ok: [C.variantPropOk("cssClass"), C.variantPropOk("text"), C.variantPropOk("_open"), C.variantPropOk("href")]};');

	if ($js === null) {
		return;
	}

	pg_assert_same(pg_co_variants($tree), $js['list'], 'the same variants');
	pg_assert_same(pg_co_apply_variant($tree, 'Dark'), $js['dark'], 'the same tree in a variant');
	pg_assert_same(array(true, true), $js['same'], 'nothing to apply answers the same object');
	pg_assert_same(pg_co_variant_changes(pg_co_variant($tree, 'Dark')), $js['changes'], 'the same count');
	pg_assert_same(pg_co_variant_nodes($default, $edited), $js['nodes'], 'the same kept changes');
	pg_assert_same(pg_co_clean_variant_name('  a   b '), $js['name'], 'the same name rule');
	pg_assert_same(array('Dark', '', ''), $js['place'], 'the same place variant');
	pg_assert_same(array(true, false, false, false), $js['ok'], 'the same prop rule');
}

// The part's variant rules in Node with a stub kit: a name that is taken,
// a place's "Variant" row, and a variant edited on the component: Done
// keeps the changed classes and options and puts the component's own back.
function test_component_overrides_variants_part()
{
	$tree = component_overrides_variants_tree();
	$code = "var document = { querySelector: function () { return null; }, addEventListener: function () {}, removeEventListener: function () {} };\n"
		. "var comp = " . json_encode($tree, JSON_UNESCAPED_UNICODE) . ", depth = 3, commits = [], writes = 0;\n"
		. "kit.sharedCache = {7: {tree: comp, name: 'Promo card'}}; kit.selectedNode = comp.children[0]; kit.selectedNodes = [];\n"
		. "kit._resolveSharedSid = function () { return 7; }; kit.render = function () {}; kit.renderProperties = function () {};\n"
		. "kit._sdCoWrite = function (sid, fn) { writes++; depth++; fn(); };\n"
		. "kit._sdCoVarCommit = function (sid, d, fn) { commits.push(d); fn(); };\n"
		. "Object.defineProperty(kit, 'undoDepth', { get: function () { return depth; } }); kit.coVar = null;\n"
		. "var row = function (ref) { var r = part.placeVariantRow(ref, 7, false); return {changed: r.changed, gone: r.gone, select: r.html.indexOf('data-co-variant') !== -1}; };\n"
		. "out = { why: [part.nameWhy(7, 'dark', ''), part.nameWhy(7, 'Dark', 'Dark'), part.nameWhy(7, 'default', ''), part.nameWhy(7, '', ''), part.nameWhy(7, 'Large', '')],\n"
		. " rows: [row({props: {sharedId: 7}}), row({props: {sharedId: 7, _variant: 'Dark'}}), row({props: {sharedId: 7, _variant: 'Gone'}})] };\n"
		. "part.startVariant(7, 'Dark');\n"
		. "out.editing = kit.coVar && {name: kit.coVar.name, depth: kit.coVar.depth}; out.during = comp.children[2].props.cssClass;\n"
		. "comp.children[2].props.cssClass = 'btn btn-light'; comp.children[0].props.text = 'typed'; comp.props.gap = '3';\n"
		. "part.variantDone();\n"
		. "out.after = {coVar: kit.coVar, commits: commits, btn: comp.children[2].props.cssClass === undefined, gap: comp.props.gap === undefined, text: comp.children[0].props.text,\n"
		. " tag: comp.children[0].props.tag, kept: window.PGComponentOverrides.variant(comp, 'Dark').nodes};";
	$js = component_overrides_place_node($code);
	if ($js === null) {
		return;
	}

	pg_assert_same(array('This name is taken.', '', 'This name is taken.', 'This name is taken.', ''), $js['why'], 'a taken name in any case, "Default" and an empty one are refused; its own name is not');
	pg_assert_same(array('changed' => false, 'gone' => '', 'select' => true), $js['rows'][0], 'on Default: the row, no dot');
	pg_assert_same(array('changed' => true, 'gone' => '', 'select' => true), $js['rows'][1], 'on a variant: the dot');
	pg_assert_same(array('changed' => true, 'gone' => 'Gone', 'select' => true), $js['rows'][2], 'a variant that is gone: said, and the row can be reset');
	pg_assert_same(array('name' => 'Dark', 'depth' => 4), $js['editing'], 'editing starts after its own undo step');
	pg_assert_same('btn text-bg-dark', $js['during'], 'the component is shown in the variant');
	pg_assert_same(null, $js['after']['coVar'], 'Done ends the editing');
	pg_assert_same(array(4), $js['after']['commits'], 'as one step from its start');
	pg_assert_true($js['after']['btn'], 'a class the component did not have is taken off it');
	pg_assert_true($js['after']['gap'], 'an option the variant added is taken off the component');
	pg_assert_same('typed', $js['after']['text'], 'a text typed meanwhile is the component\'s');
	pg_assert_same('h2', $js['after']['tag'], 'the component\'s own option is back');
	pg_assert_same(array(
		'root' => array('cssClass' => 'card text-bg-dark', 'gap' => '3'),
		'sd_t' => array('tag' => 'h3', 'level' => 3, 'bold' => true),
		'sd_b' => array('cssClass' => 'btn btn-light'),
	), $js['after']['kept'], 'the variant keeps what differs, without a node that is gone or a text');
}

// Escape while a variant is edited: with the editor's menu open (a row's
// three-dot menu) it closes the menu only; with none open it ends the
// editing. The menu is #sd-tree-ctx-menu (showCtxMenu()).
function test_component_overrides_variant_escape_closes_an_open_menu_first()
{
	$tree = component_overrides_variants_tree();
	$code = "var keyHandlers = [], menu = null, menuGone = 0;\n"
		. "var document = { querySelector: function (s) { return (menu && /#sd-tree-ctx-menu/.test(s)) ? menu : null; },\n"
		. " getElementById: function (id) { return (menu && id === 'sd-tree-ctx-menu') ? menu : null; },\n"
		. " addEventListener: function (t, f) { if (t === 'keydown') keyHandlers.push(f); },\n"
		. " removeEventListener: function (t, f) { keyHandlers = keyHandlers.filter(function (g) { return g !== f; }); } };\n"
		. "var comp = " . json_encode($tree, JSON_UNESCAPED_UNICODE) . ", depth = 3;\n"
		. "kit.sharedCache = {7: {tree: comp, name: 'Promo card'}}; kit.selectedNode = comp.children[0]; kit.selectedNodes = [];\n"
		. "kit._resolveSharedSid = function () { return 7; }; kit.render = function () {}; kit.renderProperties = function () {};\n"
		. "kit._sdCoWrite = function (sid, fn) { depth++; fn(); };\n"
		. "kit._sdCoVarCommit = function (sid, d, fn) { fn(); };\n"
		. "Object.defineProperty(kit, 'undoDepth', { get: function () { return depth; } }); kit.coVar = null;\n"
		. "var esc = function () { var stopped = false; var e = { key: 'Escape', target: { tagName: 'DIV' }, preventDefault: function () {}, stopPropagation: function () { stopped = true; } };\n"
		. " keyHandlers.slice().forEach(function (f) { f(e); }); return stopped; };\n"
		. "part.startVariant(7, 'Dark');\n"
		. "menu = { remove: function () { menuGone++; menu = null; } };\n"
		. "out = { stopped: esc() };\n"
		. "out.withMenu = { editing: !!kit.coVar, menuGone: menuGone };\n"
		. "esc();\n"
		. "out.without = { editing: !!kit.coVar };";
	$js = component_overrides_place_node($code);
	if ($js === null) {
		return;
	}

	pg_assert_true($js['stopped'], 'the key goes no further');
	pg_assert_same(array('editing' => true, 'menuGone' => 1), $js['withMenu'], 'an open menu is closed and the variant stays edited');
	pg_assert_same(array('editing' => false), $js['without'], 'with no menu open Escape ends the editing');
}

// A renamed variant keeps its former names, so a place that names it by
// one still draws it; a name a variant has now wins over a former one, and
// a former name another variant took first, or one that is a current name,
// is dropped (#12001).
function component_overrides_renamed_tree()
{
	$tree = component_overrides_variants_tree();
	$tree['props']['_variants'] = array(
		array('name' => 'Night', 'was' => array(' Dark ', 'Dark', 'night', 'Compact', '', array('x'), 'Old'), 'nodes' => array('sd_b' => array('cssClass' => 'btn text-bg-dark'))),
		array('name' => 'Compact', 'nodes' => array('root' => array('cssClass' => 'card p-1'))),
		array('name' => 'Wide', 'was' => array('old', 'Wider'), 'nodes' => array()),
		array('name' => 'Dusk', 'was' => 'Dark', 'nodes' => array()),
	);
	return $tree;
}

function test_component_overrides_renamed_variant_keeps_its_places()
{
	$tree = component_overrides_renamed_tree();
	$list = pg_co_variants($tree);

	pg_assert_same(array('Dark', 'Old'), $list[0]['was'], 'former names cleaned: trimmed, once, never its own or another variant\'s name');
	pg_assert_false(array_key_exists('was', $list[1]), 'a variant never renamed keeps no former names');
	pg_assert_same(array('Wider'), $list[2]['was'], 'a former name another variant holds first is dropped');
	pg_assert_false(array_key_exists('was', $list[3]), 'not a list: none');
	pg_assert_same('Night', pg_co_variant($tree, 'Dark')['name'], 'a former name finds the renamed variant');
	pg_assert_same('Compact', pg_co_variant($tree, 'Compact')['name'], 'a current name wins over a former one');
	pg_assert_same(null, pg_co_variant($tree, 'dark'), 'a former name matches as a current one does, case and all');
	pg_assert_same(pg_co_apply_variant($tree, 'Night'), pg_co_apply_variant($tree, 'Dark'), 'a place on the former name draws the variant');

	$placed = pg_co_place_tree($tree, array('_variant' => 'Dark'));
	pg_assert_same('btn text-bg-dark', $placed['children'][2]['props']['cssClass'], 'the site draws the renamed variant at a place saved with the old name');

	$js = component_overrides_test_node('var tree = ' . json_encode($tree, JSON_UNESCAPED_UNICODE) . ';'
		. ' out = {list: C.variants(tree), found: [C.variant(tree, "Dark").name, C.variant(tree, "Compact").name, C.variant(tree, "dark")], dark: C.applyVariant(tree, "Dark")};');
	if ($js === null) {
		return;
	}
	pg_assert_same($list, $js['list'], 'the twin keeps the same former names');
	pg_assert_same(array('Night', 'Compact', null), $js['found'], 'the twin finds the same variants');
	pg_assert_same(pg_co_apply_variant($tree, 'Dark'), $js['dark'], 'the twin draws the same tree');
}

// Rename in the part: the old name is kept as a former name, renaming back
// takes it off again, a place on the old name shows the variant (no amber
// line) and its row selects the new name.
function test_component_overrides_variant_rename_in_the_part()
{
	$tree = component_overrides_variants_tree();
	array_splice($tree['props']['_variants'], 1, 1);
	$code = "var document = { querySelector: function () { return null; }, addEventListener: function () {}, removeEventListener: function () {} };\n"
		. "var comp = " . json_encode($tree, JSON_UNESCAPED_UNICODE) . ", depth = 3;\n"
		. "kit.sharedCache = {7: {tree: comp, name: 'Promo card'}}; kit.selectedNode = comp.children[0]; kit.selectedNodes = [];\n"
		. "kit._resolveSharedSid = function () { return 7; }; kit.render = function () {}; kit.renderProperties = function () {};\n"
		. "kit._sdCoWrite = function (sid, fn) { depth++; fn(); };\n"
		. "kit._sdCoVarCommit = function (sid, d, fn) { fn(); };\n"
		. "Object.defineProperty(kit, 'undoDepth', { get: function () { return depth; } }); kit.coVar = null;\n"
		. "var raw = function () { return JSON.parse(JSON.stringify(comp.props._variants[0])); };\n"
		. "part.renameVariant(7, 'Dark'); part.nameDone(7, 'Night', true);\n"
		. "var r = part.placeVariantRow({props: {sharedId: 7, _variant: 'Dark'}}, 7, false);\n"
		. "out = { first: raw(), row: {changed: r.changed, gone: r.gone, picked: r.html.indexOf('value=\"Night\" selected') !== -1} };\n"
		. "part.renameVariant(7, 'Night'); part.nameDone(7, 'Dark', true); out.back = raw();\n";
	$js = component_overrides_place_node($code);
	if ($js === null) {
		return;
	}
	pg_assert_same('Night', $js['first']['name'], 'the variant takes the new name');
	pg_assert_same(array('Dark'), $js['first']['was'], 'and keeps the old one');
	pg_assert_same(array('changed' => true, 'gone' => '', 'picked' => true), $js['row'], 'a place on the old name keeps the variant under its new name');
	pg_assert_same('Dark', $js['back']['name'], 'renamed back');
	pg_assert_same(array('Night'), $js['back']['was'], 'its name is no former name; the one it left is');
}

// A rename that changes only the case ("dark" to "Dark") keeps its places:
// the old name cannot stay a former name (a name now holds it in any case),
// so a place's name matches a variant's name in any case after the exact
// name and the former names. Names are unique in any case, so this finds
// one variant at most.
function test_component_overrides_case_only_rename_keeps_its_places()
{
	$tree = component_overrides_variants_tree();
	$tree['props']['_variants'] = array(
		array('name' => 'Dark', 'was' => array('dark'), 'nodes' => array('sd_b' => array('cssClass' => 'btn text-bg-dark'))),
		array('name' => 'Compact', 'nodes' => array('root' => array('cssClass' => 'card p-1'))),
	);
	pg_assert_same('Dark', pg_co_variant($tree, 'dark')['name'], 'a place saved before the case changed finds the variant');
	pg_assert_same('Compact', pg_co_variant($tree, 'COMPACT')['name'], 'any case');
	pg_assert_same(null, pg_co_variant($tree, 'Gone'), 'an unknown name still finds none');
	$placed = pg_co_place_tree($tree, array('_variant' => 'dark'));
	pg_assert_same('btn text-bg-dark', $placed['children'][2]['props']['cssClass'], 'the site draws the variant');

	$js = component_overrides_test_node('var tree = ' . json_encode($tree, JSON_UNESCAPED_UNICODE) . ';'
		. ' out = {found: [C.variant(tree, "dark") && C.variant(tree, "dark").name, C.variant(tree, "COMPACT") && C.variant(tree, "COMPACT").name, C.variant(tree, "Gone")]};');
	if ($js === null) {
		return;
	}
	pg_assert_same(array('Dark', 'Compact', null), $js['found'], 'the twin finds the same variants');
}

// Duplicate of a variant whose name is near the 60-character limit: the
// copy's name was cut back to one already taken and the search for a free
// name never ended, so the editor hung. The name is shortened instead.
function test_component_overrides_duplicate_of_a_long_named_variant()
{
	$tree = component_overrides_variants_tree();
	$long = str_repeat('a', 60);
	$tree['props']['_variants'] = array(array('name' => $long, 'nodes' => array('root' => array('cssClass' => 'card p-1'))));
	$code = "var document = { querySelector: function () { return null; }, addEventListener: function () {}, removeEventListener: function () {} };\n"
		. "var comp = " . json_encode($tree, JSON_UNESCAPED_UNICODE) . ";\n"
		. "kit.sharedCache = {7: {tree: comp, name: 'Promo card'}}; kit.render = function () {}; kit.renderProperties = function () {};\n"
		. "kit._sdCoWrite = function (sid, fn) { fn(); }; kit.coVar = null;\n"
		. "part.duplicateVariant(7, '" . $long . "'); part.duplicateVariant(7, '" . $long . "');\n"
		. "out = comp.props._variants.map(function (v) { return v.name; });\n";
	$kit = "var calls = 0, tr = kit._sdT; kit._sdT = function (k, v) { if (++calls > 5000) throw new Error('no free name found'); return tr(k, v); };";
	$js = component_overrides_place_node($code, $kit);
	if ($js === null) {
		return;
	}
	pg_assert_same(3, count($js), 'two copies are added');
	pg_assert_same(3, count(array_unique(array_map('strtolower', $js))), 'each with a name of its own');
	foreach ($js as $name) {
		pg_assert_true(mb_strlen($name, 'UTF-8') <= 60, 'a name within the limit: ' . $name);
	}
}

// "Default" is the list's own first row in every language: the part
// refused it only in the language of the editor, so a variant named
// "Default" in a translated editor read as a second default elsewhere.
function test_component_overrides_default_refused_in_every_language()
{
	$tree = component_overrides_variants_tree();
	$code = "var document = { querySelector: function () { return null; }, addEventListener: function () {}, removeEventListener: function () {} };\n"
		. "kit.sharedCache = {7: {tree: " . json_encode($tree, JSON_UNESCAPED_UNICODE) . ", name: 'Promo card'}};\n"
		. "out = [part.nameWhy(7, 'Default', ''), part.nameWhy(7, 'varsayılan', ''), part.nameWhy(7, 'Large', '')];\n";
	$kit = "var tr = kit._sdT; kit._sdT = function (k, v) { return k === 'Default' ? 'Varsayılan' : (k === 'This name is taken.' ? 'Bu ad alınmış.' : tr(k, v)); };";
	$js = component_overrides_place_node($code, $kit);
	if ($js === null) {
		return;
	}
	pg_assert_same(array('Bu ad alınmış.', 'Bu ad alınmış.', ''), $js, 'the English and the translated name are both refused');
}
