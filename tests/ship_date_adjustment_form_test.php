<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ship date adjustment form shared by the create and the
 * edit screens (includes/forms/ship_date_adjustment_form.php): the pick
 * list options, the signed adjustment and its form fields, and that both
 * screens use the shared form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/ship_date_adjustment_form.php');

function test_ship_date_adjustment_form_adjustment_round_trip()
{
	pg_assert_same(-3, pg_ship_date_adjustment_form_adjustment(3, 'earlier'), 'earlier is saved negative');
	pg_assert_same('3', pg_ship_date_adjustment_form_adjustment('3', 'later'), 'later is saved as posted');

	pg_assert_same(array('adjustment_days' => 2, 'adjustment_type' => 'earlier'), pg_ship_date_adjustment_form_fields(-2), 'a negative adjustment is earlier');
	pg_assert_same(array('adjustment_days' => 5, 'adjustment_type' => 'later'), pg_ship_date_adjustment_form_fields(5), 'a positive adjustment is later');
}

function test_ship_date_adjustment_form_options()
{
	$methods = array(
		array('id' => '4', 'name' => 'Ground & Co', 'code' => 'GR'),
		array('id' => '7', 'name' => 'Air', 'code' => ''),
	);

	pg_assert_same(
		'<option value=""></option><option value="4">Ground &amp; Co (GR)</option><option value="7" selected>Air</option>',
		pg_ship_date_adjustment_form_shipping_method_options($methods, '7'),
		'methods are escaped, the code follows the name, the chosen one is selected'
	);

	pg_assert_contains('<option value="later" selected>', pg_ship_date_adjustment_form_adjustment_type_options(''), 'later is chosen when nothing is');
	pg_assert_contains('<option value="earlier" selected>', pg_ship_date_adjustment_form_adjustment_type_options('earlier'), 'earlier stays chosen');
}

function test_ship_date_adjustment_form_screens_use_shared_form()
{
	foreach (array('add_ship_date_adjustment.php', 'edit_ship_date_adjustment.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/ship_date_adjustment_form.php', $source, $file . ' loads the form');
		pg_assert_contains('pg_ship_date_adjustment_form_card($liveform', $source, $file . ' prints the shared card');
		pg_assert_contains('pg_ship_date_adjustment_form_check($liveform, ', $source, $file . ' checks the adjustment the shared way');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_contains('validate_ecommerce_access($user)', $source, $file . ' keeps the commerce gate');
		pg_assert_false(strpos($source, 'zip_code_prefix" id="zip_code_prefix"') !== false, $file . ' keeps no copy of the card');
	}
}

function test_ship_date_adjustment_form_days_and_type()
{
	pg_assert_true(pg_ship_date_adjustment_form_days_valid('2'), 'a whole number');
	pg_assert_true(pg_ship_date_adjustment_form_days_valid('100'), 'three digits');
	pg_assert_false(pg_ship_date_adjustment_form_days_valid('1.5'), 'a decimal was rounded to 2 days');
	pg_assert_false(pg_ship_date_adjustment_form_days_valid('1e2'), 'an exponent was saved as 100 days');
	pg_assert_false(pg_ship_date_adjustment_form_days_valid('0'), 'zero');
	pg_assert_false(pg_ship_date_adjustment_form_days_valid('-3'), 'negative');
	pg_assert_false(pg_ship_date_adjustment_form_days_valid(''), 'blank');
	pg_assert_false(pg_ship_date_adjustment_form_days_valid(array('2')), 'a list');

	pg_assert_true(pg_ship_date_adjustment_form_type_valid('earlier'), 'earlier');
	pg_assert_true(pg_ship_date_adjustment_form_type_valid('later'), 'later');
	pg_assert_false(pg_ship_date_adjustment_form_type_valid('sideways'), 'any other direction was saved as later');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/ship_date_adjustment_form.php');
	pg_assert_contains('SELECT COUNT(*) FROM shipping_methods WHERE id = ?', $source, 'the method has to exist');
}

function test_ship_date_adjustment_form_wide_layout()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/ship_date_adjustment_form.php');

	pg_assert_contains('col-12 lg:col-8 2xl:col-9 order-1', $source, 'the form column');
	pg_assert_contains('col-12 lg:col-4 2xl:col-3 order-2', $source, 'the status column');
	pg_assert_contains('<div class="position-sticky" style="top:3.3rem;">', $source, 'the status box sticks below the top bar');
	pg_assert_contains('col-12 order-3 position-sticky', $source, 'the sticky save bar');

	foreach (array('add_ship_date_adjustment.php', 'edit_ship_date_adjustment.php') as $file) {
		$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('pg_ship_date_adjustment_form_layout(', $screen, $file . ' prints the shared layout');
	}
}
