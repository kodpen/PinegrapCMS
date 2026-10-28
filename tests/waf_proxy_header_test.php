<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the forwarding header each trusted proxy may name the visitor
 * with (waf_proxy_entry(), waf_proxy_ranges(), waf_proxy_client_headers(),
 * waf_proxy_list_on_save(), waf_ban_shield_proxy_line() in waf.php and
 * pg_ban_shield_proxy_headers() in includes/db_guard.php). An entry without
 * a header keeps the old reading, every vendor header; an entry the operator
 * adds or edits reads X-Forwarded-For only unless a vendor header is named.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/waf.php');
require_once(PG_FUNCTIONS_DIR . '/includes/db_guard.php');

// An entry is an address or range, optionally followed by a header name.
function test_waf_proxy_entry_parse()
{
	pg_assert_same(array('range' => '10.0.0.0/8', 'header' => ''), waf_proxy_entry(' 10.0.0.0/8 '));
	pg_assert_same(array('range' => '10.0.0.1', 'header' => 'true-client-ip'), waf_proxy_entry('10.0.0.1  True-Client-IP'));
	pg_assert_same(array('range' => '10.0.0.1', 'header' => 'x-forwarded-for'), waf_proxy_entry('10.0.0.1 x-forwarded-for'));

	// A name the firewall does not know reads as X-Forwarded-For only.
	pg_assert_same(array('range' => '10.0.0.1', 'header' => 'x-forwarded-for'), waf_proxy_entry('10.0.0.1 X-Client-IP'));
}

// The ranges alone decide which peers are trusted at all.
function test_waf_proxy_ranges()
{
	pg_assert_same(array('10.0.0.0/8', '192.0.2.5', '2001:db8::/32'), waf_proxy_ranges("10.0.0.0/8,192.0.2.5 true-client-ip\n2001:db8::/32 x-forwarded-for"));
	pg_assert_same(array(), waf_proxy_ranges(''));
}

// Which single-value headers the peer may set, in the order they are read.
function test_waf_proxy_client_headers()
{
	$raw = '192.0.2.0/24,198.51.100.7 true-client-ip,203.0.113.9 x-forwarded-for';

	// An entry saved before the choice existed keeps every vendor header.
	pg_assert_same(array_values(waf_proxy_headers()), waf_proxy_client_headers($raw, '192.0.2.44'));

	pg_assert_same(array('HTTP_TRUE_CLIENT_IP'), waf_proxy_client_headers($raw, '198.51.100.7'));
	pg_assert_same(array(), waf_proxy_client_headers($raw, '203.0.113.9'));

	// A peer that is not listed may set none.
	pg_assert_same(array(), waf_proxy_client_headers($raw, '198.51.100.8'));
}

// On save an entry already stored without a header keeps the old reading;
// a new or edited one without a header reads X-Forwarded-For only.
function test_waf_proxy_list_on_save()
{
	$stored = '10.0.0.0/8,192.0.2.5';

	pg_assert_same('10.0.0.0/8,192.0.2.5', waf_proxy_list_on_save('10.0.0.0/8,192.0.2.5', $stored));
	pg_assert_same('10.0.0.0/8,192.0.2.6 x-forwarded-for', waf_proxy_list_on_save('10.0.0.0/8, 192.0.2.6', $stored));
	pg_assert_same('10.0.0.0/8 true-client-ip,198.51.100.1 cf-connecting-ip', waf_proxy_list_on_save("10.0.0.0/8 True-Client-IP\n198.51.100.1 cf-connecting-ip", $stored));

	// Once given a header, removing it is an edit too.
	pg_assert_same('192.0.2.5 x-forwarded-for', waf_proxy_list_on_save('192.0.2.5', '192.0.2.5 true-client-ip'));
	pg_assert_same('198.51.100.1 x-forwarded-for', waf_proxy_list_on_save('198.51.100.1 X-Client-IP', ''));
	pg_assert_same('', waf_proxy_list_on_save('', $stored));
}

// The ban shield mirror carries the headers each proxy line may use, and the
// shield reads exactly those; an old line without them keeps the old pair.
function test_waf_proxy_shield_lines()
{
	pg_assert_same("P 198.51.100.7 HTTP_TRUE_CLIENT_IP\n", waf_ban_shield_proxy_line('198.51.100.7 true-client-ip'));
	pg_assert_same("P 203.0.113.9 -\n", waf_ban_shield_proxy_line('203.0.113.9 x-forwarded-for'));
	pg_assert_same('P 192.0.2.0/24 ' . implode(',', array_values(waf_proxy_headers())) . "\n", waf_ban_shield_proxy_line('192.0.2.0/24'));

	$proxies = array('198.51.100.7 HTTP_TRUE_CLIENT_IP', '203.0.113.9 -', '192.0.2.0/24', '2001:db8::/32 HTTP_CF_CONNECTING_IP,HTTP_X_REAL_IP');

	pg_assert_same(array('HTTP_TRUE_CLIENT_IP'), pg_ban_shield_proxy_headers($proxies, '198.51.100.7'));
	pg_assert_same(array(), pg_ban_shield_proxy_headers($proxies, '203.0.113.9'));
	pg_assert_same(array('HTTP_CF_CONNECTING_IP', 'HTTP_TRUE_CLIENT_IP'), pg_ban_shield_proxy_headers($proxies, '192.0.2.10'));
	pg_assert_same(array('HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP'), pg_ban_shield_proxy_headers($proxies, '2001:db8::5'));
	pg_assert_same(array(), pg_ban_shield_proxy_headers($proxies, '198.51.100.8'));

	// Only forwarding headers are read, whatever the file says.
	pg_assert_same(array(), pg_ban_shield_proxy_headers(array('198.51.100.7 REMOTE_ADDR'), '198.51.100.7'));
}
