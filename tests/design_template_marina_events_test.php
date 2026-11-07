<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Marina design template's events and event entries
 * (includes/design_templates/pinegrap-marina/events.php): the pages and
 * widgets, the Events collection with a sample per event whose values fit
 * their fields, the list's kind filter reading what the filter bar sends,
 * the home lists the featured events, the event page opens the entry
 * request with the event named and the list on its kind, the entry form
 * reading ?event=, its office-only status and consent, the record page
 * showing only the skipper's own entry, the lists and e-mails reading
 * fields of their form, every page link names a template page, ids and
 * classes are the template's own, and every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys (_test_mn_page_keys()), the charters' helpers
// (_test_mnc_nodes(), _test_mnc_controls(), _test_mnc_list_queries()) and
// the requests' loader (_test_mnr_load()).
require_once(__DIR__ . '/design_template_marina_test.php');
require_once(__DIR__ . '/design_template_marina_charters_test.php');
require_once(__DIR__ . '/design_template_marina_requests_test.php');

// The events part as the template file reads it: parts.php, mail.php,
// charters.php and requests.php first, then events.php in the same scope.
// Read once per run.
function _test_mne_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-marina';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	include $dir . '/charters.php';
	include $dir . '/requests.php';
	$part = include $dir . '/events.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'events' => $events,
		'event_kinds' => $event_kinds, 'entry_statuses' => $entry_statuses, 'vessel_choices' => $vessel_choices,
		'featured' => $mn_featured_choices);
	return $out;
}

function test_marina_events_pages_and_widgets()
{
	$d = _test_mne_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('events', 'event', 'event_new', 'event_entry', 'entry_view', 'my_entries', 'staff_entries',
		'email_entry_new', 'email_entry_received'), array_keys($d['pages']));
	$folders = array('events' => 'public', 'event' => 'public', 'event_entry' => 'public', 'event_new' => 'private',
		'entry_view' => 'registration', 'my_entries' => 'registration', 'staff_entries' => 'private');
	foreach ($folders as $key => $folder) pg_assert_same($folder, $d['pages'][$key]['folder'], "$key: folder");
	pg_assert_true(!empty($d['pages']['event_new']['noindex']), 'the staff page is not indexed');
	$known = array_flip(_test_mn_page_keys());
	// No address is a page's of the other parts.
	$names = array();
	$r = _test_mnr_load();
	$c = _test_mnc_load();
	foreach (array_merge($r['part']['pages'], $c['part']['pages'], $c['public']['pages']) as $p) $names[$p['name']] = $p['key'];
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
		pg_assert_true($w['page'] === 'home' || isset($d['pages'][$w['page']]), "$key sits on a page of this part or the home");
		if ($w['page'] !== 'home') {
			pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
		}
		$placed[$w['page']] = true;
	}
	foreach (array_keys($d['pages']) as $key) pg_assert_true(isset($placed[$key]), "$key carries a widget");
	$home = null;
	foreach ($c['public']['pages'] as $p) if ($p['key'] === 'home') $home = $p;
	pg_assert_contains('"templateWidget":"home_events"', json_encode($home['tree']), 'the home places the featured events');
}

// The form is a collection with a sample per event; every sample value has
// its field and is filled, the kind is one of its answers, the photo is an
// image and featured is the staff's.
function test_marina_events_form_and_samples()
{
	$d = _test_mne_load();
	$form = $d['pages']['event_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same(count($d['events']), count($form['sample_records']), 'one sample per event');
	$controls = _test_mnc_controls($d['widgets']['event_form']['tree']);
	$fields = array('title', 'kind', 'when', 'where', 'fee', 'summary', 'description', 'photo_1', 'featured');
	pg_assert_same($fields, array_keys($controls), 'the fields of the form');
	pg_assert_same('image', $controls['photo_1']['cf']['kind'] ?? '', 'the photo is an image');
	pg_assert_true(!empty($controls['featured']['cf']['office_use_only']), 'featured is the staff\'s');
	pg_assert_true(isset($controls[$form['share_image_field']]), 'the share image is a field');
	pg_assert_same($d['event_kinds'], $controls['kind']['options'], 'the kinds');
	$kinds = array();
	$featured = 0;
	foreach ($form['sample_records'] as $n => $r) {
		pg_assert_same($fields, array_keys($r), "sample $n: every field");
		foreach ($r as $f => $v) pg_assert_true(trim((string)$v) !== '', "sample $n: $f is filled");
		pg_assert_true(in_array($r['kind'], $controls['kind']['options'], true), "sample $n: its kind is an answer");
		pg_assert_true(in_array($r['featured'], $controls['featured']['options'], true), "sample $n: featured is an answer");
		pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $r['when']), "sample $n: no fixed year");
		$kinds[$r['kind']] = true;
		if ($r['featured'] === $d['featured'][0]) $featured++;
	}
	pg_assert_same(count($d['event_kinds']), count($kinds), 'a sample of every kind');
	pg_assert_true($featured >= 3, 'the home has three featured events');
}

