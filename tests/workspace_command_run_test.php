<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for POST /workspace/commands/run (includes/workspace/api_commands.php):
 * the route's declaration, text that is no command, the dry run that shows a
 * preview and runs nothing that writes, the composer's options handed to
 * ws_command_run(), and the /claude and /ai bodies read first.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_MCP_ENTRY')) {
	define('PG_MCP_ENTRY', true);
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bulk.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bulk_sources.php');

// A function's own source, comments taken out, so the order of its calls
// can be checked.
function ws_command_run_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());
	$source = implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));

	return preg_replace('~^\s*//.*$~m', '', $source);
}

// Each needle after the one before it.
function ws_command_run_test_order($source, $needles, $label)
{
	$at = -1;

	foreach ($needles as $needle) {
		$found = strpos($source, $needle);

		pg_assert_true(($found !== false) && ($found > $at), $label . ': ' . $needle . ' comes after what is before it');
		$at = ($found === false) ? $at : $found;
	}
}

// A runner that remembers how it was called instead of running anything.
function ws_command_run_test_recorder(&$calls, $answer)
{
	return function ($viewer, $channel, $body, $options) use (&$calls, $answer) {
		$calls[] = array('body' => $body, 'options' => $options);

		return $answer;
	};
}

function test_ws_command_run_route_is_declared()
{
	$found = null;

	foreach (ws_api_routes() as $route) {
		if ($route['id'] === 'workspace.commands.run') {
			$found = $route;
		}
	}

	pg_assert_true(is_array($found), 'the route is declared');
	pg_assert_same('POST', $found['method'], 'a POST');
	pg_assert_same('/workspace/commands/run', $found['path'], 'its path');
	pg_assert_same('workspace:write', $found['scope'], 'workspace:write, no new scope');
	pg_assert_same(false, $found['mcp'], 'no MCP tool: models call the typed endpoints');
	pg_assert_false(pg_mcp_route_is_offered($found), 'not offered to MCP');
	pg_assert_true(!empty($found['dry_run']), 'it rehearses');
	pg_assert_true(function_exists($found['handler']), 'its handler exists');
	pg_assert_true(strpos($found['description'], 'what the web composer accepts') !== false, 'the contract is said to be the composer\'s');

	$names = array_map(function ($param) {
		return $param['name'];
	}, $found['params']);

	pg_assert_same(array('channel_id', 'text', 'confirm', 'force', 'assignees', 'from', 'decision', 'on_sale', 'parent_id'), $names, 'the composer\'s options');
}

// What the composer would post as a message is no command here.
function test_ws_command_run_refuses_text_that_is_no_command()
{
	$options = ws_api_command_options(array());

	foreach (array('Hello team', '//task not a command', '/var/www/html', 'a /task in the middle', '') as $text) {
		pg_assert_same('none', ws_api_command_kind($text, $options), $text);
	}

	pg_assert_same('run', ws_api_command_kind('/tsak Buy milk', $options), 'a mistyped command gets its hint');

	$source = ws_command_run_test_source('ws_api_commands_run');

	ws_command_run_test_order($source, array("api_fail(400, 'not_a_command'", 'api_dry_run_requested()', 'ws_command_run('), 'refused before anything runs');
	pg_assert_same(2, substr_count($source, "'not_a_command'"), 'a null run is refused too');
}

function test_ws_command_run_kind_tells_previews_from_writes()
{
	$options = ws_api_command_options(array());
	$answer = ws_api_command_options(array('parent_id' => 7));

	$cases = array(
		'/task Buy milk tomorrow'        => 'preview',
		'/görev Süt al'                  => 'preview',
		'/price <#product:4> 149,90'     => 'preview',
		'/stock <#product:4> +5'         => 'preview',
		'/product Blue mug 149,90'       => 'preview',
		'/create product Blue mug 149,90' => 'preview',
		'/create group Mugs'             => 'preview',
		'/poll Lunch?'                   => 'preview',
		'/poll Lunch? | Pizza'           => 'preview',
		'/poll Lunch? | Pizza | Soup'    => 'run',
		"/product Blue mug 10\nRed mug 12" => 'run',
		"/create product\nBlue mug 10\nRed mug 12" => 'run',
		'/done 42'                       => 'run',
		'/assign 42 <@user:3>'           => 'run',
		'/decision We ship on Friday'    => 'run',
		'/help'                          => 'run',
	);

	foreach ($cases as $text => $kind) {
		pg_assert_same($kind, ws_api_command_kind($text, $options), $text);
	}

	pg_assert_same('run', ws_api_command_kind('/product', $answer), 'an answer to a file makes a bulk card at once');
	pg_assert_same('run', ws_api_command_kind('/create product', $answer), 'so does /create product');
	pg_assert_same('preview', ws_api_command_kind('/product Blue mug', $answer), 'a named product in an answer is shown back');
}

