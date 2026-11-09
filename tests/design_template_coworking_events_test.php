<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Coworking design template's events and event sign-ups
 * (includes/design_templates/pinegrap-coworking/events.php): the pages in
 * their folders, the Events collection and its sample records dated from
 * today on a staffed day, both lists keeping the coming events only, the
 * cards and the event page reading only fields of the form, the sign-up
 * form starting with ?event= and its record page, lists and e-mails
 * reading only its fields, links to template pages, the template's ids
 * and classes, and every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// parts.php, mail.php and the parts before the events in one scope, as
// the template file reads them. Read once per run.
function _test_cwe_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-coworking/parts.php';
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-coworking/mail.php';
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-coworking/pages_public.php';
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-coworking/spaces.php';
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-coworking/requests.php';
	$event_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-coworking/events.php';
	$out = array('part' => $event_part, 'events' => $events, 'event_titles' => $event_titles,
		'topic_titles' => $topic_titles, 'space_names' => $space_names, 'signup_statuses' => $signup_statuses);
	return $out;
}

// Every node of a tree, depth first.
function _test_cwe_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_cwe_nodes($child, $out);
	}
}

function _test_cwe_pages()
{
	$pages = array();
	foreach (_test_cwe_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The fields of a form widget: name => the node.
function _test_cwe_form_fields($widget_key)
{
	$nodes = array();
	_test_cwe_nodes(_test_cwe_load()['part']['widgets'][$widget_key]['tree'], $nodes);
	$fields = array();
	foreach ($nodes as $n) {
		if (!in_array($n['props']['tag'] ?? '', array('input', 'select', 'textarea'), true)) continue;
		foreach ($n['props']['_attrs'] ?? array() as $a) {
			if ($a['name'] === 'name' && !isset($fields[$a['value']])) $fields[$a['value']] = $n;
		}
	}
	return $fields;
}

// The answers of a pick list node.
function _test_cwe_options($node)
{
	$values = array();
	foreach ($node['children'] as $o) {
		foreach ($o['props']['_attrs'] as $a) {
			if ($a['name'] === 'value' && $a['value'] !== '') $values[] = $a['value'];
		}
	}
	return $values;
}

function test_coworking_events_pages_and_folders()
{
	$part = _test_cwe_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	$folders = array(
		'events' => 'public', 'event' => 'public', 'event_new' => 'private', 'event_signup' => 'public',
		'signup_view' => 'registration', 'my_signups' => 'registration', 'staff_signups' => 'private',
		'email_signup_new' => 'private', 'email_signup_received' => 'private',
	);
	$pages = _test_cwe_pages();
	pg_assert_same(array_keys($folders), array_keys($pages), 'the pages');
	foreach ($folders as $key => $folder) {
		pg_assert_same($folder, $pages[$key]['folder'], "$key folder");
		pg_assert_false(isset($pages[$key]['requires']), "$key stands without the shop");
		pg_assert_true(mb_strlen($pages[$key]['meta_description'] ?? '', 'UTF-8') <= 160, "$key: meta description fits");
		if ($folder !== 'public') pg_assert_false($pages[$key]['sitemap'] ?? true, "$key: not in the sitemap");
	}
	pg_assert_false($pages['event']['sitemap'], 'the event page is a record page');
	pg_assert_same('{{tab:email_reply}}', $pages['signup_view']['comments']['email_page'], 'messages are mailed');
}

// The Events collection: its fields, and the sample events with every
// field, dated after today on the weekday their title needs or a weekday.
function test_coworking_events_collection_and_samples()
{
	$d = _test_cwe_load();
	$form = _test_cwe_pages()['event_new']['form'];
	pg_assert_same(1, $form['collection'], 'events are a collection');
	pg_assert_same('photo', $form['share_image_field'], 'the photo is the share image');
	$fields = _test_cwe_form_fields('event_form');
	pg_assert_same(array('title', 'topic', 'place', 'event_date', 'start_time', 'duration', 'price', 'summary', 'details', 'photo'),
		array_keys($fields), 'the event fields');
	pg_assert_same('date', $fields['event_date']['props']['_attrs'][0]['value'], 'the date is a date field');
	pg_assert_same($d['topic_titles'], _test_cwe_options($fields['topic']), 'the topics are the answers');
	pg_assert_same($d['space_names'], _test_cwe_options($fields['place']), 'the spaces are the places');
	pg_assert_same(count($d['events']), count($form['sample_records']), 'one record per sample event');
	$today = date('Y-m-d');
	foreach ($form['sample_records'] as $n => $r) {
		foreach (array_keys($r) as $f) pg_assert_true(isset($fields[$f]), "sample $n: $f is a field");
		pg_assert_true(in_array($r['title'], $d['event_titles'], true), "sample $n: a known title");
		pg_assert_true(in_array($r['topic'], $d['topic_titles'], true), "sample $n: an answer of Topic");
		pg_assert_true(in_array($r['place'], $d['space_names'], true), "sample $n: an answer of Place");
		pg_assert_true((bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $r['event_date']), "sample $n: a date");
		pg_assert_true($r['event_date'] > $today, "sample $n: still to come");
		pg_assert_true((int)date('N', strtotime($r['event_date'])) <= 5, "sample $n: on a weekday");
	}
	$drinks = $form['sample_records'][array_search('drinks', array_keys($d['events']), true)];
	pg_assert_same('5', date('N', strtotime($drinks['event_date'])), 'Friday drinks fall on a Friday');
}

// Both lists keep the coming events only, the nearest first; the event
// list narrows by ?topic=; the cards and the event page read only fields.
function test_coworking_events_lists_and_record()
{
	$w = _test_cwe_load()['part']['widgets'];
	$fields = _test_cwe_form_fields('event_form');
	foreach (array('events_list', 'home_events') as $key) {
		$c = $w[$key]['config'];
		pg_assert_same('{{tab:event_new}}', $c['custom_form_page_id'], "$key: the Events form");
		pg_assert_same('{{tab:event}}', $c['detail_page_id'], "$key: opens the event");
		pg_assert_same(array('event_date', 'ASC'), array($c['order_by_field'], $c['order_by_direction']), "$key: nearest first");
		pg_assert_same(array('field' => 'event_date', 'operator' => 'is greater than or equal to', 'value' => '', 'dynamic_value' => 'current date'),
			$c['filters'][0], "$key: from today on");
	}
	pg_assert_same(3, $w['home_events']['config']['max_results'], 'three on the home');
	pg_assert_same(1, count($w['home_events']['config']['filters']), 'the home is not narrowed');
	$topic = $w['events_list']['config']['filters'][1];
	pg_assert_same(array('topic', 'url parameter', 'topic'), array($topic['field'], $topic['dynamic_value'], $topic['dynamic_value_attribute']), 'narrowed by ?topic=');
	$system = array('form_item_view', 'not_found', '__edit_url', 'can_edit', 'record_found', 'record_not_found', '__page_link');
	foreach (array('events_list', 'home_events', 'event_detail') as $key) {
		$nodes = array();
		_test_cwe_nodes($w[$key]['tree'], $nodes);
		$read = 0;
		foreach ($nodes as $n) {
			foreach ($n['props']['_bindings'] ?? array() as $what => $token) {
				if ($what === 'eo_visible_if' || in_array($token, $system, true)) continue;
				pg_assert_true(isset($fields[$token]), "$key reads $token, a field of the Events form");
				$read++;
			}
			foreach ($n['props']['_bindParams']['href']['params'] ?? array() as $p) {
				pg_assert_true(isset($fields[$p['token']]), "$key sends $p[token], a field");
			}
		}
		pg_assert_true($read >= 5, "$key reads the event");
	}
	pg_assert_same('public', $w['event_detail']['config']['access_control'], 'anybody sees an event');
	$json = json_encode($w['event_detail']['tree']);
	pg_assert_contains('"page_id":"{{tab:event_signup}}","params":[{"name":"event","token":"title"}]', $json, 'Sign Up sends the title as ?event=');
	pg_assert_contains('"page_id":"{{tab:events}}","params":[{"name":"topic","token":"topic"}]', $json, 'more of the topic');
	// The topic links of the events page are answers of Topic.
	preg_match_all('/\{\{page:events\}\}\?topic=([^"]+)/', str_replace('\\/', '/', json_encode(_test_cwe_pages()['events'])), $m);
	pg_assert_same(count(_test_cwe_load()['topic_titles']), count($m[1]), 'one link per topic');
	foreach ($m[1] as $v) pg_assert_true(in_array(rawurldecode(json_decode('"' . $v . '"')), _test_cwe_load()['topic_titles'], true), "topic link $v");
}

// The sign-up: auto-registration, its record page and e-mails, ?event=,
// an office-only status, consent with the privacy link, a CAPTCHA; its
// record page, lists and e-mails read only its fields.
function test_coworking_events_signup()
{
	$form = _test_cwe_pages()['event_signup']['form'];
	pg_assert_same(1, $form['auto_registration'], 'an account for a new sender');
	pg_assert_same('{{tab:signup_view}}', $form['confirmation_page_id'], 'opens its record');
	pg_assert_same('{{tab:email_signup_new}}', $form['notify_page_id'], 'staff e-mail');
	pg_assert_same('{{tab:email_signup_received}}', $form['confirm_page_id'], 'sender e-mail');
	$fields = _test_cwe_form_fields('signup_form');
	pg_assert_same(array('event', 'guests', 'member', 'notes', 'first_name', 'last_name', 'email', 'phone', 'reply_by', 'status', 'consent'),
		array_keys($fields), 'the sign-up fields');
	pg_assert_same('event', $fields['event']['props']['_cf']['url_param'], 'the event starts with ?event=');
	pg_assert_true(!empty($fields['status']['props']['_cf']['office_use_only']), 'status is the front desk\'s');
	pg_assert_same(_test_cwe_load()['signup_statuses'], _test_cwe_options($fields['status']), 'the sign-up states');
	preg_match_all('/\^\^([a-z_]+)\^\^/', $form['notify_subject'], $m);
	foreach ($m[1] as $token) pg_assert_true(isset($fields[$token]), "subject reads $token, a field");
	$w = _test_cwe_load()['part']['widgets'];
	$json = json_encode($w['signup_form']['tree']);
	pg_assert_contains('{{page:privacy}}', $json, 'consent links the privacy page');
	pg_assert_contains('"section":"captcha"', $json, 'CAPTCHA');
	$system = array('form_item_view', 'not_found', '__edit_url', 'can_edit', 'record_found', 'record_not_found', 'section',
		'reference_code', 'number_of_comments', '__new_account_email', '__new_account_set_password_url', 'has_set_password_link');
	$checked = 0;
	foreach ($w as $key => $widget) {
		if (($widget['config']['custom_form_page_id'] ?? '') !== '{{tab:event_signup}}') continue;
		$checked++;
		$nodes = array();
		_test_cwe_nodes($widget['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach ($n['props']['_bindings'] ?? array() as $what => $token) {
				if ($what === 'eo_visible_if' || in_array($token, $system, true)) continue;
				pg_assert_true(isset($fields[$token]), "$key reads $token, a field of the sign-up");
			}
		}
		foreach ($widget['config']['search_fields'] ?? array() as $s) pg_assert_true(isset($fields[$s]), "$key: search field $s");
	}
	pg_assert_same(5, $checked, 'the record page, both lists and both e-mails');
	pg_assert_same('submitter_only', $w['signup_detail']['config']['access_control'], 'a sign-up is its sender\'s');
	pg_assert_true($w['my_signups_list']['config']['viewer_filter_submitter'], 'My Sign-ups shows one\'s own');
	foreach ($w as $key => $widget) pg_assert_true(isset(_test_cwe_pages()[$widget['page']]) || $widget['page'] === 'home', "$key sits on a page");
}

// Every link names a template page, no fixed date is in a tree.
function test_coworking_events_links_and_dates()
{
	$trees = json_encode(array(_test_cwe_pages(), _test_cwe_load()['part']['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $trees, $m);
	$known = array_flip(array('home', 'plans', 'spaces', 'space', 'space_new', 'book_online', 'tour', 'office_enquiry',
		'privacy', 'contact', 'my_account', 'staff', 'staff_tours', 'staff_enquiries', 'blog_new', 'inbox', 'blog', 'newsletter',
		'forgot_password', 'email_reply', 'events', 'event', 'event_new', 'event_signup', 'signup_view', 'my_signups',
		'staff_signups', 'email_signup_new', 'email_signup_received'));
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "{{page:$key}} names a template page");
	$widgets = _test_cwe_load()['part']['widgets'];
	$pages = _test_cwe_pages();
	unset($pages['event_new']['form']['sample_records']);
	pg_assert_false((bool)preg_match('/\b20\d\d-\d\d-\d\d\b/', json_encode(array($pages, $widgets))), 'no fixed date in a tree');
}

// Ids carry cw-, classes are Bootstrap's or the template's, no faded text.
function test_coworking_events_ids_and_classes()
{
	$nodes = array();
	foreach (_test_cwe_pages() as $p) _test_cwe_nodes($p['tree'], $nodes);
	foreach (_test_cwe_load()['part']['widgets'] as $w) _test_cwe_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pgy|pre|pev|pf|pdp|pm|pma|pmz|plx|pdl|plw|psl|pco|pcl|pht)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, 'comment_') !== 0) pg_assert_true(strpos($id, 'cw-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; an event reads "etkinlik".
function test_coworking_events_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-coworking/events.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		if (preg_match('/\bevent\b/i', $key) && !preg_match('/^[a-z-]+$/', $key)) {
			pg_assert_true(mb_stripos($map[$key], 'etkinli', 0, 'UTF-8') !== false, "'$key' says etkinlik");
		}
	}
}
