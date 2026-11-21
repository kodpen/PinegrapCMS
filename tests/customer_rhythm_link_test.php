<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the reorder link of customer rhythm
 * (includes/fn/customer_rhythm_link.php, reorder.php): the products it
 * names, its signature and 30 days, what a changed or ended link reads,
 * the field the reorder event carries, and the page's GET that changes
 * nothing.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _crl_test_now()
{
	return 1790000000;
}

function _crl_test_key()
{
	return 'test-key-0123456789abcdef';
}

// The query string of a link as an array, as $_GET would read it.
function _crl_test_get($query)
{
	parse_str($query, $get);

	return $get;
}

// One id-quantity pair per product, in the order given; a repeated product
// adds its quantity; nothing that is no product; at most 20.
function test_customer_rhythm_link_items()
{
	pg_assert_same('120-2.8-1', pg_customer_rhythm_link_items(array(array('product_id' => 120, 'quantity' => 2, 'due' => 5), array('product_id' => 8, 'quantity' => 1))));
	pg_assert_same('7-3', pg_customer_rhythm_link_items(array(array('product_id' => 7, 'quantity' => 1), array('product_id' => 7, 'quantity' => 2))));
	pg_assert_same('', pg_customer_rhythm_link_items(array(array('product_id' => 0, 'quantity' => 1), array('product_id' => 5, 'quantity' => 0), 'x')));

	$many = array();

	for ($id = 1; $id <= 25; $id++) {
		$many[] = array('product_id' => $id, 'quantity' => 1);
	}

	pg_assert_same(20, count(explode('.', pg_customer_rhythm_link_items($many))));
}

// The link names products, quantities, its end and its signature: no
// customer, contact, order or address.
function test_customer_rhythm_link_query_names_no_person()
{
	$query = pg_customer_rhythm_link_query(array(array('product_id' => 120, 'quantity' => 2)), _crl_test_now(), _crl_test_key());
	$get = _crl_test_get($query);

	pg_assert_same(array('p', 'e', 's'), array_keys($get));
	pg_assert_same('120-2', $get['p']);
	pg_assert_same((string) (_crl_test_now() + (30 * 86400)), $get['e'], 'signed for 30 days');
	pg_assert_same(1, preg_match('/^[0-9a-f]{32}$/', $get['s']));

	pg_assert_same('', pg_customer_rhythm_link_query(array(array('product_id' => 120, 'quantity' => 2)), _crl_test_now(), ''), 'no key, no link');
	pg_assert_same('', pg_customer_rhythm_link_query(array(), _crl_test_now(), _crl_test_key()), 'no products, no link');
}

// The signature has its own prefix: another HMAC of the same text under
// the same key is not one.
function test_customer_rhythm_link_signature_prefix()
{
	$signature = pg_customer_rhythm_link_signature('120-2', 100, _crl_test_key());

	pg_assert_same(substr(hash_hmac('sha256', 'customer-rhythm-reorder:120-2:100', _crl_test_key()), 0, 32), $signature);
	pg_assert_false($signature === substr(hash_hmac('sha256', '120-2:100', _crl_test_key()), 0, 32));
	pg_assert_same('', pg_customer_rhythm_link_signature('', 100, _crl_test_key()));
	pg_assert_same('', pg_customer_rhythm_link_signature('120-2', 0, _crl_test_key()));
}

// Open within its 30 days; ended after them; any change to the products,
// the quantities or the end, another site's key or a bad shape is invalid.
function test_customer_rhythm_link_state()
{
	$get = _crl_test_get(pg_customer_rhythm_link_query(array(array('product_id' => 120, 'quantity' => 2), array('product_id' => 8, 'quantity' => 1)), _crl_test_now(), _crl_test_key()));
	$parsed = pg_customer_rhythm_link_parse($get);

	pg_assert_same(array(array('product_id' => 120, 'quantity' => 2), array('product_id' => 8, 'quantity' => 1)), $parsed['lines']);
	pg_assert_same('open', pg_customer_rhythm_link_state($parsed, _crl_test_key(), _crl_test_now()));
	pg_assert_same('open', pg_customer_rhythm_link_state($parsed, _crl_test_key(), _crl_test_now() + (29 * 86400)));
	pg_assert_same('ended', pg_customer_rhythm_link_state($parsed, _crl_test_key(), _crl_test_now() + (30 * 86400)));
	pg_assert_same('invalid', pg_customer_rhythm_link_state($parsed, 'another-key', _crl_test_now()));
	pg_assert_same('invalid', pg_customer_rhythm_link_state($parsed, '', _crl_test_now()));
	pg_assert_same('invalid', pg_customer_rhythm_link_state($parsed, _crl_test_key(), _crl_test_now() - (2 * 86400)), 'an end further than 30 days ahead is none this site signs');

	foreach (array(array('p' => '120-20.8-1'), array('p' => '120-2'), array('e' => (string) ($get['e'] + 86400)), array('s' => str_repeat('0', 32))) as $change) {
		$changed = pg_customer_rhythm_link_parse(array_merge($get, $change));
		pg_assert_same('invalid', pg_customer_rhythm_link_state($changed, _crl_test_key(), _crl_test_now()), json_encode($change));
	}

	pg_assert_same('invalid', pg_customer_rhythm_link_state(null, _crl_test_key(), _crl_test_now()));
}

