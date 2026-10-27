<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for which orders the order screen's legacy Paraşüt buttons go for
 * (includes/fn/parasut.php, view_order.php): only a finished sale (the ERP's
 * billable statuses) is invoiced or shipped, in the writers as well as on
 * the screen; an order the ERP already invoiced through its e-document
 * provider gets no second invoice, and E-Shipment judges that invoice; the
 * status row says "Sent" only for an e-document whose conversion was seen
 * to finish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_bridge.php');

/**
 * The source of one function of includes/fn/parasut.php, from its
 * declaration to the next one.
 *
 * @param string $function
 * @return string  '' when the function is not found
 */
function parasut_order_gates_test_source($function)
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
function parasut_order_gates_test_get($answer, &$asked)
{
	return function ($path) use ($answer, &$asked) {
		$asked[] = $path;

		return $answer;
	};
}

/**
 * Where $needle first stands in $haystack, false when it does not.
 *
 * @param string $needle
 * @param string $haystack
 * @return int|false
 */
function parasut_order_gates_test_at($needle, $haystack)
{
	return strpos((string) $haystack, (string) $needle);
}

// erp_invoices rows of an order's sales invoice, as the ERP leaves them: sent
// through its Paraşüt provider and accepted by GİB (edoc_external_id is the
// sales invoice's id, the driver keeps it in parasut_invoice_id too), a draft
// the driver wrote, a send that got no answer (the driver's mark with the
// date and buyer card the request carried), sent through another provider,
// and issued without a provider.
function parasut_order_gates_test_erp_rows()
{
	return array(
		'parasut' => array('full_number' => 'SF2026000041', 'edoc_provider' => 'parasut', 'edoc_external_id' => '1104358636', 'parasut_invoice_id' => '1104358636', 'edoc_status' => 'accepted'),
		'parasut column only' => array('full_number' => 'SF2026000042', 'edoc_provider' => '', 'edoc_external_id' => '', 'parasut_invoice_id' => '1104358662', 'edoc_status' => 'created'),
		'unsure' => array('full_number' => 'SF2026000043', 'edoc_provider' => '', 'edoc_external_id' => '', 'parasut_invoice_id' => '?2026-10-19:1074160067', 'edoc_status' => 'error'),
		'other' => array('full_number' => 'SF2026000044', 'edoc_provider' => 'isbasi', 'edoc_external_id' => '8812', 'parasut_invoice_id' => '', 'edoc_status' => 'sent'),
		'paper' => array('full_number' => 'SF2026000045', 'edoc_provider' => '', 'edoc_external_id' => '', 'parasut_invoice_id' => '', 'edoc_status' => 'none'),
	);
}

// The statuses are the ERP's: a finished sale, complete or exported.
function test_parasut_order_gates_billable_statuses_are_the_erps()
{
	pg_assert_same(erp_order_billable_statuses(), parasut_order_billable_statuses());
	pg_assert_same(array('complete', 'exported'), parasut_order_billable_statuses());
}

// Any other status is refused in the ERP's words: a cancelled order is not
// invoiced and gets no delivery note; an unfinished checkout waits.
function test_parasut_order_gates_status_refusal()
{
	foreach (array('complete', 'exported') as $status) {
		pg_assert_same('', parasut_order_status_refusal($status, 'invoice'), $status . ' invoice');
		pg_assert_same('', parasut_order_status_refusal($status, 'shipment'), $status . ' shipment');
	}

	foreach (array('cancelled', 'canceled') as $status) {
		pg_assert_same(lang('A cancelled order is not invoiced.'), parasut_order_status_refusal($status, 'invoice'), $status);
		pg_assert_same(lang('A cancelled order gets no delivery note.'), parasut_order_status_refusal($status, 'shipment'), $status);
	}

	foreach (array('incomplete', '', 'Complete', null) as $status) {
		pg_assert_same(lang('An order is invoiced once it is complete.'), parasut_order_status_refusal($status, 'invoice'), var_export($status, true));
		pg_assert_same(lang('The order has not been completed yet; the delivery note is written once it is.'), parasut_order_status_refusal($status, 'shipment'), var_export($status, true));
	}

	// The list it is given decides, so the ERP's list is the one used.
	pg_assert_same('', parasut_order_status_refusal('complete', 'invoice', array('complete')));
	pg_assert_same(lang('An order is invoiced once it is complete.'), parasut_order_status_refusal('exported', 'invoice', array('complete')));
}

