<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the operator's notice of an automatic ban: the pending line
 * waf_register_offence() queues (waf_ban_notice_line(),
 * waf_ban_notice_parse() in waf.php), which bans are mailed one by one and
 * which become a single notice when one rule bans several addresses within an
 * hour (pg_waf_ban_notice_plan()), the mail bodies and the log search an
 * address or range is linked with (includes/fn/waf_notice.php).
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

// A queued ban is one tab-separated line that reads back as written.
function test_waf_ban_notice_line_round_trip()
{
	$line = waf_ban_notice_line(1700000000, '203.0.113.7', '203.0.113.7', 'rate-sensitive', 60);

	pg_assert_same("1700000000\t203.0.113.7\t203.0.113.7\trate-sensitive\t60\n", $line);
	pg_assert_same(
		array('when' => 1700000000, 'ip' => '203.0.113.7', 'subject' => '203.0.113.7', 'rule' => 'rate-sensitive', 'minutes' => 60),
		waf_ban_notice_parse($line)
	);

	$v6 = waf_ban_notice_parse(waf_ban_notice_line(1700000000, '2001:db8:1:2::9', '2001:db8:1:2::/64', 'sqli-union', 240));
	pg_assert_same('2001:db8:1:2::/64', $v6['subject']);
	pg_assert_same(240, $v6['minutes']);
}

// A rule cannot break the line or carry markup; an unknown rule reads as
// auto-ban.
function test_waf_ban_notice_line_cleans_the_rule()
{
	$line = waf_ban_notice_line(1700000000, '203.0.113.7', '203.0.113.7', "a\tb\n<x>", 60);

	pg_assert_same(1, substr_count($line, "\n"));
	pg_assert_same('abx', waf_ban_notice_parse($line)['rule']);
	pg_assert_same('auto-ban', waf_ban_notice_parse(waf_ban_notice_line(1700000000, '203.0.113.7', '203.0.113.7', '', 60))['rule']);
}

// A torn, empty or forged line is skipped.
function test_waf_ban_notice_parse_rejects()
{
	pg_assert_same(null, waf_ban_notice_parse(''));
	pg_assert_same(null, waf_ban_notice_parse("1700000000\t203.0.113.7\t203.0.113.7\trate"));
	pg_assert_same(null, waf_ban_notice_parse("0\t203.0.113.7\t203.0.113.7\trate\t60"));
	pg_assert_same(null, waf_ban_notice_parse("1700000000\tnot-an-ip\tx\trate\t60"));
	pg_assert_same(null, waf_ban_notice_parse("1700000000\t203.0.113.7\t<b>\trate\t60"));
}

function waf_ban_notice_test_entry($when, $ip, $rule)
{
	return array('when' => $when, 'ip' => $ip, 'subject' => $ip, 'rule' => $rule, 'minutes' => 60);
}

// The first ban of a rule is mailed on its own.
function test_waf_ban_notice_plan_first_ban_alone()
{
	$now = 1700000000;
	$plan = pg_waf_ban_notice_plan(array(waf_ban_notice_test_entry($now, '203.0.113.7', 'rate-sensitive')), array(), $now);

	pg_assert_same(1, count($plan['notices']));
	pg_assert_same('ban', $plan['notices'][0]['kind']);
	pg_assert_same('203.0.113.7', $plan['notices'][0]['entry']['ip']);
}

// A second address banned by the same rule within the hour turns into one
// notice naming both; the third and later ones of the hour send nothing.
function test_waf_ban_notice_plan_burst_across_runs()
{
	$now = 1700000000;
	$first = pg_waf_ban_notice_plan(array(waf_ban_notice_test_entry($now, '203.0.113.7', 'rate-sensitive')), array(), $now);

	$second = pg_waf_ban_notice_plan(array(waf_ban_notice_test_entry($now + 600, '203.0.113.8', 'rate-sensitive')), $first['state'], $now + 600);
	pg_assert_same(1, count($second['notices']));
	pg_assert_same('burst', $second['notices'][0]['kind']);
	pg_assert_same('rate-sensitive', $second['notices'][0]['rule']);
	pg_assert_same(array('203.0.113.7', '203.0.113.8'), $second['notices'][0]['subjects']);

	$third = pg_waf_ban_notice_plan(array(waf_ban_notice_test_entry($now + 1200, '203.0.113.9', 'rate-sensitive')), $second['state'], $now + 1200);
	pg_assert_same(array(), $third['notices']);

	// Another rule is told on its own.
	$other = pg_waf_ban_notice_plan(array(waf_ban_notice_test_entry($now + 1300, '198.51.100.1', 'sqli-union')), $third['state'], $now + 1300);
	pg_assert_same('ban', $other['notices'][0]['kind']);

	// An hour after the last notice of the rule, a ban is mailed alone again.
	$later = pg_waf_ban_notice_plan(array(waf_ban_notice_test_entry($now + 7300, '203.0.113.10', 'rate-sensitive')), $other['state'], $now + 7300);
	pg_assert_same('ban', $later['notices'][0]['kind']);
}

