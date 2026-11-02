<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the standard pages of the Law Firm design template
 * (includes/design_templates/pinegrap-law/standard.php): contact,
 * newsletter, Insights and post, inbox and conversation, help center,
 * error page and the message e-mails load with their keys and root trees;
 * every page link and every placed widget names a page or widget of the
 * finished template; the contact form opens a conversation and names its
 * e-mail pages; the threads run on the Comments widget; the lists read the
 * staff page's blog form; ids are unique per page and no fixed date is
 * written into a page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The parts as the template file reads them: parts.php, mail.php, then the
// page files in the same scope. Read once per run.
function _test_law_std_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-law';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	// The public part returns its pages only; the widgets they place are
	// this part's and the consultation part's.
	$public = array('pages' => include $dir . '/pages_public.php', 'widgets' => array());
	$consultation = include $dir . '/consultation.php';
	$standard = include $dir . '/standard.php';
	$out = array(
		'public'       => $public,
		'consultation' => $consultation,
		'standard'     => $standard,
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_law_std_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_law_std_nodes($child, $out);
	}
}

// A page of the standard part by its key.
function _test_law_std_page($key)
{
	$d = _test_law_std_load();
	foreach ($d['standard']['pages'] as $p) if ($p['key'] === $key) return $p;
	return null;
}

