<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the account and staff part of the Car Dealership design
 * template (includes/design_templates/pinegrap-dealership/account.php): its
 * pages load with their keys, folders and root trees; each page link names
 * a page of the finished template and each widget it places is its own;
 * the account e-mails are wired to the registration and password widgets
 * and the reset button carries the set password link; the account menu
 * leads to the customer's requests and no orders; the staff area is
 * private and leads to every staff list; the blog form has the fields the
 * lists read and its sample posts fill only those, with no fixed date.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// parts.php, the mail helpers and account.php in one scope, as the
// template file reads them. The mail helpers have the same names in every
// template; until the dealership's own mail.php is there, Real Estate's
// stands in. Read once per run.
function _test_dl_acc_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-dealership';
	include $dir . '/parts.php';
	include is_file($dir . '/mail.php') ? $dir . '/mail.php' : PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-realestate/mail.php';
	$part = include $dir . '/account.php';
	$out = array('pages' => $part['pages'], 'widgets' => $part['widgets']);
	return $out;
}

function _test_dl_acc_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_dl_acc_nodes($child, $out);
	}
}

function _test_dl_acc_page($key)
{
	$d = _test_dl_acc_load();
	foreach ($d['pages'] as $p) if ($p['key'] === $key) return $p;
	return null;
}

function test_dealership_account_pages_load()
{
	$d = _test_dl_acc_load();
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
		pg_assert_same('registration', _test_dl_acc_page($key)['folder'], "$key: signed in");
	}
	foreach (array('staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset') as $key) {
		$p = _test_dl_acc_page($key);
		pg_assert_same('private', $p['folder'], "$key: private");
		pg_assert_same(true, $p['noindex'], "$key: noindex");
	}
	pg_assert_same('ecommerce', _test_dl_acc_page('address_book')['requires'], 'address book needs the shop');
}

// Each link names a page of the finished template (the plan's pages), each
// placed widget is this part's own and each widget's page is here.
function test_dealership_account_links_and_widgets()
{
	$d = _test_dl_acc_load();
	$template_keys = array(
		'home', 'service', 'finance', 'about', 'visit', 'faq',
		'vehicle_new', 'vehicles', 'new_cars', 'used_cars', 'vehicle',
		'test_drive', 'test_drive_view', 'my_test_drives', 'staff_test_drives',
		'tradein', 'tradein_view', 'my_tradeins', 'staff_tradeins', 'privacy', 'email_reply',
		'finance_apply', 'finance_view', 'my_finance', 'staff_finance',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
	);
	$keys = array_flip($template_keys);
	foreach ($d['pages'] as $p) $keys[$p['key']] = true;
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($d), $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($keys[$key]), "page link {{page:$key}} names a template page");

	$nodes = array();
	foreach ($d['pages'] as $p) _test_dl_acc_nodes($p['tree'], $nodes);
	foreach ($nodes as $n) {
		if (isset($n['props']['templateWidget'])) pg_assert_true(isset($d['widgets'][$n['props']['templateWidget']]), 'widget ' . $n['props']['templateWidget'] . ' is defined');
	}
	foreach ($d['widgets'] as $k => $w) {
		pg_assert_true($w['page'] === '' || _test_dl_acc_page($w['page']) !== null, "widget $k: page {$w['page']}");
	}

	// Only this template's ids and classes.
	foreach (array('re-', 'pre-', 'pas-', 'lw-', 'plw-') as $prefix) {
		pg_assert_false(strpos(json_encode($d), '"' . $prefix) !== false, "no $prefix id or class of another template");
	}
}

