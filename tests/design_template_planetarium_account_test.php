<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Planetarium design template's account and staff part
 * (includes/design_templates/pinegrap-planetarium/account.php): the pages
 * and widgets with their keys and folders, the account widgets' settings,
 * the account bar and My Account linking the visit requests, the staff
 * area's shortcuts, the news' form with its sample articles and the
 * fields the news pages read, the staff directory, the account e-mails
 * with their bound links, every page link naming a page of the finished
 * template and none a shop page, keys and ids unique across the parts, the
 * template's own ids and classes, headings, no fixed date and every
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

// The page keys of the finished template, the node walker and the
// standard pages' loader.
require_once(__DIR__ . '/design_template_planetarium_standard_test.php');

// Every part as the template file reads them, this part last, in one
// scope. Read once per run.
function _test_plac_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-planetarium';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	$parts = array();
	foreach (array('pages_public', 'shows', 'nights') as $part_file) $parts[$part_file] = include $dir . '/' . $part_file . '.php';
	include $dir . '/mail.php';
	foreach (array('visits', 'shop', 'standard', 'account') as $part_file) $parts[$part_file] = include $dir . '/' . $part_file . '.php';
	$pages = array();
	foreach ($parts['account']['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('parts' => $parts, 'part' => $parts['account'], 'pages' => $pages, 'widgets' => $parts['account']['widgets']);
	return $out;
}

// The page keys a tree links ({{page:…}}) and names in settings ({{tab:…}}).
function _test_plac_links($tree)
{
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($tree), $m);
	return array_values(array_unique($m[1]));
}

function test_planetarium_account_pages_and_widgets()
{
	$d = _test_plac_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	$folders = array('login' => 'public', 'register' => 'public', 'forgot_password' => 'public', 'set_password' => 'public', 'logout' => 'public',
		'my_account' => 'registration', 'profile' => 'registration', 'change_password' => 'registration', 'email_preferences' => 'registration',
		'address_book' => 'registration', 'staff_directory' => 'registration', 'staff' => 'private', 'blog_new' => 'private',
		'staff_entry' => 'private', 'email_account_welcome' => 'private', 'email_password_reset' => 'private');
	pg_assert_same(array_keys($folders), array_keys($d['pages']));
	$known = array_flip(_test_planetarium_page_keys());
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a page of the finished template");
		pg_assert_same($folders[$key], $p['folder'], "$key: its folder");
		foreach (array('name', 'title', 'tree') as $f) pg_assert_true(!empty($p[$f]), "$key has $f");
		pg_assert_same('root', $p['tree']['type'], "$key: root tree");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
		pg_assert_same($key === 'address_book', ($p['requires'] ?? '') === 'ecommerce', "$key needs the shop only if it is the address book");
		pg_assert_false($p['search'] ?? true, "$key stays out of the search");
		if ($p['folder'] === 'private') pg_assert_true(!empty($p['noindex']), "$key: not indexed");
	}
	foreach (array('staff', 'blog_new', 'staff_entry') as $key) {
		pg_assert_contains('"templateShared":"staff_bar"', json_encode($d['pages'][$key]['tree']), "$key carries the staff bar");
	}
	foreach ($d['widgets'] as $key => $w) {
		if ($w['page'] !== '') {
			pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of the part");
			pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
		}
	}
	pg_assert_same('ecommerce', $d['widgets']['address_book']['requires'], 'the address book needs the shop');

	// Keys and addresses unique across every part of the template.
	$keys = array();
	$names = array();
	$widgets = array();
	foreach ($d['parts'] as $part) {
		$widgets = array_merge($widgets, array_keys($part['widgets']));
		foreach ($part['pages'] as $p) {
			$keys[] = $p['key'];
			$names[] = $p['name'];
		}
	}
	pg_assert_same(count($keys), count(array_unique($keys)), 'page keys are unique across the parts');
	pg_assert_same(count($names), count(array_unique($names)), 'page names are unique across the parts');
	pg_assert_same(count($widgets), count(array_unique($widgets)), 'widget keys are unique across the parts');
	pg_assert_same(array(), array_values(array_diff(_test_planetarium_page_keys(), $keys)), 'every page of the template is built by a part');
}

