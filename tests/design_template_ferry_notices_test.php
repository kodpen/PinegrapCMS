<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Ferry design template's travel notices
 * (includes/design_templates/pinegrap-ferry/notices.php): the pages and
 * widgets, the Travel Notices collection with a sample per notice whose
 * values fit their fields, the list's filters reading what the filter
 * bar, the footer and the route pages send, the home lists the featured
 * notices, the notice page opens the list on its route and the booking
 * request with the route chosen, every page link names a template page,
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

// The page keys of the finished template (_test_fy_page_keys()).
require_once(__DIR__ . '/design_template_ferry_test.php');

// The notices' part as the template file reads it: parts.php first, then
// the public pages and notices.php in the same scope. Read once per run.
function _test_fyn_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-ferry';
	include $dir . '/parts.php';
	$public = include $dir . '/pages_public.php';
	$part = include $dir . '/notices.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'sample_notices' => $sample_notices,
		'notice_kinds' => $notice_kinds, 'notice_route_choices' => $notice_route_choices, 'route_titles' => $route_titles,
		'public' => $public, 'footer' => $footer());
	return $out;
}

// Every node of a tree, depth first.
function _test_fyn_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_fyn_nodes($c, $out);
}

// The name, options and settings of every form control of a tree.
function _test_fyn_controls($node)
{
	$nodes = array();
	_test_fyn_nodes($node, $nodes);
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

// The query of every link to {{page:notices}} in a tree.
function _test_fyn_list_queries($tree)
{
	$nodes = array();
	_test_fyn_nodes($tree, $nodes);
	$out = array();
	$prefix = '{{page:notices}}?';
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, $prefix) !== 0) continue;
		parse_str(substr($href, strlen($prefix)), $q);
		$out[] = $q;
	}
	return $out;
}

function test_ferry_notices_pages_and_widgets()
{
	$d = _test_fyn_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('notices', 'notice', 'notice_new'), array_keys($d['pages']));
	pg_assert_same(array('notice_form', 'notices_list', 'home_notices', 'notice_detail'), array_keys($d['widgets']));
	foreach (array('notices', 'notice') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	pg_assert_same('private', $d['pages']['notice_new']['folder'], 'only the staff add a notice');
	pg_assert_true(!empty($d['pages']['notice_new']['noindex']), 'the staff page is not indexed');
	$slugs = array();
	foreach ($d['public']['pages'] as $p) $slugs[$p['name']] = $p['key'];
	$known = array_flip(_test_fy_page_keys());
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a planned page");
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
	$home = null;
	foreach ($d['public']['pages'] as $p) if ($p['key'] === 'home') $home = $p;
	pg_assert_contains('"templateWidget":"home_notices"', json_encode($home['tree']), 'the home places the featured notices');
	pg_assert_same('home', $d['widgets']['home_notices']['page']);
}

