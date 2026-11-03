<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Marketplace design template's requests
 * (includes/design_templates/pinegrap-market/mail.php and requests.php):
 * the pages are the plan's, every widget a page places is defined and sits
 * on that page, the forms' e-mails and views name request pages, the
 * request and staff pages stay out of the search, a record page shows only
 * its sender's record and the e-mail widgets the submission just sent, the
 * forms keep their status for the staff, field ids are the template's own,
 * page links name pages of the finished template, no shop page is needed,
 * no date is written into the sample, and every literal has its Turkish.
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
require_once(__DIR__ . '/design_template_market_test.php');

// parts.php, mail.php, then requests.php in the same scope, as the template
// file reads them. Read once per run.
function _test_market_requests_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-market';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$part = include $dir . '/requests.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('pages' => $pages, 'widgets' => $part['widgets'], 'shared' => array($email_header_tree(), $email_footer_tree()));
	return $out;
}

function test_market_requests_pages_are_the_plans()
{
	$d = _test_market_requests_load();
	$want = array('apply', 'application_view', 'my_applications', 'staff_applications',
		'help', 'help_view', 'my_help', 'staff_help', 'privacy',
		'email_application_new', 'email_application_received', 'email_help_new', 'email_help_received', 'email_reply');
	pg_assert_same($want, array_keys($d['pages']));
	$known = array_flip(_test_market_page_keys());
	foreach ($want as $key) pg_assert_true(isset($known[$key]), "$key is a page of the finished template");
	$names = array();
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($p['tree']['type']) && $p['tree']['type'] === 'root', "$key: a page tree");
		pg_assert_true(!isset($p['requires']), "$key stands without the shop");
		$names[] = $p['name'];
	}
	pg_assert_same(count($names), count(array_unique($names)), 'page addresses are unique');
}

function test_market_requests_widgets_are_placed_on_their_pages()
{
	$d = _test_market_requests_load();
	$json_pages = array();
	foreach ($d['pages'] as $key => $p) $json_pages[$key] = json_encode($p['tree']);
	foreach ($d['widgets'] as $wkey => $w) {
		pg_assert_true(isset($d['pages'][$w['page']]), "$wkey sits on a page of this part");
		pg_assert_contains('"' . $wkey . '"', $json_pages[$w['page']], "$wkey is placed on {$w['page']}");
		pg_assert_true(isset($w['tree']['type']), "$wkey has a tree");
	}
	// Every widget a page names is defined, here or by another part.
	$others = array('newsletter_band');
	foreach ($json_pages as $key => $json) {
		preg_match_all('/"templateWidget":"([a-z0-9_]+)"/', $json, $m);
		foreach (array_unique($m[1]) as $wkey) {
			if (in_array($wkey, $others, true)) continue;
			pg_assert_true(isset($d['widgets'][$wkey]), "$key places the defined widget $wkey");
		}
	}
}

function test_market_requests_forms_and_records()
{
	$d = _test_market_requests_load();
	foreach (array('apply' => 'application', 'help' => 'help') as $form => $kind) {
		$f = $d['pages'][$form]['form'];
		$view = $kind === 'application' ? 'application_view' : 'help_view';
		pg_assert_same('{{tab:' . $view . '}}', $f['confirmation_page_id'], "$form opens its record");
		pg_assert_same('{{tab:email_' . $kind . '_new}}', $f['notify_page_id']);
		pg_assert_same('{{tab:email_' . $kind . '_received}}', $f['confirm_page_id']);
		pg_assert_same(1, $f['auto_registration'], "$form opens an account");
		$tree = json_encode($d['widgets'][$kind . '_form']['tree']);
		pg_assert_contains('"office_use_only":true', $tree, "$form: the status is the staff's");
		pg_assert_contains('{{page:privacy}}', $tree, "$form: the consent links the privacy page");
		pg_assert_same('submitter_only', $d['widgets'][$kind . '_detail']['config']['access_control'], "$view: only its sender and the staff");
		pg_assert_same('public', $d['widgets']['email_' . $kind . '_admin']['config']['access_control']);
		pg_assert_same('public', $d['widgets']['email_' . $kind . '_client']['config']['access_control']);
		pg_assert_same('{{tab:email_reply}}', $d['pages'][$view]['comments']['email_page'], "$view: messages are mailed");
		preg_match_all('/"id":"([^"]+)"/', $tree, $m);
		foreach ($m[1] as $id) pg_assert_true(strpos($id, 'mk-') === 0, "field id $id is the template's own");
	}
	pg_assert_contains('"url_param":"category"', json_encode($d['widgets']['application_form']), 'the category page fills the category');
	pg_assert_contains('"url_param":"order"', json_encode($d['widgets']['help_form']), 'the order page fills the order number');
	foreach (array('application_view', 'my_applications', 'staff_applications', 'help_view', 'my_help', 'staff_help',
		'email_reply', 'email_help_new') as $key) {
		pg_assert_false($d['pages'][$key]['search'], "$key stays out of the site search");
		pg_assert_false($d['pages'][$key]['sitemap'], "$key stays out of the sitemap");
	}
	pg_assert_same('private', $d['pages']['staff_applications']['folder']);
	pg_assert_same('private', $d['pages']['staff_help']['folder']);
	pg_assert_same('private', $d['pages']['email_reply']['folder']);
}

function test_market_requests_links_name_template_pages()
{
	$d = _test_market_requests_load();
	$known = array_flip(_test_market_page_keys());
	$json = json_encode(array($d['pages'], $d['widgets'], $d['shared']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_market_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "requests do not link to $key");
	}
	preg_match_all('/\{\{folder:([a-z]+)\}\}/', $json, $f);
	foreach (array_unique($f[1]) as $folder) pg_assert_true(in_array($folder, array('public', 'registration', 'private'), true), "folder $folder");
}

function test_market_requests_no_fixed_dates()
{
	$d = _test_market_requests_load();
	$json = json_encode(array($d['pages'], $d['widgets'], $d['shared']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
}

function test_market_requests_turkish_words()
{
	$map = pg_test_language_map('tr');
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-market';
	foreach (array('mail.php', 'requests.php') as $file) {
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", file_get_contents($dir . '/' . $file), $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (preg_match('/\bmakers?\b/i', $key) && isset($map[$key])) {
				pg_assert_true(mb_stripos($map[$key], 'üretici', 0, 'UTF-8') !== false, "'$key' says üretici");
			}
		}
	}
}
