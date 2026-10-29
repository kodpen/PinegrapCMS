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

	foreach (array('pg_order_complete', 'pg_order_complete_receipt', 'pg_order_complete_ship_dates', 'pg_order_complete_line_stock', 'pg_order_complete_line_reservation', 'pg_order_complete_line_membership', 'pg_order_complete_line_folder_access', 'pg_order_complete_line_gift_cards', 'pg_order_line_product_form_fields', 'pg_order_complete_line_form_update', 'pg_order_line_watcher', 'pg_order_complete_line_form_create', 'pg_order_form_insert_query', 'pg_order_complete_line_watcher', 'pg_order_complete_campaigns', 'pg_order_complete_line_comments', 'pg_order_complete_line_email_page', 'pg_order_complete_line_campaigns', 'pg_order_complete_private_access', 'pg_order_complete_rewards', 'pg_order_complete_contact_groups', 'pg_order_complete_abandoned_campaigns', 'pg_order_complete_visitor', 'pg_order_complete_announce', 'pg_order_complete_key_code', 'pg_order_complete_gift_cards', 'pg_order_complete_commission') as $name) {
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
		'order completed' => array("status = 'complete'", 'pg_order_complete_query('),
		'saved lines dropped' => array('pg_order_drop_saved_lines('),
		'order number locked' => array('LOCK TABLES next_order_number WRITE'),
		'order number advanced' => array('UPDATE next_order_number SET next_order_number = next_order_number + 1'),
		'order number unlocked' => array('UNLOCK TABLES'),
		'order number written' => array('SET order_number = ?'),
		'legal documents kept' => array('pg_legal_documents_store('),
		'order.created queued' => array("api_webhook_enqueue('order.created'"),
		'order SMS queued' => array('pg_sms_order_received('),
		'key code use' => array('"UPDATE key_codes'),
		'gift card redemption' => array('"UPDATE gift_cards'),
		'gift card error mail' => array("'subject' => 'Gift card error'"),
		'affiliate commission' => array('"INSERT INTO commissions ('),
		'recurring commission profiles' => array('"INSERT INTO recurring_commission_profiles ('),
		'receipt order id kept' => array("\$_SESSION['ecommerce']['completed_order_id'] ="),
		'receipt e-mail' => array('pg_legal_documents_attachments('),
		'address book' => array('"INSERT INTO address_book ('),
		'ship and delivery dates' => array("delivery_date = '", 'delivery_date = ?'),
		'order campaigns' => array("'action' => 'order_completed'"),
		'stock' => array('UPDATE products SET inventory_quantity = (inventory_quantity - ?)'),
		'stock announced' => array('pg_product_stock_changed('),
		'reservation spots' => array('"UPDATE remaining_reservation_spots'),
		'membership extended' => array('UPDATE contacts SET expiration_date = ? WHERE member_id = ?'),
		'folder access' => array('"INSERT INTO aclfolder ('),
		'gift cards issued' => array('"INSERT INTO gift_cards ('),
		'product form read' => array('FROM form_data'),
		'submitted form updated' => array('"UPDATE forms'),
		'submitted form created' => array('"INSERT INTO forms ('),
		'submitted form e-mails' => array("'action' => 'custom_form_submitted'"),
		'watcher added' => array('"INSERT INTO watchers ('),
		'comment added' => array('"INSERT INTO comments ('),
		'comment e-mails' => array('send_comment_email_to_administrators('),
		'page e-mailed' => array("'Product Information'"),
		'product campaigns' => array("'action' => 'product_ordered'"),
		'private access' => array('ECOMMERCE_PRIVATE_FOLDER_ID'),
		'reward points' => array('UPDATE user SET user_reward_points = ?'),
		'reward membership' => array('UPDATE contacts SET expiration_date = ? WHERE id = ?'),
		'reward e-mail' => array('ECOMMERCE_REWARD_PROGRAM_EMAIL_SUBJECT'),
		'contact groups' => array('"INSERT INTO contacts_contact_groups_xref ('),
		'abandoned campaigns cancelled' => array('"UPDATE email_campaigns'),
		'visitor record' => array('UPDATE visitors'),
		'activity log' => array("lang('Order was completed')"),
		'new_order notification' => array("'action' => 'new_order'"),
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

function test_order_completion_submit_order_prepares_line_fields_through_the_functions()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');

	foreach (array('pg_order_line_variable_fields', 'pg_order_line_product_form_fields', 'pg_order_complete_line_form_update') as $name) {
		pg_assert_same(1, substr_count($source, $name . '('), $name);
	}

	pg_assert_same(0, substr_count($source, "'name' => 'billing_salutation'"), 'no variable fields of its own');
	pg_assert_same(0, substr_count($source, "(product_submit_form_fields.action = 'update')"), 'no form update of its own');
}

