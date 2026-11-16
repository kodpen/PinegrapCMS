<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel side of a task handed to a registry agent that need no
 * database: which assistants the task drawer offers (the agents a task's
 * channel allows, from the server's own check), the holder's picture on a
 * task row and a Board card, and the JavaScript that carries a model agent's
 * run on and draws an agent's face (assets/js/workspace_task_delegate.js).
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

/**
 * A function's source without its line comments.
 *
 * @param string $function
 * @return string
 */
function agent_task_panel_source_of($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return preg_replace('~^\s*//.*$~m', '', implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1)));
}

/**
 * Runs JavaScript over the task delegate script with a minimal window and
 * returns what it writes, decoded; null without Node.
 *
 * @param string $tail
 * @return mixed
 */
function agent_task_panel_node($tail)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgagenttask');

	if ($file === false) {
		return null;
	}

	$js = "var window = {};\n" . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_task_delegate.js') . "\n" . $tail;
	file_put_contents($file, $js);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return json_decode(implode("\n", $out), true);
}

// The drawer offers an agent only where the task's channel allows it: the
// check the hand-over itself makes.
function test_workspace_agent_task_drawer_offers()
{
	$agent = array('key' => 'agent:4', 'delegate' => false);
	$model = array('key' => 'model:7', 'delegate' => false);

	pg_assert_true(ws_task_drawer_offers($agent, ''), 'an agent the task\'s channel allows');
	pg_assert_false(ws_task_drawer_offers($agent, 'not allowed here'), 'an agent the channel does not allow');
	pg_assert_true(ws_task_drawer_offers($model, ''));
	pg_assert_false(ws_task_drawer_offers($model, 'no channel'));
	pg_assert_true(ws_task_drawer_offers(array('key' => 'claude'), ''), 'Claude as before');
	pg_assert_true(ws_task_drawer_offers(array('key' => 'mcp:3'), ''), 'an MCP alias as before');
	pg_assert_false(ws_task_drawer_offers(array('key' => 'app:4', 'delegate' => false), ''), 'an application\'s proposal key is no agent');
	pg_assert_false(ws_task_drawer_offers(null, ''));
}

// One list, built by the server: the drawer reads the hand-over's own check,
// and only a person who may change an open task gets the hand-over.
function test_workspace_agent_task_drawer_wiring()
{
	$detail = agent_task_panel_source_of('ws_task_delegation_detail');

	pg_assert_contains('ws_task_drawer_offers(', $detail);
	pg_assert_contains('ws_agent_task_problem(', $detail, 'the same check ws_task_delegate() makes');
	pg_assert_contains("'can_hand'   => \$can_edit && ws_task_is_open(", $detail, 'who may hand over is unchanged');
	pg_assert_contains("'can_stop'", $detail, 'Stop from the drawer as for Claude');
}

function test_workspace_agent_task_holder_avatar_wiring()
{
	pg_assert_same('', ws_task_holder_avatar('claude'), 'Claude keeps its icon');
	pg_assert_same('', ws_task_holder_avatar('mcp:3'));
	pg_assert_contains("'delegate_avatar'", agent_task_panel_source_of('ws_task_brief'));
	pg_assert_contains("'delegate_avatar'", agent_task_panel_source_of('ws_channel_board_tasks'), 'the Board card carries the holder');
	pg_assert_contains("'delegate_name'", agent_task_panel_source_of('ws_channel_board_tasks'));

	$board = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_board_channel.js');
	pg_assert_contains('window.PGWsTaskDelegate.rowTag(meta, item, k)', $board, 'the card shows the holder as a task row does');
}

function test_workspace_agent_task_js_keys()
{
	$result = agent_task_panel_node('process.stdout.write(JSON.stringify({'
		. 'kick: ["ai", "model:7", "agent:4", "claude", "mcp", ""].map(window.PGWsTaskDelegate.kickFor),'
		. 'agent: ["agent:4", "model:7", "claude", "mcp:3", "app:4", "agent:0"].map(window.PGWsTaskDelegate.isAgentKey),'
		. 'icon: [{delegate: "agent:4"}, {delegate: "model:7"}, {delegate: "claude"}, {delegate: "ai"}].map(window.PGWsTaskDelegate.delegateIcon)'
		. '}));');

	if ($result === null) {
		return;
	}

	pg_assert_same(array('ws_ai_kick', 'ws_ai_kick', '', '', 'ws_mcp_agent_kick', ''), $result['kick'], 'a model agent\'s run is carried on by the site\'s engine; an app agent answers on its own');
	pg_assert_same(array(true, true, false, false, false, false), $result['agent']);
	pg_assert_same(array('bi-robot', 'bi-robot', 'bi-stars', 'bi-cpu'), $result['icon']);
}

// An agent's row tag draws its picture; Claude's keeps its icon.
function test_workspace_agent_task_row_tag()
{
	$helpers = 'function node(tag, cls) { return { tag: tag, className: cls || "", children: [], classList: { add: function (c) { this.owner.className += " " + c; } }, appendChild: function (c) { this.children.push(c); return c; } }; }'
		. 'function made(tag, cls) { var n = node(tag, cls); n.classList.owner = n; return n; }'
		. 'var document = { createTextNode: function (t) { return { text: t }; } };'
		. 'var h = { el: made, icon: function (name, extra) { return made("i", "bi " + name + (extra ? " " + extra : "")); }, avatar: function (who) { var a = made("span", "avatar"); a.src = who.avatar; return a; }, t: function (k, v) { return k + ":" + v; } };'
		. 'function first(task) { var box = made("div"); window.PGWsTaskDelegate.rowTag(box, task, h); var tag = box.children[0]; return tag ? tag.children[0].className + "|" + (tag.children[0].src || "") : ""; }';
	$result = agent_task_panel_node($helpers . 'process.stdout.write(JSON.stringify(['
		. 'first({delegate: "agent:4", delegate_name: "Mimar", delegate_avatar: "/a.png"}),'
		. 'first({delegate: "claude", delegate_name: "Claude", delegate_avatar: ""}),'
		. 'first({delegate: "", delegate_name: ""})'
		. ']));');

	if ($result === null) {
		return;
	}

	pg_assert_same('avatar me-1|/a.png', $result[0]);
	pg_assert_same('bi bi-stars me-1|', $result[1]);
	pg_assert_same('', $result[2], 'a task with nobody holding it has no tag');
}