// The form is a collection with a sample per notice; every sample value
// has its field and is filled, a pick list's value is one of its answers,
// the photo is an image and featured is the staff's. The card's bindings
// read fields of this form.
function test_ferry_notices_form_and_samples()
{
	$d = _test_fyn_load();
	$form = $d['pages']['notice_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same(count($d['sample_notices']), count($form['sample_records']), 'one sample per notice');
	$controls = _test_fyn_controls($d['widgets']['notice_form']['tree']);
	$fields = array('title', 'kind', 'route', 'when', 'summary', 'details', 'photo_1', 'featured');
	pg_assert_same($fields, array_keys($controls), 'the fields of the form');
	pg_assert_same('image', $controls['photo_1']['cf']['kind'] ?? '', 'the photo is an image');
	pg_assert_true(!empty($controls['featured']['cf']['office_use_only']), 'featured is the staff\'s');
	pg_assert_true(isset($controls[$form['share_image_field']]), 'the share image is a field');
	pg_assert_same($d['notice_kinds'], $controls['kind']['options'], 'the kinds');
	pg_assert_same($d['notice_route_choices'], $controls['route']['options'], 'the routes and all routes');
	$featured = 0;
	foreach ($form['sample_records'] as $n => $r) {
		pg_assert_same($fields, array_keys($r), "sample $n: every field");
		foreach ($r as $name => $value) {
			pg_assert_true($value !== '', "sample $n: $name filled");
			if ($controls[$name]['tag'] === 'select') {
				pg_assert_true(in_array($value, $controls[$name]['options'], true), "sample $n: $name '$value' is an answer");
			}
			pg_assert_false((bool)preg_match('/(19|20)[0-9]{2}/', $value), "sample $n: $name names no year");
		}
		if ($r['featured'] === lang('Yes')) $featured++;
	}
	pg_assert_same(3, $featured, 'three featured samples');
	pg_assert_true($featured <= $d['widgets']['home_notices']['config']['max_results'], 'the home shows every featured sample');
	$nodes = array();
	_test_fyn_nodes($d['widgets']['notices_list']['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach ((array)($n['props']['_bindings'] ?? array()) as $token) {
			if ($token === 'form_item_view') continue;
			pg_assert_true(isset($controls[$token]), "the card reads the field $token");
		}
	}
}

// The list reads the parameters the filter bar, the footer and the route
// pages send, each an answer of its pick list; the newest come first; the
// home lists the featured notices.
function test_ferry_notices_filters()
{
	$d = _test_fyn_load();
	$params = array();
	foreach ($d['widgets']['notices_list']['config']['filters'] as $f) {
		pg_assert_same('url parameter', $f['dynamic_value'], 'the filter reads the address');
		$params[$f['field']] = $f['dynamic_value_attribute'];
	}
	pg_assert_same(array('route' => 'route', 'kind' => 'kind'), $params, 'the list filters route and kind');
	$answers = array('route' => $d['notice_route_choices'], 'kind' => $d['notice_kinds']);
	$trees = array($d['pages']['notices']['tree'], $d['footer']);
	foreach ($d['public']['pages'] as $p) $trees[] = $p['tree'];
	$seen = array('route' => 0, 'kind' => 0);
	foreach ($trees as $tree) {
		foreach (_test_fyn_list_queries($tree) as $q) {
			foreach ($q as $param => $value) {
				pg_assert_true(isset($answers[$param]), "?$param is a filter of the list");
				if (!isset($answers[$param])) continue;
				pg_assert_true(in_array($value, $answers[$param], true), "?$param=$value is an answer");
				$seen[$param]++;
			}
		}
	}
	// The filter bar (every route and all routes), the footer and one link
	// on each route's page.
	pg_assert_same(count($d['notice_route_choices']) + count($d['route_titles']) * 2, $seen['route'], 'route links: the filter bar, the footer and the route pages');
	pg_assert_same(count($d['notice_kinds']), $seen['kind'], 'kind links: the filter bar');
	foreach (array('notices_list', 'home_notices') as $key) {
		$c = $d['widgets'][$key]['config'];
		pg_assert_same('{{tab:notice_new}}', $c['custom_form_page_id'], "$key: the Travel Notices form");
		pg_assert_same('{{tab:notice}}', $c['detail_page_id'], "$key: the notice page");
		pg_assert_same(array('submitted_date_and_time', 'DESC'), array($c['order_by_field'], $c['order_by_direction']), "$key: newest first");
	}
	pg_assert_same(array(array('field' => 'featured', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => '')),
		$d['widgets']['home_notices']['config']['filters'], 'the featured ones');
}

// The notice page shows the record or the not-found note, opens the list
// on its route and the booking request with the route chosen.
function test_ferry_notices_record_page()
{
	$d = _test_fyn_load();
	$nodes = array();
	_test_fyn_nodes($d['widgets']['notice_detail']['tree'], $nodes);
	$links = array();
	$bound = array();
	foreach ($nodes as $n) {
		if (($n['props']['_bindings']['href'] ?? '') === '__page_link') $links[] = $n['props']['_bindParams']['href'];
		foreach (array('text', 'src') as $k) if (isset($n['props']['_bindings'][$k])) $bound[] = $n['props']['_bindings'][$k];
	}
	pg_assert_same(array(
		array('page_id' => '{{tab:notices}}', 'params' => array(array('name' => 'route', 'token' => 'route'))),
		array('page_id' => '{{tab:booking_request}}', 'params' => array(array('name' => 'route', 'token' => 'route'))),
	), $links, 'the list on the route, the booking request on the route');
	foreach (array('title', 'kind', 'route', 'when', 'summary', 'details', 'photo_1') as $f) {
		pg_assert_true(in_array($f, $bound, true), "the page shows $f");
	}
	$json = json_encode($d['widgets']['notice_detail']['tree']);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the record');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
	pg_assert_contains('"eo_visible_if":"can_edit"', $json, 'the Edit button');
	// Every route a sample names is a booking route, or every route.
	foreach ($d['pages']['notice_new']['form']['sample_records'] as $n => $r) {
		pg_assert_true($r['route'] === $d['notice_route_choices'][0] || in_array($r['route'], $d['route_titles'], true), "sample $n: a booking route or every route");
	}
}

function test_ferry_notices_links_ids_and_classes()
{
	$d = _test_fyn_load();
	$known = array_flip(_test_fy_page_keys());
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
	foreach ($d['pages'] as $p) _test_fyn_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_fyn_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|pmn|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh|pmz|pai|pcp|prt)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'fy-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a travel notice reads "sefer duyurusu"
// and a booking request "rezervasyon talebi".
function test_ferry_notices_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-ferry/notices.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (strpos($key, '-') !== false && preg_match('/^[a-z-]+$/', $key)) continue;
		if (preg_match('/Travel Notices?/i', $key)) pg_assert_true(mb_stripos($tr, 'sefer duyuru', 0, 'UTF-8') !== false, "'$key' says sefer duyurusu");
		if (preg_match('/Booking Requests?/i', $key)) pg_assert_true(mb_stripos($tr, 'rezervasyon tale', 0, 'UTF-8') !== false, "'$key' says rezervasyon talebi");
	}
}
