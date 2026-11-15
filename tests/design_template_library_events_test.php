<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Public Library design template's events and place requests
 * (includes/design_templates/pinegrap-library/events.php and mail.php): the
 * pages and widgets, the Events collection with a sample per event whose
 * values fit their fields, the list's filter reading what the filter row
 * sends, the home lists the featured events, the event page opens the
 * place request on its event and the list on its kind, the place request
 * form with its office-only status, consent and e-mails, the request's
 * page, lists and messages, every page link names a template page, ids
 * and classes are the template's own, and every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_lb_page_keys()).
require_once __DIR__ . '/design_template_library_test.php';

// The events' part as the template file reads it: parts.php and mail.php
// first, then the public pages, catalogue.php and events.php in the same
// scope. Read once per run.
function _test_lbe_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-library';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$public = include $dir . '/pages_public.php';
	include $dir . '/catalogue.php';
	$part = include $dir . '/events.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'events' => $events,
		'kinds' => $event_kind_titles, 'branches' => $branch_titles, 'ages' => $age_groups, 'featured' => $lb_yes_no, 'booking_statuses' => $booking_statuses,
		'public' => $public, 'footer' => $footer());
	return $out;
}

// Every node of a tree, depth first.
function _test_lbe_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_lbe_nodes($c, $out);
}

// The name, options and _cf of every form control of a tree.
function _test_lbe_controls($node)
{
	$nodes = array();
	_test_lbe_nodes($node, $nodes);
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

// The query of every link to {{page:events}} in a tree.
function _test_lbe_queries($tree)
{
	$nodes = array();
	_test_lbe_nodes($tree, $nodes);
	$out = array();
	$prefix = '{{page:events}}?';
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, $prefix) !== 0) continue;
		parse_str(substr($href, strlen($prefix)), $q);
		$out[] = $q;
	}
	return $out;
}

// The __page_link targets of a tree.
function _test_lbe_page_links($tree)
{
	$nodes = array();
	_test_lbe_nodes($tree, $nodes);
	$links = array();
	foreach ($nodes as $n) {
		if (($n['props']['_bindings']['href'] ?? '') === '__page_link') $links[] = $n['props']['_bindParams']['href'];
	}
	return $links;
}

function test_library_events_pages_and_widgets()
{
	$d = _test_lbe_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('events', 'event', 'event_new', 'event_booking', 'booking_view', 'my_bookings', 'staff_bookings', 'privacy',
		'email_booking_new', 'email_booking_received', 'email_reply'), array_keys($d['pages']));
	pg_assert_same(array('event_form', 'events_list', 'home_events', 'event_booking_form', 'booking_detail', 'booking_messages',
		'my_bookings_list', 'staff_bookings_list', 'email_booking_admin', 'email_booking_client', 'email_comment', 'event_detail'), array_keys($d['widgets']));
	$folders = array('events' => 'public', 'event' => 'public', 'event_booking' => 'public', 'privacy' => 'public',
		'booking_view' => 'registration', 'my_bookings' => 'registration',
		'event_new' => 'private', 'staff_bookings' => 'private', 'email_booking_new' => 'private', 'email_booking_received' => 'private', 'email_reply' => 'private');
	foreach ($folders as $key => $folder) pg_assert_same($folder, $d['pages'][$key]['folder'], "$key is $folder");
	foreach (array('event_new', 'email_booking_new', 'email_booking_received', 'email_reply') as $key) {
		pg_assert_true(!empty($d['pages'][$key]['noindex']), "$key is not indexed");
	}
	foreach ($d['pages'] as $key => $p) {
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true($w['page'] === 'home' || isset($d['pages'][$w['page']]), "$key sits on a page of this part or the home");
		if ($w['page'] !== 'home') {
			pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
		}
	}
	$home = null;
	foreach ($d['public']['pages'] as $p) if ($p['key'] === 'home') $home = $p;
	pg_assert_contains('"templateWidget":"home_events"', json_encode($home['tree']), 'the home places the featured events');
}

