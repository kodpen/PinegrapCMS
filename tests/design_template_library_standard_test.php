<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the standard pages of the Public Library design template
 * (includes/design_templates/pinegrap-library/standard.php): contact,
 * newsletter, News and post, inbox and conversation, help center, error
 * page and the message e-mails load with their keys and root trees; every
 * page link and every placed widget names a page or widget of the
 * template; page and widget keys are unique across the parts; the contact
 * form opens a conversation and names its e-mail pages; the threads run on
 * the Comments widget; the lists read the staff page's blog form; ids and
 * classes are the template's own, unique per page, with no fixed date; and
 * every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The parts' helpers: _test_lb_nodes() and _test_lb_page_keys().
require_once __DIR__ . '/design_template_library_test.php';

// The parts as the template file reads them: parts.php, mail.php, then the
// page files in the same scope. Read once per run.
function _test_lbst_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-library';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$parts = array();
	foreach (array('pages_public', 'catalogue', 'events', 'membership', 'rooms', 'standard') as $part_file) {
		$parts[$part_file] = include $dir . '/' . $part_file . '.php';
	}
	$pages = array();
	foreach ($parts['standard']['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('parts' => $parts, 'standard' => $parts['standard'], 'pages' => $pages, 'widgets' => $parts['standard']['widgets']);
	return $out;
}

function test_library_standard_pages_load()
{
	$d = _test_lbst_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['standard']), 'the part returns pages and widgets');
	pg_assert_same(array('contact', 'newsletter', 'blog', 'blog_post', 'inbox', 'conversation', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome'), array_keys($d['pages']));
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($p['name'], $p['title'], $p['tree'], $p['folder']), "$key: name, title, tree, folder");
		pg_assert_same('root', $p['tree']['type'], "$key: root tree");
		pg_assert_false(isset($p['requires']), "$key stands without a shop");
		if ($p['folder'] === 'public' && $key !== 'error') {
			pg_assert_true(mb_strlen($p['meta_description'], 'UTF-8') <= 160, "$key: meta description fits a result line");
		}
	}
	foreach (array('inbox', 'conversation') as $key) pg_assert_same('registration', $d['pages'][$key]['folder'], "$key: signed in");
	foreach (array('email_new_message', 'email_message_received', 'email_welcome') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "$key: private");
		pg_assert_same(true, $d['pages'][$key]['noindex'], "$key: noindex");
	}
}

// Every {{page:}} / {{tab:}} names a template page, every placed widget is
// defined (by a part, or the account step's login region), and keys are
// unique across the parts.
function test_library_standard_links_and_widgets()
{
	$d = _test_lbst_load();
	$known = array_flip(_test_lb_page_keys());
	$json = json_encode($d['standard']);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no link to a shop's $key");
	}

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
		pg_assert_true($w['page'] === '' || isset($d['pages'][$w['page']]) || $w['page'] === 'home', "widget $key: page {$w['page']}");
		pg_assert_true(isset($w['config']['regionType'], $w['tree']), "widget $key: config and tree");
	}
	// The home page places these and leaves them to this part.
	$home = json_encode($d['parts']['pages_public']['pages'][0]['tree']);
	foreach (array('home_posts', 'google_reviews') as $w) {
		pg_assert_same('home', $d['widgets'][$w]['page'], "$w sits on the home page");
		pg_assert_contains('"templateWidget":"' . $w . '"', $home, "the home page places $w");
	}
}

