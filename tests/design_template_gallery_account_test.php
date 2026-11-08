<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Art Gallery design template's account and staff part
 * (includes/design_templates/pinegrap-gallery/account.php): the pages and
 * their folders, the template whole with every part loaded (links, placed
 * widgets, the page list), the account e-mails and menus, the staff area's
 * newest enquiries, the Journal's form with its topic and its sample
 * articles, ids and classes, and every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page and shop keys of the finished template live in the parts test.
require_once __DIR__ . '/design_template_gallery_test.php';

// Every part of the template as the template file reads them, in one
// scope. Read once per run.
function _test_glac_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gallery';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	include $dir . '/mail.php';
	$parts = array();
	foreach (array('pages_public', 'artists', 'exhibitions', 'requests', 'shop', 'standard', 'account') as $part_name) {
		$part = include $dir . '/' . $part_name . '.php';
		$parts[$part_name] = array('pages' => $part['pages'], 'widgets' => $part['widgets']);
	}
	$pages = array();
	foreach ($parts['account']['pages'] as $p) $pages[$p['key']] = $p;
	$out = array(
		'parts'  => $parts,
		'pages'  => $pages,
		'topics' => $topic_titles,
		'shared' => array($account_bar_tree(), $header(), $footer(), $viewing_band(), $notice(), $staff_bar(), $email_header_tree(), $email_footer_tree()),
	);
	return $out;
}

function test_gallery_account_pages_load()
{
	$d = _test_glac_load();
	pg_assert_same(array('login', 'register', 'forgot_password', 'set_password', 'logout',
		'my_account', 'profile', 'change_password', 'email_preferences', 'address_book', 'staff_directory',
		'staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset'), array_keys($d['pages']));
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($p['name'], $p['title'], $p['tree'], $p['folder']), "$key: name, title, tree, folder");
		pg_assert_same('root', $p['tree']['type'], "$key: root tree");
		pg_assert_same(false, $p['sitemap'], "$key: out of the sitemap");
		if ($key !== 'address_book') pg_assert_false(isset($p['requires']), "$key stands without the shop");
	}
	foreach (array('my_account', 'profile', 'change_password', 'email_preferences', 'address_book', 'staff_directory') as $key) {
		pg_assert_same('registration', $d['pages'][$key]['folder'], "$key: signed in");
	}
	foreach (array('staff', 'blog_new', 'staff_entry', 'email_account_welcome', 'email_password_reset') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "$key: private");
		pg_assert_same(true, $d['pages'][$key]['noindex'], "$key: noindex");
	}
	pg_assert_same('ecommerce', $d['pages']['address_book']['requires'], 'address book needs the shop');
	pg_assert_same('ecommerce', $d['parts']['account']['widgets']['address_book']['requires'], 'its widget too');
}

// With every part loaded, the template is whole: each link names a page it
// carries, each placed widget is defined, each widget's page exists, and
// the shared page list is exactly what the parts carry.
function test_gallery_account_template_is_whole()
{
	$d = _test_glac_load();
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
	foreach ($trees as $t) _test_gl_nodes($t, $nodes);
	foreach ($nodes as $n) {
		if (isset($n['props']['templateWidget'])) pg_assert_true(isset($widgets[$n['props']['templateWidget']]), 'widget ' . $n['props']['templateWidget'] . ' is defined');
	}
	foreach ($widgets as $k => $w) pg_assert_true($w['page'] === '' || isset($keys[$w['page']]), "widget $k: page {$w['page']}");

	$expected = _test_gl_page_keys();
	$got = array_keys($keys);
	sort($expected);
	sort($got);
	pg_assert_same($expected, $got, 'the page list matches the parts');
}

function test_gallery_account_emails_and_widgets()
{
	$d = _test_glac_load();
	$w = $d['parts']['account']['widgets'];
	pg_assert_same('{{tab:email_account_welcome}}', $w['register']['config']['welcome_email_page_id']);
	pg_assert_same('{{tab:email_password_reset}}', $w['forgot_password']['config']['reset_email_page_id']);
	pg_assert_contains('"href":"__set_password_url"', json_encode($w['account_email_reset']['tree']), 'reset button carries the set password link');
	pg_assert_same('account_email', $w['account_email_welcome']['config']['regionType']);
	foreach (array('email_account_welcome', 'email_password_reset') as $key) {
		pg_assert_contains('"templateShared":"email_header"', json_encode($d['pages'][$key]['tree']), "$key: in the e-mail frame");
	}
	$login = $w['login_region']['config'];
	pg_assert_same('', $w['login_region']['page'], 'the login region sits in the account bar');
	pg_assert_same('{{tab:my_account}}', $login['account_page_id']);
	pg_assert_same('{{tab:staff}}', $login['staff_page_id']);
	pg_assert_same('{{tab:staff}}', $w['my_account']['config']['staff_page_id']);
	$region = json_encode($w['login_region']['tree']);
	foreach (array('my_enquiries', 'my_viewings', 'inbox') as $key) pg_assert_contains('{{page:' . $key . '}}', $region, "account bar menu links $key");

	// The staff area places the newest enquiries, without the search the
	// full staff list has.
	pg_assert_contains('"templateWidget":"staff_enquiries_new"', json_encode($d['pages']['staff']['tree']), 'staff page places the enquiries');
	$list = $w['staff_enquiries_new'];
	pg_assert_same('staff', $list['page']);
	pg_assert_same(10, $list['config']['items_per_page']);
	pg_assert_same('{{tab:enquire}}', $list['config']['custom_form_page_id']);
	pg_assert_same('{{tab:enquiry_view}}', $list['config']['detail_page_id']);
	pg_assert_false(isset($list['config']['search_enabled']) || isset($list['config']['viewer_filter']), 'no search, no viewer filter');
	$staff = json_encode($d['pages']['staff']['tree']);
	foreach (array('staff_enquiries', 'staff_viewings', 'artist_new', 'work_new', 'exhibition_new', 'inbox', 'blog_new', 'staff_entry') as $key) {
		pg_assert_contains('{{page:' . $key . '}}', $staff, "staff area links $key");
	}

	// My Account leads to the visitor's enquiries and viewings, and shows
	// the orders to a member with any; it never links a shop page.
	$menu = json_encode($w['my_account']['tree']);
	pg_assert_contains('"order_history"', $menu, 'order history');
	pg_assert_contains('"has_orders"', $menu, 'its heading only with orders');
	foreach (array('my_enquiries', 'my_viewings', 'viewing', 'works', 'exhibitions', 'inbox', 'staff_directory') as $key) {
		pg_assert_contains('{{page:' . $key . '}}', $menu, "account menu links $key");
	}
	$account = json_encode($d['parts']['account']);
	foreach (_test_gl_shop_keys() as $key) pg_assert_false(strpos($account, '{{page:' . $key . '}}') !== false, "no link to the shop's $key");
}

