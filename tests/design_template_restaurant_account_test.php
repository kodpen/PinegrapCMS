<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Restaurant design template's account and staff part
 * (includes/design_templates/pinegrap-restaurant/account.php): the sign-in
 * and My Account pages, the staff area and its forms, the Kitchen
 * Journal's sample posts, the account e-mails, the login region the
 * account bar places, links to template pages only, the template's ids
 * and classes, and every literal in Turkish.
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
require_once(__DIR__ . '/design_template_restaurant_test.php');

// Every part of the folder in one scope, in the template file's order.
// Read once per run.
function _test_rta_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-restaurant';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$others = array();
	foreach (array('pages_public', 'menu', 'reservations', 'private', 'shop', 'standard') as $f) $others[$f] = include $dir . '/' . $f . '.php';
	$account = include $dir . '/account.php';
	$out = array('account' => $account, 'others' => $others, 'account_bar' => $account_bar_tree());
	return $out;
}

function _test_rta_pages()
{
	$pages = array();
	foreach (_test_rta_load()['account']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The field names of a widget tree.
function _test_rta_fields($tree)
{
	$nodes = array();
	_test_rt_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		foreach ($n['props']['_attrs'] ?? array() as $a) {
			if ($a['name'] === 'name') $out[] = $a['value'];
		}
	}
	return $out;
}

function test_restaurant_account_pages_are_the_plans()
{
	$pages = _test_rta_pages();
	pg_assert_same(array('login', 'register', 'forgot_password', 'set_password', 'logout',
		'my_account', 'profile', 'change_password', 'email_preferences', 'address_book', 'staff_directory',
		'staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset'), array_keys($pages), 'the account pages');
	foreach ($pages as $key => $p) {
		pg_assert_same('root', $p['tree']['type'], "$key: root tree");
		pg_assert_same(false, $p['sitemap'], "$key: out of the sitemap");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
	}
	foreach (array('my_account', 'profile', 'change_password', 'email_preferences', 'address_book', 'staff_directory') as $key) {
		pg_assert_same('registration', $pages[$key]['folder'], "$key: signed in");
	}
	foreach (array('staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset') as $key) {
		pg_assert_same('private', $pages[$key]['folder'], "$key: private");
		pg_assert_true($pages[$key]['noindex'], "$key: noindex");
	}
	pg_assert_same('ecommerce', $pages['address_book']['requires'], 'the address book needs the shop');
	foreach ($pages as $key => $p) if ($key !== 'address_book') pg_assert_false(isset($p['requires']), "$key stands without the shop");
}

// Every page of the folder now exists: each link names one, each placed
// widget is defined once, and the account bar places the login region.
function test_restaurant_account_links_and_widgets()
{
	$d = _test_rta_load();
	$keys = array();
	$widgets = array();
	foreach (array_merge(array('account' => $d['account']), $d['others']) as $name => $part) {
		foreach ($part['pages'] as $p) {
			pg_assert_false(isset($keys[$p['key']]), "page {$p['key']} is made once");
			$keys[$p['key']] = true;
		}
		foreach ($part['widgets'] as $k => $w) {
			pg_assert_false(isset($widgets[$k]), "widget $k is defined once");
			$widgets[$k] = $w;
		}
	}
	$all = _test_rt_page_keys();
	sort($all);
	$made = array_keys($keys);
	sort($made);
	pg_assert_same($all, $made, 'the parts make every page of the template');
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(array($d['account'], $d['account_bar'])), $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($keys[$key]), "link {{page:$key}} names a template page");
	foreach (_test_rta_pages() as $p) {
		preg_match_all('/"templateWidget":"([a-z_]+)"/', json_encode($p['tree']), $m);
		foreach ($m[1] as $w) pg_assert_true(isset($widgets[$w]), "{$p['key']} places $w, defined");
	}
	foreach ($d['account']['widgets'] as $k => $w) pg_assert_true($w['page'] === '' || isset($keys[$w['page']]), "widget $k: page {$w['page']}");
	pg_assert_contains('"templateWidget":"login_region"', json_encode($d['account_bar']), 'the account bar places the login region');
	pg_assert_contains('"templateWidget":"cart_link"', json_encode($d['account_bar']), 'and the cart link');
	pg_assert_false((bool)preg_match('/\{\{page:(vouchers|product|cart|checkout|order)\}\}/', json_encode($d['account'])), 'no shop page link');
}

