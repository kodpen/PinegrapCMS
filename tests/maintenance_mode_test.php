<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for maintenance mode and the liveness answer
 * (includes/fn/maintenance_mode.php): when the mode holds, the Retry-After it
 * sends, which typed addresses the list refuses, what health.php answers, and
 * that only page renders are held.
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

// On, until its end time when it has one; off is off whatever the time says.
function test_maintenance_holds()
{
	$now = 2000000000;

	pg_assert_false(pg_maintenance_holds(false, 0, $now));
	pg_assert_false(pg_maintenance_holds(false, $now + 600, $now));
	pg_assert_true(pg_maintenance_holds(true, 0, $now));
	pg_assert_true(pg_maintenance_holds(true, $now + 1, $now));
	pg_assert_false(pg_maintenance_holds(true, $now, $now));
	pg_assert_false(pg_maintenance_holds(true, $now - 60, $now));
}

// The seconds to the end, an hour without one, held between a minute and a day.
function test_maintenance_retry_after()
{
	$now = 2000000000;

	pg_assert_same(3600, pg_maintenance_retry_after(0, $now));
	pg_assert_same(900, pg_maintenance_retry_after($now + 900, $now));
	pg_assert_same(60, pg_maintenance_retry_after($now + 5, $now));
	pg_assert_same(60, pg_maintenance_retry_after($now - 5, $now));
	pg_assert_same(86400, pg_maintenance_retry_after($now + (30 * 86400), $now));
}

// The list takes what the firewall's lists take, and names what it refuses.
function test_maintenance_allow_invalid()
{
	pg_assert_same(array(), pg_maintenance_allow_invalid(''));
	pg_assert_same(array(), pg_maintenance_allow_invalid("203.0.113.7, 198.51.100.0/24\n2001:db8::/32; 10.0.*.*"));
	pg_assert_same(array('example.com', '1.2.3.4/'), pg_maintenance_allow_invalid('203.0.113.7, example.com, 1.2.3.4/'));
}

// health.php: maintenance first, then a general job past its limit; never
// having run is not a failure.
function test_maintenance_health_verdict()
{
	pg_assert_same(array('code' => 200, 'status' => 'ok'), pg_health_verdict(false, null, 7200));
	pg_assert_same(array('code' => 200, 'status' => 'ok'), pg_health_verdict(false, 7200, 7200));
	pg_assert_same(array('code' => 503, 'status' => 'cron_stalled'), pg_health_verdict(false, 7201, 7200));
	pg_assert_same(array('code' => 503, 'status' => 'maintenance'), pg_health_verdict(true, 9999, 7200));
	pg_assert_same(array('code' => 503, 'status' => 'maintenance'), pg_health_verdict(true, null, 7200));
}

// The end time is written in digits, in the site's order.
function test_maintenance_time_text()
{
	$time = mktime(18, 5, 0, 10, 19, 2026);

	pg_assert_true(in_array(pg_maintenance_time_text($time), array('19.10.2026 18:05', '10/19/2026 6:05 PM', '19.10.2026 6:05 PM', '10/19/2026 18:05'), true));
}

// The end time typed into the card is read in the time zone the same save
// gives the site, not the one the request started with.
function test_maintenance_until_parse()
{
	pg_assert_same(0, pg_maintenance_until_parse('', 'Europe/Istanbul'), 'no end time');
	pg_assert_same(gmmktime(12, 0, 0, 12, 1, 2026), pg_maintenance_until_parse('2026-12-01T15:00', 'Europe/Istanbul'), '15:00 in Istanbul is 12:00 UTC');
	pg_assert_same(gmmktime(15, 0, 0, 12, 1, 2026), pg_maintenance_until_parse('2026-12-01T15:00', 'UTC'), '15:00 in UTC');
	pg_assert_same(gmmktime(15, 0, 0, 12, 1, 2026), pg_maintenance_until_parse('2026-12-01T15:00:30', 'UTC'), 'seconds are dropped as the input does');
	pg_assert_false(pg_maintenance_until_parse('2026-13-01T15:00', 'UTC'), 'no such month');
	pg_assert_false(pg_maintenance_until_parse('tomorrow', 'UTC'), 'not a date and time');

	$fallback = pg_maintenance_until_parse('2026-12-01T15:00', 'Not/AZone');
	$expected = (new DateTime('2026-12-01 15:00', new DateTimeZone(date_default_timezone_get())))->getTimestamp();
	pg_assert_same($expected, $fallback, 'an unknown zone falls back to the request\'s');
}

