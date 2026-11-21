<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the conversation timeline (conversation_timeline.php,
 * assets/js/workspace_conversation_timeline.js): the first words cut on a
 * word, the card's shortcut rows (what came of a poll, a question, an
 * approval and an agent request, a file as its row, the tables written in a
 * message, a recording's length, the rows' order), the panel action, the
 * words of the strip; the strip's runs and turns, its layout in pixels, its
 * days and their dates, its zoom, the message under a point and the steps
 * between messages, runs and days, the waits in words, a writer's initials;
 * and the hooks in workspace.js and the stylesheet. The Node parts are
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

// The strip's one-line constants (var NAME = value;), for its pure parts.
function workspace_conversation_timeline_test_constants()
{
	preg_match_all('/^    var [A-Z_]+ = [^;\n]+;$/m', workspace_conversation_timeline_test_source('assets/js/workspace_conversation_timeline.js'), $match);

	return implode("\n", $match[0]) . "\n";
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
	pg_assert_same(lang(array('string' => '{var:1} leads', 'vars' => 'Second')), $open['name'], 'the row names the leading option');
	pg_assert_same(lang(array('string' => '{var:1} votes', 'vars' => 3)), $open['result'], 'the votes stand at the right');
	pg_assert_same('ok', $closed['state'], 'a closed poll with a winner is settled');
	pg_assert_contains('Second', $closed['name'], 'and names the winner');

	$tie = ws_conversation_timeline_poll_result(array(array('label' => 'A', 'votes' => 2), array('label' => 'B', 'votes' => 2)), false);

	pg_assert_same(lang('Tied'), $tie['name'], 'a tie leads with none');
	pg_assert_same('bad', ws_conversation_timeline_poll_result($options, false, true)['state'], 'a poll taken back');
	pg_assert_same(array(lang('No votes yet'), '', 'open'), array_values(ws_conversation_timeline_poll_result(array(), false)), 'a poll without votes waits');
	pg_assert_same(array(lang('Closed'), '', ''), array_values(ws_conversation_timeline_poll_result(array(), true)), 'a closed poll without votes is plain');
}

function test_workspace_conversation_timeline_question_result()
{
	$answered = ws_conversation_timeline_question_result(array(array('label' => 'Yes', 'votes' => 0), array('label' => 'No', 'votes' => 1)), false);

	pg_assert_same(array('No', lang('Answered'), 'ok'), array_values($answered), 'an answered question names its answer');
	pg_assert_same(array(lang('Awaiting an answer'), '', 'open'), array_values(ws_conversation_timeline_question_result(array(array('label' => 'Yes', 'votes' => 0)), false)), 'an open one waits');
	pg_assert_same('', ws_conversation_timeline_question_result(array(), true)['state'], 'a closed one without an answer is plain');
	pg_assert_same('bad', ws_conversation_timeline_question_result(array(), false, true)['state'], 'one taken back');
}

function test_workspace_conversation_timeline_approval_result()
{
	pg_assert_same('ok', ws_conversation_timeline_approval_result('approved', array())['state']);
	pg_assert_same('bad', ws_conversation_timeline_approval_result('rejected', array())['state']);
	pg_assert_same('bad', ws_conversation_timeline_approval_result('expired', array())['state']);

	$open = ws_conversation_timeline_approval_result('open', array('approved' => 1, 'pending' => 2));

	pg_assert_same('open', $open['state'], 'an open request waits');
	pg_assert_same('1 / 3', $open['result'], 'with who said yes of everyone asked');
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
	pg_assert_true(mb_strlen($tool['text']) <= 80, 'a name is cut to 80 characters');
	pg_assert_same('message', $tool['go'], 'a row leads to its message by default');
	pg_assert_same('', ws_conversation_timeline_tool('file', 'a.png', '', 'odd')['state'], 'an unknown state is plain');
	pg_assert_same('message', ws_conversation_timeline_tool('file', 'a', '', '', array('go' => 'elsewhere'))['go'], 'an unknown way leads to the message');
	pg_assert_same(array('note', 4), array_values(array_intersect_key(ws_conversation_timeline_tool('note', 'N', '', '', array('go' => 'note', 'id' => 4)), array('go' => 1, 'id' => 1))), 'a note opens itself by its id');

	$task = ws_conversation_timeline_task(array('id' => 7, 'title' => 'Fix it', 'status' => 'done'));

	pg_assert_same(array('task', 'Fix it', 'ok', 7, 'task'), array($task['k'], $task['text'], $task['state'], $task['id'], $task['go']), 'a done task is settled and opens itself');
}

