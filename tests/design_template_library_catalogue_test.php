<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Public Library design template's catalogue
 * (includes/design_templates/pinegrap-library/catalogue.php): its pages
 * and widgets, the Catalogue form as a collection with a sample per title
 * whose pick list values are answers of their lists, the list filters on
 * the format and audience every link sends, the home's new arrivals, the
 * title page reserves the title and opens its format, links name template
 * pages, ids and classes are the template's own, and every literal has
 * its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The parts' helpers: _test_lb_nodes() and _test_lb_page_keys().
require_once __DIR__ . '/design_template_library_test.php';

// The catalogue's part as the template file reads it: parts.php first,
// then the public pages and catalogue.php in the same scope. Read once.
function _test_lbc_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-library';
	include $dir . '/parts.php';
	$public = include $dir . '/pages_public.php';
	$part = include $dir . '/catalogue.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'items' => $catalogue_items,
		'lists' => array('format' => $format_titles, 'audience' => $audience_titles, 'branch' => $branch_titles, 'availability' => $availability_choices),
		'public' => $public, 'footer' => $footer());
	return $out;
}

// The name, options and form settings of every form control of a tree.
function _test_lbc_controls($node)
{
	$nodes = array();
	_test_lb_nodes($node, $nodes);
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

function test_library_catalogue_pages_and_widgets()
{
	$d = _test_lbc_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('catalogue', 'item', 'item_new'), array_keys($d['pages']));
	pg_assert_same(array('item_form', 'catalogue_list', 'home_arrivals', 'item_detail'), array_keys($d['widgets']));
	foreach (array('catalogue', 'item') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	pg_assert_same('private', $d['pages']['item_new']['folder'], 'only the staff add a title');
	pg_assert_true(!empty($d['pages']['item_new']['noindex']), 'the staff page is not indexed');
	$names = array();
	foreach ($d['public']['pages'] as $p) $names[$p['name']] = true;
	foreach ($d['pages'] as $key => $p) {
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the address is a slug");
		pg_assert_false(isset($names[$p['name']]), "$key: its address is its own");
		$names[$p['name']] = true;
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true($w['page'] === 'home' || isset($d['pages'][$w['page']]), "$key sits on a page of this part or the home");
		if ($w['page'] !== 'home') {
			pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
		}
	}
	$home = null;
	foreach ($d['public']['pages'] as $p) if ($p['key'] === 'home') $home = $p;
	pg_assert_contains('"templateWidget":"home_arrivals"', json_encode($home['tree']), 'the home places the new arrivals');
	pg_assert_same('home', $d['widgets']['home_arrivals']['page']);
}

// The form is a collection with a sample per title; every sample value has
// its field and is filled, a pick list's value is one of its answers, the
// cover is an image and new arrival is the staff's.
function test_library_catalogue_form_and_samples()
{
	$d = _test_lbc_load();
	$form = $d['pages']['item_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same(count($d['items']), count($form['sample_records']), 'one sample per title');
	$controls = _test_lbc_controls($d['widgets']['item_form']['tree']);
	$fields = array('title', 'author', 'format', 'audience', 'genre', 'shelf', 'branch', 'availability', 'summary', 'photo_1', 'new_arrival');
	pg_assert_same($fields, array_keys($controls), 'the fields of the form');
	pg_assert_same('image', $controls['photo_1']['cf']['kind'] ?? '', 'the cover is an image');
	pg_assert_true(!empty($controls['new_arrival']['cf']['office_use_only']), 'new arrival is the staff\'s');
	pg_assert_true(isset($controls[$form['share_image_field']]), 'the share image is a field');
	foreach ($d['lists'] as $name => $list) pg_assert_same($list, $controls[$name]['options'], "$name: the answers of parts.php");
	$new = 0;
	foreach ($form['sample_records'] as $n => $r) {
		pg_assert_same($fields, array_keys($r), "sample $n: every field, in the form's order");
		foreach ($r as $name => $value) {
			pg_assert_true($value !== '', "sample $n: $name filled");
			if ($controls[$name]['tag'] === 'select') {
				pg_assert_true(in_array($value, $controls[$name]['options'], true), "sample $n: $name '$value' is an answer");
			}
			pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $value), "sample $n: $name names no year");
		}
		if ($r['new_arrival'] === lang('Yes')) $new++;
	}
	pg_assert_same(4, $new, 'four new arrivals among the samples');
}

