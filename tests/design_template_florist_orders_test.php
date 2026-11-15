<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Florist design template's e-mail builders and order
 * requests (includes/design_templates/pinegrap-florist/mail.php and
 * orders.php): the pages and widgets, the order form reading what the
 * bouquet and occasion cards, the footer and the bouquets page send
 * (?bouquet=, ?occasion=), a delivery date never in the past, the
 * office-only status, the request's page and lists binding fields of the
 * form, the e-mail pages kept out of the search, every page link naming a
 * template page and none a shop page, ids and classes the template's own,
 * and every literal with its Turkish.
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
require_once(__DIR__ . '/design_template_florist_test.php');

// The orders' part as the template file reads it: parts.php, mail.php,
// then orders.php in the same scope. Read once per run.
function _test_flo_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-florist';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$part = include $dir . '/orders.php';
	$public = include $dir . '/pages_public.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$senders = array($footer());
	foreach (array_keys($bouquets) as $key) $senders[] = $bouquet_card($key);
	foreach (array_keys($occasions) as $key) $senders[] = $occasion_card($key);
	foreach ($public['pages'] as $p) $senders[] = $p['tree'];
	$out = array('pages' => $pages, 'widgets' => $part['widgets'], 'senders' => $senders,
		'bouquet_choices' => $bouquet_choices, 'occasion_choices' => $occasion_choices, 'size_titles' => $size_titles,
		'slot_titles' => $slot_titles, 'zone_titles' => $zone_titles, 'card_choices' => $card_choices,
		'statuses' => $request_statuses, 'shared' => array($email_header_tree(), $email_footer_tree()));
	return $out;
}

function _test_flo_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_flo_nodes($c, $out);
}

// The name, values and settings of every form control of a tree; radios
// of one name gather their values.
function _test_flo_controls($node)
{
	$nodes = array();
	_test_flo_nodes($node, $nodes);
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

function test_florist_orders_pages_and_widgets()
{
	$d = _test_flo_load();
	pg_assert_same(array('order_request', 'request_view', 'my_requests', 'staff_requests', 'privacy', 'email_request_new', 'email_request_received', 'email_reply'), array_keys($d['pages']));
	pg_assert_same(array('request_form', 'request_detail', 'request_messages', 'my_requests_list', 'staff_requests_list', 'email_request_admin', 'email_request_client', 'email_comment'), array_keys($d['widgets']));
	foreach (array('order_request', 'privacy') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	pg_assert_same('registration', $d['pages']['request_view']['folder'], 'a request is read signed in');
	pg_assert_same('registration', $d['pages']['my_requests']['folder']);
	pg_assert_same('private', $d['pages']['staff_requests']['folder'], 'only the staff list every request');
	foreach (array('email_request_new', 'email_request_received', 'email_reply') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "$key is private");
		pg_assert_true(!empty($d['pages'][$key]['noindex']) && $d['pages'][$key]['search'] === false, "$key is out of the search");
	}
	pg_assert_same(lang('privacy-policy'), $d['pages']['privacy']['name'], 'the consent boxes find the privacy page');
	$known = array_flip(_test_fl_page_keys());
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a page of the plan");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of this part");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	pg_assert_same('submitter_only', $d['widgets']['request_detail']['config']['access_control'], 'a request is its sender\'s');
	pg_assert_true(!empty($d['widgets']['my_requests_list']['config']['viewer_filter_submitter']), 'My Requests lists the own requests');
	pg_assert_true(!empty($d['widgets']['staff_requests_list']['config']['search_enabled']), 'the staff search');
}

