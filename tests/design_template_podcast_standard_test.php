<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Podcast Network design template's standard pages
 * (includes/design_templates/pinegrap-podcast/standard.php): the pages and
 * widgets with their keys and folders, the contact form opening a
 * conversation with its e-mails, the newsletter in its contact group, the
 * blog list and post with comments, the inbox for its sender, the help
 * search, the e-mails' tokens, every page link naming a page of the
 * finished template and none a shop page, the template's own ids and
 * classes, no fixed date and every literal with its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template, the shop's pages and the node
// walk.
require_once(__DIR__ . '/design_template_podcast_test.php');

// parts.php, mail.php and standard.php in one scope, as the template file
// reads them. Read once per run.
function _test_ppdt_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$part = include $dir . '/standard.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'network' => $network);
	return $out;
}

// The page keys a tree links ({{page:…}}) and names in settings ({{tab:…}}).
function _test_ppdt_links($tree)
{
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($tree), $m);
	return array_values(array_unique($m[1]));
}

function test_podcast_standard_pages_and_widgets()
{
	$d = _test_ppdt_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	$folders = array('contact' => 'public', 'newsletter' => 'public', 'blog' => 'public', 'blog_post' => 'public', 'inbox' => 'registration',
		'conversation' => 'registration', 'search' => 'public', 'error' => 'public', 'email_new_message' => 'private',
		'email_message_received' => 'private', 'email_welcome' => 'private');
	pg_assert_same(array_keys($folders), array_keys($d['pages']));
	pg_assert_same(array('newsletter_form', 'contact_form', 'home_posts', 'blog_list', 'blog_post', 'post_comments', 'inbox_list', 'conversation',
		'conversation_replies', 'site_search', 'error_page', 'email_message_admin', 'email_message_sender'), array_keys($d['widgets']));
	$known = array_flip(_test_ppd_page_keys());
	$names = array();
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a page of the finished template");
		pg_assert_same($folders[$key], $p['folder'], "$key: its folder");
		foreach (array('name', 'title', 'tree') as $f) pg_assert_true(!empty($p[$f]), "$key has $f");
		pg_assert_false(isset($names[$p['name']]), "$key: its address is its own");
		$names[$p['name']] = true;
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_false(isset($w['requires']), "$key stands without the shop");
		pg_assert_true(isset($d['pages'][$w['page']]) || $w['page'] === 'home', "$key sits on a page");
	}
}

// The contact form opens a conversation, tells the staff and answers the
// sender; the newsletter fills its contact group and sends the welcome.
function test_podcast_standard_forms()
{
	$d = _test_ppdt_load();
	$c = $d['pages']['contact']['form'];
	pg_assert_same('{{tab:conversation}}', $c['confirmation_page_id'], 'a message opens its conversation');
	pg_assert_same(1, $c['auto_registration'], 'a sender without an account gets one');
	pg_assert_same('{{site_email}}', $c['notify_email'], 'the staff are told');
	pg_assert_same('{{tab:email_new_message}}', $c['notify_page_id'], 'the staff e-mail');
	pg_assert_same('{{tab:email_message_received}}', $c['confirm_page_id'], 'the sender\'s e-mail');
	$n = $d['pages']['newsletter']['form'];
	pg_assert_same('{{contact_group:newsletter}}', $n['contact_group_id'], 'the newsletter\'s contact group');
	pg_assert_same('{{tab:email_welcome}}', $n['confirm_page_id'], 'the welcome e-mail');
	$json = json_encode($d['widgets']['contact_form']['tree']);
	foreach (array('first_name', 'email', 'topic', 'message') as $name) pg_assert_contains('"value":"' . $name . '"', $json, "the contact form asks $name");
	pg_assert_contains('"value":"email"', json_encode($d['widgets']['newsletter_form']['tree']), 'the newsletter asks the address');
	pg_assert_contains('"value":"consent"', json_encode($d['widgets']['newsletter_form']['tree']), 'and the consent');
}

