<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the standard pages of the Film Studio design template
 * (includes/design_templates/pinegrap-filmstudio/standard.php): contact,
 * newsletter, the Journal and its post page, inbox and conversation, help
 * center, error page and the message e-mails load with their keys and root
 * trees; every page link and every placed widget names a page or widget of
 * the finished template; the contact form opens a conversation and names
 * its e-mail pages; the threads run on the Comments widget; the lists read
 * the staff page's blog form; ids are the template's and unique per page,
 * no fixed date is written into a page and every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_fm_page_keys()).
require_once(__DIR__ . '/design_template_filmstudio_test.php');

// The parts as the template file reads them: parts.php, mail.php, then the
// page files in the same scope. Read once per run.
function _test_fms_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-filmstudio';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$public = include $dir . '/pages_public.php';
	$projects = include $dir . '/projects.php';
	$enquiries = include $dir . '/enquiries.php';
	$careers = include $dir . '/careers.php';
	$standard = include $dir . '/standard.php';
	$out = array(
		'public'    => $public,
		'projects'  => $projects,
		'enquiries' => $enquiries,
		'careers'   => $careers,
		'standard'  => $standard,
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_fms_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_fms_nodes($child, $out);
	}
}

// A page of the standard part by its key.
function _test_fms_page($key)
{
	$d = _test_fms_load();
	foreach ($d['standard']['pages'] as $p) if ($p['key'] === $key) return $p;
	return null;
}

