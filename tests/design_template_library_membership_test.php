<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Public Library design template's library card and
 * reservation requests
 * (includes/design_templates/pinegrap-library/membership.php): the pages
 * and widgets, each request form with its office-only status, consent and
 * e-mails, the proof of address kept private, the reservation's title read
 * from ?title= as the title page sends it, the request pages, lists and
 * messages, every page link names a template page, ids and classes are
 * the template's own, and every literal has its Turkish.
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
// then the public pages, catalogue.php, events.php and membership.php in
// the same scope. Read once per run.
function _test_lbm_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-library';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$public = include $dir . '/pages_public.php';
	$catalogue = include $dir . '/catalogue.php';
	$events_part = include $dir . '/events.php';
	$part = include $dir . '/membership.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$names = array();
	foreach (array($public, $catalogue, $events_part) as $other) foreach ($other['pages'] as $p) $names[$p['name']] = true;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'other_names' => $names,
		'catalogue' => $catalogue, 'branches' => $branch_titles, 'card_kinds' => $card_kinds,
		'membership_statuses' => $membership_statuses, 'reservation_statuses' => $reservation_statuses);
	return $out;
}

// The name, options, _cf and required flag of every form control of a tree.
function _test_lbm_controls($node)
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

// The two requests: form page, view, lists, e-mails and widget keys.
function _test_lbm_requests()
{
	return array(
		'join' => array('view' => 'membership_view', 'mine' => 'my_memberships', 'staff' => 'staff_memberships',
			'new' => 'email_membership_new', 'received' => 'email_membership_received', 'form' => 'membership_form',
			'detail' => 'membership_detail', 'messages' => 'membership_messages', 'mine_list' => 'my_memberships_list',
			'staff_list' => 'staff_memberships_list', 'admin' => 'email_membership_admin', 'client' => 'email_membership_client'),
		'reserve' => array('view' => 'reservation_view', 'mine' => 'my_reservations', 'staff' => 'staff_reservations',
			'new' => 'email_reservation_new', 'received' => 'email_reservation_received', 'form' => 'reservation_form',
			'detail' => 'reservation_detail', 'messages' => 'reservation_messages', 'mine_list' => 'my_reservations_list',
			'staff_list' => 'staff_reservations_list', 'admin' => 'email_reservation_admin', 'client' => 'email_reservation_client'),
	);
}

function test_library_membership_pages_and_widgets()
{
	$d = _test_lbm_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('join', 'membership_view', 'my_memberships', 'staff_memberships', 'reserve', 'reservation_view', 'my_reservations', 'staff_reservations',
		'email_membership_new', 'email_membership_received', 'email_reservation_new', 'email_reservation_received'), array_keys($d['pages']));
	$folders = array('join' => 'public', 'reserve' => 'public', 'membership_view' => 'registration', 'my_memberships' => 'registration',
		'reservation_view' => 'registration', 'my_reservations' => 'registration', 'staff_memberships' => 'private', 'staff_reservations' => 'private',
		'email_membership_new' => 'private', 'email_membership_received' => 'private', 'email_reservation_new' => 'private', 'email_reservation_received' => 'private');
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

// Each request form: an office-only status starting at the first state, a
// consent linking the privacy page, a CAPTCHA, the contact fields; it opens
// an account and the request's page and mails both sides.
function test_library_membership_request_forms()
{
	$d = _test_lbm_load();
	$fields = array(
		'join'    => array('card_kind', 'home_branch', 'first_name', 'last_name', 'email', 'phone', 'birth_date', 'address', 'proof_of_address', 'notes', 'reply_by', 'status', 'consent'),
		'reserve' => array('title', 'card_number', 'pickup_branch', 'notes', 'first_name', 'last_name', 'email', 'phone', 'reply_by', 'status', 'consent'),
	);
	$statuses = array('join' => $d['membership_statuses'], 'reserve' => $d['reservation_statuses']);
	foreach (_test_lbm_requests() as $key => $r) {
		$controls = _test_lbm_controls($d['widgets'][$r['form']]['tree']);
		pg_assert_same($fields[$key], array_keys($controls), "$key: the fields of the request");
		pg_assert_same($statuses[$key], $controls['status']['options'], "$key: the states of a request");
		pg_assert_true(!empty($controls['status']['cf']['office_use_only']), "$key: the status is the library's");
		pg_assert_true($controls['consent']['required'], "$key: consent is required");
		pg_assert_same('email_address', $controls['email']['cf']['contact_field'] ?? '', "$key: the e-mail fills the contact");
		$json = json_encode($d['widgets'][$r['form']]['tree']);
		pg_assert_contains('{{page:privacy}}', $json, "$key: the consent links the privacy page");
		pg_assert_contains('"section":"captcha"', $json, "$key: a CAPTCHA");
		$f = $d['pages'][$key]['form'];
		pg_assert_same(1, $f['auto_registration'], "$key: an account for a new reader");
		pg_assert_same('{{tab:' . $r['view'] . '}}', $f['confirmation_page_id'], "$key: the request's page after sending");
		pg_assert_same('{{tab:' . $r['new'] . '}}', $f['notify_page_id'], "$key: the library's e-mail");
		pg_assert_same('{{tab:' . $r['received'] . '}}', $f['confirm_page_id'], "$key: the reader's e-mail");
		pg_assert_contains('^^', $f['notify_subject'], "$key: the subject names the request");
		foreach (array('admin', 'client') as $w) {
			pg_assert_same('{{tab:' . $key . '}}', $d['widgets'][$r[$w]]['config']['custom_form_page_id'], "{$r[$w]} reads a request");
			pg_assert_contains('{{page:' . $r['view'] . '}}?r=^^reference_code^^', json_encode($d['widgets'][$r[$w]]['tree']), "{$r[$w]} opens the request");
		}
		pg_assert_contains('"eo_visible_if":"has_new_account"', json_encode($d['widgets'][$r['client']]['tree']), "$key: the reader learns of the new account");
	}
}

