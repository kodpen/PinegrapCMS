<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Photography Studio design template's galleries and online
 * booking (includes/design_templates/pinegrap-photo/galleries.php): the
 * pages and widgets with their folders and places, the Galleries form as a
 * collection with a sample per gallery whose photos are the ones the cards
 * draw, the list's category filter and its links, the home's featured
 * galleries, the gallery page booking a shoot of its category, the booking
 * widget's starter on a public page that links the request form, links,
 * ids and classes, and every literal's Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template and the shop's pages.
require_once(__DIR__ . '/design_template_photo_test.php');

// The galleries' part as the template file reads it: parts.php first, then
// galleries.php in the same scope. Read once per run.
function _test_phg_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-photo';
	include $dir . '/parts.php';
	$part = include $dir . '/galleries.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('pages' => $pages, 'widgets' => $part['widgets'], 'galleries' => $galleries,
		'categories' => $categories, 'category_titles' => $category_titles);
	return $out;
}

// Every node of a tree, depth first.
function _test_phg_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_phg_nodes($c, $out);
}

// The name and options of every form control of a tree.
function _test_phg_controls($node)
{
	$nodes = array();
	_test_phg_nodes($node, $nodes);
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

function test_photo_galleries_pages_and_widgets()
{
	$d = _test_phg_load();
	pg_assert_same(array('galleries', 'gallery', 'gallery_new', 'book_online'), array_keys($d['pages']));
	pg_assert_same(array('gallery_form', 'galleries_list', 'home_galleries', 'booking_slots', 'gallery_detail'), array_keys($d['widgets']));
	foreach (array('galleries', 'gallery', 'book_online') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	pg_assert_same('private', $d['pages']['gallery_new']['folder'], 'only the staff add a gallery');
	pg_assert_true(!empty($d['pages']['gallery_new']['noindex']), 'gallery_new is not indexed');
	foreach ($d['pages'] as $key => $p) {
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_false(isset($w['requires']), "$key stands without the shop");
		pg_assert_true($w['page'] === 'home' || isset($d['pages'][$w['page']]), "$key sits on a page of this part or the home");
		if ($w['page'] !== 'home') {
			pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
		}
	}
	pg_assert_same('home', $d['widgets']['home_galleries']['page']);
}

// The Galleries form is a collection with a sample per gallery: every
// sample value has its field and is filled, the category is one of its
// answers, the photos are images (the same pictures the cards and the grid
// draw) and featured is the staff's.
function test_photo_galleries_form_and_samples()
{
	$d = _test_phg_load();
	$form = $d['pages']['gallery_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same(count($d['galleries']), count($form['sample_records']), 'one sample per gallery');
	$controls = _test_phg_controls($d['widgets']['gallery_form']['tree']);
	$images = array('cover', 'photo_1', 'photo_2', 'photo_3', 'photo_4', 'photo_5', 'photo_6');
	foreach (array_merge(array('title', 'category', 'location', 'summary', 'story', 'featured'), $images) as $name) {
		pg_assert_true(isset($controls[$name]), "the form has $name");
	}
	foreach ($images as $name) pg_assert_same('image', $controls[$name]['cf']['kind'] ?? '', "$name is an image");
	pg_assert_true(!empty($controls['featured']['cf']['office_use_only']), 'featured is the staff\'s');
	pg_assert_same('cover', $form['share_image_field'], 'the cover is the share image');
	pg_assert_same($d['category_titles'], $controls['category']['options'], 'the categories are the words the links send');
	$featured = 0;
	$keys = array_keys($d['galleries']);
	foreach ($form['sample_records'] as $n => $r) {
		foreach ($r as $name => $value) {
			pg_assert_true(isset($controls[$name]), "sample $n: field $name exists");
			pg_assert_true($value !== '', "sample $n: $name filled");
			if ($controls[$name]['tag'] === 'select') {
				pg_assert_true(in_array($value, $controls[$name]['options'], true), "sample $n: $name '$value' is an answer");
			}
		}
		// The same seeds as the card's cover and the grid's photos.
		pg_assert_contains('pinegrap-photo-cover-' . $keys[$n] . '/', $r['cover'], "sample $n: the card's cover");
		pg_assert_contains('pinegrap-photo-' . $keys[$n] . '-1/', $r['photo_1'], "sample $n: the grid's first photo");
		if ($r['featured'] === lang('Yes')) $featured++;
	}
	pg_assert_same(3, $featured, 'three featured samples');
}

// The list reads ?category= the filter links, the footer and the home's
// tiles send; every filter link names an answer; the home shows the
// featured galleries, three at most.
function test_photo_galleries_filters()
{
	$d = _test_phg_load();
	$filters = $d['widgets']['galleries_list']['config']['filters'];
	pg_assert_same(array(array('field' => 'category', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'url parameter', 'dynamic_value_attribute' => 'category')), $filters, 'the category from the address');
	$nodes = array();
	_test_phg_nodes($d['pages']['galleries']['tree'], $nodes);
	$seen = array();
	$prefix = '{{page:galleries}}?';
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, $prefix) !== 0) continue;
		parse_str(substr($href, strlen($prefix)), $q);
		pg_assert_true(in_array($q['category'] ?? '', $d['category_titles'], true), 'the link names a category answer');
		$seen[$q['category']] = true;
	}
	pg_assert_same(count($d['category_titles']), count($seen), 'a link per category');
	$c = $d['widgets']['home_galleries']['config'];
	pg_assert_same(array(array('field' => 'featured', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => '')), $c['filters'], 'the featured ones');
	pg_assert_same(3, $c['max_results'], 'three at most');
}

