<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the account and staff part of the Veterinary design template
 * (includes/design_templates/pinegrap-veterinary/account.php): the pages load in
 * their folders and stay out of the sitemap; every page link of the part
 * and of the shared parts names a page of the template; every placed
 * widget is defined once; the account e-mails are wired to sign-up and
 * the password reset; My Account and the login menu lead to the
 * appointments; the staff area leads to both request lists and the staff's
 * forms; the article form carries the fields its cards read and its
 * sample articles, each about one pet in the words of the pick list; ids
 * carry vt-, classes are the template's; every
 * literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The shop keys of the finished template (_test_vt_shop_keys()) live in
// the parts test.
require_once __DIR__ . '/design_template_veterinary_test.php';

// Every part in one scope, as the template file reads them: parts.php,
// mail.php, the page parts, then the account part. Read once per run.
function _test_vtac_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-veterinary';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$others = array();
	foreach (array('pages_public', 'vets', 'appointments', 'standard') as $name) {
		$others[$name] = include $dir . '/' . $name . '.php';
	}
	$account = include $dir . '/account.php';
	$out = array('account' => $account, 'others' => $others,
		'shared' => array($account_bar_tree(), $header(), $footer(), $booking_band(), $staff_bar()),
		'species_titles' => $species_titles);
	return $out;
}

// Every node of a tree, depth first.
function _test_vtac_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_vtac_nodes($child, $out);
	}
}

function _test_vtac_page($key)
{
	foreach (_test_vtac_load()['account']['pages'] as $p) if ($p['key'] === $key) return $p;
	return null;
}

// The control names of a form tree.
function _test_vtac_fields($tree)
{
	$nodes = array();
	_test_vtac_nodes($tree, $nodes);
	$names = array();
	foreach ($nodes as $n) {
		foreach ($n['props']['_attrs'] ?? array() as $a) {
			if ($a['name'] === 'name') $names[] = $a['value'];
		}
	}
	return $names;
}

function test_veterinary_account_pages_load()
{
	$keys = array();
	foreach (_test_vtac_load()['account']['pages'] as $p) {
		pg_assert_true(isset($p['key'], $p['name'], $p['title'], $p['tree'], $p['folder']), 'page has key, name, title, tree, folder');
		pg_assert_same('root', $p['tree']['type'], $p['key'] . ': root tree');
		pg_assert_same(false, $p['sitemap'], $p['key'] . ': out of the sitemap');
		$keys[] = $p['key'];
	}
	pg_assert_same(array('login', 'register', 'forgot_password', 'set_password', 'logout',
		'my_account', 'profile', 'change_password', 'email_preferences', 'address_book', 'staff_directory',
		'staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset'), $keys);
	foreach (array('my_account', 'profile', 'change_password', 'email_preferences', 'address_book', 'staff_directory') as $key) {
		pg_assert_same('registration', _test_vtac_page($key)['folder'], "$key: signed in");
	}
	foreach (array('staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset') as $key) {
		$p = _test_vtac_page($key);
		pg_assert_same('private', $p['folder'], "$key: private");
		pg_assert_same(true, $p['noindex'], "$key: noindex");
	}
	pg_assert_same('ecommerce', _test_vtac_page('address_book')['requires'], 'address book needs the shop');
}

// Each page link of the account part and the shared parts names a page of
// the finished template; each placed widget is defined once; each widget's
// page exists.
function test_veterinary_account_links_and_widgets()
{
	$d = _test_vtac_load();
	$keys = array();
	$widgets = array();
	foreach (array_merge(array($d['account']), array_values($d['others'])) as $part) {
		foreach ($part['pages'] as $p) $keys[$p['key']] = true;
		foreach ($part['widgets'] as $k => $w) $widgets[$k] = $w;
	}
	foreach ($d['account']['widgets'] as $k => $w) {
		foreach ($d['others'] as $name => $part) pg_assert_false(isset($part['widgets'][$k]), "widget $k is not also in $name.php");
	}
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode(array($d['account'], $d['shared'])), $m);
	pg_assert_true(count($m[1]) > 20, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($keys[$key]), "page link {{page:$key}} names a template page");

	// Every page of every part places only defined widgets.
	foreach (array_merge(array($d['account']), array_values($d['others'])) as $part) {
		foreach ($part['pages'] as $p) {
			$nodes = array();
			_test_vtac_nodes($p['tree'], $nodes);
			foreach ($nodes as $n) {
				if (isset($n['props']['templateWidget'])) pg_assert_true(isset($widgets[$n['props']['templateWidget']]), $p['key'] . ': widget ' . $n['props']['templateWidget'] . ' is defined');
			}
		}
	}
	foreach ($d['account']['widgets'] as $k => $w) pg_assert_true($w['page'] === '' || isset($keys[$w['page']]), "widget $k: page {$w['page']}");
	pg_assert_contains('"templateWidget":"login_region"', json_encode($d['shared'][0]), 'account bar places the login region');
}

