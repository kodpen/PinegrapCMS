<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Flows screen on a site whose Workspace is off: the screen's
 * gate, the panel actions the switch lets through, what the screen leaves
 * out that lives in a channel, and the boxes that need the Workspace (not
 * offered, a problem on the canvas, not tried).
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');

// The source of a PHP function of a product file, up to its closing brace.
function automation_flows_screen_test_php($file, $name)
{
	$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file));
	$start = strpos($source, "\nfunction " . $name . '(');
	$end = ($start !== false) ? strpos($source, "\n}\n", $start) : false;

	return (($start !== false) && ($end !== false)) ? substr($source, $start, $end - $start) : '';
}

// The source of one case of ws_handle_action(), up to the next case.
function automation_flows_screen_test_case($action)
{
	$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php'));
	$start = strpos($source, "case '" . $action . "':");
	$end = ($start !== false) ? strpos($source, "\n        case '", $start + 10) : false;

	return (($start !== false) && ($end !== false)) ? substr($source, $start, $end - $start) : '';
}

// A function of workspace_flow.js, as the script has it.
function automation_flows_screen_test_js($name)
{
	$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js'));

	return preg_match('/\n    function ' . preg_quote($name, '/') . '\(.*?\n    \}\n/s', $source, $match) ? $match[0] : '';
}

