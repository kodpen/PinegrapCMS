<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Art Gallery design template's standard pages
 * (includes/design_templates/pinegrap-gallery/standard.php): the pages and
 * their folders, the contact form opening a conversation, the newsletter's
 * form, the Journal narrowed by ?topic= with the topics of parts.php, the
 * threads with their own Comments widgets, the links naming template pages
 * and never a shop page, the placed widgets, ids and classes, no fixed
 * date, and every literal in Turkish in the template's words.
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

// The parts as the template file reads them: parts.php, cards.php and
// mail.php, then the page files in the same scope. Read once per run.
function _test_glst_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gallery';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	include $dir . '/mail.php';
	$parts = array();
	foreach (array('pages_public', 'artists', 'exhibitions', 'requests', 'shop', 'standard') as $part_file) {
		$parts[$part_file] = include $dir . '/' . $part_file . '.php';
	}
	$pages = array();
	foreach ($parts['standard']['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('parts' => $parts, 'standard' => $parts['standard'], 'pages' => $pages, 'widgets' => $parts['standard']['widgets'],
		'topics' => $topic_titles, 'gallery' => $gallery);
	return $out;
}

function test_gallery_standard_pages_load()
{
	$d = _test_glst_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['standard']), 'the part returns pages and widgets');
	pg_assert_same(array('contact', 'newsletter', 'blog', 'blog_post', 'inbox', 'conversation', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome'), array_keys($d['pages']));
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($p['name'], $p['title'], $p['tree'], $p['folder']), "$key: name, title, tree, folder");
		pg_assert_same('root', $p['tree']['type'], "$key: root tree");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		if ($p['folder'] === 'public' && $key !== 'error') {
			pg_assert_true(mb_strlen($p['meta_description'], 'UTF-8') <= 160, "$key: meta description fits a result line");
		}
	}
	foreach (array('inbox', 'conversation') as $key) pg_assert_same('registration', $d['pages'][$key]['folder'], "$key: signed in");
	foreach (array('email_new_message', 'email_message_received', 'email_welcome') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "$key: private");
		pg_assert_same(true, $d['pages'][$key]['noindex'], "$key: noindex");
		pg_assert_contains('"templateShared":"email_header"', json_encode($d['pages'][$key]['tree']), "$key: in the e-mail frame");
	}
}

// Every {{page:}} / {{tab:}} names a template page and none a shop page,
// every placed widget is defined (by a part, or the account step's login
// region), and keys are unique across the parts.
function test_gallery_standard_links_and_widgets()
{
	$d = _test_glst_load();
	$known = array_flip(_test_gl_page_keys());
	$json = json_encode($d['standard']);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_gl_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false || strpos($json, '{{tab:' . $key . '}}') !== false, "no link to the shop's $key");
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
		pg_assert_true($w['page'] === 'home' || isset($d['pages'][$w['page']]), "widget $key: page {$w['page']}");
		pg_assert_true(isset($w['config']['regionType'], $w['tree']), "widget $key: config and tree");
	}
	// The home page places the articles and leaves them to this part.
	$home = '';
	foreach ($d['parts']['pages_public']['pages'] as $p) if ($p['key'] === 'home') $home = json_encode($p['tree']);
	pg_assert_same('home', $d['widgets']['home_posts']['page'], 'home_posts sits on the home page');
	pg_assert_contains('"templateWidget":"home_posts"', $home, 'the home page places home_posts');
	// Every widget the parts place so far is defined, but the account
	// step's own.
	foreach ($d['parts'] as $part) {
		foreach ($part['pages'] as $p) {
			preg_match_all('/"templateWidget":"([a-z_]+)"/', json_encode($p['tree']), $m);
			foreach ($m[1] as $w) pg_assert_true(isset($widgets[$w]) || $w === 'login_region', "{$p['key']}: widget $w is defined");
		}
	}
}