function test_filmstudio_standard_pages_load()
{
	$d = _test_fms_load();
	$keys = array();
	foreach ($d['standard']['pages'] as $p) {
		pg_assert_true(isset($p['key'], $p['name'], $p['title'], $p['tree'], $p['folder']), 'page has key, name, title, tree, folder');
		pg_assert_same('root', $p['tree']['type'], $p['key'] . ': root tree');
		if ($p['folder'] === 'public' && $p['key'] !== 'error') {
			pg_assert_true(isset($p['meta_description']), $p['key'] . ': description');
			pg_assert_true(mb_strlen($p['meta_description'], 'UTF-8') <= 160, $p['key'] . ': description fits a result line');
		}
		$keys[] = $p['key'];
	}
	pg_assert_same(array('contact', 'newsletter', 'blog', 'blog_post', 'inbox', 'conversation', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome'), $keys);
	foreach (array('inbox', 'conversation') as $key) pg_assert_same('registration', _test_fms_page($key)['folder'], "$key: signed in");
	foreach (array('email_new_message', 'email_message_received', 'email_welcome') as $key) {
		$p = _test_fms_page($key);
		pg_assert_same('private', $p['folder'], "$key: private");
		pg_assert_same(true, $p['noindex'], "$key: noindex");
	}
}

// Every {{page:}} / {{tab:}} names a page of the finished template, and
// every widget a page places is defined (the standard part's own, an
// earlier part's, or the account step's login region).
function test_filmstudio_standard_links_and_widgets()
{
	$d = _test_fms_load();
	$known = array_flip(_test_fm_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($d['standard']), $m);
	pg_assert_true(count($m[1]) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('product', 'cart', 'checkout', 'order', 'quote') as $key) {
		pg_assert_false(strpos(json_encode($d['standard']), '{{page:' . $key . '}}') !== false, "no link to $key");
	}

	$parts = array('public', 'projects', 'enquiries', 'careers', 'standard');
	$widgets = array();
	foreach ($parts as $part) $widgets = array_merge($widgets, $d[$part]['widgets']);
	foreach ($d['standard']['pages'] as $p) {
		$nodes = array();
		_test_fms_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (!isset($n['props']['templateWidget'])) continue;
			$w = $n['props']['templateWidget'];
			pg_assert_true(isset($widgets[$w]) || $w === 'login_region', $p['key'] . ": places a known widget $w");
		}
		pg_assert_false(strpos(json_encode($p['tree']), 'comments_block') !== false, $p['key'] . ': no fixed comments block');
	}
	foreach ($d['standard']['widgets'] as $key => $w) {
		pg_assert_true(isset($known[$w['page']]), "widget $key: page {$w['page']}");
		pg_assert_true(isset($w['config']['regionType'], $w['tree']), "widget $key: config and tree");
	}
	// The widgets the home page places and leaves to this part.
	foreach (array('home_posts', 'google_reviews') as $w) pg_assert_true(isset($d['standard']['widgets'][$w]), "$w is defined");
	$all = array();
	$keys = array();
	$names = array();
	foreach ($parts as $part) {
		$all = array_merge($all, array_keys($d[$part]['widgets']));
		foreach ($d[$part]['pages'] as $p) {
			$keys[] = $p['key'];
			$names[] = $p['name'];
		}
	}
	pg_assert_same(count($all), count(array_unique($all)), 'widget keys are unique across the parts');
	pg_assert_same(count($keys), count(array_unique($keys)), 'page keys are unique across the parts');
	pg_assert_same(count($names), count(array_unique($names)), 'page names are unique across the parts');
}

function test_filmstudio_standard_contact_newsletter_and_threads()
{
	$d = _test_fms_load();
	$w = $d['standard']['widgets'];

	// The contact form opens a conversation and names its e-mail pages.
	$f = _test_fms_page('contact')['form'];
	pg_assert_same('{{tab:conversation}}', $f['confirmation_page_id']);
	pg_assert_same(1, $f['auto_registration']);
	pg_assert_same('{{tab:email_new_message}}', $f['notify_page_id']);
	pg_assert_same('{{tab:email_message_received}}', $f['confirm_page_id']);
	pg_assert_same('{{site_email}}', $f['notify_email']);
	$contact = json_encode($w['contact_form']['tree']);
	foreach (array('first_name', 'last_name', 'email', 'phone', 'topic', 'message') as $name) {
		pg_assert_contains('"name","value":"' . $name . '"', $contact, "contact field $name");
	}
	pg_assert_contains('{{page:enquiry}}', json_encode(_test_fms_page('contact')['tree']), 'contact points a film to the enquiry');

	// The newsletter's page owns the form.
	$n = _test_fms_page('newsletter')['form'];
	pg_assert_same('{{contact_group:newsletter}}', $n['contact_group_id']);
	pg_assert_same('{{tab:email_welcome}}', $n['confirm_page_id']);
	pg_assert_same('page', $w['newsletter_form']['config']['form_source']);

	// The lists and the post read the staff page's blog form.
	foreach (array('home_posts', 'blog_list', 'blog_post') as $key) {
		pg_assert_same('{{tab:blog_new}}', $w[$key]['config']['custom_form_page_id'], "$key: blog form");
	}
	pg_assert_same(3, $w['home_posts']['config']['max_results']);
	pg_assert_same('{{tab:blog_post}}', $w['blog_list']['config']['detail_page_id']);
	pg_assert_contains('{{page:enquiry}}', json_encode($w['blog_post']['tree']), 'a post points to the enquiry');

	// The threads: the page's comment settings, its own Comments widget, the
	// reply e-mail of the requests part (defined there, not here).
	foreach (array('blog_post' => 'post_comments', 'conversation' => 'conversation_replies') as $page => $widget) {
		$p = _test_fms_page($page);
		pg_assert_same('{{tab:email_reply}}', $p['comments']['email_page'], "$page: reply e-mail");
		pg_assert_contains('"templateWidget":"' . $widget . '"', json_encode($p['tree']), "$page places $widget");
		pg_assert_same('comments', $w[$widget]['config']['regionType']);
		pg_assert_same($page, $w[$widget]['page']);
	}
	pg_assert_same('submitter_only', $w['conversation']['config']['access_control']);
	pg_assert_same('all', $w['site_search']['config']['search_scope']);
	pg_assert_false(isset($w['email_comment']), 'the reply e-mail widget stays the requests part\'s');
	pg_assert_same('google_reviews', $w['google_reviews']['config']['regionType']);
	pg_assert_same('home', $w['google_reviews']['page']);
	pg_assert_same('starter', $w['error_page']['tree']);
}

// Ids are the template's and unique per page, classes never another
// template's, and no year or calendar date is written into a page, so the
// sample never reads as dated.
function test_filmstudio_standard_ids_classes_and_no_fixed_dates()
{
	$d = _test_fms_load();
	$trees = array();
	foreach ($d['standard']['pages'] as $p) {
		$nodes = array();
		_test_fms_nodes($p['tree'], $nodes);
		foreach ($d['standard']['widgets'] as $w) if ($w['page'] === $p['key'] && is_array($w['tree'])) _test_fms_nodes($w['tree'], $nodes);
		$seen = array();
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh|pmz|pai|pcy)-/', $c), "class $c is not another template's");
				pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			if (strpos($id, '-') !== false) pg_assert_true(strpos($id, 'fm-') === 0, "id $id is the template's");
			pg_assert_false(isset($seen[$id]), $p['key'] . ": id $id is unique");
			$seen[$id] = true;
		}
		$trees[] = $p['tree'];
	}
	foreach ($d['standard']['widgets'] as $w) $trees[] = $w['tree'];
	$text = json_encode($trees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace of the drawn icons is not a date.
	$text = str_replace('www.w3.org/2000/svg', '', $text);
	pg_assert_false((bool)preg_match('/\b(19|20)\d{2}\b/', $text), 'no year');
	pg_assert_false((bool)preg_match('/\b\d{4}-\d{2}-\d{2}\b/', $text), 'no ISO date');
}

// Every literal has its Turkish; the Journal reads "Günce", an enquiry
// "talep".
function test_filmstudio_standard_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-filmstudio/standard.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		$words = preg_replace('/\^\^[a-z_]+\^\^/', '', $key);
		if (strpos($words, ' ') === false && $words === strtolower($words)) continue;
		if (preg_match('/\bJournal\b/', $words)) pg_assert_true(mb_strpos($tr, 'Günce', 0, 'UTF-8') !== false || mb_strpos($tr, 'günce', 0, 'UTF-8') !== false, "'$key' says Günce");
		if (preg_match('/\benquir(y|ies)\b/i', $words)) pg_assert_true((bool)preg_match('/tale[pb]/iu', $tr), "'$key' says talep");
	}
}
