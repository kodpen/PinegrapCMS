<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Florist design template's enquiries
 * (includes/design_templates/pinegrap-florist/enquiries.php): the pages
 * and widgets, the event enquiry and the subscription request reading
 * what the event and plan cards and Our Work send (?event=, ?plan=), no
 * date in the past, the office-only statuses, the record pages and lists
 * binding fields of their forms, the sender's lists on My Requests, the
 * consultation page with the booking widget's starter and its link to the
 * enquiry, the e-mail pages kept out of the search, every page link naming
 * a template page and none a shop page, ids the template's own, and every
 * literal with its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template, the shop's pages and the
// control reader of the orders' test.
require_once(__DIR__ . '/design_template_florist_test.php');
require_once(__DIR__ . '/design_template_florist_orders_test.php');

// The enquiries' part as the template file reads it: parts.php, mail.php,
// orders.php, then enquiries.php in the same scope. Read once per run.
function _test_fle_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-florist';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$orders = include $dir . '/orders.php';
	$part = include $dir . '/enquiries.php';
	$public = include $dir . '/pages_public.php';
	$work = include $dir . '/work.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$order_pages = array();
	foreach ($orders['pages'] as $p) $order_pages[$p['key']] = $p;
	$senders = array($footer());
	foreach (array_keys($plans) as $key) $senders[] = $plan_card($key);
	foreach (array_keys($event_services) as $key) $senders[] = $event_card($key);
	foreach (array_merge($public['pages'], $work['pages']) as $p) $senders[] = $p['tree'];
	foreach ($work['widgets'] as $w) $senders[] = $w['tree'];
	$out = array('pages' => $pages, 'widgets' => $part['widgets'], 'order_pages' => $order_pages, 'senders' => $senders,
		'event_choices' => $event_choices, 'plan_titles' => $plan_titles, 'weekday_choices' => $weekday_choices,
		'slot_titles' => $slot_titles, 'zone_titles' => $zone_titles, 'budget_choices' => $budget_choices,
		'enquiry_statuses' => $enquiry_statuses, 'subscription_statuses' => $subscription_statuses);
	return $out;
}

// The forms of this part: the page key, the form widget and the view page.
function _test_fle_forms()
{
	return array(
		'event_enquiry' => array('event_form', 'event_enquiry_view', 'email_event_new', 'email_event_received'),
		'subscribe'     => array('subscribe_form', 'subscription_view', 'email_subscription_new', 'email_subscription_received'),
	);
}

function test_florist_enquiries_pages_and_widgets()
{
	$d = _test_fle_load();
	pg_assert_same(array('event_enquiry', 'event_enquiry_view', 'staff_events', 'subscribe', 'subscription_view', 'consultation',
		'email_event_new', 'email_event_received', 'email_subscription_new', 'email_subscription_received'), array_keys($d['pages']));
	pg_assert_same(array('event_form', 'event_detail', 'event_messages', 'my_events_list', 'staff_events_list', 'consultation_slots',
		'subscribe_form', 'subscription_detail', 'subscription_messages', 'my_subscriptions_list', 'staff_subscriptions_list',
		'email_event_admin', 'email_event_client', 'email_subscription_admin', 'email_subscription_client'), array_keys($d['widgets']));
	foreach (array('event_enquiry', 'subscribe', 'consultation') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	foreach (array('event_enquiry_view', 'subscription_view') as $key) pg_assert_same('registration', $d['pages'][$key]['folder'], "$key is read signed in");
	pg_assert_same('private', $d['pages']['staff_events']['folder'], 'only the staff list every enquiry');
	foreach (array('email_event_new', 'email_event_received', 'email_subscription_new', 'email_subscription_received') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "$key is private");
		pg_assert_true(!empty($d['pages'][$key]['noindex']) && $d['pages'][$key]['search'] === false, "$key is out of the search");
	}
	$known = array_flip(_test_fl_page_keys());
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a page of the plan");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
	// A widget sits on a page of this part, or on My Requests and the
	// staff's order list, which orders.php draws.
	$all = $d['pages'] + $d['order_pages'];
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true(isset($all[$w['page']]), "$key sits on a template page");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($all[$w['page']]['tree']), "$key is placed on its page");
	}
	pg_assert_same('my_requests', $d['widgets']['my_events_list']['page'], 'the sender finds the enquiries on My Requests');
	pg_assert_same('my_requests', $d['widgets']['my_subscriptions_list']['page'], 'and the subscriptions');
	foreach (array('event_detail', 'subscription_detail') as $key) {
		pg_assert_same('submitter_only', $d['widgets'][$key]['config']['access_control'], "$key is its sender's");
	}
	foreach (array('my_events_list', 'my_subscriptions_list') as $key) {
		pg_assert_true(!empty($d['widgets'][$key]['config']['viewer_filter_submitter']), "$key lists the own records");
	}
	foreach (array('staff_events_list', 'staff_subscriptions_list') as $key) {
		pg_assert_true(!empty($d['widgets'][$key]['config']['search_enabled']), "$key: the staff search");
	}
}

