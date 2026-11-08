<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Ski Resort design template's slopes and lifts
 * (includes/design_templates/pinegrap-skiresort/slopes.php): the pages and
 * widgets of the plan, each widget on its page and the featured slopes on
 * the home, the Slopes and Lifts forms as collections whose samples fill
 * their fields with answers of their lists, the lists filtering on the
 * address in the lists' own words, the slope map drawn with the palette
 * block's classes and rules, the record page binding the slope's fields,
 * the template's ids and classes, heading levels, no fixed dates and every
 * literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/design_template_skiresort_test.php');

// parts.php, cards.php, the public pages and slopes.php in one scope, as
// the template file reads them. Read once per run.
function _test_sks_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-skiresort';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	$public = include $dir . '/pages_public.php';
	$part = include $dir . '/slopes.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$home = null;
	foreach ($public['pages'] as $p) {
		if ($p['key'] === 'home') $home = $p['tree'];
	}
	$out = array('part' => $part, 'home' => $home, 'pages' => $pages, 'widgets' => $part['widgets'], 'slopes' => $slopes, 'lifts' => $lifts,
		'difficulty_titles' => $difficulty_titles, 'area_titles' => $area_titles, 'status_titles' => $status_titles,
		'lift_kind_titles' => $lift_kind_titles);
	return $out;
}

function _test_sks_nodes($tree)
{
	$nodes = array();
	_test_sk_nodes($tree, $nodes);
	return $nodes;
}

