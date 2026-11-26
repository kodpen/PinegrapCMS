<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the library item model (includes/fn/designer_library.php): a
 * subtree made into an item (its rules, variables, rows and record
 * references), an item inserted into a design (rules added or renamed,
 * variables, references kept, found by name or cleared and marked), the
 * file text, and who may do what.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _library_test_subtree()
{
	return array(
		'_id' => 'sd_4', 'type' => 'section', '_expanded' => true,
		'props' => array('cssClass' => 'py-5 hero-band', '_notes' => 'ask the client', '_notes_by' => 'Ayşe'),
		'children' => array(
			array('_id' => 'sd_5', 'type' => 'div', 'props' => array('cssClass' => 'row'), 'children' => array(
				array('_id' => 'sd_6', 'type' => 'h2', 'props' => array('text' => 'Hello', 'cssClass' => 'hero-title',
					'style' => 'color: var(--brand)')),
				array('_id' => 'sd_7', 'type' => 'shared_ref', 'props' => array('sharedId' => 41, 'sharedName' => 'Signup'), 'children' => array()),
				array('_id' => 'sd_8', 'type' => 'shared_ref', 'props' => array('sharedId' => 42, 'sharedName' => 'Footer note'), 'children' => array()),
				array('_id' => 'sd_9', 'type' => 'shared_ref', 'props' => array('sharedId' => 99), 'children' => array()),
			)),
		),
	);
}

function _library_test_css()
{
	return "/* the band */\n.hero-band { background: var(--band-bg); padding: 2rem }\n"
		. ".hero-title { font-weight: 700; }\n"
		. "@media (min-width: 768px) {\n  .hero-band .row { gap: 1rem; }\n}\n"
		. ".other { color: red; }\n.row { margin: 0; }\n"
		. ":root { --brand: #c00; --band-bg: var(--band-base); --band-base: #eee; --unused: 1px; }\n";
}

function _library_test_item()
{
	$design = array(
		'css' => _library_test_css(),
		'vars' => pg_library_css_var_defs(_library_test_css()),
		'framework' => array('row' => true, 'py-5' => true),
	);
	$site = array(
		'rows' => array(
			41 => array('name' => 'Signup [2]', 'tree' => array('type' => 'div', '_id' => 'w1', 'children' => array()),
				'config' => array('regionType' => 'custom_form', 'page_id' => 12, 'contact_group_id' => '3', 'template_origin' => 'x')),
			42 => array('name' => 'Footer note', 'tree' => array('type' => 'p', 'props' => array('text' => 'Hi')), 'config' => null),
		),
		'pages' => array(12 => 'Contact'),
		'groups' => array(3 => 'Newsletter'),
	);
	return pg_library_item_from_subtree(_library_test_subtree(), array('name' => "  Hero\n band ", 'group' => 'Heroes'), $design, $site);
}

function test_library_item_from_subtree()
{
	$item = _library_test_item();

	pg_assert_same(PG_LIBRARY_FORMAT, $item['format']);
	pg_assert_same('Hero band', $item['name'], 'one line');
	pg_assert_same('Heroes', $item['group']);
	pg_assert_false(isset($item['tree']['_id']), 'the editor ids go');
	pg_assert_false(isset($item['tree']['_expanded']));
	pg_assert_false(isset($item['tree']['props']['_notes']), 'the notes stay on this site');
	pg_assert_false(isset($item['tree']['props']['_notes_by']));
	pg_assert_same(1, $item['dropped'], 'a placement of a row not given is left out');

	$kids = $item['tree']['children'][0]['children'];
	pg_assert_same(3, count($kids));
	pg_assert_same(array('libraryWidget' => 'w41', 'sharedName' => 'Signup'), $kids[1]['props']);
	pg_assert_same(array('libraryShared' => 's42', 'sharedName' => 'Footer note'), $kids[2]['props']);
	pg_assert_same('custom_form', $item['widgets']['w41']['kind']);
	pg_assert_same('Signup', $item['widgets']['w41']['name'], 'the number of a taken name goes');
	pg_assert_false(isset($item['widgets']['w41']['config']['template_origin']));
	pg_assert_false(isset($item['widgets']['w41']['tree']['_id']));
	pg_assert_same('Hi', $item['shared']['s42']['tree']['props']['text']);

	$selectors = array();
	foreach ($item['rules'] as $r) $selectors[] = $r['selector'];
	pg_assert_same(array('.hero-band', '.hero-title', '.hero-band .row'), $selectors, 'its own classes; not .row alone, not .other');
	pg_assert_same(array('@media (min-width: 768px)'), $item['rules'][2]['media']);

	pg_assert_same(array('--band-base' => '#eee', '--band-bg' => 'var(--band-base)', '--brand' => '#c00'), $item['variables'],
		'read by the tree and the rules, and the ones their values read');
	pg_assert_same(array('page' => array(12 => 'Contact'), 'group' => array(3 => 'Newsletter')), $item['refs']);
}