// The form is a collection with a sample per event; every sample value has
// its field and is filled, a pick list's value is one of its answers.
function test_library_events_form_and_samples()
{
	$d = _test_lbe_load();
	$form = $d['pages']['event_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same(count($d['events']), count($form['sample_records']), 'one sample per event');
	$controls = _test_lbe_controls($d['widgets']['event_form']['tree']);
	$fields = array('title', 'kind', 'branch', 'when', 'age_group', 'summary', 'details', 'photo_1', 'featured');
	pg_assert_same($fields, array_keys($controls), 'the fields of the form');
	pg_assert_same('image', $controls['photo_1']['cf']['kind'] ?? '', 'the photo is an image');
	pg_assert_true(isset($controls[$form['share_image_field']]), 'the share image is a field');
	pg_assert_same($d['kinds'], $controls['kind']['options'], 'the six kinds');
	pg_assert_same($d['branches'], $controls['branch']['options'], 'the three branches');
	pg_assert_same($d['ages'], $controls['age_group']['options'], 'the age groups');
	pg_assert_true(!empty($controls['featured']['cf']['office_use_only']), 'Featured is office use only');
	$kinds = array();
	$featured = 0;
	foreach ($form['sample_records'] as $n => $r) {
		pg_assert_same($fields, array_keys($r), "sample $n: every field");
		foreach ($r as $name => $value) {
			pg_assert_true($value !== '', "sample $n: $name filled");
			if ($controls[$name]['tag'] === 'select') {
				pg_assert_true(in_array($value, $controls[$name]['options'], true), "sample $n: $name '$value' is an answer");
			}
			pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $value), "sample $n: $name names no year");
		}
		$key = array_keys($d['events'])[$n];
		pg_assert_same(implode("\n", $d['events'][$key]['details']), $r['details'], "sample $n: its details, a line per item");
		$kinds[$r['kind']] = true;
		if ($r['featured'] === $d['featured'][0]) $featured++;
	}
	pg_assert_same(count($d['kinds']), count($kinds), 'a sample of every kind');
	pg_assert_true($featured >= 3, 'enough featured samples for the home');
}

