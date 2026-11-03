<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the steps of a designed form (includes/fn/custom_form_steps.php):
 * a "Step" break in the widget tree splits the controls after it into a
 * <fieldset> shown one at a time with Back, Next and a progress line; a form
 * without a break renders as before.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_cf_steps_break($title)
{
	return array('type' => 'semantic', 'props' => array(
		'tag' => 'div', 'cssClass' => 'h5', 'text' => $title,
		'_bindings' => array('section' => 'form_step'),
	), 'children' => array());
}

function _test_cf_steps_input($name)
{
	return array('type' => 'semantic', 'props' => array('tag' => 'input', '_attrs' => array(
		array('name' => 'type', 'value' => 'text'),
		array('name' => 'name', 'value' => $name),
	)), 'children' => array());
}

function _test_cf_steps_names($nodes)
{
	$out = array();
	foreach ($nodes as $n) {
		if (pg_cf_is_step_break($n)) { $out[] = '|'; continue; }
		foreach ($n['props']['_attrs'] as $a) if ($a['name'] === 'name') $out[] = $a['value'];
	}
	return $out;
}

function _test_cf_steps_summary($groups)
{
	$steps = array();
	foreach ($groups['steps'] as $s) $steps[] = $s['title'] . ':' . implode(',', _test_cf_steps_names($s['children']));
	return array('head' => _test_cf_steps_names($groups['head']), 'steps' => $steps);
}

// ── Grouping ───────────────────────────────────────────────────────────

function test_custom_form_steps_break_detection()
{
	pg_assert_true(pg_cf_is_step_break(_test_cf_steps_break('A')), 'a node bound to form_step is a break');
	pg_assert_true(!pg_cf_is_step_break(_test_cf_steps_input('a')), 'a control is not');
	pg_assert_true(!pg_cf_is_step_break(array('type' => 'semantic', 'props' => array('tag' => 'div', '_bindings' => array('section' => 'captcha')))), 'another section is not');
	pg_assert_true(!pg_cf_is_step_break('text'), 'a scalar is not');
}

function test_custom_form_steps_no_break()
{
	pg_assert_same(null, pg_cf_step_groups(array(_test_cf_steps_input('a'), _test_cf_steps_input('b'))), 'no break, no groups');
	pg_assert_same(null, pg_cf_step_groups(array()), 'nothing, no groups');
}

function test_custom_form_steps_break_at_start()
{
	$g = pg_cf_step_groups(array(_test_cf_steps_break('You'), _test_cf_steps_input('a'), _test_cf_steps_break('Visit'), _test_cf_steps_input('b')));
	pg_assert_same(array('head' => array(), 'steps' => array('You:a', 'Visit:b')), _test_cf_steps_summary($g), 'two titled steps, no head');
}

function test_custom_form_steps_break_in_middle()
{
	$g = pg_cf_step_groups(array(_test_cf_steps_input('intro'), _test_cf_steps_break('One'), _test_cf_steps_input('a'),
		_test_cf_steps_input('b'), _test_cf_steps_break('Two'), _test_cf_steps_input('c')));
	pg_assert_same(array('head' => array('intro'), 'steps' => array('One:a,b', 'Two:c')), _test_cf_steps_summary($g),
		'what stands before the first break is shown above every step');
}

function test_custom_form_steps_break_at_end()
{
	$g = pg_cf_step_groups(array(_test_cf_steps_break('One'), _test_cf_steps_input('a'), _test_cf_steps_break('Two'),
		_test_cf_steps_input('b'), _test_cf_steps_break('Empty')));
	pg_assert_same(array('head' => array(), 'steps' => array('One:a', 'Two:b')), _test_cf_steps_summary($g), 'a break with nothing after it is no step');

	$g = pg_cf_step_groups(array(_test_cf_steps_break('One'), _test_cf_steps_break('Two'), _test_cf_steps_input('a')));
	pg_assert_same(array('head' => array(), 'steps' => array('Two:a')), _test_cf_steps_summary($g), 'two breaks in a row make no empty step');
}

function test_custom_form_steps_title_and_class()
{
	$g = pg_cf_step_groups(array(_test_cf_steps_break('  Your details '), _test_cf_steps_input('a')));
	pg_assert_same('Your details', $g['steps'][0]['title'], 'title trimmed');
	pg_assert_same('h5', $g['steps'][0]['class'], 'the break\'s classes go to the legend');
}

// ── Tree ───────────────────────────────────────────────────────────────

function _test_cf_steps_tree($children)
{
	return array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'col'), 'children' => $children),
	));
}

