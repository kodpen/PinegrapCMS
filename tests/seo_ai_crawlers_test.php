<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what the site tells AI crawlers (includes/fn/ai_crawlers.php):
 * robots.txt with and without its AI section, the Content-Signal line, which
 * crawlers get a group, the crawler lists against the firewall's, and
 * llms.txt.
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

// Settings as the card saves them; the firewall switches on unless told.
function seo_ai_crawlers_test_settings($changes = array())
{
	return array_merge(pg_ai_crawler_settings_from_row(array(
		'robots_ai_rules'         => 1,
		'robots_ai_training'      => 0,
		'llms_txt'                => 0,
		'additional_llms_content' => '',
		'waf_allow_ai_fetchers'   => 1,
		'waf_allow_ai_search'     => 1,
	)), $changes);
}

// Without the section the file is what get_robots.txt.php wrote before it:
// the Sitemap line, the noindex rules in the operator's catch-all group or in
// a group of our own before it, then the operator's text.
function test_seo_ai_crawlers_robots_unchanged_without_the_section()
{
	$sitemap = 'https://example.com/sitemap.xml';
	$rules = array('Disallow: /a$', 'Disallow: /a?', 'Disallow: /a/');
	$off = seo_ai_crawlers_test_settings(array('rules' => false));

	pg_assert_same('Sitemap: ' . $sitemap, pg_robots_txt_content('', array(), $sitemap), 'nothing');
	pg_assert_same('Sitemap: ' . $sitemap . "\r\n\r\nUser-agent: Googlebot\nDisallow: /x", pg_robots_txt_content("User-agent: Googlebot\nDisallow: /x", array(), $sitemap), 'operator text only');

	pg_assert_same(
		"User-agent: *\r\nDisallow: /a$\r\nDisallow: /a?\r\nDisallow: /a/\r\n\r\nSitemap: " . $sitemap . "\r\n\r\nUser-agent: Googlebot",
		pg_robots_txt_content('User-agent: Googlebot', $rules, $sitemap),
		'own group before the operator text');

	pg_assert_same(
		"Sitemap: " . $sitemap . "\r\n\r\nUser-agent: *\r\nDisallow: /a$\r\nDisallow: /a?\r\nDisallow: /a/\r\nDisallow: /tmp",
		pg_robots_txt_content("User-agent: *\nDisallow: /tmp", $rules, $sitemap),
		'merged into the operator catch-all');

	foreach (array('', 'User-agent: Googlebot', "User-agent: *\nDisallow: /tmp") as $text) {
		pg_assert_same(pg_robots_txt_content($text, $rules, $sitemap), pg_robots_txt_content($text, $rules, $sitemap, $off), 'switch off: ' . $text);
		pg_assert_same(pg_robots_txt_content($text, $rules, $sitemap), pg_robots_txt_content($text, $rules, $sitemap, pg_ai_crawler_settings_from_row(array())), 'before the step: ' . $text);
	}
}

// Training off, assistants and AI search let in by the firewall: a group per
// training crawler at the top, the signal in a catch-all group of our own.
function test_seo_ai_crawlers_robots_training_refused()
{
	$content = pg_robots_txt_content('', array(), 'https://example.com/sitemap.xml', seo_ai_crawlers_test_settings());

	pg_assert_contains("User-agent: GPTBot\r\nDisallow: /\r\n\r\n", $content, 'GPTBot');
	pg_assert_contains("User-agent: Google-Extended\r\nDisallow: /\r\n\r\n", $content, 'Google-Extended');
	pg_assert_contains("User-agent: *\r\nContent-Signal: search=yes, ai-input=yes, ai-train=no\r\nAllow: /\r\n\r\nSitemap: https://example.com/sitemap.xml", $content, 'catch-all with the signal');
	pg_assert_contains('Directive (EU) 2019/790', $content, 'reservation with ai-train=no');
	pg_assert_false(strpos($content, 'ChatGPT-User') !== false, 'assistants follow the catch-all');
	pg_assert_false(strpos($content, 'OAI-SearchBot') !== false, 'AI search follows the catch-all');
	pg_assert_true(strpos($content, 'User-agent: Bytespider') < strpos($content, 'User-agent: *'), 'groups before the catch-all');
	pg_assert_same(0, strpos($content, '# Content-Signal'), 'explained at the top');
}

// Training allowed: no training group, ai-train=yes, no reservation.
function test_seo_ai_crawlers_robots_training_allowed()
{
	$content = pg_robots_txt_content('', array(), 'https://example.com/sitemap.xml', seo_ai_crawlers_test_settings(array('training' => true)));

	pg_assert_false(strpos($content, 'GPTBot') !== false, 'no training group');
	pg_assert_contains('Content-Signal: search=yes, ai-input=yes, ai-train=yes', $content, 'signal');
	pg_assert_false(strpos($content, '2019/790') !== false, 'no reservation');
}