// The sign-in widgets lead to each other and to My Account; the account
// e-mails are the template's own pages.
function test_planetarium_account_settings()
{
	$d = _test_plac_load();
	$w = $d['widgets'];
	pg_assert_same('{{tab:staff}}', $w['login_region']['config']['staff_page_id'], 'the login region knows the staff area');
	pg_assert_same('{{tab:my_account}}', $w['login_region']['config']['account_page_id'], 'and My Account');
	pg_assert_same('{{tab:forgot_password}}', $w['login']['config']['forgot_password_page_id'], 'sign-in leads to the forgotten password');
	pg_assert_same('{{tab:email_account_welcome}}', $w['register']['config']['welcome_email_page_id'], 'the welcome e-mail');
	pg_assert_same('{{tab:email_password_reset}}', $w['forgot_password']['config']['reset_email_page_id'], 'the reset e-mail');
	foreach (array('account_email_welcome' => '__my_account_url', 'account_email_reset' => '__set_password_url') as $key => $token) {
		pg_assert_same('account_email', $w[$key]['config']['regionType'], "$key is an account e-mail");
		pg_assert_contains('"href":"' . $token . '"', json_encode($w[$key]['tree']), "$key's button is bound to $token");
	}
	foreach (array('email_account_welcome', 'email_password_reset') as $key) {
		$tree = json_encode($d['pages'][$key]['tree']);
		pg_assert_contains('"templateShared":"email_header"', $tree, "$key: the shared e-mail header");
		pg_assert_contains('"templateShared":"email_footer"', $tree, "$key: the shared e-mail footer");
	}
	preg_match_all('/var\(--[a-z-]+\)/', json_encode(array($w['account_email_welcome']['tree'], $w['account_email_reset']['tree'])), $bare);
	pg_assert_same(array(), $bare[0], 'no var() without its fallback');
}

