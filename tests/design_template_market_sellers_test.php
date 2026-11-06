<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Marketplace design template's makers
 * (includes/design_templates/pinegrap-market/sellers.php): the three pages
 * and five widgets load from the parts, the Sellers form is a collection
 * whose samples fill every field it has with an answer its pick lists
 * offer, the list's filters read the parameters the category links send,
 * the home lists the featured makers, the maker page takes star reviews,
 * every page link names a page of the finished template and the trees
 * never link to a page that needs the shop, and every literal has its
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

require_once(__DIR__ . '/helpers/language_map.php');
// The page keys of the finished template and the shop's pages.
require_once(__DIR__ . '/design_template_market_test.php');

// The makers' part as the template file reads it: parts.php first, then
// sellers.php in the same scope. Read once per run.
function _test_market_sellers_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-market';
	include $dir . '/parts.php';
	$part = include $dir . '/sellers.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('pages' => $pages, 'widgets' => $part['widgets'], 'sellers' => $sellers, 'makers_in' => $makers_in,
		'shop_url' => $seller_shop_url, 'shop_on' => $seller_shop_on);
	return $out;
}

// The name and options of every form control of a tree.
function _test_market_sellers_controls($node, &$out)
{
	if (!is_array($node)) return;
	$tag = $node['props']['tag'] ?? '';
	if (in_array($tag, array('input', 'select', 'textarea'), true)) {
		$name = '';
		foreach ((array)($node['props']['_attrs'] ?? array()) as $a) {
			if ($a['name'] === 'name') $name = $a['value'];
		}
		$options = array();
		foreach ((array)($node['children'] ?? array()) as $o) {
			foreach ((array)($o['props']['_attrs'] ?? array()) as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $options[] = $a['value'];
			}
		}
		$out[$name] = array('tag' => $tag, 'options' => $options, 'cf' => $node['props']['_cf'] ?? array());
	}
	foreach ((array)($node['children'] ?? array()) as $c) _test_market_sellers_controls($c, $out);
}

function test_market_sellers_pages_and_widgets()
{
	$d = _test_market_sellers_load();
	pg_assert_same(array('sellers', 'seller', 'seller_new'), array_keys($d['pages']));
	pg_assert_same(array('seller_form', 'sellers_list', 'home_sellers', 'seller_detail', 'seller_reviews'), array_keys($d['widgets']));
	pg_assert_same('public', $d['pages']['sellers']['folder']);
	pg_assert_same('public', $d['pages']['seller']['folder']);
	pg_assert_same('private', $d['pages']['seller_new']['folder'], 'only the staff add a maker');
	foreach ($d['pages'] as $key => $p) {
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_false(isset($w['requires']), "$key stands without the shop");
		pg_assert_true($key === 'home_sellers' || isset($d['pages'][$w['page']]), "$key sits on a page of this part");
	}
	pg_assert_same('home', $d['widgets']['home_sellers']['page']);
}

// The Sellers form is a collection with a sample per maker; every sample
// value has its field, and a pick list's value is one of its answers.
function test_market_sellers_form_and_samples()
{
	$d = _test_market_sellers_load();
	$form = $d['pages']['seller_new']['form'];
	pg_assert_same(1, $form['collection']);
	pg_assert_same(count($d['sellers']), count($form['sample_records']), 'one sample per maker');
	$controls = array();
	_test_market_sellers_controls($d['widgets']['seller_form']['tree'], $controls);
	foreach (array('shop_name', 'maker', 'city', 'category', 'tagline', 'story', 'logo', 'cover', 'shop_url', 'featured') as $name) {
		pg_assert_true(isset($controls[$name]), "the form has $name");
	}
	pg_assert_same('image', $controls['logo']['cf']['kind'] ?? '', 'the logo is an image');
	pg_assert_same('image', $controls['cover']['cf']['kind'] ?? '', 'the cover is an image');
	pg_assert_true(!empty($controls['featured']['cf']['office_use_only']), 'featured is the staff\'s');
	$featured = 0;
	foreach ($form['sample_records'] as $n => $r) {
		foreach ($r as $name => $value) {
			pg_assert_true(isset($controls[$name]), "sample $n: field $name exists");
			if ($name === 'shop_url' && !$d['shop_on']) continue;
			pg_assert_true($value !== '', "sample $n: $name filled");
			if ($controls[$name]['tag'] === 'select') {
				pg_assert_true(in_array($value, $controls[$name]['options'], true), "sample $n: $name '$value' is an answer");
			}
		}
		if ($d['shop_on']) {
			pg_assert_same(1, preg_match('/\\{\\{product_group_path:([a-z]+)\\}\\}$/', $r['shop_url'], $g), "sample $n: a group's address");
			pg_assert_true(isset($d['sellers'][$g[1]]), "sample $n: the maker's own group");
		} else {
			pg_assert_same('', $r['shop_url'], "sample $n: no shop, no address");
		}
		if ($r['featured'] === lang('Yes')) $featured++;
	}
	pg_assert_same(3, $featured, 'three featured makers');
}

// The category tiles open the list with ?category=<title>; the list reads
// that parameter and ?city=. The home lists the featured makers.
function test_market_sellers_filters()
{
	$d = _test_market_sellers_load();
	$url = call_user_func($d['makers_in'], 'home');
	parse_str((string)parse_url(str_replace('{{page:sellers}}', 'x', $url), PHP_URL_QUERY), $q);
	$params = array();
	foreach ($d['widgets']['sellers_list']['config']['filters'] as $f) {
		pg_assert_same('url parameter', $f['dynamic_value']);
		$params[$f['field']] = $f['dynamic_value_attribute'];
	}
	pg_assert_same(array('category' => 'category', 'city' => 'city'), $params);
	pg_assert_true(isset($q['category']), 'the tile link sends what the list reads');
	$home = $d['widgets']['home_sellers']['config'];
	pg_assert_same(array(array('field' => 'featured', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => '')), $home['filters']);
	pg_assert_same(3, $home['max_results']);
}

// The maker page takes reviews with stars, in its own Comments widget.
function test_market_sellers_reviews()
{
	$d = _test_market_sellers_load();
	$c = $d['pages']['seller']['comments'];
	pg_assert_true($c['allow_new'] && $c['rating'], 'reviews with stars');
	pg_assert_same('comments', $d['widgets']['seller_reviews']['config']['regionType']);
	pg_assert_contains('"templateWidget":"seller_reviews"', json_encode($d['pages']['seller']['tree']));
	pg_assert_contains('"templateWidget":"seller_detail"', json_encode($d['pages']['seller']['tree']));
}

function test_market_sellers_links()
{
	$d = _test_market_sellers_load();
	$known = array_flip(_test_market_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p['tree'];
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json . json_encode($d['pages']), $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_market_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
}

function test_market_sellers_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-market/sellers.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (preg_match('/\bmakers?\b/i', $key) && !preg_match('/^[a-z-]+$/', $key) && isset($map[$key])) {
			pg_assert_true(mb_stripos($map[$key], 'üretici', 0, 'UTF-8') !== false, "'$key' says üretici");
		}
	}
}

// Without the shop the template has no shop page, so a maker's shop address
// is empty and the shop button's box leaves (pg-hide-if-empty) instead of
// linking to "#/".
function test_market_sellers_shop_url_without_shop()
{
	$d = _test_market_sellers_load();
	pg_assert_same('', call_user_func($d['shop_url'], 'clay', false), 'no shop: no address');
	pg_assert_same('{{page:shop}}/{{product_group_path:clay}}', call_user_func($d['shop_url'], 'clay', true), 'the shop: the group on the shop page');
}
