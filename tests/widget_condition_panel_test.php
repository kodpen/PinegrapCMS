<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the "Show only when a value…" row of the designer's Bind Data
 * panel: its operator and refused-token lists against the server's
 * (includes/fn/widgets_conditions.php), the token choice, the writer and
 * the canvas sentence (style_designer.js, run under Node), the rule that a
 * condition never hides a required form control, and the condition's
 * compared text as a translatable field (includes/translate/extract.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _widget_cond_panel_js()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
}

// The text of a `var NAME = [ ... ];` block of style_designer.js.
function _widget_cond_panel_js_var($source, $name)
{
	$from = strpos($source, "\n    var " . $name . ' = [');
	if ($from === false) {
		return '';
	}
	$to = strpos($source, "\n    ];\n", $from);
	return ($to === false) ? '' : substr($source, $from, $to + 7 - $from);
}

// A function of style_designer.js, cut out at its four-space indent.
function _widget_cond_panel_js_function($source, $name)
{
	$from = strpos($source, "\n    function " . $name . '(');
	if ($from === false) {
		return '';
	}
	$to = strpos($source, "\n    }\n", $from);
	return ($to === false) ? '' : substr($source, $from, $to + 7 - $from);
}

function _widget_cond_panel_input($required, $attr_name = 'required')
{
	$attrs = array(array('name' => 'type', 'value' => 'text'));
	if ($required) {
		$attrs[] = array('name' => $attr_name, 'value' => '');
	}
	return array('type' => 'semantic', 'props' => array('tag' => 'input', '_attrs' => $attrs), 'children' => array());
}

function _widget_cond_panel_box($children, $show_if = '__name')
{
	$props = array('tag' => 'div', 'cssClass' => 'box');
	if ($show_if !== null) {
		$props['_bindings'] = array('show_if' => $show_if);
		$props['_bindParams'] = array('show_if' => array('op' => 'eq', 'value' => 'x'));
	}
	return array('type' => 'semantic', 'props' => $props, 'children' => $children);
}

// A node holds a required control when it is one or has one inside.
function test_widget_condition_holds_required()
{
	pg_assert_true(pg_sw_cond_holds_required(_widget_cond_panel_input(true)), 'a required input');
	pg_assert_true(pg_sw_cond_holds_required(_widget_cond_panel_input(true, 'REQUIRED')), 'the name in capitals');
	pg_assert_true(pg_sw_cond_holds_required(_widget_cond_panel_box(array(_widget_cond_panel_box(array(_widget_cond_panel_input(true)), null)))), 'deep inside');
	pg_assert_true(!pg_sw_cond_holds_required(_widget_cond_panel_input(false)), 'an optional input');
	pg_assert_true(!pg_sw_cond_holds_required(_widget_cond_panel_box(array(_widget_cond_panel_input(false)))), 'a box of optional inputs');
	pg_assert_true(!pg_sw_cond_holds_required(array('type' => 'content', 'props' => array('text' => 'required'), 'children' => array())), 'the word in a text');
	pg_assert_true(!pg_sw_cond_holds_required(null), 'nothing');
}

// Inside a widget a condition on a node that holds a required control is
// not applied: the control is drawn, so the form stays sendable. Any other
// node is drawn between the condition markers.
function test_widget_condition_never_hides_a_required_control()
{
	$scope = pg_sw_finish_open(array('sid' => 9, 'kind' => 'custom_form', 'email' => false));
	try {
		$required = _render_tree_node(_widget_cond_panel_box(array(_widget_cond_panel_input(true))), 0, 0);
		$optional = _render_tree_node(_widget_cond_panel_box(array(_widget_cond_panel_input(false))), 0, 0);
	} finally {
		pg_sw_finish_close();
	}
	pg_assert_contains('<input', $required);
	pg_assert_true(strpos($required, '<!--pg-if:') === false, 'no condition marker around a required control');
	pg_assert_contains('<!--pg-if:', $optional);
}

function _widget_cond_panel_props($op, $value)
{
	return array(
		'tag' => 'span',
		'text' => 'Sample',
		'_bindings' => array('text' => '__name', 'show_if' => '__category'),
		'_bindParams' => array('show_if' => array('op' => $op, 'value' => $value)),
	);
}

// The text a condition compares a value with enters the site translation
// when it is words; a number, an empty test and a missing value do not.
function test_widget_condition_operand_is_a_translatable_field()
{
	$has = function ($props) {
		foreach (pg_tr_node_fields('semantic', $props) as $spec) {
			if ($spec[0] === '_cond:show_if') return $spec[1];
		}
		return null;
	};
	foreach (array('eq', 'neq', 'contains', 'not_contains', 'starts') as $op) {
		pg_assert_same('text', $has(_widget_cond_panel_props($op, 'Kültür')), $op);
	}
	pg_assert_same(null, $has(_widget_cond_panel_props('eq', '12,50')), 'a number');
	pg_assert_same(null, $has(_widget_cond_panel_props('eq', '₺0')), 'an amount');
	pg_assert_same(null, $has(_widget_cond_panel_props('gt', 'abc')), 'a numeric operator');
	pg_assert_same(null, $has(_widget_cond_panel_props('not_empty', 'Kültür')), 'no value is compared');
	pg_assert_same(null, $has(_widget_cond_panel_props('eq', '  ')), 'an empty value');

	$props = _widget_cond_panel_props('eq', 'Kültür');
	pg_assert_same('Kültür', pg_tr_field_get($props, '_cond:show_if'));
	pg_tr_field_set($props, '_cond:show_if', 'Culture');
	pg_assert_same('Culture', $props['_bindParams']['show_if']['value']);
	pg_assert_same('eq', $props['_bindParams']['show_if']['op']);
	pg_assert_same('', pg_tr_field_get(array(), '_cond:show_if'));
}

// The renderer reads the condition after the language hook has translated
// its compared text, then drops the condition from the props it draws.
function test_widget_condition_render_translates_before_it_reads()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	$from = strpos($src, "isset(\$node['props']['_bindings']['show_if'])");
	pg_assert_true($from !== false, 'the condition block is found');
	if ($from === false) return;
	$block = substr($src, $from, 1200);
	$tr = strpos($block, 'pg_tr_condition_props(');
	$spec = strpos($block, 'pg_sw_cond_spec(');
	pg_assert_true($tr !== false && $spec !== false && $tr < $spec, 'translated before it is read');
}
