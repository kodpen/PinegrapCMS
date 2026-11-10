<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a Workspace API message's checklist (checks): one entry per
 * item, the tick given since over the written state, who ticked it, a guest
 * with no user id, and the schema that declares it. Also whom an
 * application's question rings and what counts as a question.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/interact.php');

function test_workspace_api_message_checks_ticks_over_written_state()
{
	$body = "::: Which plan?\n- [ ] Plan A\n- [x] Plan B\n- [ ] Plan C\n\n```\n- [ ] in code\n```";
	$checks = array(
		0 => array('checked' => true, 'user_id' => 4, 'name' => 'Ada', 'at' => 'now', 'updated_at' => 1762732800, 'guest' => false),
		1 => array('checked' => false, 'user_id' => 0, 'name' => 'Visitor', 'at' => 'now', 'updated_at' => 1762732860, 'guest' => true),
	);

	$out = ws_api_message_checks($body, $checks);

	pg_assert_same(3, count($out), 'code lines are not items');
	pg_assert_same(array('item' => 0, 'text' => 'Plan A', 'checked' => true, 'by' => array('user_id' => 4, 'name' => 'Ada'), 'at' => '2025-11-10T00:00:00Z'), $out[0]);
	pg_assert_same(false, $out[1]['checked'], 'cleared since it was written ticked');
	pg_assert_same(array('user_id' => null, 'name' => 'Visitor'), $out[1]['by'], 'a guest has no user id');
	pg_assert_same(array('item' => 2, 'text' => 'Plan C', 'checked' => false, 'by' => null, 'at' => null), $out[2], 'untouched');
	pg_assert_same(array(), ws_api_message_checks('no list here', array()));
}

function test_workspace_api_message_checks_schema()
{
	$schema = ws_api_message_schema();
	$objects = ws_openapi_objects();

	pg_assert_same('WorkspaceMessageCheck[]', $schema['checks'] ?? null, 'checks on WorkspaceMessage');
	pg_assert_same('ws_api_message_check_schema', $objects['WorkspaceMessageCheck'] ?? null, 'the component');
	pg_assert_same(array('item', 'text', 'checked', 'by', 'at'), array_keys(ws_api_message_check_schema()));
	pg_assert_same(array_keys(ws_api_message_check_schema()), array_keys(ws_api_message_checks("- [ ] x", array())[0]), 'presenter and schema agree');
}

function test_workspace_question_ring_pick_and_asks()
{
	pg_assert_same(array(4, 9), ws_question_ring_pick(array(4, 9, 4, 0), array(1)), 'the people it mentions');
	pg_assert_same(array(1, 2), ws_question_ring_pick(array(), array('1', 2, 2)), 'the owners when it mentions nobody');
	pg_assert_same(array(), ws_question_ring_pick(array(), array()));

	pg_assert_true(ws_checklist_asks("::: Ship?\n- [ ] Yes\n- [ ] No"));
	pg_assert_true(ws_checklist_asks("- [x] Done\n- [ ] Open"));
	pg_assert_false(ws_checklist_asks("- [x] Done\n- [X] Also done"), 'a report asks nothing');
	pg_assert_false(ws_checklist_asks("just text"));
}