function test_gallery_standard_contact_newsletter_and_threads()
{
	$d = _test_glst_load();
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
	$page = json_encode($d['pages']['contact']['tree'], JSON_UNESCAPED_UNICODE);
	foreach (array('enquire', 'viewing', 'inbox') as $key) pg_assert_contains('{{page:' . $key . '}}', $page, "contact points to $key");
	pg_assert_contains($d['gallery']['address'], $page, 'contact shows the address');
	$inbox = json_encode($d['pages']['inbox']['tree']);
	foreach (array('my_enquiries', 'my_viewings') as $key) pg_assert_contains('{{page:' . $key . '}}', $inbox, "the inbox points to $key");

	// The newsletter's page owns the form.
	$n = $d['pages']['newsletter']['form'];
	pg_assert_same('{{contact_group:newsletter}}', $n['contact_group_id']);
	pg_assert_same('{{tab:email_welcome}}', $n['confirm_page_id']);
	pg_assert_same('page', $w['newsletter_form']['config']['form_source']);

	// The lists and the article read the staff page's blog form; the
	// Journal is narrowed by ?topic= with the topics of parts.php.
	foreach (array('home_posts', 'blog_list', 'blog_post') as $key) {
		pg_assert_same('{{tab:blog_new}}', $w[$key]['config']['custom_form_page_id'], "$key: blog form");
	}
	pg_assert_same(3, $w['home_posts']['config']['max_results']);
	pg_assert_same('{{tab:blog_post}}', $w['blog_list']['config']['detail_page_id']);
	$filter = $w['blog_list']['config']['filters'][0];
	pg_assert_same('topic', $filter['field']);
	pg_assert_same('url parameter', $filter['dynamic_value']);
	pg_assert_same('topic', $filter['dynamic_value_attribute']);
	$blog = json_encode($d['pages']['blog']['tree'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	foreach ($d['topics'] as $t) {
		pg_assert_contains('{{page:blog}}?' . http_build_query(array('topic' => $t), '', '&', PHP_QUERY_RFC3986), $blog, "a link to the topic $t");
	}
	pg_assert_contains('{{page:exhibitions}}', json_encode($w['blog_post']['tree']), 'an article points to the exhibitions');
	pg_assert_contains('"topic"', json_encode($w['blog_post']['tree']), 'an article shows its topic');

	// The threads: the page's comment settings, its own Comments widget, the
	// reply e-mail of the requests part (defined there, not here).
	foreach (array('blog_post' => 'post_comments', 'conversation' => 'conversation_replies') as $page_key => $widget) {
		$p = $d['pages'][$page_key];
		pg_assert_same('{{tab:email_reply}}', $p['comments']['email_page'], "$page_key: reply e-mail");
		pg_assert_contains('"templateWidget":"' . $widget . '"', json_encode($p['tree']), "$page_key places $widget");
		pg_assert_same('comments', $w[$widget]['config']['regionType']);
		pg_assert_same($page_key, $w[$widget]['page']);
	}
	pg_assert_same('submitter_only', $w['conversation']['config']['access_control']);
	pg_assert_same('all', $w['site_search']['config']['search_scope']);
	pg_assert_contains('{{page:faq}}', json_encode($d['pages']['search']['tree']), 'the help center points to the questions');
	pg_assert_false(isset($w['email_comment']), 'the reply e-mail widget stays the requests part\'s');
	pg_assert_true(isset($d['parts']['requests']['widgets']['email_comment']), 'the requests part defines the reply e-mail widget');
}

// Ids and classes are the template's own, ids unique per page, no faded
// text, and no year or calendar date is written into a page.
function test_gallery_standard_ids_classes_and_no_fixed_dates()
{
	$d = _test_glst_load();
	$trees = array();
	foreach ($d['pages'] as $key => $p) {
		$nodes = array();
		_test_gl_nodes($p['tree'], $nodes);
		foreach ($d['widgets'] as $w) if ($w['page'] === $key && is_array($w['tree'])) _test_gl_nodes($w['tree'], $nodes);
		$seen = array();
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(prx|pmz|pph|pvt|pre|pev|pth)-/', $c), "class $c is not another template's");
				pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text ($c)");
				pg_assert_false($c === 'text-body-tertiary', "no faded text ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '' || strpos($id, 'comment_') === 0) continue;
			pg_assert_true(strpos($id, 'gl-') === 0, "id $id is the template's");
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

// Every literal has its Turkish; the gallery reads "galeri", an artist
// "sanatçı", an exhibition "sergi", the Journal "Günce".
function test_gallery_standard_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gallery/standard.php');
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
