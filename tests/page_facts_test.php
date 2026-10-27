<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for conditions on the facts of the visit (includes/fn/page_facts.php):
 * a page node outside any widget whose every condition reads a fact (signed
 * in, cart has items, cart count) is drawn between markers the stored body
 * keeps, and each request keeps or cuts it; any other node draws as before.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A paragraph shown when $token passes $op (with $value), and more
// conditions in $more (token, op, value) joined by $join.
function _pf_node($text, $token, $op = '', $value = '', $more = array(), $join = '')
{
	$props = array('tag' => 'p', 'cssClass' => 'x', 'text' => $text,
		'_bindings' => array('show_if' => $token),
		'_bindParams' => array('show_if' => array('op' => $op, 'value' => $value)));
	foreach (array_values($more) as $i => $m) {
		$name = 'show_if_' . ($i + 2);
		$props['_bindings'][$name] = $m[0];
		$props['_bindParams'][$name] = array('op' => $m[1], 'value' => $m[2]);
	}
	if ($join !== '') $props['_bindParams']['show_if']['join'] = $join;
	return array('type' => 'semantic', 'props' => $props, 'children' => array());
}

function _pf_root($children)
{
	return array('type' => 'root', 'props' => array(), 'children' => $children);
}

function _pf_facts($signed_in, $count)
{
	return array(
		'__visit_signed_in'      => $signed_in ? '1' : '',
		'__visit_cart_has_items' => ($count > 0) ? '1' : '',
		'__visit_cart_count'     => ($count > 0) ? (string)$count : '',
	);
}

// What a visitor with $facts sees of $tree: the stored body, decided.
function _pf_seen($tree, $facts)
{
	return pg_page_facts_resolve(_render_tree_node($tree, 0, 0), $facts);
}

function test_page_facts_signed_in_band()
{
	$tree = _pf_root(array(
		_pf_node('Welcome back', '__visit_signed_in'),
		_pf_node('Sign in to save', '__visit_signed_in', 'empty'),
	));
	$body = _render_tree_node($tree, 0, 0);
	pg_assert_contains('<!--pg-pf:', $body, 'the stored body keeps markers');
	pg_assert_same($body, _render_tree_node($tree, 0, 0), 'the body is drawn the same each time');

	$in = _pf_seen($tree, _pf_facts(true, 0));
	pg_assert_contains('Welcome back', $in);
	pg_assert_false(strpos($in, 'Sign in to save') !== false, 'the signed-out band goes');
	pg_assert_false(strpos($in, '<!--') !== false, 'no marker left: ' . $in);

	$out = _pf_seen($tree, _pf_facts(false, 0));
	pg_assert_same('<p class="x">Sign in to save</p>' . "\n", $out);
}

function test_page_facts_cart()
{
	$tree = _pf_root(array(
		_pf_node('Cart waiting', '__visit_cart_has_items'),
		_pf_node('Many', '__visit_cart_count', 'gte', '3'),
		_pf_node('Member or cart', '__visit_signed_in', 'not_empty', '', array(array('__visit_cart_has_items', 'not_empty', '')), 'any'),
	));
	$empty = _pf_seen($tree, _pf_facts(false, 0));
	pg_assert_same('', $empty, 'nothing for an empty cart');
	$two = _pf_seen($tree, _pf_facts(false, 2));
	pg_assert_contains('Cart waiting', $two);
	pg_assert_false(strpos($two, 'Many') !== false, '2 is not 3 or more');
	pg_assert_contains('Member or cart', $two);
	pg_assert_contains('Many', _pf_seen($tree, _pf_facts(false, 3)));
}

