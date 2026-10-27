<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the per-placement content of a system widget
 * (includes/fn/widgets_placement.php): the keys each kind accepts, values
 * cleaned, a placement only narrowing the widget's settings, the marker and
 * its way through _expand_shared_refs()'s replace.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_widget_placement_keys()
{
	pg_assert_same(array('max_results', 'order_by_field', 'order_by_direction'), pg_sw_placement_keys('form_list_view'), 'form list');
	pg_assert_same(pg_sw_placement_keys('form_list_view'), pg_sw_placement_keys('blog_list'), 'a legacy form list name');
	pg_assert_same(array('menu_name'), pg_sw_placement_keys('menu_region'), 'menu');
	pg_assert_same(array(), pg_sw_placement_keys('login_form'), 'a form takes none');
	pg_assert_same(array(), pg_sw_placement_keys(''), 'an empty widget takes none');
}

function test_widget_placement_clean()
{
	pg_assert_same(array(), pg_sw_placement_clean(null), 'none');
	pg_assert_same(array(), pg_sw_placement_clean(array('max_results' => 0, 'menu_name' => ' ', 'order_by_direction' => 'asc')), 'empty or unknown values dropped');
	pg_assert_same(array('max_results' => 3, 'order_by_field' => 'price'), pg_sw_placement_clean(array('max_results' => '3', 'order_by_field' => 'price', 'empty_message' => 'x')), 'texts are not a placement value');
	pg_assert_same(array(), pg_sw_placement_clean(array('order_by_field' => 'name; DROP')), 'a field name of word characters only');
	pg_assert_same(array('calendar_ids' => array(2, 5)), pg_sw_placement_clean(array('calendar_ids' => array('2', 0, 5, 2, 'x'))), 'calendar ids');
}

function test_widget_placement_narrow_counts()
{
	pg_assert_same(3, pg_sw_placement_narrow('catalog_listing', array('max_results' => 0), array('max_results' => 3))['max_results'], 'a cap where the widget has none');
	pg_assert_same(5, pg_sw_placement_narrow('catalog_listing', array('max_results' => 5), array('max_results' => 20))['max_results'], 'never above the widget');
	pg_assert_same(4, pg_sw_placement_narrow('calendar_view', array(), array('number_of_upcoming_events' => 4))['number_of_upcoming_events'], 'upcoming events');
	pg_assert_same(10, pg_sw_placement_narrow('calendar_view', array(), array('number_of_upcoming_events' => 40))['number_of_upcoming_events'], 'not above the default ten');
	$cfg = array('max_results' => 7, 'empty_message' => 'None');
	pg_assert_same($cfg, pg_sw_placement_narrow('login_form', $cfg, array('max_results' => 2)), 'a kind without keys is left as it is');
	pg_assert_same($cfg, pg_sw_placement_narrow('catalog_listing', $cfg, array()), 'a placement without values');
}

function test_widget_placement_narrow_sources()
{
	$within = function ($g, $a) { return $g === 12 && $a === 10; };
	pg_assert_same(12, pg_sw_placement_narrow('catalog_listing', array('product_group_id' => 0), array('product_group_id' => 12), $within)['product_group_id'], 'any group under all groups');
	pg_assert_same(12, pg_sw_placement_narrow('catalog_listing', array('product_group_id' => 10), array('product_group_id' => 12), $within)['product_group_id'], 'a group under the widget\'s');
	pg_assert_same(10, pg_sw_placement_narrow('catalog_listing', array('product_group_id' => 10), array('product_group_id' => 30), $within)['product_group_id'], 'a group outside the widget\'s is ignored');
	pg_assert_same(10, pg_sw_placement_narrow('catalog_listing', array('product_group_id' => 10), array('product_group_id' => 12))['product_group_id'], 'without the check only the same group');
	pg_assert_same(array(3), pg_sw_placement_narrow('calendar_view', array('calendar_ids' => array(1, 3)), array('calendar_ids' => array(3, 4)))['calendar_ids'], 'calendars among the widget\'s');
	pg_assert_same(array(1, 3), pg_sw_placement_narrow('calendar_view', array('calendar_ids' => array(1, 3)), array('calendar_ids' => array(4)))['calendar_ids'], 'none of the widget\'s: the widget\'s stand');
	pg_assert_same(array(4), pg_sw_placement_narrow('calendar_view', array(), array('calendar_ids' => array(4)))['calendar_ids'], 'a widget of every calendar');
	pg_assert_same('Footer', pg_sw_placement_narrow('menu_region', array('menu_name' => 'Main'), array('menu_name' => 'Footer'))['menu_name'], 'menu');
	pg_assert_same('Main', pg_sw_placement_narrow('ad_region', array('menu_name' => 'Main'), array('menu_name' => 'Footer'))['menu_name'], 'a key of another kind is not read');
	$o = pg_sw_placement_narrow('form_list_view', array('order_by_field' => 'a', 'order_by_direction' => 'DESC'), array('order_by_direction' => 'ASC'));
	pg_assert_same(array('a', 'ASC'), array($o['order_by_field'], $o['order_by_direction']), 'order');
}

