<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Home Services design template's services and team
 * (includes/design_templates/pinegrap-homeservices/services.php): the six
 * pages and nine widgets, each placed on its page; the Services and Team
 * forms are collections whose samples fill every field with an answer of
 * its pick list; the lists read the category from the address and the
 * home shows the featured services; the service page takes rated reviews
 * and opens the visit booking and the estimate request with its service;
 * links, ids and classes are the template's; every literal has its Turkish.
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
require_once(__DIR__ . '/design_template_homeservices_test.php');

// The services' part as the template file reads it: parts.php first, then
// services.php in the same scope. Read once per run.
function _test_hss_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-homeservices';
	include $dir . '/parts.php';
	$part = include $dir . '/services.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('pages' => $pages, 'widgets' => $part['widgets'], 'services' => $services, 'team' => $team,
		'category_titles' => $category_titles, 'team_roles' => $team_roles, 'price_text' => $price_text,
		'crew_text' => $crew_text, 'includes_text' => $includes_text, 'call_out_text' => $money($call_out));
	return $out;
}

// Every node of a tree, depth first.
function _test_hss_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_hss_nodes($c, $out);
}

// The name and options of every form control of a tree.
function _test_hss_controls($node)
{
	$nodes = array();
	_test_hss_nodes($node, $nodes);
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

function test_homeservices_services_pages_and_widgets()
{
	$d = _test_hss_load();
	pg_assert_same(array('services', 'service', 'team', 'team_member', 'service_new', 'team_new'), array_keys($d['pages']));
	pg_assert_same(array('service_form', 'team_form', 'services_list', 'home_services', 'team_list', 'home_team', 'service_reviews', 'service_detail', 'team_detail'), array_keys($d['widgets']));
	foreach (array('services', 'service', 'team', 'team_member') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	foreach (array('service_new', 'team_new') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "only the staff open $key");
		pg_assert_true(!empty($d['pages'][$key]['noindex']), "$key is not indexed");
	}
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
	pg_assert_same('home', $d['widgets']['home_services']['page']);
	pg_assert_same('home', $d['widgets']['home_team']['page']);
}

// Each form is a collection with a sample per record; every sample value
// has its field and is filled, a pick list's value is one of its answers,
// the photos are images.
function test_homeservices_services_forms_and_samples()
{
	$d = _test_hss_load();
	$forms = array(
		'service_new' => array('service_form', 'services', array('name', 'category', 'duration', 'crew', 'price', 'summary', 'description', 'includes', 'photo', 'featured')),
		'team_new'    => array('team_form', 'team', array('name', 'role', 'years', 'summary', 'bio', 'photo')),
	);
	foreach ($forms as $page_key => $w) {
		$form = $d['pages'][$page_key]['form'];
		pg_assert_same(1, $form['collection'], "$page_key: a collection");
		pg_assert_same(count($d[$w[1]]), count($form['sample_records']), "$page_key: one sample per record");
		$controls = _test_hss_controls($d['widgets'][$w[0]]['tree']);
		pg_assert_same($w[2], array_keys($controls), "$page_key: its fields");
		pg_assert_same('image', $controls['photo']['cf']['kind'] ?? '', "$page_key: the photo is an image");
		pg_assert_same('photo', $form['share_image_field'], "$page_key: the photo is the share image");
		foreach ($form['sample_records'] as $n => $r) {
			pg_assert_same($w[2], array_keys($r), "$page_key sample $n: every field");
			foreach ($r as $name => $value) {
				pg_assert_true($value !== '', "$page_key sample $n: $name filled");
				if ($controls[$name]['tag'] === 'select') {
					pg_assert_true(in_array($value, $controls[$name]['options'], true), "$page_key sample $n: $name '$value' is an answer");
				}
			}
		}
	}
	$service = _test_hss_controls($d['widgets']['service_form']['tree']);
	pg_assert_same($d['category_titles'], $service['category']['options'], 'the categories');
	pg_assert_true(!empty($service['featured']['cf']['office_use_only']), 'featured is the staff\'s');
	$team = _test_hss_controls($d['widgets']['team_form']['tree']);
	pg_assert_same($d['team_roles'], $team['role']['options'], 'the roles');
	pg_assert_same('number', $team['years']['cf']['kind'] ?? '', 'years sort as a number');
	// The service samples carry the words the cards show.
	$featured = 0;
	foreach (array_values($d['pages']['service_new']['form']['sample_records']) as $n => $r) {
		$key = array_keys($d['services'])[$n];
		pg_assert_same(call_user_func($d['price_text'], $key), $r['price'], "service sample $n: its price");
		pg_assert_same(call_user_func($d['crew_text'], $key), $r['crew'], "service sample $n: its crew");
		pg_assert_same(call_user_func($d['includes_text'], $key), $r['includes'], "service sample $n: what it includes");
		if ($r['featured'] === lang('Yes')) $featured++;
	}
	pg_assert_same(3, $featured, 'three featured services');
}

// The service list reads the category the filter links, the footer and
// the category cards send; the home shows the featured services, three at
// most, and four of the team.
function test_homeservices_services_filters()
{
	$d = _test_hss_load();
	$filters = $d['widgets']['services_list']['config']['filters'];
	pg_assert_same(array(array('field' => 'category', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'url parameter', 'dynamic_value_attribute' => 'category')), $filters, 'the category from the address');
	$nodes = array();
	_test_hss_nodes($d['pages']['services']['tree'], $nodes);
	$seen = array();
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:services}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:services}}?')), $q);
		pg_assert_true(in_array($q['category'], $d['category_titles'], true), "?category={$q['category']} is an answer");
		$seen[$q['category']] = true;
	}
	pg_assert_same(count($d['category_titles']), count($seen), 'a filter link per category');
	$c = $d['widgets']['home_services']['config'];
	pg_assert_same(array(array('field' => 'featured', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => '')), $c['filters'], 'the featured services');
	pg_assert_same(3, $c['max_results'], 'three at most');
	pg_assert_same(4, $d['widgets']['home_team']['config']['max_results'], 'four of the team');
	foreach (array('team_list', 'home_team') as $key) {
		pg_assert_same('years', $d['widgets'][$key]['config']['order_by_field'], "$key: by years");
		pg_assert_same('DESC', $d['widgets'][$key]['config']['order_by_direction'], "$key: the longest first");
	}
}

