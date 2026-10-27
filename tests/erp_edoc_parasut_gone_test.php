<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the company guard of a Paraşüt status question whose sales
 * invoice answers "Record was not found": before a 'created' or 'error' row
 * is freed, another Paraşüt record the row names (the buyer's card, a
 * product card) is read. A record the connected company holds frees the
 * row; a 404 there too reads as another company connected now, and the row
 * stays held with a warning that names the way out.
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
require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/service.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/parasut.php');

// An erp_edoc_parasut_request() answer with the given code and JSON body.
function pg_test_parasut_gone_answer($code, $body, $extra = array())
{
	return array_merge(array(
		'http_code' => $code,
		'body' => $body,
		'raw' => ($body === null) ? '' : json_encode($body),
		'error' => '',
		'duration_ms' => 1,
		'login_error' => '',
	), $extra);
}

function pg_test_parasut_gone_row($overrides = array())
{
	return array_merge(array(
		'id' => 42,
		'account_id' => 9,
		'edoc_provider' => 'parasut',
		'edoc_status' => 'created',
		'edoc_external_id' => '1104221607',
		'edoc_kind' => 'none',
		'parasut_invoice_id' => '1104221607',
		'parasut_edoc_id' => '',
		'parasut_job_id' => '',
		'parasut_job_expires_at' => 0,
	), $overrides);
}

// A stand-in for erp_edoc_parasut_request() answering from a path => answer
// map and noting every path it was asked; an unlisted path is a 500.
function pg_test_parasut_gone_request($answers, &$asked)
{
	$asked = array();

	return function ($method, $path, $body = null, $context = array()) use ($answers, &$asked) {
		$asked[] = $method . ' ' . $path;

		return $answers[$path] ?? pg_test_parasut_gone_answer(500, array('errors' => array(array('title' => 'Server error', 'detail' => 'unlisted'))));
	};
}

function pg_test_parasut_gone_found($type, $id)
{
	return pg_test_parasut_gone_answer(200, array('data' => array('id' => $id, 'type' => $type, 'attributes' => array('name' => 'x'))));
}

function pg_test_parasut_gone_missing($kind, $id)
{
	return pg_test_parasut_gone_answer(404, array('errors' => array(array('title' => 'Record was not found', 'detail' => $kind . '-' . $id))));
}

// The records a row names, in the order they are read: the contact of an
// unanswered send's mark, the account's linked card, the lines' product
// cards; real ids only, each once, at most three.
function test_erp_edoc_parasut_gone_witnesses()
{
	pg_assert_same(array(), erp_edoc_parasut_gone_witnesses(pg_test_parasut_gone_row(), '', array()), 'nothing named');
	pg_assert_same(array('/contacts/7001'), erp_edoc_parasut_gone_witnesses(pg_test_parasut_gone_row(), '7001', array()), 'the linked card');
	pg_assert_same(array('/contacts/7001', '/products/55'), erp_edoc_parasut_gone_witnesses(pg_test_parasut_gone_row(), ' 7001 ', array('55')), 'the card, then a product card');
	pg_assert_same(
		array('/contacts/7002', '/contacts/7001', '/products/55'),
		erp_edoc_parasut_gone_witnesses(pg_test_parasut_gone_row(array('edoc_status' => 'error', 'parasut_invoice_id' => '?2026-10-19:7002')), '7001', array('55', '56')),
		'the mark\'s contact first, three at most'
	);
	pg_assert_same(array('/contacts/7001'), erp_edoc_parasut_gone_witnesses(pg_test_parasut_gone_row(array('parasut_invoice_id' => '?2026-10-19:7001')), '7001', array()), 'each once');
	pg_assert_same(array('/products/55'), erp_edoc_parasut_gone_witnesses(pg_test_parasut_gone_row(array('parasut_invoice_id' => '?')), 'C-12', array('', 'abc', '55')), 'only real ids');
}

// The connected company holds the buyer's card: the invoice's record was
// deleted there, and the row is freed as before.
function test_erp_edoc_parasut_gone_frees_when_the_company_holds_a_record()
{
	$request = pg_test_parasut_gone_request(array('/contacts/7001' => pg_test_parasut_gone_found('contacts', '7001')), $asked);
	$gone = erp_edoc_parasut_gone_answer(pg_test_parasut_gone_row(), false, array('/contacts/7001'), $request);

	pg_assert_true(is_array($gone), 'answered');
	pg_assert_same(array('GET /contacts/7001'), $asked, 'the card is read');
	pg_assert_same('none', $gone['answer']['status'], 'freed');
	pg_assert_true($gone['answer']['gone'], 'the record is gone');
	pg_assert_contains('sent again', $gone['answer']['message']);
	pg_assert_same(array('parasut_invoice_id' => '', 'parasut_edoc_id' => '', 'parasut_job_id' => '', 'parasut_job_expires_at' => 0), $gone['forget']);

	// A first card gone on its own does not hold the row when the next is there.
	$request = pg_test_parasut_gone_request(array(
		'/contacts/7001' => pg_test_parasut_gone_missing('Contact', '7001'),
		'/products/55' => pg_test_parasut_gone_found('products', '55'),
	), $asked);
	$gone = erp_edoc_parasut_gone_answer(pg_test_parasut_gone_row(array('edoc_status' => 'error')), false, array('/contacts/7001', '/products/55'), $request);

	pg_assert_same(array('GET /contacts/7001', 'GET /products/55'), $asked, 'read until one is there');
	pg_assert_same('none', $gone['answer']['status'], 'freed on the product card');
}