// What the order's ERP invoice holds at a provider: the Paraşüt sales
// invoice its provider wrote, the mark of a send that got no answer,
// another provider's record, or nothing.
function test_parasut_order_gates_erp_invoice_record()
{
	$rows = parasut_order_gates_test_erp_rows();

	pg_assert_same(array('state' => 'parasut', 'id' => '1104358636', 'number' => 'SF2026000041', 'edoc_status' => 'accepted'), parasut_order_erp_invoice_record($rows['parasut']));
	pg_assert_same(array('state' => 'parasut', 'id' => '1104358662', 'number' => 'SF2026000042', 'edoc_status' => 'created'), parasut_order_erp_invoice_record($rows['parasut column only']));
	pg_assert_same(array('state' => 'unsure', 'id' => '', 'number' => 'SF2026000043', 'edoc_status' => 'error'), parasut_order_erp_invoice_record($rows['unsure']));
	pg_assert_same(array('state' => 'other', 'id' => '', 'number' => 'SF2026000044', 'edoc_status' => 'sent'), parasut_order_erp_invoice_record($rows['other']));
	pg_assert_same(array('state' => 'none', 'id' => '', 'number' => '', 'edoc_status' => ''), parasut_order_erp_invoice_record($rows['paper']));
	pg_assert_same(array('state' => 'none', 'id' => '', 'number' => '', 'edoc_status' => ''), parasut_order_erp_invoice_record(null));

	// The record the provider named wins over a mark a later send left.
	$resent = array_merge($rows['parasut'], array('parasut_invoice_id' => '?2026-10-20:1074160067'));
	pg_assert_same(array('state' => 'parasut', 'id' => '1104358636', 'number' => 'SF2026000041', 'edoc_status' => 'accepted'), parasut_order_erp_invoice_record($resent));
}

// The order's ERP invoice is read as the ERP reads it: its one sales
// invoice that is not cancelled.
function test_parasut_order_gates_erp_invoice_reads_the_orders_sales_invoice()
{
	$body = parasut_order_gates_test_source('parasut_order_erp_invoice');

	pg_assert_contains("waf_table_has_column('erp_invoices', 'edoc_external_id')", $body);
	pg_assert_contains("SELECT full_number, edoc_provider, edoc_external_id, parasut_invoice_id, edoc_status FROM erp_invoices", $body);
	pg_assert_contains("WHERE order_id = '\" . (int) \$order_id . \"' AND direction = 'sales' AND doc_type = 'invoice'", $body);
	pg_assert_contains("AND status <> 'cancelled'", $body);
	pg_assert_contains('return parasut_order_erp_invoice_record(', $body);
}

// Create Invoice on an order the ERP invoiced through a provider is refused
// in words that say where the invoice is; a paper ERP invoice does not stop
// it (the ERP with no provider leaves the e-document to these buttons).
function test_parasut_order_gates_create_invoice_refuses_an_order_the_erp_invoiced()
{
	$rows = parasut_order_gates_test_erp_rows();
	$order = array('status' => 'complete', 'parasut_invoice_id' => '');

	pg_assert_same(lang(array(
		'string' => 'This order was invoiced through the ERP: its invoice {var:1} went to Paraşüt as sales invoice {var:2}. A second sales invoice was not created.',
		'vars' => array('SF2026000041', '1104358636'),
	)), parasut_order_press_refusal($order, 'invoice', parasut_order_erp_invoice_record($rows['parasut'])));

	pg_assert_same(lang(array(
		'string' => 'This order was invoiced through the ERP, and the send of its invoice {var:1} to Paraşüt got no answer, so Paraşüt may hold it. A second sales invoice was not created.',
		'vars' => array('SF2026000043'),
	)), parasut_order_press_refusal($order, 'invoice', parasut_order_erp_invoice_record($rows['unsure'])));

	pg_assert_same(lang(array(
		'string' => 'This order was invoiced through the ERP: its invoice {var:1} went to GİB through the ERP\'s e-document provider. A second invoice was not created in Paraşüt.',
		'vars' => array('SF2026000044'),
	)), parasut_order_press_refusal($order, 'invoice', parasut_order_erp_invoice_record($rows['other'])));

	pg_assert_same('', parasut_order_press_refusal($order, 'invoice', parasut_order_erp_invoice_record($rows['paper'])));
	pg_assert_same('', parasut_order_press_refusal($order, 'invoice', parasut_order_erp_invoice_record(null)));

	// The status is asked first.
	pg_assert_same(lang('A cancelled order is not invoiced.'), parasut_order_press_refusal(array('status' => 'cancelled'), 'invoice', parasut_order_erp_invoice_record($rows['parasut'])));
	pg_assert_same(lang('A cancelled order gets no delivery note.'), parasut_order_press_refusal(array('status' => 'cancelled'), 'shipment', parasut_order_erp_invoice_record(null)));
}

