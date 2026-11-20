<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the agent channel switch (includes/workspace/agent_channel.php)
 * that need no database: the decisions filter, the command's words, the
 * channel's API shape and the schema step.
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

function test_workspace_agent_channel_mode_is_cleaned()
{
	pg_assert_same('', ws_agent_channel_mode(''), 'empty leaves them out');
	pg_assert_same('', ws_agent_channel_mode('exclude'), 'an unknown word leaves them out');
	pg_assert_same('include', ws_agent_channel_mode(' Include '), 'include, any case');
	pg_assert_same('only', ws_agent_channel_mode('only'), 'only');
	pg_assert_same('', ws_agent_channel_mode(null), 'null');
}

// Agent channels are left out by default, taken with the others on include,
// taken alone on only; a channel the request names is kept whatever it is.
function test_workspace_agent_channel_filter()
{
	$channels = array(
		3 => array('id' => 3, 'agent_channel' => 0),
		5 => array('id' => 5, 'agent_channel' => 1),
		7 => array('id' => 7, 'agent_channel' => '1'),
		9 => array('id' => 9),
	);

	pg_assert_same(array(3, 9), array_keys(ws_agent_channel_filter($channels, '')), 'left out by default');
	pg_assert_same(array(3, 5, 7, 9), array_keys(ws_agent_channel_filter($channels, 'include')), 'include');
	pg_assert_same(array(5, 7), array_keys(ws_agent_channel_filter($channels, 'only')), 'only');
	pg_assert_same(array(3, 5, 9), array_keys(ws_agent_channel_filter($channels, '', 5)), 'a named agent channel is kept');
	pg_assert_same(array(3, 5, 7), array_keys(ws_agent_channel_filter($channels, 'only', 3)), 'a named team channel is kept under only');
	pg_assert_same(array(), ws_agent_channel_filter(array(), 'only'), 'nothing in, nothing out');
}

function test_workspace_agent_channel_parse()
{
	pg_assert_same('show', ws_agent_channel_parse(''), 'bare');
	pg_assert_same('on', ws_agent_channel_parse('ON'), 'on');
	pg_assert_same('on', ws_agent_channel_parse('aç'), 'aç');
	pg_assert_same('off', ws_agent_channel_parse(' off '), 'off');
	pg_assert_same('off', ws_agent_channel_parse('kapalı'), 'kapalı');
	pg_assert_same(null, ws_agent_channel_parse('maybe'), 'anything else');
}

function test_workspace_agent_channel_command_names()
{
	pg_assert_same(array('command' => 'agent_channel', 'rest' => 'on'), ws_command_split('/agent-channel on'), '/agent-channel');
	pg_assert_same(array('command' => 'agent_channel', 'rest' => ''), ws_command_split('/Ajan-Kanalı'), '/Ajan-Kanalı');
}

function test_workspace_agent_channel_on_the_api_shape()
{
	pg_assert_same('boolean', ws_api_channel_schema()['agent_channel'] ?? null, 'schema');
	pg_assert_true(ws_channel_brief(array('id' => 1, 'name' => 'x', 'kind' => 'public', 'topic' => '', 'contact_id' => 0, 'department_id' => 0, 'archived_at' => 0, 'last_message_at' => 0, 'agent_channel' => 1))['agent_channel'], 'the brief carries it');

	$api = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
	pg_assert_true(strpos($api, "array('name' => 'agent_channels', 'in' => 'query', 'type' => 'enum', 'values' => array('include', 'only')") !== false, 'GET /workspace/decisions takes agent_channels');
	pg_assert_same('only', ws_timeline_filters(array('agent_channels' => 'only'))['agent_channels'], 'the timeline filter carries it');
	pg_assert_same('', ws_timeline_filters(array())['agent_channels'], 'left out by default');
}

// The step adds the column with the idempotent helper and is called with its
// label in the open version.
function test_workspace_agent_channel_schema_step()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_true(strpos($source, "upgrade_2026_11_0_ws_agent_channel();        // 11.0.180") !== false, 'called with its label');
	pg_assert_true(strpos($source, "install_add_column('ws_channels', 'agent_channel', \"TINYINT(1) NOT NULL DEFAULT 0\")") !== false, 'the column');
}

