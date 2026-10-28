<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a collection's fields in form lists (includes/fn/collections.php,
 * includes/fn/widgets.php): a number field sorted as a number
 * (pg_coll_number_order_sql()), a reference text box compared by membership
 * (pg_coll_reference_holds_sql()), and an address value that named a record
 * printing that record's label (pg_sw_url_filter_value()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _collections_lists_db_stub()
{
	if (!class_exists('db')) {
		class db
		{
			public static $con;
		}
	}
}

function test_collections_number_order_sql()
{
	pg_assert_same(
		"(sort_data.data IS NULL OR sort_data.data = '') ASC, CAST(sort_data.data AS DECIMAL(65,15)) ASC",
		pg_coll_number_order_sql('sort_data', 'asc')
	);
	pg_assert_contains('AS DECIMAL(65,15)) DESC', pg_coll_number_order_sql('sort_data2', 'DESC'));
	pg_assert_contains('AS DECIMAL(65,15)) DESC', pg_coll_number_order_sql('sort_data', 'DROP'), 'anything else is DESC');
	pg_assert_same(0, strpos(pg_coll_number_order_sql('x;y', 'ASC'), '(xy.data'), 'the alias keeps word characters only');
}

function test_collections_reference_holds_sql()
{
	_collections_lists_db_stub();
	pg_assert_same(
		"(FIND_IN_SET('abc123', REPLACE(IFNULL(d.data, ''), ', ', ',')) > 0)",
		pg_coll_reference_holds_sql('d.data', 'abc123')
	);
	pg_assert_contains("FIND_IN_SET('a\\'b'", pg_coll_reference_holds_sql('d.data', "a'b"), 'the code is escaped');
}

function test_collections_filter_value_prints_reference_label()
{
	$sql = array('params' => array('category' => 'news', 'author' => 'Jane'), 'inexact' => false, 'labels' => array('category' => 'News & Politics'));
	pg_assert_same('News & Politics, Jane', pg_sw_url_filter_value($sql, 3), 'a named record prints its label, the rest their value');
	pg_assert_same('', pg_sw_url_filter_value($sql, 0), 'nothing found: nothing printed');
	$sql['inexact'] = true;
	pg_assert_same('', pg_sw_url_filter_value($sql, 3), 'inexact: nothing printed');
	pg_assert_same('news', pg_sw_url_filter_value(array('params' => array('category' => 'news'), 'inexact' => false), 1), 'without labels the value prints');
}

function test_collections_list_wiring()
{
	$widgets = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	pg_assert_contains('pg_coll_reference_lookup((int)$custom[$key][\'reference_page_id\'], $value)', $widgets, 'a reference filter looks the value up');
	pg_assert_contains("\$out['labels'][\$url_param] = \$reference['label']", $widgets, 'an address value keeps the label');
	pg_assert_contains('pg_coll_number_order_sql($alias, $direction)', $widgets, 'a number field sorts as a number');
	pg_assert_contains("'exact' => '', 'labels' => array()", $widgets, 'labels start empty');
}
