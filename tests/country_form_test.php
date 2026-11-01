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

	pg_assert_same(
		array('name' => 'United States', 'code' => 'US', 'zip_code_required' => '1', 'transit_adjustment_days' => '', 'default_selected' => ''),
		pg_country_form_values(array('name' => ' United States ', 'code' => " US\t", 'zip_code_required' => '1')),
		'the name and the code are trimmed');
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

function test_country_form_required_errors()
{
	pg_assert_same(array(), pg_country_form_required_errors(array('name' => 'Germany', 'code' => 'DE')), 'a name and a code pass');

	$errors = pg_country_form_required_errors(pg_country_form_values(array('name' => '  ', 'code' => '')));
	pg_assert_same(array('name', 'code'), array_keys($errors), 'a blank name and code are both refused');
	pg_assert_same(lang('Enter a name.'), $errors['name'], 'the name message');
	pg_assert_same(lang('Enter a code.'), $errors['code'], 'the code message');
}

function test_country_form_card_marks_a_missing_name()
{
	$html = pg_country_form_card(null);
	pg_assert_contains('id="name" class="form-control" maxlength="50" required', $html, 'the name box is required');
	pg_assert_contains('id="code" class="form-control" maxlength="50" required', $html, 'the code box is required');

	$html = pg_country_form_card(pg_country_form_values(array('code' => 'DE')), array('name' => 'Enter a name.'));
	pg_assert_contains('id="name" class="form-control is-invalid"', $html, 'the name box is marked');
	pg_assert_contains('<div id="name-error" class="invalid-feedback d-block">Enter a name.</div>', $html, 'the message under the name');
	pg_assert_contains('value="DE"', $html, 'the typed code is kept');
}

function test_country_form_screens_refuse_a_blank_name_and_code()
{
	foreach (array('add_country.php', 'edit_country.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('pg_country_form_required_errors($values)', $source, $file . ' checks the required fields');
	}

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/add_country.php');
	pg_assert_contains("'cancel'=>array('enable'=>'true','url'=>'view_countries.php')", $source, 'add_country.php has the back arrow');
	pg_assert_true(strpos($source, 'all_ad_regions') === false, 'the breadcrumb goes to the plain list');
}
