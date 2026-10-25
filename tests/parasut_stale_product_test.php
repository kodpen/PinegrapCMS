<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Paraşüt ids Pinegrap keeps and sends without asking
 * Paraşüt (includes/fn/parasut.php, includes/erp/edoc/parasut.php): a
 * product's card (products.parasut_product_id), the default warehouse
 * (config.parasut_default_warehouse_id), an order's buyer card
 * (orders.parasut_contact_id) and, in the ERP driver's order invoice, the
 * order's shipment document (orders.parasut_shipment_id). When a create
 * answers 404 "Record was not found" naming one of them, the id is
 * forgotten and the create sent once more; the default product/service is a
 * setting and is never forgotten.
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
 * The source of one function, from its declaration to the next one.
 *
 * @param string $file      Path under the software directory
 * @param string $function
 * @return string  '' when the function is not found
 */
function parasut_stale_product_test_source($file, $function)
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
 * Whether the needles are found in this order in the source.
 *
 * @param string $source
 * @param array  $needles
 * @param string $label
 * @return bool
 */
function parasut_stale_product_test_in_order($source, $needles, $label)
{
	$from = 0;

	foreach ($needles as $needle) {
		$found = ($from <= strlen((string) $source)) ? strpos((string) $source, (string) $needle, $from) : false;

		if ($found === false) {
			return pg_assert_true(false, $label . ': ' . $needle);
		}

		$from = $found + 1;
	}

	return pg_assert_true(true, $label);
}

/**
 * A 404 that names a record as not found, as Paraşüt answers a create
 * naming a record it does not hold.
 *
 * @param string $detail
 * @return array
 */
function parasut_stale_product_test_missing($detail)
{
	$body = array('errors' => array(array('title' => 'Record was not found', 'detail' => $detail)));

	return array('http_code' => 404, 'body' => $body, 'raw' => json_encode($body));
}

// What Paraşüt answered on the demo company for a create naming a record it
// does not hold, and answers that name none.
function parasut_stale_product_test_answers()
{
	return array(
		'product' => parasut_stale_product_test_missing('Product-1079203184'),
		'warehouse' => parasut_stale_product_test_missing('Warehouse-1'),
		'contact' => parasut_stale_product_test_missing('Contact-1093318880'),
		'shipment' => parasut_stale_product_test_missing('ShipmentDocument-1003775252'),
		'created' => array('http_code' => 201, 'body' => array('data' => array('id' => '1104339388', 'type' => 'sales_invoices')), 'raw' => '{"data":{}}'),
		'no access to company' => array('http_code' => 401, 'body' => array('errors' => array(array('title' => 'No access to company: 999999999'))), 'raw' => '{"errors":[{"title":"No access to company: 999999999"}]}'),
		'bare 404' => array('http_code' => 404, 'body' => array('status' => 404, 'error' => 'Not Found'), 'raw' => '{"status":404,"error":"Not Found"}'),
		'no route' => array('http_code' => 404, 'body' => array('errors' => array(array('title' => 'Not Found', 'detail' => 'No route matches.'))), 'raw' => '{}'),
		'page not found' => array('http_code' => 404, 'body' => null, 'raw' => '<html><body>Not Found</body></html>'),
		'not found, no record named' => array('http_code' => 404, 'body' => array('errors' => array(array('title' => 'Record was not found'))), 'raw' => '{}'),
		'not found, no id' => parasut_stale_product_test_missing('Product-'),
		'not found, a word for an id' => parasut_stale_product_test_missing('Product-abc'),
		'another title' => array('http_code' => 404, 'body' => array('errors' => array(array('title' => 'Not Found', 'detail' => 'Product-1079203184'))), 'raw' => '{}'),
		'refused' => array('http_code' => 422, 'body' => array('errors' => array(array('title' => 'Record was not found', 'detail' => 'Product-1079203184'))), 'raw' => '{}'),
		'timeout' => array('http_code' => 0, 'body' => null, 'raw' => '', 'curl_error' => 'Operation timed out after 30001 milliseconds'),
		'driver timeout' => array('http_code' => 0, 'body' => null, 'raw' => '', 'error' => 'Operation timed out', 'login_error' => ''),
		'server error' => array('http_code' => 500, 'body' => null, 'raw' => ''),
		'bad gateway' => array('http_code' => 502, 'body' => null, 'raw' => '<html></html>'),
		'unauthorized' => array('http_code' => 401, 'body' => array('error' => 'invalid_token'), 'raw' => '{}'),
		'rate limit' => array('http_code' => 429, 'body' => null, 'raw' => ''),
	);
}

