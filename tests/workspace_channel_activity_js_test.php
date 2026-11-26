<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Activity tab of Channel info
 * (assets/js/workspace_channel_activity.js): when the Info and Activity tabs
 * show, which tab a channel opens on, the agents block over both tabs (a
 * row's second line in each state, an opened row's facts, six rows and
 * "n more", the context ring, no badge or pill in a row), the 15 second
 * brake on reads asked by the channel's sync, and the hooks in
 * workspace.js. The Node parts are
 * skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of a script under assets/js/, '' when it is not there.
function workspace_channel_activity_js_test_source($name = 'workspace_channel_activity.js')
{
	$file = PG_FUNCTIONS_DIR . '/assets/js/' . $name;

	return is_file($file) ? str_replace("\r\n", "\n", (string) file_get_contents($file)) : '';
}

// One "function name(" of the tab's script up to the closing brace at its
// own indent; '' when it is not there.
function workspace_channel_activity_js_test_function($name)
{
	return preg_match('/\n    function ' . $name . '\([^)]*\) \{\n.*?\n    \}\n/s', workspace_channel_activity_js_test_source(), $match) ? $match[0] : '';
}

// Runs a script with Node and answers what it printed as JSON; null without
// Node.
function workspace_channel_activity_js_test_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgactjs');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : null;
}

// A t() that names the key and its values, as the tests read the words.
function workspace_channel_activity_js_test_t()
{
	return 'function t(key) { var rest = Array.prototype.slice.call(arguments, 1); return rest.length ? key + "(" + rest.join(",") + ")" : key; }' . "\n";
}