// The blog's list and post, the inbox and its conversation.
function test_podcast_standard_lists_and_records()
{
	$d = _test_ppdt_load();
	$w = $d['widgets'];
	foreach (array('home_posts', 'blog_list') as $key) {
		pg_assert_same('{{tab:blog_new}}', $w[$key]['config']['custom_form_page_id'], "$key reads the blog");
		pg_assert_same('{{tab:blog_post}}', $w[$key]['config']['detail_page_id'], "$key opens a post");
	}
	pg_assert_same(3, $w['home_posts']['config']['max_results'], 'three posts on the home page');
	pg_assert_same('public', $w['blog_post']['config']['access_control'], 'a post is public');
	pg_assert_same('{{tab:email_reply}}', $d['pages']['blog_post']['comments']['email_page'], 'a comment\'s e-mail');
	pg_assert_same('{{tab:contact}}', $w['inbox_list']['config']['custom_form_page_id'], 'the inbox lists the contact form');
	pg_assert_true($w['inbox_list']['config']['viewer_filter_submitter'], 'the inbox shows the visitor\'s own');
	pg_assert_same('submitter_only', $w['conversation']['config']['access_control'], 'a conversation is its sender\'s');
	pg_assert_same('{{tab:email_reply}}', $d['pages']['conversation']['comments']['email_page'], 'a reply\'s e-mail');
	pg_assert_same('search_results', $w['site_search']['config']['regionType'], 'help searches the site');
	pg_assert_same('all', $w['site_search']['config']['search_scope'], 'the whole site');
	foreach (array('email_message_admin', 'email_message_sender') as $key) {
		$json = json_encode($w[$key]['tree']);
		foreach (array('topic', 'message') as $token) pg_assert_contains('"' . $token . '"', $json, "$key reads $token");
		pg_assert_contains('{{page:conversation}}?r=^^reference_code^^', $json, "$key opens the conversation");
	}
	pg_assert_contains('"reference_code"', json_encode($w['email_message_admin']['tree']), 'the staff e-mail ends with the reference code');
	pg_assert_contains('"has_new_account"', json_encode($w['email_message_sender']['tree']), 'the sender learns of a new account');
}

function test_podcast_standard_links_ids_and_classes()
{
	$d = _test_ppdt_load();
	$known = array_flip(_test_ppd_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	foreach (_test_ppdt_links($trees) as $key) {
		pg_assert_true(isset($known[$key]), "{{page:$key}} names a template page");
		pg_assert_false(in_array($key, _test_ppd_shop_keys(), true), "no link to the shop page $key");
	}
	$nodes = array();
	foreach ($d['pages'] as $p) _test_ppd_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) {
		if (is_array($w['tree'])) _test_ppd_nodes($w['tree'], $nodes);
	}
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|pmu|pmk|psc|pmz|pai|psh|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'pd-') === 0, "id $id is the template's");
	}
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast/standard.php');
	preg_match_all('/#[0-9a-f]{6}\b/i', $src, $colors);
	foreach (array_unique($colors[0]) as $hex) {
		pg_assert_true(in_array(strtolower($hex), array('#c2410c', '#fed7aa', '#fff7ed', '#ffffff', '#1f2937', '#4b5563'), true), "e-mail colour $hex is the template's");
	}
}

function test_podcast_standard_no_fixed_dates()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast/standard.php');
	$src = preg_replace('/@copyright[^\n]*/', '', $src);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $src), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $src), 'no month in the sample');
}

// Every literal has its Turkish; an episode reads "bölüm", a guest
// "konuk", and a page's address has no Turkish letters.
function test_podcast_standard_turkish_words()
{
	$map = pg_test_language_map('tr');
	$d = _test_ppdt_load();
	$names = array();
	foreach ($d['pages'] as $p) $names[$p['name']] = true;
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast/standard.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		pg_assert_false(in_array($key, array('Show', 'Shows', 'Season', 'Show Notes', 'Guest', 'Guests', 'Host', 'Listening'), true), "'$key' reads otherwise in Turkish");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/^[a-z-]+$/', $key)) {
			// A page name is an address; a widget's slug may be a shared word.
			if (isset($names[$key])) pg_assert_true((bool)preg_match('/^[a-z-]+$/', $tr), "the address '$tr' has no Turkish letters");
			continue;
		}
		if (preg_match('/\b[Ee]pisodes?\b/', $key)) pg_assert_true(mb_stripos($tr, 'bölüm', 0, 'UTF-8') !== false, "'$key' says bölüm");
		if (preg_match('/\b[Gg]uests?\b/', $key)) pg_assert_true((bool)preg_match('/konu[kğ]/iu', $tr), "'$key' says konuk");
	}
}
