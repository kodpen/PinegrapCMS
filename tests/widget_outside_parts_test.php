<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the read-only parts of a detail widget placed outside its box
 * (includes/fn/widgets_outside_parts.php): the page marker and what a part
 * may hold, the parts handed to the widget's first render, the marks its
 * output is cut at, and the markers filled, emptied or swept.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _wop_price()
{
	return array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => 'Price',
		'cssClass' => 'fs-4', '_bindings' => array('text' => '__price_formatted')), 'children' => array());
}

function _wop_part($sid, $part, $children)
{
	return array('type' => 'widget_part', 'props' => array('widgetId' => $sid, 'part' => $part), 'children' => $children);
}

// Only a known part of a named widget gets a marker; its elements ride in it.
function test_widget_outside_parts_marker()
{
	pg_assert_same('', pg_sw_outside_part_marker(array('widgetId' => 0, 'part' => 'price'), array(_wop_price())), 'no widget: no marker');
	pg_assert_same('', pg_sw_outside_part_marker(array('widgetId' => 5, 'part' => 'add_to_cart'), array(_wop_price())), 'a part with controls: no marker');
	pg_assert_same('', pg_sw_outside_part_marker(array('widgetId' => 5), array(_wop_price())), 'no part: no marker');

	$marker = pg_sw_outside_part_marker(array('widgetId' => 5, 'part' => 'price'), array(_wop_price()));
	pg_assert_true(strpos($marker, '<!--pg-widget-part:5:price:') === 0, 'marker names the widget and the part');
	pg_assert_true(strpos($marker, '=') === false && strpos($marker, '+') === false && strpos($marker, '/') === false, 'payload is URL-safe base64 without padding');

	$found = pg_sw_outside_parts_find('<p>a</p>' . $marker . '<p>b</p>' . $marker);
	pg_assert_same(2, count($found), 'every marker is found');
	pg_assert_same(5, $found[0]['sid'], 'widget id read back');
	pg_assert_same('price', $found[0]['part'], 'part read back');
	pg_assert_same(array(_wop_price()), $found[0]['children'], 'elements read back');
	pg_assert_same(array(), pg_sw_outside_parts_find('<p>no parts</p>'), 'a body without markers has none');
}

// Controls, posted values, sections, widgets, regions and a main loop stay
// inside the widget; texts, pictures and a sourced list go out.
function test_widget_outside_parts_clean()
{
	$input  = array('type' => 'semantic', 'props' => array('tag' => 'input', '_bindings' => array('value' => 'quantity')), 'children' => array());
	$button = array('type' => 'component', 'props' => array('componentType' => 'btn', '_bindings' => array('action' => 'add_to_cart')), 'children' => array());
	$link   = array('type' => 'component', 'props' => array('componentType' => 'btn', 'href' => '#'), 'children' => array());
	$form   = array('type' => 'semantic', 'props' => array('tag' => 'FORM'), 'children' => array(_wop_price()));
	$sect   = array('type' => 'semantic', 'props' => array('tag' => 'div', '_bindings' => array('section' => 'recipient_picker')), 'children' => array());
	$va     = array('type' => 'content', 'props' => array('contentType' => 'variant_attr'), 'children' => array());
	$msgs   = array('type' => 'content', 'props' => array('contentType' => 'messages'), 'children' => array());
	$php    = array('type' => 'content', 'props' => array('contentType' => 'custom_php', 'code' => 'echo 1;'), 'children' => array());
	$ref    = array('type' => 'shared_ref', 'props' => array('sharedId' => 4), 'children' => array());
	$loop   = array('type' => 'loop_area', 'props' => array(), 'children' => array(_wop_price()));
	$list   = array('type' => 'loop_area', 'props' => array('source' => 'catalog.crumbs'), 'children' => array(_wop_price()));
	$nested = _wop_part(5, 'price', array(_wop_price()));
	$gallery = array('type' => 'content', 'props' => array('contentType' => 'carousel'), 'children' => array());
	$box    = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(_wop_price(), $input, $button, $gallery));

	$kept = pg_sw_outside_part_clean(array($input, $button, $link, $form, $sect, $va, $msgs, $php, $ref, $loop, $list, $nested, $box, 'junk'));
	$box['children'] = array(_wop_price(), $gallery);
	pg_assert_same(array($link, $list, $box), $kept, 'only read-only elements are kept, at any depth');
	pg_assert_same(array(), pg_sw_outside_part_clean(null), 'no list: nothing');
}