function test_workspace_conversation_timeline_file_rows()
{
	if (!defined('PATH')) {
		define('PATH', '/');
	}

	$file = array('name' => 'abc.png', 'size' => 2048);
	$picture = ws_conversation_timeline_file(array('file_name' => 'shot.png'), $file);

	pg_assert_same(array('picture', 'shot.png', 'picture', '/abc.png'), array($picture['k'], $picture['text'], $picture['go'], $picture['url']), 'a picture opens in the picture window');

	$canvas = ws_conversation_timeline_file(array('file_name' => 'Weekly sales.png'), $file, array('version_no' => 3));

	pg_assert_same(array('canvas', 'Weekly sales', 'picture', '/abc.png?v=3'), array($canvas['k'], $canvas['text'], $canvas['go'], $canvas['url']), 'a drawing is a canvas row, named as its card, its newest picture');

	$page = ws_conversation_timeline_file(array('file_name' => 'Board.html'), array('name' => 'b.html', 'size' => 10), null, true);

	pg_assert_same(array('canvas', 'Board', 'message'), array($page['k'], $page['text'], $page['go']), 'a live page is a canvas row at its message');

	$voice = ws_conversation_timeline_file(array('file_name' => 'voice-20261121-101500.weba'), array('name' => 'v.weba', 'size' => 10), null, false, 42000);

	pg_assert_same(array('voice', lang('Voice message'), '0:42', 'message'), array($voice['k'], $voice['text'], $voice['result'], $voice['go']), 'a recording plays at its message, its length at the right');

	$sheet = ws_conversation_timeline_file(array('file_name' => 'orders.xlsx'), array('name' => 'o.xlsx', 'size' => 86016));

	pg_assert_same(array('file', 'orders.xlsx', 'file', '/o.xlsx'), array($sheet['k'], $sheet['text'], $sheet['go'], $sheet['url']), 'any other file opens its preview');
	pg_assert_true($sheet['result'] !== '', 'with its size');
}

