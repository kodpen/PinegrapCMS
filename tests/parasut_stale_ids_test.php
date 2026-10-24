<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order screen's legacy Paraşüt buttons on an order that keeps
 * the id of a record deleted on Paraşüt's screen since (includes/fn/parasut.php,
 * view_order.php): a press reads the stored record first; one Paraşüt answers
 * 404 for is left out, its id cleared on the order and the screen told; a 200
 * goes on as before; any other answer refuses as before, nothing guessed.
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
function parasut_stale_ids_test_source($function)
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
function parasut_stale_ids_test_get($answer, &$asked)
{
	return function ($path) use ($answer, &$asked) {
		$asked[] = $path;

		return $answer;
	};
}

/**
 * pg_assert_contains() for a whole source file, which would otherwise be
 * printed in full on a failure.
 *
 * @param string $needle
 * @param string $source
 * @param string $label
 * @return bool
 */
function parasut_stale_ids_test_has($needle, $source, $label)
{
	return pg_assert_true(strpos((string) $source, (string) $needle) !== false, $label . ': ' . $needle);
}

// What Paraşüt answered, live on the demo company, for a record that is
// there and for one deleted on its screen, and the answers that say nothing.
function parasut_stale_ids_test_answers()
{
	return array(
		'found' => array('http_code' => 200, 'body' => array('data' => array('id' => '1104339388', 'type' => 'sales_invoices', 'attributes' => array('shipment_included' => false))), 'raw' => '{"data":{}}'),
		'deleted' => array('http_code' => 404, 'body' => array('errors' => array(array('title' => 'Record was not found', 'detail' => 'Record was not found'))), 'raw' => '{"errors":[]}'),
		'timeout' => array('http_code' => 0, 'body' => null, 'raw' => '', 'curl_error' => 'Operation timed out after 30001 milliseconds'),
		'server error' => array('http_code' => 500, 'body' => null, 'raw' => ''),
		'bad gateway' => array('http_code' => 502, 'body' => null, 'raw' => '<html></html>'),
		'unauthorized' => array('http_code' => 401, 'body' => array('error' => 'invalid_token', 'error_description' => 'The access token is invalid'), 'raw' => '{}'),
		'forbidden' => array('http_code' => 403, 'body' => array('errors' => array(array('title' => 'Forbidden', 'detail' => 'You are not authorized'))), 'raw' => '{}'),
		'rate limit' => array('http_code' => 429, 'body' => null, 'raw' => ''),
	);
}

// 200 is a record that is there, 404 one Paraşüt no longer holds; every
// other answer is no answer.
function test_parasut_stale_ids_record_state()
{
	foreach (parasut_stale_ids_test_answers() as $label => $answer) {
		$want = ($label === 'found') ? 'kept' : (($label === 'deleted') ? 'gone' : 'unknown');

		pg_assert_same($want, parasut_stored_record_state($answer), $label);
	}

	// A cURL error is never taken for an answer, whatever code came with it.
	pg_assert_same('unknown', parasut_stored_record_state(array('http_code' => 404, 'body' => null, 'curl_error' => 'Connection reset')));
	pg_assert_same('unknown', parasut_stored_record_state(array()));
	pg_assert_same('unknown', parasut_stored_record_state(null));
}

// The stored record is read by its own path, the id trimmed and escaped;
// an order with no id asks nothing.
function test_parasut_stale_ids_check_reads_the_stored_record_first()
{
	$answers = parasut_stale_ids_test_answers();

	$asked = array();
	$check = parasut_stored_record_check('sales_invoices', ' 1104339388 ', parasut_stale_ids_test_get($answers['found'], $asked));
	pg_assert_same(array('/sales_invoices/1104339388'), $asked);
	pg_assert_same(array('state' => 'kept', 'notice' => '', 'error' => ''), $check);

	$asked = array();
	$check = parasut_stored_record_check('shipment_documents', '1003775252', parasut_stale_ids_test_get($answers['found'], $asked));
	pg_assert_same(array('/shipment_documents/1003775252'), $asked);
	pg_assert_same('kept', $check['state']);

	$asked = array();
	parasut_stored_record_check('shipment_documents', 'a/b', parasut_stale_ids_test_get($answers['found'], $asked));
	pg_assert_same(array('/shipment_documents/a%2Fb'), $asked);

	foreach (array('', '   ', null) as $empty) {
		$asked = array();
		pg_assert_same(array('state' => 'none', 'notice' => '', 'error' => ''), parasut_stored_record_check('sales_invoices', $empty, parasut_stale_ids_test_get($answers['found'], $asked)));
		pg_assert_same(array(), $asked, 'nothing asked for ' . var_export($empty, true));
	}
}

