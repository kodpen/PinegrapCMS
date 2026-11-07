<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Marina design template's charter boats
 * (includes/design_templates/pinegrap-marina/charters.php): the pages and
 * widgets, the Charter Boats collection with a sample per boat whose
 * values fit their fields, the list's filters reading what the filter bar
 * and the footer send, the home lists the featured boats, the boat page
 * opens the charter enquiry with the boat chosen and the list on its
 * type, every page link names a template page, ids and classes are the
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

// The page keys of the finished template (_test_mn_page_keys()).
require_once(__DIR__ . '/design_template_marina_test.php');

// The charters' part as the template file reads it: parts.php first,
// then the public pages and charters.php in the same scope. Read once per run.
function _test_mnc_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-marina';
	include $dir . '/parts.php';
	$public = include $dir . '/pages_public.php';
	$part = include $dir . '/charters.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'boats' => $boats,
		'type_titles' => $type_titles, 'skipper_choices' => $skipper_choices, 'charter_boat_choices' => $charter_boat_choices,
		'features_text' => $features_text, 'public' => $public, 'footer' => $footer());
	return $out;
}

// Every node of a tree, depth first.
function _test_mnc_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_mnc_nodes($c, $out);
}

// The name, options and settings of every form control of a tree.
function _test_mnc_controls($node)
{
	$nodes = array();
	_test_mnc_nodes($node, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$tag = $n['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$name = '';
		$type = '';
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

// The query of every link to {{page:charters}} in a tree.
function _test_mnc_list_queries($tree)
{
	$nodes = array();
	_test_mnc_nodes($tree, $nodes);
	$out = array();
	$prefix = '{{page:charters}}?';
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, $prefix) !== 0) continue;
		parse_str(substr($href, strlen($prefix)), $q);
		$out[] = $q;
	}
	return $out;
}

function test_marina_charters_pages_and_widgets()
{
	$d = _test_mnc_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('charters', 'boat', 'boat_new'), array_keys($d['pages']));
	pg_assert_same(array('boat_form', 'charters_list', 'home_charters', 'boat_detail'), array_keys($d['widgets']));
	foreach (array('charters', 'boat') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	pg_assert_same('private', $d['pages']['boat_new']['folder'], 'only the staff add a boat');
	pg_assert_true(!empty($d['pages']['boat_new']['noindex']), 'the staff page is not indexed');
	$slugs = array();
	foreach ($d['public']['pages'] as $p) $slugs[$p['name']] = $p['key'];
	foreach ($d['pages'] as $key => $p) {
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the address is a slug");
		pg_assert_false(isset($slugs[$p['name']]), "$key: its address is not a public page's");
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true($w['page'] === 'home' || isset($d['pages'][$w['page']]), "$key sits on a page of this part or the home");
		if ($w['page'] !== 'home') {
			pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
		}
	}
	// The home page places home_charters, which this part defines.
	$home = null;
	foreach ($d['public']['pages'] as $p) if ($p['key'] === 'home') $home = $p;
	pg_assert_contains('"templateWidget":"home_charters"', json_encode($home['tree']), 'the home places the featured boats');
	pg_assert_same('home', $d['widgets']['home_charters']['page']);
}

