<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Prototype view's server read (includes/fn/prototype_map.php):
 * what a request may ask about, the items of its hrefs, a classic menu's
 * links, the maps without the pages hidden from the operator, and the
 * links of common and designer regions.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_map.php');

function prototype_map_test_env()
{
	return array('host' => 'example.com', 'path' => '/', 'software_directory' => 'pinegrap');
}

// Strings only, each once, no empty or over-long one, at most the limit.
function test_prototype_map_request_keeps_strings_within_limits()
{
	$asked = pg_proto_map_request(array(
		'hrefs' => array('/about', '/about', '', 5, array('/x'), str_repeat('a', 2049), '/shop'),
		'menus' => 'Top',
		'regions' => array('Footer'),
	));

	pg_assert_same(array('/about', '/shop'), $asked['hrefs'], 'hrefs: strings, once, within length');
	pg_assert_same(array(), $asked['menus'], 'a non-list is no list');
	pg_assert_same(array('Footer'), $asked['regions'], 'regions');
	pg_assert_same(3, count(pg_proto_map_strings(array('a', 'b', 'c', 'd'), 3, 10)), 'at most the limit');
	pg_assert_same(array(), pg_proto_map_request(null)['hrefs'], 'no request, nothing asked');
}

// Only the hrefs naming something of this site give an item; once each.
function test_prototype_map_items_of_site_hrefs()
{
	pg_assert_same(
		array('about', 'shop/red-shirt', 'about/', ''),
		pg_proto_map_items(array('/about', 'https://other.org/', 'mailto:a@b.c', '#top', '/shop/red-shirt', '/about/', '/', 'about'), prototype_map_test_env()),
		'site items in first-seen order'
	);
}

// A menu's items in printing order, children after their parent; a page
// link gives the page's address, a hidden page none and no page id.
function test_prototype_map_menu_links_in_order()
{
	$tree = pg_menu_tree_from_rows(array(
		array('id' => 1, 'parent_id' => 0, 'name' => 'Home', 'link_page_id' => 10, 'link_page_name' => 'home', 'link_page_folder_id' => 1, 'link_url' => '', 'link_target' => ''),
		array('id' => 2, 'parent_id' => 0, 'name' => 'Staff', 'link_page_id' => 11, 'link_page_name' => 'staff', 'link_page_folder_id' => 9, 'link_url' => '', 'link_target' => ''),
		array('id' => 3, 'parent_id' => 1, 'name' => 'Blog', 'link_page_id' => 0, 'link_page_name' => null, 'link_page_folder_id' => null, 'link_url' => 'https://blog.example.org/', 'link_target' => 'New Window'),
		array('id' => 4, 'parent_id' => 0, 'name' => 'Gone', 'link_page_id' => 12, 'link_page_name' => null, 'link_page_folder_id' => null, 'link_url' => '', 'link_target' => ''),
	), array());
	$hidden = function ($page_id, $folder_id) { return $folder_id === 9; };
	$links = pg_proto_map_menu_links($tree, array('path' => '/site/'), $hidden);

	pg_assert_same(array(1, 3, 2, 4), array_column($links, 'id'), 'printing order, a child after its parent');
	pg_assert_same(array('/site/home', 'https://blog.example.org/', '', ''), array_column($links, 'href'), 'a page address, a URL, nothing for a hidden or deleted page');
	pg_assert_same(array(10, 0, 0, 12), array_column($links, 'page_id'), 'a hidden page keeps no id');
	pg_assert_same(array(false, false, true, false), array_column($links, 'hidden'), 'the hidden page is marked');
	pg_assert_same(array(0, 1, 0, 0), array_column($links, 'parent'), 'parents');
	pg_assert_same('New Window', $links[1]['target'], 'the window kept');
}

