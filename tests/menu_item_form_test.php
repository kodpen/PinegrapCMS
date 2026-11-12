<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the menu item form shared by the create and the edit screens
 * (includes/forms/menu_item_form.php): what a posted item links to, which
 * addresses the URL box refuses, and that both screens and the external API
 * use the shared form and rule.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/menu_item_form.php');

function test_menu_item_form_link_page_wins()
{
	pg_assert_same(array('link_page_id' => '12', 'link_url' => ''), pg_menu_item_form_link(array('link_page_id' => '12', 'link_url' => 'https://example.com/')), 'a page blanks the URL');
	pg_assert_same(array('link_page_id' => '', 'link_url' => 'https://example.com/'), pg_menu_item_form_link(array('link_page_id' => '', 'link_url' => 'https://example.com/')), 'no page keeps the URL');
	pg_assert_same(array('link_page_id' => '', 'link_url' => '/x'), pg_menu_item_form_link(array('link_page_id' => '0', 'link_url' => '/x')), 'page 0 is no page');
	pg_assert_same(array('link_page_id' => '', 'link_url' => ''), pg_menu_item_form_link(array()), 'nothing posted');
	pg_assert_same(array('link_page_id' => '', 'link_url' => '/x'), pg_menu_item_form_link(array('link_url' => "  /x\n")), 'the URL is trimmed');
}

function test_menu_item_form_url_problem_refuses()
{
	$space = lang('The address cannot contain spaces or control characters.');
	$scheme = lang('The address must be a web, e-mail or phone address, or a path on this site.');

	pg_assert_same($scheme, pg_menu_item_url_problem('javascript:alert(1)'), 'javascript: is refused');
	pg_assert_same($scheme, pg_menu_item_url_problem('JavaScript:alert(1)'), 'the scheme is read in any case');
	pg_assert_same($space, pg_menu_item_url_problem(" java\tscript:alert(1)"), 'a tab inside the scheme is refused');
	pg_assert_same($space, pg_menu_item_url_problem("/a\x00b"), 'a control character is refused');
	pg_assert_same($space, pg_menu_item_url_problem('/a b'), 'a space is refused');
	pg_assert_same($scheme, pg_menu_item_url_problem('data:text/html,<script>alert(1)</script>'), 'data: is refused');
	pg_assert_same($scheme, pg_menu_item_url_problem('vbscript:x'), 'another scheme is refused');
	pg_assert_same($scheme, pg_menu_item_url_problem('//evil.example/'), 'a protocol-relative address is refused');
	pg_assert_same($scheme, pg_menu_item_url_problem('/\\evil.example/'), 'a backslash is refused');
}

function test_menu_item_form_url_problem_keeps()
{
	foreach (array('', '   ', 'https://example.com/a?b=1#c', 'http://example.com', 'mailto:info@example.com', 'tel:+905551112233', '/path/page', 'page', '#anchor', '?q=1', ' /trimmed ') as $url) {
		pg_assert_same('', pg_menu_item_url_problem($url), var_export($url, true) . ' is kept');
	}
}

function test_menu_item_form_api_uses_shared_rule()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/menus.php');

	pg_assert_contains('includes/forms/menu_item_form.php', $source, 'the API loads the shared rule');
	pg_assert_contains('pg_menu_item_url_problem(', $source, 'the API checks the URL the shared way');
}

function test_menu_item_form_screens_use_shared_form()
{
	foreach (array('add_menu_item.php', 'edit_menu_item.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/menu_item_form.php', $source, $file . ' loads the form');
		pg_assert_contains('pg_menu_item_form_cards($liveform', $source, $file . ' prints the shared cards');
		pg_assert_contains('pg_menu_item_form_layout(', $source, $file . ' uses the shared layout');
		pg_assert_contains("'menu_url' => h(\$items_url)", $source, $file . ' links the menu in the Summary');
		pg_assert_false(strpos($source, "lang('Menu') . ': '") !== false, $file . ' moved the menu line into the Summary');
		pg_assert_contains('pg_menu_item_form_link(', $source, $file . ' takes the link the shared way');
		pg_assert_contains($file === 'add_menu_item.php' ? 'pg_menu_item_form_last_sort_order(' : 'pg_menu_item_form_level_order(', $source, $file . ' puts a moved or new item last');
		pg_assert_contains('pg_menu_item_form_touch_menu(', $source, $file . ' stamps the menu');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_contains('pg_menu_item_url_problem(', $source, $file . ' checks the URL');
	}
}

