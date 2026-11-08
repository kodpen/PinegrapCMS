<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Home Services design template's account and staff part
 * (includes/design_templates/pinegrap-homeservices/account.php): the
 * sign-in and My Account pages and their folders, the account e-mails
 * wired to their widgets, the login region and My Account leading to the
 * visits and estimates, the staff area's ways in, the Home Care Tips form
 * with its sample articles, the staff directory, no widget defined twice
 * across the parts, every page link naming a template page and none a
 * shop page, ids and classes the template's own, no fixed date, and every
 * literal with its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template, the shop's pages, and the
// control and node readers of the visits' test.
require_once(__DIR__ . '/design_template_homeservices_visits_test.php');

// Every part as the template file reads it, account.php last. Read once
// per run.
function _test_hsa_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-homeservices';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$others = array();
	foreach (array('pages_public', 'services', 'visits', 'estimates', 'shop', 'standard') as $name) {
		$others[$name] = include $dir . '/' . $name . '.php';
	}
	$part = include $dir . '/account.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	return $out = array('pages' => $pages, 'widgets' => $part['widgets'], 'others' => $others, 'account_bar' => $account_bar_tree());
}

function test_homeservices_account_pages_and_folders()
{
	$d = _test_hsa_load();
	pg_assert_same(array('login', 'register', 'forgot_password', 'set_password', 'logout', 'my_account', 'profile', 'change_password',
		'email_preferences', 'address_book', 'staff_directory', 'staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset'),
		array_keys($d['pages']));
	foreach (array('login', 'register', 'forgot_password', 'set_password', 'logout') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	foreach (array('my_account', 'profile', 'change_password', 'email_preferences', 'address_book', 'staff_directory') as $key) {
		pg_assert_same('registration', $d['pages'][$key]['folder'], "$key: signed in");
	}
	foreach (array('staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "$key is private");
		pg_assert_true(!empty($d['pages'][$key]['noindex']), "$key: noindex");
	}
	foreach ($d['pages'] as $key => $p) {
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_same(false, $p['sitemap'], "$key: out of the sitemap");
		if ($key !== 'address_book') pg_assert_false(isset($p['requires']), "$key stands without the shop");
	}
	pg_assert_same('ecommerce', $d['pages']['address_book']['requires'], 'the address book needs the shop');
	pg_assert_same('ecommerce', $d['widgets']['address_book']['requires'], 'its widget too');
}

// Every widget sits on a template page and is placed there; none is also
// defined by another part; the account bar places the login region.
function test_homeservices_account_widgets_placed_once()
{
	$d = _test_hsa_load();
	$known = array_flip(_test_hs_page_keys());
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true(isset($w['config']['regionType'], $w['tree']), "$key: config and tree");
		foreach ($d['others'] as $name => $part) pg_assert_false(isset($part['widgets'][$key]), "$key is not also in $name.php");
		if ($w['page'] === '') continue;
		pg_assert_true(isset($known[$w['page']]), "$key sits on a template page");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	pg_assert_contains('"templateWidget":"login_region"', json_encode($d['account_bar']), 'the account bar places the login region');
}

// The sign-up and reset e-mails, the login region and My Account leading
// to the visits and estimates, and the staff area's ways in.
function test_homeservices_account_emails_menus_and_staff()
{
	$d = _test_hsa_load();
	$w = $d['widgets'];
	pg_assert_same('{{tab:email_account_welcome}}', $w['register']['config']['welcome_email_page_id']);
	pg_assert_same('{{tab:email_password_reset}}', $w['forgot_password']['config']['reset_email_page_id']);
	pg_assert_contains('"href":"__set_password_url"', json_encode($w['account_email_reset']['tree']), 'the reset button carries the set password link');
	pg_assert_contains('"text":"__user_first_name"', json_encode($w['account_email_welcome']['tree']), 'the welcome greets the member by name');
	pg_assert_same('{{tab:my_account}}', $w['login_region']['config']['account_page_id']);
	pg_assert_same('{{tab:staff}}', $w['login_region']['config']['staff_page_id']);
	pg_assert_same('{{tab:staff}}', $w['my_account']['config']['staff_page_id']);
	foreach (array('login_region', 'my_account') as $key) {
		$json = json_encode($w[$key]['tree']);
		foreach (array('my_visits', 'my_estimates', 'inbox') as $page) pg_assert_contains('{{page:' . $page . '}}', $json, "$key leads to $page");
		pg_assert_contains('"eo_visible_if":"is_staff"', $json, "$key: the staff link only for the staff");
	}
	pg_assert_contains('"section":"order_history"', json_encode($w['my_account']['tree']), 'my account: order history');
	pg_assert_contains('"eo_visible_if":"has_orders"', json_encode($w['my_account']['tree']), 'my orders heading only with orders');
	$staff = json_encode($d['pages']['staff']['tree']);
	foreach (array('staff_visits', 'staff_estimates', 'service_new', 'team_new', 'blog_new', 'inbox', 'staff_entry') as $page) {
		pg_assert_contains('{{page:' . $page . '}}', $staff, "the staff area leads to $page");
	}
}

// The article form makes the Home Care Tips collection with its sample
// articles, every field one of the form's, none dated; the directory reads
// its form.
function test_homeservices_account_tips_form_and_directory()
{
	$d = _test_hsa_load();
	$form = $d['pages']['blog_new']['form'];
	pg_assert_same(1, $form['collection']);
	pg_assert_same('cover_image', $form['share_image_field']);
	pg_assert_same('{{tab:blog}}', $form['confirmation_page_id']);
	$fields = array_keys(_test_hsv_controls($d['widgets']['blog_form']['tree']));
	pg_assert_same(array('title', 'summary', 'cover_image', 'content'), $fields, 'the article fields');
	pg_assert_true(count($form['sample_records']) >= 4, 'sample articles');
	foreach ($form['sample_records'] as $post) {
		foreach (array_keys($post) as $f) pg_assert_true(in_array($f, $fields, true), "sample field $f is a form field");
		pg_assert_true(trim($post['title']) !== '' && trim($post['content']) !== '', 'sample has a title and words');
	}
	$staff_fields = array_keys(_test_hsv_controls($d['widgets']['staff_form']['tree']));
	foreach ($d['widgets']['staff_directory']['config']['search_fields'] as $f) pg_assert_true(in_array($f, $staff_fields, true), "the directory searches the form's $f");
	pg_assert_same('{{tab:staff_entry}}', $d['widgets']['staff_directory']['config']['custom_form_page_id']);
}

function test_homeservices_account_links_ids_and_classes()
{
	$d = _test_hsa_load();
	$known = array_flip(_test_hs_page_keys());
	$json = json_encode(array($d['pages'], $d['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_hs_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$nodes = array();
	foreach ($d['pages'] as $p) _test_hsv_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_hsv_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pbk|psh|pwd|pmu)-/', $c), "class $c is not another template's");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'hs-') === 0, "id $id is the template's");
	}
}

// The sample is never dated.
function test_homeservices_account_no_fixed_dates()
{
	$d = _test_hsa_load();
	$json = json_encode(array($d['pages'], $d['widgets']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$json = str_replace(array('www.w3.org/2000/svg', '{{year}}'), '', $json);
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; a visit reads "ziyaret", a service
// "hizmet", an estimate "teklif", and none says "rezervasyon".
function test_homeservices_account_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-homeservices/account.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\b[Vv]isits?\b/', $key)) pg_assert_true(mb_stripos($tr, 'ziyaret', 0, 'UTF-8') !== false, "'$key' says ziyaret");
		if (preg_match('/\b[Ss]ervices?\b/', $key)) pg_assert_true(mb_stripos($tr, 'hizmet', 0, 'UTF-8') !== false, "'$key' says hizmet");
		if (preg_match('/\b[Ee]stimates?\b/', $key)) pg_assert_true(mb_stripos($tr, 'teklif', 0, 'UTF-8') !== false, "'$key' says teklif");
		pg_assert_false(mb_stripos($tr, 'rezervasyon', 0, 'UTF-8') !== false, "'$key' never says rezervasyon");
	}
	foreach (array("lang('Service')", "lang('Services')", "lang('Booking')", "lang('book')") as $k) {
		pg_assert_false(strpos($src, $k) !== false, "account.php does not use $k");
	}
}
