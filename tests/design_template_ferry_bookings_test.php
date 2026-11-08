<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Ferry design template's booking requests and group
 * enquiries (includes/design_templates/pinegrap-ferry/bookings.php and
 * mail.php): the pages and widgets, every widget placed on its page, the
 * booking request reading ?route= and ?fare= with every value the other
 * pages send among its answers (or "All Routes", which leaves the choice
 * open), the group enquiry reading ?route=, office-only statuses, dates
 * not before today, the consent linking the privacy page, the record pages
 * showing only the traveller's own record with its messages, the staff
 * lists filtering what their filter bar sends, the lists and e-mails
 * reading fields of their form, every page link names a template page,
 * ids and classes are the template's own, and every literal has its
 * Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_fy_page_keys()).
require_once(__DIR__ . '/design_template_ferry_test.php');

// The bookings part as the template file reads it: parts.php, the public
// pages, notices.php and mail.php first, then bookings.php in the same
// scope. Read once per run.
function _test_fyb_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-ferry';
	include $dir . '/parts.php';
	$public = include $dir . '/pages_public.php';
	$notices = include $dir . '/notices.php';
	include $dir . '/mail.php';
	$part = include $dir . '/bookings.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'public' => $public, 'notices' => $notices,
		'route_titles' => $route_titles, 'fare_titles' => $fare_titles, 'notice_route_choices' => $notice_route_choices,
		'booking_statuses' => $booking_statuses, 'group_statuses' => $group_statuses, 'group_kind_choices' => $group_kind_choices,
		'shared' => array($header(), $footer(), $booking_band(), $fare_table(), $email_header_tree(), $email_footer_tree()));
	foreach (array_keys($routes) as $key) $out['shared'][] = $timetable($key);
	return $out;
}

// Every node of a tree, depth first.
function _test_fyb_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_fyb_nodes($c, $out);
}

// The type, options and settings of every form control of a tree, by name.
function _test_fyb_controls($node)
{
	$nodes = array();
	_test_fyb_nodes($node, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$tag = $n['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$name = '';
		$type = $tag;
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) {
			if ($a['name'] === 'name') $name = $a['value'];
			if ($a['name'] === 'type') $type = $a['value'];
		}
		$options = array();
		foreach ((array)($n['children'] ?? array()) as $o) {
			foreach ((array)($o['props']['_attrs'] ?? array()) as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $options[] = $a['value'];
			}
		}
		$out[$name] = array('tag' => $tag, 'type' => $type, 'options' => $options, 'cf' => $n['props']['_cf'] ?? array());
	}
	return $out;
}

// The query values of every link to a page key in a tree.
function _test_fyb_queries($tree, $key)
{
	$nodes = array();
	_test_fyb_nodes($tree, $nodes);
	$out = array();
	$prefix = '{{page:' . $key . '}}?';
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, $prefix) !== 0) continue;
		parse_str(substr($href, strlen($prefix)), $q);
		$out[] = $q;
	}
	return $out;
}

function test_ferry_bookings_pages_and_widgets()
{
	$d = _test_fyb_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('booking_request', 'booking_view', 'my_bookings', 'staff_bookings', 'privacy',
		'email_booking_new', 'email_booking_received', 'email_reply',
		'group_enquiry', 'group_view', 'my_groups', 'staff_groups',
		'email_group_new', 'email_group_received'), array_keys($d['pages']));
	$folders = array('booking_request' => 'public', 'privacy' => 'public', 'group_enquiry' => 'public',
		'booking_view' => 'registration', 'my_bookings' => 'registration', 'group_view' => 'registration', 'my_groups' => 'registration',
		'staff_bookings' => 'private', 'staff_groups' => 'private');
	foreach ($folders as $key => $folder) pg_assert_same($folder, $d['pages'][$key]['folder'], "$key: folder");
	$known = array_flip(_test_fy_page_keys());
	$names = array();
	foreach (array_merge($d['public']['pages'], $d['notices']['pages']) as $p) $names[$p['name']] = $p['key'];
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a page of the plan");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the address is a slug");
		pg_assert_false(isset($names[$p['name']]), "$key: its own address");
		$names[$p['name']] = $key;
		if (strpos($key, 'email_') === 0) {
			pg_assert_same('private', $p['folder'], "$key: a private e-mail page");
			pg_assert_true(!empty($p['noindex']) && empty($p['search']) && empty($p['sitemap']), "$key: out of search and sitemap");
		}
	}
	$placed = array();
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of this part");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
		$placed[$w['page']] = true;
	}
	foreach (array_keys($d['pages']) as $key) {
		if ($key === 'privacy') continue;
		pg_assert_true(isset($placed[$key]), "$key carries a widget");
	}
}