function test_library_item_into_the_same_design()
{
	$item = _library_test_item();
	$out = pg_library_item_into_design($item, array(
		'css' => _library_test_css(),
		'vars' => pg_library_css_var_defs(_library_test_css()),
		'framework' => array('row' => true, 'py-5' => true),
	), array('pages' => array(12 => 'Contact'), 'groups' => array(3 => 'Newsletter'), 'shared' => array(42 => 'Footer note')));

	pg_assert_same('', $out['css'], 'every rule and variable is there already');
	pg_assert_same(array(), $out['renamed']);
	pg_assert_same(0, $out['cleared']);
	pg_assert_same(12, $out['widgets']['w41']['config']['page_id']);
	pg_assert_same('3', $out['widgets']['w41']['config']['contact_group_id'], 'a value keeps its type');
	$kids = $out['tree']['children'][0]['children'];
	pg_assert_same(42, $kids[2]['props']['sharedId'], 'the component the site has is placed again');
	pg_assert_false(isset($kids[2]['props']['libraryShared']));
	pg_assert_same(array(), $out['shared']);
	pg_assert_same('libraryWidget', key($kids[1]['props']), 'a widget is always made anew');
}

function test_library_item_into_another_design()
{
	$item = _library_test_item();
	$css = ".hero-title { font-weight: 300 }\n.hero-title-2 { color: blue }\n:root { --brand: #00c }";
	$out = pg_library_item_into_design($item, array(
		'css' => $css,
		'vars' => pg_library_css_var_defs($css),
		'framework' => array('row' => true, 'py-5' => true),
	), array('pages' => array(7 => 'Contact', 12 => 'About'), 'groups' => array(5 => 'Members'), 'shared' => array()));

	pg_assert_same(array('hero-title' => 'hero-title-3'), $out['renamed'], 'a rule of that name that differs stays; a free name');
	pg_assert_same(3, $out['rules_added']);
	pg_assert_contains('.hero-title-3 {', $out['css']);
	pg_assert_contains(".hero-band {\n    background: var(--band-bg);\n    padding: 2rem\n}", $out['css']);
	pg_assert_contains("@media (min-width: 768px) {\n    .hero-band .row {", $out['css']);
	pg_assert_false(strpos($out['css'], '.other') !== false);
	pg_assert_false(strpos($out['css'], '--brand') !== false, 'the design value wins');
	pg_assert_contains('--band-bg: var(--band-base);', $out['css'], 'a missing variable gets the item value');
	pg_assert_same(array('--band-base' => '#eee', '--band-bg' => 'var(--band-base)'), $out['vars_added']);

	$h2 = $out['tree']['children'][0]['children'][0];
	pg_assert_same('hero-title-3', $h2['props']['cssClass']);
	pg_assert_same('py-5 hero-band', $out['tree']['props']['cssClass']);

	$w = $out['widgets']['w41'];
	pg_assert_same(7, $w['config']['page_id'], 'the page of the same name');
	pg_assert_same('', $w['config']['contact_group_id'], 'no group of that name: cleared');
	pg_assert_same(array('group'), $w['cleared']);
	pg_assert_same(1, $out['cleared']);
	pg_assert_same(array('group'), $out['tree']['children'][0]['children'][1]['props']['_libraryUnbound'], 'the placement is marked');
	pg_assert_same('Footer note', $out['shared']['s42']['name'], 'a component the site lacks is made');
}

