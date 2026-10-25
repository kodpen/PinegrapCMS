<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an ERP invoice whose Paraşüt record is no longer there (deleted
 * on Paraşüt's screen): how the driver tells Paraşüt's "Record was not
 * found" for the row's own id from other 404s, what a status question then
 * answers and clears, what the e-document service writes for it, and that
 * an e-Invoice handed to GİB is still not offered for Cancel from the row
 * (the driver's cancellable()).
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
function pg_test_parasut_deleted_answer($code, $body, $extra = array())
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

// Paraşüt's answer to GET sales_invoices/{id} for a record deleted on its
// screen (live: {"errors":[{"title":"Record was not found","detail":"SalesInvoice-<id>"}]}).
function pg_test_parasut_deleted_not_found($id)
{
	return pg_test_parasut_deleted_answer(404, array('errors' => array(array('title' => 'Record was not found', 'detail' => 'SalesInvoice-' . $id))));
}

function pg_test_parasut_deleted_row($overrides = array())
{
	return array_merge(array(
		'id' => 42,
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

// Only Paraşüt's own "Record was not found" naming the row's sales invoice
// says the record is gone; any other 404, a refusal, a server error or no
// answer says nothing about it.
function test_erp_edoc_parasut_deleted_invoice_gone()
{
	pg_assert_true(erp_edoc_parasut_invoice_gone(pg_test_parasut_deleted_not_found('1104221607'), '1104221607'), 'the record deleted on Paraşüt\'s screen');
	pg_assert_true(erp_edoc_parasut_invoice_gone(pg_test_parasut_deleted_not_found('1104221607'), ' 1104221607 '), 'the id trimmed');

	pg_assert_false(erp_edoc_parasut_invoice_gone(pg_test_parasut_deleted_not_found('999'), '1104221607'), 'another record');
	pg_assert_false(erp_edoc_parasut_invoice_gone(pg_test_parasut_deleted_not_found('11042216070'), '1104221607'), 'an id that only starts with it');
	pg_assert_false(erp_edoc_parasut_invoice_gone(pg_test_parasut_deleted_not_found(''), ''), 'no id on the row');
	pg_assert_false(erp_edoc_parasut_invoice_gone(pg_test_parasut_deleted_answer(404, array('errors' => array(array('title' => 'Record was not found', 'detail' => 'Product-1104221607')))), '1104221607'), 'another kind of record');
	pg_assert_false(erp_edoc_parasut_invoice_gone(pg_test_parasut_deleted_answer(404, array('errors' => array(array('title' => 'No route matches.', 'detail' => 'No route matches.')))), '1104221607'), 'a path Paraşüt does not know');
	pg_assert_false(erp_edoc_parasut_invoice_gone(pg_test_parasut_deleted_answer(404, null), '1104221607'), 'a 404 with no body');
	pg_assert_false(erp_edoc_parasut_invoice_gone(pg_test_parasut_deleted_answer(404, array('errors' => array('Record was not found', 7))), '1104221607'), 'errors that are not objects');
	pg_assert_false(erp_edoc_parasut_invoice_gone(pg_test_parasut_deleted_answer(401, array('errors' => array(array('title' => 'No access to company: 1', 'detail' => 'SalesInvoice-1104221607')))), '1104221607'), 'a company the user cannot reach');
	pg_assert_false(erp_edoc_parasut_invoice_gone(pg_test_parasut_deleted_answer(500, array('errors' => array(array('title' => 'Record was not found', 'detail' => 'SalesInvoice-1104221607')))), '1104221607'), 'a server error');
	pg_assert_false(erp_edoc_parasut_invoice_gone(pg_test_parasut_deleted_answer(200, array('data' => array('id' => '1104221607'))), '1104221607'), 'the record is there');
	pg_assert_false(erp_edoc_parasut_invoice_gone(array_merge(pg_test_parasut_deleted_not_found('1104221607'), array('error' => 'Operation timed out')), '1104221607'), 'a transport error');
	pg_assert_false(erp_edoc_parasut_invoice_gone(array_merge(pg_test_parasut_deleted_not_found('1104221607'), array('login_error' => 'No token')), '1104221607'), 'no token');

	$several = pg_test_parasut_deleted_answer(404, array('errors' => array(
		array('title' => 'Something else', 'detail' => 'x'),
		array('title' => 'Record was not found', 'detail' => 'SalesInvoice-1104221607'),
	)));
	pg_assert_true(erp_edoc_parasut_invoice_gone($several, '1104221607'), 'the matching error among others');
}

// A draft or a failed record that was deleted at Paraşüt: the status
// question frees the row (nothing at the provider, the driver's ids and job
// cleared), so the invoice can be sent again.
function test_erp_edoc_parasut_deleted_gone_answer_frees_the_row()
{
	foreach (array(
		'a draft not handed over' => pg_test_parasut_deleted_row(),
		'a record whose total differed' => pg_test_parasut_deleted_row(array('edoc_status' => 'error')),
		'a failed e-Invoice' => pg_test_parasut_deleted_row(array('edoc_status' => 'error', 'edoc_kind' => 'einvoice', 'parasut_edoc_id' => '5551')),
		'a failed hand-over read back as a draft' => pg_test_parasut_deleted_row(array('edoc_kind' => 'earchive', 'parasut_job_id' => '77', 'parasut_job_expires_at' => time() + 600)),
	) as $label => $row) {
		$gone = erp_edoc_parasut_gone_answer($row);

		pg_assert_true(is_array($gone), $label);

		if (!is_array($gone)) {
			continue;
		}

		pg_assert_true($gone['answer']['success'], $label . ': written');
		pg_assert_same('none', $gone['answer']['status'], $label . ': not at the provider');
		pg_assert_true($gone['answer']['gone'], $label . ': the record is gone');
		pg_assert_same('', $gone['answer']['gib_number'], $label);
		pg_assert_same('', $gone['answer']['kind'], $label);
		pg_assert_contains('1104221607', $gone['answer']['message'], $label . ': names the record');
		pg_assert_contains('deleted', $gone['answer']['message'], $label);
		pg_assert_contains('another company is connected', $gone['answer']['message'], $label . ': a switched company answers the same');
		pg_assert_contains('sent again', $gone['answer']['message'], $label);
		pg_assert_same(array('parasut_invoice_id' => '', 'parasut_edoc_id' => '', 'parasut_job_id' => '', 'parasut_job_expires_at' => 0), $gone['forget'], $label . ': the driver forgets the record');
	}

	// Freed: the send gate holds nothing back any more.
	pg_assert_same('', erp_edoc_invoice_held_refusal(array('edoc_status' => 'none', 'edoc_external_id' => '')), 'sendable again');
	pg_assert_same('', erp_edoc_invoice_held_refusal(array('edoc_status' => 'error', 'edoc_external_id' => '')), 'sendable again with the mark');
}

// The unsure mark of a send that got no answer stays: that send may have
// written a record of its own, which the next send (or a cancel) looks for.
// Such a row is 'error' without an id, the state the mark is followed from.
function test_erp_edoc_parasut_deleted_gone_answer_keeps_the_mark()
{
	foreach (array('?2026-10-19:7001', '?') as $mark) {
		$gone = erp_edoc_parasut_gone_answer(pg_test_parasut_deleted_row(array('edoc_status' => 'error', 'parasut_invoice_id' => $mark)));

		pg_assert_true(is_array($gone), $mark);

		if (!is_array($gone)) {
			continue;
		}

		pg_assert_same('error', $gone['answer']['status'], $mark . ': not "deleted"');
		pg_assert_true($gone['answer']['gone'], $mark);
		pg_assert_false(array_key_exists('parasut_invoice_id', $gone['forget']), $mark . ': the mark is kept');
		pg_assert_same(array('parasut_edoc_id' => '', 'parasut_job_id' => '', 'parasut_job_expires_at' => 0), $gone['forget'], $mark);
		pg_assert_contains('looks', $gone['answer']['message'], $mark . ': the next send looks for it first');
		pg_assert_contains('another company is connected', $gone['answer']['message'], $mark);
	}
}

// A row the ERP holds as a GİB document is never freed by a 404: the
// company may have been switched, and a second send would issue the sale
// twice. A handed-over row is freed only when this question read its
// conversion job as failed (the record was a plain draft).
function test_erp_edoc_parasut_deleted_gone_answer_leaves_documents()
{
	foreach (array('sent', 'accepted', 'cancelled', 'rejected', 'none', 'sending', 'queued') as $status) {
		pg_assert_same(null, erp_edoc_parasut_gone_answer(pg_test_parasut_deleted_row(array('edoc_status' => $status, 'edoc_kind' => 'earchive'))), $status);
	}

	pg_assert_same(null, erp_edoc_parasut_gone_answer(pg_test_parasut_deleted_row(array('edoc_status' => 'accepted')), true), 'a failed job does not free an accepted row');

	$failed_job = erp_edoc_parasut_gone_answer(pg_test_parasut_deleted_row(array('edoc_status' => 'sent', 'edoc_kind' => 'einvoice')), true);
	pg_assert_true(is_array($failed_job) && ($failed_job['answer']['status'] === 'none'), 'handed over, the job failed, the draft deleted');
}

// poll() asks whether the record is gone right after reading it, before the
// general 404 answer, forgets the driver's ids when it is, and passes on
// whether the job it read had failed. The PDF request asks without freeing:
// the service's write of the answer does not run on that path, so the row
// would keep naming the record while the driver had forgotten it.
function test_erp_edoc_parasut_deleted_poll_reads_the_gone_record()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/edoc/parasut.php');
	$from = strpos($source, 'function erp_edoc_parasut_poll(');
	$section = substr($source, $from, strpos($source, "\n}\n", $from) - $from);

	$read = strpos($section, "\$detail = erp_edoc_parasut_request('GET', '/sales_invoices/' . rawurlencode(\$external_id)");
	$gone = strpos($section, 'erp_edoc_parasut_invoice_gone($detail, $external_id)');
	$answer = strpos($section, 'erp_edoc_parasut_gone_answer($invoice, $job_failed)');
	$general = strpos($section, "if ((int) \$detail['http_code'] === 404) {");

	pg_assert_true(($read !== false) && ($gone !== false) && ($read < $gone), 'asked of the answer to the read');
	pg_assert_true(($answer !== false) && ($gone < $answer), 'the row decides what is freed');
	pg_assert_true(($general !== false) && ($answer < $general), 'before the general 404 answer');
	pg_assert_contains("\$job_failed = (\$reading['state'] === 'error');", $section, 'the failed job is remembered');
	pg_assert_contains("foreach (\$gone['forget'] as \$column => \$value)", $section, 'the driver forgets its ids');
	pg_assert_contains('function erp_edoc_parasut_poll($invoice, $job_id, $free_gone = true)', $source, 'the status question frees by default');
	pg_assert_contains('if ($free_gone && erp_edoc_parasut_invoice_gone($detail, $external_id)) {', $section, 'only a caller that writes the answer frees');

	$from = strpos($source, 'function erp_edoc_parasut_fetch_document(');
	$fetch = substr($source, $from, strpos($source, "\n}\n", $from) - $from);
	pg_assert_contains("\$polled = erp_edoc_parasut_poll(\$invoice, (string) (\$invoice['edoc_external_id'] ?? ''), false);", $fetch, 'the PDF request does not free the row');
}

// The service writes "not at the provider" only for the driver's gone answer
// on a row it frees (none, or error with the unsure mark), never on a
// document.
function test_erp_edoc_parasut_deleted_service_poll_set()
{
	$set = erp_edoc_invoice_poll_gone_set(array('success' => true, 'status' => 'none', 'gone' => true));
	pg_assert_contains("edoc_external_id = ''", $set, 'the record is no longer named');
	pg_assert_contains("edoc_kind = 'none'", $set, 'the kind is decided again on the next hand-over');
	pg_assert_contains("gib_number = ''", $set);
	pg_assert_contains("gib_uuid = ''", $set);
	pg_assert_contains("edoc_sent_at = 0", $set, 'the invoice screen and the API show no send time for a record that is gone');

	pg_assert_same($set, erp_edoc_invoice_poll_gone_set(array('success' => true, 'status' => 'error', 'gone' => true)), 'with the mark');
	pg_assert_same(array(), erp_edoc_invoice_poll_gone_set(array('success' => true, 'status' => 'accepted', 'gone' => true)), 'a document is never forgotten');
	pg_assert_same(array(), erp_edoc_invoice_poll_gone_set(array('success' => true, 'status' => 'none')), 'no gone flag');
	pg_assert_same(array(), erp_edoc_invoice_poll_gone_set(array('success' => true, 'status' => 'created', 'gone' => false)));

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/edoc/service.php');
	$from = strpos($source, 'function erp_edoc_invoice_poll(');
	$section = substr($source, $from, strpos($source, "\n}\n", $from) - $from);
	$read = strpos($section, '$gone = erp_edoc_invoice_poll_gone_set($result);');
	$merge = strpos($section, '$set = array_merge($set, $gone);');
	$write = strpos($section, 'erp_query("UPDATE erp_invoices SET " . implode');

	pg_assert_true(($read !== false) && ($merge !== false) && ($read < $merge), 'asked of the driver\'s answer');
	pg_assert_true(($merge !== false) && ($write !== false) && ($merge < $write), 'merged into the poll\'s write');
	pg_assert_contains("if (((\$status !== \$before) || (\$gone !== array())) && function_exists('erp_event_invoice')) {", $section, 'announced even when the status stays error');
}

// Cancel of an e-Invoice handed to GİB stays refused from the row, whether
// or not a status question has seen its e-document and whether or not its
// conversion job window has passed: the row cannot tell an e-Invoice GİB
// holds from a conversion that failed, and cancel_invoice() reads any 404
// (also a switched company's) as a deleted record. A status question first
// turns a failed conversion into a draft ('created'), which is cancelled.
function test_erp_edoc_parasut_deleted_sent_einvoice_stays_refused()
{
	foreach (array(
		'not read since the hand-over' => array(),
		'the job window open' => array('parasut_job_id' => '77', 'parasut_job_expires_at' => time() + 600),
		'the job window passed' => array('parasut_job_id' => '77', 'parasut_job_expires_at' => time() - 60),
		'its e-document seen' => array('parasut_edoc_id' => '5551'),
	) as $label => $overrides) {
		$answer = erp_edoc_parasut_cancellable(pg_test_parasut_deleted_row(array_merge(array('edoc_status' => 'sent', 'edoc_kind' => 'einvoice'), $overrides)));

		pg_assert_false($answer['ok'], $label . ': no Cancel');
		pg_assert_false($answer['manual'], $label);
		pg_assert_contains('return invoice', $answer['reason'], $label . ': the return invoice is offered');
	}

	pg_assert_true(erp_edoc_parasut_cancellable(pg_test_parasut_deleted_row(array('edoc_status' => 'created', 'edoc_kind' => 'einvoice')))['ok'], 'a failed conversion read back as a draft');
	pg_assert_false(erp_edoc_parasut_cancellable(pg_test_parasut_deleted_row(array('edoc_status' => 'accepted', 'edoc_kind' => 'einvoice', 'parasut_edoc_id' => '5551')))['ok'], 'an accepted e-Invoice');
}