// The page keys of the finished template: the parts loaded here, and the
// pages of the account step.
function _test_law_std_page_keys()
{
	$d = _test_law_std_load();
	$keys = array(
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
	foreach (array('public', 'consultation', 'standard') as $part) {
		foreach ($d[$part]['pages'] as $p) $keys[] = $p['key'];
	}
	return $keys;
}

function test_law_standard_pages_load()
{
	$d = _test_law_std_load();
	$keys = array();
	foreach ($d['standard']['pages'] as $p) {
		pg_assert_true(isset($p['key'], $p['name'], $p['title'], $p['tree'], $p['folder']), 'page has key, name, title, tree, folder');
		pg_assert_same('root', $p['tree']['type'], $p['key'] . ': root tree');
		if ($p['folder'] === 'public' && $p['key'] !== 'error') {
			pg_assert_true(isset($p['meta_description']), $p['key'] . ': description');
		}
		$keys[] = $p['key'];
	}
	pg_assert_same(array('contact', 'newsletter', 'blog', 'blog_post', 'inbox', 'conversation', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome'), $keys);
	foreach (array('inbox', 'conversation') as $key) pg_assert_same('registration', _test_law_std_page($key)['folder'], "$key: signed in");
	foreach (array('email_new_message', 'email_message_received', 'email_welcome') as $key) {
		$p = _test_law_std_page($key);
		pg_assert_same('private', $p['folder'], "$key: private");
		pg_assert_same(true, $p['noindex'], "$key: noindex");
	}
}

// Every {{page:}} / {{tab:}} names a page of the finished template, and
// every widget a page places is defined (the standard part's own, an
// earlier part's, or the account step's login region).
function test_law_standard_links_and_widgets()
{
	$d = _test_law_std_load();
	$known = array_flip(_test_law_std_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($d['standard']), $m);
	pg_assert_true(count($m[1]) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");

	$widgets = array_merge($d['public']['widgets'], $d['consultation']['widgets'], $d['standard']['widgets']);
	foreach ($d['standard']['pages'] as $p) {
		$nodes = array();
		_test_law_std_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (!isset($n['props']['templateWidget'])) continue;
			$w = $n['props']['templateWidget'];
			pg_assert_true(isset($widgets[$w]) || $w === 'login_region', $p['key'] . ": places a known widget $w");
		}
		pg_assert_false(strpos(json_encode($p['tree']), 'comments_block') !== false, $p['key'] . ': no fixed comments block');
	}
	foreach ($d['standard']['widgets'] as $key => $w) {
		pg_assert_true($w['page'] === '' || isset($known[$w['page']]), "widget $key: page {$w['page']}");
		pg_assert_true(isset($w['config']['regionType'], $w['tree']), "widget $key: config and tree");
	}
	// The widgets the earlier parts place and leave to this one.
	foreach (array('newsletter_band', 'home_posts', 'google_reviews') as $w) pg_assert_true(isset($d['standard']['widgets'][$w]), "$w is defined");
	// No widget key is defined twice across the parts.
	$all = array_merge(array_keys($d['public']['widgets']), array_keys($d['consultation']['widgets']), array_keys($d['standard']['widgets']));
	pg_assert_same(count($all), count(array_unique($all)), 'widget keys are unique across the parts');
	// Page keys and names are unique across the parts.
	$keys = array();
	$names = array();
	foreach (array('public', 'consultation', 'standard') as $part) {
		foreach ($d[$part]['pages'] as $p) {
			$keys[] = $p['key'];
			$names[] = $p['name'];
		}
	}
	pg_assert_same(count($keys), count(array_unique($keys)), 'page keys are unique across the parts');
	pg_assert_same(count($names), count(array_unique($names)), 'page names are unique across the parts');
}

function test_law_standard_contact_newsletter_and_threads()
{
	$d = _test_law_std_load();
	$w = $d['standard']['widgets'];

	// The contact form opens a conversation and names its e-mail pages.
	$f = _test_law_std_page('contact')['form'];
	pg_assert_same('{{tab:conversation}}', $f['confirmation_page_id']);
	pg_assert_same(1, $f['auto_registration']);
	pg_assert_same('{{tab:email_new_message}}', $f['notify_page_id']);
	pg_assert_same('{{tab:email_message_received}}', $f['confirm_page_id']);
	pg_assert_same('{{site_email}}', $f['notify_email']);
	$contact = json_encode($w['contact_form']['tree']);
	foreach (array('first_name', 'last_name', 'email', 'phone', 'topic', 'message') as $name) {
		pg_assert_contains('"name","value":"' . $name . '"', $contact, "contact field $name");
	}

	// The newsletter's page owns the form; the band sends to the same one.
	$n = _test_law_std_page('newsletter')['form'];
	pg_assert_same('{{contact_group:newsletter}}', $n['contact_group_id']);
	pg_assert_same('{{tab:email_welcome}}', $n['confirm_page_id']);
	pg_assert_same('existing', $w['newsletter_band']['config']['form_source']);
	pg_assert_same('{{tab:newsletter}}', $w['newsletter_band']['config']['form_id']);

	// The lists and the post read the staff page's blog form.
	foreach (array('home_posts', 'blog_list', 'blog_post') as $key) {
		pg_assert_same('{{tab:blog_new}}', $w[$key]['config']['custom_form_page_id'], "$key: blog form");
	}
	pg_assert_same(3, $w['home_posts']['config']['max_results']);
	pg_assert_same('{{tab:blog_post}}', $w['blog_list']['config']['detail_page_id']);

	// The threads: the page's comment settings, its own Comments widget, the
	// reply e-mail of the consultation part (defined there, not here).
	foreach (array('blog_post' => 'post_comments', 'conversation' => 'conversation_replies') as $page => $widget) {
		$p = _test_law_std_page($page);
		pg_assert_same('{{tab:email_reply}}', $p['comments']['email_page'], "$page: reply e-mail");
		pg_assert_contains('"templateWidget":"' . $widget . '"', json_encode($p['tree']), "$page places $widget");
		pg_assert_same('comments', $w[$widget]['config']['regionType']);
		pg_assert_same($page, $w[$widget]['page']);
	}
	pg_assert_same('submitter_only', $w['conversation']['config']['access_control']);
	pg_assert_same('all', $w['site_search']['config']['search_scope']);
	pg_assert_false(isset($w['email_comment']), 'the reply e-mail widget stays the consultation part\'s');
	pg_assert_true(isset($d['consultation']['widgets']['email_comment']), 'the consultation part defines the reply e-mail widget');
	pg_assert_same('google_reviews', $w['google_reviews']['config']['regionType']);
	pg_assert_same('home', $w['google_reviews']['page']);
	pg_assert_contains('{{page:consultation}}', json_encode($w['blog_post']['tree']), 'a post points to the consultation form');
	// The template's field ids keep its prefix.
	pg_assert_false(strpos(json_encode($d['standard']), 'pco-') !== false, 'no Courses id or class');
}

// Ids are unique per page, and no year or calendar date is written into a
// page, so the sample never reads as dated.
function test_law_standard_ids_and_no_fixed_dates()
{
	$d = _test_law_std_load();
	$trees = array();
	foreach ($d['standard']['pages'] as $p) {
		$nodes = array();
		_test_law_std_nodes($p['tree'], $nodes);
		foreach ($d['standard']['widgets'] as $w) if ($w['page'] === $p['key'] && is_array($w['tree'])) _test_law_std_nodes($w['tree'], $nodes);
		$seen = array();
		foreach ($nodes as $n) {
			if (!isset($n['props']['id']) || $n['props']['id'] === '') continue;
			$id = $n['props']['id'];
			pg_assert_false(isset($seen[$id]), $p['key'] . ": id $id is unique");
			$seen[$id] = true;
		}
		$trees[] = $p['tree'];
	}
	foreach ($d['standard']['widgets'] as $w) $trees[] = $w['tree'];
	$text = json_encode($trees, JSON_UNESCAPED_UNICODE);
	pg_assert_false((bool)preg_match('/\b20[2-3]\d\b/', $text), 'no year');
	pg_assert_false((bool)preg_match('/\b\d{4}-\d{2}-\d{2}\b/', $text), 'no ISO date');
}