function test_library_reference_by_name_must_be_one()
{
	$item = array('format' => PG_LIBRARY_FORMAT, 'version' => 1, 'name' => 'x',
		'tree' => array('type' => 'a', 'props' => array('_bindParams' => array('href' => array('page_id' => '12')))),
		'refs' => array('page' => array(12 => 'Contact')));
	$out = pg_library_item_into_design($item, array(), array('pages' => array(3 => 'Contact', 4 => 'Contact')));
	pg_assert_same('', $out['tree']['props']['_bindParams']['href']['page_id'], 'two of the name: not a guess');
	pg_assert_same(array('page'), $out['tree']['props']['_libraryUnbound']);
}

function test_library_css_rules()
{
	$rules = pg_library_css_rules("a{x:'}'} @font-face{font-family:F} @media screen{@supports (display:grid){.g{display:grid}}} .b{}");
	pg_assert_same(3, count($rules));
	pg_assert_same("x:'}'", $rules[0]['body'], 'a brace in a string');
	pg_assert_same(array('@media screen', '@supports (display:grid)'), $rules[1]['media']);
	pg_assert_same(array('g', 'h'), pg_library_selector_classes('.g > .h:not(.g)'));
	pg_assert_same(array(), pg_library_selector_classes('a[title=".x"]'));
	pg_assert_same(array('--a' => '1px'), pg_library_css_var_defs(':root{--a:1px;--b:}x{}@media print{:root{--c:2}}'));
	pg_assert_same(array(), pg_library_css_var_defs(':root{--a: 1px </style> }'), 'a value that leaves the rule');
}

function test_library_file_round_trip()
{
	$item = _library_test_item();
	$back = pg_library_item_decode(pg_library_item_encode($item));
	pg_assert_true(is_array($back));
	pg_assert_same($item['tree'], $back['tree']);
	pg_assert_same($item['rules'], $back['rules']);
	pg_assert_false(isset($back['dropped']));

	pg_assert_same('not_an_item', pg_library_item_decode('{"format":"x"}'));
	pg_assert_same('version', pg_library_item_decode('{"format":"pinegrap-library-item","version":9}'));
	pg_assert_same('no_name', pg_library_item_decode('{"format":"pinegrap-library-item","version":1,"tree":{"type":"div"},"name":" "}'));
	$bad = pg_library_item_decode('{"format":"pinegrap-library-item","version":1,"tree":{"type":"div"},"name":"n",'
		. '"rules":[{"selector":".a","body":"x:1}</style><script>"},{"selector":".b","body":"y:2","media":["@media x{"]}],'
		. '"variables":{"--ok":"1px","bad":"1","--x":"}"}}');
	pg_assert_same(1, count($bad['rules']), 'a rule that closes its block early is dropped');
	pg_assert_same(array(), $bad['rules'][0]['media']);
	pg_assert_same(array('--ok' => '1px'), $bad['variables']);

	pg_assert_same('library-hero-band', pg_library_file_slug('Hero band!'));
	pg_assert_same('library-item', pg_library_file_slug('Çğ'));
	pg_assert_same(array('Cards', 'heroes'), pg_library_groups(array(array('group' => 'heroes'), array('group' => 'Cards'), array('group' => ''), array('group' => 'Cards'))));
}