// E-Shipment on an order the ERP invoiced through Paraşüt goes to the link
// rule with that invoice; a send of it that got no answer refuses, as the
// order's own unsure mark does; the order's own invoice comes first.
function test_parasut_order_gates_shipment_takes_the_erps_invoice()
{
	$rows = parasut_order_gates_test_erp_rows();
	$complete = array('status' => 'complete', 'parasut_invoice_id' => '');

	pg_assert_same('', parasut_order_press_refusal($complete, 'shipment', parasut_order_erp_invoice_record($rows['parasut'])));
	pg_assert_same('', parasut_order_press_refusal($complete, 'shipment', parasut_order_erp_invoice_record($rows['other'])));
	pg_assert_same(lang(array(
		'string' => 'The send of this order\'s ERP invoice {var:1} to Paraşüt got no answer, so it is not known whether a sales invoice carrying the shipment is there. No shipment document was created.',
		'vars' => array('SF2026000043'),
	)), parasut_order_press_refusal($complete, 'shipment', parasut_order_erp_invoice_record($rows['unsure'])));
	pg_assert_same('', parasut_order_press_refusal(array('status' => 'exported', 'parasut_invoice_id' => '5001'), 'shipment', parasut_order_erp_invoice_record($rows['unsure'])));

	pg_assert_same(array('invoice_id' => '1104358636', 'erp' => true), parasut_shipment_invoice_id('', parasut_order_erp_invoice_record($rows['parasut'])));
	pg_assert_same(array('invoice_id' => '5001', 'erp' => false), parasut_shipment_invoice_id(' 5001 ', parasut_order_erp_invoice_record($rows['parasut'])));
	pg_assert_same(array('invoice_id' => '?', 'erp' => false), parasut_shipment_invoice_id('?', parasut_order_erp_invoice_record(null)));
	pg_assert_same(array('invoice_id' => '', 'erp' => false), parasut_shipment_invoice_id('', parasut_order_erp_invoice_record($rows['other'])));
	pg_assert_same(array('invoice_id' => '', 'erp' => false), parasut_shipment_invoice_id('', parasut_order_erp_invoice_record($rows['paper'])));

	// Never sent unlinked on a mark: the link refuses the mark if it is reached.
	$unsure = parasut_shipment_invoice_id('', parasut_order_erp_invoice_record($rows['unsure']));
	pg_assert_true(parasut_order_invoice_unsure($unsure['invoice_id']), 'the ERP\'s mark is handed on');
	pg_assert_true($unsure['erp'], 'as the ERP\'s');
}

// The link rule (#1506) judges the ERP's record as it judges the order's
// own: the driver's invoice of an order not shipped carries the shipment
// (Paraşüt's default) and refuses; one sent without shipment (#1530; live
// 2026-10-24: 1104358636 read shipment_included false) is named.
function test_parasut_order_gates_link_reads_the_erps_record()
{
	$rows = parasut_order_gates_test_erp_rows();
	$judged = parasut_shipment_invoice_id('', parasut_order_erp_invoice_record($rows['parasut']));

	$asked = array();
	$with = parasut_shipment_invoice_link($judged['invoice_id'], parasut_order_gates_test_get(array('http_code' => 200, 'body' => array('data' => array(
		'id' => '1104358636', 'type' => 'sales_invoices', 'attributes' => array('item_type' => 'invoice', 'shipment_included' => true),
	))), $asked));
	pg_assert_same(array('/sales_invoices/1104358636'), $asked);
	pg_assert_same('', $with['invoice_id']);
	pg_assert_contains('(ID: 1104358636) carries the shipment', $with['error']);

	$asked = array();
	$without = parasut_shipment_invoice_link($judged['invoice_id'], parasut_order_gates_test_get(array('http_code' => 200, 'body' => array('data' => array(
		'id' => '1104358636', 'type' => 'sales_invoices', 'attributes' => array('item_type' => 'invoice', 'shipment_included' => false),
	))), $asked));
	pg_assert_same(array('/sales_invoices/1104358636'), $asked);
	pg_assert_same(array('invoice_id' => '1104358636', 'error' => ''), $without);
}

