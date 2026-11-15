<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Social Community design template's reports and e-mails
 * (includes/design_templates/pinegrap-social/mail.php and reports.php): the
 * pages are the plan's, every widget a page places is defined and sits on
 * that page, the Reports form opens the member's report and mails both
 * sides, the post page's address fills its first field, its status and
 * note are the staff's, a report is shown only to its sender and the staff,
 * the staff pages are private, the moderators' notes are mailed to nobody,
 * the staff's post lists show every status, field ids and classes are the
 * template's own, page links name pages of the finished template, no date
 * is written into the sample, and every literal has its Turkish.
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
require_once(__DIR__ . '/design_template_social_test.php');

// parts.php, mail.php, then reports.php in the same scope, as the template
// file reads them. Read once per run.
function _test_social_reports_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-social';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$part = include $dir . '/reports.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('pages' => $pages, 'widgets' => $part['widgets'], 'shared' => array($email_header_tree(), $email_footer_tree()));
	return $out;
}

function test_social_reports_pages_are_the_plans()
{
	$d = _test_social_reports_load();
	$want = array('report', 'report_view', 'my_reports', 'staff_reports', 'staff_report', 'staff_posts',
		'email_report_new', 'email_report_received', 'email_comment', 'email_reply');
	pg_assert_same($want, array_keys($d['pages']));
	$known = array_flip(_test_social_page_keys());
	$names = array();
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a page of the finished template");
		pg_assert_true(isset($p['tree']['type']) && $p['tree']['type'] === 'root', "$key: a page tree");
		pg_assert_false($p['search'], "$key stays out of the site search");
		pg_assert_false($p['sitemap'], "$key stays out of the sitemap");
		$names[] = $p['name'];
	}
	pg_assert_same(count($names), count(array_unique($names)), 'page addresses are unique');
	foreach (array('staff_reports', 'staff_report', 'staff_posts', 'email_report_new', 'email_report_received', 'email_comment', 'email_reply') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "$key is private");
	}
	foreach (array('report', 'report_view', 'my_reports') as $key) {
		pg_assert_same('registration', $d['pages'][$key]['folder'], "$key is for members");
	}
}

function test_social_reports_widgets_are_placed_on_their_pages()
{
	$d = _test_social_reports_load();
	$json_pages = array();
	foreach ($d['pages'] as $key => $p) $json_pages[$key] = json_encode($p['tree']);
	foreach ($d['widgets'] as $wkey => $w) {
		pg_assert_true(isset($d['pages'][$w['page']]), "$wkey sits on a page of this part");
		pg_assert_contains('"' . $wkey . '"', $json_pages[$w['page']], "$wkey is placed on {$w['page']}");
		pg_assert_same('root', $w['tree']['type'] ?? '', "$wkey has a widget tree");
	}
	foreach ($json_pages as $key => $json) {
		preg_match_all('/"templateWidget":"([a-z0-9_]+)"/', $json, $m);
		foreach (array_unique($m[1]) as $wkey) {
			pg_assert_true(isset($d['widgets'][$wkey]), "$key places the defined widget $wkey");
		}
	}
	// Both comment e-mails draw the comment; the System region keeps the
	// fixed one for a card that lost its link.
	foreach (array('email_comment', 'email_reply') as $key) {
		pg_assert_contains('"templateWidget":"email_comment_card"', $json_pages[$key], "$key draws the comment");
		pg_assert_contains('"regionType":"system"', $json_pages[$key], "$key keeps the System region");
	}
}

function test_social_reports_form_and_records()
{
	$d = _test_social_reports_load();
	$f = $d['pages']['report']['form'];
	pg_assert_same('{{tab:report_view}}', $f['confirmation_page_id'], 'a report opens its own page');
	pg_assert_same('{{tab:email_report_new}}', $f['notify_page_id']);
	pg_assert_same('{{tab:email_report_received}}', $f['confirm_page_id']);
	pg_assert_same('{{site_email}}', $f['notify_email']);
	$tree = json_encode($d['widgets']['report_form']['tree']);
	pg_assert_contains('"url_param":"post"', $tree, 'the post page fills the address');
	pg_assert_same(2, substr_count($tree, '"office_use_only":true'), 'the status and the note are the staff\'s');
	preg_match_all('/"id":"([^"]+)"/', $tree, $m);
	pg_assert_true(count($m[1]) >= 6, 'fields found');
	foreach ($m[1] as $id) pg_assert_true(strpos($id, 'sc-') === 0, "field id $id is the template's own");
	foreach (array('report_detail', 'staff_report_view', 'email_report_staff', 'email_report_member') as $wkey) {
		pg_assert_same('submitter_only', $d['widgets'][$wkey]['config']['access_control'], "$wkey: only its sender and the staff");
		pg_assert_same('{{tab:report}}', $d['widgets'][$wkey]['config']['custom_form_page_id']);
	}
	// The member's own page never shows who sent it nor the staff's note;
	// the staff's page shows both and its Change Status.
	$member = json_encode($d['widgets']['report_detail']['tree']);
	$staff = json_encode($d['widgets']['staff_report_view']['tree']);
	pg_assert_false(strpos($member, '"staff_note"') !== false, 'the member does not see the note');
	pg_assert_false(strpos($member, '"__edit_url"') !== false, 'the member has no Change Status');
	pg_assert_contains('"staff_note"', $staff);
	pg_assert_contains('"__edit_url"', $staff);
	// The post's address is text: what the member typed is never a link.
	foreach (array($member, $staff) as $json) pg_assert_false((bool)preg_match('/"href":"post"/', $json), 'the address is no link');
	// The notes: published at once, never e-mailed, no follow box.
	$c = $d['pages']['staff_report']['comments'];
	pg_assert_true(!isset($c['email_page']) && !isset($c['notify_email']), 'notes are mailed to nobody');
	pg_assert_contains('"comment_form"', json_encode($d['widgets']['report_notes']['tree']));
	pg_assert_false(strpos(json_encode($d['widgets']['report_notes']['tree']), 'comment_watch_form') !== false, 'no follow box');
}