function test_library_rights()
{
	pg_assert_true(pg_library_may('use', array('role' => 3)), 'a role that may add elements uses it');
	pg_assert_true(pg_library_may('use', array('role' => 1)));
	foreach (array('save', 'manage', 'export', 'import') as $a) {
		pg_assert_true(pg_library_may($a, array('role' => 0)), $a);
		pg_assert_true(pg_library_may($a, array('role' => 1)), $a);
		pg_assert_false(pg_library_may($a, array('role' => 2)), $a . ' is the designer roles');
	}
	pg_assert_false(pg_library_may('use', array('role' => 9)));
	pg_assert_false(pg_library_may('other', array('role' => 0)));
}

function test_library_store_is_design_files()
{
	pg_assert_contains("files.design = '1'", pg_library_files_where());
	pg_assert_contains("files.name LIKE 'library-%'", pg_library_files_where());
	pg_assert_true(isset(pg_library_framework_classes()['container']), 'Bootstrap classes read from the vocabulary');
}

function test_library_card_for_the_blocks_list()
{
	$item = _library_test_item();
	$item['id'] = 7;
	$card = pg_library_card($item, array('row' => true, 'py-5' => true));
	pg_assert_same(7, $card['id']);
	pg_assert_same('Hero band', $card['name']);
	pg_assert_same('Heroes', $card['group']);
	pg_assert_same('section', $card['kind']);
	// The section, its row, the heading and the two placements kept.
	pg_assert_same(5, $card['count']);
	pg_assert_same(2, $card['rows'], 'one widget and one component to make');
	pg_assert_true($card['bootstrap']);
	pg_assert_same(array('--band-base', '--band-bg', '--brand'), $card['variables']);
	pg_assert_false(pg_library_uses_framework(array('tree' => array('type' => 'div', 'props' => array('cssClass' => 'hero')), 'widgets' => array(), 'shared' => array()), array('row' => true)));
}

function test_library_root_kind()
{
	pg_assert_same('row', pg_library_root_kind(array('type' => 'row', 'props' => array())));
	pg_assert_same('row', pg_library_root_kind(array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'row g-3'))));
	pg_assert_same('card', pg_library_root_kind(array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'card h-100'))));
	pg_assert_same('section', pg_library_root_kind(array('type' => 'semantic', 'props' => array('tag' => 'footer'))));
	pg_assert_same('group', pg_library_root_kind(array('type' => 'container', 'props' => array())));
	pg_assert_same(1, pg_library_node_count(array('type' => 'p')));
	pg_assert_same(0, pg_library_node_count('x'));
}

function test_library_tree_points_at_the_rows_made()
{
	$tree = array('type' => 'section', 'children' => array(
		array('type' => 'shared_ref', 'props' => array('libraryWidget' => 'w41', 'sharedName' => 'Signup', '_libraryUnbound' => array('page')), 'children' => array()),
		array('type' => 'shared_ref', 'props' => array('libraryShared' => 's42'), 'children' => array()),
		array('type' => 'shared_ref', 'props' => array('libraryShared' => 's43'), 'children' => array()),
	));
	$out = pg_library_tree_place_rows($tree, array('w41' => array('id' => 90, 'name' => 'Signup [1]'), 's42' => array('id' => 91, 'name' => 'Footer note')));
	pg_assert_same(array('sharedName' => 'Signup [1]', '_libraryUnbound' => array('page'), 'sharedId' => 90), $out['children'][0]['props']);
	pg_assert_same(array('sharedId' => 91, 'sharedName' => 'Footer note'), $out['children'][1]['props']);
	pg_assert_same(array('libraryShared' => 's43'), $out['children'][2]['props'], 'a key not made is left as it is');
}

function test_library_design_from_the_editor()
{
	$d = _pg_library_request_design(array(
		'css' => ":root { --brand: #c00; }\n",
		'vars' => array('--brand' => '#00f', '--accent' => ' teal ', '--bad' => 'red} body{', 'nope' => '1px'),
	));
	pg_assert_same(array('--brand' => '#c00', '--accent' => 'teal'), $d['vars'], 'the User Styles win; a value that could close its rule is dropped');
	pg_assert_true(isset($d['framework']['row']));
}

