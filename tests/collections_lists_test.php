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

// -- records that reference this one (C4b2) -----------------------------------------

// A filter valued from the record the detail page shows.
function _collections_lists_shown_row($operator = 'is equal to', $value = '')
{
	return array('field' => 'reference_code', 'operator' => $operator, 'value' => $value, 'dynamic_value' => 'shown record', 'dynamic_value_attribute' => '');
}

function test_collections_shown_record_code()
{
	pg_assert_same('AB12CD', pg_coll_shown_record_code(array('r' => ' AB12CD ')), 'the code ?r= names, trimmed');
	pg_assert_same('', pg_coll_shown_record_code(array()), 'no record shown');
	pg_assert_same('', pg_coll_shown_record_code(array('r' => array('x'))), 'an array is no code');
	pg_assert_same('', pg_coll_shown_record_code(array('r' => "a\x00b")), 'control characters are no code');
	pg_assert_same('', pg_coll_shown_record_code(null), 'no address');
	pg_assert_same(100, strlen(pg_coll_shown_record_code(array('r' => str_repeat('a', 150)))), 'at most 100 characters');
}

function test_collections_shown_record_filter()
{
	_collections_lists_db_stub();
	$sql = pg_sw_form_list_filter_sql(12, array(_collections_lists_shown_row()), array('r' => 'AB12CD', 'author' => 'x'));
	pg_assert_same(" AND (IFNULL(forms.reference_code, '') = 'AB12CD')", $sql['where'], 'compared with the shown code');
	pg_assert_same(array(), $sql['params'], 'not an address filter of the page');
	pg_assert_same('', $sql['exact']);
	pg_assert_false(pg_sw_filter_sql_closed($sql));

	$other = pg_sw_form_list_filter_sql(12, array(_collections_lists_shown_row('is not equal to')), array('r' => 'AB12CD'));
	pg_assert_same(" AND (IFNULL(forms.reference_code, '') != 'AB12CD')", $other['where'], 'every other record');

	$none = pg_sw_form_list_filter_sql(12, array(_collections_lists_shown_row()), array());
	pg_assert_same('', $none['where'], 'no record shown: left out, as an address value is');
	pg_assert_same(0, $none['address']);

	$record_view = pg_sw_form_list_filter_sql(12, array(_collections_lists_shown_row()), null);
	pg_assert_same(1, $record_view['address'], 'no address: counted');
	pg_assert_true(pg_sw_filter_sql_closed($record_view, true), 'a record view closes on it');

	$fixed = pg_sw_form_list_filter_sql(12, array(_collections_lists_shown_row('is equal to', 'R1')), array('r' => 'AB12CD'));
	pg_assert_same(" AND (IFNULL(forms.reference_code, '') = 'R1')", $fixed['where'], 'a fixed value wins');
}

function test_collections_shown_record_record_files()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/record_files.php');
	_collections_lists_db_stub();
	$fields = array('category' => array('id' => 5, 'name' => 'category', 'type' => 'pick list', 'multiple' => 0));
	$row = array('field' => 'category', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'shown record');
	pg_assert_same('', pg_record_file_filter_sql(array($row), $fields)['where'], 'a list leaves it out');
	pg_assert_true(pg_record_file_filter_sql(array($row), $fields, true)['closed'], 'a record view closes');
}

function test_collections_shown_record_wiring()
{
	$widgets = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	pg_assert_contains("'url parameter', 'shown record',", $widgets, 'a known dynamic value');
	pg_assert_contains('pg_coll_shown_record_code($request)', $widgets, 'read from the shown record');
	pg_assert_contains('$reference === null || $reference[\'code\'] !== $value', $widgets, 'a reference must name the shown record by its code');
}

// Every dynamic value the editor's filter rows offer is one the server
// reads, and "Shown record" is offered on a list only, with no attribute.
function test_collections_shown_record_editor()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$widgets = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	pg_assert_true((bool) preg_match('/function _swFilterDynamicOptions\(type, withUrl\) \{(.*?)\n    \}/s', $js, $m), 'the dynamic values are found in style_designer.js');
	pg_assert_true((bool) preg_match('/\$dynamic_values = array\((.*?)\);/s', $widgets, $s), 'the server list is found');
	preg_match_all("/o\.push\(\['([^']+)'/", $m[1], $offered);
	preg_match_all("/'([^']+)'/", $s[1], $known);
	pg_assert_same(array(), array_values(array_diff($offered[1], $known[1])), 'the server reads every offered value');
	pg_assert_contains("if (withUrl) o.push(['shown record', _sdT('Shown record')]);", $m[1], 'offered on a list only');
	pg_assert_contains("dyn === 'url parameter' || dyn === 'shown record'", $js, 'kept and marked on a record view');
}
