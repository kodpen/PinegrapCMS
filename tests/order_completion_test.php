<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order completion function (includes/fn/order_completion.php):
 * the UPDATE that makes a cart an order only while it is still incomplete
 * (the once-only lock), its columns and values, and the writes that follow
 * it. The tail of submit_order() is pinned as one sequence of writes, read
 * from the source with pg_order_complete()'s body spliced in where it is
 * called, so moving a write into the function keeps its place.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The body of a named function in a file, from its signature to the closing
// brace at the start of a line; '' when it is not there.
function order_completion_test_function_text($path, $name)
{
	if (!is_file($path)) {
		return '';
	}

	$source = file_get_contents($path);
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n}\n", $start);

	return substr($source, $start, ($end === false ? strlen($source) : $end + 3) - $start);
}

// submit_order() from the payment driver branch to the end of the file, with
// the body of each completion function put where submit_order() calls it.
function order_completion_test_tail()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');
	$tail = substr($source, (int) strpos($source, 'pg_payment_checkout_driver('));
	$module = PG_FUNCTIONS_DIR . '/includes/fn/order_completion.php';

	foreach (array('pg_order_complete', 'pg_order_complete_receipt', 'pg_order_complete_ship_dates', 'pg_order_complete_line_stock', 'pg_order_complete_line_reservation', 'pg_order_complete_line_membership', 'pg_order_complete_line_folder_access', 'pg_order_complete_line_gift_cards') as $name) {
		$call = strpos($tail, $name . '(');

		if ($call !== false) {
			$tail = substr($tail, 0, $call) . order_completion_test_function_text($module, $name) . substr($tail, $call);
		}
	}

	return $tail;
}

function order_completion_test_bind($query)
{
	return ($query === null) ? null : pg_db_bind($query['sql'], $query['params'], 'addslashes');
}

function test_order_completion_tail_writes_in_order()
{
	$tail = order_completion_test_tail();

	// Each step: one or more texts, the first found after the previous step
	// counts. The completing UPDATE is written inline today and built by
	// pg_order_complete_query() once it moved.
	$steps = array(
		'key code use' => array('"UPDATE key_codes'),
		'gift card redemption' => array('"UPDATE gift_cards'),
		'affiliate commission' => array('"INSERT INTO commissions ('),
		'order completed' => array("status = 'complete'", 'pg_order_complete_query('),
		'saved lines dropped' => array('pg_order_drop_saved_lines('),
		'order number locked' => array('LOCK TABLES next_order_number WRITE'),
		'order number advanced' => array('UPDATE next_order_number SET next_order_number = next_order_number + 1'),
		'order number unlocked' => array('UNLOCK TABLES'),
		'order number written' => array('SET order_number = ?'),
		'legal documents kept' => array('pg_legal_documents_store('),
		'order.created queued' => array("api_webhook_enqueue('order.created'"),
		'order SMS queued' => array('pg_sms_order_received('),
		'gift card error mail' => array("'subject' => 'Gift card error'"),
		'receipt order id kept' => array("\$_SESSION['ecommerce']['completed_order_id'] ="),
		'receipt e-mail' => array('pg_legal_documents_attachments('),
		'address book' => array('"INSERT INTO address_book ('),
		'ship and delivery dates' => array("delivery_date = '", 'delivery_date = ?'),
		'stock' => array('UPDATE products SET inventory_quantity = (inventory_quantity - ?)'),
		'stock announced' => array('pg_product_stock_changed('),
		'reservation spots' => array('"UPDATE remaining_reservation_spots'),
		'membership extended' => array('UPDATE contacts SET expiration_date = ? WHERE member_id = ?'),
		'folder access' => array('"INSERT INTO aclfolder ('),
		'gift cards issued' => array('"INSERT INTO gift_cards ('),
		'reward points' => array('UPDATE user SET user_reward_points = ?'),
		'contact groups' => array('"INSERT INTO contacts_contact_groups_xref ('),
		'abandoned campaigns cancelled' => array('"UPDATE email_campaigns'),
		'visitor record' => array('UPDATE visitors'),
		'activity log' => array("lang('Order was completed')"),
		'ERP invoice' => array('pg_erp_auto_invoice('),
		'cart emptied' => array("unset(\$_SESSION['ecommerce']['order_id']);"),
	);

	$at = 0;

	foreach ($steps as $label => $texts) {
		$found = false;

		foreach ($texts as $text) {
			$position = strpos($tail, $text, $at);

			if (($position !== false) && (($found === false) || ($position < $found))) {
				$found = $position;
			}
		}

		pg_assert_true($found !== false, $label . ' after the previous step');

		if ($found !== false) {
			$at = $found + 1;
		}
	}
}