function _library_test_shelf()
{
	return array(
		array('id' => 1, 'name' => 'Opening hours', 'group' => '', 'order' => 1),
		array('id' => 2, 'name' => 'Hero with picture', 'group' => 'Heroes', 'order' => 2),
		array('id' => 3, 'name' => 'Hero, two buttons', 'group' => 'Heroes', 'order' => 3),
		array('id' => 4, 'name' => 'Three plans', 'group' => 'Pricing', 'order' => 4),
		array('id' => 5, 'name' => 'Plan comparison', 'group' => 'Pricing', 'order' => 5),
		array('id' => 6, 'name' => 'Footer with map', 'group' => 'Footers', 'order' => 6),
	);
}

// id => group:order of what a change writes.
function _library_test_written($changes)
{
	$out = array();
	foreach ($changes['write'] as $id => $item) $out[$id] = $item['group'] . ':' . $item['order'];
	ksort($out);
	return $out;
}

function test_library_drawn_order()
{
	$items = array(
		array('id' => 1, 'group' => 'B'), array('id' => 2, 'group' => ''), array('id' => 3, 'group' => 'A'),
		array('id' => 4, 'group' => 'B'), array('id' => 5, 'group' => ''),
	);
	pg_assert_same(array(2, 5, 1, 4, 3), array_map(function ($it) { return $it['id']; }, pg_library_drawn_order($items)),
		'no group first, then each group where its first item stands');
}

function test_library_manage_rename()
{
	$c = pg_library_manage_changes(_library_test_shelf(), 'library_rename', array('id' => 2, 'name' => '  Hero with photo '));
	pg_assert_same(array(2), array_keys($c['write']), 'only the renamed item is written');
	pg_assert_same('Hero with photo', $c['write'][2]['name']);
	pg_assert_true(is_string(pg_library_manage_changes(_library_test_shelf(), 'library_rename', array('id' => 2, 'name' => '  '))), 'an empty name is not saved');
	pg_assert_true(is_string(pg_library_manage_changes(_library_test_shelf(), 'library_rename', array('id' => 9, 'name' => 'X'))), 'an unknown item');
}

function test_library_manage_move()
{
	$c = pg_library_manage_changes(_library_test_shelf(), 'library_move', array('ids' => array(2, 6), 'group' => 'Pricing'));
	pg_assert_same(array(2 => 'Pricing:5', 3 => 'Heroes:2', 4 => 'Pricing:3', 5 => 'Pricing:4', 6 => 'Pricing:6'), _library_test_written($c),
		'the moved items follow the group\'s last item');
	$c = pg_library_manage_changes(_library_test_shelf(), 'library_move', array('ids' => array(4), 'group' => 'People'));
	pg_assert_same(array(4 => 'People:6', 5 => 'Pricing:4', 6 => 'Footers:5'), _library_test_written($c), 'a new group goes after every other');
	$c = pg_library_manage_changes(_library_test_shelf(), 'library_move', array('ids' => array(6), 'group' => ''));
	pg_assert_same(array(2 => 'Heroes:3', 3 => 'Heroes:4', 4 => 'Pricing:5', 5 => 'Pricing:6', 6 => ':2'), _library_test_written($c), 'no group: above the groups');
	pg_assert_true(is_string(pg_library_manage_changes(_library_test_shelf(), 'library_move', array('ids' => array(), 'group' => 'X'))));
}

function test_library_manage_order()
{
	// Hero with picture dragged into Pricing, after Three plans.
	$c = pg_library_manage_changes(_library_test_shelf(), 'library_order', array('order' => array(
		array('id' => 1, 'group' => ''), array('id' => 3, 'group' => 'Heroes'), array('id' => 4, 'group' => 'Pricing'),
		array('id' => 2, 'group' => 'Pricing'), array('id' => 5, 'group' => 'Pricing'), array('id' => 6, 'group' => 'Footers'),
	)));
	pg_assert_same(array(2 => 'Pricing:4', 3 => 'Heroes:2', 4 => 'Pricing:3'), _library_test_written($c));
	// The last item of Heroes leaves it: the group is gone.
	$c = pg_library_manage_changes(_library_test_shelf(), 'library_order', array('order' => array(
		array('id' => 1, 'group' => ''), array('id' => 2, 'group' => ''), array('id' => 3, 'group' => ''),
	)));
	pg_assert_same(array(2 => ':2', 3 => ':3'), _library_test_written($c), 'the items left out keep their place after');
	pg_assert_true(is_string(pg_library_manage_changes(_library_test_shelf(), 'library_order', array('order' => array(array('id' => 77))))));
}

