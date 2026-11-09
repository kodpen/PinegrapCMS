<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Charity design template's standard pages
 * (includes/design_templates/pinegrap-charity/standard.php): the file
 * carries contact, newsletter, News, post, inbox, conversation, help
 * center, error and the message and welcome e-mails, each widget on one of
 * them (the newsletter band on every page); the contact form opens its
 * conversation, seen only by its sender and the staff; the newsletter fills
 * its contact group; every page link names a page of the finished template
 * and none needs the shop; the classes are the template's own; every
 * literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');
// The page keys of the finished template, the shop's pages and the node walk.
require_once(__DIR__ . '/design_template_charity_test.php');

// The standard part as the template file reads it: after parts.php,
// mail.php and forms.php in one scope. Read once per run.
function _test_charity_standard_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-charity';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	include $dir . '/forms.php';
	$part = include $dir . '/standard.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages);
	return $out;
}

function test_charity_standard_pages_and_widgets()
{
	$d = _test_charity_standard_load();
	pg_assert_same(array('contact', 'newsletter', 'blog', 'blog_post', 'inbox', 'conversation', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome'), array_keys($d['pages']));
	$known = array_flip(_test_charity_page_keys());
	foreach ($d['part']['widgets'] as $name => $w) {
		pg_assert_true($w['page'] === '' || isset($known[$w['page']]), "widget $name sits on a template page");
	}
	pg_assert_same('', $d['part']['widgets']['newsletter_band']['page'], 'the band is drawn on every page');
	pg_assert_same('home', $d['part']['widgets']['home_posts']['page']);
	pg_assert_same(3, $d['part']['widgets']['home_posts']['config']['max_results']);
}

function test_charity_standard_contact_and_newsletter_forms()
{
	$d = _test_charity_standard_load();
	$form = $d['pages']['contact']['form'];
	pg_assert_same('{{tab:conversation}}', $form['confirmation_page_id']);
	pg_assert_same('{{tab:email_new_message}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_message_received}}', $form['confirm_page_id']);
	pg_assert_same(1, $form['auto_registration']);
	pg_assert_same('submitter_only', $d['part']['widgets']['conversation']['config']['access_control']);
	pg_assert_same('{{tab:contact}}', $d['part']['widgets']['inbox_list']['config']['custom_form_page_id']);
	pg_assert_contains('{{page:privacy}}', json_encode($d['part']['widgets']['contact_form']['tree']), 'the consent links the privacy notice');

	$news = $d['pages']['newsletter']['form'];
	pg_assert_same('{{contact_group:newsletter}}', $news['contact_group_id']);
	pg_assert_same('{{tab:email_welcome}}', $news['confirm_page_id']);
	pg_assert_same('{{tab:newsletter}}', $d['part']['widgets']['newsletter_band']['config']['form_id'], 'the band fills the same list');
}

function test_charity_standard_links_classes_and_words()
{
	$d = _test_charity_standard_load();
	$all = json_encode($d['part']);
	$known = array_flip(_test_charity_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $all, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_charity_shop_keys() as $key) pg_assert_false(strpos($all, '{{page:' . $key . '}}') !== false, "standard pages do not link to $key");

	$nodes = array();
	foreach ($d['part']['pages'] as $p) _test_charity_nodes($p['tree'], $nodes);
	foreach ($d['part']['widgets'] as $w) if (is_array($w['tree'])) _test_charity_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|pmk|pc|pl|plx|plw|psl)-/', $c), "class $c is not another template's");
		}
	}
	$json = json_encode($d['part']['pages'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');

	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-charity/standard.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (preg_match('/\bcampaigns?\b/i', $key) && isset($map[$key])) {
			pg_assert_true(mb_stripos($map[$key], 'kampanya', 0, 'UTF-8') !== false, "'$key' says kampanya");
		}
		if (preg_match('/\b(donate|gifts?)\b/i', $key) && isset($map[$key])) {
			pg_assert_true(mb_stripos($map[$key], 'bağış', 0, 'UTF-8') !== false, "'$key' says bağış");
		}
	}
}
