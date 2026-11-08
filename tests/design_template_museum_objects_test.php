<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Museum design template's collection highlights
 * (includes/design_templates/pinegrap-museum/objects.php): the pages and
 * widgets, the Collection Highlights collection with a sample per object
 * whose values fit their fields, the list's filter reading what the filter
 * row, the department cards and the footer send, the featured list, the
 * object page opens the list on its department, every page link names a
 * template page, ids and classes are the template's own, and every literal
 * has its Turkish.
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

// The highlights' part as the template file reads it: parts.php, the
// public pages and exhibitions.php first, then objects.php in the same
// scope. Read once per run.
function _test_mzo_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-museum';
	include $dir . '/parts.php';
	include $dir . '/pages_public.php';
	include $dir . '/exhibitions.php';
	$part = include $dir . '/objects.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'objects' => $objects,
		'departments' => $department_titles, 'featured' => $mz_featured_choices, 'footer' => $footer());
	return $out;
}

// Every node of a tree, depth first.
function _test_mzo_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_mzo_nodes($c, $out);
}

// The name and options of every form control of a tree.
function _test_mzo_controls($node)
{
	$nodes = array();
	_test_mzo_nodes($node, $nodes);
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

// The query of every link to {{page:collection}} with a query in a tree.
function _test_mzo_queries($tree)
{
	$nodes = array();
	_test_mzo_nodes($tree, $nodes);
	$out = array();
	$prefix = '{{page:collection}}?';
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, $prefix) !== 0) continue;
		parse_str(substr($href, strlen($prefix)), $q);
		$out[] = $q;
	}
	return $out;
}

function test_museum_objects_pages_and_widgets()
{
	$d = _test_mzo_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('collection', 'object', 'object_new'), array_keys($d['pages']));
	pg_assert_same(array('object_form', 'collection_featured', 'collection_list', 'object_detail'), array_keys($d['widgets']));
	foreach (array('collection', 'object') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	pg_assert_same('private', $d['pages']['object_new']['folder'], 'only the staff add a highlight');
	pg_assert_true(!empty($d['pages']['object_new']['noindex']), 'the staff page is not indexed');
	foreach ($d['pages'] as $key => $p) {
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of this part");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
}

// The form is a collection with a sample per object; every sample value
// has its field and is filled, a pick list's value is one of its answers,
// the photo is an image and Featured is the museum's own field.
function test_museum_objects_form_and_samples()
{
	$d = _test_mzo_load();
	$form = $d['pages']['object_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same(count($d['objects']), count($form['sample_records']), 'one sample per object');
	$controls = _test_mzo_controls($d['widgets']['object_form']['tree']);
	$fields = array('title', 'department', 'maker', 'period', 'material', 'gallery', 'story', 'photo_1', 'featured');
	pg_assert_same($fields, array_keys($controls), 'the fields of the form');
	pg_assert_same('image', $controls['photo_1']['cf']['kind'] ?? '', 'the photo is an image');
	pg_assert_true(isset($controls[$form['share_image_field']]), 'the share image is a field');
	pg_assert_same($d['departments'], $controls['department']['options'], 'the six departments');
	pg_assert_same($d['featured'], $controls['featured']['options'], 'Featured: yes or no');
	pg_assert_true(!empty($controls['featured']['cf']['office_use_only']), 'Featured is office use only');
	$departments = array();
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
		$departments[$r['department']] = true;
		if ($r['featured'] === $d['featured'][0]) $featured++;
	}
	pg_assert_same(count($d['objects']), count($departments), 'each sample in its own department');
	pg_assert_true($featured >= 3, 'enough featured samples for the featured row');
}

// The list reads the parameter the filter row, the department cards and
// the footer send, each an answer of the Department list; the featured row
// shows the featured highlights.
function test_museum_objects_filters()
{
	$d = _test_mzo_load();
	$params = array();
	foreach ($d['widgets']['collection_list']['config']['filters'] as $f) {
		pg_assert_same('url parameter', $f['dynamic_value'], 'the filter reads the address');
		$params[$f['field']] = $f['dynamic_value_attribute'];
	}
	pg_assert_same(array('department' => 'department'), $params, 'the list filters the department');
	$seen = 0;
	foreach (array($d['pages']['collection']['tree'], $d['footer']) as $tree) {
		foreach (_test_mzo_queries($tree) as $q) {
			pg_assert_same(array('department'), array_keys($q), 'only ?department=');
			pg_assert_true(in_array($q['department'], $d['departments'], true), "?department={$q['department']} is an answer");
			$seen++;
		}
	}
	pg_assert_same(count($d['departments']) * 3 + 1, $seen, 'a department link in the filter row, the cards and the footer each, and the look-closer work\'s');
	$c = $d['widgets']['collection_featured']['config'];
	pg_assert_same(array(array('field' => 'featured', 'operator' => 'is equal to', 'value' => $d['featured'][0], 'dynamic_value' => '')), $c['filters'], 'the featured ones');
	pg_assert_same(3, $c['max_results'], 'three at most');
	foreach (array('collection_list', 'collection_featured') as $key) {
		pg_assert_same('{{tab:object_new}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key: the Collection Highlights form");
		pg_assert_same('{{tab:object}}', $d['widgets'][$key]['config']['detail_page_id'], "$key: the object page");
	}
}

// The object page shows the record or the not-found note and opens the
// list on its department.
function test_museum_objects_record_page()
{
	$d = _test_mzo_load();
	$nodes = array();
	_test_mzo_nodes($d['widgets']['object_detail']['tree'], $nodes);
	$links = array();
	$bound = array();
	foreach ($nodes as $n) {
		if (($n['props']['_bindings']['href'] ?? '') === '__page_link') $links[] = $n['props']['_bindParams']['href'];
		foreach (array('text', 'src') as $k) if (isset($n['props']['_bindings'][$k])) $bound[] = $n['props']['_bindings'][$k];
	}
	pg_assert_same(array(
		array('page_id' => '{{tab:collection}}', 'params' => array(array('name' => 'department', 'token' => 'department'))),
	), $links, 'the list on its department');
	foreach (array('title', 'department', 'maker', 'period', 'material', 'gallery', 'story', 'photo_1') as $f) {
		pg_assert_true(in_array($f, $bound, true), "the page shows $f");
	}
	pg_assert_false(in_array('featured', $bound, true), 'the page keeps Featured to the museum');
	$json = json_encode($d['widgets']['object_detail']['tree']);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the record');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
	pg_assert_contains('"eo_visible_if":"can_edit"', $json, 'the Edit button');
}

function test_museum_objects_links_ids_and_classes()
{
	$d = _test_mzo_load();
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
	foreach ($d['pages'] as $p) _test_mzo_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_mzo_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(ptv|pht|pre|pev|pgy|pch|plw|pwd|pph|pcn|pmu|psc|psh)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'mz-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; the collection reads "koleksiyon", a work
// or a highlight "eser".
function test_museum_objects_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-museum/objects.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || strpos($key, '-') !== false) continue;
		if (preg_match('/\bCollection\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'koleksiyon', 0, 'UTF-8') !== false, "'$key' says koleksiyon");
		if (preg_match('/\b(Highlights?|Work)\b/', $key) && !preg_match('/\bCollection\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'eser', 0, 'UTF-8') !== false, "'$key' says eser");
	}
}