// The card request: the kinds and branches of parts.php, the proof of
// address in the private folder and shown on the request's page only.
function test_library_membership_card_request()
{
	$d = _test_lbm_load();
	$controls = _test_lbm_controls($d['widgets']['membership_form']['tree']);
	pg_assert_same($d['card_kinds'], $controls['card_kind']['options'], 'the kinds of card');
	pg_assert_same($d['branches'], $controls['home_branch']['options'], 'the branches');
	pg_assert_same('{{folder:private}}', $controls['proof_of_address']['cf']['upload_folder_id'] ?? '', 'the proof of address stays private');
	pg_assert_contains('"href":"proof_of_address"', json_encode($d['widgets']['membership_detail']['tree']), 'the request\'s page opens the proof');
	foreach (array('email_membership_admin', 'email_membership_client') as $key) {
		pg_assert_false(strpos(json_encode($d['widgets'][$key]['tree']), 'proof_of_address') !== false, "$key carries no proof of address");
	}
}

// The reservation reads the title the title page sends (?title=), as text so
// any title is taken; the pick-up branches are the branches.
function test_library_membership_reservation_title()
{
	$d = _test_lbm_load();
	$controls = _test_lbm_controls($d['widgets']['reservation_form']['tree']);
	pg_assert_same('title', $controls['title']['cf']['url_param'] ?? '', 'the title from ?title=');
	pg_assert_same('input', $controls['title']['tag'], 'any title is taken');
	pg_assert_true($controls['title']['required'], 'the title is required');
	pg_assert_same($d['branches'], $controls['pickup_branch']['options'], 'the pick-up branches');
	$nodes = array();
	_test_lb_nodes($d['catalogue']['widgets']['item_detail']['tree'], $nodes);
	$sent = array();
	foreach ($nodes as $n) {
		$p = $n['props']['_bindParams']['href'] ?? null;
		if ($p && $p['page_id'] === '{{tab:reserve}}') foreach ($p['params'] as $q) $sent[] = $q['name'];
	}
	pg_assert_same(array('title'), $sent, 'the title page sends ?title=');
}

// The request's page is the sender's own, with its messages; the reader's
// list shows their own, the staff list searches every request.
function test_library_membership_request_pages()
{
	$d = _test_lbm_load();
	foreach (_test_lbm_requests() as $key => $r) {
		$view = $d['pages'][$r['view']];
		pg_assert_same('submitter_only', $d['widgets'][$r['detail']]['config']['access_control'], "{$r['view']}: the sender's own");
		pg_assert_same('{{tab:' . $key . '}}', $d['widgets'][$r['detail']]['config']['custom_form_page_id'], "{$r['view']}: its form");
		pg_assert_same('{{tab:email_reply}}', $view['comments']['email_page'], "{$r['view']}: answers are mailed");
		pg_assert_same('{{site_email}}', $view['comments']['notify_email'], "{$r['view']}: the library hears of each message");
		pg_assert_same('comments', $d['widgets'][$r['messages']]['config']['regionType'], "{$r['view']}: the messages");
		$json = json_encode($d['widgets'][$r['detail']]['tree']);
		pg_assert_contains('"eo_visible_if":"record_found"', $json, "{$r['view']}: the record");
		pg_assert_contains('"eo_visible_if":"record_not_found"', $json, "{$r['view']}: the not-found note");
		$mine = $d['widgets'][$r['mine_list']]['config'];
		pg_assert_true(!empty($mine['viewer_filter']) && !empty($mine['viewer_filter_submitter']), "{$r['mine']}: the reader's own");
		$staff = $d['widgets'][$r['staff_list']]['config'];
		pg_assert_true(!empty($staff['search_enabled']), "{$r['staff']}: the staff search");
		pg_assert_false(isset($staff['viewer_filter']), "{$r['staff']}: every request");
		foreach (array('mine_list', 'staff_list') as $w) {
			pg_assert_same('{{tab:' . $r['view'] . '}}', $d['widgets'][$r[$w]]['config']['detail_page_id'], "{$r[$w]} opens the request");
			pg_assert_same('{{tab:' . $key . '}}', $d['widgets'][$r[$w]]['config']['custom_form_page_id'], "{$r[$w]} lists its form");
		}
	}
}

function test_library_membership_links_ids_and_classes()
{
	$d = _test_lbm_load();
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

// Every literal has its Turkish; a reservation reads "ayırtma".
function test_library_membership_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-library/membership.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || strpos($key, '-') !== false) continue;
		if (preg_match('/\bReservations?\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'ayırt', 0, 'UTF-8') !== false, "'$key' says ayırtma");
	}
}