// Create Invoice refuses a status or an ERP invoice right after the order is
// read, before anything is asked of Paraşüt or written.
function test_parasut_order_gates_create_invoice_refuses_before_any_request()
{
	$body = parasut_order_gates_test_source('parasut_create_invoice');
	$read = parasut_order_gates_test_at('$order = mysqli_fetch_assoc($result);', $body);
	$gate = parasut_order_gates_test_at("\$refusal = parasut_order_press_refusal(\$order, 'invoice', parasut_order_erp_invoice((int) \$order['id']));", $body);
	$returned = parasut_order_gates_test_at("if (\$refusal !== '') {\n        return ['success' => false, 'parasut_invoice_id' => null, 'error' => \$refusal];", $body);

	pg_assert_true(($read !== false) && ($gate !== false) && ($read < $gate), 'after the order is read');
	pg_assert_true(($returned !== false) && ($gate < $returned), 'a refusal returns');

	foreach (array('_parasut_stored_record_check(', 'parasut_get_token(', '_parasut_request(', 'db("UPDATE', 'db_items(', 'parasut_sync_contact(') as $call) {
		$at = parasut_order_gates_test_at($call, $body);
		pg_assert_true(($at !== false) && ($returned !== false) && ($returned < $at), 'refused before ' . $call);
	}
}

// E-Shipment refuses a status, and an ERP send that got no answer, right
// after the order is read; the link judges the invoice the order's own id
// or the ERP's record gives, and only the order's own id is cleared when
// Paraşüt no longer holds it.
function test_parasut_order_gates_create_shipment_refuses_before_any_request()
{
	$body = parasut_order_gates_test_source('parasut_create_shipment');
	$read = parasut_order_gates_test_at('$order = mysqli_fetch_assoc($result);', $body);
	$erp = parasut_order_gates_test_at("\$erp_invoice = parasut_order_erp_invoice((int) \$order['id']);", $body);
	$gate = parasut_order_gates_test_at("\$refusal = parasut_order_press_refusal(\$order, 'shipment', \$erp_invoice);", $body);
	$returned = parasut_order_gates_test_at("if (\$refusal !== '') {\n        return ['success' => false, 'parasut_shipment_id' => null, 'error' => \$refusal];", $body);

	pg_assert_true(($read !== false) && ($erp !== false) && ($gate !== false) && ($read < $erp) && ($erp < $gate), 'after the order is read');
	pg_assert_true(($returned !== false) && ($gate < $returned), 'a refusal returns');

	foreach (array('_parasut_stored_record_check(', 'parasut_get_token(', '_parasut_request(', 'db("UPDATE', 'parasut_sync_contact(', '_parasut_order_forget_record(') as $call) {
		$at = parasut_order_gates_test_at($call, $body);
		pg_assert_true(($at !== false) && ($returned !== false) && ($returned < $at), 'refused before ' . $call);
	}

	$judged = parasut_order_gates_test_at("\$invoice_to_judge = parasut_shipment_invoice_id((string) (\$order['parasut_invoice_id'] ?? ''), \$erp_invoice);", $body);
	$link = parasut_order_gates_test_at("\$invoice_link = parasut_shipment_invoice_link(\$invoice_to_judge['invoice_id'], function (\$path) use (\$token) {", $body);
	$own = parasut_order_gates_test_at("if (!\$invoice_to_judge['erp']) {\n            _parasut_order_forget_record(\$order_id, 'parasut_invoice_id', \$invoice_link['gone']);", $body);

	pg_assert_true(($judged !== false) && ($link !== false) && ($judged < $link), 'the link judges the invoice chosen');
	pg_assert_true(($own !== false) && ($link < $own), 'only the order\'s own id is cleared');
}