function test_menu_item_form_level_order_position()
{
	pg_assert_same(array(5, 1, 2), pg_menu_item_form_level_order(array(1, 2), 5, 'top', false), 'top goes first');
	pg_assert_same(array(1, 5, 2), pg_menu_item_form_level_order(array(1, 2), 5, '1', false), 'below a sibling');
	pg_assert_same(array(1, 2, 5), pg_menu_item_form_level_order(array(1, 2), 5, '2', false), 'below the last sibling');
	pg_assert_same(array(5), pg_menu_item_form_level_order(array(), 5, 'top', false), 'alone on its level');
}

function test_menu_item_form_level_order_new_parent_goes_last()
{
	pg_assert_same(array(1, 2, 5), pg_menu_item_form_level_order(array(1, 2), 5, 'top', true), 'top of the old level is ignored');
	pg_assert_same(array(1, 2, 5), pg_menu_item_form_level_order(array(1, 2), 5, '1', true), 'a sibling of the old level is ignored');
	pg_assert_same(array(1, 2, 5), pg_menu_item_form_level_order(array(1, 2), 5, '9', false), 'an unknown position goes last');
	pg_assert_same(array(1, 2, 5), pg_menu_item_form_level_order(array(1, 2), 5, '', false), 'no position goes last');
}

