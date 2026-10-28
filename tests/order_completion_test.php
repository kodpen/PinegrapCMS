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
// pg_order_complete()'s body put where submit_order() calls it.
function order_completion_test_tail()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');
	$tail = substr($source, (int) strpos($source, 'pg_payment_checkout_driver('));
	$call = strpos($tail, 'pg_order_complete(');

	if ($call !== false) {
		$body = order_completion_test_function_text(PG_FUNCTIONS_DIR . '/includes/fn/order_completion.php', 'pg_order_complete');
		$tail = substr($tail, 0, $call) . $body . substr($tail, $call);
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
		'stock' => array('UPDATE products SET inventory_quantity = (inventory_quantity - ?)'),
		'stock announced' => array('pg_product_stock_changed('),
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