function test_library_manage_delete()
{
	$c = pg_library_manage_changes(_library_test_shelf(), 'library_delete', array('ids' => array('4', 4, 99, 5)));
	pg_assert_same(array(4, 5), $c['delete'], 'known items, once each');
	pg_assert_same(array(), $c['write'], 'a delete leaves the order as it is');
	pg_assert_true(is_string(pg_library_manage_changes(_library_test_shelf(), 'library_nothing', array())));
}

function test_library_hunt_shared_id_of_this_site_cleared()
{
	// A file made by hand may place a component by this site's own row id;
	// the export never writes one (_pg_library_portable()).
	$item = array('format' => PG_LIBRARY_FORMAT, 'version' => 1, 'name' => 'x',
		'tree' => array('type' => 'section', 'children' => array(
			array('type' => 'shared_ref', 'props' => array('sharedId' => 9, 'sharedName' => 'Admin menu'), 'children' => array()),
		)));
	$out = pg_library_item_into_design($item, array(), array('shared' => array(9 => 'Admin menu')));
	$ref = $out['tree']['children'][0];
	pg_assert_false(isset($ref['props']['sharedId']), 'not left pointing at this site\'s row');
	pg_assert_same(array('shared'), $ref['props']['_libraryUnbound']);
	pg_assert_same(1, $out['cleared']);
}

function test_library_hunt_product_group_and_calendar_refs()
{
	pg_assert_same('product_group', pg_library_ref_kind('product_group_id'));
	pg_assert_same('product_group', pg_library_ref_kind('quick_add_product_group_id'));
	pg_assert_same('product_group', pg_library_ref_kind('catalog_listing_root_group_id'));
	pg_assert_same('calendar', pg_library_ref_kind('calendar_ids'));
	pg_assert_same('group', pg_library_ref_kind('contact_group_id'));

	$item = pg_library_item_from_subtree(
		array('type' => 'section', 'children' => array(array('type' => 'shared_ref', 'props' => array('sharedId' => 4)))),
		array('name' => 'Shop band'), array(),
		array('rows' => array(4 => array('name' => 'Catalog', 'tree' => array('type' => 'div'),
			'config' => array('regionType' => 'catalog', 'product_group_id' => 3, 'calendar_ids' => array(6, 8)))),
			'product_groups' => array(3 => 'Shoes'), 'calendars' => array(6 => 'Events', 8 => 'Classes')));
	pg_assert_same(array(3 => 'Shoes'), $item['refs']['product_group']);
	pg_assert_same(array(6 => 'Events', 8 => 'Classes'), $item['refs']['calendar']);

	$out = pg_library_item_into_design($item, array(), array('product_groups' => array(3 => 'Hats', 11 => 'Shoes'),
		'calendars' => array(6 => 'Events')));
	$cfg = $out['widgets']['w4']['config'];
	pg_assert_same(11, $cfg['product_group_id'], 'the product group of that name');
	pg_assert_same(array(6, ''), $cfg['calendar_ids'], 'a calendar the site lacks is cleared, not another one');
	pg_assert_same(array('calendar'), $out['widgets']['w4']['cleared']);
}

