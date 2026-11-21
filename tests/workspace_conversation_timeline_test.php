<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the conversation timeline (conversation_timeline.php,
 * assets/js/workspace_conversation_timeline.js): the first words cut on a
 * word, what came of a poll, an approval and an agent request, the panel
 * action, the words of the strip; the strip's runs and turns, its layout in
 * pixels, the message under a point, the waits in words, a writer's
 * initials; and the hooks in workspace.js and the stylesheet. The Node
 * parts are skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');

// The source of a file under pinegrap/, '' when it is not there.
function workspace_conversation_timeline_test_source($path)
{
	$file = PG_FUNCTIONS_DIR . '/' . $path;

	return is_file($file) ? str_replace("\r\n", "\n", (string) file_get_contents($file)) : '';
}

// One "function name(" of the strip's script up to the closing brace at its
// own indent; '' when it is not there.
function workspace_conversation_timeline_test_function($name)
{
	return preg_match('/\n    function ' . $name . '\([^)]*\) \{\n.*?\n    \}\n/s', workspace_conversation_timeline_test_source('assets/js/workspace_conversation_timeline.js'), $match) ? $match[0] : '';
}

// Runs a script with Node and answers what it printed as JSON; null without
// Node.
function workspace_conversation_timeline_test_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgctljs');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : null;
}

function test_workspace_conversation_timeline_cut()
{
	pg_assert_same('Short words', ws_conversation_timeline_cut("  Short \n words "), 'spaces fold into one');

	$long = str_repeat('word ', 40);
	$cut = ws_conversation_timeline_cut($long);

	pg_assert_true(mb_strlen($cut) <= WS_CONVERSATION_TIMELINE_TEXT, 'at most 100 characters');
	pg_assert_same('…', mb_substr($cut, -1), 'an ellipsis after the cut');
	pg_assert_same('word…', mb_substr($cut, -5), 'cut on a word');
	pg_assert_same(100, WS_CONVERSATION_TIMELINE_TEXT, 'the first words are 100 characters at most');

	$one = str_repeat('ş', 150);

	pg_assert_same(100, mb_strlen(ws_conversation_timeline_cut($one)), 'a word with no space is cut where it reaches the length');
}

function test_workspace_conversation_timeline_poll_result()
{
	$options = array(array('label' => 'First', 'votes' => 1), array('label' => 'Second', 'votes' => 2));
	$open = ws_conversation_timeline_poll_result($options, false);
	$closed = ws_conversation_timeline_poll_result($options, true);

	pg_assert_same('open', $open['state'], 'an open poll waits');
	pg_assert_contains('Second', $open['result'], 'it names the leading option');
	pg_assert_same('ok', $closed['state'], 'a closed poll with a winner is settled');
	pg_assert_contains('Second', $closed['result'], 'and names the winner');

	$tie = ws_conversation_timeline_poll_result(array(array('label' => 'A', 'votes' => 2), array('label' => 'B', 'votes' => 2)), false);

	pg_assert_false(strpos($tie['result'], 'A') !== false || strpos($tie['result'], 'B') !== false, 'a tie leads with none');
	pg_assert_same('bad', ws_conversation_timeline_poll_result($options, false, true)['state'], 'a poll taken back');
	pg_assert_same('open', ws_conversation_timeline_poll_result(array(), false)['state'], 'a poll without votes waits');
}

function test_workspace_conversation_timeline_approval_result()
{
	pg_assert_same('ok', ws_conversation_timeline_approval_result('approved', array())['state']);
	pg_assert_same('bad', ws_conversation_timeline_approval_result('rejected', array())['state']);
	pg_assert_same('bad', ws_conversation_timeline_approval_result('expired', array())['state']);

	$open = ws_conversation_timeline_approval_result('open', array('approved' => 1, 'pending' => 2));

	pg_assert_same('open', $open['state'], 'an open request waits');
	pg_assert_same(lang(array('string' => 'Waiting: {var:1} of {var:2} approved', 'vars' => array(1, 3))), $open['result'], 'with who said yes of everyone asked');
}

function test_workspace_conversation_timeline_request_result()
{
	pg_assert_same('open', ws_conversation_timeline_request_result(array('answered', 'running'))['state'], 'a request in work goes first');
	pg_assert_same('bad', ws_conversation_timeline_request_result(array('answered', 'failed'))['state'], 'then a failed one');
	pg_assert_same(lang('Answered'), ws_conversation_timeline_request_result(array('answered'))['result'], 'one answered');
	pg_assert_same(lang(array('string' => '{var:1} answered', 'vars' => 2)), ws_conversation_timeline_request_result(array('answered', 'answered'))['result'], 'several answered');
	pg_assert_same('', ws_conversation_timeline_request_result(array('cancelled'))['state'], 'a cancelled one is plain');
}

