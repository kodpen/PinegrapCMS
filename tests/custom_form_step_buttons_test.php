<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the step buttons a designer builds (section form_step_nav,
 * includes/fn/custom_form_steps.php): one design drawn under every step of
 * a designed form, Back kept from the second step, Next up to the one but
 * last, marked for the steps script; a form without it renders as before.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_cf_nav_break($title)
{
	return array('type' => 'semantic', 'props' => array(
		'tag' => 'div', 'cssClass' => 'h5', 'text' => $title,
		'_bindings' => array('section' => 'form_step'),
	), 'children' => array());
}

function _test_cf_nav_input($name)
{
	return array('type' => 'semantic', 'props' => array('tag' => 'input', '_attrs' => array(
		array('name' => 'type', 'value' => 'text'),
		array('name' => 'name', 'value' => $name),
	)), 'children' => array());
}

function _test_cf_nav_tree($children)
{
	return array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'col'), 'children' => $children),
	));
}

function _test_cf_nav_button($text, $flag)
{
	$props = array('tag' => 'button', 'cssClass' => 'btn', 'text' => $text,
		'_attrs' => array(array('name' => 'type', 'value' => 'button')));
	if ($flag !== '') $props['_bindings'] = array('eo_visible_if' => $flag);
	return array('type' => 'semantic', 'props' => $props, 'children' => array());
}

function _test_cf_nav()
{
	return array('type' => 'semantic', 'props' => array(
		'tag' => 'div', 'cssClass' => 'd-flex gap-3', '_bindings' => array('section' => 'form_step_nav'),
	), 'children' => array(
		_test_cf_nav_button('Go back', 'has_prev_step'),
		_test_cf_nav_button('Help', ''),
		_test_cf_nav_button('Go on', 'has_next_step'),
	));
}

function _test_cf_nav_form($with_nav)
{
	$col = array(_test_cf_nav_break('One'), _test_cf_nav_input('a'), _test_cf_nav_break('Two'),
		_test_cf_nav_input('b'), _test_cf_nav_break('Three'), _test_cf_nav_input('c'));
	if ($with_nav) $col[] = _test_cf_nav();
	return _test_cf_nav_tree($col);
}

// The step a fieldset of the rendered form ends with: its button texts.
function _test_cf_nav_texts($fieldset)
{
	$last = end($fieldset['children']);
	$out = array();
	foreach ($last['children'] as $b) $out[] = $b['props']['text'];
	return implode(',', $out);
}

function test_custom_form_step_buttons_detection()
{
	pg_assert_true(pg_cf_is_step_nav(_test_cf_nav()), 'the step buttons section');
	pg_assert_true(!pg_cf_is_step_nav(_test_cf_nav_break('x')), 'a break is not the buttons');
	pg_assert_true(!pg_cf_is_step_nav(null), 'nothing');
}

function test_custom_form_step_buttons_per_step()
{
	$tree = _test_cf_nav_form(true);
	pg_assert_same(3, pg_cf_apply_steps($tree), 'three steps');
	$col = $tree['children'][0]['children'];
	pg_assert_same(4, count($col), 'progress and three steps: the design left its place');
	pg_assert_same('Help,Go on', _test_cf_nav_texts($col[1]), 'first step: no Back');
	pg_assert_same('Go back,Help,Go on', _test_cf_nav_texts($col[2]), 'middle step: both');
	pg_assert_same('Go back,Help', _test_cf_nav_texts($col[3]), 'last step: no Next, the submit sends');

	$html = _render_tree_node($tree, 0, 0);
	pg_assert_same(3, substr_count($html, 'data-pg-step-nav'), 'one box per step, for the script');
	pg_assert_same(2, substr_count($html, 'data-pg-step-back'), 'Back marked');
	pg_assert_same(2, substr_count($html, 'data-pg-step-next'), 'Next marked');
	pg_assert_true(strpos($html, 'd-flex gap-3') !== false, 'the box keeps its classes');
	pg_assert_true(strpos($html, 'Go back') !== false && strpos($html, '>Back<') === false, 'the design replaces the built-in buttons');
	pg_assert_true(strpos($html, 'form_step_nav') === false && strpos($html, 'has_prev_step') === false, 'no binding left in the markup');
	pg_assert_true((bool) preg_match('/<div[^>]*data-pg-step-nav[^>]*\shidden/', $html), 'drawn hidden until the script shows it');
}

function test_custom_form_step_buttons_without_design_unchanged()
{
	$a = _test_cf_nav_form(false);
	$copy = $a;
	pg_assert_same(3, pg_cf_apply_steps($a), 'three steps');
	$b = $copy;
	pg_assert_same(null, pg_cf_take_step_nav($b), 'no design found');
	pg_assert_same($copy, $b, 'taking nothing leaves the tree');
	$html = _render_tree_node($a, 0, 0);
	pg_assert_same(2, substr_count($html, 'data-pg-step-back'), 'the built-in Back');
	pg_assert_true(strpos($html, '>Back<') !== false, 'the built-in words');
}

function test_custom_form_step_buttons_without_steps()
{
	// One step or none: no buttons at all, and the design never prints.
	$one = _test_cf_nav_tree(array(_test_cf_nav_break('Only'), _test_cf_nav_input('a'), _test_cf_nav()));
	pg_assert_same(1, pg_cf_apply_steps($one), 'one step');
	$html = _render_tree_node($one, 0, 0);
	pg_assert_true(strpos($html, 'Go on') === false && strpos($html, 'data-pg-step-nav') === false, 'no buttons on a single step');

	$none = _test_cf_nav_tree(array(_test_cf_nav_input('a'), _test_cf_nav()));
	pg_assert_same(0, pg_cf_apply_steps($none), 'no break');
	pg_assert_same(1, count($none['children'][0]['children']), 'the stray design is dropped');
}

function test_custom_form_step_buttons_second_design_dropped()
{
	$tree = _test_cf_nav_form(true);
	$tree['children'][] = _test_cf_nav();
	$taken = pg_cf_take_step_nav($tree);
	pg_assert_true(pg_cf_is_step_nav($taken), 'the first design is taken');
	$left = json_encode($tree);
	pg_assert_true(strpos($left, 'form_step_nav') === false, 'every design leaves the tree');
}
