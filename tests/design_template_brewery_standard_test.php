<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the standard pages of the Craft Brewery design template
 * (includes/design_templates/pinegrap-brewery/standard.php): contact, the
 * newsletter, News and its post page, inbox and conversation, help, the
 * error page and the message e-mails load with their keys, folders and
 * root trees; every page link and placed widget names a page or widget of
 * the finished template and none a shop page; the contact form opens a
 * conversation and names its e-mail pages; the newsletter fills the
 * newsletter group; the threads run on the Comments widget; the lists read
 * the staff page's post form; the home's news is defined here; ids carry
 * bw-, classes are the template's, no fixed date; every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template and the shop's pages.
require_once(__DIR__ . '/design_template_brewery_test.php');

// parts.php, mail.php and the standard part in one scope, as the template
// file reads them. Read once per run.
function _test_bwst_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-brewery';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$out = include $dir . '/standard.php';
	return $out;
}

// Every node of a tree, depth first.
function _test_bwst_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_bwst_nodes($c, $out);
}

function _test_bwst_pages()
{
	$pages = array();
	foreach (_test_bwst_load()['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

function test_brewery_standard_pages_and_folders()
{
	$folders = array('contact' => 'public', 'newsletter' => 'public', 'blog' => 'public', 'blog_post' => 'public',
		'inbox' => 'registration', 'conversation' => 'registration', 'search' => 'public', 'error' => 'public',
		'email_new_message' => 'private', 'email_message_received' => 'private', 'email_welcome' => 'private');
	$pages = _test_bwst_pages();
	pg_assert_same(array_keys($folders), array_keys($pages), 'the pages');
	foreach ($pages as $key => $p) {
		pg_assert_true(isset($p['name'], $p['title'], $p['tree']), "$key: name, title, tree");
		pg_assert_same($folders[$key], $p['folder'], "$key: folder");
		pg_assert_same('root', $p['tree']['type'], "$key: root tree");
		pg_assert_false(isset($p['requires']), "$key: stands without the shop");
		if ($p['folder'] === 'public' && $key !== 'error') pg_assert_true(isset($p['meta_description']), "$key: description");
		if ($p['folder'] === 'private') pg_assert_same(true, $p['noindex'], "$key: noindex");
	}
	pg_assert_same(true, $pages['error']['noindex'], 'error: noindex');
}

// Every {{page:}} / {{tab:}} names a page of the finished template and none
// a shop page; every widget a page places is defined here.
function test_brewery_standard_links_and_widgets()
{
	$d = _test_bwst_load();
	$known = array_flip(_test_bw_page_keys());
	foreach (array_keys(_test_bwst_pages()) as $key) pg_assert_true(isset($known[$key]), "$key is a template page key");
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($d), $m);
	pg_assert_true(count($m[1]) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "{{page:$key}} names a template page");
	foreach (_test_bw_shop_keys() as $key) {
		pg_assert_false(in_array($key, $m[1], true), "no link to the shop page $key");
	}

	foreach ($d['pages'] as $p) {
		$nodes = array();
		_test_bwst_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (!isset($n['props']['templateWidget'])) continue;
			$w = $n['props']['templateWidget'];
			pg_assert_true(isset($d['widgets'][$w]), $p['key'] . ": places a known widget $w");
		}
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true(isset($known[$w['page']]), "widget $key: page {$w['page']}");
		pg_assert_true(isset($w['config']['regionType'], $w['tree']), "widget $key: config and tree");
	}
	// The home page places its news and leaves the widget to this part.
	pg_assert_same('home', $d['widgets']['home_posts']['page'], 'home_posts is the home\'s');
}

function test_brewery_standard_contact_newsletter_and_threads()
{
	$w = _test_bwst_load()['widgets'];
	$pages = _test_bwst_pages();

	// The contact form opens a conversation and names its e-mail pages.
	$f = $pages['contact']['form'];
	pg_assert_same('{{tab:conversation}}', $f['confirmation_page_id']);
	pg_assert_same(1, $f['auto_registration']);
	pg_assert_same('{{tab:email_new_message}}', $f['notify_page_id']);
	pg_assert_same('{{tab:email_message_received}}', $f['confirm_page_id']);
	pg_assert_same('{{site_email}}', $f['notify_email']);
	$contact = json_encode($w['contact_form']['tree']);
	foreach (array('first_name', 'last_name', 'email', 'phone', 'topic', 'message') as $name) {
		pg_assert_contains('"name","value":"' . $name . '"', $contact, "contact field $name");
	}
	pg_assert_contains('"section":"captcha"', $contact, 'contact: CAPTCHA');
	// The contact card names the brewery's address, phone, e-mail and tours
	// address.
	$card = json_encode($pages['contact']['tree'], JSON_UNESCAPED_UNICODE);
	pg_assert_contains('tel:+902125550199', $card, 'contact card: the phone');
	pg_assert_contains('Harbour Street 12', $card, 'contact card: the address');
	foreach (array('hello@example.com', 'tours@example.com') as $address) {
		pg_assert_contains('mailto:' . $address, $card, "contact card: $address");
	}

	// The newsletter page owns the form and fills the newsletter group.
	$n = $pages['newsletter']['form'];
	pg_assert_same('{{contact_group:newsletter}}', $n['contact_group_id']);
	pg_assert_same('{{tab:email_welcome}}', $n['confirm_page_id']);
	pg_assert_same('page', $w['newsletter_form']['config']['form_source']);
	pg_assert_contains('"name","value":"consent"', json_encode($w['newsletter_form']['tree']), 'newsletter: consent');

	// The lists and the post read the staff page's post form.
	foreach (array('home_posts', 'blog_list', 'blog_post') as $key) {
		pg_assert_same('{{tab:blog_new}}', $w[$key]['config']['custom_form_page_id'], "$key: post form");
	}
	pg_assert_same(3, $w['home_posts']['config']['max_results']);
	pg_assert_same('{{tab:blog_post}}', $w['blog_list']['config']['detail_page_id']);
	pg_assert_same(array('title', 'summary', 'content'), $w['blog_list']['config']['search_fields']);

	// The threads: the page's comment settings, its own Comments widget, the
	// reply e-mail of the tours part.
	foreach (array('blog_post' => 'post_comments', 'conversation' => 'conversation_replies') as $page => $widget) {
		pg_assert_same('{{tab:email_reply}}', $pages[$page]['comments']['email_page'], "$page: reply e-mail");
		pg_assert_contains('"templateWidget":"' . $widget . '"', json_encode($pages[$page]['tree']), "$page places $widget");
		pg_assert_same('comments', $w[$widget]['config']['regionType']);
		pg_assert_same($page, $w[$widget]['page']);
	}
	pg_assert_same('submitter_only', $w['conversation']['config']['access_control']);
	pg_assert_same('{{tab:contact}}', $w['inbox_list']['config']['custom_form_page_id']);
	pg_assert_same(true, $w['inbox_list']['config']['viewer_filter']);
	pg_assert_same('all', $w['site_search']['config']['search_scope']);
	pg_assert_same('starter', $w['error_page']['tree']);

	// Help's questions are search sections of their own.
	$help = json_encode($pages['search']['tree']);
	for ($i = 1; $i <= 5; $i++) pg_assert_contains('"id":"bw-help-faq-' . $i . '-item","searchSection":true', $help, "help question $i");
}

// Ids are unique per page and carry bw- (the comment form's own ids
// aside); classes are Bootstrap's or the template's, no faded text; no
// year or calendar date is written into a page.
function test_brewery_standard_ids_classes_and_no_fixed_dates()
{
	$d = _test_bwst_load();
	foreach ($d['pages'] as $p) {
		$nodes = array();
		_test_bwst_nodes($p['tree'], $nodes);
		foreach ($d['widgets'] as $w) if ($w['page'] === $p['key'] && is_array($w['tree'])) _test_bwst_nodes($w['tree'], $nodes);
		$seen = array();
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psp|pmz|pai)-/', $c), "class $c is not another template's");
				pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_false(isset($seen[$id]), $p['key'] . ": id $id is unique");
			if (strpos($id, 'comment_') !== 0) pg_assert_same(0, strpos($id, 'bw-'), $p['key'] . ": id $id is the template's");
			$seen[$id] = true;
		}
	}
	$text = json_encode(array($d['pages'], $d['widgets']), JSON_UNESCAPED_UNICODE);
	pg_assert_false((bool)preg_match('/\b20[2-3]\d\b/', $text), 'no year');
	pg_assert_false((bool)preg_match('/\b\d{4}-\d{2}-\d{2}\b/', $text), 'no ISO date');
}

// Every literal has its Turkish; the taproom reads "tadım salonu", a beer
// "bira".
function test_brewery_standard_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-brewery/standard.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		if (preg_match('/\b[Tt]aproom\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'tadım salonu', 0, 'UTF-8') !== false, "'$key' says tadım salonu");
		if (preg_match('/\b[Bb]eers?\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'bira', 0, 'UTF-8') !== false, "'$key' says bira");
	}
}
