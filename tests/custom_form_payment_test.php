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

function test_form_fee_settings_stored_shape()
{
	pg_assert_same('', pg_form_fee_settings_encode(array('product_id' => 0, 'amount_min' => 500)), 'no product: nothing stored');
	pg_assert_same('', pg_form_fee_settings_encode('junk'), 'junk: nothing stored');

	$stored = pg_form_fee_settings_encode(array('product_id' => '9', 'amount_source' => 'field', 'amount_field' => 'gift', 'amount_max' => 5000, 'extra' => 1));
	$back = pg_form_fee_settings_decode($stored);
	pg_assert_same(9, $back['product_id'], 'product kept through JSON');
	pg_assert_same('field', $back['amount_source'], 'source kept');
	pg_assert_same('gift', $back['amount_field'], 'field kept');
	pg_assert_same(5000, $back['amount_max'], 'bound kept');
	pg_assert_true(strpos($stored, 'extra') === false, 'unknown keys never stored');

	pg_assert_same(0, pg_form_fee_settings_decode(null)['product_id'], 'NULL column: no fee');
	pg_assert_same(0, pg_form_fee_settings_decode('')['product_id'], 'empty column: no fee');
	pg_assert_same(0, pg_form_fee_settings_decode('{broken')['product_id'], 'unreadable JSON: no fee');
}

function test_form_payment_order_columns()
{
	$fee = pg_form_fee(_test_form_fee_settings(array('offline' => 1)), array());
	$contact = array(
		'contact_id' => 4, 'user_id' => 0, 'first_name' => ' Ada ', 'last_name' => 'Lovelace',
		'email_address' => 'ada@example.com', 'phone_number' => '555', 'country' => 'TR', 'junk' => 'x',
	);
	$columns = pg_form_payment_order_columns($fee, $contact, 'Registration fee', 'TRY', true);

	pg_assert_same('incomplete', $columns['order']['status'], 'written incomplete: the payment completes it');
	pg_assert_same(15000, $columns['order']['total'], 'the total is the fee');
	pg_assert_same(15000, $columns['order']['subtotal'], 'nothing added to the subtotal');
	pg_assert_same('TRY', $columns['order']['currency_code'], 'the site currency');
	pg_assert_same('Ada', $columns['order']['billing_first_name'], 'billing from the contact, trimmed');
	pg_assert_same('ada@example.com', $columns['order']['billing_email_address'], 'billing e-mail');
	pg_assert_same('', $columns['order']['billing_city'], 'a missing contact field is empty');
	pg_assert_same(4, $columns['order']['contact_id'], 'the contact');
	pg_assert_same(1, $columns['order']['offline_payment_allowed'], 'offline allowed by the form and the store');
	pg_assert_true(!isset($columns['order']['notes']), 'notes untouched');

	pg_assert_same(7, $columns['item']['product_id'], 'one line of the fee product');
	pg_assert_same(1, $columns['item']['quantity'], 'quantity 1');
	pg_assert_same(15000, $columns['item']['price'], 'line price is the fee');
	pg_assert_same(0, $columns['item']['tax_total'], 'no tax on top');
	pg_assert_same('Registration fee', $columns['item']['product_name'], 'product name');

	$no_store = pg_form_payment_order_columns($fee, $contact, 'Fee', 'TRY', false);
	pg_assert_same(0, $no_store['order']['offline_payment_allowed'], 'store without offline payment: not allowed');

	$refused = pg_form_fee(_test_form_fee_settings(array('amount_source' => 'field', 'amount_field' => 'a', 'amount_min' => 1000)), array('a' => '5'));
	pg_assert_same(null, pg_form_payment_order_columns($refused, $contact, 'Fee', 'TRY', true), 'a refused fee writes no order');
	pg_assert_same(null, pg_form_payment_order_columns(null, $contact, 'Fee', 'TRY', true), 'no fee writes no order');
}

function test_form_payment_mark_paid_once()
{
	$query = pg_form_payment_mark_paid_query(31, 1700000000);
	pg_assert_true(strpos($query['sql'], 'paid_at = 0') !== false, 'only a record still unpaid is marked');
	pg_assert_true(strpos($query['sql'], 'order_id = ?') !== false, 'found by its order');
	pg_assert_same(array(1700000000, 31), $query['params'], 'time then order');
	pg_assert_same(null, pg_form_payment_mark_paid_query(0, 1700000000), 'no order: nothing marked');
	pg_assert_same(1, pg_form_payment_mark_paid_query(5, 0)['params'][0], 'a stamp is never 0, which reads as unpaid');
	// An offline payment completes the order with paid_at 0: the record waits
	// until the order itself is paid.
	pg_assert_true(strpos($query['sql'], 'orders.paid_at > 0') !== false, 'only once the order is paid');
}

function test_form_payment_state()
{
	pg_assert_same('none', pg_form_payment_state(array()), 'no columns: no fee');
	pg_assert_same('none', pg_form_payment_state(array('order_id' => 0, 'paid_at' => 0)), 'no order: no fee');
	pg_assert_same('unpaid', pg_form_payment_state(array('order_id' => 4, 'paid_at' => 0)), 'an order not yet paid');
	pg_assert_same('paid', pg_form_payment_state(array('order_id' => '4', 'paid_at' => '1700000000')), 'paid');
}

function test_form_payment_unpaid_condition()
{
	pg_assert_same('(forms.order_id > 0 AND forms.paid_at = 0)', pg_form_payment_unpaid_where('forms'), 'records with a fee order not yet paid');
}

function test_form_paid_event_payload()
{
	$payload = pg_form_paid_event_payload(array(
		'id' => '12', 'page_id' => '30', 'reference_code' => 'ABC', 'contact_id' => '0',
		'order_id' => '44', 'order_number' => '1009', 'paid_at' => '1700000000', 'secret' => 'x',
	));
	pg_assert_same(array(
		'id' => 12, 'form_id' => 30, 'reference_code' => 'ABC', 'contact_id' => null,
		'order_id' => 44, 'order_number' => '1009', 'paid_at' => gmdate('Y-m-d\\TH:i:s\\Z', 1700000000),
	), $payload, 'identifiers only, no values');
	pg_assert_same(5, pg_form_paid_event_payload(array('id' => 1, 'page_id' => 2, 'contact_id' => 5))['contact_id'], 'the contact when there is one');
}
