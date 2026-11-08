<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Home Services design template's standard pages
 * (includes/design_templates/pinegrap-homeservices/standard.php): the
 * pages every template carries and their folders, the contact form
 * opening a conversation, the newsletter's group and welcome e-mail, the
 * tips and the Google reviews on the home page, the threads mailing every
 * message, every page link naming a template page and none a shop page,
 * ids and classes the template's own, no fixed date, and every literal
 * with its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template, the shop's pages, and the
// control and node readers of the visits' test.
require_once(__DIR__ . '/design_template_homeservices_visits_test.php');

// The standard part as the template file reads it: parts.php, mail.php,
// services.php and visits.php (their record and e-mail pieces), then
// standard.php in the same scope. Read once per run.
function _test_hsst_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-homeservices';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	include $dir . '/services.php';
	include $dir . '/visits.php';
	$part = include $dir . '/standard.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	return $out = array('pages' => $pages, 'widgets' => $part['widgets']);
}

function test_homeservices_standard_pages_and_folders()
{
	$d = _test_hsst_load();
	pg_assert_same(array('contact', 'newsletter', 'blog', 'blog_post', 'inbox', 'conversation', 'search', 'error',
		'email_new_message', 'email_message_received', 'email_welcome'), array_keys($d['pages']));
	foreach (array('contact', 'newsletter', 'blog', 'blog_post', 'search', 'error') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	foreach (array('inbox', 'conversation') as $key) pg_assert_same('registration', $d['pages'][$key]['folder'], "$key: signed in");
	foreach (array('email_new_message', 'email_message_received', 'email_welcome') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "$key is private");
		pg_assert_true(!empty($d['pages'][$key]['noindex']) && $d['pages'][$key]['search'] === false, "$key is out of the search");
	}
	pg_assert_true(!empty($d['pages']['error']['noindex']), 'error: noindex');
	foreach ($d['pages'] as $key => $p) {
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
	$known = array_flip(_test_hs_page_keys());
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true(isset($w['config']['regionType'], $w['tree']), "$key: config and tree");
		pg_assert_true(isset($known[$w['page']]), "$key sits on a template page");
		if (isset($d['pages'][$w['page']])) {
			pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
		}
	}
	foreach (array('home_posts', 'google_reviews') as $key) pg_assert_same('home', $d['widgets'][$key]['page'], "$key is the home page's");
}

