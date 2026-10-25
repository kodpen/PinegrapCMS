<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ERP's Paraşüt send of an order that the order screen's
 * legacy Create Invoice already invoiced at Paraşüt (orders.parasut_invoice_id,
 * includes/erp/edoc/parasut.php). The unsure mark of an unanswered create
 * refuses before any request; a record id is read first: a record Paraşüt
 * holds refuses naming it, a 404 naming it as not found clears the id and
 * the send goes on, any other answer refuses with Paraşüt's words.
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
 * A GET stand-in that records the paths it is asked for and gives one answer.
 *
 * @param array $answer  The erp_edoc_parasut_request() answer to give
 * @param array $asked   Receives each path asked for
 * @return callable
 */
function erp_edoc_parasut_legacy_invoiced_test_get($answer, &$asked)
{
	return function ($path) use ($answer, &$asked) {
		$asked[] = $path;

		return $answer;
	};
}

/**
 * The legacy invoice check, or a failed assertion when it does not exist.
 *
 * @param string $value  orders.parasut_invoice_id
 * @param array  $answer
 * @param array  $asked
 * @return array|null
 */
function erp_edoc_parasut_legacy_invoiced_test_check($value, $answer, &$asked)
{
	if (!function_exists('erp_edoc_parasut_legacy_invoice_check')) {
		pg_assert_true(false, 'erp_edoc_parasut_legacy_invoice_check() exists');

		return null;
	}

	return erp_edoc_parasut_legacy_invoice_check($value, erp_edoc_parasut_legacy_invoiced_test_get($answer, $asked));
}

/**
 * The source of erp_edoc_parasut_send_invoice().
 *
 * @return string
 */
