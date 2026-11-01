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
		pg_assert_contains('pg_state_form_card(', $source, $file . ' draws the shared card');
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
