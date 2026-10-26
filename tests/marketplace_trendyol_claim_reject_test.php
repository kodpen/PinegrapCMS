<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for rejecting a return read from Trendyol: the reasons a rejection
 * can name and the rejection itself (mp_trendyol_claim_issue_reasons(),
 * mp_trendyol_claim_reason_rows(), mp_trendyol_claim_reject_problem(),
 * mp_trendyol_claim_multipart() and mp_trendyol_reject_claim(),
 * includes/api/outbound/connectors/trendyol.php).
 *
 * Trendyol is never called: the connector takes a transport with the signature
 * of api_http_request(), and the one here answers from tests/fixtures/trendyol/
 * (built in the shape of their getClaimIssueReasons and createClaimIssue
 * pages, read October 2026), not recorded from a live account.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/base.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/trendyol.php');

function trendyol_reject_test_account()
{
	return array(
		'id'          => 7,
		'provider'    => 'trendyol',
		'credentials' => array(
			'seller_id'   => '123456',
			'api_key'     => 'key-abc',
			'api_secret'  => 'secret-xyz',
			'environment' => 'production',
		),
	);
}

// A transport that answers every call with $status and $body and writes each
// call into $calls.
function trendyol_reject_test_transport($status, $body, &$calls)
{
	return function ($method, $url, $options) use ($status, $body, &$calls) {

		$calls[] = array('method' => $method, 'url' => $url, 'options' => $options);

		return array('ok' => ($status >= 200 && $status < 300), 'status' => $status, 'body' => $body,
			'error' => '', 'duration_ms' => 1);
	};
}

function trendyol_reject_test_photo()
{
	return array('name' => 'damaged box.jpg', 'data' => "\xFF\xD8\xFFphoto");
}

/* -------------------------------------------------------------- reasons */

function test_marketplace_trendyol_reject_connector_names_the_calls()
{
	pg_assert_true(mp_can('trendyol', 'claim_issue_reasons'));
	pg_assert_true(mp_can('trendyol', 'reject_claim'));
	pg_assert_false(mp_can('n11', 'reject_claim'));
}

function test_marketplace_trendyol_claim_issue_reasons_read_the_documented_list()
{
	$calls = array();

	$result = mp_trendyol_claim_issue_reasons(trendyol_reject_test_account(), trendyol_reject_test_transport(200,
		(string) file_get_contents(dirname(__FILE__) . '/fixtures/trendyol/claim-issue-reasons.json'), $calls));

	pg_assert_true($result['ok'], $result['error'] ?? '');
	pg_assert_same(1, count($calls));
	pg_assert_same('GET', $calls[0]['method']);
	pg_assert_same('https://apigw.trendyol.com/integration/order/claim-issue-reasons', $calls[0]['url']);

	// The row without a usable id, the one without a name and the repeated id
	// are left out.
	pg_assert_same(array('51', '401', '451', '1651', '2101'), array_column($result['data']['reasons'], 'id'));
	pg_assert_same('İade edilen ürün veya adedi eksik', $result['data']['reasons'][1]['name']);
	pg_assert_same(array(true, true, false, false, false), array_column($result['data']['reasons'], 'file'));
}

function test_marketplace_trendyol_claim_reason_rows_take_only_a_list()
{
	pg_assert_same(array(), mp_trendyol_claim_reason_rows(null));
	pg_assert_same(array(), mp_trendyol_claim_reason_rows(array('x', array('id' => '1a', 'name' => 'N'))));
}

/* -------------------------------------------------------------- problem */

function test_marketplace_trendyol_claim_reject_problem()
{
	$photo = trendyol_reject_test_photo();

	pg_assert_same('', mp_trendyol_claim_reject_problem('51', 'Used', array($photo)));
	pg_assert_same('', mp_trendyol_claim_reject_problem(1651, 'Never came', array()));
	pg_assert_same('', mp_trendyol_claim_reject_problem('451', str_repeat('ş', 500), array()));

	foreach (array(
		array('', 'Used', array($photo)),
		array('5a', 'Used', array($photo)),
		array('51', '   ', array($photo)),
		array('51', str_repeat('ş', 501), array($photo)),
		array('51', "Used \xC3", array($photo)),
		array('51', 'Used', array()),
		array('51', 'Used', array(array('name' => 'run.exe', 'data' => 'MZ'))),
		array('51', 'Used', array(array('name' => 'photo', 'data' => 'x'))),
		array('51', 'Used', array(array('name' => 'empty.pdf', 'data' => ''))),
		array('51', 'Used', array(array('name' => 'big.pdf', 'data' => str_repeat('x', 5242881)))),
		array('51', 'Used', array_fill(0, 6, $photo)),
	) as $case) {

		pg_assert_true(mp_trendyol_claim_reject_problem($case[0], $case[1], $case[2]) !== '',
			$case[0] . ' / ' . substr($case[1], 0, 20) . ' / ' . count($case[2]));
	}
}

/* --------------------------------------------------------------- reject */