// A record Paraşüt no longer holds comes back with the sentence the order
// screen shows; one that cannot be read with Paraşüt's words.
function test_parasut_stale_ids_check_says_what_it_left_out()
{
	$answers = parasut_stale_ids_test_answers();
	$asked = array();

	pg_assert_same(array(
		'state' => 'gone',
		'notice' => lang(array('string' => 'This order\'s earlier sales invoice (ID: {var:1}) is no longer at Paraşüt, so it was left out.', 'vars' => array('1104339388'))),
		'error' => '',
	), parasut_stored_record_check('sales_invoices', '1104339388', parasut_stale_ids_test_get($answers['deleted'], $asked)));

	pg_assert_same(array(
		'state' => 'gone',
		'notice' => lang(array('string' => 'This order\'s earlier shipment document (ID: {var:1}) is no longer at Paraşüt, so it was left out.', 'vars' => array('1003775252'))),
		'error' => '',
	), parasut_stored_record_check('shipment_documents', '1003775252', parasut_stale_ids_test_get($answers['deleted'], $asked)));

	pg_assert_same(parasut_stored_record_gone_notice('sales_invoices', '7'), lang(array('string' => 'This order\'s earlier sales invoice (ID: {var:1}) is no longer at Paraşüt, so it was left out.', 'vars' => array('7'))));
	pg_assert_same(parasut_stored_record_gone_notice('shipment_documents', '8'), lang(array('string' => 'This order\'s earlier shipment document (ID: {var:1}) is no longer at Paraşüt, so it was left out.', 'vars' => array('8'))));

	foreach (array('timeout', 'server error', 'bad gateway', 'unauthorized', 'forbidden', 'rate limit') as $label) {
		$check = parasut_stored_record_check('shipment_documents', '1003775252', parasut_stale_ids_test_get($answers[$label], $asked));

		pg_assert_same(array('state' => 'unknown', 'notice' => '', 'error' => _parasut_extract_error($answers[$label])), $check, $label);
	}
}

// E-Shipment after an invoice deleted on Paraşüt's screen: the read answers
// 404, so there is no invoice to name or to be refused by. The document goes
// unlinked, and the caller is told which id to clear.
function test_parasut_stale_ids_shipment_invoice_link_leaves_a_deleted_invoice_out()
{
	$answers = parasut_stale_ids_test_answers();
	$asked = array();

	pg_assert_same(array('invoice_id' => '', 'error' => '', 'gone' => '1104339388'), parasut_shipment_invoice_link(' 1104339388 ', parasut_stale_ids_test_get($answers['deleted'], $asked)));
	pg_assert_same(array('/sales_invoices/1104339388'), $asked);

	// Found: as before, named when issued without shipment.
	$asked = array();
	pg_assert_same(array('invoice_id' => '1104339388', 'error' => ''), parasut_shipment_invoice_link('1104339388', parasut_stale_ids_test_get($answers['found'], $asked)));
}

// Any other answer than 200 or 404 still refuses E-Shipment in so many words.
function test_parasut_stale_ids_shipment_invoice_link_still_refuses_without_an_answer()
{
	$answers = parasut_stale_ids_test_answers();

	foreach (array('timeout', 'server error', 'bad gateway', 'unauthorized', 'forbidden', 'rate limit') as $label) {
		$asked = array();
		$link = parasut_shipment_invoice_link('77', parasut_stale_ids_test_get($answers[$label], $asked));

		pg_assert_same(array('invoice_id' => '', 'error' => lang(array(
			'string' => 'Paraşüt could not be asked whether this order\'s sales invoice (ID: {var:1}) carries the shipment: {var:2}. No shipment document was created; try again in a minute.',
			'vars' => array('77', _parasut_extract_error($answers[$label])),
		))), $link, $label);
	}
}

