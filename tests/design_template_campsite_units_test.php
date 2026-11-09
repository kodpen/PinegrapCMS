<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Campsite design template's pitches and cabins
 * (includes/design_templates/pinegrap-campsite/units.php): the pages and
 * widgets, the Pitches and Cabins collection with a sample per unit whose
 * values fit their fields, the list's filters reading what the filter bar,
 * the stay-kind pages and the footer send, the home lists the featured
 * units, the unit page opens the stay request with the place and its kind
 * chosen and the list on its kind, every page link names a template page,
 * ids and classes are the template's own, and every literal has its
 * Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_cp_page_keys()).
require_once(__DIR__ . '/design_template_campsite_test.php');

// The units' part as the template file reads it: parts.php first, then
// the public pages and units.php in the same scope. Read once per run.
function _test_cpu_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-campsite';
	include $dir . '/parts.php';
	$public = include $dir . '/pages_public.php';
	$part = include $dir . '/units.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'units' => $units,
		'kind_titles' => $kind_titles, 'setting_titles' => $setting_titles, 'hookup_choices' => $hookup_choices,
		'request_kind_choices' => $request_kind_choices, 'request_unit_choices' => $request_unit_choices,
		'features_text' => $features_text, 'public' => $public, 'footer' => $footer());
	return $out;
}

// Every node of a tree, depth first.
function _test_cpu_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_cpu_nodes($c, $out);
}

// The name, options and settings of every form control of a tree.
function _test_cpu_controls($node)
{
	$nodes = array();
	_test_cpu_nodes($node, $nodes);
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

// The query of every link to {{page:units}} in a tree.
function _test_cpu_unit_queries($tree)
{
	$nodes = array();
	_test_cpu_nodes($tree, $nodes);
	$out = array();
	$prefix = '{{page:units}}?';
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, $prefix) !== 0) continue;
		parse_str(substr($href, strlen($prefix)), $q);
		$out[] = $q;
	}
	return $out;
}

function test_campsite_units_pages_and_widgets()
{
	$d = _test_cpu_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('units', 'unit', 'unit_new'), array_keys($d['pages']));
	pg_assert_same(array('unit_form', 'units_list', 'home_units', 'unit_detail'), array_keys($d['widgets']));
	foreach (array('units', 'unit') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	pg_assert_same('private', $d['pages']['unit_new']['folder'], 'only the staff add a unit');
	pg_assert_true(!empty($d['pages']['unit_new']['noindex']), 'the staff page is not indexed');
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
	// The home page places home_units, which this part defines.
	$home = null;
	foreach ($d['public']['pages'] as $p) if ($p['key'] === 'home') $home = $p;
	pg_assert_contains('"templateWidget":"home_units"', json_encode($home['tree']), 'the home places the featured units');
	pg_assert_same('home', $d['widgets']['home_units']['page']);
}

// The form is a collection with a sample per unit; every sample value has
// its field and is filled, a pick list's value is one of its answers,
// sleeps and size are numbers, the photos are images and featured is the
// staff's. The card's bindings read fields of this form.
function test_campsite_units_form_and_samples()
{
	$d = _test_cpu_load();
	$form = $d['pages']['unit_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same(count($d['units']), count($form['sample_records']), 'one sample per unit');
	$controls = _test_cpu_controls($d['widgets']['unit_form']['tree']);
	$fields = array('title', 'kind', 'setting', 'sleeps', 'size', 'hookup', 'price', 'summary', 'description', 'features', 'photo_1', 'photo_2', 'photo_3', 'featured');
	pg_assert_same($fields, array_keys($controls), 'the fields of the form');
	foreach (array('photo_1', 'photo_2', 'photo_3') as $name) pg_assert_same('image', $controls[$name]['cf']['kind'] ?? '', "$name is an image");
	foreach (array('sleeps', 'size') as $name) {
		pg_assert_same('number', $controls[$name]['cf']['kind'] ?? '', "$name is a number kind");
		pg_assert_same('number', $controls[$name]['type'], "$name is a number input");
	}
	pg_assert_true(!empty($controls['featured']['cf']['office_use_only']), 'featured is the staff\'s');
	pg_assert_true(isset($controls[$form['share_image_field']]), 'the share image is a field');
	pg_assert_same($d['kind_titles'], $controls['kind']['options'], 'the kinds');
	pg_assert_same($d['setting_titles'], $controls['setting']['options'], 'the settings');
	pg_assert_same($d['hookup_choices'], $controls['hookup']['options'], 'the hook-up answers');
	// The record's kind and name are answers of the stay request's lists,
	// so the unit page opens the request with them chosen.
	foreach ($d['kind_titles'] as $k) pg_assert_true(in_array($k, $d['request_kind_choices'], true), "$k is a stay request answer");
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
		foreach (array('sleeps', 'size') as $name) pg_assert_true(ctype_digit($r[$name]), "sample $n: $name is a whole number");
		pg_assert_true(in_array($r['title'], $d['request_unit_choices'], true), "sample $n: its name is a stay request answer");
		$key = array_keys($d['units'])[$n];
		pg_assert_same(call_user_func($d['features_text'], $key), $r['features'], "sample $n: its features, a line per item");
		if ($r['featured'] === lang('Yes')) $featured++;
	}
	pg_assert_same(4, $featured, 'four featured samples');
	pg_assert_true($featured <= $d['widgets']['home_units']['config']['max_results'], 'the home shows every featured sample');
	// The card in the lists reads fields of the form.
	$nodes = array();
	_test_cpu_nodes($d['widgets']['units_list']['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach ((array)($n['props']['_bindings'] ?? array()) as $token) {
			if ($token === 'form_item_view') continue;
			pg_assert_true(isset($controls[$token]), "the card reads the field $token");
		}
	}
}