// Only the link's own shape is read; a list, a zero or a long list is none.
function test_customer_rhythm_link_parse_shape()
{
	$good = array('p' => '120-2', 'e' => '1790000000', 's' => str_repeat('a', 32));

	pg_assert_true(is_array(pg_customer_rhythm_link_parse($good)));
	pg_assert_same(null, pg_customer_rhythm_link_parse('p=120-2'));

	foreach (array(array('p' => array('120-2')), array('p' => '0-2'), array('p' => '120-0'), array('p' => '120'), array('p' => '120-2.'), array('p' => '120-2,8-1'),
		array('e' => '0'), array('e' => '-5'), array('s' => str_repeat('a', 64)), array('s' => str_repeat('A', 32)), array('s' => null)) as $change) {
		pg_assert_same(null, pg_customer_rhythm_link_parse(array_merge($good, $change)), json_encode($change));
	}

	$long = array();

	for ($id = 1; $id <= 21; $id++) {
		$long[] = $id . '-1';
	}

	pg_assert_same(null, pg_customer_rhythm_link_parse(array_merge($good, array('p' => implode('.', $long)))), 'more than 20 products');
}

// The reorder event carries the link as reorder_url; the pass makes it for
// the products the event names, from the pass start.
function test_customer_rhythm_link_in_the_reorder_event()
{
	$row = array('contact_id' => 41, 'stage' => 2, 'stage_before' => 1, 'interval_days' => 30, 'last_order_at' => 1000, 'updated_at' => 90000, 'chance' => 80, 'expected_value' => 100, 'last_order_id' => 3);
	$payload = pg_customer_rhythm_reorder_payload($row, '1001', array(array('product_id' => 7, 'quantity' => 2, 'due' => 5000)), array(7 => array('P', 'Product')), 'https://example.com/p/reorder.php?p=7-2');

	pg_assert_same('https://example.com/p/reorder.php?p=7-2', $payload['reorder_url']);
	pg_assert_same('reorder_url', array_key_last($payload));

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/customer_rhythm_tell.php');
	pg_assert_true(strpos($source, 'pg_customer_rhythm_link_url($products, $now)') !== false);

	$payloads = api_webhook_event_payloads();
	pg_assert_true(isset($payloads['customer.reorder_due']['fields']['reorder_url']));

	$names = array();

	foreach (ws_flow_event_outputs()['customer.reorder_due'] as $output) {
		$names[] = $output['key'];
	}

	pg_assert_true(in_array('reorder_url', $names, true), 'a flow can put the link into its message');
}

// Opening the page changes nothing: the cart is filled only by a POST with
// the form token, on an open link. Counted on the sensitive allowance.
function test_customer_rhythm_link_page()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/reorder.php');
	$token = strpos($page, 'validate_token_field();');
	$apply = strpos($page, 'pg_customer_rhythm_link_apply(');

	pg_assert_true(($token !== false) && ($apply !== false) && ($token < $apply));
	pg_assert_true(strpos($page, "if (\$ro_post && (\$ro_state === 'open'))") !== false);
	pg_assert_same(1, substr_count($page, 'pg_customer_rhythm_link_apply('));

	foreach (array("header('Cache-Control: no-store, private')", "header('X-Robots-Tag: noindex, nofollow')", "header('Referrer-Policy: no-referrer')", "header('X-Frame-Options: DENY')") as $header) {
		pg_assert_true(strpos($page, $header) !== false, $header);
	}

	foreach (array('sessionusername', 'user_id', 'contact', 'ship_to', 'billing_') as $word) {
		pg_assert_false(stripos($page, $word) !== false, 'the page reads no ' . $word);
	}

	$waf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');
	$start = strpos($waf, 'function waf_sensitive_scripts()');
	pg_assert_true(strpos(substr($waf, $start, strpos($waf, "\n}\n", $start) - $start), "'reorder.php'") !== false);
}
