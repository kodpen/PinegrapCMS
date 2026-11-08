<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Restaurant design template's standard pages
 * (includes/design_templates/pinegrap-restaurant/standard.php): contact and
 * the inbox, the newsletter, the Kitchen Journal and its post page, the
 * guest help search, the error page and their e-mails; the forms and
 * threads they wire, the home page's journal and Google reviews, links to
 * template pages only, the template's ids and classes, and every literal
 * in Turkish.
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

// parts.php, mail.php, menu.php, the reservations and the standard pages in
// one scope, as the template file reads them. Read once per run.
function _test_rts_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-restaurant';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$menu_part = include $dir . '/menu.php';
	$reservation_part = include $dir . '/reservations.php';
	$out = include $dir . '/standard.php';
	return $out;
}

function _test_rts_pages()
{
	$pages = array();
	foreach (_test_rts_load()['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

function test_restaurant_standard_pages_are_the_plans()
{
	$d = _test_rts_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d), 'the part returns pages and widgets');
	pg_assert_same(array('contact', 'newsletter', 'blog', 'blog_post', 'inbox', 'conversation', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome'), array_keys(_test_rts_pages()), 'the standard pages');
	$folders = array('contact' => 'public', 'newsletter' => 'public', 'blog' => 'public', 'blog_post' => 'public',
		'inbox' => 'registration', 'conversation' => 'registration', 'search' => 'public', 'error' => 'public');
	foreach (_test_rts_pages() as $key => $p) {
		foreach (array('name', 'folder', 'title', 'tree') as $field) pg_assert_true(isset($p[$field]) && $p[$field] !== '', "$key has $field");
		pg_assert_same('root', $p['tree']['type'], "$key: root tree");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
		pg_assert_same($folders[$key] ?? 'private', $p['folder'], "$key: its folder");
		if (isset($p['meta_description'])) pg_assert_true(mb_strlen($p['meta_description'], 'UTF-8') <= 160, "$key: meta description fits a result line");
		if (strpos($key, 'email_') === 0) pg_assert_true(!empty($p['noindex']) && $p['search'] === false, "$key: kept out of search");
	}
	pg_assert_true(_test_rts_pages()['error']['noindex'], 'error: noindex');
	pg_assert_same(lang('kitchen-journal'), _test_rts_pages()['blog']['name'], 'the blog is the Kitchen Journal');
	// Every widget sits on its page (home for the journal and the reviews),
	// and every widget a page places is defined here.
	$placed = array();
	foreach (_test_rts_pages() as $key => $p) {
		preg_match_all('/"templateWidget":"([a-z_]+)"/', json_encode($p['tree']), $m);
		foreach ($m[1] as $w) $placed[$w] = $key;
		pg_assert_false(strpos(json_encode($p['tree']), 'comments_block') !== false, "$key: no fixed comments block");
	}
	foreach ($placed as $w => $key) pg_assert_true(isset($d['widgets'][$w]), "$key places $w, defined here");
	foreach ($d['widgets'] as $w => $def) {
		pg_assert_true(isset($def['config']['regionType'], $def['tree']), "$w: config and tree");
		pg_assert_same($def['page'], $placed[$w] ?? 'home', "$w is placed on its page");
	}
	pg_assert_same('home', $d['widgets']['home_posts']['page']);
	pg_assert_same('home', $d['widgets']['google_reviews']['page']);
}

function test_restaurant_standard_forms_and_threads()
{
	$p = _test_rts_pages();
	$w = _test_rts_load()['widgets'];
	$f = $p['contact']['form'];
	pg_assert_same('{{tab:conversation}}', $f['confirmation_page_id'], 'a message opens its conversation');
	pg_assert_same(1, $f['auto_registration']);
	pg_assert_same('{{tab:email_new_message}}', $f['notify_page_id']);
	pg_assert_same('{{tab:email_message_received}}', $f['confirm_page_id']);
	pg_assert_same('{{site_email}}', $f['notify_email']);
	pg_assert_contains('^^topic^^', $f['notify_subject'], 'the subject names the topic');
	$contact = json_encode($w['contact_form']['tree']);
	foreach (array('first_name', 'last_name', 'email', 'phone', 'topic', 'message') as $name) {
		pg_assert_contains('"name","value":"' . $name . '"', $contact, "contact field $name");
	}
	$n = $p['newsletter']['form'];
	pg_assert_same('{{contact_group:newsletter}}', $n['contact_group_id']);
	pg_assert_same('{{tab:email_welcome}}', $n['confirm_page_id']);
	pg_assert_same('page', $w['newsletter_form']['config']['form_source']);
	foreach (array('home_posts', 'blog_list', 'blog_post') as $key) {
		pg_assert_same('{{tab:blog_new}}', $w[$key]['config']['custom_form_page_id'], "$key: the post form");
	}
	pg_assert_same(3, $w['home_posts']['config']['max_results']);
	pg_assert_same(array('title', 'summary', 'content'), $w['blog_list']['config']['search_fields']);
	pg_assert_contains('"eo_visible_if":"has_reviews"', json_encode($w['google_reviews']['tree']), 'reviews show only with reviews');
	foreach (array('blog_post' => 'post_comments', 'conversation' => 'conversation_replies') as $page => $widget) {
		pg_assert_same('{{tab:email_reply}}', $p[$page]['comments']['email_page'], "$page: the reply e-mail");
		pg_assert_same('comments', $w[$widget]['config']['regionType']);
		pg_assert_same($page, $w[$widget]['page']);
	}
	pg_assert_same('submitter_only', $w['conversation']['config']['access_control']);
	pg_assert_true($w['inbox_list']['config']['viewer_filter_submitter'], 'the inbox lists the guest\'s own');
	pg_assert_same('all', $w['site_search']['config']['search_scope']);
	foreach (array('email_message_admin', 'email_message_sender') as $key) {
		pg_assert_same('{{tab:contact}}', $w[$key]['config']['custom_form_page_id']);
		pg_assert_contains('{{page:conversation}}?r=^^reference_code^^', json_encode($w[$key]['tree'], JSON_UNESCAPED_SLASHES), "$key links the conversation");
	}
	pg_assert_contains('"__new_account_email"', json_encode($w['email_message_sender']['tree']), 'the reply names a new account');
}

// Every link names a page the finished template carries and none a shop
// page; ids and classes are the template's own; no fixed date.
function test_restaurant_standard_links_ids_and_classes()
{
	$d = _test_rts_load();
	$json = json_encode($d);
	$known = array_flip(_test_rt_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	foreach (_test_rt_shop_keys() as $key) pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no page here links $key");
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p['tree'];
	foreach ($d['widgets'] as $w) if (is_array($w['tree'])) $trees[] = $w['tree'];
	$seen = array();
	foreach ($trees as $tree) {
		$nodes = array();
		_test_rt_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht|pgy|pcn|pmu|pph|psh|ptv|pwd|pai|pmz)-/', $c), "class $c is not another template's");
				pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '' || strpos($id, 'comment_') === 0) continue;
			pg_assert_true(strpos($id, 'rt-') === 0, "id $id is the template's");
			pg_assert_false(isset($seen[$id]), "id $id is unique");
			$seen[$id] = true;
		}
	}
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-restaurant/standard.php');
	pg_assert_false((bool)preg_match("/lang\\('[^']*\\b20[0-9]{2}\\b/", $src), 'no year in the words');
	pg_assert_false((bool)preg_match('/\b\d{4}-\d{2}-\d{2}\b/', $src), 'no ISO date');
}

// Every literal has its Turkish; the journal is the Kitchen Journal; shared
// keys whose Turkish names another thing are not used.
function test_restaurant_standard_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-restaurant/standard.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (isset($map[$key]) && strpos($key, 'Kitchen Journal') !== false) pg_assert_true(mb_stripos($map[$key], 'mutfak', 0, 'UTF-8') !== false, "'$key' says Mutfak");
		if (isset($map[$key]) && preg_match('/\breservations?\b/i', $key) && strpos($key, ' ') !== false) pg_assert_true(mb_stripos($map[$key], 'rezervasyon', 0, 'UTF-8') !== false, "'$key' says rezervasyon");
	}
	foreach (array('Starters', 'Course', 'Courses', 'Glass', 'Dish', 'Find Us', 'Choose a course…', 'Time', 'Hotel Journal') as $k) {
		pg_assert_false(strpos($src, "lang('" . $k . "')") !== false, "standard.php does not use lang('$k')");
	}
}
