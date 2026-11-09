<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the account and staff part of the Charity design template
 * (includes/design_templates/pinegrap-charity/account.php): its pages in
 * order, in their folders and with root trees; every page link names a
 * page of the template and every placed widget is defined; no page or
 * widget key meets another part's; the account e-mails are wired to the
 * registration and password widgets; My Account and the login menu lead to
 * the volunteering and the pledges; the staff area leads to both staff
 * lists and the staff's forms; the pledge lists read the Pledges form on
 * the donate page and search its fields; the News form has the fields the
 * post cards read and its sample stories fill only those, with no year,
 * naming the template's own campaigns; the field ids are the template's
 * own; every literal has its Turkish.
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
// The page keys of the finished template and the shop's pages.
require_once(__DIR__ . '/design_template_charity_test.php');

// parts.php, mail.php, forms.php and account.php in one scope, as the
// template file includes them, with the other parts of the folder. Read
// once per run.
function _test_charity_account()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-charity';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	include $dir . '/forms.php';
	$others = array();
	foreach (array('pages_public', 'donate', 'campaigns', 'volunteer', 'events', 'standard') as $name) {
		$others[$name] = include $dir . '/' . $name . '.php';
	}
	$out = array(
		'account'   => include $dir . '/account.php',
		'others'    => $others,
		'shared'    => array($account_bar_tree(), $staff_bar()),
		'campaigns' => $campaigns,
	);
	return $out;
}

function _test_charity_account_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_charity_account_nodes($child, $out);
	}
}

function _test_charity_account_page($key)
{
	foreach (_test_charity_account()['account']['pages'] as $p) if ($p['key'] === $key) return $p;
	return null;
}

// The field names of a widget tree.
function _test_charity_account_fields($tree)
{
	$nodes = array();
	_test_charity_account_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		if (empty($n['props']['_attrs'])) continue;
		foreach ($n['props']['_attrs'] as $a) if ($a['name'] === 'name') $out[] = $a['value'];
	}
	return $out;
}

