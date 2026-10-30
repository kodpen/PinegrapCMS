<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the flows screen (workspace_flows.php, assets/js/workspace_flow.js):
 * its panel actions in api.php's table with the Workspace's gate, the texts
 * the canvas script reads (every key it uses handed over by
 * ws_flow_js_strings(), every English source a Turkish key, which
 * tools/check_lang.php does not read for ws_js_template()), the graph and
 * list entries the screen is answered with, and the run view: its actions go
 * through the runner's own checks and writes and answer with the API's run
 * presenters, which the panel can load, and the states it draws are the
 * runner's. The shares of a flow go through the share writers' own checks
 * and answer with the API's share presenter; every fl_ text handed over is
 * read by a script. What the run view and the runs list show when a read
 * fails is run under Node, with the script's own functions (skipped without
 * Node).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');

// Function definitions only: the panel table, the templates' assign rules
// the store reads, the store, the runner, the API's run presenters (behind
// the gate the API's own panel screens open), and ws_js_template() from the
// screen helpers.
if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_shares.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');

// The actions the canvas sends.
function ws_flows_canvas_test_actions()
{
	return array('ws_flow_list', 'ws_flow_get', 'ws_flow_create', 'ws_flow_save', 'ws_flow_publish', 'ws_flow_pause', 'ws_flow_bin',
		'ws_flow_run_start', 'ws_flow_runs_list', 'ws_flow_run_get', 'ws_flow_run_cancel',
		'ws_flow_shares', 'ws_flow_share_set', 'ws_flow_share_remove');
}

// Every action is a row of the Workspace family: its handler checks the
// session and the form token, the module decides who may do what.
function test_workspace_flows_canvas_actions_are_listed_with_the_workspace_gate()
{
	$actions = pg_panel_actions();

	foreach (ws_flows_canvas_test_actions() as $action) {
		pg_assert_true(isset($actions[$action]), $action . ' listed');

		if (!isset($actions[$action])) {
			continue;
		}

		pg_assert_same('workspace.php', $actions[$action]['file'], $action . ' file');
		pg_assert_same('pg_panel_ws', $actions[$action]['handler'], $action . ' handler');
		pg_assert_true($actions[$action]['exempt'] && $actions[$action]['token'] && $actions[$action]['write'], $action . ' exempt, token, write');
		pg_assert_same('exempt', pg_panel_gate($action), $action . ' gate');
	}
}

// Each action is answered by a case of ws_handle_action().
function test_workspace_flows_canvas_actions_have_a_case()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');

	foreach (ws_flows_canvas_test_actions() as $action) {
		pg_assert_contains("case '" . $action . "':", $source, $action);
	}
}

// The body of ws_flow_js_strings(), as written.
function ws_flows_canvas_test_strings_source()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
	$start = strpos($source, 'function ws_flow_js_strings()');
	$end = strpos($source, "\n}\n", (int) $start);

	return (($start !== false) && ($end !== false)) ? substr($source, $start, $end - $start) : '';
}

function test_workspace_flows_canvas_texts_are_translated()
{
	$body = ws_flows_canvas_test_strings_source();
	$keys = pg_test_language_map();

	pg_assert_true($body !== '', 'ws_flow_js_strings() found');
	pg_assert_true(count($keys) > 0, 'Turkish map read');

	preg_match_all("/(?:lang|ws_js_template)\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", $body, $matches);

	pg_assert_true(count($matches[1]) > 50, 'texts found');

	foreach ($matches[1] as $text) {
		$text = str_replace("\\'", "'", $text);
		pg_assert_true(is_array($keys) && array_key_exists($text, $keys), 'a Turkish key: ' . $text);
	}
}

// Every fl_ key the scripts read is handed over, the keyboard rows built
// from their names included.
function test_workspace_flows_canvas_script_keys_are_handed_over()
{
	$strings = ws_flow_js_strings();
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');
	$channels = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	// The run's flag, shared by the canvas and the channel screen.
	$channels .= (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow_flag.js');

	preg_match_all("/'(fl_[a-z_]+)'/", $script . $channels, $matches);

	$used = array_diff(array_unique($matches[1]), array('fl_key_'));

	foreach (array('tab', 'arrows', 'connect', 'delete', 'enter', 'zoom', 'pan', 'escape') as $key) {
		pg_assert_contains("'" . $key . "'", $script, 'keyboard row ' . $key);
		$used[] = 'fl_key_' . $key;
	}

	foreach ($used as $key) {
		pg_assert_true(isset($strings[$key]), 'handed over: ' . $key);
	}

	pg_assert_contains('{1}', $strings['fl_status_live'], 'the version number is a hole the script fills');
}

// A box's settings go out as an object, an end's empty ones too, so the
// script never meets a list where it reads keys.
function test_workspace_flows_canvas_graph_keeps_settings_objects()
{
	$graph = ws_flow_graph_normalize(array('nodes' => array(
		array('id' => 'manual_1', 'kind' => 'manual', 'x' => 0, 'y' => 0),
		array('id' => 'end_1', 'kind' => 'end', 'x' => 288, 'y' => 0),
	), 'edges' => array(array('from' => 'manual_1', 'to' => 'end_1'))));

	$json = json_encode(ws_flow_graph_present($graph));

	pg_assert_contains('"id":"end_1","kind":"end","label":"","x":288,"y":0,"config":{}', $json, 'end settings an object');
	pg_assert_contains('"config":{"subject":""}', $json, 'start settings');
	pg_assert_contains('"on":"next"', $json, 'the arrow leaves the start on next');
	pg_assert_contains('"notes":[]', $json, 'notes a list');
}

function test_workspace_flows_canvas_brief_is_typed()
{
	$flow = array('id' => '12', 'name' => 'Onboarding', 'status' => 'live', 'access' => 'run', 'owner_id' => '4', 'channel_id' => '9', 'draft_rev' => '3', 'version_id' => '30');
	$brief = ws_flow_brief(array('id' => 7), $flow, 2);

	pg_assert_same(array('id' => 12, 'name' => 'Onboarding', 'status' => 'live', 'access' => 'run', 'mine' => false, 'channel_id' => 9, 'draft_rev' => 3, 'version' => 2, 'start' => ''), $brief);
	pg_assert_true(ws_flow_brief(array('id' => 4), $flow, 0)['mine'], 'the owner\'s own');

	foreach (array('time', 'manual', 'event', 'join') as $start) {
		pg_assert_same($start, ws_flow_brief(array('id' => 7), $flow, 2, $start)['start'], 'start ' . $start);
	}
}

// A flow opened on the screen says how it starts, so the list keeps the kind
// after a save: the draft of a flow never published, by its start box.
function test_workspace_flows_canvas_detail_carries_the_start_kind()
{
	foreach (array('schedule' => 'time', 'manual' => 'manual', 'event' => 'event', 'join' => 'join') as $kind => $start) {
		$draft = array('v' => 1, 'nodes' => array(
			array('id' => 'start_1', 'kind' => $kind, 'x' => 0, 'y' => 0),
			array('id' => 'end_1', 'kind' => 'end', 'x' => 288, 'y' => 0),
		), 'edges' => array(array('from' => 'start_1', 'to' => 'end_1')));
		$flow = array('id' => '12', 'name' => 'Onboarding', 'status' => 'draft', 'access' => 'view', 'owner_id' => '4', 'channel_id' => '9',
			'draft_rev' => '3', 'version_id' => '0', 'draft' => json_encode($draft));

		pg_assert_same($start, ws_flow_detail(array('id' => 7), $flow)['start'], 'detail start of a ' . $kind . ' box');
	}

	$flow['draft'] = json_encode(array('v' => 1, 'nodes' => array(), 'edges' => array()));
	pg_assert_same('', ws_flow_detail(array('id' => 7), $flow)['start'], 'no start box: empty');
}

// The list action hands each flow its start kind, and the screen keeps it
// in a saved flow's entry; the flow list's chips narrow by it (the select
// they replaced is gone).
function test_workspace_flows_canvas_list_filters_by_start_kind()
{
	$actions = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$list = substr($actions, strpos($actions, "case 'ws_flow_list':"), 2000);

	pg_assert_contains("\$start['kind']", $list, 'the list hands each flow its start');

	$script = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');
	$entry = substr($script, strpos($script, 'function listEntry(flow)'), 400);

	pg_assert_contains('start: flow.start', $entry, 'a saved flow keeps its start kind');
	pg_assert_false(strpos($script, 'function startFilter()') !== false, 'no start select');

	foreach (array('time', 'manual', 'event', 'join', 'draft') as $chip) {
		pg_assert_contains("['" . $chip . "', 'fl_chip_", $script, 'a chip for ' . $chip);
	}
}

// The flow list: each chip counts the flows the search finds, a chip and
// the search narrow the rows, the chips are toggle buttons with the count
// in their name, each row's header a button that opens the flow, and a
// flow without a sentence says its kind.
function test_workspace_flows_canvas_list_chips_count_and_filter()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');
	preg_match('/^    var CHIPS = \[.*?\];$/ms', $source, $chips);
	preg_match('/^    var START_BOXES = .*?;$/m', $source, $boxes);
	pg_assert_true(!empty($chips) && !empty($boxes), 'the chips and start boxes are declared');

	$result = ws_flows_canvas_test_node(
		array('listCounts', 'listMatches', 'startSentence', 'drawRows', 'flowRow', 'statusBadge'),
		($chips[0] ?? '') . "\n" . ($boxes[0] ?? '') . "\n" . <<<'JS'
var KINDS = { manual: { name: 'fl_kind_manual' }, schedule: { name: 'fl_kind_schedule' }, event: { name: 'fl_kind_event' }, join: { name: 'fl_kind_join' } };
var LEVELS = { view: 'fl_level_view' };
var FL = { build: true };
K.flowIcon = function (kind) { return new FakeNode('svg', 'ws-flow-icon ' + kind); };
function runFlag(run) { return el('div', 'wsf-flag', 'flag ' + run.id); }
function refocus() {}
function openLastRun() {}
var opened = [];
function openFromList(flow) { opened.push(flow.id); return Promise.resolve(true); }
var flows = [
    { id: 1, name: 'Morning report', status: 'live', version: 3, start: 'time', start_text: 'Every day 09:00', channel_name: 'sales', mine: true, access: 'edit', last_run: { id: 9 } },
    { id: 2, name: 'Welcome', status: 'draft', version: 0, start: 'join', start_text: '', channel_name: '', mine: true, access: 'edit', last_run: null },
    { id: 3, name: 'Low stock', status: 'paused', version: 1, start: 'event', start_text: 'When a product runs low in stock', channel_name: 'shop', mine: false, access: 'view', last_run: null },
    { id: 4, name: 'Refund', status: 'draft', version: 0, start: 'manual', start_text: 'By hand', channel_name: 'sales', mine: true, access: 'edit', last_run: null },
    { id: 5, name: 'Empty', status: 'draft', version: 0, start: '', start_text: '', channel_name: '', mine: true, access: 'edit', last_run: null }
];
function ids(chip, query) { return flows.filter(function (flow) { return listMatches(flow, chip, query); }).map(function (flow) { return flow.id; }); }
state = { flows: flows, chip: '', query: '' };
ui.chips = new FakeNode('div');
ui.rows = new FakeNode('tbody');
ui.flowsNone = new FakeNode('p', 'd-none');
var out = {};
out.counts = listCounts(flows, '');
out.searched = listCounts(flows, 'SALES');
out.time = ids('time', '');
out.draft = ids('draft', '');
out.search = ids('', 'stock low');
out.searchChannel = ids('manual', 'sales');
out.sentences = flows.map(startSentence);
drawRows();
out.chips = ui.chips.children.map(function (chip) { return [textOf(chip), chip.getAttribute('aria-pressed')]; });
out.rows = ui.rows.children.length;
var first = ui.rows.children[0];
out.header = [first.children[0].tag, first.children[0].getAttribute('scope'), first.children[0].children[0].tag, textOf(first.children[0].children[0])];
out.cells = first.children.slice(1).map(function (cell) { return [cell.getAttribute('data-label'), textOf(cell)]; });
out.level = textOf(ui.rows.children[2].children[0]);
ui.chips.children[5].click();
out.afterChip = [state.chip, ui.rows.children.length, ui.chips.children[5].getAttribute('aria-pressed'), ui.chips.children[0].getAttribute('aria-pressed')];
state.query = 'nothing like it';
drawRows();
out.none = [ui.rows.children.length, ui.flowsNone.className, ui.flowsNone.textContent];
first.children[0].children[0].click();
out.opened = opened;
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_true(is_array($result), 'the script printed its result');

	if (!is_array($result)) {
		return;
	}

	pg_assert_same(array('' => 5, 'time' => 1, 'manual' => 1, 'event' => 1, 'join' => 1, 'draft' => 3), $result['counts'], 'every chip counts its flows');
	pg_assert_same(array('' => 2, 'time' => 1, 'manual' => 1, 'event' => 0, 'join' => 0, 'draft' => 1), $result['searched'], 'the counts follow the search');
	pg_assert_same(array(1), $result['time'], 'Time: flows started at a time');
	pg_assert_same(array(2, 4, 5), $result['draft'], 'Draft: the drafts, however they start');
	pg_assert_same(array(3), $result['search'], 'every word searched for, in any order, any case');
	pg_assert_same(array(4), $result['searchChannel'], 'the home channel is searched, within the chip');
	pg_assert_same(array('Every day 09:00', 'fl_kind_join', 'When a product runs low in stock', 'By hand', 'fl_list_no_start'), $result['sentences'], 'a flow without a sentence says its kind');
	pg_assert_same(array(array('fl_chip_all 5', 'true'), array('fl_chip_time 1', 'false'), array('fl_chip_manual 1', 'false'), array('fl_chip_event 1', 'false'), array('fl_chip_join 1', 'false'), array('fl_chip_draft 3', 'false')), $result['chips'], 'chips: toggle buttons, the count in the name');
	pg_assert_same(5, $result['rows'], 'a row per flow');
	pg_assert_same(array('th', 'row', 'button', 'Morning report'), $result['header'], 'the row\'s header is a button with the flow\'s name');
	pg_assert_same(array(
		array('fl_col_start', 'Every day 09:00'),
		array('fl_col_state', 'fl_status_live 3'),
		array('fl_flag_last', 'flag 9'),
		array('fl_col_channel', '#sales'),
	), $result['cells'], 'how it starts, state, last run, home channel, each named for stacked rows');
	pg_assert_contains('fl_level_view', $result['level'], 'somebody else\'s flow says what the person may do');
	pg_assert_same(array('draft', 3, 'true', 'false'), $result['afterChip'], 'a chip narrows the rows and is pressed');
	pg_assert_same(array(0, '', 'fl_list_none_match'), $result['none'], 'nothing found says so');
	pg_assert_same(array(1), $result['opened'], 'the row\'s button opens the flow');
}

// The palette is the editor's left column: the boxes in their groups, in
// the order start, values, logic, people and tasks, message, system, found
// by every word searched for; the start group names the flow's start once
// it has one. Nothing floats over the canvas any more.
function test_workspace_flows_canvas_palette_groups()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');
	preg_match('/^    var PALETTE_GROUPS = \[.*?\];$/ms', $source, $groups);
	preg_match('/^    var PALETTE = \[.*?\];$/ms', $source, $palette);
	preg_match('/^    var KINDS = \{.*?\n    \};$/ms', $source, $kinds);
	pg_assert_true(!empty($groups) && !empty($palette) && !empty($kinds), 'the groups, the palette and the kinds are declared');
	pg_assert_false(strpos($source, "ui.stage.appendChild(ui.palette)") !== false, 'the palette does not float over the canvas');
	pg_assert_false(strpos($source, 'listToggle') !== false, 'the flow list is no longer a column of the editor');

	$result = ws_flows_canvas_test_node(
		array('paletteGroups', 'paletteHeading', 'startNode', 'kindOf'),
		($groups[0] ?? '') . "\n" . ($palette[0] ?? '') . "\n" . ($kinds[0] ?? '') . "\n" . <<<'JS'
var names = { fl_add_manual: 'Started by hand', fl_kind_schedule: 'At a time', fl_add_task: 'Task', fl_kind_condition: 'Condition', fl_add_email: 'E-mail', fl_kind_webhook: 'Webhook', fl_kind_value: 'Value', fl_kind_constants: 'Constants', fl_add_end: 'End' };
t = function (key, value) { return (names[key] || key) + ((value === undefined) ? '' : ' ' + value); };
function shape(groups) { return groups.map(function (group) { return group.group + ':' + group.items.map(function (item) { return item[0]; }).join(','); }); }
state = { graph: { nodes: [] } };
var out = {};
out.order = shape(paletteGroups(PALETTE, '')).map(function (line) { return line.split(':')[0]; });
out.firstItems = shape(paletteGroups(PALETTE, '')).slice(0, 2);
out.search = shape(paletteGroups(PALETTE, 'TIME'));
out.words = shape(paletteGroups(PALETTE, 'by hand'));
out.none = paletteGroups(PALETTE, 'nothing like it').length;
out.heading = paletteHeading('start');
out.logic = paletteHeading('logic');
state.graph.nodes = [{ id: 'task_1', kind: 'task' }, { id: 'schedule_1', kind: 'schedule' }];
out.started = paletteHeading('start');
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_true(is_array($result), 'the script printed its result');

	if (!is_array($result)) {
		return;
	}

	pg_assert_same(array('start', 'values', 'logic', 'people', 'message', 'system'), $result['order'], 'groups in the plan\'s order');
	pg_assert_same(array('start:manual,schedule,event,join,row', 'values:constants,value'), $result['firstItems'], 'each group keeps the palette\'s order');
	pg_assert_same(array('start:schedule'), $result['search'], 'a search keeps the boxes whose name holds it, any case');
	pg_assert_same(array('start:manual'), $result['words'], 'every word searched for');
	pg_assert_same(0, $result['none'], 'nothing found: no group');
	pg_assert_same('fl_group_start', $result['heading'], 'no start box yet: the group\'s name');
	pg_assert_same('fl_group_logic', $result['logic'], 'the other groups their names');
	pg_assert_same('fl_palette_this_flow At a time', $result['started'], 'with a start: This flow: <kind>');
}

// The flow list says how each flow starts in a short sentence: a time box
// by its time alone ("Every day 09:00", no "from" date), any other start in
// the words the API's start_text gives; no start box, nothing.
function test_workspace_flows_canvas_list_start_sentence_per_kind()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_anchors.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/recurrence.php');

	$viewer = array('id' => 5, 'member' => true, 'role' => 3);
	$none = function () {
		return false;
	};
	$graph = function ($kind, $config = array()) {
		return ws_flow_graph_normalize(array(
			'nodes' => array(array('id' => 'start', 'kind' => $kind, 'config' => $config), array('id' => 'stop', 'kind' => 'end')),
			'edges' => array(array('from' => 'start', 'to' => 'stop')),
		));
	};
	$at = function ($repeat, $extra = array()) {
		return array('type' => 'at', 'date' => '2030-01-07', 'time' => '09:00', 'repeat' => $repeat) + $extra;
	};

	pg_assert_same(lang(array('string' => 'Every day {var:1}', 'vars' => '09:00')), ws_flow_start_short($at('daily')), 'daily');
	pg_assert_same(lang(array('string' => 'Every {var:1} days {var:2}', 'vars' => array(3, '09:00'))), ws_flow_start_short($at('daily', array('every' => 3))), 'every 3 days');
	pg_assert_same(lang(array('string' => 'Every working day {var:1}', 'vars' => '09:00')), ws_flow_start_short($at('workdays')), 'working days');
	pg_assert_same(lang(array('string' => 'Every {var:1} {var:2}', 'vars' => array(lang('Monday'), '09:00'))), ws_flow_start_short($at('weekly', array('days' => array(1)))), 'weekly');
	pg_assert_same(lang(array('string' => 'Every month on day {var:1} {var:2}', 'vars' => array(7, '09:00'))), ws_flow_start_short($at('monthly')), 'monthly');
	pg_assert_same(lang(array('string' => 'Every year on {var:1} {var:2}', 'vars' => array('07.01', '09:00'))), ws_flow_start_short($at('yearly')), 'yearly');
	pg_assert_same(lang('Every hour'), ws_flow_start_short($at('hourly')), 'hourly');
	pg_assert_same(lang(array('string' => 'Every {var:1} minutes', 'vars' => 15)), ws_flow_start_short($at('minutes', array('every' => 15))), 'minutes');
	pg_assert_same(lang(array('string' => 'On {var:1} at {var:2}', 'vars' => array('07.01.2030', '09:00'))), ws_flow_start_short($at('none')), 'once');
	pg_assert_same('', ws_flow_start_short(array('type' => 'join')), 'not a time: no short words');

	pg_assert_same(lang('By hand'), ws_flow_start_sentence($viewer, $graph('manual'), $none), 'by hand');
	pg_assert_same(lang(array('string' => 'Every day {var:1}', 'vars' => '09:00')), ws_flow_start_sentence($viewer, $graph('schedule', array('date' => '2030-01-07', 'time' => '09:00', 'repeat' => 'daily')), $none), 'a time box');
	pg_assert_same(lang('At a time'), ws_flow_start_sentence($viewer, $graph('schedule'), $none), 'a time box not filled in');
	pg_assert_same(lang('When somebody joins a public channel'), ws_flow_start_sentence($viewer, $graph('join'), $none), 'join');
	pg_assert_same(lang('When somebody joins a channel'), ws_flow_start_sentence($viewer, $graph('join', array('channel_id' => 7)), $none), 'a channel not read is not named');
	pg_assert_same('', ws_flow_start_sentence($viewer, ws_flow_graph_empty(), $none), 'no start box');
}

