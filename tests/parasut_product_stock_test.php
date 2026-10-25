<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the stock a Paraşüt product card opens with and the stock step
 * of the order screen's E-Shipment (includes/fn/parasut.php): a card opened
 * for a document starts from the stock before that document when the sale
 * behind it already took the goods out of Pinegrap's count; E-Shipment
 * makes each Paraşüt product ready once for the order's whole quantity of
 * it; a line whose product was deleted from the catalogue is refused, not
 * left off the waybill. Paraşüt's answers are the shapes recorded on the
 * demo company (a card opened with initial_stock_count reads that
 * stock_count and has_stock_movements false).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/parasut.php');

/**
 * The source of one function of a product file, from its declaration to the
 * next one.
 *
 * @param string $file      Path under pinegrap/
 * @param string $function
 * @return string  '' when the function is not found
 */
function parasut_product_stock_test_source($file, $function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, 'function ' . $function . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", (int) $start + 1);

	return ($end === false) ? substr($source, (int) $start) : substr($source, (int) $start, $end - (int) $start);
}

/**
 * Asserts that the pieces stand in $body in the order given.
 *
 * @param string $body
 * @param array  $pieces
 * @param string $label
 */
function parasut_product_stock_test_in_order($body, $pieces, $label)
{
	$at = -1;

	foreach ($pieces as $piece) {
		$found = strpos($body, $piece, max(0, $at + 1));
		pg_assert_true($found !== false, $label . ': ' . $piece);

		if ($found !== false) {
			$at = $found;
		}
	}
}

/**
 * A products row as parasut_sync_product() reads it.
 *
 * @param array $overrides
 * @return array
 */
function parasut_product_stock_test_product($overrides = array())
{
	return array_merge(array(
		'name' => 'CGB-078',
		'short_description' => 'Mega Hediye Sepeti',
		'price' => '7995',
		'inventory' => '1',
		'inventory_quantity' => '8',
		'tax_rate' => null,
	), $overrides);
}

/**
 * Paraşüt as the stock step meets it: a product card answering GET with the
 * recorded shape, every other request answered 201. Each request is noted
 * in $calls as [method, path, body].
 *
 * @param string $stock_count  stock_count as Paraşüt writes it ("3.0")
 * @param bool   $movements    has_stock_movements
 * @param array  $calls
 * @return callable
 */
function parasut_product_stock_test_card($stock_count, $movements, &$calls)
{
	return function ($method, $path, $body = null) use ($stock_count, $movements, &$calls) {
		$calls[] = array($method, $path, $body);

		if ($method === 'GET') {
			return array('http_code' => 200, 'body' => array('data' => array(
				'id' => '1079471971',
				'type' => 'products',
				'attributes' => array(
					'name' => 'Mega Hediye Sepeti',
					'list_price' => '79.95',
					'currency' => 'TRL',
					'inventory_tracking' => true,
					'stock_count' => $stock_count,
					'initial_stock_count' => $stock_count,
					'has_stock_movements' => $movements,
				),
			)));
		}

		return array('http_code' => 201, 'body' => array('data' => array('id' => '1003775251', 'type' => 'shipment_documents')));
	};
}

/**
 * The inflow (seed) documents among the noted requests, by the quantity
 * each brings in.
 *
 * @param array $calls
 * @return array
 */
function parasut_product_stock_test_seeds($calls)
{
	$seeds = array();

	foreach ($calls as $call) {
		if (($call[0] === 'POST') && ($call[1] === '/shipment_documents') && !empty($call[2]['data']['attributes']['inflow'])) {
			foreach ($call[2]['data']['relationships']['stock_movements']['data'] as $movement) {
				$seeds[] = $movement['attributes']['quantity'];
			}
		}
	}

	return $seeds;
}

