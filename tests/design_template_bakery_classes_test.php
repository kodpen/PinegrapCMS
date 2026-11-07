<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Bakery design template's baking classes
 * (includes/design_templates/pinegrap-bakery/classes.php): the three pages
 * in their folders, the Baking Classes form a collection whose sample
 * records fill every field (the level in the words of the Level list, the
 * photo an image kind, featured for the office only), the lists and the
 * class page reading only fields the form has, the level filter from the
 * address, the home list of featured classes, the class page's buttons
 * sending the record's class and level, links to template pages, the
 * template's ids and classes, and every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_bk_page_keys()).
require_once(__DIR__ . '/design_template_bakery_test.php');

// parts.php and the classes part in one scope, as the template file reads
// them. Read once per run.
function _test_bkc_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bakery/parts.php';
	$class_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bakery/classes.php';
	$out = array('part' => $class_part, 'baking_classes' => $baking_classes, 'level_titles' => $level_titles);
	return $out;
}

// Every node of a tree, depth first.
function _test_bkc_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_bkc_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_bkc_pages()
{
	$pages = array();
	foreach (_test_bkc_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The names of the Baking Classes form's fields, with their _cf settings.
function _test_bkc_form_fields()
{
	$nodes = array();
	_test_bkc_nodes(_test_bkc_load()['part']['widgets']['class_form']['tree'], $nodes);
	$fields = array();
	foreach ($nodes as $n) {
		if (!in_array($n['props']['tag'] ?? '', array('input', 'select', 'textarea'), true)) continue;
		$name = '';
		foreach ($n['props']['_attrs'] ?? array() as $a) {
			if ($a['name'] === 'name') $name = $a['value'];
		}
		if ($name !== '') $fields[$name] = $n['props']['_cf'] ?? array();
	}
	return $fields;
}

function test_bakery_classes_pages_and_folders()
{
	$part = _test_bkc_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	$pages = _test_bkc_pages();
	pg_assert_same(array('classes', 'class', 'class_new'), array_keys($pages), 'the three pages');
	foreach (array('classes' => 'public', 'class' => 'public', 'class_new' => 'private') as $key => $folder) {
		pg_assert_same($folder, $pages[$key]['folder'], "$key folder");
		pg_assert_false(isset($pages[$key]['requires']), "$key stands without the shop");
		pg_assert_true(mb_strlen($pages[$key]['meta_description'] ?? '', 'UTF-8') <= 160, "$key: meta description fits");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $pages[$key]['name']), "$key: the page name is an address");
	}
	foreach (array('search', 'sitemap') as $f) pg_assert_false($pages['class_new'][$f], "staff page: no $f");
	pg_assert_true($pages['class_new']['noindex'], 'staff page: noindex');
	pg_assert_false($pages['class']['sitemap'], 'the record page is not in the sitemap by itself');
}

