<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the workspace's route table in the external API
 * (includes/workspace/api.php) that need no database: every route reaches a
 * handler, no two routes answer the same method on the same path, each
 * action becomes one MCP tool, and the routes only Claude's routine or the
 * person on the card may use are no tools. Also the scopes the search asks
 * of each kind of panel record (includes/workspace/api_day.php), who a call
 * from a device writes as, which of its messages ask an assistant, where what
 * they ask is carried on, how a channel's file is handed out
 * (includes/workspace/files.php), that a handler calls the panel's
 * functions that log in the signed-in person's name as the application's
 * owner, whose name a bell row carries, and that every write can be
 * rehearsed and stops before it writes. Also that the ready-made MCP
 * connectors are listed to an administrator only, and by no MCP tool.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_MCP_ENTRY')) {
	define('PG_MCP_ENTRY', true);
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');

function test_ws_api_routes_reach_a_handler()
{
	$ids = array();

	foreach (ws_api_routes() as $route) {
		pg_assert_false(isset($ids[$route['id']]), $route['id'] . ' is declared once');
		$ids[$route['id']] = true;

		pg_assert_true(function_exists($route['handler']), $route['id'] . ': ' . $route['handler'] . '() exists');
		pg_assert_true(in_array($route['scope'], array('workspace:read', 'workspace:write', 'tasks:read', 'tasks:write'), true), $route['id'] . ' asks for one of the module\'s scopes');
	}
}

// The router takes the first route that matches, so a method a route accepts
// beside its own must not be one another route on the same path answers.
function test_ws_api_routes_do_not_share_a_method_and_path()
{
	$seen = array();

	foreach (ws_api_routes() as $route) {
		$methods = array_merge(array($route['method']), (array) ($route['also_accepts'] ?? array()));

		foreach ($methods as $method) {
			$key = $method . ' ' . $route['path'];

			pg_assert_false(isset($seen[$key]), $key . ' is ' . $route['id'] . (isset($seen[$key]) ? ' and ' . $seen[$key] : ''));
			$seen[$key] = $route['id'];
		}
	}
}

// A removal addressed as a POST for IIS is the same action as the DELETE, and
// is not offered as a second tool.
function test_ws_api_plan_item_removal_is_one_tool()
{
	$offered = array();

	foreach (ws_api_routes() as $route) {
		if (pg_mcp_route_is_offered($route)) {
			$offered[] = pg_mcp_tool_name($route['id']);
		}
	}

	pg_assert_contains('workspace_plan_items_delete', $offered);
	pg_assert_false(in_array('workspace_plan_items_delete_post', $offered, true), 'the POST alias is no tool');

	foreach (array('workspace_notes_create', 'workspace_notes_update', 'workspace_plan_items_list', 'workspace_plan_items_create',
		'workspace_plan_items_update', 'workspace_search', 'workspace_my_day_get') as $tool) {
		pg_assert_contains($tool, $offered);
	}
}

// A channel is marked read with a tool; changing, deleting and hiding a
// message are no tools, since only a team devices session may make them and
// an MCP client signs in through an OAuth connection.
function test_ws_api_message_edit_delete_and_hide_are_no_tools()
{
	$offered = array();

	foreach (ws_api_routes() as $route) {
		if (pg_mcp_route_is_offered($route)) {
			$offered[] = pg_mcp_tool_name($route['id']);
		}
	}

	pg_assert_contains('workspace_channels_read', $offered);

	foreach (array('workspace_messages_update', 'workspace_messages_delete', 'workspace_messages_delete_post', 'workspace_messages_hide') as $tool) {
		pg_assert_false(in_array($tool, $offered, true), $tool . ' is no tool');
	}
}

// The changes a phone makes to messages and read marks can each be rehearsed
// with a dry run.
function test_ws_api_message_writes_rehearse()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	pg_assert_true(isset($routes['workspace.messages.hide']), 'hide is declared');

	foreach (array('workspace.messages.update', 'workspace.messages.delete', 'workspace.messages.delete_post', 'workspace.messages.hide', 'workspace.channels.read') as $id) {
		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' takes dry_run');
	}
}

// The source of a route's handler, to read which gate it calls.
function ws_api_test_handler_source($handler)
{
	$function = new ReflectionFunction($handler);
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

// A route that only a team devices session may call refuses every OAuth
// connection, and so every MCP client: it is offered to none as a tool. The
// gate is called by the handler itself or by a helper of the module it calls
// (ws_api_pin_target()).
function test_ws_api_own_words_routes_are_no_tools()
{
	$gates = array('ws_api_own_words_only(');

	foreach (get_defined_functions()['user'] as $name) {
		if ((strpos($name, 'ws_api_') === 0) && ($name !== 'ws_api_own_words_only') && (strpos(ws_api_test_handler_source($name), 'ws_api_own_words_only(') !== false)) {
			$gates[] = $name . '(';
		}
	}

	$gated = array();

	foreach (ws_api_routes() as $route) {
		$source = ws_api_test_handler_source($route['handler']);

		foreach ($gates as $gate) {
			if (strpos($source, $gate) !== false) {
				$gated[] = $route['id'];
				pg_assert_false(pg_mcp_route_is_offered($route), $route['id'] . ' is no MCP tool');
				break;
			}
		}
	}

	foreach (array('workspace.messages.update', 'workspace.messages.delete', 'workspace.messages.hide', 'workspace.messages.pin',
		'workspace.messages.pin_close', 'workspace.polls.create', 'workspace.polls.update', 'workspace.scheduled_messages.create',
		'workspace.flows.publish', 'workspace.flow_shares.set', 'workspace.flow_shares.delete', 'workspace.flow_shares.delete_post') as $id) {
		pg_assert_contains($id, $gated);
	}
}

// The queues Claude's routine works through (the Workspace requests and the
// translation jobs) answer only the key of the application chosen for
// Claude, never an OAuth connection, and the routine calls them over REST: a
// route whose handler calls either guard, itself or through a helper, is
// offered to no MCP client as a tool. The channel context has no guard and
// stays one.
function test_ws_api_claude_only_routes_are_no_tools()
{
	$guards = array('ws_api_claude_guard', 'translate_api_claude_guard');
	$gates = array();

	foreach ($guards as $guard) {
		$gates[] = $guard . '(';
	}

	foreach (get_defined_functions()['user'] as $name) {
		if (((strpos($name, 'ws_api_') === 0) || (strpos($name, 'translate_api_') === 0)) && !in_array($name, $guards, true)) {
			$source = preg_replace('~^\s*//.*$~m', '', ws_api_test_handler_source($name));

			foreach ($guards as $guard) {
				if (strpos($source, $guard . '(') !== false) {
					$gates[] = $name . '(';
					break;
				}
			}
		}
	}

	$gated = array();
	$routes = array();

	foreach (array_merge(ws_api_routes(), translate_api_routes()) as $route) {
		$routes[$route['id']] = $route;
		$source = preg_replace('~^\s*//.*$~m', '', ws_api_test_handler_source($route['handler']));

		foreach ($gates as $gate) {
			if (strpos($source, $gate) !== false) {
				$gated[] = $route['id'];
				pg_assert_same(false, $route['mcp'] ?? null, $route['id'] . ' carries \'mcp\' => false');
				pg_assert_false(pg_mcp_route_is_offered($route), $route['id'] . ' is no MCP tool');
				break;
			}
		}
	}

	foreach (array('workspace.claude.requests.list', 'workspace.claude.requests.claim', 'workspace.claude.requests.answer',
		'workspace.claude.requests.fail', 'workspace.claude.change_types', 'workspace.claude.record', 'translations.jobs.list',
		'translations.jobs.claim', 'translations.jobs.items', 'translations.jobs.results', 'translations.jobs.fail') as $id) {
		pg_assert_contains($id, $gated);
	}

	pg_assert_false(in_array('workspace.channels.context', $gated, true), 'the channel context has no guard');
	pg_assert_true(pg_mcp_route_is_offered($routes['workspace.channels.context']), 'the channel context stays a tool');
	pg_assert_true(pg_mcp_route_is_offered($routes['translations.strings.list']), 'the translations themselves stay tools');
}

// Deciding an approval and applying or setting aside a change an assistant
// proposed are the person's decisions, made on the card: an MCP client is
// offered none of them as a tool, so an assistant never decides in the
// person's name or applies what it proposed. A REST caller keeps them, and
// asking for an approval and reading the cards stay tools.
function test_ws_api_card_decisions_are_no_tools()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	foreach (array('workspace.approvals.decide', 'workspace.changes.apply', 'workspace.changes.dismiss') as $id) {
		pg_assert_true(isset($routes[$id]), $id . ' is declared');
		pg_assert_same('POST', $routes[$id]['method'], $id . ' is still a REST write');
		pg_assert_same(false, $routes[$id]['mcp'] ?? null, $id . ' carries \'mcp\' => false');
		pg_assert_false(pg_mcp_route_is_offered($routes[$id]), $id . ' is no MCP tool');
		pg_assert_contains('ws_api_person_only(', ws_api_test_handler_source($routes[$id]['handler']), $id . ' still refuses an application key');
	}

	foreach (array('workspace.approvals.list', 'workspace.approvals.create', 'workspace.changes.list') as $id) {
		pg_assert_true(pg_mcp_route_is_offered($routes[$id]), $id . ' stays a tool');
	}
}

// An AI app or an integration may propose a record change instead of writing
// it: one route in a channel and its twin on a task, both MCP tools under
// workspace:write that take the same changes as an assistant's answer,
// rehearse, and are marked as writes that only add (a card), not as
// destructive ones.
function test_ws_api_change_proposals_are_tools()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$answer = null;

	foreach ($routes['workspace.claude.requests.answer']['params'] as $param) {
		if ($param['name'] === 'changes') {
			$answer = $param;
		}
	}

	foreach (array('workspace.changes.propose' => '/workspace/channels/{id}/changes', 'workspace.tasks.changes.propose' => '/workspace/tasks/{id}/changes') as $id => $path) {
		pg_assert_true(isset($routes[$id]), $id . ' is declared');

		$route = $routes[$id];

		pg_assert_same('POST', $route['method'], $id);
		pg_assert_same($path, $route['path'], $id);
		pg_assert_same('workspace:write', $route['scope'], $id . ' asks workspace:write');
		pg_assert_false(isset($route['mcp']) && ($route['mcp'] === false), $id . ' carries no \'mcp\' => false');
		pg_assert_true(pg_mcp_route_is_offered($route), $id . ' is an MCP tool');
		pg_assert_true(!empty($route['dry_run']), $id . ' rehearses');
		pg_assert_same('WorkspaceProposal', $route['returns'], $id);
		pg_assert_same(201, $route['status'] ?? null, $id . ' answers 201');

		$tool = pg_mcp_tool_from_route($route);
		$properties = (array) $tool['inputSchema']['properties'];

		pg_assert_same(str_replace('.', '_', $id), $tool['name'], $id);
		pg_assert_same('boolean', $properties['dry_run']['type'] ?? null, $id . ': the tool takes dry_run');
		pg_assert_contains('text', $tool['inputSchema']['required'], $id);
		pg_assert_contains('changes', $tool['inputSchema']['required'], $id);
		pg_assert_false($tool['annotations']['readOnlyHint'], $id . ' writes');
		pg_assert_false($tool['annotations']['destructiveHint'], $id . ' only adds a card');

		// The same changes as an assistant's answer, by the same limit.
		$changes = null;

		foreach ($route['params'] as $param) {
			if ($param['name'] === 'changes') {
				$changes = $param;
			}
		}

		pg_assert_same($answer['of'], $changes['of'], $id . ' takes the answer\'s changes');
		pg_assert_same(WS_CHANGES_PER_ANSWER, $changes['max_items'], $id . ' takes as many as an answer');

		// What a model reads: it proposes, the person applies, nothing is
		// written to a record by the call.
		$text = $tool['description'];

		pg_assert_contains('this only proposes, the person applies', $text, $id);
		pg_assert_contains('Nothing is written to a record here', $text, $id);
		pg_assert_contains('join this same endpoint', $text, $id . ' says new kinds come here');
	}

	// A caller with workspace:write only is offered both.
	$scopes = array('workspace:read', 'workspace:write');

	foreach (array('workspace.changes.propose', 'workspace.tasks.changes.propose') as $id) {
		pg_assert_true(api_has_scope($scopes, $routes[$id]['scope']), $id . ' is listed for workspace:write');
	}
}

// A proposal writes no record and applies nothing: its handlers and the
// write they end in neither call a writer nor take a card; its request row is
// written answered under the application's agent, so no assistant's queue
// takes it; and the caller is gated as an application.
function test_ws_api_change_proposals_never_write_records()
{
	foreach (array('ws_api_changes_propose', 'ws_api_task_changes_propose', 'ws_proposal_store', 'ws_api_proposal_rows') as $function) {
		$source = preg_replace('~^\s*//.*$~m', '', ws_api_test_handler_source($function));

		foreach (array('ws_change_apply(', 'ws_change_write(', 'ws_bulk_start(', 'ws_change_dismiss(') as $write) {
			pg_assert_false(strpos($source, $write) !== false, $function . ' calls no ' . $write);
		}
	}

	pg_assert_contains('ws_changes_input(', ws_api_test_handler_source('ws_api_proposal_rows'), 'the changes are checked as an answer\'s');
	pg_assert_contains('ws_changes_store(', ws_api_test_handler_source('ws_proposal_store'), 'and kept as an answer\'s');

	$store = preg_replace('~^\s*//.*$~m', '', ws_api_test_handler_source('ws_proposal_store'));

	pg_assert_contains("'answered'", $store, 'written answered: no queue takes it');
	pg_assert_contains("e(\$agent)", $store, 'under the application\'s agent');

	foreach (array('ws_api_changes_propose', 'ws_api_task_changes_propose') as $handler) {
		pg_assert_contains('ws_api_proposal_agent(', ws_api_test_handler_source($handler), $handler . ' takes keys and connections only');
		pg_assert_contains('ws_api_proposal_scopes(', ws_api_test_handler_source($handler), $handler . ' asks the read scope of each kind');
	}

	pg_assert_contains('ws_api_is_assistant(', ws_api_test_handler_source('ws_api_proposal_agent'), 'an assistant proposes in its answer');
	pg_assert_contains("api_require_scope('tasks:read')", ws_api_test_handler_source('ws_api_task_changes_propose'), 'the task is seen under tasks:read');

	// A user account is read by no API scope: refused before any record is
	// read, so neither its e-mail nor a refusal about its role comes back.
	pg_assert_contains('ws_proposal_closed_change(', ws_api_test_handler_source('ws_api_proposal_scopes'), 'a kind no application proposes is refused first');

	// The refusals ws_changes_input() gives speak of a proposal, not of the
	// person who asked an assistant.
	pg_assert_contains('(int) $task_id, true)', ws_api_test_handler_source('ws_api_proposal_rows'), 'the changes are checked as a proposal\'s');
	pg_assert_contains('ws_bulk_input($asker, $bulk, $for_proposal)', ws_api_test_handler_source('ws_changes_input'), 'and so are many records at once');

	// Requests sent at the same moment all passed the hourly count before
	// any was written: the count is made again under a lock of the
	// application and the person, then the row is written, before its
	// message goes out.
	$at = array();
	$steps = array('GET_LOCK(', 'ws_proposal_limited(', 'INSERT INTO ws_ai_requests', 'ws_message_send(');

	foreach ($steps as $step) {
		$at[$step] = strpos($store, $step);
		pg_assert_true($at[$step] !== false, 'ws_proposal_store: ' . $step);
	}

	for ($i = 1; $i < count($steps); $i++) {
		pg_assert_true($at[$steps[$i - 1]] < $at[$steps[$i]], 'ws_proposal_store: ' . $steps[$i - 1] . ' before ' . $steps[$i]);
	}

	// On a task a proposal waits for the run under way to end, so the bell
	// at the run's end names the run, not a proposal written meanwhile.
	pg_assert_contains('ws_task_delegation_open_run(', ws_api_test_handler_source('ws_api_task_changes_propose'), 'no proposal while an assistant works on the task');

	// A proposal is no earlier answer an assistant gave on the task: nobody
	// asked for it, so the next run is not told somebody did.
	pg_assert_contains("ws_proposal_where('', false)", ws_api_test_handler_source('ws_task_delegation_earlier'), 'proposals are left out of a run\'s earlier work');
	pg_assert_contains('ws_task_delegation_open_row(', ws_api_test_handler_source('ws_task_delegation_detail'), 'the drawer carries on the open run, not the newest row');
}