function test_dealership_account_emails_menu_and_staff()
{
	$d = _test_dl_acc_load();
	$w = $d['widgets'];
	pg_assert_same('{{tab:email_account_welcome}}', $w['register']['config']['welcome_email_page_id']);
	pg_assert_same('{{tab:email_password_reset}}', $w['forgot_password']['config']['reset_email_page_id']);
	pg_assert_contains('"href":"__set_password_url"', json_encode($w['account_email_reset']['tree']), 'reset button carries the set password link');
	pg_assert_same('account_email', $w['account_email_welcome']['config']['regionType']);
	$login = $w['login_region']['config'];
	pg_assert_same('{{tab:my_account}}', $login['account_page_id']);
	pg_assert_same('{{tab:staff}}', $login['staff_page_id']);
	pg_assert_same('{{tab:staff}}', $w['my_account']['config']['staff_page_id']);
	pg_assert_same(false, $w['my_account']['config']['show_submissions']);

	// The account menu and the login region lead to the customer's
	// requests; no orders, a car is sold in the showroom.
	foreach (array('my_account', 'login_region') as $k) {
		$menu = json_encode($w[$k]['tree']);
		pg_assert_false(strpos($menu, 'order_history') !== false, "$k: no order history");
		foreach (array('my_test_drives', 'my_tradeins', 'my_finance', 'inbox') as $key) pg_assert_contains('{{page:' . $key . '}}', $menu, "$k links $key");
	}

	// The staff area leads to every list the staff work from.
	$staff = json_encode(_test_dl_acc_page('staff')['tree']);
	foreach (array('vehicle_new', 'staff_test_drives', 'staff_tradeins', 'staff_finance', 'inbox', 'blog_new', 'staff_entry') as $key) {
		pg_assert_contains('{{page:' . $key . '}}', $staff, "staff area links $key");
	}
}

function test_dealership_account_blog_form_and_samples()
{
	$d = _test_dl_acc_load();
	$form = _test_dl_acc_page('blog_new')['form'];
	pg_assert_same(1, $form['collection']);
	pg_assert_same('cover_image', $form['share_image_field']);
	pg_assert_same('{{tab:blog}}', $form['confirmation_page_id']);
	$nodes = array();
	_test_dl_acc_nodes($d['widgets']['blog_form']['tree'], $nodes);
	$fields = array();
	foreach ($nodes as $n) {
		if (empty($n['props']['_attrs'])) continue;
		foreach ($n['props']['_attrs'] as $a) if ($a['name'] === 'name') $fields[] = $a['value'];
	}
	// The post card (parts.php) and the post page read these.
	foreach (array('title', 'summary', 'content', 'cover_image') as $f) pg_assert_true(in_array($f, $fields, true), "blog form has $f");

	pg_assert_true(count($form['sample_records']) >= 4, 'sample posts');
	foreach ($form['sample_records'] as $post) {
		foreach (array_keys($post) as $f) pg_assert_true(in_array($f, $fields, true), "sample field $f is a form field");
		pg_assert_true($post['title'] !== '' && $post['content'] !== '', 'sample has a title and words');
	}
	$text = json_encode($form['sample_records'], JSON_UNESCAPED_UNICODE) . json_encode($d['pages'], JSON_UNESCAPED_UNICODE);
	pg_assert_false((bool)preg_match('/\b20[2-3]\d\b/', $text), 'no year');
}

// The cover feeds the post cards, the post page's picture and the share
// image: its file field takes images only and says so (_cf kind image).
function test_dealership_account_blog_cover_is_image_only()
{
	$d = _test_dl_acc_load();
	$nodes = array();
	_test_dl_acc_nodes($d['widgets']['blog_form']['tree'], $nodes);
	$cover = null;
	foreach ($nodes as $n) {
		if (empty($n['props']['_attrs'])) continue;
		foreach ($n['props']['_attrs'] as $a) if ($a['name'] === 'name' && $a['value'] === 'cover_image') $cover = $n;
	}
	pg_assert_true($cover !== null, 'blog form has the cover field');
	$accept = null;
	foreach ($cover['props']['_attrs'] as $a) if ($a['name'] === 'accept') $accept = $a['value'];
	pg_assert_same('image/*', $accept, 'cover accepts images only');
	pg_assert_same('image', isset($cover['props']['_cf']['kind']) ? $cover['props']['_cf']['kind'] : null, 'cover is an image field');
}