function test_marketplace_trendyol_reject_claim_sends_the_guide_form()
{
	$calls = array();

	$result = mp_trendyol_reject_claim(trendyol_reject_test_account(), 'f9da2317-876b-4b86-b8f7-0535c3b65731',
		array('b71461e3-d1a0-4c1d-9a6d-18ecbcb5158c', 'c82572f4-e2b1-4d2e-8b7e-29fdcd6269d0'),
		'51', ' Kutu ezik & ürün kullanılmış ', array(trendyol_reject_test_photo(), array('name' => 'report.PDF', 'data' => '%PDF-1.4')),
		trendyol_reject_test_transport(200, '"HTTP 200"', $calls));

	pg_assert_true($result['ok'], $result['error'] ?? '');
	pg_assert_same(2, $result['data']['rejected']);

	pg_assert_same(1, count($calls));
	pg_assert_same('POST', $calls[0]['method']);
	pg_assert_same('https://apigw.trendyol.com/integration/order/sellers/123456/claims/f9da2317-876b-4b86-b8f7-0535c3b65731/issue'
		. '?claimIssueReasonId=51'
		. '&claimItemIdList=b71461e3-d1a0-4c1d-9a6d-18ecbcb5158c,c82572f4-e2b1-4d2e-8b7e-29fdcd6269d0'
		. '&description=' . rawurlencode('Kutu ezik & ürün kullanılmış'),
		$calls[0]['url']);

	$type = '';

	foreach ($calls[0]['options']['headers'] as $header) {
		if (stripos($header, 'Content-Type:') === 0) {
			$type = $header;
		}
	}

	pg_assert_same(1, preg_match('/^Content-Type: multipart\/form-data; boundary=(pg[0-9a-f]{32})$/', $type, $match));

	$boundary = $match[1];

	pg_assert_same('--' . $boundary . "\r\n"
		. 'Content-Disposition: form-data; name="files"; filename="damaged box.jpg"' . "\r\n"
		. "Content-Type: image/jpeg\r\n\r\n"
		. "\xFF\xD8\xFFphoto\r\n"
		. '--' . $boundary . "\r\n"
		. 'Content-Disposition: form-data; name="files"; filename="report.PDF"' . "\r\n"
		. "Content-Type: application/pdf\r\n\r\n"
		. "%PDF-1.4\r\n"
		. '--' . $boundary . "--\r\n",
		$calls[0]['options']['body']);
}

function test_marketplace_trendyol_reject_claim_without_a_document()
{
	$calls = array();

	$result = mp_trendyol_reject_claim(trendyol_reject_test_account(), 'a-1', array('u-1', 'u-1'), '1651', 'Never came', array(),
		trendyol_reject_test_transport(200, '', $calls));

	pg_assert_true($result['ok'], $result['error'] ?? '');
	pg_assert_same(1, $result['data']['rejected']);
	pg_assert_true(strpos($calls[0]['url'], '&claimItemIdList=u-1&') !== false);
	pg_assert_same(1, preg_match('/^--pg[0-9a-f]{32}--\r\n$/', $calls[0]['options']['body']));
}

function test_marketplace_trendyol_claim_multipart_keeps_the_name_on_its_line()
{
	$body = mp_trendyol_claim_multipart(array(array('name' => "a\"b\\c\r\nd.png", 'data' => 'x')), 'B');

	pg_assert_true(strpos($body, 'filename="abcd.png"' . "\r\nContent-Type: image/png\r\n") !== false);
}

function test_marketplace_trendyol_reject_claim_refuses_before_the_call()
{
	$calls = array();

	$transport = trendyol_reject_test_transport(200, '', $calls);

	$photo = trendyol_reject_test_photo();

	foreach (array(
		array('a/../b', array('u-1'), '51', 'Used', array($photo)),
		array('a-1', array(), '51', 'Used', array($photo)),
		array('a-1', array('u 1'), '51', 'Used', array($photo)),
		array('a-1', 'u-1', '51', 'Used', array($photo)),
		array('a-1', array('u-1'), '51', 'Used', array()),
		array('a-1', array('u-1'), '51&x=1', 'Used', array($photo)),
		array('a-1', array('u-1'), '51', '', array($photo)),
	) as $case) {

		$result = mp_trendyol_reject_claim(trendyol_reject_test_account(), $case[0], $case[1], $case[2], $case[3], $case[4], $transport);

		pg_assert_false($result['ok'], var_export(array_slice($case, 0, 4), true));
	}

	pg_assert_same(0, count($calls));
}

function test_marketplace_trendyol_reject_claim_passes_their_refusal_on()
{
	$calls = array();

	$result = mp_trendyol_reject_claim(trendyol_reject_test_account(), 'a-1', array('u-1'), '1651', 'Never came', array(),
		trendyol_reject_test_transport(400, (string) file_get_contents(dirname(__FILE__) . '/fixtures/trendyol/error-400.json'), $calls));

	pg_assert_false($result['ok']);
	pg_assert_true($result['error'] !== '');
}
