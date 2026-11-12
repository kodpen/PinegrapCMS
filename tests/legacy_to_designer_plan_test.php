<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the bulk move to the visual designer
 * (includes/fn/legacy_to_designer_plan.php): the frame's targets and
 * splice, the page region twin of the editor, the widget names, settings
 * and starter layouts, and the dry run's plan of one style.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer_plan.php');

function _ltdp_region($type, $name, $id = '')
{
	$n = array('type' => 'region', 'props' => array('regionType' => $type, 'regionName' => $name), 'children' => array());
	if ($id !== '') $n['_id'] = $id;
	return $n;
}

function _ltdp_el($children)
{
	return array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => $children);
}

function _ltdp_frame()
{
	return pg_di_root(array(
		_ltdp_el(array(_ltdp_region('menu', 'Main', 'sd_1'), _ltdp_region('designer', 'Header'), _ltdp_region('page', ''))),
		_ltdp_el(array(_ltdp_region('menu', 'Main', 'sd_2'), _ltdp_region('ad', 'Top'), _ltdp_region('menu', ''), _ltdp_region('login', 'x'))),
		array('type' => 'shared_ref', 'props' => array('sharedId' => 9), 'children' => array(_ltdp_region('tag_cloud', 'Inside'))),
		_ltdp_region('tag_cloud', 'Tags'),
		_ltdp_region('designer', 'Header'),
		_ltdp_region('menu_sequence', 'Main'),
	));
}

function test_ltdp_frame_targets_named_regions_once()
{
	$t = pg_ltd_frame_targets(_ltdp_frame());
	pg_assert_same(array('Header'), $t['designer']);
	pg_assert_same(1, $t['page_regions']);
	$got = array();
	foreach ($t['widgets'] as $w) $got[] = $w['type'] . ':' . $w['name'] . ':' . $w['kind'] . ':' . $w['key'];
	pg_assert_same(array('menu:Main:menu_region:menu_name', 'ad:Top:ad_region:ad_region_name',
		'tag_cloud:Tags:tag_cloud_region:search_page_name', 'menu_sequence:Main:menu_sequence_region:menu_name'), $got,
		'one per region and type, nameless and nested components left out');
}

function test_ltdp_frame_splice_places_components()
{
	$count = 0;
	$tree = pg_ltd_frame_splice(_ltdp_frame(), array(
		"menu\nMain"       => array('id' => 41, 'name' => 'site-menu-widget'),
		"designer\nHeader" => array('id' => 7, 'name' => 'Header'),
	), $count);
	pg_assert_same(4, $count);
	$first = $tree['children'][0]['children'][0];
	pg_assert_same('shared_ref', $first['type']);
	pg_assert_same(41, $first['props']['sharedId']);
	pg_assert_same('sd_1', $first['_id'], 'the node id is kept');
	pg_assert_same(7, $tree['children'][0]['children'][1]['props']['sharedId']);
	pg_assert_same('region', $tree['children'][2]['children'][0]['type'], 'a nested component is not entered');
	pg_assert_same('region', $tree['children'][5]['type'], 'another type with the same name stays');
}

function test_ltdp_page_regions_own_shared_missing()
{
	$tree = pg_di_root(array(
		_ltdp_region('page', ''),
		_ltdp_el(array(_ltdp_region('page', ''))),
		array('type' => 'shared_ref', 'props' => array('sharedId' => 3), 'children' => array()),
	));
	$shared = array(3 => pg_di_root(array(_ltdp_region('page', ''),
		array('type' => 'shared_ref', 'props' => array('sharedId' => 3), 'children' => array()))));
	pg_assert_same(array('own' => 2, 'shared' => 1, 'missing' => false), pg_ltd_page_regions($tree, $shared));
	pg_assert_same(array('own' => 2, 'shared' => 0, 'missing' => true), pg_ltd_page_regions($tree, array()));
}

