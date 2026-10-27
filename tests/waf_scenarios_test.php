<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The three stories of #1989 played through the firewall's decision
 * functions, end to end: a page with forty protected images, a member of
 * staff whose address changes mid-session, and a banned visitor refreshing
 * the page. Each step is the real decision (waf.php, includes/db_guard.php,
 * includes/fn/waf_notice.php, includes/fn/waf_rescue.php); only the
 * counters' storage is a model here, since the real buckets live in the
 * waf_rate table (pg_scenario_hits() repeats waf_rate_record()'s clock
 * window and reset).
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

// One counted event on a subject's bucket: the window as waf_rate_record()
// keys it (now - now % window), the count reset when the window rolls, and
// the new count returned. $buckets is the model's waf_rate table.
function pg_scenario_hits(array &$buckets, $subject, $scope, $now, $window)
{
	$start = $now - ($now % $window);
	$key = $subject . '|' . $scope;

	if (!isset($buckets[$key]) || $buckets[$key]['window_start'] < $start) {
		$buckets[$key] = array('hits' => 1, 'window_start' => $start);
	} else {
		$buckets[$key]['hits']++;
	}

	return $buckets[$key]['hits'];
}

// One refused request through waf_register_offence()'s counting, in its
// order: the rate refusal folded per minute (waf_offence_is_repeat()), then
// the ten-minute offence slot against the threshold. Returns 'repeat' when
// the minute already counted, 'offence' when counted and under the
// threshold, 'ban' when the threshold is passed.
function pg_scenario_offence(array &$buckets, $subject, $burst, $now, $threshold)
{
	if ($burst && waf_offence_is_repeat($burst, pg_scenario_hits($buckets, $subject, 'of', $now, 60))) {
		return 'repeat';
	}

	return (pg_scenario_hits($buckets, $subject, 'o', $now, 600) > $threshold) ? 'ban' : 'offence';
}

// A page view's requests on the sensitive allowance: the page itself plus
// every fetch that reaches a sign-in screen and counts there.
function pg_scenario_sensitive_hits(array &$buckets, $subject, $now, array $requests, $limit)
{
	$refused = 0;

	foreach ($requests as $request) {
		if (!waf_sensitive_request_counts($request[0], $request[1], $request[2], $request[3])) {
			continue;
		}

		if (pg_scenario_hits($buckets, $subject, 's', $now, 60) > $limit) {
			$refused++;
		}
	}

	return $refused;
}

// Scenario 1, the issue itself: a page with forty submitted-form images
// whose records no view shows, opened by a visitor who is not signed in.
// get_file.php answers each image fetch 403 instead of sending it to the
// sign-in screen, so the sensitive allowance sees the page opening only.
function test_waf_scenarios_page_with_forty_protected_images()
{
	$buckets = array();
	$now = 1700000000;
	$ip = '203.0.113.7';

	// Every <img> fetch is a sub-resource: refused in place, never redirected.
	$refused_in_place = 0;

	for ($i = 0; $i < 40; $i++) {
		if (waf_fetch_is_subresource('GET', 'image', 'image/avif,image/webp,*/*')) {
			$refused_in_place++;
		}
	}

	pg_assert_same(40, $refused_in_place, 'every image answered 403 by get_file.php');

	// Nothing landed on the sign-in screen; the page opening counts alone.
	$requests = array(array('GET', 'router.php', 'document', 'text/html,*/*;q=0.8'));
	pg_assert_same(0, pg_scenario_sensitive_hits($buckets, $ip, $now, $requests, 30), 'no refusal');
	pg_assert_false(isset($buckets[$ip . '|s']), 'a page is not a sensitive script');

	// A browser that still follows the redirect (another site's redirect, a
	// script tag on a plain-http site): forty GETs of the sign-in screen with
	// a wildcard Accept count, ten are refused, and the fold makes the burst
	// one offence: no ban.
	$buckets = array();
	$requests = array();

	for ($i = 0; $i < 40; $i++) {
		$requests[] = array('GET', 'registration_entrance.php', '', '*/*');
	}

	pg_assert_same(10, pg_scenario_sensitive_hits($buckets, $ip, $now, $requests, 30), 'the allowance refuses the 31st request on');

	$outcomes = array();

	for ($i = 0; $i < 10; $i++) {
		$outcomes[] = pg_scenario_offence($buckets, $ip, true, $now + $i, 5);
	}

	pg_assert_same('offence', $outcomes[0], 'the first refusal of the minute counts');
	pg_assert_same(array_fill(0, 9, 'repeat'), array_slice($outcomes, 1), 'the other nine are the same event');
	pg_assert_same(1, $buckets[$ip . '|o']['hits'], 'one offence in the slot');

	// Before the fold five refusals of one second were five offences: the
	// sixth banned the address.
	$old = array();

	for ($i = 0; $i < 6; $i++) {
		$last = pg_scenario_offence($old, $ip, false, $now + $i, 5);
	}

	pg_assert_same('ban', $last, 'counted one by one, the same burst banned');

	// The same forty fetches as images are not even counted on the sign-in
	// screen: the redirect's landing is read for what it is.
	$buckets = array();
	$requests = array();

	for ($i = 0; $i < 40; $i++) {
		$requests[] = array('GET', 'registration_entrance.php', 'image', 'image/avif,image/webp,*/*');
	}

	pg_assert_same(0, pg_scenario_sensitive_hits($buckets, $ip, $now, $requests, 30));
	pg_assert_false(isset($buckets[$ip . '|s']), 'nothing counted');
}