// A change an application proposed is applied or set aside by the person
// alone, on the card or from their team devices session: an OAuth connection
// is a device row too and passes ws_api_person_only(), so without this it
// could apply the card it wrote itself, writing the record with workspace
// scopes and no write scope of its kind. A change an assistant proposed
// keeps the REST decision of an OAuth connection.
function test_ws_api_proposals_are_decided_by_the_person()
{
	$gate = preg_replace('~^\s*//.*$~m', '', ws_api_test_handler_source('ws_api_proposal_decision_gate'));

	pg_assert_contains('ws_change_proposal_agent(', $gate, 'only a change an application proposed');
	pg_assert_contains('ws_api_own_words_only(', $gate, 'needs the team devices session');

	foreach (array('ws_api_changes_apply', 'ws_api_changes_dismiss') as $handler) {
		$source = preg_replace('~^\s*//.*$~m', '', ws_api_test_handler_source($handler));
		$found = strpos($source, 'ws_api_proposal_decision_gate(');

		pg_assert_true($found !== false, $handler . ' gates an application\'s proposal');
		pg_assert_true(($found !== false) && ($found > strpos($source, 'ws_api_change_or_404(')) && ($found < strpos($source, 'api_dry_run_stop(')), $handler . ': after the change is found, before the rehearsal stops');
		pg_assert_contains('ws_api_person_only(', $source, $handler . ' still refuses an application key');
	}

	// The connection a proposal was made through is the very device row the
	// gate refuses; the person's own team devices session passes.
	$connection = array('id' => 40, 'oauth_client_id' => 5);

	pg_assert_same('oauth:5', ws_proposal_caller_key($connection, 9), 'the connection proposes as oauth:5');
	pg_assert_false(ws_api_device_writes_as_person($connection), 'and cannot apply or set aside what oauth:5 proposed');
	pg_assert_false(ws_api_device_writes_as_person(array('id' => 41, 'oauth_client_id' => 6)), 'nor can another connection');
	pg_assert_false(ws_api_device_writes_as_person(null), 'nor an application key');
	pg_assert_true(ws_api_device_writes_as_person(array('id' => 42, 'oauth_client_id' => 0)), 'the person\'s team devices session can');

	// The route texts promise no more than this.
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	foreach (array('workspace.changes.apply', 'workspace.changes.dismiss') as $id) {
		pg_assert_contains('team devices session', $routes[$id]['description'], $id . ' says who decides an application\'s proposal');
	}
}

// An OAuth connection is a device row too, so a refusal of the team devices
// gate says which session it wants and that a connection is refused as well,
// not "signed in on a device".
function test_ws_api_own_words_refusals_name_the_team_devices_session()
{
	$texts = array();

	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", ws_api_test_handler_source('ws_api_own_words_only'), $found);
	$texts = array_merge($texts, $found[1]);

	foreach (array('api_resources.php', 'api_threads.php', 'api_flows.php') as $file) {
		preg_match_all("/ws_api_own_words_only\\(lang\\('((?:[^'\\\\]|\\\\.)*)'\\)\\)/", file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/' . $file), $found);
		$texts = array_merge($texts, $found[1]);
	}

	pg_assert_true(count($texts) >= 6, 'every refusal is found');

	foreach ($texts as $text) {
		pg_assert_true(strpos($text, 'team devices session (POST /auth/login)') !== false, $text);
		pg_assert_true(strpos($text, 'OAuth connection') !== false, $text);
		pg_assert_false(strpos($text, 'signed in on a device') !== false, $text);
	}
}

// A scheduled message is posted later in the person's own name, so it is
// scheduled from a team devices session only, and no MCP tool schedules one;
// listing and deleting the owner's scheduled messages stay tools.
function test_ws_api_scheduled_message_is_scheduled_by_the_person()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$source = ws_api_test_handler_source($routes['workspace.scheduled_messages.create']['handler']);

	pg_assert_true(strpos($source, 'ws_api_own_words_only(') !== false, 'scheduling asks for the team devices session');
	pg_assert_false(strpos($source, 'ws_api_person_only(') !== false, 'an OAuth connection is not enough');
	pg_assert_false(pg_mcp_route_is_offered($routes['workspace.scheduled_messages.create']), 'scheduling is no MCP tool');
	pg_assert_true(!empty($routes['workspace.scheduled_messages.create']['dry_run']), 'scheduling rehearses');

	foreach (array('workspace.scheduled_messages.list', 'workspace.scheduled_messages.delete') as $id) {
		pg_assert_true(pg_mcp_route_is_offered($routes[$id]), $id . ' is a tool');
	}
}

// A flow is drafted by anybody the owner lets in, an assistant included,
// since a draft never runs; it is published by the person alone, from their
// team devices session, because it later acts in their name. The removal
// addressed as a POST is the same tool as the DELETE.
function test_ws_api_flows_are_drafted_by_tools_and_published_by_the_person()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$publish = ws_api_test_handler_source($routes['workspace.flows.publish']['handler']);

	pg_assert_true(strpos($publish, 'ws_api_own_words_only(') !== false, 'publishing asks for the team devices session');
	pg_assert_false(pg_mcp_route_is_offered($routes['workspace.flows.publish']), 'publishing is no MCP tool');

	foreach (array('workspace.flows.list', 'workspace.flows.get', 'workspace.flows.create', 'workspace.flows.update', 'workspace.flows.pause', 'workspace.flows.delete') as $id) {
		pg_assert_true(pg_mcp_route_is_offered($routes[$id]), $id . ' is a tool');
		pg_assert_false(strpos(ws_api_test_handler_source($routes[$id]['handler']), 'ws_api_own_words_only(') !== false, $id . ' takes an application key');
	}

	pg_assert_false(pg_mcp_route_is_offered($routes['workspace.flows.delete_post']), 'the POST alias is no tool');
	pg_assert_same($routes['workspace.flows.delete']['handler'], $routes['workspace.flows.delete_post']['handler']);
	pg_assert_same(array('PATCH'), $routes['workspace.flows.update']['also_accepts']);
	pg_assert_same(201, $routes['workspace.flows.create']['status']);
}

// The home channel of a published flow decides, at once, who may start it
// and whose channel its new runs open their tasks in: a draft change may
// make it from any caller, but moving a published flow's home is the
// person's own, from their team devices session, as publishing is. The
// change stays a tool for drafts; the gate comes before the rehearsal and
// its refusal names the team devices session.
function test_ws_api_flow_home_of_a_published_flow_is_moved_by_the_person()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$update = ws_api_test_handler_source($routes['workspace.flows.update']['handler']);

	pg_assert_true(strpos($update, 'ws_api_flow_home_move_gate(') !== false, 'the change asks the gate');
	pg_assert_true(strpos($update, 'ws_api_flow_home_move_gate(') < strpos($update, 'api_dry_run_stop('), 'before the rehearsal');
	pg_assert_true(pg_mcp_route_is_offered($routes['workspace.flows.update']), 'drafting stays a tool');

	$gate = ws_api_test_handler_source('ws_api_flow_home_move_gate');

	pg_assert_true(strpos($gate, "(int) \$flow['version_id'] <= 0") !== false, 'a flow never published is left alone');
	pg_assert_true(strpos($gate, 'ws_api_device_writes_as_person(') !== false, 'the team devices session');
	pg_assert_true(strpos($gate, 'team devices session (POST /auth/login)') !== false, 'the refusal names it');
	pg_assert_true(strpos($gate, 'OAuth connection') !== false);
}

// The application Claude works through reaches somebody else's flow only
// along a way it may take: shared with its key owner in person (as a note
// is), shared in a channel they read where Claude may be asked, or live with
// such a channel as its home; and somebody else's run only when it was
// started in such a channel. What the owner of its key owns stays readable.
// Ownership decides, not the access: a colleague shared at edit has edit
// access to a flow that is not theirs.
function test_ws_api_flow_reads_follow_where_claude_may_be_asked()
{
	$none = array('owner' => false, 'person' => false, 'channel' => false, 'home' => false);

	pg_assert_false(ws_api_flow_claude_reaches($none), 'no way at all');

	foreach (array('owner', 'person', 'channel', 'home') as $way) {
		pg_assert_true(ws_api_flow_claude_reaches(array($way => true) + $none), $way);
	}

	foreach (array('ws_api_flow_or_404', 'ws_api_flows_list') as $handler) {
		pg_assert_true(strpos(ws_api_test_handler_source($handler), 'ws_api_flow_reachable($viewer, ') !== false, $handler . ' asks where Claude may be asked');
	}

	foreach (array('ws_api_flow_run_or_404', 'ws_api_flow_runs_list') as $handler) {
		pg_assert_true(strpos(ws_api_test_handler_source($handler), 'ws_api_flow_run_reachable($viewer, ') !== false, $handler . ' asks where the run was started');
	}

	$strip = function ($source) {
		return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
	};

	foreach (array('ws_api_flow_reachable', 'ws_api_flow_run_reachable') as $name) {
		$source = $strip(ws_api_test_handler_source($name));

		pg_assert_true(strpos($source, 'ws_api_is_claude()') !== false, $name . ' asks about Claude');
		pg_assert_true(strpos($source, "(int) \$flow['owner_id'] === (int) \$viewer['id']") !== false, $name . ': the key owner\'s own flows');
		pg_assert_true(strpos($source, "'edit'") === false, $name . ': not edit access, which a share gives too');
	}

	$reachable = $strip(ws_api_test_handler_source('ws_api_flow_reachable'));

	pg_assert_true(strpos($reachable, 'ws_flow_share_rows(') !== false, 'the shares are read');
	pg_assert_true(strpos($reachable, 'ws_api_flow_claude_channel($viewer, (int) $share[\'target_id\'])') !== false, 'a share channel counts only where Claude may be asked');
	pg_assert_true(strpos($reachable, "(string) \$flow['status'] === 'live'") !== false, 'the home counts for a live flow');
	pg_assert_true(strpos($strip(ws_api_test_handler_source('ws_api_flow_claude_allowed')), 'ws_claude_channel_allowed(') !== false);

	// Both lists ask this for every row, so a channel is read once a
	// request: whether the person reads it (ws_flow_channel_readable()) and
	// whether Claude may be asked there (ws_api_flow_claude_allowed()).
	pg_assert_true(strpos($strip(ws_api_test_handler_source('ws_api_flow_claude_allowed')), 'static $') !== false, 'kept for the request');

	foreach (array('ws_api_flow_claude_channel', 'ws_api_flow_run_reachable') as $name) {
		$source = $strip(ws_api_test_handler_source($name));

		pg_assert_true(strpos($source, 'ws_api_flow_claude_allowed(') !== false, $name . ' asks whether Claude may be asked there');
		pg_assert_true(strpos($source, 'ws_channel(') === false, $name . ' reads no channel row itself');
	}

	pg_assert_true(strpos($strip(ws_api_test_handler_source('ws_api_flow_claude_channel')), 'ws_flow_channel_readable(') !== false, 'a channel the key owner reads');
	pg_assert_false(ws_api_flow_claude_allowed(0), 'no channel');
}

// A run opens tasks in the flow owner's name: starting and cancelling one
// are REST calls an application key may make, offered to no MCP client as a
// tool until an AI app can ask the person first, and refused to the
// application an assistant works through; reading runs stays a tool.
// A start answers 201, or 200 for the run the same token started.
function test_ws_api_flow_runs_writes_are_no_tools()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	foreach (array('workspace.flow_runs.start', 'workspace.flow_runs.cancel') as $id) {
		pg_assert_same('POST', $routes[$id]['method'], $id . ' is a REST write');
		pg_assert_same(false, $routes[$id]['mcp'] ?? null, $id . ' carries \'mcp\' => false');
		pg_assert_false(pg_mcp_route_is_offered($routes[$id]), $id . ' is no MCP tool');
		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' rehearses');
		pg_assert_false(strpos(ws_api_test_handler_source($routes[$id]['handler']), 'ws_api_own_words_only(') !== false, $id . ' takes an application key');

		// An assistant's application is refused on the server too: it is
		// not offered as a tool, but Claude's routine calls REST with its key.
		$source = ws_api_test_handler_source($routes[$id]['handler']);

		pg_assert_true(strpos($source, 'if (ws_api_is_assistant())') !== false, $id . ' refuses an assistant');
		pg_assert_true(strpos($source, 'if (ws_api_is_assistant())') < strpos($source, 'api_dry_run_stop('), $id . ' refuses it before the rehearsal');
	}

	foreach (array('workspace.flow_runs.list', 'workspace.flow_runs.get') as $id) {
		pg_assert_same('GET', $routes[$id]['method']);
		pg_assert_true(pg_mcp_route_is_offered($routes[$id]), $id . ' is a tool');
	}

	pg_assert_same(array(201, 200), $routes['workspace.flow_runs.start']['status']);
	pg_assert_same('/workspace/flows/{id}/runs', $routes['workspace.flow_runs.start']['path']);
	pg_assert_same('/workspace/flow-runs/{id}/cancel', $routes['workspace.flow_runs.cancel']['path']);
}

