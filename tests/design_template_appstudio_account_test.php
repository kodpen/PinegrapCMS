<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the account and staff part of the Mobile App Developer design
 * template (includes/design_templates/pinegrap-appstudio/account.php): its
 * pages load with their keys, folders and root trees; each page link names
 * a page of the finished template and each placed widget of its pages is
 * one it defines or one another part of the plan carries; the account
 * e-mails are wired to the registration and password widgets and the reset
 * button carries the set password link; the account menu leads to the
 * member's support requests and projects, with no orders; the staff area
 * is private and leads to the requests; the Dev Notes form has the fields
 * the lists read and its sample posts fill only those, with no fixed date.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The data part, the e-mail builders and the account part, in one scope as
// the template file reads them. Read once per run. Until the template's own
// mail.php is in, the Real Estate one stands in: it builds the same e-mail
// parts from the same builders.
function _test_ma_acc_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-appstudio';
	include $dir . '/parts.php';
	include is_file($dir . '/mail.php') ? $dir . '/mail.php' : PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-realestate/mail.php';
	$out = include $dir . '/account.php';
	$out['shared'] = array($account_bar_tree(), $header(), $footer(), $staff_bar());
	return $out;
}

function _test_ma_acc_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_ma_acc_nodes($child, $out);
	}
}

function _test_ma_acc_page($key)
{
	$d = _test_ma_acc_load();
	foreach ($d['pages'] as $p) if ($p['key'] === $key) return $p;
	return null;
}

function test_appstudio_account_pages_load()
{
	$d = _test_ma_acc_load();
	$keys = array();
	foreach ($d['pages'] as $p) {
		pg_assert_true(isset($p['key'], $p['name'], $p['title'], $p['tree'], $p['folder']), 'page has key, name, title, tree, folder');
		pg_assert_same('root', $p['tree']['type'], $p['key'] . ': root tree');
		pg_assert_same(false, $p['sitemap'], $p['key'] . ': out of the sitemap');
		$keys[] = $p['key'];
	}
	pg_assert_same(array('login', 'register', 'forgot_password', 'set_password', 'logout',
		'my_account', 'profile', 'change_password', 'email_preferences', 'address_book', 'staff_directory',
		'staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset'), $keys);
	foreach (array('my_account', 'profile', 'change_password', 'email_preferences', 'address_book', 'staff_directory') as $key) {
		pg_assert_same('registration', _test_ma_acc_page($key)['folder'], "$key: signed in");
	}
	foreach (array('staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset') as $key) {
		$p = _test_ma_acc_page($key);
		pg_assert_same('private', $p['folder'], "$key: private");
		pg_assert_same(true, $p['noindex'], "$key: noindex");
	}
	pg_assert_same('ecommerce', _test_ma_acc_page('address_book')['requires'], 'address book needs the shop');
}

// Each link names a page of the finished template (the plan's pages and
// this part's e-mails); each widget placed on this part's pages is defined
// here or is the newsletter band of the standard part; each widget's page
// is one of this part's.
function test_appstudio_account_links_and_widgets()
{
	$d = _test_ma_acc_load();
	$keys = array_flip(array(
		'home', 'services', 'process', 'about', 'faq', 'press',
		'app_new', 'apps', 'app',
		'release_new', 'releases', 'beta', 'beta_view', 'staff_beta',
		'support', 'support_view', 'my_tickets', 'staff_tickets', 'privacy', 'email_reply',
		'hire', 'project_view', 'my_projects', 'staff_projects',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
	));
	$own = array();
	foreach ($d['pages'] as $p) $own[$p['key']] = true;
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(array($d['pages'], $d['widgets'], $d['shared'])), $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($keys[$key]) || isset($own[$key]), "page link {{page:$key}} names a template page");

	$nodes = array();
	foreach ($d['pages'] as $p) _test_ma_acc_nodes($p['tree'], $nodes);
	foreach ($nodes as $n) {
		if (!isset($n['props']['templateWidget'])) continue;
		$w = $n['props']['templateWidget'];
		pg_assert_true(isset($d['widgets'][$w]) || $w === 'newsletter_band', "widget $w is defined");
	}
	foreach ($d['widgets'] as $k => $w) pg_assert_true($w['page'] === '' || isset($own[$w['page']]), "widget $k: page {$w['page']}");
	foreach (array('re-', 'pd-', 'lw-', 'pco-', 'plw-', 'pre-') as $prefix) {
		pg_assert_false(strpos(json_encode(array($d['pages'], $d['widgets'])), '"' . $prefix) !== false, "no $prefix id or class of another template");
	}
}

