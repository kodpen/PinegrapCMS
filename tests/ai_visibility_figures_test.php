<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the AI visibility figures (includes/fn/ai_visibility.php): the
 * bots and what the site tells them, the totals per bot, the top pages per
 * bot, the pages no bot fetched, the AI assistant referrer hosts and their
 * visits added up, and the retention day.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/ai_crawlers.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/ai_visibility.php');

// The bots are the firewall's AI fetchers and indexers, plus unverified.
function test_ai_visibility_bots_match_the_firewall()
{
	$bots = pg_ai_fetch_bots();

	foreach (waf_ai_bots() as $token => $bot) {
		pg_assert_true(isset($bots[$bot['name']]), $bot['name']);
		pg_assert_same(($bot['setting'] === 'waf_allow_ai_fetchers') ? 'agent' : 'search', $bots[$bot['name']], $bot['name']);
	}

	pg_assert_same('unverified', $bots['unverified']);
	pg_assert_same(count(waf_ai_bots()) + 1, count($bots));
}

// The firewall switches decide per kind; unverified is never let in.
function test_ai_visibility_policy()
{
	$policy = pg_ai_bot_policy(array('agents' => true, 'search' => false, 'rules' => true, 'llms' => false));

	pg_assert_true($policy['Claude-User']['firewall_allowed']);
	pg_assert_false($policy['OAI-SearchBot']['firewall_allowed']);
	pg_assert_false($policy['unverified']['firewall_allowed']);
	pg_assert_true($policy['ChatGPT-User']['robots_ai_rules']);
	pg_assert_false($policy['ChatGPT-User']['llms_txt']);
}

// Every bot is listed, most fetches first, then by name; a name the
// catalogue no longer has is kept.
function test_ai_visibility_bot_totals()
{
	$policy = pg_ai_bot_policy(array('agents' => true, 'search' => true));
	$rows = array(
		array('day' => '2026-11-01', 'bot' => 'Claude-User', 'fetches' => 3),
		array('day' => '2026-11-02', 'bot' => 'Claude-User', 'fetches' => 4),
		array('day' => '2026-11-02', 'bot' => 'unverified', 'fetches' => 9),
		array('day' => '2026-11-02', 'bot' => 'Old-Bot', 'fetches' => 1),
	);

	$totals = pg_ai_fetch_bot_totals($rows, $policy);

	pg_assert_same('unverified', $totals[0]['bot']);
	pg_assert_same(9, $totals[0]['fetches']);
	pg_assert_same('Claude-User', $totals[1]['bot']);
	pg_assert_same(7, $totals[1]['fetches']);
	pg_assert_same('Old-Bot', $totals[2]['bot']);
	pg_assert_same(count($policy) + 1, count($totals));
	pg_assert_same(0, $totals[3]['fetches']);
}

// At most $limit pages per bot, most fetches first, ties by page id.
function test_ai_visibility_top_pages_cut()
{
	$rows = array(
		array('bot' => 'Claude-User', 'page_id' => 5, 'fetches' => 2),
		array('bot' => 'ChatGPT-User', 'page_id' => 1, 'fetches' => 1),
		array('bot' => 'Claude-User', 'page_id' => 3, 'fetches' => 9),
		array('bot' => 'Claude-User', 'page_id' => 4, 'fetches' => 2),
		array('bot' => 'ChatGPT-User', 'page_id' => 2, 'fetches' => 6),
	);

	$cut = pg_ai_top_pages_cut($rows, 2);

	pg_assert_same(4, count($cut));
	pg_assert_same(array('ChatGPT-User', 2), array($cut[0]['bot'], $cut[0]['page_id']));
	pg_assert_same(array('ChatGPT-User', 1), array($cut[1]['bot'], $cut[1]['page_id']));
	pg_assert_same(array('Claude-User', 3), array($cut[2]['bot'], $cut[2]['page_id']));
	pg_assert_same(array('Claude-User', 4), array($cut[3]['bot'], $cut[3]['page_id']));
}

// The pages not fetched, counted whole and listed up to the limit.
function test_ai_visibility_unfetched_cut()
{
	$pages = array(
		array('page_id' => 1, 'page_name' => 'about'),
		array('page_id' => 2, 'page_name' => 'blog'),
		array('page_id' => 3, 'page_name' => 'contact'),
		array('page_id' => 4, 'page_name' => 'home'),
	);

	$left = pg_ai_unfetched_cut($pages, array('2', 4), 1);

	pg_assert_same(2, $left['total']);
	pg_assert_same(array(array('page_id' => 1, 'page_name' => 'about')), $left['pages']);
}

