<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Public Library design template's room booking requests
 * (includes/design_templates/pinegrap-library/rooms.php): the pages and
 * widgets, the request form with the room read from ?room= as every room
 * card sends it, a date not before today, an office-only status, consent
 * and e-mails, the request's page, lists and messages, every page link
 * names a template page, ids and classes are the template's own, and
 * every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The parts' helpers: _test_lb_nodes() and _test_lb_page_keys().
require_once __DIR__ . '/design_template_library_test.php';

// The part as the template file reads it: parts.php and mail.php first,
// then the public pages, catalogue.php, events.php, membership.php and
// rooms.php in the same scope. Read once per run.
function _test_lbr_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-library';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$public = include $dir . '/pages_public.php';
	$catalogue = include $dir . '/catalogue.php';
	$events_part = include $dir . '/events.php';
	$membership = include $dir . '/membership.php';
	$part = include $dir . '/rooms.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$names = array();
	foreach (array($public, $catalogue, $events_part, $membership) as $other) foreach ($other['pages'] as $p) $names[$p['name']] = true;
	$room_cards = array();
	foreach (array_keys($rooms) as $key) $room_cards[] = $room_card($key);
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'other_names' => $names,
		'public' => $public, 'room_cards' => $room_cards, 'rooms' => $room_titles, 'groups' => $group_sizes,
		'times' => $time_choices, 'statuses' => $room_statuses);
	return $out;
}

// The name, options, _cf and required flag of every form control of a tree.
function _test_lbr_controls($node)
{
	$nodes = array();
	_test_lb_nodes($node, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$tag = $n['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$name = '';
		$required = false;
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) {
			if ($a['name'] === 'name') $name = $a['value'];
			if ($a['name'] === 'required') $required = true;
		}
		$options = array();
		foreach ((array)($n['children'] ?? array()) as $o) {
			foreach ((array)($o['props']['_attrs'] ?? array()) as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $options[] = $a['value'];
			}
		}
		if (isset($out[$name])) continue;
		$out[$name] = array('tag' => $tag, 'options' => $options, 'cf' => $n['props']['_cf'] ?? array(), 'required' => $required);
	}
	return $out;
}

function test_library_rooms_pages_and_widgets()
{
	$d = _test_lbr_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('room_booking', 'room_booking_view', 'my_room_bookings', 'staff_room_bookings', 'email_room_booking_new', 'email_room_booking_received'), array_keys($d['pages']));
	pg_assert_same(array('room_booking_form', 'room_booking_detail', 'room_booking_messages', 'my_room_bookings_list', 'staff_room_bookings_list',
		'email_room_booking_admin', 'email_room_booking_client'), array_keys($d['widgets']));
	$folders = array('room_booking' => 'public', 'room_booking_view' => 'registration', 'my_room_bookings' => 'registration',
		'staff_room_bookings' => 'private', 'email_room_booking_new' => 'private', 'email_room_booking_received' => 'private');
	foreach ($folders as $key => $folder) pg_assert_same($folder, $d['pages'][$key]['folder'], "$key is $folder");
	$names = $d['other_names'];
	foreach ($d['pages'] as $key => $p) {
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the address is a slug");
		pg_assert_false(isset($names[$p['name']]), "$key: its address is its own");
		$names[$p['name']] = true;
		if (strpos($key, 'email_') === 0) pg_assert_true(!empty($p['noindex']), "$key is not indexed");
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of this part");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
}

