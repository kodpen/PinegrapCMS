<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the firewall's self-rescue and self-lockout guards: which block
 * entries of a Settings save cover the operator's own address
 * (pg_waf_own_address_blocks()), how waf_unban.php reads its arguments
 * (pg_waf_unban_parse_args()) and which ban rows it removes
 * (pg_waf_unban_rows()), all in includes/fn/waf_rescue.php, and the sign-in
 * throttle's address subject sparing an allowed address
 * (pg_login_throttle_address_subject() in includes/fn/auth.php).
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

// A block entry covering the operator's address is named, whatever its form.
function test_waf_own_address_blocks_ipv4()
{
	$blocks = array('198.51.100.9', '203.0.113.0/24', '203.0.*.*', '0.0.0.0/0', '*.*.*.*', '192.0.2.1');

	pg_assert_same(
		array('203.0.113.0/24', '203.0.*.*', '0.0.0.0/0', '*.*.*.*'),
		pg_waf_own_address_blocks('203.0.113.7', $blocks, array())
	);
	pg_assert_same(array('203.0.113.7'), pg_waf_own_address_blocks('203.0.113.7', array(' 203.0.113.7 '), array()));
	pg_assert_same(array(), pg_waf_own_address_blocks('203.0.113.7', array('198.51.100.0/24', '203.0.114.0/24'), array()));
}

function test_waf_own_address_blocks_ipv6()
{
	$blocks = array('2001:db8:1:2::/64', '2001:db8::/32', '::/0', '2001:db8:9::/48', '2001:DB8:1:2:0:0:0:7');

	pg_assert_same(
		array('2001:db8:1:2::/64', '2001:db8::/32', '::/0', '2001:DB8:1:2:0:0:0:7'),
		pg_waf_own_address_blocks('2001:db8:1:2::7', $blocks, array())
	);

	// The IPv4 wildcard for everything covers IPv6 too.
	pg_assert_same(array('*.*.*.*'), pg_waf_own_address_blocks('2001:db8:1:2::7', array('*.*.*.*', '10.0.0.0/8'), array()));
}

// An allow entry covering the address wins, as it does at request time.
function test_waf_own_address_blocks_allow_wins()
{
	pg_assert_same(array(), pg_waf_own_address_blocks('203.0.113.7', array('203.0.113.0/24'), array('203.0.113.7')));
	pg_assert_same(array(), pg_waf_own_address_blocks('2001:db8:1:2::7', array('::/0'), array('2001:db8:1::/48')));
	pg_assert_same(
		array('203.0.113.0/24'),
		pg_waf_own_address_blocks('203.0.113.7', array('203.0.113.0/24'), array('203.0.113.8', '203.0.113.7/'))
	);
}

// No address, or an entry that is not a pattern, blocks nothing.
function test_waf_own_address_blocks_rejects()
{
	pg_assert_same(array(), pg_waf_own_address_blocks('', array('0.0.0.0/0'), array()));
	pg_assert_same(array(), pg_waf_own_address_blocks('not-an-ip', array('0.0.0.0/0'), array()));
	pg_assert_same(array(), pg_waf_own_address_blocks('203.0.113.7', array('203.0.113.7/', '203.0.113.7/abc', '', 'x'), array()));
}

// One address or range, or --all; anything else is the usage text.
function test_waf_unban_parse_args()
{
	pg_assert_same(array('mode' => 'address', 'target' => '203.0.113.7'), pg_waf_unban_parse_args(array('203.0.113.7')));
	pg_assert_same(array('mode' => 'address', 'target' => '2001:db8:1:2::7'), pg_waf_unban_parse_args(array(' 2001:db8:1:2::7 ')));
	pg_assert_same(array('mode' => 'entry', 'target' => '2001:db8:1:2::/64'), pg_waf_unban_parse_args(array('2001:db8:1:2::/64')));
	pg_assert_same(array('mode' => 'entry', 'target' => '203.0.113.*'), pg_waf_unban_parse_args(array('203.0.113.*')));
	pg_assert_same(array('mode' => 'all', 'target' => ''), pg_waf_unban_parse_args(array('--all')));

	pg_assert_same(null, pg_waf_unban_parse_args(array()));
	pg_assert_same(null, pg_waf_unban_parse_args(array('')));
	pg_assert_same(null, pg_waf_unban_parse_args(array('203.0.113.7', '198.51.100.1')));
	pg_assert_same(null, pg_waf_unban_parse_args(array('203.0.113.7/')));
	pg_assert_same(null, pg_waf_unban_parse_args(array('--help')));
	pg_assert_same(null, pg_waf_unban_parse_args(array('example.com')));
}

// An address lifts every block row covering it, automatic or manual; a range
// lifts the row that names it; --all lifts the automatic bans only.
function test_waf_unban_rows()
{
	$rows = array(
		array('ip_address' => '203.0.113.7', 'source' => 'auto'),
		array('ip_address' => '203.0.113.0/24', 'source' => 'manual'),
		array('ip_address' => '198.51.100.1', 'source' => 'auto'),
		array('ip_address' => '2001:db8:1:2::/64', 'source' => 'auto'),
		array('ip_address' => '::/0', 'source' => 'manual'),
	);

	pg_assert_same(
		array($rows[0], $rows[1]),
		pg_waf_unban_rows($rows, array('mode' => 'address', 'target' => '203.0.113.7'))
	);
	pg_assert_same(
		array($rows[3], $rows[4]),
		pg_waf_unban_rows($rows, array('mode' => 'address', 'target' => '2001:db8:1:2::7'))
	);
	pg_assert_same(
		array($rows[3]),
		pg_waf_unban_rows($rows, array('mode' => 'entry', 'target' => '2001:DB8:1:2::/64'))
	);
	pg_assert_same(
		array($rows[0], $rows[2], $rows[3]),
		pg_waf_unban_rows($rows, array('mode' => 'all', 'target' => ''))
	);
	pg_assert_same(array(), pg_waf_unban_rows($rows, array('mode' => 'address', 'target' => '192.0.2.1')));
}

// The command the ban e-mail and the usage text name.
function test_waf_unban_command()
{
	pg_assert_same('php waf_unban.php 203.0.113.7', pg_waf_unban_command('203.0.113.7'));
	pg_assert_same('php waf_unban.php 2001:db8:1:2::/64', pg_waf_unban_command('2001:db8:1:2::/64'));
}

// The throttle's address lock spares an allowed address, IPv4 or IPv6, and
// still counts the /64 of any other IPv6 address.
function test_login_throttle_address_subject_honours_allow_list()
{
	pg_assert_same('203.0.113.7', pg_login_throttle_address_subject('203.0.113.7', true, array()));
	pg_assert_same('', pg_login_throttle_address_subject('203.0.113.7', true, array('203.0.113.0/24')));
	pg_assert_same('203.0.113.7', pg_login_throttle_address_subject('203.0.113.7', true, array('198.51.100.0/24')));

	pg_assert_same('2001:db8:1:2::/64', pg_login_throttle_address_subject('2001:db8:1:2::7', true, array()));
	pg_assert_same('', pg_login_throttle_address_subject('2001:db8:1:2::7', true, array('2001:db8:1:2::7')));
	pg_assert_same('2001:db8:1:2::/64', pg_login_throttle_address_subject('2001:db8:1:2::7', true, array('2001:db8:1:3::/64')));

	pg_assert_same('', pg_login_throttle_address_subject('', true, array()));
	pg_assert_same('', pg_login_throttle_address_subject('10.0.0.5', false, array()));
}