function test_custom_form_steps_tree_without_break_untouched()
{
	$tree = _test_cf_steps_tree(array(_test_cf_steps_input('a'), _test_cf_steps_input('b')));
	$copy = $tree;
	pg_assert_same(0, pg_cf_apply_steps($tree), 'no break, no steps');
	pg_assert_same($copy, $tree, 'the tree is left as it was (byte for byte render)');
}

function test_custom_form_steps_tree_grouped()
{
	$tree = _test_cf_steps_tree(array(_test_cf_steps_input('intro'), _test_cf_steps_break('One'), _test_cf_steps_input('a'),
		_test_cf_steps_break('Two & more'), _test_cf_steps_input('b')));
	pg_assert_same(2, pg_cf_apply_steps($tree), 'two steps');
	$col = $tree['children'][0]['children'];
	pg_assert_same('intro', $col[0]['props']['_attrs'][1]['value'], 'the head stays first');
	pg_assert_same('content', $col[1]['type'], 'then the progress line');
	pg_assert_true(strpos($col[1]['props']['html'], 'aria-current="step"') !== false, 'the first step is current');
	pg_assert_true(strpos($col[1]['props']['html'], 'Two &amp; more') !== false, 'titles escaped');
	pg_assert_same('fieldset', $col[2]['props']['tag'], 'a step is a fieldset');
	pg_assert_same('legend', $col[2]['children'][0]['props']['tag'], 'with the title as its legend');
	pg_assert_same('One', $col[2]['children'][0]['props']['text'], 'legend text');
	pg_assert_same('fieldset', $col[3]['props']['tag'], 'second step');
	pg_assert_same(4, count($col), 'head, progress, two steps');

	$html = _render_tree_node($tree, 0, 0);
	pg_assert_same(2, substr_count($html, 'pg-cf-step"') + substr_count($html, 'pg-cf-step '), 'two step fieldsets rendered');
	pg_assert_true(strpos($html, 'data-pg-step-next') !== false, 'a Next button');
	pg_assert_true(strpos($html, 'data-pg-step-back') !== false, 'a Back button');
	pg_assert_true(strpos($html, 'data-pg-step-count') !== false, 'the step count line');
}

function test_custom_form_steps_single_step_no_navigation()
{
	$tree = _test_cf_steps_tree(array(_test_cf_steps_break('Only'), _test_cf_steps_input('a')));
	pg_assert_same(1, pg_cf_apply_steps($tree), 'one step');
	$html = _render_tree_node($tree, 0, 0);
	pg_assert_true(strpos($html, '<legend') !== false, 'its title shows');
	pg_assert_true(strpos($html, 'data-pg-step-next') === false, 'no Next on a single step');
	pg_assert_true(strpos($html, 'data-pg-step-count') === false, 'no progress on a single step');
}

function test_custom_form_steps_nested_break_found()
{
	$tree = _test_cf_steps_tree(array(_test_cf_steps_input('a')));
	$tree['children'][0]['children'][] = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
		_test_cf_steps_break('One'), _test_cf_steps_input('b'), _test_cf_steps_break('Two'), _test_cf_steps_input('c')));
	pg_assert_same(2, pg_cf_apply_steps($tree), 'the container holding the breaks is grouped');
}

function test_custom_form_steps_break_is_no_field()
{
	$fields = _pg_cf_controls(_test_cf_steps_tree(array(_test_cf_steps_break('One'), _test_cf_steps_input('a'))), 0);
	pg_assert_same(array('a'), array_keys($fields), 'a break is no field');
}

// ── Script and wiring ──────────────────────────────────────────────────

function test_custom_form_steps_script()
{
	$js = pg_cf_steps_script();
	pg_assert_true(strpos($js, 'reportValidity') !== false, 'Next reports the visible step\'s controls');
	pg_assert_true(strpos($js, 'aria-current') !== false, 'the progress moves aria-current');
	pg_assert_true(strpos($js, 'pushState') === false && strpos($js, 'popstate') === false, 'the browser\'s back key is left alone');
	pg_assert_true(strpos($js, '.disabled') !== false, 'a disabled (hidden by a rule) control is not checked');
	pg_assert_same('', pg_cf_steps_script(), 'emitted once per page');
}

function test_custom_form_steps_render_wiring()
{
	$src = (string)file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	pg_assert_true(strpos($src, 'pg_cf_apply_steps($tree_decoded)') !== false, 'the renderer groups the steps');
	pg_assert_true(strpos($src, 'pg_cf_steps_script()') !== false, 'and adds the script to a stepped form');
	$js = (string)file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_true(strpos($js, "step:       { label: _sdT('Step')") !== false, 'the palette offers a Step break');
	pg_assert_true(strpos($js, "['form_step',") !== false, 'the Section binding list names it');
}
