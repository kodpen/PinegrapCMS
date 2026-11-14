<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a person's own plans in the external API and MCP
 * (includes/workspace/api_personal_plans.php) that need no database: the
 * routes and their scopes, which are MCP tools, that every handler asks for
 * the person before it reads, the scope row and the owner ceiling, the span
 * of a list and how a change is laid over the plan.
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

function ws_personal_plans_api_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return preg_replace('~^\s*//.*$~m', '', implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1)));
}

function ws_personal_plans_api_test_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		if (strpos($route['id'], 'workspace.personal_plans.') === 0) {
			$routes[$route['id']] = $route;
		}
	}

	return $routes;
}

function test_ws_personal_plans_api_routes()
{
	$routes = ws_personal_plans_api_test_routes();

	pg_assert_same(array(
		'workspace.personal_plans.list',
		'workspace.personal_plans.get',
		'workspace.personal_plans.create',
		'workspace.personal_plans.update',
		'workspace.personal_plans.delete',
		'workspace.personal_plans.delete_post',
	), array_keys($routes));

	foreach ($routes as $id => $route) {
		$read = ($route['method'] === 'GET');

		pg_assert_same('workspace:personal_plans', $route['scope'], $id . ' asks the plans scope');
		pg_assert_same(0, strpos($route['path'], '/workspace/personal-plans'), $id . ' path');

		if (strpos($id, '.delete') !== false) {
			pg_assert_false(pg_mcp_route_is_offered($route), $id . ' is no tool: it cannot be undone');
		} else {
			pg_assert_true(pg_mcp_route_is_offered($route), $id . ' is a tool');
		}

		if (!$read) {
			pg_assert_true(!empty($route['dry_run']), $id . ' can be rehearsed');
		}
	}

	pg_assert_same(array('PATCH'), $routes['workspace.personal_plans.update']['also_accepts']);
}

function test_ws_personal_plans_api_handlers_ask_for_the_person_first()
{
	foreach (ws_personal_plans_api_test_routes() as $id => $route) {
		$source = ws_personal_plans_api_test_source($route['handler']);
		$caller = strpos($source, 'ws_api_personal_plans_caller()');

		pg_assert_true($caller !== false, $id . ' asks for the person');

		foreach (array('ws_personal_plan(', 'ws_personal_plans_between(', 'ws_api_personal_plan_or_404(', 'api_dry_run_stop(') as $call) {
			$at = strpos($source, $call);

			if ($at !== false) {
				pg_assert_true($at > $caller, $id . ': ' . $call . ' comes after the person check');
			}
		}
	}

	$gate = ws_personal_plans_api_test_source('ws_api_personal_plans_caller');

	pg_assert_true(strpos($gate, 'ws_api_person_only(') < strpos($gate, 'ws_api_viewer('), 'an application key is refused before anything is read');

	// Only the caller's own plans: a shared one is neither listed nor reached.
	pg_assert_contains('ws_personal_plan_can_edit(', ws_personal_plans_api_test_source('ws_api_personal_plans_list'));
	pg_assert_contains('ws_personal_plan_can_edit(', ws_personal_plans_api_test_source('ws_api_personal_plan_or_404'));

	// Every write is checked before the rehearsal stops.
	foreach (array('ws_api_personal_plans_create', 'ws_api_personal_plans_update') as $name) {
		$source = ws_personal_plans_api_test_source($name);

		pg_assert_true(strpos($source, 'ws_api_personal_plan_check(') < strpos($source, 'api_dry_run_stop('), $name . ' checks before the rehearsal stops');
	}
}

function test_ws_personal_plans_api_names_no_table()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_personal_plans.php');

	pg_assert_false((bool) preg_match('~\b(SELECT|INSERT|UPDATE|DELETE)\b[^;]*\bws_personal_plan~i', $source), 'every read goes through personal_plans.php');
	pg_assert_false(strpos($source, 'pg_announce(') !== false, 'no webhook names a plan');
	pg_assert_false(strpos($source, 'log_activity(') !== false, 'no Site Log line names a plan');
}