function test_veterinary_account_emails_and_menus()
{
	$d = _test_vtac_load();
	$w = $d['account']['widgets'];
	pg_assert_same('{{tab:email_account_welcome}}', $w['register']['config']['welcome_email_page_id']);
	pg_assert_same('{{tab:email_password_reset}}', $w['forgot_password']['config']['reset_email_page_id']);
	pg_assert_contains('"href":"__set_password_url"', json_encode($w['account_email_reset']['tree']), 'reset button carries the set password link');
	pg_assert_contains('"text":"__user_first_name"', json_encode($w['account_email_welcome']['tree']), 'welcome greets the owner by name');
	pg_assert_same('{{tab:my_account}}', $w['login_region']['config']['account_page_id']);
	pg_assert_same('{{tab:staff}}', $w['login_region']['config']['staff_page_id']);
	pg_assert_same('{{tab:staff}}', $w['my_account']['config']['staff_page_id']);

	// My Account and the login menu lead to the appointments; the orders
	// show only for an owner who has any.
	foreach (array('my_account', 'login_region') as $k) {
		pg_assert_contains('{{page:my_appointments}}', json_encode($w[$k]['tree']), "$k: my appointments");
	}
	$mine = json_encode($w['my_account']['tree']);
	pg_assert_contains('{{page:book_online}}', $mine, 'my account: book online');
	pg_assert_contains('{{page:plan_join}}', $mine, 'my account: health plan sign-up');
	pg_assert_contains('"section":"order_history"', $mine, 'my account: order history');
	pg_assert_contains('"eo_visible_if":"has_orders"', $mine, 'my orders heading only with orders');

	// The staff area leads to both request lists and the staff's forms.
	$staff = json_encode(_test_vtac_page('staff')['tree']);
	foreach (array('staff_appointments', 'staff_plan_joins', 'vet_new', 'blog_new', 'staff_entry', 'inbox') as $key) {
		pg_assert_contains('{{page:' . $key . '}}', $staff, "staff area: $key");
	}

	// The shop's pages are never linked from this part; without the shop
	// the menu leaves out the address book.
	pg_assert_false((bool)preg_match('/\{\{page:(' . implode('|', _test_vt_shop_keys()) . ')\}\}/', json_encode($d['account'])), 'no shop page link');
	if (!(defined('ECOMMERCE') && ECOMMERCE)) {
		pg_assert_false(strpos($mine, '__address_book_url') !== false, 'no address book without the shop');
	}
}

function test_veterinary_account_article_form_and_samples()
{
	$d = _test_vtac_load();
	$form = _test_vtac_page('blog_new')['form'];
	pg_assert_same(1, $form['collection']);
	pg_assert_same('cover_image', $form['share_image_field']);
	pg_assert_same('{{tab:blog}}', $form['confirmation_page_id']);
	$fields = _test_vtac_fields($d['account']['widgets']['blog_form']['tree']);
	// The article cards ($post_card), the post page and the blog's
	// ?species= filter read these.
	foreach (array('title', 'summary', 'species', 'content', 'cover_image') as $f) pg_assert_true(in_array($f, $fields, true), "article form has $f");

	pg_assert_true(count($form['sample_records']) >= 4, 'sample articles');
	foreach ($form['sample_records'] as $post) {
		foreach (array_keys($post) as $f) pg_assert_true(in_array($f, $fields, true), "sample field $f is a form field");
		pg_assert_true($post['title'] !== '' && $post['content'] !== '', 'sample has a title and words');
		pg_assert_false((bool)preg_match('/\b(19|20)\d\d\b/', $post['title'] . ' ' . $post['summary'] . ' ' . $post['content']), 'sample names no year: ' . $post['title']);
		pg_assert_true(in_array($post['species'], $d['species_titles'], true), $post['title'] . ': its pets in the pick list\'s words');
	}
	// One sample per pet, so every pet link of the blog finds an article.
	pg_assert_same($d['species_titles'], array_column($form['sample_records'], 'species'), 'one sample article per pet');
	$species = '';
	foreach (_test_vtac_load()['account']['widgets']['blog_form']['tree']['children'] as $c) {
		if (strpos(json_encode($c), '"vt-post-species"') !== false) $species = json_encode($c);
	}
	foreach ($d['species_titles'] as $t) pg_assert_contains(json_encode($t), $species, "the pet field offers $t");

	$staff_fields = _test_vtac_fields($d['account']['widgets']['staff_form']['tree']);
	foreach ($d['account']['widgets']['staff_directory']['config']['search_fields'] as $f) pg_assert_true(in_array($f, $staff_fields, true), "directory searches the form's $f");
	pg_assert_same('{{tab:staff_entry}}', $d['account']['widgets']['staff_directory']['config']['custom_form_page_id']);
}

// Ids carry vt-, classes are Bootstrap's or the template's, no faded text.
function test_veterinary_account_ids_and_classes()
{
	$d = _test_vtac_load();
	$nodes = array();
	foreach ($d['account']['pages'] as $p) _test_vtac_nodes($p['tree'], $nodes);
	foreach ($d['account']['widgets'] as $w) _test_vtac_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht|pgy|pth|pcn|pmu|pph|psh|ptv|pwd|pch|pmz|pai)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '') pg_assert_same(0, strpos($id, 'vt-'), "id $id is the template's");
	}
}

// Every literal has its Turkish; a pet reads "evcil hayvan", a vet
// "veteriner hekim", an appointment "randevu".
function test_veterinary_account_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-veterinary/account.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || preg_match('/^[a-z0-9 ,-]+$/', $key)) continue;
		$tr = $map[$key];
		if (preg_match('/\b[Pp]ets?\b/', $key)) pg_assert_true(mb_stripos($tr, 'evcil hayvan', 0, 'UTF-8') !== false, "'$key' says evcil hayvan");
		if (preg_match('/\b[Vv]ets?\b/', $key)) pg_assert_true(mb_stripos($tr, 'veteriner hekim', 0, 'UTF-8') !== false, "'$key' says veteriner hekim");
		if (preg_match('/\b[Aa]ppointments?\b/', $key)) pg_assert_true(mb_stripos($tr, 'randevu', 0, 'UTF-8') !== false, "'$key' says randevu");
	}
}
