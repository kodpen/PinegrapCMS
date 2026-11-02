<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the requested arrival date at checkout: a date that has passed
 * or that no shipping method can meet comes back from
 * get_shipping_methods() with the arrival_date code, so the checkout shows
 * its own message instead of the "We cannot ship to this address" notice;
 * the date box offers no day before today, and a hidden box is not posted.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_arrival_date_passed_only_before_today()
{
	pg_assert_true(pg_arrival_date_passed('2026-10-01', '2026-11-01'), 'a day of last month');
	pg_assert_true(pg_arrival_date_passed('2026-10-31', '2026-11-01'), 'yesterday');
	pg_assert_false(pg_arrival_date_passed('2026-11-01', '2026-11-01'), 'today');
	pg_assert_false(pg_arrival_date_passed('2026-11-20', '2026-11-01'), 'a later day');
	pg_assert_false(pg_arrival_date_passed('0000-00-00', '2026-11-01'), 'no date (At once)');
	pg_assert_false(pg_arrival_date_passed('', '2026-11-01'), 'empty');
	pg_assert_false(pg_arrival_date_passed('2026-02-30', '2026-11-01'), 'impossible date');
}

function test_checkout_arrival_date_box_bounds()
{
	pg_assert_same(' min="2026-11-01"', _eo_custom_arrival_bounds('', '2026-11-01'), 'no latest date');
	pg_assert_same(' min="2026-11-01"', _eo_custom_arrival_bounds('0000-00-00', '2026-11-01'), 'zero latest date');
	pg_assert_same(' min="2026-11-01" max="2026-12-31"', _eo_custom_arrival_bounds('2026-12-31', '2026-11-01'), 'latest date');
	pg_assert_same('', _eo_custom_arrival_bounds('', ''), 'no today');
}

function test_arrival_date_errors_carry_the_arrival_date_code()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/shipping.php');

	pg_assert_true(
		preg_match("/'code' => 'arrival_date',\s*'message' => lang\('Sorry, we could not find a shipping method that would guarantee delivery/", $source) === 1,
		'no method meets the date: coded, so the checkout keeps it out of the address notice');
	pg_assert_true(
		preg_match("/pg_arrival_date_passed\(\\\$arrival_date, date\('Y-m-d'\)\)\)\s*\{\s*return array\(\s*'status' => 'error',\s*'code' => 'arrival_date',/", $source) === 1,
		'a passed date: coded');
}

function test_checkout_hidden_arrival_date_box_is_not_posted()
{
	$js = _eo_render_widget_js(7, 'eo_form', true, array(array('id' => 3)), '/', array(
		'shipping_drawn' => 0,
		'shipping'       => 0,
		'total'          => 2700,
	));

	pg_assert_true(strpos($js, "cd.querySelectorAll('input').forEach(function (inp) { inp.disabled = true; });") !== false, 'hidden boxes are disabled');
	pg_assert_true(strpos($js, "custWrap.querySelectorAll('input').forEach(function (inp) { inp.disabled = false; });") !== false, 'the shown box is enabled');
}