function test_ltdp_page_region_splice_in_order_all_or_none()
{
	$tree = pg_di_root(array(
		_ltdp_region('page', ''),
		_ltdp_el(array(_ltdp_region('common', 'Footer'), _ltdp_region('page', ''))),
	));
	$a = array('type' => 'semantic', 'props' => array('_editable' => 1, 'cssClass' => 'a'), 'children' => array());
	$b = array('type' => 'semantic', 'props' => array('_editable' => 1, 'cssClass' => 'b'), 'children' => array());
	$out = pg_ltd_page_region_splice($tree, array($a, $b));
	pg_assert_same('a', $out['children'][0]['props']['cssClass']);
	pg_assert_same('region', $out['children'][1]['children'][0]['type']);
	pg_assert_same('b', $out['children'][1]['children'][1]['props']['cssClass']);
	pg_assert_same(null, pg_ltd_page_region_splice($tree, array($a)), 'fewer blocks than nodes');
	pg_assert_same(null, pg_ltd_page_region_splice($tree, array($a, $b, $a)), 'more blocks than nodes');
	pg_assert_same(null, pg_ltd_page_region_splice(pg_di_root(array()), array()), 'nothing to move');
}

function test_ltdp_slug_and_widget_name()
{
	pg_assert_same('ana-sayfa-cicek', pg_ltd_slug('  Ana Sayfa — Çiçek!'));
	pg_assert_same(40, strlen(pg_ltd_slug(str_repeat('ab', 30))));
	pg_assert_same('', pg_ltd_slug('***'));
	$name = pg_ltd_widget_name('Kurumsal Şablon', 'menu_region');
	pg_assert_same(1, preg_match('/^kurumsal-sablon-[a-z0-9-]+-widget$/', $name), $name);
	pg_assert_same(1, preg_match('/^[a-z0-9-]+-widget$/', pg_ltd_widget_name('', 'ad_region')), 'an empty style name still names it');
}

function test_ltdp_widget_config_names_the_region()
{
	$t = pg_ltd_frame_targets(pg_di_root(array(_ltdp_region('ad', 'Top'))));
	pg_assert_same(array('regionType' => 'ad_region', 'ad_region_name' => 'Top'), pg_ltd_widget_config($t['widgets'][0]));
}

function test_ltdp_widget_trees_carry_the_renderer_flags()
{
	$flags = array('menu_region' => 'has_menu_items', 'menu_sequence_region' => 'in_sequence', 'ad_region' => 'has_ads', 'tag_cloud_region' => 'has_tags');
	foreach (pg_ltd_region_widgets() as $type => $map) {
		$tree = pg_ltd_widget_tree($map['kind']);
		pg_assert_same('root', $tree['type'], $map['kind']);
		pg_assert_same($flags[$map['kind']], $tree['children'][0]['props']['_bindings']['eo_visible_if'], $map['kind']);
		$json = json_encode($tree);
		pg_assert_same(1, substr_count($json, '"type":"loop_area"') >= 1 ? 1 : 0, $map['kind'] . ' has a loop area');
		$ids = array();
		$walk = function ($n) use (&$walk, &$ids) {
			$ids[] = $n['_id'];
			foreach ($n['children'] as $c) $walk($c);
		};
		$walk($tree);
		pg_assert_same(count($ids), count(array_unique($ids)), $map['kind'] . ' ids are unique');
	}
	$menu = json_encode(pg_ltd_widget_tree('menu_region'));
	pg_assert_same(true, strpos($menu, '"source":"menu.children"') !== false, 'sub-items list');
	pg_assert_same(true, strpos($menu, '"smartActive":true') !== false);
	pg_assert_same(5, substr_count(json_encode(pg_ltd_widget_tree('tag_cloud_region')), '"show_if":"__tag_weight"'));
	pg_assert_same(null, pg_ltd_widget_tree('catalog_listing'));
}

