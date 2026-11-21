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

/**
 * The card's parts as the server gathers them, for the state builders.
 */
function dashboard_v2_ai_parts($overrides = array())
{
	return array_merge(array(
		'chips'        => array(),
		'questions'    => array(),
		'chat'         => pg_dashboard_v2_ai_chat(array()),
		'agent_active' => false,
		'summary'      => null,
		'job_channel'  => array('id' => 3, 'name' => 'general'),
		'tag'          => '<@app:6>',
	), $overrides);
}

function dashboard_v2_ai_one_option()
{
	return pg_dashboard_v2_ai_chat(array(array('key' => 'agent:2', 'name' => 'Architect', 'channel_id' => 9, 'channel' => 'agents', 'prefix' => '<@app:13> ', 'max' => 2000)), 'pg_dash_ai_chat_x');
}

/**
 * Every filled (main) button in a card's data.
 */
function dashboard_v2_ai_main_buttons($data)
{
	$main = 0;

	foreach ((array) ($data['buttons'] ?? array()) as $button) {
		$main += !empty($button['main']) ? 1 : 0;
	}

	foreach (array('notice', 'agent') as $part) {
		if (!empty($data[$part]['button']['main'])) {
			$main++;
		}
	}

	return $main;
}

function test_dashboard_v2_ai_agent_active()
{
	pg_assert_same(false, pg_dashboard_v2_ai_agent_active(array()), 'no agents');
	pg_assert_same(false, pg_dashboard_v2_ai_agent_active(array(
		array('kind' => 'claude', 'status' => 'active'),
		array('kind' => 'app', 'status' => 'paused'),
	)), 'Claude\'s row and a paused application are no active agent');
	pg_assert_same(true, pg_dashboard_v2_ai_agent_active(array(array('kind' => 'app', 'status' => 'active'))), 'an active application agent');
}

function test_dashboard_v2_ai_off_with_an_assistant()
{
	$data = pg_dashboard_v2_ai_off_data(true, true, dashboard_v2_ai_parts(array('chat' => dashboard_v2_ai_one_option(), 'agent_active' => true)));

	pg_assert_same(false, $data['connected'], 'not connected');
	pg_assert_same('Claude · not connected', $data['chip']['text'], 'the head chip names Claude');
	pg_assert_true(!isset($data['title']), 'no pitch title');
	pg_assert_same('Claude is not connected', $data['notice']['title'], 'one notice');
	pg_assert_same(1, count($data['chat']['options']), 'the chat is drawn');
	pg_assert_true(!array_key_exists('job', $data) && !array_key_exists('no_job', $data), 'no job box');
	pg_assert_true(!array_key_exists('summary', $data), 'no summary');
	pg_assert_true(!array_key_exists('agent', $data), 'an active agent: no agent note');
}

function test_dashboard_v2_ai_off_with_nothing()
{
	$pitch = pg_dashboard_v2_ai_offline(true, true);
	$data = pg_dashboard_v2_ai_off_data(true, true, dashboard_v2_ai_parts());

	foreach (array('title', 'text', 'ask', 'buttons', 'note') as $key) {
		pg_assert_same($pitch[$key], $data[$key], 'the pitch as today: ' . $key);
	}
	pg_assert_true(!isset($data['notice']), 'no notice beside the pitch');
	pg_assert_true(!isset($data['chat']), 'no chat without an option');
	pg_assert_same('Workspace agents', $data['agent']['title'], 'then the agent note');

	$agent = pg_dashboard_v2_ai_off_data(true, true, dashboard_v2_ai_parts(array('agent_active' => true)));
	pg_assert_true(!isset($agent['agent']), 'no note while an agent is active');
	pg_assert_same($pitch['title'], $agent['title'], 'the pitch still');
}

function test_dashboard_v2_ai_off_with_chips()
{
	$question = pg_dashboard_v2_ai_question_chips(array(array('question' => 'Which one?', 'asker' => array('name' => 'Architect'), 'url' => 'agent_question.php?id=1', 'created_at' => 5)));
	$change = array(pg_dashboard_v2_ai_chip('change', 'H1', 'workspace.php?channel=1&message=2', 9));

	$asked = pg_dashboard_v2_ai_off_data(false, false, dashboard_v2_ai_parts(array('questions' => $question)));
	pg_assert_same('Claude is not connected', $asked['notice']['title'], 'a question: the notice');
	pg_assert_same(1, count($asked['questions']['chips']), 'the question under it');
	pg_assert_true(!isset($asked['title']), 'no pitch');
	pg_assert_true(!isset($asked['chat']), 'no chat without an option');

	$proposed = pg_dashboard_v2_ai_off_data(false, false, dashboard_v2_ai_parts(array('chips' => $change)));
	pg_assert_same(1, count($proposed['proposals']['chips']), 'a proposal under the notice');
	pg_assert_same('', $proposed['proposals']['none'], 'no "nothing waiting" line while Claude is off');
}

