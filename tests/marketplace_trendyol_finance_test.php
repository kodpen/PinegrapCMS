<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Trendyol finance read that gives an order's deductions
 * (mp_trendyol_deductions(), mp_trendyol_finance_rows()): the settlements
 * path and its 15-day window, one type a call with its sign, the pages, the
 * call budget and the state a later run resumes from.
 *
 * Trendyol is never called: the connector takes a transport and the tests
 * answer from recorded pages shaped after Trendyol's reference.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/base.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/trendyol.php');

function trendyol_finance_test_account()
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

function trendyol_finance_test_fixture($name)
{
	return (string) file_get_contents(dirname(__FILE__) . '/fixtures/trendyol/' . $name);
}

// Answers each call with the next of $answers (array(status, body)), the last
// one again when they run out, and writes every call into $calls.
function trendyol_finance_test_transport($answers, &$calls)
{
	return function ($method, $url, $options) use ($answers, &$calls) {

		$calls[] = array('method' => $method, 'url' => $url, 'options' => $options);

		$answer = $answers[min(count($calls), count($answers)) - 1];

		return array('ok' => ($answer[0] >= 200 && $answer[0] < 300), 'status' => $answer[0], 'body' => $answer[1],
			'error' => '', 'duration_ms' => 1);
	};
}

function trendyol_finance_test_query($url)
{
	parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

	return $query;
}

// An empty page of settlements.
function trendyol_finance_test_empty()
{
	return json_encode(array('page' => 0, 'size' => 500, 'totalPages' => 0, 'totalElements' => 0, 'content' => array()));
}

// A full page of $size sale records, $total_pages in all.
function trendyol_finance_test_full_page($page, $size, $total_pages)
{
	$content = array();

	for ($i = 0; $i < $size; $i++) {
		$content[] = array('id' => 'p' . $page . '-' . $i, 'transactionDate' => 1763460000000, 'commissionAmount' => 1.0,
			'orderNumber' => '9' . $i, 'shipmentPackageId' => 5000 + $i, 'affiliate' => 'TRENDYOLTR');
	}

	return json_encode(array('page' => $page, 'size' => $size, 'totalPages' => $total_pages, 'content' => $content));
}

function test_marketplace_trendyol_finance_connector_names_the_call()
{
	pg_assert_true(mp_can('trendyol', 'deductions'));
	pg_assert_false(mp_can('n11', 'deductions'));
}

// The commission of each record, its sign from the type asked for, in minor
// units with round(); a record with no commission, no usable id or a
// currency not known is left out.
function test_marketplace_trendyol_finance_rows_read_the_documented_shape()
{
	$rows = mp_trendyol_finance_rows(json_decode(trendyol_finance_test_fixture('settlements-sale-page0.json'), true), 1);

	pg_assert_same(3, count($rows));
	pg_assert_same('settlement:725041340', $rows[0]['ref']);
	pg_assert_same('1111111111', $rows[0]['package_id']);
	pg_assert_same('501915861', $rows[0]['order_number']);
	pg_assert_same(6750, $rows[0]['amount']);
	pg_assert_same('TRY', $rows[0]['currency']);
	pg_assert_same(1763460000, $rows[0]['date']);
	pg_assert_same(625, $rows[1]['amount']);
	pg_assert_same('', $rows[2]['currency'], 'another affiliate has no known currency');
}

function test_marketplace_trendyol_finance_rows_take_the_sign_of_the_type()
{
	$rows = mp_trendyol_finance_rows(json_decode(trendyol_finance_test_fixture('settlements-return-page0.json'), true), -1);

	pg_assert_same(1, count($rows));
	pg_assert_same(-625, $rows[0]['amount']);

	$rows = mp_trendyol_finance_rows(array('content' => array(
		array('id' => 'a/b', 'commissionAmount' => 2, 'shipmentPackageId' => 1),
		array('id' => '77', 'commissionAmount' => 2),
		array('id' => '78', 'commissionAmount' => 'x', 'shipmentPackageId' => 1),
	)), 1);

	pg_assert_same(array(), $rows);
}

function test_marketplace_trendyol_finance_types_and_signs()
{
	pg_assert_same(array('Sale' => 1, 'Return' => -1, 'Discount' => -1, 'DiscountCancel' => 1), mp_trendyol_finance_types());
}