function test_workspace_channel_activity_js_tabs_show()
{
	$shows = workspace_channel_activity_js_test_function('activityTabsShow');

	pg_assert_true($shows !== '', 'activityTabsShow() is in the tab\'s script');

	$result = workspace_channel_activity_js_test_node($shows . '
console.log(JSON.stringify({
	agent: activityTabsShow({ id: 1, agents: [{ id: 4 }] }),
	repository: activityTabsShow({ id: 1, agents: [], repository_linked: true }),
	both: activityTabsShow({ id: 1, agents: [{ id: 4 }], repository_linked: true }),
	none: activityTabsShow({ id: 1, agents: [], repository_linked: false }),
	noList: activityTabsShow({ id: 1 }),
	guest: activityTabsShow({ id: 1, agents: [{ id: 4 }], guest: { name: "x" } }),
	noChannel: activityTabsShow(null)
}));');

	if ($result === null) {
		return;
	}

	pg_assert_true($result['agent'], 'a channel with an agent gets the tabs');
	pg_assert_true($result['repository'], 'a channel with a repository link gets the tabs');
	pg_assert_true($result['both'], 'and one with both');
	pg_assert_false($result['none'], 'a channel with neither keeps its Channel info as it was');
	pg_assert_false($result['noList'], 'no agent list, no tabs');
	pg_assert_false($result['guest'], 'never on a guest\'s page');
	pg_assert_false($result['noChannel'], 'no channel, no tabs');
}

function test_workspace_channel_activity_js_remembered_tab()
{
	$pick = workspace_channel_activity_js_test_function('activityTabPick');
	$key = workspace_channel_activity_js_test_function('activityTabKey');

	pg_assert_true($pick !== '', 'activityTabPick() is in the tab\'s script');
	pg_assert_true($key !== '', 'activityTabKey() is in the tab\'s script');

	$result = workspace_channel_activity_js_test_node($pick . $key . '
console.log(JSON.stringify({
	activity: activityTabPick("activity", true),
	info: activityTabPick("info", true),
	nothing: activityTabPick(null, true),
	junk: activityTabPick("tasks", true),
	hidden: activityTabPick("activity", false),
	key7: activityTabKey(7),
	key8: activityTabKey(8)
}));');

	if ($result === null) {
		return;
	}

	pg_assert_same('activity', $result['activity'], 'the tab last open in the channel opens again');
	pg_assert_same('info', $result['info'], 'Info when Info was last open');
	pg_assert_same('info', $result['nothing'], 'Info when nothing is kept');
	pg_assert_same('info', $result['junk'], 'Info for a value that is no tab');
	pg_assert_same('info', $result['hidden'], 'Info where the tabs do not show');
	pg_assert_true($result['key7'] !== $result['key8'], 'each channel keeps its own tab');
	pg_assert_contains('7', $result['key7'], 'the key names the channel');
}

// The block's pure parts with the helpers and constants they lean on.
function workspace_channel_activity_js_test_block_parts()
{
	$parts = 'var SHOWN = 6; var STATE_RANK = { working: 0, waiting: 1, late: 2, idle: 3, paused: 4 }; var RING_R = 7; var RING_C = 2 * Math.PI * RING_R;' . "\n"
		. workspace_channel_activity_js_test_t();

	foreach (array('activityTokens', 'activityContext', 'agentsRows', 'agentsShown', 'agentsLine', 'agentsFacts', 'agentsRing') as $name) {
		$body = workspace_channel_activity_js_test_function($name);

		pg_assert_true($body !== '', $name . '() is in the block\'s script');
		$parts .= $body;
	}

	return $parts;
}

// Agents in each state, as the read and the channel give them.
function workspace_channel_activity_js_test_agents()
{
	return '
var working = { key: "agent-1", id: 1, read: true, kind: "app", name: "Mimar", state: "working", state_words: "Working", handle: "mimar", wake_fact: "When mentioned",
	request: { message_id: 55, asked_by: { user_id: 3, name: "Erdal" }, since: "since 14:32", note: "Drawing the sheet" },
	model: "fable", context: { used: 412000, size: 1000000 }, branch: "development", seen: "Last knock 1 min ago", seen_state: "off",
	reported_at: 1700000000, reported_ago: "2 min ago", reported: "reported 2 min ago", stale: false, waiting: 0 };
var queued = Object.assign({}, working, { waiting: 2 });
var asker = Object.assign({}, working, { request: { message_id: 55, asked_by: { user_id: 3, name: "Erdal" }, since: "since 14:51", note: "" } });
var bare = Object.assign({}, working, { request: { message_id: null, asked_by: null, since: "since 10:00", note: "" } });
var waiting2 = { key: "agent-2", id: 2, read: true, kind: "app", name: "K1", state: "waiting", state_words: "Waiting", waiting: 2 };
var waiting1 = Object.assign({}, waiting2, { waiting: 1 });
var late = { key: "agent-3", id: 3, read: true, kind: "app", name: "Test", state: "late", state_words: "Not seen", seen_rest: "last knock 3 h ago" };
var ready = { key: "agent-4", id: 4, read: true, kind: "app", name: "PR", state: "idle", state_words: "Ready", model: "opus", reported_at: 1700000000, reported: "reported 8 min ago", stale: false };
var stale = Object.assign({}, ready, { stale: true, reported: "reported 7 d ago" });
var unreported = { key: "agent-5", id: 5, read: true, kind: "app", name: "K3", handle: "k3", wake_fact: "When mentioned", state: "idle", state_words: "Ready", model: "", reported_at: null, reported_ago: "", seen: "4 min ago", seen_state: "on", waiting: 0 };
var paused = { key: "agent-6", id: 6, read: true, kind: "app", name: "K2", state: "paused", state_words: "Paused" };
var assistant = { key: "claude", name: "Claude", handle: "Claude", assistant: true, seen: "Always ready", seen_state: "ready" };
var early = Object.assign({}, working, { read: false });
var earlyReady = Object.assign({}, ready, { read: false });
';
}

// 1. Line 2 of a row at rest, in each state.
function test_workspace_channel_activity_js_agents_line()
{
	$result = workspace_channel_activity_js_test_node(workspace_channel_activity_js_test_block_parts() . workspace_channel_activity_js_test_agents() . '
console.log(JSON.stringify({
	note: agentsLine(working, t), asker: agentsLine(asker, t), bare: agentsLine(bare, t),
	w2: agentsLine(waiting2, t), w1: agentsLine(waiting1, t), late: agentsLine(late, t),
	ready: agentsLine(ready, t), stale: agentsLine(stale, t), none: agentsLine(unreported, t),
	paused: agentsLine(paused, t), assistant: agentsLine(assistant, t),
	early: agentsLine(early, t), earlyReady: agentsLine(earlyReady, t)
}));');

	if ($result === null) {
		return;
	}

	pg_assert_same(array('word' => 'Working', 'rest' => 'Drawing the sheet', 'warn' => '', 'tone' => 'blue'), $result['note'], 'working: the progress note, the word blue');
	pg_assert_same('agents_for(Erdal,since 14:51)', $result['asker']['rest'], 'with no note: for the asker, since when');
	pg_assert_same('since 10:00', $result['bare']['rest'], 'with no asker of this channel: since alone');
	pg_assert_same(array('Waiting', 'agents_queue(2)', 'amber'), array($result['w2']['word'], $result['w2']['rest'], $result['w2']['tone']), 'waiting: its queue, the word amber');
	pg_assert_same('agents_queue_one', $result['w1']['rest'], 'one request in its queue');
	pg_assert_same(array('Not seen', 'last knock 3 h ago', 'amber'), array($result['late']['word'], $result['late']['rest'], $result['late']['tone']), 'not seen: the last knock');
	pg_assert_same(array('Ready', 'opus', '', ''), array($result['ready']['word'], $result['ready']['rest'], $result['ready']['warn'], $result['ready']['tone']), 'ready: the model, plain');
	pg_assert_same(array('opus', 'reported 7 d ago'), array($result['stale']['rest'], $result['stale']['warn']), 'a report older than a day: its age in amber');
	pg_assert_same('agents_no_report', $result['none']['rest'], 'no report yet');
	pg_assert_same(array('word' => 'Paused', 'rest' => '', 'warn' => '', 'tone' => ''), $result['paused'], 'paused: the word alone');
	pg_assert_same(array('word' => 'cm_assistant', 'rest' => 'agents_always', 'warn' => '', 'tone' => ''), $result['assistant'], 'an assistant: Assistant · always ready');
	pg_assert_same('', $result['early']['rest'], 'before the read lands a working row shows its word alone');
	pg_assert_same('', $result['earlyReady']['rest'], 'and a ready one');
}

// 2. The facts of an opened row, in their order.
function test_workspace_channel_activity_js_agents_facts()
{
	$result = workspace_channel_activity_js_test_node(workspace_channel_activity_js_test_block_parts() . workspace_channel_activity_js_test_agents() . '
function keys(agent) { return agentsFacts(agent, t).map(function (fact) { return fact.key; }); }
var lateSeen = Object.assign({}, working, { seen_state: "late" });
console.log(JSON.stringify({
	full: keys(working), queued: keys(queued), none: agentsFacts(unreported, t), assistant: keys(assistant),
	facts: agentsFacts(working, t), staleFacts: agentsFacts(Object.assign({}, working, { stale: true }), t), lateFacts: agentsFacts(lateSeen, t),
	high: agentsFacts(Object.assign({}, working, { context: { used: 186000, size: 200000 } }), t)
}));');

	if ($result === null) {
		return;
	}

	pg_assert_same(array('doing', 'asked', 'handle', 'wakes', 'model', 'context', 'branch', 'seen', 'reported'), $result['full'], 'a working agent with everything has 9 facts in order');
	pg_assert_same(array('doing', 'asked', 'queue', 'handle', 'wakes', 'model', 'context', 'branch', 'seen', 'reported'), $result['queued'], 'with a queue 10');
	pg_assert_same(array('handle', 'wakes', 'seen', 'reported'), array_map(function ($fact) { return $fact['key']; }, $result['none']), 'no report: Handle, Wakes, Seen, Reported');
	pg_assert_same('agents_not_yet', $result['none'][3]['text'], 'Reported says Not yet');
	pg_assert_true($result['none'][3]['muted'], 'quietly');
	pg_assert_same(array('handle', 'wakes', 'seen'), $result['assistant'], 'an assistant has 3');

	$facts = array();

	foreach ($result['facts'] as $fact) {
		$facts[$fact['key']] = $fact;
	}

	pg_assert_same('Erdal', $facts['asked']['text'], 'Asked by names the asker');
	pg_assert_same(55, $facts['asked']['message_id'], 'linked to the message');
	pg_assert_same('since 14:32', $facts['asked']['since'], 'with since');
	pg_assert_same('@mimar', $facts['handle']['text']);
	pg_assert_same('agents_context(412k,1M,41)', $facts['context']['text'], 'the context as "412k of 1M · 41%"');
	pg_assert_false($facts['context']['high']);
	pg_assert_same('2 min ago', $facts['reported']['text'], 'the report\'s age alone');
	pg_assert_false($facts['reported']['warn']);
	pg_assert_true(end($result['staleFacts'])['warn'], 'a stale report in amber');
	pg_assert_true($result['lateFacts'][7]['warn'], 'Seen in amber when not seen');
	pg_assert_true($result['high'][5]['high'], 'the context from 90% is high');

	foreach ($result['facts'] as $fact) {
		pg_assert_true($fact['text'] !== '' && $fact['text'] !== '-', 'no fact shows a dash or nothing: ' . $fact['key']);
	}
}

// 3. Six rows and "n more"; the order of the states, the assistants last.
function test_workspace_channel_activity_js_agents_rows()
{
	$result = workspace_channel_activity_js_test_node(workspace_channel_activity_js_test_block_parts() . '
function row(id, state) { return { key: "agent-" + id, id: id, tone: id, name: "A" + id, state: state, state_words: state }; }
var base = [{ key: "claude", name: "Claude", assistant: true, tone: 3 }, row(1, "idle"), row(2, "paused"), row(3, "late"), row(4, "waiting"), row(5, "working"), row(6, "idle"), row(7, "working"), row(8, "idle")];
var read = [{ id: 1, name: "", state: "working", state_words: "Working", model: "opus" }, { id: 2 }, { id: 3 }, { id: 4 }, { id: 5 }, { id: 6 }, { id: 7 }, { id: 8 }];
var rows = agentsRows(base, read);
var six = agentsRows(base.slice(0, 6), null);
console.log(JSON.stringify({
	order: rows.map(function (r) { return r.key; }),
	joined: rows[0],
	nine: [agentsShown(rows, false).rows.length, agentsShown(rows, false).rest],
	pressed: [agentsShown(rows, true).rows.length, agentsShown(rows, true).rest],
	six: [agentsShown(six, false).rows.length, agentsShown(six, false).rest]
}));');

	if ($result === null) {
		return;
	}

	pg_assert_same(array('agent-1', 'agent-5', 'agent-7', 'agent-4', 'agent-3', 'agent-6', 'agent-8', 'agent-2', 'claude'), $result['order'], 'working, waiting, not seen, ready, paused, then the assistants; the read\'s state wins');
	pg_assert_same('A1', $result['joined']['name'], 'an empty name from the read keeps the channel\'s');
	pg_assert_same('opus', $result['joined']['model'], 'joined by id with the read\'s agent');
	pg_assert_true($result['joined']['read'], 'and marked as read');
	pg_assert_same(array(6, 3), $result['nine'], 'nine rows show six and "3 more"');
	pg_assert_same(array(9, 0), $result['pressed'], 'pressed, all of them');
	pg_assert_same(array(6, 0), $result['six'], 'six rows show six and no such row');
}

// The landed read decides the registry agents' rows: one added while the
// page is open gets a row, a removed one loses its row, the assistants stay.
function test_workspace_channel_activity_js_agents_rows_follow_read()
{
	$result = workspace_channel_activity_js_test_node(workspace_channel_activity_js_test_block_parts() . '
var base = [{ key: "claude", name: "Claude", assistant: true, tone: 3 }, { key: "ai", name: "Pinegrap AI", assistant: true, tone: 1 },
	{ key: "agent-1", id: 1, tone: 1, name: "Mimar", handle: "mimar", state: "idle", active: true }, { key: "agent-2", id: 2, tone: 2, name: "K2", handle: "k2", state: "idle", active: true }];
var read = [{ id: 1, name: "Mimar", handle: "mimar", kind: "app", state: "working", state_words: "Working", active: true },
	{ id: 9, name: "", handle: "k3", kind: "app", state: "idle", state_words: "Ready", active: true },
	{ id: 10, name: "Helper", handle: "helper", kind: "model", state: "idle", active: true }];
var rows = agentsRows(base, read);
var early = agentsRows(base, null);
var empty = agentsRows(base, []);
var added = rows.filter(function (r) { return r.id === 9; })[0];
console.log(JSON.stringify({
	keys: rows.map(function (r) { return r.key; }),
	added: added ? { name: added.name, tone: added.tone, read: added.read, assistant: added.assistant, state: added.state } : null,
	model: rows.some(function (r) { return r.id === 10 && r.assistant; }),
	early: early.map(function (r) { return r.key; }),
	empty: empty.map(function (r) { return r.key; })
}));');

	if ($result === null) {
		return;
	}

	pg_assert_same(array('agent-1', 'agent-9', 'claude', 'ai', 'agent-10'), $result['keys'], 'the read adds agent 9 and 10, drops agent 2, keeps the assistants');
	pg_assert_same(array('name' => '@k3', 'tone' => 9, 'read' => true, 'assistant' => false, 'state' => 'idle'), $result['added'], 'an agent only in the read is drawn from the read');
	pg_assert_true($result['model'], 'a model agent only in the read is an assistant row');
	pg_assert_same(array('agent-1', 'agent-2', 'claude', 'ai'), $result['early'], 'before the read lands the rows are the channel\'s');
	pg_assert_same(array('claude', 'ai'), $result['empty'], 'a read with no agent leaves the assistants alone');
}

// 4. The context ring.
function test_workspace_channel_activity_js_agents_ring()
{
	$result = workspace_channel_activity_js_test_node(workspace_channel_activity_js_test_block_parts() . '
console.log(JSON.stringify({
	r41: agentsRing({ used: 412000, size: 1000000 }), r93: agentsRing({ used: 186000, size: 200000 }), none: [agentsRing(null), agentsRing({ used: 1, size: 0 })]
}));');

	if ($result === null) {
		return;
	}

	pg_assert_same('18.03', $result['r41']['dash'], '41% draws 41% of the circle');
	pg_assert_same('43.98', $result['r41']['whole']);
	pg_assert_false($result['r41']['high']);
	pg_assert_true($result['r93']['high'], '93% is high');
	pg_assert_same(array(null, null), $result['none'], 'no context gives no ring');

	$tokens = workspace_channel_activity_js_test_node(workspace_channel_activity_js_test_block_parts() . '
console.log(JSON.stringify([activityTokens(950), activityTokens(412000), activityTokens(1000000), activityTokens(1500000), activityTokens(12345)]));');

	pg_assert_same(array('950', '412k', '1M', '1.5M', '12k'), $tokens, 'tokens in short words');
}

// The context with no size (none typed on the agent's form, none
// reported): the used tokens alone, no ring, no percent, no 90% mark; used
// above the size is drawn as full.
function test_workspace_channel_activity_js_context_without_size()
{
	$result = workspace_channel_activity_js_test_node(workspace_channel_activity_js_test_block_parts() . workspace_channel_activity_js_test_agents() . '
var unsized = Object.assign({}, working, { context: { used: 412000, size: null } });
var facts = {}; agentsFacts(unsized, t).forEach(function (fact) { facts[fact.key] = fact; });
console.log(JSON.stringify({
	context: activityContext({ used: 412000, size: null }, t), ring: agentsRing({ used: 412000, size: null }), fact: facts.context,
	over: activityContext({ used: 300000, size: 250000 }, t), overRing: agentsRing({ used: 300000, size: 250000 })
}));');

	if ($result === null) {
		return;
	}

	pg_assert_same(array('words' => 'act_context_used(412k)', 'percent' => null, 'high' => false), $result['context'], 'the used tokens alone');
	pg_assert_same(null, $result['ring'], 'no ring without a size');
	pg_assert_same('act_context_used(412k)', $result['fact']['text'], 'the opened row says the used tokens');
	pg_assert_same(null, $result['fact']['percent'], 'with no bar');
	pg_assert_false($result['fact']['high'], 'and no 90% mark');
	pg_assert_same(100, $result['over']['percent'], 'used above the size is full');
	pg_assert_same(100, $result['overRing']['percent']);
	pg_assert_contains('fact.percent !== null', workspace_channel_activity_js_test_function('factsNode'), 'the bar only with a percent');
}

// 7. and 8. No badge, pill or button in a row; the old lists and their styles gone.
function test_workspace_channel_activity_js_block_source()
{
	$row = workspace_channel_activity_js_test_function('rowNode') . workspace_channel_activity_js_test_function('factsNode') . workspace_channel_activity_js_test_function('ringNode');

	pg_assert_true($row !== '', 'rowNode() is in the block\'s script');
	pg_assert_same(0, preg_match('/badge|pg-pill|pg-button/', $row), 'no badge, pill or button inside a row');
	pg_assert_contains("'aria-expanded'", $row, 'a row\'s button tells whether it is open');
	pg_assert_contains("'aria-controls'", $row);
	pg_assert_contains("role: 'img'", $row, 'the ring is an image named by its words');

	$source = workspace_channel_activity_js_test_source();

	pg_assert_same('', workspace_channel_activity_js_test_function('drawSummary'), 'drawSummary() is gone');
	pg_assert_same('', workspace_channel_activity_js_test_function('drawAgents'), 'drawAgents() is gone');
	pg_assert_contains("'aria-live', 'polite'", $source, 'a changed state is said politely');
	pg_assert_contains("h.channelSettings(channel, 'people')", $source, 'Manage agents opens People and agents');

	$screen = workspace_channel_activity_js_test_source('workspace.js');

	pg_assert_same(2, substr_count($screen, 'agentInfoRow('), 'agentInfoRow() has one caller left, the people panel');
	pg_assert_contains('window.PGWsChannelActivity.place(body)', $screen, 'drawInfo() places the block without the tabs');
	pg_assert_contains('window.PGWsChannelActivity.forget()', $screen, 'closing the panel drops its open rows');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');

	foreach (array('.ws-act-agent', '.ws-act-summary-line', '.ws-act-state', '.ws-act-context') as $rule) {
		pg_assert_same(0, preg_match('/' . preg_quote($rule, '/') . '[\s,{.:]/', $css), 'no ' . $rule . ' rule is left');
	}

	pg_assert_contains('.ws-info-agents', $css, 'the people panel keeps its rules');
	pg_assert_contains('.ws-agents-row', $css, 'the block has its own');
}

function test_workspace_channel_activity_js_brake()
{
	$brake = workspace_channel_activity_js_test_function('activityBrake');

	pg_assert_true($brake !== '', 'activityBrake() is in the tab\'s script');

	$result = workspace_channel_activity_js_test_node($brake . '
var now = 1000000;
console.log(JSON.stringify({
	first: activityBrake(0, now),
	soon: activityBrake(now - 4000, now),
	just: activityBrake(now - 15000, now),
	later: activityBrake(now - 60000, now),
	clock: activityBrake(now + 5000, now)
}));');

	if ($result === null) {
		return;
	}

	pg_assert_same(0, $result['first'], 'the first change reads at once');
	pg_assert_same(11000, $result['soon'], 'a change within 15 seconds waits for the rest of them');
	pg_assert_same(0, $result['just'], 'after 15 seconds it reads at once');
	pg_assert_same(0, $result['later'], 'and long after');
	pg_assert_same(15000, $result['clock'], 'a clock turned back waits at most 15 seconds');
}

function test_workspace_channel_activity_js_hooks()
{
	$source = workspace_channel_activity_js_test_source();
	$screen = workspace_channel_activity_js_test_source('workspace.js');

	pg_assert_true($source !== '', 'the tab has its own script');
	pg_assert_contains('window.PGWsChannelActivity', $source, 'it is offered to workspace.js');
	pg_assert_contains("'ws_channel_activity'", $source, 'it reads the panel action of KA1');
	pg_assert_contains("role', 'tablist'", $source, 'the tabs are a tablist');
	pg_assert_contains('pg-seg', $source, 'drawn as the segmented control');
	pg_assert_same(0, preg_match('/btn btn-|nav-tabs|nav-pills|nav-underline/', $source), 'no old buttons or tab strips');
	pg_assert_contains('60000', $source, 'read every minute while open, for the ages');

	pg_assert_contains('window.PGWsChannelActivity.mount(', $screen, 'drawInfo() mounts the tabs');
	pg_assert_contains('window.PGWsChannelActivity.changed(', $screen, 'the channel\'s sync tells the tab of a change');

	$bundles = pg_js_bundles();

	pg_assert_true(in_array('assets/js/workspace_channel_activity.js', $bundles['assets/js/workspace.min.js'], true), 'the script is in the Workspace bundle');
	pg_assert_true(
		array_search('assets/js/workspace_channel_activity.js', $bundles['assets/js/workspace.min.js'], true)
			=== array_search('assets/js/workspace_channel_agents.js', $bundles['assets/js/workspace.min.js'], true) + 1,
		'beside the agents\' script'
	);
}

function test_workspace_channel_activity_js_strings()
{
	$strings = ws_channel_activity_js_strings();

	foreach (array('act_tab_info', 'act_tab_activity', 'act_context', 'act_all_decisions', 'act_more', 'agents_title', 'agents_manage', 'agents_queue', 'agents_for', 'agents_context', 'agents_ring', 'agents_f_reported') as $key) {
		pg_assert_true(isset($strings[$key]) && ($strings[$key] !== ''), $key . ' has its words');
	}

	pg_assert_contains('{1}', $strings['act_context'], 'the context\'s words take the numbers');
	pg_assert_false(isset($strings['act_agents']) || isset($strings['act_asked_by']), 'the old lists\' texts are gone');
	pg_assert_false(isset($strings['act_agents']) || isset($strings['act_asked_by']), 'the old lists\' texts are gone');
	pg_assert_contains('ws_channel_activity_js_strings()', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php'), 'the screen sends them');
}