function test_widget_placement_marker()
{
	pg_assert_same('', pg_sw_placement_marker(array()), 'no values, no marker');
	pg_assert_same('', pg_sw_placement_marker(array('max_results' => 0)), 'only empty values, no marker');
	$values = array('max_results' => 3, 'menu_name' => 'Ana menü +/');
	$marker = pg_sw_placement_marker($values);
	pg_assert_true((bool)preg_match('/^<!--pg-placement:([A-Za-z0-9_-]+)-->$/', $marker, $m), 'marker of URL-safe characters');
	pg_assert_same($values, pg_sw_placement_decode($m[1]), 'read back');
	pg_assert_same(array(), pg_sw_placement_decode('!!'), 'a payload that does not read');
	pg_assert_same(array(), pg_sw_placement_decode(rtrim(strtr(base64_encode('{"empty_message":"x"}'), '+/', '-_'), '=')), 'a payload is cleaned');
}

function test_widget_placement_tree_render()
{
	$node = array('type' => 'shared_ref', 'props' => array('sharedId' => 7, 'placement' => array('max_results' => 2)), 'children' => array());
	$html = _render_tree_node($node, 0);
	pg_assert_true(strpos($html, pg_sw_placement_marker(array('max_results' => 2)) . '<!--pg-shared-ref:7-->') !== false, 'the placement marker stands before the reference');
	$node['props']['placement'] = array();
	pg_assert_same('<!--pg-shared-ref:7-->', trim(_render_tree_node($node, 0)), 'without values the body is as before');
	$tree = array('type' => 'root', 'props' => array('placement' => array()), 'children' => array());
	pg_designer_tree_objects($tree);
	pg_assert_true($tree['props']['placement'] instanceof stdClass, 'an empty placement is stored as an object');
}

// The editor's twin (_swPlacementKeys) offers the keys the server reads, and
// _swPlacementSet drops empty values and an empty placement.
function test_widget_placement_editor_twin()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return;
	}
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$fn = function ($name) use ($source) {
		$from = strpos($source, "\n    function " . $name . '(');
		pg_assert_true($from !== false, 'the designer has ' . $name . '()');
		$to = strpos($source, "\n    }\n", $from + 1);
		return substr($source, $from, $to + 7 - $from);
	};
	$kinds = array('form_list_view', 'blog_list', 'catalog_listing', 'calendar_view', 'menu_region', 'ad_region', 'login_form', 'catalog_item_view', '');
	$script = $fn('_swPlacementKeys') . $fn('_swPlacementSet')
		. 'var r = {}; ' . json_encode($kinds) . '.forEach(function (k) { r[k] = _swPlacementKeys(k); });'
		. 'var n = { type: "shared_ref", props: { sharedId: 7 } };'
		. '_swPlacementSet(n, "max_results", 3); _swPlacementSet(n, "menu_name", "Main"); r.set = JSON.parse(JSON.stringify(n.props));'
		. '_swPlacementSet(n, "max_results", 0); _swPlacementSet(n, "menu_name", ""); r.cleared = n.props;'
		. 'console.log(JSON.stringify(r));';
	$file = tempnam(sys_get_temp_dir(), 'pgplacement');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	$r = json_decode(implode("\n", $out), true);
	pg_assert_same(0, $code, implode("\n", $out));
	foreach ($kinds as $k) {
		pg_assert_same(pg_sw_placement_keys($k), $r[$k], 'twin keys of ' . ($k === '' ? '(empty)' : $k));
	}
	pg_assert_same(array('sharedId' => 7, 'placement' => array('max_results' => 3, 'menu_name' => 'Main')), $r['set'], 'values set');
	pg_assert_same(array('sharedId' => 7), $r['cleared'], 'an empty placement is removed');
}