// Scenario 1 continued: a script that keeps crossing the allowance is banned
// after refusals in six separate minutes of the slot, the ban names its rule,
// and what it writes reads back: the queued notice and the mirror line.
function test_waf_scenarios_sustained_refusals_are_banned()
{
	$buckets = array();
	$slot = 1700000400;
	$ip = '203.0.113.9';
	$outcomes = array();

	// A refused request in each of six minutes of one ten-minute slot.
	for ($minute = 0; $minute < 6; $minute++) {
		$outcomes[] = pg_scenario_offence($buckets, $ip, true, $slot + ($minute * 60) + 5, 5);
	}

	pg_assert_same(array('offence', 'offence', 'offence', 'offence', 'offence', 'ban'), $outcomes);

	// The ban note and the operator's queued line name the rule.
	pg_assert_same('Automatic: rate-sensitive, 5+ firewall events', waf_ban_note('rate-sensitive', 5));

	$line = waf_ban_notice_line($slot + 305, $ip, $ip, 'rate-sensitive', 60);
	$entry = waf_ban_notice_parse($line);
	pg_assert_same(array('when' => $slot + 305, 'ip' => $ip, 'subject' => $ip, 'rule' => 'rate-sensitive', 'minutes' => 60), $entry);

	// The first ban of the rule within an hour is mailed on its own, with the
	// command that lifts it.
	$plan = pg_waf_ban_notice_plan(array($entry), array(), $slot + 310);
	pg_assert_same(1, count($plan['notices']));
	pg_assert_same('ban', $plan['notices'][0]['kind']);
	pg_assert_same('php waf_unban.php 203.0.113.9', pg_waf_unban_command($ip));

	// The mirror carries the ban's end, and the shield reads it as a live
	// ban until then.
	$end = $slot + 305 + 3600;
	$line = waf_ban_shield_block_line($ip, $end);
	pg_assert_same("B 203.0.113.9 " . $end . "\n", $line);
	pg_assert_same(array('pattern' => $ip, 'expires' => $end), pg_ban_shield_block_entry(substr($line, 2), $slot + 400));
}

