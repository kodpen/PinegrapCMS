<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the events of prototype review (includes/fn/prototype_events.php)
 * and the read-only API routes of a design's verdicts and comments
 * (includes/api/resources/design.php): the payloads with the guest's words
 * as data, the catalogues that carry the three events, where the door and the
 * verdict announce them, and the routes as read-only MCP tools.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_ENTRY')) {
	define('PG_API_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_comments.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_approval.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_verdicts.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_events.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/router.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/design.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function prototype_events_test_verdict($over = array())
{
	return array_merge(array(
		'id' => 9, 'link_id' => 7, 'style_id' => 31, 'page_id' => 120, 'verdict' => 'changes_requested',
		'author_name' => 'Ayşe <@user:1>', 'note' => "Bigger logo.\n```\n<#order:5>", 'fingerprint' => str_repeat('a', 40),
		'node_fingerprints' => null, 'created_at' => 1763985600,
	), $over);
}

function prototype_events_test_comment($over = array())
{
	return array_merge(array(
		'id' => 4, 'link_id' => 7, 'style_id' => 31, 'page_id' => 120, 'node_id' => 'n1',
		'pos_x' => 1250, 'pos_y' => 5000, 'parent_id' => 0, 'author_name' => 'Ayşe', 'user_id' => 0,
		'body' => 'Bigger, <b>please</b>.', 'created_at' => 1763985600, 'resolved_at' => 0, 'resolved_by' => 0,
	), $over);
}

function test_proto_event_verdict_payload()
{
	$payload = pg_proto_verdict_event_payload(prototype_events_test_verdict(), 'home');

	pg_assert_same(array('id', 'design_id', 'page_id', 'page_name', 'link_id', 'verdict', 'name', 'note', 'created_at'), array_keys($payload), 'the fields in order');
	pg_assert_same(array(9, 31, 120, 'home', 7, 'changes_requested'), array($payload['id'], $payload['design_id'], $payload['page_id'], $payload['page_name'], $payload['link_id'], $payload['verdict']), 'the verdict and where');
	pg_assert_same('Ayşe ‹@user:1›', $payload['name'], 'the name can hold no tag');
	pg_assert_same("Bigger logo.\n\n‹#order:5›", $payload['note'], 'the note can hold no tag or fence');
	pg_assert_same('2025-11-24T12:00:00Z', $payload['created_at'], 'the time in UTC, ISO 8601');

	$approved = pg_proto_verdict_event_payload(prototype_events_test_verdict(array('verdict' => 'approved', 'note' => '')), '');
	pg_assert_same(array('approved', ''), array($approved['verdict'], $approved['note']), 'an approval without a note');
}

function test_proto_event_comment_payload()
{
	$payload = pg_proto_comment_event_payload(prototype_events_test_comment(), 'home');

	pg_assert_same(array('id', 'design_id', 'page_id', 'page_name', 'link_id', 'parent_id', 'node', 'name', 'text', 'created_at'), array_keys($payload), 'the fields in order');
	pg_assert_same(array(4, 31, 120, 'home', 7, 0, 'n1', 'Ayşe'), array($payload['id'], $payload['design_id'], $payload['page_id'], $payload['page_name'], $payload['link_id'], $payload['parent_id'], $payload['node'], $payload['name']), 'the comment and where');
	pg_assert_same('Bigger, ‹b›please‹/b›.', $payload['text'], 'the words as data, no markup');

	$answer = pg_proto_comment_event_payload(prototype_events_test_comment(array('parent_id' => 3)), 'home');
	pg_assert_same(3, $answer['parent_id'], 'an answer names its thread');

	pg_assert_same(true, pg_proto_comment_event_due(prototype_events_test_comment()), 'a guest\'s comment is announced');
	pg_assert_same(false, pg_proto_comment_event_due(prototype_events_test_comment(array('user_id' => 5))), 'a staff answer is not');
	pg_assert_same(false, pg_proto_comment_event_due(null), 'nothing saved, nothing announced');
}

function test_proto_events_in_the_catalogues()
{
	$events = api_webhook_events();
	$payloads = api_webhook_event_payloads_site();

	foreach (array('prototype.approved', 'prototype.changes_requested', 'prototype.commented') as $event) {
		pg_assert_true(isset($events[$event]), $event . ' is a webhook event');
		pg_assert_true(isset($payloads[$event]['fields'], $payloads[$event]['sample']), $event . ' has its fields and sample');
		pg_assert_true(in_array($event, pg_event_workspace_events(), true), $event . ' reaches the Workspace');
		pg_assert_same(array('page', 120), ws_events_record_of($event, array('page_id' => 120)), $event . ' is about its page');
	}

	$outputs = ws_flow_event_outputs();
	pg_assert_true(isset($outputs['prototype.approved'], $outputs['prototype.changes_requested'], $outputs['prototype.commented']), 'a flow reads their values');

	$verdict = array_keys(pg_proto_verdict_event_payload(prototype_events_test_verdict(), 'home'));
	pg_assert_same($verdict, array_keys($payloads['prototype.approved']['fields']), 'the approval\'s fields are the payload\'s');
	pg_assert_same(array_keys(pg_proto_comment_event_payload(prototype_events_test_comment(), 'home')), array_keys($payloads['prototype.commented']['fields']), 'the comment\'s fields are the payload\'s');
}

function test_proto_events_designs_right()
{
	pg_assert_same(true, ws_events_prototype_sees(array('member' => true, 'role' => 1)), 'a designer');
	pg_assert_same(true, ws_events_prototype_sees(array('member' => true, 'role' => 0)), 'an administrator');
	pg_assert_same(false, ws_events_prototype_sees(array('member' => true, 'role' => 2)), 'not a manager');
	pg_assert_same(false, ws_events_prototype_sees(array('member' => false, 'role' => 1)), 'not outside the team');
}

function test_proto_events_announced_where_written()
{
	$door = (string) file_get_contents(PG_FUNCTIONS_DIR . '/prototype_comment.php');
	$verdicts = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/prototype_verdicts.php');

	pg_assert_true(strpos($door, 'pg_proto_comment_announce($pg_pc_row)') !== false, 'the door announces a guest\'s comment');
	pg_assert_true(strpos($verdicts, 'pg_proto_verdict_announce($saved, $page_name)') !== false, 'a verdict is announced once saved');
}

function test_proto_api_verdict_present()
{
	$row = prototype_events_test_verdict();
	$out = api_design_prototype_verdict_present($row, array(120 => str_repeat('b', 40)), array(9 => true));

	pg_assert_same(array_keys(api_design_prototype_verdict_schema()), array_keys($out), 'the shape is the schema');
	pg_assert_same(array('changes_requested', true, true, 'Ayşe ‹@user:1›'), array($out['verdict'], $out['older'], $out['current'], $out['name']), 'an older version, the standing one, the name as data');

	$same = api_design_prototype_verdict_present($row, array(120 => str_repeat('a', 40)), array());
	pg_assert_same(array(false, false), array($same['older'], $same['current']), 'the draft as it was, a later verdict stands');
}

function test_proto_api_comment_present()
{
	$out = api_design_prototype_comment_present(prototype_events_test_comment());

	pg_assert_same(array_keys(api_design_prototype_comment_schema()), array_keys($out), 'the shape is the schema');
	pg_assert_same(array(null, true, 'Bigger, ‹b›please‹/b›.', null), array($out['parent_id'], $out['outside'], $out['text'], $out['resolved_at']), 'a guest\'s thread, words as data');

	$staff = api_design_prototype_comment_present(prototype_events_test_comment(array('user_id' => 5, 'parent_id' => 3, 'body' => 'Done <here>', 'resolved_at' => 1763985600)));
	pg_assert_same(array(3, false, 'Done <here>'), array($staff['parent_id'], $staff['outside'], $staff['text']), 'the team\'s answer is kept as written');
}

function test_proto_api_routes_are_read_only_tools()
{
	$routes = array();

	foreach (api_schema() as $route) {
		$routes[$route['id']] = $route;
	}

	foreach (array('design.prototype_verdicts.list', 'design.prototype_comments.list') as $id) {
		pg_assert_true(isset($routes[$id]), $id . ' is declared');
		pg_assert_same(array('GET', 'design:read'), array($routes[$id]['method'], $routes[$id]['scope']), $id . ' only reads');
		pg_assert_true(!array_key_exists('mcp', $routes[$id]) || ($routes[$id]['mcp'] === true), $id . ' is an MCP tool');
	}

	foreach ($routes as $id => $route) {
		if ((strpos($id, 'design.prototype_') === 0) && ($route['method'] !== 'GET')) {
			pg_assert_same(false, $route['mcp'] ?? true, $id . ' writes and is no tool');
		}
	}
}