// A tracked product: 10 in stock, an order of 2 took it to 8. The card the
// order's document opens starts from 10, the stock before that document,
// so the document taking 2 out leaves Paraşüt at Pinegrap's 8. From 8 it
// ended at 6.
function test_parasut_product_stock_card_opens_with_the_stock_before_the_document()
{
	$product = parasut_product_stock_test_product();

	pg_assert_same(10, parasut_product_attributes($product, 2)['initial_stock_count'], 'the order\'s 2 added back');
	pg_assert_same(8, parasut_product_attributes($product)['initial_stock_count'], 'nothing taken: the stock as it stands');
	pg_assert_same(8, parasut_product_attributes($product, 0)['initial_stock_count']);
	pg_assert_same(8, parasut_product_attributes($product, -3)['initial_stock_count'], 'a negative quantity adds nothing');

	// A sale through the stock leaves 0 (the column is unsigned): the card
	// opens at the sale's quantity, and the document brings it to 0 too.
	pg_assert_same(3, parasut_product_attributes(parasut_product_stock_test_product(array('inventory_quantity' => '0')), 3)['initial_stock_count']);
	pg_assert_same(3, parasut_product_attributes(parasut_product_stock_test_product(array('inventory_quantity' => '-2')), 3)['initial_stock_count']);

	// A product without stock tracking carries no opening stock, whatever was sold.
	$untracked = parasut_product_attributes(parasut_product_stock_test_product(array('inventory' => '0')), 2);
	pg_assert_false(array_key_exists('initial_stock_count', $untracked), 'no opening stock');
	pg_assert_same(false, $untracked['inventory_tracking']);

	// The whole run on Paraşüt's side: the card at 10 needs no seed for the
	// outgoing 2, and ends at Pinegrap's count.
	$calls = array();
	$opening = (string) parasut_product_attributes($product, 2)['initial_stock_count'] . '.0';
	$ensure = parasut_ensure_product_stock_tracking('1079471971', 2, '1074149028', '1001', 'token', parasut_product_stock_test_card($opening, false, $calls));

	pg_assert_true($ensure['success']);
	pg_assert_same(array(), parasut_product_stock_test_seeds($calls), 'no seed');
	pg_assert_same(8, (int) $ensure['current_stock'] - 2, 'Paraşüt after the document = Pinegrap');
}

// parasut_sync_product() opens the card with the quantity its caller says
// the documented sale took out; a card already kept is returned without a
// request, so the quantity matters only to the create.
function test_parasut_product_stock_sync_product_takes_the_quantity()
{
	$body = parasut_product_stock_test_source('includes/fn/parasut.php', 'parasut_sync_product');

	pg_assert_contains('function parasut_sync_product($product_id, $token, $taken_out = 0)', $body);
	pg_assert_contains("'attributes' => parasut_product_attributes(\$product, \$taken_out),", $body);
}

// Which orders took their tracked lines out of Pinegrap's count: every
// placed order (checkout, counter sale, marketplace import), also one the
// shop cancelled (its cancel puts nothing back), but not a cart and not a
// marketplace order cancelled on the marketplace, which mp_order_cancel()
// cancels with no user and then restocks (mp_order_return_stock()).
function test_parasut_product_stock_order_took_stock()
{
	pg_assert_true(parasut_order_took_stock(array('status' => 'complete', 'type' => 'online')));
	pg_assert_true(parasut_order_took_stock(array('status' => 'exported', 'type' => 'online')));
	pg_assert_true(parasut_order_took_stock(array('status' => 'complete', 'type' => 'local')));
	pg_assert_true(parasut_order_took_stock(array('status' => 'complete', 'type' => 'marketplace', 'cancelled_by' => '0')));
	pg_assert_true(parasut_order_took_stock(array('status' => 'cancelled', 'type' => 'online', 'cancelled_by' => '5')));
	pg_assert_true(parasut_order_took_stock(array('status' => 'cancelled', 'type' => 'online', 'cancelled_by' => '0')), 'a shop order the unpaid-order job cancelled');
	pg_assert_true(parasut_order_took_stock(array('status' => 'cancelled', 'type' => 'marketplace', 'cancelled_by' => '5')), 'the shop cancelled a marketplace order: nothing put back');
	pg_assert_false(parasut_order_took_stock(array('status' => 'cancelled', 'type' => 'marketplace', 'cancelled_by' => '0')), 'the marketplace cancelled it: stock put back');
	pg_assert_false(parasut_order_took_stock(array('status' => 'cancelled', 'type' => 'marketplace')));
	pg_assert_false(parasut_order_took_stock(array('status' => 'incomplete', 'type' => 'online')));
	pg_assert_false(parasut_order_took_stock(array()));
}