function test_charity_account_pages()
{
	$d = _test_charity_account();
	$keys = array();
	foreach ($d['account']['pages'] as $p) {
		pg_assert_true(isset($p['key'], $p['name'], $p['title'], $p['tree'], $p['folder']), 'page has key, name, title, tree, folder');
		pg_assert_same('root', $p['tree']['type'], $p['key'] . ': root tree');
		pg_assert_same(false, $p['sitemap'], $p['key'] . ': out of the sitemap');
		$keys[] = $p['key'];
	}
	pg_assert_same(array('login', 'register', 'forgot_password', 'set_password', 'logout',
		'my_account', 'profile', 'change_password', 'email_preferences', 'address_book', 'my_pledges', 'staff_directory',
		'staff', 'staff_pledges', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset'), $keys);
	foreach (array('my_account', 'profile', 'change_password', 'email_preferences', 'address_book', 'my_pledges', 'staff_directory') as $key) {
		pg_assert_same('registration', _test_charity_account_page($key)['folder'], "$key: signed in");
	}
	foreach (array('staff', 'staff_pledges', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset') as $key) {
		$p = _test_charity_account_page($key);
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
	// Every page of the template's key list is now made by some part.
	$made = array_flip($keys);
	foreach ($d['others'] as $part) foreach ($part['pages'] as $p) $made[$p['key']] = true;
	foreach (_test_charity_page_keys() as $key) pg_assert_true(isset($made[$key]), "page $key is made by a part");
}

// Each page link of the account part and the shared parts names a page of
// the template; each placed widget is defined and each widget's page
// exists; nothing here leads to a shop page.
function test_charity_account_links_and_widgets()
{
	$d = _test_charity_account();
	$known = array_flip(_test_charity_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(array($d['account'], $d['shared'])), $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_charity_shop_keys() as $key) {
		pg_assert_false(strpos(json_encode($d['account']), '{{page:' . $key . '}}') !== false, "account does not link to $key");
	}

	$widgets = $d['account']['widgets'];
	foreach ($d['others'] as $part) $widgets += $part['widgets'];
	$nodes = array();
	foreach ($d['account']['pages'] as $p) _test_charity_account_nodes($p['tree'], $nodes);
	foreach ($nodes as $n) {
		if (isset($n['props']['templateWidget'])) pg_assert_true(isset($widgets[$n['props']['templateWidget']]), 'widget ' . $n['props']['templateWidget'] . ' is defined');
	}
	foreach ($d['account']['widgets'] as $k => $w) pg_assert_true($w['page'] === '' || isset($known[$w['page']]), "widget $k: page {$w['page']}");

	// The account bar places the login region this part defines.
	pg_assert_contains('"templateWidget":"login_region"', json_encode($d['shared'][0]), 'account bar places the login region');
	pg_assert_true(isset($d['account']['widgets']['login_region']), 'login region is defined here');
}

function test_charity_account_emails_menus_and_staff()
{
	$d = _test_charity_account();
	$w = $d['account']['widgets'];
	pg_assert_same('{{tab:email_account_welcome}}', $w['register']['config']['welcome_email_page_id']);
	pg_assert_same('{{tab:email_password_reset}}', $w['forgot_password']['config']['reset_email_page_id']);
	pg_assert_contains('"href":"__set_password_url"', json_encode($w['account_email_reset']['tree']), 'reset button carries the set password link');
	pg_assert_contains('"text":"__user_first_name"', json_encode($w['account_email_welcome']['tree']), 'welcome greets the member by name');
	pg_assert_same('{{tab:my_account}}', $w['login_region']['config']['account_page_id']);
	pg_assert_same('{{tab:staff}}', $w['login_region']['config']['staff_page_id']);
	pg_assert_same('{{tab:staff}}', $w['my_account']['config']['staff_page_id']);

	foreach (array('my_account', 'login_region') as $k) {
		$json = json_encode($w[$k]['tree']);
		pg_assert_contains('{{page:my_volunteering}}', $json, "$k: my volunteering");
		pg_assert_contains('{{page:my_pledges}}', $json, "$k: my pledges");
	}
	pg_assert_contains('"section":"order_history"', json_encode($w['my_account']['tree']), 'my account: gifts given online');
	pg_assert_contains('"eo_visible_if":"has_orders"', json_encode($w['my_account']['tree']), 'my gifts heading only with orders');

	$staff = json_encode(_test_charity_account_page('staff')['tree']);
	foreach (array('staff_volunteers', 'staff_pledges', 'campaign_new', 'event_new', 'blog_new', 'report_new', 'staff_entry', 'inbox') as $key) {
		pg_assert_contains('{{page:' . $key . '}}', $staff, "staff area: $key");
	}
	pg_assert_contains('"templateShared":"staff_bar"', $staff, 'staff area: staff bar');
}

// The pledge lists read the Pledges form on the donate page, open the
// pledge page, and search only fields the form has.
function test_charity_account_pledge_lists()
{
	$d = _test_charity_account();
	$w = $d['account']['widgets'];
	$form_fields = _test_charity_account_fields($d['others']['donate']['widgets']['pledge_form']['tree']);
	foreach (array('my_pledges_list', 'staff_pledges_list') as $k) {
		pg_assert_same('{{tab:donate}}', $w[$k]['config']['custom_form_page_id'], "$k: the Pledges form");
		pg_assert_same('{{tab:pledge_view}}', $w[$k]['config']['detail_page_id'], "$k: opens the pledge");
		preg_match_all('/"text":"([a-z_]+)"/', json_encode($w[$k]['tree']), $m);
		foreach (array_unique($m[1]) as $f) {
			if (in_array($f, array('submitted_date_and_time', 'number_of_comments'), true)) continue;
			pg_assert_true(in_array($f, $form_fields, true), "$k shows the form's $f");
		}
	}
	pg_assert_same(true, $w['my_pledges_list']['config']['viewer_filter_submitter'], 'my pledges: the donor\'s own');
	foreach ($w['staff_pledges_list']['config']['search_fields'] as $f) pg_assert_true(in_array($f, $form_fields, true), "staff list searches the form's $f");
}

function test_charity_account_news_form_and_samples()
{
	$d = _test_charity_account();
	$form = _test_charity_account_page('blog_new')['form'];
	pg_assert_same(1, $form['collection']);
	pg_assert_same('cover_image', $form['share_image_field']);
	pg_assert_same('{{tab:blog}}', $form['confirmation_page_id']);
	$fields = _test_charity_account_fields($d['account']['widgets']['blog_form']['tree']);
	foreach (array('title', 'summary', 'content', 'cover_image') as $f) pg_assert_true(in_array($f, $fields, true), "News form has $f");

	pg_assert_true(count($form['sample_records']) >= 4, 'sample stories');
	$text = '';
	foreach ($form['sample_records'] as $post) {
		foreach (array_keys($post) as $f) pg_assert_true(in_array($f, $fields, true), "sample field $f is a form field");
		pg_assert_true($post['title'] !== '' && $post['content'] !== '', 'sample has a title and words');
		pg_assert_false((bool)preg_match('/\b(19|20)\d\d\b/', $post['title'] . ' ' . $post['summary'] . ' ' . $post['content']), 'sample names no year: ' . $post['title']);
		$text .= ' ' . $post['title'] . ' ' . $post['summary'] . ' ' . $post['content'];
	}
	foreach (array('wells', 'meals') as $k) pg_assert_contains($d['campaigns'][$k]['title'], $text, "a story names {$d['campaigns'][$k]['title']}");

	$staff_fields = _test_charity_account_fields($d['account']['widgets']['staff_form']['tree']);
	foreach ($d['account']['widgets']['staff_directory']['config']['search_fields'] as $f) pg_assert_true(in_array($f, $staff_fields, true), "directory searches the form's $f");
	pg_assert_same('{{tab:staff_entry}}', $d['account']['widgets']['staff_directory']['config']['custom_form_page_id']);
}

// The field ids are the template's own (ch-).
function test_charity_account_field_ids()
{
	$d = _test_charity_account();
	foreach (array('blog_form', 'staff_form') as $k) {
		$nodes = array();
		_test_charity_account_nodes($d['account']['widgets'][$k]['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['id'])) pg_assert_same(0, strpos($n['props']['id'], 'ch-'), "$k: id {$n['props']['id']} starts with ch-");
		}
	}
}

// Every literal has its Turkish.
function test_charity_account_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-charity/account.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	pg_assert_true(count($m[1]) > 100, 'literals found');
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "account.php: Turkish for '$key'");
	}
}