// The request form: its fields, the answers of parts.php, a date not before
// today, an office-only status, a consent linking the privacy page, a
// CAPTCHA; it opens an account and the request's page and mails both sides.
function test_library_rooms_request_form()
{
	$d = _test_lbr_load();
	$controls = _test_lbr_controls($d['widgets']['room_booking_form']['tree']);
	pg_assert_same(array('room', 'group_size', 'purpose', 'preferred_date', 'preferred_time', 'notes', 'first_name', 'last_name', 'email', 'phone', 'reply_by', 'status', 'consent'),
		array_keys($controls), 'the fields of the request');
	pg_assert_same($d['rooms'], $controls['room']['options'], 'the rooms');
	pg_assert_same($d['groups'], $controls['group_size']['options'], 'the group sizes');
	pg_assert_same($d['times'], $controls['preferred_time']['options'], 'the times of day');
	pg_assert_same('today', $controls['preferred_date']['cf']['rules']['min_date'] ?? '', 'no date before today');
	pg_assert_same($d['statuses'], $controls['status']['options'], 'the states of a request');
	pg_assert_true(!empty($controls['status']['cf']['office_use_only']), 'the status is the library\'s');
	foreach (array('room', 'purpose', 'consent') as $f) pg_assert_true($controls[$f]['required'], "$f is required");
	pg_assert_same('email_address', $controls['email']['cf']['contact_field'] ?? '', 'the e-mail fills the contact');
	$json = json_encode($d['widgets']['room_booking_form']['tree']);
	pg_assert_contains('{{page:privacy}}', $json, 'the consent links the privacy page');
	pg_assert_contains('"section":"captcha"', $json, 'a CAPTCHA');
	$f = $d['pages']['room_booking']['form'];
	pg_assert_same(1, $f['auto_registration'], 'an account for a new reader');
	pg_assert_same('{{tab:room_booking_view}}', $f['confirmation_page_id'], 'the request\'s page after sending');
	pg_assert_same('{{tab:email_room_booking_new}}', $f['notify_page_id'], 'the library\'s e-mail');
	pg_assert_same('{{tab:email_room_booking_received}}', $f['confirm_page_id'], 'the reader\'s e-mail');
	pg_assert_contains('^^room^^', $f['notify_subject'], 'the subject names the room');
	foreach (array('email_room_booking_admin', 'email_room_booking_client') as $key) {
		pg_assert_same('{{tab:room_booking}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key reads a request");
		pg_assert_contains('{{page:room_booking_view}}?r=^^reference_code^^', json_encode($d['widgets'][$key]['tree']), "$key opens the request");
	}
	pg_assert_contains('"eo_visible_if":"has_new_account"', json_encode($d['widgets']['email_room_booking_client']['tree']), 'the reader learns of the new account');
}

// The room comes from ?room=, and every link that opens the request (the
// room cards, the public pages) sends one of the Room list's answers.
function test_library_rooms_room_links()
{
	$d = _test_lbr_load();
	$controls = _test_lbr_controls($d['widgets']['room_booking_form']['tree']);
	pg_assert_same('room', $controls['room']['cf']['url_param'] ?? '', 'the room from ?room=');
	$trees = $d['room_cards'];
	foreach ($d['public']['pages'] as $p) $trees[] = $p['tree'];
	$seen = array();
	$prefix = '{{page:room_booking}}?';
	foreach ($trees as $tree) {
		$nodes = array();
		_test_lb_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			$href = (string)($n['props']['href'] ?? '');
			if (strpos($href, $prefix) !== 0) continue;
			parse_str(substr($href, strlen($prefix)), $q);
			pg_assert_same(array('room'), array_keys($q), 'only ?room=');
			pg_assert_true(in_array($q['room'], $d['rooms'], true), "?room={$q['room']} is an answer");
			$seen[$q['room']] = true;
		}
	}
	pg_assert_same(count($d['rooms']), count($seen), 'every room can be asked for');
}

// The request's page is the sender's own, with its messages; My Room
// Bookings lists the reader's own, the staff list searches every request.
function test_library_rooms_request_pages()
{
	$d = _test_lbr_load();
	$view = $d['pages']['room_booking_view'];
	pg_assert_same('submitter_only', $d['widgets']['room_booking_detail']['config']['access_control'], 'the sender\'s own');
	pg_assert_same('{{tab:room_booking}}', $d['widgets']['room_booking_detail']['config']['custom_form_page_id'], 'a room booking request');
	pg_assert_same('{{tab:email_reply}}', $view['comments']['email_page'], 'answers are mailed');
	pg_assert_same('{{site_email}}', $view['comments']['notify_email'], 'the library hears of each message');
	pg_assert_same('comments', $d['widgets']['room_booking_messages']['config']['regionType'], 'the messages');
	$json = json_encode($d['widgets']['room_booking_detail']['tree']);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the record');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
	$mine = $d['widgets']['my_room_bookings_list']['config'];
	pg_assert_true(!empty($mine['viewer_filter']) && !empty($mine['viewer_filter_submitter']), 'My Room Bookings shows the reader\'s own');
	$staff = $d['widgets']['staff_room_bookings_list']['config'];
	pg_assert_true(!empty($staff['search_enabled']), 'the staff search');
	pg_assert_false(isset($staff['viewer_filter']), 'the staff see every request');
	foreach (array('my_room_bookings_list', 'staff_room_bookings_list') as $key) {
		pg_assert_same('{{tab:room_booking_view}}', $d['widgets'][$key]['config']['detail_page_id'], "$key opens the request");
	}
}

function test_library_rooms_links_ids_and_classes()
{
	$d = _test_lbr_load();
	$known = array_flip(_test_lb_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$nodes = array();
	foreach ($d['pages'] as $p) _test_lb_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_lb_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(ptv|pht|pre|pev|pgy|pch|plw|pwd|pph|pcn|pmu|psc|psh|pmz)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'lib-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a room reads "salon" or "oda".
function test_library_rooms_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-library/rooms.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || strpos($key, '-') !== false) continue;
		if (preg_match('/\bRooms?\b/', $key)) {
			pg_assert_true(mb_stripos($map[$key], 'salon', 0, 'UTF-8') !== false || mb_stripos($map[$key], 'oda', 0, 'UTF-8') !== false, "'$key' says salon or oda");
		}
	}
}
