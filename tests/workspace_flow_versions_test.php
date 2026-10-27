<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a flow's published versions and a paused flow resumed
 * (includes/workspace/flow_versions.php): which flows may be resumed, and,
 * read from the source, the resume's conditional write and its publish
 * checks on the version, the restore as a draft save, and the API routes:
 * resuming is the person's own session and no MCP tool, the versions are
 * read and brought back as tools, and every write rehearses before it
 * writes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_versions.php');

// The source of a function, comments taken out.
function ws_flow_versions_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// The routes by id.
function ws_flow_versions_test_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	return $routes;
}

// Only a paused flow with a live version goes back to live; a draft, a live
// or a deleted flow, and a paused one never published, are refused.
function test_workspace_flow_resume_refusal()
{
	pg_assert_same('', ws_flow_resume_refusal('paused', 4));
	pg_assert_same('no_version', ws_flow_resume_refusal('paused', 0));

	foreach (array('draft', 'live', 'binned', '') as $status) {
		pg_assert_same('not_paused', ws_flow_resume_refusal($status, 4), $status);
	}
}

// The resume checks the version as a publish does, as the owner alone, and
// writes only a flow still paused on that version: a second resume changes
// no row and is refused before the anchor is written again.
function test_workspace_flow_resume_writes_once()
{
	$check = ws_flow_versions_test_source('ws_flow_resume_check');

	pg_assert_contains('ws_flow_share_manager($viewer, $flow)', $check);
	pg_assert_contains("ws_flow_publish_check(\$viewer, array('draft' => (string) \$version['graph']) + \$flow)", $check);

	$resume = ws_flow_versions_test_source('ws_flow_resume');

	pg_assert_contains("AND status = 'paused' AND version_id = ", $resume);
	pg_assert_true(strpos($resume, 'mysqli_affected_rows') < strpos($resume, 'ws_flow_anchor_sync('), 'the anchor follows the counted write');
	pg_assert_false(strpos($resume, 'draft') !== false, 'the draft is not touched');
}

// A version brought back is a draft save with the version's graph, so it
// meets the save's access, draft_rev and size checks.
function test_workspace_flow_version_restore_is_a_draft_save()
{
	$check = ws_flow_versions_test_source('ws_flow_version_restore_check');

	pg_assert_contains('ws_flow_version_numbered($flow[\'id\'], $n)', $check);
	pg_assert_contains('ws_flow_save_check($viewer, $save, $flow)', $check);
	pg_assert_contains('ws_flow_save($viewer, $check[\'data\'], $flow)', ws_flow_versions_test_source('ws_flow_version_restore'));
}

// Resuming opens tasks in the person's name again: their own session, no
// MCP tool. The versions are read and brought back into the draft as tools,
// by whoever may change the flow.
function test_workspace_flow_versions_routes()
{
	$routes = ws_flow_versions_test_routes();

	foreach (array('workspace.flows.resume', 'workspace.flow_versions.list', 'workspace.flow_versions.get', 'workspace.flow_versions.restore') as $id) {
		pg_assert_true(isset($routes[$id]), $id . ' is a route');
	}

	pg_assert_false(pg_mcp_route_is_offered($routes['workspace.flows.resume']), 'resuming is no MCP tool');
	pg_assert_contains('ws_api_own_words_only(', ws_flow_versions_test_source($routes['workspace.flows.resume']['handler']));

	foreach (array('workspace.flow_versions.list', 'workspace.flow_versions.get', 'workspace.flow_versions.restore') as $id) {
		pg_assert_true(pg_mcp_route_is_offered($routes[$id]), $id . ' is a tool');

		$source = ws_flow_versions_test_source($routes[$id]['handler']);

		pg_assert_contains('ws_api_flow_editable(', $source);
		pg_assert_false(strpos($source, 'ws_api_own_words_only(') !== false, $id . ' takes an application key');
	}

	pg_assert_true(!empty($routes['workspace.flows.resume']['dry_run']) && !empty($routes['workspace.flow_versions.restore']['dry_run']), 'both writes rehearse');
}

// A rehearsal meets the engine's own checks and stops before its write.
function test_workspace_flow_versions_rehearse_before_the_write()
{
	$order = array(
		'ws_api_flows_resume'          => array('ws_api_own_words_only(', 'ws_flow_resume_check(', 'api_dry_run_stop(', 'ws_flow_resume('),
		'ws_api_flow_versions_restore' => array('ws_flow_version_restore_check(', 'api_dry_run_stop(', 'ws_flow_version_restore('),
	);

	foreach ($order as $handler => $needles) {
		$source = ws_flow_versions_test_source($handler);
		$at = -1;

		foreach ($needles as $needle) {
			$found = strpos($source, $needle);

			pg_assert_true(($found !== false) && ($found > $at), $handler . ': ' . $needle . ' comes after what is before it');
			$at = ($found === false) ? $at : $found;
		}
	}
}

// One version as GET …/versions/{n} answers it: the listing's fields, whether
// it is live, and its graph.
function test_workspace_flow_version_detail_present()
{
	$version = array('id' => 9, 'flow_id' => 3, 'n' => 2, 'published_by' => 4, 'published_at' => 0, 'graph' => '{"v":1,"nodes":[],"edges":[]}');

	$live = ws_api_flow_version_detail_present($version, 9, true);
	$old = ws_api_flow_version_detail_present($version, 12, true);

	pg_assert_same(2, $live['n']);
	pg_assert_true($live['live']);
	pg_assert_false($old['live']);
	pg_assert_same(array_keys(ws_api_flow_version_detail_schema()), array_keys($live));
}
