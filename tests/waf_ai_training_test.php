<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the AI training crawlers and SEO's "Allow AI training" switch in
 * the firewall (waf_classify_bot(), waf_ai_training_bots() in waf.php): on,
 * a training crawler is taken off the built-in bad-bot match only and gets
 * no good-bot privilege; off, it is refused as before. IS_BOT is never
 * defined here: the runner shares one process.
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

// The user agents the crawlers send.
function waf_ai_training_test_agents()
{
	return array(
		'gptbot'             => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)',
		'claudebot'          => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)',
		'ccbot'              => 'CCBot/2.0 (https://commoncrawl.org/faq/)',
		'meta-externalagent' => 'meta-externalagent/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/crawler)',
		'amazonbot'          => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Amazonbot/0.1; +https://developer.amazon.com/support/amazonbot) Chrome/119.0.6045.214 Safari/537.36',
		'bytespider'         => 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)',
	);
}

// Runs $check with the config row $row primed, then puts the row back.
function waf_ai_training_test_with(array $row, $check)
{
	$original = waf_config(null, true);
	waf_config($row + array('waf_trusted_proxies' => ''), false, true);

	try {
		$check();
	} finally {
		waf_config(is_array($original) ? $original : array('waf_trusted_proxies' => ''), false, true);
		waf_config_reloaded(false);
	}
}

// The firewall's list is the catalog's training crawlers that crawl, by the
// same names; Google-Extended and Applebot-Extended crawl nothing.
function test_waf_ai_training_bots_follow_the_catalog()
{
	$catalog = pg_ai_crawler_catalog();
	$bad = waf_bad_bots();
	$expected = array();

	foreach ($catalog['train'] as $name) {
		if (in_array(strtolower($name), $bad, true)) {
			$expected[strtolower($name)] = $name;
		}
	}

	pg_assert_same($expected, waf_ai_training_bots());
	pg_assert_false(isset($expected['google-extended']), 'Google-Extended crawls nothing');
	pg_assert_same(array_keys($expected), array_keys(waf_ai_training_test_agents()), 'every crawler has a user agent here');
}

// Off (and before the column exists), every training crawler is a bad bot.
function test_waf_ai_training_off_refused()
{
	foreach (array(array('robots_ai_training' => '0'), array()) as $row) {
		waf_ai_training_test_with($row, function () {
			pg_assert_false(waf_ai_training_allowed(), 'switch off');

			foreach (waf_ai_training_test_agents() as $token => $agent) {
				$bot = waf_classify_bot($agent);
				pg_assert_same('bad', $bot['class'], $token);
			}
		});
	}
}

// On, GPTBot and the others leave the bad-bot match, as a class of their
// own that is not a good bot: no verification, so no rate or country
// exemption. meta-externalagent's "/crawler)" does not send it back to the
// residual signal.
function test_waf_ai_training_on_passes_the_bot_match()
{
	waf_ai_training_test_with(array('robots_ai_training' => '1'), function () {
		pg_assert_true(waf_ai_training_allowed(), 'switch on');

		foreach (waf_ai_training_test_agents() as $token => $agent) {
			$bot = waf_classify_bot($agent);
			pg_assert_same('training', $bot['class'], $token);
			pg_assert_same($token, $bot['token'], $token);
		}

		// Nothing here proved a crawler: the rate limits count it like a
		// browser.
		pg_assert_false(waf_bot_rate_exempt(waf_bot_verified()), 'meets the rate limit');
	});
}

// On, everything else stays: the other bad bots, an attack tool naming
// GPTBot, the operator's Blocked Bots and Allowed Bots. The user fetchers
// and AI search indexers are not training crawlers: their own switches
// decide them.
function test_waf_ai_training_on_keeps_the_other_rules()
{
	$agents = waf_ai_training_test_agents();

	waf_ai_training_test_with(array('robots_ai_training' => '1'), function () use ($agents) {
		pg_assert_same('bad', waf_classify_bot('Mozilla/5.0 (compatible; PerplexityBot/1.0; +https://perplexity.ai/perplexitybot)')['class'], 'PerplexityBot');
		pg_assert_same('bad', waf_classify_bot('Mozilla/5.0 (compatible; anthropic-ai)')['class'], 'anthropic-ai');
		pg_assert_same('bad', waf_classify_bot($agents['gptbot'] . ' AhrefsBot/7.0')['class'], 'a second bad name');
		pg_assert_same('attack', waf_classify_bot($agents['gptbot'] . ' sqlmap/1.7')['class'], 'an attack tool');
	});

	foreach (array_merge(array_keys(waf_ai_bots()), array('perplexitybot', 'anthropic-ai', 'claude-web')) as $token) {
		pg_assert_false(isset(waf_ai_training_bots()[$token]), $token . ' is not a training crawler here');
	}

	waf_ai_training_test_with(array('robots_ai_training' => '1', 'waf_blocked_agents' => 'gptbot'), function () use ($agents) {
		pg_assert_same('bad', waf_classify_bot($agents['gptbot'])['class'], 'Blocked Bots');
		pg_assert_same('training', waf_classify_bot($agents['ccbot'])['class'], 'another crawler');
	});

	waf_ai_training_test_with(array('robots_ai_training' => '0', 'allowed_bots' => 'gptbot'), function () use ($agents) {
		pg_assert_same('good', waf_classify_bot($agents['gptbot'])['class'], 'Allowed Bots as before');
	});
}

// The bot policy lets the class through without the good-bot branch, so it
// is never verified; IS_BOT keeps it out of the visitor counts.
function test_waf_ai_training_handled_as_a_visitor()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');
	$start = strpos($source, 'function waf_handle_bot(');
	$body = substr($source, $start, strpos($source, "\nfunction ", $start + 1) - $start);
	$branch = strpos($body, "\$bot['class'] === 'training'");

	pg_assert_true($branch !== false, 'a branch for the class');
	pg_assert_true($branch < strpos($body, 'waf_verify_bot('), 'before any verification');
	pg_assert_true($branch < strpos($body, 'waf_bot_verified(true)'), 'never marked verified');
}
