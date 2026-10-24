<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order screen's two legacy Paraşüt documents of one order
 * (includes/fn/parasut.php): Create Invoice after E-Shipment sends the sales
 * invoice without shipment and names the shipment document; E-Shipment after
 * Create Invoice goes only for an invoice issued without shipment, naming it,
 * and is refused before anything is written otherwise.
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
 * The source of one function of includes/fn/parasut.php, from its
 * declaration to the next one.
 *
 * @param string $function
 * @return string  '' when the function is not found
 */
function parasut_invoice_shipment_test_source($function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/parasut.php');
	$start = strpos($source, 'function ' . $function . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", (int) $start + 1);

	return ($end === false) ? substr($source, (int) $start) : substr($source, (int) $start, $end - (int) $start);
}

/**
 * A GET stand-in that records the paths it is asked for and gives one answer.
 *
 * @param array $answer  The _parasut_request() answer to give
 * @param array $asked   Receives each path asked for
 * @return callable
 */
function parasut_invoice_shipment_test_get($answer, &$asked)
{
	return function ($path) use ($answer, &$asked) {
		$asked[] = $path;

		return $answer;
	};
}

// A sales_invoices body as the order's Create Invoice builds it.
function parasut_invoice_shipment_test_invoice_body()
{
	return array('data' => array(
		'type' => 'sales_invoices',
		'attributes' => array('item_type' => 'invoice', 'description' => 'Order #1175', 'issue_date' => '2026-10-24', 'currency' => 'TRL'),
		'relationships' => array(
			'contact' => array('data' => array('type' => 'contacts', 'id' => '1074149028')),
			'details' => array('data' => array()),
		),
	));
}

// Create Invoice on an order E-Shipment already took out of stock sends the
// invoice without shipment, naming the shipment document; Paraşüt then moves
// no stock for the invoice (live: 201, shipment_included false, stock as
// before, the document's invoices naming the invoice). Without a shipment
// document the body is left as it was: Paraşüt's default (with shipment).
function test_parasut_order_invoice_with_shipment()
{
	$body = parasut_invoice_shipment_test_invoice_body();

	pg_assert_same($body, parasut_order_invoice_with_shipment($body, ''));
	pg_assert_same($body, parasut_order_invoice_with_shipment($body, '  '));
	pg_assert_same($body, parasut_order_invoice_with_shipment($body, null));

	$linked = parasut_order_invoice_with_shipment($body, ' 1003775252 ');

	pg_assert_same(false, $linked['data']['attributes']['shipment_included']);
	pg_assert_same(array('data' => array(array('type' => 'shipment_documents', 'id' => '1003775252'))), $linked['data']['relationships']['shipment_documents']);

	// Everything else goes as it was.
	unset($linked['data']['attributes']['shipment_included'], $linked['data']['relationships']['shipment_documents']);
	pg_assert_same($body, $linked);
}

// An order with no invoice of its own: E-Shipment goes as before, naming no
// invoice, and nothing is asked of Paraşüt.
function test_parasut_shipment_invoice_link_without_an_invoice_asks_nothing()
{
	$asked = array();
	$get = parasut_invoice_shipment_test_get(array('http_code' => 200, 'body' => array()), $asked);

	pg_assert_same(array('invoice_id' => '', 'error' => ''), parasut_shipment_invoice_link('', $get));
	pg_assert_same(array('invoice_id' => '', 'error' => ''), parasut_shipment_invoice_link('  ', $get));
	pg_assert_same(array(), $asked);
}

// The unsure mark is no record to read: E-Shipment is refused until Create
// Invoice has settled the invoice, and nothing is asked of Paraşüt.
function test_parasut_shipment_invoice_link_refuses_the_unsure_mark()
{
	$asked = array();
	$get = parasut_invoice_shipment_test_get(array('http_code' => 200, 'body' => array()), $asked);
	$link = parasut_shipment_invoice_link('?', $get);

	pg_assert_same('', $link['invoice_id']);
	pg_assert_same(lang('The last try to invoice this order got no answer from Paraşüt (Check Paraşüt), so it is not known whether its sales invoice carries the shipment. Press Create Invoice again to settle the invoice first; no shipment document was created.'), $link['error']);
	pg_assert_same(array(), $asked);
}

// An invoice kept with the shipment included took its goods out of stock
// itself and cannot be linked (live: 422 "İrsaliyeli faturalar ile irsaliye
// ilişkilendirilemez."), so E-Shipment is refused in so many words.
function test_parasut_shipment_invoice_link_refuses_an_invoice_with_the_shipment()
{
	$asked = array();
	$get = parasut_invoice_shipment_test_get(array('http_code' => 200, 'body' => array('data' => array(
		'id' => '1104339388', 'type' => 'sales_invoices', 'attributes' => array('shipment_included' => true, 'net_total' => '47.14'),
	))), $asked);
	$link = parasut_shipment_invoice_link('1104339388', $get);

	pg_assert_same(array('/sales_invoices/1104339388'), $asked);
	pg_assert_same('', $link['invoice_id']);
	pg_assert_same(lang(array(
		'string' => 'This order\'s sales invoice in Paraşüt (ID: {var:1}) carries the shipment: its goods left stock with it, so a shipment document would count them out a second time. A separate e-waybill needs the invoice issued without shipment. No shipment document was created.',
		'vars' => array('1104339388'),
	)), $link['error']);
}

// An invoice issued without shipment moved no stock: E-Shipment goes and
// names it.
function test_parasut_shipment_invoice_link_names_an_invoice_without_shipment()
{
	$asked = array();
	$get = parasut_invoice_shipment_test_get(array('http_code' => 200, 'body' => array('data' => array(
		'id' => '1104339394', 'type' => 'sales_invoices', 'attributes' => array('shipment_included' => false),
	))), $asked);

	pg_assert_same(array('invoice_id' => '1104339394', 'error' => ''), parasut_shipment_invoice_link(' 1104339394 ', $get));
	pg_assert_same(array('/sales_invoices/1104339394'), $asked);
}

// A read that fails, or an answer that does not say, is no answer: E-Shipment
// is refused with Paraşüt's words rather than sent on a guess.
function test_parasut_shipment_invoice_link_refuses_when_parasut_does_not_say()
{
	$answers = array(
		'no connection' => array('http_code' => 0, 'body' => null, 'raw' => '', 'curl_error' => 'Operation timed out'),
		'server error' => array('http_code' => 500, 'body' => null, 'raw' => ''),
		'deleted there' => array('http_code' => 404, 'body' => array('errors' => array(array('title' => 'Record was not found', 'detail' => 'SalesInvoice-77'))), 'raw' => ''),
		'no attribute' => array('http_code' => 200, 'body' => array('data' => array('id' => '77', 'attributes' => array('net_total' => '10.0'))), 'raw' => ''),
		'null attribute' => array('http_code' => 200, 'body' => array('data' => array('id' => '77', 'attributes' => array('shipment_included' => null))), 'raw' => ''),
		'text attribute' => array('http_code' => 200, 'body' => array('data' => array('id' => '77', 'attributes' => array('shipment_included' => 'false'))), 'raw' => ''),
	);

	foreach ($answers as $label => $answer) {
		$asked = array();
		$link = parasut_shipment_invoice_link('77', parasut_invoice_shipment_test_get($answer, $asked));

		pg_assert_same('', $link['invoice_id'], $label);
		pg_assert_same(lang(array(
			'string' => 'Paraşüt could not be asked whether this order\'s sales invoice (ID: {var:1}) carries the shipment: {var:2}. No shipment document was created; try again in a minute.',
			'vars' => array('77', _parasut_extract_error($answer)),
		)), $link['error'], $label);
	}
}

// The outgoing shipment document names the invoice it goes with; without one
// the body is left as it was.
function test_parasut_shipment_with_invoice()
{
	$body = array('data' => array(
		'type' => 'shipment_documents',
		'attributes' => array('description' => 'Order #1177', 'issue_date' => '2026-10-24', 'inflow' => false),
		'relationships' => array(
			'contact' => array('data' => array('type' => 'contacts', 'id' => '1074149028')),
			'stock_movements' => array('data' => array(parasut_shipment_stock_movement('1079203184', '1', 1))),
		),
	));

	pg_assert_same($body, parasut_shipment_with_invoice($body, ''));

	$linked = parasut_shipment_with_invoice($body, '1104339394');

	pg_assert_same(array('data' => array(array('type' => 'sales_invoices', 'id' => '1104339394'))), $linked['data']['relationships']['invoices']);
	unset($linked['data']['relationships']['invoices']);
	pg_assert_same($body, $linked);
}

// Create Invoice reads the order's shipment document into the body before
// it looks for an unanswered try's record or writes one.
function test_parasut_create_invoice_sends_without_shipment_after_e_shipment()
{
	$body = parasut_invoice_shipment_test_source('parasut_create_invoice');
	$built = strpos($body, "\$invoice_body = parasut_order_invoice_with_shipment(\$invoice_body, (string) (\$order['parasut_shipment_id'] ?? ''));");
	$searched = strpos($body, 'parasut_order_invoice_find($invoice_body,');
	$posted = strpos($body, "'/sales_invoices', \$invoice_body");

	pg_assert_true($built !== false, 'the shipment document goes into the body');
	pg_assert_true(($searched !== false) && ($built < $searched), 'before the search');
	pg_assert_true(($posted !== false) && ($built < $posted), 'before the create');
}

// E-Shipment judges the order's invoice after the guard of a shipped order
// and before anything is written at Paraşüt: the buyer card, the product,
// its tracking and opening stock, the document. The document names the
// invoice the judgement gave.
function test_parasut_create_shipment_judges_the_invoice_before_any_write()
{
	$body = parasut_invoice_shipment_test_source('parasut_create_shipment');
	$guard = strpos($body, "\$existing_shipment_id = trim((string) (\$order['parasut_shipment_id'] ?? ''));");
	$link = strpos($body, "parasut_shipment_invoice_link((string) (\$order['parasut_invoice_id'] ?? ''),");
	$refused = strpos($body, "if (\$invoice_link['error'] !== '') {");

	pg_assert_true(($guard !== false) && ($link !== false) && ($guard < $link), 'after the guard of a shipped order');
	pg_assert_true(($refused !== false) && ($link < $refused), 'a refusal returns');

	foreach (array('parasut_sync_contact(', 'parasut_ensure_default_warehouse(', 'parasut_sync_product(', 'parasut_ensure_product_stock_tracking(', "_parasut_request('POST'") as $call) {
		$at = strpos($body, $call);
		pg_assert_true(($at !== false) && ($refused !== false) && ($refused < $at), 'judged before ' . $call);
	}

	pg_assert_contains("\$body = parasut_shipment_with_invoice(\$body, \$invoice_link['invoice_id']);", $body);
	pg_assert_true(strpos($body, "parasut_shipment_with_invoice(\$body") < strpos($body, "_parasut_request('POST'"), 'the body names the invoice before it goes');
}
