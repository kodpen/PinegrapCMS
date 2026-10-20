<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how the firewall compares an address with a list entry
 * (waf_ip_matches(), waf_ip_in_cidr() in waf.php; waf_ip_matches_simple() in
 * includes/db_guard.php): one address written several ways, and an IPv4
 * visitor handed over by a dual-stack socket as ::ffff:a.b.c.d.
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

// An IPv6 entry matches the address however either side spells it.
function test_waf_ip_match_ipv6_spellings()
{
	foreach (array('waf_ip_matches', 'waf_ip_matches_simple') as $match) {
		pg_assert_true($match('2001:db8::1', '2001:DB8::1'), $match . ': upper case');
		pg_assert_true($match('2001:db8::1', '2001:0db8::1'), $match . ': leading zeros');
		pg_assert_true($match('2001:DB8:0:0::1', '2001:db8::1'), $match . ': uncompressed visitor');
		pg_assert_false($match('2001:db8::2', '2001:db8::1'), $match . ': another address');
	}
}

// An IPv4 visitor on a dual-stack socket matches IPv4 entries and ranges.
function test_waf_ip_match_ipv4_mapped_visitor()
{
	foreach (array('waf_ip_matches', 'waf_ip_matches_simple') as $match) {
		pg_assert_true($match('::ffff:1.2.3.4', '1.2.3.4'), $match . ': an address');
		pg_assert_true($match('::ffff:1.2.3.4', '1.2.3.0/24'), $match . ': a range');
		pg_assert_true($match('::ffff:1.2.3.4', '::ffff:1.2.3.0/120'), $match . ': a range written mapped');
		pg_assert_true($match('1.2.3.4', '::ffff:1.2.3.4'), $match . ': an entry written mapped');
		pg_assert_false($match('::ffff:1.2.3.4', '1.2.4.0/24'), $match . ': another range');
		pg_assert_true($match('::ffff:1.2.3.4', '::/0'), $match . ': all of IPv6 still holds it');
	}

	pg_assert_true(waf_ip_matches('::ffff:1.2.3.4', '1.2.3.*'), 'the legacy wildcard');
	pg_assert_true(waf_ip_in_any('::ffff:127.0.0.1', array('127.0.0.0/8')), 'loopback, the tunnel case');
	pg_assert_true(waf_ip_in_any('::ffff:104.16.0.1', waf_cloudflare_ranges()), 'a Cloudflare edge');
}

// What the earlier rules refused is still refused.
function test_waf_ip_match_keeps_refusals()
{
	pg_assert_false(waf_ip_matches('1.2.3.4', '1.2.3.4/'), 'an empty prefix');
	pg_assert_false(waf_ip_matches('1.2.3.4', '1.2.3.4/abc'), 'a prefix that is not a number');
	pg_assert_false(waf_ip_matches('1.2.3.4', '2001:db8::/32'), 'across families');
	pg_assert_false(waf_ip_matches('not an address', '1.2.3.4'), 'text that is no address matches no entry');
	pg_assert_false(waf_ip_matches('1.2.3.4', 'not an address'), 'nor does an entry that is none');
	pg_assert_false(waf_ip_matches_simple('1.2.3.4', '1.2.3.4/'), 'shield: an empty prefix');
}

// The resolved visitor is in one written form.
function test_waf_ip_normalize_forms()
{
	pg_assert_same('1.2.3.4', waf_ip_normalize('::ffff:1.2.3.4'), 'mapped');
	pg_assert_same('2001:db8::1', waf_ip_normalize('2001:0DB8:0:0::1'), 'IPv6');
	pg_assert_same('', waf_ip_normalize(''), 'empty stays empty');
	pg_assert_same('x', waf_ip_normalize('x'), 'not an address');
}