// Who sees a flow is widened only by the person: sharing and taking a share
// back are REST writes from their team devices session that rehearse,
// offered to no MCP client as a tool and refused to the application an
// assistant works through; reading the shares stays a tool. A share answers 201 when it is new, 200 when its level
// changed; the removal addressed as a POST is the same handler.
function test_ws_api_flow_shares_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$expected = array(
		'workspace.flow_shares.list'        => array('GET', '/workspace/flows/{id}/shares', 'workspace:read'),
		'workspace.flow_shares.set'         => array('POST', '/workspace/flows/{id}/shares', 'workspace:write'),
		'workspace.flow_shares.delete'      => array('DELETE', '/workspace/flows/{id}/shares/{share_id}', 'workspace:write'),
		'workspace.flow_shares.delete_post' => array('POST', '/workspace/flows/{id}/shares/{share_id}/delete', 'workspace:write'),
	);

	foreach ($expected as $id => $shape) {
		pg_assert_true(isset($routes[$id]), $id . ' is declared');

		if (!isset($routes[$id])) {
			continue;
		}

		pg_assert_same($shape, array($routes[$id]['method'], $routes[$id]['path'], $routes[$id]['scope']), $id . ' method, path, scope');
	}

	if (count(array_intersect_key($routes, $expected)) !== count($expected)) {
		return;
	}

	pg_assert_true(pg_mcp_route_is_offered($routes['workspace.flow_shares.list']), 'reading the shares is a tool');
	pg_assert_same('WorkspaceFlowShare', $routes['workspace.flow_shares.list']['returns']['list'] ?? null);

	foreach (array('workspace.flow_shares.set', 'workspace.flow_shares.delete', 'workspace.flow_shares.delete_post') as $id) {
		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' rehearses');
		pg_assert_same(false, $routes[$id]['mcp'] ?? null, $id . ' carries \'mcp\' => false');
		pg_assert_false(pg_mcp_route_is_offered($routes[$id]), $id . ' is no MCP tool');
		pg_assert_same('WorkspaceFlowShare', $routes[$id]['returns']);

		$source = preg_replace('~^\s*//.*$~m', '', ws_api_test_handler_source($routes[$id]['handler']));

		pg_assert_true(strpos($source, 'if (ws_api_is_assistant())') !== false, $id . ' refuses an assistant');
		pg_assert_true(strpos($source, 'if (ws_api_is_assistant())') < strpos($source, 'api_dry_run_stop('), $id . ' refuses it before the rehearsal');
		pg_assert_true(strpos($source, 'ws_api_as_owner(') > strpos($source, 'api_dry_run_stop('), $id . ' writes in the owner\'s name after the rehearsal');

		// An application key or an OAuth connection may not widen who
		// starts the flow or changes its draft, as it may not move a
		// published flow's home.
		pg_assert_true(strpos($source, 'ws_api_own_words_only(') !== false, $id . ' asks for the team devices session');
		pg_assert_true(strpos($source, 'ws_api_own_words_only(') < strpos($source, 'api_dry_run_stop('), $id . ' before the rehearsal');
		pg_assert_true(strpos($source, 'team devices session (POST /auth/login)') !== false, $id . ': the refusal names it');
	}

	// The shares Claude's application is shown leave out the channels where
	// Claude may not be asked, as the channel listing does.
	foreach (array('ws_api_flow_shares_list', 'ws_api_flow_share_answer', 'ws_api_flow_shares_delete') as $name) {
		$source = ws_api_test_handler_source($name);

		pg_assert_true(strpos($source, 'ws_api_flow_shares_seen($viewer, $flow)') !== false, $name . ' lists the shares the application may see');
		pg_assert_true(strpos($source, 'ws_flow_shares_list(') === false, $name . ' never the store\'s list as it is');
	}

	$seen = ws_api_test_handler_source('ws_api_flow_shares_seen');

	pg_assert_true(strpos($seen, 'ws_api_is_claude()') !== false);
	pg_assert_true(strpos($seen, 'ws_claude_channel_allowed(') !== false);

	pg_assert_same(array(201, 200), $routes['workspace.flow_shares.set']['status']);
	pg_assert_same($routes['workspace.flow_shares.delete']['handler'], $routes['workspace.flow_shares.delete_post']['handler']);

	$params = array();

	foreach ($routes['workspace.flow_shares.set']['params'] as $param) {
		$params[$param['name']] = $param;
	}

	pg_assert_same(array('user', 'channel'), $params['target_type']['values'] ?? null, 'a person or a channel');
	pg_assert_same(array('view', 'run', 'edit'), $params['level']['values'] ?? null, 'the three levels');
	pg_assert_true(!empty($params['target_type']['required']) && !empty($params['target_id']['required']), 'what is shared with is required');
}

// A share's fields are read as ws_flow_share_set() takes them: the kind and
// the level as text, the id as a number, a view when no level is sent.
function test_ws_api_flow_share_input()
{
	pg_assert_same(array('target_type' => '', 'target_id' => 0, 'level' => 'view'), ws_api_flow_share_input(array()), 'nothing sent');
	pg_assert_same(array('target_type' => 'user', 'target_id' => 12, 'level' => 'run'), ws_api_flow_share_input(array('target_type' => 'user', 'target_id' => '12', 'level' => 'run')), 'the id as a number');
	pg_assert_same(array('target_type' => 'channel', 'target_id' => 3, 'level' => 'view'), ws_api_flow_share_input(array('target_type' => 'channel', 'target_id' => 3)));
}

// A flow as the API answers it to a person shared at view or run: the live
// graph of a published flow and no draft, and the draft of a flow never
// published, read-only, without what stops it from being published (the
// editors' alone, read with the record kinds, which need a database).
function test_ws_api_flow_present_gives_the_draft_to_its_readers()
{
	$draft = json_encode(ws_flow_graph_empty());
	$flow = array('id' => 9, 'name' => 'Onboarding', 'status' => 'live', 'owner_id' => 2, 'channel_id' => 3, 'draft_rev' => 4, 'draft' => $draft, 'created_at' => 1700000000, 'updated_at' => 1700000000);
	$version = array('id' => 7, 'n' => 2, 'published_by' => 2, 'published_at' => 1700000000, 'graph' => $draft);

	foreach (array('view', 'run') as $access) {
		$shared = ws_api_flow_present($flow + array('access' => $access), $version, true);

		pg_assert_same(null, $shared['draft'], $access . ': no draft of a published flow');
		pg_assert_same(null, $shared['problems'], $access . ': no problems');
		pg_assert_true(is_array($shared['graph']), $access . ': the live graph');

		$unpublished = ws_api_flow_present(array('status' => 'draft') + $flow + array('access' => $access), null, true);

		pg_assert_true(is_array($unpublished['draft']), $access . ': the draft of a flow never published');
		pg_assert_same(null, $unpublished['problems'], $access . ': without its problems');
		pg_assert_same(null, $unpublished['graph']);
	}
}

// A start retried with its token after the flow was paused answers the run
// the token started to whoever still reads it, as before the pause: the
// handler asks for that run before it answers 404 for a flow the person may
// no longer run, and the start check decides the rest.
function test_ws_api_flow_run_start_retried_after_a_pause()
{
	$source = preg_replace('~^\s*//.*$~m', '', ws_api_test_handler_source('ws_api_flow_runs_start'));
	$token = strpos($source, 'ws_flow_run_for_token(');

	pg_assert_true($token !== false, 'the run the token started is looked for');
	pg_assert_true($token < strpos($source, 'ws_api_flow_or_404('), 'before the flow is refused');
	pg_assert_true(strpos($source, 'ws_api_flow_or_404(') < strpos($source, 'ws_flow_run_start_check('), 'the start check decides after both');
}

// The list reads changed messages with changed_since, a moment.
function test_ws_api_messages_list_takes_changed_since()
{
	$types = array();

	foreach (ws_api_routes() as $route) {
		if ($route['id'] === 'workspace.messages.list') {
			foreach ($route['params'] as $param) {
				$types[$param['name']] = $param['type'];
			}
		}
	}

	pg_assert_true(($types['changed_since'] ?? '') === 'datetime', 'changed_since is a datetime');
}

// A message the reader deleted for themselves since the moment counts as
// changed for them alone: those ids join the touched_at range and the rest of
// the condition stays as it was; with none, the range is read alone.
function test_ws_messages_touched_where()
{
	pg_assert_same("channel_id = '3' AND touched_at >= '1700000000' AND id <= '900'", ws_messages_touched_where(3, 1700000000, 900));
	pg_assert_same("channel_id = '3' AND touched_at >= '1700000000' AND id <= '900' AND id > '40'", ws_messages_touched_where('3', 1700000000, 900, 40, array()));
	pg_assert_same("channel_id = '3' AND (touched_at >= '1700000000' OR id IN (41,77)) AND id <= '900' AND id > '40'", ws_messages_touched_where(3, 1700000000, 900, 40, array('41', 77, 77, 0, -2)));
}

// Without a reader, a moment or a message held there is nothing of the
// reader's own to look for, and the database is not asked.
function test_ws_message_hidden_since_needs_a_reader()
{
	pg_assert_same(array(), ws_message_hidden_since(0, 3, 1700000000, 900));
	pg_assert_same(array(), ws_message_hidden_since(7, 3, 0, 900));
	pg_assert_same(array(), ws_message_hidden_since(7, 3, 1700000000, 0));
}

// The person's other devices are told of a message hidden on one of them by
// the two reads that follow changes, and the hide says so.
function test_ws_api_hide_reaches_the_other_devices_in_the_docs()
{
	$descriptions = array();

	foreach (ws_api_routes() as $route) {
		$descriptions[$route['id']] = (string) ($route['description'] ?? '');
	}

	pg_assert_true(strpos($descriptions['workspace.messages.list'], 'deleted for themselves since then') !== false, 'changed_since names hidden messages');
	pg_assert_true(strpos($descriptions['workspace.sync'], 'deleted for themselves since') !== false, 'sync names hidden messages');
	pg_assert_true(strpos($descriptions['workspace.messages.hide'], 'other devices') !== false, 'hide names the other devices');
}

// The ready-made MCP connectors are read by an administrator's application
// only, and no MCP tool lists them: adding a server is the settings screen's.
function test_ws_api_mcp_catalogue_is_for_administrators_and_no_tool()
{
	$catalogue = null;

	foreach (ws_api_routes() as $route) {
		if ($route['id'] === 'workspace.mcp_servers.catalogue') {
			$catalogue = $route;
		}
	}

	pg_assert_true(is_array($catalogue), 'workspace.mcp_servers.catalogue is declared');
	pg_assert_same('GET', $catalogue['method']);
	pg_assert_same('/workspace/mcp-servers/catalogue', $catalogue['path']);
	pg_assert_same('workspace:read', $catalogue['scope']);
	pg_assert_false(pg_mcp_route_is_offered($catalogue), 'no MCP tool');

	$source = ws_api_test_handler_source($catalogue['handler']);

	pg_assert_true(strpos($source, "(int) \$viewer['role'] !== 0") !== false, 'an administrator only');
	pg_assert_true(strpos($source, 'api_fail(403') !== false, 'the others get 403');
}

// The site's MCP servers over the API: an administrator's, no MCP tool,
// every write rehearsed; the panel's tools form and add form call the same
// checks and writes as the API.
function test_ws_api_mcp_servers_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$expected = array(
		'workspace.mcp_servers.list'    => array('GET', '/workspace/mcp-servers', 'workspace:read'),
		'workspace.mcp_servers.create'  => array('POST', '/workspace/mcp-servers', 'workspace:write'),
		'workspace.mcp_servers.probe'   => array('POST', '/workspace/mcp-servers/probe', 'workspace:write'),
		'workspace.mcp_servers.refresh' => array('POST', '/workspace/mcp-servers/{id}/refresh', 'workspace:write'),
		'workspace.mcp_servers.tools'   => array('POST', '/workspace/mcp-servers/{id}/tools', 'workspace:write'),
	);

	foreach ($expected as $id => $shape) {
		pg_assert_true(isset($routes[$id]), $id . ' is declared');
		pg_assert_same($shape, array($routes[$id]['method'], $routes[$id]['path'], $routes[$id]['scope']), $id);
		pg_assert_false(pg_mcp_route_is_offered($routes[$id]), $id . ' is no MCP tool');
		pg_assert_true(strpos(ws_api_test_handler_source($routes[$id]['handler']), 'ws_api_mcp_admin()') !== false, $id . ': an administrator only');
	}

	$admin = ws_api_test_handler_source('ws_api_mcp_admin');
	pg_assert_true(strpos($admin, "(int) \$viewer['role'] !== 0") !== false, 'role 0 only');
	pg_assert_true(strpos($admin, 'api_fail(403') !== false, 'the others get 403');
	pg_assert_true(strpos($admin, 'if (ws_api_is_assistant())') !== false, 'an assistant key is refused');
	pg_assert_true(strpos($admin, 'if (ws_api_is_assistant())') < strpos($admin, 'api_fail(503'), 'before anything else is told');

	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mcp.php');
	pg_assert_true(strpos($panel, 'ws_mcp_tools_check($server, $wanted') !== false, 'the tools form meets the shared check');
	pg_assert_true(strpos($panel, '$check = ws_mcp_save_check($server, $post);') !== false, 'the add form meets the shared check');
	pg_assert_true(strpos($panel, 'ws_mcp_server_state($server)') !== false, 'the card badge is the shared state');
}

// The sync an app polls is a read and no tool: a model gains nothing from a
// cursor, and a route can stay out of MCP by saying so.
function test_ws_api_sync_is_a_read_and_no_tool()
{
	$sync = null;

	foreach (ws_api_routes() as $route) {
		if ($route['id'] === 'workspace.sync') {
			$sync = $route;
		}
	}

	pg_assert_true(is_array($sync), 'workspace.sync is declared');
	pg_assert_same('GET', $sync['method']);
	pg_assert_same('/workspace/sync', $sync['path']);
	pg_assert_same('workspace:read', $sync['scope']);
	pg_assert_false(pg_mcp_route_is_offered($sync), 'the sync is no MCP tool');

	// The same route without the flag would be one.
	unset($sync['mcp']);
	pg_assert_true(pg_mcp_route_is_offered($sync), 'only the flag keeps it out');
}

// The cursor reads back what it was written from; anything else is refused,
// so a damaged cursor is a 400 and not a silent fresh start.
function test_ws_api_sync_cursor_round_trip()
{
	foreach (array('', '9f8e7d6c') as $state) {
		$mark = array('t' => 1760414400, 'c' => 12, 'm' => 4815, 'ch' => '0a1b2c3d', 'th' => 'ffffffff', 'p' => $state);
		$cursor = ws_api_sync_cursor_write($mark);

		pg_assert_true((bool) preg_match('/^[A-Za-z0-9_-]+$/', $cursor), 'the cursor is URL safe');
		pg_assert_same($mark, ws_api_sync_cursor_read($cursor));

		// The same state, the same cursor: an answer after which nothing
		// changed is the same bytes, so its ETag matches.
		pg_assert_same($cursor, ws_api_sync_cursor_write($mark));
	}

	foreach (array('', 'abc', 'not a cursor!', rtrim(strtr(base64_encode('{"t":1}'), '+/', '-_'), '='),
		ws_api_sync_cursor_write(array('t' => 1, 'c' => 0, 'm' => 0, 'ch' => 'xyz', 'th' => '00000000')),
		ws_api_sync_cursor_write(array('t' => 1, 'c' => 0, 'm' => 0, 'ch' => '00000000', 'th' => '00000000', 'p' => 'xyz')),
		rtrim(strtr(base64_encode('{"t":1,"c":0,"m":0,"ch":"00000000","th":"00000000","p":7}'), '+/', '-_'), '='),
		rtrim(strtr(base64_encode('{"t":-1,"c":0,"m":0,"ch":"00000000","th":"00000000"}'), '+/', '-_'), '=')) as $bad) {
		pg_assert_same(null, ws_api_sync_cursor_read($bad), 'refused: ' . $bad);
	}
}

// A cursor an app holds from before the channel state was added still reads,
// with no state mark, so the state is sent once; and a call that carries no
// state writes its cursor as before, so an app key's ETag still matches.
function test_ws_api_sync_cursor_without_state_mark()
{
	$before = rtrim(strtr(base64_encode('{"t":1760414400,"c":12,"m":4815,"ch":"0a1b2c3d","th":"ffffffff"}'), '+/', '-_'), '=');

	pg_assert_same(array('t' => 1760414400, 'c' => 12, 'm' => 4815, 'ch' => '0a1b2c3d', 'th' => 'ffffffff', 'p' => ''), ws_api_sync_cursor_read($before));
	pg_assert_same($before, ws_api_sync_cursor_write(ws_api_sync_cursor_read($before)));
}