// The form is a collection with a sample per boat; every sample value has
// its field and is filled, a pick list's value is one of its answers,
// length, guests and cabins are numbers, the photos are images and
// featured is the staff's. The card's bindings read fields of this form.
function test_marina_charters_form_and_samples()
{
	$d = _test_mnc_load();
	$form = $d['pages']['boat_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same(count($d['boats']), count($form['sample_records']), 'one sample per boat');
	$controls = _test_mnc_controls($d['widgets']['boat_form']['tree']);
	$fields = array('title', 'type', 'skipper', 'length', 'guests', 'cabins', 'price', 'summary', 'description', 'features', 'photo_1', 'photo_2', 'photo_3', 'featured');
	pg_assert_same($fields, array_keys($controls), 'the fields of the form');
	foreach (array('photo_1', 'photo_2', 'photo_3') as $name) pg_assert_same('image', $controls[$name]['cf']['kind'] ?? '', "$name is an image");
	foreach (array('length', 'guests', 'cabins') as $name) {
		pg_assert_same('number', $controls[$name]['cf']['kind'] ?? '', "$name is a number kind");
		pg_assert_same('number', $controls[$name]['type'], "$name is a number input");
	}
	pg_assert_true(!empty($controls['featured']['cf']['office_use_only']), 'featured is the staff\'s');
	pg_assert_true(isset($controls[$form['share_image_field']]), 'the share image is a field');
	pg_assert_same($d['type_titles'], $controls['type']['options'], 'the types');
	pg_assert_same($d['skipper_choices'], $controls['skipper']['options'], 'the skipper answers');
	$featured = 0;
	foreach ($form['sample_records'] as $n => $r) {
		pg_assert_same($fields, array_keys($r), "sample $n: every field");
		foreach ($r as $name => $value) {
			pg_assert_true($value !== '', "sample $n: $name filled");
			if ($controls[$name]['tag'] === 'select') {
				pg_assert_true(in_array($value, $controls[$name]['options'], true), "sample $n: $name '$value' is an answer");
			}
			pg_assert_false((bool)preg_match('/(19|20)[0-9]{2}/', $value), "sample $n: $name names no year");
		}
		foreach (array('length', 'guests', 'cabins') as $name) pg_assert_true(ctype_digit($r[$name]), "sample $n: $name is a whole number");
		// The boat page opens the charter enquiry with the boat's name
		// chosen, so the name is an answer of the enquiry's list.
		pg_assert_true(in_array($r['title'], $d['charter_boat_choices'], true), "sample $n: its name is a charter enquiry answer");
		$key = array_keys($d['boats'])[$n];
		pg_assert_same(call_user_func($d['features_text'], $key), $r['features'], "sample $n: its features, a line per item");
		if ($r['featured'] === lang('Yes')) $featured++;
	}
	pg_assert_same(3, $featured, 'three featured samples');
	pg_assert_true($featured <= $d['widgets']['home_charters']['config']['max_results'], 'the home shows every featured sample');
	// The card in the lists reads fields of the form.
	$nodes = array();
	_test_mnc_nodes($d['widgets']['charters_list']['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach ((array)($n['props']['_bindings'] ?? array()) as $token) {
			if ($token === 'form_item_view') continue;
			pg_assert_true(isset($controls[$token]), "the card reads the field $token");
		}
	}
}

// The list reads the parameters the filter bar and the footer send, each
// an answer of its pick list; the home lists the featured boats.
function test_marina_charters_filters()
{
	$d = _test_mnc_load();
	$params = array();
	foreach ($d['widgets']['charters_list']['config']['filters'] as $f) {
		pg_assert_same('url parameter', $f['dynamic_value'], 'the filter reads the address');
		$params[$f['field']] = $f['dynamic_value_attribute'];
	}
	pg_assert_same(array('type' => 'type', 'skipper' => 'skipper'), $params, 'the list filters type and skipper');
	$answers = array('type' => $d['type_titles'], 'skipper' => $d['skipper_choices']);
	$trees = array($d['pages']['charters']['tree'], $d['footer']);
	foreach ($d['public']['pages'] as $p) $trees[] = $p['tree'];
	$seen = array('type' => 0, 'skipper' => 0);
	foreach ($trees as $tree) {
		foreach (_test_mnc_list_queries($tree) as $q) {
			foreach ($q as $param => $value) {
				pg_assert_true(isset($answers[$param]), "?$param is a filter of the list");
				if (!isset($answers[$param])) continue;
				pg_assert_true(in_array($value, $answers[$param], true), "?$param=$value is an answer");
				$seen[$param]++;
			}
		}
	}
	pg_assert_same(count($d['type_titles']) * 2, $seen['type'], 'type links: the filter bar and the footer');
	pg_assert_same(count($d['skipper_choices']), $seen['skipper'], 'skipper links: the filter bar');
	$c = $d['widgets']['home_charters']['config'];
	pg_assert_same(array(array('field' => 'featured', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => '')), $c['filters'], 'the featured ones');
	foreach (array('charters_list', 'home_charters') as $key) {
		pg_assert_same('{{tab:boat_new}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key: the Charter Boats form");
		pg_assert_same('{{tab:boat}}', $d['widgets'][$key]['config']['detail_page_id'], "$key: the boat page");
	}
}

// The boat page shows the record or the not-found note, opens the
// charter enquiry with the boat chosen, and the list on its type.
function test_marina_charters_record_page()
{
	$d = _test_mnc_load();
	$nodes = array();
	_test_mnc_nodes($d['widgets']['boat_detail']['tree'], $nodes);
	$links = array();
	$bound = array();
	foreach ($nodes as $n) {
		if (($n['props']['_bindings']['href'] ?? '') === '__page_link') $links[] = $n['props']['_bindParams']['href'];
		foreach (array('text', 'src') as $k) if (isset($n['props']['_bindings'][$k])) $bound[] = $n['props']['_bindings'][$k];
	}
	pg_assert_same(array(
		array('page_id' => '{{tab:charter_enquiry}}', 'params' => array(array('name' => 'boat', 'token' => 'title'))),
		array('page_id' => '{{tab:charters}}', 'params' => array(array('name' => 'type', 'token' => 'type'))),
	), $links, 'the charter enquiry on the boat, the list on its type');
	foreach (array('title', 'type', 'skipper', 'length', 'guests', 'cabins', 'price', 'summary', 'description', 'features', 'photo_1', 'photo_2', 'photo_3') as $f) {
		pg_assert_true(in_array($f, $bound, true), "the page shows $f");
	}
	$json = json_encode($d['widgets']['boat_detail']['tree']);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the record');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
	pg_assert_contains('"eo_visible_if":"can_edit"', $json, 'the Edit button');
}

function test_marina_charters_links_ids_and_classes()
{
	$d = _test_mnc_load();
	$known = array_flip(_test_mn_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
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
	}
}

// Every literal has its Turkish; a charter enquiry reads "kiralama
// talebi" and a charter boat "kiralık tekne".
function test_marina_charters_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-marina/charters.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (strpos($key, '-') !== false && preg_match('/^[a-z-]+$/', $key)) continue;
		if (preg_match('/charter enquiry/i', $key)) pg_assert_true(mb_stripos($tr, 'kiralama tale', 0, 'UTF-8') !== false, "'$key' says kiralama talebi");
		if (preg_match('/Charter Boats?/', $key)) pg_assert_true(mb_stripos($tr, 'kiralık tekne', 0, 'UTF-8') !== false, "'$key' says kiralık tekne");
	}
}
