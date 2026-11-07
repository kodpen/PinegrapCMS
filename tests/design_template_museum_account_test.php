<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the account and staff part of the Museum design template
 * (includes/design_templates/pinegrap-museum/account.php): the sign-in,
 * My Account, staff and account e-mail pages load with their folders; with
 * every part loaded the template is whole (each link names a page it
 * carries, each placed widget is defined, keys are unique, the page list
 * matches); the account e-mails, the login region and My Account point at
 * the visitor's bookings and school visits; the staff area lists the
 * newest place requests; the blog form and its sample posts fit the lists;
 * ids are the template's own and every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_mz_page_keys()).
require_once __DIR__ . '/design_template_museum_test.php';

// Every part of the template as the template file reads them, in one
// scope. Read once per run.
function _test_mza_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-museum';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array();
	foreach (array('pages_public', 'exhibitions', 'objects', 'events', 'learning', 'shop', 'standard', 'account') as $part_name) {
		$part = include $dir . '/' . $part_name . '.php';
		$parts[$part_name] = array('pages' => $part['pages'], 'widgets' => $part['widgets']);
	}
	$pages = array();
	foreach ($parts['account']['pages'] as $p) $pages[$p['key']] = $p;
	$out = array(
		'parts'  => $parts,
		'pages'  => $pages,
		'shared' => array($account_bar_tree(), $header(), $footer(), $visit_band(), $notice(), $staff_bar(), $email_header_tree(), $email_footer_tree()),
	);
	return $out;
}

function test_museum_account_pages_load()
{
	$d = _test_mza_load();
	pg_assert_same(array('login', 'register', 'forgot_password', 'set_password', 'logout',
		'my_account', 'profile', 'change_password', 'email_preferences', 'address_book', 'staff_directory',
		'staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset'), array_keys($d['pages']));
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($p['name'], $p['title'], $p['tree'], $p['folder']), "$key: name, title, tree, folder");
		pg_assert_same('root', $p['tree']['type'], "$key: root tree");
		pg_assert_same(false, $p['sitemap'], "$key: out of the sitemap");
	}
	foreach (array('my_account', 'profile', 'change_password', 'email_preferences', 'address_book', 'staff_directory') as $key) {
		pg_assert_same('registration', $d['pages'][$key]['folder'], "$key: signed in");
	}
	foreach (array('staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "$key: private");
		pg_assert_same(true, $d['pages'][$key]['noindex'], "$key: noindex");
	}
	pg_assert_same('ecommerce', $d['pages']['address_book']['requires'], 'address book needs the shop');
}