// The marketplace's cancel is the only one with no user that restocks: it
// passes user 0 to process_order_cancellation() and then calls
// mp_order_return_stock(); process_order_cancellation() itself restocks
// nothing and writes the user it is given into cancelled_by.
function test_parasut_product_stock_marketplace_cancel_is_told_by_its_user()
{
	$cancel = parasut_product_stock_test_source('includes/api/outbound/orders.php', 'mp_order_cancel');

	parasut_product_stock_test_in_order($cancel, array(
		'process_order_cancellation($order_id, $reason, true, 0, false)',
		'mp_order_return_stock($order_id);',
	), 'mp_order_cancel()');

	$shop = parasut_product_stock_test_source('includes/fn/ecommerce.php', 'process_order_cancellation');

	pg_assert_contains("cancelled_by        = '\" . (int) \$user_id . \"',", $shop);
	pg_assert_false(strpos($shop, 'inventory_quantity') !== false, 'the shop\'s cancel puts no stock back');
}

// The units of each product a document's lines come to: a product on two
// lines once, lines without a product and quantities a count does not move
// by (not whole, not above zero) left out.
function test_parasut_product_stock_taken_units()
{
	pg_assert_same(array(7 => 3, 9 => 1), parasut_stock_taken_units(array(
		array('product_id' => '7', 'quantity' => '2'),
		array('product_id' => 0, 'quantity' => '1.0000'),
		array('product_id' => '9', 'quantity' => '1.0000'),
		array('product_id' => '7', 'quantity' => '1.0000'),
		array('product_id' => '11', 'quantity' => '1.5000'),
		array('product_id' => '12', 'quantity' => '0'),
		array('product_id' => '13', 'quantity' => '-1'),
	)));
	pg_assert_same(array(), parasut_stock_taken_units(array()));
	pg_assert_same(array(), parasut_stock_taken_units(null));
}

// The order screen's Create Invoice: an invoice with the shipment included
// (Paraşüt's default) takes the order's goods out at Paraşüt, which the
// order already took out of Pinegrap's count; one going without shipment
// after E-Shipment moves no stock; a cart took nothing.
function test_parasut_product_stock_legacy_invoice_taken()
{
	$lines = array(
		array('product_id' => 7, 'quantity' => '2.0000'),
		array('product_id' => 0, 'quantity' => '1.0000'),
		array('product_id' => 7, 'quantity' => '1.0000'),
	);

	pg_assert_same(array(7 => 3), parasut_order_stock_taken(array('status' => 'complete', 'type' => 'online', 'parasut_shipment_id' => ''), $lines));
	pg_assert_same(array(), parasut_order_stock_taken(array('status' => 'complete', 'type' => 'online', 'parasut_shipment_id' => '1094785'), $lines), 'without shipment');
	pg_assert_same(array(), parasut_order_stock_taken(array('status' => 'incomplete', 'type' => 'online', 'parasut_shipment_id' => ''), $lines), 'a cart');
	pg_assert_same(array(), parasut_order_stock_taken(array('status' => 'cancelled', 'type' => 'marketplace', 'cancelled_by' => '0'), $lines), 'stock put back');
	pg_assert_same(array(7 => 3), parasut_order_stock_taken(array('status' => 'cancelled', 'type' => 'marketplace', 'cancelled_by' => '3', 'parasut_shipment_id' => ''), $lines), 'the shop cancelled it');
}

