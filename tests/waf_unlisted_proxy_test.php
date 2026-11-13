<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the unlisted public proxy finding in waf.php:
 * waf_unlisted_proxy_finding(), waf_unlisted_proxy_entry(),
 * waf_unlisted_proxy_offer(), waf_unlisted_proxy_standing() and
 * waf_unlisted_proxy_next(). A proxy on a public address that is not listed
 * under Trusted Proxies is found from a request that names another public
 * client, kept from staff requests, and offered as a Trusted Proxies entry.
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

// A public peer naming another public client in X-Forwarded-For is found.
function test_waf_unlisted_proxy_found_from_forwarded_for()
{
	$server = array('REMOTE_ADDR' => '198.51.100.7', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9, 192.0.2.44');

	pg_assert_same(
		array('peer' => '198.51.100.7', 'header' => 'x-forwarded-for', 'client' => '192.0.2.44'),
		waf_unlisted_proxy_finding($server, array(), array()));
}

// Without X-Forwarded-For a vendor header names the client and the entry.
function test_waf_unlisted_proxy_found_from_vendor_header()
{
	$server = array('REMOTE_ADDR' => '198.51.100.7', 'HTTP_TRUE_CLIENT_IP' => '203.0.113.9');
	$finding = waf_unlisted_proxy_finding($server, array(), array());

	pg_assert_same('true-client-ip', $finding['header']);
	pg_assert_same('198.51.100.7 true-client-ip', waf_unlisted_proxy_entry($finding));
}

// A listed proxy, a Cloudflare edge, a private peer or no header finds nothing.
function test_waf_unlisted_proxy_not_found()
{
	$server = array('REMOTE_ADDR' => '198.51.100.7', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9');

	pg_assert_same(null, waf_unlisted_proxy_finding($server, array('198.51.100.0/24'), array()));
	pg_assert_same(null, waf_unlisted_proxy_finding($server, array(), array('198.51.100.0/24')));
	pg_assert_same(null, waf_unlisted_proxy_finding(array('REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9'), array(), array()));
	pg_assert_same(null, waf_unlisted_proxy_finding(array('REMOTE_ADDR' => '198.51.100.7'), array(), array()));
	pg_assert_same(null, waf_unlisted_proxy_finding(array(), array(), array()));
}

// An office forward proxy naming a private desk, the peer itself or a value
// that is no address finds nothing: not a reverse proxy in front of the site.
function test_waf_unlisted_proxy_client_must_be_another_public_address()
{
	pg_assert_same(null, waf_unlisted_proxy_finding(array('REMOTE_ADDR' => '198.51.100.7', 'HTTP_X_FORWARDED_FOR' => '192.168.1.20'), array(), array()));
	pg_assert_same(null, waf_unlisted_proxy_finding(array('REMOTE_ADDR' => '198.51.100.7', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7'), array(), array()));
	pg_assert_same(null, waf_unlisted_proxy_finding(array('REMOTE_ADDR' => '198.51.100.7', 'HTTP_X_FORWARDED_FOR' => 'unknown'), array(), array()));
	pg_assert_same(null, waf_unlisted_proxy_finding(array('REMOTE_ADDR' => '198.51.100.7', 'HTTP_X_REAL_IP' => 'nonsense'), array(), array()));
}

// The peer is read in its one written form.
function test_waf_unlisted_proxy_peer_normalised()
{
	$finding = waf_unlisted_proxy_finding(array('REMOTE_ADDR' => '::ffff:198.51.100.7', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9'), array(), array());

	pg_assert_same('198.51.100.7', $finding['peer']);
}

// A recorded peer stands for thirty days after it was last seen and leaves
// once it is listed; rows that are not addresses are dropped.
function test_waf_unlisted_proxy_standing()
{
	$now = 1800000000;
	$state = array(
		'198.51.100.7' => array('header' => 'x-forwarded-for', 'first' => $now - 100, 'last' => $now - 50),
		'198.51.100.8' => array('header' => 'x-forwarded-for', 'first' => $now - 40 * 86400, 'last' => $now - 31 * 86400),
		'203.0.113.1'  => array('header' => 'x-real-ip', 'first' => $now, 'last' => $now),
		'junk'         => array('header' => 'x-forwarded-for', 'first' => $now, 'last' => $now),
	);

	$standing = waf_unlisted_proxy_standing($state, array('203.0.113.0/24'), $now);

	pg_assert_same(array('198.51.100.7'), array_keys($standing));
	pg_assert_same(array(), waf_unlisted_proxy_standing('garbage', array(), $now));
}

// A staff finding is recorded, refreshed once an hour, and a staff request
// from the same address without a header forgets it.
function test_waf_unlisted_proxy_next()
{
	$now = 1800000000;
	$finding = array('peer' => '198.51.100.7', 'header' => 'x-forwarded-for', 'client' => '203.0.113.9');

	$state = waf_unlisted_proxy_next(array(), '198.51.100.7', $finding, $now);
	pg_assert_same(array('198.51.100.7' => array('header' => 'x-forwarded-for', 'first' => $now, 'last' => $now)), $state);

	// Within the hour nothing is written.
	pg_assert_same(null, waf_unlisted_proxy_next($state, '198.51.100.7', $finding, $now + 60));

	// After it, last moves and first stays.
	$later = waf_unlisted_proxy_next($state, '198.51.100.7', $finding, $now + 7200);
	pg_assert_same($now, $later['198.51.100.7']['first']);
	pg_assert_same($now + 7200, $later['198.51.100.7']['last']);

	// No finding from an address that was never recorded: nothing to write.
	pg_assert_same(null, waf_unlisted_proxy_next($state, '192.0.2.1', null, $now));

	// No finding from the recorded address: it is a person, forgotten.
	pg_assert_same(array(), waf_unlisted_proxy_next($state, '198.51.100.7', null, $now));
}

// At most eight peers are kept, the most recently seen.
function test_waf_unlisted_proxy_next_keeps_eight()
{
	$now = 1800000000;
	$state = array();

	for ($i = 1; $i <= 9; $i++) {
		$state = waf_unlisted_proxy_next($state, '198.51.100.' . $i, array('peer' => '198.51.100.' . $i, 'header' => 'x-forwarded-for'), $now + $i);
	}

	pg_assert_same(8, count($state));
	pg_assert_false(isset($state['198.51.100.1']));
	pg_assert_true(isset($state['198.51.100.9']));
}

// The offer lists this request's peer first, then the recorded ones, once each.
function test_waf_unlisted_proxy_offer()
{
	$finding = array('peer' => '198.51.100.7', 'header' => 'x-forwarded-for', 'client' => '203.0.113.9');
	$standing = array(
		'198.51.100.7' => array('header' => 'x-real-ip', 'first' => 1, 'last' => 1),
		'198.51.100.8' => array('header' => 'x-real-ip', 'first' => 1, 'last' => 1),
		'198.51.100.9' => array('header' => 'x-unknown', 'first' => 1, 'last' => 1),
	);

	pg_assert_same(array(
		'198.51.100.7' => '198.51.100.7 x-forwarded-for',
		'198.51.100.8' => '198.51.100.8 x-real-ip',
		'198.51.100.9' => '198.51.100.9 x-forwarded-for',
	), waf_unlisted_proxy_offer($finding, $standing));

	pg_assert_same(array(), waf_unlisted_proxy_offer(null, array()));
}