// Create Invoice on an order that keeps its invoice's id reads that invoice
// before anything else is asked or written: still there (or no answer) it
// refuses as before; deleted there, the id is cleared, the screen told, and
// the order invoiced as one never sent.
function test_parasut_stale_ids_create_invoice_reads_its_stored_invoice_first()
{
	$body = parasut_stale_ids_test_source('parasut_create_invoice');

	pg_assert_contains('function parasut_create_invoice($order_id, &$notices = array())', $body);

	$stored = strpos($body, "\$existing_invoice_id = trim((string) (\$order['parasut_invoice_id'] ?? ''));");
	$read = strpos($body, "\$stored = _parasut_stored_record_check('sales_invoices', \$existing_invoice_id);");
	$refused = strpos($body, "if (\$stored['state'] !== 'gone') {");
	$message = strpos($body, "'This order already has a sales invoice in Paraşüt (ID: {var:1}), so a second one was not created.");
	$cleared = strpos($body, "_parasut_order_forget_record(\$order_id, 'parasut_invoice_id', \$existing_invoice_id);");
	$told = strpos($body, "\$notices[] = \$stored['notice'];");

	pg_assert_true(($stored !== false) && ($read !== false) && ($stored < $read), 'the stored id is read');
	pg_assert_true(($refused !== false) && ($read < $refused) && ($message !== false) && ($refused < $message), 'anything but gone refuses as before');
	pg_assert_true(($cleared !== false) && ($message < $cleared), 'a gone id is cleared');
	pg_assert_true(($told !== false) && ($cleared < $told), 'and the screen told');
	pg_assert_contains("\$order['parasut_invoice_id'] = '';", $body);

	foreach (array('db_items(', 'parasut_sync_contact(', "_parasut_request('POST'") as $call) {
		$at = strpos($body, $call);
		pg_assert_true(($at !== false) && ($told !== false) && ($told < $at), 'read before ' . $call);
	}
}

// Create Invoice reads the order's shipment document before the invoice
// names it: deleted there, the invoice goes as for an order never shipped
// and the id is cleared; no answer refuses before the buyer card.
function test_parasut_stale_ids_create_invoice_reads_the_shipment_before_naming_it()
{
	$body = parasut_stale_ids_test_source('parasut_create_invoice');
	$token = strpos($body, "\$token = \$token_result['token'];");
	$read = strpos($body, "\$shipment_check = parasut_stored_record_check('shipment_documents', (string) (\$order['parasut_shipment_id'] ?? ''), function (\$path) use (\$token) {");
	$gone = strpos($body, "if (\$shipment_check['state'] === 'gone') {");
	$cleared = strpos($body, "_parasut_order_forget_record(\$order_id, 'parasut_shipment_id', trim((string) \$order['parasut_shipment_id']));");
	$unknown = strpos($body, "} elseif (\$shipment_check['state'] === 'unknown') {");
	$message = strpos($body, "'Paraşüt could not be asked whether this order\\'s shipment document (ID: {var:1}) is still there: {var:2}. No invoice was created; try again in a minute.'");
	$named = strpos($body, "\$invoice_body = parasut_order_invoice_with_shipment(\$invoice_body, (string) (\$order['parasut_shipment_id'] ?? ''));");

	pg_assert_true(($token !== false) && ($read !== false) && ($token < $read), 'read with the token');
	pg_assert_true(($gone !== false) && ($read < $gone) && ($cleared !== false) && ($gone < $cleared), 'a gone document is cleared');
	pg_assert_true(($unknown !== false) && ($cleared < $unknown) && ($message !== false) && ($unknown < $message), 'no answer refuses');
	pg_assert_contains("\$notices[] = \$shipment_check['notice'];", $body);
	pg_assert_contains("\$order['parasut_shipment_id'] = '';", $body);

	foreach (array('_parasut_resolve_edoc_kind(', 'parasut_sync_contact(', 'parasut_order_invoice_find(', "_parasut_request('POST'") as $call) {
		$at = strpos($body, $call);
		pg_assert_true(($at !== false) && ($message !== false) && ($message < $at), 'read before ' . $call);
	}

	pg_assert_true(($named !== false) && ($message < $named), 'the body names what the read left');
}

// E-Shipment on an order that keeps its document's id reads that document
// before anything is written: still there (or no answer) it refuses as
// before; deleted there, the id is cleared, the screen told, the buyer's
// VKN/TCKN asked (the order screen skipped it for an order with an id), and
// the order shipped as one never shipped.
function test_parasut_stale_ids_create_shipment_reads_its_stored_document_first()
{
	$body = parasut_stale_ids_test_source('parasut_create_shipment');

	pg_assert_contains('function parasut_create_shipment($order_id, &$notices = array())', $body);

	$stored = strpos($body, "\$existing_shipment_id = trim((string) (\$order['parasut_shipment_id'] ?? ''));");
	$read = strpos($body, "\$stored = _parasut_stored_record_check('shipment_documents', \$existing_shipment_id);");
	$refused = strpos($body, "if (\$stored['state'] !== 'gone') {");
	$message = strpos($body, "'This order already has a shipment document in Paraşüt (ID: {var:1}), so a second one was not created.'");
	$cleared = strpos($body, "_parasut_order_forget_record(\$order_id, 'parasut_shipment_id', \$existing_shipment_id);");
	$told = strpos($body, "\$notices[] = \$stored['notice'];");
	$identity = strpos($body, '_parasut_shipment_identity_refusal(');

	pg_assert_true(($stored !== false) && ($read !== false) && ($stored < $read), 'the stored id is read');
	pg_assert_true(($refused !== false) && ($read < $refused) && ($message !== false) && ($refused < $message), 'anything but gone refuses as before');
	pg_assert_true(($cleared !== false) && ($message < $cleared) && ($told !== false) && ($cleared < $told), 'a gone id is cleared and the screen told');
	pg_assert_true(($identity !== false) && ($told < $identity), 'the VKN/TCKN is asked then');

	foreach (array('parasut_shipment_invoice_link(', 'parasut_sync_contact(', 'parasut_ensure_default_warehouse(', "_parasut_request('POST'") as $call) {
		$at = strpos($body, $call);
		pg_assert_true(($at !== false) && ($identity !== false) && ($identity < $at), 'before ' . $call);
	}
}

