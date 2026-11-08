<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Bakery design template's wholesale enquiries
 * (includes/design_templates/pinegrap-bakery/wholesale.php): the pages and
 * widgets, the enquiry form (its fields and pick lists, the range read from
 * ?range= as every range card sends it, the delivery days as boxes, no
 * start day before today, the private menu, the office-only status), the
 * record page with its messages, the lists and e-mails binding fields the
 * form has, every page link naming a template page, ids and classes the
 * template's own, no fixed date and every literal with its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The node walkers and controls reader of the requests' test.
require_once(__DIR__ . '/design_template_bakery_requests_test.php');

// The wholesale part as the template file reads it: parts.php, mail.php,
// the public pages, the classes, the requests, then wholesale.php in the
// same scope. Read once per run.
function _test_bkw_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bakery';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$public = include $dir . '/pages_public.php';
	include $dir . '/classes.php';
	$requests = include $dir . '/requests.php';
	$part = include $dir . '/wholesale.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$range_cards = array();
	foreach (array_keys($wholesale_ranges) as $key) $range_cards[] = $range_card($key);
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'],
		'ranges' => $range_titles, 'businesses' => $business_choices, 'days' => $delivery_day_choices,
		'statuses' => $enquiry_statuses, 'contact_choices' => $contact_choices,
		'public' => $public, 'range_cards' => $range_cards, 'requests' => $requests);
	return $out;
}

function test_bakery_wholesale_pages_and_widgets()
{
	$d = _test_bkw_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('wholesale_enquiry', 'enquiry_view', 'my_enquiries', 'staff_enquiries',
		'email_enquiry_new', 'email_enquiry_received'), array_keys($d['pages']));
	pg_assert_same(array('wholesale_enquiry_form', 'enquiry_detail', 'enquiry_messages', 'my_enquiries_list', 'staff_enquiries_list',
		'email_enquiry_admin', 'email_enquiry_client'), array_keys($d['widgets']));
	$known = array_flip(_test_bk_page_keys());
	foreach (array_keys($d['pages']) as $key) pg_assert_true(isset($known[$key]), "$key is a page of the finished template");
	foreach (array_keys($d['widgets']) as $key) pg_assert_false(isset($d['requests']['widgets'][$key]), "$key is a widget of its own");
	pg_assert_same('public', $d['pages']['wholesale_enquiry']['folder'], 'the enquiry is public');
	foreach (array('enquiry_view', 'my_enquiries') as $key) pg_assert_same('registration', $d['pages'][$key]['folder'], "$key is read signed in");
	foreach (array('staff_enquiries', 'email_enquiry_new', 'email_enquiry_received') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "$key is private");
		pg_assert_true($d['pages'][$key]['search'] === false, "$key is out of the search");
	}
	foreach ($d['pages'] as $key => $p) {
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of this part");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	pg_assert_same('submitter_only', $d['widgets']['enquiry_detail']['config']['access_control'], 'the enquiry is its sender\'s');
	pg_assert_true(!empty($d['widgets']['my_enquiries_list']['config']['viewer_filter_submitter']), 'My Enquiries lists the own records');
	pg_assert_true(!empty($d['widgets']['staff_enquiries_list']['config']['search_enabled']), 'the staff search');
}

// The enquiry: its fields, the range from the address as every range card
// sends it, the delivery days as boxes, no start day before today, the
// private menu, the office-only status, the contact fields.
function test_bakery_wholesale_enquiry_form()
{
	$d = _test_bkw_load();
	$form = $d['pages']['wholesale_enquiry']['form'];
	pg_assert_same('{{tab:enquiry_view}}', $form['confirmation_page_id']);
	pg_assert_same(1, $form['auto_registration']);
	pg_assert_same('{{tab:email_enquiry_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_enquiry_received}}', $form['confirm_page_id']);
	$c = _test_bkr_controls($d['widgets']['wholesale_enquiry_form']['tree']);
	pg_assert_same(array('business', 'business_kind', 'range', 'amounts', 'delivery_days', 'start_date', 'address', 'menu', 'notes',
		'first_name', 'last_name', 'email', 'phone', 'contact_method', 'status', 'consent'), array_keys($c));
	pg_assert_same($d['businesses'], $c['business_kind']['options'], 'the kinds of business');
	pg_assert_same($d['ranges'], $c['range']['options'], 'the ranges');
	pg_assert_same('range', $c['range']['cf']['url_param'] ?? '', 'the range reads ?range=');
	pg_assert_same('checkbox', $c['delivery_days']['type'], 'the delivery days are boxes');
	pg_assert_same($d['days'], $c['delivery_days']['options'], 'the delivery days');
	pg_assert_same('date', $c['start_date']['type'], 'the start is a day picker');
	pg_assert_same('today', $c['start_date']['cf']['rules']['min_date'] ?? '', 'no day before today');
	pg_assert_same('file', $c['menu']['type'], 'the menu is a file');
	pg_assert_same('{{folder:private}}', $c['menu']['cf']['upload_folder_id'] ?? '', 'the menu is private');
	pg_assert_same($d['statuses'], $c['status']['options'], 'the statuses');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the bakery\'s');
	pg_assert_same(array_column($d['contact_choices'], 0), $c['contact_method']['options'], 'the ways to reach the customer');
	foreach (array('first_name' => 'first_name', 'last_name' => 'last_name', 'email' => 'email_address', 'phone' => 'mobile_phone') as $name => $contact) {
		pg_assert_same($contact, $c[$name]['cf']['contact_field'] ?? '', "$name fills the contact");
	}
	$json = json_encode($d['widgets']['wholesale_enquiry_form']['tree']);
	pg_assert_contains('{{page:privacy}}', $json, 'the consent links the privacy page');
	pg_assert_contains('"section":"captcha"', $json, 'the CAPTCHA');
	pg_assert_true($form['form_name'] !== '' && !in_array($form['form_name'], array(lang('Cake Orders'), lang('Class Place Requests')), true), 'a form of its own');
	// Every ?range= a link sends is an answer.
	$trees = $d['range_cards'];
	foreach ($d['public']['pages'] as $p) $trees[] = $p['tree'];
	$seen = 0;
	foreach ($trees as $tree) {
		$nodes = array();
		_test_bkr_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			$href = (string)($n['props']['href'] ?? '');
			if (strpos($href, '{{page:wholesale_enquiry}}?') !== 0) continue;
			parse_str(substr($href, strlen('{{page:wholesale_enquiry}}?')), $q);
			pg_assert_same(array('range'), array_keys($q), 'the enquiry link sends the range');
			pg_assert_true(in_array($q['range'], $c['range']['options'], true), "?range={$q['range']} is an answer");
			$seen++;
		}
	}
	pg_assert_true($seen >= count($d['range_cards']), 'every range card opens the enquiry with its range');
}

