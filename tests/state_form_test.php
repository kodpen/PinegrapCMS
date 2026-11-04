<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the state/province form shared by add_state.php and
 * edit_state.php (includes/forms/state_form.php): the posted values and
 * both screens drawing the one card.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/state_form.php');

function test_state_form_values()
{
	pg_assert_same(
		array('name' => 'Bavaria', 'code' => 'BY', 'country_id' => '82'),
		pg_state_form_values(array('name' => 'Bavaria', 'code' => 'BY', 'country_id' => 82, 'id' => '5')),
		'the three fields as text, other keys ignored');

	pg_assert_same(
		array('name' => '', 'code' => '', 'country_id' => ''),
		pg_state_form_values(array('name' => array('x'), 'code' => null)),
		'missing, null or nested values are empty');

	pg_assert_same(
		array('name' => 'Texas', 'code' => 'TX', 'country_id' => '1'),
		pg_state_form_values(array('name' => ' Texas', 'code' => ' TX ', 'country_id' => '1')),
		'the name and the code are trimmed');
}

function test_state_form_both_screens_draw_the_shared_card()
{
	foreach (array('add_state.php', 'edit_state.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('pg_state_form_cards(', $source, $file . ' draws the shared cards');
		pg_assert_contains('pg_state_form_layout(', $source, $file . ' draws the wide layout with the save bar');
		pg_assert_contains('pg_state_form_values($_POST)', $source, $file . ' reads the posted values through the shared helper');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_contains('validate_ecommerce_access($user)', $source, $file . ' keeps its gate');
	}
}

function test_state_form_required_errors()
{
	pg_assert_same(array(), pg_state_form_required_errors(array('name' => 'Adana', 'code' => '01', 'country_id' => '5')), 'a name and a code pass');

	$errors = pg_state_form_required_errors(pg_state_form_values(array('name' => ' ', 'code' => '', 'country_id' => '5')));
	pg_assert_same(array('name', 'code'), array_keys($errors), 'a blank name and code are both refused');
}

function test_state_form_screens_refuse_a_blank_name_and_code()
{
	foreach (array('add_state.php', 'edit_state.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('pg_state_form_required_errors($values)', $source, $file . ' checks the required fields');
	}
}

function test_state_form_country_must_exist()
{
	$exists = function ($id) {
		return $id === 5;
	};

	pg_assert_same('', pg_state_form_country_error('5', $exists), 'an existing country passes');
	pg_assert_same(lang('The selected country was not found. Select a country.'), pg_state_form_country_error('99999', $exists), 'a missing country is refused');

	foreach (array('', '0', '-5', 'abc', '5x') as $value) {
		pg_assert_same(lang('Select a country.'), pg_state_form_country_error($value, $exists), var_export($value, true) . ' is no country');
	}

	foreach (array('add_state.php', 'edit_state.php') as $file) {
		pg_assert_contains("pg_state_form_country_error(\$values['country_id'])", file_get_contents(PG_FUNCTIONS_DIR . '/' . $file), $file . ' checks the country');
	}
}

function test_state_form_selected_label()
{
	pg_assert_same('Türkiye', pg_state_form_selected_label('<option value="1">Almanya</option><option value="2" selected="selected">Türkiye</option>'), 'the selected country');
	pg_assert_same('A &amp; B', pg_state_form_selected_label('<option value="3" selected="selected">A &amp; B</option>'), 'kept as the option prints it');
	pg_assert_same('', pg_state_form_selected_label('<option value="" selected="selected">Select a country</option><option value="1">Almanya</option>'), 'the empty choice of a lost country');
	pg_assert_same('', pg_state_form_selected_label('<option value="1">Almanya</option>'), 'none selected');
}

function test_state_form_screens_use_the_wide_layout()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/state_form.php');
	pg_assert_contains('col-12 lg:col-8 2xl:col-9 order-1', $source, 'the form column');
	pg_assert_contains('col-12 lg:col-4 2xl:col-3 order-2', $source, 'the status column');
	pg_assert_contains('col-12 order-3 position-sticky', $source, 'the sticky save bar');
	pg_assert_contains('formnovalidate', file_get_contents(PG_FUNCTIONS_DIR . '/edit_state.php'), 'Delete is not held back by an emptied required box');
}
