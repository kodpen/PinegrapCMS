<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for rejecting a Trendyol return: the reasons read, the checks made
 * before the call and the call itself (mp_trendyol_claim_issue_reasons(),
 * mp_trendyol_reject_claim(), includes/api/outbound/connectors/trendyol.php),
 * and the multipart form it sends (mp_multipart_body(), base.php).
 *
 * Trendyol is never called: the transport here answers from
 * tests/fixtures/trendyol/ (built in the shape of their getClaimIssueReasons
 * and createClaimIssue pages, read October 2026), not recorded from a live
 * account.
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

function trendyol_claim_reject_test_account()
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
function trendyol_claim_reject_test_transport($status, $body, &$calls)
{
	return function ($method, $url, $options) use ($status, $body, &$calls) {

		$calls[] = array('method' => $method, 'url' => $url, 'options' => $options);

		return array('ok' => ($status >= 200 && $status < 300), 'status' => $status, 'body' => $body,
			'error' => '', 'duration_ms' => 1);
	};
}

function trendyol_claim_reject_test_header($options, $name)
{
	foreach ($options['headers'] as $header) {
		if (stripos($header, $name . ':') === 0) {
			return trim(substr($header, strlen($name) + 1));
		}
	}

	return null;
}

/* ------------------------------------------------------------ connector */

function test_marketplace_trendyol_claim_reject_connector_names_the_calls()
{
	pg_assert_true(mp_can('trendyol', 'claim_issue_reasons'));
	pg_assert_true(mp_can('trendyol', 'reject_claim'));
	pg_assert_false(mp_can('n11', 'claim_issue_reasons'));
	pg_assert_false(mp_can('n11', 'reject_claim'));
}

/* -------------------------------------------------------------- reasons */

function test_marketplace_trendyol_claim_issue_reasons_read_the_documented_list()
{
	$calls = array();

	$result = mp_trendyol_claim_issue_reasons(trendyol_claim_reject_test_account(),
		trendyol_claim_reject_test_transport(200, file_get_contents(dirname(__FILE__) . '/fixtures/trendyol/claim-issue-reasons.json'), $calls));

	pg_assert_true($result['ok'], $result['error'] ?? '');
	pg_assert_same('GET', $calls[0]['method']);
	pg_assert_same('https://apigw.trendyol.com/integration/order/claim-issue-reasons', $calls[0]['url']);

	$reasons = $result['data']['reasons'];

	pg_assert_same(15, count($reasons));
	pg_assert_same(array('id' => 51, 'name' => 'Ürün olağan dışı kullanılmış ve/veya müşteri kaynaklı hasar görmüş', 'file' => true), $reasons[0]);

	$without_file = array();

	foreach ($reasons as $reason) {
		if (!$reason['file']) {
			$without_file[] = $reason['id'];
		}
	}

	pg_assert_same(array(451, 1651, 2101), $without_file);
}

function test_marketplace_trendyol_claim_issue_reason_rows_skip_what_cannot_be_named()
{
	$rows = mp_trendyol_claim_issue_reason_rows(array(
		array('id' => 1, 'name' => 'Sahte'),
		array('id' => '2', 'name' => 'Text id'),
		array('id' => 0, 'name' => 'Zero'),
		array('id' => 3, 'name' => ''),
		array('name' => 'No id'),
		'not a row',
	));

	pg_assert_same(array(array('id' => 1, 'name' => 'Sahte', 'file' => true)), $rows);
	pg_assert_same(array(), mp_trendyol_claim_issue_reason_rows(null));
}

function test_marketplace_trendyol_claim_issue_reasons_empty_list_is_an_error()
{
	$calls = array();

	$result = mp_trendyol_claim_issue_reasons(trendyol_claim_reject_test_account(),
		trendyol_claim_reject_test_transport(200, '[]', $calls));

	pg_assert_false($result['ok']);
}

/* --------------------------------------------------------------- reject */

function test_marketplace_trendyol_reject_claim_sends_reason_units_text_and_files()
{
	$calls = array();

	$result = mp_trendyol_reject_claim(trendyol_claim_reject_test_account(), 'f9da2317-876b-4b86-b8f7-0535c3b65731',
		array('b71461e3-d1a0-4c1d-9a6d-18ecbcb5158c', 'c82572f4-e2b1-4d2e-8b7e-29fdcd6269d0', 'b71461e3-d1a0-4c1d-9a6d-18ecbcb5158c'),
		51, "  Kullanılmış & yıkanmış ürün  ",
		array(array('name' => 'Ön yüz.JPG', 'content' => 'JPEGDATA'), array('name' => 'rapor.pdf', 'content' => '%PDF-1.4')),
		trendyol_claim_reject_test_transport(200, '', $calls));

	pg_assert_true($result['ok'], $result['error'] ?? '');
	pg_assert_same(2, $result['data']['rejected']);

	pg_assert_same(1, count($calls));
	pg_assert_same('POST', $calls[0]['method']);
	pg_assert_same('https://apigw.trendyol.com/integration/order/sellers/123456/claims/f9da2317-876b-4b86-b8f7-0535c3b65731/issue'
		. '?claimIssueReasonId=51'
		. '&claimItemIdList=b71461e3-d1a0-4c1d-9a6d-18ecbcb5158c,c82572f4-e2b1-4d2e-8b7e-29fdcd6269d0'
		. '&description=Kullan%C4%B1lm%C4%B1%C5%9F%20%26%20y%C4%B1kanm%C4%B1%C5%9F%20%C3%BCr%C3%BCn',
		$calls[0]['url']);

	$type = trendyol_claim_reject_test_header($calls[0]['options'], 'Content-Type');

	pg_assert_true(preg_match('/^multipart\/form-data; boundary=(.+)$/', (string)$type, $match) === 1, (string)$type);

	$body = $calls[0]['options']['body'];

	pg_assert_same(
		'--' . $match[1] . "\r\n"
		. 'Content-Disposition: form-data; name="files"; filename="Ön yüz.JPG"' . "\r\n"
		. "Content-Type: image/jpeg\r\n\r\nJPEGDATA\r\n"
		. '--' . $match[1] . "\r\n"
		. 'Content-Disposition: form-data; name="files"; filename="rapor.pdf"' . "\r\n"
		. "Content-Type: application/pdf\r\n\r\n%PDF-1.4\r\n"
		. '--' . $match[1] . "--\r\n",
		$body);

	pg_assert_true(trendyol_claim_reject_test_header($calls[0]['options'], 'Authorization') !== null);
}

