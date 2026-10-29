<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for read-only parts of the page's record placed outside the detail
 * widget (includes/fn/widgets_parts_anywhere.php): a part node stands as a
 * marker in the stored body, its widget draws it inside its main loop and
 * the drawn span moves to the marker; a tree without a part node renders
 * as before.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _wpa_el($tag, $class, $children = array(), $props = array())
{
	return array('type' => 'semantic', 'props' => array('tag' => $tag, 'cssClass' => $class) + $props, 'children' => $children);
}

// A text bound to the widget token $token.
function _wpa_bound($tag, $class, $token)
{
	return _wpa_el($tag, $class, array(), array('text' => '', '_bindings' => array('text' => $token)));
}

// A price block: the price, and a stock line shown while in stock.
function _wpa_price_block()
{
	$stock = _wpa_el('p', 'small text-success', array(), array('text' => 'In stock',
		'_bindings' => array('show_if' => '__in_stock'), '_bindParams' => array('show_if' => array('op' => 'not_empty', 'value' => ''))));
	return _wpa_el('div', 'price-block', array(_wpa_bound('span', 'fs-3 fw-bold', '__price'), $stock));
}

function _wpa_part($node, $sid)
{
	$node['props']['partOf'] = $sid;
	return $node;
}

function _wpa_root($children)
{
	return array('type' => 'root', 'props' => array(), 'children' => $children);
}

// A detail widget's tree: a heading, then the main loop with the name.
function _wpa_widget_tree($loop_children)
{
	return _wpa_root(array(
		_wpa_el('h2', 'h5', array(), array('text' => 'Product')),
		array('type' => 'loop_area', 'props' => array(), 'children' => $loop_children),
	));
}

// What a detail renderer does with $tree_json for one record of $values:
// the tree split, the loop drawn once and filled, the finish pass run, all
// inside the widget's scope (as _expand_system_widgets() opens it).
function _wpa_widget_draw($tree_json, $values)
{
	$scope = pg_sw_finish_open(array('sid' => 7, 'kind' => 'catalog_item_view', 'email' => false));
	try {
		$split = _split_widget_tree(json_decode($tree_json, true));
		$loop = '';
		foreach ($split['loop_children'] as $child) $loop .= _render_tree_node($child, 0, 0);
		$static = _render_tree_node($split['static_tree'], 0, 0);
		$rendered = pg_sw_sweep_tokens(pg_sw_fill_longest(trim($loop), $values));
		$html = str_replace('<!--pg-loop-slot-->', $rendered, pg_sw_sweep_tokens(pg_sw_fill_longest(trim($static), $values)));
	} finally {
		pg_sw_finish_close();
	}
	return pg_sw_finish_widget_html($html, $scope);
}

function test_widget_parts_anywhere_tree_without_part_is_unchanged()
{
	$tree = _wpa_root(array(_wpa_el('div', 'row', array(_wpa_price_block())), array('type' => 'shared_ref', 'props' => array('sharedId' => 7), 'children' => array())));
	$plain = _render_tree_node($tree, 0, 0);
	$zero = $tree;
	$zero['children'][0]['props']['partOf'] = 0;
	pg_assert_same($plain, _render_tree_node($zero, 0, 0));
	pg_assert_false(strpos($plain, 'pg-wpart') !== false);
	pg_assert_same($plain, pg_sw_away_place($plain, array()));
	pg_assert_same(array(), pg_sw_away_collect($plain));
	pg_assert_same('<div>x</div>', _expand_system_widgets('<div>x</div>'));
	$tree_json = json_encode(_wpa_widget_tree(array(_wpa_bound('h1', 'h3', '__name'))));
	pg_assert_same($tree_json, pg_sw_away_inject($tree_json, array()));
}

function test_widget_parts_anywhere_marker_carries_subtree()
{
	$part = _wpa_part(_wpa_price_block(), 7);
	$html = _render_tree_node(_wpa_root(array(_wpa_el('div', 'col', array($part)))), 0, 0);
	pg_assert_contains('<!--pg-wpart:7:', $html);
	$found = pg_sw_away_collect($html);
	pg_assert_same(1, count($found));
	pg_assert_same(7, $found[0]['sid']);
	pg_assert_same(_wpa_price_block(), $found[0]['node']);
	// Drawn again from the same tree, the same bytes.
	pg_assert_same($html, _render_tree_node(_wpa_root(array(_wpa_el('div', 'col', array($part)))), 0, 0));
	// "7" as a string names the same widget; a word names none.
	pg_assert_same(7, pg_sw_away_of(_wpa_part(_wpa_price_block(), '7')));
	pg_assert_same(0, pg_sw_away_of(_wpa_part(_wpa_price_block(), 'x')));
}

function test_widget_parts_anywhere_controls_are_refused()
{
	$input = _wpa_el('div', 'qty', array(_wpa_el('input', 'form-control', array(), array('type' => 'number'))));
	pg_assert_same('', _render_tree_node(_wpa_part($input, 7), 0, 0));
	$cart = _wpa_el('div', 'x', array(), array('_bindings' => array('action' => 'add_to_cart')));
	pg_assert_false(pg_sw_away_read_only($cart));
	$section = _wpa_el('div', 'x', array(), array('_bindings' => array('section' => 'cart_summary')));
	pg_assert_false(pg_sw_away_read_only($section));
	$messages = array('type' => 'content', 'props' => array('contentType' => 'messages'), 'children' => array());
	pg_assert_false(pg_sw_away_read_only(_wpa_el('div', '', array($messages))));
	pg_assert_false(pg_sw_away_read_only(array('type' => 'loop_area', 'props' => array(), 'children' => array())));
	pg_assert_false(pg_sw_away_read_only(array('type' => 'shared_ref', 'props' => array('sharedId' => 3), 'children' => array())));
	pg_assert_true(pg_sw_away_read_only(_wpa_price_block()));
	// A marker whose subtree holds a control is not drawn either.
	$spec = rtrim(strtr(base64_encode(json_encode($input)), '+/', '-_'), '=');
	$found = pg_sw_away_collect('<!--pg-wpart:7:' . $spec . '-->');
	pg_assert_same(null, $found[0]['node']);
}

