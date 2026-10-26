<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the returns (claims) read from Trendyol and their approval, and
 * the buyers' questions and their answers (mp_trendyol_claims(),
 * mp_trendyol_approve_claim(), mp_trendyol_questions(),
 * mp_trendyol_answer_question() and their readers,
 * includes/api/outbound/connectors/trendyol.php).
 *
 * Trendyol is never called: the connector takes a transport with the signature
 * of api_http_request(), and the one here answers from tests/fixtures/trendyol/
 * (built in the shape of their getClaims, approveClaimLineItems,
 * getQuestionFilter and answerQuestion pages, read October 2026), not recorded
 * from a live account.
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

function trendyol_remaining_test_account()
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

function trendyol_remaining_test_fixture($name)
{
	return (string) file_get_contents(dirname(__FILE__) . '/fixtures/trendyol/' . $name);
}

// A transport that answers each call with the next of $answers
// (array(status, body)), the last one again when they run out, and writes
// every call into $calls.
function trendyol_remaining_test_transport($answers, &$calls)
{
	return function ($method, $url, $options) use ($answers, &$calls) {

		$calls[] = array('method' => $method, 'url' => $url, 'options' => $options);

		$answer = $answers[min(count($calls), count($answers)) - 1];

		return array('ok' => ($answer[0] >= 200 && $answer[0] < 300), 'status' => $answer[0], 'body' => $answer[1],
			'error' => '', 'duration_ms' => 1);
	};
}

// A full page of $size claims, each with one waiting unit.
function trendyol_remaining_test_page($page, $size, $total_pages)
{
	$content = array();

	for ($i = 0; $i < $size; $i++) {
		$n = $page * $size + $i;
		$content[] = array(
			'id'          => sprintf('c%07d-0000-4000-8000-000000000000', $n),
			'orderNumber' => (string)(70000000 + $n),
			'claimDate'   => 1761300000000,
			'items'       => array(array(
				'orderLine'  => array('id' => 1000 + $n, 'productName' => 'P' . $n, 'barcode' => 'B' . $n, 'price' => 10),
				'claimItems' => array(array('id' => sprintf('u%07d-0000-4000-8000-000000000000', $n),
					'claimItemStatus' => array('name' => 'WaitingInAction'))),
			)),
		);
	}

	return json_encode(array('totalPages' => $total_pages, 'page' => $page, 'size' => $size, 'content' => $content));
}

/* ------------------------------------------------------------ connector */

function test_marketplace_trendyol_claims_connector_names_the_calls()
{
	pg_assert_true(mp_can('trendyol', 'claims'));
	pg_assert_true(mp_can('trendyol', 'approve_claim'));
	pg_assert_false(mp_can('n11', 'claims'));
	pg_assert_false(mp_can('n11', 'approve_claim'));
}

/* --------------------------------------------------------------- reader */

function test_marketplace_trendyol_claim_rows_read_the_documented_shape()
{
	$rows = mp_trendyol_claim_rows(json_decode(trendyol_remaining_test_fixture('claims-page0.json'), true));

	pg_assert_same(2, count($rows));

	$claim = $rows[0];

	pg_assert_same('f9da2317-876b-4b86-b8f7-0535c3b65731', $claim['claim_id']);
	pg_assert_same('65745805', $claim['order_number']);
	pg_assert_same('3853354', $claim['package_id']);
	pg_assert_same(1761300000, $claim['claimed']);
	pg_assert_same('Ayşe Yılmaz', $claim['customer']);
	pg_assert_same('Trendyol Express Marketplace', $claim['carrier']);
	pg_assert_same('7260242095704', $claim['tracking'], 'the sender number, not the overflowing tracking number');

	pg_assert_same(3, count($claim['units']));

	pg_assert_same(array(
		'id'      => 'b71461e3-d1a0-4c1d-9a6d-18ecbcb5158c',
		'barcode' => '8680000000017',
		'product' => 'Keten Gömlek Beyaz, M',
		'price'   => 34990,
		'status'  => 'WaitingInAction',
		'waiting' => true,
		'reason'  => 'Beden büyük geldi',
		'note'    => 'Bir beden küçüğünü alacağım',
	), $claim['units'][0]);

	pg_assert_same('Created', $claim['units'][2]['status']);
	pg_assert_false($claim['units'][2]['waiting'], 'still on its way back');
	pg_assert_same(12995, $claim['units'][2]['price']);

	pg_assert_same('Accepted', $rows[1]['units'][0]['status']);
	pg_assert_same('', $rows[1]['tracking']);
}