function test_order_completion_line_variable_fields()
{
	$order = array(
		'billing_salutation' => 'Ms.', 'billing_first_name' => 'Ada', 'billing_last_name' => 'Lovelace',
		'billing_company' => 'Engines', 'billing_address_1' => '1 Road', 'billing_address_2' => 'Flat 2',
		'billing_city' => 'London', 'billing_state' => 'LDN', 'billing_zip_code' => 'N1',
		'billing_country' => 'GB', 'billing_phone_number' => '123', 'billing_fax_number' => '456',
		'billing_email_address' => 'ada@example.com', 'custom_field_1' => 'c1', 'custom_field_2' => 'c2',
		'po_number' => 'PO-7', 'total' => 123456, 'order_number' => 1001, 'order_date' => mktime(14, 5, 6, 11, 10, 2026),
	);

	$fields = pg_order_line_variable_fields($order, 3);
	$by_name = array();

	foreach ($fields as $field) {
		$by_name[$field['name']] = $field;
	}

	pg_assert_same(
		array('billing_salutation', 'billing_first_name', 'billing_last_name', 'billing_company', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state', 'billing_zip_code', 'billing_country', 'billing_phone_number', 'billing_fax_number', 'billing_email_address', 'custom_field_1', 'custom_field_2', 'po_number', 'total', 'order_number', 'order_date_and_time', 'quantity', 'quantity_plural_suffix', 'quantity_plural_suffix_es'),
		array_column($fields, 'name'),
		'the names in the order the checkout gave them'
	);
	pg_assert_same('Ada', $by_name['billing_first_name']['data'], 'from the order row');
	pg_assert_same('1234.56', $by_name['total']['data'], 'the total in major units, two decimals');
	pg_assert_same(array('name' => 'order_date_and_time', 'data' => '2026-11-10 14:05:06', 'type' => 'date and time'), $by_name['order_date_and_time'], 'the order date');
	pg_assert_same(3, $by_name['quantity']['data'], 'the quantity');
	pg_assert_same('s', $by_name['quantity_plural_suffix']['data'], 'plural');
	pg_assert_same('es', $by_name['quantity_plural_suffix_es']['data'], 'plural es');

	$one = pg_order_line_variable_fields($order, 1);
	pg_assert_same('', $one[20]['data'], 'one: no suffix');
	pg_assert_same('', $one[21]['data'], 'one: no es suffix');
}

function test_order_completion_product_form_fields()
{
	$items = array(
		array('quantity_number' => '1', 'form_field_id' => '5', 'data' => 'Red', 'name' => 'colour', 'type' => 'check box'),
		array('quantity_number' => '1', 'form_field_id' => '5', 'data' => 'Blue', 'name' => 'colour', 'type' => 'check box'),
		array('quantity_number' => '1', 'form_field_id' => '6', 'data' => 'Ada', 'name' => 'name', 'type' => 'text box'),
		array('quantity_number' => '2', 'form_field_id' => '6', 'data' => 'Bob', 'name' => 'name', 'type' => 'text box'),
	);

	pg_assert_same(
		array(
			1 => array(
				array('name' => 'colour', 'data' => 'Red, Blue', 'type' => 'check box'),
				array('name' => 'name', 'data' => 'Ada', 'type' => 'text box'),
			),
			2 => array(
				array('name' => 'name', 'data' => 'Bob', 'type' => 'text box'),
			),
		),
		pg_order_product_form_fields($items),
		'one list per unit, the values of a field joined by commas'
	);
	pg_assert_same(array(), pg_order_product_form_fields(array()), 'no product form');
}

function test_order_completion_product_form_number()
{
	$forms = array(1 => array(), 2 => array());

	pg_assert_same(1, pg_order_product_form_number(1, $forms), 'the first unit');
	pg_assert_same(2, pg_order_product_form_number(2, $forms), 'a unit with its own form');
	pg_assert_same(1, pg_order_product_form_number(3, $forms), 'a unit without one uses the first');
	pg_assert_same(0, pg_order_product_form_number(1, array()), 'no product form');
	pg_assert_same(2, pg_order_product_form_number(2, array(2 => array())), 'a later unit with its own form, the first without');
	pg_assert_same(0, pg_order_product_form_number(1, array(2 => array())), 'the first unit without a form');
}

function test_order_completion_submit_order_creates_forms_through_the_function()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');

	foreach (array('pg_order_line_watcher', 'pg_order_complete_line_form_create', 'pg_order_complete_line_watcher') as $name) {
		pg_assert_same(1, substr_count($source, $name . '('), $name);
	}

	pg_assert_same(0, substr_count($source, 'INSERT INTO forms'), 'no form creation of its own');
	pg_assert_same(0, substr_count($source, 'INSERT INTO watchers'), 'no watcher write of its own');
	pg_assert_same(0, substr_count($source, "'custom_form_submitted'"), 'no form campaigns of its own');
}