// The booking request is a form of the page; it reads ?route= and ?fare=,
// each a pick list whose answers hold every value the template sends, or
// "All Routes" from a notice for every route. Dates never before today,
// the status the booking office's, the consent linking the privacy page.
function test_ferry_bookings_booking_form()
{
	$d = _test_fyb_load();
	$form = $d['pages']['booking_request']['form'];
	pg_assert_same('{{tab:booking_view}}', $form['confirmation_page_id'], 'the request opens its own page');
	pg_assert_same(1, $form['auto_registration'], 'an account for a new traveller');
	pg_assert_same('{{tab:email_booking_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_booking_received}}', $form['confirm_page_id']);
	$c = _test_fyb_controls($d['widgets']['booking_form']['tree']);
	foreach (array('route', 'journey', 'outward_date', 'return_date', 'sailing', 'adults', 'children', 'fare', 'vehicle',
		'registration', 'vehicle_length', 'cabin', 'assistance', 'notes', 'first_name', 'last_name', 'email', 'phone', 'status', 'consent') as $name) {
		pg_assert_true(isset($c[$name]), "the request asks for $name");
	}
	pg_assert_contains('^^route^^', $form['notify_subject'], 'the subject names a field of the form');
	foreach (array('outward_date', 'return_date') as $name) {
		pg_assert_same('date', $c[$name]['type'], "$name is a date");
		pg_assert_same('today', $c[$name]['cf']['rules']['min_date'] ?? '', "$name not before today");
	}
	pg_assert_same('route', $c['route']['cf']['url_param'] ?? '', 'the route reads ?route=');
	pg_assert_same('fare', $c['fare']['cf']['url_param'] ?? '', 'the fare reads ?fare=');
	pg_assert_same($d['route_titles'], $c['route']['options'], 'the routes');
	pg_assert_same($d['fare_titles'], $c['fare']['options'], 'the fares');
	foreach (array('adults', 'children') as $name) pg_assert_same('number', $c[$name]['cf']['kind'] ?? '', "$name is a number");
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the booking office\'s');
	pg_assert_same($d['booking_statuses'], $c['status']['options'], 'the statuses');
	pg_assert_contains('{{page:privacy}}', json_encode($d['widgets']['booking_form']['tree']), 'the consent links the privacy page');
	// Every ?route= and ?fare= the public pages, the shared parts and the
	// timetables send is an answer.
	$trees = $d['shared'];
	foreach ($d['public']['pages'] as $p) $trees[] = $p['tree'];
	$seen = array();
	foreach ($trees as $tree) {
		foreach (_test_fyb_queries($tree, 'booking_request') as $q) {
			foreach ($q as $param => $value) {
				pg_assert_true(in_array($param, array('route', 'fare'), true), "?$param is read by the request");
				pg_assert_true(in_array($value, $c[$param]['options'], true), "?$param=$value is an answer");
				$seen[$param] = true;
			}
		}
	}
	pg_assert_true(isset($seen['route'], $seen['fare']), 'the template sends both parameters');
	// A notice sends its route: a booking route or "All Routes", which is
	// no answer, so the prompt stays chosen.
	pg_assert_contains('{"page_id":"{{tab:booking_request}}","params":[{"name":"route","token":"route"}]}',
		json_encode($d['notices']['widgets']['notice_detail']['tree']), 'the notice opens the request on its route');
	foreach (array_slice($d['notice_route_choices'], 1) as $r) pg_assert_true(in_array($r, $c['route']['options'], true), "$r is a route answer");
	pg_assert_false(in_array($d['notice_route_choices'][0], $c['route']['options'], true), 'All Routes chooses nothing');
}