// The answers that name no record.
function parasut_stale_product_test_no_record_labels()
{
	return array('created', 'no access to company', 'bare 404', 'no route', 'page not found', 'not found, no record named', 'not found, no id', 'not found, a word for an id',
		'another title', 'refused', 'timeout', 'driver timeout', 'server error', 'bad gateway', 'unauthorized', 'rate limit');
}

// A 404 titled "Record was not found" whose detail is <Model>-<id> names
// that record; every other answer names none, whatever its code.
function test_parasut_stale_product_missing_record_reads_the_404()
{
	$answers = parasut_stale_product_test_answers();

	pg_assert_same(array('type' => 'Product', 'id' => '1079203184'), parasut_missing_record($answers['product']));
	pg_assert_same(array('type' => 'Warehouse', 'id' => '1'), parasut_missing_record($answers['warehouse']));
	pg_assert_same(array('type' => 'Contact', 'id' => '1093318880'), parasut_missing_record($answers['contact']));
	pg_assert_same(array('type' => 'ShipmentDocument', 'id' => '1003775252'), parasut_missing_record($answers['shipment']));

	foreach (parasut_stale_product_test_no_record_labels() as $label) {
		pg_assert_same(null, parasut_missing_record($answers[$label]), $label);
	}

	// Any of several errors may name it; spacing around the words is not read.
	pg_assert_same(array('type' => 'Product', 'id' => '77'), parasut_missing_record(array('http_code' => 404, 'body' => array('errors' => array(
		array('title' => 'Not Found', 'detail' => 'No route matches.'),
		'Record was not found',
		array('title' => ' Record was not found ', 'detail' => ' Product-77 '),
	)))));

	// A transport error is never taken for an answer, whatever came with it.
	pg_assert_same(null, parasut_missing_record($answers['product'] + array('curl_error' => 'Connection reset')));
	pg_assert_same(null, parasut_missing_record($answers['product'] + array('error' => 'Connection reset')));
	pg_assert_same(null, parasut_missing_record($answers['product'] + array('login_error' => 'No token')));
	pg_assert_same(null, parasut_missing_record(array()));
	pg_assert_same(null, parasut_missing_record(null));
	pg_assert_same(null, parasut_missing_record(array('http_code' => 404, 'body' => array('errors' => 'Record was not found'))));
}

// Only an id the press took from what Pinegrap keeps is forgotten, and only
// on the first answer; the default product/service is named, never
// forgotten, on either answer.
function test_parasut_stale_product_action_forgets_only_a_kept_id()
{
	$kept = array(
		'Product' => array('1079203184', ' 1079305292 '),
		'Warehouse' => array(1),
		'Contact' => array('1093318880'),
		'ShipmentDocument' => array('1003775252'),
	);

	foreach (array(
		array('type' => 'Product', 'id' => '1079203184'),
		array('type' => 'Product', 'id' => '1079305292'),
		array('type' => 'Warehouse', 'id' => '1'),
		array('type' => 'Contact', 'id' => '1093318880'),
		array('type' => 'ShipmentDocument', 'id' => '1003775252'),
	) as $missing) {
		pg_assert_same('forget', parasut_stale_kept_action($missing, $kept, '1079201749', false), $missing['type'] . ' ' . $missing['id']);
		pg_assert_same('', parasut_stale_kept_action($missing, $kept, '1079201749', true), 'resent: ' . $missing['type']);
	}

	// An id the press did not take from what is kept, a type it keeps none
	// of, a record of another type with a kept id: as before.
	pg_assert_same('', parasut_stale_kept_action(array('type' => 'Product', 'id' => '555'), $kept, '1079201749', false));
	pg_assert_same('', parasut_stale_kept_action(array('type' => 'SalesInvoice', 'id' => '1003775252'), $kept, '1079201749', false));
	pg_assert_same('', parasut_stale_kept_action(array('type' => 'Contact', 'id' => '1079203184'), $kept, '1079201749', false));
	pg_assert_same('', parasut_stale_kept_action(array('type' => 'Warehouse', 'id' => '1'), array('Product' => array('1')), '', false));
	pg_assert_same('', parasut_stale_kept_action(null, $kept, '1079201749', false));
	pg_assert_same('', parasut_stale_kept_action(array('type' => 'Product', 'id' => '1079203184'), array(), '', false));

	// The default product/service: named on either answer, even when a
	// product's card is the same id.
	pg_assert_same('default_product', parasut_stale_kept_action(array('type' => 'Product', 'id' => '1079201749'), $kept, ' 1079201749 ', false));
	pg_assert_same('default_product', parasut_stale_kept_action(array('type' => 'Product', 'id' => '1079201749'), $kept, '1079201749', true));
	pg_assert_same('default_product', parasut_stale_kept_action(array('type' => 'Product', 'id' => '1079203184'), $kept, '1079203184', false));
	pg_assert_same('', parasut_stale_kept_action(array('type' => 'Warehouse', 'id' => '1079201749'), $kept, '1079201749', false));
	pg_assert_same('', parasut_stale_kept_action(array('type' => 'Product', 'id' => '0'), array(), '', false));
}

