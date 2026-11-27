<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the move run's types step: the kinds whose starter layouts
 * Convert posts, the posted trees kept or dropped, the widget a page type
 * target becomes (a missing kind leaves the page as it is), the System
 * region replaced, the "Page types" row of the dry run and the wiring of the
 * move screen.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer_run.php');

function _let_type($name, $kind, $state, $held = array())
{
	return array('page_name' => $name, 'kind' => $kind, 'state' => $state, 'held' => $held);
}

function _let_style($types, $skip = '')
{
	return array('kind' => 'style', 'id' => 3, 'name' => 'home', 'skip' => $skip, 'components' => array(), 'pages' => array(),
	             'widgets' => array(), 'stays' => array(), 'designer_php' => array(), 'custom_layouts' => 0, 'types' => $types);
}

function _let_starter()
{
	return array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'loop_area', 'props' => array(), 'children' => array(
			array('type' => 'col', 'props' => array('xs' => '12', 'sm' => '6', 'md' => '4'), 'children' => array(
				array('type' => 'content', 'props' => array('contentType' => 'image', '_bindings' => array('src' => '__image_url')), 'children' => array()),
			)),
		)),
	));
}

function test_let_plan_type_kinds()
{
	$plan = array(
		_let_style(array(_let_type('Shop', 'catalog_listing', 'convert'), _let_type('List', 'form_list_view', 'held', array('sort_3')),
		                 _let_type('Item', 'catalog_item_view', 'file'), _let_type('Shop 2', 'catalog_listing', 'convert'))),
		array('kind' => 'set', 'skip' => '', 'styles' => array(_let_style(array(_let_type('Cart', 'shopping_cart', 'convert'))),
		                                                        _let_style(array(_let_type('Gone', 'search_results', 'convert')), 'refusal'))),
		_let_style(array(_let_type('Skipped', 'order_form', 'convert')), 'refusal'),
	);
	pg_assert_same(array('catalog_listing', 'shopping_cart'), pg_ltd_plan_type_kinds($plan));
	pg_assert_same(array(), pg_ltd_plan_type_kinds(array()));
}

function test_let_starters_clean_drops_what_was_not_listed()
{
	$posted = json_encode(array(
		'catalog_listing' => _let_starter(),
		'shopping_cart'   => _let_starter(),
		'order_form'      => array('type' => 'container', 'props' => array(), 'children' => array()),
		'search_results'  => array('type' => 'root', 'children' => array(array('type' => 'shared_ref', 'props' => array('sharedId' => 9)))),
	));
	$kept = pg_ltd_starters_clean($posted, array('catalog_listing', 'order_form', 'search_results', 'photo_gallery'));
	pg_assert_same(array('catalog_listing'), array_keys($kept), 'an unlisted kind, a tree not rooted at root and one placing a component are dropped');
	pg_assert_same(_let_starter(), $kept['catalog_listing']);
	pg_assert_same(array(), pg_ltd_starters_clean('not json', array('catalog_listing')));
	pg_assert_same(array(), pg_ltd_starters_clean(array('catalog_listing' => _let_starter()), array('catalog_listing')), 'only posted text is read');
	pg_assert_same(array(), pg_ltd_starters_clean(str_repeat(' ', PG_LTD_STARTERS_BYTES + 1), array('catalog_listing')));
}

function test_let_type_widget_puts_the_settings_in()
{
	$facts = array('cfg' => array('product_group_id' => 4, 'search_enabled' => true), 'tree' => array('columns' => 2, 'image_width' => 150));
	$w = pg_ltd_type_widget(_let_type('Shop', 'catalog_listing', 'convert'), $facts, array('catalog_listing' => _let_starter()));
	pg_assert_same(array('regionType' => 'catalog_listing', 'product_group_id' => 4, 'search_enabled' => true), $w['cfg']);
	$col = $w['tree']['children'][0]['children'][0];
	pg_assert_same(array('12', '6', ''), array($col['props']['xs'], $col['props']['sm'], $col['props']['md']));
	pg_assert_same('150', $col['children'][0]['props']['width']);
}

function test_let_type_widget_missing_kind_leaves_the_page()
{
	pg_assert_same(null, pg_ltd_type_widget(_let_type('Cart', 'shopping_cart', 'convert'), array(), array('catalog_listing' => _let_starter())));
	pg_assert_same(null, pg_ltd_type_widget(_let_type('Cart', 'shopping_cart', 'convert'), array(), array()));
}

