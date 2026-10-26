<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Menu system widget (includes/fn/widgets_menu.php) and its
 * sub-item lists (includes/fn/widgets_lists_menu.php): the rows a menu
 * gives, the item of the page shown, hidden items, the window of a link,
 * the lists drawn level by level, and the places the kind is registered in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A menu as pg_menu_tree() reads it: Home, Shop (Shoes (Boots), Bags),
// Members (security on, page in folder 9), Help (a URL in a new window).
function _mw_items()
{
	$item = function ($id, $parent, $name, $page_id = 0, $page = '', $url = '', $target = 'Same Window', $security = 0, $folder = 1) {
		return array('id' => $id, 'parent_id' => $parent, 'name' => $name, 'link_page_id' => $page_id,
			'link_page_name' => $page, 'link_page_folder_id' => $folder, 'link_url' => $url,
			'link_target' => $target, 'security' => $security, 'class' => '');
	};
	return array(
		$item(1, 0, 'Home', 10, 'home'),
		$item(2, 0, 'Shop'),
		$item(3, 2, 'Shoes', 30, 'shoes'),
		$item(4, 3, 'Boots', 40, 'boots'),
		$item(5, 2, 'Bags', 50, 'bags'),
		$item(6, 0, 'Members', 60, 'members', '', 'Same Window', 1, 9),
		$item(7, 6, 'Directory', 70, 'directory'),
		$item(8, 0, 'Help', 0, '', 'https://example.com/help?a=1&b=2', 'New Window'),
	);
}

function _mw_base()
{
	return defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
}

function test_menu_widget_rows()
{
	$rows = pg_sw_list_menu_rows(_mw_items(), 40);
	pg_assert_same(array(0, 3, 2, 6), array_keys($rows), 'levels by the item they sit under');
	pg_assert_same(array('Home', 'Shop', 'Members', 'Help'), array_column($rows[0], '__menu_item_name'), 'top level in stored order');
	$sources = pg_sw_list_sources();
	$tokens = array_map(function ($pair) { return $pair[0]; }, $sources['menu.children']['tokens']);
	pg_assert_same($tokens, array_keys($rows[0][0]), 'a row carries exactly the descriptor\'s tokens, the name first');

	$shop = $rows[0][1];
	pg_assert_same('#', $shop['__menu_item_url'], 'an item that links nowhere');
	pg_assert_same('1', $shop['__menu_item_has_children'], 'has sub-items');
	pg_assert_same('1', $shop['__menu_item_holds_current'], 'holds the item of the page shown');
	pg_assert_same('1', $rows[2][0]['__menu_item_holds_current'], 'so does every item above it');
	pg_assert_same('1', $rows[3][0]['__menu_item_is_current'], 'the item of the page shown');
	pg_assert_same('', $rows[3][0]['__menu_item_has_children'], 'a leaf');
	pg_assert_same(_mw_base() . 'home', $rows[0][0]['__menu_item_url'], 'a page link');

	$help = $rows[0][3];
	pg_assert_same('https://example.com/help?a=1&b=2', $help['__menu_item_url'], 'a URL, raw: the pass escapes it');
	pg_assert_same('_blank', $help['__menu_item_target'], 'a new window');
	pg_assert_same('', $shop['__menu_item_target'], 'never for an item that links nowhere');
}

function test_menu_widget_rows_access_and_names()
{
	$rows = pg_sw_list_menu_rows(_mw_items(), 0, function ($folder) { return (int)$folder !== 9; },
		function ($name) { return strtoupper($name); });
	pg_assert_same(array('HOME', 'SHOP', 'HELP'), array_column($rows[0], '__menu_item_name'), 'a protected page the visitor may not view is left out, names translated');
	pg_assert_false(isset($rows[6]), 'with everything under it');
	pg_assert_same('', implode('', array_column($rows[0], '__menu_item_is_current')), 'no page: no current item');

	$loop = _mw_items();
	$loop[0]['parent_id'] = 4;
	$loop[] = array('id' => 9, 'parent_id' => 9, 'name' => 'Self', 'link_page_id' => 0, 'link_page_name' => '', 'link_url' => '', 'link_target' => '', 'security' => 0);
	$rows = pg_sw_list_menu_rows($loop, 10);
	pg_assert_same(array('Home'), array_column($rows[4], '__menu_item_name'), 'stored parents that loop end the walk');
	pg_assert_same(array(), pg_sw_list_menu_rows(null, 1), 'no menu');
}