function test_social_reports_lists()
{
	$d = _test_social_reports_load();
	$own = $d['widgets']['my_reports_list']['config'];
	pg_assert_true($own['viewer_filter'] && $own['viewer_filter_submitter'], 'My Reports lists the member\'s own');
	pg_assert_same('{{tab:report_view}}', $own['detail_page_id']);
	foreach (array('staff_reports_open', 'staff_reports_all') as $wkey) {
		pg_assert_same('{{tab:staff_report}}', $d['widgets'][$wkey]['config']['detail_page_id'], "$wkey opens the staff's page");
	}
	pg_assert_same('Open', $d['widgets']['staff_reports_open']['config']['filters'][0]['value']);
	foreach (array('staff_posts_hidden', 'staff_posts_all') as $wkey) {
		$c = $d['widgets'][$wkey]['config'];
		pg_assert_same('{{tab:post_new}}', $c['custom_form_page_id'], "$wkey lists the Posts form");
		pg_assert_same('{{tab:post}}', $c['detail_page_id']);
		foreach ($c['filters'] ?? array() as $row) {
			pg_assert_false($row['operator'] === 'is not equal to', "$wkey carries no live rule: the staff see every status");
		}
	}
	pg_assert_same('Hidden', $d['widgets']['staff_posts_hidden']['config']['filters'][0]['value']);
	// The post page keeps its live rule for everyone, so a row that may be
	// hidden never links to it: the list's pencil opens the record.
	foreach (array('staff_posts_hidden', 'staff_posts_all') as $wkey) {
		pg_assert_false(strpos(json_encode($d['widgets'][$wkey]['tree']), '"href":"form_item_view"') !== false, "$wkey rows do not open the post page");
	}
	pg_assert_true($d['widgets']['staff_posts_all']['config']['search_enabled'], 'every post can be searched');
}

function test_social_reports_links_and_classes()
{
	$d = _test_social_reports_load();
	$known = array_flip(_test_social_page_keys());
	$json = json_encode(array($d['pages'], $d['widgets'], $d['shared']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 8, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	preg_match_all('/\{\{folder:([a-z]+)\}\}/', $json, $f);
	foreach (array_unique($f[1]) as $folder) pg_assert_same('private', $folder, 'a screenshot is kept private');
	$nodes = array();
	foreach (array_merge($d['pages'], $d['widgets']) as $p) _test_social_nodes($p['tree'], $nodes);
	foreach ($nodes as $n) {
		$c = (string)($n['props']['cssClass'] ?? '');
		foreach (preg_split('/\s+/', $c, -1, PREG_SPLIT_NO_EMPTY) as $cls) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|pmk|pht|pdl|pco|pc|pl|plx|plw|psl|pn)-/', $cls), "class $cls is not another template's");
		}
		if (strpos($c, 'bi ') === 0) pg_assert_contains('aria-hidden', json_encode($n), "icon $c hidden from a screen reader");
	}
}

function test_social_reports_no_fixed_dates()
{
	$d = _test_social_reports_load();
	$json = json_encode(array($d['pages'], $d['widgets'], $d['shared']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
}

// Every literal has its Turkish; a report is a "şikâyet" and a post a
// "gönderi".
function test_social_reports_turkish_words()
{
	$map = pg_test_language_map('tr');
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-social';
	foreach (array('mail.php', 'reports.php') as $file) {
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", file_get_contents($dir . '/' . $file), $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
		}
	}
	foreach (array('Report a post', 'Your Report', 'Open reports', 'Every report', 'New Report E-mail') as $key) {
		pg_assert_contains('şikâyet', mb_strtolower($map[$key] ?? '', 'UTF-8'), "'$key' says şikâyet");
	}
	foreach (array('Hidden posts', 'Every post') as $key) {
		pg_assert_contains('önderi', $map[$key] ?? '', "'$key' says gönderi");
	}
}
