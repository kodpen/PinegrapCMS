<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Ski Resort design template's events
 * (includes/design_templates/pinegrap-skiresort/events.php): the pages and
 * widgets of the plan, each widget on its page and the next events on the
 * home, the Events form as a collection whose samples fill its fields with
 * answers of its lists and dates from today on, the lists keeping the
 * events from today on, nearest first, the events page drawn in the
 * events-list block's structure, the record page binding the event's
 * fields, the template's ids and classes, heading levels, no fixed dates
 * and every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/design_template_skiresort_slopes_test.php');

// parts.php, cards.php, the public pages, slopes.php and events.php in one
// scope, as the template file reads them. Read once per run.
function _test_skv_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-skiresort';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	$public = include $dir . '/pages_public.php';
	$slopes_part = include $dir . '/slopes.php';
	$part = include $dir . '/events.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$home = null;
	foreach ($public['pages'] as $p) {
		if ($p['key'] === 'home') $home = $p['tree'];
	}
	$out = array('part' => $part, 'home' => $home, 'slopes' => $slopes_part, 'pages' => $pages, 'widgets' => $part['widgets'],
		'events' => $events, 'event_kind_titles' => $event_kind_titles, 'featured' => $skr_featured_choices);
	return $out;
}

function test_skiresort_events_pages_and_widgets()
{
	$d = _test_skv_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('events', 'event', 'event_new'), array_keys($d['pages']));
	pg_assert_same(array('event_form', 'events_list', 'home_events', 'event_detail'), array_keys($d['widgets']));
	pg_assert_same(array(), array_values(array_intersect(array_keys($d['widgets']), array_keys($d['slopes']['widgets']))), 'no widget key of the slopes is reused');
	$known = array_flip(_test_sk_page_keys());
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a planned page");
		pg_assert_same($key === 'event_new' ? 'private' : 'public', $p['folder'], "$key: its folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
	}
	pg_assert_true(!empty($d['pages']['event_new']['noindex']), 'event_new is not indexed');
	foreach ($d['widgets'] as $key => $w) {
		if ($w['page'] === 'home') continue;
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	pg_assert_contains('"templateWidget":"home_events"', json_encode($d['home']), 'the home page places home_events');
	$all = json_encode($d['part']);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $all, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	foreach (_test_sk_shop_keys() as $key) pg_assert_false(strpos($all, '{{page:' . $key . '}}') !== false, "no link to $key");
}

// The form is a collection; every sample fills a field, its kind is an
// answer of the list, and its date is still to come.
function test_skiresort_events_form_and_samples()
{
	$d = _test_skv_load();
	$form = $d['pages']['event_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same(count($d['events']), count($form['sample_records']), 'one sample per event');
	$controls = _test_sks_controls($d['widgets']['event_form']['tree']);
	pg_assert_same(array('title', 'event_date', 'event_time', 'kind', 'place', 'price', 'summary', 'photo_1', 'featured'), array_keys($controls), 'the event\'s fields');
	pg_assert_same($d['event_kind_titles'], $controls['kind']['options'], 'kind offers its list');
	pg_assert_same($d['featured'], $controls['featured']['options'], 'featured offers its list');
	pg_assert_true(!empty($controls['featured']['cf']['office_use_only']), 'featured is the office\'s field');
	pg_assert_same('image', $controls['photo_1']['cf']['kind'] ?? '', 'the photo takes pictures only');
	$today = date('Y-m-d');
	$featured = 0;
	foreach ($form['sample_records'] as $sample) {
		foreach ($sample as $field => $value) pg_assert_true(isset($controls[$field]), "sample field $field is a field of the form");
		pg_assert_true(in_array($sample['kind'], $d['event_kind_titles'], true), "kind '{$sample['kind']}' is an answer");
		pg_assert_true(in_array($sample['featured'], $d['featured'], true), 'featured is an answer');
		pg_assert_true((bool)preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $sample['event_date']) && $sample['event_date'] > $today, "{$sample['title']}: dated after today");
		pg_assert_true((bool)preg_match('/^[0-9]{2}:[0-9]{2}$/', $sample['event_time']), "{$sample['title']}: a time");
		if ($sample['featured'] === $d['featured'][0]) $featured++;
	}
	pg_assert_true($featured >= 3, 'the home finds three featured events');
}

// The lists keep the events from today on, nearest first; the home's the
// featured ones, three at most.
function test_skiresort_events_lists_from_today_on()
{
	$d = _test_skv_load();
	foreach (array('events_list', 'home_events') as $key) {
		$c = $d['widgets'][$key]['config'];
		pg_assert_same('form_list_view', $c['regionType'], "$key: a list");
		pg_assert_same('{{tab:event_new}}', $c['custom_form_page_id'], "$key: of the Events form");
		pg_assert_same('{{tab:event}}', $c['detail_page_id'], "$key: opens the event");
		pg_assert_same(array('event_date', 'ASC'), array($c['order_by_field'], $c['order_by_direction']), "$key: nearest first");
		pg_assert_same(array('field' => 'event_date', 'operator' => 'is greater than or equal to', 'value' => '', 'dynamic_value' => 'current date'),
			$c['filters'][0], "$key: from today on");
		pg_assert_true(trim((string)$c['empty_message']) !== '', "$key: says when nothing is planned");
	}
	$home = $d['widgets']['home_events']['config'];
	pg_assert_same(3, $home['max_results'], 'three on the home');
	pg_assert_same(array('field' => 'featured', 'operator' => 'is equal to', 'value' => $d['featured'][0], 'dynamic_value' => ''), $home['filters'][1], 'the featured ones');
}

