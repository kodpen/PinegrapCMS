<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Per-place values of a shared component (includes/fn/component_overrides.php
 * and its browser twin assets/js/pg_component_overrides.js): the open-field
 * list, stable ids, a place's values applied to a tree and the left-overs.
 * The twin runs in Node on the same cases; skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A card: heading, picture, button link with an icon, a note that can be
// hidden, and a component placed inside whose own fields are not the card's.
function component_overrides_test_tree()
{
	return array(
		'id' => 'root', 'type' => 'root', 'props' => array(), 'children' => array(
			array('id' => 'h', 'type' => 'content', 'props' => array('contentType' => 'heading', 'text' => 'Our menu',
				'_open' => array(array('id' => 'of_title', 'kind' => 'text', 'label' => ' Title '))), 'children' => array()),
			array('id' => 'p', 'type' => 'content', 'props' => array('contentType' => 'image', 'src' => '/a.jpg', 'alt' => 'A plate',
				'_open' => array(array('id' => 'of_pic', 'kind' => 'picture', 'label' => 'Picture'))), 'children' => array()),
			array('id' => 'b', 'type' => 'content', 'props' => array('contentType' => 'link', 'text' => 'Book', 'href' => '/book', 'iconName' => 'bi-star',
				'_open' => array(
					array('id' => 'of_link', 'kind' => 'link', 'label' => 'Link'),
					array('id' => 'of_icon', 'kind' => 'icon', 'label' => 'Icon'),
					array('id' => 'of_bad', 'kind' => 'colour', 'label' => 'Unknown kind'),
					array('id' => 'Bad id', 'kind' => 'text', 'label' => 'Bad id'),
				)), 'children' => array()),
			array('id' => 'n', 'type' => 'container', 'props' => array('_open' => array(array('id' => 'of_note', 'kind' => 'shown', 'label' => 'Note'))), 'children' => array(
				array('id' => 'nt', 'type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => 'Open daily',
					'_open' => array(array('id' => 'of_title', 'kind' => 'text', 'label' => 'Duplicate'))), 'children' => array()),
			)),
			array('id' => 'r', 'type' => 'shared_ref', 'props' => array('sharedId' => 4, 'sharedName' => 'Badge',
				'_open' => array(array('id' => 'of_ref', 'kind' => 'shown', 'label' => 'Badge'))), 'children' => array(
				array('id' => 'x', 'type' => 'content', 'props' => array('text' => 'inner', '_open' => array(array('id' => 'of_inner', 'kind' => 'text'))), 'children' => array()),
			)),
		),
	);
}

function component_overrides_test_values()
{
	return array(
		'of_title' => 'Lunch',
		'of_pic' => array('src' => '/b.jpg'),
		'of_link' => '/order',
		'of_icon' => 42,
		'of_note' => false,
		'of_gone' => 'closed field',
		'of_inner' => 'not the card\'s',
	);
}

// Runs `$code` in Node with window.PGComponentOverrides loaded (as C); its
// `out` comes back decoded. Null when Node cannot run.
function component_overrides_test_node($code)
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

	$script = "var window = {};\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_component_overrides.js') . "\n"
		. "var C = window.PGComponentOverrides, out = null;\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pgco');

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

function test_component_overrides_open_fields_in_order()
{
	$fields = pg_co_open_fields(component_overrides_test_tree());
	$short = array();
	foreach ($fields as $f) {
		$short[] = $f['id'] . ':' . $f['kind'] . ':' . $f['nodeId'] . ':' . $f['label'];
	}

	pg_assert_same(array('of_title:text:h:Title', 'of_pic:picture:p:Picture', 'of_link:link:b:Link', 'of_icon:icon:b:Icon', 'of_note:shown:n:Note', 'of_ref:shown:r:Badge'), $short,
		'document order; unknown kinds, bad ids and a repeated id dropped; a placed component not entered');
	pg_assert_same('Our menu', $fields[0]['value'], 'the component\'s text is the hint');
	pg_assert_same(array('src' => '/a.jpg', 'alt' => 'A plate'), $fields[1]['value'], 'a picture is src and alt');
	pg_assert_same(true, $fields[4]['value'], 'shown by default');
}

function test_component_overrides_ids()
{
	pg_assert_true(pg_co_valid_field_id('of_ab12cd34'), 'a new-style id');
	pg_assert_false(pg_co_valid_field_id('Of_x'), 'upper case refused');
	pg_assert_false(pg_co_valid_field_id('1abc'), 'a digit first refused');
	pg_assert_false(pg_co_valid_field_id(str_repeat('a', 41)), 'over 40 refused');
	pg_assert_false(pg_co_valid_field_id(12), 'a number refused');

	$id = pg_co_new_field_id(array('of_aaaaaaaa'));
	pg_assert_true(preg_match('/^of_[a-z0-9]{8}$/', $id) === 1 && pg_co_valid_field_id($id), 'of_ and eight characters: ' . $id);
	pg_assert_true($id !== pg_co_new_field_id(), 'random, not counted');
}

