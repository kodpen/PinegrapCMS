<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the channel's flows in the composer's "/" list and /flow
 * (/akış): the entry and its gate, the flow a typed name names, the list the
 * channel's opening hands over, a pick that asks and starts the flow as
 * Start a flow does, a flow's name typed on its own as an unknown command,
 * none in a discussion, and the customer of a channel about one
 * (includes/workspace/commands.php, command_registry.php,
 * flow_run_cards.php, actions.php, assets/js/workspace.js). The Node parts
 * are skipped where Node cannot run.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_commands.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_run_cards.php');

function workspace_command_flow_test_source($file)
{
	return str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file));
}

// One top-level "function name(" of workspace.js; '' when it is not there.
function workspace_command_flow_test_js_function($name)
{
	return preg_match('/\n    function ' . $name . '\([^)]*\) \{\n.*?\n    \}\n/s', workspace_command_flow_test_source('assets/js/workspace.js'), $match) ? $match[0] : '';
}

// One method "name: function (" of the channel screen; '' when it is not there.
function workspace_command_flow_test_js_method($name)
{
	return preg_match('/\n        ' . $name . ': function \([^)]*\) \{\n.*?\n        \},?\n/s', workspace_command_flow_test_source('assets/js/workspace.js'), $match) ? $match[0] : '';
}

// Runs a script with Node and answers what it printed as JSON; null without
// Node.
function workspace_command_flow_test_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgflowcmd');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : null;
}

// /flow and /akış: its names, the group of agents and applications, only in
// the channel itself, a flow argument with the channel's flows to choose
// from, and over the API an answer that writes nothing.
function test_workspace_command_flow_entry()
{
	$registry = ws_command_registry();
	$table = ws_command_table();

	foreach (array('akış' => 'flow', 'akis' => 'flow', 'flow' => 'flow') as $name => $key) {
		pg_assert_same($key, $table[$name] ?? '', '/' . $name);
	}

	$entry = $registry['flow'];

	pg_assert_same('agents', $entry['group']);
	pg_assert_same('channel', $entry['where'], 'never in a discussion');
	pg_assert_same('preview', $entry['api'], 'over the API nothing is started');
	pg_assert_same('ws_command_gate_flow', $entry['gate']);
	pg_assert_same(array('flow'), array_column($entry['args'], 'kind'));
	pg_assert_same('flows', $entry['args'][0]['suggest']);
	pg_assert_true($entry['args'][0]['optional'], 'without a flow the list opens');
	pg_assert_true(in_array('flow', ws_command_argument_kinds(), true));

	foreach (array('flow_start', 'flow_form') as $type) {
		pg_assert_true(in_array($type, ws_api_command_preview_types(), true), $type . ' is an answer shown back');
	}

	pg_assert_true(ws_command_gate_flow(null), 'listed without a person');
}

// The gate is Start a flow's: Flows shown to the person, a public or
// private channel; a mailbox room or a direct message is none of them.
function test_workspace_command_flow_gate_is_start_a_flows()
{
	$source = workspace_command_flow_test_source('includes/workspace/commands.php');
	$start = strpos($source, 'function ws_command_gate_flow(');
	$gate = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	pg_assert_contains('ws_flows_nav_visible($viewer)', $gate, 'whoever the Workspace shows Flows to');
	pg_assert_contains("array('public', 'private')", $gate, 'a public or private channel only');

	$flows_here = workspace_command_flow_test_source('includes/workspace/flow_run_cards.php');

	foreach (array('mailbox', 'direct', 'thread', 'guest', '') as $kind) {
		pg_assert_same(array(), ws_flows_here(array('id' => 5, 'member' => true), array('id' => 3, 'kind' => $kind)), 'no flow in a channel of kind "' . $kind . '"');
	}

	pg_assert_contains('ws_flows_runnable_in($viewer, (int) $channel[\'id\'])', $flows_here, 'the list Start a flow shows');
}

