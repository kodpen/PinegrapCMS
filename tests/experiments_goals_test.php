<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the goals of page experiments in includes/fn/experiments.php:
 * the once-a-day mark kept in the pg_exp cookie, which event each goal kind
 * counts, the count lines an event gives and the click goal's script.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// An experiment row as pg_experiment_running_for_goals() gives it.
function _test_experiment_goal_row($id, $kind, $target = '', $page_id = 10)
{
	return array(
		'id' => $id,
		'page_id' => $page_id,
		'goal_kind' => $kind,
		'goal_target' => $target,
		'variants' => array(
			'a' => array('id' => $id * 10 + 1, 'draft_page_id' => 0, 'variant_key' => 'a', 'weight' => 50),
			'b' => array('id' => $id * 10 + 2, 'draft_page_id' => 90 + $id, 'variant_key' => 'b', 'weight' => 50),
		),
	);
}

// A day is counted from 1970-01-01, whatever the server's time zone.
function test_experiment_day_number()
{
	pg_assert_same(0, pg_experiment_day_number('1970-01-01'), 'epoch');
	pg_assert_same(20760, pg_experiment_day_number('2026-11-03'), 'a day');
	pg_assert_same(20761, pg_experiment_day_number('2026-11-04'), 'the next');
	pg_assert_same(0, pg_experiment_day_number('bogus'), 'not a date');
}

// The goal mark rides on the pair as a third part and survives a round trip.
function test_experiment_goal_seen_round_trip()
{
	$value = pg_experiment_bucket_format(array(7 => 'b', 12 => 'a'), array(7 => 20760, 99 => 20760));

	pg_assert_same('7:b:20760.12:a', $value, 'the mark of a kept pair only');
	pg_assert_same(array(7 => 'b', 12 => 'a'), pg_experiment_bucket_parse($value), 'buckets read as before');
	pg_assert_same(array(7 => 20760), pg_experiment_goal_seen_parse($value), 'marks read back');

	pg_assert_same(array(), pg_experiment_goal_seen_parse('3:a:x.4:b:1234567.5:c:'), 'bad marks dropped');
	pg_assert_same(array(3 => 'a'), pg_experiment_bucket_parse('3:a:x'), 'a bad mark keeps its bucket out');
	pg_assert_same(array(), pg_experiment_bucket_parse('3:a:1:2'), 'four parts are no pair');
	pg_assert_same('3:a', pg_experiment_bucket_format(array(3 => 'a'), array(3 => 'x')), 'a bad mark is not written');
}

// A goal counts once a day per experiment.
function test_experiment_goal_once_a_day()
{
	pg_assert_true(pg_experiment_goal_due(array(), 5, 20760), 'never counted');
	pg_assert_false(pg_experiment_goal_due(array(5 => 20760), 5, 20760), 'counted today');
	pg_assert_true(pg_experiment_goal_due(array(5 => 20759), 5, 20760), 'counted yesterday');
	pg_assert_true(pg_experiment_goal_due(array(6 => 20760), 5, 20760), 'another experiment counted');
}