// The list reads ?kind=, and every kind the filter bar sends is an answer;
// the home shows only the featured events.
function test_marina_events_filters()
{
	$d = _test_mne_load();
	$filters = $d['widgets']['events_list']['config']['filters'];
	pg_assert_same(array('kind'), array_column($filters, 'field'));
	pg_assert_same('url parameter', $filters[0]['dynamic_value']);
	pg_assert_same('kind', $filters[0]['dynamic_value_attribute']);
	$controls = array_keys(_test_mnc_controls($d['widgets']['event_form']['tree']));
	foreach ($d['widgets']['events_list']['config']['search_fields'] as $f) pg_assert_true(in_array($f, $controls, true), "the search reads the field $f");
	$sent = array();
	$nodes = array();
	_test_mnc_nodes($d['pages']['events']['tree'], $nodes);
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:events}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:events}}?')), $q);
		pg_assert_same(array('kind'), array_keys($q), 'the bar sends ?kind=');
		$sent[] = $q['kind'];
	}
	pg_assert_same($d['event_kinds'], $sent, 'a link per kind');
	$home = $d['widgets']['home_events']['config']['filters'];
	pg_assert_same(array(array('field' => 'featured', 'operator' => 'is equal to', 'value' => $d['featured'][0], 'dynamic_value' => '')), $home);
	foreach (array('events_list', 'home_events') as $key) {
		pg_assert_same('{{tab:event_new}}', $d['widgets'][$key]['config']['custom_form_page_id']);
		pg_assert_same('{{tab:event}}', $d['widgets'][$key]['config']['detail_page_id']);
		$nodes = array();
		_test_mnc_nodes($d['widgets'][$key]['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach ((array)($n['props']['_bindings'] ?? array()) as $prop => $token) {
				pg_assert_true(in_array($token, $controls, true) || $token === 'form_item_view', "$key reads the field $token");
			}
		}
	}
}

// The event page reads its record and opens the entry request with the
// event named (?event=) and the list on its kind (?kind=).
function test_marina_events_record_page()
{
	$d = _test_mne_load();
	$w = $d['widgets']['event_detail'];
	pg_assert_same('public', $w['config']['access_control']);
	pg_assert_same('{{tab:event_new}}', $w['config']['custom_form_page_id']);
	$json = json_encode($w['tree']);
	pg_assert_contains('{"page_id":"{{tab:event_entry}}","params":[{"name":"event","token":"title"}]}', $json, 'the event page opens the entry with its title');
	pg_assert_contains('{"page_id":"{{tab:events}}","params":[{"name":"kind","token":"kind"}]}', $json, 'the event page opens its kind');
	foreach (array('record_found', 'record_not_found', 'can_edit') as $flag) pg_assert_contains('"eo_visible_if":"' . $flag . '"', $json, "the event page: $flag");
	$controls = array_keys(_test_mnc_controls($d['widgets']['event_form']['tree']));
	$nodes = array();
	_test_mnc_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach ((array)($n['props']['_bindings'] ?? array()) as $prop => $token) {
			if ($prop === 'eo_visible_if') continue;
			pg_assert_true(in_array($token, $controls, true) || in_array($token, array('not_found', '__edit_url', '__page_link'), true), "the event page reads the field $token");
		}
	}
}

