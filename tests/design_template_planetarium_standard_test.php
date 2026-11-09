<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Planetarium design template's standard pages
 * (includes/design_templates/pinegrap-planetarium/standard.php): the pages
 * and widgets every template carries, each link naming a page of the
 * finished template, keys unique across the parts, the contact form
 * opening a conversation, the newsletter owning its form, the blog lists
 * reading the staff page's form, the threads on their pages with the
 * visits part's reply e-mail, the template's ids and classes, headings,
 * no fixed date and every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template, the node walker and the visits
// part's loader.
require_once(__DIR__ . '/design_template_planetarium_visits_test.php');

// The parts as the template file reads them: parts.php, cards.php, the
// public pages, the shows and nights, mail.php, the visits, then this
// part, in one scope. Read once per run.
function _test_plst_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-planetarium';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	$parts = array();
	foreach (array('pages_public', 'shows', 'nights') as $part_file) $parts[$part_file] = include $dir . '/' . $part_file . '.php';
	include $dir . '/mail.php';
	foreach (array('visits', 'standard') as $part_file) $parts[$part_file] = include $dir . '/' . $part_file . '.php';
	$pages = array();
	foreach ($parts['standard']['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('parts' => $parts, 'standard' => $parts['standard'], 'pages' => $pages, 'widgets' => $parts['standard']['widgets']);
	return $out;
}

function test_planetarium_standard_pages_load()
{
	$d = _test_plst_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['standard']), 'the part returns pages and widgets');
	pg_assert_same(array('contact', 'newsletter', 'blog', 'blog_post', 'inbox', 'conversation', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome'), array_keys($d['pages']));
	pg_assert_same(array('newsletter_form', 'contact_form', 'home_posts', 'blog_list', 'blog_post', 'post_comments',
		'inbox_list', 'conversation', 'conversation_replies', 'site_search', 'error_page', 'email_message_admin', 'email_message_sender'),
		array_keys($d['widgets']));
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($p['name'], $p['title'], $p['tree'], $p['folder']), "$key: name, title, tree, folder");
		pg_assert_same('root', $p['tree']['type'], "$key: root tree");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
		if ($p['folder'] === 'public' && $key !== 'error') {
			pg_assert_true(mb_strlen($p['meta_description'], 'UTF-8') <= 160, "$key: meta description fits a result line");
		}
	}
	foreach (array('inbox', 'conversation') as $key) pg_assert_same('registration', $d['pages'][$key]['folder'], "$key: signed in");
	foreach (array('email_new_message', 'email_message_received', 'email_welcome') as $key) {
		$p = $d['pages'][$key];
		pg_assert_same('private', $p['folder'], "$key: private");
		pg_assert_true(!empty($p['noindex']) && $p['search'] === false && $p['sitemap'] === false, "$key: out of the search");
		$tree = json_encode($p['tree']);
		pg_assert_contains('"templateShared":"email_header"', $tree, "$key: the shared e-mail header");
		pg_assert_contains('"templateShared":"email_footer"', $tree, "$key: the shared e-mail footer");
	}
}

// Every {{page:}} / {{tab:}} names a template page, every placed widget is
// defined (by a part, or the login region), and keys are unique across the
// parts.
function test_planetarium_standard_links_and_widgets()
{
	$d = _test_plst_load();
	$known = array_flip(_test_planetarium_page_keys());
	$json = json_encode($d['standard']);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_planetarium_shop_keys() as $key) pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no link to the shop's $key");
	pg_assert_false(strpos($json, '?view=') !== false, 'never ?view=');

	$widgets = array();
	$all = array();
	$keys = array();
	$names = array();
	foreach ($d['parts'] as $part) {
		$widgets = array_merge($widgets, $part['widgets']);
		$all = array_merge($all, array_keys($part['widgets']));
		foreach ($part['pages'] as $p) {
			$keys[] = $p['key'];
			$names[] = $p['name'];
		}
	}
	pg_assert_same(count($all), count(array_unique($all)), 'widget keys are unique across the parts');
	pg_assert_same(count($keys), count(array_unique($keys)), 'page keys are unique across the parts');
	pg_assert_same(count($names), count(array_unique($names)), 'page names are unique across the parts');
	foreach ($keys as $key) pg_assert_true(isset($known[$key]), "page $key is in the template's list");

	foreach ($d['pages'] as $key => $p) {
		preg_match_all('/"templateWidget":"([a-z_]+)"/', json_encode($p['tree']), $m);
		foreach ($m[1] as $w) pg_assert_true(isset($widgets[$w]) || $w === 'login_region', "$key places a known widget $w");
		pg_assert_false(strpos(json_encode($p['tree']), 'comments_block') !== false, "$key: no fixed comments block");
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true(isset($d['pages'][$w['page']]) || $w['page'] === 'home', "widget $key: page {$w['page']}");
		pg_assert_true(isset($w['config']['regionType'], $w['tree']), "widget $key: config and tree");
		if ($w['page'] !== 'home') pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	// The home page places the newest posts and leaves them to this part.
	$home = null;
	foreach ($d['parts']['pages_public']['pages'] as $p) if ($p['key'] === 'home') $home = json_encode($p['tree']);
	pg_assert_same('home', $d['widgets']['home_posts']['page'], 'home_posts sits on the home page');
	pg_assert_contains('"templateWidget":"home_posts"', (string)$home, 'the home page places home_posts');
}