// The firewall switches decide assistants and AI search.
function test_seo_ai_crawlers_robots_follow_the_firewall()
{
	$agents_off = pg_ai_robots_blocked_agents(seo_ai_crawlers_test_settings(array('training' => true, 'agents' => false)), '');
	pg_assert_same(array('ChatGPT-User', 'Claude-User', 'Perplexity-User'), $agents_off, 'assistants refused');

	$search_off = pg_ai_robots_blocked_agents(seo_ai_crawlers_test_settings(array('training' => true, 'search' => false)), '');
	pg_assert_same(array('OAI-SearchBot', 'Claude-SearchBot'), $search_off, 'AI search refused');

	pg_assert_same('Content-Signal: search=yes, ai-input=yes, ai-train=no', pg_ai_content_signal_line(seo_ai_crawlers_test_settings(array('agents' => false)), false), 'search alone keeps ai-input');
	pg_assert_same('Content-Signal: search=yes, ai-input=no, ai-train=no', pg_ai_content_signal_line(seo_ai_crawlers_test_settings(array('agents' => false, 'search' => false)), false), 'neither');
	pg_assert_same('Content-Signal: search=no, ai-input=no, ai-train=no', pg_ai_content_signal_line(seo_ai_crawlers_test_settings(array('training' => true)), true), 'closed site');
}

// The operator's text wins: a crawler it names keeps its own group, a
// Content-Signal it writes is the only one, and the signal joins its
// catch-all group after the User-agent lines.
function test_seo_ai_crawlers_robots_operator_text_wins()
{
	$settings = seo_ai_crawlers_test_settings();

	$named = pg_ai_robots_blocked_agents($settings, "user-agent: gptbot\nAllow: /\n\nUser-agent: CCBot # mine\nDisallow: /private");
	pg_assert_false(in_array('GPTBot', $named, true), 'GPTBot left to the operator');
	pg_assert_false(in_array('CCBot', $named, true), 'CCBot left to the operator');
	pg_assert_true(in_array('ClaudeBot', $named, true), 'the others stay');

	$own_signal = pg_robots_txt_content("User-agent: *\nContent-Signal: search=yes, ai-train=yes\nAllow: /", array(), 'https://example.com/sitemap.xml', $settings);
	pg_assert_same(1, substr_count(strtolower($own_signal), 'content-signal:'), 'one signal line');
	pg_assert_false(strpos($own_signal, '# Content-Signal') !== false, 'no explanation for theirs');
	pg_assert_contains('User-agent: GPTBot', $own_signal, 'groups still written');

	$merged = pg_robots_txt_content("User-agent: Bingbot\nUser-agent: *\nDisallow: /tmp", array('Disallow: /a$'), 'https://example.com/sitemap.xml', $settings);
	pg_assert_contains("\r\n\r\nUser-agent: Bingbot\r\nUser-agent: *\r\nContent-Signal: search=yes, ai-input=yes, ai-train=no\r\nDisallow: /a$\r\nDisallow: /tmp", $merged, 'into their catch-all');
	pg_assert_same(1, substr_count($merged, 'User-agent: *'), 'one catch-all group');
	pg_assert_same(0, preg_match('/^Allow: \/\r?$/m', $merged), 'no Allow line of ours in theirs');
}

// Only "Disallow: /" in the catch-all group closes the site.
function test_seo_ai_crawlers_catch_all_closed()
{
	pg_assert_true(pg_robots_catch_all_closed("User-agent: *\nDisallow: /"), 'block all');
	pg_assert_true(pg_robots_catch_all_closed("User-agent: Bingbot\n\n# note\nUser-agent: *\nDisallow: / # everything"), 'shared group, comment');
	pg_assert_false(pg_robots_catch_all_closed("User-agent: Googlebot\nDisallow: /"), 'one crawler');
	pg_assert_false(pg_robots_catch_all_closed("User-agent: *\nDisallow: /private"), 'one folder');
	pg_assert_false(pg_robots_catch_all_closed("User-agent: *\nDisallow: /tmp\nUser-agent: Googlebot\nDisallow: /"), 'a later group');
	pg_assert_false(pg_robots_catch_all_closed(''), 'empty');
}

// Before the step everything is off; the firewall switches read as on while
// their columns are missing, as the firewall card shows them.
function test_seo_ai_crawlers_settings_from_row()
{
	$before = pg_ai_crawler_settings_from_row(array('title' => 'x'));
	pg_assert_false($before['ready'], 'not ready');
	pg_assert_false($before['rules'], 'rules off');
	pg_assert_false($before['llms'], 'llms.txt off');
	pg_assert_true($before['agents'], 'assistants');
	pg_assert_true($before['search'], 'AI search');

	$after = pg_ai_crawler_settings_from_row(array('robots_ai_rules' => '1', 'robots_ai_training' => '0', 'llms_txt' => '1', 'additional_llms_content' => null, 'waf_allow_ai_fetchers' => '0', 'waf_allow_ai_search' => '1'));
	pg_assert_true($after['ready'] && $after['rules'] && $after['llms'], 'on');
	pg_assert_false($after['training'], 'training');
	pg_assert_false($after['agents'], 'assistants off');
	pg_assert_same('', $after['llms_text'], 'empty text');
}