function test_order_completion_number_written_once()
{
	$tail = order_completion_test_tail();

	pg_assert_same(1, substr_count($tail, 'LOCK TABLES next_order_number WRITE'), 'one order number per completion');
	pg_assert_same(1, substr_count($tail, "api_webhook_enqueue('order.created'"), 'one order.created');
	pg_assert_same(1, substr_count($tail, 'pg_sms_order_received('), 'one order SMS');
}

function test_order_completion_submit_order_calls_the_function()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');

	pg_assert_same(1, substr_count($source, 'pg_order_complete('), 'submit_order() completes through the function');
	pg_assert_same(0, substr_count($source, "status = 'complete',"), 'no completing UPDATE of its own');
	pg_assert_same(0, substr_count($source, 'next_order_number'), 'no order number of its own');
	pg_assert_true(function_exists('pg_order_complete'), 'the function is loaded with functions.php');
}

function test_order_completion_function_reads_no_session_or_form()
{
	$text = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/order_completion.php');
	$code = '';

	foreach (token_get_all((string) $text) as $token) {
		if (!is_array($token) || !in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) {
			$code .= is_array($token) ? $token[1] : $token;
		}
	}

	foreach (array('$_SESSION', '$_POST', '$_GET', '$_REQUEST', '$liveform') as $source) {
		pg_assert_same(false, strpos($code, $source), 'no ' . $source);
	}
}

function test_order_completion_query_locks_on_incomplete()
{
	$sql = order_completion_test_bind(pg_order_complete_query(42, array(
		'payment_method' => 'Credit/Debit Card',
		'total' => 12345,
		'transaction_id' => "pi_3a'b",
		'ip_address' => '203.0.113.9',
	)));

	pg_assert_same(
		"UPDATE orders SET payment_method = 'Credit/Debit Card', total = '12345', transaction_id = 'pi_3a\\'b', ip_address = IFNULL(INET_ATON('203.0.113.9'), 0), status = 'complete' WHERE id = 42 AND status = 'incomplete'",
		$sql,
		'the columns in the given order, then the status, only while incomplete'
	);
}

function test_order_completion_query_quotes_values_as_before()
{
	// The inline UPDATE quoted every value; a figure is written the same way.
	$sql = order_completion_test_bind(pg_order_complete_query(7, array('subtotal' => 1999, 'discount' => 0, 'commission' => 12.5)));

	pg_assert_contains("subtotal = '1999', discount = '0', commission = '12.5'", (string) $sql, 'figures as quoted text');
}

function test_order_completion_query_refuses_unknown_column()
{
	pg_assert_same(null, pg_order_complete_query(7, array('status' => 'exported')), 'the status is the function\'s own');
	pg_assert_same(null, pg_order_complete_query(7, array('total; DROP' => 1)), 'not a column');
	pg_assert_same(null, pg_order_complete_query(0, array('total' => 1)), 'no order');
	pg_assert_same(null, pg_order_complete_query(7, array('total' => array(1))), 'one value per column');
}