// With every part loaded, the template is whole: each link names a page it
// carries, each placed widget is defined, each widget's page exists, and
// the shared page list is exactly what the parts carry.
function test_museum_account_template_is_whole()
{
	$d = _test_mza_load();
	$keys = array();
	$names = array();
	$widgets = array();
	foreach ($d['parts'] as $part) {
		foreach ($part['pages'] as $p) {
			pg_assert_false(isset($keys[$p['key']]), 'page key ' . $p['key'] . ' is unique');
			pg_assert_false(isset($names[$p['name']]), 'page name ' . $p['name'] . ' is unique');
			$keys[$p['key']] = true;
			$names[$p['name']] = true;
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
	foreach ($trees as $t) _test_mz_nodes($t, $nodes);
	foreach ($nodes as $n) {
		if (isset($n['props']['templateWidget'])) pg_assert_true(isset($widgets[$n['props']['templateWidget']]), 'widget ' . $n['props']['templateWidget'] . ' is defined');
	}
	foreach ($widgets as $k => $w) pg_assert_true($w['page'] === '' || isset($keys[$w['page']]), "widget $k: page {$w['page']}");

	$expected = _test_mz_page_keys();
	$got = array_keys($keys);
	sort($expected);
	sort($got);
	pg_assert_same($expected, $got, 'the page list matches the parts');
}

function test_museum_account_emails_and_widgets()
{
	$d = _test_mza_load();
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
	foreach (array('my_bookings', 'inbox') as $key) pg_assert_contains('{{page:' . $key . '}}', $region, "account bar menu links $key");

	// The staff area places the newest place requests, without the search
	// the full staff list has.
	pg_assert_contains('"templateWidget":"staff_bookings_new"', json_encode($d['pages']['staff']['tree']), 'staff page places the place requests');
	$list = $w['staff_bookings_new'];
	pg_assert_same('staff', $list['page']);
	pg_assert_same(10, $list['config']['items_per_page']);
	pg_assert_same('{{tab:event_booking}}', $list['config']['custom_form_page_id']);
	pg_assert_same('{{tab:booking_view}}', $list['config']['detail_page_id']);
	pg_assert_false(isset($list['config']['search_enabled']) || isset($list['config']['viewer_filter']), 'no search, no viewer filter');
	$staff = json_encode($d['pages']['staff']['tree']);
	foreach (array('staff_bookings', 'staff_school_visits', 'exhibition_new', 'object_new', 'event_new', 'inbox', 'blog_new', 'staff_entry') as $key) {
		pg_assert_contains('{{page:' . $key . '}}', $staff, "staff area links $key");
	}

	// My Account leads to the visitor's bookings and school visits, and
	// shows the orders (tickets and memberships) to a member with any.
	$menu = json_encode($w['my_account']['tree']);
	pg_assert_contains('"order_history"', $menu, 'order history');
	pg_assert_contains('"has_orders"', $menu, 'its heading only with orders');
	foreach (array('my_bookings', 'my_school_visits', 'school_visit', 'events', 'exhibitions', 'inbox') as $key) {
		pg_assert_contains('{{page:' . $key . '}}', $menu, "account menu links $key");
	}
	foreach (array('tickets', 'membership', 'cart') as $key) pg_assert_false(strpos($menu, '{{page:' . $key . '}}') !== false, "no link to the shop's $key");
}

function test_museum_account_blog_form_and_samples()
{
	$d = _test_mza_load();
	$form = $d['pages']['blog_new']['form'];
	pg_assert_same(1, $form['collection']);
	pg_assert_same('cover_image', $form['share_image_field']);
	$nodes = array();
	_test_mz_nodes($d['parts']['account']['widgets']['blog_form']['tree'], $nodes);
	$fields = array();
	$cover = null;
	foreach ($nodes as $n) {
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) {
			if ($a['name'] !== 'name') continue;
			$fields[] = $a['value'];
			if ($a['value'] === 'cover_image') $cover = $n;
		}
	}
	// The lists and the post page (standard.php) read these.
	foreach (array('title', 'summary', 'content', 'cover_image') as $f) pg_assert_true(in_array($f, $fields, true), "blog form has $f");
	pg_assert_true($cover !== null, 'blog form has a cover input');
	pg_assert_same('image', $cover['props']['_cf']['kind'] ?? null, 'the cover takes images only');

	pg_assert_same(4, count($form['sample_records']), 'four sample posts');
	foreach ($form['sample_records'] as $post) {
		foreach (array_keys($post) as $f) pg_assert_true(in_array($f, $fields, true), "sample field $f is a form field");
		pg_assert_true($post['title'] !== '' && $post['summary'] !== '' && $post['content'] !== '', 'sample has a title, summary and words');
	}
	$text = json_encode(array($form['sample_records'], $d['parts']['account']), JSON_UNESCAPED_UNICODE);
	pg_assert_false((bool)preg_match('/\b20[2-3]\d\b/', $text), 'no year');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $text), 'no month');
}

// Ids and classes are the template's own; every literal has its Turkish,
// an exhibition reads "sergi", an event "etkinlik", a school visit "okul
// ziyareti".
function test_museum_account_ids_and_turkish()
{
	$d = _test_mza_load();
	$nodes = array();
	foreach ($d['pages'] as $p) _test_mz_nodes($p['tree'], $nodes);
	foreach ($d['parts']['account']['widgets'] as $w) if (is_array($w['tree'])) _test_mz_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'mz-') === 0, "id $id is the template's");
	}
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-museum/account.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || preg_match('/^[a-z0-9-]+$/', $key)) continue;
		$tr = $map[$key];
		if (preg_match('/\b[Ee]xhibitions?\b/', $key)) pg_assert_true(mb_stripos($tr, 'sergi', 0, 'UTF-8') !== false, "'$key' says sergi");
		if (preg_match('/\b[Ee]vents?\b/', $key)) pg_assert_true(mb_stripos($tr, 'etkinli', 0, 'UTF-8') !== false, "'$key' says etkinlik");
		if (preg_match('/\b[Ss]chool [Vv]isits?\b/', $key)) pg_assert_true(mb_stripos($tr, 'okul ziyaret', 0, 'UTF-8') !== false, "'$key' says okul ziyareti");
	}
}
