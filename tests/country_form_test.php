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

function test_country_form_transit_days_take_whole_numbers_only()
{
	$message = lang('Enter a whole number of days, 0 or more.');

	foreach (array('', '0', '3', ' 12 ', '007', '999999999') as $value) {
		pg_assert_same('', pg_country_form_transit_days_error($value), var_export($value, true) . ' passes');
	}

	foreach (array('-5', '12,50', '12.5', 'abc', '1e3', '+2', '1000000000') as $value) {
		pg_assert_same($message, pg_country_form_transit_days_error($value), var_export($value, true) . ' is refused');
	}

	pg_assert_same(0, pg_country_form_transit_days(''), 'an empty box stores 0');
	pg_assert_same(12, pg_country_form_transit_days(' 12 '), 'a whole number is stored as is');

	$errors = pg_country_form_required_errors(array('name' => 'Germany', 'code' => 'DE', 'transit_adjustment_days' => '-5'));
	pg_assert_same(array('transit_adjustment_days' => $message), $errors, 'the form refuses negative days');

	$card = pg_country_form_card(pg_country_form_values(array('transit_adjustment_days' => '-5')), $errors);
	pg_assert_contains('value="-5" type="text" name="transit_adjustment_days"', $card, 'the typed value is kept');
	pg_assert_contains('<div id="transit_adjustment_days-error" class="invalid-feedback d-block">' . h($message) . '</div>', $card, 'the message under the field');
	pg_assert_contains('data-inputmask-alias="integer"', $card, 'the box takes whole numbers');

	foreach (array('add_country.php', 'edit_country.php') as $file) {
		pg_assert_contains("pg_country_form_transit_days(\$values['transit_adjustment_days'])", file_get_contents(PG_FUNCTIONS_DIR . '/' . $file), $file . ' stores the days as an integer');
	}
}

function test_country_form_card_prints_stored_values()
{
	$card = pg_country_form_card(array('name' => 'A "B"', 'code' => 'AB', 'zip_code_required' => '1', 'transit_adjustment_days' => '3', 'default_selected' => '0'));

	pg_assert_contains('value="A &quot;B&quot;"', $card, 'the name is escaped');
	pg_assert_contains('value="1" checked name="zip_code_required"', $card, 'a stored zip switch is on');
	pg_assert_contains('value="1" name="default_selected"', $card, 'an off default switch stays off');
	pg_assert_contains('value="3" type="text" name="transit_adjustment_days"', $card, 'the transit days are filled');

	$empty = pg_country_form_card(null);

	pg_assert_false(strpos($empty, ' checked name=') !== false, 'the create card has no switch on');
	pg_assert_contains('maxlength="50" required />', $empty, 'the create card prints no name value');
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

function test_country_form_wide_layout()
{
	$card = pg_country_form_card(array('name' => 'Germany', 'code' => 'DE', 'zip_code_required' => '1', 'transit_adjustment_days' => '007', 'default_selected' => '1'));

	pg_assert_contains('<div class="col-12 lg:col-8 2xl:col-9 order-1">', $card, 'the form column');
	pg_assert_contains('<div class="col-12 lg:col-4 2xl:col-3 order-2">', $card, 'the status column');
	pg_assert_contains('<div class="position-sticky" style="top:3.3rem;">', $card, 'the status box sticks below the top bar');
	pg_assert_true(strpos($card, 'order-1') < strpos($card, 'name="zip_code_required"') && strpos($card, 'name="zip_code_required"') < strpos($card, 'order-2'), 'the zip switch sits in the form column');
	pg_assert_true(strpos($card, 'order-2') < strpos($card, 'name="default_selected"'), 'the default switch sits in the status column');
	pg_assert_contains('id="country_summary_code">DE</dd>', $card, 'the Summary shows the code');
	pg_assert_contains('id="country_summary_zip">' . h(lang('Yes')) . '</dd>', $card, 'the Summary shows the zip rule');
	pg_assert_contains('id="country_summary_days">7</dd>', $card, 'the Summary shows the days as stored');

	$rows = pg_country_form_edit_status_rows(3, 2, 1, '5 minutes ago admin');
	pg_assert_contains('<dd class="mb-0 fw-bold text-end text-break">3</dd>', $rows, 'the state count');
	pg_assert_contains(h(lang('Tax Zones')), $rows, 'the tax zone row');
	pg_assert_contains('<dd class="mb-0 fw-bold text-end text-break">5 minutes ago admin</dd>', $rows, 'the last change');

	$with_rows = pg_country_form_card(null, array(), $rows);
	pg_assert_contains('py-2 border-bottom">' . "\n" . '                                                <dt class="fw-normal">' . h(lang('Extra Delivery Days')) . '</dt>', $with_rows, 'the days row keeps its border above the edit rows');

	$layout = pg_country_form_layout('CARDS', '<button>Save</button>');
	pg_assert_contains('<div class="row">CARDS', $layout, 'the columns inside the row');
	pg_assert_contains('<div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">', $layout, 'the sticky save bar');

	foreach (array('add_country.php', 'edit_country.php') as $file) {
		pg_assert_contains('pg_country_form_layout(', (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file), $file . ' draws the shared layout');
	}
	pg_assert_contains('pg_country_form_card($values, $errors, $status_rows)', (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_country.php'), 'edit passes its Summary rows');
}
