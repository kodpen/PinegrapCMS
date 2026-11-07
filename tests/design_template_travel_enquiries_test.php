<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Travel Agency design template's tailor-made enquiries
 * (includes/design_templates/pinegrap-travel/enquiries.php): the pages and
 * widgets, the form's answers taken from parts.php's pick lists, the
 * destination read from ?destination=, the office-only status, the record,
 * list and e-mail bindings naming fields of the form, the e-mail pages kept
 * out of the search, every page link naming a template page and none a
 * shop page, ids and classes the template's own, and every literal with
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

// The page keys of the finished template, and the node and control
// readers of the bookings' tests.
require_once(__DIR__ . '/design_template_travel_test.php');
require_once(__DIR__ . '/design_template_travel_bookings_test.php');

// The enquiries' part as the template file reads it: parts.php, mail.php,
// bookings.php (whose helpers it uses), then enquiries.php in the same
// scope. Read once per run.
function _test_tve_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-travel';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	include $dir . '/bookings.php';
	$part = include $dir . '/enquiries.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('pages' => $pages, 'widgets' => $part['widgets'], 'destinations' => $destination_choices,
		'kinds' => $trip_kinds, 'lengths' => $length_choices, 'budgets' => $budget_choices,
		'contacts' => $contact_choices, 'statuses' => $enquiry_statuses);
	return $out;
}

function test_travel_enquiries_pages_and_widgets()
{
	$d = _test_tve_load();
	pg_assert_same(array('enquiry', 'enquiry_view', 'my_enquiries', 'staff_enquiries', 'email_enquiry_new', 'email_enquiry_received'), array_keys($d['pages']));
	pg_assert_same(array('enquiry_form', 'enquiry_detail', 'enquiry_messages', 'my_enquiries_list', 'staff_enquiries_list', 'email_enquiry_admin', 'email_enquiry_client'), array_keys($d['widgets']));
	pg_assert_same('public', $d['pages']['enquiry']['folder']);
	pg_assert_same('registration', $d['pages']['enquiry_view']['folder'], 'an enquiry is read signed in');
	pg_assert_same('registration', $d['pages']['my_enquiries']['folder']);
	pg_assert_same('private', $d['pages']['staff_enquiries']['folder'], 'only the staff list every enquiry');
	foreach (array('email_enquiry_new', 'email_enquiry_received') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "$key is private");
		pg_assert_true(!empty($d['pages'][$key]['noindex']) && $d['pages'][$key]['search'] === false, "$key is out of the search");
	}
	foreach ($d['pages'] as $key => $p) {
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of this part");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	pg_assert_same('submitter_only', $d['widgets']['enquiry_detail']['config']['access_control'], 'an enquiry is its sender\'s');
	pg_assert_true(!empty($d['widgets']['my_enquiries_list']['config']['viewer_filter_submitter']), 'My Enquiries lists the own enquiries');
	pg_assert_true(!empty($d['widgets']['staff_enquiries_list']['config']['search_enabled']), 'the staff search');
	$comments = $d['pages']['enquiry_view']['comments'];
	pg_assert_true($comments['allow_new'] && $comments['login'], 'messages from the signed-in sender');
	pg_assert_same('{{tab:email_reply}}', $comments['email_page']);
}

// The form opens the enquiry's page and mails both sides; its answers are
// parts.php's, the destination reads ?destination= and the status is the
// agency's.
function test_travel_enquiries_form()
{
	$d = _test_tve_load();
	$form = $d['pages']['enquiry']['form'];
	pg_assert_same('{{tab:enquiry_view}}', $form['confirmation_page_id']);
	pg_assert_same(1, $form['auto_registration']);
	pg_assert_same('{{tab:email_enquiry_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_enquiry_received}}', $form['confirm_page_id']);
	$c = _test_tvb_controls($d['widgets']['enquiry_form']['tree']);
	foreach (array('destination', 'trip_kind', 'travel_time', 'trip_length', 'travellers', 'budget', 'details', 'contact_method', 'first_name', 'last_name', 'email', 'phone', 'status', 'consent') as $name) {
		pg_assert_true(isset($c[$name]), "the form has $name");
	}
	pg_assert_same($d['destinations'], $c['destination']['options'], 'the destinations');
	pg_assert_same('destination', $c['destination']['cf']['url_param'] ?? '', 'the destination reads ?destination=');
	pg_assert_same($d['kinds'], $c['trip_kind']['options'], 'the kinds of trip');
	pg_assert_same($d['lengths'], $c['trip_length']['options'], 'the lengths');
	pg_assert_same($d['budgets'], $c['budget']['options'], 'the budgets');
	pg_assert_same(array_column($d['contacts'], 0), $c['contact_method']['options'], 'the ways to reach');
	pg_assert_same($d['statuses'], $c['status']['options'], 'the statuses');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the agency\'s');
	// Every field the record, the lists and the e-mails show is the form's.
	$fields = array_keys($c);
	foreach (array('enquiry_detail', 'my_enquiries_list', 'staff_enquiries_list', 'email_enquiry_admin', 'email_enquiry_client') as $key) {
		$nodes = array();
		_test_tvb_nodes($d['widgets'][$key]['tree'], $nodes);
		foreach ($nodes as $n) {
			$token = (string)($n['props']['_bindings']['text'] ?? '');
			if ($token === '' || strpos($token, '__') === 0 || in_array($token, array('reference_code', 'not_found', 'number_of_comments'), true)) continue;
			pg_assert_true(in_array($token, $fields, true), "$key binds $token, a field of the form");
		}
	}
	foreach ($d['widgets']['staff_enquiries_list']['config']['search_fields'] as $name) {
		pg_assert_true(in_array($name, $fields, true), "the staff search reads $name");
	}
}

function test_travel_enquiries_links_ids_and_classes()
{
	$d = _test_tve_load();
	$known = array_flip(_test_tv_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_tv_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$nodes = array();
	foreach ($trees as $t) _test_tvb_nodes(is_array($t) && isset($t['tree']) ? $t['tree'] : $t, $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch)-/', $c), "class $c is not another template's");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'tv-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a tour reads "tur" and a destination
// "destinasyon".
function test_travel_enquiries_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-travel/enquiries.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		// A ^^field^^ token is the field's name, not a word.
		$words = preg_replace('/\^\^[a-z_]+\^\^/', '', $key);
		if (preg_match('/\b[Tt]ours?\b/', $words)) pg_assert_true(mb_stripos($tr, 'tur', 0, 'UTF-8') !== false, "'$key' says tur");
		if (preg_match('/\b[Dd]estinations?\b/', $words)) pg_assert_true(mb_stripos($tr, 'destinasyon', 0, 'UTF-8') !== false, "'$key' says destinasyon");
	}
}