function test_workspace_conversation_timeline_tool()
{
	$tool = ws_conversation_timeline_tool('approval', str_repeat('x', 200), 'Approved', 'ok');

	pg_assert_same('approval', $tool['k']);
	pg_assert_true(mb_strlen($tool['text']) <= 80, 'a subject is cut to 80 characters');
	pg_assert_same('', ws_conversation_timeline_tool('file', 'a.png', '', 'odd')['state'], 'an unknown state is plain');

	$task = ws_conversation_timeline_task(array('id' => 7, 'title' => 'Fix it', 'status' => 'done'));

	pg_assert_same(array('task', 'Fix it', 'ok', 7), array($task['k'], $task['text'], $task['state'], $task['id']), 'a done task is settled');
}

function test_workspace_conversation_timeline_action_and_words()
{
	$entry = pg_panel_action('ws_conversation_timeline');

	pg_assert_true(is_array($entry), 'routed by the panel');
	pg_assert_same('pg_panel_ws', is_array($entry) ? $entry['handler'] : '', 'handled by the workspace');
	pg_assert_contains("case 'ws_conversation_timeline':", workspace_conversation_timeline_test_source('includes/workspace/actions.php'), 'answered by the workspace actions');

	$words = ws_conversation_timeline_js_strings();
	$script = workspace_conversation_timeline_test_source('assets/js/workspace_conversation_timeline.js');

	preg_match_all("/t\\('(ctl_[a-z_]*[a-z])'/", $script, $used);

	foreach (array_unique($used[1]) as $key) {
		pg_assert_true(isset($words[$key]), 'the strip has the words of ' . $key);
	}

	foreach (array('decision', 'note', 'task', 'file', 'picture', 'voice', 'poll', 'question', 'approval', 'ack', 'status', 'checkin', 'agent') as $kind) {
		pg_assert_true(isset($words['ctl_tool_' . $kind]), 'a card names the tool ' . $kind);
	}

	foreach (array('agent', 'app', 'guest', 'mail', 'system') as $kind) {
		pg_assert_true(isset($words['ctl_kind_' . $kind]), 'a card names the writer kind ' . $kind);
	}

	pg_assert_contains('ws_conversation_timeline_js_strings()', workspace_conversation_timeline_test_source('includes/workspace/screen.php'), 'the words reach the screen');
	pg_assert_true(in_array('assets/js/workspace_conversation_timeline.js', pg_js_bundles()['assets/js/workspace.min.js'], true), 'bundled with the workspace');
}

function test_workspace_conversation_timeline_current_conversation_only()
{
	$source = workspace_conversation_timeline_test_source('includes/workspace/conversation_timeline.php');

	pg_assert_contains('ws_channel_floor($channel_id)', $source, 'the read starts after the last clear');
	pg_assert_contains("id > '\" . \$floor . \"'", $source, 'nothing of an earlier version');
	pg_assert_contains('deleted_at = 0', $source, 'no deleted message');
	pg_assert_contains('ws_message_hidden_ids(', $source, 'nor one the reader deleted for themselves');
}

