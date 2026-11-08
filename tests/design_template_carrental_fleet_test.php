<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Car Rental design template's fleet
 * (includes/design_templates/pinegrap-carrental/fleet.php): the pages and
 * widgets with their places, the Fleet form with its fields and one sample
 * per sample car (every pick list value an answer, numbers whole), lists
 * and the car page that read only fields the form has or the renderer's
 * own tokens, the filters (?class=, ?gearbox=, ?fuel=, cars taken out of
 * the fleet left out, the shown car left out of the other cars), the
 * filter links in the words of the lists, the Book button sending the car
 * to the booking request, links that name template pages, the template's
 * ids and classes, no faded text, no fixed date and every literal in
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

// The page keys of the finished template (_test_cr_page_keys()).
require_once __DIR__ . '/design_template_carrental_test.php';

// parts.php, pages_public.php and fleet.php in one scope, as the template
// file reads them. Read once per run.
function _test_crf_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-carrental';
	include $dir . '/parts.php';
	$public = include $dir . '/pages_public.php';
	$cars_before = $cars;
	$part = include $dir . '/fleet.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$home = null;
	foreach ($public as $p) if ($p['key'] === 'home') $home = $p;
	$rates = array();
	foreach (array_keys($cars) as $key) $rates[$key] = $car_rate($key);
	$out = array(
		'part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'home' => $home,
		'cars_kept' => $cars_before === $cars, 'cars' => $cars, 'classes' => $classes, 'rates' => $rates,
		'class_titles' => $class_titles, 'transmission_titles' => $transmission_titles, 'fuel_titles' => $fuel_titles,
		'car_titles' => $car_titles, 'yes_no' => $fleet_yes_no,
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_crf_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_crf_nodes($c, $out);
}