// Each form opens its record's page and mails both sides; its widgets read
// the form's page; the subject names fields of the form.
function test_florist_enquiries_forms()
{
	$d = _test_fle_load();
	foreach (_test_fle_forms() as $key => $f) {
		$form = $d['pages'][$key]['form'];
		pg_assert_same('{{tab:' . $f[1] . '}}', $form['confirmation_page_id'], "$key opens its record");
		pg_assert_same(1, $form['auto_registration'], "$key opens an account");
		pg_assert_same('{{tab:' . $f[2] . '}}', $form['notify_page_id'], "$key: the florist's e-mail");
		pg_assert_same('{{tab:' . $f[3] . '}}', $form['confirm_page_id'], "$key: the sender's e-mail");
		$c = _test_flo_controls($d['widgets'][$f[0]]['tree']);
		preg_match_all('/\^\^([a-z_]+)\^\^/', $form['notify_subject'], $m);
		pg_assert_true(count($m[1]) > 0, "$key: the subject names the record");
		foreach ($m[1] as $name) pg_assert_true(isset($c[$name]), "$key: the subject reads $name, a field of the form");
		foreach (array('first_name', 'last_name', 'email', 'phone', 'status', 'consent') as $name) pg_assert_true(isset($c[$name]), "$key has $name");
		foreach ($d['widgets'] as $wkey => $w) {
			$page = (string)($w['config']['custom_form_page_id'] ?? '');
			if ($page === '') continue;
			pg_assert_true(in_array($page, array('{{tab:event_enquiry}}', '{{tab:subscribe}}'), true), "$wkey reads a form of this part");
		}
	}
	$c = _test_flo_controls($d['widgets']['event_form']['tree']);
	pg_assert_same($d['event_choices'], $c['event']['options'], 'the events');
	pg_assert_same('event', $c['event']['cf']['url_param'] ?? '', 'the event reads ?event=');
	pg_assert_same($d['budget_choices'], $c['budget']['options'], 'the budget bands');
	pg_assert_same('today', $c['event_date']['cf']['rules']['min_date'] ?? '', 'no event date in the past');
	pg_assert_false(isset($c['event_date']['attrs']['required']), 'an open date may stay empty');
	pg_assert_same($d['enquiry_statuses'], $c['status']['options'], 'the enquiry statuses');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the enquiry status is the florist\'s');
	$c = _test_flo_controls($d['widgets']['subscribe_form']['tree']);
	pg_assert_same($d['plan_titles'], $c['plan']['options'], 'the plans');
	pg_assert_same('plan', $c['plan']['cf']['url_param'] ?? '', 'the plan reads ?plan=');
	pg_assert_same($d['weekday_choices'], $c['delivery_day']['options'], 'the delivery days');
	pg_assert_same($d['slot_titles'], $c['delivery_time']['options'], 'the delivery times');
	pg_assert_same($d['zone_titles'], $c['zone']['options'], 'the delivery areas');
	pg_assert_same('today', $c['start_date']['cf']['rules']['min_date'] ?? '', 'no first delivery in the past');
	pg_assert_true(isset($c['start_date']['attrs']['required']), 'a request names its first day');
	pg_assert_true(isset($c['address']['attrs']['required']), 'a request names its address');
	pg_assert_same($d['subscription_statuses'], $c['status']['options'], 'the subscription statuses');
	pg_assert_same(count($d['subscription_statuses']), count(array_unique($d['subscription_statuses'])), 'no status twice');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the subscription status is the florist\'s');
}

// Every choice a card, the footer, a public page or Our Work sends is an
// answer of its form's pick list.
function test_florist_enquiries_links_choose_an_answer()
{
	$d = _test_fle_load();
	$forms = array(
		'{{page:event_enquiry}}?' => _test_flo_controls($d['widgets']['event_form']['tree']),
		'{{page:subscribe}}?'     => _test_flo_controls($d['widgets']['subscribe_form']['tree']),
	);
	$seen = array();
	foreach ($d['senders'] as $tree) {
		$nodes = array();
		_test_flo_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			$href = (string)($n['props']['href'] ?? '');
			foreach ($forms as $prefix => $c) {
				if (strpos($href, $prefix) !== 0) continue;
				parse_str(substr($href, strlen($prefix)), $q);
				foreach ($q as $name => $value) {
					pg_assert_true(isset($c[$name]['cf']['url_param']), "?$name= is read by the form");
					// Our Work sends its record's Kind, a token the page fills in.
					if (strpos($value, '^^') === 0) continue;
					pg_assert_true(in_array($value, $c[$name]['options'], true), "?$name=$value is an answer");
				}
				$seen[$prefix] = true;
			}
		}
	}
	pg_assert_same(2, count($seen), 'links open both forms with a choice made');
}

