<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the menu tree the classic menu renderers draw from:
 * pg_menu_tree_from_rows() (includes/fn/content.php), and the renderers
 * themselves, get_menu_content() and get_menu_sequence(), which read a menu
 * once per request through pg_menu_tree() instead of a query for every level
 * and every item.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Rows keep the order they came in under each parent; the counts are handed
// through as they are, so an item counted with children another menu holds
// stays a parent.
function test_menu_tree_from_rows()
{
	$rows = array(
		array('id' => '5', 'parent_id' => '0', 'name' => 'First'),
		array('id' => '7', 'parent_id' => '5', 'name' => 'Under first'),
		array('id' => '6', 'parent_id' => '0', 'name' => 'Second'),
		array('id' => '9', 'parent_id' => '5', 'name' => 'Also under first'),
		array('id' => '8', 'parent_id' => '99', 'name' => 'Orphan'),
	);

	$tree = pg_menu_tree_from_rows($rows, array(5 => 2, 6 => 1));

	pg_assert_same(array('First', 'Second'), array_column($tree['children'][0], 'name'), 'top level in the order given');
	pg_assert_same(array('Under first', 'Also under first'), array_column($tree['children'][5], 'name'), 'children in the order given');
	pg_assert_same('Orphan', $tree['children'][99][0]['name'], 'an item whose parent is gone is kept under that id, never reached from the top');
	pg_assert_false(isset($tree['children'][6]), 'no children of its own in this menu');
	pg_assert_same(1, $tree['counts'][6], 'yet counted as a parent, as the per-item count did');
	pg_assert_same('5', $tree['children'][0][0]['id'], 'rows are kept as they came');

	pg_assert_same(array('children' => array(), 'counts' => array()), pg_menu_tree_from_rows(array(), array()), 'an empty menu');
}

// The source of one function, up to the next top-level function.
function menu_tree_test_function_source($file, $name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$from = strpos($source, 'function ' . $name . '(');

	if ($from === false) {
		return '';
	}

	$next = preg_match('/\nfunction [a-z_]+\(/', $source, $match, PREG_OFFSET_CAPTURE, $from + 10);

	return $next ? substr($source, $from, $match[0][1] - $from) : substr($source, $from);
}

// Neither renderer asks the database while it walks the menu: both read the
// tree, which reads the menu in two queries (its items, in printing order,
// and the children counted across every menu).
function test_menu_tree_renderers_read_the_tree()
{
	foreach (array('includes/fn/content.php' => 'get_menu_content', 'includes/fn/designer.php' => 'get_menu_sequence') as $file => $function) {
		$section = menu_tree_test_function_source($file, $function);

		pg_assert_true($section !== '', $function . ' is there');
		pg_assert_contains('pg_menu_tree($menu_id)', $section, $function . ' reads the tree');
		pg_assert_false(stripos($section, 'FROM menu_items') !== false, $function . ' queries no menu items itself');
		pg_assert_false(strpos($section, 'mysqli_query') !== false, $function . ' runs no query of its own');
	}

	$tree = menu_tree_test_function_source('includes/fn/content.php', 'pg_menu_tree');

	pg_assert_contains('ORDER BY menu_items.sort_order, menu_items.id', $tree, 'printing order, ties by id');
	pg_assert_same(1, preg_match('/SELECT parent_id, COUNT\(\*\) AS children\s+FROM menu_items\s+WHERE parent_id IN \(/', $tree), 'children counted by parent only, in any menu');
	pg_assert_same(2, substr_count($tree, 'db_items('), 'two queries for a menu');
}

// A page name may hold ?, # or %, so the classic menu links to it as an
// encoded path, as every other page link does; the parent picker lists items
// in the order the site prints them.
function test_menu_page_link_is_encoded()
{
	$section = menu_tree_test_function_source('includes/fn/content.php', 'get_menu_content');

	pg_assert_contains("OUTPUT_PATH . h(encode_url_path(\$menu_item['link_page_name']))", $section, 'the link is the encoded page name');
	pg_assert_contains("PATH . encode_url_path(\$menu_item['link_page_name'])", $section, 'the prefetch note is the same address');
	pg_assert_same('/faq%3F', '/' . encode_url_path('faq?'), 'a question mark stays part of the name');

	$options = menu_tree_test_function_source('includes/fn/content.php', 'get_menu_item_options');

	pg_assert_contains('ORDER BY sort_order, id', $options, 'parent picker in printing order');
}