// A hidden page is left out of the maps, its folded index too; the item
// names reaching it (itself, a pretty address under it, another letter
// case) come back as hidden; a visible page and the rest stay.
function test_prototype_map_visible_leaves_hidden_pages_out()
{
	$maps = pg_link_maps_fold(array(
		'pages' => array(
			'about' => array('id' => 1, 'type' => 'standard', 'layout_type' => 'system', 'folder' => 1),
			'staff' => array('id' => 2, 'type' => 'catalog', 'layout_type' => 'custom', 'folder' => 9),
		),
		'files' => array('logo.png' => 5),
		'short_links' => array(),
		'products' => array('red' => 7),
		'product_groups' => array(),
		'home_page_id' => 1,
	));
	$items = array('about', 'staff', 'Staff', 'staff/red', 'logo.png', 'nothing');
	$visible = pg_proto_map_visible($maps, $items, function ($page_id, $folder_id) { return $folder_id === 9; });

	pg_assert_same(array('about'), array_keys($visible['maps']['pages']), 'the hidden page left out');
	pg_assert_false(isset($visible['maps']['folded']['pages']['staff']), 'and out of the folded index');
	pg_assert_same(array('staff', 'Staff', 'staff/red'), $visible['hidden'], 'the names reaching it are hidden');
	pg_assert_same(array('type' => 'unknown', 'id' => 0, 'class' => 'unknown'), pg_link_resolve('/staff', $visible['maps'], prototype_map_test_env()), 'it resolves to nothing');
	pg_assert_same(array('type' => 'file', 'id' => 5, 'class' => 'file'), pg_link_resolve('/logo.png', $visible['maps'], prototype_map_test_env()), 'the rest resolves');

	$all = pg_proto_map_visible($maps, $items, function () { return false; });

	pg_assert_same($maps, $all['maps'], 'nothing hidden, the maps as they were');
	pg_assert_same(array(), $all['hidden'], 'and no hidden name');
}

// A region's kind and the hrefs of its content, once each; a name with no
// region says so.
function test_prototype_map_region_links()
{
	$links = pg_proto_map_region_links(array(
		'Footer' => array('cregion_id' => 1, 'cregion_name' => 'Footer', 'cregion_designer_type' => 'no', 'cregion_content' => '<a href="/about">A</a> <a href="/about">B</a> <script>var a = "<a href=\'/x\'>";</script><a href="mailto:a@b.c">M</a>'),
		'Promo' => array('cregion_id' => 2, 'cregion_name' => 'Promo', 'cregion_designer_type' => 'yes', 'cregion_content' => '<p>No links</p>'),
		'Missing' => null,
	));

	pg_assert_same(array('kind' => 'common', 'hrefs' => array('/about', 'mailto:a@b.c')), $links['Footer'], 'a common region\'s links, outside scripts, once');
	pg_assert_same(array('kind' => 'designer', 'hrefs' => array()), $links['Promo'], 'a designer region without links');
	pg_assert_same(array('kind' => '', 'hrefs' => array()), $links['Missing'], 'no region by that name');
}

// The designer endpoint answers the read, open to content-level operators.
function test_prototype_map_is_a_designer_read()
{
	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/designer.php');

	pg_assert_contains("'revisions_list', 'revision_get', 'prototype_map');", $panel, 'content-level operators may read it');
	pg_assert_contains("case 'prototype_map':", $panel, 'the sub action');
	pg_assert_contains("respond(pg_proto_map(\$request, \$user));", $panel, 'answered by pg_proto_map()');
}

// Page ids of links kept by page id: whole numbers above 0, each once.
function test_prototype_map_request_page_ids()
{
	pg_assert_same(array(7, 3, 12), pg_proto_map_request(array('page_ids' => array(7, '3', 7, 0, -2, '04', 'x', 1.5, '12', array(9))))['page_ids']);
	pg_assert_same(array(), pg_proto_map_request(array('page_ids' => '7'))['page_ids'], 'a non-list is no list');
	pg_assert_same(array(1, 2), pg_proto_map_ids(array(1, 2, 3), 2), 'at most the limit');
}

// An id whose page the visible maps left out is answered by its id alone;
// an id of no page is no hidden one.
function test_prototype_map_hidden_ids()
{
	$maps = array('pages' => array('about' => array('id' => 7, 'folder' => 1)));

	pg_assert_same(array(9), pg_proto_map_hidden_ids(array(7, 9, 10), array(7 => 'about', 9 => 'secret'), $maps));
	pg_assert_same(array(), pg_proto_map_hidden_ids(array(), array(), $maps));
}