// The entry request reads ?event= into a text field (an event added later
// is taken as well); the status is the harbour office's; the record page,
// lists and e-mails read fields of the form.
function test_marina_events_entries()
{
	$d = _test_mne_load();
	$form = $d['pages']['event_entry']['form'];
	pg_assert_same('{{tab:entry_view}}', $form['confirmation_page_id']);
	pg_assert_same(1, $form['auto_registration']);
	pg_assert_same('{{tab:email_entry_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_entry_received}}', $form['confirm_page_id']);
	pg_assert_contains('^^event^^', $form['notify_subject']);
	$c = _test_mnc_controls($d['widgets']['entry_form']['tree']);
	foreach (array('event', 'boat_name', 'vessel', 'boat_length', 'sail_number', 'crew', 'notes',
		'first_name', 'last_name', 'email', 'phone', 'status', 'consent') as $name) {
		pg_assert_true(isset($c[$name]), "the entry asks for $name");
	}
	pg_assert_same('input', $c['event']['tag'], 'the event is a text field');
	pg_assert_same('event', $c['event']['cf']['url_param'] ?? '', 'the event reads ?event=');
	pg_assert_same($d['vessel_choices'], $c['vessel']['options']);
	pg_assert_same($d['entry_statuses'], $c['status']['options']);
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the harbour office\'s');
	pg_assert_contains('{{page:privacy}}', json_encode($d['widgets']['entry_form']['tree']), 'the consent links the privacy page');
	pg_assert_same('submitter_only', $d['widgets']['entry_detail']['config']['access_control'], 'the skipper\'s own');
	pg_assert_same('{{tab:email_reply}}', $d['pages']['entry_view']['comments']['email_page'], 'messages are mailed');
	pg_assert_same('comments', $d['widgets']['entry_messages']['config']['regionType']);
	pg_assert_true(!empty($d['widgets']['my_entries_list']['config']['viewer_filter_submitter']), 'my entries: the skipper\'s own');
	$fields = array_keys($c);
	foreach ($d['widgets']['staff_entries_list']['config']['search_fields'] as $f) pg_assert_true(in_array($f, $fields, true), "the staff search reads $f");
	foreach (array('email_entry_admin', 'email_entry_client') as $mail) pg_assert_same('public', $d['widgets'][$mail]['config']['access_control']);
	pg_assert_contains('{{page:entry_view}}?r=^^reference_code^^', json_encode($d['widgets']['email_entry_admin']['tree'], JSON_UNESCAPED_SLASHES));
	$system = array('form_item_view', 'reference_code', 'number_of_comments', 'submitted_date_and_time', 'not_found',
		'__new_account_email', '__new_account_set_password_url', '__edit_url');
	foreach (array('entry_detail', 'my_entries_list', 'staff_entries_list', 'email_entry_admin', 'email_entry_client') as $key) {
		pg_assert_same('{{tab:event_entry}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key reads the entries");
		$nodes = array();
		_test_mnc_nodes($d['widgets'][$key]['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach ((array)($n['props']['_bindings'] ?? array()) as $prop => $token) {
				if ($prop === 'eo_visible_if' || $prop === 'section' || $prop === 'action') continue;
				pg_assert_true(in_array($token, $fields, true) || in_array($token, $system, true), "$key reads the field $token");
			}
		}
	}
}

function test_marina_events_links_ids_and_classes()
{
	$d = _test_mne_load();
	$known = array_flip(_test_mn_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	pg_assert_false((bool)preg_match('/[?&]view=/', json_encode($trees, JSON_UNESCAPED_SLASHES)), 'no ?view= link');
	$nodes = array();
	foreach ($d['pages'] as $p) _test_mnc_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_mnc_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh|pmz|pai|pcp|prt)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'mn-') === 0, "id $id is the template's");
		pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', (string)($n['props']['text'] ?? '')), 'no fixed year in a text');
	}
}

// Every literal has its Turkish; an event entry reads "katılım başvurusu",
// as the entry statuses and the staff menu do.
function test_marina_events_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-marina/events.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		if (preg_match('/^[a-z-]+$/', $key)) continue;
		if (preg_match('/\bevent entr(y|ies)\b/i', $key)) pg_assert_true(mb_stripos($map[$key], 'katılım başvuru', 0, 'UTF-8') !== false, "'$key' says katılım başvurusu");
	}
}