function test_appstudio_account_emails_menu_and_staff()
{
	$d = _test_ma_acc_load();
	$w = $d['widgets'];
	pg_assert_same('{{tab:email_account_welcome}}', $w['register']['config']['welcome_email_page_id']);
	pg_assert_same('{{tab:email_password_reset}}', $w['forgot_password']['config']['reset_email_page_id']);
	pg_assert_contains('"href":"__set_password_url"', json_encode($w['account_email_reset']['tree']), 'reset button carries the set password link');
	pg_assert_same('account_email', $w['account_email_welcome']['config']['regionType']);
	$login = $w['login_region']['config'];
	pg_assert_same('{{tab:my_account}}', $login['account_page_id']);
	pg_assert_same('{{tab:staff}}', $login['staff_page_id']);
	pg_assert_same('{{tab:staff}}', $w['my_account']['config']['staff_page_id']);

	// The account menu and the login region lead to the member's requests;
	// no orders, the apps sell in the stores.
	foreach (array('my_account', 'login_region') as $k) {
		$menu = json_encode($w[$k]['tree']);
		pg_assert_false(strpos($menu, 'order_history') !== false, "$k: no order history");
		foreach (array('my_tickets', 'my_projects', 'inbox') as $key) pg_assert_contains('{{page:' . $key . '}}', $menu, "$k links $key");
	}
	pg_assert_contains('"eo_visible_if":"is_staff"', json_encode($w['my_account']['tree']), 'staff link only for the staff');

	// The staff area leads to every staff list and form.
	$staff = json_encode(_test_ma_acc_page('staff')['tree']);
	foreach (array('staff_tickets', 'staff_projects', 'staff_beta', 'app_new', 'release_new', 'blog_new', 'inbox', 'staff_entry') as $key) {
		pg_assert_contains('{{page:' . $key . '}}', $staff, "staff area links $key");
	}
	pg_assert_contains('"staff_bar"', $staff, 'staff area under the staff bar');
}

function test_appstudio_account_blog_form_and_samples()
{
	$d = _test_ma_acc_load();
	$form = _test_ma_acc_page('blog_new')['form'];
	pg_assert_same(1, $form['collection']);
	pg_assert_same('cover_image', $form['share_image_field']);
	pg_assert_same('{{tab:blog}}', $form['confirmation_page_id']);
	$nodes = array();
	_test_ma_acc_nodes($d['widgets']['blog_form']['tree'], $nodes);
	$fields = array();
	foreach ($nodes as $n) {
		if (empty($n['props']['_attrs'])) continue;
		foreach ($n['props']['_attrs'] as $a) if ($a['name'] === 'name') $fields[] = $a['value'];
	}
	// The post card of parts.php and the post page read these.
	foreach (array('title', 'summary', 'content', 'cover_image') as $f) pg_assert_true(in_array($f, $fields, true), "blog form has $f");

	pg_assert_true(count($form['sample_records']) >= 4, 'sample posts');
	foreach ($form['sample_records'] as $post) {
		foreach (array_keys($post) as $f) pg_assert_true(in_array($f, $fields, true), "sample field $f is a form field");
		pg_assert_true($post['title'] !== '' && $post['content'] !== '', 'sample has a title and words');
	}
	$text = json_encode($form['sample_records'], JSON_UNESCAPED_UNICODE) . json_encode($d['pages'], JSON_UNESCAPED_UNICODE);
	pg_assert_false((bool)preg_match('/\b20[2-3]\d\b/', $text), 'no year');
}
