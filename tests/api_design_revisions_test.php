<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the revision and schedule endpoints of the page design API
 * (includes/api/resources/design.php): the objects they answer with, their
 * declarations, the routes and the MCP tools made from them.
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

if (!defined('PG_MCP_ENTRY')) {
	define('PG_MCP_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/design.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');

// The presenter's time helper lives in includes/api/response.php, which loads
// only behind the API's own entry. A stand-in of the same shape.
if (!function_exists('api_time')) {
	function api_time($unix)
	{
		return ((int) $unix > 0) ? gmdate('Y-m-d\\TH:i:s\\Z', (int) $unix) : null;
	}
}

// The route of an id, as api_schema() declares it.
function api_design_revisions_test_route($id)
{
	foreach (api_schema() as $route) {
		if (isset($route['id']) && $route['id'] === $id) {
			return $route;
		}
	}

	return null;
}

// The names of a route's parameters, keyed by name.
function api_design_revisions_test_params($route)
{
	$out = array();

	foreach ($route['params'] as $param) {
		$out[$param['name']] = $param;
	}

	return $out;
}

// A revision row as pg_page_revisions_list() returns it.
function test_api_design_revision_present()
{
	$row = array(
		'revision_no'    => 7,
		'source'         => 'restore',
		'created_at'     => 1792400377,
		'user_id'        => 3,
		'user_username'  => 'ayse',
		'tree_code_hash' => str_repeat('a', 40),
		'settings'       => array('page_name' => 'kampanya', 'page_title' => 'Ekim kampanyası', 'page_folder' => '12'),
		'size'           => 5120,
	);

	$out = api_design_revision_present($row);

	pg_assert_same(array(
		'revision_no'     => 7,
		'source'          => 'restore',
		'name'            => 'kampanya',
		'title'           => 'Ekim kampanyası',
		'user_id'         => 3,
		'user'            => 'ayse',
		'size'            => 5120,
		'created_at'      => '2026-10-19T08:59:37Z',
		'created_at_unix' => 1792400377,
	), $out);

	pg_assert_same(array_keys(api_design_revision_schema()), array_keys($out), 'every field declared, in order');
}

// A revision written by the job or the importer has no person, and one from
// before a settings column existed has no name or title for it.
function test_api_design_revision_present_without_person_or_settings()
{
	$out = api_design_revision_present(array(
		'revision_no' => 1, 'source' => 'import', 'created_at' => 1792400377,
		'user_id' => 0, 'user_username' => '', 'settings' => array(), 'size' => 40,
	));

	pg_assert_same(null, $out['user_id']);
	pg_assert_same(null, $out['user']);
	pg_assert_same(null, $out['name']);
	pg_assert_same(null, $out['title']);
}

// The page object carries the two times, null for none; the restore answer
// wraps it with the revisions it was put back from and recorded as.
function test_api_design_page_times_and_restore_present()
{
	$schema = api_design_page_schema();

	pg_assert_same('string?', $schema['publish_at']);
	pg_assert_same('string?', $schema['unpublish_at']);

	$page    = array('id' => 41);
	$restore = api_design_restore_present($page, 5, 0, false, array('a'));

	pg_assert_same($page, $restore['page']);
	pg_assert_same(5, $restore['restored_from']);
	pg_assert_same(null, $restore['revision_no'], 'nothing recorded when nothing changed');
	pg_assert_same(false, $restore['changed']);
	pg_assert_same(array('a'), $restore['warnings']);
	pg_assert_same(array_keys(api_design_restore_schema()), array_keys($restore), 'every restore field declared, in order');
	pg_assert_same('DesignPage', api_design_restore_schema()['page']);

	pg_assert_same(8, api_design_restore_present($page, 5, 8, true, array())['revision_no']);
}

// The view of a page says whether it is a draft and when it is scheduled.
function test_api_design_view_declares_the_times()
{
	$schema = api_design_view_schema();

	pg_assert_same('boolean', $schema['draft']);
	pg_assert_same('string?', $schema['publish_at']);
	pg_assert_same('string?', $schema['unpublish_at']);
}

// The routes: a read of the revisions, a restore and a schedule write that
// rehearse, under the design scope, POST for IIS.
function test_api_design_revision_and_schedule_routes()
{
	$list = api_design_revisions_test_route('design.revisions.list');

	pg_assert_true(is_array($list), 'revision list declared');
	pg_assert_same(array('GET', '/design/pages/{id}/revisions', 'design:read'), array($list['method'], $list['path'], $list['scope']));
	pg_assert_same(array('list' => 'DesignRevision'), $list['returns']);

	$restore = api_design_revisions_test_route('design.revisions.restore');

	pg_assert_true(is_array($restore), 'restore declared');
	pg_assert_same(array('POST', '/design/pages/{id}/revisions/{no}/restore', 'design:write'), array($restore['method'], $restore['path'], $restore['scope']));
	pg_assert_same(true, $restore['dry_run']);
	pg_assert_same('DesignRestore', $restore['returns']);

	$update = api_design_revisions_test_route('design.pages.update');

	pg_assert_true(is_array($update), 'schedule write declared');
	pg_assert_same(array('POST', '/design/pages/{id}', 'design:write'), array($update['method'], $update['path'], $update['scope']));
	pg_assert_same(array('PATCH'), $update['also_accepts']);
	pg_assert_same(true, $update['dry_run']);

	foreach (array($update, api_design_revisions_test_route('design.pages.create')) as $route) {
		$params = api_design_revisions_test_params($route);

		foreach (array('publish_at', 'unpublish_at') as $name) {
			pg_assert_true(isset($params[$name]), $route['id'] . ' takes ' . $name);
			pg_assert_same(array('body', 'string', true), array($params[$name]['in'], $params[$name]['type'], $params[$name]['nullable']), $route['id'] . ' ' . $name);
		}
	}

	$objects = api_openapi_objects();

	pg_assert_same('api_design_revision_schema', $objects['DesignRevision']);
	pg_assert_same('api_design_restore_schema', $objects['DesignRestore']);
}

// The MCP server offers the three as tools, the writes with a rehearsal.
function test_api_design_revision_routes_are_tools()
{
	foreach (array('design.revisions.list', 'design.revisions.restore', 'design.pages.update') as $id) {
		$route = api_design_revisions_test_route($id);

		pg_assert_true(pg_mcp_route_is_offered($route), $id . ' is a tool');

		$tool = pg_mcp_tool_from_route($route);

		$properties = (array) $tool['inputSchema']['properties'];

		pg_assert_same($id !== 'design.revisions.list', isset($properties['dry_run']), $id . ' rehearses');
	}
}