function test_ltdp_plan_style_actions_and_writes()
{
	$frame = pg_ltd_frame_targets(_ltdp_frame());
	$p = pg_ltd_plan_style(array(
		'refusal' => '', 'common' => array('Footer'), 'frame' => $frame, 'frame_exists' => true,
		'designer_made' => array('Header'), 'warnings' => array('w'),
		'pages' => array(
			array('page_id' => 1, 'page_name' => 'Home', 'source' => 'own', 'has_tree' => false, 'holder' => ''),
			array('page_id' => 2, 'page_name' => 'About', 'source' => 'folder', 'has_tree' => false, 'holder' => 'ayse'),
			array('page_id' => 3, 'page_name' => 'Old', 'source' => 'own', 'has_tree' => true, 'holder' => '', 'own_regions' => 0),
			array('page_id' => 4, 'page_name' => 'Mixed', 'source' => 'own', 'has_tree' => true, 'holder' => '', 'own_regions' => 2),
		),
	));
	pg_assert_same('', $p['skip']);
	pg_assert_same('update', $p['frame']);
	$actions = array();
	foreach ($p['pages'] as $page) $actions[] = $page['action'];
	pg_assert_same(array('tree', 'held', 'keep', 'regions'), $actions);
	pg_assert_same(array(array('kind' => 'common', 'name' => 'Footer', 'exists' => false), array('kind' => 'designer', 'name' => 'Header', 'exists' => true)), $p['components']);
	pg_assert_same(4, count($p['widgets']));
	pg_assert_false($p['frame_split'], 'a frame with no split fact stays whole');
	pg_assert_same(0, $p['frame_parts']);
	// One component, frame and style, two pages, four widgets.
	pg_assert_same(1 + 2 + 2 + 4, $p['writes']);
	pg_assert_same(array('w'), $p['warnings']);
}

function test_ltdp_plan_style_split_frame_writes()
{
	$p = pg_ltd_plan_style(array(
		'refusal' => '', 'common' => array(), 'frame' => array('designer' => array(), 'widgets' => array(), 'page_regions' => 0),
		'frame_split' => true, 'frame_parts' => 3, 'frame_exists' => false,
		'pages' => array(
			array('page_id' => 1, 'page_name' => 'Home', 'source' => 'own', 'has_tree' => false, 'holder' => ''),
			array('page_id' => 2, 'page_name' => 'About', 'source' => 'folder', 'has_tree' => false, 'holder' => ''),
			array('page_id' => 3, 'page_name' => 'Mixed', 'source' => 'folder', 'has_tree' => true, 'holder' => '', 'own_regions' => 1),
		),
	));
	pg_assert_true($p['frame_split']);
	pg_assert_same(3, $p['frame_parts']);
	pg_assert_same('make', $p['frame']);
	// Three parts and the style; three pages, the folder page given a tree
	// written again when its page regions are taken in.
	pg_assert_same(3 + 1 + 3 + 1, $p['writes']);

	$items = pg_ltd_plan_items(array_merge($p, array('name' => 'Main')));
	pg_assert_true(strpos($items, 'split into 3 shared components') !== false, 'the split line');
	pg_assert_true(strpos($items, 'go into each page') !== false, 'where page content is edited now');
	pg_assert_false(strpos($items, 'frame is made a shared component') !== false, 'not the whole frame line');

	$none = pg_ltd_plan_style(array('refusal' => '', 'frame' => array('designer' => array(), 'widgets' => array(), 'page_regions' => 0),
		'frame_split' => true, 'frame_parts' => 0, 'pages' => array()));
	pg_assert_same(1, $none['writes'], 'no part: the style alone');
	pg_assert_true(strpos(pg_ltd_plan_items(array_merge($none, array('name' => 'Main'))), 'holds nothing but the page content') !== false);

	$whole = pg_ltd_plan_style(array('refusal' => '', 'frame' => array('designer' => array(), 'widgets' => array(), 'page_regions' => 0), 'pages' => array()));
	pg_assert_true(strpos(pg_ltd_plan_items(array_merge($whole, array('name' => 'Main'))), 'frame is made a shared component') !== false);
}

function test_ltdp_plan_style_refused()
{
	$p = pg_ltd_plan_style(array('refusal' => 'no code', 'frame' => pg_ltd_frame_targets(_ltdp_frame())));
	pg_assert_same('refusal', $p['skip']);
	pg_assert_same(array(), $p['widgets']);
	pg_assert_same(0, $p['writes']);
}
