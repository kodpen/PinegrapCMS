<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the edit hold of an agent's request
 * (includes/workspace/edit_hold.php) that need no database: when a request
 * is held, what an answer meets after an edit in each status, the line
 * under a held message, and that every picker and door honours the hold.
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

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function test_workspace_edit_hold_free_where()
{
	pg_assert_same(" AND held_until <= '1000'", ws_edit_hold_free_where(1000, true));
	pg_assert_same(" AND r.held_until <= '1000'", ws_edit_hold_free_where(1000, true, 'r.'));
	pg_assert_same('', ws_edit_hold_free_where(1000, false), 'before the upgrade nothing is left out');
	pg_assert_same(120, WS_EDIT_HOLD_SECONDS, 'two minutes from the last beat');
}

function test_workspace_edit_hold_is_held()
{
	$now = 1000;

	foreach (array('queued', 'sent', 'running') as $status) {
		pg_assert_true(ws_edit_hold_is_held(array('status' => $status, 'held_until' => 1100), $now), $status . ' is held while the hold lasts');
		pg_assert_true(!ws_edit_hold_is_held(array('status' => $status, 'held_until' => 1000), $now), $status . ' is free when the two minutes are over');
		pg_assert_true(!ws_edit_hold_is_held(array('status' => $status, 'held_until' => 0), $now), $status . ' is free with no hold');
	}

	foreach (array('answered', 'failed', 'cancelled') as $status) {
		pg_assert_true(!ws_edit_hold_is_held(array('status' => $status, 'held_until' => 1100), $now), 'a closed request (' . $status . ') is never held');
	}

	pg_assert_true(!ws_edit_hold_is_held(array('status' => 'queued'), $now), 'a row read before the upgrade');
}

function test_workspace_edit_hold_answer_after_save_by_status()
{
	$now = 2000;
	$edited = array('id' => 7, 'edited_at' => 1500);

	// Claimed before the save: the run read the words of before.
	pg_assert_same('edited', ws_edit_hold_answer_refusal(array('status' => 'running', 'claimed_at' => 1400, 'held_until' => 0), $edited, $now));

	// Claimed after the save: it read the new words.
	pg_assert_same('', ws_edit_hold_answer_refusal(array('status' => 'running', 'claimed_at' => 1600, 'held_until' => 0), $edited, $now));
	pg_assert_same('', ws_edit_hold_answer_refusal(array('status' => 'running', 'claimed_at' => 1500, 'held_until' => 0), $edited, $now), 'the same second counts as read after');

	// Never claimed: the claim reads the new words.
	pg_assert_same('', ws_edit_hold_answer_refusal(array('status' => 'queued', 'claimed_at' => 0, 'held_until' => 0), $edited, $now));
	pg_assert_same('', ws_edit_hold_answer_refusal(array('status' => 'sent', 'claimed_at' => 0, 'held_until' => 0), $edited, $now));

	// An answer already given is not asked again.
	pg_assert_same('', ws_edit_hold_answer_refusal(array('status' => 'answered', 'claimed_at' => 1400, 'held_until' => 0), $edited, $now));

	// Never edited, or a wake with no message.
	pg_assert_same('', ws_edit_hold_answer_refusal(array('status' => 'running', 'claimed_at' => 1400, 'held_until' => 0), array('id' => 7, 'edited_at' => 0), $now));
	pg_assert_same('', ws_edit_hold_answer_refusal(array('status' => 'running', 'claimed_at' => 1400, 'held_until' => 0), null, $now));

	// The edit box is open: wait, whatever was saved before.
	pg_assert_same('held', ws_edit_hold_answer_refusal(array('status' => 'running', 'claimed_at' => 1400, 'held_until' => 2100), $edited, $now));
	pg_assert_same('held', ws_edit_hold_answer_refusal(array('status' => 'running', 'claimed_at' => 1900, 'held_until' => 2100), array('edited_at' => 0), $now));
}

function test_workspace_edit_hold_refusal_codes()
{
	pg_assert_same('editing', ws_edit_hold_refusal_error('held')['code'], 'not held: Claude\'s claim already says held for the other side\'s request');
	pg_assert_same('edited', ws_edit_hold_refusal_error('edited')['code']);
	pg_assert_true(ws_edit_hold_refusal_error('held')['error'] !== '');
}