// The group enquiry reads ?route=; its group size is a number of ten or
// more.
function test_ferry_bookings_group_form()
{
	$d = _test_fyb_load();
	$form = $d['pages']['group_enquiry']['form'];
	pg_assert_same('{{tab:group_view}}', $form['confirmation_page_id']);
	pg_assert_same('{{tab:email_group_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_group_received}}', $form['confirm_page_id']);
	pg_assert_contains('^^route^^', $form['notify_subject']);
	$c = _test_fyb_controls($d['widgets']['group_form']['tree']);
	foreach (array('route', 'travel_date', 'group_size', 'group_kind', 'coach', 'group_name', 'details', 'contact_method',
		'first_name', 'last_name', 'email', 'phone', 'status', 'consent') as $name) {
		pg_assert_true(isset($c[$name]), "the enquiry asks for $name");
	}
	pg_assert_same('today', $c['travel_date']['cf']['rules']['min_date'] ?? '', 'the travel date not before today');
	pg_assert_same('route', $c['route']['cf']['url_param'] ?? '', 'the route reads ?route=');
	pg_assert_same($d['route_titles'], $c['route']['options']);
	pg_assert_same($d['group_kind_choices'], $c['group_kind']['options']);
	pg_assert_same($d['group_statuses'], $c['status']['options']);
	pg_assert_same('number', $c['group_size']['cf']['kind'] ?? '', 'the group size is a number');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the group desk\'s');
	pg_assert_contains('{{page:privacy}}', json_encode($d['widgets']['group_form']['tree']), 'the consent links the privacy page');
}