function test_planetarium_standard_contact_newsletter_and_threads()
{
	$d = _test_plst_load();
	$w = $d['widgets'];

	// The contact form opens a conversation and names its e-mail pages.
	$f = $d['pages']['contact']['form'];
	pg_assert_same('{{tab:conversation}}', $f['confirmation_page_id']);
	pg_assert_same(1, $f['auto_registration']);
	pg_assert_same('{{tab:email_new_message}}', $f['notify_page_id']);
	pg_assert_same('{{tab:email_message_received}}', $f['confirm_page_id']);
	pg_assert_same('{{site_email}}', $f['notify_email']);
	pg_assert_contains('^^topic^^', $f['notify_subject'], 'the subject names the topic');
	$c = _test_pls_controls($w['contact_form']['tree']);
	pg_assert_same(array('first_name', 'last_name', 'email', 'phone', 'topic', 'message'), array_keys($c), 'the contact fields');
	pg_assert_contains('"section":"captcha"', json_encode($w['contact_form']['tree']), 'the contact form has a CAPTCHA');
	pg_assert_contains('{{page:visit_request}}', json_encode($d['pages']['contact']['tree']), 'contact points a school to its request');
	pg_assert_contains('{{page:my_visits}}', json_encode($d['pages']['inbox']['tree']), 'the inbox points to My Visits');

	// The newsletter's page owns the form.
	$n = $d['pages']['newsletter']['form'];
	pg_assert_same('{{contact_group:newsletter}}', $n['contact_group_id']);
	pg_assert_same('{{tab:email_welcome}}', $n['confirm_page_id']);
	pg_assert_same('page', $w['newsletter_form']['config']['form_source']);
	pg_assert_same('{{page:blog}}', _test_plst_first_href($d['pages']['email_welcome']['tree'], '{{page:blog}}'), 'the welcome links the news');

	// The lists and the post read the staff page's blog form.
	foreach (array('home_posts', 'blog_list', 'blog_post') as $key) {
		pg_assert_same('{{tab:blog_new}}', $w[$key]['config']['custom_form_page_id'], "$key: blog form");
	}
	pg_assert_same(3, $w['home_posts']['config']['max_results']);
	pg_assert_same('{{tab:blog_post}}', $w['blog_list']['config']['detail_page_id']);
	pg_assert_true(!empty($w['blog_list']['config']['search_enabled']), 'the news is searchable');
	pg_assert_contains('{{page:tonight}}', json_encode($w['blog_post']['tree']), 'a post points to tonight\'s sky');

	// The threads: the page's comment settings, its own Comments widget, the
	// reply e-mail of the visits part (defined there, not here).
	foreach (array('blog_post' => 'post_comments', 'conversation' => 'conversation_replies') as $page => $widget) {
		$p = $d['pages'][$page];
		pg_assert_same('{{tab:email_reply}}', $p['comments']['email_page'], "$page: reply e-mail");
		pg_assert_contains('"templateWidget":"' . $widget . '"', json_encode($p['tree']), "$page places $widget");
		pg_assert_same('comments', $w[$widget]['config']['regionType']);
		pg_assert_same($page, $w[$widget]['page']);
	}
	pg_assert_same('submitter_only', $w['conversation']['config']['access_control']);
	pg_assert_same('{{tab:contact}}', $w['inbox_list']['config']['custom_form_page_id'], 'the inbox reads the contact form');
	pg_assert_true(!empty($w['inbox_list']['config']['viewer_filter_submitter']), 'the inbox shows the visitor\'s own');
	pg_assert_same('all', $w['site_search']['config']['search_scope']);
	pg_assert_same('search_results', $w['site_search']['config']['regionType']);
	pg_assert_false(isset($w['email_comment']), 'the reply e-mail widget stays the visits part\'s');
	pg_assert_true(isset($d['parts']['visits']['widgets']['email_comment']), 'the visits part defines the reply e-mail widget');
	foreach (array('email_message_admin', 'email_message_sender') as $key) {
		pg_assert_same('public', $w[$key]['config']['access_control'], "$key is drawn for the submission just sent");
		pg_assert_contains('{{page:conversation}}?r=^^reference_code^^', json_encode($w[$key]['tree'], JSON_UNESCAPED_SLASHES), "$key links the conversation");
	}
}

