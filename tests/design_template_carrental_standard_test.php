<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Car Rental design template's standard pages
 * (includes/design_templates/pinegrap-carrental/standard.php): the file
 * carries contact, newsletter, the Road Trip Journal, post, inbox,
 * conversation, help center, error and the message and welcome e-mails,
 * each widget on one of them (the newsletter band on every page, the
 * newest posts and the Google reviews on the home page); the contact form
 * opens its conversation, seen only by its sender and the staff, and its
 * e-mails read only the form's fields; the newsletter fills its contact
 * group; every page link names a page of the finished template; the ids
 * and classes are the template's own; no fixed date; every literal has its
 * Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template and the tree helpers.
require_once __DIR__ . '/design_template_carrental_test.php';
require_once __DIR__ . '/design_template_carrental_bookings_test.php';

// The standard part as the template file reads it: after parts.php,
// mail.php and bookings.php in one scope. Read once per run.
function _test_crs_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-carrental';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	include $dir . '/bookings.php';
	$part = include $dir . '/standard.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'contact_topics' => $contact_topics, 'company' => $company);
	return $out;
}

function test_carrental_standard_pages_and_widgets()
{
	$d = _test_crs_load();
	pg_assert_same(array('contact', 'newsletter', 'blog', 'blog_post', 'inbox', 'conversation', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome'), array_keys($d['pages']));
	$known = array_flip(_test_cr_page_keys());
	$names = array();
	foreach ($d['pages'] as $key => $p) {
		foreach (array('name', 'title', 'tree') as $f) pg_assert_true(!empty($p[$f]), "$key has $f");
		pg_assert_true((bool)preg_match('/^[a-z-]+$/', $p['name']), "$key: the page name is an address");
		pg_assert_true(!isset($names[$p['name']]), "$key: page name used once");
		$names[$p['name']] = true;
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		if (strpos($key, 'email_') === 0) {
			pg_assert_true(!empty($p['noindex']) && $p['search'] === false && $p['sitemap'] === false, "$key: an e-mail page out of search");
		}
	}
	foreach ($d['widgets'] as $name => $w) {
		pg_assert_true($w['page'] === '' || isset($known[$w['page']]), "widget $name sits on a template page");
		if (isset($d['pages'][$w['page']])) {
			pg_assert_contains('"templateWidget":"' . $name . '"', json_encode($d['pages'][$w['page']]['tree']), "$name is placed on its page");
		}
	}
	pg_assert_same('', $d['widgets']['newsletter_band']['page'], 'the band is drawn on every page');
	pg_assert_same('home', $d['widgets']['home_posts']['page']);
	pg_assert_same(3, $d['widgets']['home_posts']['config']['max_results']);
	pg_assert_same('home', $d['widgets']['google_reviews']['page']);
	pg_assert_same('google_reviews', $d['widgets']['google_reviews']['config']['regionType']);
	pg_assert_contains('"eo_visible_if":"has_reviews"', json_encode($d['widgets']['google_reviews']['tree']), 'the reviews leave when Google returns none');
	pg_assert_same('starter', $d['widgets']['error_page']['tree']);
}

function test_carrental_standard_contact_and_newsletter_forms()
{
	$d = _test_crs_load();
	$w = $d['widgets'];
	$form = $d['pages']['contact']['form'];
	pg_assert_same('{{tab:conversation}}', $form['confirmation_page_id']);
	pg_assert_same('{{tab:email_new_message}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_message_received}}', $form['confirm_page_id']);
	pg_assert_same(1, $form['auto_registration']);
	pg_assert_same('submitter_only', $w['conversation']['config']['access_control']);
	foreach (array('email_message_admin', 'email_message_sender') as $key) {
		pg_assert_same('public', $w[$key]['config']['access_control'], "$key is drawn for the message just sent");
	}
	$c = _test_crb_controls($w['contact_form']['tree']);
	pg_assert_same(array('first_name', 'last_name', 'email', 'phone', 'topic', 'message', 'consent'), array_keys($c), 'the contact form\'s fields');
	pg_assert_same($d['contact_topics'], $c['topic']['options'], 'the topics');
	$own = array('form_item_view', 'not_found', 'reference_code', 'number_of_comments', 'submitted_date_and_time', '__edit_url', 'can_edit',
		'__new_account_email', '__new_account_set_password_url', 'has_set_password_link', 'has_new_account', 'record_found', 'record_not_found',
		'submitted_this_session');
	foreach (array('conversation', 'inbox_list', 'email_message_admin', 'email_message_sender') as $key) {
		pg_assert_same('{{tab:contact}}', $w[$key]['config']['custom_form_page_id'], "$key reads the contact form");
		foreach (_test_crb_tokens($w[$key]['tree']) as $t) {
			pg_assert_true(isset($c[$t]) || in_array($t, $own, true), "$key reads $t, a field of the contact form");
		}
	}
	preg_match_all('/\^\^([a-z_]+)\^\^/', $form['notify_subject'], $m);
	foreach ($m[1] as $f) pg_assert_true(isset($c[$f]), "the notice subject reads $f, a field");
	pg_assert_contains('{{page:privacy}}', json_encode($w['contact_form']['tree']), 'the consent links the privacy notice');
	pg_assert_contains(json_encode($d['company']['address']), json_encode($d['pages']['contact']['tree']), 'the contact page names the head office');

	$news = $d['pages']['newsletter']['form'];
	pg_assert_same('{{contact_group:newsletter}}', $news['contact_group_id']);
	pg_assert_same('{{tab:email_welcome}}', $news['confirm_page_id']);
	pg_assert_same('{{tab:newsletter}}', $w['newsletter_band']['config']['form_id'], 'the band fills the same list');
	foreach (array('home_posts', 'blog_list', 'blog_post') as $key) {
		pg_assert_same('{{tab:blog_new}}', $w[$key]['config']['custom_form_page_id'], "$key reads the journal's posts");
	}
}

function test_carrental_standard_links_ids_and_classes()
{
	$d = _test_crs_load();
	$known = array_flip(_test_cr_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($d['part']), $m);
	pg_assert_true(count(array_unique($m[1])) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	$own = array('pcr-brand', 'pcr-display', 'pcr-eyebrow', 'pcr-hero', 'pcr-lift', 'pcr-price');
	$trees = array();
	foreach ($d['pages'] as $key => $p) $trees[$key] = $p['tree'];
	foreach ($d['widgets'] as $key => $w) if (is_array($w['tree'])) $trees['widget ' . $key] = $w['tree'];
	foreach ($trees as $key => $tree) {
		$nodes = array();
		_test_crb_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				if (preg_match('/^p[a-z]{2}-/', $c)) pg_assert_true(in_array($c, $own, true), "$key: class $c is the template's");
				pg_assert_false(strpos($c, 'opacity-') === 0, "$key: no faded text ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'cr-') === 0, "$key: id $id is the template's");
		}
	}
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-carrental/standard.php');
	$src = preg_replace('/@copyright[^\n]*/', '', $src);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $src), 'standard.php: no year');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $src), 'standard.php: no month');
	pg_assert_false((bool)preg_match('/#[0-9a-f]{6}\b/i', $src), 'standard.php: its e-mails take their colours from mail.php');
}

// Every literal has its Turkish; the blog reads "Yol Günlüğü", a deal
// "kampanya" or "fırsat".
function test_carrental_standard_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-carrental/standard.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		if (strpos($key, 'Road Trip Journal') !== false) pg_assert_true((bool)preg_match('/Yol Günlüğü/u', $map[$key]), "'$key' says Yol Günlüğü");
		if (preg_match('/\bdeals?\b/i', $key)) pg_assert_true((bool)preg_match('/kampanya|fırsat/iu', $map[$key]), "'$key' says kampanya or fırsat");
		pg_assert_false(in_array($key, array('Contact', 'Bookings', 'Deposit', 'Doors', 'Available', 'Return', 'Deal', 'Desk', 'News'), true),
			"'$key' means something else in Turkish");
	}
}
