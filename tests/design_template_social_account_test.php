<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the account and staff part of the Social Community design
 * template (includes/design_templates/pinegrap-social/account.php): its
 * pages in order, in their folders and with root trees; every page link
 * names a page of the template and every placed widget is defined; the
 * account e-mails are wired to the registration and password widgets and
 * the reset button carries the set password link; My Account and the login
 * menu lead to the member's posts, saved posts, profile and reports, and
 * show no orders; the staff area leads to the moderation lists; the
 * Community News form has the fields the news cards read and its sample
 * posts fill only those, with no fixed date, naming the community's own
 * members; the field ids are the template's own; every literal has its
 * Turkish.
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

// parts.php, mail.php and account.php in one scope, as the template file
// includes them, with the other parts of the folder that are there. While
// mail.php is not in the tree, plain stand-ins of the e-mail builders
// account.php uses take its place. Read once per run.
function _test_sca_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-social';
	include $dir . '/parts.php';
	if (is_file($dir . '/mail.php')) {
		include $dir . '/mail.php';
	} else {
		$mail_style    = function ($css) use ($attr) { return array('_attrs' => array($attr('style', $css))); };
		$mail_page     = function ($children) use ($el) { return array('type' => 'root', 'props' => array(), 'children' => array($el('div', '', 'E-mail Body', $children))); };
		$mail_heading  = function ($text) use ($heading) { return $heading('h1', $text, ''); };
		$mail_para     = function ($text, $class = 'mb-3', $style = '') use ($para) { return $para($text, $class); };
		$mail_button   = function ($text, $href, $extra = array()) use ($link) { return $link($text, $href, 'btn', array(), $extra); };
		$mail_settings = array('folder' => 'private', 'search' => false, 'sitemap' => false, 'noindex' => true);
	}
	$others = array();
	foreach (array('pages_public', 'profiles', 'posts', 'reports', 'standard') as $name) {
		if (is_file($dir . '/' . $name . '.php')) $others[$name] = include $dir . '/' . $name . '.php';
	}
	$out = array(
		'account' => include $dir . '/account.php',
		'others'  => $others,
		'shared'  => array($account_bar_tree(), $header(), $member_bar(), $footer(), $notice(), $staff_bar()),
		'members' => $members,
	);
	return $out;
}

function _test_sca_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_sca_nodes($child, $out);
	}
}

function _test_sca_page($key)
{
	$d = _test_sca_load();
	foreach ($d['account']['pages'] as $p) if ($p['key'] === $key) return $p;
	return null;
}

// The field names of a widget tree.
function _test_sca_fields($tree)
{
	$nodes = array();
	_test_sca_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		if (empty($n['props']['_attrs'])) continue;
		foreach ($n['props']['_attrs'] as $a) if ($a['name'] === 'name') $out[] = $a['value'];
	}
	return $out;
}

function test_social_account_pages_load()
{
	$d = _test_sca_load();
	$keys = array();
	foreach ($d['account']['pages'] as $p) {
		pg_assert_true(isset($p['key'], $p['name'], $p['title'], $p['tree'], $p['folder']), 'page has key, name, title, tree, folder');
		pg_assert_same('root', $p['tree']['type'], $p['key'] . ': root tree');
		pg_assert_same(false, $p['sitemap'], $p['key'] . ': out of the sitemap');
		pg_assert_false(isset($p['requires']), $p['key'] . ': needs no shop');
		$keys[] = $p['key'];
	}
	pg_assert_same(array('login', 'register', 'forgot_password', 'set_password', 'logout',
		'my_account', 'profile', 'change_password', 'email_preferences', 'staff_directory',
		'staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset'), $keys);
	foreach (array('my_account', 'profile', 'change_password', 'email_preferences', 'staff_directory') as $key) {
		$p = _test_sca_page($key);
		pg_assert_same('registration', $p['folder'], "$key: signed in");
		pg_assert_contains('"templateShared":"member_bar"', json_encode($p['tree']), "$key: member bar");
	}
	foreach (array('staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset') as $key) {
		$p = _test_sca_page($key);
		pg_assert_same('private', $p['folder'], "$key: private");
		pg_assert_same(true, $p['noindex'], "$key: noindex");
	}
	// The account page is not named like the member's public profile page.
	pg_assert_true(_test_sca_page('profile')['name'] !== lang('my-profile'), 'account page keeps its own address');
	// No page key or widget key meets one of the other parts.
	foreach ($d['others'] as $name => $part) {
		foreach ($part['pages'] as $p) pg_assert_false(in_array($p['key'], $keys, true), "page {$p['key']} is not also in $name.php");
		foreach ($d['account']['widgets'] as $k => $w) pg_assert_false(isset($part['widgets'][$k]), "widget $k is not also in $name.php");
	}
}

// Each page link of the account part and the shared parts names a page of
// the template: one of the account part, of the other parts in the tree,
// or of the plan's parts still to come. Each placed widget is defined and
// each widget's page exists.
function test_social_account_links_and_widgets()
{
	$d = _test_sca_load();
	$keys = array_fill_keys(array(
		'home', 'topics', 'about', 'guidelines', 'faq', 'help', 'privacy', 'terms',
		'my_profile', 'people', 'member', 'staff_members',
		'post_new', 'explore', 'tag', 'post', 'my_posts', 'saved',
		'report', 'report_view', 'my_reports', 'staff_reports', 'staff_report', 'staff_posts', 'email_reply', 'email_comment',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
	), true);
	$widgets = array();
	foreach (array_merge(array($d['account']), array_values($d['others'])) as $part) {
		foreach ($part['pages'] as $p) $keys[$p['key']] = true;
		foreach ($part['widgets'] as $k => $w) $widgets[$k] = $w;
	}
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(array($d['account'], $d['shared'])), $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($keys[$key]), "page link {{page:$key}} names a template page");

	$nodes = array();
	foreach ($d['account']['pages'] as $p) _test_sca_nodes($p['tree'], $nodes);
	foreach ($nodes as $n) {
		if (isset($n['props']['templateWidget'])) pg_assert_true(isset($widgets[$n['props']['templateWidget']]), 'widget ' . $n['props']['templateWidget'] . ' is defined');
	}
	foreach ($d['account']['widgets'] as $k => $w) pg_assert_true($w['page'] === '' || isset($keys[$w['page']]), "widget $k: page {$w['page']}");

	// The account bar places the login region this part defines.
	pg_assert_contains('"templateWidget":"login_region"', json_encode($d['shared'][0]), 'account bar places the login region');
	pg_assert_true(isset($d['account']['widgets']['login_region']), 'login region is defined here');
}