function test_marketplace_trendyol_reject_claim_without_file_for_an_exempt_reason()
{
	$calls = array();

	$result = mp_trendyol_reject_claim(trendyol_claim_reject_test_account(), 'a-1', array('u-1'), 1651, 'Paket gelmedi',
		array(), trendyol_claim_reject_test_transport(200, '', $calls));

	pg_assert_true($result['ok'], $result['error'] ?? '');

	preg_match('/boundary=(.+)$/', (string)trendyol_claim_reject_test_header($calls[0]['options'], 'Content-Type'), $match);

	pg_assert_same('--' . $match[1] . "--\r\n", $calls[0]['options']['body']);
}

function test_marketplace_trendyol_reject_claim_refuses_before_the_call()
{
	$calls = array();

	$transport = trendyol_claim_reject_test_transport(200, '', $calls);

	$pdf = array(array('name' => 'r.pdf', 'content' => '%PDF'));

	foreach (array(
		'bad claim id'        => array('a/../b', array('u-1'), 51, 'x', $pdf),
		'no units'            => array('a-1', array(), 51, 'x', $pdf),
		'units not a list'    => array('a-1', 'u-1', 51, 'x', $pdf),
		'bad unit id'         => array('a-1', array('u 1'), 51, 'x', $pdf),
		'reason as text'      => array('a-1', array('u-1'), '51', 'x', $pdf),
		'reason zero'         => array('a-1', array('u-1'), 0, 'x', $pdf),
		'blank text'          => array('a-1', array('u-1'), 51, '   ', $pdf),
		'text too long'       => array('a-1', array('u-1'), 51, str_repeat('ş', 501), $pdf),
		'text not utf-8'      => array('a-1', array('u-1'), 51, "\xC3\x28", $pdf),
		'reason needs a file' => array('a-1', array('u-1'), 51, 'x', array()),
		'too many files'      => array('a-1', array('u-1'), 51, 'x', array_fill(0, 6, $pdf[0])),
		'type not taken'      => array('a-1', array('u-1'), 51, 'x', array(array('name' => 'r.exe', 'content' => 'MZ'))),
		'no extension'        => array('a-1', array('u-1'), 51, 'x', array(array('name' => 'rapor', 'content' => 'x'))),
		'empty file'          => array('a-1', array('u-1'), 51, 'x', array(array('name' => 'r.pdf', 'content' => ''))),
		'file too large'      => array('a-1', array('u-1'), 51, 'x', array(array('name' => 'r.pdf', 'content' => str_repeat('a', 5242881)))),
	) as $label => $case) {

		$result = mp_trendyol_reject_claim(trendyol_claim_reject_test_account(), $case[0], $case[1], $case[2], $case[3], $case[4], $transport);

		pg_assert_false($result['ok'], $label);
	}

	pg_assert_same(0, count($calls));

	// The limits themselves pass: 500 characters, five files of 5 MB.
	$result = mp_trendyol_reject_claim(trendyol_claim_reject_test_account(), 'a-1', array('u-1'), 51, str_repeat('ş', 500),
		array_fill(0, 5, array('name' => 'r.pdf', 'content' => str_repeat('a', 5242880))), $transport);

	pg_assert_true($result['ok'], $result['error'] ?? '');
}

function test_marketplace_trendyol_reject_claim_passes_their_refusal_on()
{
	$calls = array();

	$result = mp_trendyol_reject_claim(trendyol_claim_reject_test_account(), 'a-1', array('u-1'), 451, 'Analiz',
		array(), trendyol_claim_reject_test_transport(400, file_get_contents(dirname(__FILE__) . '/fixtures/trendyol/error-400.json'), $calls));

	pg_assert_false($result['ok']);
	pg_assert_true($result['error'] !== '');
}

/* ------------------------------------------------------------ multipart */

function test_marketplace_multipart_body_cleans_the_file_name_and_type()
{
	$form = mp_multipart_body('files', array(
		array('name' => "a\"b\r\nX-Evil: 1\\.pdf", 'type' => "text/plain\r\nX: y", 'content' => 'c'),
	), 'BOUNDARY');

	pg_assert_same('multipart/form-data; boundary=BOUNDARY', $form['type']);
	pg_assert_same(
		"--BOUNDARY\r\n"
		. 'Content-Disposition: form-data; name="files"; filename="a_b__X-Evil: 1_.pdf"' . "\r\n"
		. "Content-Type: application/octet-stream\r\n\r\nc\r\n"
		. "--BOUNDARY--\r\n",
		$form['body']);
}

function test_marketplace_multipart_body_draws_a_new_boundary_on_a_clash()
{
	$form = mp_multipart_body('files', array(array('name' => 'a.pdf', 'type' => 'application/pdf', 'content' => 'xxBOUNDARYxx')), 'BOUNDARY');

	pg_assert_true($form['type'] !== 'multipart/form-data; boundary=BOUNDARY');
	pg_assert_true(strpos($form['body'], '--BOUNDARY') === false);
}