// The ERP driver: what its caller worked out stands; an order's invoice
// takes its lines when the order took them and the invoice goes with the
// shipment; a typed invoice takes what its own stock moves took out of the
// count (applied, wanted). Nothing is read without a product line or for
// an invoice that names the order's shipment document.
function test_parasut_product_stock_driver_invoice_taken()
{
	$lines = array(
		array('id' => 501, 'product_id' => 7, 'quantity' => '2.0000'),
		array('id' => 502, 'product_id' => 0, 'quantity' => '1.0000'),
		array('id' => 503, 'product_id' => 9, 'quantity' => '1.0000'),
	);
	$reads = array();
	$read = function ($what, $id) use (&$reads) {
		$reads[] = array($what, $id);

		if ($what === 'order') {
			$orders = array(
				438 => array('status' => 'complete', 'type' => 'online'),
				439 => array('status' => 'incomplete', 'type' => 'online'),
				440 => array('status' => 'cancelled', 'type' => 'marketplace', 'cancelled_by' => '0'),
				442 => array('status' => 'cancelled', 'type' => 'marketplace', 'cancelled_by' => '3'),
			);

			return $orders[$id] ?? null;
		}

		return ($id === 42)
			? array(array('product_id' => '7', 'quantity' => '2.0000'), array('product_id' => '9', 'quantity' => '1.0000'))
			: array();
	};

	pg_assert_same(array(7 => 5), parasut_invoice_stock_taken(array('order_id' => 438, 'stock_taken' => array(7 => 5)), $lines, $read), 'the caller\'s');
	pg_assert_same(array(), parasut_invoice_stock_taken(array('order_id' => 438), array($lines[1]), $read), 'no product line');
	pg_assert_same(array(), parasut_invoice_stock_taken(array('order_id' => 438, 'order_shipment_id' => '1094785'), $lines, $read), 'without shipment');
	pg_assert_same(array(), $reads, 'nothing read for those');

	pg_assert_same(array(7 => 2, 9 => 1), parasut_invoice_stock_taken(array('id' => 41, 'order_id' => 438, 'order_shipment_id' => ''), $lines, $read), 'an order\'s invoice');
	pg_assert_same(array(), parasut_invoice_stock_taken(array('id' => 41, 'order_id' => 439), $lines, $read), 'a cart');
	pg_assert_same(array(), parasut_invoice_stock_taken(array('id' => 41, 'order_id' => 440), $lines, $read), 'stock put back');
	pg_assert_same(array(), parasut_invoice_stock_taken(array('id' => 41, 'order_id' => 441), $lines, $read), 'no such order');
	pg_assert_same(array(7 => 2, 9 => 1), parasut_invoice_stock_taken(array('id' => 41, 'order_id' => 442), $lines, $read), 'the shop cancelled a marketplace order');

	pg_assert_same(array(7 => 2, 9 => 1), parasut_invoice_stock_taken(array('id' => 42, 'order_id' => 0), $lines, $read), 'a typed invoice, by its moves');
	pg_assert_same(array(), parasut_invoice_stock_taken(array('id' => 43, 'order_id' => 0), $lines, $read), 'a typed invoice that moved no count');
	pg_assert_same(array(array('order', 438), array('order', 439), array('order', 440), array('order', 441), array('order', 442), array('moves', 42), array('moves', 43)), $reads);
}

// The moves a typed invoice's count change is read from: its own sale moves
// that wanted the count changed and were applied to it.
function test_parasut_product_stock_typed_invoice_reads_applied_moves()
{
	$body = parasut_product_stock_test_source('includes/fn/parasut.php', '_parasut_stock_taken_read');

	pg_assert_contains('FROM erp_stock_moves', $body);
	pg_assert_contains("kind = 'sale'", $body);
	pg_assert_contains('stock_wanted = 1', $body);
	pg_assert_contains('stock_applied = 1', $body);
	pg_assert_contains('erp_stock_ready()', $body);
	pg_assert_contains('SELECT status, type, cancelled_by FROM orders', $body);
}

// The driver's line product hands the quantity to parasut_sync_product();
// the body builder works it out once per invoice and gives each line its
// product's.
function test_parasut_product_stock_driver_passes_the_quantity()
{
	$line = parasut_product_stock_test_source('includes/erp/edoc/parasut.php', 'erp_edoc_parasut_line_product');

	pg_assert_contains('function erp_edoc_parasut_line_product($line, $taken_out = 0)', $line);
	pg_assert_contains("\$sync = parasut_sync_product(\$product_id, (string) \$token['token'], \$taken_out);", $line);

	$payload = parasut_product_stock_test_source('includes/erp/edoc/parasut.php', 'erp_edoc_parasut_invoice_payload');

	parasut_product_stock_test_in_order($payload, array(
		'$taken = parasut_invoice_stock_taken($invoice, $lines);',
		'foreach ($lines as $index => $line) {',
		"\$product = erp_edoc_parasut_line_product(\$line, (int) (\$taken[(int) (\$line['product_id'] ?? 0)] ?? 0));",
	), 'payload');
}

