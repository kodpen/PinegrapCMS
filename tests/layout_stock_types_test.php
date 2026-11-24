<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what a classic page type's settings become in the widget that
 * stands in for it: the settings its layout takes (columns, image size,
 * pg_layout_widget_tree_seed(), pg_layout_widget_tree_apply() and the
 * editor's twin _sdClassicTreeSeed()), the settings it cannot hold
 * (pg_layout_widget_held()) and the move's dry run naming them
 * (pg_ltd_type_target(), pg_ltd_plan_groups()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/layouts.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer_plan.php');

// A catalog starter's shape: a breadcrumb outside the loop, a column inside
// it holding the product picture, and a second image bound to nothing.
function _layout_stock_tree()
{
	return array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'container', 'props' => array(), 'children' => array(
			array('type' => 'col', 'props' => array('xs' => '12', 'md' => '8'), 'children' => array()),
			array('type' => 'row', 'props' => array(), 'children' => array(
				array('type' => 'loop_area', 'props' => array(), 'children' => array(
					array('type' => 'col', 'props' => array('xs' => '12', 'sm' => '6', 'md' => '4', 'lg' => '', 'xl' => '', 'xxl' => '', 'cssClass' => 'mb-4'), 'children' => array(
						array('type' => 'content', 'props' => array('contentType' => 'image', 'src' => '', '_bindings' => array('src' => '__image_url')), 'children' => array()),
						array('type' => 'content', 'props' => array('contentType' => 'image', 'src' => 'x.png'), 'children' => array()),
					)),
				)),
			)),
		)),
	));
}

function test_layout_tree_seed_catalog()
{
	pg_assert_same(array('columns' => 4, 'image_width' => 120, 'image_height' => 90),
		pg_layout_widget_tree_seed('catalog', array('number_of_columns' => '4', 'image_width' => '120', 'image_height' => '90')));
	pg_assert_same(array('columns' => 1), pg_layout_widget_tree_seed('catalog', array('number_of_columns' => '1', 'image_width' => '0', 'image_height' => '0')), 'no size: none carried');
	pg_assert_same(array(), pg_layout_widget_tree_seed('catalog', array('number_of_columns' => '5')), 'five columns do not split twelve');
	pg_assert_same(array(), pg_layout_widget_tree_seed('catalog', array('number_of_columns' => '0')));
	pg_assert_same(array(), pg_layout_widget_tree_seed('form list view', array('number_of_columns' => '3')), 'only the catalog');
}

function test_layout_tree_apply()
{
	$tree = pg_layout_widget_tree_apply(_layout_stock_tree(), array('columns' => 4, 'image_width' => 120, 'image_height' => 90));
	$outside = $tree['children'][0]['children'][0];
	$col = $tree['children'][0]['children'][1]['children'][0]['children'][0];
	pg_assert_same(array('xs' => '12', 'md' => '8'), $outside['props'], 'a column outside the loop is left');
	pg_assert_same('12', $col['props']['xs']);
	pg_assert_same('6', $col['props']['sm']);
	pg_assert_same('4', $col['props']['md']);
	pg_assert_same('3', $col['props']['lg'], 'four columns from the large screen up');
	pg_assert_same('mb-4', $col['props']['cssClass'], 'its other props stay');
	pg_assert_same('120', $col['children'][0]['props']['width'], 'the product picture takes the width');
	pg_assert_same('90', $col['children'][0]['props']['height']);
	pg_assert_true(!isset($col['children'][1]['props']['width']), 'an image bound to nothing is left');

	$one = pg_layout_widget_tree_apply(_layout_stock_tree(), array('columns' => 1));
	$col = $one['children'][0]['children'][1]['children'][0]['children'][0];
	pg_assert_same(array('12', '', '', ''), array($col['props']['xs'], $col['props']['sm'], $col['props']['md'], $col['props']['lg']), 'one column: the whole row');
	pg_assert_true(!isset($col['children'][0]['props']['width']), 'no size: the picture is left');

	$six = pg_layout_widget_tree_apply(_layout_stock_tree(), array('columns' => 6));
	pg_assert_same('2', $six['children'][0]['children'][1]['children'][0]['children'][0]['props']['lg']);
	pg_assert_same(_layout_stock_tree(), pg_layout_widget_tree_apply(_layout_stock_tree(), array()), 'no seed: unchanged');
}

function test_layout_held_catalog()
{
	pg_assert_same(array(), pg_layout_widget_held('catalog', array('number_of_columns' => '3', 'number_of_featured_items' => '0', 'number_of_new_items' => '0')));
	pg_assert_same(array('featured_items', 'new_items', 'columns'),
		pg_layout_widget_held('catalog', array('number_of_columns' => '5', 'number_of_featured_items' => '4', 'number_of_new_items' => '2')));
	pg_assert_same(array('columns'), pg_layout_widget_held('catalog', array('number_of_columns' => '8')));
	pg_assert_same(array(), pg_layout_widget_held('catalog', array('number_of_columns' => '1')), 'one column is held by the layout');
	pg_assert_same(array(), pg_layout_widget_held('catalog detail', array('number_of_featured_items' => '4')), 'only its own type\'s settings');
}