// "Sent" only for a conversion seen to finish (orders.parasut_exported);
// a sales invoice whose e-document failed, was rejected or was still being
// processed says so, with its id. The unsure mark is unchanged.
function test_parasut_order_gates_invoice_badge_says_sent_only_when_confirmed()
{
	$sent = parasut_order_invoice_status_badge('5001', false, true);
	pg_assert_contains('theme-success">' . lang('Sent') . '</span>', $sent);
	pg_assert_contains(lang('Parasut ID') . ': 5001', $sent);
	pg_assert_contains('>' . lang('Sent') . '</span>', parasut_order_invoice_status_badge('5001', true, true));

	foreach (array(array('5001', false), array('5001', true)) as $case) {
		$open = parasut_order_invoice_status_badge($case[0], $case[1], false);

		pg_assert_false(strpos($open, '>' . lang('Sent') . '<') !== false, 'not "Sent"');
		pg_assert_contains('theme-warning', $open);
		pg_assert_contains(lang('Parasut ID') . ': 5001', $open);
		pg_assert_contains(h(lang('Pinegrap did not see the conversion finish: the e-document failed, was rejected or was still being processed. Check the invoice in Paraşüt.')), $open);
	}

	pg_assert_contains(h(lang('Sales invoice created, e-document not confirmed')) . '</span>', parasut_order_invoice_status_badge('5001', false, false));
	pg_assert_contains(h(lang('E-document not confirmed')) . '</span>', parasut_order_invoice_status_badge('5001', true, false));

	// Unconfirmed is what a caller that does not say gets.
	pg_assert_same(parasut_order_invoice_status_badge('5001', false, false), parasut_order_invoice_status_badge('5001'));

	pg_assert_contains(lang('Not Sent'), parasut_order_invoice_status_badge('', false, false));
	pg_assert_same('', parasut_order_invoice_status_badge('', true, true));
	pg_assert_contains(h(lang('Check Paraşüt')), parasut_order_invoice_status_badge('?', false, true));
}

// The status row of an order the ERP invoiced through a provider says so,
// with the ERP invoice's number and the Paraşüt id; the order's own record,
// if it has one, is shown beside it.
function test_parasut_order_gates_row_badge_names_the_erps_invoice()
{
	$rows = parasut_order_gates_test_erp_rows();

	$erp = parasut_order_invoice_row_badge('', false, parasut_order_erp_invoice_record($rows['parasut']));
	pg_assert_contains('theme-success">' . h(lang('Invoiced through the ERP')) . '</span>', $erp);
	pg_assert_contains('SF2026000041', $erp);
	pg_assert_contains(lang('Parasut ID') . ': 1104358636', $erp);
	pg_assert_false(strpos($erp, lang('Not Sent')) !== false, 'not "Not Sent"');

	$unsure = parasut_order_invoice_row_badge('', false, parasut_order_erp_invoice_record($rows['unsure']));
	pg_assert_contains(h(lang('Invoiced through the ERP')), $unsure);
	pg_assert_contains(h(lang('Check Paraşüt')), $unsure);
	pg_assert_false(strpos($unsure, lang('Parasut ID')) !== false, 'the mark is not an id');

	$other = parasut_order_invoice_row_badge('', false, parasut_order_erp_invoice_record($rows['other']));
	pg_assert_contains(h(lang('Invoiced through the ERP')), $other);
	pg_assert_contains('SF2026000044', $other);
	pg_assert_false(strpos($other, '8812') !== false, 'another provider\'s id is not a Paraşüt id');

	$both = parasut_order_invoice_row_badge('5001', true, parasut_order_erp_invoice_record($rows['parasut']));
	pg_assert_contains(h(lang('Invoiced through the ERP')), $both);
	pg_assert_contains(lang('Parasut ID') . ': 5001', $both);

	pg_assert_same(parasut_order_invoice_status_badge('5001', false, true), parasut_order_invoice_row_badge('5001', true, parasut_order_erp_invoice_record($rows['paper'])));
	pg_assert_same(parasut_order_invoice_status_badge('', false, false), parasut_order_invoice_row_badge('', false, parasut_order_erp_invoice_record(null)));

	// Values are escaped.
	$odd = parasut_order_invoice_row_badge('', false, parasut_order_erp_invoice_record(array('full_number' => '<b>', 'edoc_provider' => 'parasut', 'edoc_external_id' => '"1"', 'parasut_invoice_id' => '')));
	pg_assert_false(strpos($odd, '<b>') !== false, 'number escaped');
	pg_assert_contains('&lt;b&gt;', $odd);
	pg_assert_contains('&quot;1&quot;', $odd);
}