// One window from the state's start, one type a call: the path, the
// documented parameters, and the window never longer than 15 days.
function test_marketplace_trendyol_deductions_read_each_type_of_a_window()
{
	$calls = array();
	$now = 1763900000;
	$from = $now - 3 * 86400;

	$result = mp_trendyol_deductions(trendyol_finance_test_account(), array('from' => $from, 'type' => 0, 'page' => 0), 10,
		trendyol_finance_test_transport(array(
			array(200, trendyol_finance_test_fixture('settlements-sale-page0.json')),
			array(200, trendyol_finance_test_fixture('settlements-return-page0.json')),
			array(200, trendyol_finance_test_empty()),
		), $calls), $now);

	pg_assert_true($result['ok']);
	pg_assert_same(4, count($calls));
	pg_assert_same('GET', $calls[0]['method']);
	pg_assert_same('/integration/finance/che/sellers/123456/settlements', parse_url($calls[0]['url'], PHP_URL_PATH));

	$types = array();

	foreach ($calls as $call) {
		$query = trendyol_finance_test_query($call['url']);
		$types[] = $query['transactionType'];
		pg_assert_same((string) ($from * 1000), $query['startDate']);
		pg_assert_same((string) ($now * 1000), $query['endDate']);
		pg_assert_same('0', $query['page']);
		pg_assert_same('500', $query['size']);
		pg_assert_same('123456', $query['supplierId']);
	}

	pg_assert_same(array('Sale', 'Return', 'Discount', 'DiscountCancel'), $types);
	pg_assert_same(4, count($result['data']['records']));
	pg_assert_same(-625, $result['data']['records'][3]['amount']);
	pg_assert_true($result['data']['caught_up']);
	// Caught up: the next run reads the last two days again.
	pg_assert_same(array('from' => $now - 172800, 'type' => 0, 'page' => 0), $result['data']['state']);
}

// A state far behind reads 14-day windows one after the other.
function test_marketplace_trendyol_deductions_move_window_by_window()
{
	$calls = array();
	$now = 1763900000;
	$from = $now - 40 * 86400;

	$result = mp_trendyol_deductions(trendyol_finance_test_account(), array('from' => $from, 'type' => 0, 'page' => 0), 6,
		trendyol_finance_test_transport(array(array(200, trendyol_finance_test_empty())), $calls), $now);

	pg_assert_true($result['ok']);
	pg_assert_same(6, count($calls));

	$first = trendyol_finance_test_query($calls[0]['url']);
	pg_assert_same((string) (($from + 14 * 86400) * 1000), $first['endDate']);

	$fifth = trendyol_finance_test_query($calls[4]['url']);
	pg_assert_same((string) (($from + 14 * 86400) * 1000), $fifth['startDate']);
	pg_assert_same('Sale', $fifth['transactionType']);

	pg_assert_false($result['data']['caught_up']);
	pg_assert_same(array('from' => $from + 14 * 86400, 'type' => 2, 'page' => 0), $result['data']['state']);
}

// Full pages go on to the next page; the budget stops the read on the page
// it would have read next, and the state says so.
function test_marketplace_trendyol_deductions_page_and_stop_at_the_budget()
{
	$calls = array();
	$now = 1763900000;

	$result = mp_trendyol_deductions(trendyol_finance_test_account(), array('from' => $now - 86400, 'type' => 0, 'page' => 0), 2,
		trendyol_finance_test_transport(array(
			array(200, trendyol_finance_test_full_page(0, 500, 3)),
			array(200, trendyol_finance_test_full_page(1, 500, 3)),
		), $calls), $now);

	pg_assert_true($result['ok']);
	pg_assert_same(2, count($calls));
	pg_assert_same('1', trendyol_finance_test_query($calls[1]['url'])['page']);
	pg_assert_same(1000, count($result['data']['records']));
	pg_assert_same(array('from' => $now - 86400, 'type' => 0, 'page' => 2), $result['data']['state']);
	pg_assert_false($result['data']['caught_up']);
}

function test_marketplace_trendyol_deductions_stop_at_the_last_page()
{
	$calls = array();
	$now = 1763900000;

	$result = mp_trendyol_deductions(trendyol_finance_test_account(), array('from' => $now - 86400, 'type' => 3, 'page' => 0), 5,
		trendyol_finance_test_transport(array(array(200, trendyol_finance_test_full_page(0, 500, 1))), $calls), $now);

	pg_assert_true($result['ok']);
	pg_assert_same(1, count($calls));
	pg_assert_true($result['data']['caught_up']);
}

// A failed page is an error and nothing of the run is given back: the state
// the caller kept is read again.
function test_marketplace_trendyol_deductions_failure_is_an_error()
{
	$calls = array();
	$now = 1763900000;

	$result = mp_trendyol_deductions(trendyol_finance_test_account(), array('from' => $now - 86400, 'type' => 0, 'page' => 0), 5,
		trendyol_finance_test_transport(array(
			array(200, trendyol_finance_test_fixture('settlements-sale-page0.json')),
			array(429, '{}'),
		), $calls), $now);

	pg_assert_false($result['ok']);
	pg_assert_same(2, count($calls));
}

// A state from the future or not a state at all starts over safely.
function test_marketplace_trendyol_deductions_clamp_a_bad_state()
{
	$calls = array();
	$now = 1763900000;

	$result = mp_trendyol_deductions(trendyol_finance_test_account(), array('from' => $now + 500, 'type' => 9, 'page' => -3), 1,
		trendyol_finance_test_transport(array(array(200, trendyol_finance_test_empty())), $calls), $now);

	pg_assert_true($result['ok']);

	$query = trendyol_finance_test_query($calls[0]['url']);
	pg_assert_same('Sale', $query['transactionType']);
	pg_assert_same('0', $query['page']);
	pg_assert_true((int) $query['startDate'] < (int) $query['endDate']);
}