// Runs functions of workspace_flow.js in Node with the script given, which
// prints its result as JSON; null where Node is not there.
function automation_flows_screen_test_node(array $names, $script)
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

	$body = "var FL = {};\nvar K = { foldText: function (text) { return String(text).toLowerCase(); } };\n";

	foreach ($names as $name) {
		$function = automation_flows_screen_test_js($name);
		pg_assert_true($function !== '', 'workspace_flow.js has ' . $name . '()');
		$body .= $function . "\n";
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgafscreen');

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

// A box of a graph.
function automation_flows_screen_test_node_box($id, $kind, $config = array())
{
	return array('id' => $id, 'kind' => $kind, 'label' => '', 'x' => 0, 'y' => 0, 'config' => $config);
}

// With the Workspace on every action passes; with it off only the Flows
// screen's, once flows run, and of those a search for contacts and a share
// with a person.
function test_automation_flows_screen_switch_allows()
{
	pg_assert_true(ws_action_switch_allows('ws_channel_open', array(), true, false), 'Workspace on: any action');
	pg_assert_true(ws_action_switch_allows('ws_flow_list', array(), true, true), 'Workspace on: a flow action');

	pg_assert_false(ws_action_switch_allows('ws_flow_list', array(), false, false), 'Workspace off, no flows: nothing');
	pg_assert_true(ws_action_switch_allows('ws_flow_list', array(), false, true), 'Workspace off: the flow list');
	pg_assert_true(ws_action_switch_allows('ws_bootstrap', array(), false, true), 'Workspace off: the team for the screen');
	pg_assert_true(ws_action_switch_allows('ws_tick', array(), false, true), 'Workspace off: the screen\'s run of what is due');

	foreach (array('ws_channel_open', 'ws_sync', 'ws_palette', 'ws_side', 'ws_scheduled_list', 'ws_task_get',
		'ws_flow_editor_config', 'ws_flow_embed_config', 'ws_flow_draft_view', 'ws_flow_draft_graph', 'ws_flow_from_draft',
		'ws_flow_scheduled_copy', 'ws_flow_scheduled_edit') as $action) {
		pg_assert_false(ws_action_switch_allows($action, array(), false, true), 'Workspace off: ' . $action . ' is the Workspace\'s');
	}

	pg_assert_true(ws_action_switch_allows('ws_ref_search', array('type' => 'contact', 'q' => 'a'), false, true), 'a customer for Run');
	pg_assert_false(ws_action_switch_allows('ws_ref_search', array('type' => 'channel', 'q' => 'a'), false, true), 'not the Workspace\'s records');
	pg_assert_false(ws_action_switch_allows('ws_ref_search', array('q' => 'a'), false, true), 'not every kind at once');

	pg_assert_true(ws_action_switch_allows('ws_flow_share_set', array('target_type' => 'user', 'target_id' => 4), false, true), 'a share with a person');
	pg_assert_false(ws_action_switch_allows('ws_flow_share_set', array('target_type' => 'channel', 'target_id' => 4), false, true), 'no share in a channel');
	pg_assert_true(ws_action_switch_allows('ws_flow_share_remove', array('share_id' => 4), false, true), 'a share taken back, a channel\'s too');
}

// Every action the Flows screen's script sends passes with the Workspace
// off, and every action on the list is a panel action ws_handle_action()
// answers.
function test_automation_flows_screen_actions_are_the_screens()
{
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');
	preg_match_all("/'(ws_[a-z_]+)'/", $script, $found);
	$sent = array_values(array_unique($found[1]));
	$allowed = ws_flow_actions_without_workspace();

	pg_assert_true(count($sent) > 20, 'the script names its actions');

	foreach ($sent as $action) {
		pg_assert_true(in_array($action, $allowed, true), $action . ' is sent by the Flows screen');
	}

	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');
	$handler = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');

	foreach ($allowed as $action) {
		pg_assert_true(strpos($panel, "'" . $action . "'") !== false, $action . ' is a panel action');
		pg_assert_true(strpos($handler, "case '" . $action . "':") !== false, $action . ' is answered');
	}

	$shell = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	pg_assert_contains("api('ws_bootstrap'", $shell, 'the shell asks for the team');
	pg_assert_contains("api('ws_tick')", $shell, 'and runs what is due');
}

// The screen's gate, the switch in the actions' door and what the
// actions answer with the Workspace off.
function test_automation_flows_screen_gate_and_answers()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_flows.php');

	pg_assert_contains('$viewer = ws_screen_gate($user, true);', $page, 'the Flows screen asks the flows\' gate');

	$gate = automation_flows_screen_test_php('includes/workspace/screen.php', 'ws_screen_gate');

	pg_assert_contains('if (!ws_enabled() && !($flows && pg_flows_enabled())) {', $gate, 'the Flows screen opens with the Workspace off');
	pg_assert_true(strpos($gate, 'if (!ws_ready()) {') < strpos($gate, '!ws_enabled()'), 'a site not yet updated is told so first');
	pg_assert_true(strpos($gate, "\$viewer['member']") > strpos($gate, '!ws_enabled()'), 'the team\'s rights decide after the switch');

	$assets = automation_flows_screen_test_php('includes/workspace/screen.php', 'ws_screen_assets');

	pg_assert_contains("if (ws_enabled()) {\n        \$output .= pg_js_bundle_tags('assets/js/workspace_palette.min.js');", $assets, 'the search box only with the Workspace');

	$door = automation_flows_screen_test_php('includes/workspace/actions.php', 'ws_handle_action');

	pg_assert_contains('if (!ws_action_switch_allows($action, $request, ws_enabled(), ws_ready() && pg_flows_enabled())) {', $door, 'the switch asked first');
	pg_assert_true(strpos($door, 'ws_action_switch_allows(') < strpos($door, '$viewer = ws_viewer($user);'), 'before the person is read');
	pg_assert_false(strpos($door, "    if (!ws_enabled()) {\n        return ws_action_error") !== false, 'no refusal of every action left');

	$tick = automation_flows_screen_test_case('ws_tick');
	$off = strpos($tick, "if (!ws_enabled()) {\n                if (function_exists('ws_scheduled_run')) {\n                    ws_scheduled_run();\n                }\n\n                return ws_action_ok();");

	pg_assert_true($off !== false, 'with the Workspace off a look runs only the scheduled run');
	pg_assert_true($off < strpos($tick, 'ws_recurrence_tick()'), 'before the Workspace\'s own parts');

	$boot = automation_flows_screen_test_case('ws_bootstrap');

	pg_assert_contains("'channels'    => !\$workspace ? array() :", $boot, 'no channels with the Workspace off');
	pg_assert_contains("'inbox'       => !\$workspace ? 0 :", $boot, 'no inbox with the Workspace off');

	pg_assert_contains("(\$runnable || !ws_enabled()) ? null : ws_flows_scheduled_rows(\$viewer)", automation_flows_screen_test_case('ws_flow_list'), 'the list ends with no scheduled actions');
	pg_assert_contains("((int) (\$request['channel_id'] ?? 0) > 0) && ws_enabled()", automation_flows_screen_test_case('ws_flow_run_start'), 'no run card into a channel');

	$config = automation_flows_screen_test_php('includes/workspace/flows.php', 'ws_flow_js_config');

	pg_assert_contains("'workspace' => ws_enabled(),", $config, 'the screen is told whether the Workspace is on');
	pg_assert_contains("'workspace_kinds' => ws_flow_kinds_workspace(),", $config, 'and which boxes need it');
}

