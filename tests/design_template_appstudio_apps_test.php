<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Mobile App Developer design template's apps
 * (includes/design_templates/pinegrap-appstudio/apps.php): the Apps form is
 * a collection whose sample records fill its own fields with answers of
 * its lists, the rating is a number and the pictures are images, Featured
 * is the staff's, every list reads the Apps form and filters on its
 * fields under names a list may read (the platform with "contains"), the
 * filter links name answers of the lists, and the app page sends the app's
 * name to the release notes and the support form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// parts.php, then apps.php in the same scope. Read once per run.
function _test_ma_apps_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-appstudio';
	include $dir . '/parts.php';
	$app_part = include $dir . '/apps.php';
	$out = array(
		'part'             => $app_part,
		'apps'             => $apps,
		'releases'         => $releases,
		'category_titles'  => $category_titles,
		'platform_choices' => $platform_choices,
		'price_models'     => $price_models,
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_ma_apps_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_ma_apps_nodes($child, $out);
	}
}

// The named controls of a widget tree: tag, kind, office use, answers.
function _test_ma_apps_controls($tree)
{
	$nodes = array();
	_test_ma_apps_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $node) {
		$name = '';
		foreach ($node['props']['_attrs'] ?? array() as $a) {
			if ($a['name'] === 'name') $name = $a['value'];
		}
		if ($name === '' || !in_array($node['props']['tag'] ?? '', array('input', 'select', 'textarea'), true)) continue;
		$options = array();
		foreach ($node['children'] as $o) {
			foreach ($o['props']['_attrs'] ?? array() as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $options[] = $a['value'];
			}
		}
		$out[$name] = array(
			'tag'     => $node['props']['tag'],
			'kind'    => $node['props']['_cf']['kind'] ?? '',
			'office'  => !empty($node['props']['_cf']['office_use_only']),
			'options' => $options,
		);
	}
	return $out;
}

function test_appstudio_apps_form_and_samples()
{
	$d = _test_ma_apps_load();
	$pages = array();
	foreach ($d['part']['pages'] as $p) $pages[$p['key']] = $p;
	pg_assert_same(array('apps', 'app', 'app_new'), array_keys($pages), 'the app pages in order');
	pg_assert_same('private', $pages['app_new']['folder'], 'the staff page is private');
	$form = $pages['app_new']['form'];
	pg_assert_same(1, $form['collection'], 'Apps is a collection');
	pg_assert_same('icon', $form['share_image_field'], 'the icon is the share image');
	$controls = _test_ma_apps_controls($d['part']['widgets']['app_form']['tree']);
	pg_assert_same('number', $controls['rating']['kind'], 'the rating is a number');
	foreach (array('icon', 'screenshot_1', 'screenshot_2', 'screenshot_3') as $f) pg_assert_same('image', $controls[$f]['kind'], "$f is an image");
	pg_assert_same($d['category_titles'], $controls['category']['options'], 'kind answers');
	pg_assert_same($d['platform_choices'], $controls['platforms']['options'], 'platform answers');
	pg_assert_same($d['price_models'], $controls['price_model']['options'], 'price model answers');
	pg_assert_true($controls['featured']['office'], 'Featured is office use only');
	foreach ($controls as $name => $c) {
		if ($name !== 'featured') pg_assert_false($c['office'], "$name is the visitor's field");
	}
	pg_assert_same(count($d['apps']), count($form['sample_records']), 'a record per sample app');
	$featured = 0;
	foreach ($form['sample_records'] as $n => $r) {
		foreach ($r as $field => $value) pg_assert_true(isset($controls[$field]), "sample $n: $field is a field of the form");
		foreach (array('category', 'platforms', 'price_model', 'featured') as $f) {
			pg_assert_true(in_array($r[$f], $controls[$f]['options'], true), "sample $n: $f is an answer of its list");
		}
		pg_assert_true((bool)preg_match('/^[1-5]\.\d$/', $r['rating']), "sample $n: rating is a plain number");
		// A store address only where the app is: the button leaves without it.
		$on_ios = strpos($r['platforms'], 'iOS') !== false;
		$on_android = strpos($r['platforms'], 'Android') !== false;
		pg_assert_same($on_ios, $r['app_store_url'] !== '', "sample $n: App Store address follows the platforms");
		pg_assert_same($on_android, $r['google_play_url'] !== '', "sample $n: Google Play address follows the platforms");
		if ($r['featured'] === $controls['featured']['options'][0]) $featured++;
	}
	pg_assert_same(3, $featured, 'three featured samples for the home');
	// Release notes name an app by its name: the app list's names.
	$names = array_column($form['sample_records'], 'name');
	foreach ($d['releases'] as $n => $rel) pg_assert_true(in_array($d['apps'][$rel['app']]['name'], $names, true), "release $n: names a sample app");
}

