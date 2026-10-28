<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the categories of the main menu (includes/fn/output.php): the
 * table of categories (pg_menu_categories()), the layout of the rows a person
 * gets (pg_menu_layout()), the heading (pg_menu_heading()) and the parts of
 * output_menu() that feed them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');

// The layout as key => slots, the easiest shape to compare.
function pg_test_menu_layout($drawn)
{
	$shape = array();

	foreach (pg_menu_layout($drawn, pg_menu_categories()) as $block) {
		$shape[] = array($block['key'], $block['label'], $block['slots']);
	}

	return $shape;
}

function test_menu_category_with_no_row_has_no_heading()
{
	$shape = pg_test_menu_layout(array(0, 8, 20));

	pg_assert_same(array(array('', '', array(0)), array('system', 'System', array(8, 20))), $shape, 'only the rows built are laid out');
}

function test_menu_categories_keep_the_table_order_whatever_the_build_order()
{
	$shape = pg_test_menu_layout(array(20, 8, 15, 10, 1, 2, 23, 0));

	pg_assert_same(array(
		array('', '', array(0, 23)),
		array('content', 'Content', array(2, 1)),
		array('ecommerce', 'Ecommerce', array(10, 15)),
		array('system', 'System', array(8, 20)),
	), $shape);
}

function test_menu_categories_for_narrow_rights()
{
	pg_assert_same(array(array('', '', array(0)), array('ecommerce', 'Ecommerce', array(15))), pg_test_menu_layout(array(0, 15)), 'order reports only: one heading over the one row');
	pg_assert_same(array(array('', '', array(0)), array('accounting', 'Accounting', array(22))), pg_test_menu_layout(array(0, 22)), 'the ERP alone is not under the store');
	pg_assert_same(array(array('', '', array(0)), array('content', 'Content', array(2, 1))), pg_test_menu_layout(array(0, 1, 2)), 'folder rights: pages and the file manager');
	pg_assert_same(array(array('', '', array(0, 23, 24))), pg_test_menu_layout(array(0, 23, 24)), 'the workspace and a pinned My Notes: no heading');
	pg_assert_same(array(array('', '', array(0))), pg_test_menu_layout(array(0)), 'nothing but the dashboard');
	pg_assert_same(array(), pg_test_menu_layout(array()), 'no rows, no blocks');
}

function test_menu_manager_gets_no_design_heading()
{
	// Rows 17 and 21 are built for roles 0 and 1 only.
	foreach (pg_menu_layout(array(0, 23, 1, 2, 4, 5, 6, 7, 8, 9, 10, 11, 12, 14, 15, 20, 22), pg_menu_categories()) as $block) {
		pg_assert_false($block['key'] === 'design', 'no Design block');
	}
}

function test_menu_row_no_category_lists_is_drawn_at_the_end()
{
	$shape = pg_test_menu_layout(array(0, 99, 8));

	pg_assert_same(array(array('', '', array(0)), array('system', 'System', array(8)), array('', '', array(99))), $shape, 'an unlisted row is kept, untitled, last');
}

function test_menu_categories_list_every_row_once()
{
	// The slots output_menu() builds, read from its source.
	preg_match_all('/\$menu_items\[(\d+)\]\[\'id\'\] = /', pg_test_function_source('output_menu'), $matches);
	$built = array_map('intval', array_unique($matches[1]));
	sort($built);

	$listed = array();
	$labels = array();

	foreach (pg_menu_categories() as $key => $category) {
		foreach ($category['slots'] as $slot) {
			pg_assert_false(in_array($slot, $listed, true), 'slot ' . $slot . ' is listed once');
			$listed[] = $slot;
		}

		if ($key !== '') {
			$labels[] = $category['label'];
		}
	}

	sort($listed);

	pg_assert_same($built, $listed, 'every row built has a place, and nothing else does');

	foreach (array(3, 13, 18, 19) as $retired) {
		pg_assert_false(in_array($retired, $listed, true), 'retired slot ' . $retired);
	}

	// Each title is a lang() source the Turkish shards translate.
	foreach ($labels as $label) {
		pg_assert_true(pg_test_menu_label_translated($label), $label . ' is translated');
	}
}

function pg_test_menu_label_translated($label)
{
	foreach (glob(PG_FUNCTIONS_DIR . '/includes/local/tr/*.json') as $file) {
		$shard = json_decode((string) file_get_contents($file), true);

		if (is_array($shard) && isset($shard[$label]) && ($shard[$label] !== '')) {
			return true;
		}
	}

	return false;
}

function test_menu_heading_markup_is_escaped()
{
	$html = pg_menu_heading('content', 'Pages & <b>Files</b>');

	pg_assert_contains('id="menu_heading_content"', $html, 'its id');
	pg_assert_contains('class="pg-menu-heading"', $html, 'its class');
	pg_assert_contains('Pages &amp; &lt;b&gt;Files&lt;/b&gt;', $html, 'the label is escaped');
	pg_assert_false(strpos($html, '<a') !== false, 'not a link');
	pg_assert_false(strpos($html, 'list-group-item') !== false, 'not a row');
}

function test_menu_layout_reads_only_the_rows_built()
{
	$menu = pg_test_function_source('output_menu');

	pg_assert_contains("if (isset(\$options['id'])) {\n            \$menu_drawn[] = (int) \$key;", $menu, 'a row counts once it has its id');
	pg_assert_contains('pg_menu_layout($menu_drawn, pg_menu_categories())', $menu, 'the layout is fed the rows built');
	pg_assert_false(strpos($menu, '$reordered') !== false, 'no second ordering of the rows');
}

function test_menu_file_manager_row_holds_the_files()
{
	$menu = pg_test_function_source('output_menu');

	pg_assert_contains("\$menu_items[1]['href'] = 'view_folders.php';", $menu, 'the row opens the explorer');
	pg_assert_contains("\$menu_items[1]['data-bs-content'] .= '<a href=\\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_folders.php?view=files\\''", $menu, 'the Files view is its entry');
	pg_assert_contains("\$menu_items[1]['data-bs-content'] .= '<a href=\\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_folders.php?view=files&upload=1\\''", $menu, 'and the upload');
	pg_assert_false(strpos($menu, "\$menu_items[3]") !== false, 'the Files row is gone');
	pg_assert_false(strpos($menu, '? 3 : 1') !== false, 'every view of the explorer marks the File Manager row');
	pg_assert_false(strpos($menu, '$active_menu = 3;') !== false, 'nothing marks the retired row');
	pg_assert_false(strpos($menu, '$active_menu = 13;') !== false, 'nor the never-built 13');
}

function test_menu_store_rows_are_named_for_their_subject()
{
	$menu = pg_test_function_source('output_menu');

	foreach (array(10 => 'Orders', 11 => 'Products', 14 => 'Offers', 15 => 'Shipping') as $slot => $title) {
		pg_assert_contains("\$menu_items[" . $slot . "]['title'] = '" . $title . "';", $menu, 'row ' . $slot);
		pg_assert_contains("data-pg-menu-label=\\'' . h(lang('" . $title . "')) . '\\'", $menu, 'its first entry gives the row name as its hint label');
	}
}
