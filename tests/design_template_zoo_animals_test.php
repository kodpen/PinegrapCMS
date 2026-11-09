<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Zoo design template's animals
 * (includes/design_templates/pinegrap-zoo/animals.php): the pages and
 * widgets of the plan, each widget on its page and the featured animals on
 * the home, the Animals form as a collection whose samples fill its fields
 * with answers of their lists, the list filtering on ?habitat= and ?class=
 * in the lists' own words, the record page binding the animal's fields,
 * the template's ids and classes, heading levels, no fixed dates, every
 * literal in Turkish and the page files keeping the shared names.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/design_template_zoo_test.php');

// parts.php, cards.php, the public pages and animals.php in one scope, as
// the template file reads them. Read once per run.
function _test_zooa_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-zoo';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	$public = include $dir . '/pages_public.php';
	$part = include $dir . '/animals.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$home = null;
	foreach ($public['pages'] as $p) {
		if ($p['key'] === 'home') $home = $p['tree'];
	}
	$out = array('part' => $part, 'home' => $home, 'pages' => $pages, 'widgets' => $part['widgets'], 'animals' => $animals,
		'habitat_titles' => $habitat_titles, 'class_titles' => $class_titles, 'status_titles' => $status_titles);
	return $out;
}

function _test_zooa_nodes($tree)
{
	$nodes = array();
	_test_zoo_nodes($tree, $nodes);
	return $nodes;
}