// The order screen's Create Invoice says what its order took, judged with
// the order's shipment document as it stands when the body is built.
function test_parasut_product_stock_create_invoice_says_what_was_taken()
{
	$body = parasut_product_stock_test_source('includes/fn/parasut.php', 'parasut_create_invoice');

	parasut_product_stock_test_in_order($body, array(
		'$build_invoice_body = function ($contact_id) use (',
		'$invoice_body = erp_edoc_parasut_invoice_payload(array(',
		"'stock_taken' => parasut_order_stock_taken(\$order, \$built['lines']),",
		"), \$built['lines'], \$contact_id);",
	), 'Create Invoice');
}

// E-Shipment opens a card with the order's whole quantity of the product
// added back when the order took it out of Pinegrap's count.
function test_parasut_product_stock_e_shipment_opens_cards_with_the_order_total()
{
	$body = parasut_product_stock_test_source('includes/fn/parasut.php', 'parasut_create_shipment');

	parasut_product_stock_test_in_order($body, array(
		'$taken = parasut_order_took_stock($order) ? parasut_stock_taken_units($items) : array();',
		"\$sync = parasut_sync_product(\$item['product_id'], \$token, \$taken[(int) \$item['product_id']] ?? 0);",
	), 'E-Shipment');
}

// The order's lines by Paraşüt product: two lines of one product (two
// recipients) are one need of their total, named by the first line.
function test_parasut_product_stock_shipment_needs_sum_per_parasut_product()
{
	pg_assert_same(array(
		array('parasut_product_id' => '1079471971', 'quantity' => 4, 'name' => 'Mega Hediye Sepeti'),
		array('parasut_product_id' => '1079470721', 'quantity' => 1, 'name' => 'Deri Kalem Kılıfı'),
	), parasut_shipment_stock_needs(array(
		array('parasut_product_id' => '1079471971', 'quantity' => 2, 'name' => 'Mega Hediye Sepeti'),
		array('parasut_product_id' => '1079470721', 'quantity' => 1, 'name' => 'Deri Kalem Kılıfı'),
		array('parasut_product_id' => '1079471971', 'quantity' => 2, 'name' => 'Mega Hediye Sepeti (2)'),
	)));
	pg_assert_same(array(), parasut_shipment_stock_needs(array()));
}

// A card at 3 with no movements and an order of 2 + 2 of it: checked line by
// line, 3 covers each 2 and nothing is seeded, and the document takes 4 out
// of 3. Checked once for the total, 1 is seeded first.
function test_parasut_product_stock_seed_covers_the_order_total()
{
	$calls = array();
	$card = parasut_product_stock_test_card('3.0', false, $calls);

	parasut_ensure_product_stock_tracking('1079471971', 2, '1074149028', '1001', 'token', $card);
	parasut_ensure_product_stock_tracking('1079471971', 2, '1074149028', '1001', 'token', $card);
	pg_assert_same(array(), parasut_product_stock_test_seeds($calls), 'line by line: nothing seeded for 4 out of 3');

	$calls = array();
	$ensure = parasut_ensure_product_stock_tracking('1079471971', 4, '1074149028', '1001', 'token', parasut_product_stock_test_card('3.0', false, $calls));

	pg_assert_true($ensure['success']);
	pg_assert_same(array(1), parasut_product_stock_test_seeds($calls), 'the total: 1 seeded');
	pg_assert_same(4.0, $ensure['current_stock']);
	pg_assert_same(array('GET', '/products/1079471971'), array($calls[0][0], $calls[0][1]));
	pg_assert_same('1001', $calls[1][2]['data']['relationships']['stock_movements']['data'][0]['relationships']['warehouse']['data']['id'], 'the seed goes to the warehouse');

	// A card with movements is not seeded: the code assumes Paraşüt lets its
	// stock go below zero (not checked live).
	$calls = array();
	parasut_ensure_product_stock_tracking('1079471971', 4, '1074149028', '1001', 'token', parasut_product_stock_test_card('3.0', true, $calls));
	pg_assert_same(array(), parasut_product_stock_test_seeds($calls));
}