// A passed end time refuses only what would start or reschedule a mode that
// is over before it begins; a mode that ended by itself does not block every
// other general setting.
function test_maintenance_until_refused()
{
	$now = 1000000;

	pg_assert_true(pg_maintenance_until_refused(true, $now - 60, $now, false, 0), 'switched on with a passed end');
	pg_assert_true(pg_maintenance_until_refused(true, $now - 60, $now, true, $now - 3600), 'on, given another passed end');
	pg_assert_false(pg_maintenance_until_refused(true, $now - 3600, $now, true, $now - 3600), 'ended by itself, saved as it was');
	pg_assert_false(pg_maintenance_until_refused(true, $now + 60, $now, false, 0), 'an end still ahead');
	pg_assert_false(pg_maintenance_until_refused(true, 0, $now, true, $now - 3600), 'no end time');
	pg_assert_false(pg_maintenance_until_refused(false, $now - 60, $now, false, 0), 'switched off');
	pg_assert_true(pg_maintenance_until_refused(true, $now, $now, false, 0), 'an end that is now has passed');
}

// Only a page render is held: the gate sits in get_page.php, after init.php
// and before anything is looked up, and nowhere in the bootstrap every
// request shares - a payment provider calling the shop back must get through.
function test_maintenance_gate_only_on_pages()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page.php');

	$init = strpos($page, "include ('init.php');");
	$gate = strpos($page, 'pg_maintenance_gate();');
	$tracking = strpos($page, 'init_tracking();');

	pg_assert_true(($init !== false) && ($gate !== false) && ($tracking !== false) && ($init < $gate) && ($gate < $tracking));

	foreach (array('init.php', 'router.php', 'submit_order.php', 'api.php', 'integration.php') as $file) {
		pg_assert_false(strpos((string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file), 'pg_maintenance_gate(') !== false, $file);
	}
}

// health.php keeps no session and says no more than its status and the age
// of the general job's run.
function test_maintenance_health_answer_is_small()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/health.php');

	pg_assert_true(strpos($source, "define('PG_NO_SESSION', true);") < strpos($source, "require(dirname(__FILE__) . '/init.php');"));
	pg_assert_contains("'status'   => \$health['status'],", $source);
	pg_assert_contains("'cron_age' => \$health_cron_age,", $source);
	pg_assert_same(1, substr_count($source, 'json_encode('));
}

// The server itself (behind a tunnel or a reverse proxy on the same machine)
// is an address every visitor arrives with; a real address is not. The
// runner has no database, so the firewall is handed its settings: no trusted
// proxy declared.
function test_maintenance_ip_is_shared()
{
	waf_config(array('waf_trusted_proxies' => ''));

	pg_assert_true(pg_maintenance_ip_is_shared('::1'), 'IPv6 loopback, a local tunnel');
	pg_assert_true(pg_maintenance_ip_is_shared('127.0.0.1'), 'IPv4 loopback');
	pg_assert_true(pg_maintenance_ip_is_shared('127.8.9.10'), 'the whole loopback range');
	pg_assert_false(pg_maintenance_ip_is_shared('203.0.113.7'), 'a public address');
	pg_assert_false(pg_maintenance_ip_is_shared('192.168.1.20'), 'a private address seen directly');
	pg_assert_false(pg_maintenance_ip_is_shared(''), 'no address');
}

// A list lets everyone through when it holds the address this request comes
// from and every visitor shares that address.
function test_maintenance_list_opens_to_all()
{
	pg_assert_true(pg_maintenance_list_opens_to_all('::1', '::1', true), 'the tunnel address itself');
	pg_assert_true(pg_maintenance_list_opens_to_all("203.0.113.9\n127.0.0.1", '127.0.0.1', true), 'among other addresses');
	pg_assert_true(pg_maintenance_list_opens_to_all('127.0.0.0/8', '127.0.0.1', true), 'a range that holds it');
	pg_assert_true(pg_maintenance_list_opens_to_all('127.0.0.*', '127.0.0.1', true), 'a wildcard that holds it');

	pg_assert_false(pg_maintenance_list_opens_to_all('203.0.113.9', '127.0.0.1', true), 'the list does not hold it');
	pg_assert_false(pg_maintenance_list_opens_to_all('', '127.0.0.1', true), 'an empty list');
	pg_assert_false(pg_maintenance_list_opens_to_all('203.0.113.7', '203.0.113.7', false), 'an address of the visitor\'s own');
	pg_assert_false(pg_maintenance_list_opens_to_all('::1', '', true), 'no address');
}