function test_component_overrides_apply()
{
	$tree = component_overrides_test_tree();
	$out = pg_co_apply($tree, component_overrides_test_values());

	pg_assert_same('Lunch', $out['children'][0]['props']['text'], 'open text takes the place\'s value');
	pg_assert_same('/b.jpg', $out['children'][1]['props']['src'], 'picture src replaced');
	pg_assert_same('A plate', $out['children'][1]['props']['alt'], 'no alt given: the component\'s alt kept');
	pg_assert_same('/order', $out['children'][2]['props']['href'], 'link replaced');
	pg_assert_same('bi-star', $out['children'][2]['props']['iconName'], 'a number for an icon is unfit and ignored');
	pg_assert_same(array('h', 'p', 'b', 'r'), array_column($out['children'], 'id'), 'shown false leaves the node out');
	pg_assert_same('inner', $out['children'][3]['children'][0]['props']['text'], 'a placed component inside is not entered');
	pg_assert_same('Our menu', $tree['children'][0]['props']['text'], 'the given tree is not changed');

	$one = pg_co_apply($tree, array('of_title' => 'Dinner'));
	pg_assert_same('Open daily', $one['children'][3]['children'][0]['props']['text'], 'a repeated id applies to its first node only');

	$root = $tree;
	$root['props']['_open'] = array(array('id' => 'of_all', 'kind' => 'shown'));
	$kept = pg_co_apply($root, array('of_all' => false, 'of_title' => 'X'));
	pg_assert_same('X', $kept['children'][0]['props']['text'], 'the root is never left out, its children still take values');

	$alt = pg_co_apply($tree, array('of_pic' => array('src' => '/c.jpg', 'alt' => 'Soup')));
	pg_assert_same('Soup', $alt['children'][1]['props']['alt'], 'an alt given replaces the component\'s');
}

function test_component_overrides_nothing_to_apply_is_the_same_tree()
{
	$tree = component_overrides_test_tree();
	pg_assert_same($tree, pg_co_apply($tree, array()), 'no values');
	pg_assert_same($tree, pg_co_apply($tree, array('of_gone' => 'x', 'of_icon' => 1)), 'only left-overs and unfit values');

	$plain = array('type' => 'root', 'props' => array(), 'children' => array(array('type' => 'content', 'props' => array('text' => 'a'), 'children' => array())));
	pg_assert_same(json_encode($plain), json_encode(pg_co_apply($plain, array('of_x' => 'b'))), 'a component with no open field draws as it is');
}

function test_component_overrides_leftovers()
{
	pg_assert_same(array('of_icon', 'of_gone', 'of_inner'), pg_co_leftovers(component_overrides_test_tree(), component_overrides_test_values()),
		'a closed field, a removed node, an unfit value; in the place\'s order');
	pg_assert_same(array(), pg_co_leftovers(component_overrides_test_tree(), array()), 'no values, no left-overs');
}

function test_component_overrides_clean_value_and_place_values()
{
	pg_assert_same(array('src' => '/x.png', 'alt' => null), pg_co_clean_value('picture', '/x.png'), 'a string picture is its src');
	pg_assert_same(null, pg_co_clean_value('picture', array('alt' => 'no src')), 'a picture needs a src');
	pg_assert_same(null, pg_co_clean_value('shown', 'false'), 'shown needs a boolean');
	pg_assert_same(null, pg_co_clean_value('size', 'x'), 'unknown kind');

	pg_assert_same(array('of_a' => 'x'), pg_co_place_values(array('type' => 'shared_ref', 'props' => array('_place' => array('of_a' => 'x')))), 'the place\'s values');
	pg_assert_same(array(), pg_co_place_values(array('type' => 'shared_ref', 'props' => array('_place' => 'x'))), 'not an object: none');
	pg_assert_same(array(), pg_co_place_values(null), 'no node: none');
}

