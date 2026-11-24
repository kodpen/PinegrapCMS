<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the agency summary's pure parts (includes/fn/launch_summary.php):
 * the signed link's token and stored state, what a token opens (invalid,
 * withdrawn, expired, open), the journey row's other keys kept, the lines'
 * and answers' cleaning, the done steps the customer sees, and that the
 * page is a sensitive script of the firewall.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/launch_journey.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/launch_summary.php');

function test_launch_summary_token()
{
	$token = pg_launch_summary_token(1764000000, 'key');
	pg_assert_same(hash_hmac('sha256', 'launch-summary:1764000000', 'key'), $token);
	pg_assert_same(1, preg_match('/^[0-9a-f]{64}$/', $token));
	pg_assert_same('', pg_launch_summary_token(1764000000, ''), 'no key, no link');
	pg_assert_same('', pg_launch_summary_token(0, 'key'), 'no issue time, no link');
	pg_assert_false($token === pg_launch_summary_token(1764000001, 'key'), 'a new link has a new token');
}

function test_launch_summary_link_states()
{
	$now = 1764000000;
	$link = pg_launch_summary_link_new($now, 7, 'key');
	$token = pg_launch_summary_token($now, 'key');

	pg_assert_same(hash('sha256', $token), $link['hash'], 'only the hash is kept');
	pg_assert_same($now + 7 * 86400, $link['expires_at']);
	pg_assert_same($now + 30 * 86400, pg_launch_summary_link_new($now, 3, 'key')['expires_at'], 'a period not offered takes the first');

	pg_assert_same('open', pg_launch_summary_link_state($link, $token, $now + 60));
	pg_assert_same('expired', pg_launch_summary_link_state($link, $token, $now + 7 * 86400));
	pg_assert_same('invalid', pg_launch_summary_link_state($link, pg_launch_summary_token($now + 1, 'key'), $now), 'an older or other token');
	pg_assert_same('invalid', pg_launch_summary_link_state($link, 'nothex', $now));
	pg_assert_same('invalid', pg_launch_summary_link_state(pg_launch_summary_link_read(''), $token, $now), 'no link made');
	pg_assert_same('withdrawn', pg_launch_summary_link_state(array('withdrawn_at' => $now) + $link, $token, $now));
}

function test_launch_summary_row_keeps_journey()
{
	$row = json_encode(array('purpose' => 'agency', 'hidden' => array('team'), 'live_at' => 5));
	$link = pg_launch_summary_link_new(1764000000, 30, 'key');
	$changed = pg_launch_summary_link_change($row, $link);

	pg_assert_same($link, pg_launch_summary_link_read($changed));
	$journey = pg_launch_journey_stored_read($changed);
	pg_assert_same('agency', $journey['purpose'], 'the purpose stays');
	pg_assert_same(5, $journey['live_at']);

	// The journey's own writes keep the link.
	pg_assert_same($link, pg_launch_summary_link_read(pg_launch_journey_stored_change($changed, array('purpose' => 'shop'))));

	$bad = pg_launch_summary_link_read(json_encode(array('summary' => array('hash' => 'x', 'issued_at' => -4))));
	pg_assert_same('', $bad['hash'], 'a hash that is not one is dropped');
	pg_assert_same(0, $bad['issued_at']);
}

function test_launch_summary_cleaning()
{
	pg_assert_same('Logo as SVG', pg_launch_summary_line_clean("  Logo \n as   SVG "));
	pg_assert_same('', pg_launch_summary_line_clean(array('x')));
	pg_assert_same(500, mb_strlen(pg_launch_summary_line_clean(str_repeat('ş', 600)), 'UTF-8'));
	pg_assert_same("Line one\nLine two", pg_launch_summary_answer_clean("  Line one\r\nLine two \n"));
	pg_assert_same(5000, mb_strlen(pg_launch_summary_answer_clean(str_repeat('a', 6000)), 'UTF-8'));
}

function test_launch_summary_done_steps_only()
{
	$rows = array(
		array('name' => 'Domain and SSL', 'done' => true, 'line' => 'Certificate ends 2027-01-01'),
		array('name' => 'Mail', 'done' => false, 'line' => 'SPF found, DKIM missing'),
		array('name' => 'Backups', 'done' => true),
	);

	pg_assert_same(array('Domain and SSL', 'Backups'), pg_launch_summary_done_steps($rows), 'names only, never a line of state');
	pg_assert_same(array(), pg_launch_summary_done_steps(null));
}

function test_launch_summary_steps_done_then_open()
{
	$rows = array(
		array('key' => 'domain', 'name' => 'Domain and SSL', 'done' => false, 'level' => 'blocker', 'line' => 'Certificate missing'),
		array('key' => 'mail', 'name' => 'Mail', 'done' => true, 'line' => 'SPF found'),
		array('key' => 'seo', 'name' => 'Search engines', 'done' => false, 'line' => 'robots.txt closes the site'),
		array('key' => 'backups', 'name' => 'Backups', 'done' => true),
	);

	pg_assert_same(array(
		array('name' => 'Mail', 'done' => true),
		array('name' => 'Backups', 'done' => true),
		array('name' => 'Domain and SSL', 'done' => false),
		array('name' => 'Search engines', 'done' => false),
	), pg_launch_summary_steps($rows), 'done first, then open, each in the card\'s order; name and state only');
}

function test_launch_summary_steps_bad_input()
{
	pg_assert_same(array(), pg_launch_summary_steps(null));
	pg_assert_same(array(), pg_launch_summary_steps(array()));
	pg_assert_same(array(array('name' => 'Mail', 'done' => false)), pg_launch_summary_steps(array('junk', array('done' => true), array('name' => 'Mail'))), 'a row without a name is skipped');
}

function test_launch_summary_page_counts_and_waits()
{
	$page = file_get_contents(PG_FUNCTIONS_DIR . '/launch_summary.php');
	pg_assert_contains("pg_launch_summary_steps(", $page);
	pg_assert_contains("'{var:1} of {var:2} steps done'", $page);
	pg_assert_contains("lang('Waiting')", $page);
	pg_assert_same(
		preg_match_all('/\\$ls_card\\[\'rows\'\\]/', $page),
		preg_match_all('/pg_launch_summary_(?:done_)?steps\\(\\$ls_card\\[\'rows\'\\]\\)/', $page),
		'the card\'s rows reach the page only through the name-and-state functions'
	);
}

function test_launch_summary_page_is_sensitive()
{
	$waf = file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');
	pg_assert_contains("'launch_summary.php'", $waf, 'counted on the sensitive allowance');

	$page = file_get_contents(PG_FUNCTIONS_DIR . '/launch_summary.php');
	pg_assert_contains("X-Robots-Tag: noindex, nofollow", $page);
	pg_assert_contains("validate_token_field()", $page, 'an answer needs the form token');
	pg_assert_false(strpos($page, 'output_header(') !== false, 'no panel menu');
}
