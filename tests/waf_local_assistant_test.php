<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for waf_bot_claim_unverifiable() in waf.php: Claude Code on a
 * person's own computer sends "Claude-User (claude-code/...)" from that
 * person's address, which no published range can hold, so the claim is never
 * tested against the ranges and never read as forged.
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

// The command-line assistant's user agent, any version and any case.
function test_waf_bot_claim_unverifiable_claude_code()
{
	pg_assert_true(waf_bot_claim_unverifiable('claude-user', 'Claude-User (claude-code/2.1.309; +https://support.anthropic.com/)'));
	pg_assert_true(waf_bot_claim_unverifiable('claude-user', 'claude-user (CLAUDE-CODE/1.0)'));
}

// The fetcher Anthropic runs from its own addresses is still verified.
function test_waf_bot_claim_unverifiable_hosted_fetcher()
{
	pg_assert_false(waf_bot_claim_unverifiable('claude-user', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Claude-User/1.0; +Claude-User@anthropic.com)'));
	pg_assert_false(waf_bot_claim_unverifiable('claude-user', ''));
}

// Only the Claude-User name is spared: another bot naming claude-code is
// verified as before.
function test_waf_bot_claim_unverifiable_other_tokens()
{
	pg_assert_false(waf_bot_claim_unverifiable('claude-searchbot', 'Claude-SearchBot (claude-code/2.1.309)'));
	pg_assert_false(waf_bot_claim_unverifiable('chatgpt-user', 'ChatGPT-User (claude-code/2.1.309)'));
	pg_assert_false(waf_bot_claim_unverifiable('googlebot', 'Googlebot claude-code/1.0'));
}
