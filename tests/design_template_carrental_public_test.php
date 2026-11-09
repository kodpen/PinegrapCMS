<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Car Rental design template's public pages
 * (includes/design_templates/pinegrap-carrental/pages_public.php): the
 * seven pages with their keys, names and descriptions, every page link
 * naming a page of the finished template, no class of another template,
 * unique element ids and search anchors on each page, a search section
 * for each desk and rental term with the anchor the footer links, the
 * questions opening their own answers, and the home placing the widgets
 * and band the other files build with links that make their choice in the
 * booking request.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The public pages as the template file reads them: parts.php first, then
// pages_public.php in the same scope. Read once per run.
function _test_crp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-carrental';
	include $dir . '/parts.php';
	$pages = include $dir . '/pages_public.php';
	$out = array('pages' => array(), 'locations' => $locations, 'terms' => $terms, 'location_titles' => $location_titles,
	             'deal_choices' => $deal_choices, 'class_titles' => $class_titles, 'footer' => $footer());
	foreach ($pages as $p) $out['pages'][$p['key']] = $p;
	return $out;
}

// Every node of a tree, depth first.
function _test_crp_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_crp_nodes($child, $out);
	}
}

// The search sections of a page, by anchor.
function _test_crp_sections($tree)
{
	$nodes = array();
	_test_crp_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		if (!empty($n['props']['searchSection'])) $out[$n['props']['id']] = $n;
	}
	return $out;
}

function test_carrental_public_pages_are_the_plans()
{
	$d = _test_crp_load();
	pg_assert_same(array('home', 'locations', 'extras', 'deals', 'terms', 'about', 'faq'), array_keys($d['pages']), 'the seven public pages in order');
	$names = array();
	foreach ($d['pages'] as $key => $p) {
		pg_assert_same('public', $p['folder'], "$key: public folder");
		pg_assert_true((bool)preg_match('/^[a-z-]+$/', $p['name']), "$key: page name");
		pg_assert_true(!isset($names[$p['name']]), "$key: page name used once");
		$names[$p['name']] = true;
		pg_assert_true(trim($p['title']) !== '', "$key: title");
		$len = strlen($p['meta_description']);
		pg_assert_true($len > 60 && $len <= 170, "$key: description length ($len)");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
	}
}

function test_carrental_public_pages_link_template_pages()
{
	$d = _test_crp_load();
	$known = array_flip(array(
		'home', 'locations', 'extras', 'deals', 'terms', 'about', 'faq',
		'fleet', 'car', 'book', 'business', 'contact', 'blog', 'privacy',
	));
	foreach ($d['pages'] as $key => $p) {
		$nodes = array();
		_test_crp_nodes($p['tree'], $nodes);
		$ids = array();
		foreach ($nodes as $n) {
			$encoded = json_encode($n['props']);
			preg_match_all('/\{\{page:([a-z_]+)\}\}/', $encoded, $m);
			foreach ($m[1] as $target) pg_assert_true(isset($known[$target]), "$key: link to a page of the template: $target");
			$class = isset($n['props']['cssClass']) ? $n['props']['cssClass'] : '';
			pg_assert_false((bool)preg_match('/(^|\s)(pdl|pht|pgy|pre|pap|pdt)-/', $class), "$key: no class of another template ($class)");
			pg_assert_false((bool)preg_match('/(^|\s)opacity-/', $class) && isset($n['props']['text']), "$key: no faded text ($class)");
			if (isset($n['props']['id'])) {
				$id = $n['props']['id'];
				pg_assert_true(!isset($ids[$id]), "$key: id $id used once");
				$ids[$id] = true;
			}
		}
	}
}

// Each desk has its search section on the locations page under the anchor
// the footer links, with the words it may be searched by; each rental
// term has its own on the terms page.
function test_carrental_public_desks_and_terms_are_searchable()
{
	$d = _test_crp_load();
	$sections = _test_crp_sections($d['pages']['locations']['tree']);
	$footer = json_encode($d['footer']);
	foreach ($d['locations'] as $key => $l) {
		$id = 'cr-loc-' . $key;
		pg_assert_true(isset($sections[$id]), "desk $key: a search section");
		pg_assert_true(trim($sections[$id]['props']['searchKeywords']) !== '', "desk $key: search words");
		pg_assert_true(strpos($footer, '{{page:locations}}#' . $id) !== false, "desk $key: the footer links its section");
		$nodes = array();
		_test_crp_nodes($sections[$id], $nodes);
		$hrefs = array();
		foreach ($nodes as $n) if (isset($n['props']['href'])) $hrefs[] = $n['props']['href'];
		pg_assert_true(in_array('{{page:book}}?' . http_build_query(array('location' => $l['title']), '', '&', PHP_QUERY_RFC3986), $hrefs, true), "desk $key: books with the desk chosen");
	}
	$terms = _test_crp_sections($d['pages']['terms']['tree']);
	foreach (array_keys($d['terms']) as $key) pg_assert_true(isset($terms['cr-term-' . $key]), "term $key: a search section");
}

function test_carrental_public_faq_opens_its_answers()
{
	$d = _test_crp_load();
	$nodes = array();
	_test_crp_nodes($d['pages']['faq']['tree'], $nodes);
	$targets = array();
	$panels = array();
	foreach ($nodes as $n) {
		if (empty($n['props']['_attrs'])) continue;
		foreach ($n['props']['_attrs'] as $a) {
			if ($a['name'] === 'data-bs-target') $targets[] = substr($a['value'], 1);
			if ($a['name'] === 'data-bs-parent') {
				pg_assert_same('#cr-faq', $a['value'], 'answer closes with the others');
				$panels[] = $n['props']['id'];
			}
		}
	}
	pg_assert_true(count($targets) >= 6, 'questions on the page');
	pg_assert_same($targets, $panels, 'each question opens its own answer');
}

function test_carrental_home_places_the_other_parts()
{
	$d = _test_crp_load();
	$nodes = array();
	_test_crp_nodes($d['pages']['home']['tree'], $nodes);
	$widgets = array();
	$shared = array();
	$queries = array('location' => array(), 'deal' => array(), 'class' => array());
	foreach ($nodes as $n) {
		if (isset($n['props']['templateWidget'])) $widgets[] = $n['props']['templateWidget'];
		if (isset($n['props']['templateShared'])) $shared[] = $n['props']['templateShared'];
		if (isset($n['props']['href']) && preg_match('/^\{\{page:(book|fleet)\}\}\?(.*)$/', $n['props']['href'], $m)) {
			parse_str($m[2], $q);
			foreach ($q as $param => $value) $queries[$param][$value] = true;
		}
	}
	foreach (array('home_cars', 'google_reviews', 'home_posts', 'newsletter_band') as $w) {
		pg_assert_true(in_array($w, $widgets, true), "home places widget $w");
	}
	foreach (array('account_bar', 'header', 'booking_band', 'footer') as $s) {
		pg_assert_true(in_array($s, $shared, true), "home places shared $s");
	}
	pg_assert_same(array('class', 'deal', 'location'), array_values(array_intersect(array('class', 'deal', 'location'), array_keys($queries))), 'the home chooses classes, deals and desks');
	foreach (array('location' => 'location_titles', 'deal' => 'deal_choices', 'class' => 'class_titles') as $param => $list) {
		foreach (array_keys($queries[$param]) as $value) pg_assert_true(in_array($value, $d[$list], true), "home: $param $value is an answer of its list");
	}
	pg_assert_same(count($d['locations']), count($queries['location']), 'every desk can be chosen from the home');
}