// A typed name names a flow of the channel only when it is the whole name,
// folded as command names are; a part of it, another flow's name or nothing
// names none.
function test_workspace_command_flow_named()
{
	$flows = array(
		array('id' => 4, 'name' => 'Yeni müşteri', 'version' => 2),
		array('id' => 9, 'name' => 'Weekly report', 'version' => 1),
	);

	pg_assert_same(4, ws_command_flow_named($flows, 'Yeni müşteri')['id']);
	pg_assert_same(4, ws_command_flow_named($flows, '  YENİ   MÜŞTERİ ')['id'], 'case and spaces');
	pg_assert_same(4, ws_command_flow_named($flows, 'yeni musteri')['id'], 'Turkish letters folded');
	pg_assert_same(9, ws_command_flow_named($flows, 'weekly report')['id']);
	pg_assert_same(null, ws_command_flow_named($flows, 'Yeni'), 'a part of a name');
	pg_assert_same(null, ws_command_flow_named($flows, 'Monthly report'), 'no such flow');
	pg_assert_same(null, ws_command_flow_named($flows, ''), 'nothing typed');
	pg_assert_same(null, ws_command_flow_named(array(), 'Weekly report'), 'no flow here');
}

// The composer's entry of each flow: id, name and the number of its live
// version, which the start's question names.
function test_workspace_command_flow_here_entries()
{
	$rows = array(
		array('id' => '4', 'name' => 'Yeni müşteri', 'version_id' => '31'),
		array('id' => '9', 'name' => 'Weekly report', 'version_id' => '40'),
	);

	pg_assert_same(array(
		array('id' => 4, 'name' => 'Yeni müşteri', 'version' => 3),
		array('id' => 9, 'name' => 'Weekly report', 'version' => 0),
	), ws_flows_here_present($rows, array(31 => 3)));
}

// /flow writes nothing: a flow named goes back to the composer, which asks
// and starts it; none named opens Start a flow's list. Not in a discussion.
function test_workspace_command_flow_runner()
{
	$source = workspace_command_flow_test_source('includes/workspace/commands.php');
	$start = strpos($source, 'function ws_command_run_flow(');
	$runner = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	pg_assert_contains("array('type' => 'flow_start', 'flow' => \$flow)", $runner);
	pg_assert_contains("array('type' => 'flow_form')", $runner);
	pg_assert_false(strpos($runner, 'ws_flow_run_start(') !== false, 'nothing is started on the server from a typed line');

	$thread = array('id' => 8, 'kind' => 'thread', 'name' => 'x', 'archived_at' => 0, 'parent_id' => 7);
	$answer = ws_command_run(array('id' => 5), $thread, '/akış Weekly report');

	pg_assert_same('error', $answer['type'], 'refused in a discussion');
	pg_assert_same(lang('This command runs in the channel itself, not in one of its discussions.'), $answer['error']);

	$answer = ws_command_run(null, array('id' => 7, 'kind' => 'direct'), '/flow');

	pg_assert_same('error', $answer['type'], 'refused outside a public or private channel');
}

// A flow's name typed with a slash is no command: it is answered as any
// unknown command, never started.
function test_workspace_command_flow_name_typed_alone_is_unknown()
{
	pg_assert_same('weeklyreport', ws_command_unknown('/weeklyreport'), 'read as an unknown command');
	pg_assert_same(null, ws_command_split('/weeklyreport'), 'no built-in of that name');

	$send = workspace_command_flow_test_js_method('send');

	pg_assert_contains("result.command.type === 'flow_start'", $send, 'only /flow\'s answer starts');
}

