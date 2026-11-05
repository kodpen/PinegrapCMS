<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the X-Forwarded-For walk (waf_forwarded_for_client() in waf.php):
 * the visitor is the rightmost entry that is not a trusted proxy, an entry a
 * proxy wrote with a port is read without it, and an entry that cannot be read
 * ends the walk instead of passing on to what the client wrote further left.
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

// The rightmost address that is not one of our own proxies is the visitor.
function test_waf_forwarded_for_rightmost_untrusted()
{
	$trusted = array('10.0.0.0/8');

	pg_assert_same('198.51.100.7', waf_forwarded_for_client('198.51.100.7', $trusted));
	pg_assert_same('198.51.100.7', waf_forwarded_for_client('203.0.113.9, 198.51.100.7', $trusted));
	pg_assert_same('198.51.100.7', waf_forwarded_for_client('203.0.113.9, 198.51.100.7, 10.0.0.2', $trusted));
	pg_assert_same('2001:db8::5', waf_forwarded_for_client('203.0.113.9, 2001:db8::5', $trusted));
	pg_assert_same('', waf_forwarded_for_client('10.0.0.3, 10.0.0.2', $trusted));
	pg_assert_same('', waf_forwarded_for_client('', $trusted));
}

// A proxy that appends the visitor with its port (Azure Application Gateway,
// IIS ARR) names that visitor; the address the client wrote before it is
// not taken instead.
function test_waf_forwarded_for_port_forms()
{
	$trusted = array('10.0.0.0/8');

	pg_assert_same('198.51.100.7', waf_forwarded_for_client('203.0.113.9, 198.51.100.7:51234', $trusted));
	pg_assert_same('2001:db8::5', waf_forwarded_for_client('203.0.113.9, [2001:db8::5]:443', $trusted));
	pg_assert_same('2001:db8::5', waf_forwarded_for_client('203.0.113.9, [2001:db8::5]', $trusted));
	pg_assert_same('198.51.100.7', waf_forwarded_for_client('198.51.100.7, 10.0.0.2:8080', $trusted));
}

// An entry that is no address ("unknown", a hidden or malformed one) is where
// the trustworthy part of the chain ends: what lies left of it was written by
// the client, so nothing is taken.
function test_waf_forwarded_for_unreadable_entry_stops()
{
	$trusted = array('10.0.0.0/8');

	pg_assert_same('', waf_forwarded_for_client('203.0.113.9, unknown', $trusted));
	pg_assert_same('', waf_forwarded_for_client('203.0.113.9, 999.1.1.1', $trusted));
	pg_assert_same('', waf_forwarded_for_client('203.0.113.9, , 10.0.0.2', $trusted));
	pg_assert_same('', waf_forwarded_for_client('203.0.113.9, 1.2.3.4:99999', $trusted));
}
