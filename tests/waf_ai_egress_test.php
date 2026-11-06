<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the AI fetcher range verdict in waf.php: Claude-User is proven
 * by Anthropic's published crawler list or by its documented API egress
 * ranges (waf_ai_fixed_ranges()), which the fetch tool of claude.ai and the
 * API uses. The fixed ranges never age.
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

function waf_ai_egress_test_list($fetched_at)
{
	return array('fetched_at' => $fetched_at, 'prefixes' => array('216.73.216.0/22'));
}

// Only Claude-User has fixed ranges; the crawlers come from the list.
function test_waf_ai_fixed_ranges_by_token()
{
	pg_assert_true(in_array('160.79.104.0/21', waf_ai_fixed_ranges('claude-user'), true));
	pg_assert_true(in_array('153.61.208.0/20', waf_ai_fixed_ranges('claude-user'), true));
	pg_assert_same(array(), waf_ai_fixed_ranges('claude-searchbot'));
	pg_assert_same(array(), waf_ai_fixed_ranges('chatgpt-user'));
}

// An API egress address, a list address and an outsider, with a fresh list.
function test_waf_ranges_verdict_claude_user()
{
	$now = 1800000000;
	$fixed = waf_ai_fixed_ranges('claude-user');
	$list = waf_ai_egress_test_list($now - 3600);

	pg_assert_same('verified', waf_ranges_verdict('160.79.106.135', $list, $fixed, $now));
	pg_assert_same('verified', waf_ranges_verdict('153.61.210.7', $list, $fixed, $now));
	pg_assert_same('verified', waf_ranges_verdict('216.73.216.10', $list, $fixed, $now));
	pg_assert_same('spoofed', waf_ranges_verdict('8.8.8.8', $list, $fixed, $now));
}

// The fixed ranges prove the address with a stale list or none at all; a
// miss then stays unknown.
function test_waf_ranges_verdict_without_a_fresh_list()
{
	$now = 1800000000;
	$fixed = waf_ai_fixed_ranges('claude-user');
	$stale = waf_ai_egress_test_list($now - 40 * 86400);

	pg_assert_same('verified', waf_ranges_verdict('160.79.106.135', false, $fixed, $now));
	pg_assert_same('verified', waf_ranges_verdict('160.79.106.135', $stale, $fixed, $now));
	pg_assert_same('unknown', waf_ranges_verdict('8.8.8.8', false, $fixed, $now));
	pg_assert_same('unknown', waf_ranges_verdict('8.8.8.8', $stale, $fixed, $now));
	pg_assert_same('unknown', waf_ranges_verdict('not an ip', $stale, $fixed, $now));
}

// Claude-SearchBot from an API egress address is still a forgery.
function test_waf_ranges_verdict_searchbot_not_from_api_egress()
{
	$now = 1800000000;
	$list = waf_ai_egress_test_list($now - 3600);

	pg_assert_same('spoofed', waf_ranges_verdict('160.79.106.135', $list, waf_ai_fixed_ranges('claude-searchbot'), $now));
}