/**
 * A send stand-in giving one answer per call, and recording the calls.
 *
 * @param array $answers  The answers in order (the last one repeats)
 * @param array $kept     What each try took from what Pinegrap keeps
 * @param int   $calls    Receives the number of calls
 * @return callable
 */
function parasut_stale_product_test_send($answers, $kept, &$calls)
{
	$calls = 0;

	return function () use ($answers, $kept, &$calls) {
		$answer = $answers[min($calls, count($answers) - 1)];
		$calls++;

		return array('answer' => $answer, 'kept' => $kept, 'press' => 'try ' . $calls);
	};
}

// A create answered 404 for a kept id: the id is forgotten, the screen told,
// and the create sent once more; what that try answers is the outcome.
function test_parasut_stale_product_send_once_more_forgets_and_sends_again()
{
	$answers = parasut_stale_product_test_answers();
	$kept = array('Product' => array('1079203184'), 'Contact' => array('1093318880'), 'Warehouse' => array('1'));

	foreach (array('product' => array('Product', '1079203184'), 'contact' => array('Contact', '1093318880'), 'warehouse' => array('Warehouse', '1')) as $label => $named) {
		$forgotten = array();
		$send = parasut_stale_product_test_send(array($answers[$label], $answers['created']), $kept, $calls);
		$sent = parasut_send_once_more($send, '1079201749', function ($type, $id) use (&$forgotten) {
			$forgotten[] = array($type, $id);

			return '';
		});

		pg_assert_same(2, $calls, $label . ': sent twice');
		pg_assert_same(array($named), $forgotten, $label . ': forgotten once');
		pg_assert_same(array(parasut_stale_kept_notice($named[0], $named[1])), $sent['notices'], $label . ': told');
		pg_assert_same('', $sent['refusal'], $label);
		pg_assert_same(array('answer' => $answers['created'], 'kept' => $kept, 'press' => 'try 2'), $sent['sent'], $label . ': the second try is the outcome');
	}
}

// Exactly one resend: a second 404, for the same id or another kept one,
// is the outcome, and the caller refuses with Paraşüt's words.
function test_parasut_stale_product_send_once_more_sends_again_only_once()
{
	$answers = parasut_stale_product_test_answers();
	$kept = array('Product' => array('1079203184'), 'Contact' => array('1093318880'));

	foreach (array(array('product', 'product'), array('product', 'contact'), array('contact', 'server error'), array('product', 'no access to company')) as $pair) {
		$forgotten = 0;
		$send = parasut_stale_product_test_send(array($answers[$pair[0]], $answers[$pair[1]], $answers['created']), $kept, $calls);
		$sent = parasut_send_once_more($send, '', function () use (&$forgotten) {
			$forgotten++;

			return '';
		});

		pg_assert_same(2, $calls, implode(' then ', $pair) . ': no third try');
		pg_assert_same(1, $forgotten, implode(' then ', $pair));
		pg_assert_same($answers[$pair[1]], $sent['sent']['answer'], implode(' then ', $pair));
		pg_assert_same('', $sent['refusal'], implode(' then ', $pair));
		pg_assert_same(1, count($sent['notices']), implode(' then ', $pair));
	}
}