function test_social_account_emails_and_widgets()
{
	$d = _test_sca_load();
	$w = $d['account']['widgets'];
	pg_assert_same('{{tab:email_account_welcome}}', $w['register']['config']['welcome_email_page_id']);
	pg_assert_same('{{tab:email_password_reset}}', $w['forgot_password']['config']['reset_email_page_id']);
	pg_assert_contains('"href":"__set_password_url"', json_encode($w['account_email_reset']['tree']), 'reset button carries the set password link');
	pg_assert_contains('"text":"__user_first_name"', json_encode($w['account_email_welcome']['tree']), 'welcome greets the member by name');
	pg_assert_same('account_email', $w['account_email_welcome']['config']['regionType']);
	$login = $w['login_region']['config'];
	pg_assert_same('{{tab:my_account}}', $login['account_page_id']);
	pg_assert_same('{{tab:staff}}', $login['staff_page_id']);
	pg_assert_same('{{tab:staff}}', $w['my_account']['config']['staff_page_id']);

	// My Account and the login menu lead to the member's own pages; the
	// community has no shop, so no orders and no address book.
	foreach (array('my_account', 'login_region') as $k) {
		$json = json_encode($w[$k]['tree']);
		foreach (array('my_posts', 'saved', 'my_profile', 'my_reports') as $key) pg_assert_contains('{{page:' . $key . '}}', $json, "$k: $key");
		pg_assert_contains('"href":"__profile_url"', $json, "$k: account details");
		pg_assert_false(strpos($json, 'order_history') !== false || strpos($json, '__address_book_url') !== false, "$k: no shop parts");
	}
	pg_assert_false(isset($w['address_book']), 'no address book widget');

	// The staff area leads to the moderation lists and the news form.
	$staff = json_encode(_test_sca_page('staff')['tree']);
	foreach (array('staff_reports', 'staff_posts', 'staff_members', 'blog_new', 'staff_entry', 'inbox') as $key) {
		pg_assert_contains('{{page:' . $key . '}}', $staff, "staff area: $key");
	}
	pg_assert_contains('"templateShared":"staff_bar"', $staff, 'staff area: staff bar');
}

function test_social_account_news_form_and_samples()
{
	$d = _test_sca_load();
	$form = _test_sca_page('blog_new')['form'];
	pg_assert_same(1, $form['collection']);
	pg_assert_same('cover_image', $form['share_image_field']);
	pg_assert_same('{{tab:blog}}', $form['confirmation_page_id']);
	$fields = _test_sca_fields($d['account']['widgets']['blog_form']['tree']);
	// The news cards ($news_card) and the news post page read these.
	foreach (array('title', 'summary', 'content', 'cover_image') as $f) pg_assert_true(in_array($f, $fields, true), "news form has $f");

	pg_assert_true(count($form['sample_records']) >= 4, 'sample posts');
	$text = '';
	foreach ($form['sample_records'] as $post) {
		foreach (array_keys($post) as $f) pg_assert_true(in_array($f, $fields, true), "sample field $f is a form field");
		pg_assert_true($post['title'] !== '' && $post['content'] !== '', 'sample has a title and words');
		pg_assert_false((bool)preg_match('/\b(19|20)\d\d\b/', $post['title'] . ' ' . $post['summary'] . ' ' . $post['content']), 'sample names no year: ' . $post['title']);
		$text .= ' ' . $post['title'] . ' ' . $post['summary'] . ' ' . $post['content'];
	}
	// The posts are about the community's own members.
	foreach (array('tom', 'ayla', 'sofia', 'lena') as $k) pg_assert_contains($d['members'][$k]['name'], $text, "a post names {$d['members'][$k]['name']}");

	// The directory lists the fields its form asks for.
	$staff_fields = _test_sca_fields($d['account']['widgets']['staff_form']['tree']);
	foreach ($d['account']['widgets']['staff_directory']['config']['search_fields'] as $f) pg_assert_true(in_array($f, $staff_fields, true), "directory searches the form's $f");
	pg_assert_same('{{tab:staff_entry}}', $d['account']['widgets']['staff_directory']['config']['custom_form_page_id']);
	pg_assert_same('full_name', $d['account']['widgets']['staff_directory']['config']['order_by_field']);
}

// The field ids are the template's own (sc-), so they never meet another
// template's on a site that mixes their pages.
function test_social_account_field_ids()
{
	$d = _test_sca_load();
	foreach (array('blog_form', 'staff_form') as $k) {
		$nodes = array();
		_test_sca_nodes($d['account']['widgets'][$k]['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['id'])) pg_assert_same(0, strpos($n['props']['id'], 'sc-'), "$k: id {$n['props']['id']} starts with sc-");
		}
	}
}

// Every literal has its Turkish.
function test_social_account_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-social/account.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	pg_assert_true(count($m[1]) > 100, 'literals found');
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "account.php: Turkish for '$key'");
	}
}
