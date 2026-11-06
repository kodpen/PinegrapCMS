<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Museum design template's exhibitions
 * (includes/design_templates/pinegrap-museum/exhibitions.php): the pages
 * and widgets, the Exhibitions collection with a sample per exhibition
 * whose values fit their fields, the list's filter reading what the filter
 * row and the footer send, the home lists the exhibitions on now, the
 * exhibition page opens the list on its state and books a timed entry,
 * every page link names a template page, ids and classes are the
 * template's own, and every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_mz_page_keys()).
require_once __DIR__ . '/design_template_museum_test.php';

// The exhibitions' part as the template file reads it: parts.php first,
// then the public pages and exhibitions.php in the same scope. Read once
// per run.
function _test_mzx_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-museum';
	include $dir . '/parts.php';
	$public = include $dir . '/pages_public.php';
	$part = include $dir . '/exhibitions.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'exhibitions' => $exhibitions,
		'statuses' => $exhibition_statuses, 'status_titles' => $exhibition_status_titles,
		'public' => $public, 'footer' => $footer());
	return $out;
}

// Every node of a tree, depth first.
function _test_mzx_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_mzx_nodes($c, $out);
}

// The name and options of every form control of a tree.
function _test_mzx_controls($node)
{
	$nodes = array();
	_test_mzx_nodes($node, $nodes);
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

// The query of every link to {{page:exhibitions}} in a tree.
function _test_mzx_queries($tree)
{
	$nodes = array();
	_test_mzx_nodes($tree, $nodes);
	$out = array();
	$prefix = '{{page:exhibitions}}?';
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, $prefix) !== 0) continue;
		parse_str(substr($href, strlen($prefix)), $q);
		$out[] = $q;
	}
	return $out;
}

function test_museum_exhibitions_pages_and_widgets()
{
	$d = _test_mzx_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('exhibitions', 'exhibition', 'exhibition_new'), array_keys($d['pages']));
	pg_assert_same(array('exhibition_form', 'exhibitions_list', 'home_exhibitions', 'exhibition_detail'), array_keys($d['widgets']));
	foreach (array('exhibitions', 'exhibition') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	pg_assert_same('private', $d['pages']['exhibition_new']['folder'], 'only the staff add an exhibition');
	pg_assert_true(!empty($d['pages']['exhibition_new']['noindex']), 'the staff page is not indexed');
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
	pg_assert_contains('"templateWidget":"home_exhibitions"', json_encode($home['tree']), 'the home places the exhibitions on now');
}

// The form is a collection with a sample per exhibition; every sample value
// has its field and is filled, a pick list's value is one of its answers,
// the photos are images.
function test_museum_exhibitions_form_and_samples()
{
	$d = _test_mzx_load();
	$form = $d['pages']['exhibition_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same(count($d['exhibitions']), count($form['sample_records']), 'one sample per exhibition');
	$controls = _test_mzx_controls($d['widgets']['exhibition_form']['tree']);
	$fields = array('title', 'status', 'when', 'gallery', 'curator', 'summary', 'details', 'photo_1', 'photo_2', 'photo_3');
	pg_assert_same($fields, array_keys($controls), 'the fields of the form');
	foreach (array('photo_1', 'photo_2', 'photo_3') as $f) pg_assert_same('image', $controls[$f]['cf']['kind'] ?? '', "$f is an image");
	pg_assert_true(isset($controls[$form['share_image_field']]), 'the share image is a field');
	pg_assert_same($d['statuses'], $controls['status']['options'], 'the three states');
	$states = array();
	foreach ($form['sample_records'] as $n => $r) {
		pg_assert_same($fields, array_keys($r), "sample $n: every field");
		foreach ($r as $name => $value) {
			pg_assert_true($value !== '', "sample $n: $name filled");
			if ($controls[$name]['tag'] === 'select') {
				pg_assert_true(in_array($value, $controls[$name]['options'], true), "sample $n: $name '$value' is an answer");
			}
			pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $controls[$name]['tag'] === 'input' && strpos($name, 'photo_') === 0 ? '' : $value), "sample $n: $name names no year");
		}
		$key = array_keys($d['exhibitions'])[$n];
		pg_assert_same(implode("\n", $d['exhibitions'][$key]['details']), $r['details'], "sample $n: its details, a line per item");
		$states[$r['status']] = true;
	}
	pg_assert_same(3, count($states), 'samples in every state');
}