// Any answer that names no kept id goes as before: one try, nothing
// forgotten, nothing said.
function test_parasut_stale_product_send_once_more_leaves_other_answers_alone()
{
	$answers = parasut_stale_product_test_answers();
	$kept = array('Product' => array('555'), 'Contact' => array('777'), 'Warehouse' => array('9'), 'ShipmentDocument' => array('8'));

	foreach (array_merge(parasut_stale_product_test_no_record_labels(), array('product', 'warehouse', 'contact', 'shipment')) as $label) {
		$forgotten = 0;
		$send = parasut_stale_product_test_send(array($answers[$label]), $kept, $calls);
		$sent = parasut_send_once_more($send, '', function () use (&$forgotten) {
			$forgotten++;

			return '';
		});

		pg_assert_same(1, $calls, $label . ': one try');
		pg_assert_same(0, $forgotten, $label . ': nothing forgotten');
		pg_assert_same(array('sent' => array('answer' => $answers[$label], 'kept' => $kept, 'press' => 'try 1'), 'notices' => array(), 'refusal' => ''), $sent, $label);
	}

	// A try that sent nothing (refused before its request) is its own outcome.
	$send = function () {
		return array('answer' => null, 'kept' => array('Product' => array('1')), 'error' => 'refused before any request');
	};
	$sent = parasut_send_once_more($send, '', function () {
		return 'never asked';
	});
	pg_assert_same(array('sent' => array('answer' => null, 'kept' => array('Product' => array('1')), 'error' => 'refused before any request'), 'notices' => array(), 'refusal' => ''), $sent);
}

// The default product/service is a setting: never forgotten, the refusal
// names it, on the first answer or after a resend.
function test_parasut_stale_product_send_once_more_names_the_default_product()
{
	$answers = parasut_stale_product_test_answers();
	$default = parasut_stale_product_test_missing('Product-1079201749');
	$kept = array('Product' => array('1079203184', '1079201749'));

	$forgotten = 0;
	$send = parasut_stale_product_test_send(array($default, $answers['created']), $kept, $calls);
	$sent = parasut_send_once_more($send, '1079201749', function () use (&$forgotten) {
		$forgotten++;

		return '';
	});

	pg_assert_same(1, $calls, 'not sent again');
	pg_assert_same(0, $forgotten, 'nothing forgotten');
	pg_assert_same(array(), $sent['notices']);
	pg_assert_same(parasut_default_product_gone_refusal('1079201749'), $sent['refusal']);
	pg_assert_same($default, $sent['sent']['answer']);

	// A product card forgotten first, then the default named.
	$forgotten = 0;
	$send = parasut_stale_product_test_send(array($answers['product'], $default, $answers['created']), $kept, $calls);
	$sent = parasut_send_once_more($send, '1079201749', function () use (&$forgotten) {
		$forgotten++;

		return '';
	});

	pg_assert_same(2, $calls);
	pg_assert_same(1, $forgotten);
	pg_assert_same(array(parasut_stale_kept_notice('Product', '1079203184')), $sent['notices']);
	pg_assert_same(parasut_default_product_gone_refusal('1079201749'), $sent['refusal']);
}

// When the create cannot be readied again (the buyer card would not open),
// it is not sent again and the reason is the refusal.
function test_parasut_stale_product_send_once_more_stops_when_it_cannot_ready_the_create()
{
	$answers = parasut_stale_product_test_answers();
	$send = parasut_stale_product_test_send(array($answers['contact'], $answers['created']), array('Contact' => array('1093318880')), $calls);
	$sent = parasut_send_once_more($send, '', function () {
		return 'Contact create failed: name is blank';
	});

	pg_assert_same(1, $calls);
	pg_assert_same('Contact create failed: name is blank', $sent['refusal']);
	pg_assert_same(array(parasut_stale_kept_notice('Contact', '1093318880')), $sent['notices']);
}