// The open channel's state: each part as the panel's screen has it, nothing
// added on the way out, and a channel's state never marked like another's.
function test_ws_api_sync_channel_state_present()
{
	$message = array('id' => 77, 'channel_id' => 12, 'kind' => 'message', 'text' => 'Kargo yarın');

	$state = array(
		'channel_id' => 12,
		'pin'        => array('message' => $message, 'pinned_by' => array('user_id' => 3, 'name' => 'Erdal'), 'pinned_at' => 0, 'hidden' => 1),
		'tab_counts' => array('decisions' => '4', 'tasks' => 2, 'summary' => 1),
		'board'      => 'd41d8cd98f00b204e9800998ecf8427e',
	);

	$out = ws_api_sync_channel_state_present($state);

	pg_assert_same(array_keys(ws_api_sync_channel_state_schema()), array_keys($out));
	pg_assert_same(array_keys(ws_api_sync_pin_schema()), array_keys($out['pin']));
	pg_assert_same($message, $out['pin']['message']);
	pg_assert_same(null, $out['pin']['pinned_at'], 'no moment, no time');
	pg_assert_same(true, $out['pin']['hidden']);
	pg_assert_same(array('decisions' => 4, 'tasks' => 2, 'summary' => true), $out['tab_counts']);
	pg_assert_same('d41d8cd98f00b204e9800998ecf8427e', $out['board']);

	// A discussion: no pin, no tabs, no board.
	$thread = ws_api_sync_channel_state_present(array('channel_id' => 40, 'pin' => null, 'tab_counts' => null, 'board' => null));

	pg_assert_same(array('channel_id' => 40, 'pin' => null, 'tab_counts' => null, 'board' => null), $thread);

	// Two channels with nothing pinned, counted or on a board are still two
	// marks: a switch always sends the new channel's state.
	pg_assert_false(ws_api_sync_mark(array('channel_id' => 40, 'pin' => null, 'tab_counts' => null, 'board' => null))
		=== ws_api_sync_mark(array('channel_id' => 41, 'pin' => null, 'tab_counts' => null, 'board' => null)), 'the channel is in the mark');

	// Closing the pin on the panel moves the mark.
	$closed = $state;
	$closed['pin']['hidden'] = 0;

	pg_assert_false(ws_api_sync_mark($state) === ws_api_sync_mark($closed), 'hiding the pin moves it');
}

// What is not there comes as null, and every field the answer has is
// declared: an empty inbox has no moment, a caller without the bell's
// permission no count, a call without a person's open channel no state.
function test_ws_api_sync_present_nulls()
{
	$out = ws_api_sync_present(array(
		'cursor' => 'abc', 'channels' => null, 'threads' => null, 'inbox_unread' => 0, 'inbox_changed_at' => 0,
		'notifications_unread' => null, 'gone' => false, 'messages' => array(), 'more' => false,
		'changed' => array(), 'reload' => false, 'channel_state' => null,
	));

	pg_assert_same(array_keys(ws_api_sync_schema()), array_keys($out));
	pg_assert_same(null, $out['inbox_changed_at']);
	pg_assert_same(null, $out['notifications_unread']);
	pg_assert_same(null, $out['channel_state']);
}

// The bell is the Own account permission's: without it the count is left out
// rather than refused, as GET /auth/me leaves its unread out.
function test_ws_api_sync_bell_needs_own_account()
{
	pg_assert_same(null, ws_api_sync_bell(array('id' => 1, 'role' => 0), array('workspace:read', 'workspace:write')));
	pg_assert_same(null, ws_api_sync_bell(array('id' => 1, 'role' => 0), array()));
}

// A list's mark moves when the list does, and only then.
function test_ws_api_sync_mark_follows_the_list()
{
	$list = array(array('id' => 1, 'unread' => 0), array('id' => 2, 'unread' => 3));

	pg_assert_same(ws_api_sync_mark($list), ws_api_sync_mark($list));
	pg_assert_true((bool) preg_match('/^[0-9a-f]{8}$/', ws_api_sync_mark($list)), 'eight hex digits');

	$list[1]['unread'] = 4;

	pg_assert_false(ws_api_sync_mark($list) === ws_api_sync_mark(array(array('id' => 1, 'unread' => 0), array('id' => 2, 'unread' => 3))), 'an unread count moves it');
}

// The inbox is read with workspace:read and marked read with workspace:write;
// all three are tools, and the mark can be rehearsed.
function test_ws_api_inbox_routes()
{
	$found = array();

	foreach (ws_api_routes() as $route) {
		if (strpos($route['id'], 'workspace.inbox.') === 0) {
			$found[$route['id']] = $route;
		}
	}

	$ids = array_keys($found);
	sort($ids);

	pg_assert_same(array('workspace.inbox.get', 'workspace.inbox.list', 'workspace.inbox.read'), $ids);

	pg_assert_same('GET /workspace/inbox workspace:read', $found['workspace.inbox.list']['method'] . ' ' . $found['workspace.inbox.list']['path'] . ' ' . $found['workspace.inbox.list']['scope']);
	pg_assert_same('GET /workspace/inbox/{id} workspace:read', $found['workspace.inbox.get']['method'] . ' ' . $found['workspace.inbox.get']['path'] . ' ' . $found['workspace.inbox.get']['scope']);
	pg_assert_same('POST /workspace/inbox/read workspace:write', $found['workspace.inbox.read']['method'] . ' ' . $found['workspace.inbox.read']['path'] . ' ' . $found['workspace.inbox.read']['scope']);
	pg_assert_true(!empty($found['workspace.inbox.read']['dry_run']), 'marking read can be rehearsed');

	foreach ($found as $id => $route) {
		pg_assert_true(pg_mcp_route_is_offered($route), $id . ' is a tool');
	}
}

// The inbox picks up its changed lines as a channel does its messages: a
// changed_since moment, paged with since_id, in the tool as well.
function test_ws_api_inbox_list_takes_changed_since()
{
	$list = null;

	foreach (ws_api_routes() as $route) {
		if ($route['id'] === 'workspace.inbox.list') {
			$list = $route;
		}
	}

	$types = array();

	foreach ($list['params'] as $param) {
		$types[$param['name']] = $param['type'];
	}

	pg_assert_same('datetime', $types['changed_since'] ?? '');
	pg_assert_same('int', $types['since_id'] ?? '');

	$properties = (array) pg_mcp_tool_from_route($list)['inputSchema']['properties'];

	pg_assert_true(isset($properties['changed_since']) && isset($properties['since_id']), 'the tool takes both');
}

// The inbox's listing says beside the page whether the app must read it
// again (reload), and the document describes that field next to data and
// page; a listing that names nothing beside 'list' stays as it was.
function test_ws_api_inbox_list_declares_reload()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');

	$list = null;

	foreach (ws_api_routes() as $route) {
		if ($route['id'] === 'workspace.inbox.list') {
			$list = $route;
		}
	}

	pg_assert_same(array('list' => 'WorkspaceInboxItem', 'reload' => 'boolean'), $list['returns']);

	$schema = api_openapi_success_schema($list);

	pg_assert_same(array('data', 'page', 'reload'), array_keys($schema['properties']));
	pg_assert_same('boolean', $schema['properties']['reload']['type']);

	$plain = api_openapi_success_schema(array('returns' => array('list' => 'WorkspaceInboxItem')));

	pg_assert_same(array('data', 'page'), array_keys($plain['properties']));
}

// Lines taken away are remembered for a while: a moment within it reads the
// changes whole, a moment further back is told to read the inbox again.
function test_ws_api_inbox_reload()
{
	$now = 1700000000;
	$keep = 30 * 86400;

	pg_assert_same(false, ws_api_inbox_reload($now - 60, $now, $keep));
	pg_assert_same(false, ws_api_inbox_reload($now - $keep, $now, $keep));
	pg_assert_same(true, ws_api_inbox_reload($now - $keep - 1, $now, $keep));
	pg_assert_same(true, ws_api_inbox_reload(0, $now, $keep));
	pg_assert_same(false, ws_api_inbox_reload($now + 60, $now, $keep));
}

// Back in a channel, the lines about its tasks come back only for the tasks
// seen through it: the creator and the people on a task saw it all along.
function test_ws_inbox_task_through_channel()
{
	$task = array('channel_id' => 7, 'creator_id' => 3);

	pg_assert_same(true, ws_inbox_task_through_channel(5, $task, array(4)));
	pg_assert_same(true, ws_inbox_task_through_channel(5, $task, array()));
	pg_assert_same(false, ws_inbox_task_through_channel(3, $task, array(4)), 'its creator');
	pg_assert_same(false, ws_inbox_task_through_channel(5, $task, array('4', '5')), 'one of its people');
	pg_assert_same(false, ws_inbox_task_through_channel(5, array('channel_id' => 0, 'creator_id' => 3), array()), 'a task in no channel');
}

// A row changes by being written, sent again or read: either stamp at or
// after the moment counts, only the person's rows after the one given.
function test_ws_api_inbox_changed_where()
{
	pg_assert_same("user_id = '7' AND (created_at >= '1700000000' OR read_at >= '1700000000') AND id > '42'", ws_api_inbox_changed_where(7, 1700000000, 42));

	// An unread row has read_at 0; no moment may reach it.
	pg_assert_same("user_id = '7' AND (created_at >= '1' OR read_at >= '1') AND id > '0'", ws_api_inbox_changed_where('7', 0, -5));
}

// A line taken away is found by when it went, only the person's marks after
// the one given, and no further than the live rows were read when that read
// stopped short.
function test_ws_api_inbox_deleted_where()
{
	pg_assert_same("user_id = '7' AND deleted_at >= '1700000000' AND id > '42'", ws_api_inbox_deleted_where(7, 1700000000, 42));
	pg_assert_same("user_id = '7' AND deleted_at >= '1' AND id > '0' AND id <= '900'", ws_api_inbox_deleted_where('7', 0, -5, 900));
}

// A line taken away comes back with the fields of any other line, deleted,
// read and with nothing of what it said; a line still there is not deleted.
function test_ws_api_inbox_deleted_present()
{
	$line = ws_api_inbox_deleted_present(array('id' => '12', 'kind' => 'mention', 'channel_id' => '3', 'created_at' => '1700000000', 'deleted_at' => '1700000100'));

	pg_assert_same(array_keys(ws_api_inbox_schema()), array_keys($line));
	pg_assert_same(12, $line['id']);
	pg_assert_same('mention', $line['kind']);
	pg_assert_true($line['deleted'], 'marked deleted');
	pg_assert_true($line['read'], 'no longer waits');
	pg_assert_same('', $line['title']);
	pg_assert_same(null, $line['channel_id']);
	pg_assert_same('2023-11-14T22:13:20Z', $line['created_at']);
	pg_assert_same('boolean', ws_api_inbox_schema()['deleted']);
}

// The inbox's addresses start at the site's root; an app gets them whole,
// and any other address as it is.
function test_ws_api_inbox_url()
{
	pg_assert_same('https://example.com/pinegrap/workspace.php?channel=3&message=9', ws_api_inbox_url('/pinegrap/workspace.php?channel=3&message=9', 'https://example.com'));
	pg_assert_same('https://example.com/pinegrap/workspace_tasks.php?task=4', ws_api_inbox_url('/pinegrap/workspace_tasks.php?task=4', 'https://example.com/'));
	pg_assert_same('https://meet.example.org/abc', ws_api_inbox_url('https://meet.example.org/abc', 'https://example.com'));
	pg_assert_same('workspace.php', ws_api_inbox_url('workspace.php', 'https://example.com'));
	pg_assert_same('//elsewhere.example/x', ws_api_inbox_url('//elsewhere.example/x', 'https://example.com'));
}

// The search looks in a kind of record only with the scope that reads it; a
// kind it names has to be one a tag can point at.
function test_ws_api_search_record_scopes_name_known_kinds()
{
	foreach (ws_api_search_record_scopes() as $kind => $scope) {
		pg_assert_contains($kind, ws_record_type_keys(), $kind . ' is a record kind');
		pg_assert_true((bool) preg_match('/^[a-z_]+:read$/', $scope), $kind . ' asks for a read scope');
	}

	pg_assert_false(array_key_exists('user_account', ws_api_search_record_scopes()), 'no scope reads user accounts');

	// A sales quote is found with the scope the ERP's own quote routes read
	// it with, so the search is no way round them.
	$quote_routes = 0;

	foreach (erp_api_routes() as $route) {
		if (in_array($route['id'], array('erp.quotes.list', 'erp.quotes.get'), true)) {
			$quote_routes++;
			pg_assert_same($route['scope'], ws_api_search_record_scopes()['erp_quote'] ?? null, 'a sales quote is searched with the scope of ' . $route['id']);
		}
	}

	pg_assert_same(2, $quote_routes, 'the ERP\'s quote routes were read');
}

// A person signed in on their own device writes in their own name; an
// application key, and an OAuth connection (a device row with a client),
// write as the application.
function test_ws_api_device_writes_as_person()
{
	pg_assert_false(ws_api_device_writes_as_person(null), 'an application key writes as the application');
	pg_assert_true(ws_api_device_writes_as_person(array('id' => 4, 'app_id' => 2)), 'a team devices session writes as the person');
	pg_assert_true(ws_api_device_writes_as_person(array('id' => 4, 'app_id' => 2, 'oauth_client_id' => '0')), 'a device row with no client writes as the person');
	pg_assert_false(ws_api_device_writes_as_person(array('id' => 5, 'app_id' => 3, 'oauth_client_id' => '7')), 'an OAuth connection writes as the application');
}

// Only the person's own message asks the assistants it names, as the panel's
// composer does: never an application's words, nor a decision or a note.
function test_ws_api_message_asks_assistants()
{
	pg_assert_true(ws_api_message_asks_assistants(0, 'message'), 'a message from the person\'s device asks');
	pg_assert_false(ws_api_message_asks_assistants(7, 'message'), 'an application\'s message does not ask');
	pg_assert_false(ws_api_message_asks_assistants(0, 'decision'), 'a decision does not ask');
	pg_assert_false(ws_api_message_asks_assistants(0, 'note'), 'a note does not ask');
}

// Pinegrap AI and an MCP agent asked from a device: after the answer where
// the server can send it first; where it cannot, only an open app's sync,
// which waits for news anyway, does the work in the call, and a message
// written leaves it to the next sync, the panel's screens and the hourly job.
function test_ws_api_carry_on_where()
{
	pg_assert_same('after', ws_api_carry_on_where(true, false), 'PHP-FPM, a message written');
	pg_assert_same('after', ws_api_carry_on_where(true, true), 'PHP-FPM, the sync');
	pg_assert_same('here', ws_api_carry_on_where(false, true), 'IIS, the sync');
	pg_assert_same('', ws_api_carry_on_where(false, false), 'IIS, a message written');
}

// A file is posted and fetched with the caller's own token. Posting is an
// MCP tool for a small file sent as content_base64; the download is none,
// since a tool result cannot carry the bytes, and it is described as the
// file itself rather than a record.
function test_ws_api_files_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	pg_assert_true(isset($routes['workspace.files.create']), 'workspace.files.create is declared');
	pg_assert_same('POST', $routes['workspace.files.create']['method']);
	pg_assert_same('/workspace/channels/{id}/files', $routes['workspace.files.create']['path']);
	pg_assert_same('workspace:write', $routes['workspace.files.create']['scope']);
	pg_assert_same('WorkspaceMessage', $routes['workspace.files.create']['returns']);
	pg_assert_true(pg_mcp_route_is_offered($routes['workspace.files.create']), 'posting a small file is an MCP tool');

	pg_assert_true(isset($routes['workspace.files.get']), 'workspace.files.get is declared');
	pg_assert_same('GET', $routes['workspace.files.get']['method']);
	pg_assert_same('/workspace/messages/{id}/file', $routes['workspace.files.get']['path']);
	pg_assert_same('workspace:read', $routes['workspace.files.get']['scope']);
	pg_assert_true(!empty($routes['workspace.files.get']['binary']), 'the download answers with the file');
	pg_assert_false(pg_mcp_route_is_offered($routes['workspace.files.get']), 'the download is no MCP tool');
}