function test_library_standard_contact_newsletter_and_threads()
{
	$d = _test_lbst_load();
	$w = $d['widgets'];

	// The contact form opens a conversation and names its e-mail pages.
	$f = $d['pages']['contact']['form'];
	pg_assert_same('{{tab:conversation}}', $f['confirmation_page_id']);
	pg_assert_same(1, $f['auto_registration']);
	pg_assert_same('{{tab:email_new_message}}', $f['notify_page_id']);
	pg_assert_same('{{tab:email_message_received}}', $f['confirm_page_id']);
	pg_assert_same('{{site_email}}', $f['notify_email']);
	$contact = json_encode($w['contact_form']['tree']);
	foreach (array('first_name', 'last_name', 'email', 'phone', 'topic', 'message') as $name) {
		pg_assert_contains('"name","value":"' . $name . '"', $contact, "contact field $name");
	}
	pg_assert_contains('{{page:reserve}}', json_encode($d['pages']['contact']['tree']), 'contact points a wish for a title to the reservation request');
	$inbox = json_encode($d['pages']['inbox']['tree']);
	foreach (array('my_bookings', 'my_reservations', 'my_memberships', 'my_room_bookings') as $key) pg_assert_contains('{{page:' . $key . '}}', $inbox, "the inbox points to $key");

	// The newsletter's page owns the form.
	$n = $d['pages']['newsletter']['form'];
	pg_assert_same('{{contact_group:newsletter}}', $n['contact_group_id']);
	pg_assert_same('{{tab:email_welcome}}', $n['confirm_page_id']);
	pg_assert_same('page', $w['newsletter_form']['config']['form_source']);

	// The lists and the post read the staff page's blog form.
	foreach (array('home_posts', 'blog_list', 'blog_post') as $key) {
		pg_assert_same('{{tab:blog_new}}', $w[$key]['config']['custom_form_page_id'], "$key: blog form");
	}
	pg_assert_same(3, $w['home_posts']['config']['max_results']);
	pg_assert_same('{{tab:blog_post}}', $w['blog_list']['config']['detail_page_id']);
	pg_assert_contains('{{page:join}}', json_encode($w['blog_post']['tree']), 'a post points to the card request');

	// The threads: the page's comment settings, its own Comments widget, the
	// reply e-mail of the events part (defined there, not here).
	foreach (array('blog_post' => 'post_comments', 'conversation' => 'conversation_replies') as $page => $widget) {
		$p = $d['pages'][$page];
		pg_assert_same('{{tab:email_reply}}', $p['comments']['email_page'], "$page: reply e-mail");
		pg_assert_contains('"templateWidget":"' . $widget . '"', json_encode($p['tree']), "$page places $widget");
		pg_assert_same('comments', $w[$widget]['config']['regionType']);
		pg_assert_same($page, $w[$widget]['page']);
	}
	pg_assert_same('submitter_only', $w['conversation']['config']['access_control']);
	pg_assert_same('all', $w['site_search']['config']['search_scope']);
	pg_assert_false(isset($w['email_comment']), 'the reply e-mail widget stays the events part\'s');
	pg_assert_true(isset($d['parts']['events']['widgets']['email_comment']), 'the events part defines the reply e-mail widget');
	pg_assert_same('google_reviews', $w['google_reviews']['config']['regionType']);
}

// Ids and classes are the template's own, ids unique per page, and no year
// or calendar date is written into a page.
function test_library_standard_ids_classes_and_no_fixed_dates()
{
	$d = _test_lbst_load();
	$trees = array();
	foreach ($d['pages'] as $key => $p) {
		$nodes = array();
		_test_lb_nodes($p['tree'], $nodes);
		foreach ($d['widgets'] as $w) if ($w['page'] === $key && is_array($w['tree'])) _test_lb_nodes($w['tree'], $nodes);
		$seen = array();
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(ptv|pht|pre|pev|pgy|pch|plw|pwd|pph|pcn|pmu|psc|psh|pmz|mz)-/', $c), "class $c is not another template's");
				pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			if (strpos($id, '-') !== false) pg_assert_true(strpos($id, 'lib-') === 0, "id $id is the template's");
			pg_assert_false(isset($seen[$id]), "$key: id $id is unique");
			$seen[$id] = true;
		}
		$trees[] = $p['tree'];
	}
	foreach ($d['widgets'] as $w) $trees[] = $w['tree'];
	$text = json_encode($trees, JSON_UNESCAPED_UNICODE);
	pg_assert_false((bool)preg_match('/\b20[2-3]\d\b/', $text), 'no year');
	pg_assert_false((bool)preg_match('/\b\d{4}-\d{2}-\d{2}\b/', $text), 'no ISO date');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $text), 'no month');
}

// Every literal has its Turkish, and the Turkish names the template's
// things one way: the library "kütüphane", a branch "şube", an event
// "etkinlik", a reservation "ayırtma", a room "salon" or "oda", News
// "Haberler".
function test_library_standard_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-library/standard.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || preg_match('/^[a-z0-9-]+$/', $key)) continue;
		$tr = $map[$key];
		if (preg_match('/\b[Ll]ibrary\b/', $key)) pg_assert_true(mb_stripos($tr, 'kütüphane', 0, 'UTF-8') !== false, "'$key' says kütüphane");
		if (preg_match('/\b[Bb]ranch(es)?\b/', $key)) pg_assert_true(mb_stripos($tr, 'şube', 0, 'UTF-8') !== false, "'$key' says şube");
		if (preg_match('/\b[Ee]vents?\b/', $key)) pg_assert_true(mb_stripos($tr, 'etkinli', 0, 'UTF-8') !== false, "'$key' says etkinlik");
		if (preg_match('/\b[Rr]eserv(ation|e|ed)\b/', $key)) pg_assert_true(mb_stripos($tr, 'ayırt', 0, 'UTF-8') !== false, "'$key' says ayırtma");
		if (preg_match('/\b[Rr]ooms?\b/', $key)) pg_assert_true(mb_stripos($tr, 'salon', 0, 'UTF-8') !== false || mb_stripos($tr, 'oda', 0, 'UTF-8') !== false, "'$key' says salon or oda");
	}
	pg_assert_same('Haberler', $map['News'], 'News reads Haberler');
}
