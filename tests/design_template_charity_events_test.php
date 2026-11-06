<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Charity design template's events and reports
 * (includes/design_templates/pinegrap-charity/events.php): the file carries
 * the events, event, new event and new report pages and places the home
 * page's next events and the transparency page's reports; the sample events
 * are still to come whenever the template is opened and the lists show
 * them from today on; every Sign up button opens the events page with the
 * event named, which the sign-up form reads; the report file goes to the
 * public reports folder; every page link names a page of the finished
 * template and none needs the shop; no year is written; every literal has
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

require_once(__DIR__ . '/helpers/language_map.php');
// The page keys of the finished template, the shop's pages and the node walk.
require_once(__DIR__ . '/design_template_charity_test.php');

// The events part as the template file reads it: after parts.php, mail.php
// and forms.php in one scope. Read once per run.
function _test_charity_events_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-charity';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	include $dir . '/forms.php';
	$part = include $dir . '/events.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages);
	return $out;
}


function test_charity_events_pages_and_widgets()
{
	$d = _test_charity_events_load();
	pg_assert_same(array('events', 'event_view', 'event_new', 'report_new'), array_keys($d['pages']));
	$known = array_flip(_test_charity_page_keys());
	foreach ($d['part']['widgets'] as $name => $w) {
		pg_assert_true(isset($known[$w['page']]), "widget $name sits on a template page");
	}
	pg_assert_same('home', $d['part']['widgets']['home_events']['page']);
	pg_assert_same('transparency', $d['part']['widgets']['reports']['page']);
	pg_assert_same('private', $d['pages']['event_new']['folder']);
	pg_assert_same('private', $d['pages']['report_new']['folder']);
	pg_assert_same(1, $d['pages']['event_new']['form']['collection']);
	pg_assert_same(1, $d['pages']['report_new']['form']['collection']);
}

// The samples fall after today, and the lists keep only the coming events.
function test_charity_events_samples_are_to_come()
{
	$d = _test_charity_events_load();
	$today = date('Y-m-d');
	$samples = $d['pages']['event_new']['form']['sample_records'];
	pg_assert_true(count($samples) >= 3, 'sample events');
	foreach ($samples as $s) {
		pg_assert_true((bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $s['event_date']), 'a stored date');
		pg_assert_true($s['event_date'] > $today, "{$s['title']} is still to come");
	}
	foreach (array('home_events', 'events_list') as $w) {
		$cfg = $d['part']['widgets'][$w]['config'];
		pg_assert_same('event_date', $cfg['order_by_field']);
		pg_assert_same('current date', $cfg['filters'][0]['dynamic_value'], "$w shows the coming events");
	}
	pg_assert_same(3, $d['part']['widgets']['home_events']['config']['max_results']);
}

// Every Sign up button opens the events page with the event's title as
// ?event=, the parameter the sign-up form's Event field starts with.
function test_charity_events_sign_up_reads_the_event()
{
	$d = _test_charity_events_load();
	$json = json_encode($d['part']['widgets']);
	pg_assert_true(substr_count($json, '"page_id":"{{tab:events}}","params":[{"name":"event","token":"title"}]') >= 2, 'the card and the event page send the event');
	$nodes = array();
	_test_charity_nodes($d['part']['widgets']['event_signup_form']['tree'], $nodes);
	$event = null;
	$consent = false;
	foreach ($nodes as $n) {
		if (($n['props']['id'] ?? '') === 'ch-su-event') $event = $n;
		if (($n['props']['id'] ?? '') === 'ch-su-consent') $consent = true;
	}
	pg_assert_true($event !== null, 'the Event field');
	pg_assert_same('event', $event['props']['_cf']['url_param']);
	pg_assert_true($consent, 'the consent box');
	pg_assert_same('events', $d['part']['widgets']['event_signup_form']['page']);
	pg_assert_same('{{site_email}}', $d['pages']['events']['form']['notify_email']);
}

function test_charity_events_report_file_goes_to_the_reports_folder()
{
	$d = _test_charity_events_load();
	$json = json_encode($d['part']['widgets']['report_form']['tree']);
	pg_assert_contains('{{folder:reports}}', $json);
	$cfg = $d['part']['widgets']['reports']['config'];
	pg_assert_same('{{tab:report_new}}', $cfg['custom_form_page_id']);
	foreach ($d['pages']['report_new']['form']['sample_records'] as $s) {
		pg_assert_false(isset($s['file']), 'a sample report carries no file');
	}
}

function test_charity_events_links_dates_and_words()
{
	$d = _test_charity_events_load();
	$all = json_encode($d['part']);
	$known = array_flip(_test_charity_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $all, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_charity_shop_keys() as $key) pg_assert_false(strpos($all, '{{page:' . $key . '}}') !== false, "events do not link to $key");

	// The samples' stored dates aside, no year or month is written.
	$part = $d['part'];
	unset($part['pages'][2]['form']['sample_records']);
	$json = json_encode($part, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$json = preg_replace('/"text":"[0-9.\-]+"/', '', $json);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the pages');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the pages');

	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-charity/events.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (preg_match('/\b(donate|gifts?)\b/i', $key) && isset($map[$key])) {
			pg_assert_true(mb_stripos($map[$key], 'bağış', 0, 'UTF-8') !== false, "'$key' says bağış");
		}
	}
}