// What a browser shows is sent with its own type and inline; anything else,
// and anything a browser would run, as bytes to be saved.
function test_ws_file_type()
{
	pg_assert_same(array('mime' => 'image/jpeg', 'inline' => true), ws_file_type('photo.jpg'));
	pg_assert_same(array('mime' => 'image/jpeg', 'inline' => true), ws_file_type('PHOTO.JPEG'));
	pg_assert_same(array('mime' => 'application/pdf', 'inline' => true), ws_file_type('offer.pdf'));
	pg_assert_same(array('mime' => 'video/mp4', 'inline' => true), ws_file_type('clip.mp4'));
	pg_assert_same(array('mime' => 'audio/mp4', 'inline' => true), ws_file_type('voice.m4a'));

	foreach (array('page.html', 'logo.svg', 'feed.xml', 'script.js', 'report.docx', 'archive.tar.gz', 'README', 'photo.jpg.php') as $name) {
		pg_assert_same(array('mime' => 'application/octet-stream', 'inline' => false), ws_file_type($name), $name . ' is saved, not shown');
	}
}

// Muting a channel, voting in a poll and joining or leaving a discussion are
// the person's own choices from a team app: writes that rehearse, offered to
// no MCP client.
function test_ws_api_notify_vote_and_thread_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$expect = array(
		'workspace.channels.notify' => array('/workspace/channels/{id}/notify', 'WorkspaceChannel'),
		'workspace.polls.vote'      => array('/workspace/polls/{id}/vote', 'WorkspaceMessage'),
		'workspace.threads.join'    => array('/workspace/threads/{id}/join', 'WorkspaceThread'),
		'workspace.threads.leave'   => array('/workspace/threads/{id}/leave', 'WorkspaceThread'),
	);

	foreach ($expect as $id => $shape) {
		pg_assert_true(isset($routes[$id]), $id . ' is declared');
		pg_assert_same('POST', $routes[$id]['method'], $id . ' is a POST');
		pg_assert_same($shape[0], $routes[$id]['path'], $id . ' path');
		pg_assert_same('workspace:write', $routes[$id]['scope'], $id . ' scope');
		pg_assert_same($shape[1], $routes[$id]['returns'], $id . ' answer');
		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' rehearses');
		pg_assert_false(pg_mcp_route_is_offered($routes[$id]), $id . ' is no MCP tool');
	}

	pg_assert_true(isset(ws_openapi_objects()['WorkspacePoll']), 'the poll object is declared');
}

// The poll on a message, from what the channel screen draws: an anonymous
// poll names nobody, a guest who voted has no user id, and a poll open
// until it is closed has no closes_at.
function test_ws_api_poll_present()
{
	$present = ws_api_poll_present(array(
		'id' => 7, 'multiple' => false, 'anonymous' => false, 'closed' => false, 'closes_at' => 0,
		'voters' => 2, 'voted' => true, 'can_vote' => true, 'result_id' => 0,
		'options' => array(
			array('id' => 31, 'label' => 'Tuesday', 'count' => 2, 'mine' => true, 'leading' => true, 'voters' => array(
				array('id' => 12, 'name' => 'Ayşe', 'avatar' => ''),
				array('id' => 0, 'name' => 'Guest', 'avatar' => '', 'guest' => true),
			)),
			array('id' => 32, 'label' => 'Wednesday', 'count' => 0, 'mine' => false, 'leading' => false, 'voters' => array()),
		),
	));

	pg_assert_same(7, $present['id']);
	pg_assert_false($present['can_edit'] || $present['can_close'], 'what the person may do is false unless the screen says so');
	pg_assert_same(null, $present['closes_at'], 'open until closed');
	pg_assert_same(null, $present['result_message_id']);
	pg_assert_same(array(31, 32), array_column($present['options'], 'id'));
	pg_assert_same(array(array('user_id' => 12, 'name' => 'Ayşe'), array('user_id' => null, 'name' => 'Guest')), $present['options'][0]['voters']);
	pg_assert_true($present['options'][0]['mine'] && !$present['options'][1]['mine'], 'mine marks the person\'s choice');
	pg_assert_same(array_keys(ws_api_poll_schema()), array_keys($present), 'the answer has the declared fields in order');
}

// The download takes a size for a picture's smaller copy, bounded so the
// query string cannot ask for anything absurd.
function test_ws_api_files_get_takes_a_size()
{
	$route = null;

	foreach (ws_api_routes() as $candidate) {
		if ($candidate['id'] === 'workspace.files.get') {
			$route = $candidate;
		}
	}

	$size = null;

	foreach ((array) ($route['params'] ?? array()) as $param) {
		if ($param['name'] === 'size') {
			$size = $param;
		}
	}

	pg_assert_true(is_array($size), 'size is declared');
	pg_assert_same('query', $size['in']);
	pg_assert_same('int', $size['type']);
	pg_assert_same(1, $size['min']);
	pg_assert_same(4096, $size['max']);
}

// A smaller copy is drawn at one of a few fixed edges: the first at least as
// large as asked, the largest of them at most.
function test_ws_file_preview_edge()
{
	pg_assert_same(160, ws_file_preview_edge(1));
	pg_assert_same(160, ws_file_preview_edge(160));
	pg_assert_same(320, ws_file_preview_edge(161));
	pg_assert_same(640, ws_file_preview_edge(600));
	pg_assert_same(1280, ws_file_preview_edge(1000));
	pg_assert_same(1920, ws_file_preview_edge(1500));
	pg_assert_same(1920, ws_file_preview_edge(4096));
}

// A picture already no larger than the edge, and a file that is no picture,
// are sent as they are; nothing is drawn or kept for them.
function test_ws_file_preview_sends_the_original()
{
	$directory = sys_get_temp_dir() . '/pg_ws_preview_test_' . getmypid();
	@mkdir($directory);

	$text = $directory . '/notes.jpg';
	file_put_contents($text, 'not a picture');
	pg_assert_same(null, ws_file_preview($text, 160), 'not a picture');

	if (function_exists('imagecreatetruecolor') && function_exists('imagepng')) {
		$small = $directory . '/small.png';
		$image = imagecreatetruecolor(150, 90);
		imagepng($image, $small);
		pg_image_destroy($image);

		pg_assert_same(null, ws_file_preview($small, 160), 'no larger than the edge');
		pg_assert_same(null, ws_file_preview($small, 1), 'smaller than the smallest edge');
		@unlink($small);
	}

	@unlink($text);
	@rmdir($directory);
}

// The pin bar's actions are writes of the person's own app: they can be
// rehearsed, answer with the channel's state the sync carries, and are no
// MCP tools.
function test_ws_api_pin_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$paths = array('workspace.messages.pin' => '/workspace/messages/{id}/pin', 'workspace.messages.pin_close' => '/workspace/messages/{id}/pin/close');

	foreach ($paths as $id => $path) {
		pg_assert_true(isset($routes[$id]), $id . ' is declared');
		pg_assert_same('POST', $routes[$id]['method']);
		pg_assert_same($path, $routes[$id]['path']);
		pg_assert_same('workspace:write', $routes[$id]['scope']);
		pg_assert_same('WorkspaceSyncChannelState', $routes[$id]['returns']);
		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' takes dry_run');
		pg_assert_false(pg_mcp_route_is_offered($routes[$id]), $id . ' is no MCP tool');
	}

	$on = null;

	foreach ($routes['workspace.messages.pin']['params'] as $param) {
		if ($param['name'] === 'on') {
			$on = $param;
		}
	}

	pg_assert_true(is_array($on) && ($on['type'] === 'bool') && ($on['default'] === true), 'on pins by default');
}

// Pinning a channel and moving it in the list are the person's own sidebar:
// a write that rehearses, offered to no MCP client, whose fields the channel
// object carries under the same names.
function test_ws_api_channel_prefs_route()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$route = $routes['workspace.channels.prefs'] ?? null;

	pg_assert_true(is_array($route), 'the route is declared');
	pg_assert_same('POST', $route['method']);
	pg_assert_same('/workspace/channels/{id}/prefs', $route['path']);
	pg_assert_same('workspace:write', $route['scope']);
	pg_assert_same('WorkspaceChannel', $route['returns']);
	pg_assert_true(!empty($route['dry_run']), 'it rehearses');
	pg_assert_false(pg_mcp_route_is_offered($route), 'it is no MCP tool');

	$body = array();

	foreach ($route['params'] as $param) {
		if (($param['in'] ?? '') === 'body') {
			$body[$param['name']] = $param;
			pg_assert_true(empty($param['required']), $param['name'] . ' may be left out');
		}
	}

	pg_assert_same(array('pinned', 'order'), array_keys($body));
	pg_assert_same(1, $body['order']['min'], 'the top is place 1');
	pg_assert_true(array_key_exists('pinned', ws_api_channel_schema()) && array_key_exists('order', ws_api_channel_schema()), 'the channel carries both');
}

// A channel the person never placed has no order; a public one they are not
// in is neither pinned nor placed.
function test_ws_api_channel_present_prefs()
{
	$brief = array(
		'id' => 4, 'name' => 'sales', 'kind' => 'public', 'topic' => '', 'joined' => true, 'notify' => 'all',
		'pinned' => true, 'sort' => 3, 'contact_id' => 0, 'customer_type' => '', 'customer_id' => 0,
		'department_id' => 0, 'archived' => false, 'unread' => 0, 'last_message_at' => 0,
	);

	$present = ws_api_channel_present($brief);

	pg_assert_true($present['pinned']);
	pg_assert_same(3, $present['order']);

	$present = ws_api_channel_present(array_merge($brief, array('joined' => false, 'pinned' => false, 'sort' => 0)));

	pg_assert_false($present['pinned']);
	pg_assert_same(null, $present['order']);
	pg_assert_same(array_keys(ws_api_channel_schema()), array_keys($present), 'the answer has the declared fields in order');
}

// Asking, changing and closing a poll: writes that rehearse, offered to no
// MCP client. The change answers PATCH beside POST, and its path takes no
// method the vote or the closing answers.
function test_ws_api_poll_create_update_close_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$expect = array(
		'workspace.polls.create' => '/workspace/channels/{id}/polls',
		'workspace.polls.update' => '/workspace/polls/{id}',
		'workspace.polls.close'  => '/workspace/polls/{id}/close',
	);

	foreach ($expect as $id => $path) {
		pg_assert_true(isset($routes[$id]), $id . ' is declared');
		pg_assert_same('POST', $routes[$id]['method'], $id . ' is a POST');
		pg_assert_same($path, $routes[$id]['path'], $id . ' path');
		pg_assert_same('workspace:write', $routes[$id]['scope'], $id . ' scope');
		pg_assert_same('WorkspaceMessage', $routes[$id]['returns'], $id . ' answers with the poll\'s message');
		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' rehearses');
		pg_assert_false(pg_mcp_route_is_offered($routes[$id]), $id . ' is no MCP tool');
	}

	pg_assert_same(array('PATCH'), $routes['workspace.polls.update']['also_accepts'], 'a change also answers PATCH');

	$params = array();

	foreach ($routes['workspace.polls.update']['params'] as $param) {
		$params[$param['name']] = $param;
	}

	pg_assert_false(!empty($params['question']['required']) || !empty($params['options']['required']), 'a change sends only what changes');
	pg_assert_true(!empty($params['closes_at']['nullable']), 'null leaves the poll open until it is closed');
}

// The checks a poll is asked with, without a database: what a dry run meets
// before anything is written.
function test_ws_poll_input()
{
	$ok = ws_poll_input(array('question' => '  Which day?  ', 'options' => array('Tuesday', ' Wednesday ', 'Tuesday', ''), 'closes_at' => 0));

	pg_assert_true($ok['ok'], 'a question with two different options');
	pg_assert_same('Which day?', $ok['question']);
	pg_assert_same(array('Tuesday', 'Wednesday'), $ok['options'], 'blank and repeated options are left out');
	pg_assert_same(0, $ok['closes_at'], 'open until closed');

	$refused = array(
		'question'  => array('question' => '', 'options' => array('A', 'B')),
		'options'   => array('question' => 'Q', 'options' => array('A', 'A')),
		'closes_at' => array('question' => 'Q', 'options' => array('A', 'B'), 'closes_at' => time() + 30),
	);

	foreach ($refused as $field => $data) {
		$result = ws_poll_input($data);
		pg_assert_false($result['ok'], $field . ' is refused');
		pg_assert_same($field, $result['field'], $field . ' is named');
	}

	pg_assert_false(ws_poll_input(array('question' => 'Q', 'options' => range(1, 11)))['ok'], 'eleven options are too many');
	pg_assert_same('question', ws_poll_input(array('question' => str_repeat('a', WS_MESSAGE_MAX + 1), 'options' => array('A', 'B')))['field'], 'a question longer than a message');
	pg_assert_true(ws_poll_input(array('question' => 'Q', 'options' => array('A', 'B'), 'closes_at' => time() + 3600))['ok'], 'closing in an hour');
}

// Moving a channel into a group is staff work on the channel, gated the way
// changing the channel is: any caller the owner's rights allow, a rehearsal,
// and offered to an MCP client as the other channel changes are.
function test_ws_api_channel_group_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$move = $routes['workspace.channels.group'] ?? null;

	pg_assert_true(is_array($move), 'the move is declared');
	pg_assert_same('POST', $move['method']);
	pg_assert_same('/workspace/channels/{id}/group', $move['path']);
	pg_assert_same($routes['workspace.channels.update']['scope'], $move['scope'], 'the permission that changes a channel');
	pg_assert_same('WorkspaceChannelGroupMove', $move['returns']);
	pg_assert_true(!empty($move['dry_run']), 'it rehearses');
	pg_assert_same(pg_mcp_route_is_offered($routes['workspace.channels.update']), pg_mcp_route_is_offered($move), 'a tool when changing a channel is one');

	$body = array();

	foreach ($move['params'] as $param) {
		if (($param['in'] ?? '') === 'body') {
			$body[$param['name']] = $param;
		}
	}

	pg_assert_same(array('group_id'), array_keys($body));
	pg_assert_true(!empty($body['group_id']['required']) && !empty($body['group_id']['nullable']), 'group_id is sent; null takes the channel out');
	pg_assert_same(0, $body['group_id']['min'], '0 takes the channel out');

	$list = $routes['workspace.channel_groups.list'] ?? null;

	pg_assert_true(is_array($list), 'the groups are listed');
	pg_assert_same('GET', $list['method']);
	pg_assert_same('/workspace/channel-groups', $list['path']);
	pg_assert_same('workspace:read', $list['scope']);
	pg_assert_same(array('list' => 'WorkspaceChannelGroup'), $list['returns']);
	pg_assert_true(array_key_exists('group_id', ws_api_channel_schema()), 'the channel says which group it is in');
}

// How many people hold access on a group is for staff to see; a group at the
// top has no parent, and one without a colour none.
function test_ws_api_channel_group_present()
{
	$group = array('id' => 7, 'parent_id' => 0, 'name' => 'Customers', 'color' => 0, 'hex' => '', 'sort' => 1, 'access' => 3, 'mine' => true, 'path' => 'Customers');

	$staff = ws_api_channel_group_present($group, true);

	pg_assert_same(7, $staff['id']);
	pg_assert_same('Customers', $staff['path']);
	pg_assert_same(null, $staff['parent_id']);
	pg_assert_same(null, $staff['color']);
	pg_assert_same(3, $staff['access']);
	pg_assert_true($staff['mine']);
	pg_assert_same(array_keys(ws_api_channel_group_schema()), array_keys($staff), 'the answer has the declared fields in order');

	$other = ws_api_channel_group_present(array_merge($group, array('parent_id' => 2, 'hex' => '#0d6efd', 'mine' => false)), false);

	pg_assert_same(2, $other['parent_id']);
	pg_assert_same('#0d6efd', $other['color']);
	pg_assert_same(null, $other['access'], 'only staff see who holds access');
	pg_assert_false($other['mine']);
}

