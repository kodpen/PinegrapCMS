<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for booking deposits (includes/fn/booking_deposits.php): the
 * deposit boxes of a service, what a booking owes, the deposit order's
 * columns, the once-only paid statement gated on the order's paid_at and
 * the event it announces, and the wiring to the order's completion.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A deposit is a part of a price and names its product.
function test_booking_deposit_clean()
{
	$ok = pg_booking_deposit_clean(5000, '12', 35000);
	pg_assert_same('', $ok['error']);
	pg_assert_same(array('deposit' => 5000, 'deposit_product_id' => 12), $ok['values']);

	pg_assert_same('', pg_booking_deposit_clean(35000, 12, 35000)['error'], 'the whole price may be asked');
	pg_assert_same('', pg_booking_deposit_clean(0, 0, 0)['error'], 'no deposit, no product: nothing to check');
	pg_assert_same('', pg_booking_deposit_clean(0, 12, 35000)['error'], 'a product with no deposit asks for nothing');
	pg_assert_same('deposit', pg_booking_deposit_clean(36000, 12, 35000)['error'], 'more than the price');
	pg_assert_same('deposit', pg_booking_deposit_clean(1000, 12, 0)['error'], 'a free service asks for no deposit');
	pg_assert_same('deposit', pg_booking_deposit_clean(null, 12, 35000)['error'], 'a figure too large');
	pg_assert_same('deposit_product', pg_booking_deposit_clean(1000, '0', 35000)['error']);
	pg_assert_same(0, pg_booking_deposit_clean(1000, array(4), 35000)['values']['deposit_product_id']);
}

// The service row carries the deposit only when its boxes were drawn.
function test_booking_deposit_service_row()
{
	$row = array('name' => 'Haircut', 'length' => '45', 'price' => '350,00');

	$without = pg_booking_settings_service_clean($row);
	pg_assert_false(array_key_exists('deposit', $without['values']), 'no boxes: the stored deposit stays');

	$with = pg_booking_settings_service_clean($row + array('deposit' => '50,00', 'deposit_product' => '7'));
	pg_assert_same('', $with['error']);
	pg_assert_same(5000, $with['values']['deposit']);
	pg_assert_same(7, $with['values']['deposit_product_id']);

	pg_assert_same('deposit', pg_booking_settings_service_clean($row + array('deposit' => '400', 'deposit_product' => '7'))['error']);
	pg_assert_same('deposit_product', pg_booking_settings_service_clean($row + array('deposit' => '50', 'deposit_product' => '0'))['error']);
	pg_assert_same('name', pg_booking_settings_service_clean(array('name' => '', 'length' => '45', 'deposit' => '400'))['error'], 'the first wrong box is named');
}

// A service owes a deposit only with an amount, a product and a price.
function test_booking_deposit_due()
{
	pg_assert_same(array('amount' => 5000, 'product_id' => 7), pg_booking_deposit_due(array('price' => '35000', 'deposit' => '5000', 'deposit_product_id' => '7')));
	pg_assert_same(null, pg_booking_deposit_due(array('price' => 35000, 'deposit' => 0, 'deposit_product_id' => 7)));
	pg_assert_same(null, pg_booking_deposit_due(array('price' => 35000, 'deposit' => 5000, 'deposit_product_id' => 0)));
	pg_assert_same(null, pg_booking_deposit_due(array('price' => 0, 'deposit' => 5000, 'deposit_product_id' => 7)), 'the price was cleared later');
	pg_assert_same(null, pg_booking_deposit_due(array('price' => 4000, 'deposit' => 5000, 'deposit_product_id' => 7)), 'the price was lowered under the deposit');
	pg_assert_same(null, pg_booking_deposit_due(array()), 'a row before the upgrade');
	pg_assert_same(null, pg_booking_deposit_due(null));
}