// The form opens the request's page and mails both sides; its bouquet and
// occasion start with what a link sends, the delivery date is never in
// the past and the status is the florist's.
function test_florist_orders_form()
{
	$d = _test_flo_load();
	$form = $d['pages']['order_request']['form'];
	pg_assert_same('{{tab:request_view}}', $form['confirmation_page_id']);
	pg_assert_same(1, $form['auto_registration']);
	pg_assert_same('{{tab:email_request_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_request_received}}', $form['confirm_page_id']);
	$c = _test_flo_controls($d['widgets']['request_form']['tree']);
	foreach (array('bouquet', 'occasion', 'size', 'delivery_date', 'delivery_time', 'zone', 'recipient_name', 'recipient_phone', 'address',
		'card', 'card_message', 'notes', 'first_name', 'last_name', 'email', 'phone', 'status', 'consent') as $name) {
		pg_assert_true(isset($c[$name]), "the form has $name");
	}
	preg_match_all('/\^\^([a-z_]+)\^\^/', $form['notify_subject'], $m);
	pg_assert_true(count($m[1]) > 0, 'the subject names the request');
	foreach ($m[1] as $name) pg_assert_true(isset($c[$name]), "the subject reads $name, a field of the form");
	foreach (array('bouquet' => 'bouquet_choices', 'occasion' => 'occasion_choices') as $name => $list) {
		pg_assert_same($d[$list], $c[$name]['options'], "the {$name}s");
		pg_assert_same($name, $c[$name]['cf']['url_param'] ?? '', "$name reads ?$name=");
	}
	pg_assert_same($d['size_titles'], $c['size']['options'], 'the sizes');
	pg_assert_same($d['slot_titles'], $c['delivery_time']['options'], 'the delivery times');
	pg_assert_same($d['zone_titles'], $c['zone']['options'], 'the delivery areas');
	$cards = array();
	foreach ($d['card_choices'] as $o) $cards[] = $o[0];
	pg_assert_same($cards, $c['card']['options'], 'the card choices');
	pg_assert_same('today', $c['delivery_date']['cf']['rules']['min_date'] ?? '', 'no delivery date in the past');
	pg_assert_true(isset($c['delivery_date']['attrs']['required']), 'a request names its day');
	pg_assert_same($d['statuses'], $c['status']['options'], 'the statuses');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the florist\'s');
	// Every choice a card, the footer or a public page sends is an answer
	// of its pick list.
	$seen = 0;
	foreach ($d['senders'] as $tree) {
		$nodes = array();
		_test_flo_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			$href = (string)($n['props']['href'] ?? '');
			if (strpos($href, '{{page:order_request}}?') !== 0) continue;
			parse_str(substr($href, strlen('{{page:order_request}}?')), $q);
			foreach ($q as $name => $value) {
				pg_assert_true(isset($c[$name]['cf']['url_param']), "?$name= is read by the form");
				pg_assert_true(in_array($value, $c[$name]['options'], true), "?$name=$value is an answer");
			}
			$seen++;
		}
	}
	pg_assert_true($seen > 0, 'links open the request with a choice made');
}

// The request's page carries its messages and mails each one; the e-mails
// and lists bind fields the form has.
function test_florist_orders_record_and_mail()
{
	$d = _test_flo_load();
	$comments = $d['pages']['request_view']['comments'];
	pg_assert_true($comments['allow_new'] && $comments['login'], 'messages from the signed-in sender');
	pg_assert_same('{{tab:email_reply}}', $comments['email_page']);
	$fields = array_keys(_test_flo_controls($d['widgets']['request_form']['tree']));
	foreach (array('request_detail', 'my_requests_list', 'staff_requests_list', 'email_request_admin', 'email_request_client') as $key) {
		$nodes = array();
		_test_flo_nodes($d['widgets'][$key]['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (array('text', 'href') as $slot) {
				$token = (string)($n['props']['_bindings'][$slot] ?? '');
				if ($token === '' || strpos($token, '__') === 0 || in_array($token, array('reference_code', 'not_found', 'number_of_comments', 'form_item_view'), true)) continue;
				pg_assert_true(in_array($token, $fields, true), "$key binds $token, a field of the form");
			}
		}
	}
	foreach ($d['widgets']['staff_requests_list']['config']['search_fields'] as $name) {
		pg_assert_true(in_array($name, $fields, true), "the staff search reads $name");
	}
	$json = json_encode($d['widgets']['request_detail']['tree']);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the record');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
	pg_assert_contains('"regionType":"system"', json_encode($d['pages']['email_reply']['tree']), 'the reply e-mail keeps the fixed comment');
}

function test_florist_orders_links_ids_and_classes()
{
	$d = _test_flo_load();
	$known = array_flip(_test_fl_page_keys());
	$trees = $d['shared'];
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_fl_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$sets = array();
	foreach ($d['pages'] as $key => $p) $sets[$key] = $p['tree'];
	foreach ($d['widgets'] as $key => $w) $sets[$key] = $w['tree'];
	foreach ($sets as $key => $tree) {
		$nodes = array();
		_test_flo_nodes($tree, $nodes);
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

// Every literal has its Turkish; an order request reads "sipariş talebi",
// a bouquet "buket" and the card message "kart mesajı". Page names are
// address words.
function test_florist_orders_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('mail.php', 'orders.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-florist/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (!isset($map[$key]) || preg_match('/^[a-z-]+$/', $key)) continue;
			$tr = $map[$key];
			$words = preg_replace('/\^\^[a-z_]+\^\^/', '', $key);
			if (preg_match('/\b[Oo]rder [Rr]equests?\b/', $words)) pg_assert_true(mb_stripos($tr, 'sipariş talep', 0, 'UTF-8') !== false || mb_stripos($tr, 'sipariş taleb', 0, 'UTF-8') !== false, "'$key' says sipariş talebi");
			if (preg_match('/\b[Bb]ouquets?\b/', $words)) pg_assert_true(mb_stripos($tr, 'buket', 0, 'UTF-8') !== false, "'$key' says buket");
			if (preg_match('/\b[Cc]ard message\b/', $words)) pg_assert_true(mb_stripos($tr, 'kart mesaj', 0, 'UTF-8') !== false, "'$key' says kart mesajı");
		}
	}
}
