<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Travel Agency design template's e-mail builders and
 * booking requests (includes/design_templates/pinegrap-travel/mail.php and
 * bookings.php): the pages and widgets, the booking form reading what the
 * tour page, the tours table and the deal cards send (?tour=, ?deal=), the
 * office-only status, the request's page and lists, the e-mail pages kept
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

// The page keys of the finished template and the shop's pages.
require_once(__DIR__ . '/design_template_travel_test.php');

// The bookings' part as the template file reads it: parts.php, mail.php,
// then bookings.php in the same scope. Read once per run.
function _test_tvb_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-travel';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$part = include $dir . '/bookings.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$deal_hrefs = array();
	foreach (array_keys($deals) as $key) $deal_hrefs[] = $deal_card($key);
	$out = array('pages' => $pages, 'widgets' => $part['widgets'], 'tour_choices' => $booking_tour_choices,
		'deal_choices' => $deal_choices, 'room_choices' => $room_choices, 'statuses' => $booking_statuses,
		'tours' => $tours, 'deal_cards' => $deal_hrefs,
		'shared' => array($email_header_tree(), $email_footer_tree()));
	return $out;
}

function _test_tvb_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_tvb_nodes($c, $out);
}

// The name, values and settings of every form control of a tree; radios
// of one name gather their values.
function _test_tvb_controls($node)
{
	$nodes = array();
	_test_tvb_nodes($node, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$tag = $n['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$attrs = array();
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) $attrs[$a['name']] = $a['value'];
		$name = $attrs['name'] ?? '';
		$options = array();
		foreach ((array)($n['children'] ?? array()) as $o) {
			foreach ((array)($o['props']['_attrs'] ?? array()) as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $options[] = $a['value'];
			}
		}
		if (($attrs['type'] ?? '') === 'radio') {
			$out[$name]['tag'] = 'radio';
			$out[$name]['options'][] = $attrs['value'];
			continue;
		}
		$out[$name] = array('tag' => $tag, 'options' => $options, 'cf' => $n['props']['_cf'] ?? array(), 'attrs' => $attrs);
	}
	return $out;
}

function test_travel_bookings_pages_and_widgets()
{
	$d = _test_tvb_load();
	pg_assert_same(array('book', 'booking_view', 'my_bookings', 'staff_bookings', 'privacy', 'email_booking_new', 'email_booking_received', 'email_reply'), array_keys($d['pages']));
	pg_assert_same(array('book_form', 'booking_detail', 'booking_messages', 'my_bookings_list', 'staff_bookings_list', 'email_booking_admin', 'email_booking_client', 'email_comment'), array_keys($d['widgets']));
	pg_assert_same('public', $d['pages']['book']['folder']);
	pg_assert_same('public', $d['pages']['privacy']['folder']);
	pg_assert_same('registration', $d['pages']['booking_view']['folder'], 'a request is read signed in');
	pg_assert_same('registration', $d['pages']['my_bookings']['folder']);
	pg_assert_same('private', $d['pages']['staff_bookings']['folder'], 'only the staff list every request');
	foreach (array('email_booking_new', 'email_booking_received', 'email_reply') as $key) {
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
	pg_assert_same('submitter_only', $d['widgets']['booking_detail']['config']['access_control'], 'a request is its sender\'s');
	pg_assert_true(!empty($d['widgets']['my_bookings_list']['config']['viewer_filter_submitter']), 'My Bookings lists the own requests');
	pg_assert_true(!empty($d['widgets']['staff_bookings_list']['config']['search_enabled']), 'the staff search');
}

// The form opens the request's page and mails both sides; its tour and deal
// start with what the links send, the date is never in the past and the
// status is the agency's.
function test_travel_bookings_form()
{
	$d = _test_tvb_load();
	$form = $d['pages']['book']['form'];
	pg_assert_same('{{tab:booking_view}}', $form['confirmation_page_id']);
	pg_assert_same(1, $form['auto_registration']);
	pg_assert_same('{{tab:email_booking_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_booking_received}}', $form['confirm_page_id']);
	$c = _test_tvb_controls($d['widgets']['book_form']['tree']);
	foreach (array('tour', 'departure_date', 'travellers', 'deal', 'room', 'wishes', 'first_name', 'last_name', 'email', 'phone', 'status', 'consent') as $name) {
		pg_assert_true(isset($c[$name]), "the form has $name");
	}
	pg_assert_same($d['tour_choices'], $c['tour']['options'], 'the tours');
	pg_assert_same('tour', $c['tour']['cf']['url_param'] ?? '', 'the tour reads ?tour=');
	pg_assert_same($d['deal_choices'], $c['deal']['options'], 'the deals');
	pg_assert_same('deal', $c['deal']['cf']['url_param'] ?? '', 'the deal reads ?deal=');
	pg_assert_same('today', $c['departure_date']['cf']['rules']['min_date'] ?? '', 'no departure in the past');
	pg_assert_same(array_column($d['room_choices'], 0), $c['room']['options'], 'the rooms');
	pg_assert_same($d['statuses'], $c['status']['options'], 'the statuses');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the agency\'s');
	// Every tour a link may send is an answer: the sample tours by name and
	// the deal cards' titles.
	foreach ($d['tours'] as $key => $t) pg_assert_true(in_array($t['name'], $c['tour']['options'], true), "tour $key is an answer");
	$seen = 0;
	foreach ($d['deal_cards'] as $card) {
		$nodes = array();
		_test_tvb_nodes($card, $nodes);
		foreach ($nodes as $n) {
			$href = (string)($n['props']['href'] ?? '');
			if (strpos($href, '{{page:book}}?') !== 0) continue;
			parse_str(substr($href, strlen('{{page:book}}?')), $q);
			pg_assert_true(in_array($q['deal'] ?? '', $c['deal']['options'], true), "?deal={$q['deal']} is an answer");
			$seen++;
		}
	}
	pg_assert_same(count($d['deal_cards']), $seen, 'every deal card books its deal');
}