// Which event each goal kind counts.
function test_experiment_goal_matches()
{
	$form = _test_experiment_goal_row(1, 'form');
	pg_assert_true(pg_experiment_goal_matches($form, 'form', array('page_id' => 10)), 'a form on the live page');
	pg_assert_true(pg_experiment_goal_matches($form, 'form', array('page_id' => 91)), 'a form on the variant');
	pg_assert_false(pg_experiment_goal_matches($form, 'form', array('page_id' => 11)), 'a form elsewhere');
	pg_assert_false(pg_experiment_goal_matches($form, 'order', array()), 'another kind');

	$form_on = _test_experiment_goal_row(1, 'form', '44');
	pg_assert_true(pg_experiment_goal_matches($form_on, 'form', array('page_id' => 44)), 'a form on the named page');
	pg_assert_false(pg_experiment_goal_matches($form_on, 'form', array('page_id' => 10)), 'not the named page');

	pg_assert_true(pg_experiment_goal_matches(_test_experiment_goal_row(2, 'order'), 'order', array()), 'any order');

	$click = _test_experiment_goal_row(3, 'click', 'signup');
	pg_assert_true(pg_experiment_goal_matches($click, 'click', array('experiment_id' => 3, 'name' => 'signup')), 'the named element');
	pg_assert_false(pg_experiment_goal_matches($click, 'click', array('experiment_id' => 3, 'name' => 'other')), 'another element');
	pg_assert_false(pg_experiment_goal_matches($click, 'click', array('experiment_id' => 4, 'name' => 'signup')), 'another experiment\'s page');
	pg_assert_true(pg_experiment_goal_matches(_test_experiment_goal_row(3, 'click'), 'click', array('experiment_id' => 3, 'name' => 'x')), 'no name: any marked element');

	$page = _test_experiment_goal_row(5, 'page', '77');
	pg_assert_true(pg_experiment_goal_matches($page, 'page', array('page_id' => 77)), 'the goal page');
	pg_assert_false(pg_experiment_goal_matches($page, 'page', array('page_id' => 10)), 'the experiment page itself');
	pg_assert_false(pg_experiment_goal_matches(_test_experiment_goal_row(5, 'page'), 'page', array('page_id' => 77)), 'no page named');
}

// An event gives one count line per experiment whose bucket the visitor holds,
// for the variant of that bucket, and marks the day.
function test_experiment_goal_lines()
{
	$experiments = array(_test_experiment_goal_row(1, 'order'), _test_experiment_goal_row(2, 'order'), _test_experiment_goal_row(3, 'order'));
	$buckets = array(1 => 'b', 2 => 'a', 3 => 'z');

	$plan = pg_experiment_goal_lines($experiments, $buckets, array(2 => 20760), 'order', array(), 20760);

	pg_assert_same(array(array(1, 12)), $plan['lines'], 'one line: 2 counted today, 3 holds an unknown key');
	pg_assert_same(array(1 => 20760, 2 => 20760), $plan['seen'], 'the day marked');

	$again = pg_experiment_goal_lines($experiments, $buckets, $plan['seen'], 'order', array(), 20760);
	pg_assert_same(array(), $again['lines'], 'the same day counts nothing');

	$next = pg_experiment_goal_lines($experiments, $buckets, $plan['seen'], 'order', array(), 20761);
	pg_assert_same(array(array(1, 12), array(2, 21)), $next['lines'], 'the next day counts again');

	$none = pg_experiment_goal_lines($experiments, array(), array(), 'order', array(), 20760);
	pg_assert_same(array(), $none['lines'], 'no bucket, no goal');

	$page = pg_experiment_goal_lines(array(_test_experiment_goal_row(4, 'page', '77')), array(4 => 'a'), array(), 'page', array('page_id' => 77), 20760);
	pg_assert_same(array(array(4, 41)), $page['lines'], 'a page goal');
}

// The click goal's script posts the experiment and the element's goal name.
function test_experiment_click_script()
{
	$html = '<html><body><p>x</p></body></html>';
	$out = pg_experiment_click_inject($html, 7, '/pinegrap/page_view.php');

	pg_assert_true(strpos($out, 'data-pg-goal') !== false, 'listens to marked elements');
	pg_assert_true(strpos($out, '"xg",7') !== false || strpos($out, '"xg","7"') !== false, 'names the experiment');
	pg_assert_true(strpos($out, '\/pinegrap\/page_view.php') !== false || strpos($out, '/pinegrap/page_view.php') !== false, 'posts to the beacon');
	pg_assert_true(strrpos($out, '</script></body>') !== false, 'before the closing body');
	pg_assert_same($html, pg_experiment_click_inject($html, 0, '/x'), 'no experiment, no script');
	pg_assert_same('no body', pg_experiment_click_inject('no body', 7, '/x'), 'no body, no script');
}