function test_component_overrides_browser_twin_answers_the_same()
{
	$tree = component_overrides_test_tree();
	$values = component_overrides_test_values();
	$root = $tree;
	$root['props']['_open'] = array(array('id' => 'of_all', 'kind' => 'shown'));
	$cases = array(
		array($tree, $values),
		array($tree, array('of_title' => 'Dinner')),
		array($tree, array('of_pic' => array('src' => '/c.jpg', 'alt' => 'Soup'), 'of_note' => true)),
		array($root, array('of_all' => false, 'of_title' => 'X')),
	);

	$js = component_overrides_test_node('var cases = ' . json_encode($cases, JSON_UNESCAPED_UNICODE) . ';'
		. ' out = {fields: C.openFields(cases[0][0]), runs: cases.map(function (c) { return [C.apply(c[0], c[1]), C.leftovers(c[0], c[1])]; }),'
		. ' same: C.apply(cases[0][0], {}) === cases[0][0], untouched: cases[0][0].children[0].props.text,'
		. ' id: C.newFieldId(["of_aaaaaaaa"]), valid: [C.validFieldId("of_ab12"), C.validFieldId("Of_x"), C.validFieldId(12)],'
		. ' clean: [C.cleanValue("picture", "/x.png"), C.cleanValue("shown", "false"), C.cleanValue("size", "x")],'
		. ' place: [C.placeValues({props: {_place: {of_a: "x"}}}), C.placeValues({props: {_place: "x"}}), C.placeValues(null)]};');

	if ($js === null) {
		return;
	}

	pg_assert_same(pg_co_open_fields($tree), $js['fields'], 'the same open fields');
	foreach ($cases as $i => $case) {
		pg_assert_same(pg_co_apply($case[0], $case[1]), $js['runs'][$i][0], 'the same drawn tree, case ' . $i);
		pg_assert_same(pg_co_leftovers($case[0], $case[1]), $js['runs'][$i][1], 'the same left-overs, case ' . $i);
	}
	pg_assert_true($js['same'], 'nothing to apply answers the same object');
	pg_assert_same('Our menu', $js['untouched'], 'apply never changes the given tree');
	pg_assert_true(preg_match('/^of_[a-z0-9]{8}$/', $js['id']) === 1, 'the same id shape: ' . $js['id']);
	pg_assert_same(array(true, false, false), $js['valid'], 'the same id rule');
	pg_assert_same(array(pg_co_clean_value('picture', '/x.png'), null, null), $js['clean'], 'the same value rules');
	pg_assert_same(array(array('of_a' => 'x'), array(), array()), $js['place'], 'the same place values');
}

// The component's settings keep the order every place lists the fields in:
// the root's `props._openOrder`. Ids it names come first, in its order; the
// rest follow in document order; an id no open field has is skipped. The
// designer's trees name a node by `_id`, which is the field's nodeId.
function test_component_overrides_order_and_designer_ids()
{
	$tree = component_overrides_test_tree();
	$tree['props']['_openOrder'] = array('of_note', 'of_gone', 'of_link', 7);
	$tree['children'][0]['_id'] = 'sd_7';
	$ids = array_map(function ($f) { return $f['id']; }, pg_co_open_fields($tree));
	pg_assert_same(array('of_note', 'of_link', 'of_title', 'of_pic', 'of_icon', 'of_ref'), $ids, 'the stored order first, then document order');
	$fields = pg_co_open_fields($tree);
	pg_assert_same('sd_7', $fields[2]['nodeId'], 'a designer node is named by its _id');

	$js = component_overrides_test_node('out = C.openFields(' . json_encode($tree, JSON_UNESCAPED_UNICODE) . ');');
	if ($js === null) {
		return;
	}
	pg_assert_same($fields, $js, 'the twin orders the same');
}

// The "Can change per place" part (assets/js/style_designer_overrides.js)
// run in Node with a stub kit: which fields an element offers, the labels,
// and a field opened from the list writes the component's tree through the
// core's _sdCoWrite(). Skipped where Node cannot run.
function test_component_overrides_part_fields_and_labels()
{
	$code = "var StyleDesigner = { definePart: function (n, f) { part = f(kit); } }, part = null, writes = 0;\n"
		. "var comp = {_id: 'c', type: 'root', props: {}, children: [\n"
		. " {_id: 'b', type: 'content', props: {contentType: 'button', text: 'Shop now', href: '/shop', customName: 'Button'}, children: []},\n"
		. " {_id: 'i', type: 'content', props: {contentType: 'image', src: '/a.jpg', _bindings: {src: 'image'}}, children: []}]};\n"
		. "var kit = { _sdT: function (k, v) { return v === undefined ? k : k.replace('{var}', v); }, esc: function (s) { return String(s); },\n"
		. " getNodeLabel: function (n) { return n.props.customName || n._id; }, sharedCache: {5: {name: 'Promo card', tree: comp}},\n"
		. " _resolveSharedSid: function () { return 5; }, _sdCoWrite: function (sid, fn) { writes++; fn(); } };\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_overrides.js') . "\n"
		. "out = { button: part.kindsOf(comp.children[0], false), root: part.kindsOf(comp, true), image: part.kindsOf(comp.children[1], false),\n"
		. " one: part.defaultLabel(comp.children[0], 'text', false), two: part.defaultLabel(comp.children[0], 'link', true) };";
	$js = component_overrides_test_node($code);
	if ($js === null) {
		return;
	}
	pg_assert_same(array('text', 'link', 'shown'), $js['button'], 'a button offers its text, its link and Shown');
	pg_assert_same(array(), $js['root'], 'the component root offers no Shown');
	pg_assert_same(array('picture', 'shown'), $js['image'], 'a picture offers its picture and Shown');
	pg_assert_same('Button', $js['one'], 'one field takes the element name');
	pg_assert_same('Button link', $js['two'], 'a second field adds the field name');
}

