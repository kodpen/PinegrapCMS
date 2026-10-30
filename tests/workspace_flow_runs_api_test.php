<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a run about a customer over the external API
 * (POST /workspace/flows/{id}/runs with contact_id, GET of a run): the
 * route's field, the server path it goes through (the contact check, the
 * open run answered or refused, the run's subject), its dry run, the
 * refusal codes, the contact a run read and a run's webhook body name, and
 * a start without contact_id left as it was.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_run_events.php');

// A route of the workspace's API, by id.
function ws_flow_runs_api_test_route($id)
{
	foreach (ws_api_routes() as $route) {
		if ($route['id'] === $id) {
			return $route;
		}
	}

	return null;
}

// The body of a function, comments left out.
function ws_flow_runs_api_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// The start route takes an optional contact_id in the body; it stays no MCP
// tool, as every run write.
function test_workspace_flow_runs_api_contact_field()
{
	$route = ws_flow_runs_api_test_route('workspace.flow_runs.start');

	pg_assert_true(is_array($route), 'the start route');

	$params = array();

	foreach ($route['params'] as $param) {
		$params[$param['name']] = $param;
	}

	pg_assert_true(isset($params['contact_id']), 'contact_id is a field');
	pg_assert_same('body', $params['contact_id']['in']);
	pg_assert_same('int', $params['contact_id']['type']);
	pg_assert_same(1, $params['contact_id']['min']);
	pg_assert_true(empty($params['contact_id']['required']), 'optional');
	pg_assert_true(($params['contact_id']['description'] ?? '') !== '', 'described');
	pg_assert_same(false, $route['mcp'], 'still no MCP tool');
	pg_assert_same(true, $route['dry_run']);
}

// The route hands contact_id to the panel's start check, so the 422 of a
// contact that is missing or not readable, the open run answered (200) or
// refused (409) and the run's subject are the panel's; without it nothing
// is added to what the check gets.
function test_workspace_flow_runs_api_contact_server_path()
{
	$start = ws_flow_runs_api_test_source('ws_api_flow_runs_start');

	pg_assert_contains("array_key_exists('contact_id', \$params)", $start, 'only when sent');
	pg_assert_contains("\$data['contact_id'] = (int) \$params['contact_id'];", $start);
	pg_assert_contains('ws_flow_run_start_check($viewer, $flow, $data)', $start, 'the dry run meets the same refusals');
	pg_assert_contains('ws_flow_run_start($viewer, $flow, $data)', $start, 'the run gets its subject');
	pg_assert_true(strpos($start, 'ws_flow_run_start_check(') < strpos($start, 'api_dry_run_stop('), 'checked before the dry run stops');

	// The dry run names the customer of a run it would start.
	pg_assert_contains("'contact_id'", substr($start, strpos($start, 'api_dry_run_stop(')));

	// The panel's check: 422 contact_id, the open run before the limits.
	$check = ws_flow_runs_api_test_source('ws_flow_run_contact_check');

	pg_assert_contains("422, 'contact_id'", $check);
	pg_assert_contains("409, 'contact_id'", ws_flow_runs_api_test_source('ws_flow_run_start_check'));
}

// A 409 about the customer's open run is its own code, not the
// changed_meanwhile of a draft saved in two places.
function test_workspace_flow_runs_api_open_run_code()
{
	pg_assert_same('run_open', ws_api_flow_refusal_code(409, 'contact_id'));
	pg_assert_same('changed_meanwhile', ws_api_flow_refusal_code(409, 'draft_rev'));
	pg_assert_same('changed_meanwhile', ws_api_flow_refusal_code(409, ''));
	pg_assert_same('forbidden', ws_api_flow_refusal_code(403, 'channel_id'));
	pg_assert_same('validation_failed', ws_api_flow_refusal_code(422, 'contact_id'));
	pg_assert_contains('ws_api_flow_refusal_code(', ws_flow_runs_api_test_source('ws_api_flow_refused'));
}

// A run read names the customer it is about, null for none.
function test_workspace_flow_runs_api_read_names_the_contact()
{
	pg_assert_same(5, ws_api_flow_run_contact_id(array('subject_type' => 'contact', 'subject_id' => 5)));
	pg_assert_same(null, ws_api_flow_run_contact_id(array('subject_type' => '', 'subject_id' => 0)), 'about nobody');
	pg_assert_same(null, ws_api_flow_run_contact_id(array('subject_type' => 'order', 'subject_id' => 5)), 'another kind of subject');
	pg_assert_same(null, ws_api_flow_run_contact_id(array('subject_type' => 'contact', 'subject_id' => 0)));
	pg_assert_same(null, ws_api_flow_run_contact_id(array()), 'a row before the columns');

	pg_assert_contains('ws_api_flow_run_contact_id($run)', ws_flow_runs_api_test_source('ws_api_flow_run_present'));
	pg_assert_same('integer?', ws_api_flow_run_schema()['contact_id']);
}

// A run's webhook body names the customer as the run read does.
function test_workspace_flow_runs_api_event_names_the_contact()
{
	$run = array('id' => 9, 'flow_id' => 3, 'version_id' => 21, 'status' => 'running', 'started_by' => 5, 'actor_id' => 2, 'boxes_run' => 1,
		'started_at' => 1767225600, 'updated_at' => 1767225600, 'finished_at' => 0, 'context' => '{}', 'subject_type' => 'contact', 'subject_id' => 44);

	pg_assert_same(44, ws_flow_run_event_run($run, 1)['contact_id']);
	pg_assert_same(ws_api_flow_run_contact_id($run), ws_flow_run_event_run($run, 1)['contact_id']);
	pg_assert_same(null, ws_flow_run_event_run(array('subject_type' => '', 'subject_id' => 0) + $run, 1)['contact_id'], 'about nobody');
}