// The name and options of every form control of a tree.
function _test_sks_controls($tree)
{
	$out = array();
	foreach (_test_sks_nodes($tree) as $n) {
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

function test_skiresort_slopes_pages_and_widgets()
{
	$d = _test_sks_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('slopes', 'slope', 'lifts', 'slope_new', 'lift_new'), array_keys($d['pages']));
	pg_assert_same(array('slope_form', 'lift_form', 'slopes_list', 'home_slopes', 'lifts_list', 'slope_detail'), array_keys($d['widgets']));
	$known = array_flip(_test_sk_page_keys());
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a planned page");
		pg_assert_same(substr($key, -4) === '_new' ? 'private' : 'public', $p['folder'], "$key: its folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
	}
	foreach (array('slope_new', 'lift_new') as $key) pg_assert_true(!empty($d['pages'][$key]['noindex']), "$key is not indexed");
	foreach ($d['widgets'] as $key => $w) {
		if ($w['page'] === 'home') continue;
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	pg_assert_contains('"templateWidget":"home_slopes"', json_encode($d['home']), 'the home page places home_slopes');
	$known_pages = array_flip(_test_sk_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($d['part']), $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known_pages[$key]), "link {{page:$key}} names a template page");
	foreach (_test_sk_shop_keys() as $key) pg_assert_false(strpos(json_encode($d['part']), '{{page:' . $key . '}}') !== false, "no link to $key");
}

// The forms are collections; every sample fills a field, and its pick
// list values are answers of the field's list.
function test_skiresort_slopes_forms_and_samples()
{
	$d = _test_sks_load();
	$cases = array(
		'slope_new' => array('slope_form', count($d['slopes']), array('difficulty' => $d['difficulty_titles'], 'area' => $d['area_titles'], 'status' => $d['status_titles'], 'featured' => array(lang('Yes'), lang('No')))),
		'lift_new'  => array('lift_form', count($d['lifts']), array('kind' => $d['lift_kind_titles'], 'area' => $d['area_titles'], 'status' => $d['status_titles'])),
	);
	foreach ($cases as $page => $c) {
		$form = $d['pages'][$page]['form'];
		pg_assert_same(1, $form['collection'], "$page: a collection");
		pg_assert_same($c[1], count($form['sample_records']), "$page: one sample per record");
		$controls = _test_sks_controls($d['widgets'][$c[0]]['tree']);
		foreach ($form['sample_records'] as $sample) {
			foreach ($sample as $field => $value) {
				pg_assert_true(isset($controls[$field]), "$page: sample field $field is a field of the form");
				if (isset($c[2][$field])) pg_assert_true(in_array($value, $c[2][$field], true), "$page: $field '$value' is an answer");
			}
		}
		foreach ($c[2] as $field => $answers) pg_assert_same($answers, $controls[$field]['options'], "$page: $field offers its list");
	}
	pg_assert_true(!empty(_test_sks_controls($d['widgets']['slope_form']['tree'])['featured']['cf']['office_use_only']), 'featured is the office\'s field');
	$featured = 0;
	foreach ($d['pages']['slope_new']['form']['sample_records'] as $s) if ($s['featured'] === lang('Yes')) $featured++;
	pg_assert_true($featured >= 3, 'the home finds three featured slopes');
}

// The slope list filters on ?difficulty= and ?area=, the lift list on
// ?area=; every filter link uses an answer of its list.
function test_skiresort_slopes_lists_filter_in_the_lists_words()
{
	$d = _test_sks_load();
	$params = array();
	foreach ($d['widgets']['slopes_list']['config']['filters'] as $f) $params[] = $f['dynamic_value_attribute'];
	pg_assert_same(array('difficulty', 'area'), $params, 'the slope list reads the address');
	pg_assert_same('area', $d['widgets']['lifts_list']['config']['filters'][0]['dynamic_value_attribute'], 'the lift list reads the address');
	pg_assert_false(isset($d['widgets']['lifts_list']['config']['detail_page_id']), 'a lift has no page');
	$lists = array('difficulty' => $d['difficulty_titles'], 'area' => $d['area_titles']);
	$seen = array();
	foreach ($d['pages'] as $key => $p) {
		foreach (_test_sks_nodes($p['tree']) as $n) {
			$href = (string)($n['props']['href'] ?? '');
			if (!preg_match('/^\{\{page:(slopes|lifts)\}\}\?(.*)$/', $href, $m)) continue;
			parse_str($m[2], $q);
			foreach ($q as $name => $value) {
				pg_assert_true(isset($lists[$name]) && in_array($value, $lists[$name], true), "$key: $name '$value' is an answer");
				$seen[$m[1] . ':' . $name][$value] = true;
			}
		}
	}
	pg_assert_same(count($d['difficulty_titles']), count($seen['slopes:difficulty']), 'every difficulty opens the slopes');
	pg_assert_same(count($d['area_titles']), count($seen['slopes:area']), 'every area opens the slopes');
	pg_assert_same(count($d['area_titles']), count($seen['lifts:area']), 'every area opens the lifts');
}

// The slopes page opens with the slope map: the legend's colours are the
// Ski Resort family's rules, and each grade is named in words.
function test_skiresort_slopes_map_is_the_palette_block()
{
	$d = _test_sks_load();
	$json = json_encode($d['pages']['slopes']['tree'], JSON_UNESCAPED_UNICODE);
	$rules = _pg_designer_block_css_parse((string)file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_blocks_skiresort.js'));
	foreach (array('pg-ski-green', 'pg-ski-blue', 'pg-ski-red', 'pg-ski-black') as $class) {
		pg_assert_contains($class, $json, "the legend colours $class");
		pg_assert_true(isset($rules['rules'][$class]), "$class is a rule of the family");
	}
	foreach ($d['difficulty_titles'] as $t) pg_assert_contains(json_encode($t, JSON_UNESCAPED_UNICODE), $json, "the legend names $t");
	pg_assert_contains('"id":"skr-slope-map"', $json, 'the map is a search section');
}

// The slope's page binds the record's fields and has its not-found state.
function test_skiresort_slopes_detail_reads_the_record()
{
	$d = _test_sks_load();
	$w = $d['widgets']['slope_detail'];
	pg_assert_same('form_item_view', $w['config']['regionType'], 'a record view');
	pg_assert_same('{{tab:slope_new}}', $w['config']['custom_form_page_id'], 'of the Slopes form');
	$bound = array();
	foreach (_test_sks_nodes($w['tree']) as $n) {
		foreach ((array)($n['props']['_bindings'] ?? array()) as $token) $bound[$token] = true;
	}
	foreach (array('title', 'difficulty', 'area', 'length', 'drop', 'lift', 'status', 'summary', 'details', 'photo_1', 'record_found', 'record_not_found') as $token) {
		pg_assert_true(isset($bound[$token]), "the page reads $token");
	}
}

function test_skiresort_slopes_ids_classes_and_headings()
{
	$d = _test_sks_load();
	$ids = array();
	$trees = array();
	foreach ($d['pages'] as $key => $p) $trees[$key] = $p['tree'];
	foreach ($d['widgets'] as $key => $w) $trees['widget ' . $key] = $w['tree'];
	foreach ($trees as $key => $tree) {
		foreach (_test_sks_nodes($tree) as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pgl|pmz|pph|pvt|pre|pev|sk)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false(strpos($c, 'opacity-') === 0, "$key: no faded text ($c)");
				pg_assert_false(in_array($c, array('text-white', 'bg-white', 'bg-dark', 'text-dark', 'btn-light', 'text-muted', 'text-body-tertiary'), true), "$key: no fixed or faint colour ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'skr-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$id]), "id $id once");
			$ids[$id] = true;
		}
	}
	foreach (array('slopes', 'lifts') as $key) {
		$levels = array();
		foreach (_test_sks_nodes($d['pages'][$key]['tree']) as $n) {
			if (preg_match('/^h([1-6])$/', (string)($n['props']['tag'] ?? ''), $m)) $levels[] = (int)$m[1];
		}
		// The list's cards (h3) sit under the list's own h2.
		$levels[] = 3;
		$set = array_values(array_unique($levels));
		sort($set);
		pg_assert_same(range(1, max($set)), $set, "$key: no heading level skipped");
	}
}

function test_skiresort_slopes_no_fixed_dates()
{
	$json = rawurldecode(json_encode(_test_sks_load()['part'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

function test_skiresort_slopes_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-skiresort/slopes.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bSlopes?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'pist', 0, 'UTF-8') !== false, "'$key' says pist");
	}
}

// The page files share one scope with parts.php and cards.php: a page file
// that assigns a name of theirs would replace a builder or the data for
// every file read after it.
function test_skiresort_page_files_keep_the_shared_names()
{
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-skiresort';
	$shared = array();
	foreach (array('parts.php', 'cards.php') as $file) {
		preg_match_all('/^\$([a-z_0-9]+) = /m', (string)file_get_contents($dir . '/' . $file), $m);
		$shared = array_merge($shared, $m[1]);
	}
	pg_assert_true(in_array('lift_status', $shared, true), 'the shared names are read');
	foreach (glob($dir . '/*.php') as $path) {
		if (in_array(basename($path), array('parts.php', 'cards.php'), true)) continue;
		preg_match_all('/^\$([a-z_0-9]+) = /m', (string)file_get_contents($path), $m);
		pg_assert_same(array(), array_values(array_intersect(array_unique($m[1]), $shared)), basename($path) . ' keeps the shared names');
	}
}
