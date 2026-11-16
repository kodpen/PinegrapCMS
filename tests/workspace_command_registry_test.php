<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the command registry (includes/workspace/command_registry.php):
 * every entry's gate and API class name functions that exist, the commands keep today's order
 * and names, and an application's command named as a built-in one is
 * shadowed: kept, never offered, listed by the API as shadowed.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/app_commands.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/agent_channel.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

// Every entry's gate and API class name functions that are there, and every
// field is filled.
function test_workspace_command_registry_every_function_exists()
{
	$registry = ws_command_registry();

	pg_assert_true(count($registry) > 0, 'the registry holds commands');

	foreach ($registry as $key => $entry) {
		pg_assert_same($key, $entry['key'], $key . ' is listed under its key');

		foreach (array('key', 'tr', 'en', 'label', 'description', 'example', 'args', 'names', 'group', 'where', 'gate', 'live', 'api', 'order') as $field) {
			pg_assert_true(array_key_exists($field, $entry), $key . ' has ' . $field);
		}

		pg_assert_true(in_array($entry['group'], ws_command_groups(), true), $key . ' is in a known group');
		pg_assert_true(($entry['gate'] === '') || function_exists($entry['gate']), $key . ' gate: ' . $entry['gate']);
		pg_assert_true(is_bool($entry['live']), $key . ' live line is said');
		pg_assert_true(in_array($entry['api'], array('preview', 'run'), true) || function_exists($entry['api']), $key . ' API class: ' . $entry['api']);
	}

	// Every name the parser answers to is a key's.
	foreach (ws_command_table() as $name => $key) {
		pg_assert_true(isset($registry[$key]), '/' . $name . ' has an entry');
	}
}

// The order, the keys and the names are today's.
function test_workspace_command_registry_keeps_the_order_and_names()
{
	pg_assert_same(array('task', 'assign', 'done', 'postpone', 'status', 'timer', 'schedule', 'away', 'meeting', 'decision', 'note', 'plan', 'poll', 'price', 'stock', 'create', 'product', 'wake', 'agent_channel', 'claude', 'ai', 'help'),
		array_keys(ws_command_registry()), 'the registry in picker order');

	pg_assert_same(array('task', 'assign', 'done', 'postpone', 'status', 'timer', 'schedule', 'away', 'meeting', 'decision', 'note', 'plan', 'poll', 'price', 'stock', 'create', 'product', 'wake', 'agent_channel', 'help'),
		array_column(ws_commands(null), 'key'), 'without a person the assistants are left out');

	pg_assert_same(array(
		'gorev' => 'task', 'görev' => 'task', 'task' => 'task', 'g' => 'task', 't' => 'task',
		'ata' => 'assign', 'assign' => 'assign',
		'bitti' => 'done', 'tamam' => 'done', 'done' => 'done',
		'ertele' => 'postpone', 'postpone' => 'postpone',
		'durum' => 'status', 'status' => 'status',
		'süre' => 'timer', 'sure' => 'timer', 'timer' => 'timer',
		'zamanla' => 'schedule', 'schedule' => 'schedule',
		'izin' => 'away', 'away' => 'away',
		'toplantı' => 'meeting', 'toplanti' => 'meeting', 'meeting' => 'meeting',
		'karar' => 'decision', 'decision' => 'decision',
		'not' => 'note', 'note' => 'note',
		'plan' => 'plan',
		'oylama' => 'poll', 'anket' => 'poll', 'sor' => 'poll', 'poll' => 'poll',
		'fiyat' => 'price', 'price' => 'price',
		'stok' => 'stock', 'stock' => 'stock',
		'uyandır' => 'wake', 'uyandir' => 'wake', 'wake' => 'wake',
		'ajan-kanalı' => 'agent_channel', 'ajan-kanali' => 'agent_channel', 'agent-channel' => 'agent_channel',
		'yardim' => 'help', 'yardım' => 'help', 'help' => 'help',
		'oluştur' => 'create', 'olustur' => 'create', 'create' => 'create',
		'urun' => 'product', 'ürün' => 'product', 'product' => 'product',
	), ws_command_table(), 'every name, in the parser\'s order');

	$live = array();

	foreach (ws_commands(null) as $entry) {
		if ($entry['live']) {
			$live[] = $entry['key'];
		}
	}

	pg_assert_same(array('task', 'status', 'timer', 'schedule', 'away', 'meeting'), $live, 'the commands with a live line');

	$classes = array();

	foreach (array('/task x', '/price #p 5', '/stock #p 5', '/poll Q | a', '/poll Q | a | b', '/create product Mug', "/product A\nB", '/done T-1', '/help', '/note x') as $body) {
		$classes[$body] = ws_api_command_kind($body, array());
	}

	pg_assert_same(array('/task x' => 'preview', '/price #p 5' => 'preview', '/stock #p 5' => 'preview', '/poll Q | a' => 'preview', '/poll Q | a | b' => 'run',
		'/create product Mug' => 'preview', "/product A\nB" => 'run', '/done T-1' => 'run', '/help' => 'run', '/note x' => 'run'), $classes, 'the API classes');
}

// A built-in name wins over an application's: the row stays, is never
// offered and the API lists it as shadowed.
function test_workspace_command_registry_app_command_named_as_a_built_in_is_shadowed()
{
	pg_assert_true(ws_command_shadowed('görev'), 'a built-in name');
	pg_assert_true(ws_command_shadowed('GOREV'), 'folded');
	pg_assert_true(ws_command_shadowed('claude'), 'an assistant');
	pg_assert_true(!ws_command_shadowed('deploy'), 'a name of its own');

	$rows = array(
		array('app_id' => 4, 'name' => 'deploy', 'description' => 'Deploys', 'usage_text' => '', 'app_name' => 'CI'),
		array('app_id' => 4, 'name' => 'görev', 'description' => 'Old task', 'usage_text' => '', 'app_name' => 'CI'),
	);

	$offered = ws_command_app_entries($rows, false);

	pg_assert_same(array('app:deploy'), array_column($offered, 'key'), 'the shadowed row is not offered');
	pg_assert_same(false, $offered[0]['shadowed']);

	$listed = ws_command_app_entries($rows, true);

	pg_assert_same(array('app:deploy', 'app:görev'), array_column($listed, 'key'), 'the API lists both');
	pg_assert_same(array(false, true), array_column($listed, 'shadowed'), 'and marks the one a built-in took');
	pg_assert_same(true, ws_api_command_present($listed[1])['shadowed'], 'the API answer carries it');
	pg_assert_same('boolean', ws_api_command_schema()['shadowed'] ?? null, 'and its schema declares it');
	pg_assert_same('name_taken', ws_app_command_check(array('name' => 'Görev', 'description' => 'x'))['error'] ?? '', 'a built-in name is still refused at registration');
}
