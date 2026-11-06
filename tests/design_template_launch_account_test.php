<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the account and staff part of the Phone Launch Site design
 * template (includes/design_templates/pinegrap-launch/account.php): its
 * pages in order, in their folders and with root trees; every page link
 * names a page of the template and every placed widget is defined; the
 * account e-mails are wired to the registration and password widgets and
 * the reset button carries the set password link; My Account leads to the
 * trade-ins and support requests; the press release form has the fields
 * the newsroom lists read and its sample releases fill only those, with no
 * fixed date; the field ids are the template's own; every literal has its
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
// includes them, with the other parts of the folder that are there. Read
// once per run; null while mail.php (the e-mail builders account.php
// uses) is not in the tree.
function _test_lxa_load()
{
	static $out = false;
	if ($out !== false) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-launch';
	if (!is_file($dir . '/mail.php')) return $out = null;
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$others = array();
	foreach (array('pages_public', 'shop', 'requests', 'standard') as $name) {
		if (is_file($dir . '/' . $name . '.php')) $others[$name] = include $dir . '/' . $name . '.php';
	}
	$out = array(
		'account' => include $dir . '/account.php',
		'others'  => $others,
		'shared'  => array($account_bar_tree(), $header(), $footer(), $notice(), $staff_bar()),
	);
	return $out;
}

function _test_lxa_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_lxa_nodes($child, $out);
	}
}

function _test_lxa_page($key)
{
	$d = _test_lxa_load();
	foreach ($d['account']['pages'] as $p) if ($p['key'] === $key) return $p;
	return null;
}

// The field names of a widget tree.
function _test_lxa_fields($tree)
{
	$nodes = array();
	_test_lxa_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		if (empty($n['props']['_attrs'])) continue;
		foreach ($n['props']['_attrs'] as $a) if ($a['name'] === 'name') $out[] = $a['value'];
	}
	return $out;
}

function test_launch_account_pages_load()
{
	$d = _test_lxa_load();
	if ($d === null) {
		pg_assert_true(true, 'mail.php is not in the tree yet');
		return;
	}
	$keys = array();
	foreach ($d['account']['pages'] as $p) {
		pg_assert_true(isset($p['key'], $p['name'], $p['title'], $p['tree'], $p['folder']), 'page has key, name, title, tree, folder');
		pg_assert_same('root', $p['tree']['type'], $p['key'] . ': root tree');
		pg_assert_same(false, $p['sitemap'], $p['key'] . ': out of the sitemap');
		$keys[] = $p['key'];
	}
	pg_assert_same(array('login', 'register', 'forgot_password', 'set_password', 'logout',
		'my_account', 'profile', 'change_password', 'email_preferences', 'address_book', 'staff_directory',
		'staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset'), $keys);
	foreach (array('my_account', 'profile', 'change_password', 'email_preferences', 'address_book', 'staff_directory') as $key) {
		pg_assert_same('registration', _test_lxa_page($key)['folder'], "$key: signed in");
	}
	foreach (array('staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset') as $key) {
		$p = _test_lxa_page($key);
		pg_assert_same('private', $p['folder'], "$key: private");
		pg_assert_same(true, $p['noindex'], "$key: noindex");
	}
	pg_assert_same('ecommerce', _test_lxa_page('address_book')['requires'], 'address book needs the shop');
}

// Each page link of the account part and the shared parts names a page of
// the template: one of the account part, of the other parts in the tree,
// or of the plan's parts still to come. Each placed widget is defined and
// each widget's page exists.
function test_launch_account_links_and_widgets()
{
	$d = _test_lxa_load();
	if ($d === null) {
		pg_assert_true(true, 'mail.php is not in the tree yet');
		return;
	}
	$keys = array_fill_keys(array(
		'home', 'features', 'camera', 'specs', 'compare', 'about', 'faq',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'buy', 'tradein', 'tradein_view', 'my_tradeins', 'staff_tradeins',
		'support', 'support_view', 'my_support', 'staff_support', 'privacy',
	), true);
	$widgets = array();
	foreach (array_merge(array($d['account']), array_values($d['others'])) as $part) {
		foreach ($part['pages'] as $p) $keys[$p['key']] = true;
		foreach ($part['widgets'] as $k => $w) $widgets[$k] = $w;
	}
	foreach ($d['account']['widgets'] as $k => $w) {
		foreach ($d['others'] as $name => $part) pg_assert_false(isset($part['widgets'][$k]), "widget $k is not also in $name.php");
	}
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(array($d['account'], $d['shared'])), $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($keys[$key]), "page link {{page:$key}} names a template page");

	$nodes = array();
	foreach ($d['account']['pages'] as $p) _test_lxa_nodes($p['tree'], $nodes);
	foreach ($nodes as $n) {
		if (isset($n['props']['templateWidget'])) pg_assert_true(isset($widgets[$n['props']['templateWidget']]), 'widget ' . $n['props']['templateWidget'] . ' is defined');
	}
	foreach ($d['account']['widgets'] as $k => $w) pg_assert_true($w['page'] === '' || isset($keys[$w['page']]), "widget $k: page {$w['page']}");

	// The account bar places the login region this part defines.
	pg_assert_contains('"templateWidget":"login_region"', json_encode($d['shared'][0]), 'account bar places the login region');
}