function erp_edoc_parasut_legacy_invoiced_test_send_source()
{
	$function = new ReflectionFunction('erp_edoc_parasut_send_invoice');

	return implode('', array_slice(file($function->getFileName()), $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

/**
 * Whether the needles are found in this order in the source.
 *
 * @param string $source
 * @param array  $needles
 * @param string $label
 * @return bool
 */
function erp_edoc_parasut_legacy_invoiced_test_in_order($source, $needles, $label)
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

// What Paraşüt answered, live on the demo company (2026-10-24), to a read of
// a sales invoice that is there and of one deleted on its screen, and to a
// read sent to a company number the token's user cannot reach; then answers
// that say nothing about the record, in the shape erp_edoc_parasut_request()
// gives them ('error' for the transport, 'login_error' for the token).
function erp_edoc_parasut_legacy_invoiced_test_answers()
{
	$none = array('http_code' => 0, 'body' => null, 'raw' => '', 'error' => '', 'duration_ms' => 0, 'login_error' => '');

	return array(
		'found' => array_merge($none, array('http_code' => 200, 'body' => array('data' => array('id' => '1104385591', 'type' => 'sales_invoices', 'attributes' => array('shipment_included' => true, 'net_total' => '47.94'))), 'raw' => '{"data":{}}')),
		'deleted' => array_merge($none, array('http_code' => 404, 'body' => array('errors' => array(array('title' => 'Record was not found', 'detail' => 'SalesInvoice-1104385591'))), 'raw' => '{"errors":[{"title":"Record was not found","detail":"SalesInvoice-1104385591"}]}')),
		'no access to company' => array_merge($none, array('http_code' => 401, 'body' => array('errors' => array(array('title' => 'No access to company: 999999999'))), 'raw' => '{"errors":[{"title":"No access to company: 999999999"}]}')),
		'no route' => array_merge($none, array('http_code' => 404, 'body' => array('errors' => array(array('title' => 'Not Found', 'detail' => 'No route matches.'))), 'raw' => '{"errors":[{"title":"Not Found","detail":"No route matches."}]}')),
		'bare 404' => array_merge($none, array('http_code' => 404, 'body' => array('status' => 404, 'error' => 'Not Found'), 'raw' => '{"status":404,"error":"Not Found"}')),
		'another record' => array_merge($none, array('http_code' => 404, 'body' => array('errors' => array(array('title' => 'Record was not found', 'detail' => 'ShipmentDocument-1003775252'))), 'raw' => '{}')),
		'timeout' => array_merge($none, array('error' => 'Operation timed out after 40001 milliseconds')),
		'not connected' => array_merge($none, array('login_error' => 'Pinegrap is not connected to Paraşüt.')),
		'server error' => array_merge($none, array('http_code' => 500, 'raw' => '')),
		'rate limit' => array_merge($none, array('http_code' => 429, 'raw' => '')),
	);
}

// An order with nothing in the column asks Paraşüt nothing and goes on.
function test_erp_edoc_parasut_legacy_invoiced_none_reads_nothing()
{
	foreach (array('', '   ') as $value) {
		$asked = array();
		$check = erp_edoc_parasut_legacy_invoiced_test_check($value, erp_edoc_parasut_legacy_invoiced_test_answers()['found'], $asked);

		if ($check === null) {
			return;
		}

		pg_assert_same('none', $check['state'], var_export($value, true));
		pg_assert_same('', $check['refusal']);
		pg_assert_same('', $check['notice']);
		pg_assert_same(array(), $asked, 'nothing read');
	}
}

// The unsure mark of a Create Invoice whose create got no answer: Paraşüt
// may hold the sale's invoice, and the mark is no record to read, so the
// send refuses before any request.
function test_erp_edoc_parasut_legacy_invoiced_unsure_mark_refuses_unread()
{
	foreach (array(ERP_EDOC_PARASUT_UNSURE, ' ' . ERP_EDOC_PARASUT_UNSURE . ' ', ERP_EDOC_PARASUT_UNSURE . '2026-10-24:1074177903') as $value) {
		$asked = array();
		$check = erp_edoc_parasut_legacy_invoiced_test_check($value, erp_edoc_parasut_legacy_invoiced_test_answers()['deleted'], $asked);

		if ($check === null) {
			return;
		}

		pg_assert_same('unsure', $check['state'], var_export($value, true));
		pg_assert_same(lang('The order screen\'s Create Invoice got no answer from Paraşüt for this order, so Paraşüt may already hold its sales invoice. This invoice was not sent there, so that the sale is not invoiced twice: look for the record on Paraşüt\'s screen; if it is there, keep this invoice as a PDF document.'), $check['refusal']);
		pg_assert_same(array(), $asked, 'nothing read');
	}
}

// A record Paraşüt holds is the sale's invoice: the send refuses naming it,
// read once with the driver's path. The record may be one whose e-document
// never went through (the legacy create kept the id then too), so the
// refusal says where to issue it.
function test_erp_edoc_parasut_legacy_invoiced_kept_record_refuses_naming_it()
{
	$asked = array();
	$check = erp_edoc_parasut_legacy_invoiced_test_check(' 1104385591 ', erp_edoc_parasut_legacy_invoiced_test_answers()['found'], $asked);

	if ($check === null) {
		return;
	}

	pg_assert_same('kept', $check['state']);
	pg_assert_same('1104385591', $check['id']);
	pg_assert_same(array('/sales_invoices/1104385591'), $asked);
	pg_assert_same(lang(array(
		'string' => 'This order was already invoiced at Paraşüt from the order screen (sales invoice ID: {var:1}), so this invoice was not sent there: the same sale would be invoiced twice. Keep this invoice as a PDF document; if that record\'s e-document did not go through, issue it from that invoice in Paraşüt. Or delete that record on Paraşüt\'s screen first and send again.',
		'vars' => array('1104385591'),
	)), $check['refusal']);
	pg_assert_same('', $check['notice']);
}

// A record deleted on Paraşüt's screen (404 "Record was not found",
// SalesInvoice-<id>) is no invoice of the sale: no refusal, the id to clear
// and what the operator is told.
function test_erp_edoc_parasut_legacy_invoiced_deleted_record_is_left_out()
{
	$asked = array();
	$check = erp_edoc_parasut_legacy_invoiced_test_check('1104385591', erp_edoc_parasut_legacy_invoiced_test_answers()['deleted'], $asked);

	if ($check === null) {
		return;
	}

	pg_assert_same('gone', $check['state']);
	pg_assert_same('1104385591', $check['id']);
	pg_assert_same('', $check['refusal']);
	pg_assert_same(array('/sales_invoices/1104385591'), $asked);
	pg_assert_same(lang(array(
		'string' => 'The sales invoice the order screen created at Paraşüt for this order (ID: {var:1}) is no longer there: its id was removed from the order.',
		'vars' => array('1104385591'),
	)), $check['notice']);
}

// Every other answer says nothing about the record: the 401 of a company
// number the token's user cannot reach, a 404 with no route or naming no
// sales invoice, no connection, no token, a 5xx, a 429. The send refuses
// with Paraşüt's words, one closing period dropped, and the id stays.
function test_erp_edoc_parasut_legacy_invoiced_other_answers_refuse_with_parasut_words()
{
	$answers = erp_edoc_parasut_legacy_invoiced_test_answers();
	$words = array(
		'no access to company' => 'No access to company: 999999999',
		'no route' => 'Not Found: No route matches',
		'bare 404' => 'Not Found',
		'another record' => 'Record was not found: ShipmentDocument-1003775252',
		'timeout' => 'Operation timed out after 40001 milliseconds',
		'not connected' => 'Pinegrap is not connected to Paraşüt',
		'server error' => 'HTTP 500',
		'rate limit' => 'HTTP 429',
	);

	foreach ($words as $label => $said) {
		$asked = array();
		$check = erp_edoc_parasut_legacy_invoiced_test_check('1104385591', $answers[$label], $asked);

		if ($check === null) {
			return;
		}

		pg_assert_same('unknown', $check['state'], $label);
		pg_assert_same(lang(array(
			'string' => 'This order has a sales invoice at Paraşüt from the order screen (ID: {var:1}), and Paraşüt could not be asked whether it is still there: {var:2}. This invoice was not sent; try again in a minute.',
			'vars' => array('1104385591', $said),
		)), $check['refusal'], $label);
		pg_assert_false(strpos($check['refusal'], '..') !== false, $label . ': no double period');
		pg_assert_same('', $check['notice'], $label);
	}
}

// The send itself: an order whose column holds the unsure mark is refused
// before the buyer's card is looked for, so nothing reaches Paraşüt, and
// before the invoice's own checks, so the operator is not first sent to fix
// a line or the account card of an invoice that cannot go anyway. The
// runner loads no configuration and has no Paraşüt company, so a send that
// went on would stop at its first request instead of reaching Paraşüt.
function test_erp_edoc_parasut_legacy_invoiced_send_refuses_the_unsure_mark_first()
{
	if (!pg_assert_false(defined('PARASUT_COMPANY_ID'), 'no Paraşüt company in the runner')) {
		return;
	}

	$invoice = array(
		'id' => 9001,
		'direction' => 'sales',
		'doc_type' => 'invoice',
		'invoice_type' => 'SATIS',
		'account_id' => 7,
		'order_id' => 443,
		'order_number' => '1177',
		'order_date' => '2026-10-24',
		'order_shipment_id' => '',
		'order_parasut_invoice_id' => ERP_EDOC_PARASUT_UNSURE,
		'issue_date' => '2026-10-25',
		'currency' => 'TRY',
		'grand_total' => 1200,
		'is_internet_sale' => 0,
		'parasut_invoice_id' => '',
	);
	$lines = array(array(
		'product_id' => 45, 'description' => 'Deneme', 'quantity' => '1.0000', 'unit_price' => 1000, 'line_total' => 1000,
		'discount_rate' => '0.000', 'discount_amount' => 0, 'tax_rate' => '20.000', 'tax_total' => 200, 'tax2_amount' => 0, 'withholding_rate' => '0.000',
	));
	$party = array(
		'title' => 'Deneme Ticaret A.Ş.', 'is_person' => false, 'tax_number' => '1234567808', 'tax_office' => 'Konak',
		'address' => 'Cumhuriyet Bulvarı 1', 'district' => 'Konak', 'city' => 'İzmir', 'country_code' => 'TR', 'provider_code' => '1074177903',
	);

	$unsure = lang('The order screen\'s Create Invoice got no answer from Paraşüt for this order, so Paraşüt may already hold its sales invoice. This invoice was not sent there, so that the sale is not invoiced twice: look for the record on Paraşüt\'s screen; if it is there, keep this invoice as a PDF document.');
	$result = erp_edoc_parasut_send_invoice($invoice, $lines, array('party' => $party));

	pg_assert_false($result['success']);
	pg_assert_same('', $result['external_id']);
	pg_assert_same($unsure, $result['error']);

	// A line below zero and an account card with no address: each a refusal
	// of its own, both after the order's own invoice at Paraşüt.
	$lines[] = array_merge($lines[0], array('description' => 'Kupon', 'unit_price' => -100, 'line_total' => -100, 'tax_total' => -20));
	$party['address'] = '';
	$result = erp_edoc_parasut_send_invoice($invoice, $lines, array('party' => $party));

	pg_assert_false($result['success']);
	pg_assert_same($unsure, $result['error'], 'the order\'s invoice at Paraşüt is said first');
}

// The send reads the order's own Paraşüt invoice with its number, day and
// shipment document, asks about it through the driver's request before the
// invoice's own checks and the buyer's card, refuses on the check's
// refusal, clears a deleted record's id only while the order holds it, and
// says so with every outcome.
function test_erp_edoc_parasut_legacy_invoiced_send_asks_before_the_buyer_card()
{
	$source = erp_edoc_parasut_legacy_invoiced_test_send_source();

	pg_assert_true((bool) preg_match('/SELECT [^"]*parasut_invoice_id[^"]* FROM orders WHERE id/', $source), 'the order read takes parasut_invoice_id');
	pg_assert_true(strpos($source, "!isset(\$invoice['order_parasut_invoice_id'])") !== false, 'the order is read when its Paraşüt invoice is not given');

	erp_edoc_parasut_legacy_invoiced_test_in_order($source, array(
		"\$context = array('doc_type' => 'invoice', 'doc_id' => (int) \$invoice['id']);",
		"\$invoice['order_parasut_invoice_id'] = is_array(\$order) ? trim((string) \$order['parasut_invoice_id']) : '';",
		"\$legacy = erp_edoc_parasut_legacy_invoice_check((string) (\$invoice['order_parasut_invoice_id'] ?? ''), function (\$path) use (\$context) {",
		"return erp_edoc_parasut_request('GET', \$path, null, \$context);",
		"if (\$legacy['refusal'] !== '') {",
		"return \$fail(\$legacy['refusal']);",
		"if (\$legacy['state'] === 'gone') {",
		"_parasut_order_forget_record((int) \$invoice['order_id'], 'parasut_invoice_id', \$legacy['id']);",
		"\$legacy_notice = \$legacy['notice'];",
		"\$fail = function (\$error) use (\$fail, \$legacy_notice) {",
		"\$rate_refusal = erp_edoc_parasut_vat_rate_refusal(\$lines);",
		"\$product_refusal = erp_edoc_parasut_product_refusal(\$lines);",
		"\$missing = erp_edoc_parasut_party_missing(\$party, \$invoice);",
		"\$document_missing = erp_edoc_parasut_document_missing(\$invoice);",
		"\$contact = erp_edoc_parasut_contact_for(\$party, \$tax_number, \$context);",
		"\$told = trim(\$legacy_notice . ' ' . \$told);",
		"'message' => \$told,",
	), 'legacy invoice read before the invoice\'s own checks and the buyer card');
}
