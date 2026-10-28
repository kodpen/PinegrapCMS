<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how refusals become an automatic ban (waf_register_offence(),
 * waf.php): a rate refusal counts once per subject and minute
 * (waf_offence_is_repeat()), which callers pass the burst flag, an address
 * with a staff sign-in is spared after the threshold, and the firewall log
 * screen forgets the offences of a ban it releases (waf_offence_clear()),
 * a concurrent refusal is no offence and a rate ban stays off the ladder
 * (waf_auto_ban_climbs(), waf_auto_ban_sql()).
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

	foreach (array('waf_handle_rate_sensitive', 'waf_handle_rate_api') as $handler) {
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
	$threshold = strpos($body, "waf_rate_record(\$subject, 'o', 600) <= \$threshold");
	$spared    = strpos($body, "waf_rate_hits(\$subject, 'ob', 600) > 0");
	$staff     = strpos($body, 'waf_user_is_staff() || waf_address_has_staff_sign_in($ip)');
	$mark      = strpos($body, "waf_rate_record(\$subject, 'ob', 600);");
	$would     = strpos($body, "waf_log_event('would-ban', 'auto-ban', 'ban'");
	$ban       = strpos($body, 'waf_auto_ban($subject, $minutes');

	pg_assert_true($fold !== false && $threshold !== false && $spared !== false && $staff !== false && $mark !== false && $would !== false && $ban !== false);
	pg_assert_true($fold < $threshold && $threshold < $spared && $spared < $staff && $staff < $mark && $mark < $would && $would < $ban, 'fold, threshold, spared this slot, staff, mark, would-ban, ban');
	pg_assert_contains('$burst = false)', $body, 'callers without the flag are counted one by one');
}

// The count a bucket write returns is the one it wrote, read from the
// statement itself: two refusals arriving together must not both read the
// other's write and both pass as repeats.
function test_waf_offence_bucket_count_is_the_written_one()
{
	$waf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');
	$body = waf_offence_test_function_source($waf, 'waf_rate_record');

	pg_assert_contains('hits = LAST_INSERT_ID(IF(window_start <', $body);
	$written  = strpos($body, '(int) @mysqli_insert_id(db::$con)');
	$inserted = strpos($body, '@mysqli_affected_rows(db::$con) === 1');
	$fallback = strpos($body, 'return waf_rate_hits($subject, $scope, $window_seconds);');
	pg_assert_true($written !== false && $inserted !== false && $fallback !== false);
	pg_assert_true($written < $inserted && $inserted < $fallback, 'the written value, then the plain insert, then the read');
}

// The staff question reads panel roles first and, for IPv6, the whole /64 the
// ban would name; the address of a token follows its activity.
function test_waf_offence_staff_lookup_reads_the_block_and_roles_first()
{
	$waf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');
	$body = waf_offence_test_function_source($waf, 'waf_address_has_staff_sign_in');

	pg_assert_contains("t.ip_address LIKE '%:%'", $body, 'every IPv6 address of the day');
	pg_assert_contains("waf_ip_subject((string) \$row['ip_address']) !== \$subject", $body, 'told apart by block in PHP');
	pg_assert_contains('ORDER BY u.user_role ASC', $body, 'panel roles first');
	pg_assert_contains('LIMIT 500', $body);
	pg_assert_contains('waf_user_id_is_staff($user_id)', $body, 'one copy of the staff rule');

	$auth = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/auth.php');
	pg_assert_same(2, substr_count($auth, '" . $auth_bind_stamp . "'), 'both activity stamps carry the address');
	pg_assert_contains("ip_address = '\" . escape(pg_auth_request_address()) . \"'", $auth);
	$shared = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/authentication.php');
	pg_assert_contains("last_used_at = '\" . time() . \"', ip_address = '\" . escape(pg_auth_request_address()) . \"'", $shared, 'the remember-me redeem too');
	pg_assert_same('', pg_auth_request_address(), 'no address outside a request');
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
	pg_assert_contains("waf_rate_clear(\$key, 'ob');", $body, 'the spared mark goes with the ban');
	pg_assert_contains('$keys[] = waf_ip_subject($stored);', $body, 'the /64 of an address');
}