function test_order_completion_form_insert_query_reads_the_order_row()
{
	$query = pg_order_form_insert_query(12, '7', '31', 'ABC123', array(
		'tracking_code' => 'spring',
		'affiliate_code' => "aff'1",
		'http_referer' => 'https://example.com/a?b=1',
		'ip_address' => '3405803785',
	));

	pg_assert_same(
		"INSERT INTO forms (page_id, complete, user_id, contact_id, reference_code, tracking_code, affiliate_code, http_referer, ip_address, submitted_timestamp, last_modified_user_id, last_modified_timestamp) VALUES ('12', '1', '7', '31', 'ABC123', 'spring', 'aff\\'1', 'https://example.com/a?b=1', '3405803785', UNIX_TIMESTAMP(), '7', UNIX_TIMESTAMP())",
		str_replace('( ', '(', preg_replace('/\s+/', ' ', pg_db_bind($query['sql'], $query['params'], 'addslashes'))),
		'the origin from the order row, the IP as the number the order keeps'
	);

	$query = pg_order_form_insert_query(12, '', '31', 'ABC123', array());

	pg_assert_same(array('12', '', '31', 'ABC123', '', '', '', '0', ''), $query['params'], 'no order row: empty origin, IP 0');
}

function test_order_completion_form_submitter_addresses()
{
	pg_assert_same(array('buyer@example.com', 'watch@example.com'), pg_order_form_submitter_addresses('buyer@example.com', array('id' => '5', 'email_address' => 'watch@example.com')), 'the buyer, then the watcher');
	pg_assert_same(array('buyer@example.com'), pg_order_form_submitter_addresses('buyer@example.com', array()), 'no watcher');
	pg_assert_same(array('buyer@example.com'), pg_order_form_submitter_addresses('buyer@example.com', array('id' => '', 'email_address' => 'x@example.com')), 'a watcher not found');
}

function test_order_completion_form_admin_addresses()
{
	pg_assert_same(array('a@example.com', 'b@example.com', 'to@example.com'), pg_order_form_admin_addresses(array('a@example.com', 'b@example.com', 'a@example.com'), 'to@example.com'), 'each option address once, then the to address');
	pg_assert_same(array('to@example.com'), pg_order_form_admin_addresses(array('to@example.com'), 'to@example.com'), 'the to address once');
	pg_assert_same(array(), pg_order_form_admin_addresses(array(), ''), 'nobody');
}

function test_order_completion_submit_order_adds_comments_and_pages_through_the_functions()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');

	foreach (array('pg_order_complete_campaigns', 'pg_order_complete_line_comments', 'pg_order_complete_line_email_page', 'pg_order_complete_line_campaigns') as $name) {
		pg_assert_same(1, substr_count($source, $name . '('), $name);
	}

	pg_assert_same(0, substr_count($source, 'INSERT INTO comments'), 'no comment write of its own');
	pg_assert_same(0, substr_count($source, 'send_comment_email_to_'), 'no comment e-mails of its own');
	pg_assert_same(0, substr_count($source, "'Product Information'"), 'no e-mailed page of its own');
	pg_assert_same(0, substr_count($source, "'order_completed'"), 'no order campaigns of its own');
	pg_assert_same(0, substr_count($source, "'product_ordered'"), 'no line campaigns of its own');
}

function test_order_completion_line_unit_count()
{
	pg_assert_same(3, pg_order_line_unit_count('One Form per Quantity', 3), 'one per unit');
	pg_assert_same(1, pg_order_line_unit_count('One Form per Product', 3), 'one per line');
	pg_assert_same(1, pg_order_line_unit_count('', 5), 'one per line by default');
}

function test_order_completion_line_unit_form()
{
	$forms = array(1 => array('id' => 7, 'type' => 'created'), 3 => array('id' => 9, 'type' => 'updated'));

	pg_assert_same(7, pg_order_line_unit_form(1, $forms), 'the first unit\'s own');
	pg_assert_same(7, pg_order_line_unit_form(2, $forms), 'else the first unit\'s');
	pg_assert_same(9, pg_order_line_unit_form(3, $forms), 'the unit\'s own');
	pg_assert_same(0, pg_order_line_unit_form(2, array(3 => array('id' => 9, 'type' => 'updated'))), 'none');
}

