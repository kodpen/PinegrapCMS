<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the City Hall design template's collections
 * (includes/design_templates/pinegrap-cityhall/notices.php): the pages and
 * widgets, the Notices and Events collections with a sample per record
 * whose values fit their fields, each list's filter reading what the
 * filter row, the footer and the council page send, the home lists the
 * featured records, the record pages open the list on their type or
 * category and offer the notice's document, every page link names a
 * template page, ids and classes are the template's own, and every
 * literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_cy_page_keys()).
require_once(__DIR__ . '/design_template_cityhall_test.php');

// The collections' part as the template file reads it: parts.php first,
// then notices.php in the same scope. Read once per run.
function _test_cyn_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-cityhall';
	include $dir . '/parts.php';
	$part = include $dir . '/notices.php';
	$public = include $dir . '/pages_public.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'],
		'notices' => $notices, 'events' => $events,
		'notice_type_titles' => $notice_type_titles, 'event_category_titles' => $event_category_titles,
		'notice_department_titles' => $notice_department_titles,
		'notice_details' => $notice_details_text, 'event_details' => $event_details_text,
		'public' => $public, 'footer' => $footer());
	return $out;
}

// Every node of a tree, depth first.
function _test_cyn_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_cyn_nodes($c, $out);
}

// The name and options of every form control of a tree.
function _test_cyn_controls($node)
{
	$nodes = array();
	_test_cyn_nodes($node, $nodes);
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

// The query of every link to {{page:$page}} in a tree.
function _test_cyn_queries($tree, $page)
{
	$nodes = array();
	_test_cyn_nodes($tree, $nodes);
	$out = array();
	$prefix = '{{page:' . $page . '}}?';
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, $prefix) !== 0) continue;
		parse_str(substr($href, strlen($prefix)), $q);
		$out[] = $q;
	}
	return $out;
}

function test_cityhall_notices_pages_and_widgets()
{
	$d = _test_cyn_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('notices', 'notice', 'notice_new', 'events', 'event', 'event_new'), array_keys($d['pages']));
	pg_assert_same(array('notice_form', 'notices_list', 'home_notices', 'event_form', 'events_list', 'home_events', 'notice_detail', 'event_detail'), array_keys($d['widgets']));
	foreach (array('notices', 'notice', 'events', 'event') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	foreach (array('notice_new', 'event_new') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "only the staff use $key");
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
	// The home page places home_notices and home_events, which this part
	// defines.
	$home = null;
	foreach ($d['public']['pages'] as $p) if ($p['key'] === 'home') $home = $p;
	foreach (array('home_notices', 'home_events') as $key) {
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($home['tree']), "the home places $key");
		pg_assert_same('home', $d['widgets'][$key]['page']);
	}
}

// Each form is a collection with a sample per record; every sample value
// has its field and is filled, a pick list's value is one of its answers,
// featured is the staff's.
function test_cityhall_notices_forms_and_samples()
{
	$d = _test_cyn_load();
	$cases = array(
		'notice' => array('notice_new', 'notice_form', 'notices', 'notice_details',
			array('title', 'type', 'reference', 'department', 'published', 'deadline', 'summary', 'details', 'document', 'featured'),
			array('title', 'type', 'reference', 'department', 'published', 'deadline', 'summary', 'details', 'featured')),
		'event'  => array('event_new', 'event_form', 'events', 'event_details',
			array('title', 'category', 'place', 'when', 'summary', 'details', 'photo_1', 'featured'),
			array('title', 'category', 'when', 'place', 'summary', 'details', 'photo_1', 'featured')),
	);
	foreach ($cases as $what => $c) {
		list($page, $widget, $data, $details, $fields, $sample_fields) = $c;
		$form = $d['pages'][$page]['form'];
		pg_assert_same(1, $form['collection'], "$what: a collection");
		pg_assert_same(count($d[$data]), count($form['sample_records']), "$what: one sample per record");
		$controls = _test_cyn_controls($d['widgets'][$widget]['tree']);
		pg_assert_same($fields, array_keys($controls), "$what: the fields of the form");
		pg_assert_true(!empty($controls['featured']['cf']['office_use_only']), "$what: featured is the staff's");
		$featured = 0;
		foreach ($form['sample_records'] as $n => $r) {
			pg_assert_same($sample_fields, array_keys($r), "$what sample $n: every field");
			foreach ($r as $name => $value) {
				pg_assert_true($value !== '', "$what sample $n: $name filled");
				if ($controls[$name]['tag'] === 'select') {
					pg_assert_true(in_array($value, $controls[$name]['options'], true), "$what sample $n: $name '$value' is an answer");
				}
				pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $value), "$what sample $n: $name names no year");
			}
			$key = array_keys($d[$data])[$n];
			pg_assert_same(call_user_func($d[$details], $key), $r['details'], "$what sample $n: its details, a line per item");
			if ($r['featured'] === lang('Yes')) $featured++;
		}
		pg_assert_same(3, $featured, "$what: three featured samples");
	}
	$notice = _test_cyn_controls($d['widgets']['notice_form']['tree']);
	pg_assert_same($d['notice_type_titles'], $notice['type']['options'], 'the notice types');
	pg_assert_same($d['notice_department_titles'], $notice['department']['options'], 'the departments and the clerks');
	pg_assert_false(isset($notice['document']['cf']['kind']), 'the document is a file, not an image');
	$event = _test_cyn_controls($d['widgets']['event_form']['tree']);
	pg_assert_same($d['event_category_titles'], $event['category']['options'], 'the event categories');
	pg_assert_same('image', $event['photo_1']['cf']['kind'] ?? '', 'the event photo is an image');
	pg_assert_same('photo_1', $d['pages']['event_new']['form']['share_image_field'], 'the event photo is the share image');
	pg_assert_true(isset($event[$d['pages']['event_new']['form']['share_image_field']]), 'the share image is a field');
}