function test_order_completion_query_without_values()
{
	pg_assert_same(
		"UPDATE orders SET status = 'complete' WHERE id = 9 AND status = 'incomplete'",
		order_completion_test_bind(pg_order_complete_query(9, array())),
		'an order the payment completes keeps its figures'
	);
}

function test_order_completion_columns_cover_the_checkout()
{
	// Every column the checkout's completing UPDATE wrote.
	$checkout = array(
		'payment_method', 'erp_on_account', 'erp_account_id',
		'card_type', 'card_number', 'expiration_month', 'expiration_year', 'cardholder', 'card_verification_number',
		'subtotal', 'discount', 'tax', 'shipping', 'gift_card_discount', 'surcharge', 'total',
		'payment_installment', 'installment_charges', 'commission', 'order_date', 'user_id', 'special_offer_code',
		'last_modified_timestamp', 'transaction_id', 'paid_at', 'authorization_code', 'tracking_code',
		'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'affiliate_code', 'ip_address',
	);

	pg_assert_same(array(), array_values(array_diff($checkout, pg_order_complete_columns())), 'none missing');
	pg_assert_false(in_array('status', pg_order_complete_columns(), true), 'not the status');
	pg_assert_false(in_array('order_number', pg_order_complete_columns(), true), 'not the number');
}

function test_order_completion_submit_order_calls_the_stage_functions()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');

	pg_assert_same(1, substr_count($source, 'pg_order_complete_receipt('), 'the receipt e-mail');
	pg_assert_same(1, substr_count($source, 'pg_order_complete_ship_dates('), 'the ship dates');
	pg_assert_same(0, substr_count($source, 'INSERT INTO address_book'), 'no address book write of its own');
	pg_assert_same(0, substr_count($source, 'get_order_receipt_in_plain_text('), 'no receipt of its own');
}

function test_order_completion_receipt_bcc_list()
{
	pg_assert_same(
		array('shop@example.com', 'maker@example.com', 'other@example.com'),
		pg_order_receipt_bcc_list('shop@example.com', array('maker@example.com', '', 'shop@example.com', 'not an address', 'maker@example.com', 'other@example.com')),
		'the store first, then each valid product address once, in line order'
	);
	pg_assert_same(array('maker@example.com'), pg_order_receipt_bcc_list('', array('maker@example.com')), 'no store address');
	pg_assert_same(array(), pg_order_receipt_bcc_list('', array()), 'nobody');
}

function test_order_completion_transit_date_backwards_from_arrival()
{
	// Arrival Thursday 2026-12-24, 2 transit days, no weekend transit: the
	// arrival day counts, then each counted day before it.
	pg_assert_same('2026-12-22', pg_order_transit_date('2026-12-24', 2, -1, false, false, array()), 'Thu, Wed, Tue');
	// Arrival Monday: the weekend is skipped.
	pg_assert_same('2026-12-03', pg_order_transit_date('2026-12-07', 2, -1, false, false, array()), 'Mon, Fri, Thu');
	pg_assert_same('2026-12-05', pg_order_transit_date('2026-12-07', 2, -1, true, true, array()), 'Mon, Sun, Sat');
	pg_assert_same('2026-12-02', pg_order_transit_date('2026-12-07', 2, -1, false, false, array('2026-12-04')), 'an excluded date is skipped');
}

function test_order_completion_transit_date_forwards_from_ship_date()
{
	pg_assert_same('2026-12-08', pg_order_transit_date('2026-12-04', 2, 1, false, false, array()), 'Fri, Mon, Tue');
	pg_assert_same('2026-12-04', pg_order_transit_date('2026-12-04', 0, 1, false, false, array()), 'no transit days');
	pg_assert_same('2026-12-09', pg_order_transit_date('2026-12-04', 2, 1, false, false, array('2026-12-08')), 'an excluded date is skipped');
}

