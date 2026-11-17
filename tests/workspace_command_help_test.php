<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the command gate that knows the channel, /help in groups,
 * /help <command>, the closest names for an unknown one, the palette's
 * group and place, and the commands that act on the message they answer
 * (includes/workspace/commands.php, command_registry.php, palette.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/palette.php');

function workspace_command_help_test_channel($kind)
{
	return array('id' => 7, 'kind' => $kind, 'name' => 'general');
}

// A channel-only command is not offered in a discussion, and the server
// refuses it there all the same; elsewhere nothing changes.
function test_workspace_command_gate_knows_the_channel()
{
	$thread = workspace_command_help_test_channel('thread');
	$public = workspace_command_help_test_channel('public');

	pg_assert_same('channel', ws_command_registry()['agent_channel']['where'], '/agent-channel runs in the channel itself');
	pg_assert_true(!ws_command_offered(null, 'agent_channel', $thread), 'not offered in a discussion');
	pg_assert_true(ws_command_offered(null, 'agent_channel', $public), 'offered in a channel');
	pg_assert_true(ws_command_offered(null, 'agent_channel'), 'offered without a channel');
	pg_assert_true(ws_command_offered(null, 'task', $thread), '/task runs in a discussion');

	pg_assert_true(!in_array('agent_channel', array_column(ws_commands(null, false, $thread), 'key'), true), 'left out of a discussion\'s list');
	pg_assert_true(in_array('agent_channel', array_column(ws_commands(null, false, $public), 'key'), true), 'listed in a channel');
	pg_assert_true(strpos(ws_command_run(null, $thread, '/help')['html'], '/agent-channel') === false, 'left out of a discussion\'s /help');

	$refused = ws_command_run(null, $thread, '/agent-channel on');

	pg_assert_same('error', $refused['type'], 'refused when run in a discussion');
	pg_assert_same(lang('This command runs in the channel itself, not in one of its discussions.'), $refused['error']);
}

// /help lists the commands under their group headers, in the groups' order.
function test_workspace_command_help_in_groups()
{
	$answer = ws_command_run(null, workspace_command_help_test_channel('public'), '/help');

	pg_assert_same('ephemeral', $answer['type'], 'to the writer alone');

	$html = $answer['html'];
	$at = -1;

	foreach (ws_command_group_labels() as $group => $label) {
		$found = strpos($html, '>' . h($label) . '<');
		pg_assert_true($found !== false, $group . ' has its header');
		pg_assert_true($found > $at, $group . ' in order');
		$at = (int) $found;
	}

	pg_assert_true(strpos($html, '>' . h(lang('Tasks')) . '<') < strpos($html, '/task · /görev'), '/task under Tasks');
	pg_assert_true(strpos($html, '/price · /fiyat') > strpos($html, '>' . h(lang('Records')) . '<'), '/price under Records');

	$help = ws_command_help(null);

	pg_assert_same('tasks', $help[0]['group'], 'the help entries carry their group');
	pg_assert_same(lang('Tasks'), $help[0]['group_label']);
	pg_assert_same('any', $help[0]['where']);
}

// /help <command> shows that command alone: its names, usage and example.
function test_workspace_command_help_one_command()
{
	$channel = workspace_command_help_test_channel('public');

	foreach (array('/help task', '/yardım görev', '/help /gorev', '/YARDIM Görev') as $body) {
		$answer = ws_command_run(null, $channel, $body);

		pg_assert_same('ephemeral', $answer['type'], $body);
		pg_assert_contains('/task · /görev', $answer['html'], $body . ': the command');
		pg_assert_contains('/gorev', $answer['html'], $body . ': every name');
		pg_assert_contains(h(lang('/task Logo revision @ayse friday ~2h !urgent')), $answer['html'], $body . ': the example');
		pg_assert_true(strpos($answer['html'], '/assign') === false, $body . ': that command alone');
	}

	// Each argument stays whole on a line: a narrow screen wraps between
	// arguments, never inside their brackets.
	$task = ws_command_run(null, $channel, '/help task')['html'];
	$entry = ws_command_help(null)[0];

	pg_assert_contains('<span class="text-nowrap">' . h('[' . lang('!priority') . ']') . '</span>', $task, 'an argument kept whole');
	pg_assert_same(substr_count(ws_command_usage_html($entry), 'text-nowrap'), count($entry['args']), 'one span an argument');
	pg_assert_same(html_entity_decode(strip_tags(ws_command_usage_html($entry)), ENT_QUOTES, 'UTF-8'), $entry['usage'], 'the same line as the usage');

	$unknown = ws_command_run(null, $channel, '/help gorevx');

	pg_assert_same('ephemeral', $unknown['type'], 'an unknown name');
	pg_assert_contains(h(lang(array('string' => 'There is no {var:1} command.', 'vars' => '/gorevx'))), $unknown['html'], 'says so');
	pg_assert_contains('<code>/görev</code>', $unknown['html'], 'with the closest names');
	pg_assert_same(array('/görev'), array_slice($unknown['closest'], 0, 1));

	$thread = ws_command_run(null, workspace_command_help_test_channel('thread'), '/help agent-channel');

	pg_assert_contains(h(lang(array('string' => 'There is no {var:1} command.', 'vars' => '/agent-channel'))), $thread['html'], 'a command that does not run here is not shown');
}

// The palette's command rows carry their group and where they run.
function test_workspace_command_palette_carries_the_group()
{
	$rows = ws_palette_commands('', null);

	pg_assert_true(count($rows) > 0, 'commands listed');
	pg_assert_same('tasks', $rows[0]['group'], 'the group');
	pg_assert_same(lang('Tasks'), $rows[0]['group_label'], 'its header');
	pg_assert_same('any', $rows[0]['where'], 'where it runs');

	$agent = array_values(array_filter($rows, function ($row) {
		return $row['title'] === '/agent-channel';
	}));

	pg_assert_same('channel', $agent[0]['where'] ?? '', '/agent-channel runs in the channel itself');
}

// Typed as a reply without a text, /task, /decision and /note act on the
// answered message; with a text, or without a reply, they do as before.
function test_workspace_command_reply_reaches_the_runner()
{
	pg_assert_true(ws_command_acts_on_reply('', array('parent_id' => 5)), 'a reply without a text');
	pg_assert_true(!ws_command_acts_on_reply('Kraft it is', array('parent_id' => 5)), 'a reply with a text');
	pg_assert_true(!ws_command_acts_on_reply('', array('parent_id' => 0)), 'no reply');
	pg_assert_true(!ws_command_acts_on_reply('', array()), 'no options');

	$channel = workspace_command_help_test_channel('public');

	pg_assert_same(lang('Write the text after the command.'), ws_command_run(null, $channel, '/decision')['error'], '/decision without a reply');
	pg_assert_same(lang('Write the text after the command.'), ws_command_run(null, $channel, '/not')['error'], '/note without a reply');

	// The answered message is read only for a reply without a text.
	foreach (array('ws_command_run_noted', 'ws_command_task') as $function) {
		$reflection = new ReflectionFunction($function);
		$source = implode('', array_slice(file($reflection->getFileName()), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));

		pg_assert_contains('ws_command_acts_on_reply(', $source, $function . ' asks first');
		pg_assert_contains('ws_command_answered_message(', $source, $function . ' reads the answered message');
	}

	pg_assert_contains('ws_message_mark(', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php'), '/decision and /note mark it as the menu does');
}
