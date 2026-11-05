<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the All Referral Sources list quick edit
 * (includes/fn/referral_source_list_edit.php): the name, the code and the
 * sort order are fields, checked with the referral source screen's rules.
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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/referral_source_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/referral_source_list_edit.php');

function referral_source_list_edit_test_source(array $over = array())
{
	return $over + array('id' => 4, 'name' => 'Radio', 'code' => 'RAD', 'sort_order' => '7', 'user' => '<b>ayse</b>', 'timestamp' => time());
}

function test_referral_source_quick_edit_every_column_but_last_modified_is_a_field()
{
	$columns = pg_referral_source_quick_edit_columns();
	$text = array_diff(array_keys(pg_referral_source_list_columns(true)), array_keys($columns));
	pg_assert_same(array('last_modified'), array_values($text), 'only the last change stays text');
	foreach (array('name', 'code') as $field) {
		pg_assert_same('text', $columns[$field]['type']);
		pg_assert_same(50, $columns[$field]['max'], 'the screen\'s input limit');
		pg_assert_true(!empty($columns[$field]['required']));
	}
	pg_assert_same('integer', $columns['sort_order']['type']);
	pg_assert_same(999, $columns['sort_order']['max'], 'the screen\'s sort order bound');
	pg_assert_true(!empty($columns['sort_order']['empty']), 'a blank sort order is 0, as on the screen');
	foreach (pg_referral_source_list_columns(false) as $key => $column) {
		pg_assert_true(!isset($column['editable']), $key . ' is plain without quick edit');
	}
}

function test_referral_source_quick_cells_carry_their_fields()
{
	$cells = pg_referral_source_list_cells(referral_source_list_edit_test_source(), pg_referral_source_quick_edit_columns());
	pg_assert_same('name', $cells['name']['attributes']['data-pg-edit']);
	pg_assert_same('Radio', $cells['name']['attributes']['data-pg-edit-value']);
	pg_assert_contains('data-pg-open', $cells['name']['html']);
	pg_assert_same('RAD', $cells['code']['attributes']['data-pg-edit-value']);
	pg_assert_same('7', $cells['sort_order']['attributes']['data-pg-edit-value']);
	pg_assert_true(!isset($cells['last_modified']['attributes']['data-pg-edit']));

	$plain = pg_referral_source_list_cells(referral_source_list_edit_test_source(), array());
	pg_assert_true(!isset($plain['name']['attributes']['data-pg-edit']), 'no field without quick edit');
}

function test_referral_source_quick_values_keep_the_others()
{
	$source = referral_source_list_edit_test_source();
	pg_assert_same(array('name' => 'Radio', 'code' => 'TV', 'sort_order' => '7'), pg_referral_source_quick_values($source, 'code', ' TV '));
	pg_assert_same(array('name' => 'Radio', 'code' => 'RAD', 'sort_order' => ''), pg_referral_source_quick_values($source, 'sort_order', ''));
	pg_assert_same(array('name' => 'Radio', 'code' => 'RAD', 'sort_order' => '12'), pg_referral_source_quick_values($source, 'sort_order', 12));
}

function test_referral_source_quick_error_uses_the_screen_rules()
{
	$source = referral_source_list_edit_test_source();
	$others = array(array('name' => 'Television', 'code' => 'TV'));
	pg_assert_same('', pg_referral_source_form_error(pg_referral_source_quick_values($source, 'sort_order', ''), $others), 'blank sort order is 0');
	pg_assert_true(pg_referral_source_form_error(pg_referral_source_quick_values($source, 'code', 'tv'), $others) !== '', 'another source has the code');
	pg_assert_true(pg_referral_source_form_error(pg_referral_source_quick_values($source, 'name', 'television'), $others) !== '', 'another source has the name');
	pg_assert_true(pg_referral_source_form_error(pg_referral_source_quick_values(referral_source_list_edit_test_source(array('code' => '')), 'name', 'Press'), $others) !== '', 'a stored blank code refuses until set');
}

function test_referral_source_quick_saved_answers_every_cell()
{
	$answer = pg_referral_source_quick_saved(pg_referral_source_list_cells(referral_source_list_edit_test_source(), pg_referral_source_quick_edit_columns()), 'code');
	pg_assert_true($answer['ok']);
	pg_assert_same('RAD', $answer['value']);
	pg_assert_same(array('name', 'sort_order', 'last_modified'), array_keys($answer['cells']));
	pg_assert_same('Radio', $answer['cells']['name']['value']);
}

function test_referral_source_quick_edit_answers_before_the_screen()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_referral_sources.php');
	pg_assert_true(strpos($src, 'pg_list_edit_request($quick_edit') < strpos($src, 'validate_ecommerce_access($user);'), 'a refusal is JSON');
	pg_assert_contains("'edit' => array('url' => 'view_referral_sources.php', 'columns' => \$quick_edit)", $src);
}