// A move to where the channel already is names nobody. The dry run's detail
// leaves the channel out; the answer carries it.
function test_ws_api_channel_group_move_present()
{
	$move = array('ok' => true, 'error' => '', 'joining' => array(), 'leaving' => array(), 'joining_ids' => array(5), 'leaving_ids' => array(), 'path' => 'Customers');
	$channel = array('id' => 4, 'group_id' => 7);

	$same = ws_api_channel_group_move_present($channel, 7, $move, false);

	pg_assert_false($same['changes']);
	pg_assert_same(array(), $same['joining'], 'nothing moves, so nobody joins');
	pg_assert_same(7, $same['group_id']);
	pg_assert_same(7, $same['previous_group_id']);
	pg_assert_false(array_key_exists('channel', $same), 'a dry run has no channel');

	$out = ws_api_channel_group_move_present($channel, 0, array_merge($move, array('joining_ids' => array(), 'path' => '')), true, array('id' => 4));

	pg_assert_true($out['changes']);
	pg_assert_same(null, $out['group_id'], 'out of every group');
	pg_assert_same(null, $out['group_path']);
	pg_assert_same(array(), $out['leaving']);
	pg_assert_same(array_keys(ws_api_channel_group_move_schema()), array_keys($out), 'the answer has the declared fields in order');
}

// Arranging the groups themselves is staff work, gated the way moving a
// channel into a group is: the permission that changes a channel, a rehearsal
// on every write, and one MCP tool per action; the removal addressed as a
// POST for IIS is no second tool.
function test_ws_api_channel_group_management_routes()
{
	$routes = array();
	$offered = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;

		if (pg_mcp_route_is_offered($route)) {
			$offered[] = pg_mcp_tool_name($route['id']);
		}
	}

	$expected = array(
		'workspace.channel_groups.create'        => array('POST', '/workspace/channel-groups', 'WorkspaceChannelGroup'),
		'workspace.channel_groups.update'        => array('POST', '/workspace/channel-groups/{id}', 'WorkspaceChannelGroupChange'),
		'workspace.channel_groups.delete'        => array('DELETE', '/workspace/channel-groups/{id}', 'WorkspaceChannelGroupRemoval'),
		'workspace.channel_groups.delete_post'   => array('POST', '/workspace/channel-groups/{id}/delete', 'WorkspaceChannelGroupRemoval'),
		'workspace.channel_groups.access.grant'  => array('POST', '/workspace/channel-groups/{id}/access', 'WorkspaceChannelGroupAccessChange'),
		'workspace.channel_groups.access.revoke' => array('DELETE', '/workspace/channel-groups/{id}/access/{user_id}', 'WorkspaceChannelGroupAccessChange'),
	);

	foreach ($expected as $id => $shape) {
		$route = $routes[$id] ?? null;

		pg_assert_true(is_array($route), $id . ' is declared');
		pg_assert_same($shape[0], $route['method'], $id);
		pg_assert_same($shape[1], $route['path'], $id);
		pg_assert_same($shape[2], $route['returns'], $id);
		pg_assert_same($routes['workspace.channels.group']['scope'], $route['scope'], $id . ' asks what moving a channel into a group asks');
		pg_assert_true(!empty($route['dry_run']), $id . ' rehearses');
	}

	pg_assert_same(array('PATCH'), $routes['workspace.channel_groups.update']['also_accepts'], 'PATCH beside POST');
	pg_assert_same(array('POST'), $routes['workspace.channel_groups.access.revoke']['also_accepts'], 'POST beside DELETE for IIS');

	$access = $routes['workspace.channel_groups.access'] ?? null;

	pg_assert_true(is_array($access), 'who holds access is read');
	pg_assert_same('GET', $access['method']);
	pg_assert_same('/workspace/channel-groups/{id}/access', $access['path']);
	pg_assert_same('workspace:read', $access['scope']);
	pg_assert_same('WorkspaceChannelGroupAccess', $access['returns']);

	foreach (array('workspace_channel_groups_create', 'workspace_channel_groups_update', 'workspace_channel_groups_delete',
		'workspace_channel_groups_access', 'workspace_channel_groups_access_grant', 'workspace_channel_groups_access_revoke') as $tool) {
		pg_assert_contains($tool, $offered);
	}

	pg_assert_false(in_array('workspace_channel_groups_delete_post', $offered, true), 'the POST alias is no tool');

	$body = array();

	foreach ($routes['workspace.channel_groups.update']['params'] as $param) {
		if (($param['in'] ?? '') === 'body') {
			$body[$param['name']] = $param;
		}
	}

	pg_assert_same(array('name', 'color', 'parent_id', 'before_id'), array_keys($body));
	pg_assert_same(ws_palette_hexes(), $body['color']['values'], 'a colour is one of the palette\'s');
	pg_assert_true(!empty($body['color']['nullable']) && !empty($body['parent_id']['nullable']), 'null clears the colour and puts the group at the top');
}

// Who a chain of groups wants in a channel: the nearest group that gives the
// access is the one named, and only people in the team count.
function test_ws_group_wanted_from()
{
	$rows = array(
		array('group_id' => 3, 'user_id' => 10),
		array('group_id' => 1, 'user_id' => 10),
		array('group_id' => 1, 'user_id' => 11),
		array('group_id' => 9, 'user_id' => 12),
		array('group_id' => 1, 'user_id' => 99),
	);

	pg_assert_same(array(10 => 3, 11 => 1), ws_group_wanted_from(array(3, 1), $rows, array(10, 11, 12)));
	pg_assert_same(array(10 => 1, 11 => 1), ws_group_wanted_from(array(1), $rows, array(10, 11, 12)), 'a group below gives nothing upwards');
	pg_assert_same(array(), ws_group_wanted_from(array(), $rows, array(10, 11, 12)), 'no group, nobody');
}

// Bringing a channel in step: in come the wanted who are not members, out go
// only the members a group had brought whom no group wants now.
function test_ws_group_sync_split()
{
	$members = array(
		10 => array('role' => 'member', 'via_group' => 3),
		11 => array('role' => 'member', 'via_group' => 0),
		12 => array('role' => 'owner', 'via_group' => 3),
		13 => array('role' => 'member', 'via_group' => 1),
	);

	$split = ws_group_sync_split($members, array(10 => 1, 14 => 1));

	pg_assert_same(array(14), $split['in']);
	pg_assert_same(array(13), $split['out'], 'joined by themselves (11) and the owner (12) stay');

	$none = ws_group_sync_split($members, array(10 => 3, 13 => 1));

	pg_assert_same(array(), $none['in']);
	pg_assert_same(array(), $none['out']);
}

// A channel's chain once a group on it goes inside another one, or is taken
// away.
function test_ws_group_chain_after_a_change()
{
	pg_assert_same(array(5, 3, 7, 8), ws_group_chain_moved(array(5, 3, 1), 3, array(7, 8)), 'what is above the group changes, what is below stays');
	pg_assert_same(array(3), ws_group_chain_moved(array(3, 1), 3, array()), 'to the top');
	pg_assert_same(array(4, 2), ws_group_chain_moved(array(4, 2), 3, array(7)), 'a channel the group is not above');
	pg_assert_same(array(5, 1), ws_group_chain_removed(array(5, 3, 1), 3), 'what was in it moves up');
	pg_assert_same(array(1), ws_group_chain_removed(array(3, 1), 3), 'its own channels go to the group it was in');
}

// One rule for where a group may go in front of, on the panel and in the
// API: another group of the level it ends up on, or 0 for the end. A
// before_id from a list that changed meanwhile is refused, not read as the
// end.
function test_ws_group_before_fits()
{
	$groups = array(
		1 => array('id' => 1, 'parent_id' => 0),
		2 => array('id' => 2, 'parent_id' => 0),
		3 => array('id' => 3, 'parent_id' => 1),
		4 => array('id' => 4, 'parent_id' => 1),
	);

	pg_assert_true(ws_group_before_fits(2, 0, 1, $groups), 'in front of a group of the same level');
	pg_assert_true(ws_group_before_fits(3, 1, 4, $groups), 'inside a group too');
	pg_assert_true(ws_group_before_fits(2, 1, 3, $groups), 'in front of a group of the level it moves to');
	pg_assert_true(ws_group_before_fits(2, 0, 0, $groups), '0 is the end');
	pg_assert_true(ws_group_before_fits(2, 0, -1, $groups), 'below 0 is the end as well');
	pg_assert_false(ws_group_before_fits(2, 0, 9, $groups), 'a group that is no longer there');
	pg_assert_false(ws_group_before_fits(2, 0, 3, $groups), 'a group of another level');
	pg_assert_false(ws_group_before_fits(3, 0, 4, $groups), 'a group of the level it leaves');
	pg_assert_false(ws_group_before_fits(2, 0, 2, $groups), 'the group itself');

	// The panel's move and the API's change both ask it: the API before its
	// dry run and before it saves the name, so it can name the field.
	$update = ws_api_test_handler_source('ws_api_channel_groups_update');

	pg_assert_contains('ws_group_before_fits(', ws_api_test_handler_source('ws_group_move'), 'the panel\'s move asks it');
	pg_assert_contains('ws_group_before_fits(', $update, 'the API\'s change asks it');

	pg_assert_true(strpos($update, 'ws_group_before_fits(') < strpos($update, 'api_dry_run_stop('), 'refused before the dry run answers');
	pg_assert_true(strpos($update, 'ws_group_before_fits(') < strpos($update, 'ws_group_save('), 'refused before anything is saved');
}

// A colour is written as the code the list shows; anything else is none.
function test_ws_palette_place_of()
{
	$hexes = ws_palette_hexes();

	pg_assert_same(count(ws_palette()), count($hexes));
	pg_assert_same(1, ws_palette_place_of($hexes[0]));
	pg_assert_same(count($hexes), ws_palette_place_of(strtoupper($hexes[count($hexes) - 1])));
	pg_assert_same(0, ws_palette_place_of(''));
	pg_assert_same(0, ws_palette_place_of('#000000'));
	pg_assert_same($hexes[4], ws_palette_hex(ws_palette_place_of($hexes[4])), 'the round trip');
}

// A channel the owner of the application does not read is counted, never
// named; a channel nobody comes into or leaves is left out.
function test_ws_api_channel_group_memberships_present()
{
	$out = ws_api_channel_group_memberships_present(array(
		4 => array('added' => array(10, 11), 'removed' => array(12)),
		6 => array('added' => array(), 'removed' => array()),
	), array(6 => 'general'));

	pg_assert_same(2, $out['joining']);
	pg_assert_same(1, $out['leaving']);
	pg_assert_same(array(), $out['channels'], 'the private channel is not named');
	pg_assert_same(1, $out['unseen_channels']);
	pg_assert_same(array_keys(ws_api_channel_group_memberships_schema()), array_keys($out), 'the answer has the declared fields in order');
}

// Taking a group away names what moves up and counts what the owner of the
// application does not read.
function test_ws_api_channel_group_removal_present()
{
	$memberships = ws_api_channel_group_memberships_present(array(), array());
	$group = array('id' => 7, 'parent_id' => 0, 'name' => 'Customers', 'path' => 'Customers');

	$out = ws_api_channel_group_removal_present($group, array(4, 5), array(8 => 'Istanbul'), array(), array(4 => 'sales'), $memberships, false);

	pg_assert_same(null, $out['parent_id'], 'what was in it goes to the top');
	pg_assert_same(array(array('channel_id' => 4, 'name' => 'sales')), $out['channels']);
	pg_assert_same(1, $out['unseen_channels']);
	pg_assert_same(array(array('id' => 8, 'name' => 'Istanbul')), $out['groups']);
	pg_assert_false($out['deleted'], 'a dry run deletes nothing');
	pg_assert_same(array_keys(ws_api_channel_group_removal_schema()), array_keys($out), 'the answer has the declared fields in order');
}

/**
 * The workspace functions that write their own line in the activity log in
 * the name of the signed-in person: log_activity() and the session's user
 * name in one function body.
 *
 * @return string[] function names
 */