function test_workspace_conversation_timeline_js_runs()
{
	$runs = workspace_conversation_timeline_test_function('timelineRuns');

	pg_assert_true($runs !== '', 'timelineRuns() is in the script');

	$result = workspace_conversation_timeline_test_node($runs . '
var items = [
	{ id: 1, at: 0, s: 0 }, { id: 2, at: 30, s: 0 }, { id: 3, at: 60, s: 1 },
	{ id: 4, at: 400, s: 1 }, { id: 5, at: 5000, s: 0 }
];
console.log(JSON.stringify(timelineRuns(items, 120, 1800)));
');

	if ($result === null) {
		return;
	}

	pg_assert_same(2, count($result), 'a wait of half an hour starts a run');
	pg_assert_same(3, count($result[0]['turns']), 'one writer close together is one turn; a longer wait starts another');
	pg_assert_same(array(0, 1), array($result[0]['turns'][0]['from'], $result[0]['turns'][0]['to']), 'the first turn holds the first two messages');
	pg_assert_same(340, $result[0]['turns'][2]['wait'], 'a turn knows the wait before it');
	pg_assert_same(4600, $result[1]['turns'][0]['wait'], 'a run\'s first turn keeps the wait that split the strip');
}

function test_workspace_conversation_timeline_js_layout()
{
	$source = workspace_conversation_timeline_test_function('timelineRuns') . workspace_conversation_timeline_test_function('timelineLayout')
		. workspace_conversation_timeline_test_function('timelinePick') . workspace_conversation_timeline_test_function('timelinePlace')
		. workspace_conversation_timeline_test_function('timelineIndexOf');

	$result = workspace_conversation_timeline_test_node($source . '
var items = [];
for (var i = 0; i < 12; i++) { items.push({ id: 10 + i, at: i * 300 + ((i > 6) ? 7200 : 0), s: i % 3 }); }
var layout = timelineLayout(timelineRuns(items, 120, 1800), 600, 10, 6, 3);
var last = layout.runs[layout.runs.length - 1];
var turns = [].concat.apply([], layout.runs.map(function (run) { return run.turns; }));
var tight = timelineLayout(timelineRuns(items.map(function (item, i) { return { id: item.id, at: i * 400, s: i % 2 }; }), 120, 1800), 40, 10, 6, 3);
var tightTurns = tight.runs[0].turns;
console.log(JSON.stringify({
	runs: layout.runs.length,
	end: Math.round(last.left + last.width),
	least: Math.min.apply(null, turns.map(function (turn) { return turn.width; })),
	ordered: turns.every(function (turn, i) { return !i || turn.left >= turns[i - 1].left + turns[i - 1].width - 0.001; }),
	gap: Math.round(layout.runs[1].left - (layout.runs[0].left + layout.runs[0].width)),
	pickFirst: timelinePick(layout, 0).index,
	pickLast: timelinePick(layout, 600).index,
	pickBeyond: timelinePick(layout, 9999).index,
	place: Math.round(timelinePlace(layout, 0).x) >= 0,
	tightLeast: Math.min.apply(null, tightTurns.map(function (turn) { return turn.width; })),
	indexOf: [timelineIndexOf(items, 9), timelineIndexOf(items, 10), timelineIndexOf(items, 15), timelineIndexOf(items, 99)],
	empty: timelineLayout([], 300).runs.length
}));
');

	if ($result === null) {
		return;
	}

	pg_assert_same(2, $result['runs'], 'two hours apart is two runs');
	pg_assert_same(600, $result['end'], 'the strip fills its width');
	pg_assert_true($result['least'] >= 6 - 0.001, 'no turn narrower than six pixels while there is room');
	pg_assert_true($result['ordered'], 'turns stand left to right without overlapping');
	pg_assert_same(10, $result['gap'], 'runs stand ten pixels apart');
	pg_assert_same(0, $result['pickFirst'], 'the left end is the first message');
	pg_assert_same(11, $result['pickLast'], 'the right end is the last');
	pg_assert_same(11, $result['pickBeyond'], 'past the end the nearest turn');
	pg_assert_true($result['place'], 'a message has its place');
	pg_assert_true(($result['tightLeast'] < 6) && ($result['tightLeast'] >= 1), 'a crowded strip makes its turns narrower, never under a pixel');
	pg_assert_same(array(-1, 0, 5, 11), $result['indexOf'], 'a message id finds itself or the one before');
	pg_assert_same(0, $result['empty'], 'an empty conversation draws nothing');
}

function test_workspace_conversation_timeline_js_words()
{
	$source = workspace_conversation_timeline_test_function('timelineWait') . workspace_conversation_timeline_test_function('timelineInitials');

	$result = workspace_conversation_timeline_test_node('function t(key) { var rest = Array.prototype.slice.call(arguments, 1); return key + "(" + rest.join(",") + ")"; }' . "\n" . $source . '
console.log(JSON.stringify({
	waits: [timelineWait(40, t), timelineWait(600, t), timelineWait(3900, t), timelineWait(90000, t)],
	initials: [timelineInitials("Mert Kaya"), timelineInitials("mert.kaya"), timelineInitials("Koordinatör console (uygulama)"), timelineInitials(""), timelineInitials("ışık")]
}));
');

	if ($result === null) {
		return;
	}

	pg_assert_same(array('ctl_sec(40)', 'ctl_min(10)', 'ctl_hour(1,5)', 'ctl_day(1,1)'), $result['waits'], 'waits in seconds, minutes, hours and days');
	pg_assert_same(array('MK', 'MK', 'KC', '?', 'I'), $result['initials'], 'two letters, an application\'s mark left out');
}

function test_workspace_conversation_timeline_screen_hooks()
{
	$screen = workspace_conversation_timeline_test_source('assets/js/workspace.js');
	$css = workspace_conversation_timeline_test_source('assets/css/backend.src.css');

	pg_assert_contains("window.PGWsConversationTimeline.mount(timelineHolder, channel, 'head'", $screen, 'the tabs row holds the strip');
	pg_assert_contains("window.PGWsConversationTimeline.mount(timelineHolder, channel, 'info'", $screen, 'Channel info holds it at its foot');
	pg_assert_contains('window.PGWsConversationTimeline.changed(self.channel.id, data.messages.length > 0)', $screen, 'a new message reads it again at once');
	pg_assert_contains('window.PGWsConversationTimeline.forget(channel.id)', $screen, 'a clear drops what it held');
	pg_assert_contains('timelineGo: function (messageId)', $screen, 'a chosen message opens in the conversation');
	pg_assert_contains('(window.PGWsConversationTimeline && !self.era)', $screen, 'never over an earlier version');
	pg_assert_contains('.ws-app.ws-ctl-narrow .ws-channel-card > .ws-ctl > *,', $css, 'a narrow tabs row hides it');
	pg_assert_contains('.ws-app:not(.ws-ctl-narrow) .ws-info-panel .ws-ctl-section {', $css, 'and only then Channel info shows it');
	pg_assert_contains('body.ws-keyboard .ws-channel-card > .ws-ctl {', $css, 'it waits while the phone keyboard is up');
	pg_assert_contains('.ws-ctl-turn.is-new {', $css, 'a new message comes onto it live');
}