// The first href of a tree equal to $href, '' without one.
function _test_plst_first_href($tree, $href)
{
	foreach (_test_plp_nodes($tree) as $n) {
		if (($n['props']['href'] ?? '') === $href) return $href;
	}
	return '';
}

// Ids and classes are the template's own, ids once beside the other parts'
// ids, one h1 per content page with no level skipped, and no year or month
// written into a page.
function test_planetarium_standard_ids_classes_headings_and_no_fixed_dates()
{
	$d = _test_plst_load();
	$ids = array();
	foreach ($d['parts'] as $name => $part) {
		if ($name === 'standard') continue;
		foreach (array_merge($part['pages'], array_values($part['widgets'])) as $p) {
			if (!is_array($p['tree'])) continue;
			foreach (_test_plp_nodes($p['tree']) as $n) {
				$id = (string)($n['props']['id'] ?? '');
				if ($id !== '') $ids[$id] = true;
			}
		}
	}
	$trees = array();
	foreach ($d['pages'] as $key => $p) $trees[$key] = $p['tree'];
	foreach ($d['widgets'] as $key => $w) if (is_array($w['tree'])) $trees['widget ' . $key] = $w['tree'];
	foreach ($trees as $key => $tree) {
		foreach (_test_plp_nodes($tree) as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				if (preg_match('/^([a-z]{3})-/', $c, $pre) && $pre[1][0] === 'p') pg_assert_true($pre[1] === 'ppm', "$key: class $c is not another template's");
				pg_assert_false(strpos($c, 'opacity-') === 0, "$key: no faded text ($c)");
				pg_assert_false(in_array($c, array('text-white', 'bg-white', 'bg-dark', 'text-dark', 'btn-light', 'text-muted', 'text-body-tertiary'), true), "$key: no fixed or faint colour ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			if (strpos($id, '-') !== false) pg_assert_true(strpos($id, 'plm-') === 0 || strpos($id, 'comment_') === 0, "$key: id $id is the template's");
			if (strpos($id, 'comment_') === 0) continue;
			pg_assert_false(isset($ids[$id]), "id $id once, beside the other pages' ids");
			$ids[$id] = true;
		}
	}
	foreach (array('contact', 'newsletter', 'blog', 'inbox', 'search') as $key) {
		$levels = array();
		foreach (_test_plp_nodes($d['pages'][$key]['tree']) as $n) {
			if (preg_match('/^h([1-6])$/', (string)($n['props']['tag'] ?? ''), $m)) $levels[] = (int)$m[1];
		}
		pg_assert_same(1, count(array_keys($levels, 1, true)), "$key: one h1");
		$set = array_values(array_unique($levels));
		sort($set);
		pg_assert_same(range(1, max($set)), $set, "$key: no heading level skipped");
	}
	$json = rawurldecode(json_encode($d['standard'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	$json = str_replace('http://www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b\d{4}-\d{2}-\d{2}\b/', $json), 'no ISO date');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish, and the Turkish names the template's
// things one way: "planetaryum", "okul ziyareti", "kubbe gösterisi",
// "teleskop gecesi", News "Haberler".
function test_planetarium_standard_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-planetarium/standard.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || preg_match('/^[a-z0-9-]+$/', $key)) continue;
		$tr = $map[$key];
		if (preg_match('/\bplanetarium\b/i', $key)) pg_assert_true(mb_stripos($tr, 'planetaryum', 0, 'UTF-8') !== false, "'$key' says planetaryum");
		if (preg_match('/\bschool visits?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'okul ziyaret', 0, 'UTF-8') !== false, "'$key' says okul ziyareti");
		if (preg_match('/\bdome shows?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'kubbe gösteri', 0, 'UTF-8') !== false, "'$key' says kubbe gösterisi");
		if (preg_match('/\btelescope nights?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'teleskop gece', 0, 'UTF-8') !== false, "'$key' says teleskop gecesi");
	}
	pg_assert_same('Haberler', $map['News'], 'News reads Haberler');
}