// The tag, type, options and _cf of every form control of a tree, by name.
function _test_crf_controls($tree)
{
	$nodes = array();
	_test_crf_nodes($tree, $nodes);
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

// The tokens a tree's bindings read.
function _test_crf_tokens($tree)
{
	$nodes = array();
	_test_crf_nodes($tree, $nodes);
	$tokens = array();
	foreach ($nodes as $n) {
		foreach ((array)($n['props']['_bindings'] ?? array()) as $t) $tokens[$t] = true;
	}
	return array_keys($tokens);
}

function test_carrental_fleet_pages_and_widgets()
{
	$d = _test_crf_load();
	pg_assert_true($d['cars_kept'], 'fleet.php leaves the sample cars of parts.php as they are');
	pg_assert_same(array('fleet', 'car', 'car_new'), array_keys($d['pages']), 'the fleet\'s pages in order');
	pg_assert_same(array('car_form', 'fleet_list', 'home_cars', 'car_more', 'car_detail'), array_keys($d['widgets']), 'the fleet\'s widgets');
	foreach (array('fleet', 'car') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	pg_assert_same('private', $d['pages']['car_new']['folder'], 'only the staff open car_new');
	pg_assert_true(!empty($d['pages']['car_new']['noindex']), 'car_new is not indexed');
	$names = array();
	foreach ($d['pages'] as $key => $p) {
		foreach (array('name', 'title', 'tree') as $f) pg_assert_true(!empty($p[$f]), "$key has $f");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true((bool)preg_match('/^[a-z-]+$/', $p['name']), "$key: the page name is an address");
		pg_assert_true(!isset($names[$p['name']]), "$key: page name used once");
		$names[$p['name']] = true;
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_false(isset($w['requires']), "$key stands without the shop");
		$tree = $w['page'] === 'home' ? $d['home']['tree'] : $d['pages'][$w['page']]['tree'];
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($tree), "$key is placed on its page ({$w['page']})");
	}
	pg_assert_same('home', $d['widgets']['home_cars']['page'], 'the home\'s featured cars');
}

// The Fleet form is a collection with one sample per sample car; every
// sample fills every field, a pick list's value is one of its answers and
// a number is whole. Featured and Available are the staff's.
function test_carrental_fleet_form_and_samples()
{
	$d = _test_crf_load();
	$form = $d['pages']['car_new']['form'];
	$controls = _test_crf_controls($d['widgets']['car_form']['tree']);
	pg_assert_same(lang('Fleet'), $form['form_name'], 'the Fleet form');
	pg_assert_same(1, $form['collection'], 'the Fleet form is a collection');
	pg_assert_same('{{tab:fleet}}', $form['confirmation_page_id'], 'a new car opens the fleet');
	pg_assert_same(array('title', 'make', 'model', 'class', 'transmission', 'fuel', 'seats', 'doors', 'bags', 'min_age',
		'rate', 'rate_label', 'deposit', 'photo_1', 'photo_2', 'photo_3', 'summary', 'description', 'features', 'featured', 'available'),
		array_keys($controls), 'the Fleet form\'s fields');
	pg_assert_true(isset($controls[$form['share_image_field']]), 'the share image is a field');
	foreach (array('seats', 'doors', 'bags', 'min_age', 'rate') as $f) pg_assert_same('number', $controls[$f]['cf']['kind'] ?? '', "$f is a number field");
	foreach (array('photo_1', 'photo_2', 'photo_3') as $f) pg_assert_same('image', $controls[$f]['cf']['kind'] ?? '', "$f is an image field");
	foreach (array('featured', 'available') as $f) {
		pg_assert_true(!empty($controls[$f]['cf']['office_use_only']), "$f is the staff's");
		pg_assert_same($d['yes_no'], $controls[$f]['options'], "$f: Yes or No");
	}
	pg_assert_same($d['class_titles'], $controls['class']['options'], 'the classes');
	pg_assert_same($d['transmission_titles'], $controls['transmission']['options'], 'the gearboxes');
	pg_assert_same($d['fuel_titles'], $controls['fuel']['options'], 'the fuels');
	pg_assert_same(count($d['cars']), count($form['sample_records']), 'one record per sample car');
	$names = array_keys($controls);
	sort($names);
	$featured = 0;
	foreach (array_values($form['sample_records']) as $n => $r) {
		$rk = array_keys($r);
		sort($rk);
		pg_assert_same($names, $rk, "sample $n fills every field and no other");
		foreach ($controls as $name => $c) {
			pg_assert_true(trim((string)$r[$name]) !== '', "sample $n: $name filled");
			if ($c['tag'] === 'select') pg_assert_true(in_array($r[$name], $c['options'], true), "sample $n: $name '{$r[$name]}' is an answer");
			if (($c['cf']['kind'] ?? '') === 'number') pg_assert_true(ctype_digit($r[$name]), "sample $n: $name is a whole number");
		}
		pg_assert_true(in_array($r['title'], $d['car_titles'], true), "sample $n: the title is an answer of the booking request's car list");
		pg_assert_same($d['yes_no'][0], $r['available'], "sample $n: available");
		if ($r['featured'] === $d['yes_no'][0]) $featured++;
	}
	pg_assert_same(3, $featured, 'three featured cars for the home');
	$keys = array_keys($d['cars']);
	foreach ($keys as $n => $key) {
		pg_assert_same($d['rates'][$key], $form['sample_records'][$n]['rate_label'], "sample $n: the rate as the cards show it");
		pg_assert_contains($form['sample_records'][$n]['rate'], $d['rates'][$key], "sample $n: the rate label names the rate");
	}
}

// The cards, the car page and the filters read only fields the form has,
// or the renderer's own tokens.
function test_carrental_fleet_bindings_name_fields()
{
	$d = _test_crf_load();
	$fields = array_keys(_test_crf_controls($d['widgets']['car_form']['tree']));
	$own = array('form_item_view', 'not_found', '__edit_url', 'can_edit', 'record_found', 'record_not_found', '__page_link');
	$standard = pg_sw_standard_fields();
	foreach (array('fleet_list', 'home_cars', 'car_more', 'car_detail') as $key) {
		foreach (_test_crf_tokens($d['widgets'][$key]['tree']) as $t) {
			pg_assert_true(in_array($t, $fields, true) || in_array($t, $own, true), "$key reads $t, a field of the Fleet form");
		}
		foreach ((array)($d['widgets'][$key]['config']['filters'] ?? array()) as $f) {
			pg_assert_true(in_array($f['field'], $fields, true) || isset($standard[$f['field']]), "$key filters on {$f['field']}, a field of the Fleet form");
		}
	}
	$card = _test_crf_tokens($d['widgets']['fleet_list']['tree']);
	foreach (array('title', 'class', 'photo_1', 'rate_label', 'seats', 'bags', 'transmission', 'fuel') as $t) {
		pg_assert_true(in_array($t, $card, true), "a car card shows $t");
	}
	$detail = _test_crf_tokens($d['widgets']['car_detail']['tree']);
	foreach (array('class', 'seats', 'doors', 'bags', 'transmission', 'fuel', 'min_age', 'deposit', 'rate_label', 'features', 'description',
		'photo_1', 'photo_2', 'photo_3', 'record_found', 'record_not_found', '__edit_url') as $t) {
		pg_assert_true(in_array($t, $detail, true), "the car page shows $t");
	}
	$c = $d['widgets']['car_detail']['config'];
	pg_assert_same(array('form_item_view', '{{tab:car_new}}', 'public'), array($c['regionType'], $c['custom_form_page_id'], $c['access_control']), 'the car page reads the Fleet form');
}

// The fleet reads ?class=, ?gearbox= and ?fuel=; every list leaves out a
// car taken out of the fleet; the car page's other cars leave out the one
// shown; the filter links name answers of the lists.
function test_carrental_fleet_filters()
{
	$d = _test_crf_load();
	$w = $d['widgets'];
	$available = array('field' => 'available', 'operator' => 'is equal to', 'value' => $d['yes_no'][0], 'dynamic_value' => '', 'dynamic_value_attribute' => '');
	$url = function ($field, $param) {
		return array('field' => $field, 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'url parameter', 'dynamic_value_attribute' => $param);
	};
	pg_assert_same(array($available, $url('class', 'class'), $url('transmission', 'gearbox'), $url('fuel', 'fuel')),
		$w['fleet_list']['config']['filters'], 'the fleet by class, gearbox and fuel');
	pg_assert_same(array(array('field' => 'featured', 'operator' => 'is equal to', 'value' => $d['yes_no'][0], 'dynamic_value' => '', 'dynamic_value_attribute' => ''), $available),
		$w['home_cars']['config']['filters'], 'the home\'s featured cars');
	pg_assert_same(array(array('field' => 'reference_code', 'operator' => 'is not equal to', 'value' => '', 'dynamic_value' => 'shown record', 'dynamic_value_attribute' => ''), $available),
		$w['car_more']['config']['filters'], 'the other cars but the one shown');
	$controls = _test_crf_controls($w['car_form']['tree']);
	foreach (array('fleet_list', 'home_cars', 'car_more') as $key) {
		$c = $w[$key]['config'];
		pg_assert_same('form_list_view', $c['regionType'], "$key: a list");
		pg_assert_same('{{tab:car_new}}', $c['custom_form_page_id'], "$key: the Fleet form");
		pg_assert_same('{{tab:car}}', $c['detail_page_id'], "$key: opens the car page");
		pg_assert_same('number', $controls[$c['order_by_field']]['cf']['kind'] ?? '', "$key: sorts on a number field");
	}
	pg_assert_same(3, $w['home_cars']['config']['max_results'], 'three cars on the home');
	pg_assert_same(3, $w['car_more']['config']['max_results'], 'three other cars');
	$answers = array('class' => $d['class_titles'], 'gearbox' => $d['transmission_titles'], 'fuel' => $d['fuel_titles']);
	$nodes = array();
	_test_crf_nodes($d['pages']['fleet']['tree'], $nodes);
	$seen = array();
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:fleet}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:fleet}}?')), $q);
		pg_assert_same(1, count($q), "$href: one filter");
		$param = key($q);
		pg_assert_true(isset($answers[$param]), "$href: a parameter the list reads");
		pg_assert_true(in_array($q[$param], $answers[$param], true), "$href: an answer of the list");
		$seen[$param][$q[$param]] = true;
	}
	foreach ($answers as $param => $list) pg_assert_same(count($list), count($seen[$param] ?? array()), "a link per $param answer");
}

