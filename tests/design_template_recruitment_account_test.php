<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Recruitment Agency design template's account and staff
 * part (includes/design_templates/pinegrap-recruitment/account.php):
 * signing in and out, My Account and its pages, the login region, the staff
 * area with the newest applications and staff requests, the Career Advice
 * form and its sample guides, the staff directory, the account e-mails;
 * page links naming template pages, ids and classes the template's own, no
 * fixed date and every literal with its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys (_test_rc_page_keys()) and the tree helpers
// (_test_rca_nodes(), _test_rca_controls()).
require_once(__DIR__ . '/design_template_recruitment_test.php');
require_once(__DIR__ . '/design_template_recruitment_applications_test.php');

// The account part as the template file reads it: parts.php, mail.php,
// jobs.php, applications.php, employers.php, standard.php, then
// account.php in the same scope. Read once per run.
function _test_rcac_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-recruitment';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	include $dir . '/jobs.php';
	include $dir . '/applications.php';
	include $dir . '/employers.php';
	include $dir . '/standard.php';
	$part = include $dir . '/account.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'],
		'standard_widgets' => $standard_widgets, 'sectors' => $enquiry_sector_choices);
	return $out;
}

function test_recruitment_account_pages_and_widgets()
{
	$d = _test_rcac_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('login', 'register', 'forgot_password', 'set_password', 'logout', 'my_account', 'profile', 'change_password',
		'email_preferences', 'address_book', 'staff_directory', 'staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset'),
		array_keys($d['pages']));
	foreach ($d['pages'] as $key => $p) {
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_same(false, $p['sitemap'], "$key: out of the sitemap");
		pg_assert_same(false, $p['search'], "$key: out of the search");
	}
	foreach (array('login', 'register', 'forgot_password', 'set_password', 'logout') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	foreach (array('my_account', 'profile', 'change_password', 'email_preferences', 'address_book', 'staff_directory') as $key) {
		pg_assert_same('registration', $d['pages'][$key]['folder'], "$key is read signed in");
	}
	foreach (array('staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "$key is private");
		pg_assert_true($d['pages'][$key]['noindex'], "$key is not indexed");
	}
	pg_assert_same('ecommerce', $d['pages']['address_book']['requires'], 'only the address book needs the shop');
	pg_assert_same('ecommerce', $d['widgets']['address_book']['requires']);
	foreach ($d['pages'] as $key => $p) {
		if ($key !== 'address_book') pg_assert_false(isset($p['requires']), "$key stands without the shop");
	}
	foreach ($d['widgets'] as $key => $w) {
		if ($w['page'] === '') continue;
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of this part");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	// The account bar places the login region.
	pg_assert_contains("\$widget('login_region')", file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-recruitment/parts.php'), 'the account bar places the login region');
}

function test_recruitment_account_menus_and_mail()
{
	$d = _test_rcac_load();
	$w = $d['widgets'];
	pg_assert_same('{{tab:email_account_welcome}}', $w['register']['config']['welcome_email_page_id']);
	pg_assert_same('{{tab:email_password_reset}}', $w['forgot_password']['config']['reset_email_page_id']);
	pg_assert_contains('"href":"__set_password_url"', json_encode($w['account_email_reset']['tree']), 'the reset button carries the set password link');
	pg_assert_contains('"href":"__my_account_url"', json_encode($w['account_email_welcome']['tree']), 'the welcome button opens the account');
	foreach (array('account_email_welcome', 'account_email_reset') as $key) pg_assert_same('account_email', $w[$key]['config']['regionType']);
	foreach (array('email_account_welcome', 'email_password_reset') as $key) {
		pg_assert_contains('"templateShared":"email_header"', json_encode($d['pages'][$key]['tree']), "$key: the e-mail header");
	}
	pg_assert_same('{{tab:my_account}}', $w['login_region']['config']['account_page_id']);
	pg_assert_same('{{tab:staff}}', $w['login_region']['config']['staff_page_id']);
	pg_assert_same('{{tab:staff}}', $w['my_account']['config']['staff_page_id']);
	pg_assert_false($w['my_account']['config']['show_submissions'], 'My Account draws its own cards');
	$region = json_encode($w['login_region']['tree']);
	$menu = json_encode($w['my_account']['tree']);
	foreach (array('my_applications', 'my_enquiries', 'inbox') as $key) {
		pg_assert_contains('{{page:' . $key . '}}', $region, "the account bar menu links $key");
		pg_assert_contains('{{page:' . $key . '}}', $menu, "My Account links $key");
	}
	pg_assert_contains('"eo_visible_if":"is_staff"', $menu, 'the staff link only for the staff');
	pg_assert_false(strpos($menu . $region, 'order_history') !== false, 'no order history');
}

// The staff area shows the newest ten applications and staff requests:
// the staff lists without their search, on the staff page.
function test_recruitment_account_staff_area()
{
	$d = _test_rcac_load();
	$staff = json_encode($d['pages']['staff']['tree']);
	foreach (array('staff_applications_new' => array('apply', 'application_view'), 'staff_enquiries_new' => array('hire', 'enquiry_view')) as $key => $pages) {
		$list = $d['widgets'][$key];
		pg_assert_contains('"templateWidget":"' . $key . '"', $staff, "the staff page places $key");
		pg_assert_same('staff', $list['page']);
		pg_assert_same(10, $list['config']['items_per_page']);
		pg_assert_same('{{tab:' . $pages[0] . '}}', $list['config']['custom_form_page_id']);
		pg_assert_same('{{tab:' . $pages[1] . '}}', $list['config']['detail_page_id']);
		pg_assert_false(isset($list['config']['search_enabled']) || isset($list['config']['viewer_filter']), "$key: no search, no viewer filter");
	}
	foreach (array('job_new', 'staff_applications', 'staff_enquiries', 'inbox', 'blog_new', 'staff_entry') as $key) {
		pg_assert_contains('{{page:' . $key . '}}', $staff, "the staff area links $key");
	}
}

// The Career Advice form and its sample guides, which the post views read;
// the directory form and its list.
function test_recruitment_account_forms_and_samples()
{
	$d = _test_rcac_load();
	$form = $d['pages']['blog_new']['form'];
	pg_assert_same(1, $form['collection']);
	pg_assert_same('cover_image', $form['share_image_field']);
	pg_assert_same('{{tab:blog}}', $form['confirmation_page_id']);
	$c = _test_rca_controls($d['widgets']['blog_form']['tree']);
	pg_assert_same(array('title', 'summary', 'cover_image', 'content'), array_keys($c));
	pg_assert_same('{{folder:public}}', $c['cover_image']['cf']['upload_folder_id'] ?? '', 'the cover is public');
	pg_assert_same('image', $c['cover_image']['cf']['kind'] ?? '', 'the cover takes pictures only');
	pg_assert_same(4, count($form['sample_records']), 'four sample guides');
	foreach ($form['sample_records'] as $post) {
		pg_assert_same(array('title', 'summary', 'content', 'cover_image'), array_keys($post), 'a sample fills the form\'s fields');
		pg_assert_true(trim($post['title']) !== '' && substr_count($post['content'], "\n\n") === 2, 'a sample has a title and three paragraphs');
	}
	$titles = array_column($form['sample_records'], 'title');
	pg_assert_contains(json_encode($titles[0]), json_encode($d['standard_widgets']['blog_post']['tree']), 'the post page\'s sample is the first guide');
	$s = _test_rca_controls($d['widgets']['staff_form']['tree']);
	pg_assert_same(array('full_name', 'job_title', 'sector', 'photo', 'email', 'phone', 'bio'), array_keys($s));
	pg_assert_same($d['sectors'], $s['sector']['options'], 'the directory names the sectors');
	pg_assert_same('{{tab:staff_entry}}', $d['widgets']['staff_directory']['config']['custom_form_page_id']);
	foreach ($d['widgets']['staff_directory']['config']['search_fields'] as $f) pg_assert_true(isset($s[$f]), "the directory searches $f, a field of its form");
	$nodes = array();
	_test_rca_nodes($d['widgets']['staff_directory']['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (array('text', 'src', 'alt') as $prop) {
			$token = (string)($n['props']['_bindings'][$prop] ?? '');
			if ($token !== '') pg_assert_true(isset($s[$token]), "the directory binds $token, a field of its form");
		}
	}
}

function test_recruitment_account_links_ids_and_classes()
{
	$d = _test_rcac_load();
	$known = array_flip(_test_rc_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array_keys($d['pages']) as $key) pg_assert_true(isset($known[$key]), "$key is a page of the finished template");
	$nodes = array();
	foreach ($d['pages'] as $p) _test_rca_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_rca_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^p[a-z]{2}-/', $c) && strpos($c, 'prc-') !== 0 && strpos($c, 'pg-') !== 0, "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'rc-') === 0, "id $id is the template's");
	}
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-recruitment/account.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $mm);
	foreach ($mm[1] as $raw) {
		pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b|\b(January|February|March|April|May|June|July|August|September|October|November|December)\b/', $raw), "'$raw' names no fixed date");
	}
}

// Every literal has its Turkish; Career Advice reads "Kariyer Rehberi", an
// application "başvuru", a staff request "personel talebi", a consultant
// "danışman", a candidate "aday".
function test_recruitment_account_turkish_words()
{
	$map = pg_test_language_map('tr');
	$words = array(
		'/\bcareer advice\b/i'    => 'kariyer rehber',
		'/\bapplications?\b/i'    => 'başvuru',
		'/\bstaff requests?\b/i'  => 'personel tale',
		'/\bconsultants?\b/i'     => 'danışman',
		'/\bcandidates?\b/i'      => 'aday',
	);
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-recruitment/account.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || preg_match('/^[a-z0-9-]+$/', $key)) continue;
		$value = mb_strtolower(str_replace('İ', 'i', $map[$key]), 'UTF-8');
		foreach ($words as $re => $tr) {
			if (preg_match($re, $key)) pg_assert_true(mb_stripos($value, $tr, 0, 'UTF-8') !== false, "'$key' says $tr");
		}
	}
}