// Scenario 2: a member of staff signed in at the office, whose address
// changes to a phone's mid-session; an office behind an unlisted proxy; the
// settings save that would shut the operator out.
function test_waf_scenarios_staff_address_changes_mid_session()
{
	$office = '203.0.113.7';
	$phone = '198.51.100.4';
	$allowed = array($office);

	// No database here: the firewall's settings are seeded, with no trusted
	// proxy listed.
	waf_config(array('waf_trusted_proxies' => ''));

	// Both are visitors' own addresses, counted and bannable on their own
	// subject; the office address, on the allow list, is spared the sign-in
	// lock, the phone's is not.
	pg_assert_true(waf_ip_is_counted($office) && waf_ip_is_counted($phone));
	pg_assert_same($office, waf_ip_subject($office));
	pg_assert_same('', pg_login_throttle_address_subject($office, true, $allowed), 'the allow list spares the office');
	pg_assert_same($phone, pg_login_throttle_address_subject($phone, true, $allowed), 'the phone is counted');

	// An IPv6 phone rotating inside its block: the ban would name the /64,
	// and so does the staff sign-in's row, so the two agree.
	$morning = '2001:db8:1:2::aaaa';
	$evening = '2001:db8:1:2:8f3:ffff:1:2';
	pg_assert_same('2001:db8:1:2::/64', waf_ip_subject($morning));
	pg_assert_same(waf_ip_subject($morning), waf_ip_subject($evening), 'one block, one subject');
	pg_assert_true(waf_ip_subject('2001:db8:1:3::1') !== waf_ip_subject($morning), 'another block');

	// The office behind a proxy nobody listed under Trusted Proxies: every
	// visitor is 10.0.0.1, which no counter and no lock keys on.
	pg_assert_false(waf_ip_is_counted('10.0.0.1'));
	pg_assert_same('', pg_login_throttle_address_subject('10.0.0.1', waf_ip_is_counted('10.0.0.1'), array()));
	pg_assert_false(waf_ip_is_infrastructure('10.0.0.1'), 'and still a person\'s own address to the maintenance card');

	// Settings › Firewall: a block range typed from the phone that covers the
	// phone is refused, unless an allow entry covers it too.
	pg_assert_same(array('198.51.100.0/24'), pg_waf_own_address_blocks($phone, array('203.0.113.200', '198.51.100.0/24'), array()));
	pg_assert_same(array(), pg_waf_own_address_blocks($phone, array('198.51.100.0/24'), array($phone)));

	// The token's row follows the device: outside a request there is no
	// address to stamp.
	pg_assert_same('', pg_auth_request_address());
}

// Scenario 3: a banned visitor refreshes the page. The shield's page names
// the ban's end and a reference the operator can look up, the refusal is
// recorded for the log with that reference, and once the ban ends the mirror
// line stops counting even if nobody refreshed the mirror.
function test_waf_scenarios_refresh_after_the_ban()
{
	$ip = '203.0.113.7';
	$banned_at = 1700000000;
	$end = $banned_at + 3600;

	// The mirror line written at ban time, read by the shield mid-ban.
	$entry = pg_ban_shield_block_entry(substr(waf_ban_shield_block_line($ip, $end), 2), $banned_at + 1800);
	pg_assert_same(array('pattern' => $ip, 'expires' => $end), $entry);
	pg_assert_true(waf_ip_matches_simple($ip, $entry['pattern']), 'the refresh is refused');

	// The page says who refused it, until when, and what to quote.
	$reference = 'A1B2C3D4E5F6';
	$page = pg_ban_shield_page($reference, $end);
	pg_assert_contains('blocked by the site firewall', $page);
	pg_assert_contains('The block ends at ' . date('Y-m-d H:i T', $end), $page);
	pg_assert_contains('<code>' . $reference . '</code>', $page);

	// The refusal reaches the log with the same reference once a request with
	// a database drains the pending file.
	$pending = waf_shield_pending_line(($banned_at + 1800) . "\t" . $ip . "\t/\t" . $reference);
	pg_assert_same(array('when' => $banned_at + 1800, 'ip' => $ip, 'path' => '/', 'reference' => $reference), $pending);

	// A refresh after the hour: the line is dead on its own.
	pg_assert_same(null, pg_ban_shield_block_entry(substr(waf_ban_shield_block_line($ip, $end), 2), $end));
	pg_assert_same(null, pg_ban_shield_block_entry(substr(waf_ban_shield_block_line($ip, $end), 2), $end + 86400));

	// A manual entry never ends on its own.
	pg_assert_same(array('pattern' => '203.0.113.0/24', 'expires' => 0), pg_ban_shield_block_entry(substr(waf_ban_shield_block_line('203.0.113.0/24', 0), 2), $end + 86400));

	// A second address banned by the same rule within the hour turns the
	// operator's second e-mail into one notice naming both.
	$first = waf_ban_notice_parse(waf_ban_notice_line($banned_at, $ip, $ip, 'rate-sensitive', 60));
	$plan = pg_waf_ban_notice_plan(array($first), array(), $banned_at + 5);
	$second = waf_ban_notice_parse(waf_ban_notice_line($banned_at + 600, '203.0.113.8', '203.0.113.8', 'rate-sensitive', 60));
	$plan = pg_waf_ban_notice_plan(array($second), $plan['state'], $banned_at + 605);
	pg_assert_same(1, count($plan['notices']));
	pg_assert_same('burst', $plan['notices'][0]['kind']);
	pg_assert_same(array($ip, '203.0.113.8'), $plan['notices'][0]['subjects']);
}
