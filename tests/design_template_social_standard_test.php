<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the standard pages of the Social Community design template
 * (includes/design_templates/pinegrap-social/standard.php): contact, the
 * newsletter, Community News and its post page, inbox and conversation,
 * the search, error page and the message e-mails load with their keys and
 * root trees; every page link and every placed widget names a page or
 * widget of the finished template, and no page needs the shop; the contact
 * form opens a conversation and names its e-mail pages; the newsletter
 * fills the newsletter group; the threads run on the Comments widget; the
 * news lists read the staff page's post form; Community News opens members'
 * profiles and the search offers every topic; no widget key clashes with
 * the posts part's; ids are unique per page and use the template's prefix;
 * no fixed date is written into a page; every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The parts as the template file reads them: parts.php, mail.php, then
// standard.php in the same scope. Read once per run.
function _test_social_std_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-social';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$out = include $dir . '/standard.php';
	return $out;
}

// Every node of a tree, depth first.
function _test_social_std_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_social_std_nodes($child, $out);
	}
}

// A page of the standard part by its key.
function _test_social_std_page($key)
{
	$d = _test_social_std_load();
	foreach ($d['pages'] as $p) if ($p['key'] === $key) return $p;
	return null;
}

// The page keys of the finished template: this part's, and the pages of
// the public, profiles, posts, reports and account steps.
function _test_social_std_page_keys()
{
	$d = _test_social_std_load();
	$keys = array(
		'home', 'topics', 'about', 'guidelines', 'faq', 'help', 'privacy', 'terms',
		'my_profile', 'people', 'member', 'staff_members',
		'post_new', 'explore', 'tag', 'post', 'my_posts', 'saved',
		'report', 'report_view', 'my_reports', 'staff_reports', 'staff_report', 'staff_posts', 'email_reply', 'email_comment',
		'my_account', 'staff', 'staff_directory', 'staff_entry', 'blog_new', 'login', 'register', 'set_password', 'logout',
		'profile', 'change_password', 'email_preferences', 'forgot_password',
	);
	foreach ($d['pages'] as $p) $keys[] = $p['key'];
	return $keys;
}

function test_social_standard_pages_load()
{
	$d = _test_social_std_load();
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
	foreach (array('inbox', 'conversation') as $key) pg_assert_same('registration', _test_social_std_page($key)['folder'], "$key: signed in");
	foreach (array('email_new_message', 'email_message_received', 'email_welcome') as $key) {
		$p = _test_social_std_page($key);
		pg_assert_same('private', $p['folder'], "$key: private");
		pg_assert_same(true, $p['noindex'], "$key: noindex");
	}
	pg_assert_same(true, _test_social_std_page('error')['noindex'], 'error: noindex');
	// The community has no shop: no page asks for it.
	foreach ($d['pages'] as $p) pg_assert_false(isset($p['requires']), $p['key'] . ': stands without the shop');
}

// Every {{page:}} / {{tab:}} names a page of the finished template, and
// every widget a page places is defined here (the account step's login
// region aside).
function test_social_standard_links_and_widgets()
{
	$d = _test_social_std_load();
	$known = array_flip(_test_social_std_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($d), $m);
	pg_assert_true(count($m[1]) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");

	foreach ($d['pages'] as $p) {
		$nodes = array();
		_test_social_std_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (!isset($n['props']['templateWidget'])) continue;
			$w = $n['props']['templateWidget'];
			pg_assert_true(isset($d['widgets'][$w]), $p['key'] . ": places a known widget $w");
		}
		pg_assert_false(strpos(json_encode($p['tree']), 'comments_block') !== false, $p['key'] . ': no fixed comments block');
	}
	// The community has no shop, so no page links into one.
	foreach (array('shop', 'product', 'cart', 'checkout', 'order', 'email_order') as $key) {
		pg_assert_false(in_array($key, $m[1], true), "no link to the shop page $key");
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true($w['page'] === '' || isset($known[$w['page']]), "widget $key: page {$w['page']}");
		pg_assert_true(isset($w['config']['regionType'], $w['tree']), "widget $key: config and tree");
	}
	// The widgets the other parts place and leave to this one.
	foreach (array('newsletter_band', 'home_news') as $w) pg_assert_true(isset($d['widgets'][$w]), "$w is defined");
}