// Every list reads the Apps form, filters on its fields, takes its URL
// values under names a list may read; the filter links name answers.
function test_appstudio_apps_lists_and_filters()
{
	$d = _test_ma_apps_load();
	$widgets = $d['part']['widgets'];
	$controls = _test_ma_apps_controls($widgets['app_form']['tree']);
	$reserved = function_exists('pg_sw_url_param_reserved') ? pg_sw_url_param_reserved() : array();
	pg_assert_same(array('app_form', 'apps_list', 'home_apps', 'app_detail'), array_keys($widgets), 'the app widgets');
	$pages = array();
	foreach ($d['part']['pages'] as $p) $pages[$p['key']] = $p;
	$params = array();
	foreach ($widgets as $key => $w) {
		if ($w['page'] !== 'home') {
			pg_assert_true(isset($pages[$w['page']]), "$key: on a page of this part");
		}
		if ($w['config']['regionType'] !== 'form_list_view') continue;
		pg_assert_same('{{tab:app_new}}', $w['config']['custom_form_page_id'], "$key: reads Apps");
		pg_assert_same('{{tab:app}}', $w['config']['detail_page_id'], "$key: opens the app page");
		foreach ($w['config']['filters'] as $f) {
			pg_assert_true(isset($controls[$f['field']]), "$key: filters on a field of the form: " . $f['field']);
			if ($f['dynamic_value'] === 'url parameter') {
				pg_assert_true((bool)preg_match('/^[a-z][a-z0-9_]{0,31}$/', $f['dynamic_value_attribute']), "$key: parameter name");
				pg_assert_false(in_array($f['dynamic_value_attribute'], $reserved, true), "$key: parameter not reserved");
				$params[$f['dynamic_value_attribute']] = $f;
			} else {
				pg_assert_true(in_array($f['value'], $controls[$f['field']]['options'], true), "$key: fixed value is an answer: " . $f['value']);
			}
		}
		foreach ($w['config']['search_fields'] ?? array() as $f) pg_assert_true(isset($controls[$f]), "$key: searches a field: $f");
	}
	pg_assert_same('is equal to', $params['category']['operator'], '?category= is exact');
	pg_assert_same('platforms', $params['platform']['field'], '?platform= reads the platforms');
	pg_assert_same('contains', $params['platform']['operator'], '?platform= contains: both platforms show under each');
	// The filter links of the list page name an answer the list holds.
	$nodes = array();
	_test_ma_apps_nodes($pages['apps']['tree'], $nodes);
	$links = 0;
	foreach ($nodes as $node) {
		$href = $node['props']['href'] ?? '';
		if (!preg_match('/^\{\{page:apps\}\}\?(.+)$/', $href, $m)) continue;
		parse_str($m[1], $q);
		foreach ($q as $param => $value) {
			pg_assert_true(isset($params[$param]), "filter link: a parameter of the list: $param");
			$answers = $controls[$params[$param]['field']]['options'];
			$hits = array_filter($answers, function ($a) use ($value) { return strpos($a, $value) !== false; });
			pg_assert_true(count($hits) > 0, "filter link: $param matches an answer: $value");
		}
		$links++;
	}
	pg_assert_same(count($d['category_titles']) + 2, $links, 'a filter link per kind and per platform');
}

// The app page reads the app's fields and sends its name to the release
// notes and the support form.
function test_appstudio_app_page()
{
	$d = _test_ma_apps_load();
	$widgets = $d['part']['widgets'];
	$controls = _test_ma_apps_controls($widgets['app_form']['tree']);
	$nodes = array();
	_test_ma_apps_nodes($widgets['app_detail']['tree'], $nodes);
	$read = array();
	$sends = array();
	foreach ($nodes as $node) {
		foreach ($node['props']['_bindings'] ?? array() as $prop => $token) {
			if ($token === '__page_link') {
				$spec = $node['props']['_bindParams'][$prop];
				$sends[$spec['page_id']] = $spec['params'];
			} elseif (isset($controls[$token])) {
				$read[$token] = true;
			}
		}
	}
	foreach (array_keys($controls) as $f) {
		// Featured is the staff's; the summary is the cards' two lines.
		if ($f === 'featured' || $f === 'summary') continue;
		pg_assert_true(isset($read[$f]), "app page shows $f");
	}
	$by_name = array(array('name' => 'app', 'token' => 'name'));
	pg_assert_same(array('{{tab:releases}}' => $by_name, '{{tab:support}}' => $by_name), $sends, 'release notes and support get the app');
	pg_assert_same('public', $widgets['app_detail']['config']['access_control'], 'the app page is public');
}
