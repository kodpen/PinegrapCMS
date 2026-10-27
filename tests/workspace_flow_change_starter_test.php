<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the change box of a flow and the person who started its run
 * (includes/workspace/steps.php): a change goes through only when the
 * starter may make it too, a run the owner started is unchanged, and a
 * starter who is gone or out of the team refuses the change.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');

// People by id: 5 may change contacts, 6 is in the team without that right,
// 7 has left the team, and nobody else exists.
function ws_flow_change_starter_test_people()
{
	$people = array(
		5 => array('id' => 5, 'member' => true, 'contacts' => true),
		6 => array('id' => 6, 'member' => true, 'contacts' => false),
		7 => array('id' => 7, 'member' => false, 'contacts' => true),
	);

	return function ($id) use ($people) {
		return $people[(int) $id] ?? null;
	};
}

// A check that lets through whoever may change contacts.
function ws_flow_change_starter_test_check()
{
	return function ($viewer, $kind, $config) {
		return empty($viewer['contacts'])
			? array('ok' => false, 'error' => 'no right', 'action' => null)
			: array('ok' => true, 'error' => '', 'action' => array('type' => 'change'));
	};
}

function ws_flow_change_starter_test_refusal($started_by, $kind = 'change')
{
	$run = array('id' => 1, 'started_by' => $started_by, 'actor_id' => 2);
	$config = array('record' => 'contact', 'id' => 9, 'fields' => array('notes' => 'VIP'));

	return ws_step_starter_refusal($run, $kind, $config, ws_flow_change_starter_test_people(), ws_flow_change_starter_test_check());
}

function test_workspace_flow_change_starter_rights()
{
	$sentence = lang('The person who started the run may not make this change.');

	pg_assert_same('', ws_flow_change_starter_test_refusal(2), 'a run the owner started keeps the owner\'s rights alone');
	pg_assert_same('', ws_flow_change_starter_test_refusal(5), 'a colleague with the right');
	pg_assert_same($sentence, ws_flow_change_starter_test_refusal(6), 'a colleague without the right');
	pg_assert_same($sentence, ws_flow_change_starter_test_refusal(7), 'a starter out of the team');
	pg_assert_same($sentence, ws_flow_change_starter_test_refusal(8), 'a starter who no longer exists');
	pg_assert_same($sentence, ws_flow_change_starter_test_refusal(0), 'a run without a starter');
	pg_assert_same('', ws_flow_change_starter_test_refusal(6, 'post'), 'other boxes keep the owner\'s rights alone');
}

// The box asks it after the owner's check and before anything is done.
function test_workspace_flow_change_starter_checked_as_the_box_runs()
{
	$function = new ReflectionFunction('ws_step_run');
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	$owner = strpos($source, 'ws_step_check($owner');
	$starter = strpos($source, 'ws_step_starter_refusal($run');
	$done = strpos($source, 'ws_scheduled_do(');

	pg_assert_true($owner !== false && $starter !== false && $done !== false);
	pg_assert_true($owner < $starter && $starter < $done);
}