// The deposit order is the fee order's shape, billed to the booking's customer.
function test_booking_deposit_order_columns()
{
	pg_assert_same(array('first_name' => 'Ayşe Nur', 'last_name' => 'Yılmaz'), pg_booking_name_split("  Ayşe  Nur\tYılmaz "));
	pg_assert_same(array('first_name' => 'Cher', 'last_name' => ''), pg_booking_name_split('Cher'));

	$booking = array('name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'phone' => '+44 20', 'user_id' => '9');
	$columns = pg_booking_deposit_order_columns($booking, array('amount' => 5000, 'product_id' => 7), 'Deposit', 'TRY', true);

	pg_assert_same('incomplete', $columns['order']['status']);
	pg_assert_same(9, $columns['order']['user_id']);
	pg_assert_same('Ada', $columns['order']['billing_first_name']);
	pg_assert_same('Lovelace', $columns['order']['billing_last_name']);
	pg_assert_same('ada@example.com', $columns['order']['billing_email_address']);
	pg_assert_same('+44 20', $columns['order']['billing_phone_number']);
	pg_assert_same(5000, $columns['order']['total']);
	pg_assert_same(1, $columns['order']['offline_payment_allowed'], 'paid on arrival when the store allows it');
	pg_assert_same(7, $columns['item']['product_id']);
	pg_assert_same(5000, $columns['item']['price']);

	pg_assert_same(0, pg_booking_deposit_order_columns($booking, array('amount' => 5000, 'product_id' => 7), 'Deposit', 'TRY', false)['order']['offline_payment_allowed']);
	pg_assert_same(null, pg_booking_deposit_order_columns($booking, null, 'Deposit', 'TRY', true));
}

// Paid once, only when the order is paid; a live hold confirms, a lapsed one waits for staff.
function test_booking_deposit_mark_paid_query()
{
	$query = pg_booking_deposit_mark_paid_query(31, 1700000000);

	pg_assert_contains('INNER JOIN orders ON orders.id = bookings.order_id', $query['sql']);
	pg_assert_contains('bookings.paid_at = 0 AND orders.paid_at > 0', $query['sql'], 'an offline completion marks nothing');
	pg_assert_contains("(bookings.hold_until = 0 OR bookings.hold_until >= ?) THEN 'confirmed'", $query['sql']);
	pg_assert_contains("WHEN bookings.status = 'held' THEN 'requested'", $query['sql']);
	pg_assert_contains('ELSE bookings.status END', $query['sql'], 'a cancelled booking only records the payment');
	pg_assert_same(array(1700000000, 1700000000, 1700000000, 31), $query['params']);

	pg_assert_same(null, pg_booking_deposit_mark_paid_query(0, 1700000000));
	pg_assert_same(1, pg_booking_deposit_mark_paid_query(5, 0)['params'][1], 'a stamp is never 0, which reads as unpaid');

	pg_assert_same('created', pg_booking_deposit_event('held', 'confirmed'));
	pg_assert_same('created', pg_booking_deposit_event('held', 'requested'));
	pg_assert_same('confirmed', pg_booking_deposit_event('requested', 'confirmed'));
	pg_assert_same('', pg_booking_deposit_event('cancelled', 'cancelled'));
	pg_assert_same('', pg_booking_deposit_event('confirmed', 'confirmed'));
}

// The completion and Payment Received mark the booking as they mark a form record.
function test_booking_deposit_wiring()
{
	$completion = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/order_completion.php');
	pg_assert_contains("pg_form_payment_mark_paid(\$order_id);\n        pg_booking_deposit_mark_paid(\$order_id);", $completion);

	$ecommerce = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/ecommerce.php');
	pg_assert_contains("pg_form_payment_mark_paid(\$order_id);\n    pg_booking_deposit_mark_paid(\$order_id);", $ecommerce);

	$migration = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	pg_assert_contains('upgrade_2026_11_0_booking_deposit();         // 11.0.169', $migration);
	pg_assert_contains("install_add_index('bookings', 'idx_order_id', \"INDEX idx_order_id (order_id)\");", $migration);
}
