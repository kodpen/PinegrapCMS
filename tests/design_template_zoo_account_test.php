<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Zoo design template's account and staff part
 * (includes/design_templates/pinegrap-zoo/account.php): the pages and
 * widgets with their keys and folders, the account e-mails with their
 * bound links, the account bar and My Account leading to the visit
 * requests, the staff area with the newest requests and its shortcuts, the
 * News form with its sample posts, the staff directory, every link naming a
 * page of the finished template and none a shop page, the template's own
 * ids and classes, no fixed date and every literal with its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/design_template_zoo_animals_test.php');

// parts.php, cards.php, the public pages, mail.php, visits.php and
// account.php in one scope, as the template file reads them. Read once per
// run.
function _test_zooac_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-zoo';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	include $dir . '/pages_public.php';
	include $dir . '/mail.php';
	$visits_part = include $dir . '/visits.php';
	$part = include $dir . '/account.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'],
		'others' => array_keys($visits_part['widgets']));
	return $out;
}

function test_zoo_account_pages_and_widgets()
{
	$d = _test_zooac_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('login', 'register', 'forgot_password', 'set_password', 'logout',
		'my_account', 'profile', 'change_password', 'email_preferences', 'address_book', 'staff_directory',
		'staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset'), array_keys($d['pages']));
	pg_assert_same(array(), array_values(array_intersect(array_keys($d['widgets']), $d['others'])), 'no widget key of another part is reused');
	$known = array_flip(_test_zoo_page_keys());
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a planned page");
		pg_assert_true(isset($p['name'], $p['title'], $p['tree'], $p['folder']), "$key: name, title, tree, folder");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_same(false, $p['sitemap'], "$key: out of the sitemap");
		pg_assert_same(false, $p['search'], "$key: out of the search");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
	}
	foreach (array('login', 'register', 'forgot_password', 'set_password', 'logout') as $key) {
		pg_assert_same('public', $d['pages'][$key]['folder'], "$key: public");
	}
	foreach (array('my_account', 'profile', 'change_password', 'email_preferences', 'address_book', 'staff_directory') as $key) {
		pg_assert_same('registration', $d['pages'][$key]['folder'], "$key: signed in");
	}
	foreach (array('staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "$key: private");
		pg_assert_same(true, $d['pages'][$key]['noindex'], "$key: noindex");
	}
	foreach (array('email_account_welcome', 'email_password_reset') as $key) {
		$tree = json_encode($d['pages'][$key]['tree']);
		pg_assert_contains('"templateShared":"email_header"', $tree, "$key: the shared e-mail header");
		pg_assert_contains('"templateShared":"email_footer"', $tree, "$key: the shared e-mail footer");
	}
	pg_assert_same('ecommerce', $d['pages']['address_book']['requires'], 'the address book needs the shop');
	pg_assert_same('ecommerce', $d['widgets']['address_book']['requires'], 'its widget too');
	foreach ($d['pages'] as $key => $p) {
		if ($key !== 'address_book') pg_assert_false(isset($p['requires']), "$key stands without the shop");
	}
	foreach ($d['widgets'] as $key => $w) {
		if ($w['page'] === '') continue;
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	$all = json_encode($d['part']);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $all, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	foreach (_test_zoo_shop_keys() as $key) pg_assert_false(strpos($all, '{{page:' . $key . '}}') !== false, "no link to $key");
}

function test_zoo_account_emails_and_widgets()
{
	$d = _test_zooac_load();
	$w = $d['widgets'];
	pg_assert_same('{{tab:email_account_welcome}}', $w['register']['config']['welcome_email_page_id']);
	pg_assert_same('{{tab:email_password_reset}}', $w['forgot_password']['config']['reset_email_page_id']);
	pg_assert_same('{{tab:forgot_password}}', $w['login']['config']['forgot_password_page_id']);
	pg_assert_contains('"href":"__set_password_url"', json_encode($w['account_email_reset']['tree']), 'the reset button carries the set password link');
	pg_assert_contains('"href":"__my_account_url"', json_encode($w['account_email_welcome']['tree']), 'the welcome leads to the account');
	pg_assert_same('account_email', $w['account_email_welcome']['config']['regionType']);
	pg_assert_same('account_email', $w['account_email_reset']['config']['regionType']);
	pg_assert_same('', $w['login_region']['page'], 'the login region is the account bar\'s');
	$login = $w['login_region']['config'];
	pg_assert_same('{{tab:my_account}}', $login['account_page_id']);
	pg_assert_same('{{tab:staff}}', $login['staff_page_id']);
	pg_assert_same('{{tab:staff}}', $w['my_account']['config']['staff_page_id']);
	$region = json_encode($w['login_region']['tree']);
	foreach (array('my_visits', 'inbox') as $key) pg_assert_contains('{{page:' . $key . '}}', $region, "the account bar menu links $key");
	pg_assert_contains('"eo_visible_if":"is_staff"', $region, 'the staff link only for the staff');

	// The staff area places the newest visit requests, without the search
	// the full staff list has.
	$staff = json_encode($d['pages']['staff']['tree']);
	pg_assert_contains('"templateWidget":"staff_visits_new"', $staff, 'the staff page places the newest requests');
	$list = $w['staff_visits_new'];
	pg_assert_same('staff', $list['page']);
	pg_assert_same(8, $list['config']['items_per_page']);
	pg_assert_same('{{tab:visit_request}}', $list['config']['custom_form_page_id']);
	pg_assert_same('{{tab:visit_view}}', $list['config']['detail_page_id']);
	pg_assert_false(isset($list['config']['search_enabled']) || isset($list['config']['viewer_filter']), 'no search, no viewer filter');
	pg_assert_contains('"status"', json_encode($list['tree']), 'each row shows its status');
	foreach (array('staff_visits', 'animal_new', 'event_new', 'today', 'map', 'inbox', 'blog_new', 'staff_entry', 'staff_directory') as $key) {
		pg_assert_contains('{{page:' . $key . '}}', $staff, "the staff area links $key");
	}

	// My Account leads to the visitor's requests and the form, and shows
	// the orders (tickets, memberships, adoptions) to a member with any.
	$menu = json_encode($w['my_account']['tree']);
	pg_assert_contains('"order_history"', $menu, 'order history');
	pg_assert_contains('"has_orders"', $menu, 'its heading only with orders');
	pg_assert_contains('"eo_visible_if":"is_staff"', $menu, 'the staff link only for the staff');
	foreach (array('my_visits', 'visit_request', 'today', 'events', 'adopt', 'inbox', 'staff_directory') as $key) {
		pg_assert_contains('{{page:' . $key . '}}', $menu, "the account menu links $key");
	}
}

function test_zoo_account_blog_form_and_samples()
{
	$d = _test_zooac_load();
	$form = $d['pages']['blog_new']['form'];
	pg_assert_same(1, $form['collection']);
	pg_assert_same('cover_image', $form['share_image_field']);
	pg_assert_same('{{tab:blog}}', $form['confirmation_page_id'], 'a post opens the blog');
	pg_assert_same(1, $d['pages']['staff_entry']['form']['collection']);
	$c = _test_zooa_controls($d['widgets']['blog_form']['tree']);
	// The lists and the post page (standard.php) read these.
	pg_assert_same(array('title', 'summary', 'cover_image', 'content'), array_keys($c), 'the post\'s fields');
	pg_assert_same('image', $c['cover_image']['cf']['kind'] ?? null, 'the cover takes images only');
	$s = _test_zooa_controls($d['widgets']['staff_form']['tree']);
	pg_assert_same(array('full_name', 'job_title', 'department', 'photo', 'email', 'phone', 'bio'), array_keys($s), 'the directory\'s fields');
	$dir = $d['widgets']['staff_directory']['config'];
	pg_assert_same('{{tab:staff_entry}}', $dir['custom_form_page_id']);
	foreach ($dir['search_fields'] as $f) pg_assert_true(isset($s[$f]), "the directory searches $f, a field of its form");
	pg_assert_true(isset($s[$dir['order_by_field']]), 'the directory is sorted by a field of its form');

	pg_assert_same(4, count($form['sample_records']), 'four sample posts');
	foreach ($form['sample_records'] as $post) {
		foreach (array_keys($post) as $f) pg_assert_true(isset($c[$f]), "sample field $f is a form field");
		pg_assert_true($post['title'] !== '' && $post['summary'] !== '' && $post['content'] !== '', 'a sample has a title, a summary and words');
		pg_assert_contains('pinegrap-zoo-', $post['cover_image'], 'a cover of the template\'s own');
	}
}

// Ids and classes are the template's own; nothing is dated; headings run
// in order; every literal has its Turkish, a visit reads "ziyaret", an
// event "etkinlik", an adoption "sahiplen".
function test_zoo_account_ids_dates_and_turkish()
{
	$d = _test_zooac_load();
	$trees = array();
	foreach ($d['pages'] as $key => $p) $trees[$key] = $p['tree'];
	foreach ($d['widgets'] as $key => $w) if (is_array($w['tree'])) $trees['widget ' . $key] = $w['tree'];
	$ids = array();
	foreach ($trees as $key => $tree) {
		foreach (_test_zooa_nodes($tree) as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(prx|pmz|pph|pvt|pre|pev|pgl|pmn|prt|plb|pht|pma|pco|pcl|pgy|pch|psk|sk)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false(strpos($c, 'opacity-') === 0, "$key: no faded text ($c)");
				pg_assert_false(in_array($c, array('text-white', 'bg-white', 'bg-dark', 'text-dark', 'btn-light', 'text-muted', 'text-body-tertiary'), true), "$key: no fixed or faint colour ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'zoo-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$id]), "id $id once");
			$ids[$id] = true;
		}
	}
	foreach (array('staff', 'staff_directory') as $key) {
		$levels = array();
		foreach (_test_zooa_nodes($d['pages'][$key]['tree']) as $n) {
			if (preg_match('/^h([1-6])$/', (string)($n['props']['tag'] ?? ''), $m)) $levels[] = (int)$m[1];
		}
		pg_assert_same(1, count(array_keys($levels, 1, true)), "$key: one h1");
		$set = array_values(array_unique($levels));
		sort($set);
		pg_assert_same(range(1, max($set)), $set, "$key: no heading level skipped");
	}

	$json = json_encode($d['part'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');

	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-zoo/account.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || preg_match('/^[a-z0-9-]+$/', $key)) continue;
		$tr = $map[$key];
		if (preg_match('/\bvisits?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'ziyaret', 0, 'UTF-8') !== false || mb_stripos($tr, 'gezi', 0, 'UTF-8') !== false, "'$key' says ziyaret");
		if (preg_match('/\bevents?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'etkinli', 0, 'UTF-8') !== false, "'$key' says etkinlik");
		if (preg_match('/\badopt/i', $key)) pg_assert_true(mb_stripos($tr, 'sahiplen', 0, 'UTF-8') !== false, "'$key' says sahiplenme");
	}
}