function test_dashboard_v2_ai_on_as_today()
{
	$data = pg_dashboard_v2_ai_on_data(dashboard_v2_ai_parts(array('agent_active' => true, 'chat' => dashboard_v2_ai_one_option())));

	pg_assert_same(array('connected', 'chip', 'proposals', 'questions', 'summary', 'job', 'no_job', 'chat'), array_keys($data), 'the data as today');
	pg_assert_same(true, $data['connected'], 'connected');
	pg_assert_same('Claude · connected', $data['chip']['text'], 'the chip');
	pg_assert_true($data['proposals']['none'] !== '', 'nothing waiting is said while Claude is ready');
	pg_assert_same(3, $data['job']['channel_id'], 'the job box');
	pg_assert_same('<@app:6>', $data['job']['tag'], 'Claude\'s tag');

	$none = pg_dashboard_v2_ai_on_data(dashboard_v2_ai_parts(array('job_channel' => null)));
	pg_assert_same(null, $none['job'], 'no channel: no box');
	pg_assert_true($none['no_job'] !== '', 'the line saying why');
	pg_assert_same('Workspace agents', $none['agent']['title'], 'no agent: the note, Claude ready too');
}

function test_dashboard_v2_ai_agent_note()
{
	$settings = pg_dashboard_v2_ai_agent_note(true);
	pg_assert_same(2, count($settings['lines']), 'two lines');
	pg_assert_same('Connect an agent', $settings['button']['label'], 'one button');
	pg_assert_true(strpos($settings['button']['url'], 'ws-agents') !== false, 'at the Workspace agents row');
	pg_assert_same('', $settings['ask'], 'not the line beside it');

	$member = pg_dashboard_v2_ai_agent_note(false);
	pg_assert_same(null, $member['button'], 'no button');
	pg_assert_same('An administrator connects agents under Site Settings › Assistants and agents.', $member['ask'], 'the administrator line');
}

function test_dashboard_v2_ai_notice()
{
	$settings = pg_dashboard_v2_ai_notice(true);
	pg_assert_same('Connect Claude', $settings['button']['label'], 'Connect Claude for who may open the settings');
	pg_assert_same('', $settings['ask'], 'no line beside it');

	$member = pg_dashboard_v2_ai_notice(false);
	pg_assert_same(null, $member['button'], 'no button');
	pg_assert_true($member['ask'] !== '', 'the administrator line');
}

function test_dashboard_v2_ai_one_filled_button()
{
	foreach (array(true, false) as $settings) {
		foreach (array(true, false) as $manager) {
			foreach (array(array(), array('chat' => dashboard_v2_ai_one_option())) as $parts) {
				$data = pg_dashboard_v2_ai_off_data($settings, $manager, dashboard_v2_ai_parts($parts));
				pg_assert_true(dashboard_v2_ai_main_buttons($data) <= 1, 'at most one filled button');
			}
		}
	}

	foreach (array(pg_dashboard_v2_ai_notice(true), pg_dashboard_v2_ai_agent_note(true)) as $part) {
		pg_assert_same(false, $part['button']['main'], 'the notice\'s and the note\'s buttons are quiet');
	}
}

function test_dashboard_v2_ai_short_lines()
{
	foreach (array(true, false) as $settings) {
		$notice = pg_dashboard_v2_ai_notice($settings);
		$note = pg_dashboard_v2_ai_agent_note($settings);
		$lines = array_merge(array($notice['title'], $notice['text'], $notice['ask'], $note['title'], $note['ask']), $note['lines']);

		foreach (array($notice['button'], $note['button']) as $button) {
			if ($button) {
				$lines[] = $button['label'];
			}
		}

		foreach ($lines as $line) {
			pg_assert_true(mb_strlen($line) <= 110, 'at most 110 characters: ' . $line);
		}
	}
}

/**
 * Draws the card with the renderer in Node on a small stand-in DOM and
 * returns the drawn class names in order with their texts, or null without
 * Node.
 */