// Every record the row names answers 404 too: another company is connected
// now. The row stays as it was (nothing forgotten, not 'gone'), and the
// warning names the way out.
function test_erp_edoc_parasut_gone_holds_after_a_company_switch()
{
	$request = pg_test_parasut_gone_request(array(
		'/contacts/7001' => pg_test_parasut_gone_missing('Contact', '7001'),
		'/products/55' => pg_test_parasut_gone_answer(404, array('errors' => array(array('title' => 'Record was not found', 'detail' => 'Product-55')))),
	), $asked);

	foreach (array('created', 'error') as $status) {
		$gone = erp_edoc_parasut_gone_answer(pg_test_parasut_gone_row(array('edoc_status' => $status)), false, array('/contacts/7001', '/products/55'), $request);

		pg_assert_true(is_array($gone), $status);
		pg_assert_true($gone['answer']['success'], $status . ': the warning is written on the row');
		pg_assert_same($status, $gone['answer']['status'], $status . ': held as it was');
		pg_assert_false(!empty($gone['answer']['gone']), $status . ': not gone');
		pg_assert_same(array(), $gone['forget'], $status . ': the driver forgets nothing');
		pg_assert_contains('1104221607', $gone['answer']['message'], $status);
		pg_assert_contains('another company', $gone['answer']['message'], $status);
		pg_assert_contains('settings card', $gone['answer']['message'], $status . ': the way out');
		pg_assert_contains('cancel the invoice', $gone['answer']['message'], $status);
	}

	// The send gate keeps refusing such a row: it still names the record.
	pg_assert_true(erp_edoc_invoice_held_refusal(pg_test_parasut_gone_row()) !== '', 'a held draft is not sent again');
}

// Nothing to read: the row is held too, with the cancel as the way out.
function test_erp_edoc_parasut_gone_holds_with_nothing_to_read()
{
	$request = pg_test_parasut_gone_request(array(), $asked);
	$gone = erp_edoc_parasut_gone_answer(pg_test_parasut_gone_row(), false, array(), $request);

	pg_assert_same(array(), $asked, 'no request');
	pg_assert_same('created', $gone['answer']['status'], 'held');
	pg_assert_same(array(), $gone['forget']);
	pg_assert_contains('cancel the invoice', $gone['answer']['message']);
}

// Paraşüt says nothing clear (a server error, no token, a card of another
// id): no write, the row is asked about again later.
function test_erp_edoc_parasut_gone_unclear_answer_writes_nothing()
{
	foreach (array(
		'a server error' => pg_test_parasut_gone_answer(503, null),
		'no token' => pg_test_parasut_gone_answer(0, null, array('login_error' => 'No token')),
		'a timeout' => pg_test_parasut_gone_answer(0, null, array('error' => 'Operation timed out')),
		'another card' => pg_test_parasut_gone_found('contacts', '7009'),
	) as $label => $answer) {
		$request = pg_test_parasut_gone_request(array('/contacts/7001' => $answer), $asked);
		$gone = erp_edoc_parasut_gone_answer(pg_test_parasut_gone_row(), false, array('/contacts/7001', '/products/55'), $request);

		pg_assert_same(array('GET /contacts/7001'), $asked, $label . ': stops at the first unclear answer');
		pg_assert_false($gone['answer']['success'], $label . ': not written');
		pg_assert_true($gone['answer']['error'] !== '', $label . ': says why');
		pg_assert_same(array(), $gone['forget'], $label);
	}
}

// A conversion job this question read at the connected company, and read as
// failed, is itself a record the company holds: the handed-over draft was
// deleted there, and the row is freed without another read.
function test_erp_edoc_parasut_gone_failed_job_is_the_company_s_record()
{
	$request = pg_test_parasut_gone_request(array(), $asked);
	$gone = erp_edoc_parasut_gone_answer(pg_test_parasut_gone_row(array('edoc_status' => 'sent', 'edoc_kind' => 'einvoice')), true, array('/contacts/7001'), $request);

	pg_assert_same(array(), $asked, 'no further read');
	pg_assert_same('none', $gone['answer']['status'], 'freed');
	pg_assert_true($gone['answer']['gone']);

	pg_assert_same(null, erp_edoc_parasut_gone_answer(pg_test_parasut_gone_row(array('edoc_status' => 'accepted')), true, array(), $request), 'a document is never let go');
}

// poll() hands the row's records to the guard and writes the forget only
// when there is one (a held row writes nothing of the driver's).
function test_erp_edoc_parasut_gone_poll_reads_the_witnesses()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/edoc/parasut.php');
	$from = strpos($source, 'function erp_edoc_parasut_poll(');
	$section = substr($source, $from, strpos($source, "\n}\n", $from) - $from);

	pg_assert_contains('erp_edoc_parasut_gone_answer($invoice, $job_failed, erp_edoc_parasut_gone_witnesses_of($invoice))', $section, 'the row\'s records');
	pg_assert_contains("if (\$gone['forget'] !== array()) {", $section, 'a held row writes nothing of the driver\'s');
}