// The gallery page shows the record (or its not-found note), its six
// photos and books a shoot of its own category (?category= from the
// record).
function test_photo_galleries_record_page()
{
	$d = _test_phg_load();
	$nodes = array();
	_test_phg_nodes($d['widgets']['gallery_detail']['tree'], $nodes);
	$links = array();
	$tokens = array();
	foreach ($nodes as $n) {
		if (($n['props']['_bindings']['href'] ?? '') === '__page_link') $links[] = $n['props']['_bindParams']['href'];
		foreach ((array)($n['props']['_bindings'] ?? array()) as $t) $tokens[$t] = true;
	}
	pg_assert_same(array(array('page_id' => '{{tab:book}}', 'params' => array(array('name' => 'category', 'token' => 'category')))), $links, 'the gallery books a shoot of its category');
	foreach (array('title', 'category', 'location', 'summary', 'story', 'cover', 'photo_1', 'photo_6') as $t) pg_assert_true(isset($tokens[$t]), "the page reads $t");
	$json = json_encode($d['widgets']['gallery_detail']['tree']);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the record');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
	pg_assert_same('public', $d['widgets']['gallery_detail']['config']['access_control'], 'anybody may open a gallery');
}

// The online booking page places the booking widget with the editor's
// starter layout and links the request form for any other shoot; its page
// name is the one the booking templates share.
function test_photo_galleries_book_online()
{
	$d = _test_phg_load();
	$w = $d['widgets']['booking_slots'];
	pg_assert_same('book_online', $w['page']);
	pg_assert_same('booking', $w['config']['regionType']);
	pg_assert_same('starter', $w['tree'], 'the starter layout');
	$p = $d['pages']['book_online'];
	pg_assert_same(lang('book-online'), $p['name']);
	pg_assert_contains('{{page:book}}', json_encode($p['tree']), 'the page links the request form');
}

function test_photo_galleries_links_ids_and_classes()
{
	$d = _test_phg_load();
	$known = array_flip(_test_ph_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p['tree'];
	foreach ($d['widgets'] as $w) {
		if (is_array($w['tree'])) $trees[] = $w['tree'];
	}
	$json = json_encode(array($d['pages'], $d['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_ph_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$nodes = array();
	foreach ($trees as $t) _test_phg_nodes($t, $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv)-/', $c), "class $c is not another template's");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'ph-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a gallery reads "galeri", a shoot
// "çekim", a package "paket".
function test_photo_galleries_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-photo/galleries.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bGaller(y|ies)\b/', $key)) pg_assert_true(mb_stripos($tr, 'galeri', 0, 'UTF-8') !== false, "'$key' says galeri");
		if (preg_match('/\b[Ss]hoot\b/', $key)) pg_assert_true(mb_stripos($tr, 'çekim', 0, 'UTF-8') !== false, "'$key' says çekim");
		if (preg_match('/\b[Pp]ackages?\b/', $key)) pg_assert_true(mb_stripos($tr, 'paket', 0, 'UTF-8') !== false, "'$key' says paket");
	}
}
