<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the account and staff part of the Web Designer Portfolio
 * design template (includes/design_templates/pinegrap-portfolio/account.php):
 * its pages load with their keys, folders and root trees; each page it
 * links is one of the template's pages; each widget it places is defined
 * and each of its widgets names one of its pages; the account e-mails are
 * wired to the registration and password widgets and the reset button
 * carries the set password link; the Journal form has the fields the lists
 * read and its sample posts fill only those, with no fixed date; the staff
 * area is private and leads to the briefs.
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
// mail.php is in, the Real Estate one stands in: it builds the same
// e-mail parts from the same builders.
function _test_pd_acc_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-portfolio';
	include $dir . '/parts.php';
	include is_file($dir . '/mail.php') ? $dir . '/mail.php' : PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-realestate/mail.php';
	$out = include $dir . '/account.php';
	$out['shared'] = array($account_bar_tree(), $header(), $footer(), $staff_bar());
	return $out;
}

function _test_pd_acc_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_pd_acc_nodes($child, $out);
	}
}

function _test_pd_acc_page($key)
{
	$d = _test_pd_acc_load();
	foreach ($d['pages'] as $p) if ($p['key'] === $key) return $p;
	return null;
}

function test_portfolio_account_pages_load()
{
	$d = _test_pd_acc_load();
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
		pg_assert_same('registration', _test_pd_acc_page($key)['folder'], "$key: signed in");
	}
	foreach (array('staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset') as $key) {
		$p = _test_pd_acc_page($key);
		pg_assert_same('private', $p['folder'], "$key: private");
		pg_assert_same(true, $p['noindex'], "$key: noindex");
	}
	pg_assert_same('ecommerce', _test_pd_acc_page('address_book')['requires'], 'address book needs the shop');
}

// Each link names a page of this part or of the template's other parts;
// each placed widget is defined here or is one the other parts carry; each
// widget of this part draws on one of its pages.
function test_portfolio_account_links_and_widgets()
{
	$d = _test_pd_acc_load();
	$keys = array();
	foreach ($d['pages'] as $p) $keys[$p['key']] = true;
	$others = array('home', 'work', 'project_new', 'brief', 'my_briefs', 'staff_briefs', 'privacy', 'inbox', 'blog', 'contact', 'services', 'process', 'about', 'faq', 'search');
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($d['pages']) . json_encode($d['widgets']), $m);
	foreach (array_unique($m[1]) as $key) {
		pg_assert_true(isset($keys[$key]) || in_array($key, $others, true), "page link {{page:$key}} names a template page");
	}

	$nodes = array();
	foreach ($d['pages'] as $p) _test_pd_acc_nodes($p['tree'], $nodes);
	foreach ($d['shared'] as $t) _test_pd_acc_nodes($t, $nodes);
	$elsewhere = array('newsletter_band');
	foreach ($nodes as $n) {
		if (!isset($n['props']['templateWidget'])) continue;
		$k = $n['props']['templateWidget'];
		pg_assert_true(isset($d['widgets'][$k]) || in_array($k, $elsewhere, true), "widget $k is defined");
	}
	foreach ($d['widgets'] as $k => $w) pg_assert_true($w['page'] === '' || isset($keys[$w['page']]), "widget $k: page {$w['page']}");

	// Field ids carry the template's own prefix, no other template's.
	$json = json_encode($d);
	foreach (array('"re-', '"lw-', '"plw-', '"pf-') as $prefix) {
		pg_assert_false(strpos($json, $prefix) !== false, "no $prefix id or class of another template");
	}
}

function test_portfolio_account_emails_and_menus()
{
	$d = _test_pd_acc_load();
	$w = $d['widgets'];
	pg_assert_same('{{tab:email_account_welcome}}', $w['register']['config']['welcome_email_page_id']);
	pg_assert_same('{{tab:email_password_reset}}', $w['forgot_password']['config']['reset_email_page_id']);
	pg_assert_contains('"href":"__set_password_url"', json_encode($w['account_email_reset']['tree']), 'reset button carries the set password link');
	pg_assert_same('account_email', $w['account_email_welcome']['config']['regionType']);
	pg_assert_same('account_email', $w['account_email_reset']['config']['regionType']);
	$login = $w['login_region']['config'];
	pg_assert_same('{{tab:my_account}}', $login['account_page_id']);
	pg_assert_same('{{tab:staff}}', $login['staff_page_id']);
	pg_assert_same('{{tab:staff}}', $w['my_account']['config']['staff_page_id']);

	// The account menu leads to the client's briefs; no orders, a website
	// is quoted, not bought.
	$menu = json_encode($w['my_account']['tree']);
	pg_assert_false(strpos($menu, 'order_history') !== false, 'no order history');
	foreach (array('my_briefs', 'brief', 'inbox') as $key) pg_assert_contains('{{page:' . $key . '}}', $menu, "account menu links $key");
	pg_assert_contains('{{page:my_briefs}}', json_encode($w['login_region']['tree']), 'login menu links the briefs');

	// The staff area leads to the briefs and the case study form.
	$staff = json_encode(_test_pd_acc_page('staff')['tree']);
	foreach (array('staff_briefs', 'project_new', 'blog_new', 'inbox') as $key) pg_assert_contains('{{page:' . $key . '}}', $staff, "staff area links $key");
}

// The Journal form names the fields the post card and the post page read;
// the sample posts fill only those, and carry no date (the date is the day
// the form is made).
function test_portfolio_account_journal_form()
{
	$d = _test_pd_acc_load();
	$names = array();
	$nodes = array();
	_test_pd_acc_nodes($d['widgets']['blog_form']['tree'], $nodes);
	foreach ($nodes as $n) {
		if (empty($n['props']['_attrs'])) continue;
		foreach ($n['props']['_attrs'] as $a) if ($a['name'] === 'name') $names[] = $a['value'];
	}
	pg_assert_same(array('title', 'summary', 'cover_image', 'content'), $names);

	$form = _test_pd_acc_page('blog_new')['form'];
	pg_assert_same(1, $form['collection']);
	pg_assert_same('cover_image', $form['share_image_field']);
	pg_assert_same('{{tab:blog}}', $form['confirmation_page_id']);
	pg_assert_true(count($form['sample_records']) >= 3, 'at least three sample posts');
	foreach ($form['sample_records'] as $post) {
		pg_assert_same(array(), array_diff(array_keys($post), $names), 'a sample post fills only form fields');
		pg_assert_true($post['title'] !== '' && $post['summary'] !== '' && $post['content'] !== '', 'a sample post has its words');
	}
}

// The blog's cover is shown as an <img> on the post cards, the post page
// and the share image: its input takes images only and marks the field as
// an image, so a PDF or Word file is refused.
function test_portfolio_account_blog_cover_takes_images_only()
{
	$d = _test_pd_acc_load()['widgets']['blog_form']['tree'];
	$nodes = array();
	_test_pd_acc_nodes($d, $nodes);
	$cover = null;
	foreach ($nodes as $n) {
		if (empty($n['props']['_attrs'])) continue;
		foreach ($n['props']['_attrs'] as $a) if ($a['name'] === 'name' && $a['value'] === 'cover_image') $cover = $n;
	}
	pg_assert_true($cover !== null, 'blog form has a cover input');
	$accept = null;
	foreach ($cover['props']['_attrs'] as $a) if ($a['name'] === 'accept') $accept = $a['value'];
	pg_assert_same('image/*', $accept);
	pg_assert_same('image', $cover['props']['_cf']['kind'] ?? null);
}
