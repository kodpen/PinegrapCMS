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