// The Baking Classes form: a collection whose sample records fill exactly
// its fields, the level in the words of the Level list, the photo an image
// kept in the public folder and featured an office field.
function test_bakery_classes_form_is_a_collection()
{
	$d = _test_bkc_load();
	$form = _test_bkc_pages()['class_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same('{{tab:classes}}', $form['confirmation_page_id'], 'back to the list');
	pg_assert_same('photo', $form['share_image_field'], 'the photo is the share image');
	$fields = _test_bkc_form_fields();
	pg_assert_same(array('name', 'level', 'teacher', 'length', 'seats', 'price', 'summary', 'details', 'photo', 'featured'), array_keys($fields), 'the form fields');
	pg_assert_same('image', $fields['photo']['kind'] ?? '', 'the photo is an image');
	pg_assert_same('{{folder:public}}', $fields['photo']['upload_folder_id'] ?? '', 'photos in the public folder');
	pg_assert_true(!empty($fields['featured']['office_use_only']), 'featured is the bakery\'s choice');
	pg_assert_same(count($d['baking_classes']), count($form['sample_records']), 'a sample record per class');
	$featured = 0;
	foreach ($form['sample_records'] as $n => $r) {
		$want = array_keys($fields);
		$got = array_keys($r);
		sort($want);
		sort($got);
		pg_assert_same($want, $got, "sample $n fills every field");
		pg_assert_true(in_array($r['level'], $d['level_titles'], true), "sample $n: a Level answer");
		foreach ($r as $f => $v) pg_assert_true(trim($v) !== '', "sample $n: $f");
		if ($r['featured'] === lang('Yes')) $featured++;
	}
	pg_assert_true($featured >= 1 && $featured <= 4, 'the home has featured classes to show');
}

// The lists and the class page read only fields the form has; the list
// filters on the level from the address; the home shows the featured four.
function test_bakery_classes_widgets_read_the_form()
{
	$w = _test_bkc_load()['part']['widgets'];
	pg_assert_same(array('class_form', 'classes_list', 'home_classes', 'class_detail'), array_keys($w), 'the widgets');
	$fields = _test_bkc_form_fields();
	$system = array('form_item_view', 'not_found', '__edit_url', 'can_edit', 'record_found', 'record_not_found', 'section', '__page_link');
	foreach (array('classes_list', 'home_classes', 'class_detail') as $key) {
		$nodes = array();
		_test_bkc_nodes($w[$key]['tree'], $nodes);
		$read = 0;
		foreach ($nodes as $n) {
			foreach ($n['props']['_bindings'] ?? array() as $what => $token) {
				if ($what === 'eo_visible_if' || in_array($token, $system, true)) continue;
				pg_assert_true(isset($fields[$token]), "$key reads $token, a field of the form");
				$read++;
			}
			foreach ($n['props']['_bindParams']['href']['params'] ?? array() as $p) {
				pg_assert_true(isset($fields[$p['token']]), "$key sends $p[token], a field of the form");
			}
		}
		pg_assert_true($read >= 5, "$key reads the class");
		pg_assert_same('{{tab:class_new}}', $w[$key]['config']['custom_form_page_id'], "$key: the Baking Classes form");
	}
	$f = $w['classes_list']['config']['filters'];
	pg_assert_same(1, count($f), 'one filter');
	pg_assert_same(array('level', 'url parameter', 'level'), array($f[0]['field'], $f[0]['dynamic_value'], $f[0]['dynamic_value_attribute']), 'level from ?level=');
	foreach ($w['classes_list']['config']['search_fields'] as $s) pg_assert_true(isset($fields[$s]), "search field $s");
	pg_assert_same(4, $w['home_classes']['config']['max_results'], 'four on the home');
	pg_assert_same('home', $w['home_classes']['page'], 'the home places it');
	pg_assert_same(array(array('field' => 'featured', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => '')),
		$w['home_classes']['config']['filters'], 'the home shows the featured classes');
}

// The class page asks for a place with the record's class (?class=) and
// opens the list on its level (?level=).
function test_bakery_classes_detail_sends_the_record()
{
	$nodes = array();
	_test_bkc_nodes(_test_bkc_load()['part']['widgets']['class_detail']['tree'], $nodes);
	$links = array();
	foreach ($nodes as $n) {
		if (($n['props']['_bindings']['href'] ?? '') !== '__page_link') continue;
		$spec = $n['props']['_bindParams']['href'];
		$params = array();
		foreach ($spec['params'] as $p) $params[$p['name']] = $p['token'];
		$links[$spec['page_id']] = $params;
	}
	pg_assert_same(array('{{tab:class_booking}}' => array('class' => 'name'), '{{tab:classes}}' => array('level' => 'level')), $links, 'the two record links');
}

// The level filter links name a Level answer, and every link names a
// template page and no shop page.
function test_bakery_classes_links()
{
	$d = _test_bkc_load();
	$json = json_encode(array(_test_bkc_pages(), $d['part']['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	$known = array_flip(_test_bk_page_keys());
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "{{page:$key}} names a template page");
	foreach (_test_bk_shop_keys() as $key) pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no link to $key");
	$nodes = array();
	_test_bkc_nodes(_test_bkc_pages()['classes']['tree'], $nodes);
	$seen = array();
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:classes}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:classes}}?')), $q);
		pg_assert_true(in_array($q['level'], $d['level_titles'], true), 'a Level answer');
		$seen[$q['level']] = true;
	}
	pg_assert_same(count($d['level_titles']), count($seen), 'a filter link per level');
}

// Ids carry bky-, classes are Bootstrap's or the template's, no faded text.
function test_bakery_classes_ids_and_classes()
{
	$nodes = array();
	foreach (_test_bkc_pages() as $p) _test_bkc_nodes($p['tree'], $nodes);
	foreach (_test_bkc_load()['part']['widgets'] as $w) {
		if (is_array($w['tree'])) _test_bkc_nodes($w['tree'], $nodes);
	}
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht|pgy|pmk|pwd|pch|pcn|pmu|pph|psh|ptv|pmz|pai|pcp)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '') pg_assert_true(strpos($id, 'bky-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a baking class reads "atölye", never
// "ders" or "sınıf".
function test_bakery_classes_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bakery/classes.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		// Page names are addresses without accents; only words are checked.
		if (strpos($key, ' ') !== false && preg_match('/\b(Baking )?Class(es)?\b/i', $key)) {
			pg_assert_true(mb_stripos($map[$key], 'atölye', 0, 'UTF-8') !== false, "'$key' says atölye");
		}
	}
	foreach (array('Classes', 'Class') as $k) pg_assert_false(strpos($src, "lang('$k')") !== false, "no lang('$k')");
}