// The list action hands each flow its start sentence and its home
// channel's name, read for the whole list at once.
function test_workspace_flows_canvas_list_carries_sentence_and_channel()
{
	$actions = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$list = substr($actions, strpos($actions, "case 'ws_flow_list':"), 2000);

	pg_assert_contains('ws_flows_starts($viewer, $rows)', $list, 'starts read once for the list');
	pg_assert_contains("'start_text'", $list, 'the start sentence');
	pg_assert_contains("'channel_name'", $list, 'the home channel\'s name');
	pg_assert_contains('ws_flows_channel_names($viewer, $rows)', $list, 'channel names read once for the list');
}

// A save always names the draft it was made on: without draft_rev,
// ws_flow_save_check() would skip its "changed meanwhile" check and a stale
// screen would write over a draft saved elsewhere. Only the three fields a
// save may change are passed on.
function test_workspace_flows_canvas_save_fields_always_carry_draft_rev()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');

	pg_assert_same(array('draft_rev' => 0), ws_action_flow_fields(array()), 'nothing sent: draft_rev 0');
	pg_assert_same(array('draft_rev' => 0), ws_action_flow_fields(array('action' => 'ws_flow_save', 'token' => 'x', 'flow_id' => 7, 'status' => 'live')), 'other keys dropped');

	$graph = array('v' => 1, 'nodes' => array(), 'edges' => array(), 'notes' => array());

	pg_assert_same(
		array('draft_rev' => 5, 'name' => 'Onboarding', 'channel_id' => 3, 'graph' => $graph),
		ws_action_flow_fields(array('flow_id' => 7, 'draft_rev' => '5', 'name' => 'Onboarding', 'channel_id' => 3, 'graph' => $graph)),
		'name, channel and graph kept, draft_rev an integer'
	);
}

// A 409 from the store (a draft saved meanwhile elsewhere) reaches the screen
// as field changed_meanwhile, which its conflict banner listens for; any other
// refusal keeps the store's own field.
function test_workspace_flows_canvas_refusal_tells_a_conflict_apart()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');

	$conflict = ws_action_flow_refused(array('ok' => false, 'error' => 'Changed meanwhile.', 'status' => 409, 'field' => 'draft_rev'));
	$refused = ws_action_flow_refused(array('ok' => false, 'error' => 'Too large.', 'status' => 422, 'field' => 'graph'));

	pg_assert_same(array('status' => 'error', 'message' => 'Changed meanwhile.', 'field' => 'changed_meanwhile'), $conflict, '409');
	pg_assert_same(array('status' => 'error', 'message' => 'Too large.', 'field' => 'graph'), $refused, '422');
}

// A record's drawer draws no channel sidebar: the flows config tells it
// nothing, so the order, product and contact screens read nothing for it.
function test_workspace_flows_canvas_record_drawer_gets_no_flows_config()
{
	pg_assert_same(array('nav' => false), ws_flow_js_config(array('id' => 4, 'member' => true, 'role' => 3), 'record'));
}

// The body of one function of a file, as written.
function ws_flows_canvas_test_function_source($file, $name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, 'function ' . $name . '(');
	$end = ($start !== false) ? strpos($source, "\n}\n", $start) : false;

	return (($start !== false) && ($end !== false)) ? substr($source, $start, $end - $start) : '';
}

// The source of one case of ws_handle_action(), up to the next case.
function ws_flows_canvas_test_case_source($action)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$start = strpos($source, "case '" . $action . "':");
	$end = ($start !== false) ? strpos($source, "\n        case '", $start + 10) : false;

	return (($start !== false) && ($end !== false)) ? substr($source, $start, $end - $start) : '';
}

// The run actions decide nothing themselves: the runner's check-and-write
// functions refuse what they refuse (the API's run routes call the same),
// and the answer is the API's presenter.
function test_workspace_flows_canvas_run_actions_use_the_runner()
{
	$flow = "ws_flow_for(\$viewer, (int) (\$request['flow_id'] ?? 0), 'view')";
	$expected = array(
		'ws_flow_run_start'  => array($flow, 'ws_flow_run_start($viewer, $flow, ws_action_flow_run_fields($request))', 'ws_action_flow_run_answer('),
		'ws_flow_runs_list'  => array($flow, 'ws_flow_runs_list($viewer, $flow,', 'ws_action_flow_run_present('),
		'ws_flow_run_get'    => array('ws_action_flow_run_answer(', 'ws_scheduled_due()'),
		'ws_flow_run_cancel' => array('ws_flow_run_for($viewer,', 'ws_flow_run_cancel($viewer, $run)', 'ws_action_flow_run_answer('),
	);

	foreach ($expected as $action => $needles) {
		$case = ws_flows_canvas_test_case_source($action);

		pg_assert_true($case !== '', $action . ' case found');

		foreach ($needles as $needle) {
			pg_assert_contains($needle, $case, $action . ': ' . $needle);
		}

		pg_assert_false(strpos($case, 'ws_flow_access(') !== false, $action . ' asks no access of its own');
	}

	pg_assert_contains('ws_api_flow_run_present(', ws_flows_canvas_test_function_source('includes/workspace/actions.php', 'ws_action_flow_run_present'), 'the API presenter answers');
	pg_assert_contains('ws_flow_run_for(', ws_flows_canvas_test_function_source('includes/workspace/actions.php', 'ws_action_flow_run_answer'), 'a run is read again through its access');
}

// A start passes on only the client's token, sent as start_token, and as it
// came: a token of the wrong shape meets ws_flow_run_start_check()'s refusal
// rather than being dropped, which would start a run without one. The
// request's `token` is the CSRF token api.php checks and never becomes the
// run's (64 hex characters, over the runner's 56, or one start key for every
// start of a session).
function test_workspace_flows_canvas_run_start_fields_carry_only_the_token()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');

	$csrf = str_repeat('ab', 32);

	pg_assert_same(array(), ws_action_flow_run_fields(array('flow_id' => 5, 'action' => 'ws_flow_run_start')), 'no token sent');
	pg_assert_same(array(), ws_action_flow_run_fields(array('flow_id' => 5, 'action' => 'ws_flow_run_start', 'token' => $csrf)), 'the CSRF token is not a run token');
	pg_assert_same(array('token' => 'canvas.0a1b'), ws_action_flow_run_fields(array('flow_id' => 5, 'token' => $csrf, 'start_token' => 'canvas.0a1b', 'status' => 'done')), 'the start token alone');
	pg_assert_same(array('token' => array('x')), ws_action_flow_run_fields(array('start_token' => array('x'))), 'a wrong shape passed on as it came');
	pg_assert_false(ws_flow_start_token_valid(ws_action_flow_run_fields(array('start_token' => array('x')))['token']), 'which the runner refuses');
	pg_assert_true(ws_flow_start_token_valid('canvas.' . str_repeat('0f', 12)), 'the canvas token shape is valid');
	pg_assert_false(ws_flow_start_token_valid($csrf), 'a CSRF token is not one');
}

// The canvas sends its start token under its own name, never as `token`,
// which workspace.js's api() fills with the CSRF token and a data key of the
// same name would overwrite.
function test_workspace_flows_canvas_run_start_sends_start_token()
{
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');
	$start = strpos($script, "K.api('ws_flow_run_start', {");
	$call = ($start !== false) ? substr($script, $start, (int) strpos($script, '}', $start) - $start) : '';

	pg_assert_true($call !== '', 'the start request found');
	pg_assert_contains('start_token:', $call, 'the start token under its own name');
	pg_assert_false((bool) preg_match('/[{,]\s*token\s*:/', $call), 'no data key named token');
}

// The times of a run as the API writes them (api_time()), from a function the
// panel has: 0 is no time.
function test_workspace_flows_canvas_run_times_are_iso_utc()
{
	pg_assert_same(null, ws_flow_time(0), 'none');
	pg_assert_same(null, ws_flow_time(''), 'empty');
	pg_assert_same('2026-10-14T17:46:40Z', ws_flow_time(1792000000), 'a moment');
	pg_assert_same('2026-10-14T17:46:40Z', ws_flow_time('1792000000'), 'as the row keeps it');

	foreach (array('ws_api_flow_run_present', 'ws_api_flow_run_step_present') as $presenter) {
		$body = ws_flows_canvas_test_function_source('includes/workspace/api_flows.php', $presenter);

		pg_assert_true($body !== '', $presenter . ' found');
		pg_assert_false(strpos($body, 'api_time(') !== false, $presenter . ' needs nothing only the API loads');
	}
}

// A run and its boxes as the canvas is answered: the API's shape, field for
// field, built without the database.
function test_workspace_flows_canvas_run_answer_is_the_api_shape()
{
	$graph = ws_flow_graph_normalize(array('nodes' => array(
		array('id' => 'manual_1', 'kind' => 'manual', 'label' => 'Return asked', 'x' => 0, 'y' => 0),
		array('id' => 'task_1', 'kind' => 'task', 'label' => 'Take it back', 'x' => 288, 'y' => 0, 'config' => array('title' => 'Take it back')),
		array('id' => 'end_1', 'kind' => 'end', 'x' => 576, 'y' => 0),
	), 'edges' => array(array('from' => 'manual_1', 'to' => 'task_1'), array('from' => 'task_1', 'to' => 'end_1', 'on' => 'done'))));
	$run = array('id' => '12', 'flow_id' => '7', 'version_id' => '30', 'status' => 'waiting', 'access' => 'read', 'started_by' => '4', 'actor_id' => '1',
		'context' => json_encode(array('trigger' => 'manual', 'node' => 'manual_1', 'channel_id' => 11, 'flow_name' => 'Returns')), 'boxes_run' => '1',
		'started_at' => '1792000000', 'updated_at' => '1792000060', 'finished_at' => '0');
	$steps = array(
		array('node_id' => 'manual_1', 'status' => 'done', 'outcome' => 'next', 'effect' => '', 'attempt' => '1', 'ready_at' => '1792000000', 'claimed_at' => '1792000000', 'finished_at' => '1792000000', 'result' => ws_flow_result_pack('Started by Ayşe.')),
		array('node_id' => 'task_1', 'status' => 'waiting', 'outcome' => '', 'effect' => 'task:41', 'attempt' => '1', 'ready_at' => '1792000000', 'claimed_at' => '1792000001', 'finished_at' => '0', 'result' => ws_flow_result_pack('task T-41 opened', array('task_id' => 41))),
	);

	$full = ws_api_flow_run_present(array('id' => 4), $run, 2, $steps, $graph);
	$brief = ws_api_flow_run_present(array('id' => 9), $run, 2);

	pg_assert_same(array_keys(ws_api_flow_run_schema()), array_keys($full), 'run fields');
	pg_assert_same(array_keys(ws_api_flow_run_step_schema()), array_keys($full['steps'][1]), 'box fields');
	pg_assert_same(array(12, 'waiting', 11, '2026-10-14T17:46:40Z', null), array($full['id'], $full['status'], $full['channel_id'], $full['started_at'], $full['finished_at']), 'typed, times in UTC');
	pg_assert_true($full['can_cancel'], 'its starter may cancel it');
	pg_assert_false($brief['can_cancel'], 'another reader may not');
	pg_assert_same(null, $brief['steps'], 'a listing carries no boxes');
	pg_assert_same('task T-41 opened', $full['result'], 'the flag shows the open box, the latest');
	pg_assert_same('', $brief['result'], 'a listing gets its results passed in');
	pg_assert_same('Done.', ws_api_flow_run_present(array('id' => 9), $run, 2, null, null, 'Done.')['result']);
	pg_assert_same(array('task_1', 'waiting', 41, 'task T-41 opened', '2026-10-14T17:46:41Z'), array($full['steps'][1]['node_id'], $full['steps'][1]['status'], $full['steps'][1]['task_id'], $full['steps'][1]['result'], $full['steps'][1]['started_at']), 'the waiting box and its task');
	pg_assert_contains('"config":{}', json_encode($full['graph']), 'the graph as the canvas reads it');
}