// Each list reads the parameter its filter row, the footer and the council
// page send, each an answer of its pick list; the home lists the featured
// records, three at most.
function test_cityhall_notices_filters()
{
	$d = _test_cyn_load();
	$cases = array(
		array('notices_list', 'home_notices', 'notices', 'type', $d['notice_type_titles'], 'notice_new', 'notice'),
		array('events_list', 'home_events', 'events', 'category', $d['event_category_titles'], 'event_new', 'event'),
	);
	foreach ($cases as $c) {
		list($list, $home, $page, $param, $answers, $form, $detail) = $c;
		$params = array();
		foreach ($d['widgets'][$list]['config']['filters'] as $f) {
			pg_assert_same('url parameter', $f['dynamic_value'], "$list: the filter reads the address");
			$params[$f['field']] = $f['dynamic_value_attribute'];
		}
		pg_assert_same(array($param => $param), $params, "$list filters the $param");
		$trees = array($d['pages'][$page]['tree'], $d['footer']);
		foreach ($d['public']['pages'] as $p) $trees[] = $p['tree'];
		$seen = 0;
		foreach ($trees as $tree) {
			foreach (_test_cyn_queries($tree, $page) as $q) {
				pg_assert_same(array($param), array_keys($q), "$page: only ?$param=");
				pg_assert_true(in_array($q[$param] ?? '', $answers, true), "$page: ?$param={$q[$param]} is an answer");
				$seen++;
			}
		}
		pg_assert_true($seen >= count($answers), "$page: filter links");
		$hc = $d['widgets'][$home]['config'];
		pg_assert_same(array(array('field' => 'featured', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => '')), $hc['filters'], "$home: the featured ones");
		pg_assert_same(3, $hc['max_results'], "$home: three at most");
		foreach (array($list, $home) as $key) {
			pg_assert_same('{{tab:' . $form . '}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key: its form");
			pg_assert_same('{{tab:' . $detail . '}}', $d['widgets'][$key]['config']['detail_page_id'], "$key: its record page");
		}
	}
}

// A record page shows the record or the not-found note, opens the list on
// its type or category, and a notice offers its document.
function test_cityhall_notices_record_pages()
{
	$d = _test_cyn_load();
	$cases = array(
		'notice_detail' => array('notices', 'type', array('title', 'type', 'reference', 'department', 'published', 'deadline', 'summary', 'details')),
		'event_detail'  => array('events', 'category', array('title', 'category', 'when', 'place', 'summary', 'details', 'photo_1')),
	);
	foreach ($cases as $key => $c) {
		list($list, $param, $fields) = $c;
		$nodes = array();
		_test_cyn_nodes($d['widgets'][$key]['tree'], $nodes);
		$links = array();
		$bound = array();
		foreach ($nodes as $n) {
			if (($n['props']['_bindings']['href'] ?? '') === '__page_link') $links[] = $n['props']['_bindParams']['href'];
			foreach (array('text', 'src', 'href') as $k) if (isset($n['props']['_bindings'][$k])) $bound[] = $n['props']['_bindings'][$k];
		}
		pg_assert_same(array(
			array('page_id' => '{{tab:' . $list . '}}', 'params' => array(array('name' => $param, 'token' => $param))),
		), $links, "$key: the list on its $param");
		foreach ($fields as $f) pg_assert_true(in_array($f, $bound, true), "$key shows $f");
		$json = json_encode($d['widgets'][$key]['tree']);
		pg_assert_contains('"eo_visible_if":"record_found"', $json, "$key: the record");
		pg_assert_contains('"eo_visible_if":"record_not_found"', $json, "$key: the not-found note");
		pg_assert_contains('"eo_visible_if":"can_edit"', $json, "$key: the Edit button");
		if ($key === 'notice_detail') pg_assert_true(in_array('document', $bound, true), 'the notice offers its document');
	}
}

function test_cityhall_notices_links_ids_and_classes()
{
	$d = _test_cyn_load();
	$known = array_flip(_test_cy_page_keys());
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
	foreach ($d['pages'] as $p) _test_cyn_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_cyn_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(ptv|pht|pre|pev|pgy|pch|plw|pwd|pph|pcn|pmu|psc|psh|pmz|pai|prt)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'cy-') === 0, "id $id is the template's");
	}
	$json = json_encode($d['pages'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the pages');
}

// Every literal has its Turkish; a notice reads "duyuru", an event
// "etkinlik".
function test_cityhall_notices_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-cityhall/notices.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = str_replace('İ', 'i', $map[$key]);
		if (preg_match('/^(Public Notices|New Notice|Notice not found|More notices of this type|The notice is published\.)$/', $key)) pg_assert_true(mb_stripos($tr, 'duyuru', 0, 'UTF-8') !== false, "'$key' says duyuru");
		if (preg_match('/^(City Events|New Event|Event not found|More events of this category)$/', $key)) pg_assert_true(mb_stripos($tr, 'etkinli', 0, 'UTF-8') !== false, "'$key' says etkinlik");
	}
}
