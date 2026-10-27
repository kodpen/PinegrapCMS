<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how refusals become an automatic ban (waf_register_offence(),
 * waf.php): a rate refusal counts once per subject and minute
 * (waf_offence_is_repeat()), which callers pass the burst flag, an address
 * with a staff sign-in is spared after the threshold, and the firewall log
 * screen forgets the offences of a ban it releases (waf_offence_clear()).
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

// The first refusal of a minute is an offence; the ones after it in the same
// minute are the same event. A refusal that is not a rate refusal is never
// folded, and a bucket that could not be written (0 hits) still counts.
function test_waf_offence_repeat_within_the_minute()
{
	pg_assert_false(waf_offence_is_repeat(true, 1), 'the first refusal');
	pg_assert_true(waf_offence_is_repeat(true, 2), 'the second refusal of the minute');
	pg_assert_true(waf_offence_is_repeat(true, 40), 'a page full of refused requests');
	pg_assert_false(waf_offence_is_repeat(true, 0), 'the bucket could not be written');
	pg_assert_false(waf_offence_is_repeat(false, 7), 'a signature hit is never folded');
	pg_assert_false(waf_offence_is_repeat(false, 1), 'nor a first one');
}

// A staff sign-in keeps its address out of the ban for a day.
function test_waf_offence_staff_address_window_is_a_day()
{
	pg_assert_same(86400, waf_staff_address_window());
}

// The source of a function, from its declaration to its closing brace.
function waf_offence_test_function_source($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');
	pg_assert_true($start !== false, $name . ' is defined');
	$end = strpos($source, "\n}\n", (int) $start);

	return substr($source, (int) $start, (int) $end - (int) $start);
}

// The rate refusals pass the burst flag; a signature hit, an attack tool and
// a spoofed crawler do not.
function test_waf_offence_rate_refusals_are_folded()
{
	$waf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');

	foreach (array('waf_handle_rate_sensitive', 'waf_handle_rate_api', 'waf_handle_inflight') as $handler) {
		pg_assert_contains('waf_register_offence($ip, 5, $blocking, true);', waf_offence_test_function_source($waf, $handler), $handler);
	}

	pg_assert_contains("waf_register_offence(\$ip, \$scan['score'], \$blocking);", waf_offence_test_function_source($waf, 'waf_handle_scan'), 'the signature scan');
	pg_assert_same(2, substr_count(waf_offence_test_function_source($waf, 'waf_handle_bot'), 'waf_register_offence($ip, 10, $blocking);'), 'attack tools and spoofed crawlers');

	// The password reset ceiling refuses a whole company's reset wave from
	// one address; its refusals are folded like the firewall's own.
	$auth = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/auth.php');
	pg_assert_contains('waf_register_offence(waf_client_ip(), 5, $blocking, true);', waf_offence_test_function_source($auth, 'pg_password_reset_deny'));

	// A wrong key or password is one act per request, as before.
	foreach (array('includes/api/auth.php', 'includes/api/outbound/webhook_receiver.php', 'includes/workspace/guests.php', 'includes/fn/prototype.php', 'includes/fn/prototype_links.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_same(0, preg_match('/waf_register_offence\([^;]*,\s*true\);/', $source), $file);
		pg_assert_true(strpos($source, 'waf_register_offence(') !== false, $file . ' still registers');
	}
}

// Inside waf_register_offence(): the fold is asked before the threshold, the
// staff question only after it, and a spared address is logged as would-ban.
function test_waf_offence_order_of_checks()
{
	$waf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');
	$body = waf_offence_test_function_source($waf, 'waf_register_offence');

	$fold      = strpos($body, "waf_offence_is_repeat(\$burst, waf_rate_record(\$subject, 'of', 60))");
	$threshold = strpos($body, "waf_rate_exceeded(\$subject, 'o', \$threshold, 600)");
	$staff     = strpos($body, 'waf_user_is_staff() || waf_address_has_staff_sign_in($ip)');
	$would     = strpos($body, "waf_log_event('would-ban', 'auto-ban', 'ban'");
	$ban       = strpos($body, 'waf_auto_ban($subject, $minutes');

	pg_assert_true($fold !== false && $threshold !== false && $staff !== false && $would !== false && $ban !== false);
	pg_assert_true($fold < $threshold && $threshold < $staff && $staff < $would && $would < $ban, 'fold, threshold, staff, would-ban, ban');
	pg_assert_contains('$burst = false)', $body, 'callers without the flag are counted one by one');
}

// Releasing a ban or allowing an address forgets its offences, so the next
// refusal does not place the ban again; both keys of a /64 ban are cleared.
function test_waf_offence_release_and_allow_clear_the_bucket()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_waf_log.php');

	$release = strpos($screen, "if (isset(\$_POST['submit_release'])) {");
	$allow   = strpos($screen, "if (isset(\$_POST['submit_allow'])) {");
	$first   = strpos($screen, 'waf_offence_clear($release_ip);');
	$matched = strpos($screen, "waf_offence_clear(\$auto_row['ip_address']);");
	$allowed = strpos($screen, 'waf_offence_clear($allow_ip);');

	pg_assert_true($release !== false && $allow !== false && $first !== false && $matched !== false && $allowed !== false);
	pg_assert_true($release < $first && $first < $allow, 'the release branch clears the released address');
	pg_assert_true($allow < $matched && $matched < $allowed, 'the allow branch clears every ban it lifts and the address itself');

	$waf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');
	$body = waf_offence_test_function_source($waf, 'waf_offence_clear');
	pg_assert_contains("waf_rate_clear(\$key, 'o');", $body);
	pg_assert_contains("waf_rate_clear(\$key, 'of');", $body);
	pg_assert_contains('$keys[] = waf_ip_subject($stored);', $body, 'the /64 of an address');
}

// The log screen names the new action.
function test_waf_offence_would_ban_has_a_badge()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_waf_log.php');

	pg_assert_contains("'would-ban'   => array('bg-warning text-dark', lang('Would ban')),", $screen);
}