// The states the canvas draws for a run and its boxes are the runner's, and
// a box the run has not reached yet has its own word.
function test_workspace_flows_canvas_run_states_are_the_runners()
{
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');
	$read = function ($name) use ($script) {
		$start = strpos($script, 'var ' . $name . ' = {');
		$end = ($start !== false) ? strpos($script, '};', $start) : false;

		preg_match_all('/^\s+([a-z]+): \[/m', (($start !== false) && ($end !== false)) ? substr($script, $start, $end - $start) : '', $found);

		return $found[1];
	};

	pg_assert_same(array_keys(ws_flow_run_statuses()), $read('RUN_STATES'), 'run states');
	pg_assert_same(array_merge(array_keys(ws_flow_step_statuses()), array('none')), $read('STEP_STATES'), 'box states, and none');
}

// Every fl_ text ws_flow_js_strings() hands over is read by a script: by its
// literal name, or as a keyboard row built from its name.
function test_workspace_flows_canvas_every_text_is_read()
{
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');
	$channels = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	// The run's flag, shared by the canvas and the channel screen.
	$channels .= (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow_flag.js');

	preg_match_all("/'(fl_[a-z_]+)'\\s*=>/", ws_flows_canvas_test_strings_source(), $handed);

	pg_assert_true(count($handed[1]) > 50, 'keys found');
	pg_assert_same(array_keys(ws_flow_js_strings()), $handed[1], 'every key written once, in order');

	foreach ($handed[1] as $key) {
		$row = (strpos($key, 'fl_key_') === 0) && (strpos($script, "'" . substr($key, 7) . "'") !== false);

		pg_assert_true($row || (strpos($script . $channels, "'" . $key . "'") !== false), 'read by a script: ' . $key);
	}

	foreach (array('fl_share', 'fl_shared_with', 'fl_level_view', 'fl_level_run', 'fl_level_edit') as $key) {
		pg_assert_true(isset(ws_flow_js_strings()[$key]), 'handed over: ' . $key);
	}
}

// The share actions decide nothing themselves: the share writers' checks
// refuse what they refuse (the API's share routes call the same), and the
// list is the store's, in the API's shape.
function test_workspace_flows_canvas_share_actions_use_the_share_writers()
{
	$flow = "ws_flow_for(\$viewer, (int) (\$request['flow_id'] ?? 0), 'view')";
	$expected = array(
		'ws_flow_shares'       => array($flow, 'ws_action_flow_shares_answer('),
		'ws_flow_share_set'    => array($flow, 'ws_flow_share_set($viewer, $flow, ws_action_flow_share_fields($request))', 'ws_action_flow_shares_answer('),
		'ws_flow_share_remove' => array($flow, "ws_flow_share_remove(\$viewer, \$flow, (int) (\$request['share_id'] ?? 0))", 'ws_action_flow_shares_answer('),
	);

	foreach ($expected as $action => $needles) {
		$case = ws_flows_canvas_test_case_source($action);

		pg_assert_true($case !== '', $action . ' case found');

		foreach ($needles as $needle) {
			pg_assert_contains($needle, $case, $action . ': ' . $needle);
		}

		pg_assert_false(strpos($case, 'ws_flow_access(') !== false, $action . ' asks no access of its own');
	}

	$answer = ws_flows_canvas_test_function_source('includes/workspace/actions.php', 'ws_action_flow_shares_answer');

	pg_assert_contains('ws_flow_shares_list($viewer, $flow)', $answer, 'the store lists them');
	pg_assert_contains('ws_api_flow_share_present', $answer, 'in the API\'s shape');
	pg_assert_contains('ws_flow_share_manager($viewer, $flow)', $answer, 'with whether the person manages them');
}

// Only what a share may carry is passed on: the kind, the id and the level.
function test_workspace_flows_canvas_share_fields()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');

	pg_assert_same(array('target_type' => '', 'target_id' => 0, 'level' => 'view'), ws_action_flow_share_fields(array()), 'nothing sent');
	pg_assert_same(
		array('target_type' => 'user', 'target_id' => 12, 'level' => 'edit'),
		ws_action_flow_share_fields(array('flow_id' => 4, 'token' => 'x', 'target_type' => 'user', 'target_id' => '12', 'level' => 'edit', 'owner_id' => 9)),
		'the three fields, the id an integer'
	);
}

// The canvas sends the share actions and draws a share's level with the
// level texts, and shows the dialog's controls to whoever manages the shares.
function test_workspace_flows_canvas_share_dialog_is_drawn_by_the_script()
{
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');

	// The list is read with ws_flow_shares; every change goes through
	// shareRequest(), which sends it and draws the list from the answer.
	foreach (array("K.api('ws_flow_shares'", "shareRequest('ws_flow_share_set'", "shareRequest('ws_flow_share_remove'") as $call) {
		pg_assert_contains($call, $script);
	}

	$start = strpos($script, 'function shareRequest(');
	$request = ($start !== false) ? substr($script, $start, (int) strpos($script, "\n    }\n", $start) - $start) : '';

	pg_assert_contains('K.api(action, data)', $request, 'shareRequest() sends the action');
	pg_assert_contains('share.data = answer', $request, 'and keeps the list it answers');

	pg_assert_contains('data.manage', $script, 'the controls follow the answer');
	pg_assert_contains('flow.manage', $script, 'the menu item follows the flow');
}

// One top-level function of workspace_flow.js, as written: from its line to
// the closing brace at its own indent ('' when it is not there).
function ws_flows_canvas_test_script_function($script, $name)
{
	if (!preg_match('/^( *)function ' . preg_quote($name, '/') . '\(/m', $script, $found, PREG_OFFSET_CAPTURE)) {
		return '';
	}

	$close = "\n" . $found[1][0] . "}\n";
	$end = strpos($script, $close, $found[0][1]);

	return ($end !== false) ? substr($script, $found[0][1], $end - $found[0][1] + strlen($close)) : '';
}

// Stand-ins for what the canvas functions read of the page and of the
// Workspace kit: elements that keep their children, text and listeners,
// K.api answering from a queue, timers kept to be fired by hand, t() that
// gives the key with its values.
function ws_flows_canvas_test_stage()
{
	return <<<'JS'
var calls = [], answers = [], failed = [], announced = [], timers = [];
function FakeNode(tag, className, text) {
    var self = this;
    this.tag = tag;
    this.className = className || '';
    this.textContent = ((text === undefined) || (text === null)) ? '' : String(text);
    this.children = [];
    this.attrs = {};
    this.listeners = {};
    this.disabled = false;
    this.classList = {
        add: function (name) { self.mark(name, true); },
        remove: function (name) { self.mark(name, false); },
        toggle: function (name, on) { self.mark(name, (on === undefined) ? !self.classList.contains(name) : !!on); },
        contains: function (name) { return (' ' + self.className + ' ').indexOf(' ' + name + ' ') !== -1; }
    };
}
FakeNode.prototype.mark = function (name, on) {
    var list = this.className.split(/\s+/).filter(function (item) { return item && (item !== name); });
    if (on) { list.push(name); }
    this.className = list.join(' ');
};
FakeNode.prototype.appendChild = function (child) { this.children.push(child); return child; };
FakeNode.prototype.setAttribute = function (name, value) { this.attrs[name] = String(value); };
FakeNode.prototype.getAttribute = function (name) { return Object.prototype.hasOwnProperty.call(this.attrs, name) ? this.attrs[name] : null; };
FakeNode.prototype.addEventListener = function (type, fn) { (this.listeners[type] = this.listeners[type] || []).push(fn); };
FakeNode.prototype.click = function () { (this.listeners.click || []).forEach(function (fn) { fn({ button: 0 }); }); };
FakeNode.prototype.querySelector = function () { return null; };
FakeNode.prototype.querySelectorAll = function () { return []; };
FakeNode.prototype.contains = function () { return false; };
function textOf(node) { return [node.textContent].concat(node.children.map(textOf)).join(' ').replace(/\s+/g, ' ').trim(); }
function buttonsOf(node) { return node.children.reduce(function (all, child) { return all.concat(buttonsOf(child)); }, (node.tag === 'button') ? [node] : []); }
var el = function (tag, className, text) { return new FakeNode(tag, className, text); };
var t = function () { return Array.prototype.slice.call(arguments).join(' '); };
var K = {
    el: el,
    clear: function (node) { node.children = []; node.textContent = ''; return node; },
    icon: function (name) { return new FakeNode('i', 'bi ' + (name || '')); },
    button: function (className, label) { return new FakeNode('button', className, label); },
    fail: function (error) { failed.push(error && error.message); },
    toast: function () {},
    api: function (action) { calls.push(action); var next = answers.shift(); return next ? next() : new Promise(function () {}); }
};
var window = {
    setTimeout: function (fn, ms) { timers.push({ fn: fn, ms: ms, live: true }); return timers.length; },
    clearTimeout: function (id) { if (id && timers[id - 1]) { timers[id - 1].live = false; } }
};
var document = { visibilityState: 'visible', activeElement: null, body: new FakeNode('body') };
var ui = { root: new FakeNode('div'), runBox: new FakeNode('div', 'wsf-run-bar d-none'), side: new FakeNode('aside'), app: new FakeNode('div'), outlineBox: new FakeNode('div'), boxes: new FakeNode('div') };
var state = {};
function live() { return timers.filter(function (timer) { return timer.live; }).map(function (timer) { return timer.ms; }); }
function fire() { var timer = timers.filter(function (item) { return item.live; }).pop(); timer.live = false; timer.fn(); }
function settle() { return new Promise(function (resolve) { setImmediate(resolve); }); }
// A refusal read from the server's JSON answer, as workspace.js's api() throws it.
function refused(message) { var error = new Error(message); error.field = ''; error.code = ''; error.stale = false; return Promise.reject(error); }
function keepFocus(draw) { draw(); }
function announce(text) { announced.push(text); }
function runBadge(states, status) { return el('span', 'wsf-run-badge', status); }
function personName(id) { return 'person ' + id; }
function when(value) { return String(value); }
var RUN_STATES = {};
JS;
}

