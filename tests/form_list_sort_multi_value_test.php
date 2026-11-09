<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the form_list widget's sort by a form field: a check box or a
 * pick list with multiple values sorts by its values joined in a subquery,
 * as the classic list view does, so a record with several values is listed
 * once instead of once per value; every other field keeps its join.
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

function test_form_list_sort_by_check_box_uses_a_subquery()
{
	$order = pg_sw_form_list_field_order('sort_data', 12, 'check box', 0, '', 'ASC');

	pg_assert_same('', $order['join'], 'no join that repeats the record per value');
	pg_assert_same(
		"(SELECT GROUP_CONCAT(sort_data.data ORDER BY sort_data.id ASC SEPARATOR ', ') FROM form_data sort_data WHERE (sort_data.form_id = forms.id) AND (sort_data.form_field_id = '12')) ASC",
		$order['sql'],
		'the values joined together, as the classic list view sorts them'
	);
}

function test_form_list_sort_by_multiple_pick_list_uses_a_subquery()
{
	$order = pg_sw_form_list_field_order('sort_data2', 7, 'pick list', '1', '', 'DESC');

	pg_assert_same('', $order['join'], 'a multiple pick list has no join either');
	pg_assert_contains('GROUP_CONCAT(sort_data2.data', $order['sql'], 'its values joined');
	pg_assert_contains(') DESC', $order['sql'], 'in the direction asked');
}

function test_form_list_sort_by_single_value_field_keeps_its_join()
{
	$order = pg_sw_form_list_field_order('sort_data', 5, 'pick list', 0, '', 'ASC');

	pg_assert_contains("LEFT JOIN form_data sort_data", $order['join'], 'a single pick list joins');
	pg_assert_contains("sort_data.form_field_id = '5'", $order['join'], 'its field');
	pg_assert_same('sort_data.data ASC', $order['sql'], 'and sorts by the joined value');

	$number = pg_sw_form_list_field_order('sort_data', 5, 'text box', 0, 'number', 'DESC');
	pg_assert_same(pg_coll_number_order_sql('sort_data', 'DESC'), $number['sql'], 'a collection number field sorts as a number');
}

function test_form_list_widget_reads_the_multiple_flag()
{
	$source = pg_test_function_source('_render_system_widget_form_list');

	pg_assert_contains('pg_sw_form_list_field_order(', $source, 'the sort goes through the shared rule');
	pg_assert_contains('SELECT id, type, multiple', $source, 'the field\'s multiple flag is read');
}
