<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the account and staff part of the Car Rental design template
 * (includes/design_templates/pinegrap-carrental/account.php): the pages
 * load in their folders and stay out of the sitemap; every page link of the
 * part names a page of the finished template; every widget is the part's
 * own and sits on one of its pages; the account e-mails are wired to
 * sign-up and the password reset; My Account and the login menu lead to
 * the booking requests and the business enquiries; the staff area leads to
 * the two request lists and the staff's forms; the post form carries the
 * fields the Road Trip Journal's cards read and its sample posts; ids carry
 * cr-, classes are the template's; no fixed year; every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template and the tree helpers.
require_once __DIR__ . '/design_template_carrental_test.php';

// The account part as the template file reads it: after parts.php and
// mail.php in one scope. Read once per run.
function _test_cra_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-carrental';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$part = include $dir . '/account.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'staff_bar' => $staff_bar());
	return $out;
}

// The control names of a form tree.
function _test_cra_fields($tree)
{
	$nodes = array();
	_test_cr_nodes($tree, $nodes);
	$names = array();
	foreach ($nodes as $n) {
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) {
			if ($a['name'] === 'name') $names[] = $a['value'];
		}
	}
	return $names;
}

function test_carrental_account_pages_load()
{
	$d = _test_cra_load();
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($p['name'], $p['title'], $p['tree'], $p['folder']), "$key has name, title, tree, folder");
		pg_assert_same('root', $p['tree']['type'], "$key: root tree");
		pg_assert_same(false, $p['sitemap'], "$key: out of the sitemap");
	}
	pg_assert_same(array('login', 'register', 'forgot_password', 'set_password', 'logout',
		'my_account', 'profile', 'change_password', 'email_preferences', 'address_book', 'staff_directory',
		'staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset'), array_keys($d['pages']));
	foreach (array('my_account', 'profile', 'change_password', 'email_preferences', 'address_book', 'staff_directory') as $key) {
		pg_assert_same('registration', $d['pages'][$key]['folder'], "$key: signed in");
	}
	foreach (array('staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "$key: private");
		pg_assert_same(true, $d['pages'][$key]['noindex'], "$key: noindex");
	}
	pg_assert_same('ecommerce', $d['pages']['address_book']['requires'], 'address book needs the shop');
	pg_assert_same('ecommerce', $d['widgets']['address_book']['requires'], 'address book widget needs the shop');
}

// Each page link names a page of the finished template; each placed widget
// is the part's own; each widget's page is one of the part's.
function test_carrental_account_links_and_widgets()
{
	$d = _test_cra_load();
	$known = array_flip(array_merge(_test_cr_page_keys(), array('email_account_welcome', 'email_password_reset')));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($d['part']), $m);
	pg_assert_true(count($m[1]) > 20, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach ($d['pages'] as $key => $p) {
		$nodes = array();
		_test_cr_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) pg_assert_true(isset($d['widgets'][$n['props']['templateWidget']]), "$key: widget " . $n['props']['templateWidget'] . ' is defined');
		}
	}
	foreach ($d['widgets'] as $k => $w) pg_assert_true($w['page'] === '' || isset($d['pages'][$w['page']]), "widget $k: page {$w['page']}");
	// The staff bar's links are pages of the finished template too.
	preg_match_all('/\{\{page:([a-z0-9_]+)\}\}/', json_encode($d['staff_bar']), $m);
	foreach ($m[1] as $key) pg_assert_true(isset($known[$key]), "staff bar: {{page:$key}}");
}

