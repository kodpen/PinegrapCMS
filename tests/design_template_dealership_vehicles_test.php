<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Car Dealership design template's vehicles
 * (includes/design_templates/pinegrap-dealership/vehicles.php): the sample
 * records fill exactly the Vehicles form's fields with answers its pick
 * lists offer, the lists filter and sort on fields the form has (sorting
 * only on number fields), the address filters are the ones the shared
 * parts' links send, sold cars leave every list, the vehicle page's
 * buttons send the car to the request forms, and every page link names a
 * page of the finished template.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// parts.php, then vehicles.php in the same scope, as the template file
// reads them. Read once per run.
function _test_dlv_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-dealership';
	include $dir . '/parts.php';
	$samples_before = $vehicles;
	$part = include $dir . '/vehicles.php';
	$out = array(
		'part'          => $part,
		'samples_kept'  => $samples_before === $vehicles,
		'vehicles'      => $vehicles,
		'statuses'      => $vehicle_statuses,
		'conditions'    => $conditions,
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_dlv_nodes($node)
{
	$list = array($node);
	foreach (isset($node['children']) ? $node['children'] : array() as $child) {
		$list = array_merge($list, _test_dlv_nodes($child));
	}
	return $list;
}

function _test_dlv_attr($node, $name)
{
	foreach (isset($node['props']['_attrs']) ? $node['props']['_attrs'] : array() as $a) {
		if ($a['name'] === $name) return $a['value'];
	}
	return null;
}

// The Vehicles form's fields: name => array(tag, kind, options).
function _test_dlv_fields($tree)
{
	$fields = array();
	foreach (_test_dlv_nodes($tree) as $n) {
		$tag = isset($n['props']['tag']) ? $n['props']['tag'] : '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$name = _test_dlv_attr($n, 'name');
		if ($name === null) continue;
		$options = array();
		if ($tag === 'select') {
			foreach ($n['children'] as $o) {
				$v = _test_dlv_attr($o, 'value');
				if ($v !== '') $options[] = $v;
			}
		}
		$fields[$name] = array(
			'tag'     => $tag,
			'kind'    => isset($n['props']['_cf']['kind']) ? $n['props']['_cf']['kind'] : '',
			'office'  => !empty($n['props']['_cf']['office_use_only']),
			'options' => $options,
		);
	}
	return $fields;
}

function test_dealership_vehicles_part_shape()
{
	$d = _test_dlv_load();
	pg_assert_true($d['samples_kept'], 'vehicles.php leaves the sample vehicles of parts.php as they are');
	$keys = array();
	foreach ($d['part']['pages'] as $p) $keys[] = $p['key'];
	sort($keys);
	pg_assert_same(array('new_cars', 'used_cars', 'vehicle', 'vehicle_new', 'vehicles'), $keys, 'the step\'s pages');
	foreach (array('vehicle_form', 'vehicles_list', 'new_cars_list', 'used_cars_list', 'home_featured', 'home_used', 'vehicle_detail') as $w) {
		pg_assert_true(isset($d['part']['widgets'][$w]), "widget $w");
	}
}

function test_dealership_home_used_lists_the_latest_cars()
{
	$d = _test_dlv_load();
	$c = $d['part']['widgets']['home_used']['config'];
	pg_assert_same('submitted_date_and_time', $c['order_by_field'], 'Latest used cars sorts on the date the car was added');
	pg_assert_same('DESC', $c['order_by_direction'], 'Latest used cars puts the newest first');
	pg_assert_same(3, $c['max_results'], 'Latest used cars shows three');
}

function test_dealership_vehicle_samples_fill_the_form()
{
	$d = _test_dlv_load();
	$form = null;
	foreach ($d['part']['pages'] as $p) if ($p['key'] === 'vehicle_new') $form = $p['form'];
	$fields = _test_dlv_fields($d['part']['widgets']['vehicle_form']['tree']);
	pg_assert_same(1, $form['collection'], 'the Vehicles form is a collection');
	pg_assert_true(isset($fields[$form['share_image_field']]), 'the share image is a field');
	pg_assert_same(count($d['vehicles']), count($form['sample_records']), 'one record per sample vehicle');
	foreach (array('year', 'mileage', 'power', 'price') as $f) pg_assert_same('number', $fields[$f]['kind'], "$f is a number field");
	foreach (array('photo_1', 'photo_2', 'photo_3', 'photo_4') as $f) pg_assert_same('image', $fields[$f]['kind'], "$f is an image field");
	pg_assert_true($fields['status']['office'] && $fields['featured']['office'], 'status and featured are the staff\'s');
	$names = array_keys($fields);
	sort($names);
	foreach ($form['sample_records'] as $n => $r) {
		$rk = array_keys($r);
		sort($rk);
		pg_assert_same($names, $rk, "sample $n fills every field and no other");
		foreach ($fields as $name => $f) {
			if ($f['tag'] === 'select') pg_assert_true(in_array($r[$name], $f['options'], true), "sample $n: $name is an answer of its list");
			if ($f['kind'] === 'number') pg_assert_true(ctype_digit($r[$name]), "sample $n: $name is a whole number");
		}
	}
}

function test_dealership_vehicle_lists_filter_known_fields()
{
	$d = _test_dlv_load();
	$fields = _test_dlv_fields($d['part']['widgets']['vehicle_form']['tree']);
	$sold = $d['statuses'][2];
	foreach ($d['part']['widgets'] as $key => $w) {
		$c = $w['config'];
		if ($c['regionType'] !== 'form_list_view') continue;
		pg_assert_same('{{tab:vehicle_new}}', $c['custom_form_page_id'], "$key: the Vehicles form");
		pg_assert_same('{{tab:vehicle}}', $c['detail_page_id'], "$key: opens the vehicle page");
		if ($c['order_by_field'] !== 'submitted_date_and_time') {
			pg_assert_same('number', $fields[$c['order_by_field']]['kind'], "$key: sorts on a number field");
		}
		$hides_sold = false;
		foreach ($c['filters'] as $f) {
			pg_assert_true(isset($fields[$f['field']]), "$key: filters on a field of the form ({$f['field']})");
			if ($f['dynamic_value'] === 'url parameter') {
				pg_assert_true(in_array($f['dynamic_value_attribute'], array('make', 'body', 'fuel', 'condition'), true), "$key: a parameter the links send");
				pg_assert_same($f['field'], $f['dynamic_value_attribute'], "$key: the parameter is named as its field");
			} elseif ($f['operator'] === 'is equal to' || $f['operator'] === 'is not equal to') {
				pg_assert_true(in_array($f['value'], $fields[$f['field']]['options'], true), "$key: compares with an answer of {$f['field']}");
			}
			if ($f['field'] === 'status' && $f['operator'] === 'is not equal to' && $f['value'] === $sold) $hides_sold = true;
		}
		pg_assert_true($hides_sold, "$key: a sold car leaves the list");
	}
}

function test_dealership_vehicle_links_name_template_pages()
{
	$d = _test_dlv_load();
	$known = function_exists('_test_dl_page_keys') ? array_flip(_test_dl_page_keys()) : null;
	$sent = array();
	$trees = array();
	foreach ($d['part']['pages'] as $p) $trees[] = $p['tree'];
	foreach ($d['part']['widgets'] as $w) $trees[] = $w['tree'];
	foreach ($trees as $tree) {
		foreach (_test_dlv_nodes($tree) as $n) {
			$json = json_encode(isset($n['props']) ? $n['props'] : array());
			if ($known !== null && preg_match_all('/\{\{(?:page|tab):([a-z_]+)\}\}/', $json, $m)) {
				foreach ($m[1] as $k) pg_assert_true(isset($known[$k]), "a link names a template page ($k)");
			}
			if (isset($n['props']['_bindParams']['href'])) {
				$bp = $n['props']['_bindParams']['href'];
				pg_assert_same(array(array('name' => 'vehicle', 'token' => 'title')), $bp['params'], 'a request button sends the car\'s title as ?vehicle=');
				$sent[] = $bp['page_id'];
			}
		}
	}
	sort($sent);
	pg_assert_same(array('{{tab:finance_apply}}', '{{tab:test_drive}}', '{{tab:tradein}}'), $sent, 'the vehicle page opens the three requests');
}