// The Decisions tab's scope: agents reads every agent channel, anything else
// the channel alone.
function test_workspace_agent_channel_scope()
{
	pg_assert_same('agents', ws_agent_channel_scope(' Agents '));
	pg_assert_same('channel', ws_agent_channel_scope(''));
	pg_assert_same('channel', ws_agent_channel_scope('all'));
	pg_assert_same('channel', ws_agent_channel_scope(null));
}

// An agent channel asking for every agent channel reads the readable ones
// and itself; a team channel, or the channel's own scope, reads itself alone.
function test_workspace_agent_channel_decision_ids()
{
	$readable = array(5 => array('id' => 5, 'name' => 'dev', 'agent_channel' => 1), 7 => array('id' => 7, 'name' => 'old dev', 'agent_channel' => 1));
	$agent = array('id' => 5, 'agent_channel' => 1);

	pg_assert_same(array(5, 7), ws_agent_channel_decision_ids($agent, $readable, 'agents'), 'every readable agent channel');
	pg_assert_same(array(5), ws_agent_channel_decision_ids($agent, $readable, 'channel'), 'this channel alone');
	pg_assert_same(array(3), ws_agent_channel_decision_ids(array('id' => 3, 'agent_channel' => 0), $readable, 'agents'), 'a team channel never reads them');
	pg_assert_same(array(9, 5, 7), ws_agent_channel_decision_ids(array('id' => 9, 'agent_channel' => 1), $readable, 'agents'), 'the channel itself is kept first when missing');
	pg_assert_same(array(9), ws_agent_channel_decision_ids(array('id' => 9, 'agent_channel' => 1), array(), 'agents'), 'nothing else readable');
}

// A decision from another agent channel carries that channel's name; the
// channel's own and an unreadable one carry none.
function test_workspace_agent_channel_decision_names()
{
	$readable = array(7 => array('id' => 7, 'name' => 'old dev'));
	$named = ws_agent_channel_decision_names(array(array('id' => 1, 'channel_id' => 5), array('id' => 2, 'channel_id' => 7), array('id' => 3, 'channel_id' => 8)), 5, $readable);

	pg_assert_same(array('', 'old dev', ''), array_column($named, 'channel_name'));
	pg_assert_same(array(), ws_agent_channel_decision_names(array(), 5, $readable));
}

// The command, Channel settings and the New channel form switch it through
// one function: a manager only, never a discussion, a system line naming who.
function test_workspace_agent_channel_set_is_shared()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/agent_channel.php');
	$command = substr($source, (int) strpos($source, 'function ws_command_agent_channel('));
	$set = substr($source, (int) strpos($source, 'function ws_agent_channel_set('), 2400);

	pg_assert_contains('ws_agent_channel_set($viewer, $channel, $action === \'on\')', $command, 'the command switches through it');
	pg_assert_true(strpos($command, 'UPDATE ws_channels') === false, 'the command writes nothing itself');
	pg_assert_contains('ws_can_manage_channel($viewer, $channel)', $set, 'a manager only');
	pg_assert_contains("=== 'thread'", $set, 'never a discussion');
	pg_assert_contains('ws_message_system(', $set, 'a system line');
}

// An agent channel's brief and Decisions tab read the readable agent
// channels' decisions, answered agent questions included.
function test_workspace_agent_channel_decisions_read_across()
{
	pg_assert_same("p.channel_id IN (5,7)", ws_agent_question_channels_sql(array(5, '7', 5)));
	pg_assert_same('0 = 1', ws_agent_question_channels_sql(array()));

	$memory = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/channel_memory.php');
	$brief = substr($memory, (int) strpos($memory, 'function ws_memory_brief('), 3000);
	pg_assert_contains("ws_agent_channel_decision_ids(\$channel, \$readable, 'agents')", $brief);
	pg_assert_contains('ws_agent_question_decisions($viewer, $channel, WS_MEMORY_BRIEF_DECISIONS, $ids)', $brief);

	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	pg_assert_contains("ws_agent_channel_scope(\$request['scope'] ?? '')", $actions);
	pg_assert_contains('ws_channel_decisions($viewer, $channel[\'id\'], $ids)', $actions);
}
