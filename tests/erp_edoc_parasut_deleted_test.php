<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an ERP invoice whose Paraşüt record is no longer there (deleted
 * on Paraşüt's screen): how the driver tells Paraşüt's "Record was not
 * found" for the row's own id from other 404s, what a status question then
 * answers and clears, what the e-document service writes for it, and when
 * an invoice handed to GİB may be taken back (the driver's cancellable()).
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
	}
}

// A row that can stand for a document GİB has is never freed by a 404: the
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
// whether the job it read had failed.
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

// Handed to GİB and not read since: the row cannot tell an e-Invoice GİB has
// from a conversion that failed, so Paraşüt is asked (cancel_invoice() reads
// active_e_document and refuses an e-Invoice). While the conversion job may
// still run, the cancel waits for a status question, so a record being
// converted is never deleted.
function test_erp_edoc_parasut_deleted_cancellable_sent()
{
	$unread = erp_edoc_parasut_cancellable(pg_test_parasut_deleted_row(array('edoc_status' => 'sent', 'edoc_kind' => 'einvoice')));
	pg_assert_true($unread['ok'], 'an e-Invoice hand-over not read since: Paraşüt is asked');
	pg_assert_false($unread['manual']);
	pg_assert_contains('asked', $unread['reason']);

	$expired = erp_edoc_parasut_cancellable(pg_test_parasut_deleted_row(array('edoc_status' => 'sent', 'edoc_kind' => 'einvoice', 'parasut_job_id' => '77', 'parasut_job_expires_at' => time() - 60)));
	pg_assert_true($expired['ok'], 'the job window has passed');

	foreach (array('einvoice', 'earchive') as $kind) {
		$running = erp_edoc_parasut_cancellable(pg_test_parasut_deleted_row(array('edoc_status' => 'sent', 'edoc_kind' => $kind, 'parasut_job_id' => '77', 'parasut_job_expires_at' => time() + 600)));
		pg_assert_false($running['ok'], $kind . ': the conversion may still run');
		pg_assert_contains('ask after its status', $running['reason'], $kind);
	}

	// A status question saw the e-Invoice on its way to GİB: refused from the row.
	$seen = erp_edoc_parasut_cancellable(pg_test_parasut_deleted_row(array('edoc_status' => 'sent', 'edoc_kind' => 'einvoice', 'parasut_edoc_id' => '5551')));
	pg_assert_false($seen['ok'], 'an e-Invoice Paraşüt was seen to hold');
	pg_assert_contains('return invoice', $seen['reason']);

	$archive = erp_edoc_parasut_cancellable(pg_test_parasut_deleted_row(array('edoc_status' => 'sent', 'edoc_kind' => 'earchive', 'parasut_edoc_id' => '5552')));
	pg_assert_true($archive['ok'], 'an e-Archive invoice is cancelled through the API');

	pg_assert_false(erp_edoc_parasut_cancellable(pg_test_parasut_deleted_row(array('edoc_status' => 'accepted', 'edoc_kind' => 'einvoice', 'parasut_edoc_id' => '5551')))['ok'], 'an accepted e-Invoice, unchanged');
	pg_assert_true(erp_edoc_parasut_cancellable(pg_test_parasut_deleted_row(array('edoc_status' => 'error', 'edoc_kind' => 'einvoice')))['ok'], 'an error row, unchanged');
}

// cancel_invoice() reads what Paraşüt holds and refuses an e-Invoice before
// any DELETE: a plain record is deleted only when no e-document stands
// behind it, and the e-Archive cancel comes after the e-Invoice refusal.
function test_erp_edoc_parasut_deleted_cancel_refuses_an_einvoice()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/edoc/parasut.php');
	$from = strpos($source, 'function erp_edoc_parasut_cancel_invoice(');
	$section = substr($source, $from, strpos($source, "\n}\n", $from) - $from);

	$read = strpos($section, "'/sales_invoices/' . rawurlencode(\$external_id) . '?include=active_e_document'");
	$plain = strpos($section, "if (trim((string) (\$document_reference['id'] ?? '')) === '') {");
	$delete = strpos($section, "erp_edoc_parasut_request('DELETE', '/sales_invoices/' . rawurlencode(\$external_id), null, \$context);");
	$einvoice = strpos($section, "if (\$document_type === 'e_invoices') {");
	$cancel = strpos($section, "'/sales_invoices/' . rawurlencode(\$external_id) . '/cancel'");

	pg_assert_true(($read !== false) && ($plain !== false) && ($read < $plain), 'the record is read first');
	pg_assert_true(($delete !== false) && ($plain < $delete) && ($delete < $einvoice), 'the delete only inside the no-e-document branch');
	pg_assert_true(($einvoice !== false) && ($cancel !== false) && ($einvoice < $cancel), 'the e-Invoice is refused before any cancel');
}
