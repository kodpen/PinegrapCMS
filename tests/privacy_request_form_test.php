<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a person's own request about their personal data
 * (includes/fn/privacy_request_form.php, privacy_request.php): the public
 * form off by default and its switch, a signed-in person against a visitor,
 * the hourly limit per address, the proof link's signature and its 48 hours.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/privacy_requests.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/privacy_request_form.php');

// Off until switched on: no row, an empty or any other value is off.
function test_privacy_request_form_is_off_by_default()
{
	pg_assert_false(pg_privacy_request_form_on_value(''), 'no row');
	pg_assert_false(pg_privacy_request_form_on_value(null), 'nothing read');
	pg_assert_false(pg_privacy_request_form_on_value('0'), 'zero');
	pg_assert_false(pg_privacy_request_form_on_value('yes'), 'another word');
	pg_assert_true(pg_privacy_request_form_on_value('1'), 'switched on');
}

// A visitor reaches the form only while it is on; a signed-in person always;
// nobody before the table is there.
function test_privacy_request_form_access()
{
	pg_assert_same('off', pg_privacy_request_form_access(false, 0), 'a visitor, switch off: 404');
	pg_assert_same('form', pg_privacy_request_form_access(true, 0), 'a visitor, switch on');
	pg_assert_same('account', pg_privacy_request_form_access(false, 12), 'signed in, switch off');
	pg_assert_same('account', pg_privacy_request_form_access(true, 12), 'signed in, switch on');
	pg_assert_same('off', pg_privacy_request_form_access(true, 12, false), 'no table yet');
}

// Only the role that may save Site Settings switches the form.
function test_privacy_request_form_switch_is_refused_below_manager()
{
	pg_assert_same('refused', pg_privacy_request_form_switch(true, array('role' => 3, 'id' => 5)), 'a role-3 user');
	pg_assert_same('refused', pg_privacy_request_form_switch(true, array()), 'no role');
}

// A signed-in person's request is open at once with its 30 days; the address
// is the account's, whatever was posted.
function test_privacy_request_form_row_signed_in()
{
	$now = mktime(9, 0, 0, 11, 21, 2026);
	$out = pg_privacy_request_form_row(
		array('kind' => 'erasure', 'email' => 'other@example.com', 'message' => "Erase\x07 me"),
		'account',
		array('user_id' => 12, 'email' => 'Ayse@Example.com', 'name' => 'Ayşe Yılmaz'),
		$now);

	pg_assert_same(array(), $out['errors'], 'no errors');
	pg_assert_same('open', $out['row']['status'], 'open');
	pg_assert_same('account', $out['row']['source'], 'from My Account');
	pg_assert_same('ayse@example.com', $out['row']['email'], 'the account address');
	pg_assert_same(12, $out['row']['user_id'], 'the account');
	pg_assert_same($now, $out['row']['received_at'], 'received now');
	pg_assert_same(pg_privacy_request_due_at($now), $out['row']['due_at'], '30 days');
	pg_assert_same('Erase me', $out['row']['message'], 'control characters dropped');

	$none = pg_privacy_request_form_row(array('kind' => 'other'), 'account', array('user_id' => 12, 'email' => ''), $now);
	pg_assert_true(isset($none['errors']['email']), 'an account without an address');
}

// A visitor's request waits for the proof: unproven, swept after 7 days.
function test_privacy_request_form_row_visitor()
{
	$now = mktime(9, 0, 0, 11, 21, 2026);
	$out = pg_privacy_request_form_row(array('kind' => 'information', 'email' => ' Visitor@Example.com ', 'name' => 'Ali'), 'form', array(), $now);

	pg_assert_same(array(), $out['errors'], 'no errors');
	pg_assert_same('unproven', $out['row']['status'], 'unproven');
	pg_assert_same('form', $out['row']['source'], 'public form');
	pg_assert_same('visitor@example.com', $out['row']['email'], 'the address typed');
	pg_assert_same(0, $out['row']['user_id'], 'no account');
	pg_assert_same(0, $out['row']['received_at'], 'not received yet');
	pg_assert_same(pg_privacy_request_sweep_at($now), $out['row']['due_at'], 'the sweep moment');

	$bad = pg_privacy_request_form_row(array('kind' => 'delete-all', 'email' => 'nobody'), 'form', array(), $now);
	pg_assert_true(isset($bad['errors']['kind']), 'an unknown kind');
	pg_assert_true(isset($bad['errors']['email']), 'not an address');
}