function test_marketplace_trendyol_claim_rows_skip_what_cannot_be_named()
{
	$rows = mp_trendyol_claim_rows(array('content' => array(
		'not a claim',
		array('orderNumber' => '1', 'items' => array()),
		array('id' => 'bad id/../x', 'items' => array()),
		array('id' => 'a-1', 'items' => array(
			array('orderLine' => 'x', 'claimItems' => array(array('id' => 'u-1', 'claimItemStatus' => array('name' => 'Created')))),
			array('orderLine' => array('barcode' => 'B'), 'claimItems' => array(
				array('id' => '', 'claimItemStatus' => array('name' => 'WaitingInAction')),
				array('id' => 'u 2', 'claimItemStatus' => array('name' => 'WaitingInAction')),
				'x',
				array('id' => 'u-3'),
			)),
		)),
	)));

	pg_assert_same(1, count($rows));
	pg_assert_same('a-1', $rows[0]['claim_id']);
	pg_assert_same(array('u-1', 'u-3'), array_column($rows[0]['units'], 'id'));
	pg_assert_same('', $rows[0]['units'][0]['barcode']);
	pg_assert_same('', $rows[0]['units'][1]['status']);

	pg_assert_same(array(), mp_trendyol_claim_rows(null));
	pg_assert_same(array(), mp_trendyol_claim_rows(array('content' => 'x')));
}

function test_marketplace_trendyol_claim_id_fits()
{
	pg_assert_true(mp_trendyol_claim_id_fits('f9da2317-876b-4b86-b8f7-0535c3b65731'));
	pg_assert_true(mp_trendyol_claim_id_fits('12345'));

	foreach (array('', ' ', '-abc', 'a/b', 'a b', '../x', 'a?b', str_repeat('a', 65), null, array('a'), 12.5) as $id) {
		pg_assert_false(mp_trendyol_claim_id_fits($id), var_export($id, true));
	}
}

function test_marketplace_trendyol_claim_waiting_takes_only_waiting_units()
{
	$rows = mp_trendyol_claim_rows(json_decode(trendyol_remaining_test_fixture('claims-page0.json'), true));

	pg_assert_same(array('b71461e3-d1a0-4c1d-9a6d-18ecbcb5158c', 'c82572f4-e2b1-4d2e-8b7e-29fdcd6269d0'),
		mp_claim_waiting($rows[0]));

	pg_assert_same(array(), mp_claim_waiting($rows[1]));
	pg_assert_same(array(), mp_claim_waiting(array()));
}

/* ----------------------------------------------------------------- read */

function test_marketplace_trendyol_claims_read_the_last_fourteen_days()
{
	$calls = array();

	$now = 1761400000;

	$result = mp_trendyol_claims(trendyol_remaining_test_account(),
		trendyol_remaining_test_transport(array(array(200, trendyol_remaining_test_fixture('claims-page0.json'))), $calls),
		$now);

	pg_assert_true($result['ok'], $result['error'] ?? '');
	pg_assert_same(2, count($result['data']['claims']));
	pg_assert_false($result['data']['more']);

	pg_assert_same(1, count($calls));
	pg_assert_same('GET', $calls[0]['method']);

	$parts = parse_url($calls[0]['url']);

	pg_assert_same('/integration/order/sellers/123456/claims', $parts['path']);

	parse_str($parts['query'], $query);

	pg_assert_same((string)(($now - 1209600 + 3600) * 1000), $query['startDate']);
	pg_assert_same((string)($now * 1000), $query['endDate']);
	pg_assert_same('0', $query['page']);
	pg_assert_same('50', $query['size']);
	pg_assert_false(isset($query['claimItemStatus']), 'every status is read');
}