// The assistants and AI search crawlers are the firewall's (waf_ai_bots()),
// split by the switch that lets them in; the training crawlers that crawl at
// all are the ones the firewall turns away as bad bots.
function test_seo_ai_crawlers_catalog_matches_the_firewall()
{
	$catalog = pg_ai_crawler_catalog();
	$by_setting = array('waf_allow_ai_fetchers' => array(), 'waf_allow_ai_search' => array());

	foreach (waf_ai_bots() as $meta) {
		$by_setting[$meta['setting']][] = $meta['name'];
	}

	pg_assert_same($by_setting['waf_allow_ai_fetchers'], $catalog['agent'], 'assistants');
	pg_assert_same($by_setting['waf_allow_ai_search'], $catalog['search'], 'AI search');

	foreach ($catalog['train'] as $agent) {
		if (substr($agent, -9) === '-Extended') {
			continue;
		}

		pg_assert_true(in_array(strtolower($agent), waf_bad_bots(), true), $agent . ' is a bad bot');
	}
}

// Home first, then views, then the newest change; one home page.
function test_seo_ai_crawlers_llms_rank_pages()
{
	$pages = array(
		array('id' => 1, 'home' => 'no',  'timestamp' => 100),
		array('id' => 2, 'home' => 'yes', 'timestamp' => 50),
		array('id' => 3, 'home' => 'no',  'timestamp' => 300),
		array('id' => 4, 'home' => 'no',  'timestamp' => 200),
		array('id' => 5, 'home' => 'yes', 'timestamp' => 400),
	);

	$ids = function ($rows) {
		$list = array();

		foreach ($rows as $row) {
			$list[] = $row['id'];
		}

		return $list;
	};

	pg_assert_same(array(2, 3, 4, 1), $ids(pg_llms_txt_rank_pages($pages, array(), 10)), 'no views');
	pg_assert_same(array(2, 1, 3, 4), $ids(pg_llms_txt_rank_pages($pages, array(1 => 9, 5 => 99), 10)), 'views first');
	pg_assert_same(array(2, 3), $ids(pg_llms_txt_rank_pages($pages, array(), 2)), 'limit');
}

// Name, description, the operator's text, the pages, the sitemap.
function test_seo_ai_crawlers_llms_content()
{
	$content = pg_llms_txt_content(
		array('name' => 'Acme <b>Ltd</b>', 'description' => "Tools &amp; parts\nsince 1990"),
		array(
			array('title' => 'Home', 'url' => 'https://example.com/', 'description' => ''),
			array('title' => 'Prices [2026]', 'url' => 'https://example.com/prices', 'description' => 'What things cost.'),
		),
		"Ask us before ordering.\r\n\r\n## Docs\n\n- [Guide](https://example.com/guide)",
		'https://example.com/sitemap.xml',
		'Pages');

	pg_assert_same(
		"# Acme Ltd\n\n> Tools & parts since 1990\n\nAsk us before ordering.\n\n## Docs\n\n- [Guide](https://example.com/guide)\n\n## Pages\n\n- [Home](https://example.com/)\n- [Prices \\[2026\\]](https://example.com/prices): What things cost.\n\n## Optional\n\n- [sitemap.xml](https://example.com/sitemap.xml)\n",
		$content);

	pg_assert_same(
		"# Acme\n\n## Optional\n\n- [sitemap.xml](https://example.com/sitemap.xml)\n",
		pg_llms_txt_content(array('name' => 'Acme', 'description' => ''), array(), '  ', 'https://example.com/sitemap.xml', 'Pages'),
		'nothing else to say');
}

// One line, at most the length asked, an ellipsis where it was cut.
function test_seo_ai_crawlers_llms_line()
{
	pg_assert_same('a b', pg_llms_txt_line("<p>a</p>\n\t b"));
	pg_assert_same('Çağ…', pg_llms_txt_line('Çağrı merkezi', 4));
	pg_assert_same('Çağrı', pg_llms_txt_line('Çağrı', 5));
}

// llms.txt is answered before a file or a page of that name, and only when
// the site publishes one; under a language directory it is sent to the root.
function test_seo_ai_crawlers_router_dispatch()
{
	$router = file_get_contents(PG_FUNCTIONS_DIR . '/router.php');

	$dispatch = strpos($router, "(\$item_name_lowercase == 'llms.txt') && router_llms_txt_enabled()");
	$files = strpos($router, "// Check if item is a file.");

	pg_assert_true(($dispatch !== false) && ($files !== false) && ($dispatch < $files), 'before the file check');
	pg_assert_contains("(\$lower == 'llms.txt')", $router, 'language directory');
}
