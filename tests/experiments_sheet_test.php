<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the designer's Experiments sheet in
 * includes/fn/experiments_sheet.php: the form's values cleaned, the actions
 * each status offers, the chip a status draws and when a variant draft is
 * held.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A full form passes; the values come back trimmed and typed.
function test_experiment_sheet_clean_valid()
{
	$out = pg_experiment_sheet_clean(array(
		'name' => '  Headline test  ',
		'goal_kind' => 'form',
		'goal_target' => '',
		'weight_b' => '30',
	));

	pg_assert_same(array(), $out['errors']);
	pg_assert_same('Headline test', $out['values']['name']);
	pg_assert_same('form', $out['values']['goal_kind']);
	pg_assert_same('', $out['values']['goal_target']);
	pg_assert_same(30, $out['values']['weight_b']);
}

// A missing name, an unknown goal and a weight outside 1-99 are refused.
function test_experiment_sheet_clean_errors()
{
	$out = pg_experiment_sheet_clean(array('name' => ' ', 'goal_kind' => 'bogus', 'weight_b' => '0'));
	pg_assert_same(array('name', 'goal', 'weight'), $out['errors']);

	$out = pg_experiment_sheet_clean(array('name' => 'A', 'goal_kind' => 'order', 'weight_b' => '100'));
	pg_assert_same(array('weight'), $out['errors'], 'a variant needs some visitors');

	$out = pg_experiment_sheet_clean(array('name' => 'A', 'goal_kind' => 'order', 'weight_b' => 'abc'));
	pg_assert_same(array('weight'), $out['errors'], 'not a number');

	$out = pg_experiment_sheet_clean(array('name' => str_repeat('x', 101), 'goal_kind' => 'order', 'weight_b' => 50));
	pg_assert_same(array('name'), $out['errors'], 'name over 100 characters');
}

// A click goal names its element and a page goal its page; the others ignore the target.
function test_experiment_sheet_clean_targets()
{
	$out = pg_experiment_sheet_clean(array('name' => 'A', 'goal_kind' => 'click', 'goal_target' => '', 'weight_b' => 50));
	pg_assert_same(array('target'), $out['errors'], 'click needs a goal name');

	$out = pg_experiment_sheet_clean(array('name' => 'A', 'goal_kind' => 'click', 'goal_target' => ' buy-now ', 'weight_b' => 50));
	pg_assert_same(array(), $out['errors']);
	pg_assert_same('buy-now', $out['values']['goal_target']);

	$out = pg_experiment_sheet_clean(array('name' => 'A', 'goal_kind' => 'click', 'goal_target' => 'buy now!', 'weight_b' => 50));
	pg_assert_same(array('target'), $out['errors'], 'goal names are letters, digits, - and _');

	$out = pg_experiment_sheet_clean(array('name' => 'A', 'goal_kind' => 'page', 'goal_target' => '0', 'weight_b' => 50));
	pg_assert_same(array('target'), $out['errors'], 'page goal needs a page');

	$out = pg_experiment_sheet_clean(array('name' => 'A', 'goal_kind' => 'page', 'goal_target' => '42', 'weight_b' => 50));
	pg_assert_same('42', $out['values']['goal_target']);

	$out = pg_experiment_sheet_clean(array('name' => 'A', 'goal_kind' => 'order', 'goal_target' => 'left over', 'weight_b' => 50));
	pg_assert_same('', $out['values']['goal_target'], 'order goal keeps no target');
}

// The two weights always add up to 100.
function test_experiment_sheet_weights()
{
	pg_assert_same(array('a' => 50, 'b' => 50), pg_experiment_sheet_weights(50));
	pg_assert_same(array('a' => 70, 'b' => 30), pg_experiment_sheet_weights(30));
	pg_assert_same(array('a' => 99, 'b' => 1), pg_experiment_sheet_weights(-5), 'held inside 1-99');
	pg_assert_same(array('a' => 1, 'b' => 99), pg_experiment_sheet_weights(500));
}

// Each status offers the moves the model allows, and the winner only once it ran.
function test_experiment_sheet_actions()
{
	pg_assert_same(array('start', 'delete'), pg_experiment_sheet_actions('draft'));
	pg_assert_same(array('pause', 'end', 'publish_winner'), pg_experiment_sheet_actions('running'));
	pg_assert_same(array('start', 'end', 'publish_winner'), pg_experiment_sheet_actions('paused'));
	pg_assert_same(array(), pg_experiment_sheet_actions('ended'));
	pg_assert_same(array(), pg_experiment_sheet_actions('bogus'));

	foreach (array('start' => 'running', 'pause' => 'paused', 'end' => 'ended') as $action => $to) {
		pg_assert_same($to, pg_experiment_sheet_action_status($action));
	}
	pg_assert_same('', pg_experiment_sheet_action_status('delete'));
}

// A variant draft is held until its experiment ends.
function test_experiment_sheet_variant_held()
{
	pg_assert_true(pg_experiment_variant_held('draft'), 'set up');
	pg_assert_true(pg_experiment_variant_held('running'), 'running');
	pg_assert_true(pg_experiment_variant_held('paused'), 'paused');
	pg_assert_false(pg_experiment_variant_held('ended'), 'ended');
	pg_assert_false(pg_experiment_variant_held(''), 'none');
}

// The chip's colour per status; an ended experiment draws none.
function test_experiment_sheet_chip_theme()
{
	pg_assert_same('success', pg_experiment_chip_theme('running'));
	pg_assert_same('warning', pg_experiment_chip_theme('paused'));
	pg_assert_same('secondary', pg_experiment_chip_theme('draft'));
	pg_assert_same('', pg_experiment_chip_theme('ended'));
}
