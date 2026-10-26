<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Menu Sequence system widget (includes/fn/widgets_menu.php):
 * the pages of a menu in reading order, the links before and after the
 * page shown, and the places the kind is registered in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Home, Shop (no page; Shoes (Boots), Bags), Again (Home's page again).
function _msw_items()
{
	$item = function ($id, $parent, $name, $page_id = 0, $page = '') {
		return array('id' => $id, 'parent_id' => $parent, 'name' => $name, 'link_page_id' => $page_id,
			'link_page_name' => $page, 'link_page_folder_id' => 1, 'link_url' => '', 'link_target' => '', 'security' => 0);
	};
	return array(
		$item(1, 0, 'Home', 10, 'home'),
		$item(2, 0, 'Shop'),
		$item(3, 2, 'Shoes', 30, 'shoes'),
		$item(4, 3, 'Boots', 40, 'boots'),
		$item(5, 2, 'Bags', 50, 'bags'),
		$item(6, 0, 'Again', 10, 'home'),
	);
}

function test_menu_sequence_widget_order()
{
	$seq = pg_mw_sequence(_msw_items());
	pg_assert_same(array('home', 'shoes', 'boots', 'bags'), array_column($seq, 'page'), 'each item before its sub-items, no page left out, a page once');
	pg_assert_same(array(), pg_mw_sequence(null), 'no menu');
	$loop = _msw_items();
	$loop[0]['parent_id'] = 4;
	pg_assert_same(array('shoes', 'boots', 'home', 'bags'), array_column(pg_mw_sequence($loop), 'page'), 'parents that loop end the walk');
}

function test_menu_sequence_widget_values()
{
	$base = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
	$seq = pg_mw_sequence(_msw_items());

	$v = pg_mw_sequence_values($seq, 40);
	pg_assert_true($v['flags']['in_sequence'] && $v['flags']['has_previous'] && !$v['flags']['next_wraps'], 'a page in the middle');
	pg_assert_same('Shoes', $v['tokens']['__previous_name'], 'the page before');
	pg_assert_same($base . 'bags', $v['tokens']['__next_url'], 'the page after');
	pg_assert_same(array('3', '4'), array($v['tokens']['__sequence_position'], $v['tokens']['__sequence_count']), 'its place');

	$v = pg_mw_sequence_values($seq, 10, 'strtoupper');
	pg_assert_false($v['flags']['has_previous'], 'the first page has none before');
	pg_assert_same('SHOES', $v['tokens']['__next_name'], 'names translated');

	$v = pg_mw_sequence_values($seq, 50);
	pg_assert_true($v['flags']['next_wraps'], 'the last page');
	pg_assert_same($base . 'home', $v['tokens']['__next_url'], 'its next link goes back to the first');

	$v = pg_mw_sequence_values($seq, 99);
	pg_assert_true($v['flags']['not_in_sequence'] && !$v['flags']['in_sequence'], 'a page the menu does not link to');
	pg_assert_same('', $v['tokens']['__next_url'] . $v['tokens']['__previous_url'], 'gets no links');
}

function test_menu_sequence_widget_registered()
{
	$labels = pg_sw_type_labels();
	pg_assert_true(isset($labels['menu_sequence_region']), 'panel label');
	$designer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_contains("_render_system_widget_menu_sequence_region(", $designer, 'dispatched by _expand_system_widgets()');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	pg_assert_contains("{ type: 'menu_sequence_region'", $js, 'in SW_TYPES');
	pg_assert_contains("case 'menu_sequence_region': {", $js, 'has a starter tree');
	pg_assert_contains("menu_sequence_region: SW_MENU_SEQUENCE_TOKEN_GROUPS", $js, 'has a token palette');
	pg_assert_contains("menu_region: 1, menu_sequence_region: 1 };", $js, 'draws no Messages block');
}
