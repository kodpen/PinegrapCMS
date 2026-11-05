<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the All Ship Date Adjustments list quick edit
 * (includes/fn/ship_date_adjustment_list_edit.php): the zip code prefix,
 * the shipping method and the adjustment are fields, checked with the
 * adjustment screen's rules.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/ship_date_adjustment_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/ship_date_adjustment_list_edit.php');

function ship_date_adjustment_list_edit_test_methods()
{
	return array(
		array('id' => 4, 'name' => 'Ground', 'code' => 'GND'),
		array('id' => 7, 'name' => '<b>Express</b>', 'code' => ''),
	);
}

function ship_date_adjustment_list_edit_test_item(array $over = array())
{
	return $over + array(
		'id' => 12, 'zip_code_prefix' => '852', 'shipping_method_id' => 4, 'shipping_method_name' => 'Ground',
		'shipping_method_code' => 'GND', 'adjustment' => -3, 'created_username' => 'ayse', 'created_timestamp' => time(),
		'last_modified_username' => 'ayse', 'last_modified_timestamp' => time());
}

function test_ship_date_adjustment_quick_edit_every_column_but_the_times_is_a_field()
{
	$columns = pg_ship_date_adjustment_quick_edit_columns(ship_date_adjustment_list_edit_test_methods());
	$cells = pg_ship_date_adjustment_list_cells(ship_date_adjustment_list_edit_test_item(), $columns);
	$text = array();
	foreach (pg_ship_date_adjustment_list_columns(true) as $key => $column) {
		pg_assert_same(isset($column['editable']), isset($cells[$key]['attributes']['data-pg-edit']), $key . ': editable column and field agree');
		if (isset($column['editable'])) {
			// The driver keeps only the kinds it has an input for.
			pg_assert_true(pg_list_table_column($key, $column)['editable'] !== '', $key . ': the driver opens a field for it');
		}
		if (!isset($column['editable'])) {
			$text[] = $key;
		}
	}
	pg_assert_same(array('created', 'last_modified'), $text, 'only the times stay text');
	pg_assert_same(3, $columns['zip_code_prefix']['max'], 'the screen\'s three characters');
	pg_assert_same(array('4' => 'Ground (GND)', '7' => '<b>Express</b>'), $columns['shipping_method_id']['options'], 'the screen\'s pick list, as text');
	pg_assert_same(-999, $columns['adjustment']['min']);
	pg_assert_same(999, $columns['adjustment']['max']);
	foreach (pg_ship_date_adjustment_list_columns(false) as $key => $column) {
		pg_assert_true(!isset($column['editable']), $key . ' is plain without quick edit');
	}
}

function test_ship_date_adjustment_quick_cells_carry_their_values()
{
	$columns = pg_ship_date_adjustment_quick_edit_columns(ship_date_adjustment_list_edit_test_methods());
	$cells = pg_ship_date_adjustment_list_cells(ship_date_adjustment_list_edit_test_item(), $columns);
	pg_assert_same('852', $cells['zip_code_prefix']['attributes']['data-pg-edit-value']);
	pg_assert_contains('data-pg-open', $cells['zip_code_prefix']['html'], 'the prefix opens the record');
	pg_assert_same('shipping_method_id', $cells['shipping_method']['attributes']['data-pg-edit']);
	pg_assert_same('4', $cells['shipping_method']['attributes']['data-pg-edit-value']);
	pg_assert_same('-3', $cells['adjustment']['attributes']['data-pg-edit-value'], 'the signed days');
	pg_assert_same('-3', (string) $cells['adjustment']['attributes']['data-pg-sort-value']);

	$gone = pg_ship_date_adjustment_list_cells(ship_date_adjustment_list_edit_test_item(array('shipping_method_id' => 99)), $columns);
	pg_assert_true(!isset($gone['shipping_method']['attributes']['data-pg-edit']), 'a method not offered stays text');

	$plain = pg_ship_date_adjustment_list_cells(ship_date_adjustment_list_edit_test_item(), array());
	pg_assert_true(!isset($plain['zip_code_prefix']['attributes']['data-pg-edit']), 'no field without quick edit');
}

function test_ship_date_adjustment_quick_values_keep_what_was_not_edited()
{
	$item = ship_date_adjustment_list_edit_test_item();
	pg_assert_same(array('zip_code_prefix' => '853', 'shipping_method_id' => 4, 'adjustment' => -3), pg_ship_date_adjustment_quick_values($item, 'zip_code_prefix', ' 853 '));
	pg_assert_same(array('zip_code_prefix' => '852', 'shipping_method_id' => 7, 'adjustment' => -3), pg_ship_date_adjustment_quick_values($item, 'shipping_method_id', '7'));
	pg_assert_same(array('zip_code_prefix' => '852', 'shipping_method_id' => 4, 'adjustment' => 5), pg_ship_date_adjustment_quick_values($item, 'adjustment', 5));
}

function test_ship_date_adjustment_quick_error_uses_the_screen_rules()
{
	$item = ship_date_adjustment_list_edit_test_item();
	pg_assert_same(lang('Sorry, the zip code prefix must contain 3 characters.'), pg_ship_date_adjustment_quick_error(pg_ship_date_adjustment_quick_values($item, 'zip_code_prefix', '85'), 'zip_code_prefix'));
	pg_assert_same(lang('Please enter a valid number of days.'), pg_ship_date_adjustment_quick_error(pg_ship_date_adjustment_quick_values($item, 'adjustment', 0), 'adjustment'), 'no adjustment of 0 days');
	pg_assert_same('', pg_ship_date_adjustment_quick_error(pg_ship_date_adjustment_quick_values($item, 'adjustment', -999), 'adjustment'));
	pg_assert_same('', pg_ship_date_adjustment_quick_error(pg_ship_date_adjustment_quick_values($item, 'adjustment', 2), 'adjustment'));
}

function test_ship_date_adjustment_quick_saved_answers_every_cell()
{
	$columns = pg_ship_date_adjustment_quick_edit_columns(ship_date_adjustment_list_edit_test_methods());
	$answer = pg_ship_date_adjustment_quick_saved(pg_ship_date_adjustment_list_cells(ship_date_adjustment_list_edit_test_item(), $columns), 'shipping_method_id');
	pg_assert_true($answer['ok']);
	pg_assert_same('4', $answer['value']);
	pg_assert_same(array('zip_code_prefix', 'adjustment', 'created', 'last_modified'), array_keys($answer['cells']));
}

function test_ship_date_adjustment_quick_edit_answers_before_the_screen()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_ship_date_adjustments.php');
	pg_assert_true(strpos($src, 'pg_list_edit_request($quick_edit') < strpos($src, 'validate_ecommerce_access($user);'), 'a refusal is JSON');
	pg_assert_contains("'edit' => array('url' => 'view_ship_date_adjustments.php', 'columns' => \$quick_edit)", $src);
}