// The log screen and the dashboard widget name the new action and count it
// among what the firewall held back.
function test_waf_offence_would_ban_has_a_badge()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_waf_log.php');
	pg_assert_contains("'would-ban'   => array('bg-warning fg-black', lang('Would ban')),", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/waf_log_list.php'));
	pg_assert_contains("action IN ('would-block','would-rate','would-ban')", $screen);

	$widget = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets/widget_21.php');
	pg_assert_contains("'would-ban'   => array('warning',           lang('Would ban')),", $widget);
	pg_assert_contains("action IN ('would-block','would-rate','would-ban')", $widget);
	pg_assert_contains("action IN ('would-block','would-rate','would-ban')", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets/widget_22.php'));
}

// The fourth simultaneous request is refused and logged, but is no offence:
// an office behind one address opens that many forms at once.
function test_waf_offence_concurrent_refusal_is_no_offence()
{
	$waf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');
	$body = waf_offence_test_function_source($waf, 'waf_handle_inflight');

	pg_assert_same(false, strpos($body, 'waf_register_offence('), 'no offence for a concurrent refusal');
	pg_assert_contains("waf_log_event(\$blocking ? 'rate' : 'would-rate', 'concurrent', 'rate'", $body, 'still logged');
	pg_assert_contains('waf_deny(429, waf_reference());', $body, 'still refused');
}

// Only a ban brought by an offence that is not a rate refusal climbs the x4
// ladder; a rate ban is the base length however often it came before.
function test_waf_offence_rate_ban_stays_off_the_ladder()
{
	pg_assert_false(waf_auto_ban_climbs('rate'), 'a rate ban');
	pg_assert_true(waf_auto_ban_climbs('sqli'), 'a signature ban');
	pg_assert_true(waf_auto_ban_climbs('bot'), 'an attack tool');
	pg_assert_true(waf_auto_ban_climbs(''), 'an offence without a logged category');

	$rate = waf_auto_ban_sql('203.0.113.7', 'Automatic: rate-sensitive', 1000, 3600, 604800, false);
	pg_assert_same(false, strpos($rate, 'POW('), 'no ladder for a rate ban');
	pg_assert_same(false, strpos($rate, 'hit_count + 1'), 'nor a rung');
	pg_assert_contains('hit_count = hit_count,', $rate);
	pg_assert_contains('expires_at = GREATEST(expires_at, 4600)', $rate, 'an expired ban comes back at the base length');
	pg_assert_contains("'203.0.113.7', 'block', 'auto'", $rate);

	$signature = waf_auto_ban_sql('203.0.113.7', 'Automatic: sqli', 1000, 3600, 604800, true);
	pg_assert_contains('hit_count = IF(expires_at < 1000, hit_count + 1, hit_count),', $signature, 'a signature ban climbs');
	pg_assert_contains('POW(4, LEAST(GREATEST(hit_count, 1) - 1, 8))', $signature);
	pg_assert_contains('GREATEST(expires_at, 4600)', $signature, 'a running ban keeps at least the base');

	$waf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');
	pg_assert_contains('waf_last_category($category);', waf_offence_test_function_source($waf, 'waf_log_event'));
	$body = waf_offence_test_function_source($waf, 'waf_register_offence');
	$read = strpos($body, '$climb = waf_auto_ban_climbs(waf_last_category());');
	$would = strpos($body, "waf_log_event('would-ban'");
	pg_assert_true($read !== false && $would !== false && $read < $would, 'the category is read before the log rows overwrite it');
	pg_assert_contains('waf_auto_ban($subject, $minutes, waf_ban_note($rule, $threshold), $climb);', $body);
}