// The unfetched list keeps only what sitemap.xml lists: a standard page
// with a system widget it leaves out is dropped, and of the pages marked as
// home only the newest one (the root address) stays.
function test_ai_visibility_sitemap_listed_pages()
{
	$rows = array(
		array('page_id' => 1, 'page_name' => 'home-new', 'page_type' => 'standard', 'page_home' => 'yes', 'page_timestamp' => 300),
		array('page_id' => 2, 'page_name' => 'home-old', 'page_type' => 'standard', 'page_home' => 'yes', 'page_timestamp' => 100),
		array('page_id' => 3, 'page_name' => 'login', 'page_type' => 'standard', 'page_home' => 'no', 'page_timestamp' => 200),
		array('page_id' => 4, 'page_name' => 'about', 'page_type' => 'standard', 'page_home' => 'no', 'page_timestamp' => 50),
		array('page_id' => 5, 'page_name' => 'shop', 'page_type' => 'catalog', 'page_home' => 'no', 'page_timestamp' => 60),
	);

	$listed = pg_ai_sitemap_listed_pages($rows, array(3 => true, 5 => true));

	pg_assert_same(array(1, 4, 5), array_map(function ($row) {
		return (int) $row['page_id'];
	}, $listed));
}

// A referrer host is an assistant's with or without www., in any case.
function test_ai_visibility_assistant_hosts()
{
	pg_assert_same('ChatGPT', pg_visitor_ai_assistant('chatgpt.com'));
	pg_assert_same('ChatGPT', pg_visitor_ai_assistant('CHAT.OPENAI.COM'));
	pg_assert_same('Perplexity', pg_visitor_ai_assistant('www.perplexity.ai'));
	pg_assert_same('Claude', pg_visitor_ai_assistant('claude.ai.'));
	pg_assert_same('', pg_visitor_ai_assistant('google.com'));
	pg_assert_same('', pg_visitor_ai_assistant('notclaude.ai'));
	pg_assert_same('', pg_visitor_ai_assistant(''));

	$list = pg_visitor_ai_host_list();
	pg_assert_true(in_array('www.chatgpt.com', $list, true));
	pg_assert_same(2 * count(pg_visitor_ai_hosts()), count($list));
}

// Host rows added up by assistant; other hosts are left out.
function test_ai_visibility_referrals_fold()
{
	$folded = pg_ai_referrals_fold(array(
		array('host' => 'chatgpt.com', 'visits' => 3, 'page_views' => 7, 'forms_submitted' => 1, 'orders_completed' => 0, 'order_total' => 0),
		array('host' => 'chat.openai.com', 'visits' => 2, 'page_views' => 2, 'forms_submitted' => 0, 'orders_completed' => 1, 'order_total' => '1500'),
		array('host' => 'www.perplexity.ai', 'visits' => 4, 'page_views' => 4, 'forms_submitted' => 0, 'orders_completed' => 0, 'order_total' => 0),
		array('host' => 'example.com', 'visits' => 50, 'page_views' => 50, 'forms_submitted' => 0, 'orders_completed' => 0, 'order_total' => 0),
	));

	pg_assert_same(9, $folded['totals']['visits']);
	pg_assert_same(1500, $folded['totals']['order_total']);
	pg_assert_same(2, count($folded['assistants']));
	pg_assert_same('ChatGPT', $folded['assistants'][0]['assistant']);
	pg_assert_same(5, $folded['assistants'][0]['visits']);
	pg_assert_same(1, $folded['assistants'][0]['orders_completed']);
	pg_assert_same('Perplexity', $folded['assistants'][1]['assistant']);
}

// Four hundred days are kept, counted by the calendar.
function test_ai_visibility_keep_from()
{
	$now = strtotime('2026-11-02 12:00:00');

	pg_assert_same(date('Y-m-d', strtotime('-400 days', $now)), pg_ai_fetch_keep_from($now));
	pg_assert_same('2025-09-28', pg_ai_fetch_keep_from($now));
}

// A top page whose page row is gone is labelled as deleted, without a link;
// the home page (an existing page with an empty name) is still the home page.
function test_ai_visibility_deleted_page_is_not_the_home_page()
{
	$rows = pg_visitor_label_content_rows(array(
		array('page_id' => 7, 'page_name' => '', 'item_type' => '', 'item_id' => 0, 'views' => 3, 'deleted' => true),
		array('page_id' => 1, 'page_name' => '', 'item_type' => '', 'item_id' => 0, 'views' => 5),
		array('page_id' => 2, 'page_name' => 'about', 'item_type' => '', 'item_id' => 0, 'views' => 2),
	));

	pg_assert_same(lang('Deleted page'), $rows[0]['label']);
	pg_assert_same('', $rows[0]['url']);
	pg_assert_same(lang('Homepage'), $rows[1]['label']);
	pg_assert_true($rows[1]['url'] !== '', 'the home page keeps its link');
	pg_assert_same('about', $rows[2]['label']);
	pg_assert_true(substr($rows[2]['url'], -6) === '/about', 'a page links to itself');
}