function test_order_completion_line_comment_target()
{
	pg_assert_same(
		array('item_id' => 9, 'item_type' => 'submitted_form', 'updated' => true),
		pg_order_line_comment_target('form item view', array('id' => 9, 'type' => 'updated'), true),
		'an updated form on a form item view');
	pg_assert_same(null, pg_order_line_comment_target('form item view', array('id' => 7, 'type' => 'created'), true), 'only for an update: a created form adds none');
	pg_assert_same(null, pg_order_line_comment_target('form item view', null, true), 'only for an update: no form adds none');
	pg_assert_same(
		array('item_id' => 7, 'item_type' => 'submitted_form', 'updated' => false),
		pg_order_line_comment_target('form item view', array('id' => 7, 'type' => 'created'), false),
		'a created form');
	pg_assert_same(
		array('item_id' => 0, 'item_type' => '', 'updated' => true),
		pg_order_line_comment_target('standard', array('id' => 9, 'type' => 'updated'), false),
		'another page type names no item');
	pg_assert_same(
		array('item_id' => 0, 'item_type' => '', 'updated' => false),
		pg_order_line_comment_target('form item view', null, false),
		'no form names no item');
}

function test_order_completion_email_page_bcc()
{
	pg_assert_same('a@example.com', pg_order_email_page_bcc('a@example.com', 'shop@example.com'), 'the product\'s');
	pg_assert_same('shop@example.com', pg_order_email_page_bcc('', 'shop@example.com'), 'else the store\'s');
}

function test_order_completion_submit_order_finishes_through_the_stage_functions()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');

	foreach (array('pg_order_line_reward_points', 'pg_order_complete_private_access', 'pg_order_complete_rewards', 'pg_order_complete_contact_groups', 'pg_order_complete_abandoned_campaigns', 'pg_order_complete_visitor', 'pg_order_complete_announce', 'pg_order_complete_key_code', 'pg_order_complete_gift_cards', 'pg_order_complete_commission') as $name) {
		pg_assert_same(1, substr_count($source, $name . '('), $name);
	}

	pg_assert_same(0, substr_count($source, 'aclfolder'), 'no folder access write of its own');
	pg_assert_same(0, substr_count($source, 'user_reward_points'), 'no reward points of its own');
	pg_assert_same(0, substr_count($source, 'UPDATE email_campaigns'), 'no campaign cancel of its own');
	pg_assert_same(0, substr_count($source, 'UPDATE visitors'), 'no visitor write of its own');
	pg_assert_same(0, substr_count($source, "'new_order'"), 'no notification of its own');
}

function test_order_completion_reward_membership_until()
{
	pg_assert_same('0000-00-00', pg_order_reward_membership_until('2026-12-01', 0, '2026-11-10'), 'no days: lifetime');
	pg_assert_same('2026-12-31', pg_order_reward_membership_until('2026-12-01', 30, '2026-11-10'), 'a future end moves on');
	pg_assert_same('2026-12-10', pg_order_reward_membership_until('2026-11-01', 30, '2026-11-10'), 'a past end starts from today');
	pg_assert_same('2026-12-10', pg_order_reward_membership_until('', 30, '2026-11-10'), 'no end yet starts from today');
	pg_assert_same('2026-12-10', pg_order_reward_membership_until('0000-00-00', 30, '2026-11-10'), 'a lifetime membership gets the days from today, as before');
}

function test_order_completion_line_reward_points()
{
	pg_assert_same(15, pg_order_line_reward_points(array('reward_points' => '5', 'quantity' => '3'), true), 'points times quantity');
	pg_assert_same(0, pg_order_line_reward_points(array('reward_points' => '5', 'quantity' => '3'), false), 'the program is off');
	pg_assert_same(0, pg_order_line_reward_points(array('reward_points' => '0', 'quantity' => '3'), true), 'a product without points');
}