// What the script leaves out with the Workspace off: the home channel
// (field, column, search), a share in a channel, a box's task card and the
// boxes that need the Workspace.
function test_automation_flows_screen_script_leaves_channels_out()
{
	$result = automation_flows_screen_test_node(array('listMatches', 'homeField', 'taskShown', 'memberKind'), <<<'JS'
var asked = 0;
function memberOwn() { asked++; return false; }
var out = {};
var flow = { name: 'Morning report', status: 'live', start: 'time', start_text: 'Every day', channel_name: 'sales' };
out.onSearch = listMatches(flow, '', 'sales');
out.onKinds = [memberKind('post'), memberKind('email')];
out.onTask = [taskShown({ task_id: 5 }), taskShown({ task_id: 0 })];
FL = { workspace: false, workspace_kinds: ['post', 'task'] };
out.offSearch = [listMatches(flow, '', 'sales'), listMatches(flow, '', 'morning')];
var box = { children: [], appendChild: function (child) { this.children.push(child); return child; } };
homeField(box);
out.offHome = [box.children.length, asked];
out.offKinds = [memberKind('post'), memberKind('task'), memberKind('email')];
out.offTask = taskShown({ task_id: 5 });
FL = { workspace: false, workspace_kinds: ['post'], member_kinds: ['email'] };
out.member = [memberKind('email'), memberKind('webhook'), memberKind('post')];
console.log(JSON.stringify(out));
JS
	);

	if (is_array($result)) {
		pg_assert_true($result['onSearch'], 'Workspace on: the home channel is searched');
		pg_assert_same(array(true, true), $result['onKinds'], 'Workspace on: every box is offered');
		pg_assert_same(array(true, false), $result['onTask'], 'Workspace on: a box\'s task is linked');
		pg_assert_same(array(false, true), $result['offSearch'], 'Workspace off: the home is not searched, the name is');
		pg_assert_same(array(0, 0), $result['offHome'], 'Workspace off: no home field');
		pg_assert_same(array(false, false, true), $result['offKinds'], 'Workspace off: its boxes are not offered');
		pg_assert_false($result['offTask'], 'Workspace off: no task card');
		pg_assert_same(array(true, false, false), $result['member'], 'a member\'s own kinds, without the Workspace\'s');
	}

	$draw = automation_flows_screen_test_js('drawFlows');

	pg_assert_contains("['fl_col_flow', 'fl_col_start', 'fl_col_state', 'fl_flag_last'].concat((FL.workspace !== false) ? ['fl_col_channel'] : [])", $draw, 'the head has the home column only with the Workspace');

	$row = automation_flows_screen_test_js('flowRow');

	pg_assert_contains("if (FL.workspace !== false) {\n            cells.push([channel, 'fl_col_channel']);", $row, 'and the row its cell');

	pg_assert_contains("if (FL.workspace !== false) {\n                body.appendChild(shareChannel(shares));", automation_flows_screen_test_js('drawShares'), 'no share in a channel with the Workspace off');

	foreach (array('paintRun', 'stepDetail', 'outlineRun') as $name) {
		$function = automation_flows_screen_test_js($name);

		pg_assert_contains('taskShown(step)', $function, $name . '() links a task only with the Workspace');
		pg_assert_false(strpos($function, '&& step.task_id) {') !== false, $name . '() asks no task id of its own');
	}
}

