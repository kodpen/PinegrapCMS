<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's experiments in
 * includes/api/resources/experiments.php: the presenter with the figures of
 * each variant, the move a route asks for, and the scopes and events the
 * resource declares.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/experiments.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');

function _test_api_experiment_row()
{
	return array(
		'id' => '7', 'page_id' => '10', 'page_name' => 'home', 'name' => 'Headline', 'status' => 'running',
		'goal_kind' => 'click', 'goal_target' => 'signup', 'started_at' => '1700000000', 'ended_at' => '0',
		'winner_variant_id' => '0', 'created_at' => '1699990000', 'updated_at' => '1700000000',
	);
}

function _test_api_experiment_variants()
{
	return array(
		'a' => array('id' => '71', 'draft_page_id' => '0', 'variant_key' => 'a', 'weight' => '50', 'page_name' => null, 'views' => 1000, 'goals' => 100),
		'b' => array('id' => '72', 'draft_page_id' => '91', 'variant_key' => 'b', 'weight' => '50', 'page_name' => 'home-b', 'views' => 1000, 'goals' => 130),
	);
}

// The experiment with its variants, figures and result.
function test_api_experiment_present()
{
	$out = api_experiment_present(_test_api_experiment_row(), _test_api_experiment_variants());

	pg_assert_same(7, $out['id'], 'id');
	pg_assert_same('running', $out['status'], 'status');
	pg_assert_same('signup', $out['goal_target'], 'goal target');
	pg_assert_same('2023-11-14T22:13:20Z', $out['started_at'], 'ISO time');
	pg_assert_same(null, $out['ended_at'], 'never ended');
	pg_assert_same(null, $out['winner_variant_id'], 'no winner');
	pg_assert_same(2000, $out['views'], 'views summed');
	pg_assert_same(230, $out['goals'], 'goals summed');

	$a = $out['variants'][0];
	$b = $out['variants'][1];

	pg_assert_same('a', $a['key'], 'control first');
	pg_assert_true($a['control'], 'the live page is the control');
	pg_assert_same(null, $a['draft_page_id'], 'the control has no draft');
	pg_assert_same(null, $a['verdict'], 'the control has no verdict');
	pg_assert_same(91, $b['draft_page_id'], 'variant draft');
	pg_assert_same('home-b', $b['page_name'], 'variant page');
	pg_assert_same(0.13, $b['rate'], 'rate');
	pg_assert_same(0.3, $b['lift'], 'lift rounded');
	pg_assert_true($b['significant'], 'significant');
	pg_assert_same('ahead', $b['verdict'], 'verdict');
	pg_assert_same(0, $b['needed_views'], 'nothing more needed');
}

// An empty goal target is null; a winner is named.
function test_api_experiment_present_empty_target_and_winner()
{
	$row = _test_api_experiment_row();
	$row['goal_target'] = '';
	$row['status'] = 'ended';
	$row['winner_variant_id'] = '72';
	$row['ended_at'] = '1700100000';

	$out = api_experiment_present($row, array());

	pg_assert_same(null, $out['goal_target'], 'empty target');
	pg_assert_same(72, $out['winner_variant_id'], 'winner');
	pg_assert_same(array(), $out['variants'], 'no variants');
	pg_assert_same(0, $out['views'], 'no views');
}

// Which status each move route asks for.
function test_api_experiment_move_targets()
{
	pg_assert_same('running', api_experiment_move_target('start'), 'start');
	pg_assert_same('paused', api_experiment_move_target('pause'), 'pause');
	pg_assert_same('ended', api_experiment_move_target('end'), 'end');
	pg_assert_same('', api_experiment_move_target('publish'), 'unknown');
}

// The scopes: a group, a designer's ceiling; the events in the catalogue.
function test_api_experiment_scopes_and_events()
{
	$groups = api_scope_groups();
	pg_assert_same('experiments:read', $groups['experiments']['read'], 'read scope');
	pg_assert_same('experiments:write', $groups['experiments']['write'], 'write scope');

	$designer = api_owner_scopes(array('id' => 7, 'username' => 'd', 'role' => 1, 'manage_ecommerce' => false, 'manage_contacts' => false, 'manage_forms' => false, 'manage_emails' => 'no', 'manage_calendars' => false, 'manage_visitors' => 'no'));
	$manager = api_owner_scopes(array('id' => 8, 'username' => 'm', 'role' => 2, 'manage_ecommerce' => false, 'manage_contacts' => false, 'manage_forms' => false, 'manage_emails' => 'no', 'manage_calendars' => false, 'manage_visitors' => 'no'));
	pg_assert_true(in_array('experiments:write', $designer, true), 'a designer may delegate');
	pg_assert_false(in_array('experiments:read', $manager, true), 'a manager may not');

	$events = api_webhook_events();
	pg_assert_true(isset($events['experiment.started']), 'started');
	pg_assert_true(isset($events['experiment.ended']), 'ended');

	$ids = array();
	foreach (api_schema() as $route) {
		$ids[] = $route['id'];
	}
	foreach (array('experiments.list', 'experiments.get', 'experiments.start', 'experiments.pause', 'experiments.end') as $id) {
		pg_assert_true(in_array($id, $ids, true), 'route ' . $id);
	}
}

// A first start and an end are announced; a pause or a resume is not.
function test_experiment_move_event()
{
	pg_assert_same('experiment.started', pg_experiment_move_event('draft', 'running'), 'first start');
	pg_assert_same('', pg_experiment_move_event('paused', 'running'), 'a resume');
	pg_assert_same('', pg_experiment_move_event('running', 'paused'), 'a pause');
	pg_assert_same('experiment.ended', pg_experiment_move_event('running', 'ended'), 'an end');
	pg_assert_same('experiment.ended', pg_experiment_move_event('paused', 'ended'), 'an end from a pause');
}