function pg_test_ws_api_session_loggers()
{
	$names = array();

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/workspace/*.php') as $file) {
		if (strpos(basename($file), 'api') === 0) {
			continue;
		}

		$parts = preg_split('/^function\s+(\w+)\s*\(/m', (string) file_get_contents($file), -1, PREG_SPLIT_DELIM_CAPTURE);

		for ($i = 1; $i < count($parts); $i += 2) {
			$body = $parts[$i + 1];

			if ((strpos($body, 'log_activity(') !== false) && (strpos($body, "\$_SESSION['sessionusername']") !== false)) {
				$names[] = $parts[$i];
			}
		}
	}

	return $names;
}

/**
 * The calls of the given functions in a PHP source, each with whether it
 * sits inside a closure handed to ws_api_as_owner().
 *
 * @param string   $source
 * @param string[] $names
 * @return array[] name, line, as_owner
 */
function pg_test_ws_api_calls_as_owner($source, $names)
{
	$tokens = token_get_all($source);
	$wanted = array_flip($names);
	$significant = array();

	foreach ($tokens as $token) {
		if (!is_array($token) || !in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
			$significant[] = $token;
		}
	}

	$depth = 0;
	$owner = array();
	$pending = false;
	$calls = array();
	$count = count($significant);

	for ($i = 0; $i < $count; $i++) {
		$token = $significant[$i];

		if ($token === '{' || (is_array($token) && in_array($token[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
			$depth++;

			// The body of the closure handed to ws_api_as_owner().
			if ($pending && ($token === '{')) {
				$owner[] = $depth;
				$pending = false;
			}

			continue;
		}

		if ($token === '}') {
			if (!empty($owner) && (end($owner) === $depth)) {
				array_pop($owner);
			}

			$depth--;
			continue;
		}

		if (!is_array($token) || ($token[0] !== T_STRING) || (($significant[$i + 1] ?? null) !== '(')) {
			continue;
		}

		$previous = $significant[$i - 1] ?? null;

		if (is_array($previous) && in_array($previous[0], array(T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON), true)) {
			continue;
		}

		if ($token[1] === 'ws_api_as_owner') {
			$pending = is_array($significant[$i + 2] ?? null) && ($significant[$i + 2][0] === T_FUNCTION);
		} elseif (isset($wanted[$token[1]])) {
			$calls[] = array('name' => $token[1], 'line' => $token[2], 'as_owner' => !empty($owner));
		}
	}

	return $calls;
}

// The scan above tells a call inside the closure from one beside it.
function test_ws_api_calls_as_owner_scan()
{
	$source = "<?php\nfunction a() {\n    \$x = ws_api_as_owner(function () use (\$y) {\n        if (\$y) { \$s = \"{\$y}\"; }\n        return ws_channel_create(\$y);\n    });\n    ws_channel_create(\$x);\n}\n";
	$calls = pg_test_ws_api_calls_as_owner($source, array('ws_channel_create'));

	pg_assert_same(2, count($calls));
	pg_assert_true($calls[0]['as_owner'], 'inside the closure');
	pg_assert_same(5, $calls[0]['line']);
	pg_assert_false($calls[1]['as_owner'], 'after the closure');
}

// The API keeps no session: a handler calls a panel function that writes its
// activity log line in the name of the signed-in person inside
// ws_api_as_owner(), or the line is written with no name.
function test_ws_api_panel_log_lines_carry_the_owner()
{
	$loggers = pg_test_ws_api_session_loggers();

	foreach (array('ws_channel_create', 'ws_message_delete', 'ws_template_apply', 'ws_group_save') as $name) {
		pg_assert_true(in_array($name, $loggers, true), $name . '() writes its line in the name of the signed-in person');
	}

	$found = array();

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/workspace/api*.php') as $file) {
		foreach (pg_test_ws_api_calls_as_owner((string) file_get_contents($file), $loggers) as $call) {
			$found[$call['name']] = true;
			pg_assert_true($call['as_owner'], basename($file) . ':' . $call['line'] . ' calls ' . $call['name'] . '() inside ws_api_as_owner()');
		}
	}

	foreach (array('ws_channel_create', 'ws_message_delete', 'ws_template_apply', 'ws_group_save') as $name) {
		pg_assert_true(isset($found[$name]), 'the API calls ' . $name . '()');
	}
}

// Writing a message, posting a file, leaving an emoji, giving a read
// receipt, answering an approval and adding a task note can each be
// rehearsed; the ones offered to an MCP client take dry_run as an argument.
function test_ws_api_message_file_reaction_ack_decision_and_note_rehearse()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$ids = array('workspace.messages.create', 'workspace.files.create', 'workspace.messages.react', 'workspace.messages.ack',
		'workspace.approvals.decide', 'workspace.tasks.notes.create');

	foreach ($ids as $id) {
		pg_assert_true(isset($routes[$id]), $id . ' is declared');
		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' rehearses');

		if (pg_mcp_route_is_offered($routes[$id])) {
			$properties = (array) pg_mcp_tool_from_route($routes[$id])['inputSchema']['properties'];

			pg_assert_same('boolean', $properties['dry_run']['type'] ?? null, $id . ': the tool takes dry_run');
		}
	}

	pg_assert_true(pg_mcp_route_is_offered($routes['workspace.files.create']), 'the upload is a tool, for a small file');
	pg_assert_true(pg_mcp_route_is_offered($routes['workspace.messages.create']), 'writing is still a tool');
}

// The panel's base64, with the data: prefix it sends or without; anything
// that is not base64 is no file.
function test_ws_upload_decode()
{
	pg_assert_same('hello', ws_upload_decode('data:text/plain;base64,aGVsbG8='));
	pg_assert_same('hello', ws_upload_decode('aGVsbG8='));
	pg_assert_same("\xfb\xef", ws_upload_decode('data:image/png;base64, +8='), 'a space a form turned + into is a +');
	pg_assert_same(false, ws_upload_decode('not*base64'));
}

// What a channel says of a file from its name and bytes, without anything
// kept: what a dry run meets before the file would reach the disk.
function test_ws_upload_inspect()
{
	$ok = ws_upload_inspect("..\\dir/re\x01port.PDF", '%PDF-1.4 a document');

	pg_assert_true($ok['ok'], 'a document');
	pg_assert_same('report.PDF', $ok['name'], 'the path and control characters are left out');
	pg_assert_same('pdf', $ok['extension']);

	pg_assert_false(ws_upload_inspect('', 'bytes')['ok'], 'no name');
	pg_assert_false(ws_upload_inspect('notes.txt', '')['ok'], 'no bytes');
	pg_assert_false(ws_upload_inspect('notes.txt', false)['ok'], 'bytes that could not be decoded');

	foreach (array('logo.svg', 'page.html', 'shell.php', 'photo.jpg.php', '.htaccess') as $name) {
		pg_assert_false(ws_upload_inspect($name, 'bytes')['ok'], $name . ' is refused by its name');
	}

	pg_assert_false(ws_upload_inspect('photo.png', 'not a picture')['ok'], 'a picture has to be a picture');

	if (function_exists('finfo_open')) {
		pg_assert_false(ws_upload_inspect('notes.txt', "<!DOCTYPE html>\n<html><head><title>x</title></head><body><script>alert(1)</script></body></html>")['ok'], 'a page is refused whatever it is called');
	}

	if (function_exists('imagecreatetruecolor') && function_exists('imagepng')) {
		$image = imagecreatetruecolor(4, 4);

		ob_start();
		imagepng($image);
		$png = (string) ob_get_clean();
		pg_image_destroy($image);

		$picture = ws_upload_inspect('photo.png', $png);

		pg_assert_true($picture['ok'], 'a real picture');
		pg_assert_same('png', $picture['extension']);
	}
}

// A bell row names who set it off: the person signed in on the panel, or,
// for a call through the API (which keeps no session), the application's
// owner - the person on the device or behind the OAuth connection, the owner
// of an application key.
function test_ws_bell_user()
{
	pg_assert_same('ayse', ws_bell_user('ayse', null), 'the panel: the person signed in');
	pg_assert_same('mehmet', ws_bell_user('', array('id' => 3, 'owner' => array('id' => 7, 'username' => 'mehmet'))), 'the API: the application\'s owner');
	pg_assert_same('mehmet', ws_bell_user(null, array('id' => 3, 'owner' => array('id' => 7, 'username' => 'mehmet'))), 'no session at all');
	pg_assert_same('ayse', ws_bell_user('ayse', array('id' => 3, 'owner' => array('id' => 7, 'username' => 'mehmet'))), 'a session set by ws_api_as_owner() stands');
	pg_assert_same('', ws_bell_user('', array('id' => 3)), 'a call refused before its owner was loaded');
	pg_assert_same('', ws_bell_user('', null), 'a scheduled job');
}

// Every bell row the workspace writes takes its user from ws_bell_user(),
// whatever set it off (a mention, a task, an approval, a poll ...).
function test_ws_bell_rows_carry_the_owner()
{
	$writers = array();

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/workspace/*.php') as $file) {
		$parts = preg_split('/^function\s+(\w+)\s*\(/m', (string) file_get_contents($file), -1, PREG_SPLIT_DELIM_CAPTURE);

		for ($i = 1; $i < count($parts); $i += 2) {
			if (stripos($parts[$i + 1], 'INSERT INTO notifications') !== false) {
				$writers[] = $parts[$i];
				pg_assert_contains('ws_bell_user(', $parts[$i + 1], basename($file) . ': ' . $parts[$i] . '() names the user through ws_bell_user()');
			}
		}
	}

	pg_assert_true(in_array('ws_bell_add', $writers, true), 'ws_bell_add() writes the bell row');
}

// A file posted from the panel (the paperclip, an edited copy sent as a
// reply) is kept only once its message would be written, as the API's files
// endpoint does: a refused message leaves no file, no file row and no channel
// folder behind.
function test_ws_message_send_file_checks_before_storing()
{
	pg_assert_contains('ws_message_send_file_bytes(', ws_api_test_handler_source('ws_message_send_file'), 'base64 goes through the bytes path');

	$send = ws_api_test_handler_source('ws_message_send_file_bytes');

	pg_assert_true(strpos($send, 'ws_upload_check(') < strpos($send, 'ws_store_upload_bytes('), 'the file is checked before it is kept');
	pg_assert_true(strpos($send, 'ws_message_send_check(') < strpos($send, 'ws_store_upload_bytes('), 'the message is checked before the file is kept');
	pg_assert_true(strpos($send, 'ws_store_upload_bytes(') < strpos($send, 'ws_upload_discard('), 'a message refused after all takes its file back');

	$api = ws_api_test_handler_source('ws_api_files_create');

	pg_assert_true(strpos($api, 'ws_message_send_check(') < strpos($api, 'ws_store_upload_bytes('), 'the API checks the message first as well');

	$edited = ws_api_test_handler_source('ws_message_file_send');

	pg_assert_contains('ws_message_send_file(', $edited, 'an edited copy goes through it');
	pg_assert_false(strpos($edited, 'ws_store_upload') !== false, 'an edited copy keeps no file of its own');

	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$start = strpos($actions, "case 'ws_attach':");
	$attach = ($start === false) ? '' : substr($actions, $start, strpos($actions, 'case \'', $start + 18) - $start);

	pg_assert_contains('ws_message_send_file(', $attach, 'the paperclip goes through it');
	pg_assert_false(strpos($attach, 'ws_store_upload') !== false, 'the paperclip keeps no file of its own');

	// A copy of a file of the file manager (library.php) too.
	$library = ws_api_test_handler_source('ws_library_send');

	pg_assert_contains('ws_message_send_file_bytes(', $library, 'a file of the file manager goes through it');
	pg_assert_false(strpos($library, 'ws_store_upload') !== false, 'a file of the file manager keeps no file of its own');
	pg_assert_true(strpos($library, 'ws_library_file(') < strpos($library, 'file_get_contents('), 'the file is checked before it is read');

	$start = strpos($actions, "case 'ws_library_attach':");
	$picked = ($start === false) ? '' : substr($actions, $start, strpos($actions, 'case \'', $start + 26) - $start);

	pg_assert_contains("ws_action_channel(\$viewer, \$request, 'post')", $picked, 'the picker needs a channel the person may post in');
	pg_assert_contains('ws_library_send(', $picked, 'the picker goes through it');
}

// Through MCP a file travels as content_base64 inside one JSON-RPC message:
// the tool takes it and name, never the form part, and holds the file to
// ws_mcp_upload_max_bytes(), measured on the text before it is decoded.
function test_ws_api_files_create_small_file_tool()
{
	$route = null;

	foreach (ws_api_routes() as $candidate) {
		if ($candidate['id'] === 'workspace.files.create') {
			$route = $candidate;
		}
	}

	$tool = pg_mcp_tool_from_route($route);
	$properties = (array) $tool['inputSchema']['properties'];

	pg_assert_same('workspace_files_create', $tool['name']);
	pg_assert_true(isset($properties['content_base64'], $properties['name'], $properties['dry_run']), 'the tool takes content_base64, name and dry_run');
	pg_assert_false(isset($properties['file']), 'no form part through MCP');
	pg_assert_false($tool['annotations']['destructiveHint'], 'posting a file only adds');
	pg_assert_contains('MCP', $tool['description']);

	pg_assert_true(ws_mcp_upload_max_bytes() > 0, 'a small file is taken');
	pg_assert_true(ws_mcp_upload_max_bytes() <= 1048576, 'at most 1 MB through MCP');
	pg_assert_true(ws_mcp_upload_max_bytes() <= ws_upload_max_bytes(), 'never above the channel limit');

	foreach (array(0, 1, 2, 3, 4, 1000, 1048577) as $size) {
		$encoded = base64_encode(str_repeat('a', $size));

		pg_assert_same($size, ws_upload_base64_bytes($encoded), $size . ' bytes');
		pg_assert_same($size, ws_upload_base64_bytes('data:image/png;base64,' . $encoded), $size . ' bytes after a data: prefix');
		pg_assert_same($size, ws_upload_base64_bytes(chunk_split($encoded, 76, "\r\n")), $size . ' bytes in lines');
	}

	$input = preg_replace('~^\s*//.*$~m', '', ws_api_test_handler_source('ws_api_upload_input'));

	pg_assert_contains('ws_mcp_upload_max_bytes(', $input);
	pg_assert_contains('ws_upload_base64_bytes(', $input);
}

// A file may answer a message (a voice reply): files.create declares an
// optional parent_id, refuses a bad one on that field before its dry run
// stops, and hands it to the write; the panel's upload sends it on through
// the same check. Posting a file stays no MCP tool.
function test_ws_api_files_create_answers_a_message()
{
	$route = null;

	foreach (ws_api_routes() as $candidate) {
		if ($candidate['id'] === 'workspace.files.create') {
			$route = $candidate;
		}
	}

	$params = array();

	foreach ((array) ($route['params'] ?? array()) as $param) {
		$params[$param['name']] = $param;
	}

	pg_assert_true(isset($params['parent_id']), 'files.create declares parent_id');
	pg_assert_same('body', $params['parent_id']['in'] ?? null, 'parent_id is a body field');
	pg_assert_same('int', $params['parent_id']['type'] ?? null, 'parent_id is a whole number');
	pg_assert_same(1, $params['parent_id']['min'] ?? null, 'parent_id names a message');
	pg_assert_true(empty($params['parent_id']['required']), 'parent_id may be left out');
	pg_assert_contains('parent_id', (string) $route['description'], 'the description says what parent_id does');
	pg_assert_false(pg_mcp_route_is_offered($route), 'posting a file stays no MCP tool');

	$api = preg_replace('~^\s*//.*$~m', '', ws_api_test_handler_source('ws_api_files_create'));

	pg_assert_contains("api_fail_validation(\$refused, 'parent_id')", $api, 'a bad parent is refused on parent_id');
	pg_assert_contains("'parent_id' => \$parent_id", $api, 'the parent reaches the write');

	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$start = strpos($actions, "case 'ws_attach':");
	$attach = ($start === false) ? '' : substr($actions, $start, strpos($actions, 'case \'', $start + 18) - $start);
	$attach = preg_replace('~^\s*//.*$~m', '', $attach);

	pg_assert_true(strpos($attach, 'ws_message_parent_check(') !== false, 'the panel checks the parent');
	pg_assert_true(strpos($attach, 'ws_message_parent_check(') < strpos($attach, 'ws_message_send_file('), 'the panel checks the parent first');
	pg_assert_contains("array('parent_id' => \$parent_id)", $attach, 'the panel hands the parent to the upload');
}

// Every write of the module can be rehearsed: X-Dry-Run is offered on the
// route, and the tool an MCP client sees takes dry_run as an argument.
function test_ws_api_every_write_rehearses()
{
	foreach (ws_api_routes() as $route) {
		if ($route['method'] === 'GET') {
			continue;
		}

		pg_assert_true(!empty($route['dry_run']), $route['id'] . ' rehearses');

		if (pg_mcp_route_is_offered($route)) {
			$properties = (array) pg_mcp_tool_from_route($route)['inputSchema']['properties'];

			pg_assert_same('boolean', $properties['dry_run']['type'] ?? null, $route['id'] . ': the tool takes dry_run');
		}
	}
}

// The scheduled actions' routes (api_scheduled.php): staff only, all four
// tools, pause and cancel through one rehearsed write; the panel's write
// meets the same check.
function test_ws_api_scheduled_actions_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$expected = array(
		'workspace.scheduled_actions.list'   => array('GET', '/workspace/scheduled-actions', 'workspace:read', 'ws_api_scheduled_actions_list'),
		'workspace.scheduled_actions.get'    => array('GET', '/workspace/scheduled-actions/{id}', 'workspace:read', 'ws_api_scheduled_actions_get'),
		'workspace.scheduled_actions.pause'  => array('POST', '/workspace/scheduled-actions/{id}/pause', 'workspace:write', 'ws_api_scheduled_actions_pause'),
		'workspace.scheduled_actions.cancel' => array('POST', '/workspace/scheduled-actions/{id}/cancel', 'workspace:write', 'ws_api_scheduled_actions_cancel'),
	);

	foreach ($expected as $id => $shape) {
		pg_assert_true(isset($routes[$id]), $id . ' is declared');

		if (!isset($routes[$id])) {
			continue;
		}

		pg_assert_same($shape, array($routes[$id]['method'], $routes[$id]['path'], $routes[$id]['scope'], $routes[$id]['handler']), $id . ' method, path, scope, handler');
		pg_assert_true(pg_mcp_route_is_offered($routes[$id]), $id . ' is a tool');
		pg_assert_contains('ws_api_scheduled_viewer()', ws_api_test_handler_source($routes[$id]['handler']) . ws_api_test_handler_source('ws_api_scheduled_actions_set_status'), $id . ' is for staff only');
	}

	pg_assert_same('ws_api_scheduled_action_schema', ws_openapi_objects()['WorkspaceScheduledAction'] ?? null, 'the object is declared');
	pg_assert_contains('ws_can_schedule($viewer)', ws_api_test_handler_source('ws_api_scheduled_viewer'), 'staff are told apart by ws_can_schedule()');

	foreach (array('ws_api_scheduled_actions_pause' => "'paused'", 'ws_api_scheduled_actions_cancel' => "'cancelled'") as $handler => $to) {
		pg_assert_contains('ws_api_scheduled_actions_set_status($params, ' . $to . ')', ws_api_test_handler_source($handler), $handler . ' sets ' . $to);
	}

	// Never a scheduled message nor a flow's anchor row.
	$one = ws_api_test_handler_source('ws_api_scheduled_action_or_404');

	pg_assert_contains('ws_scheduled_action(', $one, 'only the action kind is reached');
	pg_assert_contains('ws_api_scheduled_is_flow_anchor(', $one, 'a flow\'s row is a 404');
	pg_assert_true(ws_api_scheduled_is_flow_anchor(array('flow_id' => '7')), 'flow_id set is an anchor');
	pg_assert_false(ws_api_scheduled_is_flow_anchor(array('flow_id' => '0')), 'flow_id 0 is an action');
	pg_assert_false(ws_api_scheduled_is_flow_anchor(array()), 'no flow_id column is an action');

	$list = ws_api_test_handler_source('ws_api_scheduled_actions_list');

	pg_assert_contains('ws_scheduled_actions_only()', $list, 'the listing leaves scheduled messages out');
	pg_assert_contains('flow_id = 0', $list, 'the listing leaves flow anchors out');

	// The panel's pause, resume and cancel run the same check.
	$panel = ws_api_test_handler_source('ws_scheduled_set_status');

	pg_assert_true(strpos($panel, 'ws_scheduled_set_status_check(') < strpos($panel, 'UPDATE ws_scheduled_actions'), 'the panel checks before it writes');
}

