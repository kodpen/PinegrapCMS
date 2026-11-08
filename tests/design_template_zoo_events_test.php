<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Zoo design template's events
 * (includes/design_templates/pinegrap-zoo/events.php): the pages and
 * widgets of the plan, each widget on its page and the featured events on
 * the home, the Events form as a collection whose samples fill its fields
 * with answers of their lists and dates still to come, the lists showing
 * today on by date and filtering on ?kind= in the list's own words, the
 * record page binding the event's fields, the template's ids and classes,
 * heading levels, no fixed dates and every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/design_template_zoo_animals_test.php');

// parts.php, cards.php, the public pages and events.php in one scope, as
// the template file reads them. Read once per run.
function _test_zooe_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-zoo';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	$public = include $dir . '/pages_public.php';
	$part = include $dir . '/events.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$home = null;
	foreach ($public['pages'] as $p) {
		if ($p['key'] === 'home') $home = $p['tree'];
	}
	$out = array('part' => $part, 'home' => $home, 'pages' => $pages, 'widgets' => $part['widgets'], 'events' => $events,
		'kind_titles' => $event_kind_titles);
	return $out;
}

function test_zoo_events_pages_and_widgets()
{
	$d = _test_zooe_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('events', 'event', 'event_new'), array_keys($d['pages']));
	pg_assert_same(array('event_form', 'events_list', 'home_events', 'event_detail'), array_keys($d['widgets']));
	$known = array_flip(_test_zoo_page_keys());
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
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($d['part']), $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	foreach (_test_zoo_shop_keys() as $key) pg_assert_false(strpos(json_encode($d['part']), '{{page:' . $key . '}}') !== false, "no link to $key");
}

// The form is a collection; every sample fills a field the cards read, its
// kind is an answer of the list and its date is still to come.
function test_zoo_events_form_and_samples()
{
	$d = _test_zooe_load();
	$form = $d['pages']['event_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same(count($d['events']), count($form['sample_records']), 'one sample per event');
	$controls = _test_zooa_controls($d['widgets']['event_form']['tree']);
	$names = array('title', 'event_date', 'event_time', 'kind', 'place', 'price', 'summary', 'photo_1', 'featured');
	$fields = array_keys($controls);
	sort($fields);
	$sorted = $names;
	sort($sorted);
	pg_assert_same($sorted, $fields, 'the fields the cards read');
	pg_assert_same('date', (function ($tree) {
		foreach (_test_zooa_nodes($tree) as $n) {
			$type = '';
			$name = '';
			foreach ((array)($n['props']['_attrs'] ?? array()) as $a) {
				if ($a['name'] === 'type') $type = $a['value'];
				if ($a['name'] === 'name') $name = $a['value'];
			}
			if ($name === 'event_date') return $type;
		}
		return '';
	})($d['widgets']['event_form']['tree']), 'the date is a date field');
	$lists = array('kind' => $d['kind_titles'], 'featured' => array(lang('Yes'), lang('No')));
	$today = date('Y-m-d');
	$featured = 0;
	foreach ($form['sample_records'] as $sample) {
		pg_assert_same($names, array_keys($sample), 'the sample fills every field');
		foreach ($lists as $field => $answers) pg_assert_true(in_array($sample[$field], $answers, true), "$field '{$sample[$field]}' is an answer");
		pg_assert_true((bool)preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $sample['event_date']) && $sample['event_date'] > $today, "{$sample['title']}: a date still to come");
		if ($sample['featured'] === lang('Yes')) $featured++;
	}
	foreach ($lists as $field => $answers) pg_assert_same($answers, $controls[$field]['options'], "$field offers its list");
	pg_assert_true(!empty($controls['featured']['cf']['office_use_only']), 'featured is the office\'s field');
	pg_assert_true($featured >= (int)$d['widgets']['home_events']['config']['max_results'], 'the home finds its featured events');
}

