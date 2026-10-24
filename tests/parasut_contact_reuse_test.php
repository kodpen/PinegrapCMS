<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the buyer card of the order screen's legacy Paraşüt buttons
 * (includes/fn/parasut.php, parasut_sync_contact()): a buyer with a VKN/TCKN
 * goes on the card Paraşüt already holds for the number, found the way the
 * ERP's driver finds it; a buyer without one, or with the final consumer's,
 * gets a card of the order's own.
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
 * A request stand-in for parasut_order_contact_for(): GET lists the cards a
 * page at a time as Paraşüt does (25 a page, meta.total_pages), POST opens a
 * card with id 9001. Every call is recorded.
 *
 * @param array $cards        Listed cards, newest first: ['id' => ..., 'attributes' => ...]
 * @param array $calls        Receives [method, path, body] of each call
 * @param array $answers      method => the answer to give instead (a 404, a 500, a refused POST)
 * @param int   $total_pages  meta.total_pages to report; 0 counts the cards
 * @return callable
 */
function parasut_contact_reuse_test_request($cards, &$calls, $answers = array(), $total_pages = 0)
{
	return function ($method, $path, $body) use ($cards, &$calls, $answers, $total_pages) {
		$calls[] = array($method, $path, $body);

		if (isset($answers[$method])) {
			return $answers[$method];
		}

		if ($method === 'POST') {
			return array('http_code' => 201, 'body' => array('data' => array('id' => '9001', 'type' => 'contacts')), 'raw' => '{}');
		}

		$page = preg_match('/page\[number\]=(\d+)/', (string) $path, $match) ? (int) $match[1] : 1;
		$pages = ($total_pages > 0) ? $total_pages : max(1, (int) ceil(count($cards) / 25));

		return array('http_code' => 200, 'raw' => '{}', 'body' => array(
			'data' => array_slice($cards, ($page - 1) * 25, 25),
			'meta' => array('total_count' => count($cards), 'total_pages' => $pages, 'current_page' => $page),
		));
	};
}

// A card as the contacts list gives it.
function parasut_contact_reuse_test_card($id, $tax_number, $archived = false)
{
	return array('id' => (string) $id, 'type' => 'contacts', 'attributes' => array(
		'name' => 'PG Test Alıcı ' . $id, 'contact_type' => 'person', 'account_type' => 'customer',
		'tax_number' => $tax_number, 'archived' => $archived,
	));
}

// What a new card is opened with (parasut_order_contact_attributes()).
function parasut_contact_reuse_test_attributes()
{
	return array('account_type' => 'customer', 'contact_type' => 'person', 'name' => 'Ayşe Demir',
		'email' => 'ayse@example.com', 'phone' => '', 'tax_number' => '10000000146', 'address' => 'Test Sokak 1');
}

// The calls of one method.
function parasut_contact_reuse_test_calls_of($calls, $method)
{
	return count(array_filter($calls, function ($call) use ($method) {
		return $call[0] === $method;
	}));
}

// Only a number that passes GİB's check digits is looked up, as digits: not
// an empty field, not the final consumer's 11111111111 (many buyers carry
// it), not a mistyped number or a placeholder that fails the check. A real
// VKN/TCKN always passes; a placeholder that passes it is looked up like a
// real number.
function test_parasut_order_contact_search_number()
{
	pg_assert_same('10000000146', parasut_order_contact_search_number('10000000146'), 'TCKN');
	pg_assert_same('10000000146', parasut_order_contact_search_number(' 100 000 001 46 '), 'spaces');
	pg_assert_same('1234567808', parasut_order_contact_search_number('1234567808'), 'VKN');
	pg_assert_same('', parasut_order_contact_search_number(''), 'empty');
	pg_assert_same('', parasut_order_contact_search_number(null), 'none');
	pg_assert_same('', parasut_order_contact_search_number('11111111111'), 'final consumer');
	pg_assert_same('', parasut_order_contact_search_number('00000000000'), 'zeros');
	pg_assert_same('', parasut_order_contact_search_number('2222222222'), 'a placeholder that fails the check');
	pg_assert_same('1234567890', parasut_order_contact_search_number('1234567890'), 'a placeholder that passes the check');
	pg_assert_same('', parasut_order_contact_search_number('10000000147'), 'wrong check digit');
	pg_assert_same('', parasut_order_contact_search_number('123456789'), 'nine digits');
	pg_assert_same('', parasut_order_contact_search_number('abc'), 'no digits');
}