function test_marketplace_trendyol_claims_stop_at_four_pages()
{
	$calls = array();

	$answers = array();

	for ($page = 0; $page < 6; $page++) {
		$answers[] = array(200, trendyol_remaining_test_page($page, 50, 6));
	}

	$result = mp_trendyol_claims(trendyol_remaining_test_account(),
		trendyol_remaining_test_transport($answers, $calls), 1761400000);

	pg_assert_true($result['ok']);
	pg_assert_same(4, count($calls));
	pg_assert_same(200, count($result['data']['claims']));
	pg_assert_true($result['data']['more']);
	pg_assert_true(strpos($calls[3]['url'], '&page=3&') !== false);
}

function test_marketplace_trendyol_claims_stop_at_the_last_page()
{
	$calls = array();

	$result = mp_trendyol_claims(trendyol_remaining_test_account(),
		trendyol_remaining_test_transport(array(
			array(200, trendyol_remaining_test_page(0, 50, 2)),
			array(200, trendyol_remaining_test_page(1, 50, 2)),
		), $calls), 1761400000);

	pg_assert_true($result['ok']);
	pg_assert_same(2, count($calls), 'totalPages ends the read even after a full page');
	pg_assert_same(100, count($result['data']['claims']));
	pg_assert_false($result['data']['more']);
}

function test_marketplace_trendyol_claims_failure_is_an_error()
{
	$calls = array();

	$result = mp_trendyol_claims(trendyol_remaining_test_account(),
		trendyol_remaining_test_transport(array(
			array(200, trendyol_remaining_test_page(0, 50, 3)),
			array(400, trendyol_remaining_test_fixture('error-400.json')),
		), $calls), 1761400000);

	pg_assert_false($result['ok'], 'a half read is not shown as the list');
	pg_assert_true($result['error'] !== '');
}

/* -------------------------------------------------------------- approve */

function test_marketplace_trendyol_approve_claim_sends_the_units()
{
	$calls = array();

	$result = mp_trendyol_approve_claim(trendyol_remaining_test_account(), 'f9da2317-876b-4b86-b8f7-0535c3b65731',
		array('b71461e3-d1a0-4c1d-9a6d-18ecbcb5158c', 'c82572f4-e2b1-4d2e-8b7e-29fdcd6269d0'),
		trendyol_remaining_test_transport(array(array(200, '')), $calls));

	pg_assert_true($result['ok'], $result['error'] ?? '');
	pg_assert_same(2, $result['data']['approved']);

	pg_assert_same(1, count($calls));
	pg_assert_same('PUT', $calls[0]['method']);
	pg_assert_same('https://apigw.trendyol.com/integration/order/sellers/123456/claims/f9da2317-876b-4b86-b8f7-0535c3b65731/items/approve',
		$calls[0]['url']);
	pg_assert_same('{"claimLineItemIdList":["b71461e3-d1a0-4c1d-9a6d-18ecbcb5158c","c82572f4-e2b1-4d2e-8b7e-29fdcd6269d0"]}',
		$calls[0]['options']['body']);
}

function test_marketplace_trendyol_approve_claim_refuses_before_the_call()
{
	$calls = array();

	$transport = trendyol_remaining_test_transport(array(array(200, '')), $calls);

	foreach (array(
		array('a/../b', array('u-1')),
		array('a-1', array()),
		array('a-1', array('u 1')),
		array('a-1', 'u-1'),
	) as $case) {

		$result = mp_trendyol_approve_claim(trendyol_remaining_test_account(), $case[0], $case[1], $transport);

		pg_assert_false($result['ok'], var_export($case, true));
	}

	pg_assert_same(0, count($calls));
}