function test_library_hunt_menu_by_name()
{
	$item = array('format' => PG_LIBRARY_FORMAT, 'version' => 1, 'name' => 'Header',
		'tree' => array('type' => 'section', 'children' => array(
			array('type' => 'shared_ref', 'props' => array('libraryWidget' => 'w5')),
			array('type' => 'shared_ref', 'props' => array('libraryWidget' => 'w6')),
		)),
		'widgets' => array(
			'w5' => array('name' => 'Main menu', 'kind' => 'menu', 'tree' => array('type' => 'div'), 'config' => array('menu_name' => 'Main')),
			'w6' => array('name' => 'Footer menu', 'kind' => 'menu', 'tree' => array('type' => 'div'), 'config' => array('menu_name' => 'Footer links')),
		));
	$out = pg_library_item_into_design($item, array(), array('menus' => array(3 => 'main', 4 => 'Shop')));
	pg_assert_same('Main', $out['widgets']['w5']['config']['menu_name'], 'the site has a menu of that name');
	pg_assert_same(array(), $out['widgets']['w5']['cleared']);
	pg_assert_same('', $out['widgets']['w6']['config']['menu_name'], 'no menu of that name: cleared');
	pg_assert_same(array('menu'), $out['widgets']['w6']['cleared']);
	pg_assert_same(array('menu'), $out['tree']['children'][1]['props']['_libraryUnbound'], 'the placement is marked');
	pg_assert_false(isset($out['tree']['children'][0]['props']['_libraryUnbound']));
	pg_assert_same(1, $out['cleared']);

	$saved = pg_library_item_from_subtree(array('type' => 'div', 'props' => array('menu_name' => 'Main')), array('name' => 'x'), array(), array());
	pg_assert_same('Main', $saved['tree']['props']['menu_name'], 'saving keeps the name');
	pg_assert_false(isset($saved['refs']['menu']));
}

function test_library_missing_pictures_counted()
{
	$item = array('format' => PG_LIBRARY_FORMAT, 'version' => 1, 'name' => 'Gallery',
		'tree' => array('type' => 'section', 'props' => array('style' => 'background-image: url("/hero.jpg")'), 'children' => array(
			array('type' => 'img', 'props' => array('src' => '/team.jpg')),
			array('type' => 'img', 'props' => array('src' => '/team.jpg', 'srcset' => '/team.jpg 1x, /logo.png 2x')),
			array('type' => 'img', 'props' => array('src' => 'https://cdn.example.com/far.jpg')),
			array('type' => 'img', 'props' => array('src' => 'data:image/png;base64,AAAA')),
			array('type' => 'img', 'props' => array('src' => '{{image_url}}')),
			array('type' => 'a', 'props' => array('href' => '/about')),
		)));
	$has = function ($name) { return $name === 'logo.png'; };
	$out = pg_library_item_into_design($item, array(), array('path' => '/', 'has_file' => $has));
	pg_assert_same(2, $out['pictures_missing'], 'two missing (one used twice); another host, data:, a placeholder and one the site has are not counted');
	pg_assert_same('/team.jpg', $out['tree']['children'][0]['props']['src'], 'the address stays');
	pg_assert_false(isset($out['tree']['children'][0]['props']['_libraryUnbound']), 'no mark');

	pg_assert_same(1, pg_library_missing_pictures(array('/sub/a.webp', '/sub/a.webp', '/b.jpg', 'c.gif', '/sub/x/d.jpg'), '/sub/',
		function ($name) { return $name === 'c.gif'; }), 'under the site path only, folders are not file names');
	pg_assert_same(0, pg_library_missing_pictures(array('?v=1', '#top', '', 'https://shop.test'), '/', function ($name) { return false; }, 'shop.test:8080'),
		'nothing left of an address once its query is dropped');
	pg_assert_same(1, pg_library_missing_pictures(array('https://shop.test/a.jpg', 'https://other.test/b.jpg', '//shop.test/c.jpg?v=2'), '/',
		function ($name) { return $name === 'c.jpg'; }, 'shop.test'), 'this site\'s own host counts');
	pg_assert_same(0, pg_library_item_into_design($item, array(), array())['pictures_missing'], 'no callback: nothing counted');
}