// A span inside a kept span is decided too; a cut span takes its inside.
function test_page_facts_nested()
{
	$outer = _pf_node('', '__visit_signed_in');
	$outer['props']['tag'] = 'div';
	$outer['children'] = array(_pf_node('Cart too', '__visit_cart_has_items'), _pf_node('Always', '', ''));
	unset($outer['children'][1]['props']['_bindings'], $outer['children'][1]['props']['_bindParams']);
	$tree = _pf_root(array($outer));

	$both = _pf_seen($tree, _pf_facts(true, 1));
	pg_assert_contains('Cart too', $both);
	pg_assert_contains('Always', $both);
	$no_cart = _pf_seen($tree, _pf_facts(true, 0));
	pg_assert_false(strpos($no_cart, 'Cart too') !== false, 'inner span cut');
	pg_assert_contains('Always', $no_cart);
	pg_assert_same('', _pf_seen($tree, _pf_facts(false, 1)), 'outer cut takes all');

	// Two identical siblings pair in turn.
	$twins = _pf_root(array(_pf_node('A', '__visit_signed_in'), _pf_node('A', '__visit_signed_in')));
	pg_assert_same(2, substr_count(_pf_seen($twins, _pf_facts(true, 0)), '<p class="x">A</p>'));
	pg_assert_same('', _pf_seen($twins, _pf_facts(false, 0)));
}

// A node mixing a fact with another value, or with no fact at all, draws
// as before outside a widget: unmarked, always shown.
function test_page_facts_other_conditions_unchanged()
{
	$mixed = _pf_node('Mixed', '__visit_signed_in', '', '', array(array('__price', 'gt', '0')));
	$plain = _pf_node('Plain', '__price', 'gt', '0');
	foreach (array($mixed, $plain) as $node) {
		$html = _render_tree_node($node, 0, 0);
		pg_assert_false(strpos($html, '<!--') !== false, $html);
	}
	pg_assert_same('<p class="x">Plain</p>' . "\n", _render_tree_node($plain, 0, 0));
}

// Inside a widget render the finish pass decides it, as any condition.
function test_page_facts_inside_a_widget_untouched()
{
	$scope = pg_sw_finish_open(array('sid' => 7, 'kind' => 'form_list_view', 'email' => false));
	try {
		$html = _render_tree_node(_pf_node('In widget', '__visit_signed_in'), 0, 0);
	} finally {
		pg_sw_finish_close();
	}
	pg_assert_false(strpos($html, '<!--pg-pf:') !== false, 'no page marker');
	pg_assert_contains('<!--pg-if:', $html);
}

// Markers that cannot be read: an open one without its close shows its
// node; a span whose conditions cannot be read, or name another value or
// operator, is cut.
function test_page_facts_fail_closed()
{
	$facts = _pf_facts(true, 1);
	$spec = function ($data) { return rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '='); };
	$id = 'abcdef012345';
	pg_assert_same('a<b>x</b>', pg_page_facts_resolve('a<!--pg-pf:' . $id . ':' . $spec(array('j' => 'all', 'c' => array(array('__visit_signed_in', 'not_empty', '')))) . '--><b>x</b>', $facts));
	foreach (array(
		'junk',
		$spec(array('j' => 'all', 'c' => array(array('__price', 'not_empty', '')))),
		$spec(array('j' => 'all', 'c' => array(array('__visit_signed_in', 'nope', '')))),
		$spec(array('j' => 'all', 'c' => array(array('__visit_cart_count', 'gt', '')))),
		$spec(array('j' => 'all', 'c' => array())),
	) as $bad) {
		pg_assert_same('ab', pg_page_facts_resolve('a<!--pg-pf:' . $id . ':' . $bad . '-->x<!--/pg-pf:' . $id . '-->b', $facts), $bad);
	}
	pg_assert_same('plain <!-- other -->', pg_page_facts_apply('plain <!-- other -->'), 'a page without markers is left as it is');
}

// Background renders belong to no visit.
function test_page_facts_values_of_an_email()
{
	pg_assert_same(_pf_facts(false, 0), pg_page_fact_values(true));
}

// The editor offers the facts the server reads (SD_PAGE_FACTS).
function test_page_facts_editor_list_matches()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$from = strpos($js, "\n    var SD_PAGE_FACTS = [");
	pg_assert_true($from !== false, 'SD_PAGE_FACTS is found');
	$block = substr($js, $from, strpos($js, "\n    ];\n", $from) - $from);
	preg_match_all("/\\['(__[a-z_]+)'/", $block, $m);
	pg_assert_same(pg_page_fact_tokens(), $m[1]);
}
