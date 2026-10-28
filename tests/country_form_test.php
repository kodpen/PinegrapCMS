<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the country form shared by add_country.php and edit_country.php
 * (includes/forms/country_form.php): the posted values, the card and both
 * screens drawing the one card.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/country_form.php');

function test_country_form_values()
{
	pg_assert_same(
		array('name' => 'Germany', 'code' => 'DE', 'zip_code_required' => '1', 'transit_adjustment_days' => '2', 'default_selected' => '0'),
		pg_country_form_values(array('name' => 'Germany', 'code' => 'DE', 'zip_code_required' => 1, 'transit_adjustment_days' => 2, 'default_selected' => '0', 'id' => '5')),
		'the five fields as text, other keys ignored');

	pg_assert_same(
		array('name' => '', 'code' => '', 'zip_code_required' => '', 'transit_adjustment_days' => '', 'default_selected' => ''),
		pg_country_form_values(array('name' => array('x'), 'code' => null)),
		'missing, null or nested values are empty');
}

function test_country_form_card_prints_stored_values()
{
	$card = pg_country_form_card(array('name' => 'A "B"', 'code' => 'AB', 'zip_code_required' => '1', 'transit_adjustment_days' => '3', 'default_selected' => '0'));

	pg_assert_contains('value="A &quot;B&quot;"', $card, 'the name is escaped');
	pg_assert_contains('value="1" checked name="zip_code_required"', $card, 'a stored zip switch is on');
	pg_assert_contains('value="1" name="default_selected"', $card, 'an off default switch stays off');
	pg_assert_contains('value="3" type="text" name="transit_adjustment_days"', $card, 'the transit days are filled');

	$empty = pg_country_form_card(null);

	pg_assert_false(strpos($empty, 'checked') !== false, 'the create card has no switch on');
	pg_assert_contains('maxlength="50" />', $empty, 'the create card prints no name value');
}

function test_country_form_both_screens_draw_the_shared_card()
{
	foreach (array('add_country.php', 'edit_country.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('pg_country_form_card(', $source, $file . ' draws the shared card');
		pg_assert_contains('pg_country_form_values($_POST)', $source, $file . ' reads the posted values through the shared helper');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_contains('validate_ecommerce_access($user)', $source, $file . ' keeps its gate');
	}
}