// The core offers the row only inside an ordinary component, for roles
// that edit any component, and hands the part what it uses.
function test_component_overrides_core_entry_points()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_true(strpos($src, "_sdCoMayOpen(node) ? [{ sep: true }") !== false, 'the element menu gains the row');
	pg_assert_true(strpos($src, "id: 'act_open_field'") !== false, 'the command palette has the row');
	pg_assert_true(preg_match('/function _sdCoMayOpen\(node\) \{[^}]*_sdIsContentLevel\(\)/', $src) === 1, 'content roles never get the row');
	pg_assert_true(strpos($src, '_isInsideSystemWidget(node)') !== false, 'a system widget is left out');
	$parts = pg_designer_parts();
	pg_assert_same('assets/js/style_designer_overrides.min.js', $parts['overrides'], 'the part is loaded on first use');
}

// An element duplicated inside a component carries its open fields with the
// same ids; the first node keeps them. A place that hides the first one
// must not hand its other values to the copy: the copy draws as the
// component has it, in both twins.
function test_component_overrides_hidden_element_keeps_its_fields_from_a_copy()
{
	$card = array('_id' => 'a', 'type' => 'content', 'props' => array('text' => 'Our menu', '_open' => array(
		array('id' => 'of_s', 'kind' => 'shown', 'label' => 'Shown'),
		array('id' => 'of_t', 'kind' => 'text', 'label' => 'Title'),
	)), 'children' => array());
	$copy = $card;
	$copy['_id'] = 'b';
	$inner = array('_id' => 'n', 'type' => 'container', 'props' => array('_open' => array(array('id' => 'of_box', 'kind' => 'shown', 'label' => 'Box'))), 'children' => array(
		array('_id' => 'nt', 'type' => 'content', 'props' => array('text' => 'Open daily', '_open' => array(array('id' => 'of_note', 'kind' => 'text', 'label' => 'Note'))), 'children' => array()),
	));
	$innerCopy = $inner;
	$innerCopy['_id'] = 'n2';
	$innerCopy['children'][0]['_id'] = 'nt2';
	$tree = array('_id' => 'r', 'type' => 'root', 'props' => array(), 'children' => array($card, $copy, $inner, $innerCopy));
	$values = array('of_s' => false, 'of_t' => 'Lunch', 'of_box' => false, 'of_note' => 'Closed');

	$out = pg_co_apply($tree, $values);
	pg_assert_same(array('b', 'n2'), array_column($out['children'], '_id'), 'the hidden elements are left out');
	pg_assert_same('Our menu', $out['children'][0]['props']['text'], 'the copy keeps the component\'s text');
	pg_assert_same('Open daily', $out['children'][1]['children'][0]['props']['text'], 'a field inside a hidden element does not pass to the copy');
	pg_assert_same(array(), pg_co_place_texts($tree, $values), 'no place text is drawn, none is extracted');

	$js = component_overrides_test_node('out = C.apply(' . json_encode($tree, JSON_UNESCAPED_UNICODE) . ', ' . json_encode($values) . ');');
	if ($js === null) {
		return;
	}
	pg_assert_same($out, $js, 'the twin draws the same');
}