function test_gallery_account_blog_form_and_samples()
{
	$d = _test_glac_load();
	$form = $d['pages']['blog_new']['form'];
	pg_assert_same(1, $form['collection']);
	pg_assert_same('cover_image', $form['share_image_field']);
	pg_assert_same('{{tab:blog}}', $form['confirmation_page_id']);
	$nodes = array();
	_test_gl_nodes($d['parts']['account']['widgets']['blog_form']['tree'], $nodes);
	$fields = array();
	$cover = null;
	$topic_options = array();
	foreach ($nodes as $n) {
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) {
			if ($a['name'] !== 'name') continue;
			$fields[] = $a['value'];
			if ($a['value'] === 'cover_image') $cover = $n;
			if ($a['value'] === 'topic') {
				foreach ($n['children'] as $o) {
					foreach ($o['props']['_attrs'] ?? array() as $oa) {
						if ($oa['name'] === 'value' && $oa['value'] !== '') $topic_options[] = $oa['value'];
					}
				}
			}
		}
	}
	// The lists, the post page and the Journal's filter (standard.php) read these.
	foreach (array('title', 'topic', 'summary', 'content', 'cover_image') as $f) pg_assert_true(in_array($f, $fields, true), "blog form has $f");
	pg_assert_same($d['topics'], $topic_options, 'the topic answers are the Journal\'s filter values');
	pg_assert_true($cover !== null, 'blog form has a cover input');
	pg_assert_same('image', $cover['props']['_cf']['kind'] ?? null, 'the cover takes images only');

	pg_assert_same(4, count($form['sample_records']), 'four sample articles');
	$used = array();
	foreach ($form['sample_records'] as $post) {
		foreach (array_keys($post) as $f) pg_assert_true(in_array($f, $fields, true), "sample field $f is a form field");
		pg_assert_true($post['title'] !== '' && $post['summary'] !== '' && $post['content'] !== '', 'sample has a title, summary and words');
		pg_assert_true(in_array($post['topic'], $d['topics'], true), 'sample topic is a topic answer');
		$used[$post['topic']] = true;
	}
	pg_assert_same(count($d['topics']), count($used), 'every topic has an article');
	$text = json_encode(array($form['sample_records'], $d['parts']['account']), JSON_UNESCAPED_UNICODE);
	pg_assert_false((bool)preg_match('/\b20[2-3]\d\b/', $text), 'no year');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $text), 'no month');
}

// Ids and classes are the template's own; every literal has its Turkish,
// the gallery reads "galeri", an artist "sanatçı", an exhibition "sergi",
// the Journal "Günce".
function test_gallery_account_ids_and_turkish()
{
	$d = _test_glac_load();
	$nodes = array();
	foreach ($d['pages'] as $p) _test_gl_nodes($p['tree'], $nodes);
	foreach ($d['parts']['account']['widgets'] as $w) if (is_array($w['tree'])) _test_gl_nodes($w['tree'], $nodes);
	pg_assert_true(count($nodes) > 100, 'nodes found');
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(prx|pmz|pph|pvt|pre|pev|pth)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, 'comment_') !== 0) pg_assert_true(strpos($id, 'gl-') === 0, "id $id is the template's");
	}
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gallery/account.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	pg_assert_true(count($m[1]) > 50, 'literals found');
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || preg_match('/^[a-z0-9-]+$/', $key)) continue;
		$tr = $map[$key];
		if (preg_match('/\b[Gg]allery\b/', $key)) pg_assert_true(mb_stripos($tr, 'galeri', 0, 'UTF-8') !== false, "'$key' says galeri");
		if (preg_match('/\b[Aa]rtists?\b/', $key)) pg_assert_true(mb_stripos($tr, 'sanatç', 0, 'UTF-8') !== false, "'$key' says sanatçı");
		if (preg_match('/\b[Ee]xhibitions?\b/', $key)) pg_assert_true(mb_stripos($tr, 'serg', 0, 'UTF-8') !== false, "'$key' says sergi");
		if (preg_match('/\bJournal\b/', $key)) pg_assert_true(mb_stripos($tr, 'Günce', 0, 'UTF-8') !== false, "'$key' says Günce");
	}
}
