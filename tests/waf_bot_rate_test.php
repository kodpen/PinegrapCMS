<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for which bots skip the rate limits (waf_bot_rate_exempt() in
 * waf.php): only a crawler whose address proved its name. curl, an empty
 * user agent, a "bot" token and a good-bot name that could not be verified
 * count like a browser. IS_BOT is never defined here: the runner shares one
 * process, so the constant would leak into the other tests.
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

// A crawler proven by reverse DNS or by its published ranges is exempt.
function test_waf_bot_rate_exempt_verified_crawler()
{
	pg_assert_true(waf_bot_rate_exempt(true));
}

// Without the proof no bot is exempt, whatever its user agent says; with
// bot verification off waf_bot_verified() is never set, so this is the
// answer for every bot.
function test_waf_bot_rate_exempt_unverified_clients()
{
	pg_assert_false(waf_bot_rate_exempt(false));
	pg_assert_false(waf_bot_rate_exempt(null));
	pg_assert_false(waf_bot_rate_exempt(1));
	pg_assert_false(waf_bot_rate_exempt('verified'));
}

// Nothing in a request proves a crawler until waf_handle_bot() has verified
// it: curl, an empty user agent, a "bot" token or a forged Googlebot leave
// the mark unset, so the rate limits count them like a browser.
function test_waf_bot_rate_exempt_without_proof()
{
	pg_assert_false(waf_bot_verified());
	pg_assert_false(waf_bot_rate_exempt(waf_bot_verified()));
}