// A repeat buyer goes on the card Paraşüt already holds for the number, read
// newest first as the ERP's driver reads it; nothing is opened.
function test_parasut_order_contact_for_reuses_the_card_of_the_number()
{
	$calls = array();
	$cards = array(parasut_contact_reuse_test_card(7001, '10000000146'));
	$contact = parasut_order_contact_for('10000000146', parasut_contact_reuse_test_attributes(), parasut_contact_reuse_test_request($cards, $calls));

	pg_assert_same(array('id' => '7001', 'reused' => true, 'error' => ''), $contact);
	pg_assert_same(array(array('GET', '/contacts?filter[tax_number]=10000000146&sort=-id&page[number]=1&page[size]=25', null)), $calls);
}

// An archived card, and a card whose number only begins the same way, are
// passed over; the first one left is taken.
function test_parasut_order_contact_for_skips_archived_and_other_numbers()
{
	$calls = array();
	$cards = array(
		parasut_contact_reuse_test_card(7004, '10000000146', true),
		parasut_contact_reuse_test_card(7003, '100000001460'),
		parasut_contact_reuse_test_card(7002, ' 100 000 001 46 '),
		parasut_contact_reuse_test_card(7001, '10000000146'),
	);
	$contact = parasut_order_contact_for('10000000146', parasut_contact_reuse_test_attributes(), parasut_contact_reuse_test_request($cards, $calls));

	pg_assert_same(array('id' => '7002', 'reused' => true, 'error' => ''), $contact);
	pg_assert_same(0, parasut_contact_reuse_test_calls_of($calls, 'POST'));
}

// No usable card under the number: one is opened with the order's own
// details, exactly as before.
function test_parasut_order_contact_for_opens_a_card_when_none_is_usable()
{
	$calls = array();
	$attributes = parasut_contact_reuse_test_attributes();
	$cards = array(parasut_contact_reuse_test_card(7004, '10000000146', true), parasut_contact_reuse_test_card(7003, '100000001460'));
	$contact = parasut_order_contact_for('10000000146', $attributes, parasut_contact_reuse_test_request($cards, $calls));

	pg_assert_same(array('id' => '9001', 'reused' => false, 'error' => ''), $contact);
	pg_assert_same(1, parasut_contact_reuse_test_calls_of($calls, 'GET'));
	pg_assert_same(array('POST', '/contacts', array('data' => array('type' => 'contacts', 'attributes' => $attributes))), $calls[1]);

	// 404 is Paraşüt's "no card under the number".
	$calls = array();
	$missing = array('GET' => array('http_code' => 404, 'body' => array('errors' => array(array('title' => 'Record was not found'))), 'raw' => '{}'));
	$contact = parasut_order_contact_for('10000000146', $attributes, parasut_contact_reuse_test_request(array(), $calls, $missing));

	pg_assert_same(array('id' => '9001', 'reused' => false, 'error' => ''), $contact);
	pg_assert_same(array('GET', 'POST'), array_column($calls, 0));
}

// The cards under a number are read page after page until one is taken.
function test_parasut_order_contact_for_reads_later_pages()
{
	$cards = array();

	for ($i = 0; $i < 30; $i++) {
		$cards[] = parasut_contact_reuse_test_card(8000 - $i, '10000000146', true);
	}

	$cards[] = parasut_contact_reuse_test_card(5005, '10000000146');
	$calls = array();
	$contact = parasut_order_contact_for('10000000146', parasut_contact_reuse_test_attributes(), parasut_contact_reuse_test_request($cards, $calls));

	pg_assert_same(array('id' => '5005', 'reused' => true, 'error' => ''), $contact);
	pg_assert_same(2, count($calls), 'pages 1 and 2, nothing opened');
	pg_assert_contains('&page[number]=2&', $calls[1][1]);
}

// At most ERP_EDOC_PARASUT_FIND_PAGES pages are read; then a card is opened,
// as the ERP's driver does.
function test_parasut_order_contact_for_stops_at_the_page_cap()
{
	$cards = array();

	for ($i = 0; $i < 25; $i++) {
		$cards[] = parasut_contact_reuse_test_card(8000 - $i, '10000000146', true);
	}

	$calls = array();
	$contact = parasut_order_contact_for('10000000146', parasut_contact_reuse_test_attributes(), parasut_contact_reuse_test_request($cards, $calls, array(), (int) ERP_EDOC_PARASUT_FIND_PAGES + 5));

	pg_assert_same('9001', $contact['id']);
	pg_assert_same((int) ERP_EDOC_PARASUT_FIND_PAGES, parasut_contact_reuse_test_calls_of($calls, 'GET'));
	pg_assert_same(1, parasut_contact_reuse_test_calls_of($calls, 'POST'));
}