// The contact form opens the conversation and mails both sides; the
// newsletter fills its group and sends the welcome; the threads mail
// every message.
function test_homeservices_standard_contact_newsletter_and_threads()
{
	$d = _test_hsst_load();
	$f = $d['pages']['contact']['form'];
	pg_assert_same('{{tab:conversation}}', $f['confirmation_page_id']);
	pg_assert_same(1, $f['auto_registration']);
	pg_assert_same('{{tab:email_new_message}}', $f['notify_page_id']);
	pg_assert_same('{{tab:email_message_received}}', $f['confirm_page_id']);
	pg_assert_same('{{site_email}}', $f['notify_email']);
	$c = _test_hsv_controls($d['widgets']['contact_form']['tree']);
	pg_assert_same(array('first_name', 'last_name', 'email', 'phone', 'topic', 'message'), array_keys($c), 'the contact fields');
	pg_assert_same('email_address', $c['email']['cf']['contact_field'] ?? '', 'the e-mail is the contact\'s');
	$fields = array_keys($c);
	foreach (array('conversation', 'inbox_list', 'email_message_admin', 'email_message_sender') as $key) {
		$nodes = array();
		_test_hsv_nodes($d['widgets'][$key]['tree'], $nodes);
		foreach ($nodes as $n) {
			$token = (string)($n['props']['_bindings']['text'] ?? '');
			if ($token === '' || strpos($token, '__') === 0 || in_array($token, array('reference_code', 'not_found', 'number_of_comments', 'submitted_date_and_time'), true)) continue;
			pg_assert_true(in_array($token, $fields, true), "$key binds $token, a field of the contact form");
		}
		pg_assert_same('{{tab:contact}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key reads the contact form");
	}
	$n = $d['pages']['newsletter']['form'];
	pg_assert_same('{{contact_group:newsletter}}', $n['contact_group_id']);
	pg_assert_same('{{tab:email_welcome}}', $n['confirm_page_id']);
	pg_assert_same('page', $d['widgets']['newsletter_form']['config']['form_source']);
	foreach (array('blog_post' => 'post_comments', 'conversation' => 'conversation_replies') as $page => $widget) {
		$p = $d['pages'][$page];
		pg_assert_true($p['comments']['allow_new'] && $p['comments']['login'], "$page: comments from signed-in members");
		pg_assert_same('{{tab:email_reply}}', $p['comments']['email_page'], "$page: reply e-mail");
		pg_assert_same('{{site_email}}', $p['comments']['notify_email'], "$page: the company hears every message");
		pg_assert_same('comments', $d['widgets'][$widget]['config']['regionType']);
	}
	pg_assert_same('submitter_only', $d['widgets']['conversation']['config']['access_control']);
	pg_assert_true(!empty($d['widgets']['inbox_list']['config']['viewer_filter_submitter']), 'the inbox lists the own conversations');
	pg_assert_same('all', $d['widgets']['site_search']['config']['search_scope']);
	pg_assert_contains('"eo_visible_if":"has_reviews"', json_encode($d['widgets']['google_reviews']['tree']), 'the reviews show only with reviews');
	foreach (array('home_posts', 'blog_list', 'blog_post') as $key) {
		pg_assert_same('{{tab:blog_new}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key reads the article form");
	}
	pg_assert_same(3, $d['widgets']['home_posts']['config']['max_results'], 'three tips on the home page');
	pg_assert_same(array('title', 'summary', 'content'), $d['widgets']['blog_list']['config']['search_fields']);
	$json = json_encode(array($d['widgets']['blog_post']['tree'], $d['widgets']['conversation']['tree']));
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found notes');
	pg_assert_contains('"eo_visible_if":"can_edit"', $json, 'the staff\'s Edit button');
	foreach (array('email_message_admin', 'email_message_sender') as $key) {
		pg_assert_contains('{{page:conversation}}?r=^^reference_code^^', json_encode($d['widgets'][$key]['tree'], JSON_UNESCAPED_SLASHES), "$key opens the conversation");
	}
}

// The help centre's questions are search sections of their own.
function test_homeservices_standard_help_questions()
{
	$d = _test_hsst_load();
	$nodes = array();
	_test_hsv_nodes($d['pages']['search']['tree'], $nodes);
	$sections = 0;
	foreach ($nodes as $n) {
		if (!empty($n['props']['searchSection']) && strpos((string)($n['props']['id'] ?? ''), 'hs-help-faq') === 0) $sections++;
	}
	pg_assert_same(5, $sections, 'five questions, each a search section');
}

function test_homeservices_standard_links_ids_and_classes()
{
	$d = _test_hsst_load();
	$known = array_flip(_test_hs_page_keys());
	$json = json_encode(array($d['pages'], $d['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_hs_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p['tree'];
	foreach ($d['widgets'] as $w) $trees[] = $w['tree'];
	$nodes = array();
	foreach ($trees as $t) _test_hsv_nodes($t, $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pbk|psh|pwd|pmu)-/', $c), "class $c is not another template's");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'hs-') === 0, "id $id is the template's");
	}
}

// The sample is never dated.
function test_homeservices_standard_no_fixed_dates()
{
	$d = _test_hsst_load();
	$json = json_encode(array($d['pages'], $d['widgets']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$json = str_replace(array('www.w3.org/2000/svg', '{{year}}'), '', $json);
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; a visit reads "ziyaret", a service
// "hizmet", an estimate "teklif", and none says "rezervasyon".
function test_homeservices_standard_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-homeservices/standard.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		$words = preg_replace('/\^\^[a-z_]+\^\^/', '', $key);
		if (preg_match('/\b[Vv]isits?\b/', $words)) pg_assert_true(mb_stripos($tr, 'ziyaret', 0, 'UTF-8') !== false, "'$key' says ziyaret");
		if (preg_match('/\b[Ss]ervices?\b/', $words)) pg_assert_true(mb_stripos($tr, 'hizmet', 0, 'UTF-8') !== false, "'$key' says hizmet");
		if (preg_match('/\b[Ee]stimates?\b/', $words)) pg_assert_true(mb_stripos($tr, 'teklif', 0, 'UTF-8') !== false, "'$key' says teklif");
		pg_assert_false(mb_stripos($tr, 'rezervasyon', 0, 'UTF-8') !== false, "'$key' never says rezervasyon");
	}
	foreach (array("lang('Service')", "lang('Services')", "lang('Booking')", "lang('book')") as $k) {
		pg_assert_false(strpos($src, $k) !== false, "standard.php does not use $k");
	}
}