// The page draws the marker; the widget's render draws the elements between
// this request's marks.
function test_widget_outside_parts_render_node()
{
	$node = _wop_part(5, 'price', array(_wop_price()));
	$out  = _render_tree_node(array('type' => 'root', 'props' => array(), 'children' => array($node)));
	pg_assert_same(pg_sw_outside_part_marker($node['props'], $node['children']), trim($out), 'outside a widget: the marker');
	pg_assert_same('', _render_tree_node(_wop_part(0, 'price', array(_wop_price()))), 'no widget: nothing');

	$tree = pg_sw_outside_parts_tree('{"type":"root","props":{},"children":[]}', array(3 => array('part' => 'price', 'children' => array(_wop_price()))));
	$decoded = json_decode($tree, true);
	pg_assert_same('widget_part', $decoded['children'][0]['type'], 'the part goes to the end of the widget tree');
	pg_assert_same(3, $decoded['children'][0]['props']['_partNo'], 'carrying its number');
	pg_assert_same('not json', pg_sw_outside_parts_tree('not json', array(array('part' => 'price', 'children' => array()))), 'a tree that does not read stays');
	pg_assert_same('{"a":1}', pg_sw_outside_parts_tree('{"a":1}', array()), 'no parts: the tree as it is');

	pg_sw_finish_open(array('sid' => 5, 'kind' => 'catalog_item_view', 'email' => false));
	try {
		$html = _render_tree_node($decoded);
		$stray = _render_tree_node(_wop_part(5, 'price', array(_wop_price())));
	} finally {
		pg_sw_finish_close();
	}
	list($open, $close) = pg_sw_outside_part_marks(pg_sw_marker_nonce(), 3);
	pg_assert_true(strpos($html, $open) !== false && strpos($html, $close) > strpos($html, $open), 'inside the widget: between the marks');
	pg_assert_contains('^^__price_formatted^^', $html, 'bound as the widget binds');
	pg_assert_same('', $stray, 'a part node in a widget tree without a number draws nothing');
}

// The widget hands each part to its marker; one it does not draw empties it;
// a marker no widget took is swept.
function test_widget_outside_parts_take_and_place()
{
	$m0 = pg_sw_outside_part_marker(array('widgetId' => 5, 'part' => 'price'), array(_wop_price()));
	$m1 = pg_sw_outside_part_marker(array('widgetId' => 5, 'part' => 'stock'), array(_wop_price()));
	$m2 = pg_sw_outside_part_marker(array('widgetId' => 9, 'part' => 'rating'), array(_wop_price()));
	$page = '<header>' . $m0 . '</header><!--pg-system-widget:5--><aside>' . $m1 . '</aside>' . $m2;

	$pending = pg_sw_outside_parts_find($page);
	pg_assert_same(array(), pg_sw_outside_parts_for($pending, 5, 'order_view'), 'a kind without parts takes none');
	$own = pg_sw_outside_parts_for($pending, 5, 'catalog_item_view');
	pg_assert_same(array(0, 1), array_keys($own), 'the widget takes its own parts');
	pg_assert_same(array(2), array_keys($pending), 'another widget\'s part stays pending');
	pg_assert_same(array(), pg_sw_outside_parts_for($pending, 5, 'catalog_item_view'), 'a second placement takes none');

	$nonce = 'abc123';
	list($o0, $c0) = pg_sw_outside_part_marks($nonce, 0);
	$widget = '<form><h1>Boot</h1>' . $o0 . "\n<p>$ 10</p>\n" . $c0 . '</form>';
	$drawn  = array();
	pg_assert_same('<form><h1>Boot</h1></form>', pg_sw_outside_parts_take($widget, array(0, 1), $drawn, $nonce), 'the part leaves the widget box');
	pg_assert_same(array(0 => '<p>$ 10</p>'), $drawn, 'its markup is kept under its number');

	$placed = pg_sw_outside_parts_place($page, $own, $drawn);
	pg_assert_same('<header><p>$ 10</p></header><!--pg-system-widget:5--><aside></aside>' . $m2, $placed, 'drawn part placed, undrawn one emptied, another widget\'s left');
	pg_assert_same('<header><p>$ 10</p></header><!--pg-system-widget:5--><aside></aside>', pg_sw_outside_parts_sweep($placed), 'the sweep takes what no widget drew');
	pg_assert_same('<p>x</p>', pg_sw_outside_parts_sweep('<p>x</p>'), 'a body without markers is unchanged');
}
