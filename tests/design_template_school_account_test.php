<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the account and staff part of the Private School design
 * template (includes/design_templates/pinegrap-school/account.php): its
 * pages load with their keys, folders and root trees; with every part
 * loaded, each page link and each placed widget of the template names a
 * page or widget it carries; the account e-mails are wired to the
 * registration and password widgets and the reset button carries the set
 * password link; the blog form has the fields the lists read and its
 * sample posts fill only those, with no fixed date; the staff area places
 * the newest admission applications and is private; the account menu leads
 * to the applications and the visit request; the template's own page list
 * matches what the parts carry.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Every part of the template as the template file reads them, in one
// scope. Read once per run.
function _test_sha6_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-school';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array();
	foreach (array('pages_public', 'events', 'admissions', 'standard', 'account') as $name) {
		$part = include $dir . '/' . $name . '.php';
		$parts[$name] = array('pages' => $part['pages'], 'widgets' => $part['widgets']);
	}
	$out = array(
		'parts'  => $parts,
		'shared' => array($account_bar_tree(), $header(), $footer(), $admissions_band(), $notice(), $staff_bar(), $email_header_tree(), $email_footer_tree()),
	);
	return $out;
}

function _test_sha6_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_sha6_nodes($child, $out);
	}
}

function _test_sha6_page($key)
{
	$d = _test_sha6_load();
	foreach ($d['parts']['account']['pages'] as $p) if ($p['key'] === $key) return $p;
	return null;
}

function test_school_account_pages_load()
{
	$d = _test_sha6_load();
	$keys = array();
	foreach ($d['parts']['account']['pages'] as $p) {
		pg_assert_true(isset($p['key'], $p['name'], $p['title'], $p['tree'], $p['folder']), 'page has key, name, title, tree, folder');
		pg_assert_same('root', $p['tree']['type'], $p['key'] . ': root tree');
		pg_assert_same(false, $p['sitemap'], $p['key'] . ': out of the sitemap');
		$keys[] = $p['key'];
	}
	pg_assert_same(array('login', 'register', 'forgot_password', 'set_password', 'logout',
		'my_account', 'profile', 'change_password', 'email_preferences', 'address_book', 'staff_directory',
		'staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset'), $keys);
	foreach (array('my_account', 'profile', 'change_password', 'email_preferences', 'address_book', 'staff_directory') as $key) {
		pg_assert_same('registration', _test_sha6_page($key)['folder'], "$key: signed in");
	}
	foreach (array('staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset') as $key) {
		$p = _test_sha6_page($key);
		pg_assert_same('private', $p['folder'], "$key: private");
		pg_assert_same(true, $p['noindex'], "$key: noindex");
	}
	pg_assert_same('ecommerce', _test_sha6_page('address_book')['requires'], 'address book needs the shop');
}

// With every part loaded, the template is whole: each link names a page it
// carries, each placed widget is defined, each widget's page exists.
function test_school_account_template_is_whole()
{
	$d = _test_sha6_load();
	$keys = array();
	$widgets = array();
	foreach ($d['parts'] as $part) {
		foreach ($part['pages'] as $p) {
			pg_assert_false(isset($keys[$p['key']]), 'page key ' . $p['key'] . ' is unique');
			$keys[$p['key']] = true;
		}
		foreach ($part['widgets'] as $k => $w) {
			pg_assert_false(isset($widgets[$k]), "widget key $k is unique");
			$widgets[$k] = $w;
		}
	}
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(array($d['parts'], $d['shared'])), $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($keys[$key]), "page link {{page:$key}} names a template page");

	$trees = $d['shared'];
	foreach ($d['parts'] as $part) foreach ($part['pages'] as $p) $trees[] = $p['tree'];
	$nodes = array();
	foreach ($trees as $t) _test_sha6_nodes($t, $nodes);
	foreach ($nodes as $n) {
		if (isset($n['props']['templateWidget'])) pg_assert_true(isset($widgets[$n['props']['templateWidget']]), 'widget ' . $n['props']['templateWidget'] . ' is defined');
	}
	foreach ($widgets as $k => $w) pg_assert_true($w['page'] === '' || isset($keys[$w['page']]), "widget $k: page {$w['page']}");
}

