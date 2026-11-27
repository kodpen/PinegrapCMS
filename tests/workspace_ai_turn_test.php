<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how one model turn's tool calls are made by the Workspace
 * assistants' loop (includes/workspace/ai.php): the calls that end the
 * request come after the others, and a tool that makes something and fails
 * holds the turn's answer back.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Function definitions only: nothing in this file runs a query when loaded.
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/ai.php');

function workspace_ai_turn_call($id, $name)
{
	return array('id' => $id, 'type' => 'function', 'function' => array('name' => $name, 'arguments' => '{}'), 'valid' => true, 'args' => array());
}

function workspace_ai_turn_ids($calls)
{
	return array_map(function ($call) {
		return $call['id'];
	}, $calls);
}

// An answer written before a drawing no longer ends the request first.
function test_workspace_ai_turn_order_answer_last()
{
	$turn = array(
		workspace_ai_turn_call('a1', 'answer'),
		workspace_ai_turn_call('d1', 'draw_canvas'),
		workspace_ai_turn_call('a2', 'answer'),
		workspace_ai_turn_call('d2', 'draw_canvas'),
	);

	pg_assert_same(array('d1', 'd2', 'a1', 'a2'), workspace_ai_turn_ids(ws_ai_turn_order($turn)), 'answers after the drawings, each group in order');
}

// fail and decide end the request too; among the closing calls the written
// order stands, so an answer before a fail still wins.
function test_workspace_ai_turn_order_closers_keep_their_order()
{
	$turn = array(
		workspace_ai_turn_call('a', 'answer'),
		workspace_ai_turn_call('f', 'fail'),
		workspace_ai_turn_call('r', 'read_canvas'),
		workspace_ai_turn_call('x', 'decide'),
		workspace_ai_turn_call('p', 'propose_change'),
	);

	pg_assert_same(array('r', 'p', 'a', 'f', 'x'), workspace_ai_turn_ids(ws_ai_turn_order($turn)), 'tools first, closers after in order');
}

// A turn with nothing to move, and an empty one, come back as they were.
function test_workspace_ai_turn_order_unchanged()
{
	$turn = array(workspace_ai_turn_call('d', 'draw_canvas'), workspace_ai_turn_call('a', 'answer'));

	pg_assert_same($turn, ws_ai_turn_order($turn), 'already in order');
	pg_assert_same(array(), ws_ai_turn_order(array()), 'empty');
}

// A canvas or live page tool that answered with an error holds the answer
// back, as a page tool does; a page tool's warning too.
function test_workspace_ai_turn_holds_answer()
{
	pg_assert_true(ws_ai_holds_answer('draw_canvas', array('error' => 'The SVG could not be read.')), 'draw_canvas error');
	pg_assert_true(ws_ai_holds_answer('edit_canvas', array('error' => 'The canvas changed since.')), 'edit_canvas error');
	pg_assert_true(ws_ai_holds_answer('read_canvas', array('error' => 'There is no such canvas.')), 'read_canvas error');
	pg_assert_true(ws_ai_holds_answer('make_live_page', array('error' => 'The page could not be kept.')), 'make_live_page error');
	pg_assert_true(ws_ai_holds_answer('page_set', array('error' => 'No such node.')), 'page tool error');
	pg_assert_true(ws_ai_holds_answer('page_css', array('ok' => true, 'warning' => 'Unknown class.')), 'page tool warning');

	pg_assert_false(ws_ai_holds_answer('draw_canvas', array('ok' => true, 'message_id' => 5, 'version' => 1)), 'canvas drawn');
	pg_assert_false(ws_ai_holds_answer('edit_canvas', array('ok' => true, 'warning' => 'x')), 'a canvas warning is no failure');
	pg_assert_false(ws_ai_holds_answer('search_records', array('error' => 'Records of that kind cannot be read here.')), 'a read tool outside canvas and pages');
}

// Arguments that were not valid JSON: the tool never ran.
function test_workspace_ai_turn_holds_answer_invalid_arguments()
{
	pg_assert_true(ws_ai_holds_answer('draw_canvas', null), 'drawing not made');
	pg_assert_true(ws_ai_holds_answer('page_replace', null), 'page change not made');
	pg_assert_false(ws_ai_holds_answer('read_channel', null), 'read tool');
}