function test_let_type_splice_takes_the_own_system_region()
{
	$own = array('_id' => 'n7', 'type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array());
	$named = array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => 'Sidebar'), 'children' => array());
	$in_shared = array('type' => 'shared_ref', 'props' => array('sharedId' => 3), 'children' => array($own));
	$tree = array('type' => 'root', 'props' => array(), 'children' => array($in_shared, $named,
		array('type' => 'container', 'props' => array(), 'children' => array($own, $own))));
	$ref = array('type' => 'shared_ref', 'props' => array('sharedId' => 12, 'sharedName' => 'shop-catalog-listing-widget'), 'children' => array());
	$count = 0;
	$out = pg_ltd_type_splice($tree, $ref, $count);
	pg_assert_same(1, $count);
	pg_assert_same($in_shared, $out['children'][0], 'a shared component is not entered');
	pg_assert_same($named, $out['children'][1], 'a named System region stays');
	pg_assert_same(array_merge($ref, array('_id' => 'n7')), $out['children'][2]['children'][0], 'the first own one is replaced, its id kept');
	pg_assert_same($own, $out['children'][2]['children'][1], 'only the first');
	$none = 0;
	pg_ltd_type_splice(array('type' => 'root', 'children' => array($named)), $ref, $none);
	pg_assert_same(0, $none);
}

function test_let_page_types_row_in_the_dry_run()
{
	$g = pg_ltd_plan_groups(_let_style(array(_let_type('Shop', 'catalog_listing', 'convert'), _let_type('List', 'form_list_view', 'held', array('sort_3')))));
	$rows = array();
	foreach ($g['editable'] as $r) $rows[$r['key']] = $r['names'];
	pg_assert_same(array('Shop'), $rows['types'] ?? null);
	pg_assert_same(true, strpos(pg_ltd_plan_groups_html($g), 'bi-window-stack') !== false);
}

function test_let_widget_name_by_kind()
{
	pg_assert_same(1, preg_match('/^shop-catalog-listing-widget$/', pg_ltd_widget_name('Shop', 'catalog_listing')));
}

function test_let_wiring()
{
	pg_assert_same(PG_FUNCTIONS_DIR . '/data/temp/legacy_to_designer_starters.json', pg_ltd_starters_path());
	$screen = (string)file_get_contents(PG_FUNCTIONS_DIR . '/legacy_to_designer.php');
	pg_assert_same(true, strpos($screen, 'hash_equals(') < strpos($screen, 'pg_designer_i18n_map()'), 'the texts are given after the token check');
	pg_assert_same(true, strpos($screen, 'validate_token_field()') < strpos($screen, 'pg_ltd_starters_clean('), 'the posted trees after the form token');
	$run = (string)file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/legacy_to_designer_run.php');
	pg_assert_same(true, strpos($run, '_validate_and_clean_tree_json(json_encode($tree))') !== false, 'each starter passes the editor\'s tree check');
	pg_assert_same(true, strpos($run, "@unlink(pg_ltd_starters_path());\n") !== false, 'the starters go when the run ends');
	pg_assert_same(true, strpos(pg_ltd_convert_script(), 'StyleDesigner.starterTree(') !== false);
}

// A page given the frame's tree holds its own System region only when the
// frame is split and the region sits in the page content: in an unsplit
// frame, or in a part around the content, it is a shared component's and
// the types step leaves it.
function test_let_page_system_only_in_the_page_content()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/classic_style_design.php');
	$frame = function ($body) {
		return pg_classic_style_frame('<!DOCTYPE html><html><head><title>Site</title></head><body>' . $body . '</body></html>', function ($name) {
			return array('regionType' => 'common');
		})['frame'];
	};
	pg_assert_same(true, pg_ltd_frame_page_system($frame('<header>Top</header><main><pregion></pregion><system></system><pregion></pregion></main><footer>End</footer>')),
		'between the page regions');
	pg_assert_same(false, pg_ltd_frame_page_system($frame('<header>Top</header><main><pregion></pregion><system></system></main><footer>End</footer>')),
		'after the last page region: in the part after the content');
	pg_assert_same(false, pg_ltd_frame_page_system($frame('<header>Top</header><main><system></system></main><footer>End</footer>')),
		'no page region: the frame is one shared component');
	pg_assert_same(false, pg_ltd_frame_page_system($frame('<header><system></system></header><main><pregion></pregion></main><footer>End</footer>')),
		'in the part before the page content');
	pg_assert_same(false, pg_ltd_frame_page_system($frame('<main><pregion></pregion></main>')), 'no System region');
}

// Two pages whose names give the same slug (or none, a name in another
// script) and the same settings: a page type widget is found again by its
// layout too, so the second page never takes the first one's layout.
function test_let_type_widget_found_again_by_its_layout()
{
	if (!class_exists('db')) {
		eval('class db { public static $con; }');
	}
	$cfg = json_encode(array('regionType' => 'catalog_detail'));
	$where = pg_ltd_widget_row_where($cfg, 'design-catalog-detail-widget', 100, '{"type":"root","children":[]}');
	pg_assert_same(true, strpos($where, "MD5(tree_json) = '" . md5('{"type":"root","children":[]}') . "'") !== false, 'the layout is compared');
	pg_assert_same(true, strpos($where, "created_at >= '100'") !== false, 'made by this run');
	pg_assert_same(true, strpos($where, "name = 'design-catalog-detail-widget'") !== false, 'by its name');
	$region = pg_ltd_widget_row_where($cfg, 'home-menu-widget', 100, null);
	pg_assert_same(false, strpos($region, 'tree_json'), 'a region widget is found by name and settings, its starter is the same for every region');
}
