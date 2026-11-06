<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Private School design template's events
 * (includes/design_templates/pinegrap-school/events.php): the pages and
 * widgets, the Events collection with a sample per event whose values fit
 * their fields, the list's filter reading what the filter row, the
 * programme pages and the footer send, the home lists the featured
 * events, the event page opens the list on its programme, every page link
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

// The page keys of the finished template (_test_sh_page_keys()).
require_once(__DIR__ . '/design_template_school_test.php');

// The events' part as the template file reads it: parts.php first, then
// events.php in the same scope. Read once per run.
function _test_she_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-school';
	include $dir . '/parts.php';
	$part = include $dir . '/events.php';
	$public = include $dir . '/pages_public.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'events' => $events,
		'event_programme_titles' => $event_programme_titles, 'details_text' => $details_text,
		'public' => $public, 'footer' => $footer());
	return $out;
}

// Every node of a tree, depth first.
function _test_she_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_she_nodes($c, $out);
}

// The name and options of every form control of a tree.
function _test_she_controls($node)
{
	$nodes = array();
	_test_she_nodes($node, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$tag = $n['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$name = '';
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) {
			if ($a['name'] === 'name') $name = $a['value'];
		}
		$options = array();
		foreach ((array)($n['children'] ?? array()) as $o) {
			foreach ((array)($o['props']['_attrs'] ?? array()) as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $options[] = $a['value'];
			}
		}
		$out[$name] = array('tag' => $tag, 'options' => $options, 'cf' => $n['props']['_cf'] ?? array());
	}
	return $out;
}

// The query of every link to {{page:events}} in a tree.
function _test_she_event_queries($tree)
{
	$nodes = array();
	_test_she_nodes($tree, $nodes);
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

function test_school_events_pages_and_widgets()
{
	$d = _test_she_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('events', 'event', 'event_new'), array_keys($d['pages']));
	pg_assert_same(array('event_form', 'events_list', 'home_events', 'event_detail'), array_keys($d['widgets']));
	foreach (array('events', 'event') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	pg_assert_same('private', $d['pages']['event_new']['folder'], 'only the staff add an event');
	pg_assert_true(!empty($d['pages']['event_new']['noindex']), 'the staff page is not indexed');
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
	// The home page places home_events, which this part defines.
	$home = null;
	foreach ($d['public']['pages'] as $p) if ($p['key'] === 'home') $home = $p;
	pg_assert_contains('"templateWidget":"home_events"', json_encode($home['tree']), 'the home places the featured events');
	pg_assert_same('home', $d['widgets']['home_events']['page']);
}

// The form is a collection with a sample per event; every sample value has
// its field and is filled, a pick list's value is one of its answers, the
// photo is an image and featured is the staff's.
function test_school_events_form_and_samples()
{
	$d = _test_she_load();
	$form = $d['pages']['event_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same(count($d['events']), count($form['sample_records']), 'one sample per event');
	$controls = _test_she_controls($d['widgets']['event_form']['tree']);
	$fields = array('title', 'programme', 'place', 'when', 'summary', 'details', 'photo_1', 'featured');
	pg_assert_same($fields, array_keys($controls), 'the fields of the form');
	pg_assert_same('image', $controls['photo_1']['cf']['kind'] ?? '', 'the photo is an image');
	pg_assert_true(!empty($controls['featured']['cf']['office_use_only']), 'featured is the staff\'s');
	pg_assert_true(isset($controls[$form['share_image_field']]), 'the share image is a field');
	pg_assert_same($d['event_programme_titles'], $controls['programme']['options'], 'the programmes and the whole school');
	$featured = 0;
	foreach ($form['sample_records'] as $n => $r) {
		pg_assert_same(array('title', 'programme', 'when', 'place', 'summary', 'details', 'photo_1', 'featured'), array_keys($r), "sample $n: every field");
		foreach ($r as $name => $value) {
			pg_assert_true($value !== '', "sample $n: $name filled");
			if ($controls[$name]['tag'] === 'select') {
				pg_assert_true(in_array($value, $controls[$name]['options'], true), "sample $n: $name '$value' is an answer");
			}
			pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $value), "sample $n: $name names no year");
		}
		$key = array_keys($d['events'])[$n];
		pg_assert_same(call_user_func($d['details_text'], $key), $r['details'], "sample $n: its details, a line per item");
		if ($r['featured'] === lang('Yes')) $featured++;
	}
	pg_assert_same(3, $featured, 'three featured samples');
}