function test_marketplace_trendyol_approve_claim_passes_their_refusal_on()
{
	$calls = array();

	$result = mp_trendyol_approve_claim(trendyol_remaining_test_account(), 'a-1', array('u-1'),
		trendyol_remaining_test_transport(array(array(400, trendyol_remaining_test_fixture('error-400.json'))), $calls));

	pg_assert_false($result['ok']);
	pg_assert_true($result['error'] !== '');
}

/* ------------------------------------------------------------ questions */

function test_marketplace_trendyol_questions_connector_names_the_calls()
{
	pg_assert_true(mp_can('trendyol', 'questions'));
	pg_assert_true(mp_can('trendyol', 'answer_question'));
	pg_assert_false(mp_can('n11', 'questions'));
	pg_assert_false(mp_can('n11', 'answer_question'));
}

function test_marketplace_trendyol_question_rows_read_the_documented_shape()
{
	$rows = mp_trendyol_question_rows(json_decode(trendyol_remaining_test_fixture('questions-page0.json'), true));

	pg_assert_same(3, count($rows));

	pg_assert_same(array(
		'id'          => '3045671234',
		'text'        => 'Bu gömleğin kumaşı yüzde yüz keten mi?',
		'status'      => 'WAITING_FOR_ANSWER',
		'waiting'     => true,
		'asked'       => 1761300000,
		'user'        => 'A** Y**',
		'product'     => 'Keten Gömlek Beyaz',
		'product_url' => 'https://www.trendyol.com/marka/keten-gomlek-beyaz-p-123456',
		'answer'      => '',
		'reason'      => '',
	), $rows[0]);

	pg_assert_false($rows[1]['waiting']);
	pg_assert_same('Siparişiniz aynı gün kargoya verilir.', $rows[1]['answer']);
	pg_assert_same('', $rows[1]['user'], 'the name is hidden');
	pg_assert_same('', $rows[1]['product_url'], 'only an https link is kept');

	pg_assert_same('REJECTED', $rows[2]['status']);
	pg_assert_same('0555 000 00 00 numarasından ulaşabilirsiniz.', $rows[2]['answer'], 'the rejected answer, when there is no active one');
	pg_assert_same('Kişisel bilgi içeriyor', $rows[2]['reason']);
}

function test_marketplace_trendyol_question_rows_skip_what_cannot_be_named()
{
	$rows = mp_trendyol_question_rows(array('content' => array(
		'x',
		array('text' => 'no id'),
		array('id' => '0123', 'text' => 'leading zero'),
		array('id' => 'abc', 'text' => 'not a number'),
		array('id' => 77, 'text' => '', 'status' => 'WAITING_FOR_ANSWER'),
		array('id' => 78, 'text' => 'Ok?', 'userName' => 'B** K**', 'showUserName' => false),
	)));

	pg_assert_same(array('78'), array_column($rows, 'id'), 'a question without text is left out too');
	pg_assert_same('', $rows[0]['user']);
	pg_assert_same(array(), mp_trendyol_question_rows(null));
}

function test_marketplace_trendyol_answer_problem()
{
	pg_assert_same('', mp_trendyol_answer_problem('Evet, yüzde yüz keten.'));
	pg_assert_same('', mp_trendyol_answer_problem('  0123456789  '), 'ten characters after trimming');
	pg_assert_same('', mp_trendyol_answer_problem(str_repeat('ğ', 2000)), 'characters, not bytes');

	foreach (array('', '   ', 'Evet.', '012345678', str_repeat('a', 2001), null, array('x')) as $text) {
		pg_assert_true(mp_trendyol_answer_problem($text) !== '', var_export($text, true));
	}
}