// The menu items screen lists a menu depth first from the top level, each row
// with its depth (1 = top), from the one query that reads the whole menu.
function test_menu_tree_flatten()
{
	$rows = array(
		array('id' => '5', 'parent_id' => '0', 'name' => 'First'),
		array('id' => '7', 'parent_id' => '5', 'name' => 'Under first'),
		array('id' => '10', 'parent_id' => '7', 'name' => 'Deep'),
		array('id' => '6', 'parent_id' => '0', 'name' => 'Second'),
		array('id' => '9', 'parent_id' => '5', 'name' => 'Also under first'),
		array('id' => '8', 'parent_id' => '99', 'name' => 'Orphan'),
	);

	$flat = pg_menu_tree_flatten($rows);

	pg_assert_same(array('First', 'Under first', 'Deep', 'Also under first', 'Second'), array_column($flat, 'name'), 'depth first, siblings in the order given');
	pg_assert_same(array(1, 2, 3, 2, 1), array_column($flat, 'level'), 'level counts from 1 at the top');
	pg_assert_same('Under first', $flat[1]['name'], 'rows keep their own fields');
	pg_assert_false(in_array('Orphan', array_column($flat, 'name'), true), 'an item whose parent is not in the menu is left out');

	pg_assert_same(array(), pg_menu_tree_flatten(array()), 'an empty menu');
}

// Siblings with the same sort_order come in by id from the query
// (ORDER BY sort_order, id) and keep that order.
function test_menu_tree_flatten_keeps_tie_order()
{
	$rows = array(
		array('id' => '3', 'parent_id' => '0', 'name' => 'A'),
		array('id' => '4', 'parent_id' => '0', 'name' => 'B'),
		array('id' => '11', 'parent_id' => '0', 'name' => 'C'),
	);

	pg_assert_same(array('3', '4', '11'), array_column(pg_menu_tree_flatten($rows), 'id'), 'ties in the order given');
}

// A parent loop is never reached from the top; an item listed twice (a loop
// back to an item already shown) is shown once.
function test_menu_tree_flatten_stops_on_a_loop()
{
	$rows = array(
		array('id' => '1', 'parent_id' => '0', 'name' => 'Top'),
		array('id' => '2', 'parent_id' => '3', 'name' => 'Loop A'),
		array('id' => '3', 'parent_id' => '2', 'name' => 'Loop B'),
		array('id' => '1', 'parent_id' => '1', 'name' => 'Top again'),
	);

	$flat = pg_menu_tree_flatten($rows);

	pg_assert_same(array('Top'), array_column($flat, 'name'), 'loops are left out and an item is shown once');
}

// The screen reads the menu once instead of a query per level and per item.
function test_menu_items_screen_reads_the_menu_once()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_menu_items.php');

	pg_assert_true(strpos($source, 'pg_menu_tree_flatten(') !== false, 'the screen flattens one read');
	pg_assert_false(strpos($source, 'SELECT COUNT(*) FROM menu_items WHERE parent_id') !== false, 'no child count per item');
}

// On a phone the list fits the screen: the date columns step aside below md,
// the link breaks inside its cell, and what is left scrolls inside the card
// instead of the whole page (#8994).
function test_menu_items_screen_fits_a_phone()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_menu_items.php');

	pg_assert_contains('<div class="card-body p-0 position-relative table-responsive">', $source, 'the table scrolls inside its card');
	pg_assert_contains('<th scope="col" class="d-none md:d-table-cell">\' . lang(\'Created\') . \'</th>', $source, 'Created header hidden below md');
	pg_assert_contains('<th scope="col" class="d-none md:d-table-cell">\' . lang(\'Last Modified\') . \'</th>', $source, 'Last Modified header hidden below md');
	pg_assert_same(4, substr_count($source, 'd-none md:d-table-cell'), 'both date headers and both date cells');
	pg_assert_contains('<td class="align-middle text-break pg-cell-wrap">\' . h($link_to_value) . \'</td>', $source, 'a long address breaks inside its cell');
}