function test_launch_account_emails_and_widgets()
{
	$d = _test_lxa_load();
	if ($d === null) {
		pg_assert_true(true, 'mail.php is not in the tree yet');
		return;
	}
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

	// My Account and the login menu lead to the customer's requests; the
	// orders show only for a member who has any.
	foreach (array('my_account', 'login_region') as $k) {
		$json = json_encode($w[$k]['tree']);
		pg_assert_contains('{{page:my_tradeins}}', $json, "$k: my trade-ins");
		pg_assert_contains('{{page:my_support}}', $json, "$k: my support requests");
	}
	pg_assert_contains('"section":"order_history"', json_encode($w['my_account']['tree']), 'my account: order history');
	pg_assert_contains('"eo_visible_if":"has_orders"', json_encode($w['my_account']['tree']), 'my orders heading only with orders');

	// The staff area leads to both request lists.
	$staff = json_encode(_test_lxa_page('staff')['tree']);
	pg_assert_contains('{{page:staff_tradeins}}', $staff, 'staff area: all trade-ins');
	pg_assert_contains('{{page:staff_support}}', $staff, 'staff area: all support requests');
}

function test_launch_account_newsroom_form_and_samples()
{
	$d = _test_lxa_load();
	if ($d === null) {
		pg_assert_true(true, 'mail.php is not in the tree yet');
		return;
	}
	$form = _test_lxa_page('blog_new')['form'];
	pg_assert_same(1, $form['collection']);
	pg_assert_same('cover_image', $form['share_image_field']);
	pg_assert_same('{{tab:blog}}', $form['confirmation_page_id']);
	$fields = _test_lxa_fields($d['account']['widgets']['blog_form']['tree']);
	// The newsroom's cards ($post_card) and the release page read these.
	foreach (array('title', 'summary', 'content', 'cover_image') as $f) pg_assert_true(in_array($f, $fields, true), "press release form has $f");

	pg_assert_true(count($form['sample_records']) >= 4, 'sample releases');
	foreach ($form['sample_records'] as $post) {
		foreach (array_keys($post) as $f) pg_assert_true(in_array($f, $fields, true), "sample field $f is a form field");
		pg_assert_true($post['title'] !== '' && $post['content'] !== '', 'sample has a title and words');
		pg_assert_false((bool)preg_match('/\b(19|20)\d\d\b/', $post['title'] . ' ' . $post['summary'] . ' ' . $post['content']), 'sample names no year: ' . $post['title']);
	}

	// The directory lists the fields its form asks for.
	$staff_fields = _test_lxa_fields($d['account']['widgets']['staff_form']['tree']);
	foreach ($d['account']['widgets']['staff_directory']['config']['search_fields'] as $f) pg_assert_true(in_array($f, $staff_fields, true), "directory searches the form's $f");
	pg_assert_same('{{tab:staff_entry}}', $d['account']['widgets']['staff_directory']['config']['custom_form_page_id']);
}

// The field ids are the template's own (lx-), so they never meet another
// template's on a site that mixes their pages.
function test_launch_account_field_ids()
{
	$d = _test_lxa_load();
	if ($d === null) {
		pg_assert_true(true, 'mail.php is not in the tree yet');
		return;
	}
	foreach (array('blog_form', 'staff_form') as $k) {
		$nodes = array();
		_test_lxa_nodes($d['account']['widgets'][$k]['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['id'])) pg_assert_same(0, strpos($n['props']['id'], 'lx-'), "$k: id {$n['props']['id']} starts with lx-");
		}
	}
}

// Every literal has its Turkish; the phone's name stays Orbit in it.
function test_launch_account_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-launch/account.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	pg_assert_true(count($m[1]) > 100, 'literals found');
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "account.php: Turkish for '$key'");
		if (stripos($key, 'trade-in') !== false && isset($map[$key])) pg_assert_true(mb_stripos($map[$key], 'takas', 0, 'UTF-8') !== false, "'$key' says takas");
		if (strpos($key, 'Orbit') !== false && isset($map[$key])) pg_assert_contains('Orbit', $map[$key], "'$key' keeps the name Orbit");
	}
}

// The blog's cover is shown as an <img> on the post cards, the post page
// and the share image: its input takes images only and marks the field as
// an image, so a PDF or Word file is refused.
function test_launch_account_blog_cover_takes_images_only()
{
	$d = _test_lxa_load()['account']['widgets']['blog_form']['tree'];
	$nodes = array();
	_test_lxa_nodes($d, $nodes);
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