function test_marketplace_trendyol_questions_read_the_last_two_weeks()
{
	$calls = array();

	$now = 1761400000;

	$result = mp_trendyol_questions(trendyol_remaining_test_account(),
		trendyol_remaining_test_transport(array(array(200, trendyol_remaining_test_fixture('questions-page0.json'))), $calls),
		$now);

	pg_assert_true($result['ok'], $result['error'] ?? '');
	pg_assert_same(3, count($result['data']['questions']));
	pg_assert_false($result['data']['more']);

	pg_assert_same(1, count($calls));
	pg_assert_same('GET', $calls[0]['method']);

	$parts = parse_url($calls[0]['url']);

	pg_assert_same('/integration/qna/sellers/123456/questions/filter', $parts['path']);

	parse_str($parts['query'], $query);

	pg_assert_same((string)(($now - 1209600 + 3600) * 1000), $query['startDate']);
	pg_assert_same((string)($now * 1000), $query['endDate']);
	pg_assert_same('CreatedDate', $query['orderByField']);
	pg_assert_same('DESC', $query['orderByDirection']);
	pg_assert_same('0', $query['page']);
	pg_assert_same('50', $query['size']);
	pg_assert_false(isset($query['status']), 'every status is read');
}

function test_marketplace_trendyol_questions_stop_at_four_pages()
{
	$calls = array();

	$answers = array();

	for ($page = 0; $page < 6; $page++) {
		$content = array();
		for ($i = 0; $i < 50; $i++) {
			$content[] = array('id' => 1000 + $page * 50 + $i, 'text' => 'Q' . $i, 'status' => 'WAITING_FOR_ANSWER');
		}
		$answers[] = array(200, json_encode(array('content' => $content, 'totalPages' => 6, 'page' => $page)));
	}

	$result = mp_trendyol_questions(trendyol_remaining_test_account(),
		trendyol_remaining_test_transport($answers, $calls), 1761400000);

	pg_assert_true($result['ok']);
	pg_assert_same(4, count($calls));
	pg_assert_same(200, count($result['data']['questions']));
	pg_assert_true($result['data']['more']);
}

function test_marketplace_trendyol_questions_failure_is_an_error()
{
	$calls = array();

	$result = mp_trendyol_questions(trendyol_remaining_test_account(),
		trendyol_remaining_test_transport(array(array(400, trendyol_remaining_test_fixture('error-400.json'))), $calls),
		1761400000);

	pg_assert_false($result['ok']);
	pg_assert_true($result['error'] !== '');
}

function test_marketplace_trendyol_answer_question_sends_the_text()
{
	$calls = array();

	$result = mp_trendyol_answer_question(trendyol_remaining_test_account(), '3045671234', '  Evet, yüzde yüz keten.  ',
		trendyol_remaining_test_transport(array(array(200, '"Your answer has been successfully saved."')), $calls));

	pg_assert_true($result['ok'], $result['error'] ?? '');

	pg_assert_same(1, count($calls));
	pg_assert_same('POST', $calls[0]['method']);
	pg_assert_same('https://apigw.trendyol.com/integration/qna/sellers/123456/questions/3045671234/answers', $calls[0]['url']);
	pg_assert_same('{"text":"Evet, yüzde yüz keten."}', $calls[0]['options']['body']);
}

function test_marketplace_trendyol_answer_question_refuses_before_the_call()
{
	$calls = array();

	$transport = trendyol_remaining_test_transport(array(array(200, '')), $calls);

	foreach (array(array('0123', 'Evet, yüzde yüz keten.'), array('12/../3', 'Evet, yüzde yüz keten.'),
		array('3045671234', 'Evet.')) as $case) {

		pg_assert_false(mp_trendyol_answer_question(trendyol_remaining_test_account(), $case[0], $case[1], $transport)['ok'],
			var_export($case, true));
	}

	pg_assert_same(0, count($calls));
}

function test_marketplace_trendyol_answer_question_passes_their_refusal_on()
{
	$calls = array();

	$result = mp_trendyol_answer_question(trendyol_remaining_test_account(), '3045671234', 'Evet, yüzde yüz keten.',
		trendyol_remaining_test_transport(array(array(400, trendyol_remaining_test_fixture('error-400.json'))), $calls));

	pg_assert_false($result['ok']);
	pg_assert_same('Kategori bulunamadı', $result['error']);
}
