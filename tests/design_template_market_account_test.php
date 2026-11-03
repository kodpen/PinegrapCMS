<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the account and staff part of the Marketplace design template
 * (includes/design_templates/pinegrap-market/account.php): its pages in
 * order, in their folders and with root trees; every page link names a
 * page of the template and every placed widget is defined; the account
 * e-mails are wired to the registration and password widgets and the reset
 * button carries the set password link; My Account leads to the help
 * requests and the applications; the staff area leads to both staff lists
 * and the new maker form; the Journal form has the fields the post cards
 * read and its sample stories fill only those, with no fixed date, naming
 * the market's own makers; the field ids are the template's own; every
 * literal has its Turkish.
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
function _test_mka_load()
{
	static $out = false;
	if ($out !== false) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-market';
	if (!is_file($dir . '/mail.php')) return $out = null;
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$others = array();
	foreach (array('pages_public', 'shop', 'sellers', 'requests', 'standard') as $name) {
		if (is_file($dir . '/' . $name . '.php')) $others[$name] = include $dir . '/' . $name . '.php';
	}
	$out = array(
		'account' => include $dir . '/account.php',
		'others'  => $others,
		'shared'  => array($account_bar_tree(), $header(), $footer(), $notice(), $staff_bar()),
		'sellers' => $sellers,
	);
	return $out;
}

function _test_mka_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_mka_nodes($child, $out);
	}
}

function _test_mka_page($key)
{
	$d = _test_mka_load();
	foreach ($d['account']['pages'] as $p) if ($p['key'] === $key) return $p;
	return null;
}

// The field names of a widget tree.
function _test_mka_fields($tree)
{
	$nodes = array();
	_test_mka_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		if (empty($n['props']['_attrs'])) continue;
		foreach ($n['props']['_attrs'] as $a) if ($a['name'] === 'name') $out[] = $a['value'];
	}
	return $out;
}