function test_order_completion_writes_before_the_lock_moved_behind_it()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');
	$lock = strpos($source, 'pg_order_complete(');

	foreach (array('pg_order_complete_key_code', 'pg_order_complete_gift_cards', 'pg_order_complete_commission') as $name) {
		pg_assert_same(1, substr_count($source, $name . '('), $name);
		pg_assert_true(strpos($source, $name . '(') > $lock, $name . ' after the lock');
	}

	foreach (array('pg_order_key_code', 'pg_order_affiliate_rate', 'pg_order_commission') as $name) {
		pg_assert_true(strpos($source, $name . '(') < $lock, $name . ' settles a value before the lock');
	}

	pg_assert_same(0, substr_count($source, 'UPDATE key_codes'), 'no key code write of its own');
	pg_assert_same(0, substr_count($source, 'UPDATE gift_cards'), 'no redemption of its own');
	pg_assert_same(0, substr_count($source, "send_givex_request('redemption'"), 'no Givex redemption of its own');
	pg_assert_same(0, substr_count($source, "'Gift card error'"), 'no gift card error mail of its own');
	pg_assert_same(0, substr_count($source, 'INSERT INTO commissions'), 'no commission of its own');
	pg_assert_same(0, substr_count($source, 'INSERT INTO recurring_commission_profiles'), 'no recurring commission of its own');

	// Auto-registration stays with the visitor's request: it signs the
	// visitor in and its user id is a value of the completing UPDATE.
	pg_assert_true(strpos($source, 'create_member_user(') < $lock, 'auto-registration before the lock');
}

function test_order_completion_commission_rate()
{
	pg_assert_same(5, pg_order_commission_rate(array('commission_rate_limit' => 5), 10), 'a lower limit');
	pg_assert_same(10, pg_order_commission_rate(array('commission_rate_limit' => 15), 10), 'a higher limit: the affiliate\'s');
	pg_assert_same(10, pg_order_commission_rate(array('commission_rate_limit' => 0), 10), 'no limit');
}

function test_order_completion_commission()
{
	$lines = array(
		array('commissionable' => '1', 'commission_rate_limit' => '0', 'quantity' => '2', 'price' => '1000'),
		array('commissionable' => '1', 'commission_rate_limit' => '5', 'quantity' => '1', 'price' => '3000'),
		array('commissionable' => '0', 'commission_rate_limit' => '0', 'quantity' => '1', 'price' => '9999'),
	);

	// 2 x 1000 x 10% + 3000 x 5% = 200 + 150.
	pg_assert_same(350.0, pg_order_commission($lines, 10, 0, 14999, 0, 14999), 'per line rate, a line not commissionable left out');
	// A 10% order discount: 315.
	pg_assert_same(315.0, pg_order_commission($lines, 10, 1500, 15000, 0, 13500), 'less the discount\'s share');
	// A gift card paying a quarter: 350 x 0.75 = 262.5, rounded.
	pg_assert_same(263.0, pg_order_commission($lines, 10, 0, 14999, 3750, 11250), 'less the gift cards\' share, rounded');
	pg_assert_same(0.0, pg_order_commission(array(), 10, 0, 0, 0, 0), 'no lines');
}

function test_order_completion_recurring_commission_start()
{
	$now = mktime(10, 0, 0, 11, 10, 2026);

	pg_assert_same('2026-12-10', pg_order_recurring_commission_start('Monthly', '2026-11-10', $now), 'paid with the order: in 30 days');
	pg_assert_same('2027-11-10', pg_order_recurring_commission_start('Yearly', '2026-01-01', $now), 'paid with the order: in 365 days');
	pg_assert_same('2026-12-01', pg_order_recurring_commission_start('Monthly', '2026-12-01', $now), 'a later start: its own');
}

function test_order_completion_gift_card_error_message()
{
	$message = pg_order_gift_card_error_message('1001', 5000, 2000, array(
		array('gift_card_code' => 'ABCDEFGHIJKL', 'amount' => 3000, 'error_message' => 'Gift card was deleted or balance of card changed right when order was submitted.', 'givex' => false),
		array('gift_card_code' => '603628123456789012', 'amount' => 1000, 'curl_errno' => 7, 'curl_error' => 'refused', 'givex' => true),
		array('gift_card_code' => '603628123456789013', 'amount' => 1000, 'error_message' => 'Card expired', 'givex' => true),
	));

	pg_assert_contains('Order (1001) was accepted however', $message, 'names the order');
	pg_assert_contains('Gift card: ' . protect_gift_card_code('ABCDEFGHIJKL') . '. Attempted redemption amount: ', $message, 'a card of the store, masked');
	pg_assert_contains(' Gift card was deleted or balance of card changed right when order was submitted.', $message, 'its error');
	pg_assert_contains('Gift card: ' . protect_givex_gift_card_code('603628123456789012') . '.', $message, 'a Givex card, masked');
	pg_assert_contains(' cURL Error Number: 7. cURL Error Message: refused.', $message, 'a connection error');
	pg_assert_contains(' Givex error: Card expired.', $message, 'a Givex error');
	pg_assert_same(false, strpos($message, 'ABCDEFGHIJKL'), 'never the whole code');
}