function test_ws_personal_plans_api_scopes()
{
	$groups = ws_scope_groups();

	pg_assert_same('', $groups['personal_plans']['read'], 'one scope, no read half an assistant key would take');
	pg_assert_same('workspace:personal_plans', $groups['personal_plans']['write']);

	$source = ws_personal_plans_api_test_source('ws_owner_scopes');

	pg_assert_contains("'workspace:personal_plans'", $source, 'a team member may hand it on');

	pg_assert_false(in_array('workspace:personal_plans', api_assistant_scopes($groups, api_assistant_scopes_left_out()), true), 'no assistant key and no "All" on the consent screen holds it');
	pg_assert_false(in_array('workspace:personal_plans', api_plugin_scopes($groups), true), 'no plugin default holds it');
	pg_assert_same(array_keys(ws_api_personal_plan_schema()), array_keys(array(
		'id' => 1, 'title' => '', 'note' => '', 'start' => '', 'end' => '', 'start_time' => '', 'end_time' => '',
		'all_day' => false, 'remind' => '', 'visibility' => '', 'owner_id' => 0, 'mine' => true, 'people' => array(), 'owner_name' => '',
		'time_day' => '', 'repeat' => '', 'repeat_every' => 1, 'repeat_end' => '', 'repeat_until' => '', 'repeat_count' => '',
	)), 'the schema names what ws_personal_plan_for_reader() gives');
}

function test_ws_personal_plans_api_span()
{
	$now = strtotime('2026-11-14 10:00:00');

	pg_assert_same(array(strtotime('2026-11-14 00:00:00'), strtotime('2026-12-15 00:00:00')), ws_api_personal_plans_span(null, null, $now), 'today and 30 days on');
	pg_assert_same(array(strtotime('2026-11-01 00:00:00'), strtotime('2026-11-02 00:00:00')), ws_api_personal_plans_span('2026-11-01', '2026-11-01', $now), 'one day');
	pg_assert_same('from', ws_api_personal_plans_span('2026-13-01', null, $now)['field'], 'no such month');
	pg_assert_same('to', ws_api_personal_plans_span('2026-11-01', '01.11.2026', $now)['field'], 'not YYYY-MM-DD');
	pg_assert_same('to', ws_api_personal_plans_span('2026-11-02', '2026-11-01', $now)['field'], 'the end before the start');
	pg_assert_true(!isset(ws_api_personal_plans_span('2026-01-01', '2026-12-31', $now)['error']), 'a year is fine');
	pg_assert_same('to', ws_api_personal_plans_span('2026-01-01', '2027-01-02', $now)['field'], 'more than a year');
}

function test_ws_personal_plans_api_merge()
{
	$current = array(
		'id' => 7, 'title' => 'Dentist', 'note' => 'Bring the card', 'start' => '2026-11-20', 'end' => '2026-11-20',
		'start_time' => '09:30', 'end_time' => '10:30', 'all_day' => false, 'remind' => '60',
		'visibility' => 'people', 'owner_id' => 3, 'mine' => true, 'people' => array(5, 6), 'owner_name' => '',
	);

	$data = ws_api_personal_plan_merge($current, array('title' => 'Dentist (moved)', 'start_time' => '11:00', 'end_time' => null, 'id' => 7));

	pg_assert_same('Dentist (moved)', $data['title'], 'a sent field replaces');
	pg_assert_same('11:00', $data['start_time']);
	pg_assert_same('10:30', $data['end_time'], 'a field not sent stays');
	pg_assert_same('60', $data['remind'], 'the reminder stays');
	pg_assert_same(array(5, 6), $data['people'], 'the people stay');
	pg_assert_same('', $data['all_day'], 'not all day');
	pg_assert_false(isset($data['id']) || isset($data['owner_id']) || isset($data['mine']), 'only the plan\'s fields');

	$data = ws_api_personal_plan_merge($current, array('all_day' => true, 'remind' => '', 'people' => array('8')));

	pg_assert_same('1', $data['all_day']);
	pg_assert_same('', $data['remind'], 'the reminder can be taken off');
	pg_assert_same(array(8), $data['people'], 'people replaces the list');

	$clean = ws_personal_plan_clean(ws_api_personal_plan_merge(null, array('title' => 'Call the bank', 'start' => '2026-11-20', 'start_time' => '14:00')));

	pg_assert_same('me', $clean['visibility'], 'a new plan is the person\'s alone');
	pg_assert_same(strtotime('2026-11-20 15:00:00'), $clean['ends_at'], 'one hour when no end');
	pg_assert_true(isset(ws_personal_plan_clean(ws_api_personal_plan_merge(null, array('start' => '2026-11-20')))['error']), 'a title is needed');
}
