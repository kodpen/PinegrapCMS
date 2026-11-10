<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new dashboard's Assistant card
 * (includes/dashboard/v2/cards/ai.php): chip texts and order, where a
 * proposal opens, a change's name, the card when Claude is not connected
 * and the chat box's assistants, channels and texts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_DASHBOARD_V2')) {
	define('PG_DASHBOARD_V2', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/ai.php');

function test_dashboard_v2_ai_cut()
{
	pg_assert_same('Short text', pg_dashboard_v2_ai_cut("  Short \n text "), 'spaces folded');
	pg_assert_same('Write an answer to…', pg_dashboard_v2_ai_cut('Write an answer to the barista', 20), 'cut on a word');
	pg_assert_same('Abcdefghi…', pg_dashboard_v2_ai_cut('Abcdefghijklmnop', 10), 'one long word is cut where it ends');
	pg_assert_same(10, mb_strlen(pg_dashboard_v2_ai_cut('Çğıöşüçğıöşüçğıö', 10)), 'multibyte length');
}

function test_dashboard_v2_ai_proposal_url()
{
	pg_assert_same('workspace.php?channel=4&message=91', pg_dashboard_v2_ai_proposal_url(4, 91, 0), 'a channel: its message');
	pg_assert_same('workspace_tasks.php?task=12', pg_dashboard_v2_ai_proposal_url(0, 0, 12), 'a task: the task');
	pg_assert_same('', pg_dashboard_v2_ai_proposal_url(0, 5, 0), 'neither');
}

function test_dashboard_v2_ai_change_title()
{
	$types = array('page' => array('label' => 'Page'));

	pg_assert_same('H1 for 75 pages', pg_dashboard_v2_ai_change_title(array('record_type' => 'page', 'action' => 'update', 'reason' => ' H1 for 75 pages '), $types), 'the reason');
	pg_assert_same('Update Page', pg_dashboard_v2_ai_change_title(array('record_type' => 'page', 'action' => 'update', 'reason' => ''), $types), 'update');
	pg_assert_same('New Page', pg_dashboard_v2_ai_change_title(array('record_type' => 'page', 'action' => 'create'), $types), 'create');
	pg_assert_same('Delete widget', pg_dashboard_v2_ai_change_title(array('record_type' => 'widget', 'action' => 'delete'), $types), 'an unknown kind keeps its key');
}

function test_dashboard_v2_ai_chips()
{
	$task = pg_dashboard_v2_ai_chip('task', 'Answer the barista', 'workspace.php?channel=1&message=2', 100);
	pg_assert_same('task', $task['kind'], 'kind');
	pg_assert_same('Task: Answer the barista', $task['text'], 'task text');

	$change = pg_dashboard_v2_ai_chip('anything', 'H1', '', 300);
	pg_assert_same('change', $change['kind'], 'any other kind is a change');
	pg_assert_same('Change: H1', $change['text'], 'change text');

	$ordered = pg_dashboard_v2_ai_chips_order(array($task, $change, pg_dashboard_v2_ai_chip('task', 'B', '', 200)), 2);
	pg_assert_same(array(300, 200), array_column($ordered, 'time'), 'newest first, cut to the limit');
}

function test_dashboard_v2_ai_offline()
{
	$all = pg_dashboard_v2_ai_offline(true, true);
	pg_assert_same(false, $all['connected'], 'not connected');
	pg_assert_same(array('workspace_settings.php#ws-claude', 'api_settings.php'), array_column($all['buttons'], 'url'), 'both buttons');
	pg_assert_same('', $all['ask'], 'no line for somebody who connects it');

	$manager = pg_dashboard_v2_ai_offline(false, true);
	pg_assert_same(array('api_settings.php'), array_column($manager['buttons'], 'url'), 'Connections only');

	$member = pg_dashboard_v2_ai_offline(false, false);
	pg_assert_same(array(), $member['buttons'], 'no buttons');
	pg_assert_true($member['ask'] !== '', 'who connects it');
	pg_assert_true($member['note'] !== '', 'the MCP note for everyone');
}

function test_dashboard_v2_ai_summary_lines()
{
	pg_assert_same(array('3 orders wait to be shipped', 'Task #12 is late', 'Two comments wait'), pg_dashboard_v2_ai_summary_lines("- 3 orders wait to be shipped\n\n2) Task #12 is late\r\n• Two comments wait\nA fourth line"), 'list markers dropped, empty lines left out, three kept');
	pg_assert_same(array('One line only'), pg_dashboard_v2_ai_summary_lines('One line only'));
	pg_assert_same(array(), pg_dashboard_v2_ai_summary_lines("  \n "), 'nothing to show');
	$long = pg_dashboard_v2_ai_summary_lines(str_repeat('word ', 60))[0];
	pg_assert_true(mb_strlen($long) <= 140, 'a long line is cut');
	pg_assert_same('…', mb_substr($long, -1), 'and says so');
}

function test_dashboard_v2_ai_chat_options()
{
	$assistants = array(
		'ai'      => array('key' => 'ai', 'name' => 'Pinegrap AI', 'handle' => '@ai', 'avatar' => 'ai.svg'),
		'claude'  => array('key' => 'claude', 'name' => 'Claude', 'handle' => '@Claude', 'avatar' => 'claude.svg'),
		'agent:2' => array('key' => 'agent:2', 'name' => 'Architect', 'handle' => '@mimar', 'avatar' => 'robot.svg'),
		'mcp:5'   => array('key' => 'mcp:5', 'name' => '@notes', 'handle' => '@notes', 'avatar' => 'notes.svg'),
	);
	$channels = array(array('id' => 7, 'name' => 'design'), array('id' => 3, 'name' => 'general'), array('id' => 9, 'name' => 'agents'));
	$askable = array(
		'ai'      => array('tag' => '<@app:4>', 'channels' => array(3, 9)),
		'claude'  => array('tag' => '<@app:6>', 'channels' => array(9, 7)),
		// Allowed nowhere the person may write: left out.
		'agent:2' => array('tag' => '<@app:13>', 'channels' => array(25)),
		// No tag: it cannot be asked by a message.
		'mcp:5'   => array('tag' => '', 'channels' => array(3)),
		// Not among the assistants (switched off): left out.
		'model:8' => array('tag' => '<@app:20>', 'channels' => array(3)),
	);

	$options = pg_dashboard_v2_ai_chat_options($assistants, $channels, $askable, 4000);
	pg_assert_same(array('ai', 'claude'), array_column($options, 'key'), 'only assistants that can be asked where the person may write, in the list order');
	pg_assert_same(3, $options[0]['channel_id'], 'the first channel in the order where it may be asked');
	pg_assert_same('general', $options[0]['channel'], 'its name');
	pg_assert_same(7, $options[1]['channel_id'], 'the channel order decides, not the assistant\'s list');
	pg_assert_same('<@app:6> ', $options[1]['prefix'], 'the tag and a space start the message');
	pg_assert_same('Claude', $options[1]['name'], 'the name it is shown with');
	pg_assert_true(strpos($options[1]['note'], '#design') !== false, 'the note names its channel');
	pg_assert_true(strpos($options[1]['sent'], '#design') !== false, 'so does the line after sending');

	pg_assert_same(array(), pg_dashboard_v2_ai_chat_options($assistants, array(), $askable, 4000), 'no channel to write in: no assistant');
}

function test_dashboard_v2_ai_chat_max()
{
	pg_assert_same(PG_DASHBOARD_V2_AI_JOB_MAX, pg_dashboard_v2_ai_chat_max('<@app:6> ', 4000), 'the box\'s own limit when the message has room');
	pg_assert_same(91, pg_dashboard_v2_ai_chat_max('<@app:6> ', 100), 'the message limit less the tag');
	pg_assert_same(0, pg_dashboard_v2_ai_chat_max(str_repeat('x', 120), 100), 'never below nothing');

	$options = pg_dashboard_v2_ai_chat_options(
		array('claude' => array('key' => 'claude', 'name' => 'Claude')),
		array(array('id' => 1, 'name' => 'general')),
		array('claude' => array('tag' => '<@app:6>', 'channels' => array(1))),
		100
	);
	pg_assert_same(91, $options[0]['max'], 'each option carries its own limit');
}

function test_dashboard_v2_ai_chat_box()
{
	$none = pg_dashboard_v2_ai_chat(array());
	pg_assert_same(array(), $none['options'], 'nobody to ask');
	pg_assert_true($none['none'] !== '', 'the line saying why');
	pg_assert_true(!isset($none['send']), 'no box');

	$box = pg_dashboard_v2_ai_chat(array(array('key' => 'claude', 'name' => 'Claude', 'channel_id' => 1, 'channel' => 'general', 'prefix' => '<@app:6> ', 'max' => 2000)));
	pg_assert_same('', $box['none'], 'a box');
	pg_assert_same(1, count($box['options']), 'its option');
	foreach (array('title', 'label', 'pick', 'placeholder', 'send', 'open', 'failed', 'you') as $text) {
		pg_assert_true(($box[$text] ?? '') !== '', 'text ' . $text);
	}
}