// The list reads the parameter the filter row, the programme pages and the
// footer send, each an answer of the Programme list; the home lists the
// featured events, three at most.
function test_school_events_filters()
{
	$d = _test_she_load();
	$params = array();
	foreach ($d['widgets']['events_list']['config']['filters'] as $f) {
		pg_assert_same('url parameter', $f['dynamic_value'], 'the filter reads the address');
		$params[$f['field']] = $f['dynamic_value_attribute'];
	}
	pg_assert_same(array('programme' => 'programme'), $params, 'the list filters the programme');
	$trees = array($d['pages']['events']['tree'], $d['footer']);
	foreach ($d['public']['pages'] as $p) $trees[] = $p['tree'];
	$seen = 0;
	foreach ($trees as $tree) {
		foreach (_test_she_event_queries($tree) as $q) {
			pg_assert_same(array('programme'), array_keys($q), 'only ?programme=');
			pg_assert_true(in_array($q['programme'] ?? '', $d['event_programme_titles'], true), "?programme={$q['programme']} is an answer");
			$seen++;
		}
	}
	pg_assert_true($seen >= count($d['event_programme_titles']) * 2, 'programme links');
	$c = $d['widgets']['home_events']['config'];
	pg_assert_same(array(array('field' => 'featured', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => '')), $c['filters'], 'the featured ones');
	pg_assert_same(3, $c['max_results'], 'three at most');
	foreach (array('events_list', 'home_events') as $key) {
		pg_assert_same('{{tab:event_new}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key: the Events form");
		pg_assert_same('{{tab:event}}', $d['widgets'][$key]['config']['detail_page_id'], "$key: the event page");
	}
}

// The event page shows the record or the not-found note and opens the list
// on its programme.
function test_school_events_record_page()
{
	$d = _test_she_load();
	$nodes = array();
	_test_she_nodes($d['widgets']['event_detail']['tree'], $nodes);
	$links = array();
	$bound = array();
	foreach ($nodes as $n) {
		if (($n['props']['_bindings']['href'] ?? '') === '__page_link') $links[] = $n['props']['_bindParams']['href'];
		foreach (array('text', 'src') as $k) if (isset($n['props']['_bindings'][$k])) $bound[] = $n['props']['_bindings'][$k];
	}
	pg_assert_same(array(
		array('page_id' => '{{tab:events}}', 'params' => array(array('name' => 'programme', 'token' => 'programme'))),
	), $links, 'the list on its programme');
	foreach (array('title', 'programme', 'when', 'place', 'summary', 'details', 'photo_1') as $f) {
		pg_assert_true(in_array($f, $bound, true), "the page shows $f");
	}
	$json = json_encode($d['widgets']['event_detail']['tree']);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the record');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
	pg_assert_contains('"eo_visible_if":"can_edit"', $json, 'the Edit button');
}

function test_school_events_links_ids_and_classes()
{
	$d = _test_she_load();
	$known = array_flip(_test_sh_page_keys());
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
	foreach ($d['pages'] as $p) _test_she_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_she_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(ptv|pht|pre|pev|pgy|pch|plw|pwd|pph|pcn|pmu|psc)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'sh-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; an event reads "etkinlik" and a
// programme "program".
function test_school_events_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-school/events.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bEvents?\b/', $key) && strpos($key, '-') === false) pg_assert_true(mb_stripos($tr, 'etkinli', 0, 'UTF-8') !== false, "'$key' says etkinlik");
		if (preg_match('/\bProgrammes?\b/', $key)) pg_assert_true(mb_stripos($tr, 'program', 0, 'UTF-8') !== false, "'$key' says program");
	}
}