function test_school_account_emails_and_widgets()
{
	$d = _test_sha6_load();
	$w = $d['parts']['account']['widgets'];
	pg_assert_same('{{tab:email_account_welcome}}', $w['register']['config']['welcome_email_page_id']);
	pg_assert_same('{{tab:email_password_reset}}', $w['forgot_password']['config']['reset_email_page_id']);
	pg_assert_contains('"href":"__set_password_url"', json_encode($w['account_email_reset']['tree']), 'reset button carries the set password link');
	pg_assert_same('account_email', $w['account_email_welcome']['config']['regionType']);
	$login = $w['login_region']['config'];
	pg_assert_same('{{tab:my_account}}', $login['account_page_id']);
	pg_assert_same('{{tab:staff}}', $login['staff_page_id']);
	pg_assert_same('{{tab:staff}}', $w['my_account']['config']['staff_page_id']);
	$region = json_encode($w['login_region']['tree']);
	foreach (array('my_applications', 'inbox') as $key) pg_assert_contains('{{page:' . $key . '}}', $region, "account bar menu links $key");
	pg_assert_false(strpos($region, '{{page:my_quotes}}') !== false, 'no quote requests in the account bar');

	// The staff area places the newest admission applications, without the
	// search the full staff list has.
	pg_assert_contains('"templateWidget":"staff_applications_new"', json_encode(_test_sha6_page('staff')['tree']), 'staff page places the applications');
	$list = $w['staff_applications_new'];
	pg_assert_same('staff', $list['page']);
	pg_assert_same(10, $list['config']['items_per_page']);
	pg_assert_same('{{tab:apply}}', $list['config']['custom_form_page_id']);
	pg_assert_same('{{tab:application_view}}', $list['config']['detail_page_id']);
	pg_assert_false(isset($list['config']['search_enabled']) || isset($list['config']['viewer_filter']), 'no search, no viewer filter');

	// The account menu leads to the parent's applications and the visit
	// request; no orders, the school takes no payment online.
	$menu = json_encode($w['my_account']['tree']);
	pg_assert_false(strpos($menu, 'order_history') !== false, 'no order history');
	$json = json_encode($d['parts']['account']);
	pg_assert_false(strpos($json, 'cn-') !== false || strpos($json, 'pco-') !== false, 'no other template\'s ids');
	foreach (array('my_applications', 'apply', 'visit', 'inbox') as $key) pg_assert_contains('{{page:' . $key . '}}', $menu, "account menu links $key");
}

function test_school_account_blog_form_and_samples()
{
	$d = _test_sha6_load();
	$form = _test_sha6_page('blog_new')['form'];
	pg_assert_same(1, $form['collection']);
	pg_assert_same('cover_image', $form['share_image_field']);
	$nodes = array();
	_test_sha6_nodes($d['parts']['account']['widgets']['blog_form']['tree'], $nodes);
	$fields = array();
	foreach ($nodes as $n) {
		if (empty($n['props']['_attrs'])) continue;
		foreach ($n['props']['_attrs'] as $a) if ($a['name'] === 'name') $fields[] = $a['value'];
	}
	// The lists and the post page (standard.php) read these.
	foreach (array('title', 'summary', 'content', 'cover_image') as $f) pg_assert_true(in_array($f, $fields, true), "blog form has $f");

	pg_assert_true(count($form['sample_records']) >= 4, 'sample posts');
	foreach ($form['sample_records'] as $post) {
		foreach (array_keys($post) as $f) pg_assert_true(in_array($f, $fields, true), "sample field $f is a form field");
		pg_assert_true($post['title'] !== '' && $post['content'] !== '', 'sample has a title and words');
	}
	$text = json_encode($form['sample_records'], JSON_UNESCAPED_UNICODE) . json_encode($d['parts']['account']['pages'], JSON_UNESCAPED_UNICODE);
	pg_assert_false((bool)preg_match('/\b20[2-3]\d\b/', $text), 'no year');
}

// The blog's cover is shown as an <img> on the post cards, the post page
// and the share image: its input takes images only and marks the field as
// an image, so a PDF or Word file is refused.
function test_school_account_blog_cover_takes_images_only()
{
	$d = _test_sha6_load()['parts']['account']['widgets']['blog_form']['tree'];
	$nodes = array();
	_test_sha6_nodes($d, $nodes);
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

// The template's page list (_test_sh_page_keys(), shared by the other
// tests) is exactly what the parts carry.
function test_school_account_page_list_matches_the_parts()
{
	require_once(__DIR__ . '/design_template_school_test.php');
	$keys = array();
	foreach (_test_sha6_load()['parts'] as $part) foreach ($part['pages'] as $p) $keys[] = $p['key'];
	$expected = _test_sh_page_keys();
	sort($keys);
	sort($expected);
	pg_assert_same($expected, $keys);
}
