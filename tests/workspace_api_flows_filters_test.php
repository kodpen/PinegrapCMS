<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the narrowed flow list and the start of a flow in words
 * (GET /workspace/flows, GET /workspace/flows/{id}): the conditions each
 * filter adds to the query, the values the route takes, how a graph
 * starts, and the start of each kind of start box said in words, a channel
 * the person does not read left unnamed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_conditions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_anchors.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/recurrence.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/watch.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_flows.php');

// A graph of one start box and the end.
function ws_api_flows_filters_test_graph($kind, $config = array())
{
	return ws_flow_graph_normalize(array(
		'nodes' => array(
			array('id' => 'start', 'kind' => $kind, 'config' => $config),
			array('id' => 'stop', 'kind' => 'end'),
		),
		'edges' => array(array('from' => 'start', 'to' => 'stop')),
	));
}

// The parameters of the list route, by name.
function ws_api_flows_filters_test_params()
{
	foreach (ws_api_routes() as $route) {
		if ($route['id'] === 'workspace.flows.list') {
			$params = array();

			foreach ($route['params'] as $param) {
				$params[$param['name']] = $param;
			}

			return $params;
		}
	}

	return array();
}

// No filter adds nothing; each adds its own condition, all of them together.
function test_workspace_api_flows_filters_where()
{
	pg_assert_same('', ws_flows_list_where(array()));
	pg_assert_same('', ws_flows_list_where(array('start' => 'time', 'status' => '', 'channel_id' => 0, 'updated_since' => 0)));
	pg_assert_same(" AND status = 'paused'", ws_flows_list_where(array('status' => 'paused')));
	pg_assert_same(" AND channel_id = '12'", ws_flows_list_where(array('channel_id' => '12')));
	pg_assert_same(" AND updated_at >= '1798761600'", ws_flows_list_where(array('updated_since' => 1798761600)));
	pg_assert_same(" AND status = 'live' AND channel_id = '4' AND updated_at >= '100'",
		ws_flows_list_where(array('status' => 'live', 'channel_id' => 4, 'updated_since' => 100)));
}

// A state outside draft, live and paused, a binned one included, adds
// nothing to the query: deleted flows are never listed.
function test_workspace_api_flows_filters_where_refuses_other_values()
{
	pg_assert_same('', ws_flows_list_where(array('status' => 'binned')));
	pg_assert_same('', ws_flows_list_where(array('status' => "live' OR '1'='1")));
	pg_assert_same('', ws_flows_list_where(array('channel_id' => -3, 'updated_since' => -1)));
}

// The route takes each filter with its values; anything else is refused by
// the validator (422 validation_failed) before the handler runs.
function test_workspace_api_flows_filters_route_params()
{
	$params = ws_api_flows_filters_test_params();

	pg_assert_same('enum', $params['start']['type']);
	pg_assert_same(array('time', 'event', 'join', 'manual'), $params['start']['values']);
	pg_assert_same(array_values(ws_flow_start_names()), array('time', 'event', 'join', 'manual'));
	pg_assert_same('enum', $params['status']['type']);
	pg_assert_same(array('draft', 'live', 'paused'), $params['status']['values']);
	pg_assert_same('int', $params['channel_id']['type']);
	pg_assert_same(1, $params['channel_id']['min']);
	pg_assert_same('datetime', $params['updated_since']['type']);

	foreach (array('start', 'status', 'channel_id', 'updated_since') as $name) {
		pg_assert_same('query', $params[$name]['in'], $name);
		pg_assert_false(!empty($params[$name]['required']), $name . ' is optional');
	}
}

// How a graph starts, by its start box; a draft without one starts no way.
function test_workspace_api_flows_filters_start_kind()
{
	pg_assert_same('manual', ws_flow_start_kind(ws_api_flows_filters_test_graph('manual')));
	pg_assert_same('time', ws_flow_start_kind(ws_api_flows_filters_test_graph('schedule', array('date' => '2030-01-07', 'time' => '09:00'))));
	pg_assert_same('event', ws_flow_start_kind(ws_api_flows_filters_test_graph('event', array('event' => 'order.created'))));
	pg_assert_same('join', ws_flow_start_kind(ws_api_flows_filters_test_graph('join')));
	pg_assert_same('', ws_flow_start_kind(ws_flow_graph_empty()));
}

