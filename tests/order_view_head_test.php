<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order screen's head (includes/fn/order_view.php): the order,
 * payment and shipping pills worked out from the order row, its addresses
 * and their parcels; the title, the count of items and units, the grey line,
 * and the copy button the order number carries (includes/fn/output.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * A paid online order of 100.00 with nothing refunded.
 */
function order_view_head_test_order(array $over = array())
{
	return array_merge(array(
		'status' => 'complete',
		'total' => 10000,
		'refunded_amount' => 0,
		'payment_method' => 'Credit/Debit Card',
		'paid_at' => 1700000000,
		'erp_on_account' => 0,
	), $over);
}

/**
 * An address with one line of two units.
 */
function order_view_head_test_address(array $over = array())
{
	return array_merge(array(
		'ship_date' => '0000-00-00',
		'delivery_date' => '0000-00-00',
		'lines' => array(array('quantity' => 2, 'shipped_quantity' => 0, 'show_shipped_quantity' => 1)),
		'parcels' => array(),
	), $over);
}

function test_order_view_order_pill_follows_the_status()
{
	$today = '2026-11-22';
	$cases = array(
		'incomplete' => array('Incomplete', 'gray'),
		'complete' => array('Complete', 'teal'),
		'exported' => array('Exported', 'teal'),
		'cancelled' => array('Cancelled', 'rose'),
	);
	foreach ($cases as $status => $want) {
		$states = pg_order_view_states(order_view_head_test_order(array('status' => $status)), array(), $today);
		pg_assert_same($want, $states['order'], 'order pill of ' . $status);
	}
}

function test_order_view_payment_pill()
{
	$today = '2026-11-22';

	$paid = pg_order_view_states(order_view_head_test_order(), array(), $today);
	pg_assert_same(array('Paid', 'teal'), $paid['payment'], 'paid');

	$transfer = pg_order_view_states(order_view_head_test_order(array('payment_method' => 'Offline Payment', 'paid_at' => 0)), array(), $today);
	pg_assert_same(array('Awaiting bank transfer', 'amber'), $transfer['payment'], 'a bank transfer not yet arrived');

	$paypal = pg_order_view_states(order_view_head_test_order(array('payment_method' => 'PayPal Checkout', 'paid_at' => 0)), array(), $today);
	pg_assert_same(array('Payment pending on PayPal', 'amber'), $paypal['payment'], 'a PayPal payment left pending');

	$account_waiting = pg_order_view_states(order_view_head_test_order(array('payment_method' => 'Offline Payment', 'paid_at' => 0, 'erp_on_account' => 1)), array(), $today);
	pg_assert_same(array('On account', 'amber'), $account_waiting['payment'], 'on account, not yet paid: the awaiting badge text');

	$account = pg_order_view_states(order_view_head_test_order(array('erp_on_account' => 1)), array(), $today);
	pg_assert_same(array('Put on account', 'blue'), $account['payment'], 'put on account');

	$refunded = pg_order_view_states(order_view_head_test_order(array('refunded_amount' => 10000)), array(), $today);
	pg_assert_same(array('Refunded', 'teal'), $refunded['payment'], 'refund reaches the total');

	$partial = pg_order_view_states(order_view_head_test_order(array('refunded_amount' => 2500)), array(), $today);
	pg_assert_same(array('Partially refunded', 'amber'), $partial['payment'], 'refund above zero');

	$cancelled_refunded = pg_order_view_states(order_view_head_test_order(array('status' => 'cancelled', 'refunded_amount' => 10000)), array(), $today);
	pg_assert_same(array('Refunded', 'teal'), $cancelled_refunded['payment'], 'a cancelled order refunded in full');
}

function test_order_view_payment_pill_left_out()
{
	$today = '2026-11-22';

	$incomplete = pg_order_view_states(order_view_head_test_order(array('status' => 'incomplete')), array(), $today);
	pg_assert_same(null, $incomplete['payment'], 'no payment pill on an incomplete order');

	$free = pg_order_view_states(order_view_head_test_order(array('total' => 0)), array(), $today);
	pg_assert_same(null, $free['payment'], 'no payment pill for a total of zero');
}

function test_order_view_address_shipping_state()
{
	$today = '2026-11-22';

	pg_assert_same('none', pg_order_view_address_shipping(order_view_head_test_address(), $today), 'nothing done');

	pg_assert_same('delivered', pg_order_view_address_shipping(order_view_head_test_address(array('delivery_date' => '2026-11-22')), $today), 'delivered today');
	pg_assert_same('none', pg_order_view_address_shipping(order_view_head_test_address(array('delivery_date' => '2026-11-23')), $today), 'a delivery date to come is not delivered');
	pg_assert_same('delivered', pg_order_view_address_shipping(order_view_head_test_address(array('parcels' => array('delivered', 'delivered'))), $today), 'every parcel delivered');

	pg_assert_same('shipped', pg_order_view_address_shipping(order_view_head_test_address(array('parcels' => array('delivered', 'in_transit'))), $today), 'a parcel exists, one not delivered');
	pg_assert_same('shipped', pg_order_view_address_shipping(order_view_head_test_address(array('ship_date' => '2026-11-21')), $today), 'ship date before today');
	pg_assert_same('none', pg_order_view_address_shipping(order_view_head_test_address(array('ship_date' => '2026-12-01')), $today), 'a ship date to come');
	pg_assert_same('shipped', pg_order_view_address_shipping(order_view_head_test_address(array('lines' => array(
		array('quantity' => 2, 'shipped_quantity' => 2, 'show_shipped_quantity' => 1),
		array('quantity' => 1, 'shipped_quantity' => 1, 'show_shipped_quantity' => 1),
	))), $today), 'every shipped quantity full');

	pg_assert_same('partial', pg_order_view_address_shipping(order_view_head_test_address(array('lines' => array(
		array('quantity' => 2, 'shipped_quantity' => 1, 'show_shipped_quantity' => 1),
		array('quantity' => 1, 'shipped_quantity' => 0, 'show_shipped_quantity' => 1),
	))), $today), 'some shipped quantity above zero');

	pg_assert_same('none', pg_order_view_address_shipping(order_view_head_test_address(array('lines' => array(
		array('quantity' => 1, 'shipped_quantity' => 0, 'show_shipped_quantity' => 0),
	))), $today), 'a line with no shipped quantity does not count as shipped');
}

function test_order_view_shipping_pill_of_the_order()
{
	$today = '2026-11-22';
	$order = order_view_head_test_order();
	$delivered = order_view_head_test_address(array('delivery_date' => '2026-11-20'));
	$shipped = order_view_head_test_address(array('parcels' => array('in_transit')));
	$none = order_view_head_test_address();

	$states = pg_order_view_states($order, array(), $today);
	pg_assert_same(null, $states['shipping'], 'no address, no shipping pill');
	pg_assert_same(array(), $states['addresses'], 'no address pills');

	$states = pg_order_view_states($order, array(7 => $none), $today);
	pg_assert_same(array('Not shipped', 'gray'), $states['shipping'], 'not shipped');

	$states = pg_order_view_states($order, array(7 => $delivered, 8 => $delivered), $today);
	pg_assert_same(array('Delivered', 'teal'), $states['shipping'], 'all delivered');

	$states = pg_order_view_states($order, array(7 => $delivered, 8 => $shipped), $today);
	pg_assert_same(array('Shipped', 'blue'), $states['shipping'], 'all shipped, some delivered');

	$states = pg_order_view_states($order, array(7 => $shipped, 8 => $none), $today);
	pg_assert_same(array('Partially shipped', 'amber'), $states['shipping'], 'one of two begun');
	pg_assert_same(array('Shipped', 'blue'), $states['addresses'][7], 'the first address its own pill');
	pg_assert_same(array('Not shipped', 'gray'), $states['addresses'][8], 'the second address its own pill');
}

function test_order_view_cancelled_order_shipping_pill_only_when_shipped()
{
	$today = '2026-11-22';
	$order = order_view_head_test_order(array('status' => 'cancelled'));

	$states = pg_order_view_states($order, array(7 => order_view_head_test_address()), $today);
	pg_assert_same(null, $states['shipping'], 'nothing shipped: no pill');

	$states = pg_order_view_states($order, array(7 => order_view_head_test_address(array('ship_date' => '2026-11-01'))), $today);
	pg_assert_same(array('Shipped', 'blue'), $states['shipping'], 'shipped before it was cancelled');
}

function test_order_view_head_title()
{
	pg_assert_same('Order #1171', pg_order_view_head_title('1171', 9), 'the order number');
	pg_assert_same('Incomplete #428', pg_order_view_head_title('', 428), 'no number: the id');
	pg_assert_same('Incomplete #428', pg_order_view_head_title(null, '428'), 'null number');
}

function test_order_view_counts_text()
{
	pg_assert_same('2 items, 3 units', pg_order_view_counts_text(2, 3), 'plural');
	pg_assert_same('1 item, 1 unit', pg_order_view_counts_text(1, 1), 'singular');
	pg_assert_same('', pg_order_view_counts_text(0, 0), 'no lines, nothing');
}

function test_order_view_head_meta_line()
{
	$html = pg_order_view_head_meta(array('16 Apr 2020 17:41', 'Online', '<a href="edit_contact.php?id=4">Ada</a>', '', '2 items, 3 units'));
	pg_assert_same('<p class="pg-record-head-meta">16 Apr 2020 17:41 · Online · <a href="edit_contact.php?id=4">Ada</a> · 2 items, 3 units</p>', $html, 'parts joined, empty ones left out');
	pg_assert_same('', pg_order_view_head_meta(array('', ' ')), 'no part, no line');
}

function test_order_view_head_markup()
{
	$html = pg_order_view_head(array(
		'title' => 'Order #1171',
		'copy' => '1171',
		'copy_label' => 'Copy the order number',
		'pills' => array(array('Complete', 'teal'), null, array('Not shipped', 'gray')),
		'meta' => array('16 Apr 2020', 'R&D <x>'),
		'actions' => '<nav id="button_bar"></nav>',
	));

	pg_assert_contains('<div class="pg-record-head">', $html, 'the head');
	pg_assert_contains('<h2 class="pg-record-head-title" id="pg_record_head_title">Order #1171</h2>', $html, 'the title');
	pg_assert_contains('data-pg-copy="1171"', $html, 'the number is copied without #');
	pg_assert_contains('aria-label="Copy the order number"', $html, 'the copy button is named');
	pg_assert_contains('<span class="pg-pill pg-tone-teal">Complete</span>', $html, 'order pill');
	pg_assert_contains('<span class="pg-pill pg-tone-gray">Not shipped</span>', $html, 'shipping pill');
	pg_assert_same(2, substr_count($html, 'pg-pill '), 'a missing pill is left out');
	pg_assert_contains('16 Apr 2020 · R&D <x>', $html, 'the meta parts are markup the caller escaped');
	pg_assert_contains('<div class="pg-record-head-actions"><nav id="button_bar"></nav></div>', $html, 'the toolbar at the right');
}

function test_copy_button_markup()
{
	$html = pg_copy_button('A "1" & <b>', 'Copy the order number');

	pg_assert_contains('<button type="button" class="pg-icon-button pg-icon-button-sm" data-pg-copy="A &quot;1&quot; &amp; &lt;b&gt;"', $html, 'the text escaped in the attribute');
	pg_assert_contains('title="Copy the order number" aria-label="Copy the order number"', $html, 'named for a screen reader');
	pg_assert_contains('<i class="bi bi-copy" aria-hidden="true"></i>', $html, 'the icon');
	pg_assert_contains('role="status" aria-live="polite" data-copied="Copied"', $html, 'a polite status beside the button');
	pg_assert_contains('data-refused="The browser did not let the text be copied. Select it and copy it from here."', $html, 'the refusal text');
	pg_assert_same('', pg_copy_button('', 'Copy'), 'nothing to copy, no button');
}

function test_copy_helper_in_the_panel_bundle()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');

	pg_assert_contains('[data-pg-copy]', $js, 'one delegated listener for every copy button');
	pg_assert_contains('navigator.clipboard && window.isSecureContext', $js, 'clipboard API only in a secure context');
	pg_assert_contains('document.execCommand("copy")', $js, 'the fallback decides by execCommand');
}