function test_social_standard_contact_newsletter_and_threads()
{
	$d = _test_social_std_load();
	$w = $d['widgets'];

	// The contact form opens a conversation and names its e-mail pages.
	$f = _test_social_std_page('contact')['form'];
	pg_assert_same('{{tab:conversation}}', $f['confirmation_page_id']);
	pg_assert_same(1, $f['auto_registration']);
	pg_assert_same('{{tab:email_new_message}}', $f['notify_page_id']);
	pg_assert_same('{{tab:email_message_received}}', $f['confirm_page_id']);
	pg_assert_same('{{site_email}}', $f['notify_email']);
	$contact = json_encode($w['contact_form']['tree']);
	foreach (array('first_name', 'last_name', 'email', 'user_name', 'topic', 'message') as $name) {
		pg_assert_contains('"name","value":"' . $name . '"', $contact, "contact field $name");
	}

	// The newsletter's page owns the form; the band sends to the same one.
	$n = _test_social_std_page('newsletter')['form'];
	pg_assert_same('{{contact_group:newsletter}}', $n['contact_group_id']);
	pg_assert_same('{{tab:email_welcome}}', $n['confirm_page_id']);
	pg_assert_same('existing', $w['newsletter_band']['config']['form_source']);
	pg_assert_same('{{tab:newsletter}}', $w['newsletter_band']['config']['form_id']);
	pg_assert_same('', $w['newsletter_band']['page'], 'the band is placed by every page');

	// The news lists and the post read the staff page's post form.
	foreach (array('home_news', 'blog_list', 'blog_post') as $key) {
		pg_assert_same('{{tab:blog_new}}', $w[$key]['config']['custom_form_page_id'], "$key: post form");
	}
	pg_assert_same(3, $w['home_news']['config']['max_results']);
	pg_assert_same('home', $w['home_news']['page']);
	pg_assert_same('{{tab:blog_post}}', $w['blog_list']['config']['detail_page_id']);
	pg_assert_same(array('title', 'summary', 'content'), $w['blog_list']['config']['search_fields']);

	// The threads: the page's comment settings, its own Comments widget, the
	// reply e-mail of the requests part.
	foreach (array('blog_post' => 'news_comments', 'conversation' => 'conversation_replies') as $page => $widget) {
		$p = _test_social_std_page($page);
		pg_assert_same('{{tab:email_reply}}', $p['comments']['email_page'], "$page: reply e-mail");
		pg_assert_contains('"templateWidget":"' . $widget . '"', json_encode($p['tree']), "$page places $widget");
		pg_assert_same('comments', $w[$widget]['config']['regionType']);
		pg_assert_same($page, $w[$widget]['page']);
	}
	pg_assert_same('submitter_only', $w['conversation']['config']['access_control']);
	pg_assert_same('{{tab:contact}}', $w['inbox_list']['config']['custom_form_page_id']);
	pg_assert_same(true, $w['inbox_list']['config']['viewer_filter']);
	pg_assert_same('all', $w['site_search']['config']['search_scope']);

	// The sender's confirmation greets them by the name the contact form
	// recorded.
	pg_assert_contains('"_bindings":{"text":"first_name"}', json_encode($w['email_message_sender']['tree']), 'greeting binds first_name');
}

// Ids are unique per page and carry the template's sc- prefix (the comment
// form's own ids aside); no year or calendar date is written into a page,
// so the sample never reads as stale a year later.
function test_social_standard_ids_and_no_fixed_dates()
{
	$d = _test_social_std_load();
	$trees = array();
	foreach ($d['pages'] as $p) {
		$nodes = array();
		_test_social_std_nodes($p['tree'], $nodes);
		foreach ($d['widgets'] as $w) if ($w['page'] === $p['key'] && is_array($w['tree'])) _test_social_std_nodes($w['tree'], $nodes);
		$seen = array();
		foreach ($nodes as $n) {
			if (!isset($n['props']['id']) || $n['props']['id'] === '') continue;
			$id = $n['props']['id'];
			pg_assert_false(isset($seen[$id]), $p['key'] . ": id $id is unique");
			// The comment thread's form controls keep the fixed comment_* ids
			// the Comments widget reads.
			if (strpos($id, 'comment_') !== 0) pg_assert_same(0, strpos($id, 'sc-'), $p['key'] . ": id $id has the template prefix");
			$seen[$id] = true;
		}
		$trees[] = $p['tree'];
	}
	foreach ($d['widgets'] as $w) $trees[] = $w['tree'];
	$text = json_encode($trees, JSON_UNESCAPED_UNICODE);
	pg_assert_false((bool)preg_match('/\b20[2-3]\d\b/', $text), 'no year');
	pg_assert_false((bool)preg_match('/\b\d{4}-\d{2}-\d{2}\b/', $text), 'no ISO date');
}

// Community News names members, each opening their profile; the search
// offers every topic's explore list and keeps its questions in their own
// accordion.
function test_social_standard_news_members_and_search_topics()
{
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-social';
	include $dir . '/parts.php';
	$blog = json_encode(_test_social_std_page('blog')['tree'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$found = 0;
	foreach (array_keys($members) as $key) {
		if (strpos($blog, json_encode($members[$key]['name'], JSON_UNESCAPED_UNICODE)) === false) continue;
		pg_assert_contains($member_url($key), $blog, "$key: opens the member in the people list");
		pg_assert_true(strpos($blog, '@' . $members[$key]['handle']) === false, "$key: no user name the site does not have");
		$found++;
	}
	pg_assert_true($found >= 3, 'members to meet');
	pg_assert_true(strpos($blog, '{{page:member}}?member=') === false, 'no sample member links the member page');
	pg_assert_contains('"templateWidget":"blog_list"', $blog);
	pg_assert_contains('{{page:people}}', $blog);

	$search = json_encode(_test_social_std_page('search')['tree'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	foreach (array_keys($topics) as $key) pg_assert_contains($topic_url($key), $search, "search offers $key");
	pg_assert_contains('"id":"sc-search-faq"', $search);
	pg_assert_contains('"templateWidget":"site_search"', $search);
}

// The posts part has its own home lists and post thread: this part's
// widgets keep keys of their own, so the assembly's merge loses none.
function test_social_standard_widget_keys_stay_its_own()
{
	$d = _test_social_std_load();
	foreach (array('home_posts', 'post_comments', 'home_feed', 'home_latest') as $key) {
		pg_assert_false(isset($d['widgets'][$key]), "no widget $key here");
	}
	pg_assert_same(lang('Community News'), _test_social_std_page('blog')['title'], 'the blog is Community News');
}

// Every literal of the part has its Turkish.
function test_social_standard_turkish_words()
{
	require_once(__DIR__ . '/helpers/language_map.php');
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-social/standard.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	pg_assert_true(count($m[1]) > 100, 'literals found');
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
	}
}
