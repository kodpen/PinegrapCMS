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