function test_market_account_pages_load()
{
	$d = _test_mka_load();
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
		pg_assert_same('registration', _test_mka_page($key)['folder'], "$key: signed in");
	}
	foreach (array('staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset') as $key) {
		$p = _test_mka_page($key);
		pg_assert_same('private', $p['folder'], "$key: private");
		pg_assert_same(true, $p['noindex'], "$key: noindex");
	}
	// Only the address book needs the shop: the rest stands without it.
	foreach ($d['account']['pages'] as $p) {
		pg_assert_same($p['key'] === 'address_book' ? 'ecommerce' : null, isset($p['requires']) ? $p['requires'] : null, $p['key'] . ': requires');
	}
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
function test_market_account_links_and_widgets()
{
	$d = _test_mka_load();
	if ($d === null) {
		pg_assert_true(true, 'mail.php is not in the tree yet');
		return;
	}
	$keys = array_fill_keys(array(
		'home', 'categories', 'how_it_works', 'sell', 'about', 'faq', 'gift_guide',
		'shop', 'product', 'cart', 'checkout', 'order', 'email_order',
		'seller_new', 'sellers', 'seller',
		'apply', 'application_view', 'my_applications', 'staff_applications',
		'help', 'help_view', 'my_help', 'staff_help', 'privacy', 'email_reply',
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
	foreach ($d['account']['pages'] as $p) _test_mka_nodes($p['tree'], $nodes);
	foreach ($nodes as $n) {
		if (isset($n['props']['templateWidget'])) pg_assert_true(isset($widgets[$n['props']['templateWidget']]), 'widget ' . $n['props']['templateWidget'] . ' is defined');
	}
	foreach ($d['account']['widgets'] as $k => $w) pg_assert_true($w['page'] === '' || isset($keys[$w['page']]), "widget $k: page {$w['page']}");

	// The account bar places the login region this part defines.
	pg_assert_contains('"templateWidget":"login_region"', json_encode($d['shared'][0]), 'account bar places the login region');
	pg_assert_true(isset($d['account']['widgets']['login_region']), 'login region is defined here');
}

function test_market_account_emails_and_widgets()
{
	$d = _test_mka_load();
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

	// My Account and the login menu lead to the member's requests; the
	// orders show only for a member who has any.
	foreach (array('my_account', 'login_region') as $k) {
		$json = json_encode($w[$k]['tree']);
		pg_assert_contains('{{page:my_help}}', $json, "$k: my help requests");
		pg_assert_contains('{{page:my_applications}}', $json, "$k: my applications");
	}
	pg_assert_contains('"section":"order_history"', json_encode($w['my_account']['tree']), 'my account: order history');
	pg_assert_contains('"eo_visible_if":"has_orders"', json_encode($w['my_account']['tree']), 'my orders heading only with orders');

	// The staff area leads to both request lists and the new maker form.
	$staff = json_encode(_test_mka_page('staff')['tree']);
	pg_assert_contains('{{page:staff_applications}}', $staff, 'staff area: all applications');
	pg_assert_contains('{{page:staff_help}}', $staff, 'staff area: all help requests');
	pg_assert_contains('{{page:seller_new}}', $staff, 'staff area: new maker');
	pg_assert_contains('"templateShared":"staff_bar"', $staff, 'staff area: staff bar');
}

function test_market_account_journal_form_and_samples()
{
	$d = _test_mka_load();
	if ($d === null) {
		pg_assert_true(true, 'mail.php is not in the tree yet');
		return;
	}
	$form = _test_mka_page('blog_new')['form'];
	pg_assert_same(1, $form['collection']);
	pg_assert_same('cover_image', $form['share_image_field']);
	pg_assert_same('{{tab:blog}}', $form['confirmation_page_id']);
	$fields = _test_mka_fields($d['account']['widgets']['blog_form']['tree']);
	// The Journal's cards ($post_card) and the post page read these.
	foreach (array('title', 'summary', 'content', 'cover_image') as $f) pg_assert_true(in_array($f, $fields, true), "Journal form has $f");

	pg_assert_true(count($form['sample_records']) >= 4, 'sample stories');
	$text = '';
	foreach ($form['sample_records'] as $post) {
		foreach (array_keys($post) as $f) pg_assert_true(in_array($f, $fields, true), "sample field $f is a form field");
		pg_assert_true($post['title'] !== '' && $post['content'] !== '', 'sample has a title and words');
		pg_assert_false((bool)preg_match('/\b(19|20)\d\d\b/', $post['title'] . ' ' . $post['summary'] . ' ' . $post['content']), 'sample names no year: ' . $post['title']);
		$text .= ' ' . $post['title'] . ' ' . $post['summary'] . ' ' . $post['content'];
	}
	// The stories are about the market's own makers.
	foreach (array('clay', 'silver') as $k) pg_assert_contains($d['sellers'][$k]['maker'], $text, "a story names {$d['sellers'][$k]['maker']}");

	// The directory lists the fields its form asks for.
	$staff_fields = _test_mka_fields($d['account']['widgets']['staff_form']['tree']);
	foreach ($d['account']['widgets']['staff_directory']['config']['search_fields'] as $f) pg_assert_true(in_array($f, $staff_fields, true), "directory searches the form's $f");
	pg_assert_same('{{tab:staff_entry}}', $d['account']['widgets']['staff_directory']['config']['custom_form_page_id']);
	pg_assert_same('full_name', $d['account']['widgets']['staff_directory']['config']['order_by_field']);
}

// The field ids are the template's own (mk-), so they never meet another
// template's on a site that mixes their pages.
function test_market_account_field_ids()
{
	$d = _test_mka_load();
	if ($d === null) {
		pg_assert_true(true, 'mail.php is not in the tree yet');
		return;
	}
	foreach (array('blog_form', 'staff_form') as $k) {
		$nodes = array();
		_test_mka_nodes($d['account']['widgets'][$k]['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['id'])) pg_assert_same(0, strpos($n['props']['id'], 'mk-'), "$k: id {$n['props']['id']} starts with mk-");
		}
	}
}

// Every literal has its Turkish.
function test_market_account_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-market/account.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	pg_assert_true(count($m[1]) > 100, 'literals found');
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "account.php: Turkish for '$key'");
	}
}
