<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the All Shipping Zones list quick edit
 * (includes/fn/zone_list_edit.php): the name and the four rates are fields,
 * checked with the zone screen's rules; a rate not edited is written as
 * stored, never read again from text.
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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/zone_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/zone_list_edit.php');

function zone_list_edit_test_zone(array $over = array())
{
	return $over + array(
		'id' => 3, 'name' => 'Europe', 'base_rate' => 1250, 'primary_weight_rate' => 0,
		'secondary_weight_rate' => 75, 'item_rate' => 100, 'user' => '<b>ayse</b>', 'timestamp' => time());
}

function test_zone_quick_edit_every_column_but_last_modified_is_a_field()
{
	$columns = pg_zone_quick_edit_columns();
	$text = array_diff(array_keys(pg_zone_list_columns(true)), array_keys($columns));
	pg_assert_same(array('last_modified'), array_values($text), 'only the last change stays text');
	pg_assert_same(50, $columns['name']['max'], 'the screen\'s name length');
	pg_assert_true(!empty($columns['name']['required']));
	foreach (pg_store_zone_rate_columns() as $rate) {
		pg_assert_same('money', $columns[$rate]['type']);
		pg_assert_true(!empty($columns[$rate]['empty']), 'a blank rate is 0, as on the screen');
	}
	foreach (pg_zone_list_columns(false) as $key => $column) {
		pg_assert_true(!isset($column['editable']), $key . ' is plain without quick edit');
	}
}

function test_zone_quick_cells_carry_their_fields()
{
	$cells = pg_zone_list_cells(zone_list_edit_test_zone(), pg_zone_quick_edit_columns());
	pg_assert_same('name', $cells['name']['attributes']['data-pg-edit']);
	pg_assert_same('Europe', $cells['name']['attributes']['data-pg-edit-value']);
	pg_assert_same('base_rate', $cells['base_rate']['attributes']['data-pg-edit']);
	pg_assert_same('1250', (string) $cells['base_rate']['attributes']['data-pg-sort-value']);
	pg_assert_true(!isset($cells['last_modified']['attributes']['data-pg-edit']));

	$plain = pg_zone_list_cells(zone_list_edit_test_zone(), array());
	pg_assert_true(!isset($plain['name']['attributes']['data-pg-edit']), 'no field without quick edit');
}

function test_zone_quick_write_keeps_the_rates_not_edited()
{
	$zone = zone_list_edit_test_zone();
	$write = pg_zone_quick_write($zone, pg_zone_quick_values($zone, 'item_rate', '2'), 'item_rate');
	pg_assert_same(array('name' => 'Europe', 'base_rate' => 1250, 'primary_weight_rate' => 0, 'secondary_weight_rate' => 75, 'item_rate' => 200), $write);

	$named = pg_zone_quick_write($zone, pg_zone_quick_values($zone, 'name', '  Asia '), 'name');
	pg_assert_same('Asia', $named['name']);
	pg_assert_same(1250, $named['base_rate']);
}

function test_zone_quick_error_uses_the_screen_rules()
{
	$zone = zone_list_edit_test_zone();
	pg_assert_same(pg_zone_form_name_error(''), pg_zone_quick_error(pg_zone_quick_values($zone, 'name', ' '), 'name'));
	pg_assert_true(pg_zone_quick_error(pg_zone_quick_values($zone, 'base_rate', '99999999999'), 'base_rate') !== '', 'above the column limit');
	pg_assert_same('', pg_zone_quick_error(pg_zone_quick_values($zone, 'base_rate', ''), 'base_rate'), 'blank is 0');
	pg_assert_same(pg_zone_form_name_error(''), pg_zone_quick_error(pg_zone_quick_values(zone_list_edit_test_zone(array('name' => '')), 'item_rate', '1'), 'item_rate'), 'a stored blank name refuses until named');
}

function test_zone_quick_saved_answers_every_cell()
{
	$answer = pg_zone_quick_saved(pg_zone_list_cells(zone_list_edit_test_zone(), pg_zone_quick_edit_columns()), 'base_rate');
	pg_assert_true($answer['ok']);
	pg_assert_same('1250', $answer['sort']);
	pg_assert_same(array('name', 'primary_weight_rate', 'secondary_weight_rate', 'item_rate', 'last_modified'), array_keys($answer['cells']));
	pg_assert_same('Europe', $answer['cells']['name']['value']);
}

function test_zone_quick_edit_answers_before_the_screen()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_zones.php');
	pg_assert_true(strpos($src, 'pg_list_edit_request($quick_edit') < strpos($src, 'validate_ecommerce_access($user);'), 'a refusal is JSON');
	pg_assert_contains("'edit' => array('url' => 'view_zones.php', 'columns' => \$quick_edit)", $src);
}