// What the screen and the ERP are told, one sentence per kind of record.
function test_parasut_stale_product_notices_and_refusal()
{
	pg_assert_same(lang(array('string' => 'Paraşüt no longer holds product card {var:1}, which Pinegrap kept for a product: the id was forgotten and Pinegrap tried once more without it.', 'vars' => array('1079203184'))), parasut_stale_kept_notice('Product', '1079203184'));
	pg_assert_same(lang(array('string' => 'Paraşüt no longer holds warehouse {var:1}, the default warehouse kept in the Paraşüt settings: the id was forgotten and Pinegrap tried once more without it.', 'vars' => array('1'))), parasut_stale_kept_notice('Warehouse', '1'));
	pg_assert_same(lang(array('string' => 'Paraşüt no longer holds buyer card {var:1}, which this order kept: the id was forgotten and Pinegrap tried once more without it.', 'vars' => array('1093318880'))), parasut_stale_kept_notice('Contact', '1093318880'));
	pg_assert_same(lang(array('string' => 'Paraşüt no longer holds shipment document {var:1}, which E-Shipment on the order screen wrote for this order: the id was forgotten and Pinegrap tried once more without it, as an ordinary invoice of the order with the shipment included.', 'vars' => array('1003775252'))), parasut_stale_kept_notice('ShipmentDocument', '1003775252'));
	pg_assert_same('', parasut_stale_kept_notice('SalesInvoice', '1'));

	pg_assert_same(lang(array('string' => 'Paraşüt no longer holds the default product/service {var:1} entered on the Paraşüt card of the commerce settings, which lines without a product card of their own go on. Enter there the id of a product or service Paraşüt holds; the document was not written.', 'vars' => array('1079201749'))), parasut_default_product_gone_refusal('1079201749'));
}

// What forgetting writes: every product holding the card, the warehouse
// setting while it holds the id, the order's buyer card while it holds it;
// nothing for another type, or for an id that is not a Paraşüt id (digits).
// The order's shipment document is cleared by _parasut_order_forget_record().
function test_parasut_stale_product_forget_sql()
{
	pg_assert_same("UPDATE products SET parasut_product_id = '' WHERE TRIM(parasut_product_id) = '1079203184'", parasut_forget_kept_id_sql('Product', ' 1079203184 ', 443));
	pg_assert_same("UPDATE config SET parasut_default_warehouse_id = '' WHERE TRIM(parasut_default_warehouse_id) = '1'", parasut_forget_kept_id_sql('Warehouse', '1'));
	pg_assert_same("UPDATE orders SET parasut_contact_id = '' WHERE id = '443' AND TRIM(parasut_contact_id) = '1093318880'", parasut_forget_kept_id_sql('Contact', '1093318880', 443));
	pg_assert_same('', parasut_forget_kept_id_sql('Product', "1'2"), 'a Paraşüt id is digits');
	pg_assert_same('', parasut_forget_kept_id_sql('Warehouse', '12a'), 'a Paraşüt id is digits');
	pg_assert_same('', parasut_forget_kept_id_sql('Contact', '1093318880', 0), 'no order, no buyer card cleared');
	pg_assert_same('', parasut_forget_kept_id_sql('Product', '  '));
	pg_assert_same('', parasut_forget_kept_id_sql('SalesInvoice', '1', 443));
	pg_assert_same('', parasut_forget_kept_id_sql('ShipmentDocument', '1', 443));

	$body = parasut_stale_product_test_source('includes/fn/parasut.php', '_parasut_forget_kept_id');
	pg_assert_true($body !== '', '_parasut_forget_kept_id() is found');
	pg_assert_contains("_parasut_order_forget_record(\$order_id, 'parasut_shipment_id', \$id);", $body);
	pg_assert_contains('db($sql);', $body);
}

// The warehouse the settings keep is used unless it is the one Paraşüt just
// answered as not found; the constant was read at init, before the clear.
function test_parasut_stale_product_kept_warehouse()
{
	pg_assert_same('1001', parasut_kept_warehouse_id('1001', ''));
	pg_assert_same('1001', parasut_kept_warehouse_id(' 1001 ', '1'));
	pg_assert_same('', parasut_kept_warehouse_id('1', '1'));
	pg_assert_same('', parasut_kept_warehouse_id(' 1 ', '1'));
	pg_assert_same('', parasut_kept_warehouse_id('', ''));
	pg_assert_same('', parasut_kept_warehouse_id(null, ''));

	$body = parasut_stale_product_test_source('includes/fn/parasut.php', 'parasut_ensure_default_warehouse');
	pg_assert_contains('function parasut_ensure_default_warehouse($token, $skip = \'\')', $body);
	parasut_stale_product_test_in_order($body, array(
		"\$kept = parasut_kept_warehouse_id(defined('PARASUT_DEFAULT_WAREHOUSE_ID') ? PARASUT_DEFAULT_WAREHOUSE_ID : '', \$skip);",
		"return ['success' => true, 'warehouse_id' => \$kept, 'error' => '', 'kept' => true];",
		"/warehouses?page[size]=1",
		"db(\"UPDATE config SET parasut_default_warehouse_id = '\" . escape(\$warehouse_id) . \"'\");",
		"return ['success' => true, 'warehouse_id' => \$warehouse_id, 'error' => '', 'kept' => false];",
	), 'the kept warehouse, or the first one fetched');
}

