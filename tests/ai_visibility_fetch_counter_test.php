<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the AI fetch counter (waf_ai_fetch_*() in waf.php): which
 * requests are counted under which bot name, the page a count is filed
 * under, the bucket key, that a refused request writes nothing, and that the
 * page cache keeps the page a stored copy stands for.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_cache.php');

// A user fetcher or search indexer whose address the ranges proved is
// counted under its catalogue name.
function test_ai_fetch_counter_verified_names()
{
	pg_assert_same('ChatGPT-User', waf_ai_fetch_bot('good', 'chatgpt-user', 'verified'));
	pg_assert_same('Claude-User', waf_ai_fetch_bot('good', 'claude-user', 'verified'));
	pg_assert_same('Perplexity-User', waf_ai_fetch_bot('good', 'perplexity-user', 'verified'));
	pg_assert_same('OAI-SearchBot', waf_ai_fetch_bot('good', 'oai-searchbot', 'verified'));
	pg_assert_same('Claude-SearchBot', waf_ai_fetch_bot('good', 'claude-searchbot', 'verified'));
}

// Every name fits the bot column.
function test_ai_fetch_counter_names_fit_the_column()
{
	foreach (waf_ai_bots() as $token => $bot) {
		pg_assert_true(strlen($bot['name']) <= 40, $token);
	}
}

// A forged claim (spoofed), an unprovable one (unknown) and one never asked
// (verification off) are counted as unverified.
function test_ai_fetch_counter_unverified_verdicts()
{
	pg_assert_same('unverified', waf_ai_fetch_bot('good', 'claude-user', 'spoofed'));
	pg_assert_same('unverified', waf_ai_fetch_bot('good', 'oai-searchbot', 'unknown'));
	pg_assert_same('unverified', waf_ai_fetch_bot('good', 'chatgpt-user', ''));
}

// A search engine crawler, a training crawler, a browser and anything else
// are not counted.
function test_ai_fetch_counter_other_classes()
{
	pg_assert_same('', waf_ai_fetch_bot('good', 'googlebot', 'verified'));
	pg_assert_same('', waf_ai_fetch_bot('bad', 'gptbot', ''));
	pg_assert_same('', waf_ai_fetch_bot('bad', 'claudebot', ''));
	pg_assert_same('', waf_ai_fetch_bot('human', '', ''));
	pg_assert_same('', waf_ai_fetch_bot('attack', 'sqlmap', ''));
	pg_assert_same('', waf_ai_fetch_bot('unverified', 'chatgpt-user', 'verified'));
}

// A verified fetch names its page; an unverified one never does, and a
// negative page is no page.
function test_ai_fetch_counter_page()
{
	pg_assert_same(12, waf_ai_fetch_page_for('Claude-User', 12));
	pg_assert_same(0, waf_ai_fetch_page_for('Claude-User', 0));
	pg_assert_same(0, waf_ai_fetch_page_for('Claude-User', -3));
	pg_assert_same(0, waf_ai_fetch_page_for('unverified', 12));
	pg_assert_same(0, waf_ai_fetch_page_for('', 12));
}

// One bucket per day, bot and page.
function test_ai_fetch_counter_key()
{
	pg_assert_same(sha1('2026-11-02|Claude-User|12'), waf_ai_fetch_key('2026-11-02', 'Claude-User', 12));
	pg_assert_true(waf_ai_fetch_key('2026-11-02', 'Claude-User', 12) !== waf_ai_fetch_key('2026-11-03', 'Claude-User', 12));
	pg_assert_true(waf_ai_fetch_key('2026-11-02', 'Claude-User', 12) !== waf_ai_fetch_key('2026-11-02', 'Claude-User', 13));
	pg_assert_same(40, strlen(waf_ai_fetch_key('2026-11-02', 'unverified', 0)));
}

// The request state: the first page named wins, a refusal cancels the count.
// Run last in this file: the state is per process and the shutdown write
// finds no bot once cancelled.
function test_ai_fetch_counter_state_cancel()
{
	waf_ai_fetch_page(7);
	waf_ai_fetch_page(9);
	pg_assert_same(7, waf_ai_fetch_state()['page']);

	waf_ai_fetch_state('bot', 'Claude-User');
	pg_assert_same('Claude-User', waf_ai_fetch_state()['bot']);

	waf_ai_fetch_state('cancel');
	pg_assert_same('', waf_ai_fetch_state()['bot']);
}

// A stored copy keeps the page it stands for; an older file reads as page 0.
function test_ai_fetch_counter_page_cache_meta()
{
	$back = pg_page_cache_unpack(pg_page_cache_pack(array('created' => 5, 'headers' => array(), 'page_id' => 42), '<p>x</p>'));
	pg_assert_same(42, $back['meta']['page_id']);

	$old = pg_page_cache_unpack('{"v":' . PG_PAGE_CACHE_FORMAT . ',"created":5,"headers":[]}' . "\n<p>x</p>");
	pg_assert_same(0, $old['meta']['page_id']);
}