// Several bans of one rule drained together make one notice.
function test_waf_ban_notice_plan_burst_in_one_run()
{
	$now = 1700000000;
	$entries = array();

	for ($i = 1; $i <= 6; $i++) {
		$entries[] = waf_ban_notice_test_entry($now + $i, '203.0.113.' . $i, 'rate-sensitive');
	}

	$plan = pg_waf_ban_notice_plan($entries, array(), $now + 10);

	pg_assert_same(2, count($plan['notices']));
	pg_assert_same('ban', $plan['notices'][0]['kind']);
	pg_assert_same('burst', $plan['notices'][1]['kind']);
	pg_assert_same(6, count($plan['notices'][1]['subjects']));
}

// The state keeps only the last hour and stays small.
function test_waf_ban_notice_plan_state_is_pruned()
{
	$now = 1700000000;
	$state = array('rules' => array('old-rule' => array('bans' => array(array($now - 7200, '203.0.113.1')), 'burst' => $now - 7200)));

	$plan = pg_waf_ban_notice_plan(array(), $state, $now);
	pg_assert_same(array(), $plan['state']['rules']);

	$entries = array();

	for ($i = 0; $i < 300; $i++) {
		$entries[] = waf_ban_notice_test_entry($now, '10.0.' . intdiv($i, 250) . '.' . ($i % 250), 'rate-sensitive');
	}

	$plan = pg_waf_ban_notice_plan($entries, array(), $now);
	pg_assert_true(count($plan['state']['rules']['rate-sensitive']['bans']) <= 100);
	pg_assert_true(count($plan['notices'][1]['subjects']) <= 100);
}

// The log is searched for an address as it is, and for a range by the
// address part before its prefix length.
function test_waf_ban_log_search()
{
	pg_assert_same('203.0.113.7', pg_waf_ban_log_search('203.0.113.7'));
	pg_assert_same('2001:db8:1:2:', pg_waf_ban_log_search('2001:db8:1:2::/64'));
	pg_assert_same('198.51.100.', pg_waf_ban_log_search('198.51.100.0/24'));
	pg_assert_same('view_waf_log.php?range=7&search=2001%3Adb8%3A1%3A2%3A', pg_waf_ban_log_url('2001:db8:1:2::/64'));
}

// The ban mail names the rule, the address, the samples and the log link,
// all escaped.
function test_waf_ban_notice_email_html()
{
	$entry = waf_ban_notice_test_entry(1700000000, '203.0.113.7', 'rate-sensitive');
	$samples = array(
		array('when' => 1699999990, 'rule' => 'rate-sensitive', 'method' => 'GET', 'url' => '/get_file.php?name=a.jpg&x=<script>', 'hits' => 36),
	);

	$html = pg_waf_ban_notice_email_html($entry, $samples, 'https://example.com/pinegrap/view_waf_log.php?range=7&search=203.0.113.7');

	pg_assert_true(strpos($html, '203.0.113.7') !== false, 'address');
	pg_assert_true(strpos($html, 'rate-sensitive') !== false, 'rule');
	pg_assert_true(strpos($html, '/get_file.php?name=a.jpg&amp;x=&lt;script&gt;') !== false, 'sample escaped');
	pg_assert_true(strpos($html, '<script>') === false, 'no raw markup');
	pg_assert_true(strpos($html, 'href="https://example.com/pinegrap/view_waf_log.php?range=7&amp;search=203.0.113.7"') !== false, 'log link');
	pg_assert_true(strpos($html, '36') !== false, 'hit count');
}

// The burst notice lists the addresses once and links the log by rule.
function test_waf_ban_notice_burst_html()
{
	$html = pg_waf_ban_notice_burst_html('rate-sensitive', array('203.0.113.7', '2001:db8:1:2::/64'), 'https://example.com/pinegrap/view_waf_log.php?range=1&search=rate-sensitive');

	pg_assert_true(strpos($html, 'rate-sensitive') !== false);
	pg_assert_true(strpos($html, '203.0.113.7') !== false);
	pg_assert_true(strpos($html, '2001:db8:1:2::/64') !== false);
	pg_assert_true(strpos($html, 'search=rate-sensitive') !== false);
}