function test_menu_item_form_wide_layout_columns_and_save_bar()
{
	$layout = pg_menu_item_form_layout('<div id="cards"></div>', '<button id="b"></button>');
	pg_assert_contains('<div class="row"><div id="cards"></div>', $layout, 'the cards open the row');
	pg_assert_contains('col-12 order-3 position-sticky', $layout, 'the save bar is sticky under both columns');
	pg_assert_contains('<button id="b"></button>', $layout, 'the buttons go in the bar');

	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/menu_item_form.php');
	$form_column = strpos($src, 'col-12 lg:col-8 2xl:col-9 order-1');
	$status_column = strpos($src, 'col-12 lg:col-4 2xl:col-3 order-2');
	pg_assert_true($form_column !== false, 'the form column');
	pg_assert_true(($status_column !== false) && ($status_column > $form_column), 'the status column follows the form column in the source');
	pg_assert_contains('style="top:3.3rem;"', $src, 'the status box sticks below the top bar');
	pg_assert_contains('id="menu_item_summary_link"', $src, 'the Summary shows what the item links to');

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_menu_item.php');
	pg_assert_contains("'status' => pg_menu_item_form_children_status(\$child_count)", $edit, 'the edit screen counts the items under it');
	pg_assert_contains("pg_picker_field('page', 'link_page_id', \$liveform->get_field_value('link_page_id'))", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/menu_item_form.php'), 'the stored page stays in the page field');
}

function test_menu_item_form_children_status()
{
	$row = pg_menu_item_form_children_status(4);
	pg_assert_contains('id="menu_item_summary_children">4</dd>', $row, 'the count');
}

function test_menu_item_form_link_target_is_one_of_two()
{
	pg_assert_same('New Window', pg_menu_item_form_link_target('New Window'), 'new window kept');
	pg_assert_same('Same Window', pg_menu_item_form_link_target('Same Window'), 'same window kept');
	pg_assert_same('Same Window', pg_menu_item_form_link_target('Bogus'), 'an unknown value');
	pg_assert_same('Same Window', pg_menu_item_form_link_target(array('New Window')), 'an array');
}

/**
 * Menu 20: 644 > 645 > 646, 647 at the top; menu 21: 700.
 */
function menu_item_parent_test_lookup()
{
	$rows = array(
		644 => array('menu_id' => 20, 'parent_id' => 0),
		645 => array('menu_id' => 20, 'parent_id' => 644),
		646 => array('menu_id' => 20, 'parent_id' => 645),
		647 => array('menu_id' => 20, 'parent_id' => 0),
		700 => array('menu_id' => 21, 'parent_id' => 0),
	);

	return function ($id) use ($rows) {
		return $rows[$id] ?? null;
	};
}

function test_menu_item_parent_allowed()
{
	$lookup = menu_item_parent_test_lookup();

	pg_assert_true(pg_menu_item_parent_allowed(20, 644, '0', $lookup), 'top level');
	pg_assert_true(pg_menu_item_parent_allowed(20, 644, '', $lookup), 'empty is top level');
	pg_assert_true(pg_menu_item_parent_allowed(20, 645, '647', $lookup), 'another branch of the menu');
	pg_assert_true(pg_menu_item_parent_allowed(20, 646, '644', $lookup), 'an ancestor');
	pg_assert_true(pg_menu_item_parent_allowed(20, 0, '646', $lookup), 'create below any item of the menu');
	pg_assert_false(pg_menu_item_parent_allowed(20, 644, '644', $lookup), 'the item itself');
	pg_assert_false(pg_menu_item_parent_allowed(20, 644, '646', $lookup), 'a grandchild is a cycle');
	pg_assert_false(pg_menu_item_parent_allowed(20, 644, '645', $lookup), 'a child is a cycle');
	pg_assert_false(pg_menu_item_parent_allowed(20, 644, '700', $lookup), 'an item of another menu');
	pg_assert_false(pg_menu_item_parent_allowed(20, 0, '700', $lookup), 'create below another menu');
	pg_assert_false(pg_menu_item_parent_allowed(20, 644, '999', $lookup), 'a missing item');
	pg_assert_false(pg_menu_item_parent_allowed(20, 644, '-3', $lookup), 'a negative id');
	pg_assert_false(pg_menu_item_parent_allowed(20, 644, array('0'), $lookup), 'an array');
}

function test_menu_item_parent_allowed_stops_on_a_stored_loop()
{
	$lookup = function ($id) {
		return array('menu_id' => 5, 'parent_id' => ($id === 1 ? 2 : 1));
	};

	pg_assert_false(pg_menu_item_parent_allowed(5, 9, '1', $lookup), 'a loop that never reaches the top is refused');
}

// The order the menu screen's drag and drop saves: every group's parent
// must be an item of the menu as it is now, an item may be placed once, and
// the moves may not close a cycle. Items no longer in the menu are skipped.
function test_menu_item_sort_plan()
{
	// 1 and 2 at the top, 3 under 1, 4 under 3.
	$parent_of = array(1 => 0, 2 => 0, 3 => 1, 4 => 3);

	pg_assert_same(
		array(array(2, 0, 1), array(1, 0, 2), array(3, 2, 1), array(4, 3, 1)),
		pg_menu_item_sort_plan(array(
			array('parent_id' => 0, 'order' => array(2, 1)),
			array('parent_id' => '2', 'order' => array('3')),
			array('parent_id' => 3, 'order' => array(4)),
		), $parent_of),
		'a valid tree: id, parent, place among its siblings');

	pg_assert_same(
		array(array(2, 0, 1)),
		pg_menu_item_sort_plan(array(array('parent_id' => 0, 'order' => array(9, 2))), $parent_of),
		'an item deleted since the screen was opened is skipped and does not take a place');

	pg_assert_same(null,
		pg_menu_item_sort_plan(array(array('parent_id' => 9, 'order' => array(2))), $parent_of),
		'a parent deleted since the screen was opened is refused: the item would vanish from the menu');

	pg_assert_same(null,
		pg_menu_item_sort_plan(array(array('parent_id' => 4, 'order' => array(1))), $parent_of),
		'moving an item under its own sub-item is refused');

	pg_assert_same(null,
		pg_menu_item_sort_plan(array(array('parent_id' => 2, 'order' => array(2))), $parent_of),
		'an item under itself is refused');

	pg_assert_same(null,
		pg_menu_item_sort_plan(array(
			array('parent_id' => 0, 'order' => array(1)),
			array('parent_id' => 2, 'order' => array(1)),
		), $parent_of),
		'an item placed twice is refused');

	pg_assert_same(null,
		pg_menu_item_sort_plan(array(array('parent_id' => 'x', 'order' => array(1))), $parent_of),
		'a parent that is not a number is refused');

	pg_assert_same(array(),
		pg_menu_item_sort_plan(array(), $parent_of),
		'nothing to save');

	// A stored orphan (its parent is gone) left out of the save does not
	// make the save fail.
	pg_assert_same(
		array(array(1, 0, 1)),
		pg_menu_item_sort_plan(array(array('parent_id' => 0, 'order' => array(1))), array(1 => 0, 5 => 77)),
		'an orphan the save does not touch is no cycle');
}

// The sort action reads the menu's items and refuses a plan the rule turns
// down, before any row is written.
function test_menu_item_sort_action_uses_plan()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/content.php');
	$start = strpos($source, 'function pg_panel_sort_menu_items');
	pg_assert_true($start !== false, 'the sort action exists');
	$body = substr($source, $start, 4000);
	pg_assert_true(strpos($body, 'pg_menu_item_sort_plan(') !== false, 'the sort action checks the plan');
	pg_assert_true(strpos($body, 'pg_menu_item_sort_plan(') < strpos($body, 'UPDATE menu_items'), 'before writing');
}
