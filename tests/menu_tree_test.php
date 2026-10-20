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