function test_layout_held_form_list()
{
	$names = array(7 => 'city');
	pg_assert_same(array(), pg_layout_widget_held('form list view', array(
		'search_advanced' => '0', 'order_by_1_standard_field' => 'submitted_date_and_time', 'order_by_2_form_field_id' => '7',
		'order_by_3_standard_field' => '', 'order_by_3_form_field_id' => '0', 'header' => '<p> </p>', 'footer' => '',
	), $names), 'carried sorts and an empty header');
	pg_assert_same(array('advanced_search', 'sort', 'sort_3', 'header', 'footer'), pg_layout_widget_held('form list view', array(
		'search_advanced' => '1', 'order_by_1_standard_field' => 'random', 'order_by_3_form_field_id' => '7',
		'header' => '<h2>Members</h2>', 'footer' => '<img src="x.png">',
	), $names));
	pg_assert_same(array('sort'), pg_layout_widget_held('form list view', array('order_by_2_form_field_id' => '9'), $names), 'a field the form no longer has');
	pg_assert_same('Featured items', pg_layout_held_label('featured_items'));
	pg_assert_same('Third sort order', pg_layout_held_label('sort_3'));
}

function test_layout_type_target()
{
	$facts = array('kind' => 'catalog_listing', 'modified' => false, 'held' => array());
	pg_assert_same(array('page_name' => 'Shop', 'kind' => 'catalog_listing', 'state' => 'convert', 'held' => array()), pg_ltd_type_target('Shop', $facts, false));
	$facts['held'] = array('featured_items');
	pg_assert_same('held', pg_ltd_type_target('Shop', $facts, false)['state']);
	$facts['modified'] = true;
	pg_assert_same('file', pg_ltd_type_target('Shop', $facts, false)['state'], 'its own layout file goes to the converter');
	pg_assert_same('held', pg_ltd_type_target('Shop', $facts, true)['state'], 'a hosted site draws no layout file');

	pg_assert_true(pg_ltd_tree_has_own_system(array('type' => 'root', 'children' => array(
		array('type' => 'container', 'children' => array(array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => '')))),
	))));
	pg_assert_true(!pg_ltd_tree_has_own_system(array('type' => 'root', 'children' => array(
		array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => 'other-page')),
		array('type' => 'shared_ref', 'props' => array(), 'children' => array(array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => '')))),
	))), 'another page\'s region and one inside a shared component do not count');
}

function test_layout_dry_run_names_held_settings()
{
	$style = pg_ltd_plan_style(array('refusal' => '', 'pages' => array(), 'types' => array(
		array('page_name' => 'Shop', 'kind' => 'catalog_listing', 'state' => 'held', 'held' => array('featured_items', 'new_items')),
		array('page_name' => 'Outlet', 'kind' => 'catalog_listing', 'state' => 'held', 'held' => array('featured_items')),
		array('page_name' => 'Members', 'kind' => 'form_list_view', 'state' => 'convert', 'held' => array()),
	)));
	pg_assert_same(3, count($style['types']), 'the facts pass through');
	$groups = pg_ltd_plan_groups(array('kind' => 'style', 'name' => 'Main') + $style);
	$rows = array();
	foreach ($groups['code'] as $r) {
		$rows[$r['label']] = $r;
	}
	pg_assert_true(isset($rows['Featured items']), 'a row a setting');
	pg_assert_same(2, $rows['Featured items']['count']);
	pg_assert_same(array('Shop', 'Outlet'), $rows['Featured items']['names']);
	pg_assert_same(array('Shop'), $rows['New items']['names']);
	pg_assert_contains('stay System regions', $rows['New items']['reason']);
	pg_assert_same(2, count($rows), 'a page the widget holds is not listed as code');

	$refused = pg_ltd_plan_style(array('refusal' => 'php', 'types' => array(array('page_name' => 'Shop', 'state' => 'held', 'held' => array('columns')))));
	pg_assert_same(array(), $refused['types'], 'a refused style lists none');
}

function test_layout_editor_twin()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains('_sdClassicTreeSeed(_buildStarterTree(o.kind), o.tree)', $js, 'the editor puts the layout settings into the starter');
	pg_assert_contains('_sdClassicTreeSeed(_buildStarterTree(o.kind), o.tree), function (newId, newName)', $js, 'beside a custom layout too');
	pg_assert_contains("_sdT('The widget has no setting for: {var}. The page loses it.'", $js, 'the confirm names what the widget cannot hold');

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return;
	}

	if (!preg_match('/\n    function _sdClassicTreeSeed\(.*?\n    }\n/s', $js, $m)) {
		pg_assert_true(false, 'style_designer.js has _sdClassicTreeSeed()');
		return;
	}
	$seed = array('columns' => 4, 'image_width' => 120, 'image_height' => 90);
	$script = $m[0] . 'console.log(JSON.stringify([_sdClassicTreeSeed(' . json_encode(_layout_stock_tree()) . ', ' . json_encode($seed) . '), '
		. '_sdClassicTreeSeed(' . json_encode(_layout_stock_tree()) . ', {columns: 1})]));';
	$file = tempnam(sys_get_temp_dir(), 'pglst');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	$res = json_decode(implode("\n", $out), true);
	if (!pg_assert_true(is_array($res), 'the script answers')) {
		return;
	}
	pg_assert_same(pg_layout_widget_tree_apply(_layout_stock_tree(), $seed), $res[0], 'the editor and the server put the same settings in');
	pg_assert_same(pg_layout_widget_tree_apply(_layout_stock_tree(), array('columns' => 1)), $res[1]);
}
