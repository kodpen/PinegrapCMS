<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a search results page's own layout file turned into the Search
 * Results widget's design elements: the featured, catalog and other results
 * loops become places of the widget's one list, each under its row flag;
 * the starting sites' text and the stock template convert with nothing
 * dropped; the widget's layout and row flags and its rows per place.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/layout_convert.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/layouts.php');

// Every node of a tree, depth first, the root left out.
function _test_srl_nodes($nodes)
{
	$out = array();
	foreach ((array)$nodes as $n) {
		$out[] = $n;
		if (!empty($n['children'])) $out = array_merge($out, _test_srl_nodes($n['children']));
	}
	return $out;
}

function _test_srl_site($site)
{
	return (string)file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/' . $site . '/layouts/216.php');
}

// The loop areas of a tree, in order.
function _test_srl_loops($tree)
{
	return array_values(array_filter(_test_srl_nodes($tree['children']), function ($n) { return ($n['type'] ?? '') === 'loop_area'; }));
}

// The flags (eo_visible_if) over a tree, in order.
function _test_srl_flags($nodes)
{
	$out = array();
	foreach (_test_srl_nodes($nodes) as $n) {
		if (isset($n['props']['_bindings']['eo_visible_if'])) $out[] = $n['props']['_bindings']['eo_visible_if'];
	}
	return $out;
}

function test_srl_type()
{
	pg_assert_true(in_array('search results', pg_layout_convert_types(), true), 'search results is a mapped type');
	pg_assert_true(is_array(pg_layout_piece_map('search results')), 'it has a map');
}

// The starting sites' search results page (216, both sites): nothing
// dropped, the three lists as three places each under its kind's flag,
// the search box the widget's.
function test_srl_starting_sites()
{
	foreach (array('english_default', 'turkish_default') as $site) {
		$r = pg_layout_convert('search results', _test_srl_site($site));
		pg_assert_true($r['ok'], $site . ' converts');
		pg_assert_same(array(), $r['dropped'], $site . ' drops nothing');
		$loops = _test_srl_loops($r['tree']);
		pg_assert_same(3, count($loops), $site . ' three places of the list');
		$kinds = array();
		foreach ($loops as $l) {
			pg_assert_true(!isset($l['props']['_kinds']), $site . ' no merge mark left');
			$kinds[] = $l['children'][0]['props']['_bindings']['eo_visible_if'] ?? '';
		}
		pg_assert_same(array('result_is_featured', 'result_is_catalog', 'result_is_page'), $kinds, $site . ' each place its kind');
		$flags = _test_srl_flags($r['tree']['children']);
		foreach (array('no_query', 'no_results', 'has_results', 'is_limited', 'not_limited', 'has_featured_results', 'has_catalog_results', 'has_page_results', 'has_featured_or_catalog_results', 'result_has_excerpt', 'result_has_image', 'result_has_price') as $f) {
			pg_assert_true(in_array($f, $flags, true), $site . ' flag ' . $f);
		}
		$bound = array();
		foreach (_test_srl_nodes($r['tree']['children']) as $n) {
			foreach ((array)($n['props']['_bindings'] ?? array()) as $k => $v) $bound[] = $k . '=' . $v;
		}
		foreach (array('action=search_form', 'value=search_query', 'href=__result_url', 'text=__result_title', 'text=__result_full_url', 'text=__result_excerpt', 'src=__result_image', 'text=__result_price') as $b) {
			pg_assert_true(in_array($b, $bound, true), $site . ' binds ' . $b);
		}
		$json = json_encode($r['tree']);
		pg_assert_true(strpos($json, '^^__search_query^^') !== false && strpos($json, '^^__result_count^^') !== false, $site . ' the query and the count');
		pg_assert_true(strpos($json, 'page_query') === false, $site . ' no classic control name left');
	}
	// The English "result<s>": the line once for each.
	$flags = _test_srl_flags(pg_layout_convert('search results', _test_srl_site('english_default'))['tree']['children']);
	pg_assert_true(in_array('many_results', $flags, true) && in_array('one_result', $flags, true), 'plural line twice');
}

// The stock template converts with nothing dropped: its sentences are the
// widget's heading.
function test_srl_stock_template()
{
	$code = pg_layout_strip_template_header((string)file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/search_results.php'));
	$r = pg_layout_convert('search results', $code);
	pg_assert_same(array(), $r['dropped'], 'template drops nothing');
	pg_assert_same(3, count(_test_srl_loops($r['tree'])), 'template three places');
	pg_assert_true(strpos(json_encode($r['tree']), '^^__results_heading^^') !== false, 'template heading');
}

function test_srl_row_flags()
{
	$f = pg_sr_row_flags(array('group' => 'catalog', 'excerpt' => '', 'image' => '/a.jpg', 'price' => '$1'));
	pg_assert_same(array('result_is_featured' => false, 'result_is_catalog' => true, 'result_is_page' => false,
		'result_has_excerpt' => false, 'result_has_image' => true, 'result_has_price' => true), $f, 'catalog row');
	$f = pg_sr_row_flags(array('group' => 'pages', 'excerpt' => 'x'));
	pg_assert_true($f['result_is_page'] && $f['result_has_excerpt'] && !$f['result_has_image'], 'page row');
	pg_assert_same(false, pg_sr_row_flags(array())['result_is_featured'], 'no group');
}

function test_srl_layout_flags()
{
	$shown = array(array('group' => 'featured'), array('group' => 'pages'));
	$f = pg_sr_layout_flags('tea', 2, false, $shown);
	pg_assert_true(!$f['no_query'] && $f['not_limited'] && !$f['is_limited'] && $f['many_results'] && !$f['one_result'], 'two results');
	pg_assert_true($f['has_featured_results'] && !$f['has_catalog_results'] && $f['has_page_results'] && $f['has_featured_or_catalog_results'], 'kinds shown');
	$f = pg_sr_layout_flags('tea', 1, true, array(array('group' => 'catalog')));
	pg_assert_true($f['is_limited'] && !$f['not_limited'] && $f['one_result'] && $f['has_featured_or_catalog_results'], 'limited, one');
	$f = pg_sr_layout_flags('', 0, false, array());
	pg_assert_true($f['no_query'] && !$f['not_limited'] && !$f['one_result'] && !$f['has_page_results'], 'nothing searched');
}

// The converted tree's further places come out as the cart's markers, and
// each place draws only its kind of rows.
function test_srl_places_rows()
{
	$r = pg_layout_convert('search results', _test_srl_site('english_default'));
	$tree = $r['tree'];
	$extra = pg_cart_extra_loops($tree);
	pg_assert_same(2, count($extra), 'two further places');
	$split = _split_widget_tree($tree);
	$page = pg_sr_row_flags(array('group' => 'pages', 'excerpt' => 'About tea'));
	$cat = pg_sr_row_flags(array('group' => 'catalog', 'image' => '/t.jpg'));
	pg_assert_same('', pg_sr_row_template($split['loop_children'], $page, ''), 'featured place: no page row');
	pg_assert_true(pg_sr_row_template($extra[1], $page, '') !== '', 'other place: page row');
	pg_assert_same('', pg_sr_row_template($extra[1], $cat, ''), 'other place: no product row');
	$html = pg_sr_row_template($extra[0], $cat, '');
	pg_assert_true(strpos($html, 'masonry-item') !== false && strpos($html, '^^__result_image^^') !== false, 'catalog place: product row with its image');
	pg_assert_true(strpos($html, '^^__result_price^^') === false, 'no price: its line goes');
}