function test_workspace_edit_hold_site_runs()
{
	pg_assert_true(ws_edit_hold_site_run('ai'), 'Pinegrap AI starts over on save');
	pg_assert_true(ws_edit_hold_site_run('model:3'), 'a model agent starts over on save');
	pg_assert_true(!ws_edit_hold_site_run('agent:3'), 'an application agent is refused at its answer instead');
	pg_assert_true(!ws_edit_hold_site_run('claude'));
	pg_assert_true(!ws_edit_hold_site_run('mcp'));
}

function test_workspace_edit_hold_line()
{
	$state = array('id' => 4, 'status' => 'queued', 'label' => 'Waiting for @x', 'icon' => 'bi-hourglass-split', 'session_url' => '');
	$row = array('id' => 4, 'status' => 'queued', 'agent' => 'other', 'held_until' => 1100);

	$held = ws_edit_hold_state($state, $row, 1000);
	pg_assert_same('bi-pencil', $held['icon']);
	pg_assert_true($held['held']);
	pg_assert_same('Being edited, The agent is waiting', $held['label'], 'the line says it waits');
	pg_assert_same('queued', $held['status'], 'the status stays the request\'s');

	pg_assert_same($state, ws_edit_hold_state($state, array('status' => 'queued', 'held_until' => 1000), 1000), 'no hold, the request\'s own line');
	pg_assert_same($state, ws_edit_hold_state($state, array('status' => 'answered', 'held_until' => 1100), 1000), 'an answered request keeps its line');
}

function test_workspace_edit_hold_every_picker()
{
	$dir = PG_FUNCTIONS_DIR . '/includes/workspace/';

	// Every place that hands a request to an agent leaves a held one out.
	foreach (array('ai.php', 'model_agents.php', 'mcp_agent.php', 'claude.php', 'api_claude.php', 'api_agent_requests.php', 'agent_queue.php') as $file) {
		pg_assert_true(strpos(file_get_contents($dir . $file), 'ws_edit_hold_free_sql()') !== false, $file . ' leaves held requests out');
	}

	$routes = file_get_contents($dir . 'api_agent_requests.php');
	pg_assert_true(substr_count($routes, 'ws_edit_hold_is_held($row') === 1, 'an app agent\'s claim answers 409 while held');
	pg_assert_true(strpos($routes, 'ws_edit_hold_answer_guard($row') !== false, 'an app agent\'s answer meets the edit');

	$claude = file_get_contents($dir . 'api_claude.php');
	pg_assert_true(strpos($claude, 'ws_edit_hold_is_held($row') !== false, 'Claude\'s claim answers 409 while held');
	pg_assert_true(strpos($claude, 'ws_edit_hold_answer_guard($row') !== false, 'Claude\'s answer meets the edit');

	$queue = file_get_contents($dir . 'agent_queue.php');
	pg_assert_true(strpos($queue, 'ws_edit_hold_request_held($request_id)') !== false, 'no knock for a held request');
	pg_assert_true(strpos($queue, 'ws_edit_hold_expire()') !== false, 'a hold that ran out is ended with the queue\'s work');

	pg_assert_true(strpos(file_get_contents($dir . 'messages.php'), 'ws_edit_hold_release((int) $message[\'id\'], true)') !== false, 'a save hands the requests on');
	pg_assert_true(strpos(file_get_contents($dir . 'claude.php'), 'ws_edit_hold_state($state, $row, time())') !== false, 'every agent\'s line says it waits');
}

function test_workspace_edit_hold_action_and_step()
{
	pg_assert_true(strpos(file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/actions.php'), "'ws_edit_hold'") !== false, 'the beat is a panel action');
	pg_assert_true(strpos(file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php'), "case 'ws_edit_hold':") !== false);

	$step = file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	pg_assert_true(strpos($step, "upgrade_2026_11_0_ws_edit_hold();            // 11.0.192") !== false, 'the step is called with its label');
	pg_assert_true(strpos($step, "install_add_column('ws_ai_requests', 'held_until', \"INT UNSIGNED NOT NULL DEFAULT 0\")") !== false);

	$js = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	pg_assert_true(strpos($js, "api('ws_edit_hold', { message_id: hold.id })") !== false, 'the box beats');
	pg_assert_true(strpos($js, "api('ws_edit_hold', { message_id: hold.id, release: 1 })") !== false, 'closing unsaved ends the hold');
	pg_assert_true(strpos($js, "if (!message || !this.claudeWaiting(message)) {") !== false, 'the hold starts only while an agent the message woke is still open');
	pg_assert_true(strpos(file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.min.js'), 'ws_edit_hold') !== false, 'the bundle carries it');
}