// With the Workspace off a box that needs it is a problem on that box;
// with it on, none.
function test_automation_flows_screen_workspace_problems()
{
	$graph = array('v' => 1, 'nodes' => array(
		automation_flows_screen_test_node_box('start', 'manual'),
		automation_flows_screen_test_node_box('say', 'post', array('channel_id' => 3, 'body' => 'Hi')),
		automation_flows_screen_test_node_box('mail', 'email', array('to' => 'a@example.com')),
		automation_flows_screen_test_node_box('sum', 'report', array('channel_id' => 0, 'to' => 'a@example.com')),
	), 'edges' => array(), 'notes' => array());

	pg_assert_same(array(), ws_flow_workspace_problems(true, $graph), 'Workspace on: no problem');

	$problems = ws_flow_workspace_problems(false, $graph);

	pg_assert_same(array('workspace_off'), array_column($problems, 'code'), 'one problem');
	pg_assert_same('say', $problems[0]['node'], 'on the box that needs it');
	pg_assert_false(isset($problems[0]['warning']), 'a problem, not a warning: it stops publishing');
	pg_assert_true(strpos($problems[0]['text'], 'Workspace') !== false, 'it names the Workspace');

	pg_assert_true(ws_flow_graph_needs_workspace($graph), 'a message in a channel needs it');

	$graph['nodes'][1] = automation_flows_screen_test_node_box('say', 'webhook');
	pg_assert_false(ws_flow_graph_needs_workspace($graph), 'an e-mail, a webhook and a summary by e-mail do not');
	pg_assert_same(array(), ws_flow_workspace_problems(false, $graph), 'Workspace off: nothing to mark');

	$graph['nodes'][3] = automation_flows_screen_test_node_box('sum', 'report', array('channel_id' => 5, 'to' => ''));
	pg_assert_same(array('sum'), array_column(ws_flow_workspace_problems(false, $graph), 'node'), 'a summary posted into a channel');
	pg_assert_false(ws_flow_graph_needs_workspace(array()), 'an empty canvas');
}

// The problem is shown and stops publishing wherever the graph's own
// problems do; what is offered to start from leaves those boxes out; Try
// refuses them.
function test_automation_flows_screen_boxes_offered_only_with_it()
{
	$asked = 'ws_flow_workspace_problems(ws_enabled(), ';

	pg_assert_contains($asked . '$graph)', automation_flows_screen_test_php('includes/workspace/flows.php', 'ws_flow_save_check'), 'a save answers with it');
	pg_assert_contains($asked . '$graph)', automation_flows_screen_test_php('includes/workspace/flows.php', 'ws_flow_publish_check'), 'publishing is refused on it');
	pg_assert_contains($asked . '$draft)', automation_flows_screen_test_php('includes/workspace/flows.php', 'ws_flow_detail'), 'an opened flow shows it');

	pg_assert_contains("return !ws_flow_graph_needs_workspace(\$template['graph'] ?? array());", automation_flows_screen_test_php('includes/workspace/flow_templates.php', 'ws_flow_templates_for'), 'no template holding one');
	pg_assert_contains('array_diff(($kinds === null) ? array_keys(ws_flow_kinds()) : $kinds, ws_flow_kinds_workspace())', automation_flows_screen_test_php('includes/workspace/flow_quick.php', 'ws_flow_quick_fill'), 'the quick setup offers none');
	pg_assert_contains("(\$workspace || !in_array(\$kind['kind'], ws_flow_kinds_workspace(), true))", automation_flows_screen_test_php('includes/workspace/flow_draft.php', 'ws_flow_ai_catalogue'), 'Pinegrap AI drafts none');

	$try = automation_flows_screen_test_php('includes/workspace/steps.php', 'ws_flow_box_try');
	$member = strpos($try, 'ws_flow_member_box_refusal(ws_flow_builder_kind($owner), $node)');
	$workspace = strpos($try, 'ws_flow_workspace_box_refusal(ws_enabled(), $node)');

	pg_assert_true(($member !== false) && ($workspace !== false) && ($member < $workspace), 'Try refuses it after the member\'s own refusal');
	pg_assert_contains("memberKind(item[0])", automation_flows_screen_test_js('paletteItems'), 'the palette asks memberKind()');
	pg_assert_contains("!memberKind(kind)", automation_flows_screen_test_js('addItem'), 'so does adding a box');
}