// Both lists show the events from today on, nearest first; the events list
// filters on ?kind= and every kind pill uses an answer of the list.
function test_zoo_events_lists_by_date_and_kind()
{
	$d = _test_zooe_load();
	foreach (array('events_list', 'home_events') as $key) {
		$c = $d['widgets'][$key]['config'];
		pg_assert_same(array('event_date', 'ASC'), array($c['order_by_field'], $c['order_by_direction']), "$key: nearest first");
		pg_assert_same('{{tab:event}}', $c['detail_page_id'], "$key: a card opens the event");
		$today = false;
		foreach ($c['filters'] as $f) {
			if ($f['field'] === 'event_date') $today = $f['operator'] === 'is greater than or equal to' && $f['dynamic_value'] === 'current date';
		}
		pg_assert_true($today, "$key: from today on");
	}
	$params = array();
	foreach ($d['widgets']['events_list']['config']['filters'] as $f) {
		if (($f['dynamic_value'] ?? '') === 'url parameter') $params[$f['field']] = $f['dynamic_value_attribute'];
	}
	pg_assert_same(array('kind' => 'kind'), $params, 'the list reads the address');
	$seen = array();
	foreach (_test_zooa_nodes($d['pages']['events']['tree']) as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (!preg_match('/^\{\{page:events\}\}\?(.*)$/', $href, $m)) continue;
		parse_str($m[1], $q);
		foreach ($q as $name => $value) {
			pg_assert_same('kind', $name, 'a pill filters the kind');
			pg_assert_true(in_array($value, $d['kind_titles'], true), "kind '$value' is an answer");
			$seen[$value] = true;
		}
	}
	pg_assert_same(count($d['kind_titles']), count($seen), 'every kind opens the list');
	pg_assert_false(strpos(json_encode($d['part']), '?view=') !== false, 'never ?view=');
}

// The event's page binds the record's fields and has its not-found state.
function test_zoo_events_detail_reads_the_record()
{
	$d = _test_zooe_load();
	$w = $d['widgets']['event_detail'];
	pg_assert_same('form_item_view', $w['config']['regionType'], 'a record view');
	pg_assert_same('{{tab:event_new}}', $w['config']['custom_form_page_id'], 'of the Events form');
	$bound = array();
	foreach (_test_zooa_nodes($w['tree']) as $n) {
		foreach ((array)($n['props']['_bindings'] ?? array()) as $token) $bound[$token] = true;
	}
	foreach (array('title', 'event_date', 'event_time', 'kind', 'place', 'price', 'summary', 'photo_1', 'record_found', 'record_not_found', 'not_found') as $token) {
		pg_assert_true(isset($bound[$token]), "the page reads $token");
	}
}

function test_zoo_events_ids_classes_and_headings()
{
	$d = _test_zooe_load();
	$ids = array();
	$trees = array();
	foreach ($d['pages'] as $key => $p) $trees[$key] = $p['tree'];
	foreach ($d['widgets'] as $key => $w) $trees['widget ' . $key] = $w['tree'];
	foreach ($trees as $key => $tree) {
		foreach (_test_zooa_nodes($tree) as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(prx|pmz|pph|pvt|pre|pev|pgl|pmn|prt|plb|pht|pma|pco|pcl|pgy|pch|psk|sk|pbw)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false(strpos($c, 'opacity-') === 0, "$key: no faded text ($c)");
				pg_assert_false(in_array($c, array('text-white', 'bg-white', 'bg-dark', 'text-dark', 'btn-light', 'text-muted', 'text-body-tertiary'), true), "$key: no fixed or faint colour ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'zoo-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$id]), "id $id once");
			$ids[$id] = true;
		}
	}
	$levels = array();
	foreach (_test_zooa_nodes($d['widgets']['event_detail']['tree']) as $n) {
		if (($n['props']['_label'] ?? '') === lang('Not Found')) continue;
		if (preg_match('/^h([1-6])$/', (string)($n['props']['tag'] ?? ''), $m)) $levels[] = (int)$m[1];
	}
	pg_assert_same(1, count(array_keys($levels, 1, true)), 'event: one h1');
	$levels = array();
	foreach (_test_zooa_nodes($d['pages']['events']['tree']) as $n) {
		if (preg_match('/^h([1-6])$/', (string)($n['props']['tag'] ?? ''), $m)) $levels[] = (int)$m[1];
	}
	pg_assert_same(1, count(array_keys($levels, 1, true)), 'events: one h1');
}

function test_zoo_events_no_fixed_dates()
{
	$part = _test_zooe_load()['part'];
	// The sample records carry dates counted from today; the rest must not.
	foreach ($part['pages'] as $i => $p) {
		if (isset($p['form']['sample_records'])) unset($part['pages'][$i]['form']['sample_records']);
	}
	$json = json_encode($part, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$json = rawurldecode(preg_replace('/"text":"[0-9.]+"/', '"text":""', $json));
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year written in');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

function test_zoo_events_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-zoo/events.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		if (preg_match('/\bevents?\b/i', $key) && strpos($key, ',') === false) pg_assert_true(mb_stripos($map[$key], 'etkinli', 0, 'UTF-8') !== false, "'$key' says etkinlik");
	}
}