// The record page carries its messages and mails each one; the page, the
// lists and the e-mails bind fields the form has.
function test_bakery_wholesale_record_and_mail()
{
	$d = _test_bkw_load();
	$comments = $d['pages']['enquiry_view']['comments'];
	pg_assert_true($comments['allow_new'] && $comments['login'], 'messages from the signed-in sender');
	pg_assert_same('{{tab:email_reply}}', $comments['email_page']);
	pg_assert_same('{{site_email}}', $comments['notify_email'], 'the bakery hears of every message');
	$fields = array_keys(_test_bkr_controls($d['widgets']['wholesale_enquiry_form']['tree']));
	foreach (array('enquiry_detail', 'my_enquiries_list', 'staff_enquiries_list', 'email_enquiry_admin', 'email_enquiry_client') as $key) {
		$nodes = array();
		_test_bkr_nodes($d['widgets'][$key]['tree'], $nodes);
		$read = 0;
		foreach ($nodes as $n) {
			foreach (array('text', 'href', 'src') as $prop) {
				$token = (string)($n['props']['_bindings'][$prop] ?? '');
				if ($token === '' || strpos($token, '__') === 0 || in_array($token, array('reference_code', 'not_found', 'number_of_comments', 'form_item_view'), true)) continue;
				pg_assert_true(in_array($token, $fields, true), "$key binds $token, a field of the form");
				$read++;
			}
		}
		pg_assert_true($read >= 3, "$key reads the record");
	}
	foreach ($d['widgets']['staff_enquiries_list']['config']['search_fields'] as $name) {
		pg_assert_true(in_array($name, $fields, true), "the staff list searches $name");
	}
	$json = json_encode($d['widgets']['enquiry_detail']['tree']);
	foreach (array('record_found', 'record_not_found', 'has_new_account') as $flag) pg_assert_contains('"eo_visible_if":"' . $flag . '"', $json, "enquiry_detail: $flag");
	foreach (array('email_enquiry_admin', 'email_enquiry_client') as $key) {
		pg_assert_same('public', $d['widgets'][$key]['config']['access_control'], "$key is drawn for the submission just sent");
		pg_assert_contains('{{page:enquiry_view}}?r=^^reference_code^^', json_encode($d['widgets'][$key]['tree'], JSON_UNESCAPED_SLASHES), "$key opens the record");
	}
	pg_assert_contains('"text":"reference_code"', json_encode($d['widgets']['email_enquiry_admin']['tree']), 'the reference line');
	pg_assert_contains('"eo_visible_if":"has_new_account"', json_encode($d['widgets']['email_enquiry_client']['tree']), 'the new-account note');
}

function test_bakery_wholesale_links_ids_classes_and_dates()
{
	$d = _test_bkw_load();
	$known = array_flip(_test_bk_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_bk_shop_keys() as $key) pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	$nodes = array();
	foreach ($d['pages'] as $p) _test_bkr_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_bkr_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(p[a-z]{2,3}|pf|pm)-/', $c) && strpos($c, 'pbk-') !== 0 && strpos($c, 'pg-') !== 0, "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'bky-') === 0, "id $id is the template's");
	}
	$text = json_encode(array($d['pages'], $d['widgets']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$text = str_replace('www.w3.org/2000/svg', '', $text);
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $text), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $text), 'no month in the sample');
}

// Every literal has its Turkish; wholesale reads "toptan".
function test_bakery_wholesale_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bakery/wholesale.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "wholesale.php: Turkish for '$key'");
		if (!isset($map[$key]) || strpos($key, ' ') === false) continue;
		if (preg_match('/\bwholesale\b/i', $key)) pg_assert_true(mb_stripos($map[$key], 'toptan', 0, 'UTF-8') !== false, "'$key' says toptan");
	}
}