// The list reads the parameters the filter rows, the public pages and the
// footer send, each an answer of its list; the home lists the new
// arrivals, six at most.
function test_library_catalogue_filters()
{
	$d = _test_lbc_load();
	$params = array();
	foreach ($d['widgets']['catalogue_list']['config']['filters'] as $f) {
		pg_assert_same('url parameter', $f['dynamic_value'], 'the filter reads the address');
		$params[$f['field']] = $f['dynamic_value_attribute'];
	}
	pg_assert_same(array('format' => 'format', 'audience' => 'audience'), $params, 'the list filters format and audience');
	$trees = array($d['pages']['catalogue']['tree'], $d['footer']);
	foreach ($d['public']['pages'] as $p) $trees[] = $p['tree'];
	$seen = array();
	foreach ($trees as $tree) {
		$nodes = array();
		_test_lb_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			$href = (string)($n['props']['href'] ?? '');
			if (strpos($href, '{{page:catalogue}}?') !== 0) continue;
			parse_str(substr($href, strlen('{{page:catalogue}}?')), $q);
			pg_assert_same(1, count($q), 'one filter per link');
			pg_assert_true(isset($params[key($q)]), '?' . key($q) . '= is a filter of the list');
			pg_assert_true(in_array(current($q), $d['lists'][key($q)], true), '?' . key($q) . '=' . current($q) . ' is an answer');
			$seen[key($q)][current($q)] = true;
		}
	}
	pg_assert_same(count($d['lists']['format']), count($seen['format'] ?? array()), 'a link per format');
	pg_assert_same(count($d['lists']['audience']), count($seen['audience'] ?? array()), 'a link per audience');
	$c = $d['widgets']['home_arrivals']['config'];
	pg_assert_same(array(array('field' => 'new_arrival', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => '')), $c['filters'], 'the new arrivals');
	pg_assert_same(6, $c['max_results'], 'six at most');
	foreach (array('catalogue_list', 'home_arrivals') as $key) {
		pg_assert_same('{{tab:item_new}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key: the Catalogue form");
		pg_assert_same('{{tab:item}}', $d['widgets'][$key]['config']['detail_page_id'], "$key: the title page");
	}
	pg_assert_true(!empty($d['widgets']['catalogue_list']['config']['search_enabled']), 'the catalogue is searchable');
}

// The title page shows the record or the not-found note, reserves the
// title (?title=) and opens the catalogue on its format.
function test_library_catalogue_record_page()
{
	$d = _test_lbc_load();
	$nodes = array();
	_test_lb_nodes($d['widgets']['item_detail']['tree'], $nodes);
	$links = array();
	$bound = array();
	foreach ($nodes as $n) {
		if (($n['props']['_bindings']['href'] ?? '') === '__page_link') $links[] = $n['props']['_bindParams']['href'];
		foreach (array('text', 'src') as $k) if (isset($n['props']['_bindings'][$k])) $bound[] = $n['props']['_bindings'][$k];
	}
	pg_assert_same(array(
		array('page_id' => '{{tab:reserve}}', 'params' => array(array('name' => 'title', 'token' => 'title'))),
		array('page_id' => '{{tab:catalogue}}', 'params' => array(array('name' => 'format', 'token' => 'format'))),
	), $links, 'reserve the title, more in its format');
	foreach (array('title', 'author', 'format', 'audience', 'genre', 'shelf', 'branch', 'availability', 'summary', 'photo_1') as $f) {
		pg_assert_true(in_array($f, $bound, true), "the page shows $f");
	}
	$json = json_encode($d['widgets']['item_detail']['tree']);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the record');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
	pg_assert_contains('"eo_visible_if":"can_edit"', $json, 'the Edit button');
	pg_assert_contains('plb-cover', $json, 'the cover keeps its shape');
}

function test_library_catalogue_links_ids_and_classes()
{
	$d = _test_lbc_load();
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
	foreach ($d['pages'] as $p) _test_lb_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_lb_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(ptv|pht|pre|pev|pgy|pch|plw|pwd|pph|pcn|pmu|psc|psh|pmz)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'lib-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; the catalogue reads "katalog".
function test_library_catalogue_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-library/catalogue.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		if (preg_match('/\bCatalogue\b/i', $key)) pg_assert_true(mb_stripos($map[$key], 'katalo', 0, 'UTF-8') !== false, "'$key' says katalog");
	}
}