// Five sends an hour for one address, counted with the sender's address too.
function test_privacy_request_rate_limit()
{
	$limits = pg_privacy_request_form_limits();

	pg_assert_same(5, $limits['per_email'], 'five an address');
	pg_assert_same(3600, $limits['window'], 'an hour');
	pg_assert_same(
		array('email:visitor@example.com' => 5, 'ip:203.0.113.0' => $limits['per_ip']),
		pg_privacy_request_rate_subjects('Visitor@Example.com', '203.0.113.0'),
		'both buckets');
	pg_assert_same(array('email:visitor@example.com' => 5), pg_privacy_request_rate_subjects('visitor@example.com', ''), 'no network address');

	// Counted in the rate store itself, which runs with the firewall off;
	// checked after the form and the spam check, before the row is written.
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/privacy_request.php');
	$throttle = strpos($page, 'pg_privacy_request_throttled(');
	pg_assert_true($throttle !== false, 'the page counts');
	pg_assert_true($throttle > strpos($page, 'validate_captcha_answer('), 'after the spam check');
	pg_assert_true($throttle < strpos($page, 'pg_privacy_request_submit('), 'before the row');

	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/privacy_request_form.php');
	pg_assert_false(strpos($module, 'waf_mode') !== false, 'not tied to the firewall mode');
	pg_assert_true(in_array('privacy_request.php', privacy_request_form_test_sensitive_scripts(), true), 'a sensitive script');
}

// The proof link: signed over request, time and address; 48 hours.
function test_privacy_request_proof_link()
{
	$key = 'test-key';
	$issued = mktime(9, 0, 0, 11, 21, 2026);
	$signature = pg_privacy_request_proof_token(7, 'visitor@example.com', $issued, $key);
	$link = pg_privacy_request_proof_parse(array('r' => '7', 't' => (string) $issued, 's' => $signature));

	pg_assert_same(64, strlen($signature), 'a sha256 HMAC');
	pg_assert_same($signature, pg_privacy_request_proof_token(7, 'Visitor@Example.com', $issued, $key), 'the address as normalised');
	pg_assert_false($signature === pg_privacy_request_proof_token(8, 'visitor@example.com', $issued, $key), 'another request');
	pg_assert_false($signature === pg_privacy_request_proof_token(7, 'other@example.com', $issued, $key), 'another address');
	pg_assert_false($signature === pg_privacy_request_proof_token(7, 'visitor@example.com', $issued, 'other-key'), 'another key');
	pg_assert_false($signature === hash_hmac('sha256', 'subscribe-confirm:7:' . $issued . ':visitor@example.com', $key), 'not a subscription link');
	pg_assert_same('', pg_privacy_request_proof_token(7, 'visitor@example.com', $issued, ''), 'no key, no link');

	pg_assert_same('confirm', pg_privacy_request_proof_state($link, $signature, $issued + 3600, false), 'within 48 hours');
	pg_assert_same('confirm', pg_privacy_request_proof_state($link, $signature, $issued + 172800, false), 'at 48 hours');
	pg_assert_same('expired', pg_privacy_request_proof_state($link, $signature, $issued + 172801, false), 'past 48 hours');
	pg_assert_same('proven', pg_privacy_request_proof_state($link, $signature, $issued + 172801, true), 'proven already');
	pg_assert_same('invalid', pg_privacy_request_proof_state($link, str_repeat('0', 64), $issued, false), 'a wrong signature');
	pg_assert_same('invalid', pg_privacy_request_proof_state($link, '', $issued, false), 'no row');
	pg_assert_same('invalid', pg_privacy_request_proof_state($link, $signature, $issued - 600, false), 'issued in the future');

	pg_assert_same(null, pg_privacy_request_proof_parse(array('r' => '7', 't' => (string) $issued, 's' => 'abc')), 'a short signature');
	pg_assert_same(null, pg_privacy_request_proof_parse(array('r' => '07', 't' => (string) $issued, 's' => $signature)), 'a padded id');
	pg_assert_same(null, pg_privacy_request_proof_parse(array('r' => array('7'), 't' => (string) $issued, 's' => $signature)), 'an array');
}

// The page: 404 while off for a visitor, the same answer for any address,
// the proof a POST with the form token.
function test_privacy_request_page_rules()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/privacy_request.php');

	pg_assert_contains("if (\$pr_access === 'off') {\n    output_error(lang('This page is not available.'), 404);", $page, '404 while off');
	pg_assert_contains("if (!\$pr_form_on || !pg_privacy_requests_ready()) {", $page, 'the proof link only while on');
	pg_assert_contains("if (\$pr_post && (\$pr_state === 'confirm')) {", $page, 'only the POST proves');
	pg_assert_contains("go(\$pr_self . '?sent=1');", $page, 'one answer after a send');
	pg_assert_false(strpos($page, 'pg_privacy_export_find') !== false, 'the page never says whether the address is known');
}

function privacy_request_form_test_sensitive_scripts()
{
	if (!function_exists('waf_sensitive_scripts')) {
		$waf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');
		$start = strpos($waf, 'function waf_sensitive_scripts()');
		$body = substr($waf, $start, strpos($waf, "\n}\n", $start) - $start);
		preg_match_all("/'([a-z_]+\\.php)'/", $body, $match);

		return $match[1];
	}

	return waf_sensitive_scripts();
}