// Runs the functions named, cut from workspace_flow.js as written (its
// POLL_* timings with them), after the stand-ins and before $script, and
// returns what $script prints as JSON. null without Node.
function ws_flows_canvas_test_node(array $names, $script)
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

	$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js'));
	$body = ws_flows_canvas_test_stage() . "\n";

	preg_match_all('/^ *var POLL_[A-Z_]+ = [0-9.]+;$/m', $source, $timings);
	$body .= implode("\n", $timings[0]) . "\n";

	foreach ($names as $name) {
		$function = ws_flows_canvas_test_script_function($source, $name);

		pg_assert_true($function !== '', 'workspace_flow.js has ' . $name . '()');
		$body .= $function . "\n";
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgwsflow');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $body . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// A look at an open run that the server refuses (the flow binned with its
// runs, the channel no longer read, the session ended: a JSON answer, which
// api() gives a code) is final: no further look, and the bar over the canvas
// says why and that the run is no longer followed. A look that got no answer
// (a network failure, a server error page without JSON) is tried again,
// later each time, with a word in the bar that goes once a look succeeds.
// Before, a refusal stopped the looks without a word, and the run kept
// showing as still going with its Cancel.
function test_workspace_flows_canvas_run_poll_failures_are_told()
{
	$result = ws_flows_canvas_test_node(
		array('runOpen', 'stopPoll', 'schedulePoll', 'pollRun', 'pollDone', 'pollResume', 'pollFailed', 'drawRunBar', 'runSide'),
		<<<'JS'
function applyRun(run) { state.run = run; return false; }
function drawRunViews() { drawRunBar(); K.clear(ui.side); runSide(); }
function closeRun() {}
function tick() {}
function fresh() {
    state = { run: { id: 12, flow_id: 7, status: 'running', version: 2, can_cancel: true, started_by: 4, started_at: 'a', finished_at: null, steps: [] }, poll: { timer: 0, delay: POLL_FIRST, busy: 0 }, outline: true, refocus: '' };
    timers = [];
    announced = [];
    K.clear(ui.runBox);
    K.clear(ui.side);
}
(async function () {
    var out = {};

    fresh();
    answers.push(function () { return refused('That run could not be found.'); });
    pollRun();
    await settle();
    out.refused = { looks: live(), bar: textOf(ui.runBox), side: textOf(ui.side), sideButtons: buttonsOf(ui.side).map(textOf), told: announced.length, cancel: state.run.can_cancel };
    schedulePoll(0);
    out.refused.again = live();

    fresh();
    answers.push(function () { return Promise.reject(new TypeError('Failed to fetch')); });
    pollRun();
    await settle();
    out.unreached = { looks: live(), bar: textOf(ui.runBox), told: announced.length };
    answers.push(function () { return Promise.reject(new Error('Something went wrong.')); });
    fire();
    await settle();
    out.unreached.second = live();
    out.unreached.toldOnce = announced.length;
    answers.push(function () { return Promise.resolve({ run: state.run, due: false }); });
    fire();
    await settle();
    out.unreached.healed = textOf(ui.runBox);
    out.unreached.next = live().length;

    console.log(JSON.stringify(out));
})();
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_true(is_array($result), 'the script printed its result');

	if (!is_array($result)) {
		return;
	}

	pg_assert_same(array(), $result['refused']['looks'], 'a refusal is not read again');
	pg_assert_contains('That run could not be found.', $result['refused']['bar'], 'the bar says why');
	pg_assert_contains('fl_run_lost', $result['refused']['bar'], 'and that the run is no longer followed');
	pg_assert_true($result['refused']['told'] > 0, 'screen readers are told');
	pg_assert_false($result['refused']['cancel'], 'a run that cannot be read offers no Cancel');
	pg_assert_false(in_array('fl_run_cancel', $result['refused']['sideButtons'], true), 'nor does its side panel');
	pg_assert_false(strpos($result['refused']['side'], 'fl_run_open') !== false, 'the side panel no longer says it is still going');
	pg_assert_contains('fl_run_lost', $result['refused']['side'], 'but that it is shown as last read');
	pg_assert_same(array(), $result['refused']['again'], 'a tab shown again or the screen\'s tick does not read it again');

	pg_assert_same(array(4000), $result['unreached']['looks'], 'no answer: read again later');
	pg_assert_contains('fl_run_unreached', $result['unreached']['bar'], 'the bar says it is tried again');
	pg_assert_same(1, $result['unreached']['told'], 'screen readers are told');
	pg_assert_same(array(8000), $result['unreached']['second'], 'a server error page waits longer still');
	pg_assert_same(1, $result['unreached']['toldOnce'], 'told once while it lasts');
	pg_assert_false(strpos($result['unreached']['healed'], 'fl_run_unreached') !== false, 'the word goes once a look succeeds');
	pg_assert_same(1, $result['unreached']['next'], 'and the run keeps being read');
}

// The runs list that could not be loaded says so, with the reason and a
// way to load it again, instead of "This flow has not run yet."
function test_workspace_flows_canvas_runs_list_failure_is_told()
{
	$result = ws_flows_canvas_test_node(
		array('loadRuns', 'runsSide'),
		<<<'JS'
function drawSettings() { K.clear(ui.side); runsSide(); }
function openRun() {}
state = { flow: { id: 7 }, panel: 'runs', run: null, selected: null, outline: false, opening: {}, refocus: '', runs: { flow: 7, items: [], next: null, loading: false } };
(async function () {
    var out = {};

    answers.push(function () { return refused('That flow could not be found.'); });
    loadRuns(false);
    await settle();
    drawSettings();
    out.failed = textOf(ui.side);
    var retry = buttonsOf(ui.side);
    out.buttons = retry.length;
    answers.push(function () { return Promise.resolve({ runs: [{ id: 31, flow_id: 7, status: 'done', version: 2, started_by: 4, started_at: 'a', finished_at: 'b' }], next: null }); });

    if (retry.length) {
        retry[0].click();
    }

    out.again = textOf(ui.side);
    await settle();
    drawSettings();
    out.loaded = textOf(ui.side);
    out.calls = calls.length;

    console.log(JSON.stringify(out));
})();
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_true(is_array($result), 'the script printed its result');

	if (!is_array($result)) {
		return;
	}

	pg_assert_false(strpos($result['failed'], 'fl_runs_none') !== false, 'a failed load is not "has not run yet"');
	pg_assert_contains('That flow could not be found.', $result['failed'], 'the reason');
	pg_assert_contains('fl_retry', $result['failed'], 'a way to load it again');
	pg_assert_same(1, $result['buttons'], 'one button');
	pg_assert_contains('loading', $result['again'], 'loading again shows at once');
	pg_assert_contains('fl_run_name 31', $result['loaded'], 'then the runs');
	pg_assert_same(2, $result['calls'], 'read twice');
}

// The value a condition box compares a record's field with is kept as the
// record keeps it, as the scheduled form sends it: an amount typed as 100 is
// 10000 minor units (and 10000 is shown as 100.00), yes or no '1' or '0', a
// day YYYY-MM-DD, a moment YYYY-MM-DD HH:MM:00, a decimal with a dot. What
// was typed used to be kept as it was, so "Price is more than 100" held for
// any price over 1.00. A count measured against a number asks for that
// number, 5 to start with, as the scheduled form does; it used to stay 0,
// which counts only the products out of stock.
function test_workspace_flows_canvas_condition_values_are_kept_as_the_field_holds_them()
{
	$result = ws_flows_canvas_test_node(
		array('field', 'textInput', 'stepLists', 'changeInput', 'channelPick', 'conditionValue', 'conditionCompare', 'conditionMetric'),
		<<<'JS'
var uid = 0;
var FL = {};
var BOOT = { channels: [] };
state = { edit: true };
K.select = function (options, value) {
    var node = new FakeNode('select');
    options.forEach(function (option) {
        var item = new FakeNode('option', '', option[1]);
        item.value = String(option[0]);
        node.appendChild(item);
    });
    node.value = String(value);
    return node;
};
function refreshNode() {}
function drawSettings() {}
// The controls of the fields drawn into a box, in order.
function controls(box) { return box.children.map(function (wrap) { return wrap.children[1]; }); }
function enter(control, value) {
    control.value = value;
    (control.listeners[(control.tag === 'select') ? 'change' : 'input'] || []).forEach(function (fn) { fn(); });
}
// A field of a kind compared by op: the value control as drawn for what is
// kept, then what is kept once value is entered into it.
function compare(kind, op, kept, value) {
    var box = new FakeNode('div');
    var rule = { type: 'record', record: 'product', id: 12, field: 'f', op: op, value: kept };
    conditionCompare(box, { config: { rule: rule } }, rule, [[op, op]], { kind: kind, label: 'F', values: [], labels: {} });
    var control = controls(box)[1];
    var out = { tag: control ? control.tag : '', type: control ? (control.type || '') : '', shown: control ? control.value : null, options: control ? control.children.map(function (item) { return item.value; }) : [] };
    if (control) {
        enter(control, value);
    }
    out.kept = rule.value;
    return out;
}
var out = {
    money: compare('money', 'gt', '10000', '100'),
    cents: compare('money', 'lt', '', '12.5'),
    yes: compare('bool', 'eq', '', '1'),
    no: compare('bool', 'eq', '1', '0'),
    day: compare('date', 'gt', '', '2026-11-01'),
    moment: compare('datetime', 'lt', '2026-10-26 14:30:00', '2026-11-01T09:15'),
    decimal: compare('decimal', 'gte', '', '18,5'),
    text: compare('text', 'contains', '', 'Ankara'),
    days: compare('date', 'within', '7', '10'),
    overdue: compare('date', 'overdue', '', '')
};
FL.steps = {
    metrics: [{ key: 'tasks_open', label: 'Open tasks', scope: 'channel' }, { key: 'stock_low', label: 'Products low in stock', scope: 'threshold' }],
    counts: ['gt'],
    operators: { gt: 'is more than' }
};
var rule = { type: 'metric', metric: '', param: 0, op: 'gt', value: '' };
var box = new FakeNode('div');
conditionMetric(box, { config: { rule: rule } }, rule);
enter(controls(box)[0], 'stock_low');
out.picked = rule.param;
box = new FakeNode('div');
conditionMetric(box, { config: { rule: rule } }, rule);
out.threshold = { label: textOf(box.children[1].children[0]), shown: controls(box)[1].value, type: controls(box)[1].type || '' };
enter(controls(box)[1], '3');
out.threshold.kept = rule.param;
enter(controls(box)[0], 'tasks_open');
out.channel = rule.param;
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_true(is_array($result), 'the script printed its result');

	if (!is_array($result)) {
		return;
	}

	pg_assert_same('100.00', $result['money']['shown'], 'minor units shown as a decimal');
	pg_assert_same('10000', $result['money']['kept'], 'an amount typed as 100 kept in minor units');
	pg_assert_same('1250', $result['cents']['kept'], 'and 12.5 as 1250');
	pg_assert_same(array('select', array('1', '0')), array($result['yes']['tag'], $result['yes']['options']), 'yes or no picked from two');
	pg_assert_same('1', $result['yes']['kept'], 'yes kept as "1"');
	pg_assert_same('1', $result['no']['shown'], '"1" shown as yes');
	pg_assert_same('0', $result['no']['kept'], 'no kept as "0"');
	pg_assert_same('date', $result['day']['type'], 'a day picked as a date');
	pg_assert_same('2026-11-01', $result['day']['kept'], 'kept as YYYY-MM-DD');
	pg_assert_same(array('datetime-local', '2026-10-26T14:30'), array($result['moment']['type'], $result['moment']['shown']), 'a moment picked as one');
	pg_assert_same('2026-11-01 09:15:00', $result['moment']['kept'], 'kept as the record keeps it');
	pg_assert_same('18.5', $result['decimal']['kept'], 'a decimal comma kept as a dot');
	pg_assert_same(array('text', 'Ankara'), array($result['text']['type'], $result['text']['kept']), 'a text as typed');
	pg_assert_same(array('text', '10'), array($result['days']['type'], $result['days']['kept']), '"falls within the next days" takes a number of days');
	pg_assert_same('', $result['overdue']['tag'], '"has gone by" takes no value');

	pg_assert_same(5, $result['picked'], 'a count against a number starts at 5');
	pg_assert_same(array('fl_cond_threshold', '5', 'number'), array($result['threshold']['label'], $result['threshold']['shown'], $result['threshold']['type']), 'and asks for it');
	pg_assert_same(3, $result['threshold']['kept'], 'what is typed is kept');
	pg_assert_same(0, $result['channel'], 'a count of a channel\'s tasks starts at every channel');
}

// "Add value" offers what started the run and the boxes before the box, in
// arrow order, each with the fields the server lets {{node.*}} and
// {{trigger.*}} read: a web check's by its mode, a report's counts by the
// counts it sends, an event's by the event. A box after the reader, the box
// itself and a start that gives nothing are not offered. A box that may not
// have run is marked as the reference check warns about it
// (ws_flow_sure_before()): a condition's branch, not the boxes every way in
// passes. Before, the builder typed {{node.<id>.<field>}} with ids the
// canvas never shows.
function test_workspace_flows_canvas_value_picker_offers_the_boxes_before()
{
	$result = ws_flows_canvas_test_node(
		array('nodeById', 'kindOf', 'titleOf', 'nameOf', 'orderedNodes', 'stepLists', 'arrowMaps', 'boxesBefore', 'sureBefore', 'boxValues', 'flowLoops', 'loopOf', 'valueGroups'),
		<<<'JS'
var KINDS = {
    event: { into: false, out: ['next'] },
    manual: { into: false, out: ['next'] },
    web_check: { name: 'Check a web address', into: true, out: ['done', 'failed'] },
    condition: { name: 'Condition', into: true, out: ['yes', 'no'] },
    report: { name: 'Report', into: true, out: ['done', 'failed'] },
    post: { name: 'Post', into: true, out: ['done', 'failed'] },
    email: { name: 'Email', into: true, out: ['done', 'failed'] }
};
var FL = {
    outputs: { web_check: [{ key: 'status_code', label: 'Status code', mode: 'status' }, { key: 'days_left', label: 'Days left', mode: 'ssl' }], report: [{ key: 'message_id', label: 'Message' }], post: [{ key: 'message_id', label: 'Message' }], condition: [], email: [{ key: 'sent', label: 'Sent' }] },
    trigger_outputs: { kinds: { event: [{ key: 'event', label: 'What happened' }, { key: 'record_id', label: 'Record' }], manual: [] }, events: { 'order.created': [{ key: 'order_number', label: 'Order number' }, { key: 'record_id', label: 'Again' }] } },
    steps: { metrics: [{ key: 'tasks_open', label: 'Open tasks' }] }
};
function box(id, kind, config, y) { return { id: id, kind: kind, label: '', config: config || {}, x: 0, y: y }; }
function groups(id) {
    return valueGroups(nodeById(id)).map(function (group) {
        return group.node.id + (group.skipped ? ' skipped' : '') + ': ' + group.values.map(function (value) { return value.token.slice(2, -2) + '=' + value.label; }).join(', ');
    });
}
state = { graph: { nodes: [
    box('event_1', 'event', { event: 'order.created' }, 0),
    box('web_check_1', 'web_check', { mode: 'ssl' }, 1),
    box('report_1', 'report', { metrics: [{ key: 'tasks_open', param: 4 }, { key: 'Bad-key', param: 0 }] }, 2),
    box('condition_1', 'condition', {}, 3),
    box('post_1', 'post', {}, 4),
    box('email_1', 'email', {}, 5)
], edges: [
    { from: 'event_1', to: 'web_check_1', on: 'next' },
    { from: 'web_check_1', to: 'report_1', on: 'done' },
    { from: 'report_1', to: 'condition_1', on: 'done' },
    { from: 'condition_1', to: 'post_1', on: 'yes' },
    { from: 'post_1', to: 'email_1', on: 'done' },
    { from: 'condition_1', to: 'email_1', on: 'no' }
] } };
var out = { email: groups('email_1'), web: groups('web_check_1') };
state.graph.nodes[0] = box('manual_1', 'manual', {}, 0);
state.graph.edges[0].from = 'manual_1';
out.manual = groups('web_check_1');
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(
		'event_1: trigger.event=What happened, trigger.record_id=Record, trigger.order_number=Order number',
		'web_check_1: node.web_check_1.text=fl_values_text, node.web_check_1.outcome=fl_values_outcome, node.web_check_1.status=fl_values_status, node.web_check_1.days_left=Days left',
		'report_1: node.report_1.text=fl_values_text, node.report_1.outcome=fl_values_outcome, node.report_1.status=fl_values_status, node.report_1.message_id=Message, node.report_1.tasks_open_4=Open tasks',
		'condition_1: node.condition_1.text=fl_values_text, node.condition_1.outcome=fl_values_outcome, node.condition_1.status=fl_values_status',
		'post_1 skipped: node.post_1.text=fl_values_text, node.post_1.outcome=fl_values_outcome, node.post_1.status=fl_values_status, node.post_1.message_id=Message',
	), $result['email'], 'the start, then every box before in arrow order; a branch marked');
	pg_assert_same(array('event_1: trigger.event=What happened, trigger.record_id=Record, trigger.order_number=Order number'), $result['web'], 'the first box after the start reads only the start');
	pg_assert_same(array(), $result['manual'], 'a start that gives nothing is not offered');
}

// A condition on a value (data-passing D7) offers the values "Add value"
// does, a branch that may be skipped marked; a number's comparisons include
// more and less, a text's "contains"; choosing a text value after "is more
// than" goes back to "is". The box reads the value's name.
function test_workspace_flows_canvas_condition_value_rule()
{
	$result = ws_flows_canvas_test_node(
		array('nodeById', 'kindOf', 'titleOf', 'nameOf', 'orderedNodes', 'stepLists', 'arrowMaps', 'boxesBefore', 'sureBefore', 'boxValues', 'flowLoops', 'loopOf', 'valueGroups', 'valueSources', 'valueNames',
			'field', 'textInput', 'changeInput', 'conditionValue', 'conditionCompare', 'conditionValueRule', 'conditionMeta'),
		<<<'JS'
var uid = 0;
var DAYS = {};
var VALUE_OPS = { number: ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'empty', 'not_empty'], text: ['eq', 'neq', 'contains', 'empty', 'not_empty'] };
var KINDS = {
    manual: { into: false, out: ['next'] },
    web_check: { name: 'Check a web address', into: true, out: ['done', 'failed'] },
    condition: { name: 'Condition', into: true, out: ['yes', 'no'] },
    post: { name: 'Post', into: true, out: ['done', 'failed'] }
};
var FL = {
    outputs: { web_check: [{ key: 'status_code', type: 'number', label: 'Status code', mode: 'status' }], post: [{ key: 'message_id', type: 'number', label: 'Message' }], condition: [] },
    trigger_outputs: { kinds: { manual: [] }, events: {} },
    steps: { operators: { eq: 'is', neq: 'is not', gt: 'is more than', gte: 'is at least', lt: 'is less than', lte: 'is at most', contains: 'contains', empty: 'is empty', not_empty: 'is not empty' } }
};
K.select = function (options, value) {
    var node = new FakeNode('select');
    options.forEach(function (option) {
        var item = new FakeNode('option', '', option[1]);
        item.value = String(option[0]);
        node.appendChild(item);
    });
    node.value = String(value);
    return node;
};
var redrawn = 0;
function refreshNode() {}
function drawSettings() { redrawn++; }
function box(id, kind, y) { return { id: id, kind: kind, label: '', config: {}, x: 0, y: y }; }
state = { edit: true, graph: { nodes: [box('manual_1', 'manual', 0), box('web_check_1', 'web_check', 1), box('post_1', 'post', 2), box('condition_1', 'condition', 3), box('condition_2', 'condition', 4)], edges: [
    { from: 'manual_1', to: 'web_check_1', on: 'next' },
    { from: 'web_check_1', to: 'post_1', on: 'done' },
    { from: 'web_check_1', to: 'condition_1', on: 'failed' },
    { from: 'post_1', to: 'condition_1', on: 'done' }
] } };
function controls(box) { return box.children.map(function (wrap) { return wrap.children[1]; }); }
function options(select) { return select.children.map(function (item) { return item.value + '=' + item.textContent; }); }
var rule = { type: 'value', ref: '{{node.web_check_1.status_code}}', op: 'gt', value: '' };
var node = nodeById('condition_1');
node.config.rule = rule;
var form = new FakeNode('div');
conditionValueRule(form, node, rule);
var out = { values: options(controls(form)[0]), ops: options(controls(form)[1]).map(function (item) { return item.split('=')[0]; }), meta: conditionMeta(rule, node) };
var pick = controls(form)[0];
pick.value = '{{node.web_check_1.text}}';
pick.listeners.change.forEach(function (fn) { fn(); });
out.after = { ref: rule.ref, op: rule.op, redrawn: redrawn };
form = new FakeNode('div');
conditionValueRule(form, node, rule);
out.textOps = options(controls(form)[1]).map(function (item) { return item.split('=')[0]; });
form = new FakeNode('div');
conditionValueRule(form, nodeById('condition_2'), { type: 'value', ref: '', op: 'eq', value: '' });
out.alone = options(controls(form)[0]).length;
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(
		'=fl_cond_value_ref_pick',
		'{{node.web_check_1.text}}=Check a web address › fl_values_text',
		'{{node.web_check_1.outcome}}=Check a web address › fl_values_outcome',
		'{{node.web_check_1.status}}=Check a web address › fl_values_status',
		'{{node.web_check_1.status_code}}=Check a web address › Status code',
		'{{node.post_1.text}}=Post › fl_values_text (fl_values_skipped)',
		'{{node.post_1.outcome}}=Post › fl_values_outcome (fl_values_skipped)',
		'{{node.post_1.status}}=Post › fl_values_status (fl_values_skipped)',
		'{{node.post_1.message_id}}=Post › Message (fl_values_skipped)',
	), $result['values'], 'the boxes before, a branch marked');
	pg_assert_same(array('eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'empty', 'not_empty'), $result['ops'], 'a number\'s comparisons');
	pg_assert_same('Check a web address › Status code', $result['meta'], 'the box reads the value\'s name');
	pg_assert_same(array('ref' => '{{node.web_check_1.text}}', 'op' => 'eq', 'redrawn' => 1), $result['after'], 'a text after "is more than" goes back to "is"');
	pg_assert_same(array('eq', 'neq', 'contains', 'empty', 'not_empty'), $result['textOps'], 'a text\'s comparisons');
	pg_assert_same(1, $result['alone'], 'a box with nothing before offers no value');
}

// The data threads drawn on the canvas are read from the references the
// fields hold (Flows T1): one per value and field, from the box that gives
// it (a start box for what started the run), typed and toned by type in
// turn; a value no box before gives has no source, one from a branch that
// may be skipped is marked; a value written twice in a field is one thread,
// in two fields two. A condition on a value reads its one value.
function test_workspace_flows_canvas_data_threads_come_from_the_fields()
{
	$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js'));
	$constants = '';

	foreach (array('VALUE_TOKEN', 'VALUE_FIELDS', 'THREAD_TONES') as $name) {
		pg_assert_true((bool) preg_match('/^( *)var ' . $name . ' = (?:[^\n]*;\n|\{\n.*?\n\1\};\n)/ms', $source, $found), 'workspace_flow.js has ' . $name);
		$constants .= $found[0];
	}

	$result = ws_flows_canvas_test_node(
		array('nodeById', 'kindOf', 'titleOf', 'nameOf', 'orderedNodes', 'stepLists', 'arrowMaps', 'boxesBefore', 'sureBefore', 'boxValues', 'flowLoops', 'loopOf', 'valueGroups', 'valueSources',
			'fieldTexts', 'dataThreads'),
		$constants . <<<'JS'
var KINDS = {
    event: { name: 'Event', into: false, out: ['next'] },
    web_check: { name: 'Check a web address', into: true, out: ['done', 'failed'] },
    condition: { name: 'Condition', into: true, out: ['yes', 'no'] },
    post: { name: 'Post', into: true, out: ['done', 'failed'] },
    email: { name: 'Email', into: true, out: ['done', 'failed'] }
};
var FL = {
    outputs: { web_check: [{ key: 'status_code', type: 'number', label: 'Status code', mode: 'status' }], post: [{ key: 'message_id', type: 'number', label: 'Message' }], condition: [], email: [] },
    trigger_outputs: { kinds: { event: [{ key: 'event', label: 'What happened' }] }, events: { 'order.created': [{ key: 'order_number', type: 'text', label: 'Order number' }] } },
    steps: {}
};
function box(id, kind, config, y) { return { id: id, kind: kind, label: '', config: config || {}, x: 0, y: y }; }
state = { graph: { nodes: [
    box('event_1', 'event', { event: 'order.created' }, 0),
    box('web_check_1', 'web_check', { mode: 'status' }, 1),
    box('condition_1', 'condition', { rule: { type: 'value', ref: '{{node.web_check_1.status_code}}' } }, 2),
    box('post_1', 'post', { body: 'Code {{node.web_check_1.status_code}} {{node.web_check_1.status_code}}, {{node.web_check_1.text}}, {{node.gone_1.text}}' }, 3),
    box('email_1', 'email', { subject: 'Order {{trigger.order_number}}', body: '{{node.post_1.message_id}} {{node.web_check_1.status_code}}' }, 4)
], edges: [
    { from: 'event_1', to: 'web_check_1', on: 'next' },
    { from: 'web_check_1', to: 'condition_1', on: 'done' },
    { from: 'condition_1', to: 'post_1', on: 'yes' },
    { from: 'condition_1', to: 'email_1', on: 'no' },
    { from: 'post_1', to: 'email_1', on: 'done' }
] } };
console.log(JSON.stringify(dataThreads().map(function (thread) {
    return [thread.from, thread.to, thread.field, thread.fieldLabel, thread.label, thread.type + '-' + thread.tone, thread.skipped ? 'skipped' : ''].join(' | ');
})));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(
		'web_check_1 | condition_1 | rule | fl_cond_value_ref | Check a web address › Status code | number-0 | ',
		'web_check_1 | post_1 | body | fl_post_body | Check a web address › Status code | number-1 | ',
		'web_check_1 | post_1 | body | fl_post_body | Check a web address › fl_values_text | text-0 | ',
		' | post_1 | body | fl_post_body | node.gone_1.text | text-1 | ',
		'event_1 | email_1 | subject | fl_email_subject | Event › Order number | text-2 | ',
		'post_1 | email_1 | body | fl_email_body | Post › Message | number-2 | skipped',
		'web_check_1 | email_1 | body | fl_email_body | Check a web address › Status code | number-0 | ',
	), $result, 'one thread per value and field, from the box that gives it');
}

// A thread drawn from a value (Flows T2) may go into the fields of the boxes
// that may read it, never a field already holding it, never an address
// (VALUE_FIELDS has none), a condition's only while it compares nothing; a
// start's value may go into any box. Dropped, the token goes at the end of
// the field after a space; a condition compares it, its comparison back to
// "is" when a number's "more than" does not fit a text; a value that does
// not fit the field's maximum is refused.
function test_workspace_flows_canvas_thread_binds_into_the_fields_after()
{
	$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js'));
	$constants = '';

	foreach (array('VALUE_FIELDS', 'VALUE_OPS') as $name) {
		pg_assert_true((bool) preg_match('/^( *)var ' . $name . ' = (?:[^\n]*;\n|\{\n.*?\n\1\};\n)/ms', $source, $found), 'workspace_flow.js has ' . $name);
		$constants .= $found[0];
	}

	$result = ws_flows_canvas_test_node(
		array('nodeById', 'kindOf', 'titleOf', 'nameOf', 'orderedNodes', 'stepLists', 'arrowMaps', 'boxesBefore', 'sureBefore', 'boxValues', 'flowLoops', 'loopOf', 'valueGroups', 'valueSources',
			'openFields', 'listValue', 'fieldTakes', 'bindTargets', 'bindValue'),
		$constants . <<<'JS'
var KINDS = {
    event: { name: 'Event', into: false, out: ['next'] },
    web_check: { name: 'Check', into: true, out: ['done', 'failed'] },
    condition: { name: 'Condition', into: true, out: ['yes', 'no'] },
    post: { name: 'Post', into: true, out: ['done', 'failed'] },
    email: { name: 'Email', into: true, out: ['done', 'failed'] }
};
var FL = {
    outputs: { web_check: [{ key: 'status_code', type: 'number', label: 'Status code', mode: 'status' }], post: [{ key: 'message_id', type: 'number', label: 'Message' }], condition: [], email: [] },
    trigger_outputs: { kinds: { event: [{ key: 'event', type: 'text', label: 'What happened' }] }, events: {} },
    steps: {}
};
var toasts = [];
K.toast = function (text) { toasts.push(text); };
var refreshed = [];
function refreshNode(node) { refreshed.push(node.id); }
function drawSettings() {}
function box(id, kind, config, y) { return { id: id, kind: kind, label: '', config: config || {}, x: 0, y: y }; }
state = { graph: { nodes: [
    box('event_1', 'event', {}, 0),
    box('web_check_1', 'web_check', { mode: 'status', url: 'https://example.com' }, 1),
    box('condition_1', 'condition', { rule: { type: 'value', ref: '', op: 'gt' } }, 2),
    box('post_1', 'post', { body: 'Code {{node.web_check_1.status_code}}' }, 3),
    box('email_1', 'email', { to: 'a@example.com', subject: 'Hi', body: '' }, 4)
], edges: [
    { from: 'event_1', to: 'web_check_1', on: 'next' },
    { from: 'web_check_1', to: 'condition_1', on: 'done' },
    { from: 'condition_1', to: 'post_1', on: 'yes' },
    { from: 'post_1', to: 'email_1', on: 'done' }
] } };
var where = function (from, token) { return bindTargets(from, token).map(function (item) { return item.node.id + '.' + item.key; }); };
var out = {
    status: where('web_check_1', '{{node.web_check_1.status_code}}'),
    text: where('web_check_1', '{{node.web_check_1.text}}'),
    trigger: where('event_1', '{{trigger.event}}'),
    message: where('post_1', '{{node.post_1.message_id}}'),
    later: where('email_1', '{{node.email_1.text}}')
};
out.subject = bindValue('web_check_1', '{{node.web_check_1.text}}', 'email_1', 'subject');
out.body = bindValue('web_check_1', '{{node.web_check_1.text}}', 'email_1', 'body');
out.rule = bindValue('web_check_1', '{{node.web_check_1.text}}', 'condition_1', 'rule');
out.twice = bindValue('web_check_1', '{{node.web_check_1.text}}', 'email_1', 'body');
out.address = bindValue('web_check_1', '{{node.web_check_1.text}}', 'email_1', 'to');
nodeById('email_1').config.subject = new Array(240).join('x');
out.full = bindValue('event_1', '{{trigger.event}}', 'email_1', 'subject');
out.config = { subject: nodeById('email_1').config.subject.length, body: nodeById('email_1').config.body, rule: nodeById('condition_1').config.rule };
out.refreshed = refreshed;
out.toasts = toasts;
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('condition_1.rule', 'email_1.subject', 'email_1.body'), $result['status'], 'a value goes into the fields of the boxes after it, not one holding it');
	pg_assert_same(array('condition_1.rule', 'post_1.body', 'email_1.subject', 'email_1.body'), $result['text'], 'another value of the box may go into the same field');
	pg_assert_same(array('condition_1.rule', 'post_1.body', 'email_1.subject', 'email_1.body'), $result['trigger'], 'what started the run may go into any box');
	pg_assert_same(array('email_1.subject', 'email_1.body'), $result['message'], 'not into a box before it');
	pg_assert_same(array(), $result['later'], 'the last box gives to nobody');
	pg_assert_same(array(true, true, true, false, false, false), array($result['subject'], $result['body'], $result['rule'], $result['twice'], $result['address'], $result['full']), 'bound, refused twice, into an address and over the maximum');
	pg_assert_same(array('subject' => 239, 'body' => '{{node.web_check_1.text}}', 'rule' => array('type' => 'value', 'ref' => '{{node.web_check_1.text}}', 'op' => 'eq')), $result['config'], 'at the end after a space; a condition compares it as text');
	pg_assert_same(array('email_1', 'email_1', 'condition_1'), $result['refreshed'], 'each bound box is redrawn');
	pg_assert_same(array('fl_thread_full'), $result['toasts'], 'a full field says so');
}

// A constants box (Flows T3) has no arrows: every box is offered its named
// rows, typed, never "empty if skipped"; it is offered nothing itself and is
// not taken for the start.
function test_workspace_flows_canvas_constants_are_offered_to_every_box()
{
	$result = ws_flows_canvas_test_node(
		array('nodeById', 'kindOf', 'titleOf', 'nameOf', 'orderedNodes', 'stepLists', 'arrowMaps', 'boxesBefore', 'sureBefore', 'boxValues', 'flowLoops', 'loopOf', 'valueGroups'),
		<<<'JS'
var KINDS = {
    manual: { name: 'Manual', into: false, out: ['next'] },
    condition: { name: 'Condition', into: true, out: ['yes', 'no'] },
    post: { name: 'Post', into: true, out: ['done', 'failed'] },
    constants: { name: 'Constants', into: false, out: [], pure: true }
};
var FL = { outputs: { post: [], condition: [] }, trigger_outputs: { kinds: { manual: [] }, events: {} }, steps: {} };
function box(id, kind, config, y) { return { id: id, kind: kind, label: '', config: config || {}, x: 0, y: y }; }
state = { graph: { nodes: [
    box('manual_1', 'manual', {}, 0),
    box('condition_1', 'condition', {}, 1),
    box('post_1', 'post', {}, 2),
    box('constants_1', 'constants', { rows: [{ key: 'rate', type: 'number', value: '18' }, { key: '', type: 'text', value: 'x' }, { key: 'sign', type: 'text', value: 'Hi' }] }, 3)
], edges: [
    { from: 'manual_1', to: 'condition_1', on: 'next' },
    { from: 'condition_1', to: 'post_1', on: 'yes' }
] } };
var groups = function (id) {
    return valueGroups(nodeById(id)).filter(function (group) { return group.node.id === 'constants_1'; }).map(function (group) {
        return (group.skipped ? 'skipped ' : '') + group.values.map(function (value) { return value.token + ':' + value.type; }).join(', ');
    });
};
console.log(JSON.stringify({ post: groups('post_1'), condition: groups('condition_1'), itself: valueGroups(nodeById('constants_1')).map(function (group) { return group.node.id; }) }));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('{{node.constants_1.rate}}:number, {{node.constants_1.sign}}:text'), $result['post'], 'its named rows, never skipped');
	pg_assert_same($result['post'], $result['condition'], 'the same to the first box');
	pg_assert_same(array(), $result['itself'], 'a box reads nothing of itself, and the start gives nothing');
}

// A derived value box reads any box, wherever it stands, and gives one value
// of its rule's type; a rule of one input has no second field to fill.
function test_workspace_flows_canvas_derived_box_reads_any_box()
{
	$result = ws_flows_canvas_test_node(
		array('nodeById', 'kindOf', 'titleOf', 'nameOf', 'orderedNodes', 'stepLists', 'arrowMaps', 'boxesBefore', 'sureBefore', 'boxValues', 'flowLoops', 'loopOf', 'valueGroups', 'derivedRule', 'openFields'),
		"var VALUE_FIELDS = { post: [['body', 'fl_post_body', 4000]], value: [['a', 'fl_derived_a', 500], ['b', 'fl_derived_b', 500]] };\n" . <<<'JS'
var KINDS = {
    manual: { name: 'Manual', into: false, out: ['next'] },
    condition: { name: 'Condition', into: true, out: ['yes', 'no'] },
    post: { name: 'Post', into: true, out: ['done', 'failed'] },
    value: { name: 'Derived value', into: false, out: [], pure: true }
};
var FL = {
    outputs: { post: [{ key: 'message_id', type: 'number', label: 'Message' }], condition: [] },
    trigger_outputs: { kinds: { manual: [] }, events: {} },
    steps: {},
    derived: { rules: [{ key: 'join', label: 'Join', type: 'text', two: true }, { key: 'round', label: 'Round', type: 'number', two: false }] }
};
function box(id, kind, config, y) { return { id: id, kind: kind, label: '', config: config || {}, x: 0, y: y }; }
state = { graph: { nodes: [
    box('manual_1', 'manual', {}, 0),
    box('condition_1', 'condition', {}, 1),
    box('post_1', 'post', {}, 2),
    box('post_2', 'post', {}, 3),
    box('value_1', 'value', { rule: 'round', a: '', b: '' }, 4),
    box('value_2', 'value', { rule: 'nothing', a: '', b: '' }, 5)
], edges: [
    { from: 'manual_1', to: 'condition_1', on: 'next' },
    { from: 'condition_1', to: 'post_1', on: 'yes' },
    { from: 'condition_1', to: 'post_2', on: 'no' }
] } };
var read = function (id) {
    return valueGroups(nodeById(id)).map(function (group) { return (group.skipped ? 'skipped ' : '') + group.node.id; });
};
console.log(JSON.stringify({
    value: read('value_1'),
    post: read('post_2'),
    gives: boxValues(nodeById('value_1')).map(function (value) { return value.token + ':' + value.type; }).concat(boxValues(nodeById('value_2')).map(function (value) { return value.type; })),
    fields: openFields(nodeById('value_1')).map(function (item) { return item.key; }).concat(openFields(nodeById('value_2')).map(function (item) { return item.key; }))
}));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('condition_1', 'post_1', 'post_2', 'value_2'), $result['value'], 'every other box, none marked skipped');
	pg_assert_same(array('condition_1', 'value_1', 'value_2'), $result['post'], 'a box reads it wherever it stands');
	pg_assert_same(array('{{node.value_1.value}}:number', 'text'), $result['gives'], 'its value, of its rule\'s type; an unknown rule as join');
	pg_assert_same(array('a', 'a', 'b'), $result['fields'], 'round reads one value, join two');
}

// The run view (Flows T4) lists what each box received: every thread into
// a field with the value it brought in the run (a box's kept value, its
// sentence, a constants row; empty for a box skipped or not reached, not
// yet for one still going; what started the run named, never guessed), the
// field as filled once the box ran, and the field as written on request.
function test_workspace_flows_canvas_run_lists_the_values_received()
{
	$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js'));
	$constants = '';

	foreach (array('VALUE_TOKEN', 'VALUE_FIELDS', 'THREAD_TONES') as $name) {
		pg_assert_true((bool) preg_match('/^( *)var ' . $name . ' = (?:[^\n]*;\n|\{\n.*?\n\1\};\n)/ms', $source, $found), 'workspace_flow.js has ' . $name);
		$constants .= $found[0];
	}

	$result = ws_flows_canvas_test_node(
		array('nodeById', 'kindOf', 'titleOf', 'nameOf', 'orderedNodes', 'stepLists', 'arrowMaps', 'boxesBefore', 'sureBefore', 'boxValues', 'flowLoops', 'loopOf', 'valueGroups', 'valueSources',
			'fieldTexts', 'dataThreads', 'toneClass', 'stepOf', 'runValue', 'runFilled', 'runReads'),
		$constants . <<<'JS'
var KINDS = {
    manual: { name: 'Manual', into: false, out: ['next'] },
    web_check: { name: 'Check', into: true, out: ['done', 'failed'] },
    condition: { name: 'Condition', into: true, out: ['yes', 'no'] },
    email: { name: 'Email', into: true, out: ['done', 'failed'] },
    post: { name: 'Post', into: true, out: ['done', 'failed'] },
    task: { name: 'Task', into: true, out: ['done'] },
    constants: { name: 'Constants', into: false, out: [], pure: true }
};
var FL = {
    outputs: { web_check: [{ key: 'status_code', type: 'number', label: 'Status code' }], email: [{ key: 'sent', type: 'number', label: 'Sent' }], post: [{ key: 'message_id', type: 'number', label: 'Message' }], condition: [], task: [] },
    trigger_outputs: { kinds: { manual: [{ key: 'who', type: 'text', label: 'Who' }] }, events: {} },
    steps: {}
};
var redrawn = 0;
function drawSettings() { redrawn++; }
function refocus() {}
function box(id, kind, config, y) { return { id: id, kind: kind, label: '', config: config || {}, x: 0, y: y }; }
state = { graph: { nodes: [
    box('manual_1', 'manual', {}, 0),
    box('web_check_1', 'web_check', {}, 1),
    box('condition_1', 'condition', {}, 2),
    box('email_1', 'email', { subject: 'Hi', body: '{{node.web_check_1.outcome}}' }, 3),
    box('post_1', 'post', { body: 'Code {{node.web_check_1.status_code}}: {{node.web_check_1.text}} {{node.constants_1.sign}} {{node.email_1.sent}} {{trigger.who}}' }, 4),
    box('task_1', 'task', { title: '{{node.post_1.message_id}}' }, 5),
    box('constants_1', 'constants', { rows: [{ key: 'sign', type: 'text', value: 'Bye' }] }, 6)
], edges: [
    { from: 'manual_1', to: 'web_check_1', on: 'next' },
    { from: 'web_check_1', to: 'condition_1', on: 'done' },
    { from: 'condition_1', to: 'email_1', on: 'yes' },
    { from: 'condition_1', to: 'post_1', on: 'no' },
    { from: 'email_1', to: 'post_1', on: 'done' },
    { from: 'post_1', to: 'task_1', on: 'done' }
] }, run: { id: 4, steps: [
    { node_id: 'manual_1', status: 'done', outcome: 'next', result: '', outputs: {} },
    { node_id: 'web_check_1', status: 'done', outcome: 'done', result: 'Reached the address', outputs: { status_code: 200 } },
    { node_id: 'condition_1', status: 'done', outcome: 'no', result: '', outputs: {} },
    { node_id: 'email_1', status: 'skipped', outcome: '', result: '', outputs: {} },
    { node_id: 'post_1', status: 'running', outcome: null, result: '', outputs: {} }
] } };
var post = runReads(nodeById('post_1'));
var before = textOf(post);
buttonsOf(post)[0].click();
console.log(JSON.stringify({
    post: before,
    toggled: [redrawn, textOf(runReads(nodeById('post_1')))],
    email: textOf(runReads(nodeById('email_1'))),
    task: textOf(runReads(nodeById('task_1'))),
    nothing: runReads(nodeById('web_check_1'))
}));
JS
	);

	if ($result === null) {
		return;
	}

	$post = 'fl_run_values fl_post_body Check › Status code: 200 Check › fl_values_text: Reached the address Constants › sign: Bye Email › Sent: fl_run_value_skipped Manual › Who: fl_run_value_trigger '
		. 'fl_run_filled Code 200: Reached the address Bye [Manual › Who] fl_run_template_show';
	pg_assert_same($post, $result['post'], 'each thread with its value, the field as filled');
	pg_assert_same(array(1, str_replace('fl_run_template_show', 'fl_run_template_hide', $post) . ' ' . 'Code {{node.web_check_1.status_code}}: {{node.web_check_1.text}} {{node.constants_1.sign}} {{node.email_1.sent}} {{trigger.who}}'),
		$result['toggled'], 'the template on request');
	pg_assert_same('fl_run_values fl_email_body Check › fl_values_outcome: done fl_run_template_show', $result['email'], 'a skipped box: its values, no filled field');
	pg_assert_same('fl_run_values fl_title Post › Message: fl_run_value_pending fl_run_template_show', $result['task'], 'a box not reached: no filled field');
	pg_assert_same(null, $result['nothing'], 'a box that reads nothing lists nothing');
}

// Undo and redo on the canvas keep the graph as JSON text before each
// change: close changes that are not structural (typing, arrow-key moves)
// fold into one step, a structural one is a step of its own, a new change
// drops what was undone, and the oldest steps go past HISTORY_MAX.
function test_workspace_flows_canvas_history_steps()
{
	$result = ws_flows_canvas_test_node(
		array('historyOf', 'historyRecord', 'historyStep'),
		<<<'JS'
var HISTORY_MAX = 3, HISTORY_FOLD = 1000;
var h = historyOf(7, 'a');
var same = historyRecord(h, 'a', true, 100);
historyRecord(h, 'b', false, 100);
historyRecord(h, 'c', true, 200);
historyRecord(h, 'd', true, 900);
historyRecord(h, 'e', true, 2500);
var folded = h.past.slice();
var back = [historyStep(h, true), historyStep(h, true), historyStep(h, true), historyStep(h, true)];
var forward = historyStep(h, false);
historyRecord(h, 'x', true, 2600);
var dropped = [h.future.length, historyStep(h, false)];
historyRecord(h, 'y', false, 2700);
historyRecord(h, 'z', false, 2700);
console.log(JSON.stringify({ same: same, folded: folded, back: back, forward: forward, dropped: dropped, past: h.past, now: h.now }));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(false, $result['same'], 'the same graph is no step');
	pg_assert_same(array('a', 'b', 'd'), $result['folded'], 'close typing folds, a pause or a structural change starts a step');
	pg_assert_same(array('d', 'b', 'a', null), $result['back'], 'undo goes back step by step, then stops');
	pg_assert_same('b', $result['forward'], 'redo comes forward again');
	pg_assert_same(array(0, null), $result['dropped'], 'a new change drops what was undone');
	pg_assert_same(array('b', 'x', 'y'), $result['past'], 'only the last steps are kept');
	pg_assert_same('z', $result['now'], 'the graph as it is');
}

// The MCP box: the palette offers it only while a server is listed for the
// person; its form takes a tool that asks first once it has the channel its
// owner is asked in, and tells one no longer on, a server out of reach and
// arguments that are no JSON object (each value read as empty, a value
// outside quotes leaves none), as the server's check does.
function test_workspace_flows_canvas_mcp_box()
{
	$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js'));
	$constants = '';

	foreach (array('VALUE_TOKEN', 'PALETTE') as $name) {
		pg_assert_true((bool) preg_match('/^( *)var ' . $name . ' = (?:[^\n]*;\n|\[\n.*?\n\1\];\n)/ms', $source, $found), 'workspace_flow.js has ' . $name);
		$constants .= $found[0];
	}

	$result = ws_flows_canvas_test_node(
		array('stepLists', 'rowWriteTables', 'paletteItems', 'mcpServer', 'mcpToolAsks', 'mcpToolProblem', 'mcpArgumentsProblem'),
		$constants . <<<'JS'
var FL = { steps: {} };
var none = paletteItems().map(function (item) { return item[0]; }).indexOf('mcp');
FL.steps.mcp = [{ id: 7, alias: 'docs', name: 'Docs', tools: [{ name: 'search', description: 'Finds pages' }], asks: ['create_page'] }];
var offered = paletteItems().map(function (item) { return item[0]; }).indexOf('mcp');
console.log(JSON.stringify({
    none: none,
    offered: offered > -1,
    tools: [
        mcpToolProblem({ server_id: 7, tool: 'search' }),
        mcpToolProblem({ server_id: 7, tool: 'create_page' }),
        mcpToolProblem({ server_id: 7, tool: 'create_page', channel_id: 5, closes_in: 24 }),
        mcpToolProblem({ server_id: 7, tool: 'gone' }),
        mcpToolProblem({ server_id: 9, tool: 'search' }),
        mcpToolProblem({ server_id: 0, tool: '' }),
        mcpToolProblem({ server_id: 7, tool: '' })
    ],
    asks: [
        mcpToolAsks({ server_id: 7, tool: 'create_page' }),
        mcpToolAsks({ server_id: 7, tool: 'search' }),
        mcpToolAsks({ server_id: 9, tool: 'create_page' }),
        mcpToolAsks({ server_id: 7, tool: '' })
    ],
    args: [
        mcpArgumentsProblem(''),
        mcpArgumentsProblem('{"q": "{{node.a.text}}", "n": 2}'),
        mcpArgumentsProblem('{"q": "{{trigger.name}}"}'),
        mcpArgumentsProblem('{"n": {{node.a.text}}}'),
        mcpArgumentsProblem('[1, 2]'),
        mcpArgumentsProblem('"x"'),
        mcpArgumentsProblem('{oops')
    ]
}));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(-1, $result['none'], 'no server, no MCP box in the palette');
	pg_assert_true($result['offered'], 'a server listed: the palette offers it');
	pg_assert_same(array('', 'fl_approval_no_channel', '', 'fl_mcp_tool_off gone docs', 'fl_mcp_server_out', '', ''), $result['tools'], 'a tool that asks first runs once it has its channel');
	pg_assert_same(array(true, false, false, false), $result['asks'], 'only a tool the server lists as asking first asks');
	pg_assert_same(array('', '', '', 'fl_mcp_arguments_bad', 'fl_mcp_arguments_bad', 'fl_mcp_arguments_bad', 'fl_mcp_arguments_bad'), $result['args']);

	// The lists come with the flows config, built by the steps' helper with
	// the person's account on an OAuth server.
	$config = ws_flows_canvas_test_function_source('includes/workspace/flows.php', 'ws_flow_js_config');
	pg_assert_contains("\$steps['mcp'] = ws_step_mcp_form_servers(ws_mcp_active_servers()", $config);
	pg_assert_contains('ws_mcp_oauth_account($server, (int) $viewer[\'id\'])', $config);
}


// A row box (a workflow table's row starts the flow) is offered only while
// the person reads a table; it reads what happens and the table, and gives
// the server's values (table, row, who) and one per column of its table, by
// the column's name. A table no longer in the list is named by its id and
// gives no cells.
function test_workspace_flows_canvas_row_box()
{
	$result = ws_flows_canvas_test_node(
		array('nodeById', 'kindOf', 'stepLists', 'rowWriteTables', 'paletteItems', 'rowTable', 'rowMeta', 'rowCellValues', 'boxValues'),
		<<<'JS'
var KINDS = { row: { into: false, out: ['next'] }, mcp: { into: true, out: ['done', 'failed'] }, post: { into: true, out: ['done', 'failed'] } };
var PALETTE = [['row', 'fl_kind_row'], ['post', 'fl_add_post'], ['mcp', 'fl_kind_mcp']];
var ROW_WHATS = { created: 'fl_row_created', updated: 'fl_row_updated', deleted: 'fl_row_deleted' };
var FL = {
    trigger_outputs: { kinds: { row: [{ key: 'table_id', label: 'Table' }, { key: 'row_id', label: 'Row' }] }, events: {} },
    steps: { tables: [{ id: 4, name: 'Deals', columns: [{ id: 7, name: 'Customer', type: 'text' }, { id: 9, name: 'Amount', type: 'money' }] }] }
};
function keys(list) { return list.map(function (item) { return item.token.slice(2, -2) + '=' + item.label + ':' + item.type; }); }
var row = { id: 'row_1', kind: 'row', config: { table_id: 4, what: 'updated', column_id: 9 } };
var out = {
    palette: paletteItems().map(function (item) { return item[0]; }),
    meta: rowMeta(row.config),
    values: keys(boxValues(row)),
    none: rowMeta({ table_id: 0, what: 'created', column_id: 0 }),
    gone: rowMeta({ table_id: 8, what: 'deleted', column_id: 0 }),
    goneValues: keys(boxValues({ id: 'row_2', kind: 'row', config: { table_id: 8, what: 'created', column_id: 0 } }))
};
FL.steps.tables = [];
out.empty = paletteItems().map(function (item) { return item[0]; });
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('row', 'post'), $result['palette'], 'a row box while a table is read; no MCP server, no MCP box');
	pg_assert_same(array('post'), $result['empty'], 'no table read: no row box');
	pg_assert_same('fl_row_updated · Deals · Amount', $result['meta']);
	pg_assert_same('fl_row_none', $result['none']);
	pg_assert_same('fl_row_deleted · #8', $result['gone'], 'a table no longer read is named by its id');
	pg_assert_same(array('trigger.table_id=Table:text', 'trigger.row_id=Row:text', 'trigger.cell_7=Customer:text', 'trigger.cell_9=Amount:number'), $result['values']);
	pg_assert_same(array('trigger.table_id=Table:text', 'trigger.row_id=Row:text'), $result['goneValues']);
}

// The boxes that add or change a row: offered while the person may change
// rows of a table, the tables to pick only those; their cells kept as the
// JSON object the server reads, one column at a time, money typed as an
// amount and kept in minor units, a value from a box before kept as a text;
// cells the form cannot read (null) are left to their JSON field. A value
// is threaded from the cells but never dropped into them.
function test_workspace_flows_canvas_table_write_boxes()
{
	$result = ws_flows_canvas_test_node(
		array('kindOf', 'stepLists', 'rowWriteTables', 'rowWriteTable', 'rowWriteColumns', 'paletteItems', 'rowTable', 'rowCells', 'rowWriteMeta', 'cellPlaces', 'cellShown', 'cellKept', 'setCell', 'fieldTexts', 'openFields'),
		<<<'JS'
var KINDS = { row: { into: false, out: ['next'] }, row_add: { into: true, out: ['done', 'failed'] }, row_set: { into: true, out: ['done', 'failed'] } };
var PALETTE = [['row', 'fl_kind_row'], ['row_add', 'fl_kind_row_add'], ['row_set', 'fl_kind_row_set']];
var VALUE_FIELDS = { row_set: [['row', 'fl_rows_row', 200]] };
var FL = { steps: { tables: [
    { id: 4, name: 'Deals', edit: true, columns: [{ id: 7, name: 'Customer', type: 'text' }, { id: 9, name: 'Amount', type: 'money', options: { decimals: 2 } }, { id: 11, name: 'Total', type: 'formula' }] },
    { id: 5, name: 'Read only', edit: false, columns: [] }
] } };
function valueNames() { return { '{{node.add.row_id}}': { label: 'Add › Row' } }; }
function refreshNode() {}
var amount = { id: 9, type: 'money', options: { decimals: 2 } };
var node = { id: 'set', kind: 'row_set', config: { table_id: 4, row: '{{node.add.row_id}}', cells: '{"7":"Ada","15":"kept"}' } };
setCell(node, '9', cellKept(amount, '12.5'));
var afterMoney = node.config.cells;
setCell(node, '7', cellKept({ id: 7, type: 'text' }, ' '));
var out = {
    palette: paletteItems().map(function (item) { return item[0]; }),
    tables: rowWriteTables().map(function (table) { return table.id; }),
    columns: rowWriteColumns(rowWriteTable({ table_id: 4 })).map(function (column) { return column.id; }),
    readOnly: rowWriteTable({ table_id: 5 }),
    cells: [rowCells(''), rowCells('{"3": {{trigger.x}}}'), rowCells('[1]'), rowCells('{"3":"a"}')],
    afterMoney: afterMoney,
    afterEmpty: node.config.cells,
    kept: [cellKept(amount, '{{trigger.amount}}'), cellKept({ type: 'number' }, '3.5'), cellKept({ type: 'person' }, '12'), cellKept({ type: 'date' }, ' 2026-11-02 '), cellKept({ type: 'text' }, ' a b ')],
    shown: [cellShown(amount, 1250), cellShown(amount, '{{trigger.amount}}'), cellShown({ type: 'number' }, null)],
    meta: [rowWriteMeta(node), rowWriteMeta({ kind: 'row_set', config: { table_id: 4, row: '12' } }), rowWriteMeta({ kind: 'row_add', config: { table_id: 8, cells: '' } }), rowWriteMeta({ kind: 'row_add', config: { table_id: 0 } })],
    texts: fieldTexts(node).map(function (item) { return item.key; }),
    open: openFields(node).map(function (item) { return item.key; }),
    openAdd: openFields({ kind: 'row_add', config: { cells: '' } }).length
};
FL.steps.tables[0].edit = false;
out.noEdit = paletteItems().map(function (item) { return item[0]; });
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('row', 'row_add', 'row_set'), $result['palette']);
	pg_assert_same(array('row'), $result['noEdit'], 'no table to change: no box that writes one');
	pg_assert_same(array(4), $result['tables'], 'only tables the person may change');
	pg_assert_same(array(7, 9), $result['columns'], 'a formula column is not written');
	pg_assert_same(null, $result['readOnly']);
	pg_assert_same(array(array(), null, null, array('3' => 'a')), $result['cells'], 'a value outside quotes or a list is no object');
	pg_assert_same('{"7":"Ada","9":1250,"15":"kept"}', $result['afterMoney'], 'money in minor units; a column the form does not show is kept');
	pg_assert_same('{"9":1250,"15":"kept"}', $result['afterEmpty'], 'an empty field leaves its column out');
	pg_assert_same(array('{{trigger.amount}}', 3.5, 12, '2026-11-02', ' a b '), $result['kept']);
	pg_assert_same(array('12.50', '{{trigger.amount}}', ''), $result['shown']);
	pg_assert_same(array('fl_rows_meta Deals Add › Row', 'fl_rows_meta Deals 12', '#8', 'fl_row_none'), $result['meta']);
	pg_assert_same(array('row', 'cells'), $result['texts'], 'the cells\' values are threaded');
	pg_assert_same(array('row'), $result['open'], 'a value is never dropped into the JSON');
	pg_assert_same(0, $result['openAdd']);
}

// The groups of the flow boxes and their colours (dark theme as the visual
// spec gives them; workspace-flows-look.md V1), in their listing order.
function ws_flows_canvas_test_groups()
{
	return array(
		'start' => array('#6fdca6', array('manual', 'schedule', 'event', 'join', 'row')),
		'values' => array('#e58fd0', array('constants', 'value')),
		'logic' => array('#e9b44c', array('condition', 'delay', 'end', 'for_each', 'for_each_end')),
		'people' => array('#b49cff', array('task', 'task_open', 'checkin', 'approval', 'task_digest', 'lead')),
		'message' => array('#6ea8ff', array('post', 'email', 'notify', 'report')),
		'system' => array('#45c4d6', array('web_check', 'webhook', 'change', 'start_flow', 'start_action', 'mcp', 'row_add', 'row_set'))
	);
}

// WCAG contrast ratio of two #rrggbb colours.
function ws_flows_canvas_test_contrast($one, $two)
{
	$light = function ($hex) {
		$sum = 0;

		foreach (array(array(1, .2126), array(3, .7152), array(5, .0722)) as $part) {
			$value = hexdec(substr($hex, $part[0], 2)) / 255;
			$sum += $part[1] * (($value <= .03928) ? $value / 12.92 : pow(($value + .055) / 1.055, 2.4));
		}

		return $sum;
	};
	$a = $light($one);
	$b = $light($two);

	return (max($a, $b) + .05) / (min($a, $b) + .05);
}

// Every kind of box has exactly one group, the plan's, and a box and its
// outline entry carry the group's class; the palette offers no kind
// without one. Before, only four kinds had a colour of their own.
function test_workspace_flows_canvas_every_kind_has_one_group()
{
	$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js'));

	pg_assert_true((bool) preg_match('/\n    var KINDS = \{\n.*?\n    \};\n/s', $source, $kinds), 'KINDS is in the script');
	pg_assert_true((bool) preg_match('/\n    var PALETTE = \[\n.*?\n    \];\n/s', $source, $palette), 'PALETTE is in the script');
	pg_assert_contains("el('div', 'wsf-node ' + kindClasses(node.kind))", $source, 'a box takes its group class');
	pg_assert_contains("el('li', 'wsf-outline-item ' + kindClasses(node.kind))", $source, 'an outline entry takes its group class');

	$result = ws_flows_canvas_test_node(
		array('kindClasses'),
		$kinds[0] . $palette[0] . <<<'JS'
var out = { groups: {}, classes: {}, palette: PALETTE.map(function (item) { return item[0]; }) };
Object.keys(KINDS).forEach(function (kind) {
    out.groups[kind] = KINDS[kind].group;
    out.classes[kind] = kindClasses(kind);
});
out.unknown = kindClasses('nothing');
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	$expected = array();

	foreach (ws_flows_canvas_test_groups() as $group => $entry) {
		foreach ($entry[1] as $kind) {
			pg_assert_false(isset($expected[$kind]), $kind . ' is in one group only');
			$expected[$kind] = $group;
		}
	}

	ksort($expected);
	$given = $result['groups'];
	ksort($given);
	pg_assert_same($expected, $given, 'each kind in its group, none left out');

	foreach ($result['classes'] as $kind => $classes) {
		pg_assert_same('wsf-node-' . $kind . ' wsf-group-' . $expected[$kind], $classes, $kind . "'s classes");
	}

	pg_assert_same('wsf-node-unknown', $result['unknown'], 'an unknown kind has no group');
	pg_assert_same(array(), array_values(array_diff($result['palette'], array_keys($expected))), 'every kind the palette adds has a group');
}

// Each kind of box leaves on the outcomes the server lets an arrow leave
// it on (ws_flow_kinds()), in its order: the first is a new arrow's. An MCP
// box with a tool that asks first also leaves on how its request ended.
function test_workspace_flows_canvas_kinds_leave_on_the_servers_outcomes()
{
	$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js'));

	pg_assert_true((bool) preg_match('/\n    var KINDS = \{\n.*?\n    \};\n/s', $source, $kinds), 'KINDS is in the script');

	$result = ws_flows_canvas_test_node(
		array(),
		$kinds[0] . <<<'JS'
var out = {};
Object.keys(KINDS).forEach(function (kind) { out[kind] = KINDS[kind].out; });
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	$server = ws_flow_kinds();

	pg_assert_true(isset($server['mcp']), 'the server has the MCP box');

	// A kind the canvas does not build yet (the table boxes) is left out.
	foreach (array_intersect_key($server, $result) as $kind => $info) {
		pg_assert_same(array_values((array) $info['outcomes']), $result[$kind], $kind . "'s outcomes");
	}

	pg_assert_same(array(), array_values(array_diff(array_keys($result), array_keys($server))), 'the canvas has no kind the server lacks');

	pg_assert_same(array('done', 'failed', 'rejected', 'expired', 'withdrawn'), $result['mcp'] ?? null, 'the MCP box leaves on how its request ended');
}

// Each group's colour is set once per theme: dark the spec's, light a
// deeper tone readable on white (4.5:1); a box has a 1.5 px border all
// round, not the 4 px left stripe, a value box a dashed one; control
// arrows 2 px #7d8696 with a filled head; the scheduled-action drawing
// uses the groups too.
function test_workspace_flows_canvas_group_colours()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');

	foreach (ws_flows_canvas_test_groups() as $group => $entry) {
		pg_assert_true((bool) preg_match('/\n\.wsf-group-' . $group . ' \{ --wsf-kind: (#[0-9a-f]{6}); \}/', $css, $light), $group . ' has a light colour');
		pg_assert_same(1, preg_match_all('/\n\.wsf-group-' . $group . ' \{/', $css), $group . "'s light colour is set once");
		pg_assert_true(ws_flows_canvas_test_contrast($light[1], '#ffffff') >= 4.5, $group . ' light ' . $light[1] . ' keeps 4.5:1 on white');
		pg_assert_contains('html[data-bs-theme="dark"] .wsf-group-' . $group . ' { --wsf-kind: ' . $entry[0] . '; }', $css, $group . "'s dark colour is the spec's");
	}

	pg_assert_false((bool) preg_match('/\.wsf-node-(manual|task|end|constants|value) \{ --wsf-kind/', $css), 'no colour per kind');
	pg_assert_true((bool) preg_match('/\n\.wsf-node \{[^}]*border: 1\.5px solid var\(--wsf-kind/', $css, $box), 'a box has a 1.5 px border in its colour');
	pg_assert_false(strpos($box[0], 'border-left') !== false, 'no left stripe');
	pg_assert_contains(".wsf-node.wsf-group-values {\n  border-style: dashed;\n}", $css, 'a value box is dashed');
	pg_assert_contains('html[data-bs-theme="dark"] .wsf-node { --wsf-ring: #a78bfa; }', $css, 'the selection ring');
	pg_assert_contains(".wsf-edge-line {\n  fill: none;\n  stroke: #7d8696;\n  stroke-width: 2;\n  marker-end: url(#wsf-arrow);\n}", $css, 'control arrows');
	pg_assert_contains('.wsf-arrowhead { fill: #7d8696; }', $css, 'a filled head');
	pg_assert_contains('.wsf-edge.wsf-edge-taken .wsf-edge-line', $css, 'the run view keeps its taken arrows');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');
	pg_assert_true((bool) preg_match('/var ACTION_KINDS = \{(.*?)\};/s', $source, $actions), 'ACTION_KINDS is in the script');
	preg_match_all("/'wsf-group-([a-z]+)'/", $actions[1], $used);
	pg_assert_same(4, count($used[1]), 'every scheduled-action box has a group');
	pg_assert_same(array(), array_values(array_diff($used[1], array_keys(ws_flows_canvas_test_groups()))), 'only groups the CSS colours');
}

// The canvas's own sizes (BOX_W, BOX_H, DATA_ROW), as the script has them.
function ws_flows_canvas_test_sizes()
{
	$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js'));

	preg_match_all('/^    var (BOX_W|BOX_H|DATA_ROW) = [^\n]+;$/m', $source, $found);
	pg_assert_same(3, count($found[0]), 'the box and row sizes are in the script');

	return implode("\n", $found[0]) . "\n";
}

// The row under a box never grows wider than its box: for 1 to 6 values
// given, with no, one or two fields read, every chip and dot lies within
// the box's width, none overlaps the next while they fit, and a dot is
// never narrower than its ring. Before, the chips (76 px) and dots (84 px)
// went on in one row, so a box giving four values ("Ask for approval")
// spread it under its neighbours.
function test_workspace_flows_canvas_data_row_stays_within_the_box()
{
	$result = ws_flows_canvas_test_node(array('dataRowSlots'), ws_flows_canvas_test_sizes() . <<<'JS'
var out = { box: BOX_W, ring: DATA_ROW.ring, rows: [] };
[0, 1, 2].forEach(function (fields) {
    [1, 2, 3, 4, 5, 6].forEach(function (outs) {
        out.rows.push({ fields: fields, outs: outs, slots: dataRowSlots(fields, outs) });
    });
});
out.two = dataRowSlots(0, 2);
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	foreach ($result['rows'] as $row) {
		$name = $row['fields'] . ' fields, ' . $row['outs'] . ' values';
		$all = array_merge($row['slots']['field'], $row['slots']['out']);

		pg_assert_same($row['fields'], count($row['slots']['field']), $name . ': a slot per field');
		pg_assert_same($row['outs'], count($row['slots']['out']), $name . ': a slot per value');

		foreach ($all as $index => $slot) {
			pg_assert_true($slot['left'] >= 0 && $slot['left'] + $slot['width'] <= $result['box'], $name . ': item ' . $index . ' within the box');
			pg_assert_true($slot['width'] >= $result['ring'], $name . ': item ' . $index . ' at least a ring wide');

			if ($index > 0) {
				pg_assert_true($slot['left'] >= $all[$index - 1]['left'] + $all[$index - 1]['width'], $name . ': item ' . $index . ' after the one before');
			}
		}
	}

	pg_assert_same(array(array('left' => 8, 'width' => 84), array('left' => 96, 'width' => 84)), $result['two']['out'], 'two values keep their full width');
}

// A thread that would pass under another box hangs deeper until it passes
// below it; one that passes clear keeps its sag, and so does one whose box
// lies too far down to hang below.
function test_workspace_flows_canvas_thread_bends_around_a_box()
{
	$result = ws_flows_canvas_test_node(array('threadPath'), ws_flows_canvas_test_sizes() . <<<'JS'
var y = 89;
console.log(JSON.stringify({
    clear: threadPath(104, y, 704, y, 46, []),
    below: threadPath(104, y, 704, y, 46, [{ x: 300, y: 400 }]),
    under: threadPath(104, y, 704, y, 46, [{ x: 300, y: 40 }]),
    deep: threadPath(104, y, 704, y, 46, [{ x: 300, y: 100 }])
}));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(46, $result['clear']['sag'], 'nothing in the way: its own sag');
	pg_assert_same('M104 89 Q404 135 704 89', $result['clear']['d'], 'the quadratic curve below the chord');
	pg_assert_same(46, $result['below']['sag'], 'a box far below is not in the way');
	pg_assert_true($result['under']['sag'] > 46, 'a box in the way: it hangs deeper');
	// The curve's lowest point is halfway between the ends' level and the
	// control point; it passes below the box and its data row (40 + 80 + 18).
	pg_assert_true(89 + $result['under']['sag'] / 2 > 138, 'and passes below the box');
	pg_assert_same(46, $result['deep']['sag'], 'a box too far down to pass below: its own sag');
}

// The row as drawn: a box's chips and dots within its width, a value no
// thread takes drawn only while its box is selected, and the dots in the
// order of the boxes they reach.
function test_workspace_flows_canvas_data_row_drawing()
{
	$result = ws_flows_canvas_test_node(array('dataRowSlots', 'threadPath', 'toneClass', 'drawData'), ws_flows_canvas_test_sizes() . <<<'JS'
var THREAD_TONES = { number: 3, text: 3 };
var drawn = {};
var nodes = [
    { id: 'a', kind: 'approval', x: 0, y: 0 },
    { id: 'b', kind: 'task', x: 640, y: 0 },
    { id: 'c', kind: 'post', x: 320, y: 0 }
];
function nodeById(id) { return nodes.filter(function (node) { return node.id === id; })[0] || null; }
function thread(token, to, field) { return { token: token, from: 'a', to: to, field: field, fieldLabel: field, value: token, type: 'text', tone: 0, skipped: false }; }
function dataThreads() { return [thread('t1', 'b', 'title'), thread('t2', 'b', 'text'), thread('t3', 'c', 'text'), thread('t4', 'b', 'title')]; }
function boxValues() { return ['t1', 't2', 't3', 't4', 't5', 't6'].map(function (token) { return { token: token, label: token, type: 'text' }; }); }
function bindTargets() { return []; }
function markThreads() {}
function liveDot() {}
function svgEl(tag, className) { return new FakeNode(tag, className); }
FakeNode.prototype.style = null;
ui.layer = new FakeNode('div');
ui.svg = new FakeNode('svg');
ui.boxes.querySelector = function (selector) {
    var id = /data-node="([^"]+)"/.exec(selector)[1];
    return (drawn[id] = drawn[id] || new FakeNode('div', 'wsf-node'));
};
var made = el;
el = function (tag, className, text) {
    var node = made(tag, className, text);
    node.style = { setProperty: function (name, value) { node.style[name] = value; } };
    return node;
};
state.graph = { nodes: nodes };
state.edit = true;
function row(id) {
    return (drawn[id] ? drawn[id].children : []).map(function (item) {
        return { kind: item.className.indexOf('wsf-data-out') !== -1 ? 'out' : 'field', left: parseInt(item.style.left, 10), width: parseInt(item.style['--wsf-w'], 10), name: item.title, unused: item.className.indexOf('wsf-data-unused') !== -1 };
    });
}
drawData();
var out = { plain: row('a'), reader: row('b'), paths: ui.svg.children.length };
drawn = {};
state.selected = { type: 'node', id: 'a' };
drawData();
out.selected = row('a');
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	foreach (array('plain', 'reader', 'selected') as $part) {
		foreach ($result[$part] as $item) {
			pg_assert_true($item['left'] >= 0 && $item['left'] + $item['width'] <= 208, $part . ': ' . $item['name'] . ' within the box');
		}
	}

	pg_assert_same(array('t3', 't1', 't2', 't4'), array_column($result['plain'], 'name'), 'the dot reaching the nearer box first');
	pg_assert_same(array(false, false, false, false), array_column($result['plain'], 'unused'), 'unused values not drawn while the box is not selected');
	pg_assert_same(array('title', 'text'), array_column($result['reader'], 'name'), 'the reading box shows a chip per field');
	pg_assert_same(4, $result['paths'], 'a thread per value and field');
	pg_assert_same(6, count($result['selected']), 'selected: its unused values too');
	pg_assert_same(array(true, true), array_slice(array_column($result['selected'], 'unused'), 4), 'the unused ones last');
}

// Every kind of box has a line icon of its own in workspace.js's set (16 ×
// 16 view, drawn by flowIcon() as an SVG the screen reader skips); the
// canvas draws boxes, palette, outline, template cards and run steps with
// it, and the mini window of a draft with the same paths. Before, each kind
// had a Bootstrap icon, and the mini window none.
function test_workspace_flows_canvas_kinds_have_line_icons()
{
	$flow = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js'));
	$host = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js'));

	pg_assert_true((bool) preg_match('/\n    var KINDS = \{\n.*?\n    \};\n/s', $flow, $kinds), 'KINDS is in the canvas script');
	pg_assert_true((bool) preg_match('/\n    var PALETTE = \[\n.*?\n    \];\n/s', $flow, $palette), 'PALETTE is in the canvas script');
	pg_assert_true((bool) preg_match('/\n    var FLOW_ICONS = \{\n.*?\n    \};\n/s', $host, $icons), 'FLOW_ICONS is in workspace.js');
	pg_assert_false(strpos($kinds[0] . $palette[0], "'bi-") !== false, 'no Bootstrap icon left on a kind');

	foreach (array("K.flowIcon(node.kind)", "K.flowIcon(node.kind, 'me-1')", "K.flowIcon(item[0], 'me-1')") as $use) {
		pg_assert_contains($use, $flow, 'the canvas draws ' . $use);
	}

	pg_assert_contains('flowIcon: flowIcon,', $host, 'the kit lends flowIcon()');
	pg_assert_contains('flowIconPaths(node.kind).forEach', $host, 'the mini window draws the same icon');

	$result = ws_flows_canvas_test_node(array(), $kinds[0] . $icons[0]
		. ws_flows_canvas_test_script_function($host, 'flowIconPaths') . ws_flows_canvas_test_script_function($host, 'flowIcon') . <<<'JS'
document.createElementNS = function (ns, tag) { var node = new FakeNode(tag); node.ns = ns; return node; };
var made = flowIcon('approval', 'me-1');
console.log(JSON.stringify({
    kinds: Object.keys(KINDS),
    icons: Object.keys(FLOW_ICONS),
    paths: FLOW_ICONS,
    unknown: flowIconPaths('nothing'),
    made: { tag: made.tag, ns: made.ns, attrs: made.attrs, paths: made.children.map(function (path) { return path.attrs.d; }) }
}));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(), array_values(array_diff($result['kinds'], $result['icons'])), 'every kind has its icon');
	pg_assert_same(array(), array_values(array_diff($result['icons'], $result['kinds'])), 'no icon for a kind that is not there');

	foreach ($result['paths'] as $kind => $paths) {
		pg_assert_true(count($paths) > 0, $kind . ' has a path');

		foreach ($paths as $d) {
			pg_assert_true((bool) preg_match('/^M[0-9. ]+[MLHVCSQTAZmlhvcsqtaz0-9 .-]*$/', $d), $kind . ': a path: ' . $d);
			// Every absolute point stays in the 16 × 16 view.
			preg_match('/^M([0-9.]+) ([0-9.]+)/', $d, $first);
			pg_assert_true($first[1] >= 0 && $first[1] <= 16 && $first[2] >= 0 && $first[2] <= 16, $kind . ': starts in the view');
		}
	}

	pg_assert_same(array('M2.5 2.5h11v11h-11z'), $result['unknown'], 'an unknown kind: a plain square');
	pg_assert_same('svg', $result['made']['tag']);
	pg_assert_same('http://www.w3.org/2000/svg', $result['made']['ns']);
	pg_assert_same(array('class' => 'ws-flow-icon me-1', 'viewBox' => '0 0 16 16', 'width' => '16', 'height' => '16', 'aria-hidden' => 'true', 'focusable' => 'false'), $result['made']['attrs']);
	pg_assert_same($result['paths']['approval'], $result['made']['paths'], 'its paths drawn');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	pg_assert_true((bool) preg_match('/\n\.ws-flow-icon \{[^}]*width: 16px;[^}]*stroke: currentColor;[^}]*stroke-width: 1\.5;/', $css), 'drawn 16 px with a 1.5 px stroke in the colour around it');
}

// Saved templates in the gallery: a saved or changed one comes first (the
// latest changed first), a deleted one goes; a saved card carries the mark
// of its own group, and edit and delete only for whoever may change it; a
// built-in card stays a card with Use alone. The editor's ⋯ offers "Save as
// template" behind the config's template_save.
function test_workspace_flows_canvas_saved_templates()
{
	$result = ws_flows_canvas_test_node(array('templatesKept', 'templateCard', 'savedMark'), <<<'JS'
var KINDS = { manual: { name: 'fl_kind_manual' } };
var FL = { template_categories: { team: 'Team', site: 'Site', sales: 'Sales', saved: 'Saved on this site' } };
K.flowIcon = function (kind) { return new FakeNode('svg', 'ws-flow-icon ' + kind); };
var edited = [], binned = [];
function templateDialog(template) { edited.push(template.key); }
function binTemplate(template) { binned.push(template.key); }
function useTemplate() { return Promise.resolve(); }
function card(key, saved) {
    return { key: key, category: saved ? 'saved' : 'team', title: 'T ' + key, help: 'line', start: 'manual', boxes: 1, threads: 0,
        graph: { nodes: [{ id: 'a', kind: 'manual' }] }, saved: saved || undefined };
}
var list = [card('site_2', { category: 'sales', edit: true }), card('morning')];
var out = {};
function keys(items) { return items.map(function (item) { return item.key; }); }
out.saved = keys(templatesKept(list, card('site_3', { category: 'team', edit: true }), null));
out.changed = keys(templatesKept([card('morning'), card('site_2', { category: 'sales', edit: true })], card('site_2', { category: 'site', edit: true }), card('site_2')));
out.deleted = keys(templatesKept(list, null, card('site_2')));
out.unchanged = keys(list);
var mine = templateCard(card('site_2', { category: 'sales', edit: true }), null);
var theirs = templateCard(card('site_4', { category: 'team', edit: false }), null);
var builtin = templateCard(card('morning'), null);
out.mine = [mine.children[1].className, textOf(mine.children[1]), buttonsOf(mine).length];
out.theirs = [textOf(theirs.children[1]), buttonsOf(theirs).length];
out.builtin = [builtin.children[1].tag, buttonsOf(builtin).length];
buttonsOf(mine)[1].click();
buttonsOf(mine)[2].click();
out.edited = edited;
out.binned = binned;
console.log(JSON.stringify(out));
JS
	);

	if (!is_array($result)) {
		return;
	}

	pg_assert_same(array('site_3', 'site_2', 'morning'), $result['saved'], 'a saved template comes first');
	pg_assert_same(array('site_2', 'morning'), $result['changed'], 'a changed template moves first, once');
	pg_assert_same(array('morning'), $result['deleted'], 'a deleted template goes');
	pg_assert_same(array('site_2', 'morning'), $result['unchanged'], 'the list handed in is not changed');
	pg_assert_contains('badge', $result['mine'][0], 'a saved card is marked');
	pg_assert_same('Sales', $result['mine'][1], 'with its own group');
	pg_assert_same(3, $result['mine'][2], 'Use, edit and delete for whoever may change it');
	pg_assert_same(array('Team', 1), $result['theirs'], 'Use alone for the others');
	pg_assert_same(array('h4', 1), $result['builtin'], 'a built-in card has no mark and Use alone');
	pg_assert_same(array('site_2'), $result['edited'], 'edit opens the dialog for that template');
	pg_assert_same(array('site_2'), $result['binned'], 'delete asks for that template');

	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');
	$more = ws_flows_canvas_test_script_function($script, 'moreMenu');

	pg_assert_contains('FL.template_save', $more, 'Save as template only where the config allows it');
	pg_assert_contains("'ws_flow_template_save'", $script, 'the save goes through the panel action');
	pg_assert_contains("'ws_flow_template_update'", $script, 'the change goes through the panel action');
	pg_assert_contains("'ws_flow_template_bin'", $script, 'the delete goes through the panel action');
	pg_assert_contains("'template_save'", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php'), 'the config says whether templates may be saved');
}

// For each / End for each on the canvas (data-passing D9): the canvas finds
// a loop's body as ws_flow_loops() does; a box outside the loop is offered
// neither the body's values nor the item; a For each box's list takes only a
// list (a webhook's answer, an End box's list), the End box's gathered value
// only a body box's, each field one value; a For each box never leaves on
// always; the frame goes around the loop's boxes.
function test_workspace_flows_canvas_for_each_loop()
{
	$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js'));

	pg_assert_true((bool) preg_match('/\n    var KINDS = \{\n.*?\n    \};\n/s', $source, $kinds), 'KINDS is in the script');
	pg_assert_true((bool) preg_match('/\n    var VALUE_FIELDS = \{\n.*?\n    \};\n/s', $source, $fields), 'VALUE_FIELDS is in the script');
	pg_assert_contains("loopFields(box, node);", $source, 'a For each box has its form');
	pg_assert_contains("loopEndFields(box, node);", $source, 'an End for each box has its form');
	pg_assert_contains("drawLoops();", $source, 'the loops are drawn with the arrows');

	$graph = array(
		'nodes' => array(
			array('id' => 'manual_1', 'kind' => 'manual', 'config' => array()),
			array('id' => 'webhook_1', 'kind' => 'webhook', 'config' => array()),
			array('id' => 'for_each_1', 'kind' => 'for_each', 'config' => array('list' => '', 'path' => '', 'fields' => array('sku'))),
			array('id' => 'post_1', 'kind' => 'post', 'config' => array()),
			array('id' => 'email_1', 'kind' => 'email', 'config' => array()),
			array('id' => 'for_each_end_1', 'kind' => 'for_each_end', 'config' => array('for_each' => 'for_each_1', 'collect' => '')),
			array('id' => 'notify_1', 'kind' => 'notify', 'config' => array()),
		),
		'edges' => array(
			array('from' => 'manual_1', 'to' => 'webhook_1', 'on' => 'next'),
			array('from' => 'webhook_1', 'to' => 'for_each_1', 'on' => 'done'),
			array('from' => 'for_each_1', 'to' => 'post_1', 'on' => 'each'),
			array('from' => 'post_1', 'to' => 'email_1', 'on' => 'done'),
			array('from' => 'email_1', 'to' => 'for_each_end_1', 'on' => 'done'),
			array('from' => 'for_each_end_1', 'to' => 'notify_1', 'on' => 'done'),
			array('from' => 'for_each_1', 'to' => 'notify_1', 'on' => 'failed'),
		),
	);
	$nodes = array();

	foreach ($graph['nodes'] as $index => $node) {
		$nodes[] = $node + array('label' => '', 'x' => 240 * $index, 'y' => 100);
	}

	$result = ws_flows_canvas_test_node(
		array('nodeById', 'kindOf', 'titleOf', 'nameOf', 'orderedNodes', 'stepLists', 'arrowMaps', 'boxesBefore', 'sureBefore', 'boxValues', 'flowLoops', 'loopOf',
			'valueGroups', 'valueSources', 'openFields', 'listValue', 'fieldTakes', 'bindTargets', 'arrowOutcomes', 'loopFrame'),
		$kinds[0] . $fields[0] . 'var BOX_W = 208; var BOX_H = 80; var LOOP_PAD = { side: 16, top: 28, bottom: 32 };' . "\n"
		. 'var FL = { outputs: ' . json_encode(array_map(function ($kind) { return $kind['outputs']; }, ws_flow_kinds())) . ', trigger_outputs: { kinds: {} }, steps: {} };' . "\n"
		. 'state = { graph: { nodes: ' . json_encode($nodes) . ', edges: ' . json_encode($graph['edges']) . ' } };' . "\n" . <<<'JS'
function keys(id) {
    return valueGroups(nodeById(id)).map(function (group) {
        return group.node.id + ':' + group.values.map(function (value) { return value.key; }).join(',');
    });
}
function targets(from, token) {
    return bindTargets(from, token).map(function (item) { return item.node.id + '.' + item.key; });
}
var loops = flowLoops();
var out = {
    loops: loops,
    inside: keys('email_1'),
    end: keys('for_each_end_1'),
    after: keys('notify_1'),
    list: targets('webhook_1', '{{node.webhook_1.body}}'),
    status: targets('webhook_1', '{{node.webhook_1.status}}'),
    collect: targets('post_1', '{{node.post_1.message_id}}'),
    outcomes: { start: arrowOutcomes(nodeById('for_each_1')), end: arrowOutcomes(nodeById('for_each_end_1')) },
    frame: loopFrame(loops.for_each_1, 'for_each_1')
};
nodeById('for_each_end_1').config.collect = '{{node.post_1.message_id}}';
out.full = openFields(nodeById('for_each_end_1')).length;
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	$server = ws_flow_loops($graph);

	pg_assert_same(array_keys($server), array_keys($result['loops']), 'the same loops as the server');
	pg_assert_same($server['for_each_1']['end'], $result['loops']['for_each_1']['end'], 'the same End box');
	pg_assert_same($server['for_each_1']['each'], $result['loops']['for_each_1']['each'], 'the same each arrows');
	pg_assert_same($server['for_each_1']['body'], $result['loops']['for_each_1']['body'], 'the same body');
	pg_assert_same(array('post_1', 'email_1'), $result['loops']['for_each_1']['body'], 'the body: between For each and its End');

	pg_assert_contains('for_each_1:text,outcome,status,count,index,item,sku', implode(' ', $result['inside']), 'inside, the item and its fields');
	pg_assert_contains('post_1:', implode(' ', $result['inside']), 'inside, the boxes before in the body');
	pg_assert_contains('for_each_1:text,outcome,status,count', implode(' ', $result['end']), 'the End box: no item');
	pg_assert_contains('post_1:', implode(' ', $result['end']), 'the End box reads the body');
	pg_assert_false(strpos(implode(' ', $result['after']), 'post_1:') !== false, 'after the loop, no body box');
	pg_assert_contains('for_each_end_1:text,outcome,status,count,done,failed,list', implode(' ', $result['after']), 'after the loop, the End box\'s outputs');

	pg_assert_true(in_array('for_each_1.list', $result['list'], true), 'a webhook\'s answer goes into the list');
	pg_assert_false(in_array('for_each_1.list', $result['status'], true), 'a status is no list');
	pg_assert_same(array('for_each_end_1.collect'), array_values(array_filter($result['collect'], function ($target) { return strpos($target, 'for_each') === 0; })), 'a body value is gathered');
	pg_assert_same(0, $result['full'], 'a gathered value is one value');

	pg_assert_same(array('each', 'failed'), $result['outcomes']['start'], 'a For each box never leaves on always');
	pg_assert_same(array('done', 'always'), $result['outcomes']['end'], 'an End box leaves as any box');
	pg_assert_same(array('x' => 480 - 16, 'y' => 100 - 28, 'width' => (240 * 5 + 208 + 16) - (240 * 2 - 16), 'height' => 80 + 28 + 32), array('x' => $result['frame']['x'], 'y' => $result['frame']['y'], 'width' => $result['frame']['width'], 'height' => $result['frame']['height']), 'the frame around For each, the body and the End box');
}