// The list reads the parameters the filter bar, the stay-kind pages and
// the footer send, each an answer of its pick list; the home lists the
// featured units.
function test_campsite_units_filters()
{
	$d = _test_cpu_load();
	$params = array();
	foreach ($d['widgets']['units_list']['config']['filters'] as $f) {
		pg_assert_same('url parameter', $f['dynamic_value'], 'the filter reads the address');
		$params[$f['field']] = $f['dynamic_value_attribute'];
	}
	pg_assert_same(array('kind' => 'kind', 'setting' => 'setting'), $params, 'the list filters kind and setting');
	$answers = array('kind' => $d['kind_titles'], 'setting' => $d['setting_titles']);
	$trees = array($d['pages']['units']['tree'], $d['footer']);
	foreach ($d['public']['pages'] as $p) $trees[] = $p['tree'];
	$seen = array('kind' => 0, 'setting' => 0);
	foreach ($trees as $tree) {
		foreach (_test_cpu_unit_queries($tree) as $q) {
			foreach ($q as $param => $value) {
				pg_assert_true(isset($answers[$param]), "?$param is a filter of the list");
				if (!isset($answers[$param])) continue;
				pg_assert_true(in_array($value, $answers[$param], true), "?$param=$value is an answer");
				$seen[$param]++;
			}
		}
	}
	pg_assert_true($seen['kind'] >= count($d['kind_titles']) * 3, 'kind links: the filter bar, the footer, the stay-kind pages');
	pg_assert_same(count($d['setting_titles']), $seen['setting'], 'setting links: the filter bar');
	$c = $d['widgets']['home_units']['config'];
	pg_assert_same(array(array('field' => 'featured', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => '')), $c['filters'], 'the featured ones');
	foreach (array('units_list', 'home_units') as $key) {
		pg_assert_same('{{tab:unit_new}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key: the Pitches and Cabins form");
		pg_assert_same('{{tab:unit}}', $d['widgets'][$key]['config']['detail_page_id'], "$key: the unit page");
	}
}

// The unit page shows the record or the not-found note, opens the stay
// request with the place and its kind chosen, and the list on its kind.
function test_campsite_units_record_page()
{
	$d = _test_cpu_load();
	$nodes = array();
	_test_cpu_nodes($d['widgets']['unit_detail']['tree'], $nodes);
	$links = array();
	$bound = array();
	foreach ($nodes as $n) {
		if (($n['props']['_bindings']['href'] ?? '') === '__page_link') $links[] = $n['props']['_bindParams']['href'];
		foreach (array('text', 'src') as $k) if (isset($n['props']['_bindings'][$k])) $bound[] = $n['props']['_bindings'][$k];
	}
	pg_assert_same(array(
		array('page_id' => '{{tab:book}}', 'params' => array(array('name' => 'unit', 'token' => 'title'), array('name' => 'kind', 'token' => 'kind'))),
		array('page_id' => '{{tab:units}}', 'params' => array(array('name' => 'kind', 'token' => 'kind'))),
	), $links, 'the stay request on the place and its kind, the list on its kind');
	foreach (array('title', 'kind', 'setting', 'sleeps', 'size', 'hookup', 'price', 'summary', 'description', 'features', 'photo_1', 'photo_2', 'photo_3') as $f) {
		pg_assert_true(in_array($f, $bound, true), "the page shows $f");
	}
	$json = json_encode($d['widgets']['unit_detail']['tree']);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the record');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
	pg_assert_contains('"eo_visible_if":"can_edit"', $json, 'the Edit button');
}

function test_campsite_units_links_ids_and_classes()
{
	$d = _test_cpu_load();
	$known = array_flip(_test_cp_page_keys());
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
	foreach ($d['pages'] as $p) _test_cpu_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_cpu_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(ptv|pht|pre|pev|pgy|pch|plw|pwd|pph|pcn|psh|pmu|psc)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'cp-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a pitch reads "yer", a cabin "kulübe" and
// a stay request "konaklama talebi".
function test_campsite_units_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-campsite/units.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (strpos($key, '-') !== false && preg_match('/^[a-z-]+$/', $key)) continue;
		if (preg_match('/\b[Pp]itch(es)?\b/', $key)) pg_assert_true(mb_stripos($tr, 'yer', 0, 'UTF-8') !== false, "'$key' says yer");
		if (preg_match('/\b[Cc]abins?\b/', $key)) pg_assert_true(mb_stripos($tr, 'kulübe', 0, 'UTF-8') !== false, "'$key' says kulübe");
		if (preg_match('/\bstay request\b/i', $key)) pg_assert_true(mb_stripos($tr, 'konaklama tale', 0, 'UTF-8') !== false, "'$key' says konaklama talebi");
	}
}