// The list reads the parameter the filter row sends, each an answer of the
// Kind list; the home lists the featured events.
function test_library_events_filters()
{
	$d = _test_lbe_load();
	$params = array();
	foreach ($d['widgets']['events_list']['config']['filters'] as $f) {
		pg_assert_same('url parameter', $f['dynamic_value'], 'the filter reads the address');
		$params[$f['field']] = $f['dynamic_value_attribute'];
	}
	pg_assert_same(array('kind' => 'kind'), $params, 'the list filters the kind');
	$trees = array($d['pages']['events']['tree'], $d['footer']);
	foreach ($d['public']['pages'] as $p) $trees[] = $p['tree'];
	foreach ($trees as $tree) {
		foreach (_test_lbe_queries($tree) as $q) {
			pg_assert_same(array('kind'), array_keys($q), 'only ?kind=');
			pg_assert_true(in_array($q['kind'], $d['kinds'], true), "?kind={$q['kind']} is an answer");
		}
	}
	$row = array();
	foreach (_test_lbe_queries($d['pages']['events']['tree']) as $q) $row[] = $q['kind'];
	pg_assert_same($d['kinds'], $row, 'a kind link each in the filter row');
	$c = $d['widgets']['home_events']['config'];
	pg_assert_same(array(array('field' => 'featured', 'operator' => 'is equal to', 'value' => $d['featured'][0], 'dynamic_value' => '')), $c['filters'], 'the featured ones');
	pg_assert_same(3, $c['max_results'], 'three at most');
	foreach (array('events_list', 'home_events') as $key) {
		pg_assert_same('{{tab:event_new}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key: the Events form");
		pg_assert_same('{{tab:event}}', $d['widgets'][$key]['config']['detail_page_id'], "$key: the event page");
	}
}

// The event page shows the record or the not-found note, opens the place
// request on its event and the list on its kind.
function test_library_events_record_page()
{
	$d = _test_lbe_load();
	$tree = $d['widgets']['event_detail']['tree'];
	pg_assert_same(array(
		array('page_id' => '{{tab:event_booking}}', 'params' => array(array('name' => 'event', 'token' => 'title'))),
		array('page_id' => '{{tab:events}}', 'params' => array(array('name' => 'kind', 'token' => 'kind'))),
	), _test_lbe_page_links($tree), 'the request on its event, the list on its kind');
	$nodes = array();
	_test_lbe_nodes($tree, $nodes);
	$bound = array();
	foreach ($nodes as $n) foreach (array('text', 'src') as $k) if (isset($n['props']['_bindings'][$k])) $bound[] = $n['props']['_bindings'][$k];
	foreach (array('title', 'kind', 'branch', 'when', 'age_group', 'summary', 'details', 'photo_1') as $f) {
		pg_assert_true(in_array($f, $bound, true), "the page shows $f");
	}
	$json = json_encode($tree);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the record');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
	pg_assert_contains('"eo_visible_if":"can_edit"', $json, 'the Edit button');
}

// The place request: the event from ?event=, the places, the contact
// fields, an office-only status starting at the first state, a consent
// linking the privacy page; it opens an account and the request's page and
// mails both sides.
function test_library_events_place_request()
{
	$d = _test_lbe_load();
	$page = $d['pages']['event_booking'];
	$controls = _test_lbe_controls($d['widgets']['event_booking_form']['tree']);
	pg_assert_same(array('event', 'places', 'notes', 'first_name', 'last_name', 'email', 'phone', 'reply_by', 'status', 'consent'), array_keys($controls), 'the fields of the request');
	pg_assert_same('event', $controls['event']['cf']['url_param'] ?? '', 'the event from ?event=');
	pg_assert_same('input', $controls['event']['tag'], 'any event title is taken');
	pg_assert_true($controls['event']['required'] && $controls['places']['required'] && $controls['consent']['required'], 'event, places and consent are required');
	pg_assert_same($d['booking_statuses'], $controls['status']['options'], 'the states of a request');
	pg_assert_true(!empty($controls['status']['cf']['office_use_only']), 'the status is the library\'s');
	pg_assert_same('email_address', $controls['email']['cf']['contact_field'] ?? '', 'the e-mail fills the contact');
	$json = json_encode($d['widgets']['event_booking_form']['tree']);
	pg_assert_contains('{{page:privacy}}', $json, 'the consent links the privacy page');
	pg_assert_contains('"section":"captcha"', $json, 'a CAPTCHA');
	$f = $page['form'];
	pg_assert_same(1, $f['auto_registration'], 'an account for a new reader');
	pg_assert_same('{{tab:booking_view}}', $f['confirmation_page_id'], 'the request\'s page after sending');
	pg_assert_same('{{tab:email_booking_new}}', $f['notify_page_id'], 'the library\'s e-mail');
	pg_assert_same('{{tab:email_booking_received}}', $f['confirm_page_id'], 'the reader\'s e-mail');
	pg_assert_contains('^^event^^', $f['notify_subject'], 'the subject names the event');
	foreach (array('email_booking_admin', 'email_booking_client') as $key) {
		pg_assert_same('{{tab:event_booking}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key reads a request");
		pg_assert_contains('{{page:booking_view}}?r=^^reference_code^^', json_encode($d['widgets'][$key]['tree']), "$key opens the request");
	}
	pg_assert_contains('"eo_visible_if":"has_new_account"', json_encode($d['widgets']['email_booking_client']['tree']), 'the reader learns of the new account');
}

// The request's page is the sender's own, with its messages; My Bookings
// lists the reader's own, the staff list searches every request.
function test_library_events_request_pages()
{
	$d = _test_lbe_load();
	$view = $d['pages']['booking_view'];
	pg_assert_same('submitter_only', $d['widgets']['booking_detail']['config']['access_control'], 'the sender\'s own');
	pg_assert_same('{{tab:event_booking}}', $d['widgets']['booking_detail']['config']['custom_form_page_id'], 'a place request');
	pg_assert_same('{{tab:email_reply}}', $view['comments']['email_page'], 'answers are mailed');
	pg_assert_same('{{site_email}}', $view['comments']['notify_email'], 'the library hears of each message');
	pg_assert_same('comments', $d['widgets']['booking_messages']['config']['regionType'], 'the messages');
	$mine = $d['widgets']['my_bookings_list']['config'];
	pg_assert_true(!empty($mine['viewer_filter']) && !empty($mine['viewer_filter_submitter']), 'My Bookings shows the reader\'s own');
	$staff = $d['widgets']['staff_bookings_list']['config'];
	pg_assert_true(!empty($staff['search_enabled']), 'the staff search');
	pg_assert_false(isset($staff['viewer_filter']), 'the staff see every request');
	foreach (array('my_bookings_list', 'staff_bookings_list') as $key) {
		pg_assert_same('{{tab:booking_view}}', $d['widgets'][$key]['config']['detail_page_id'], "$key opens the request");
	}
	pg_assert_same('comments', $d['widgets']['email_comment']['config']['regionType'], 'the reply e-mail draws the comment');
	pg_assert_contains('"regionType":"system"', json_encode($d['pages']['email_reply']['tree']), 'the fixed comment for other e-mails');
}

function test_library_events_links_ids_and_classes()
{
	$d = _test_lbe_load();
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
	foreach ($d['pages'] as $p) _test_lbe_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_lbe_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(ptv|pht|pre|pev|pgy|pch|plw|pwd|pph|pcn|pmu|psc|psh|pmz)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'lib-') === 0, "id $id is the template's");
	}
	// The e-mails keep the template's colour when the stylesheet is dropped.
	$mail = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-library/mail.php');
	pg_assert_contains('var(--bs-primary,#3f6212)', $mail, 'the forest green fallback');
}

// Every literal has its Turkish; an event reads "etkinlik".
function test_library_events_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('events.php', 'mail.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-library/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
			if (!isset($map[$key]) || strpos($key, '-') !== false) continue;
			if (preg_match('/\bEvents?\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'etkinli', 0, 'UTF-8') !== false, "'$key' says etkinlik");
		}
	}
}