// The account bar and My Account lead to the visit requests; the staff
// area to every staff list and form.
function test_planetarium_account_links()
{
	$d = _test_plac_load();
	foreach (array('login_region', 'my_account') as $key) {
		$links = _test_plac_links($d['widgets'][$key]['tree']);
		foreach (array('my_visits', 'inbox') as $page) pg_assert_true(in_array($page, $links, true), "$key links $page");
	}
	pg_assert_contains('"eo_visible_if":"is_staff"', json_encode($d['widgets']['my_account']['tree']), 'the staff link only for the staff');
	pg_assert_contains('"section":"order_history"', json_encode($d['widgets']['my_account']['tree']), 'tickets and memberships among the orders');
	$staff = _test_plac_links($d['pages']['staff']['tree']);
	foreach (array('staff_visits', 'show_new', 'night_new', 'blog_new', 'inbox', 'staff_entry') as $page) {
		pg_assert_true(in_array($page, $staff, true), "the staff area links $page");
	}
	$known = array_flip(_test_planetarium_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	foreach (_test_plac_links($trees) as $key) {
		pg_assert_true(isset($known[$key]), "{{page:$key}} names a template page");
		pg_assert_false(in_array($key, _test_planetarium_shop_keys(), true), "no link to the shop page $key");
	}
	pg_assert_false(strpos(json_encode($d['part']), '?view=') !== false, 'never ?view=');
}

// The news' form writes its sample articles with the fields the news
// pages read; the directory lists the form the staff fill in.
function test_planetarium_account_forms()
{
	$d = _test_plac_load();
	$blog = $d['pages']['blog_new']['form'];
	pg_assert_same(1, $blog['collection'], 'the news is a collection');
	pg_assert_same('{{tab:blog}}', $blog['confirmation_page_id'], 'an article sent opens the news');
	pg_assert_same('cover_image', $blog['share_image_field'], 'the cover is the share image');
	pg_assert_true(count($blog['sample_records']) >= 3, 'the news opens with its articles');
	foreach ($blog['sample_records'] as $n => $r) {
		foreach (array('title', 'summary', 'content', 'cover_image') as $f) pg_assert_true(trim($r[$f]) !== '', "article $n: $f");
		pg_assert_true(strpos($r['cover_image'], 'pinegrap-planetarium-') !== false, "article $n: the template's own sample picture");
	}
	$json = json_encode($d['widgets']['blog_form']['tree']);
	foreach (array('title', 'summary', 'cover_image', 'content') as $name) pg_assert_contains('"value":"' . $name . '"', $json, "the article form asks $name");
	// The news pages of the standard part read these fields.
	$standard = json_encode($d['parts']['standard']['widgets']);
	foreach (array('title', 'summary', 'content', 'cover_image') as $name) pg_assert_contains('"' . $name . '"', $standard, "the news pages read $name");
	pg_assert_same('{{tab:staff_entry}}', $d['widgets']['staff_directory']['config']['custom_form_page_id'], 'the directory lists the staff form');
	$json = json_encode($d['widgets']['staff_form']['tree']);
	foreach (array('full_name', 'job_title', 'department', 'photo', 'email', 'phone', 'bio') as $name) {
		pg_assert_contains('"value":"' . $name . '"', $json, "the staff form asks $name");
	}
	$list = json_encode($d['widgets']['staff_directory']['tree']);
	foreach (array('full_name', 'job_title', 'department', 'email', 'phone', 'bio') as $name) {
		pg_assert_contains('"text":"' . $name . '"', $list, "the directory shows $name");
	}
}

function test_planetarium_account_ids_classes_and_headings()
{
	$d = _test_plac_load();
	$ids = array();
	foreach ($d['parts'] as $part_key => $part) {
		if ($part_key === 'account') continue;
		$trees = array();
		foreach ($part['pages'] as $p) $trees[] = $p['tree'];
		foreach ($part['widgets'] as $w) $trees[] = $w['tree'];
		foreach ($trees as $tree) {
			foreach (_test_plp_nodes($tree) as $n) {
				$id = (string)($n['props']['id'] ?? '');
				if ($id !== '') $ids[$id] = true;
			}
		}
	}
	$trees = array();
	foreach ($d['pages'] as $key => $p) $trees[$key] = $p['tree'];
	foreach ($d['widgets'] as $key => $w) {
		if (is_array($w['tree'])) $trees['widget ' . $key] = $w['tree'];
	}
	foreach ($trees as $key => $tree) {
		foreach (_test_plp_nodes($tree) as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				if (preg_match('/^([a-z]{3})-/', $c, $pre) && $pre[1][0] === 'p') pg_assert_true($pre[1] === 'ppm', "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false(strpos($c, 'opacity-') === 0, "$key: no faded text ($c)");
				pg_assert_false(in_array($c, array('text-white', 'bg-white', 'bg-dark', 'text-dark', 'btn-light', 'text-muted', 'text-body-tertiary'), true), "$key: no fixed or faint colour ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'plm-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$id]), "id $id once, beside the other parts' ids");
			$ids[$id] = true;
		}
	}
	// One h1 and no heading level skipped, the widgets drawn in place.
	$expand = function ($node) use (&$expand, $d) {
		if (!is_array($node)) return $node;
		$w = $node['props']['templateWidget'] ?? '';
		if ($w !== '' && isset($d['widgets'][$w]) && is_array($d['widgets'][$w]['tree'])) return $expand($d['widgets'][$w]['tree']);
		if (!empty($node['children'])) $node['children'] = array_map($expand, $node['children']);
		return $node;
	};
	foreach (array('my_account', 'staff', 'staff_directory', 'blog_new', 'staff_entry') as $key) {
		$levels = array();
		foreach (_test_plp_nodes($expand($d['pages'][$key]['tree'])) as $n) {
			if (preg_match('/^h([1-6])$/', (string)($n['props']['tag'] ?? ''), $m)) $levels[] = (int)$m[1];
		}
		pg_assert_same(1, count(array_keys($levels, 1, true)), "$key: one h1");
		$set = array_values(array_unique($levels));
		sort($set);
		pg_assert_same(range(1, max($set)), $set, "$key: no heading level skipped");
	}
	// The e-mails keep to the colours of the template's e-mail frame.
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-planetarium/account.php');
	preg_match_all('/#[0-9a-f]{6}\b/i', $src, $colors);
	foreach (array_unique($colors[0]) as $hex) {
		pg_assert_true(in_array(strtolower($hex), array('#4338ca', '#dcdcef', '#f3f3fa', '#ffffff', '#1f2937', '#4b5563'), true), "e-mail colour $hex is the template's");
	}
}

function test_planetarium_account_no_fixed_dates()
{
	$json = rawurldecode(json_encode(_test_plac_load()['part'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; the dome reads "kubbe", a telescope night
// "teleskop gecesi", and a page's address has no Turkish letters.
function test_planetarium_account_turkish_words()
{
	$map = pg_test_language_map('tr');
	$d = _test_plac_load();
	$names = array();
	foreach ($d['pages'] as $p) $names[$p['name']] = true;
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-planetarium/account.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/^[a-z-]+$/', $key)) {
			// A page name is an address; a widget's slug may be a shared word.
			if (isset($names[$key])) pg_assert_true((bool)preg_match('/^[a-z-]+$/', $tr), "the address '$tr' has no Turkish letters");
			continue;
		}
		if (preg_match('/\bdome\b/i', $key)) pg_assert_true(mb_stripos($tr, 'kubbe', 0, 'UTF-8') !== false, "'$key' says kubbe");
		if (preg_match('/\btelescope nights?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'teleskop gece', 0, 'UTF-8') !== false, "'$key' says teleskop gecesi");
	}
}
