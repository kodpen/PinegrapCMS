<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the standard pages of the Car Dealership design template
 * (includes/design_templates/pinegrap-dealership/standard.php): contact, the
 * newsletter, Showroom News and its post, inbox and conversation, help center,
 * error page and the message e-mails load with their keys and root trees;
 * every page link names a page of the finished template; the contact form
 * opens a conversation and names its e-mail pages; the newsletter joins its
 * contact group; the threads run on the Comments widget; the lists read the
 * staff page's blog form; ids are unique per page, keep the template's
 * prefix, and no fixed date is written into a page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The part as the template file reads it: parts.php, mail.php, then
// standard.php in the same scope. Read once per run. Until the e-mail part
// is in the folder, its builders are stood in for with the same names and
// node shapes, so this part is tested on its own.
function _test_dl_std_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-dealership';
	include $dir . '/parts.php';
	if (is_file($dir . '/mail.php')) {
		include $dir . '/mail.php';
	} else {
		$mail_style = function ($css) use ($attr) {
			return array('_attrs' => array($attr('style', $css)));
		};
		$mail_page = function ($children) use ($el, $shared) {
			return array('type' => 'root', 'props' => array('cssClass' => ''), 'children' => array(
				$shared('email_header'), $el('div', 'p-4', 'E-mail Body', $children), $shared('email_footer'),
			));
		};
		$mail_heading = function ($text) use ($heading) {
			return $heading('h1', $text, 'h4');
		};
		$mail_para = function ($text, $class = 'mb-3') use ($para) {
			return $para($text, $class);
		};
		$mail_button = function ($text, $href) use ($link) {
			return $link($text, $href, 'btn btn-primary');
		};
		$mail_line = function ($label, $values) use ($el) {
			return $el('p', 'pg-hide-if-empty', $label, $values);
		};
		$mail_box = function ($children, $name) use ($el) {
			return $el('div', 'p-3', $name, $children);
		};
		$mail_greeting = function () use ($el) {
			return $el('p', 'mb-3', 'Greeting');
		};
		$mail_settings = array('folder' => 'private', 'search' => false, 'sitemap' => false, 'noindex' => true);
	}
	$out = include $dir . '/standard.php';
	return $out;
}

// Every node of a tree, depth first.
function _test_dl_std_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_dl_std_nodes($child, $out);
	}
}

// A page of the standard part by its key.
function _test_dl_std_page($key)
{
	$d = _test_dl_std_load();
	foreach ($d['pages'] as $p) if ($p['key'] === $key) return $p;
	return null;
}