function test_carrental_account_emails_and_menus()
{
	$w = _test_cra_load()['widgets'];
	pg_assert_same('{{tab:email_account_welcome}}', $w['register']['config']['welcome_email_page_id']);
	pg_assert_same('{{tab:email_password_reset}}', $w['forgot_password']['config']['reset_email_page_id']);
	pg_assert_contains('"href":"__set_password_url"', json_encode($w['account_email_reset']['tree']), 'reset button carries the set password link');
	pg_assert_contains('"text":"__user_first_name"', json_encode($w['account_email_welcome']['tree']), 'welcome greets the member by name');
	pg_assert_same('{{tab:my_account}}', $w['login_region']['config']['account_page_id']);
	pg_assert_same('{{tab:staff}}', $w['login_region']['config']['staff_page_id']);
	pg_assert_same('{{tab:staff}}', $w['my_account']['config']['staff_page_id']);

	// My Account and the login menu lead to the booking requests and the
	// business enquiries; the orders show only for a member who has any.
	foreach (array('my_account', 'login_region') as $k) {
		pg_assert_contains('{{page:my_bookings}}', json_encode($w[$k]['tree']), "$k: my bookings");
		pg_assert_contains('{{page:my_enquiries}}', json_encode($w[$k]['tree']), "$k: my enquiries");
		pg_assert_contains('"eo_visible_if":"is_staff"', json_encode($w[$k]['tree']), "$k: staff link only for the staff");
	}
	$mine = json_encode($w['my_account']['tree']);
	foreach (array('book', 'business', 'fleet', 'deals') as $key) pg_assert_contains('{{page:' . $key . '}}', $mine, "my account: $key");
	pg_assert_contains('"section":"order_history"', $mine, 'my account: order history');
	pg_assert_contains('"eo_visible_if":"has_orders"', $mine, 'my orders heading only with orders');
	if (!(defined('ECOMMERCE') && ECOMMERCE)) pg_assert_false(strpos($mine, '__address_book_url') !== false, 'no address book without the shop');

	// The staff area leads to the two request lists and the staff's forms.
	$staff = json_encode(_test_cra_load()['pages']['staff']['tree']);
	foreach (array('staff_bookings', 'staff_enquiries', 'car_new', 'blog_new', 'staff_entry', 'inbox') as $key) {
		pg_assert_contains('{{page:' . $key . '}}', $staff, "staff area: $key");
	}
	pg_assert_contains('"templateShared":"staff_bar"', $staff, 'staff area carries the staff bar');
}

function test_carrental_account_post_form_and_samples()
{
	$d = _test_cra_load();
	$form = $d['pages']['blog_new']['form'];
	pg_assert_same(1, $form['collection']);
	pg_assert_same('cover_image', $form['share_image_field']);
	pg_assert_same('{{tab:blog}}', $form['confirmation_page_id']);
	$fields = _test_cra_fields($d['widgets']['blog_form']['tree']);
	// The Road Trip Journal's cards ($post_card) and the post page read these.
	foreach (array('title', 'summary', 'content', 'cover_image') as $f) pg_assert_true(in_array($f, $fields, true), "post form has $f");

	pg_assert_true(count($form['sample_records']) >= 4, 'sample posts');
	foreach ($form['sample_records'] as $post) {
		foreach (array_keys($post) as $f) pg_assert_true(in_array($f, $fields, true), "sample field $f is a form field");
		pg_assert_true($post['title'] !== '' && $post['content'] !== '', 'sample has a title and words');
		pg_assert_false((bool)preg_match('/\b(19|20)\d\d\b/', $post['title'] . ' ' . $post['summary'] . ' ' . $post['content']), 'sample names no year: ' . $post['title']);
	}

	$staff_fields = _test_cra_fields($d['widgets']['staff_form']['tree']);
	foreach ($d['widgets']['staff_directory']['config']['search_fields'] as $f) pg_assert_true(in_array($f, $staff_fields, true), "directory searches the form's $f");
	pg_assert_same('{{tab:staff_entry}}', $d['widgets']['staff_directory']['config']['custom_form_page_id']);
}

// Ids carry cr-, classes are Bootstrap's or the template's, no faded text.
function test_carrental_account_ids_and_classes()
{
	$d = _test_cra_load();
	$nodes = array();
	foreach ($d['pages'] as $p) _test_cr_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) if (is_array($w['tree'])) _test_cr_nodes($w['tree'], $nodes);
	pg_assert_true(count($nodes) > 100, 'nodes found');
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			if (preg_match('/^p[a-z]{2}-/', $c)) pg_assert_same(0, strpos($c, 'pcr-'), "class $c is the template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '') pg_assert_same(0, strpos($id, 'cr-'), "id $id is the template's");
	}
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-carrental/account.php');
	$src = preg_replace('/@copyright[^\n]*/', '', $src);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $src), 'no year');
}

// Every literal has its Turkish; a booking reads "rezervasyon", a deal
// "kampanya" or "fırsat", the fleet "filo".
function test_carrental_account_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-carrental/account.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	pg_assert_true(count($m[1]) > 100, 'literals found');
	$words = array('/\b[Bb]ooking/' => '/rezervasyon/iu', '/\b[Dd]eals?\b/' => '/(kampanya|fırsat)/iu', '/\b[Ff]leet\b/' => '/filo/iu');
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		if (preg_match('/^[a-z ,-]+$/', $key)) continue;
		foreach ($words as $re => $tr) {
			if (preg_match($re, $key)) pg_assert_true((bool)preg_match($tr, $map[$key]), "'$key' says $tr");
		}
		pg_assert_false(in_array($key, array('Bookings', 'Booking', 'Contact', 'Deposit', 'Bookings Card'), true), "'$key' means something else in Turkish");
	}
}