// The channels an action names, and its sentences given only when the
// caller reads every one of them.
function test_ws_api_scheduled_channel_ids()
{
	$row = array(
		'channel_id' => 4,
		'rules'      => array(array('type' => 'join', 'channel_id' => 9), array('type' => 'workday'), 'junk'),
		'action'     => array('type' => 'post', 'channel_id' => -1, 'body' => 'Hi'),
		'follow'     => array(array('on' => 'done', 'do' => array('type' => 'post', 'channel_id' => 12)), array('on' => 'failed', 'do' => array('type' => 'trigger', 'target_id' => 3)), array('on' => 'done', 'do' => array('type' => 'post', 'channel_id' => 4))),
	);

	pg_assert_same(array(4, 9, 12), ws_api_scheduled_channel_ids($row), 'home, rule and follow-up channels, once, the joined channel left out');
	pg_assert_same(array(), ws_api_scheduled_channel_ids(array('channel_id' => 0, 'rules' => array(), 'action' => null, 'follow' => array())), 'none');

	pg_assert_true(ws_api_scheduled_texts_given(array(4, 9), array(4 => true, 9 => true)), 'all read');
	pg_assert_false(ws_api_scheduled_texts_given(array(4, 9), array(4 => true, 9 => false)), 'one not read');
	pg_assert_false(ws_api_scheduled_texts_given(array(4, 9), array(4 => true)), 'one not looked at');
	pg_assert_true(ws_api_scheduled_texts_given(array(), array()), 'no channel named');

	$present = ws_api_test_handler_source('ws_api_scheduled_action_present');

	pg_assert_contains('ws_api_scheduled_channel_open(', $present, 'each channel is looked at');
	pg_assert_contains('ws_can_read_channel(', ws_api_test_handler_source('ws_api_scheduled_channel_open'), 'by the owner\'s reach');
	pg_assert_contains('ws_claude_channel_allowed(', ws_api_test_handler_source('ws_api_scheduled_channel_open'), 'and Claude\'s');
	pg_assert_same(array_keys(ws_api_scheduled_action_schema()), array_keys(array(
		'id' => 1, 'name' => 1, 'status' => 1, 'next_run_at' => 1, 'repeats' => 1, 'on_event' => 1, 'joins' => 1, 'trigger_only' => 1, 'texts_withheld' => 1,
		'when' => 1, 'conditions' => 1, 'action_type' => 1, 'action_text' => 1, 'follow' => 1, 'starters' => 1, 'channel_id' => 1, 'channel_name' => 1,
		'message_id' => 1, 'run_count' => 1, 'last_run' => 1, 'can_change' => 1, 'created_by' => 1, 'created_at' => 1, 'updated_at' => 1, 'history' => 1,
	)), 'the schema names the fields');
}

// A rehearsal meets the panel's own checks and stops before the panel's
// write: the *_check() function first, then the stop, then the write.
function test_ws_api_rehearsal_stops_before_the_write()
{
	$order = array(
		'ws_api_tasks_create'         => array('ws_task_create_check(', 'api_dry_run_stop(', 'ws_task_create('),
		'ws_api_tasks_update'         => array('ws_task_update_check(', 'api_dry_run_stop(', 'ws_task_update('),
		'ws_api_tasks_complete'       => array('ws_task_set_status_check(', 'api_dry_run_stop(', 'ws_task_set_status('),
		'ws_api_task_recurrence_set'  => array('ws_recurrence_save_check(', 'api_dry_run_stop(', 'ws_recurrence_save('),
		'ws_api_task_recurrence_end'  => array('ws_recurrence_end_check(', 'api_dry_run_stop(', 'ws_recurrence_end('),
		'ws_api_tasks_delegate'       => array('ws_task_delegate_check(', 'api_dry_run_stop(', 'ws_task_delegate('),
		'ws_api_tasks_delegate_stop'  => array('ws_task_delegation_stop_check(', 'api_dry_run_stop(', 'ws_task_delegation_stop('),
		'ws_api_notes_create'         => array('ws_note_save_check(', 'api_dry_run_stop(', 'ws_note_save('),
		'ws_api_notes_update'         => array('ws_note_save_check(', 'api_dry_run_stop(', 'ws_note_save('),
		'ws_api_plan_items_create'    => array('ws_event_save_check(', 'api_dry_run_stop(', 'ws_event_save('),
		'ws_api_plan_items_update'    => array('ws_event_save_check(', 'api_dry_run_stop(', 'ws_event_save('),
		'ws_api_changes_apply'        => array('ws_api_proposal_decision_gate(', 'ws_change_apply_check(', 'ws_change_apply_record_check(', 'api_dry_run_stop(', 'ws_change_apply('),
		'ws_api_changes_dismiss'      => array('ws_api_proposal_decision_gate(', 'ws_change_dismiss_check(', 'api_dry_run_stop(', 'ws_change_dismiss('),
		'ws_api_flows_create'         => array('ws_flow_save_check(', 'api_dry_run_stop(', 'ws_flow_save('),
		'ws_api_flows_update'         => array('ws_flow_save_check(', 'api_dry_run_stop(', 'ws_flow_save('),
		'ws_api_flows_publish'        => array('ws_api_own_words_only(', 'ws_flow_publish_check(', 'api_dry_run_stop(', 'ws_flow_publish('),
		'ws_api_flows_pause'          => array('ws_flow_pause_check(', 'api_dry_run_stop(', 'ws_flow_pause('),
		'ws_api_flows_delete'         => array('ws_flow_bin_check(', 'api_dry_run_stop(', 'ws_flow_bin('),
		'ws_api_flow_runs_start'      => array('ws_flow_run_start_check(', 'api_dry_run_stop(', 'ws_flow_run_start('),
		'ws_api_flow_runs_cancel'     => array('ws_flow_run_cancel_check(', 'api_dry_run_stop(', 'ws_flow_run_cancel('),
		'ws_api_scheduled_actions_set_status' => array('ws_api_scheduled_viewer(', 'ws_api_scheduled_action_or_404(', 'ws_scheduled_set_status_check(', 'api_dry_run_stop(', 'ws_scheduled_set_status('),
		'ws_api_flow_shares_set'      => array('ws_api_own_words_only(', 'ws_api_is_assistant()', 'ws_flow_share_set_check(', 'api_dry_run_stop(', 'ws_flow_share_set('),
		'ws_api_flow_shares_delete'   => array('ws_api_own_words_only(', 'ws_api_is_assistant()', 'ws_flow_share_remove_check(', 'api_dry_run_stop(', 'ws_flow_share_remove('),
		'ws_api_messages_forward'     => array('ws_forward_to_channel_check(', 'ws_api_channel_or_404($viewer, $checked', 'api_dry_run_stop(', 'ws_forward_to_channel('),
		'ws_api_files_create'         => array('ws_upload_check(', 'ws_message_send_check(', 'ws_message_parent_check(', 'api_dry_run_stop(', 'ws_store_upload_bytes('),
		'ws_api_changes_propose'      => array('ws_api_proposal_agent(', 'ws_api_proposal_scopes(', 'ws_message_send_check(', 'ws_api_proposal_rows(', 'ws_api_proposal_limit(', 'api_dry_run_stop(', 'ws_proposal_store('),
		'ws_api_task_changes_propose' => array('ws_api_proposal_agent(', 'ws_api_proposal_scopes(', 'ws_task_note_add_check(', 'ws_api_proposal_rows(', 'ws_task_delegation_open_run(', 'ws_api_proposal_limit(', 'api_dry_run_stop(', 'ws_proposal_store('),
		'ws_api_mcp_servers_create'   => array('ws_api_mcp_admin(', 'ws_mcp_save_check(', 'api_dry_run_stop(', 'ws_mcp_save(', 'ws_mcp_refresh_tools('),
		'ws_api_mcp_servers_refresh'  => array('ws_api_mcp_admin(', 'ws_mcp_refresh_check(', 'api_dry_run_stop(', 'ws_mcp_refresh_tools('),
		'ws_api_mcp_servers_tools'    => array('ws_api_mcp_admin(', 'ws_mcp_tools_check(', 'api_dry_run_stop(', 'ws_mcp_tools_save('),
		'ws_api_mcp_servers_probe'    => array('ws_api_mcp_admin(', 'ws_mcp_probe_check(', 'api_dry_run_stop(', 'ws_mcp_probe_address('),
		'ws_api_claude_claim'         => array('api_dry_run_stop(', 'UPDATE ws_ai_requests'),
		'ws_api_claude_fail'          => array('api_dry_run_stop(', 'ws_message_send('),
	);

	foreach ($order as $handler => $needles) {
		// The calls, not the comments that name them.
		$source = preg_replace('~^\s*//.*$~m', '', ws_api_test_handler_source($handler));
		$at = -1;

		foreach ($needles as $needle) {
			$found = strpos($source, $needle);

			pg_assert_true(($found !== false) && ($found > $at), $handler . ': ' . $needle . ' comes after what is before it');
			$at = ($found === false) ? $at : $found;
		}
	}

	// An answer to Claude's request stops in each of its four places (the
	// editor, a note, a task, a channel), before what that place writes.
	$answer = preg_replace('~^\s*//.*$~m', '', ws_api_test_handler_source('ws_api_claude_answer'));

	pg_assert_same(4, substr_count($answer, 'api_dry_run_stop('), 'every kind of request rehearses');

	// The answer in a channel is written through ws_assistant_writer(): the
	// owner of the application, or the person who asked.
	foreach (array('pg_design_ai_request_answer(', "UPDATE ws_ai_requests", 'ws_task_delegation_answer(', 'ws_message_send($place[\'writer\']') as $write) {
		$before = substr($answer, 0, (int) strpos($answer, $write));

		pg_assert_true(strrpos($before, 'api_dry_run_stop(') > (int) strrpos($before, 'api_fail_validation('), $write . ' follows its own stop');
	}

	pg_assert_true(strpos($answer, 'ws_message_send_check(') < strpos($answer, 'ws_message_send($place[\'writer\']'), 'the answer in a channel is checked as a message first');
}

// A decision that records an applied change says who proposed it in its
// claude_change, by the agent GET /workspace/changes lists the change under:
// an application's proposal is never credited to Claude.
function test_ws_api_decision_change_names_its_agent()
{
	$row = array(
		'id' => 70, 'channel_id' => 3, 'kind' => 'decision', 'body' => 'Price changed.', 'sender_kind' => 'user', 'sender_id' => 4,
		'marked_by' => 0, 'locked' => 1, 'file_id' => 0, 'file_name' => '', 'tl_at' => 1800000000, 'created_at' => 1800000000,
	);
	$people = array(4 => array('name' => 'Erdal'));
	$change = array('type' => 'product', 'type_label' => 'Product', 'action' => 'update', 'record_id' => 12);

	foreach (array('oauth:5', 'app:12', 'ai', 'claude') as $agent) {
		$decision = ws_api_decision_present(array('id' => 4), $row, array(), array(), $people, array(70 => $change + array('agent_key' => $agent)));

		pg_assert_same($agent, $decision['claude_change']['agent'] ?? null, $agent . ' is named');
	}

	pg_assert_same('claude', ws_api_decision_present(array('id' => 4), $row, array(), array(), $people, array(70 => $change))['claude_change']['agent'] ?? null, 'a change with no agent is Claude\'s');
	pg_assert_same('string', ws_api_decision_change_schema()['agent'] ?? null, 'the field is declared');
	pg_assert_contains('ws_change_agent_key(', ws_api_test_handler_source('ws_timeline_claude_changes'), 'the agent is the one GET /workspace/changes gives');
}

// Forwarding messages over the API (workspace.messages.forward) is an
// ordinary write, offered to an MCP client as a tool: the new message carries
// the writer's name, so it needs no team devices session. The panel's
// forward and the route meet one check.
function test_ws_api_messages_forward_route()
{
	$route = null;

	foreach (ws_api_routes() as $candidate) {
		if ($candidate['id'] === 'workspace.messages.forward') {
			$route = $candidate;
		}
	}

	pg_assert_true(is_array($route), 'the forward route is declared');
	pg_assert_same('POST', $route['method']);
	pg_assert_same('/workspace/channels/{id}/forwards', $route['path']);
	pg_assert_same('workspace:write', $route['scope']);
	pg_assert_true(!empty($route['dry_run']), 'forward rehearses');
	pg_assert_true(pg_mcp_route_is_offered($route), 'forward is an MCP tool');
	pg_assert_true(strpos(ws_api_test_handler_source('ws_owner_scopes'), "'workspace:write'") !== false, 'the scope is in the owner ceiling');

	$source = ws_api_test_handler_source('ws_api_messages_forward');

	pg_assert_false(strpos($source, 'ws_api_own_words_only(') !== false, 'forward is not gated as the person\'s own words');
	pg_assert_false(strpos($source, 'ws_api_person_only(') !== false, 'an application key may forward');
	pg_assert_true(strpos($source, 'ws_api_writer_app_id()') !== false, 'an application forwards as itself');
	pg_assert_true(strpos(ws_api_test_handler_source('ws_forward_to_channel'), 'ws_forward_to_channel_check(') !== false, 'the panel meets the same check');
	pg_assert_true(strpos(ws_api_test_handler_source('ws_forward_to_channel_check'), 'ws_forward_pick(') !== false, 'the source channel is read with ws_can_read_channel()');
	pg_assert_true(strpos(ws_api_test_handler_source('ws_forward_pick'), 'ws_can_read_channel(') !== false, 'the pick reads the source channel');
}