// The ERP driver's send takes its lines' product cards and the order's
// shipment document from what Pinegrap keeps.
function test_parasut_stale_product_driver_kept_ids()
{
	$order = array('order_id' => 443, 'order_number' => '1177', 'order_date' => '2026-10-24');

	pg_assert_same(array('Product' => array('1079203184', '1079305292'), 'ShipmentDocument' => array('1003775252')),
		erp_edoc_parasut_kept_ids(array_merge($order, array('order_shipment_id' => '1003775252')), array('1079203184', ' 1079305292 ', '')));
	pg_assert_same(array('Product' => array(), 'ShipmentDocument' => array()),
		erp_edoc_parasut_kept_ids(array_merge($order, array('order_shipment_id' => '')), array()));
	pg_assert_same(array('Product' => array('1'), 'ShipmentDocument' => array()),
		erp_edoc_parasut_kept_ids(array('order_id' => 0, 'order_shipment_id' => '1003775252'), array('1')), 'a typed invoice names no shipment');
	pg_assert_same(array('Product' => array(), 'ShipmentDocument' => array()),
		erp_edoc_parasut_kept_ids(array_merge($order, array('order_shipment_id' => '?')), null));
}

// Create Invoice: the buyer card and product cards it takes from what is
// kept are noted before anything is built, the create goes through
// parasut_send_once_more(), the screen is told what was forgotten, and a
// buyer card forgotten is opened again before the body is built again.
function test_parasut_stale_product_create_invoice_sends_once_more()
{
	$body = parasut_stale_product_test_source('includes/fn/parasut.php', 'parasut_create_invoice');

	parasut_stale_product_test_in_order($body, array(
		"'Contact' => (\$kept_contact !== '') ? array(\$kept_contact) : array(),",
		"'Product' => parasut_kept_product_cards(array_column(\$built['lines'], 'product_id')),",
		'$contact_result = parasut_sync_contact($order);',
		'$invoice_body = $build_invoice_body($parasut_contact_id);',
		'if ($sales_invoice_id === \'\') {',
		"\$inv_result = _parasut_request('POST', _parasut_prefix() . '/sales_invoices', \$invoice_body, \$token);",
		"return array('answer' => \$inv_result, 'kept' => \$kept);",
		'$sent = parasut_send_once_more($create, $default_product, function ($type, $id) use (',
		'_parasut_forget_kept_id($type, $id, $order_id);',
		"if (\$type === 'Contact') {",
		"\$order['parasut_contact_id'] = '';",
		'$contact_result = parasut_sync_contact($order);',
		'$invoice_body = $build_invoice_body($parasut_contact_id);',
		"foreach (\$sent['notices'] as \$notice) {",
		'$notices[] = $notice;',
		"if (\$sent['refusal'] !== '') {",
		"'Invoice create failed: {var:1}'",
	), 'Create Invoice');

	pg_assert_contains("\$default_product = defined('PARASUT_DEFAULT_PRODUCT_ID') ? (string) PARASUT_DEFAULT_PRODUCT_ID : '';", $body);
	pg_assert_false(strpos($body, 'ShipmentDocument') !== false, 'the shipment document was read first: not kept here');
}