// The name and options of every form control of a tree.
function _test_zooa_controls($tree)
{
	$out = array();
	foreach (_test_zooa_nodes($tree) as $n) {
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

function test_zoo_animals_pages_and_widgets()
{
	$d = _test_zooa_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('animals', 'animal', 'animal_new'), array_keys($d['pages']));
	pg_assert_same(array('animal_form', 'animals_list', 'home_animals', 'animal_detail'), array_keys($d['widgets']));
	$known = array_flip(_test_zoo_page_keys());
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a planned page");
		pg_assert_same($key === 'animal_new' ? 'private' : 'public', $p['folder'], "$key: its folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
	}
	pg_assert_true(!empty($d['pages']['animal_new']['noindex']), 'animal_new is not indexed');
	foreach ($d['widgets'] as $key => $w) {
		if ($w['page'] === 'home') continue;
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	pg_assert_contains('"templateWidget":"home_animals"', json_encode($d['home']), 'the home page places home_animals');
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', json_encode($d['part']), $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	foreach (_test_zoo_shop_keys() as $key) pg_assert_false(strpos(json_encode($d['part']), '{{page:' . $key . '}}') !== false, "no link to $key");
}

// The form is a collection; every sample fills a field, its pick list
// values are answers of the field's list, and the cards' field names are
// the form's.
function test_zoo_animals_form_and_samples()
{
	$d = _test_zooa_load();
	$form = $d['pages']['animal_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same(count($d['animals']), count($form['sample_records']), 'one sample per animal');
	$controls = _test_zooa_controls($d['widgets']['animal_form']['tree']);
	$names = array('title', 'species', 'habitat', 'habitat_number', 'animal_class', 'conservation_status', 'diet', 'lifespan', 'summary', 'details', 'photo_1', 'featured');
	$fields = array_keys($controls);
	sort($fields);
	$sorted = $names;
	sort($sorted);
	pg_assert_same($sorted, $fields, 'the fields the cards read');
	$lists = array('habitat' => $d['habitat_titles'], 'animal_class' => $d['class_titles'], 'conservation_status' => $d['status_titles'], 'featured' => array(lang('Yes'), lang('No')));
	foreach ($form['sample_records'] as $sample) {
		pg_assert_same($names, array_keys($sample), 'the sample fills every field');
		foreach ($lists as $field => $answers) pg_assert_true(in_array($sample[$field], $answers, true), "$field '{$sample[$field]}' is an answer");
	}
	foreach ($lists as $field => $answers) pg_assert_same($answers, $controls[$field]['options'], "$field offers its list");
	pg_assert_true(!empty($controls['featured']['cf']['office_use_only']), 'featured is the office\'s field');
	$featured = 0;
	foreach ($form['sample_records'] as $s) if ($s['featured'] === lang('Yes')) $featured++;
	pg_assert_same(3, $featured, 'the home finds three featured animals');
}

// The list filters on ?habitat= and ?class=; every link to the animals page
// uses an answer of its list, and every habitat and class has its link.
function test_zoo_animals_list_filters_in_the_lists_words()
{
	$d = _test_zooa_load();
	$params = array();
	foreach ($d['widgets']['animals_list']['config']['filters'] as $f) $params[$f['field']] = $f['dynamic_value_attribute'];
	pg_assert_same(array('habitat' => 'habitat', 'animal_class' => 'class'), $params, 'the list reads the address');
	pg_assert_same('{{tab:animal}}', $d['widgets']['animals_list']['config']['detail_page_id'], 'a card opens the animal');
	$lists = array('habitat' => $d['habitat_titles'], 'class' => $d['class_titles']);
	$seen = array();
	foreach (_test_zooa_nodes($d['pages']['animals']['tree']) as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (!preg_match('/^\{\{page:animals\}\}\?(.*)$/', $href, $m)) continue;
		parse_str($m[1], $q);
		foreach ($q as $name => $value) {
			pg_assert_true(isset($lists[$name]) && in_array($value, $lists[$name], true), "$name '$value' is an answer");
			$seen[$name][$value] = true;
		}
	}
	pg_assert_same(count($d['habitat_titles']), count($seen['habitat'] ?? array()), 'every habitat opens the list');
	pg_assert_same(count($d['class_titles']), count($seen['class'] ?? array()), 'every class opens the list');
	pg_assert_false(strpos(json_encode($d['part']), '?view=') !== false, 'never ?view=');
}

// The animal's page binds the record's fields and has its not-found state;
// its habitat pin is the family's rule.
function test_zoo_animals_detail_reads_the_record()
{
	$d = _test_zooa_load();
	$w = $d['widgets']['animal_detail'];
	pg_assert_same('form_item_view', $w['config']['regionType'], 'a record view');
	pg_assert_same('{{tab:animal_new}}', $w['config']['custom_form_page_id'], 'of the Animals form');
	$bound = array();
	foreach (_test_zooa_nodes($w['tree']) as $n) {
		foreach ((array)($n['props']['_bindings'] ?? array()) as $token) $bound[$token] = true;
	}
	foreach (array('title', 'species', 'habitat', 'habitat_number', 'animal_class', 'conservation_status', 'diet', 'lifespan', 'summary', 'details', 'photo_1', 'record_found', 'record_not_found') as $token) {
		pg_assert_true(isset($bound[$token]), "the page reads $token");
	}
	$rules = _pg_designer_block_css_parse((string)file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_blocks_zoo.js'));
	pg_assert_true(isset($rules['rules']['pg-zoo-pin']), 'pg-zoo-pin is a rule of the family');
	pg_assert_contains('pg-zoo-pin', json_encode($w['tree']), 'the habitat number is a pin');
}

function test_zoo_animals_ids_classes_and_headings()
{
	$d = _test_zooa_load();
	$ids = array();
	$trees = array();
	foreach ($d['pages'] as $key => $p) $trees[$key] = $p['tree'];
	foreach ($d['widgets'] as $key => $w) $trees['widget ' . $key] = $w['tree'];
	foreach ($trees as $key => $tree) {
		foreach (_test_zooa_nodes($tree) as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(prx|pmz|pph|pvt|pre|pev|pgl|pmn|prt|plb|pht|pma|pco|pcl|pgy|pch|psk|sk)-/', $c), "$key: class $c is not another template's");
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
	foreach (_test_zooa_nodes($d['pages']['animals']['tree']) as $n) {
		if (preg_match('/^h([1-6])$/', (string)($n['props']['tag'] ?? ''), $m)) $levels[] = (int)$m[1];
	}
	pg_assert_same(1, count(array_keys($levels, 1, true)), 'animals: one h1');
	$set = array_values(array_unique($levels));
	sort($set);
	pg_assert_same(array(1, 2, 3), $set, 'animals: no heading level skipped');
	$levels = array();
	foreach (_test_zooa_nodes($d['widgets']['animal_detail']['tree']) as $n) {
		if (($n['props']['_label'] ?? '') === lang('Not Found')) continue;
		if (preg_match('/^h([1-6])$/', (string)($n['props']['tag'] ?? ''), $m)) $levels[] = (int)$m[1];
	}
	pg_assert_same(1, count(array_keys($levels, 1, true)), 'animal: one h1');
}

function test_zoo_animals_no_fixed_dates()
{
	$json = rawurldecode(json_encode(_test_zooa_load()['part'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

function test_zoo_animals_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-zoo/animals.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\banimals?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'hayvan', 0, 'UTF-8') !== false, "'$key' says hayvan");
		if (preg_match('/\bhabitats?\b/i', $key) && strpos($key, ',') === false) pg_assert_true(mb_stripos($tr, 'yaşam alan', 0, 'UTF-8') !== false, "'$key' says yaşam alanı");
	}
}

// The page files share one scope with parts.php and cards.php: a page file
// that assigns a name of theirs would replace a builder or the data for
// every file read after it.
function test_zoo_page_files_keep_the_shared_names()
{
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-zoo';
	$shared = array();
	foreach (array('parts.php', 'cards.php') as $file) {
		preg_match_all('/^\$([a-z_0-9]+) = /m', (string)file_get_contents($dir . '/' . $file), $m);
		$shared = array_merge($shared, $m[1]);
	}
	pg_assert_true(in_array('animal_card', $shared, true), 'the shared names are read');
	foreach (glob($dir . '/*.php') as $path) {
		if (in_array(basename($path), array('parts.php', 'cards.php'), true)) continue;
		$src = (string)file_get_contents($path);
		preg_match_all('/^\$([a-z_0-9]+) = /m', $src, $m);
		preg_match_all('/^foreach \(.* as (?:\$([a-z_0-9]+) => )?\$([a-z_0-9]+)\)/m', $src, $f);
		$assigned = array_unique(array_filter(array_merge($m[1], $f[1], $f[2])));
		pg_assert_same(array(), array_values(array_intersect($assigned, $shared)), basename($path) . ' keeps the shared names');
	}
}