// The record pages carry their messages and mail each one; the record
// widgets, lists and e-mails bind fields their form has.
function test_florist_enquiries_records_and_mail()
{
	$d = _test_fle_load();
	foreach (array('event_enquiry_view', 'subscription_view') as $key) {
		$comments = $d['pages'][$key]['comments'];
		pg_assert_true($comments['allow_new'] && $comments['login'], "$key: messages from the signed-in sender");
		pg_assert_same('{{tab:email_reply}}', $comments['email_page'], "$key: the reply e-mail");
	}
	$fields = array(
		'{{tab:event_enquiry}}' => array_keys(_test_flo_controls($d['widgets']['event_form']['tree'])),
		'{{tab:subscribe}}'     => array_keys(_test_flo_controls($d['widgets']['subscribe_form']['tree'])),
	);
	foreach ($d['widgets'] as $key => $w) {
		$form = (string)($w['config']['custom_form_page_id'] ?? '');
		if ($form === '' || $w['config']['regionType'] === 'custom_form') continue;
		$nodes = array();
		_test_flo_nodes($w['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (array('text', 'href') as $slot) {
				$token = (string)($n['props']['_bindings'][$slot] ?? '');
				if ($token === '' || strpos($token, '__') === 0 || in_array($token, array('reference_code', 'not_found', 'number_of_comments', 'form_item_view'), true)) continue;
				pg_assert_true(in_array($token, $fields[$form], true), "$key binds $token, a field of the form");
			}
		}
		foreach ((array)($w['config']['search_fields'] ?? array()) as $name) {
			pg_assert_true(in_array($name, $fields[$form], true), "$key searches $name");
		}
	}
	foreach (array('event_detail', 'subscription_detail') as $key) {
		$json = json_encode($d['widgets'][$key]['tree']);
		pg_assert_contains('"eo_visible_if":"record_found"', $json, "$key: the record");
		pg_assert_contains('"eo_visible_if":"record_not_found"', $json, "$key: the not-found note");
	}
}

// The consultation places the booking widget's starter on its own page,
// and it and the event enquiry link each other.
function test_florist_enquiries_consultation()
{
	$d = _test_fle_load();
	$w = $d['widgets']['consultation_slots'];
	pg_assert_same('consultation', $w['page']);
	pg_assert_same('booking', $w['config']['regionType']);
	pg_assert_same('starter', $w['tree'], 'the editor\'s starter layout');
	pg_assert_same(1, substr_count(json_encode($d['pages']['consultation']['tree']), '"templateWidget":"consultation_slots"'), 'one booking widget');
	pg_assert_contains('{{page:event_enquiry}}', json_encode($d['pages']['consultation']['tree']), 'the consultation links the enquiry');
	pg_assert_contains('{{page:consultation}}', json_encode($d['pages']['event_enquiry']['tree']), 'the enquiry links the consultation');
}

function test_florist_enquiries_links_and_ids()
{
	$d = _test_fle_load();
	$known = array_flip(_test_fl_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_fl_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	foreach ($d['widgets'] as $key => $w) {
		if (!is_array($w['tree'])) continue;
		$nodes = array();
		_test_flo_nodes($w['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pwd|pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph)-/', $c), "$key: class $c is not another template's");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '' || strpos($id, '-') === false) continue;
			pg_assert_true(strpos($id, 'fl-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$id]), "$key: id $id once");
			$ids[$id] = true;
		}
	}
}

// Every literal has its Turkish; a subscription reads "abonelik", an
// event "etkinlik" and a consultation "görüşme". Page names are address
// words.
function test_florist_enquiries_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-florist/enquiries.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || preg_match('/^[a-z-]+$/', $key)) continue;
		$tr = $map[$key];
		$words = preg_replace('/\^\^[a-z_]+\^\^/', '', $key);
		if (preg_match('/\b[Ss]ubscriptions?\b/', $words)) pg_assert_true(mb_stripos($tr, 'abonel', 0, 'UTF-8') !== false, "'$key' says abonelik");
		if (preg_match('/\b[Ee]vent enquir/', $words)) pg_assert_true(mb_stripos($tr, 'etkinlik', 0, 'UTF-8') !== false, "'$key' says etkinlik");
		if (preg_match('/\b[Cc]onsultation\b/', $words)) pg_assert_true(mb_stripos($tr, 'görüşme', 0, 'UTF-8') !== false, "'$key' says görüşme");
	}
}