// E-Shipment after an invoice deleted on Paraşüt's screen clears the order's
// invoice id and says so before anything is written at Paraşüt.
function test_parasut_stale_ids_create_shipment_forgets_a_deleted_invoice()
{
	$body = parasut_stale_ids_test_source('parasut_create_shipment');
	$refused = strpos($body, "if (\$invoice_link['error'] !== '') {");
	$gone = strpos($body, "if ((string) (\$invoice_link['gone'] ?? '') !== '') {");
	$cleared = strpos($body, "_parasut_order_forget_record(\$order_id, 'parasut_invoice_id', \$invoice_link['gone']);");
	$told = strpos($body, "\$notices[] = parasut_stored_record_gone_notice('sales_invoices', \$invoice_link['gone']);");
	$contact = strpos($body, 'parasut_sync_contact(');

	pg_assert_true(($refused !== false) && ($gone !== false) && ($refused < $gone), 'after the refusals');
	pg_assert_true(($cleared !== false) && ($gone < $cleared) && ($told !== false) && ($cleared < $told), 'cleared and told');
	pg_assert_true(($contact !== false) && ($told !== false) && ($told < $contact), 'before the buyer card');
}

// The clear touches only the two id columns, and only while the column
// still holds the id that was read: a press beside this one may have
// written a new record's id meanwhile.
function test_parasut_stale_ids_forget_record_clears_only_the_id_it_read()
{
	$body = parasut_stale_ids_test_source('_parasut_order_forget_record');

	pg_assert_contains("if (!in_array(\$column, array('parasut_invoice_id', 'parasut_shipment_id'), true)) {", $body);
	pg_assert_contains("db(\"UPDATE orders SET \" . \$column . \" = '' WHERE id = '\" . escape(\$order_id) . \"' AND TRIM(\" . \$column . \") = '\" . escape(trim((string) \$id)) . \"'\");", $body);
}

// The order screen offers both buttons again on an order that keeps an id
// (the press asks Paraşüt first), with a confirm that says so, and shows
// what a press left out beside its outcome.
function test_parasut_stale_ids_order_screen_offers_the_buttons_and_shows_the_notice()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_order.php');

	parasut_stale_ids_test_has('$result = parasut_create_invoice($order_id, $parasut_notices);', $source, 'Create Invoice hands its notices over');
	parasut_stale_ids_test_has('$result = parasut_create_shipment($order_id, $parasut_notices);', $source, 'E-Shipment hands its notices over');
	parasut_stale_ids_test_has("foreach (\$parasut_notices as \$parasut_notice) {\n            \$liveform->add_notice(h(\$parasut_notice));", $source, 'the screen shows them');

	parasut_stale_ids_test_has("lang('This order already has a sales invoice in Paraşüt. Pressing again first asks Paraşüt for it and creates a new one only if it was deleted there. Continue?')", $source, 'the invoice confirm');
	parasut_stale_ids_test_has("lang('This order already has a shipment document in Paraşüt. Pressing again first asks Paraşüt for it and creates a new one only if it was deleted there. Continue?')", $source, 'the shipment confirm');

	$start = strpos($source, "\$output_parasut_shipment_button = '';");
	$end = ($start === false) ? false : strpos($source, '$output_parasut_buttons =', $start);
	pg_assert_true(($start !== false) && ($end !== false), 'the two buttons are found');
	$block = (($start !== false) && ($end !== false)) ? substr($source, $start, $end - $start) : '';

	pg_assert_false(strpos($block, ' disabled') !== false, 'neither button is drawn disabled');
	parasut_stale_ids_test_has("h(lang('This order already has a shipment document in Paraşüt.'))", $block, 'the sent E-Shipment title');
	parasut_stale_ids_test_has("h(lang('This order already has a sales invoice in Paraşüt.'))", $block, 'the sent Create Invoice title');
}