function test_restaurant_account_menus_staff_and_emails()
{
	$w = _test_rta_load()['account']['widgets'];
	pg_assert_same('{{tab:email_account_welcome}}', $w['register']['config']['welcome_email_page_id']);
	pg_assert_same('{{tab:email_password_reset}}', $w['forgot_password']['config']['reset_email_page_id']);
	pg_assert_contains('"href":"__set_password_url"', json_encode($w['account_email_reset']['tree']), 'the reset button carries the set password link');
	pg_assert_contains('"text":"__user_first_name"', json_encode($w['account_email_welcome']['tree']), 'the welcome greets the member by name');
	pg_assert_contains('var(--bs-primary,#9f1239)', json_encode($w['account_email_reset']['tree']), 'the button keeps its colour inline');
	pg_assert_same('{{tab:staff}}', $w['login_region']['config']['staff_page_id']);
	pg_assert_same('{{tab:staff}}', $w['my_account']['config']['staff_page_id']);
	foreach (array('my_account', 'login_region') as $k) {
		$json = json_encode($w[$k]['tree']);
		pg_assert_contains('{{page:my_reservations}}', $json, "$k: My Reservations");
		pg_assert_contains('{{page:my_enquiries}}', $json, "$k: My Enquiries");
		pg_assert_contains('{{page:inbox}}', $json, "$k: the inbox");
	}
	pg_assert_contains('"eo_visible_if":"has_orders"', json_encode($w['my_account']['tree']), 'My Orders only with orders');
	$staff = json_encode(_test_rta_pages()['staff']['tree']);
	foreach (array('staff_reservations', 'staff_enquiries', 'dish_new', 'event_new', 'blog_new', 'inbox', 'staff_entry') as $key) {
		pg_assert_contains('{{page:' . $key . '}}', $staff, "the staff area leads to $key");
	}
}

function test_restaurant_account_journal_form_and_samples()
{
	$d = _test_rta_load();
	$form = _test_rta_pages()['blog_new']['form'];
	pg_assert_same(1, $form['collection']);
	pg_assert_same(lang('Kitchen Journal'), $form['form_name']);
	pg_assert_same('cover_image', $form['share_image_field']);
	pg_assert_same('{{tab:blog}}', $form['confirmation_page_id']);
	$fields = _test_rta_fields($d['account']['widgets']['blog_form']['tree']);
	// The journal's cards, list and post page (standard.php) read these.
	$standard = $d['others']['standard']['widgets'];
	foreach (array('title', 'summary', 'content', 'cover_image') as $f) pg_assert_true(in_array($f, $fields, true), "the post form has $f");
	foreach ($standard['blog_list']['config']['search_fields'] as $f) pg_assert_true(in_array($f, $fields, true), "the journal searches $f, a field");
	pg_assert_true(count($form['sample_records']) >= 4, 'sample posts');
	foreach ($form['sample_records'] as $post) {
		foreach (array_keys($post) as $f) pg_assert_true(in_array($f, $fields, true), "sample field $f is a form field");
		pg_assert_false((bool)preg_match('/\b(19|20)\d\d\b/', $post['title'] . ' ' . $post['summary'] . ' ' . $post['content']), 'the sample names no year: ' . $post['title']);
	}
	$staff_fields = _test_rta_fields($d['account']['widgets']['staff_form']['tree']);
	foreach ($d['account']['widgets']['staff_directory']['config']['search_fields'] as $f) pg_assert_true(in_array($f, $staff_fields, true), "the directory searches the form's $f");
}

// Ids and classes are the template's own; every literal has its Turkish.
function test_restaurant_account_ids_classes_and_turkish()
{
	$d = _test_rta_load();
	$trees = array($d['account_bar']);
	foreach ($d['account']['pages'] as $p) $trees[] = $p['tree'];
	foreach ($d['account']['widgets'] as $w) if (is_array($w['tree'])) $trees[] = $w['tree'];
	foreach ($trees as $tree) {
		$nodes = array();
		_test_rt_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht|pgy|pcn|pmu|pph|psh|ptv|pwd|pai|pmz)-/', $c), "class $c is not another template's");
				pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id !== '') pg_assert_true(strpos($id, 'rt-') === 0, "id $id is the template's");
		}
	}
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-restaurant/account.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (isset($map[$key]) && strpos($key, 'Kitchen Journal') !== false) pg_assert_true(mb_stripos($map[$key], 'mutfak', 0, 'UTF-8') !== false, "'$key' says Mutfak");
		if (isset($map[$key]) && preg_match('/\breservations?\b/i', $key) && strpos($key, ' ') !== false) pg_assert_true(mb_stripos($map[$key], 'rezervasyon', 0, 'UTF-8') !== false, "'$key' says rezervasyon");
	}
	foreach (array('Starters', 'Course', 'Glass', 'Dish', 'Find Us', 'Time', 'Hotel Journal') as $k) {
		pg_assert_false(strpos($src, "lang('" . $k . "')") !== false, "account.php does not use lang('$k')");
	}
}