// The events page draws each record in the events-list structure; every
// value is bound to a field of the form.
function test_skiresort_events_list_is_the_palette_block()
{
	$d = _test_skv_load();
	$fields = array_keys(_test_sks_controls($d['widgets']['event_form']['tree']));
	foreach (array('events_list', 'home_events', 'event_detail') as $key) {
		$bound = array();
		foreach (_test_sks_nodes($d['widgets'][$key]['tree']) as $n) {
			foreach ((array)($n['props']['_bindings'] ?? array()) as $name => $token) {
				if ($name === 'eo_visible_if' || in_array($token, array('form_item_view', 'not_found', '__edit_url', 'can_edit'), true)) continue;
				pg_assert_true(in_array($token, $fields, true), "$key: $token is a field of the form");
				$bound[$token] = true;
			}
		}
		foreach (array('title', 'event_date', 'event_time', 'kind', 'place', 'price') as $token) pg_assert_true(isset($bound[$token]), "$key reads $token");
	}
	$json = json_encode($d['widgets']['events_list']['tree']);
	foreach (array('Event Body', 'Event Date', 'Event Copy', 'Event Action') as $part) pg_assert_contains(json_encode(lang($part)), $json, "the list has the block's $part");
	pg_assert_contains('flex-sm-row align-items-sm-center gap-3 gap-sm-4', $json, 'the events-list row');
	pg_assert_contains('"m.Y"', $json, 'the month from the record');
	$detail = json_encode($d['widgets']['event_detail']['tree']);
	foreach (array('record_found', 'record_not_found') as $flag) pg_assert_contains('"' . $flag . '"', $detail, "the event page has $flag");
}

function test_skiresort_events_ids_classes_and_headings()
{
	$d = _test_skv_load();
	$ids = array();
	foreach ($d['slopes']['widgets'] as $w) {
		foreach (_test_sks_nodes($w['tree']) as $n) {
			$id = (string)($n['props']['id'] ?? '');
			if ($id !== '') $ids[$id] = true;
		}
	}
	$trees = array();
	foreach ($d['pages'] as $key => $p) $trees[$key] = $p['tree'];
	foreach ($d['widgets'] as $key => $w) $trees['widget ' . $key] = $w['tree'];
	foreach ($trees as $key => $tree) {
		foreach (_test_sks_nodes($tree) as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pgl|pmz|pph|pvt|pre|pev|prt|sk)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false(strpos($c, 'opacity-') === 0, "$key: no faded text ($c)");
				pg_assert_false(in_array($c, array('text-white', 'bg-white', 'bg-dark', 'text-dark', 'text-muted', 'text-body-tertiary'), true), "$key: no fixed or faint colour ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'skr-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$id]), "id $id once, beside the slopes' ids");
			$ids[$id] = true;
		}
	}
	$levels = array();
	foreach (_test_sks_nodes($d['pages']['events']['tree']) as $n) {
		if (preg_match('/^h([1-6])$/', (string)($n['props']['tag'] ?? ''), $m)) $levels[] = (int)$m[1];
	}
	// The list's events (h3) sit under the list's own h2.
	$levels[] = 3;
	$set = array_values(array_unique($levels));
	sort($set);
	pg_assert_same(range(1, max($set)), $set, 'events: no heading level skipped');
}

// The sample dates are counted from today; nothing else carries a year or
// a month.
function test_skiresort_events_no_fixed_dates()
{
	$d = _test_skv_load();
	$part = $d['part'];
	unset($part['pages'][2]['form']['sample_records']);
	$json = rawurldecode(json_encode($part, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	// The sample's own date, counted from today, fills the canvas.
	foreach ($d['events'] as $e) {
		$json = str_replace(array(date('m.Y', strtotime(_test_skv_on($e))), _test_skv_on($e)), '', $json);
	}
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// An event's date as events.php counts it.
function _test_skv_on($e)
{
	$ts = strtotime('tomorrow');
	while ((int)date('N', $ts) !== (int)$e['weekday']) $ts = strtotime('+1 day', $ts);
	return date('Y-m-d', strtotime('+' . (int)$e['weeks'] . ' week', $ts));
}

function test_skiresort_events_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-skiresort/events.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		if (preg_match('/\bevents?\b/i', $key) && !preg_match('/^[a-z-]+$/', $key)) pg_assert_true(mb_stripos($map[$key], 'etkinli', 0, 'UTF-8') !== false, "'$key' says etkinlik");
		if (preg_match('/\blifts?\b/i', $key)) pg_assert_true(mb_stripos($map[$key], 'mekanik tesis', 0, 'UTF-8') !== false, "'$key' says mekanik tesis");
	}
}
