<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Recruitment Agency design template's standard pages
 * (includes/design_templates/pinegrap-recruitment/standard.php): contact
 * opening a conversation, the inbox and the conversation with its replies,
 * the job alerts (the newsletter's form and contact group), Career Advice
 * (the blog) and its post, the help center's search and questions, the
 * error page, the message and welcome e-mails, the home page's guides and
 * Google reviews; bindings a form has, page links naming template pages,
 * ids and classes the template's own, no fixed date and every literal with
 * its Turkish.
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

// The standard part as the template file reads it: parts.php, mail.php,
// jobs.php, applications.php, employers.php, then standard.php in the same
// scope. Read once per run.
function _test_rcs_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-recruitment';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	include $dir . '/jobs.php';
	include $dir . '/applications.php';
	include $dir . '/employers.php';
	$part = include $dir . '/standard.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'topics' => $contact_topics,
		'contact_choices' => $contact_choices);
	return $out;
}

function test_recruitment_standard_pages_and_widgets()
{
	$d = _test_rcs_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('contact', 'newsletter', 'blog', 'blog_post', 'inbox', 'conversation', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome'), array_keys($d['pages']));
	pg_assert_same(array('newsletter_form', 'contact_form', 'google_reviews', 'home_posts', 'blog_list', 'blog_post', 'post_comments',
		'inbox_list', 'conversation', 'conversation_replies', 'site_search', 'error_page', 'email_message_admin', 'email_message_sender'),
		array_keys($d['widgets']));
	foreach (array('contact', 'newsletter', 'blog', 'blog_post', 'search', 'error') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	foreach (array('inbox', 'conversation') as $key) pg_assert_same('registration', $d['pages'][$key]['folder'], "$key is read signed in");
	foreach (array('email_new_message', 'email_message_received', 'email_welcome') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "$key is private");
		pg_assert_true($d['pages'][$key]['search'] === false && $d['pages'][$key]['sitemap'] === false, "$key is out of the search and the sitemap");
		$json = json_encode($d['pages'][$key]['tree']);
		pg_assert_contains('"templateShared":"email_header"', $json, "$key: the e-mail header");
		pg_assert_contains('"templateShared":"email_footer"', $json, "$key: the e-mail footer");
	}
	pg_assert_true($d['pages']['error']['noindex'], 'the error page is not indexed');
	foreach ($d['pages'] as $key => $p) {
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
	foreach ($d['widgets'] as $key => $w) {
		if ($w['page'] === 'home') continue;
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of this part");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	// The home page places the guides and the reviews this part draws.
	$public = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-recruitment/pages_public.php');
	foreach (array('home_posts', 'google_reviews') as $key) {
		pg_assert_same('home', $d['widgets'][$key]['page'], "$key is the home page's");
		pg_assert_contains("\$widget('$key')", $public, "the home page places $key");
	}
	pg_assert_same('starter', $d['widgets']['error_page']['tree'], 'the error page keeps the renderer\'s layout');
	pg_assert_same('all', $d['widgets']['site_search']['config']['search_scope'], 'the help center searches the whole site');
	pg_assert_contains('"eo_visible_if":"has_reviews"', json_encode($d['widgets']['google_reviews']['tree']), 'the reviews show only while Google returns some');
}

// Contact opens a conversation; job alerts join the newsletter's group.
function test_recruitment_standard_forms()
{
	$d = _test_rcs_load();
	$form = $d['pages']['contact']['form'];
	pg_assert_same('{{tab:conversation}}', $form['confirmation_page_id'], 'a message opens its conversation');
	pg_assert_same(1, $form['auto_registration']);
	pg_assert_same('{{tab:email_new_message}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_message_received}}', $form['confirm_page_id']);
	$c = _test_rca_controls($d['widgets']['contact_form']['tree']);
	pg_assert_same(array('first_name', 'last_name', 'email', 'phone', 'topic', 'message', 'answer_by'), array_keys($c));
	pg_assert_same($d['topics'], $c['topic']['options'], 'the topics');
	pg_assert_same('textarea', $c['message']['tag']);
	pg_assert_contains('"section":"captcha"', json_encode($d['widgets']['contact_form']['tree']), 'the contact CAPTCHA');
	pg_assert_contains('{{page:apply}}', json_encode($d['pages']['contact']['tree']), 'contact points a candidate to the application');
	pg_assert_contains('{{page:hire}}', json_encode($d['pages']['contact']['tree']), 'contact points an employer to the staff request');
	$alerts = $d['pages']['newsletter']['form'];
	pg_assert_same('{{contact_group:newsletter}}', $alerts['contact_group_id'], 'job alerts are the newsletter\'s group');
	pg_assert_same('{{tab:email_welcome}}', $alerts['confirm_page_id']);
	$n = _test_rca_controls($d['widgets']['newsletter_form']['tree']);
	pg_assert_same(array('email', 'consent'), array_keys($n));
	pg_assert_same('email_address', $n['email']['cf']['contact_field'] ?? '', 'the address is the contact\'s');
	pg_assert_contains('{{page:jobs}}', json_encode($d['pages']['email_welcome']['tree']), 'the welcome links the open jobs');
}

// The record pages and e-mails bind fields their form has or the renderer
// gives; the blog reads the staff's posts.
function test_recruitment_standard_bindings()
{
	$d = _test_rcs_load();
	$contact = array_keys(_test_rca_controls($d['widgets']['contact_form']['tree']));
	$post = array('title', 'summary', 'content', 'cover_image', 'submitter_name');
	$renderer = array('reference_code', 'not_found', 'number_of_comments', 'form_item_view', 'submitted_date_and_time');
	foreach (array('inbox_list' => $contact, 'conversation' => $contact, 'email_message_admin' => $contact, 'email_message_sender' => $contact,
		'home_posts' => $post, 'blog_list' => $post, 'blog_post' => $post) as $key => $fields) {
		$nodes = array();
		_test_rca_nodes($d['widgets'][$key]['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (array('text', 'href', 'src', 'alt') as $prop) {
				$token = (string)($n['props']['_bindings'][$prop] ?? '');
				if ($token === '' || strpos($token, '__') === 0 || in_array($token, $renderer, true)) continue;
				pg_assert_true(in_array($token, $fields, true), "$key binds $token, a field of its form");
			}
		}
	}
	foreach (array('inbox_list', 'conversation', 'email_message_admin', 'email_message_sender') as $key) {
		pg_assert_same('{{tab:contact}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key reads the contact form");
	}
	foreach (array('home_posts', 'blog_list', 'blog_post') as $key) {
		pg_assert_same('{{tab:blog_new}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key reads the posts");
	}
	pg_assert_same('submitter_only', $d['widgets']['conversation']['config']['access_control'], 'a conversation is its sender\'s');
	pg_assert_true(!empty($d['widgets']['inbox_list']['config']['viewer_filter_submitter']), 'the inbox lists the own conversations');
	foreach (array('email_message_admin', 'email_message_sender') as $key) {
		pg_assert_same('public', $d['widgets'][$key]['config']['access_control'], "$key is drawn for the message just sent");
		pg_assert_contains('{{page:conversation}}?r=^^reference_code^^', json_encode($d['widgets'][$key]['tree'], JSON_UNESCAPED_SLASHES), "$key opens the conversation");
	}
	pg_assert_contains('"eo_visible_if":"has_new_account"', json_encode($d['widgets']['email_message_sender']['tree']), 'the sender e-mail names a new account');
	foreach (array('blog_post' => 'post_comments', 'conversation' => 'conversation_replies') as $page => $widget) {
		pg_assert_same('{{tab:email_reply}}', $d['pages'][$page]['comments']['email_page'], "$page mails its comments");
		pg_assert_same('comments', $d['widgets'][$widget]['config']['regionType']);
	}
	$json = json_encode($d['pages']['search']['tree']);
	for ($n = 1; $n <= 5; $n++) pg_assert_contains('"id":"rc-help-faq-' . $n . '-item","searchSection":true', $json, "help question $n is a search section");
}

function test_recruitment_standard_links_ids_and_classes()
{
	$d = _test_rcs_load();
	$known = array_flip(_test_rc_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('product', 'cart', 'checkout', 'order', 'email_order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
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
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-recruitment/standard.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $mm);
	foreach ($mm[1] as $raw) {
		pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b|\b(January|February|March|April|May|June|July|August|September|October|November|December)\b/', $raw), "'$raw' names no fixed date");
	}
}

// Every literal has its Turkish; Career Advice reads "Kariyer Rehberi",
// job alerts "İş Alarmı", an application "başvuru", a staff request
// "personel talebi", a consultant "danışman".
function test_recruitment_standard_turkish_words()
{
	$map = pg_test_language_map('tr');
	$words = array(
		'/\bcareer advice\b/i'    => 'kariyer rehber',
		'/\bjob alerts?\b/i'      => 'iş alarm',
		'/\bapplications?\b/i'    => 'başvuru',
		'/\bstaff requests?\b/i'  => 'personel tale',
		'/\bconsultants?\b/i'     => 'danışman',
	);
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-recruitment/standard.php');
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