// A label typed in the list of an element's fields is kept when the list is
// closed before the field is left (a click outside removes it, and a removed
// field fires no change); Escape drops it.
function test_component_overrides_list_keeps_a_typed_label_on_close()
{
	$code = "var StyleDesigner = { definePart: function (n, f) { part = f(kit); } }, part = null;\n"
		. "var comp = {_id: 'c', type: 'root', props: {}, children: [{_id: 'b', type: 'content', props: {contentType: 'button', text: 'Shop', href: '/s',\n"
		. " _open: [{id: 'of_t', kind: 'text', label: 'b'}]}, children: []}]};\n"
		. "var label = { value: '', defaultValue: 'b', getAttribute: function (a) { return a === 'data-co-label' ? 'text' : null; } };\n"
		. "var listeners = {}, pop = { setAttribute: function () {}, offsetWidth: 300, offsetHeight: 200, style: {}, set innerHTML(v) {},\n"
		. " addEventListener: function (t, f) { listeners[t] = f; }, querySelector: function () { return null; },\n"
		. " querySelectorAll: function (s) { return /data-co-label/.test(s) ? [label] : []; }, contains: function () { return true; }, remove: function () {} };\n"
		. "window.innerWidth = 1200; window.innerHeight = 800;\n"
		. "var document = { createElement: function () { return pop; }, body: { appendChild: function () {} }, activeElement: label,\n"
		. " addEventListener: function () {}, removeEventListener: function () {}, getElementById: function () { return null; } };\n"
		. "var kit = { _sdT: function (k) { return k; }, esc: String, getNodeLabel: function (n) { return n._id; }, sharedCache: {5: {tree: comp}},\n"
		. " _resolveSharedSid: function () { return 5; }, _sdCoWrite: function (sid, fn) { fn(); }, _sdTipsOff: function () { return true; } };\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_overrides.js') . "\n"
		. "var entry = comp.children[0].props._open[0];\n"
		. "part.openFor(comp.children[0], null); label.value = 'Dish name'; part.close();\n"
		. "out = { kept: entry.label };\n"
		. "part.openFor(comp.children[0], null); label.value = 'Other';\n"
		. "listeners.keydown({ key: 'Escape', target: label, preventDefault: function () {}, stopPropagation: function () {} });\n"
		. "out.escape = entry.label;\n"
		. "part.openFor(comp.children[0], null); label.value = 'Again'; listeners.change({ target: label }); part.close();\n"
		. "out.once = entry.label;";
	$js = component_overrides_test_node($code);
	if ($js === null) {
		return;
	}
	pg_assert_same('Dish name', $js['kept'], 'a click outside keeps the typed label');
	pg_assert_same('Dish name', $js['escape'], 'Escape drops what was typed');
	pg_assert_same('Again', $js['once'], 'a label already kept by its change is kept once');
}

// Undo puts the component's tree back as a new copy. A switch flipped in the
// list of an element's fields afterwards writes into the tree the editor has
// now, never into the element as it was before the Undo.
function test_component_overrides_list_writes_the_live_tree_after_undo()
{
	$code = "var StyleDesigner = { definePart: function (n, f) { part = f(kit); } }, part = null;\n"
		. "var comp = {_id: 'c', type: 'root', props: {}, children: [{_id: 'b', type: 'content', props: {contentType: 'button', text: 'Shop', href: '/s'}, children: []}]};\n"
		. "var listeners = {}, pop = { setAttribute: function () {}, offsetWidth: 300, offsetHeight: 200, style: {}, set innerHTML(v) {},\n"
		. " addEventListener: function (t, f) { listeners[t] = f; }, querySelector: function () { return null; },\n"
		. " querySelectorAll: function () { return []; }, contains: function () { return true; }, remove: function () {} };\n"
		. "window.innerWidth = 1200; window.innerHeight = 800;\n"
		. "var document = { createElement: function () { return pop; }, body: { appendChild: function () {} }, activeElement: null,\n"
		. " addEventListener: function () {}, removeEventListener: function () {}, getElementById: function () { return null; } };\n"
		. "var kit = { _sdT: function (k) { return k; }, esc: String, getNodeLabel: function (n) { return n._id; }, sharedCache: {5: {tree: comp}},\n"
		. " _resolveSharedSid: function () { return 5; }, _sdCoWrite: function (sid, fn) { fn(); }, _sdTipsOff: function () { return true; } };\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_overrides.js') . "\n"
		. "var sw = function (kind, on) { return { target: { checked: on, getAttribute: function (a) { return a === 'data-co-kind' ? kind : null; } } }; };\n"
		. "part.openFor(comp.children[0], null);\n"
		. "listeners.change(sw('text', true));\n"
		. "kit.sharedCache[5].tree = JSON.parse(JSON.stringify(comp));\n"
		. "listeners.change(sw('link', true));\n"
		. "var live = kit.sharedCache[5].tree.children[0].props._open || [];\n"
		. "out = live.map(function (e) { return e.kind; });";
	$js = component_overrides_test_node($code);
	if ($js === null) {
		return;
	}
	pg_assert_same(array('text', 'link'), $js, 'the field opened after the Undo is in the live tree');
}