// The car page's Book button opens the booking request with the car's
// title as ?car=, an answer of the request's car list for the samples.
function test_carrental_fleet_car_page_books_the_car()
{
	$d = _test_crf_load();
	$nodes = array();
	_test_crf_nodes($d['widgets']['car_detail']['tree'], $nodes);
	$sent = array();
	foreach ($nodes as $n) {
		if (!isset($n['props']['_bindParams']['href'])) continue;
		pg_assert_same('__page_link', $n['props']['_bindings']['href'], 'a link with the record\'s values');
		$sent[] = $n['props']['_bindParams']['href'];
	}
	pg_assert_same(array(array('page_id' => '{{tab:book}}', 'params' => array(array('name' => 'car', 'token' => 'title')))), $sent, 'one Book button sending ?car=');
	$json = json_encode($d['widgets']['car_detail']['tree']);
	pg_assert_contains(json_encode(lang('or similar')), $json, 'a class is promised, not the model');
}

// Every link names a page the finished template carries; ids and classes
// are the template's; no faded text.
function test_carrental_fleet_links_ids_and_classes()
{
	$d = _test_crf_load();
	$known = array_flip(_test_cr_page_keys());
	$json = json_encode(array($d['part']['pages'], $d['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	$own = array('pcr-brand', 'pcr-display', 'pcr-eyebrow', 'pcr-hero', 'pcr-lift', 'pcr-price');
	$trees = array();
	foreach ($d['pages'] as $key => $p) $trees[$key] = $p['tree'];
	foreach ($d['widgets'] as $key => $w) $trees[$key] = $w['tree'];
	foreach ($trees as $key => $tree) {
		$nodes = array();
		_test_crf_nodes($tree, $nodes);
		$ids = array();
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				// A template's look prefix is p and two letters (pcr-, pdl-, pht-).
				if (preg_match('/^p[a-z]{2}-/', $c)) {
					pg_assert_true(in_array($c, $own, true), "$key: class $c is the template's");
				}
				pg_assert_false(strpos($c, 'opacity-') === 0, "$key: no faded text ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'cr-') === 0, "$key: id $id is the template's");
			pg_assert_true(!isset($ids[$id]), "$key: id $id used once");
			$ids[$id] = true;
		}
	}
}

// The source carries no fixed date.
function test_carrental_fleet_no_fixed_dates()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-carrental/fleet.php');
	$src = preg_replace('/@copyright[^\n]*/', '', $src);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $src), 'fleet.php: no year');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $src), 'fleet.php: no month');
}

// Every literal has its Turkish; the fleet reads "filo".
function test_carrental_fleet_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-carrental/fleet.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (isset($map[$key]) && preg_match('/\bfleet\b/i', $key)) {
			pg_assert_true(mb_stripos($map[$key], 'filo', 0, 'UTF-8') !== false, "'$key' says filo");
		}
	}
	// Shared keys whose Turkish means something else in a car rental.
	foreach (array('Doors', 'Choose a class…', 'Available') as $k) {
		pg_assert_false(strpos($src, "lang('" . $k . "')") !== false, "fleet.php does not use lang('$k')");
	}
}