function test_workspace_conversation_timeline_tables_and_order()
{
	$body = "Before\n| Name | Count |\n|---|---:|\n| a | 1 |\n| b | 2 |\n\n```\n| x | y |\n|---|---|\n| 1 | 2 |\n```\n| One |\n| --- |";
	$tables = ws_conversation_timeline_tables($body);

	pg_assert_same(2, count($tables), 'a table outside a code block is found, one inside is not');
	pg_assert_same(array('Name', 'Count'), $tables[0]['header'], 'its header cells');
	pg_assert_same(2, $tables[0]['rows'], 'and the rows under the dashes');
	pg_assert_same(0, $tables[1]['rows'], 'a header with no row is a table of none');
	pg_assert_same(array(), ws_conversation_timeline_tables('| not | a table |'), 'no dash row, no table');
	pg_assert_same(array('0:00', '0:42', '12:05', '1:02:09'), array_map('ws_conversation_timeline_duration', array(0, 41600, 725000, 3729000)), 'a recording\'s length as the player shows it');

	$rows = ws_conversation_timeline_order(array(
		ws_conversation_timeline_tool('poll'), ws_conversation_timeline_tool('file', 'b'), ws_conversation_timeline_tool('note'),
		ws_conversation_timeline_tool('file', 'a'), ws_conversation_timeline_tool('table'), ws_conversation_timeline_tool('task'),
	));

	pg_assert_same(array('note', 'task', 'file', 'file', 'table', 'poll'), array_column($rows, 'k'), 'what it shares first, then what it asks');
	pg_assert_same(array('b', 'a'), array_values(array_filter(array_map(function ($row) { return ($row['k'] === 'file') ? $row['text'] : null; }, $rows))), 'rows of one kind as they came');
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

	foreach (array('decision', 'note', 'task', 'canvas', 'picture', 'file', 'voice', 'table', 'poll', 'question', 'approval', 'ack', 'status', 'checkin', 'agent') as $kind) {
		pg_assert_true(isset($words['ctl_tool_' . $kind]), 'a card names the kind ' . $kind);
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

// The strip's constants and the pure parts named, for a Node script.
function workspace_conversation_timeline_test_parts($names)
{
	$source = workspace_conversation_timeline_test_constants();

	foreach ($names as $name) {
		$part = workspace_conversation_timeline_test_function($name);

		pg_assert_true($part !== '', $name . '() is in the script');
		$source .= $part;
	}

	return $source;
}

function test_workspace_conversation_timeline_js_layout()
{
	$source = workspace_conversation_timeline_test_parts(array('timelineRuns', 'timelineLayout', 'timelinePick', 'timelinePlace', 'timelineSpan', 'timelineAnchor', 'timelineIndexOf'));

	$result = workspace_conversation_timeline_test_node($source . '
var items = [];
for (var i = 0; i < 12; i++) { items.push({ id: 10 + i, at: i * 300 + ((i > 6) ? 7200 : 0), s: i % 3 }); }
var runs = timelineRuns(items, 120, 1800);
var layout = timelineLayout(runs, 5.2);
var out = timelineLayout(runs, 1);
var turns = [].concat.apply([], layout.runs.map(function (run) { return run.turns; }));
var outTurns = [].concat.apply([], out.runs.map(function (run) { return run.turns; }));
var first = layout.runs[0], second = layout.runs[1], last = second.turns[second.turns.length - 1];
var long = [{ id: 1, at: 0, s: 0 }, { id: 2, at: 50000, s: 1 }];
var longLayout = timelineLayout(timelineRuns(long, 120, 1800), 5.2);
console.log(JSON.stringify({
	runs: layout.runs.length,
	start: first.turns[0].left,
	apart: first.turns.every(function (turn, i) { return !i || turn.left === first.turns[i - 1].left + first.turns[i - 1].width + 1; }),
	ends: [first.turns[0].first, first.turns[0].last, last.last, first.turns.length > 1 ? first.turns[1].first : false],
	runWidth: first.width === (first.turns[first.turns.length - 1].left + first.turns[first.turns.length - 1].width - first.left),
	gap: second.left - (first.left + first.width),
	longGap: longLayout.runs[1].left - (longLayout.runs[0].left + longLayout.runs[0].width),
	width: layout.width === last.left + last.width + 8,
	least: Math.min.apply(null, turns.map(function (turn) { return turn.width; })),
	outLeast: Math.min.apply(null, outTurns.map(function (turn) { return turn.width; })),
	outGap: out.runs[1].left - (out.runs[0].left + out.runs[0].width),
	wider: timelineLayout(runs, 12).width > layout.width,
	days: [layout.days.length, layout.days[0].line, layout.days[0].from, layout.days[0].to],
	pickFirst: timelinePick(layout, 0).index,
	pickLast: timelinePick(layout, layout.width).index,
	away: timelinePick(layout, layout.width + 100).away > 100,
	place: (function () { var p = timelinePlace(layout, 0); return [p.left, p.right > p.left, p.x === (p.left + p.right) / 2]; })(),
	span: (function () { var s = timelineSpan(layout, 0, 11); return [s.left, s.right === last.left + last.width]; })(),
	anchor: (function () { var p = timelinePlace(layout, 3); var a = timelineAnchor(layout, (p.left + p.right) / 2); return [a.index, Math.round(a.share * 10) / 10]; })(),
	indexOf: [timelineIndexOf(items, 9), timelineIndexOf(items, 10), timelineIndexOf(items, 15), timelineIndexOf(items, 99)],
	empty: [timelineLayout([], 5).runs.length, timelineLayout([], 5).width]
}));
');

	if ($result === null) {
		return;
	}

	pg_assert_same(2, $result['runs'], 'two hours apart is two runs');
	pg_assert_same(6, $result['start'], 'the strip starts 6 pixels in');
	pg_assert_true($result['apart'], 'a run\'s turns stand 1 pixel apart');
	pg_assert_same(array(true, false, true, false), $result['ends'], 'only a run\'s two ends are marked for their rounding');
	pg_assert_true($result['runWidth'], 'a run is as wide as its turns');
	pg_assert_true(($result['gap'] >= 8) && ($result['gap'] <= 26), 'runs stand 8 pixels or more apart by the wait');
	pg_assert_same(26, $result['longGap'], 'a long wait stands at most 26 pixels');
	pg_assert_true($result['width'], 'the strip ends 8 pixels after its last turn');
	pg_assert_true($result['least'] >= 6, 'no turn narrower than 6 pixels');
	pg_assert_true(($result['outLeast'] >= 2) && ($result['outLeast'] < 6), 'fully zoomed out a turn may be 2 pixels');
	pg_assert_same(3, $result['outGap'], 'and runs 3 pixels apart');
	pg_assert_true($result['wider'], 'a larger scale lays it out wider');
	pg_assert_same(array(1, null, 0, 11), $result['days'], 'without a day change one column, no hairline');
	pg_assert_same(0, $result['pickFirst'], 'the left end is the first message');
	pg_assert_same(11, $result['pickLast'], 'the right end is the last');
	pg_assert_true($result['away'], 'a point off the strip says how far it is');
	pg_assert_same(array(6, true, true), $result['place'], 'a message has its share of its turn');
	pg_assert_same(array(6, true), $result['span'], 'the stretch from the first to the last message');
	pg_assert_same(array(3, 0.5), $result['anchor'], 'a point is its message and its share of it');
	pg_assert_same(array(-1, 0, 5, 11), $result['indexOf'], 'a message id finds itself or the one before');
	pg_assert_same(array(0, 0), $result['empty'], 'an empty conversation draws nothing');
}

function test_workspace_conversation_timeline_js_days()
{
	$source = workspace_conversation_timeline_test_parts(array('timelineRuns', 'timelineLayout', 'timelineDayText', 'timelineDayLeft', 'timelineJump'));

	$result = workspace_conversation_timeline_test_node($source . '
var day = function (at) { return "d" + Math.floor(at / 86400); };
var items = [
	{ id: 1, at: 2 * 86400 - 200, s: 0 }, { id: 2, at: 2 * 86400 - 140, s: 1 },
	{ id: 3, at: 2 * 86400 - 30, s: 1 }, { id: 4, at: 2 * 86400 + 10, s: 1 },
	{ id: 5, at: 2 * 86400 + 40, s: 0 }, { id: 6, at: 2 * 86400 + 4000, s: 0 },
	{ id: 7, at: 3 * 86400 + 5, s: 0 }
];
var runs = timelineRuns(items, 120, 1800, day);
var layout = timelineLayout(runs, 5.2);
var days = layout.days;
console.log(JSON.stringify({
	runs: runs.length,
	marked: runs.map(function (run) { return run.day; }),
	split: runs[1].turns[0].from,
	columns: days.map(function (d) { return [d.from, d.to, d.line === null]; }),
	before: days[1].line - days[0].end,
	after: days[1].first - days[1].line,
	text: [timelineDayText("16 Nov", "16", 40), timelineDayText("16 Nov", "16", 34), timelineDayText("16 Nov", "16", 20), timelineDayText("16 Nov", "16", 12), timelineDayText("Today", "", 20)],
	left: [timelineDayLeft({ first: 100, end: 300 }, 50), timelineDayLeft({ first: 100, end: 300 }, 150), timelineDayLeft({ first: 100, end: 300 }, 270)],
	runSteps: [timelineJump(layout, 0, "run", 1), timelineJump(layout, 4, "run", 1), timelineJump(layout, 4, "run", -1), timelineJump(layout, 3, "run", -1), timelineJump(layout, 0, "run", -1), timelineJump(layout, 6, "run", 1)],
	daySteps: [timelineJump(layout, 0, "day", 1), timelineJump(layout, 4, "day", 1), timelineJump(layout, 6, "day", -1), timelineJump(layout, 4, "day", -1)],
	plain: timelineRuns(items, 120, 1800).map(function (run) { return run.day; })
}));
');

	if ($result === null) {
		return;
	}

	pg_assert_same(4, $result['runs'], 'a new day starts a run even within half an hour');
	pg_assert_same(array(false, true, false, true), $result['marked'], 'every run that opens a day is marked');
	pg_assert_same(3, $result['split'], 'one writer across midnight is two turns');
	pg_assert_same(array(array(0, 2, true), array(3, 5, false), array(6, 6, false)), $result['columns'], 'each day a column with its first and last message, a hairline before all but the first');
	pg_assert_same(8, $result['before'], '8 pixels before a day\'s hairline');
	pg_assert_same(9, $result['after'], 'and the hairline and 8 pixels after it');
	pg_assert_same(array('16 Nov', '16 Nov', '16', '', ''), $result['text'], 'a date shortens to its day number under 34 pixels and goes under 13; today has no number');
	pg_assert_same(array(100, 172, 100), $result['left'], 'the date of a day cut at the left edge stands inside it while enough of the day is left');
	pg_assert_same(array(3, 5, 3, 0, 0, 6), $result['runSteps'], 'Shift and an arrow step a run');
	pg_assert_same(array(3, 6, 3, 3), $result['daySteps'], 'Page Up and Down step a day');
	pg_assert_same(array(false, false, false), $result['plain'], 'without a day the runs are as before');
}

function test_workspace_conversation_timeline_js_zoom()
{
	$source = workspace_conversation_timeline_test_parts(array('timelineRuns', 'timelineLayout', 'timelineZoomRange', 'timelineZoomStep', 'timelineZoomWheel', 'timelineFollow'));

	$result = workspace_conversation_timeline_test_node($source . '
var items = [];
for (var i = 0; i < 240; i++) { items.push({ id: i + 1, at: i * 60 + 4000 * Math.floor(i / 20), s: Math.floor(i / 4) % 2 }); }
var runs = timelineRuns(items, 120, 1800);
var range = timelineZoomRange(runs, 400);
var few = timelineZoomRange(timelineRuns(items.slice(0, 5), 120, 1800), 400);
var crowd = [];
for (var j = 0; j < 300; j++) { crowd.push({ id: j + 1, at: j * 200, s: j % 2 }); }
console.log(JSON.stringify({
	low: range.low < SCALE_OPEN,
	crowd: (function () { var cr = timelineRuns(crowd, 120, 1800); var low = timelineZoomRange(cr, 400).low; var least = timelineLayout(cr, 0.05).width; return [low > 0.05, timelineLayout(cr, low).width === least, timelineLayout(cr, low * 1.05).width > least, least > 400]; })(),
	fits: timelineLayout(runs, range.low).width <= 400,
	snug: timelineLayout(runs, range.low * 1.05).width > 400,
	top: range.top,
	few: few.low,
	none: timelineZoomRange([], 400).low,
	steps: [timelineZoomStep(4, 1, 1, 48), timelineZoomStep(6, -1, 1, 48), timelineZoomStep(36, 1, 1, 48), timelineZoomStep(1.4, -1, 1, 48), timelineZoomStep(48, 1, 1, 48), timelineZoomStep(1, -1, 1, 48)],
	wheel: [timelineZoomWheel(2, -100, 1, 48) > 2, timelineZoomWheel(2, 100, 1, 48) < 2, timelineZoomWheel(1, 500, 1, 48), timelineZoomWheel(47, -5000, 1, 48)],
	follow: [timelineFollow(0, 400, 100, 200), timelineFollow(0, 400, 900, 1000), timelineFollow(1000, 400, 100, 200), timelineFollow(0, 400, 380, 420), timelineFollow(0, 100, 300, 500)]
}));
');

	if ($result === null) {
		return;
	}

	pg_assert_true($result['low'], 'a long conversation zooms out past the opening scale');
	pg_assert_true($result['fits'] && $result['snug'], 'fully zoomed out it fills the well, the whole conversation in sight');
	pg_assert_same(48, $result['top'], 'it zooms in until a message is 48 pixels');
	pg_assert_same(array(true, true, true, true), $result['crowd'], 'a conversation of more turns than the well holds stops at the largest scale of its least strip, still wider than the well, so the first zoom in shows');
	pg_assert_same(5.2, $result['few'], 'a short conversation stands at the left at the opening scale, nothing to zoom out to');
	pg_assert_same(5.2, $result['none'], 'an empty one too');
	pg_assert_same(array(6, 4, 48, 1, 48, 1), $result['steps'], 'a step is half again, to the end when it would stop just short');
	pg_assert_same(array(true, true, 1, 48), $result['wheel'], 'the wheel and a pinch zoom smoothly within the ends');
	pg_assert_same(array(0, 750, 0, 200, 400), $result['follow'], 'the strip moves only to bring the part on the screen into view, its newest end when it is wider');
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
	pg_assert_same(array('MK', 'MK', 'KC', '?', 'IŞ'), $result['initials'], 'two letters, a one-word name its first two, an application\'s mark left out');
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
	pg_assert_contains('timelineOpen: function (go, row, messageId)', $screen, 'a shortcut row opens its thing');
	pg_assert_same(2, substr_count($screen, 'open: function (go, row, messageId) { self.timelineOpen(go, row, messageId); }'), 'from the strip in both places');
	pg_assert_contains("el('h3', 'ws-panel-kicker', t('ctl_heading'))", $screen, 'Channel info heads it "The conversation"');
	pg_assert_contains('(window.PGWsConversationTimeline && !self.era)', $screen, 'never over an earlier version');
	pg_assert_contains('.ws-app.ws-ctl-narrow .ws-channel-card > .ws-ctl > *,', $css, 'a narrow tabs row hides it');
	pg_assert_contains('.ws-app:not(.ws-ctl-narrow) .ws-info-panel .ws-ctl-section {', $css, 'and only then Channel info shows it');
	pg_assert_contains('body.ws-keyboard .ws-channel-card > .ws-ctl {', $css, 'it waits while the phone keyboard is up');
	pg_assert_contains('.ws-ctl-turn.is-new {', $css, 'a new message comes onto it live');
}