// The "/" list: the channel's flows after the commands under a header of
// their own, filtered by the word typed, /flow left out where no flow can be
// started; a pick asks and starts the flow through ws_flow_run_start() with
// the channel, as Start a flow does, and writes nothing into the box.
function test_workspace_command_flow_picker_js()
{
	$rows = workspace_command_flow_test_js_function('commandPickerRows');
	$fold = workspace_command_flow_test_js_function('foldText');
	$flow_rows = workspace_command_flow_test_js_function('flowPickerRows');
	$add = workspace_command_flow_test_js_function('commandPickerAddFlows');

	pg_assert_true(($rows !== '') && ($fold !== '') && ($flow_rows !== '') && ($add !== ''), 'the functions are there');

	$pick = workspace_command_flow_test_js_method('pick');
	$start = workspace_command_flow_test_js_method('startFlowHere');

	pg_assert_contains('this.startFlowHere(item.flow, null, null);', $pick, 'a pick starts the flow');
	pg_assert_contains("api('ws_flow_run_start', { flow_id: flow.id, channel_id: channelId, start_token: flow.startToken })", $start, 'through the start of Start a flow');
	pg_assert_contains("ask(t('fl_run_ask', flow.name, flow.version), t('fl_run_do'))", $start, 'asked first');

	$script = $fold . $rows . $flow_rows . $add . '
var commands = [
	{ key: "task", name: "/task", names: ["/task", "/görev"], group: "tasks", group_label: "Tasks", where: "any" },
	{ key: "flow", name: "/flow", names: ["/flow", "/akış"], group: "agents", group_label: "Agents", where: "channel" }
];
var flows = [{ id: 4, name: "Yeni müşteri", version: 2 }, { id: 9, name: "Weekly report", version: 1 }];
var labels = function (list) { return list.map(function (row) { return row.header ? "#" + row.label : row.label; }); };
console.log(JSON.stringify({
	all: labels(commandPickerAddFlows(commandPickerRows(commands, "", false, undefined, true), flowPickerRows(flows, "", "Start"), "Flows")),
	none: labels(commandPickerRows(commands, "", false, undefined, false)),
	word: labels(flowPickerRows(flows, "rep", "Start")),
	folded: labels(flowPickerRows(flows, "MUS", "Start")),
	nothing: labels(commandPickerAddFlows([], flowPickerRows(flows, "zz", "Start"), "Flows")),
	carries: flowPickerRows(flows, "wee", "Start")[0].flow.id
}));
';

	$out = workspace_command_flow_test_node($script);

	if ($out === null) {
		return;
	}

	pg_assert_same(array('/task', '/flow', '#Flows', 'Yeni müşteri', 'Weekly report'), $out['all'], 'flows after the commands, under their header');
	pg_assert_same(array('/task'), $out['none'], 'no /flow where no flow can be started');
	pg_assert_same(array('Weekly report'), $out['word'], 'a word of the name');
	pg_assert_same(array('Yeni müşteri'), $out['folded'], 'Turkish letters folded');
	pg_assert_same(array(), $out['nothing'], 'no header without a flow');
	pg_assert_same(9, $out['carries'], 'the row carries its flow');
}

// The flows are read once, as the channel opens; "/" reads nothing.
function test_workspace_command_flow_read_once()
{
	$js = workspace_command_flow_test_source('assets/js/workspace.js');

	pg_assert_contains('self.flowsList = data.flows || [];', $js, 'kept as the channel opens');
	pg_assert_false(strpos(workspace_command_flow_test_js_function('flowPickerRows'), 'api(') !== false, 'the rows read no server');
}

// A channel about a customer stands for a contact: the contact itself, or
// the one kept for a company or an account; a start there is about them
// when the starter reads the contact.
function test_workspace_command_flow_customer_channel_contact()
{
	pg_assert_same(12, ws_flow_channel_contact_id(array('customer_type' => 'contact', 'customer_id' => 12, 'contact_id' => 12)));
	pg_assert_same(30, ws_flow_channel_contact_id(array('customer_type' => 'company', 'customer_id' => 5, 'contact_id' => 30)), 'a company\'s contact');
	pg_assert_same(7, ws_flow_channel_contact_id(array('customer_type' => '', 'customer_id' => 0, 'contact_id' => 7)), 'a channel written before the kinds');
	pg_assert_same(0, ws_flow_channel_contact_id(array('customer_type' => '', 'customer_id' => 0, 'contact_id' => 0)), 'about nobody');
	pg_assert_same(0, ws_flow_channel_contact_id(null));

	$source = workspace_command_flow_test_source('includes/workspace/flow_run_cards.php');
	$start = strpos($source, 'function ws_flow_run_channel_contact(');
	$contact = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	pg_assert_contains('ws_flow_run_contact_check($viewer, $contact_id)[\'ok\']', $contact, 'only a contact the starter reads');

	$actions = workspace_command_flow_test_source('includes/workspace/actions.php');
	$case = substr($actions, (int) strpos($actions, "case 'ws_flow_run_start':"), 1800);

	pg_assert_contains("ws_flow_run_channel_contact(\$viewer, ws_channel(\$fields['channel_id']))", $case, 'the composer\'s start names the customer');
	pg_assert_contains("!isset(\$fields['contact_id'])", $case, 'a customer picked on the canvas stays');
}