// A search Paraşüt does not answer refuses, as a create it does not answer
// did: no card is opened while one may be there.
function test_parasut_order_contact_for_refuses_a_failed_search()
{
	$answers = array(
		'no connection' => array('http_code' => 0, 'body' => null, 'raw' => '', 'curl_error' => 'Operation timed out'),
		'server error' => array('http_code' => 500, 'body' => array('errors' => array(array('title' => 'Sunucu hatası'))), 'raw' => '{}'),
		'no list' => array('http_code' => 200, 'body' => null, 'raw' => 'not json'),
	);

	foreach ($answers as $label => $answer) {
		$calls = array();
		$contact = parasut_order_contact_for('10000000146', parasut_contact_reuse_test_attributes(), parasut_contact_reuse_test_request(array(), $calls, array('GET' => $answer)));

		pg_assert_same('', $contact['id'], $label);
		pg_assert_same(lang(array('string' => 'Paraşüt would not search its contacts: {var:1}', 'vars' => array(_parasut_extract_error($answer)))), $contact['error'], $label);
		pg_assert_same(0, parasut_contact_reuse_test_calls_of($calls, 'POST'), $label);
	}
}

// A buyer without a number, the final consumer and a number that fails the
// check digits get a card of the order's own, unasked, as before.
function test_parasut_order_contact_for_opens_a_card_without_a_number()
{
	foreach (array('', '11111111111', '12345678901') as $identity) {
		$calls = array();
		$cards = array(parasut_contact_reuse_test_card(7001, $identity));
		$contact = parasut_order_contact_for($identity, parasut_contact_reuse_test_attributes(), parasut_contact_reuse_test_request($cards, $calls));

		pg_assert_same(array('id' => '9001', 'reused' => false, 'error' => ''), $contact, 'identity ' . $identity);
		pg_assert_same(array('POST'), array_column($calls, 0), 'identity ' . $identity);
	}
}

// A create Paraşüt refuses is reported in the words it always was.
function test_parasut_order_contact_for_reports_a_refused_create()
{
	$refused = array('http_code' => 422, 'body' => array('errors' => array(array('title' => 'Geçersiz', 'detail' => 'İsim boş olamaz'))), 'raw' => '{}');
	$calls = array();
	$contact = parasut_order_contact_for('', parasut_contact_reuse_test_attributes(), parasut_contact_reuse_test_request(array(), $calls, array('POST' => $refused)));

	pg_assert_same('', $contact['id']);
	pg_assert_same(lang(array('string' => 'Contact create failed: {var:1}', 'vars' => array('Geçersiz: İsim boş olamaz'))), $contact['error']);
}

// parasut_sync_contact() keeps an id the order already holds, and otherwise
// goes through parasut_order_contact_for() with the order's number and the
// attributes a new card is opened with; it opens no card of its own.
function test_parasut_sync_contact_looks_for_the_card_before_it_opens_one()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/parasut.php');
	$start = strpos($source, 'function parasut_sync_contact(');
	$body = ($start === false) ? '' : substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	$stored = strpos($body, "if (!empty(\$order['parasut_contact_id'])) {");
	$loaded = strpos($body, '_parasut_order_invoice_load();');
	$found = strpos($body, 'parasut_order_contact_for($identity, parasut_order_contact_attributes($order, $identity),');

	pg_assert_true(($stored !== false) && ($found !== false) && ($stored < $found), 'a stored id first');
	pg_assert_true(($loaded !== false) && ($loaded < $found), 'the driver loaded first');
	pg_assert_contains('$identity = _parasut_order_identity($order);', $body);
	pg_assert_true(strpos($body, "_parasut_request('POST'") === false, 'no card opened outside the search');
	pg_assert_contains("db(\"UPDATE orders SET parasut_contact_id = '\" . escape(\$parasut_contact_id) . \"' WHERE id = '\" . escape(\$order['id']) . \"'\");", $body);
}
