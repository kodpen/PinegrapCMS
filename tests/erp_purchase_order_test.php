<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the purchase order line keys: erp_source_line_key()
 * (includes/erp/invoice_manual.php), which cleans the key a line arrives
 * with, and erp_purchase_order_line_keys() (includes/erp/purchase_orders.php),
 * which gives every order line one of its own; for what is read from them:
 * erp_purchase_order_progress(), erp_purchase_order_receipt_state() and the
 * quantity suggested from the minimum stock; for the moves an order allows;
 * for the order's routes in the external API (includes/erp/api.php,
 * api_purchase_orders.php); and for the series a purchase bill is numbered
 * in (erp_invoice_series()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/invoice_manual.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/purchase_orders.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');

// A plain key passes through as it is.
function test_erp_purchase_order_source_line_key_keeps_letters_and_digits()
{
	pg_assert_same('a1b2c3d4', erp_source_line_key('a1b2c3d4'));
	pg_assert_same('7', erp_source_line_key(7));
}

// Anything but letters and digits is dropped, and the key is cut at 16.
function test_erp_purchase_order_source_line_key_cleans_and_cuts()
{
	pg_assert_same('ab12', erp_source_line_key(' a-b"1<2> '));
	pg_assert_same('0123456789abcdef', erp_source_line_key('0123456789abcdefXYZ'));
}

// Empty, and the zero the line editor leaves in an emptied row, are no key.
function test_erp_purchase_order_source_line_key_empty_and_zero()
{
	pg_assert_same('', erp_source_line_key(''));
	pg_assert_same('', erp_source_line_key(null));
	pg_assert_same('', erp_source_line_key('0'));
	pg_assert_same('', erp_source_line_key('000'));
	pg_assert_same('10', erp_source_line_key('10'));
}

// Lines that come with their keys keep them, in order.
function test_erp_purchase_order_line_keys_keep_existing()
{
	$keyed = erp_purchase_order_line_keys(array(
		array('description' => 'A', 'source_line' => 'aaaa1111'),
		array('description' => 'B', 'source_line' => 'bbbb2222'),
	));

	pg_assert_same(array('aaaa1111', 'bbbb2222'), array_column($keyed, 'source_line'));
	pg_assert_same(array('A', 'B'), array_column($keyed, 'description'));
}

// A new line, or one whose key was emptied, is given a fresh key.
function test_erp_purchase_order_line_keys_give_new_lines_a_key()
{
	$next = 0;
	$keyed = erp_purchase_order_line_keys(array(
		array('description' => 'A', 'source_line' => 'aaaa1111'),
		array('description' => 'B'),
		array('description' => 'C', 'source_line' => '0'),
	), function () use (&$next) {
		return 'new' . (++$next);
	});

	pg_assert_same(array('aaaa1111', 'new1', 'new2'), array_column($keyed, 'source_line'));
}

// A key already taken by an earlier line (a copied row, a key typed twice,
// one differing only in case) is not handed to a second line.
function test_erp_purchase_order_line_keys_no_duplicates()
{
	$next = 0;
	$keyed = erp_purchase_order_line_keys(array(
		array('source_line' => 'abcd'),
		array('source_line' => 'abcd'),
		array('source_line' => 'ABCD'),
	), function () use (&$next) {
		return 'k' . (++$next);
	});

	pg_assert_same(array('abcd', 'k1', 'k2'), array_column($keyed, 'source_line'));
}

// A fresh key that clashes with one already used is drawn again.
function test_erp_purchase_order_line_keys_redraw_on_clash()
{
	$draws = array('aaaa', 'aaaa', 'bbbb');
	$keyed = erp_purchase_order_line_keys(array(
		array('source_line' => 'aaaa'),
		array('source_line' => ''),
	), function () use (&$draws) {
		return array_shift($draws);
	});

	pg_assert_same(array('aaaa', 'bbbb'), array_column($keyed, 'source_line'));
}

// The default keys are eight hex digits, and differ from line to line.
function test_erp_purchase_order_line_keys_default_random()
{
	$keyed = erp_purchase_order_line_keys(array(array(), array(), array()));
	$keys = array_column($keyed, 'source_line');

	pg_assert_same(3, count(array_unique($keys)));
	foreach ($keys as $key) {
		pg_assert_true(preg_match('/^[0-9a-f]{8}$/', $key) === 1);
	}
}

// Back up to the minimum, at least one, whatever the count on hand.
function test_erp_purchase_order_suggested_quantity()
{
	pg_assert_same(7, erp_purchase_order_suggested_quantity(10, 3));
	pg_assert_same(1, erp_purchase_order_suggested_quantity(10, 10));
	pg_assert_same(1, erp_purchase_order_suggested_quantity(5, 12));
	pg_assert_same(12, erp_purchase_order_suggested_quantity(10, -2));
	pg_assert_same(1, erp_purchase_order_suggested_quantity(0, 0));
}

// Per line: what was ordered, received, on drafts, still to draft and still to come.
function test_erp_purchase_order_progress_per_line()
{
	$lines = array(
		array('source_line' => 'aaaa', 'description' => 'Screw', 'quantity' => 10),
		array('source_line' => 'BBBB', 'description' => 'Nut', 'quantity' => 100),
		array('source_line' => 'cccc', 'description' => 'Washer', 'quantity' => 5),
	);
	$progress = erp_purchase_order_progress($lines, array('aaaa' => 4, 'bbbb' => 100), array('aaaa' => 3, 'cccc' => 5));

	pg_assert_same(array(1, 2, 3), array_column($progress, 'line_no'));
	pg_assert_same(array(4.0, 100.0, 0.0), array_column($progress, 'received'));
	pg_assert_same(array(3.0, 0.0, 5.0), array_column($progress, 'drafted'));
	pg_assert_same(array(3.0, 0.0, 0.0), array_column($progress, 'remaining'));
	pg_assert_same(array(6.0, 0.0, 5.0), array_column($progress, 'open'));
	pg_assert_same('bbbb', $progress[1]['source_line']);
}

// More than was ordered leaves nothing to come, never less than nothing.
function test_erp_purchase_order_progress_over_delivery()
{
	$progress = erp_purchase_order_progress(array(array('source_line' => 'aaaa', 'quantity' => 2.5)), array('aaaa' => 3), array('aaaa' => 1));

	pg_assert_same(0.0, $progress[0]['remaining']);
	pg_assert_same(0.0, $progress[0]['open']);
}

// A line without a key has nothing come against it.
function test_erp_purchase_order_progress_line_without_key()
{
	$progress = erp_purchase_order_progress(array(array('quantity' => 4)), array('' => 4));

	pg_assert_same(0.0, $progress[0]['received']);
	pg_assert_same(4.0, $progress[0]['remaining']);
}

// Nothing, part or all of the goods.
function test_erp_purchase_order_receipt_state()
{
	$lines = array(
		array('source_line' => 'aaaa', 'quantity' => 10),
		array('source_line' => 'bbbb', 'quantity' => 2),
	);

	pg_assert_same('none', erp_purchase_order_receipt_state(erp_purchase_order_progress($lines)));
	pg_assert_same('none', erp_purchase_order_receipt_state(erp_purchase_order_progress($lines, array(), array('aaaa' => 10, 'bbbb' => 2))));
	pg_assert_same('partial', erp_purchase_order_receipt_state(erp_purchase_order_progress($lines, array('aaaa' => 10))));
	pg_assert_same('partial', erp_purchase_order_receipt_state(erp_purchase_order_progress($lines, array('aaaa' => 0.5, 'bbbb' => 2))));
	pg_assert_same('received', erp_purchase_order_receipt_state(erp_purchase_order_progress($lines, array('aaaa' => 10, 'bbbb' => 2))));
	pg_assert_same('received', erp_purchase_order_receipt_state(erp_purchase_order_progress($lines, array('aaaa' => 12, 'bbbb' => 2))));
	pg_assert_same('none', erp_purchase_order_receipt_state(array()));
}

// Sent from anything but a cancelled order, cancelled from anything but one
// that is already over, open again only from cancelled.
function test_erp_purchase_order_status_refusal()
{
	$order = function ($status) {
		return array('status' => $status);
	};

	foreach (array('open', 'sent', 'partial', 'received') as $status) {
		pg_assert_same('', erp_purchase_order_status_refusal($order($status), 'sent'), 'sent from ' . $status);
		pg_assert_true(erp_purchase_order_status_refusal($order($status), 'open') !== '', 'open from ' . $status);
	}

	pg_assert_true(erp_purchase_order_status_refusal($order('cancelled'), 'sent') !== '');

	foreach (array('open', 'sent', 'partial') as $status) {
		pg_assert_same('', erp_purchase_order_status_refusal($order($status), 'cancelled'), 'cancelled from ' . $status);
	}

	pg_assert_true(erp_purchase_order_status_refusal($order('received'), 'cancelled') !== '');
	pg_assert_true(erp_purchase_order_status_refusal($order('cancelled'), 'cancelled') !== '');
	pg_assert_same('', erp_purchase_order_status_refusal($order('cancelled'), 'open'));
	pg_assert_true(erp_purchase_order_status_refusal($order('open'), 'received') !== '');
}

// A draft is made only while something is left to put on one.
function test_erp_purchase_order_invoice_refusal()
{
	$lines = array(
		array('source_line' => 'aaaa', 'quantity' => 10),
		array('source_line' => 'bbbb', 'quantity' => 2),
	);
	$nothing_yet = erp_purchase_order_progress($lines);
	$all_on_drafts = erp_purchase_order_progress($lines, array('aaaa' => 4), array('aaaa' => 6, 'bbbb' => 2));

	pg_assert_same('', erp_purchase_order_invoice_refusal(array('status' => 'sent'), $nothing_yet));
	pg_assert_same('', erp_purchase_order_invoice_refusal(array('status' => 'partial'), erp_purchase_order_progress($lines, array('aaaa' => 4))));
	pg_assert_true(erp_purchase_order_invoice_refusal(array('status' => 'partial'), $all_on_drafts) !== '');
	pg_assert_true(erp_purchase_order_invoice_refusal(array('status' => 'cancelled'), $nothing_yet) !== '');
	pg_assert_true(erp_purchase_order_invoice_refusal(array('status' => 'received'), $nothing_yet) !== '');
}

// Reads with erp:read; every write with erp:write, and each can be tried
// first with X-Dry-Run, the e-mail above all.
function test_erp_purchase_order_api_routes()
{
	$found = array();

	foreach (erp_api_routes() as $route) {
		if (strpos($route['id'], 'erp.purchase_orders.') !== 0) {
			continue;
		}

		$found[] = substr($route['id'], strlen('erp.purchase_orders.'));
		pg_assert_same(0, strpos($route['path'], '/erp/purchase-orders'), $route['id'] . ' lives under /erp/purchase-orders');

		if ($route['method'] === 'GET') {
			pg_assert_same('erp:read', $route['scope'], $route['id'] . ' reads with erp:read');
		} else {
			pg_assert_same('erp:write', $route['scope'], $route['id'] . ' writes with erp:write');
			pg_assert_true(!empty($route['dry_run']), $route['id'] . ' takes X-Dry-Run');
		}
	}

	sort($found);
	pg_assert_same(array('create', 'document', 'email', 'get', 'invoice', 'list', 'status', 'update'), $found);
}

// A line's key is taken where the line can be tied to an order's line, and
// only there.
function test_erp_purchase_order_api_lines_carry_keys()
{
	$of = array();

	foreach (erp_api_routes() as $route) {
		foreach ($route['params'] as $param) {
			if (($param['name'] ?? '') === 'lines') {
				$of[$route['id']] = array_keys((array) ($param['of'] ?? array()));
			}
		}
	}

	pg_assert_true(in_array('key', $of['erp.purchase_orders.update'], true));
	pg_assert_false(in_array('key', $of['erp.purchase_orders.create'], true));
	pg_assert_true(in_array('purchase_order_line', $of['erp.invoices.update'], true));
	pg_assert_false(in_array('purchase_order_line', $of['erp.invoices.create'], true));
	pg_assert_false(in_array('purchase_order_line', $of['erp.quotes.update'], true));

	$lines = erp_api_lines_in(array(
		array('description' => 'Screw', 'unit_price' => 100, 'tax_rate' => 20, 'key' => 'ab12cd34'),
		array('description' => 'Nut', 'unit_price' => 50, 'tax_rate' => 20),
	), 0, 'key');

	pg_assert_same(array('ab12cd34', ''), array_column($lines, 'source_line'));

	$plain = erp_api_lines_in(array(array('description' => 'Screw', 'unit_price' => 100, 'tax_rate' => 20)), 0);

	pg_assert_same('', $plain[0]['source_line']);
}

// Every event the purchase orders announce is one an application can
// subscribe to.
function test_erp_purchase_order_events_are_offered()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/purchase_orders.php');
	$offered = erp_webhook_events();

	pg_assert_true(preg_match_all("/'(erp\.purchase_order\.[a-z_]+)'/", $source, $found) > 0);

	foreach (array_unique($found[1]) as $event) {
		pg_assert_true(isset($offered[$event]), $event . ' is in erp_webhook_events()');
	}

	foreach (array('created', 'sent', 'received', 'cancelled', 'reopened') as $name) {
		pg_assert_true(isset($offered['erp.purchase_order.' . $name]), 'erp.purchase_order.' . $name);
	}
}

// A purchase bill is numbered in a series of its own, so it never prints the
// number of a sales invoice: the invoice series with an A after it, unless
// config.php names one. A sales invoice keeps the invoice series.
function test_erp_purchase_invoice_series_is_its_own()
{
	$sales = defined('ERP_DEFAULT_SERIES') ? trim((string) ERP_DEFAULT_SERIES) : 'PGF';

	pg_assert_same($sales, erp_invoice_series('sales'));
	pg_assert_same($sales, erp_invoice_series());

	if (!defined('ERP_PURCHASE_INVOICE_SERIES')) {
		pg_assert_same($sales . 'A', erp_invoice_series('purchase'));
	}

	pg_assert_true(erp_invoice_series('purchase') !== erp_invoice_series('sales'));
	// Nor the purchase orders', the delivery notes' or the returns'.
	pg_assert_true(erp_invoice_series('purchase') !== erp_purchase_order_series());
	pg_assert_true(erp_invoice_series('purchase') !== $sales . 'S');
	pg_assert_true(erp_invoice_series('purchase') !== $sales . 'I');
}