// The service page takes reviews with stars in its own Comments widget,
// opens the visit booking and the estimate request with its service
// (?service= from the record's name) and names the call-out charge.
function test_homeservices_services_record_pages()
{
	$d = _test_hss_load();
	$c = $d['pages']['service']['comments'];
	pg_assert_true($c['allow_new'] && $c['rating'], 'reviews with stars');
	pg_assert_same('{{tab:email_reply}}', $c['email_page'], 'the company is told by e-mail');
	pg_assert_same('comments', $d['widgets']['service_reviews']['config']['regionType']);
	$links = array();
	$nodes = array();
	_test_hss_nodes($d['widgets']['service_detail']['tree'], $nodes);
	foreach ($nodes as $n) {
		if (($n['props']['_bindings']['href'] ?? '') === '__page_link') $links[] = $n['props']['_bindParams']['href'];
	}
	pg_assert_same(array(
		array('page_id' => '{{tab:book}}', 'params' => array(array('name' => 'service', 'token' => 'name'))),
		array('page_id' => '{{tab:estimate}}', 'params' => array(array('name' => 'service', 'token' => 'name'))),
	), $links, 'the service page opens both forms with its service');
	pg_assert_contains($d['call_out_text'], json_encode($d['widgets']['service_detail']['tree'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'the service page names the call-out charge');
	$expect = array(
		'service_detail' => array('category', 'name', 'summary', 'photo', 'description', 'includes', 'price', 'duration', 'crew'),
		'team_detail'    => array('role', 'name', 'years', 'summary', 'bio', 'photo'),
	);
	foreach ($expect as $key => $tokens) {
		$json = json_encode($d['widgets'][$key]['tree']);
		pg_assert_contains('"eo_visible_if":"record_found"', $json, "$key: the record");
		pg_assert_contains('"eo_visible_if":"record_not_found"', $json, "$key: the not-found note");
		$nodes = array();
		_test_hss_nodes($d['widgets'][$key]['tree'], $nodes);
		$read = array();
		foreach ($nodes as $n) {
			foreach ((array)($n['props']['_bindings'] ?? array()) as $token) $read[$token] = true;
		}
		foreach ($tokens as $t) pg_assert_true(isset($read[$t]), "$key reads $t");
	}
}

function test_homeservices_services_links_ids_and_classes()
{
	$d = _test_hss_load();
	$known = array_flip(_test_hs_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p['tree'];
	foreach ($d['widgets'] as $w) $trees[] = $w['tree'];
	$json = json_encode(array($d['pages'], $d['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_hs_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$nodes = array();
	foreach ($trees as $t) _test_hss_nodes($t, $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pbk|psh|pwd|pmu)-/', $c), "class $c is not another template's");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'hs-') === 0, "id $id is the template's");
	}
}

// The sample is never dated.
function test_homeservices_services_no_fixed_dates()
{
	$d = _test_hss_load();
	$json = json_encode(array($d['pages'], $d['widgets']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; a service reads "hizmet", a visit
// "ziyaret", an estimate "teklif", and none says "rezervasyon".
function test_homeservices_services_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-homeservices/services.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\b[Ss]ervices?\b/', $key) && strpos($key, ' ') !== false) {
			// A sentence about a yearly service of a heater uses the word in
			// its other sense (bakım).
			if (strpos($key, 'yearly service') === false) pg_assert_true(mb_stripos($tr, 'hizmet', 0, 'UTF-8') !== false, "'$key' says hizmet");
		}
		if (preg_match('/\b[Vv]isits?\b/', $key)) pg_assert_true(mb_stripos($tr, 'ziyaret', 0, 'UTF-8') !== false, "'$key' says ziyaret");
		if (preg_match('/\b[Ee]stimates?\b/', $key)) pg_assert_true(mb_stripos($tr, 'teklif', 0, 'UTF-8') !== false, "'$key' says teklif");
		pg_assert_false(mb_stripos($tr, 'rezervasyon', 0, 'UTF-8') !== false, "'$key' never says rezervasyon");
	}
	// "Service" and "Services" read Servis/Servisler: the part names a
	// service with its own keys.
	foreach (array("lang('Service')", "lang('Services')", "lang('service')") as $k) {
		pg_assert_false(strpos($src, $k) !== false, "the part does not use $k");
	}
}
