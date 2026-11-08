<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Podcast Network design template's account and staff part
 * (includes/design_templates/pinegrap-podcast/account.php): the pages and
 * widgets with their keys and folders, the account widgets' settings, the
 * account bar and My Account linking the enquiries and the supporters'
 * area, the staff area's shortcuts, the news' form with its sample
 * articles, the staff directory, the account e-mails with their bound
 * links, every page link naming a page of the finished template and none a
 * shop page, the template's own ids and classes, no fixed date and every
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

// The page keys of the finished template, the shop's pages and the node
// walk.
require_once(__DIR__ . '/design_template_podcast_test.php');

// parts.php, mail.php and account.php in one scope, as the template file
// reads them. Read once per run.
function _test_ppda_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$part = include $dir . '/account.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets']);
	return $out;
}

// The page keys a tree links ({{page:…}}) and names in settings ({{tab:…}}).
function _test_ppda_links($tree)
{
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($tree), $m);
	return array_values(array_unique($m[1]));
}

function test_podcast_account_pages_and_widgets()
{
	$d = _test_ppda_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	$folders = array('login' => 'public', 'register' => 'public', 'forgot_password' => 'public', 'set_password' => 'public', 'logout' => 'public',
		'my_account' => 'registration', 'profile' => 'registration', 'change_password' => 'registration', 'email_preferences' => 'registration',
		'address_book' => 'registration', 'staff_directory' => 'registration', 'staff' => 'private', 'blog_new' => 'private',
		'staff_entry' => 'private', 'email_account_welcome' => 'private', 'email_password_reset' => 'private');
	pg_assert_same(array_keys($folders), array_keys($d['pages']));
	$known = array_flip(_test_ppd_page_keys());
	$names = array();
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a page of the finished template");
		pg_assert_same($folders[$key], $p['folder'], "$key: its folder");
		foreach (array('name', 'title', 'tree') as $f) pg_assert_true(!empty($p[$f]), "$key has $f");
		pg_assert_false(isset($names[$p['name']]), "$key: its address is its own");
		$names[$p['name']] = true;
		pg_assert_same($key === 'address_book', ($p['requires'] ?? '') === 'ecommerce', "$key needs the shop only if it is the address book");
		pg_assert_false($p['search'] ?? true, "$key stays out of the search");
	}
	foreach ($d['widgets'] as $key => $w) {
		if ($w['page'] !== '') pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of the part");
	}
	pg_assert_same('ecommerce', $d['widgets']['address_book']['requires'], 'the address book needs the shop');
}

// The sign-in widgets lead to each other and to My Account; the account
// e-mails are the template's own pages.
function test_podcast_account_settings()
{
	$d = _test_ppda_load();
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
	$json = json_encode($w['account_email_reset']['tree']);
	preg_match_all('/var\(--[a-z-]+\)/', $json, $bare);
	pg_assert_same(array(), $bare[0], 'no var() without its fallback');
}

// The account bar and My Account lead to the enquiries and the supporters'
// area; the staff area to every staff list and form.
function test_podcast_account_links()
{
	$d = _test_ppda_load();
	foreach (array('login_region', 'my_account') as $key) {
		$links = _test_ppda_links($d['widgets'][$key]['tree']);
		foreach (array('my_enquiries', 'supporters_home', 'inbox') as $page) pg_assert_true(in_array($page, $links, true), "$key links $page");
	}
	pg_assert_contains('"eo_visible_if":"is_staff"', json_encode($d['widgets']['my_account']['tree']), 'the staff link only for the staff');
	$staff = _test_ppda_links($d['pages']['staff']['tree']);
	foreach (array('staff_enquiries', 'staff_pitches', 'episode_new', 'podcast_new', 'blog_new', 'inbox', 'staff_entry') as $page) {
		pg_assert_true(in_array($page, $staff, true), "the staff area links $page");
	}
	$known = array_flip(_test_ppd_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	foreach (_test_ppda_links($trees) as $key) {
		pg_assert_true(isset($known[$key]), "{{page:$key}} names a template page");
		pg_assert_false(in_array($key, _test_ppd_shop_keys(), true), "no link to the shop page $key");
	}
}

// The news' form writes its sample articles; the directory lists the form
// the staff fill in.
function test_podcast_account_forms()
{
	$d = _test_ppda_load();
	$blog = $d['pages']['blog_new']['form'];
	pg_assert_same(1, $blog['collection'], 'the news is a collection');
	pg_assert_same('cover_image', $blog['share_image_field'], 'the cover is the share image');
	pg_assert_true(count($blog['sample_records']) >= 3, 'the news opens with its articles');
	foreach ($blog['sample_records'] as $n => $r) {
		foreach (array('title', 'summary', 'content', 'cover_image') as $f) pg_assert_true(trim($r[$f]) !== '', "article $n: $f");
	}
	$json = json_encode($d['widgets']['blog_form']['tree']);
	foreach (array('title', 'summary', 'cover_image', 'content') as $name) pg_assert_contains('"value":"' . $name . '"', $json, "the article form asks $name");
	pg_assert_same('{{tab:staff_entry}}', $d['widgets']['staff_directory']['config']['custom_form_page_id'], 'the directory lists the staff form');
	$json = json_encode($d['widgets']['staff_form']['tree']);
	foreach (array('full_name', 'job_title', 'department', 'photo', 'email', 'phone', 'bio') as $name) {
		pg_assert_contains('"value":"' . $name . '"', $json, "the staff form asks $name");
	}
}

function test_podcast_account_ids_and_classes()
{
	$d = _test_ppda_load();
	$nodes = array();
	foreach ($d['pages'] as $p) _test_ppd_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) {
		if (is_array($w['tree'])) _test_ppd_nodes($w['tree'], $nodes);
	}
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|pmu|pmk|psc|pmz|pai|psh|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'pd-') === 0, "id $id is the template's");
	}
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast/account.php');
	preg_match_all('/#[0-9a-f]{6}\b/i', $src, $colors);
	foreach (array_unique($colors[0]) as $hex) {
		pg_assert_true(in_array(strtolower($hex), array('#c2410c', '#fed7aa', '#fff7ed', '#ffffff', '#1f2937', '#4b5563'), true), "e-mail colour $hex is the template's");
	}
}

function test_podcast_account_no_fixed_dates()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast/account.php');
	$src = preg_replace('/@copyright[^\n]*/', '', $src);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $src), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $src), 'no month in the sample');
}

// Every literal has its Turkish; an episode reads "bölüm", a guest
// "konuk", and a page's address has no Turkish letters.
function test_podcast_account_turkish_words()
{
	$map = pg_test_language_map('tr');
	$d = _test_ppda_load();
	$names = array();
	foreach ($d['pages'] as $p) $names[$p['name']] = true;
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast/account.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		pg_assert_false(in_array($key, array('Show', 'Shows', 'Season', 'Show Notes', 'Guest', 'Guests', 'Host', 'Listening'), true), "'$key' reads otherwise in Turkish");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/^[a-z-]+$/', $key)) {
			// A page name is an address; a widget's slug may be a shared word.
			if (isset($names[$key])) pg_assert_true((bool)preg_match('/^[a-z-]+$/', $tr), "the address '$tr' has no Turkish letters");
			continue;
		}
		if (preg_match('/\b[Ee]pisodes?\b/', $key)) pg_assert_true(mb_stripos($tr, 'bölüm', 0, 'UTF-8') !== false, "'$key' says bölüm");
		if (preg_match('/\b[Gg]uests?\b/', $key)) pg_assert_true((bool)preg_match('/konu[kğ]/iu', $tr), "'$key' says konuk");
	}
}