function dashboard_v2_ai_render_node($data)
{
	$script = <<<'JS'
function Node(tag) { this.tag = tag; this.className = ''; this.textContent = ''; this.children = []; this.attrs = {}; this.hidden = false; }
Node.prototype.appendChild = function (c) { this.children.push(c); return c; };
Node.prototype.removeChild = function (c) { this.children.splice(this.children.indexOf(c), 1); };
Node.prototype.setAttribute = function (k, v) { this.attrs[k] = String(v); };
Node.prototype.addEventListener = function () {};
Node.prototype.closest = function () { return null; };
Object.defineProperty(Node.prototype, 'firstChild', { get: function () { return this.children[0] || null; } });
document = { createElement: function (t) { return new Node(t); }, createTextNode: function (t) { var n = new Node('#text'); n.textContent = t; return n; } };
window.pgDash.el = function (tag, cls, text) { var n = document.createElement(tag); if (cls) { n.className = cls; } if (text !== undefined && text !== null) { n.textContent = String(text); } return n; };
var out = [];
function walk(n) { if (n.className && !n.hidden) { out.push([n.tag, n.className, n.textContent]); } n.children.forEach(walk); }
var body = new Node('div');
window.pgDash.renderers.ai(body, DATA);
walk(body);
process.stdout.write(JSON.stringify(out));
JS;

	return dashboard_v2_ai_talk_node(str_replace('DATA', json_encode($data), $script));
}

function dashboard_v2_ai_classes($drawn)
{
	return array_map(function ($node) {
		return $node[1];
	}, (array) $drawn);
}

function test_dashboard_v2_ai_rendered_off()
{
	$question = pg_dashboard_v2_ai_question_chips(array(array('question' => 'Which one?', 'url' => 'agent_question.php?id=1', 'created_at' => 5)));
	$drawn = dashboard_v2_ai_render_node(pg_dashboard_v2_ai_off_data(true, true, dashboard_v2_ai_parts(array('chat' => dashboard_v2_ai_one_option(), 'questions' => $question))));

	if ($drawn === null) {
		return;
	}

	$classes = dashboard_v2_ai_classes($drawn);
	$at = function ($class) use ($classes) {
		$index = array_search($class, $classes, true);
		return ($index === false) ? -1 : $index;
	};

	pg_assert_same(1, count(array_keys($classes, 'pg-dash-ai-notice', true)), 'one notice');
	pg_assert_same(-1, $at('pg-dash-ai-off-title'), 'no pitch title');
	pg_assert_same(-1, $at('pg-dash-ai-job'), 'no job box');
	pg_assert_same(-1, $at('pg-dash-ai-summary'), 'no summary');
	pg_assert_true($at('pg-dash-ai-job pg-dash-ai-chat-form') > 0, 'the chat\'s form is drawn');
	pg_assert_true(($at('pg-dash-ai-notice') < $at('pg-dash-ai-chips')) && ($at('pg-dash-ai-chips') < $at('pg-dash-ai-talk')) && ($at('pg-dash-ai-talk') < $at('pg-dash-ai-agents')), 'notice, question, chat, agent note');
	pg_assert_same(0, count(array_keys($classes, 'pg-dash-btn pg-dash-btn-main', true)), 'no filled button beside the chat\'s send');
	pg_assert_same(2, count(array_keys($classes, 'pg-dash-btn-sm', true)), 'the notice\'s and the note\'s buttons are quiet and small');
}

function test_dashboard_v2_ai_rendered_pitch()
{
	$drawn = dashboard_v2_ai_render_node(pg_dashboard_v2_ai_off_data(false, false, dashboard_v2_ai_parts()));

	if ($drawn === null) {
		return;
	}

	$classes = dashboard_v2_ai_classes($drawn);
	pg_assert_true(in_array('pg-dash-ai-off-title', $classes, true), 'the pitch');
	pg_assert_true(!in_array('pg-dash-ai-notice', $classes, true), 'no notice');
	pg_assert_true(!in_array('pg-dash-ai-talk', $classes, true) && !in_array('pg-dash-card-empty', $classes, true), 'nothing else');
	pg_assert_true(array_search('pg-dash-ai-off', $classes, true) < array_search('pg-dash-ai-agents', $classes, true), 'the agent note last');
	pg_assert_true(!in_array('pg-dash-btn-sm', $classes, true), 'no button: the administrator line');
}

function test_dashboard_v2_ai_rendered_on()
{
	$data = pg_dashboard_v2_ai_on_data(dashboard_v2_ai_parts(array('agent_active' => true, 'chat' => dashboard_v2_ai_one_option())));
	$drawn = dashboard_v2_ai_render_node($data);

	if ($drawn === null) {
		return;
	}

	pg_assert_same(array('pg-dash-rows-title', 'pg-dash-card-empty', 'pg-dash-ai-job', 'visually-hidden', 'form-control pg-dash-ai-input', 'btn btn-solid theme-primary pg-dash-ai-send', 'bi bi-arrow-right', 'pg-dash-card-note pg-dash-ai-status', 'pg-dash-ai-talk'),
		array_slice(dashboard_v2_ai_classes($drawn), 0, 9), 'proposals, job box, chat as today');
	pg_assert_true(!in_array('pg-dash-ai-agents', dashboard_v2_ai_classes($drawn), true), 'no note with an active agent');
}
