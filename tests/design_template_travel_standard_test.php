<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Travel Agency design template's standard pages
 * (includes/design_templates/pinegrap-travel/standard.php): the pages and
 * their folders, every page link naming a template page, every placed
 * widget defined, the contact form opening a conversation, the newsletter
 * filling its group, the journal lists reading the staff's post form, the
 * threads and their e-mails, ids with the template's prefix, no fixed date,
 * and every literal with its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template.
require_once(__DIR__ . '/design_template_travel_test.php');

// The parts as the template file reads them: parts.php, mail.php, then
// standard.php in the same scope. Read once per run.
function _test_tv_std_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-travel';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$out = include $dir . '/standard.php';
	return $out;
}

// Every node of a tree, depth first.
function _test_tv_std_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_tv_std_nodes($child, $out);
	}
}

// A page of the standard part by its key.
function _test_tv_std_page($key)
{
	$d = _test_tv_std_load();
	foreach ($d['pages'] as $p) if ($p['key'] === $key) return $p;
	return null;
}

function test_travel_standard_pages_load()
{
	$d = _test_tv_std_load();
	$keys = array();
	foreach ($d['pages'] as $p) {
		pg_assert_true(isset($p['key'], $p['name'], $p['title'], $p['tree'], $p['folder']), 'page has key, name, title, tree, folder');
		pg_assert_same('root', $p['tree']['type'], $p['key'] . ': root tree');
		if ($p['folder'] === 'public' && $p['key'] !== 'error') {
			pg_assert_true(isset($p['meta_description']), $p['key'] . ': description');
		}
		$keys[] = $p['key'];
	}
	pg_assert_same(array('contact', 'newsletter', 'blog', 'blog_post', 'inbox', 'conversation', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome'), $keys);
	foreach (array('inbox', 'conversation') as $key) pg_assert_same('registration', _test_tv_std_page($key)['folder'], "$key: signed in");
	foreach (array('email_new_message', 'email_message_received', 'email_welcome') as $key) {
		$p = _test_tv_std_page($key);
		pg_assert_same('private', $p['folder'], "$key: private");
		pg_assert_same(true, $p['noindex'], "$key: noindex");
	}
	pg_assert_same(true, _test_tv_std_page('error')['noindex'], 'error: noindex');
	// No standard page needs the shop: they all stand without it.
	foreach ($d['pages'] as $p) pg_assert_false(isset($p['requires']), $p['key'] . ': stands without the shop');
}

// Every {{page:}} / {{tab:}} names a page of the finished template, and
// every widget a page places is defined here (the account step's login
// region aside).
function test_travel_standard_links_and_widgets()
{
	$d = _test_tv_std_load();
	$known = array_flip(_test_tv_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($d), $m);
	pg_assert_true(count($m[1]) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");

	foreach ($d['pages'] as $p) {
		$nodes = array();
		_test_tv_std_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (!isset($n['props']['templateWidget'])) continue;
			$w = $n['props']['templateWidget'];
			pg_assert_true(isset($d['widgets'][$w]), $p['key'] . ": places a known widget $w");
		}
		pg_assert_false(strpos(json_encode($p['tree']), 'comments_block') !== false, $p['key'] . ': no fixed comments block');
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true($w['page'] === '' || isset($known[$w['page']]), "widget $key: page {$w['page']}");
		pg_assert_true(isset($w['config']['regionType'], $w['tree']), "widget $key: config and tree");
	}
	// The widgets the home page places and leaves to this one.
	foreach (array('home_posts', 'google_reviews') as $w) pg_assert_true(isset($d['widgets'][$w]), "$w is defined");
}

function test_travel_standard_contact_newsletter_and_threads()
{
	$d = _test_tv_std_load();
	$w = $d['widgets'];

	// The contact form opens a conversation and names its e-mail pages.
	$f = _test_tv_std_page('contact')['form'];
	pg_assert_same('{{tab:conversation}}', $f['confirmation_page_id']);
	pg_assert_same(1, $f['auto_registration']);
	pg_assert_same('{{tab:email_new_message}}', $f['notify_page_id']);
	pg_assert_same('{{tab:email_message_received}}', $f['confirm_page_id']);
	pg_assert_same('{{site_email}}', $f['notify_email']);
	$contact = json_encode($w['contact_form']['tree']);
	foreach (array('first_name', 'last_name', 'email', 'phone', 'topic', 'message') as $name) {
		pg_assert_contains('"name","value":"' . $name . '"', $contact, "contact field $name");
	}

	// The newsletter's page owns the form and fills the newsletter group.
	$n = _test_tv_std_page('newsletter')['form'];
	pg_assert_same('{{contact_group:newsletter}}', $n['contact_group_id']);
	pg_assert_same('{{tab:email_welcome}}', $n['confirm_page_id']);
	pg_assert_same('page', $w['newsletter_form']['config']['form_source']);
	pg_assert_contains('"templateWidget":"newsletter_form"', json_encode(_test_tv_std_page('newsletter')['tree']));

	// The home's Google reviews show only while Google returns reviews.
	pg_assert_same('google_reviews', $w['google_reviews']['config']['regionType']);
	pg_assert_same('home', $w['google_reviews']['page']);
	pg_assert_contains('"eo_visible_if":"has_reviews"', json_encode($w['google_reviews']['tree']));

	// The lists and the post read the staff page's post form.
	foreach (array('home_posts', 'blog_list', 'blog_post') as $key) {
		pg_assert_same('{{tab:blog_new}}', $w[$key]['config']['custom_form_page_id'], "$key: post form");
	}
	pg_assert_same(3, $w['home_posts']['config']['max_results']);
	pg_assert_same('home', $w['home_posts']['page']);
	pg_assert_same('{{tab:blog_post}}', $w['blog_list']['config']['detail_page_id']);
	pg_assert_same(array('title', 'summary', 'content'), $w['blog_list']['config']['search_fields']);

	// The threads: the page's comment settings, its own Comments widget, the
	// reply e-mail of the requests part.
	foreach (array('blog_post' => 'post_comments', 'conversation' => 'conversation_replies') as $page => $widget) {
		$p = _test_tv_std_page($page);
		pg_assert_same('{{tab:email_reply}}', $p['comments']['email_page'], "$page: reply e-mail");
		pg_assert_contains('"templateWidget":"' . $widget . '"', json_encode($p['tree']), "$page places $widget");
		pg_assert_same('comments', $w[$widget]['config']['regionType']);
		pg_assert_same($page, $w[$widget]['page']);
	}
	pg_assert_same('submitter_only', $w['conversation']['config']['access_control']);
	pg_assert_same('{{tab:contact}}', $w['inbox_list']['config']['custom_form_page_id']);
	pg_assert_same(true, $w['inbox_list']['config']['viewer_filter']);
	pg_assert_same('all', $w['site_search']['config']['search_scope']);
}

// Ids are unique per page and carry the template's tv- prefix (the comment
// form's own ids aside); no year or calendar date is written into a page,
// so the sample never reads as an old post.
function test_travel_standard_ids_and_no_fixed_dates()
{
	$d = _test_tv_std_load();
	$trees = array();
	foreach ($d['pages'] as $p) {
		$nodes = array();
		_test_tv_std_nodes($p['tree'], $nodes);
		foreach ($d['widgets'] as $w) if ($w['page'] === $p['key'] && is_array($w['tree'])) _test_tv_std_nodes($w['tree'], $nodes);
		$seen = array();
		foreach ($nodes as $n) {
			if (!isset($n['props']['id']) || $n['props']['id'] === '') continue;
			$id = $n['props']['id'];
			pg_assert_false(isset($seen[$id]), $p['key'] . ": id $id is unique");
			// The comment thread's form controls keep the fixed comment_* ids
			// the Comments widget reads.
			if (strpos($id, 'comment_') !== 0) pg_assert_same(0, strpos($id, 'tv-'), $p['key'] . ": id $id has the template prefix");
			$seen[$id] = true;
		}
		$trees[] = $p['tree'];
	}
	foreach ($d['widgets'] as $w) $trees[] = $w['tree'];
	$text = json_encode($trees, JSON_UNESCAPED_UNICODE);
	pg_assert_false((bool)preg_match('/\b20[2-3]\d\b/', $text), 'no year');
	pg_assert_false((bool)preg_match('/\b\d{4}-\d{2}-\d{2}\b/', $text), 'no ISO date');
}

// Every literal has its Turkish; a tour reads "tur", a deposit "kapora".
function test_travel_standard_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-travel/standard.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$words = preg_replace('/\^\^[a-z_]+\^\^/', '', $key);
		if (preg_match('/\b[Tt]ours?\b/', $words)) pg_assert_true(mb_stripos($map[$key], 'tur', 0, 'UTF-8') !== false, "'$key' says tur");
		if (preg_match('/\b[Dd]eposits?\b/', $words)) pg_assert_true(mb_stripos($map[$key], 'kapora', 0, 'UTF-8') !== false, "'$key' says kapora");
	}
}