// A dry run runs only what writes nothing, and runs it unconfirmed.
function test_ws_command_run_dry_run_shows_the_preview_and_writes_nothing()
{
	$preview = array('type' => 'preview', 'preview' => array('title' => 'Buy milk'));
	$calls = array();
	$options = ws_api_command_options(array('confirm' => true, 'force' => true, 'assignees' => array('5')));

	$got = ws_api_command_preview(array('id' => 1), array('id' => 2), '/task Buy milk', $options, ws_command_run_test_recorder($calls, $preview));

	pg_assert_same($preview, $got, 'the preview comes back');
	pg_assert_same(1, count($calls), 'run once');
	pg_assert_same(false, $calls[0]['options']['confirm'], 'never confirmed');
	pg_assert_same(false, $calls[0]['options']['force'], 'never forced');
	pg_assert_same(array(5), $calls[0]['options']['assignees'], 'the other options kept');

	foreach (array('/done 42', '/decision We ship', "/product A 10\nB 12", '/poll Q | a | b', '/help') as $text) {
		$calls = array();

		pg_assert_same(null, ws_api_command_preview(array('id' => 1), array('id' => 2), $text, $options, ws_command_run_test_recorder($calls, $preview)), $text . ': no preview');
		pg_assert_same(0, count($calls), $text . ': not run');
	}

	// An answer that is no preview is not passed on as one.
	$calls = array();

	pg_assert_same(null, ws_api_command_preview(array('id' => 1), array('id' => 2), '/task Buy milk', $options, ws_command_run_test_recorder($calls, array('type' => 'done'))), 'done is no preview');

	$error = array('type' => 'error', 'error' => 'Write what the task is after the command.');

	pg_assert_same($error, ws_api_command_preview(array('id' => 1), array('id' => 2), '/task', $options, ws_command_run_test_recorder($calls, $error)), 'a refusal is shown');

	// The rehearsal stops before the real run.
	ws_command_run_test_order(ws_command_run_test_source('ws_api_commands_run'), array('ws_api_own_words_only(', 'ws_api_channel_or_404(', 'ws_can_post_channel(', 'ws_api_command_preview(', 'api_dry_run_stop(', 'ws_command_run('), 'the rehearsal');
}

// The options reach ws_command_run() as the composer's ws_send passes them.
function test_ws_command_run_passes_the_composer_options()
{
	pg_assert_same(array(
		'confirm'   => false,
		'force'     => false,
		'assignees' => null,
		'from'      => null,
		'decision'  => false,
		'on_sale'   => false,
		'parent_id' => 0,
	), ws_api_command_options(array()), 'none sent');

	pg_assert_same(array(
		'confirm'   => true,
		'force'     => true,
		'assignees' => array(3, 4),
		'from'      => '14990',
		'decision'  => true,
		'on_sale'   => true,
		'parent_id' => 9,
	), ws_api_command_options(array('confirm' => true, 'force' => true, 'assignees' => array('3', 4), 'from' => '14990', 'decision' => true, 'on_sale' => true, 'parent_id' => 9)), 'all sent');

	$source = ws_command_run_test_source('ws_api_commands_run');

	pg_assert_true(strpos($source, 'ws_command_run($viewer, $channel, $body, $options)') !== false, 'the run gets the options');
	pg_assert_true(strpos($source, 'ws_api_as_owner(') < strpos($source, 'ws_command_run('), 'its log lines carry the owner\'s name');
}

// /claude and /ai become the assistant's tag before the text is read.
function test_ws_command_run_reads_the_assistant_bodies_first()
{
	ws_command_run_test_order(ws_command_run_test_source('ws_api_command_text'), array('ws_claude_command_body(', 'ws_ai_command_body('), 'as the composer');
	ws_command_run_test_order(ws_command_run_test_source('ws_api_commands_run'), array('ws_api_command_text(', 'ws_api_command_kind(', 'ws_command_run('), 'before the command is read');

	if (!function_exists('ws_claude_command_body') && !function_exists('ws_ai_command_body')) {
		pg_assert_same('/task Buy milk', ws_api_command_text('/task Buy milk'), 'left as typed without the assistants');
	}
}