// The ERP's badge takes the colour of the ERP invoice's e-document state and
// names that state: green only once GİB accepted the document. A draft at the
// provider, a document still awaiting GİB, an error, a rejection or a send
// that got no answer is not shown as filed.
function test_parasut_order_gates_erp_badge_follows_the_edoc_state()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/service.php');

	$labels = erp_edoc_status_labels();
	$rows = parasut_order_gates_test_erp_rows();
	$classes = array(
		'accepted' => 'theme-success',
		'sent' => 'theme-primary',
		'created' => 'theme-warning',
		'error' => 'theme-danger',
		'rejected' => 'theme-danger',
		'cancelled' => 'theme-secondary',
		'' => 'theme-secondary',
	);

	foreach (array('parasut', 'other') as $kind) {
		foreach ($classes as $status => $class) {
			$row = $rows[$kind];
			$row['edoc_status'] = $status;
			$record = parasut_order_erp_invoice_record($row);
			$badge = parasut_order_erp_invoice_badge($record);

			pg_assert_same($status, $record['edoc_status'], $kind . ' ' . $status . ': the record keeps the state');
			pg_assert_contains('<span class="badge ' . $class . '">' . h(lang('Invoiced through the ERP')) . '</span>', $badge, $kind . ' ' . $status);

			if ($status !== '') {
				pg_assert_contains(h($labels[$status][0]), $badge, $kind . ' ' . $status . ': the state is named');
			}
		}
	}

	// A send that got no answer is a warning, whatever state the row was left in.
	$unsure = parasut_order_erp_invoice_badge(parasut_order_erp_invoice_record($rows['unsure']));
	pg_assert_contains('<span class="badge theme-warning">' . h(lang('Invoiced through the ERP')) . '</span>', $unsure);
	pg_assert_contains(h(lang('Check Paraşüt')), $unsure);

	// The order screen reads the state with the rest of the row.
	pg_assert_contains('SELECT full_number, edoc_provider, edoc_external_id, parasut_invoice_id, edoc_status FROM erp_invoices', parasut_order_gates_test_source('parasut_order_erp_invoice'));
}

// The order screen: a post refuses a status before the contact and VKN/TCKN
// checks; the buttons and their forms are drawn only for a finished sale;
// Create Invoice is not drawn for an order the ERP invoiced through a
// provider; the badges are told whether the conversion was seen to finish.
function test_parasut_order_gates_order_screen()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_order.php');

	$select = parasut_order_gates_test_at("\$parasut_order = db_item(\"SELECT status, contact_id, custom_field_1, custom_field_2, parasut_shipment_id FROM orders WHERE id = '\$order_id'\");", $source);
	$status = parasut_order_gates_test_at("\$parasut_status_refusal = parasut_order_status_refusal(\$parasut_order['status'] ?? '', (\$parasut_action === 'e_irsaliye') ? 'shipment' : 'invoice');", $source);
	$contact = parasut_order_gates_test_at('if (!$parasut_contact_id) {', $source);
	$identity = parasut_order_gates_test_at('$parasut_identity_refusal = _parasut_shipment_identity_refusal(', $source);
	pg_assert_true(($select !== false) && ($status !== false) && ($select < $status), 'the status is read with the order');
	pg_assert_true(($contact !== false) && ($identity !== false) && ($status < $contact) && ($status < $identity), 'and refused first');

	pg_assert_true(parasut_order_gates_test_at("\$parasut_billable = (parasut_order_status_refusal(\$status, 'invoice') === '');", $source) !== false, 'the screen asks the status');
	pg_assert_true(parasut_order_gates_test_at("if (\$parasut_in_erp || !\$parasut_billable) {\n            \$output_parasut_buttons = '';\n            \$output_parasut_forms = '';", $source) !== false, 'no buttons or forms for another status');

	pg_assert_true(parasut_order_gates_test_at("\$parasut_erp_invoice = parasut_order_erp_invoice((int) \$_GET['id']);", $source) !== false, 'the ERP invoice is read');
	pg_assert_true(parasut_order_gates_test_at("\$output_parasut_invoice_button = (\$parasut_erp_invoice['state'] === 'none')", $source) !== false, 'Create Invoice only without one');

	pg_assert_true(parasut_order_gates_test_at("\$parasut_exported = !empty(\$row['parasut_exported']);", $source) !== false, 'the flag is read with the order');
	pg_assert_true(parasut_order_gates_test_at('$output_parasut_invoice_badge = parasut_order_invoice_status_badge($parasut_invoice_id, true, $parasut_exported);', $source) !== false, 'the button badge');
	pg_assert_true(parasut_order_gates_test_at('$invoice_row_badge  = parasut_order_invoice_row_badge($parasut_invoice_id, $parasut_exported, $parasut_erp_invoice);', $source) !== false, 'the row badge');
}
