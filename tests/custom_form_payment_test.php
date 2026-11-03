<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the fee of a designed form (includes/fn/form_payments.php): the
 * amount comes from the fee product's price or from a numeric field held
 * between a minimum and a maximum; a form without a fee product has no fee.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_form_fee_settings($extra = array())
{
	return array_merge(array(
		'product_id'    => 7,
		'product_price' => 15000,
		'amount_source' => 'product',
		'amount_field'  => '',
		'amount_min'    => 0,
		'amount_max'    => 0,
		'offline'       => 0,
	), $extra);
}

function test_form_fee_none_without_product()
{
	pg_assert_same(null, pg_form_fee(array(), array()), 'no settings: no fee');
	pg_assert_same(null, pg_form_fee(_test_form_fee_settings(array('product_id' => 0)), array()), 'no product: no fee');
	pg_assert_same(null, pg_form_fee('junk', array('a' => '1')), 'settings that are not an array: no fee');
}

function test_form_fee_product_price()
{
	$fee = pg_form_fee(_test_form_fee_settings(), array('amount' => '999'));
	pg_assert_same(true, $fee['ok'], 'the product price is the fee');
	pg_assert_same(15000, $fee['amount'], 'in minor units, the posted values ignored');
	pg_assert_same(7, $fee['product_id'], 'the fee product');
	pg_assert_same(0, $fee['offline'], 'offline payment off');

	$free = pg_form_fee(_test_form_fee_settings(array('product_price' => 0)), array());
	pg_assert_same(null, $free, 'a product priced 0 asks for no payment');
}

function test_form_fee_from_field()
{
	$settings = _test_form_fee_settings(array(
		'amount_source' => 'field', 'amount_field' => 'donation',
		'amount_min' => 1000, 'amount_max' => 500000, 'offline' => 1,
	));

	$fee = pg_form_fee($settings, array('donation' => '25'));
	pg_assert_same(true, $fee['ok'], 'a whole figure');
	pg_assert_same(2500, $fee['amount'], '25 is 2500 minor units');
	pg_assert_same(1, $fee['offline'], 'offline payment allowed');

	$fee = pg_form_fee($settings, array('donation' => '1.234,567'));
	pg_assert_same(123457, $fee['amount'], 'grouping read, the third decimal rounded, never truncated');

	$fee = pg_form_fee($settings, array('donation' => array('30')));
	pg_assert_same(3000, $fee['amount'], 'a one-value list is read as its value');
}

function test_form_fee_field_bounds()
{
	$settings = _test_form_fee_settings(array(
		'amount_source' => 'field', 'amount_field' => 'donation',
		'amount_min' => 1000, 'amount_max' => 500000,
	));

	$low = pg_form_fee($settings, array('donation' => '5'));
	pg_assert_same(false, $low['ok'], 'under the minimum refused, never raised');
	pg_assert_same('below_min', $low['error'], 'with its reason');

	$high = pg_form_fee($settings, array('donation' => '6000'));
	pg_assert_same(false, $high['ok'], 'over the maximum refused, never lowered');
	pg_assert_same('above_max', $high['error'], 'with its reason');

	foreach (array('', 'abc', '-20', '0') as $value) {
		$bad = pg_form_fee($settings, array('donation' => $value));
		pg_assert_same('no_amount', $bad['error'], 'no positive amount in "' . $value . '"');
	}

	$missing = pg_form_fee($settings, array());
	pg_assert_same('no_amount', $missing['error'], 'the field not posted');

	$open = pg_form_fee(array_merge($settings, array('amount_min' => 0, 'amount_max' => 0)), array('donation' => '0,01'));
	pg_assert_same(1, $open['amount'], 'no bounds: any positive amount');

	$nofield = pg_form_fee(array_merge($settings, array('amount_field' => '')), array('donation' => '50'));
	pg_assert_same('no_amount', $nofield['error'], 'a field source without a field asks for nothing it can read');
}

function test_form_fee_settings_clean()
{
	$clean = pg_form_fee_settings_clean(array(
		'product_id' => '12', 'amount_source' => 'weird', 'amount_field' => '  donation ',
		'amount_min' => '-5', 'amount_max' => '100', 'offline' => 'yes', 'extra' => 'dropped',
	));
	pg_assert_same(12, $clean['product_id'], 'product id as a number');
	pg_assert_same('product', $clean['amount_source'], 'an unknown source is the product price');
	pg_assert_same('donation', $clean['amount_field'], 'field name trimmed');
	pg_assert_same(0, $clean['amount_min'], 'a negative bound is none');
	pg_assert_same(100, $clean['amount_max'], 'bound kept');
	pg_assert_same(1, $clean['offline'], 'offline as 0 or 1');
	pg_assert_true(!isset($clean['extra']), 'unknown keys dropped');

	$swap = pg_form_fee_settings_clean(array('amount_source' => 'field', 'amount_min' => 900, 'amount_max' => 100));
	pg_assert_same(100, $swap['amount_min'], 'a minimum over the maximum swapped');
	pg_assert_same(900, $swap['amount_max'], 'and the maximum');

	pg_assert_same(0, pg_form_fee_settings_clean(null)['product_id'], 'nothing in: no product');
}