// The record pages show only the traveller's own record and the messages
// under it; the lists show the traveller's own or, for the staff, every
// one with the route and status from the address and a search; the
// e-mails read the record they are sent for. Every binding reads a field
// of the form or a system token.
function test_ferry_bookings_records_lists_and_mail()
{
	$d = _test_fyb_load();
	$sets = array(
		'booking_request' => array('booking_detail', 'my_bookings_list', 'staff_bookings_list', 'email_booking_admin', 'email_booking_client', 'booking_form', 'booking_view', 'booking_messages', 'staff_bookings', 'booking_statuses'),
		'group_enquiry' => array('group_detail', 'my_groups_list', 'staff_groups_list', 'email_group_admin', 'email_group_client', 'group_form', 'group_view', 'group_messages', 'staff_groups', 'group_statuses'),
	);
	$system = array('form_item_view', 'reference_code', 'number_of_comments', 'submitted_date_and_time', 'not_found',
		'__new_account_email', '__new_account_set_password_url', '__edit_url');
	foreach ($sets as $form_key => $s) {
		list($detail, $mine, $staff, $admin, $client, $form, $view, $messages, $staff_page, $statuses) = $s;
		$fields = array_keys(_test_fyb_controls($d['widgets'][$form]['tree']));
		$detail_widget = $d['widgets'][$detail];
		pg_assert_same('submitter_only', $detail_widget['config']['access_control'], "$detail: the traveller's own");
		pg_assert_same('{{tab:' . $form_key . '}}', $detail_widget['config']['custom_form_page_id']);
		pg_assert_same($view, $detail_widget['page']);
		pg_assert_same('{{tab:email_reply}}', $d['pages'][$view]['comments']['email_page'], "$view: messages are mailed");
		pg_assert_same($view, $d['widgets'][$messages]['page']);
		pg_assert_same('comments', $d['widgets'][$messages]['config']['regionType']);
		pg_assert_true(!empty($d['widgets'][$mine]['config']['viewer_filter_submitter']), "$mine: the traveller's own");
		$sc = $d['widgets'][$staff]['config'];
		pg_assert_true(!empty($sc['search_enabled']), "$staff: a search");
		foreach ($sc['search_fields'] as $f) pg_assert_true(in_array($f, $fields, true), "$staff searches the field $f");
		$params = array();
		foreach ($sc['filters'] as $f) {
			pg_assert_same('url parameter', $f['dynamic_value'], "$staff: the filter reads the address");
			pg_assert_true(in_array($f['field'], $fields, true), "$staff filters the field {$f['field']}");
			$params[$f['field']] = $f['dynamic_value_attribute'];
		}
		pg_assert_same(array('route' => 'route', 'status' => 'status'), $params, "$staff: route and status");
		// The filter bar sends answers of the form's pick lists.
		$c = _test_fyb_controls($d['widgets'][$form]['tree']);
		$sent = 0;
		foreach (_test_fyb_queries($d['pages'][$staff_page]['tree'], $staff_page) as $q) {
			foreach ($q as $param => $value) {
				pg_assert_true(isset($params[$param]), "$staff_page: ?$param is a filter");
				pg_assert_true(in_array($value, $c[$param]['options'], true), "$staff_page: ?$param=$value is an answer");
				$sent++;
			}
		}
		pg_assert_same(count($d['route_titles']) + count($d[$statuses]), $sent, "$staff_page: a link per route and per status");
		foreach (array($mine, $staff) as $list) {
			pg_assert_same('{{tab:' . $form_key . '}}', $d['widgets'][$list]['config']['custom_form_page_id']);
			pg_assert_same('{{tab:' . $view . '}}', $d['widgets'][$list]['config']['detail_page_id']);
			pg_assert_same('DESC', $d['widgets'][$list]['config']['order_by_direction'], "$list: newest first");
		}
		foreach (array($admin, $client) as $mail) pg_assert_same('public', $d['widgets'][$mail]['config']['access_control'], "$mail reads the record sent");
		pg_assert_contains('{{page:' . $view . '}}?r=^^reference_code^^', json_encode($d['widgets'][$admin]['tree'], JSON_UNESCAPED_SLASHES), "$admin opens the record");
		foreach (array($detail, $mine, $staff, $admin, $client) as $key) {
			$nodes = array();
			_test_fyb_nodes($d['widgets'][$key]['tree'], $nodes);
			foreach ($nodes as $n) {
				foreach ((array)($n['props']['_bindings'] ?? array()) as $prop => $token) {
					if ($prop === 'eo_visible_if' || $prop === 'section' || $prop === 'action') continue;
					pg_assert_true(in_array($token, $fields, true) || in_array($token, $system, true), "$key reads the field $token");
				}
			}
		}
	}
	$json = json_encode($d['widgets']['booking_detail']['tree']);
	foreach (array('record_found', 'record_not_found', 'can_edit', 'has_new_account', 'submitted_this_session') as $flag) {
		pg_assert_contains('"eo_visible_if":"' . $flag . '"', $json, "the request page: $flag");
	}
	pg_assert_contains('"regionType":"system"', json_encode($d['pages']['email_reply']['tree']), 'the reply e-mail keeps the fixed comment');
	pg_assert_contains('"templateWidget":"email_comment"', json_encode($d['pages']['email_reply']['tree']), 'the reply e-mail draws the comment');
}

function test_ferry_bookings_links_ids_and_classes()
{
	$d = _test_fyb_load();
	$known = array_flip(_test_fy_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$trees[] = $d['shared'];
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	pg_assert_false((bool)preg_match('/[?&]view=/', json_encode($trees, JSON_UNESCAPED_SLASHES)), 'no ?view= link');
	$nodes = array();
	foreach ($d['pages'] as $p) _test_fyb_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_fyb_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|pmn|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh|pmz|pai|pcp|prt)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'fy-') === 0, "id $id is the template's");
		pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', (string)($n['props']['text'] ?? '')), 'no fixed year in a text');
	}
}

// Every literal of both files has its Turkish; a booking request reads
// "rezervasyon talebi" and a group enquiry "grup talebi".
function test_ferry_bookings_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('bookings.php', 'mail.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-ferry/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (!isset($map[$key])) continue;
			$tr = $map[$key];
			if (preg_match('/^[a-z-]+$/', $key)) continue;
			if (preg_match('/\bbooking requests?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'rezervasyon tale', 0, 'UTF-8') !== false, "'$key' says rezervasyon talebi");
			if (preg_match('/\bgroup enquir(y|ies)\b/i', $key)) pg_assert_true(mb_stripos($tr, 'grup tale', 0, 'UTF-8') !== false, "'$key' says grup talebi");
		}
	}
}