// A published flow starts as its live version does, one never published as
// its draft.
function test_workspace_api_flows_filters_start_graph()
{
	$draft = json_encode(ws_api_flows_filters_test_graph('manual'));
	$live = json_encode(ws_api_flows_filters_test_graph('join'));
	$flow = array('draft' => $draft);

	pg_assert_same('join', ws_flow_start_kind(ws_flow_start_graph($flow, array('graph' => $live))));
	pg_assert_same('manual', ws_flow_start_kind(ws_flow_start_graph($flow, null)));
}

// Each kind of start in words.
function test_workspace_api_flows_filters_start_text()
{
	$viewer = array('id' => 5, 'member' => true, 'role' => 3);
	$none = function () {
		return false;
	};

	pg_assert_same(lang('By hand'), ws_flow_start_text($viewer, ws_api_flows_filters_test_graph('manual'), $none));

	$weekly = ws_flow_start_text($viewer, ws_api_flows_filters_test_graph('schedule', array('date' => '2030-01-07', 'time' => '09:00', 'repeat' => 'weekly', 'days' => array(1))), $none);
	pg_assert_same(lang(array('string' => 'Every week on {var:1} at {var:2}, from {var:3}', 'vars' => array(lang('Monday'), '09:00', '07.01.2030'))), $weekly);

	$once = ws_flow_start_text($viewer, ws_api_flows_filters_test_graph('schedule', array('date' => '2030-01-07', 'time' => '09:00')), $none);
	pg_assert_same(lang(array('string' => 'On {var:1} at {var:2}', 'vars' => array('07.01.2030', '09:00'))), $once);

	// The event catalogue reads the tables: the rule it is said from.
	$event = ws_flow_start_rule(ws_api_flows_filters_test_graph('event', array('event' => 'form.submitted', 'page_id' => 31)), $none);
	pg_assert_same(lang('When something happens'), $event['text']);
	pg_assert_same(array('type' => 'event', 'event' => 'form.submitted', 'page_id' => 31, 'status' => '', 'channel_id' => 0), $event['rule']);

	pg_assert_same(lang('When somebody joins a public channel'), ws_flow_start_text($viewer, ws_api_flows_filters_test_graph('join'), $none));
	pg_assert_same(null, ws_flow_start_text($viewer, ws_flow_graph_empty(), $none));
}

// A time box not filled in yet says only that it starts at a time.
function test_workspace_api_flows_filters_start_text_unfinished_time()
{
	$viewer = array('id' => 5, 'member' => true, 'role' => 3);

	pg_assert_same(lang('At a time'), ws_flow_start_text($viewer, ws_api_flows_filters_test_graph('schedule'), function () {
		return true;
	}));
}

// A channel the person does not read is not named: the join box says only
// that somebody joins a channel, the event box leaves the channel out.
function test_workspace_api_flows_filters_start_text_hides_unread_channel()
{
	$viewer = array('id' => 5, 'member' => true, 'role' => 3);
	$asked = array();
	$none = function ($channel_id) use (&$asked) {
		$asked[] = $channel_id;

		return false;
	};

	pg_assert_same(lang('When somebody joins a channel'), ws_flow_start_text($viewer, ws_api_flows_filters_test_graph('join', array('channel_id' => 7)), $none));

	$event = ws_flow_start_rule(ws_api_flows_filters_test_graph('event', array('event' => 'workspace.task.completed', 'channel_id' => 9)), $none);
	pg_assert_same(0, $event['rule']['channel_id']);
	pg_assert_same(array(7, 9), $asked);

	$seen = ws_flow_start_rule(ws_api_flows_filters_test_graph('event', array('event' => 'workspace.task.completed', 'channel_id' => 9)), function () {
		return true;
	});
	pg_assert_same(9, $seen['rule']['channel_id']);
}

// The listing carries no start; the flow read alone does.
function test_workspace_api_flows_filters_schema_fields()
{
	$schema = ws_api_flow_schema();

	pg_assert_same('string?', $schema['start']);
	pg_assert_same('string?', $schema['start_text']);

	$row = array('id' => 3, 'name' => 'Onboarding', 'status' => 'draft', 'access' => 'edit', 'owner_id' => 5, 'channel_id' => 0,
		'draft_rev' => 1, 'draft' => json_encode(ws_api_flows_filters_test_graph('manual')), 'created_at' => 0, 'updated_at' => 0);
	$listed = ws_api_flow_present($row, null, false);

	pg_assert_true(array_key_exists('start', $listed) && ($listed['start'] === null));
	pg_assert_true(array_key_exists('start_text', $listed) && ($listed['start_text'] === null));
	pg_assert_same(array_keys($schema), array_keys($listed));
}