// The list reads the parameter the filter row and the footer send, each an
// answer of the Status list; the home lists the exhibitions on now.
function test_museum_exhibitions_filters()
{
	$d = _test_mzx_load();
	$params = array();
	foreach ($d['widgets']['exhibitions_list']['config']['filters'] as $f) {
		pg_assert_same('url parameter', $f['dynamic_value'], 'the filter reads the address');
		$params[$f['field']] = $f['dynamic_value_attribute'];
	}
	pg_assert_same(array('status' => 'status'), $params, 'the list filters the state');
	$seen = 0;
	foreach (array($d['pages']['exhibitions']['tree'], $d['footer']) as $tree) {
		foreach (_test_mzx_queries($tree) as $q) {
			pg_assert_same(array('status'), array_keys($q), 'only ?status=');
			pg_assert_true(in_array($q['status'], $d['statuses'], true), "?status={$q['status']} is an answer");
			$seen++;
		}
	}
	pg_assert_same(count($d['statuses']) * 2, $seen, 'a state link in the filter row and the footer each');
	$c = $d['widgets']['home_exhibitions']['config'];
	pg_assert_same(array(array('field' => 'status', 'operator' => 'is equal to', 'value' => $d['status_titles']['now'], 'dynamic_value' => '')), $c['filters'], 'the ones on now');
	pg_assert_same(3, $c['max_results'], 'three at most');
	foreach (array('exhibitions_list', 'home_exhibitions') as $key) {
		pg_assert_same('{{tab:exhibition_new}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key: the Exhibitions form");
		pg_assert_same('{{tab:exhibition}}', $d['widgets'][$key]['config']['detail_page_id'], "$key: the exhibition page");
	}
}

// The exhibition page shows the record or the not-found note, opens the
// list on its state and books a timed entry.
function test_museum_exhibitions_record_page()
{
	$d = _test_mzx_load();
	$nodes = array();
	_test_mzx_nodes($d['widgets']['exhibition_detail']['tree'], $nodes);
	$links = array();
	$bound = array();
	foreach ($nodes as $n) {
		if (($n['props']['_bindings']['href'] ?? '') === '__page_link') $links[] = $n['props']['_bindParams']['href'];
		foreach (array('text', 'src') as $k) if (isset($n['props']['_bindings'][$k])) $bound[] = $n['props']['_bindings'][$k];
	}
	pg_assert_same(array(
		array('page_id' => '{{tab:exhibitions}}', 'params' => array(array('name' => 'status', 'token' => 'status'))),
	), $links, 'the list on its state');
	foreach (array('title', 'status', 'when', 'gallery', 'curator', 'summary', 'details', 'photo_1', 'photo_2', 'photo_3') as $f) {
		pg_assert_true(in_array($f, $bound, true), "the page shows $f");
	}
	$json = json_encode($d['widgets']['exhibition_detail']['tree']);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the record');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
	pg_assert_contains('"eo_visible_if":"can_edit"', $json, 'the Edit button');
	pg_assert_contains('{{page:book_online}}', $json, 'a timed entry');
}

function test_museum_exhibitions_links_ids_and_classes()
{
	$d = _test_mzx_load();
	$known = array_flip(_test_mz_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('tickets', 'membership', 'product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$nodes = array();
	foreach ($d['pages'] as $p) _test_mzx_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_mzx_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(ptv|pht|pre|pev|pgy|pch|plw|pwd|pph|pcn|pmu|psc|psh)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'mz-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; an exhibition reads "sergi".
function test_museum_exhibitions_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-museum/exhibitions.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		if (preg_match('/\bExhibitions?\b/', $key) && strpos($key, '-') === false) pg_assert_true(mb_stripos($map[$key], 'sergi', 0, 'UTF-8') !== false, "'$key' says sergi");
	}
}
