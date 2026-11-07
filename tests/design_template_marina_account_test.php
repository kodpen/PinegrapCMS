<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the account and staff part of the Marina design template
 * (includes/design_templates/pinegrap-marina/account.php): its
 * pages in order, in their folders and with root trees; every page link
 * names a page of the template and every placed widget is defined; the
 * account e-mails are wired to the registration and password widgets and
 * the reset button carries the set password link; My Account leads to the
 * berth requests, charter enquiries and event entries; the article form has the fields
 * the News lists read and its sample articles fill only those,
 * with no fixed date; the field ids are the template's own; every literal
 * has its Turkish.
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

// The page keys of the finished template (_test_mn_page_keys()).
require_once(__DIR__ . '/design_template_marina_test.php');

// parts.php, mail.php and account.php in one scope, as the template file
// includes them, with the other parts of the folder. Read once per run.
function _test_mna_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-marina';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$others = array();
	foreach (array('pages_public', 'charters', 'requests', 'events', 'standard') as $name) {
		$others[$name] = include $dir . '/' . $name . '.php';
	}
	$out = array(
		'account' => include $dir . '/account.php',
		'others'  => $others,
		'shared'  => array($account_bar_tree(), $header(), $footer(), $notice(), $staff_bar()),
	);
	return $out;
}

function _test_mna_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_mna_nodes($child, $out);
	}
}

function _test_mna_page($key)
{
	$d = _test_mna_load();
	foreach ($d['account']['pages'] as $p) if ($p['key'] === $key) return $p;
	return null;
}

// The field names of a widget tree.
function _test_mna_fields($tree)
{
	$nodes = array();
	_test_mna_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		if (empty($n['props']['_attrs'])) continue;
		foreach ($n['props']['_attrs'] as $a) if ($a['name'] === 'name') $out[] = $a['value'];
	}
	return $out;
}

function test_marina_account_pages_load()
{
	$d = _test_mna_load();
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
		pg_assert_same('registration', _test_mna_page($key)['folder'], "$key: signed in");
	}
	foreach (array('staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset') as $key) {
		$p = _test_mna_page($key);
		pg_assert_same('private', $p['folder'], "$key: private");
		pg_assert_same(true, $p['noindex'], "$key: noindex");
	}
	pg_assert_same('ecommerce', _test_mna_page('address_book')['requires'], 'address book needs the shop');
}

// Each page link of the account part and the shared parts names a page of
// the template: one of the account part, of the other parts in the tree,
// or of the plan's parts still to come. Each placed widget is defined and
// each widget's page exists.
function test_marina_account_links_and_widgets()
{
	$d = _test_mna_load();
	$keys = array_fill_keys(_test_mn_page_keys(), true);
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
	foreach ($d['account']['pages'] as $p) _test_mna_nodes($p['tree'], $nodes);
	foreach ($nodes as $n) {
		if (isset($n['props']['templateWidget'])) pg_assert_true(isset($widgets[$n['props']['templateWidget']]), 'widget ' . $n['props']['templateWidget'] . ' is defined');
	}
	foreach ($d['account']['widgets'] as $k => $w) pg_assert_true($w['page'] === '' || isset($keys[$w['page']]), "widget $k: page {$w['page']}");

	// The account bar places the login region this part defines.
	pg_assert_contains('"templateWidget":"login_region"', json_encode($d['shared'][0]), 'account bar places the login region');
}

function test_marina_account_emails_and_widgets()
{
	$d = _test_mna_load();
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

	// My Account and the login menu lead to the skipper's requests; no
	// shop, so no order history.
	foreach (array('my_account', 'login_region') as $k) {
		$json = json_encode($w[$k]['tree']);
		pg_assert_contains('{{page:my_requests}}', $json, "$k: my berth requests");
		pg_assert_contains('{{page:my_enquiries}}', $json, "$k: my charter enquiries");
		pg_assert_contains('{{page:my_entries}}', $json, "$k: my event entries");
	}
	pg_assert_false(strpos(json_encode($w['my_account']['tree']), 'order_history') !== false, 'my account: no order history');

	// The staff area leads to the three record lists and the staff's forms.
	$staff = json_encode(_test_mna_page('staff')['tree']);
	pg_assert_contains('{{page:staff_requests}}', $staff, 'staff area: berth requests');
	pg_assert_contains('{{page:staff_enquiries}}', $staff, 'staff area: charter enquiries');
	pg_assert_contains('{{page:staff_entries}}', $staff, 'staff area: event entries');
	pg_assert_contains('{{page:boat_new}}', $staff, 'staff area: new charter boat');
	pg_assert_contains('{{page:event_new}}', $staff, 'staff area: new event');
	pg_assert_contains('{{page:blog_new}}', $staff, 'staff area: new article');

	// The shop's pages are never linked from this part: the address book
	// is reached by its token, and the page itself needs the shop.
	pg_assert_false((bool)preg_match('/\{\{page:(vouchers|product|cart|checkout|order)\}\}/', json_encode($d['account'])), 'no shop page link');
}

function test_marina_account_news_form_and_samples()
{
	$d = _test_mna_load();
	$form = _test_mna_page('blog_new')['form'];
	pg_assert_same(1, $form['collection']);
	pg_assert_same('cover_image', $form['share_image_field']);
	pg_assert_same('{{tab:blog}}', $form['confirmation_page_id']);
	$fields = _test_mna_fields($d['account']['widgets']['blog_form']['tree']);
	// The News cards ($post_card) and the article page read these.
	foreach (array('title', 'summary', 'content', 'cover_image') as $f) pg_assert_true(in_array($f, $fields, true), "article form has $f");

	pg_assert_true(count($form['sample_records']) >= 4, 'sample articles');
	foreach ($form['sample_records'] as $post) {
		foreach (array_keys($post) as $f) pg_assert_true(in_array($f, $fields, true), "sample field $f is a form field");
		pg_assert_true($post['title'] !== '' && $post['content'] !== '', 'sample has a title and words');
		pg_assert_false((bool)preg_match('/\b(19|20)\d\d\b/', $post['title'] . ' ' . $post['summary'] . ' ' . $post['content']), 'sample names no year: ' . $post['title']);
	}

	// The directory lists the fields its form asks for.
	$staff_fields = _test_mna_fields($d['account']['widgets']['staff_form']['tree']);
	foreach ($d['account']['widgets']['staff_directory']['config']['search_fields'] as $f) pg_assert_true(in_array($f, $staff_fields, true), "directory searches the form's $f");
	pg_assert_same('{{tab:staff_entry}}', $d['account']['widgets']['staff_directory']['config']['custom_form_page_id']);
}

// The field ids are the template's own (mn-), so they never meet another
// template's on a site that mixes their pages.
function test_marina_account_field_ids()
{
	$d = _test_mna_load();
	foreach (array('blog_form', 'staff_form') as $k) {
		$nodes = array();
		_test_mna_nodes($d['account']['widgets'][$k]['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['id'])) pg_assert_same(0, strpos($n['props']['id'], 'mn-'), "$k: id {$n['props']['id']} starts with mn-");
		}
	}
}

// Every literal has its Turkish.
function test_marina_account_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-marina/account.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	pg_assert_true(count($m[1]) > 100, 'literals found');
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "account.php: Turkish for '$key'");
	}
}