// E-Shipment asks the stock step once per Paraşüt product with the order's
// total of it, not once per line with that line's quantity; the movement
// lines stay one per order line.
function test_parasut_product_stock_e_shipment_checks_the_total_once_per_product()
{
	$body = parasut_product_stock_test_source('includes/fn/parasut.php', 'parasut_create_shipment');

	parasut_product_stock_test_in_order($body, array(
		"\$stock_lines[] = array('parasut_product_id' => \$item_parasut_product_id, 'quantity' => \$item_qty, 'name' => \$item_name);",
		'foreach (parasut_shipment_stock_needs($stock_lines) as $need) {',
		'$ensure = parasut_ensure_product_stock_tracking(',
		"\$need['parasut_product_id'],",
		"\$need['quantity'],",
		"'vars' => array(\$need['name'], \$ensure['error'])",
		'foreach ($stock_lines as $stock_line) {',
		"\$stock_movements[] = parasut_shipment_stock_movement(\$stock_line['parasut_product_id'], \$default_warehouse_id, \$stock_line['quantity']);",
		"_parasut_request('POST', _parasut_prefix() . '/shipment_documents', \$body, \$token);",
	), 'E-Shipment');

	// The stock step's own argument list, whatever the line endings.
	$arguments = preg_match('/\$ensure = parasut_ensure_product_stock_tracking\((.*?)\);/s', $body, $match) ? $match[1] : '';

	pg_assert_contains("\$need['quantity'],", $arguments);
	pg_assert_false(strpos($arguments, '$item_qty') !== false, 'no line\'s own quantity goes to the stock step');
	pg_assert_same(0, preg_match('/\$item_qty,[ \t]*\R/', $body), 'no argument line names a line\'s own quantity');
}

// A line whose product row is gone (deleted from the catalogue since the
// order) is no untracked service: E-Shipment refuses naming it.
function test_parasut_product_stock_shipment_refuses_deleted_products()
{
	$refusal = parasut_shipment_deleted_products_refusal(array(
		array('product_id' => '12', 'product_name' => 'Deri Kalem Kılıfı', 'product_row_id' => null),
		array('product_id' => '43', 'product_name' => 'CGB-078', 'product_row_id' => '43'),
		array('product_id' => '0', 'product_name' => 'Kargo', 'product_row_id' => null),
		array('product_id' => '12', 'product_name' => 'Deri Kalem Kılıfı', 'product_row_id' => null),
		array('product_id' => '15', 'product_name' => '', 'product_row_id' => null),
	));

	pg_assert_contains('"Deri Kalem Kılıfı", #15', $refusal);
	pg_assert_same(1, substr_count($refusal, 'Deri Kalem Kılıfı'), 'a product on two lines is named once');
	pg_assert_false(strpos($refusal, 'CGB-078') !== false, 'a product still there is not named');
	pg_assert_false(strpos($refusal, 'Kargo') !== false, 'a line without a product is not named');

	pg_assert_same('', parasut_shipment_deleted_products_refusal(array(
		array('product_id' => '43', 'product_name' => 'CGB-078', 'product_row_id' => '43'),
		array('product_id' => '0', 'product_name' => 'Kargo', 'product_row_id' => null),
	)));
	pg_assert_same('', parasut_shipment_deleted_products_refusal(array()));
}

// The refusal comes after the guard of a shipped order, which reads a kept
// shipment document (and forgets one Paraşüt no longer holds), and before
// the token and anything else asked of or written at Paraşüt.
function test_parasut_product_stock_e_shipment_refuses_deleted_products_first()
{
	$body = parasut_product_stock_test_source('includes/fn/parasut.php', 'parasut_create_shipment');
	$guard = strpos($body, "'This order already has a shipment document in Paraşüt (ID: {var:1}), so a second one was not created.'");
	$refused = strpos($body, "if (\$deleted_refusal !== '') {");

	pg_assert_contains('$deleted_refusal = parasut_shipment_deleted_products_refusal(', $body);
	pg_assert_contains('products.id AS product_row_id', $body);
	pg_assert_true(($guard !== false) && ($refused !== false) && ($guard < $refused), 'after the guard of a shipped order');

	foreach (array('parasut_get_token()', 'parasut_shipment_invoice_link(', 'parasut_sync_contact(', 'parasut_ensure_default_warehouse(', 'parasut_sync_product(', 'parasut_ensure_product_stock_tracking(', "_parasut_request('POST'") as $call) {
		$at = strpos($body, $call);
		pg_assert_true(($refused !== false) && ($at !== false) && ($refused < $at), 'refused before ' . $call);
	}
}