// The request's page carries its messages and mails each one; the e-mails
// bind fields the form has.
function test_travel_bookings_record_and_mail()
{
	$d = _test_tvb_load();
	$comments = $d['pages']['booking_view']['comments'];
	pg_assert_true($comments['allow_new'] && $comments['login'], 'messages from the signed-in sender');
	pg_assert_same('{{tab:email_reply}}', $comments['email_page']);
	$fields = array_keys(_test_tvb_controls($d['widgets']['book_form']['tree']));
	foreach (array('booking_detail', 'my_bookings_list', 'staff_bookings_list', 'email_booking_admin', 'email_booking_client') as $key) {
		$nodes = array();
		_test_tvb_nodes($d['widgets'][$key]['tree'], $nodes);
		foreach ($nodes as $n) {
			$token = (string)($n['props']['_bindings']['text'] ?? '');
			if ($token === '' || strpos($token, '__') === 0 || in_array($token, array('reference_code', 'not_found', 'number_of_comments'), true)) continue;
			pg_assert_true(in_array($token, $fields, true), "$key binds $token, a field of the form");
		}
	}
	foreach (array('search_fields') as $f) {
		foreach ($d['widgets']['staff_bookings_list']['config'][$f] as $name) pg_assert_true(in_array($name, $fields, true), "the staff search reads $name");
	}
	$json = json_encode($d['widgets']['booking_detail']['tree']);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the record');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
	pg_assert_contains('"regionType":"system"', json_encode($d['pages']['email_reply']['tree']), 'the reply e-mail keeps the fixed comment');
}

function test_travel_bookings_links_ids_and_classes()
{
	$d = _test_tvb_load();
	$known = array_flip(_test_tv_page_keys());
	$trees = $d['shared'];
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

// Every literal has its Turkish; a tour reads "tur" and a booking
// "rezervasyon".
function test_travel_bookings_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('mail.php', 'bookings.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-travel/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (!isset($map[$key])) continue;
			$tr = $map[$key];
			// A ^^field^^ token is the field's name, not a word.
			$words = preg_replace('/\^\^[a-z_]+\^\^/', '', $key);
			if (preg_match('/\b[Tt]ours?\b/', $words)) pg_assert_true(mb_stripos($tr, 'tur', 0, 'UTF-8') !== false, "'$key' says tur");
			if (preg_match('/\b[Bb]ookings?\b/', $words)) pg_assert_true(mb_stripos($tr, 'rezervasyon', 0, 'UTF-8') !== false, "'$key' says rezervasyon");
			if (preg_match('/\b[Dd]eposits?\b/', $words)) pg_assert_true(mb_stripos($tr, 'kapora', 0, 'UTF-8') !== false, "'$key' says kapora");
		}
	}
}