function test_widget_parts_anywhere_part_draws_as_inside_the_widget()
{
	$values = array('__name' => 'Mug', '__price' => '120,00 TL', '__in_stock' => '1');
	$name = _wpa_bound('h1', 'h3', '__name');

	// The part drawn by the widget, from its marker on the page.
	$page = _render_tree_node(_wpa_root(array(_wpa_el('aside', 'col-4', array(_wpa_part(_wpa_price_block(), 7))))), 0, 0);
	$parts = array();
	foreach (pg_sw_away_collect($page) as $i => $p) $parts['t.' . $i] = $p['node'];
	$widget = _wpa_widget_draw(pg_sw_away_inject(json_encode(_wpa_widget_tree(array($name))), $parts), $values);
	$spans = pg_sw_away_take($widget, array_keys($parts));
	$placed = pg_sw_away_place($page, $spans, 't.', array(7));

	// The same block placed inside the widget's loop.
	$inside = _wpa_widget_draw(json_encode(_wpa_widget_tree(array($name, _wpa_price_block()))), $values);
	$alone = _wpa_widget_draw(json_encode(_wpa_widget_tree(array($name))), $values);

	pg_assert_contains('120,00 TL', $spans['t.0']);
	pg_assert_contains('In stock', $spans['t.0']);
	pg_assert_contains($spans['t.0'], $inside);
	pg_assert_same(trim($alone), trim($widget));
	pg_assert_contains('<aside class="col-4">', $placed);
	pg_assert_contains($spans['t.0'], $placed);
	pg_assert_false(strpos($placed . $widget, 'pg-wpart') !== false);

	// Out of stock: the widget's condition decides the part's stock line.
	$widget = _wpa_widget_draw(pg_sw_away_inject(json_encode(_wpa_widget_tree(array($name))), $parts), array('__in_stock' => '') + $values);
	$spans = pg_sw_away_take($widget, array_keys($parts));
	pg_assert_false(strpos($spans['t.0'], 'In stock') !== false);
}

function test_widget_parts_anywhere_tree_without_loop_takes_part_at_end()
{
	$tree = _wpa_root(array(_wpa_bound('h1', 'h3', '__name')));
	$out = json_decode(pg_sw_away_inject(json_encode($tree), array('k.0' => _wpa_price_block())), true);
	pg_assert_same(4, count($out['children']));
	pg_assert_same('<!--pg-wpart-s:k.0-->', $out['children'][1]['props']['html']);
	pg_assert_same(_wpa_price_block(), $out['children'][2]);
	pg_assert_same('<!--pg-wpart-e:k.0-->', $out['children'][3]['props']['html']);
}

function test_widget_parts_anywhere_place_and_leftovers()
{
	$m7 = pg_sw_away_marker(_wpa_part(_wpa_price_block(), 7));
	$m9 = pg_sw_away_marker(_wpa_part(_wpa_price_block(), 9));
	$html = '<a>' . $m7 . '</a><b>' . $m9 . '</b><c>' . $m7 . '</c>';
	// Widget 7 drew its first part only (a condition cut the other);
	// widget 9 waits for a later pass.
	pg_assert_same('<a>P</a><b>' . $m9 . '</b><c></c>', pg_sw_away_place($html, array('x.0' => 'P'), 'x.', array(7)));
	// A part of a widget the page does not draw shows nothing.
	pg_assert_same('<a></a><b></b><c></c>', pg_sw_away_place($html, array()));
	// A widget that drew no record leaves no fence behind.
	$out = "<div>\n    <!--pg-wpart-s:k.0-->\n</div>";
	pg_assert_same(array(), pg_sw_away_take($out, array('k.0')));
	pg_assert_same("<div>\n    \n</div>", $out);
}

function test_widget_parts_anywhere_page_fact_condition_wraps_marker()
{
	$part = _wpa_part(_wpa_price_block(), 7);
	$part['props']['_bindings']['show_if'] = '__visit_signed_in';
	$part['props']['_bindParams']['show_if'] = array('op' => 'not_empty', 'value' => '');
	$html = _render_tree_node($part, 0, 0);
	pg_assert_contains('<!--pg-pf:', $html);
	pg_assert_contains('<!--pg-wpart:7:', $html);
	$found = pg_sw_away_collect($html);
	pg_assert_false(isset($found[0]['node']['props']['_bindings']['show_if']));

	// A condition on a value of the widget goes with the part.
	$own = _wpa_part(_wpa_price_block(), 7);
	$own['props']['_bindings']['show_if'] = '__in_stock';
	$own['props']['_bindParams']['show_if'] = array('op' => 'not_empty', 'value' => '');
	$html = _render_tree_node($own, 0, 0);
	pg_assert_false(strpos($html, '<!--pg-pf:') !== false);
	$found = pg_sw_away_collect($html);
	pg_assert_same('__in_stock', $found[0]['node']['props']['_bindings']['show_if']);
}
