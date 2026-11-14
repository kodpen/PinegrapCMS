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
	pg_assert_same(array('settings_workspace_assistants.php#pgset-ws-claude', 'api_settings.php'), array_column($all['buttons'], 'url'), 'both buttons');
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
	foreach (array('title', 'label', 'pick', 'placeholder', 'send', 'open', 'failed', 'you', 'new') as $text) {
		pg_assert_true(($box[$text] ?? '') !== '', 'text ' . $text);
	}
}

function test_dashboard_v2_ai_chat_store()
{
	$key = pg_dashboard_v2_ai_chat_store('/', 7);
	pg_assert_true((bool) preg_match('/^pg_dash_ai_chat_[0-9a-f]{16}$/', $key), 'a storage key');
	pg_assert_same($key, pg_dashboard_v2_ai_chat_store('/', '7'), 'the same site and person keep one key');
	pg_assert_true($key !== pg_dashboard_v2_ai_chat_store('/', 8), 'another person, another key');
	pg_assert_true($key !== pg_dashboard_v2_ai_chat_store('/shop/', 7), 'another site on the host, another key');

	$box = pg_dashboard_v2_ai_chat(array(array('key' => 'claude', 'name' => 'Claude', 'channel_id' => 1, 'channel' => 'general', 'prefix' => '<@app:6> ', 'max' => 2000)), $key);
	pg_assert_same($key, $box['store'], 'the box names its key');
}

function test_dashboard_v2_ai_links_read_as_a_verb()
{
	// lang('Open') is the state ("Açık"); a link says what it does.
	foreach (array('ai.php', 'queue.php', 'waiting.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/' . $file);
		pg_assert_same(0, preg_match("/lang\\('Open'\\)/", $source), $file . ' labels no link with the state word');
	}
}

/**
 * Runs the chat's pure helpers (pgDash.aiTalk) in Node and returns what the
 * script prints, or null without Node.
 */
function dashboard_v2_ai_talk_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgdashai');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, "var window = {};\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_dash_cards_assistant.js')
		. "\nvar T = window.pgDash.aiTalk;\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

function test_dashboard_v2_ai_chat_kept_talk()
{
	$result = dashboard_v2_ai_talk_node(<<<'JS'
var options = [{key: 'claude', channel_id: 3, prefix: '<@app:6> '}, {key: 'ai', channel_id: 3, prefix: '@ai '}];
var now = 1800000000000;
var talk = {key: 'ai', channel_id: 3, root: 40, ids: [40, 44], at: now - 1000, sent: now - 1000, ai: true, last: 50, until: 1, waiting: true};
var packed = T.pack(talk);
var back = T.unpack(JSON.parse(JSON.stringify(packed)), now, options);
var rows = [
  {id: 40, parent_id: 0, mine: true, raw: '@ai first'},
  {id: 41, parent_id: 0, mine: false},
  {id: 42, parent_id: 40, mine: false},
  {id: 43, parent_id: 42, mine: false, deleted: true},
  {id: 44, parent_id: 40, mine: true},
  {id: 45, parent_id: 40, mine: true},
  {id: 46, parent_id: 44, mine: false}
];
var chain = T.chain(rows, back.talk.ids);
process.stdout.write(JSON.stringify({
  keys: Object.keys(packed).sort(),
  index: back.index,
  last: back.talk.last,
  stale: T.unpack(packed, now + T.keep + 1000, options),
  gone: T.unpack(packed, now, [options[0]]),
  moved: T.unpack(packed, now, [{key: 'ai', channel_id: 9}]),
  junk: [T.unpack(null, now, options), T.unpack({root: 'x'}, now, options), T.unpack({key: 'ai', channel_id: 3, root: 40, ids: 'x', at: now}, now, options)],
  rootless: T.unpack({key: 'ai', channel_id: 3, root: 40, ids: [44], at: now}, now, options).talk.ids,
  chain: chain.messages.map(function (m) { return m.id; }),
  ids: chain.ids,
  own: [T.ownText('@ai  first\n line', '@ai '), T.ownText('plain', '@ai '), T.ownText(null, '')],
  lines: T.lines
}));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('agent', 'ai', 'at', 'channel_id', 'ids', 'key', 'root', 'sent'), $result['keys'], 'ids and times are kept, never a text or a timer');
	pg_assert_same(1, $result['index'], 'the talk goes back to its assistant');
	pg_assert_same(39, $result['last'], 'the reading starts at the root');
	pg_assert_same(null, $result['stale'], 'a day after its last line the talk is gone');
	pg_assert_same(null, $result['gone'], 'an assistant no longer askable drops the talk');
	pg_assert_same(null, $result['moved'], 'another channel drops the talk');
	pg_assert_same(array(null, null, null), $result['junk'], 'unreadable storage is no talk');
	pg_assert_same(array(40, 44), $result['rootless'], 'the root is always in the chain');
	pg_assert_same(array(40, 42, 44, 46), $result['chain'], 'the chain: own lines of it and the answers, nothing else of the channel, no deleted line');
	pg_assert_same(array(40, 44, 42, 46), $result['ids'], 'answers join the chain');
	pg_assert_same(array('first line', 'plain', ''), $result['own'], 'the tag comes off the person\'s line');
	pg_assert_same(5, $result['lines'], 'five lines after a reload');
}