// E-Shipment: each try notes what it takes from what is kept (the buyer
// card, the warehouse, the product cards), the stock step hands back the
// answer that stopped it, and a forgotten warehouse is skipped by the next
// try's lookup.
function test_parasut_stale_product_create_shipment_sends_once_more()
{
	$body = parasut_stale_product_test_source('includes/fn/parasut.php', 'parasut_create_shipment');

	parasut_stale_product_test_in_order($body, array(
		"\$skip_warehouse = '';",
		'$send = function () use (',
		"\$kept = array('Contact' => array(), 'Warehouse' => array(), 'Product' => array());",
		"\$kept['Contact'][] = trim((string) \$order['parasut_contact_id']);",
		'$contact_result = parasut_sync_contact($order);',
		"\$parasut_contact_id = \$contact_result['parasut_contact_id'];",
		// A card the first try opened is the order's: the try made once
		// more uses it instead of opening a second card.
		"\$order['parasut_contact_id'] = \$parasut_contact_id;",
		'$warehouse_result = parasut_ensure_default_warehouse($token, $skip_warehouse);',
		"if (!empty(\$warehouse_result['kept'])) {",
		"\$kept['Product'][] = \$item_parasut_product_id;",
		'$sync = parasut_sync_product($item[\'product_id\'], $token);',
		"'answer' => \$ensure['answer'] ?? null,",
		"\$result = _parasut_request('POST', _parasut_prefix() . '/shipment_documents', \$body, \$token);",
		"'answer' => \$result,",
		"\$default_product = defined('PARASUT_DEFAULT_PRODUCT_ID') ? (string) PARASUT_DEFAULT_PRODUCT_ID : '';",
		"\$sent = parasut_send_once_more(\$send, \$default_product, function (\$type, \$id) use (",
		'_parasut_forget_kept_id($type, $id, $order_id);',
		"\$order['parasut_contact_id'] = '';",
		'$skip_warehouse = $id;',
		"foreach (\$sent['notices'] as \$notice) {",
		"if (\$sent['refusal'] !== '') {",
		"return ['success' => false, 'parasut_shipment_id' => null, 'error' => \$sent['refusal']];",
	), 'E-Shipment');

	$stock = parasut_stale_product_test_source('includes/fn/parasut.php', 'parasut_ensure_product_stock_tracking');
	pg_assert_contains("'vars' => array(_parasut_extract_error(\$get)))), 'answer' => \$get];", $stock);
	pg_assert_contains("'vars' => array(_parasut_extract_error(\$patch)))), 'answer' => \$patch];", $stock);
	pg_assert_contains("'answer' => \$seed,", $stock);
}

// The ERP driver: the kept ids are noted before the body is built, the
// create goes through parasut_send_once_more(), a forgotten shipment
// document leaves the order's invoice without the link, and what was
// forgotten is said in the message or before the refusal.
function test_parasut_stale_product_driver_sends_once_more()
{
	$body = parasut_stale_product_test_source('includes/erp/edoc/parasut.php', 'erp_edoc_parasut_send_invoice');

	parasut_stale_product_test_in_order($body, array(
		'$kept = erp_edoc_parasut_kept_ids($invoice, parasut_kept_product_cards(array_column($lines, \'product_id\')));',
		'$payload = erp_edoc_parasut_invoice_payload($invoice, $lines, $contact[\'id\']);',
		'$mark(erp_edoc_parasut_unsure_mark($date_now, $contact_now));',
		"\$result = erp_edoc_parasut_request('POST', '/sales_invoices', \$payload, \$context);",
		"return array('answer' => \$result, 'kept' => \$kept);",
		"\$sent = parasut_send_once_more(\$create, defined('PARASUT_DEFAULT_PRODUCT_ID') ? (string) PARASUT_DEFAULT_PRODUCT_ID : '', function (\$type, \$id) use (",
		"_parasut_forget_kept_id(\$type, \$id, (int) (\$invoice['order_id'] ?? 0));",
		"if (\$type === 'ShipmentDocument') {",
		"\$invoice['order_shipment_id'] = '';",
		'$payload = erp_edoc_parasut_invoice_payload($invoice, $lines, $contact[\'id\']);',
		"\$told = implode(' ', \$sent['notices']);",
		"if (\$sent['refusal'] !== '') {",
		"\$mark('');",
		"'Paraşüt would not take the invoice: {var:1}'",
		"'message' => \$told,",
	), 'driver');

	$service = parasut_stale_product_test_source('includes/erp/edoc/service.php', 'erp_edoc_invoice_send');
	parasut_stale_product_test_in_order($service, array(
		"'{var:1} took the invoice (id {var:2}).'",
		"\$told = trim((string) (\$result['message'] ?? ''));",
		"\$message .= ' ' . \$told;",
		"would not hand it to GİB: {var:3}'",
		")) . ((\$told !== '') ? ' ' . \$told : ''),",
	), 'the service passes the driver\'s message on, also when the hand-over fails');
}