function test_menu_widget_target_stamp()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'a', '_bindings' => array('href' => '__menu_item_url')), 'children' => array()),
		array('type' => 'semantic', 'props' => array('tag' => 'a', 'target' => '_self', '_bindings' => array('href' => '__menu_item_url')), 'children' => array()),
		array('type' => 'semantic', 'props' => array('tag' => 'a', '_bindings' => array('href' => '__cart_url')), 'children' => array()),
	));
	pg_mw_stamp_targets($tree);
	pg_assert_same('^^__menu_item_target^^', $tree['children'][0]['props']['target'], 'an item\'s link takes its window');
	pg_assert_same('_self', $tree['children'][1]['props']['target'], 'a window the designer set stays');
	pg_assert_false(isset($tree['children'][2]['props']['target']), 'other links are left');
}

// What the renderer does: the main loop over the top level, a list of the
// sub-items in each row and one more inside it, then the finish pass.
function test_menu_widget_lists_drawn()
{
	$link = function ($class) {
		return array('type' => 'semantic', 'props' => array('tag' => 'a', 'cssClass' => $class, 'text' => 'x', 'href' => '#',
			'_bindings' => array('href' => '__menu_item_url', 'text' => '__menu_item_name')), 'children' => array());
	};
	$list = function ($children) {
		return array('type' => 'loop_area', 'props' => array('source' => 'menu.children'), 'children' => $children);
	};
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'ul', 'cssClass' => 'top'), 'children' => array(
			array('type' => 'loop_area', 'props' => array(), 'children' => array(
				array('type' => 'semantic', 'props' => array('tag' => 'li', 'cssClass' => 'item'), 'children' => array(
					$link('l1'),
					$list(array($link('l2'), $list(array($link('l3'))))),
				)),
			)),
		)),
	));
	pg_mw_stamp_targets($tree);
	$by_parent = pg_sw_list_menu_rows(_mw_items(), 0);

	$scope = pg_sw_finish_open(array('sid' => 91, 'kind' => 'menu_region', 'email' => false));
	try {
		$split = _split_widget_tree($tree);
		$tpl = '';
		foreach ($split['loop_children'] as $child) $tpl .= _render_tree_node($child, 0, 0);
		pg_assert_same(array('menu.children' => true), pg_sw_list_wanted('menu'), 'the widget drew a menu list');
		pg_sw_list_publish('menu', array('menu.children' => $by_parent));
		$rows = '';
		foreach ($by_parent[0] as $row) {
			$rows .= pg_sw_sweep_tokens(_pg_sw_fill_tokens(trim($tpl), pg_sw_escape_token_values($row)));
		}
		$html = str_replace('<!--pg-loop-slot-->', $rows, trim(_render_tree_node($split['static_tree'], 0, 0)));
		$html = pg_sw_finish_widget_html($html, $scope);
	} finally {
		pg_sw_finish_close();
	}

	preg_match_all('#class="(l[123])"[^>]*>([^<]*)<#', $html, $m);
	pg_assert_same(array('l1', 'l1', 'l2', 'l3', 'l2', 'l1', 'l2', 'l1'), $m[1], 'the levels nest');
	pg_assert_same(array('Home', 'Shop', 'Shoes', 'Boots', 'Bags', 'Members', 'Directory', 'Help'), $m[2], 'each row lists its own sub-items');
	pg_assert_contains('href="https://example.com/help?a=1&amp;b=2" target="_blank"', $html, 'escaped once, with its window');
	pg_assert_false(strpos($html, '<!--pg-') !== false, 'no marker left');
	pg_assert_false(strpos($html, '^^') !== false, 'no token left');
}

function test_menu_widget_registered()
{
	$labels = pg_sw_type_labels();
	pg_assert_true(isset($labels['menu_region']), 'panel label');
	pg_assert_same(array('menu.children'), array_keys(pg_sw_kind_lists('menu_region')), 'offers the sub-item list');
	pg_assert_false(isset(pg_sw_kind_lists('catalog_listing')['menu.children']), 'no other kind does');
	$designer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_contains("_render_system_widget_menu_region(", $designer, 'dispatched by _expand_system_widgets()');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	pg_assert_contains("{ type: 'menu_region'", $js, 'in SW_TYPES');
	pg_assert_contains("case 'menu_region': {", $js, 'has a starter tree');
	pg_assert_contains("menu_region:       SW_MENU_REGION_TOKEN_GROUPS", $js, 'has a token palette');
	pg_assert_contains("comments_recent: 1, menu_region: 1,", $js, 'draws no Messages block');
}