// The page keys of the finished template (the plan's pages).
function _test_dl_std_page_keys()
{
	return array(
		'home', 'service', 'finance', 'about', 'visit', 'faq',
		'vehicle_new', 'vehicles', 'new_cars', 'used_cars', 'vehicle',
		'test_drive', 'test_drive_view', 'my_test_drives', 'staff_test_drives',
		'tradein', 'tradein_view', 'my_tradeins', 'staff_tradeins', 'privacy', 'email_reply',
		'finance_apply', 'finance_view', 'my_finance', 'staff_finance',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
}

function test_dealership_standard_pages_load()
{
	$d = _test_dl_std_load();
	$keys = array();
	$names = array();
	foreach ($d['pages'] as $p) {
		pg_assert_true(isset($p['key'], $p['name'], $p['title'], $p['tree'], $p['folder']), 'page has key, name, title, tree, folder');
		pg_assert_same('root', $p['tree']['type'], $p['key'] . ': root tree');
		if ($p['folder'] === 'public' && $p['key'] !== 'error') {
			pg_assert_true(isset($p['meta_description']), $p['key'] . ': description');
		}
		$keys[] = $p['key'];
		$names[] = $p['name'];
	}
	pg_assert_same(array('contact', 'newsletter', 'blog', 'blog_post', 'inbox', 'conversation', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome'), $keys);
	pg_assert_same(count($names), count(array_unique($names)), 'page names are unique');
	pg_assert_same(lang('Showroom News'), _test_dl_std_page('blog')['title'], 'the blog is called Showroom News');
	foreach (array('inbox', 'conversation') as $key) pg_assert_same('registration', _test_dl_std_page($key)['folder'], "$key: signed in");
	foreach (array('email_new_message', 'email_message_received', 'email_welcome') as $key) {
		$p = _test_dl_std_page($key);
		pg_assert_same('private', $p['folder'], "$key: private");
		pg_assert_same(true, $p['noindex'], "$key: noindex");
	}
}

// Every {{page:}} / {{tab:}} names a page of the finished template, and
// every widget defined here sits on one of them.
function test_dealership_standard_links_and_widgets()
{
	$d = _test_dl_std_load();
	$known = array_flip(_test_dl_std_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($d), $m);
	pg_assert_true(count($m[1]) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");

	foreach ($d['pages'] as $p) {
		$nodes = array();
		_test_dl_std_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (!isset($n['props']['templateWidget'])) continue;
			$w = $n['props']['templateWidget'];
			pg_assert_true(isset($d['widgets'][$w]), $p['key'] . ": places a widget of this part: $w");
		}
		pg_assert_false(strpos(json_encode($p['tree']), 'comments_block') !== false, $p['key'] . ': no fixed comments block');
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true($w['page'] === '' || isset($known[$w['page']]), "widget $key: page {$w['page']}");
		pg_assert_true(isset($w['config']['regionType'], $w['tree']), "widget $key: config and tree");
	}
	// The widgets the other parts place and leave to this one.
	foreach (array('newsletter_band', 'home_posts') as $w) pg_assert_true(isset($d['widgets'][$w]), "$w is defined");
	// The reply e-mail and the privacy page are the requests part's.
	pg_assert_false(isset($d['widgets']['email_comment']), 'the reply e-mail widget stays the requests part\'s');
	pg_assert_false(_test_dl_std_page('privacy') !== null, 'the privacy page stays the requests part\'s');
}

function test_dealership_standard_contact_newsletter_and_threads()
{
	$d = _test_dl_std_load();
	$w = $d['widgets'];

	// The contact form opens a conversation and names its e-mail pages.
	$f = _test_dl_std_page('contact')['form'];
	pg_assert_same('{{tab:conversation}}', $f['confirmation_page_id']);
	pg_assert_same(1, $f['auto_registration']);
	pg_assert_same('{{tab:email_new_message}}', $f['notify_page_id']);
	pg_assert_same('{{tab:email_message_received}}', $f['confirm_page_id']);
	pg_assert_same('{{site_email}}', $f['notify_email']);
	$contact = json_encode($w['contact_form']['tree']);
	foreach (array('first_name', 'last_name', 'email', 'phone', 'topic', 'message') as $name) {
		pg_assert_contains('"name","value":"' . $name . '"', $contact, "contact field $name");
	}
	// The contact page sends test drives, trade-ins and finance to their own forms.
	$page = json_encode(_test_dl_std_page('contact')['tree']);
	foreach (array('test_drive', 'tradein', 'finance_apply') as $key) pg_assert_contains('{{page:' . $key . '}}', $page, "contact points to $key");

	// The newsletter's page owns the form; the band sends to the same one.
	$n = _test_dl_std_page('newsletter')['form'];
	pg_assert_same('{{contact_group:newsletter}}', $n['contact_group_id']);
	pg_assert_same('{{tab:email_welcome}}', $n['confirm_page_id']);
	pg_assert_same('existing', $w['newsletter_band']['config']['form_source']);
	pg_assert_same('{{tab:newsletter}}', $w['newsletter_band']['config']['form_id']);
	pg_assert_same('', $w['newsletter_band']['page'], 'the band is placed by every page');

	// The lists and the post read the staff page's blog form.
	foreach (array('home_posts', 'blog_list', 'blog_post') as $key) {
		pg_assert_same('{{tab:blog_new}}', $w[$key]['config']['custom_form_page_id'], "$key: blog form");
	}
	pg_assert_same(3, $w['home_posts']['config']['max_results']);
	pg_assert_same('home', $w['home_posts']['page']);
	pg_assert_same('{{tab:blog_post}}', $w['blog_list']['config']['detail_page_id']);
	pg_assert_contains('{{page:test_drive}}', json_encode($w['blog_post']['tree']), 'a post points to the test drive request');

	// The threads: the page's comment settings, its own Comments widget, the
	// requests part's reply e-mail.
	foreach (array('blog_post' => 'post_comments', 'conversation' => 'conversation_replies') as $page_key => $widget) {
		$p = _test_dl_std_page($page_key);
		pg_assert_same('{{tab:email_reply}}', $p['comments']['email_page'], "$page_key: reply e-mail");
		pg_assert_contains('"templateWidget":"' . $widget . '"', json_encode($p['tree']), "$page_key places $widget");
		pg_assert_same('comments', $w[$widget]['config']['regionType']);
		pg_assert_same($page_key, $w[$widget]['page']);
	}
	pg_assert_same('submitter_only', $w['conversation']['config']['access_control']);
	pg_assert_true($w['inbox_list']['config']['viewer_filter'], 'the inbox lists the viewer\'s own conversations');
	pg_assert_same('all', $w['site_search']['config']['search_scope']);
}

// Ids are unique per page and carry the template's prefix; no class or id
// of another template; no year or calendar date is written into a page, so
// the sample never reads as dated.
function test_dealership_standard_ids_and_no_fixed_dates()
{
	$d = _test_dl_std_load();
	$trees = array();
	foreach ($d['pages'] as $p) {
		$nodes = array();
		_test_dl_std_nodes($p['tree'], $nodes);
		foreach ($d['widgets'] as $w) if ($w['page'] === $p['key'] && is_array($w['tree'])) _test_dl_std_nodes($w['tree'], $nodes);
		$seen = array();
		foreach ($nodes as $n) {
			$class = isset($n['props']['cssClass']) ? (string)$n['props']['cssClass'] : '';
			pg_assert_false((bool)preg_match('/\b(plw|psl|pre|pco|pev|pcl|pf|pdp|pm|pma|pht|plx|pmk)-[a-z]/', $class), 'no class of another template: ' . $class);
			if (!isset($n['props']['id']) || $n['props']['id'] === '') continue;
			$id = $n['props']['id'];
			// The Comments widget's own controls keep the names it reads.
			if (strpos($id, 'comment_') !== 0) pg_assert_true(strpos($id, 'dl-') === 0, $p['key'] . ": id $id has the template's prefix");
			pg_assert_false(isset($seen[$id]), $p['key'] . ": id $id is unique");
			$seen[$id] = true;
		}
		$trees[] = $p['tree'];
	}
	foreach ($d['widgets'] as $w) $trees[] = $w['tree'];
	$text = json_encode($trees, JSON_UNESCAPED_UNICODE);
	pg_assert_false((bool)preg_match('/\b20[2-3]\d\b/', $text), 'no year');
	pg_assert_false((bool)preg_match('/\b\d{4}-\d{2}-\d{2}\b/', $text), 'no ISO date');
}