function test_order_completion_ship_date_adjustment()
{
	pg_assert_same('2026-12-01', pg_order_ship_date_adjusted('2026-12-03', -2, '2026-11-20'), 'earlier');
	pg_assert_same('2026-12-05', pg_order_ship_date_adjusted('2026-12-03', 2, '2026-11-20'), 'later');
	pg_assert_same('2026-11-20', pg_order_ship_date_adjusted('2026-11-21', -3, '2026-11-20'), 'never before the order date');
	pg_assert_same('2026-12-03', pg_order_ship_date_adjusted('2026-12-03', 0, '2026-11-20'), 'no adjustment');
}

function test_order_completion_submit_order_calls_the_line_functions()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');

	foreach (array('pg_order_complete_line_stock', 'pg_order_complete_line_reservation', 'pg_order_complete_line_membership', 'pg_order_complete_line_folder_access') as $name) {
		pg_assert_same(1, substr_count($source, $name . '('), $name);
	}

	pg_assert_same(0, substr_count($source, 'remaining_reservation_spots'), 'no reservation write of its own');
	pg_assert_same(0, substr_count($source, 'UPDATE products SET inventory_quantity'), 'no stock write of its own');
}

function test_order_completion_extended_until()
{
	pg_assert_same('2027-01-30', pg_order_extended_until('2026-12-01', 30, 2, '2026-11-10'), 'a future end moves on by days x quantity');
	pg_assert_same('2026-11-20', pg_order_extended_until('2026-11-01', 10, 1, '2026-11-10'), 'a past end starts from today');
	pg_assert_same('2026-11-20', pg_order_extended_until('2026-11-10', 10, 1, '2026-11-10'), 'ending today starts from today');
	pg_assert_same('2026-11-20', pg_order_extended_until('', 10, 1, '2026-11-10'), 'no end yet starts from today');
}

function test_order_completion_folder_access_extends()
{
	pg_assert_true(pg_order_folder_access_extends('', ''), 'no access yet');
	pg_assert_true(pg_order_folder_access_extends('1', '2026-12-01'), 'access that ends');
	pg_assert_false(pg_order_folder_access_extends('1', '0000-00-00'), 'access for ever stays');
	pg_assert_false(pg_order_folder_access_extends('2', '2026-12-01'), 'edit rights stay');
}

function test_order_completion_submit_order_issues_gift_cards_through_the_function()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');

	pg_assert_same(1, substr_count($source, 'pg_order_complete_line_gift_cards('), 'one call in the product loop');
	pg_assert_same(0, substr_count($source, 'INSERT INTO gift_cards'), 'no gift card write of its own');
	pg_assert_same(0, substr_count($source, "'gift_card_ordered'"), 'no gift card e-mail of its own');
}

function test_order_completion_gift_card_count()
{
	pg_assert_same(3, pg_order_gift_card_count(3), 'one card per unit');
	pg_assert_same(100, pg_order_gift_card_count(100), 'up to 100');
	pg_assert_same(100, pg_order_gift_card_count(250), 'never more than 100');
	pg_assert_same(0, pg_order_gift_card_count(0), 'none');
}

function test_order_completion_gift_card_expiration()
{
	$today = mktime(10, 0, 0, 11, 10, 2026);

	pg_assert_same('', pg_order_gift_card_expiration('0000-00-00', 0, $today), 'no validity days: no end');
	pg_assert_same('', pg_order_gift_card_expiration('2026-12-01', 0, $today), 'no validity days, a delivery date: no end');
	pg_assert_same('2026-12-10', pg_order_gift_card_expiration('0000-00-00', 30, $today), 'sent now: from today');
	pg_assert_same('2026-12-31', pg_order_gift_card_expiration('2026-12-01', 30, $today), 'sent later: from the delivery date');
}

function test_order_completion_gift_card_send_time()
{
	pg_assert_same('', pg_order_gift_card_send_time('0000-00-00'), 'no delivery date: sent at once');
	pg_assert_same('2026-12-01 12:00:00', pg_order_gift_card_send_time('2026-12-01'), 'noon of the delivery date');
}
