<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the booking widget's deposit payment
 * (includes/fn/booking_deposit_pay.php): the deposit's state and words, the
 * agenda's mark, the page the payment comes back to, the offline line of
 * the payment step, the Booking fields of the API and the wiring of the
 * details step, the payment form, the return and the hold kept with an
 * order.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/bookings.php');

// Paid, owed, or no deposit order at all.
function test_booking_deposit_pay_view()
{
	pg_assert_same(array('state' => 'none', 'paid_at' => 0, 'order_id' => 0, 'order_number' => ''),
		pg_booking_deposit_view(array('id' => 4, 'status' => 'confirmed')), 'before the columns');
	pg_assert_same('none', pg_booking_deposit_view(array('order_id' => '0', 'paid_at' => '0'))['state']);

	$owed = pg_booking_deposit_view(array('order_id' => '31', 'paid_at' => '0', 'order_number' => '0'));
	pg_assert_same('unpaid', $owed['state']);
	pg_assert_same('', $owed['order_number'], 'an incomplete order has no number yet');

	$paid = pg_booking_deposit_view(array('order_id' => '31', 'paid_at' => '1793440000', 'order_number' => '1042'));
	pg_assert_same('paid', $paid['state']);
	pg_assert_same(1793440000, $paid['paid_at']);
	pg_assert_same('1042', $paid['order_number']);

	pg_assert_same('', pg_booking_deposit_label(array('state' => 'none')));
	pg_assert_true(pg_booking_deposit_label($paid) !== pg_booking_deposit_label($owed));
}

// The agenda's mark: none without an order, green when paid.
function test_booking_deposit_pay_badge()
{
	pg_assert_same('', pg_booking_deposit_badge(array('status' => 'confirmed')));
	pg_assert_contains('theme-success', pg_booking_deposit_badge(array('order_id' => 3, 'paid_at' => 5)));
	pg_assert_contains('theme-warning', pg_booking_deposit_badge(array('order_id' => 3, 'paid_at' => 0)));
	pg_assert_contains('visually-hidden', pg_booking_deposit_badge(array('order_id' => 3, 'paid_at' => 0)), 'read out, not only an icon');
}

// The payment comes back to a page of this site only.
function test_booking_deposit_pay_page()
{
	pg_assert_same('/booking?x=1', pg_booking_pay_page('/booking?x=1'));
	pg_assert_same(PATH, pg_booking_pay_page('https://evil.example/'), 'another host');
	pg_assert_same(PATH, pg_booking_pay_page('//evil.example/'), 'a protocol-relative address');
	pg_assert_same(PATH, pg_booking_pay_page(array('/x')));
	pg_assert_same(PATH, pg_booking_pay_page(''));
}

// The step's offline line is the caller's when given, the form's otherwise.
function test_booking_deposit_pay_offline_note()
{
	$view = array('amount' => '50', 'methods' => array('Credit/Debit Card' => 'Card', 'Offline Payment' => 'On arrival'), 'box' => '');
	$form = pg_form_payment_step_html($view);
	$booking = pg_form_payment_step_html($view + array('offline_note' => 'Pay when you come <b>'));

	pg_assert_contains('data-pg-cf-offline', $form);
	pg_assert_contains('Pay when you come &lt;b&gt;', $booking, 'escaped');
	pg_assert_false(strpos($booking, 'submission') !== false, 'not the form\'s words');
}

// The API's Booking carries the deposit order and its payment.
function test_booking_deposit_pay_api_fields()
{
	$row = array(
		'id' => '12', 'service_id' => '3', 'staff_id' => '5', 'user_id' => '0',
		'name' => 'Ada', 'email' => '', 'phone' => '', 'notes' => '',
		'start_at' => '1793440800', 'end_at' => '1793442600', 'status' => 'confirmed', 'source' => 'site',
		'hold_until' => '0', 'created_at' => '1793000000', 'updated_at' => '1793000100',
	);
	$none = api_booking_present($row, array(), array());
	pg_assert_same(null, $none['order_id']);
	pg_assert_same(null, $none['order_number']);
	pg_assert_same(null, $none['paid_at']);

	$paid = api_booking_present($row + array('order_id' => '31', 'paid_at' => '1793440000', 'order_number' => '1042'), array(), array());
	pg_assert_same(31, $paid['order_id']);
	pg_assert_same('1042', $paid['order_number']);
	pg_assert_same('2026-10-31T09:46:40Z', $paid['paid_at']);

	pg_assert_same(array_keys(api_booking_schema()), array_keys($paid), 'the schema names every field');
}

// The details step pays first, the payment posts and comes back here, and
// a hold with an order is cancelled rather than removed.
function test_booking_deposit_pay_wiring()
{
	$widget = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_booking.php');
	$confirm = substr($widget, strpos($widget, 'function pg_booking_confirm_request'));
	pg_assert_true(strpos($confirm, 'pg_booking_pay_start(') < strpos($confirm, 'pg_booking_confirm($booking'), 'paid before it is booked');
	pg_assert_contains("status = 'cancelled', slot_at = NULL", $widget, 'release keeps a row with an order');

	$bookings = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/bookings.php');
	pg_assert_contains('pg_booking_clear_expired_hold($staff_id, $start, $now);', $bookings);
	pg_assert_contains('pg_booking_clear_expired_hold($staff_id, $start, $now);', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/booking_manage.php'));

	$slots = (string) file_get_contents(PG_FUNCTIONS_DIR . '/booking_slots.php');
	pg_assert_contains('pg_booking_pay_return();', $slots);
	pg_assert_true(strpos($slots, 'validate_token_field();') < strpos($slots, 'pg_booking_pay_retry('), 'the payment post carries the form token');
	pg_assert_contains("'payment'", $slots);

	$pay = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/booking_deposit_pay.php');
	pg_assert_contains("pg_booking_confirm(\$booking, 'requested', \$now)", $pay, 'paid on arrival is a request');
	pg_assert_contains("'/booking_slots.php?mode='", $pay, 'a payment page comes back to booking_slots.php');
	pg_assert_contains('get_token_field()', $pay);

	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_booking.js');
	pg_assert_contains("answer.status === 'pay'", $script);
	pg_assert_contains('#pg-booking-payment', $script);
	pg_assert_contains("'#' . PG_BOOKING_PAY_FRAGMENT", $pay);
}
