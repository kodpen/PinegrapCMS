<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for saved replies (includes/workspace/saved_replies.php and
 * api_saved_replies.php) that need no database: how a shortcut is kept, the
 * field checks, which reply holds a shortcut, the answer's shape, the routes,
 * which are MCP tools and that every write checks before the rehearsal stops.
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

function ws_saved_replies_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return preg_replace('~^\s*//.*$~m', '', implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1)));
}

function ws_saved_replies_test_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		if (strpos($route['id'], 'workspace.saved_replies.') === 0) {
			$routes[$route['id']] = $route;
		}
	}

	return $routes;
}

function test_ws_saved_reply_shortcut()
{
	pg_assert_same('thanks', ws_saved_reply_shortcut(';Thanks '), 'the ; and spaces go, lower case');
	pg_assert_same('ıade', ws_saved_reply_shortcut('IADE'), 'Turkish lower case');
	pg_assert_same('kargo-2_gün', ws_saved_reply_shortcut('kargo-2_gün'));
	pg_assert_same('', ws_saved_reply_shortcut('two words'), 'one word only');
	pg_assert_same('', ws_saved_reply_shortcut(';'), 'empty');
	pg_assert_same('', ws_saved_reply_shortcut('a.b'));
	pg_assert_same('', ws_saved_reply_shortcut(str_repeat('a', 33)), 'longer than the column');
	pg_assert_same(str_repeat('ş', 32), ws_saved_reply_shortcut(str_repeat('ş', 32)), '32 letters, counted as characters');
	pg_assert_same('', ws_saved_reply_shortcut(12), 'not a string');
}

function test_ws_saved_reply_input_check_new()
{
	$ok = ws_saved_reply_input_check(array('shortcut' => ';Hi', 'title' => "  Hello\nthere ", 'body' => "Dear {customer},\r\nthanks.\n\n"));
	pg_assert_true($ok['ok']);
	pg_assert_same(array('shortcut' => 'hi', 'title' => 'Hello there', 'body' => "Dear {customer},\nthanks."), $ok['sets'], 'kept as typed, placeholders included');

	$untitled = ws_saved_reply_input_check(array('shortcut' => 'hi', 'body' => 'x'));
	pg_assert_same('hi', $untitled['sets']['title'], 'no title: the shortcut');

	$refusals = array(
		'shortcut_invalid' => array('body' => 'x'),
		'body_empty'       => array('shortcut' => 'hi', 'body' => " \n "),
		'title_too_long'   => array('shortcut' => 'hi', 'title' => str_repeat('a', 101), 'body' => 'x'),
		'body_too_long'    => array('shortcut' => 'hi', 'body' => str_repeat('a', ws_saved_reply_body_max() + 1)),
	);

	foreach ($refusals as $error => $input) {
		$checked = ws_saved_reply_input_check($input);
		pg_assert_same(array(false, 422, $error), array($checked['ok'], $checked['status'], $checked['error']), $error);
	}

	pg_assert_same(WS_MESSAGE_MAX, ws_saved_reply_body_max(), 'a reply fits a message');
}

function test_ws_saved_reply_input_check_change()
{
	$only_body = ws_saved_reply_input_check(array('body' => 'new'), true);
	pg_assert_same(array('body' => 'new'), $only_body['sets'], 'only the fields sent');

	$cleared = ws_saved_reply_input_check(array('title' => ''), true);
	pg_assert_same(array('title' => ''), $cleared['sets'], 'the save check gives a cleared title its shortcut');

	$nothing = ws_saved_reply_input_check(array(), true);
	pg_assert_same('nothing_to_change', $nothing['error']);

	$bad = ws_saved_reply_input_check(array('shortcut' => 'a b'), true);
	pg_assert_same('shortcut', $bad['field']);
}

function test_ws_saved_reply_holder()
{
	$rows = array(
		array('id' => '3', 'shortcut' => 'hi'),
		array('id' => '5', 'shortcut' => 'iade'),
	);

	pg_assert_same('3', ws_saved_reply_holder($rows, 'hi')['id']);
	pg_assert_same(null, ws_saved_reply_holder($rows, 'hi', 3), 'the reply being changed keeps its own shortcut');
	pg_assert_same(null, ws_saved_reply_holder($rows, 'bye'));
}

function test_ws_saved_reply_present()
{
	$out = ws_saved_reply_present(array(
		'id' => '7', 'user_id' => '2', 'scope' => 'personal', 'shortcut' => 'hi', 'title' => 'Hello',
		'body' => 'Order {order}', 'created_at' => '0', 'updated_at' => '86400',
	));

	pg_assert_same(array(7, 'hi', 'Hello', 'Order {order}', null, '1970-01-02T00:00:00Z'),
		array($out['id'], $out['shortcut'], $out['title'], $out['body'], $out['created_at'], $out['updated_at']));
	pg_assert_same(array_keys(ws_api_saved_reply_schema()), array_keys($out), 'the schema names every field');
	pg_assert_false(isset($out['user_id']) || isset($out['scope']), 'whose and which scope stay inside');
}

function test_ws_saved_replies_routes()
{
	$routes = ws_saved_replies_test_routes();
	$expected = array(
		'workspace.saved_replies.list'        => array('GET', '/workspace/saved-replies', 'workspace:read'),
		'workspace.saved_replies.get'         => array('GET', '/workspace/saved-replies/{id}', 'workspace:read'),
		'workspace.saved_replies.create'      => array('POST', '/workspace/saved-replies', 'workspace:write'),
		'workspace.saved_replies.update'      => array('POST', '/workspace/saved-replies/{id}', 'workspace:write'),
		'workspace.saved_replies.delete'      => array('DELETE', '/workspace/saved-replies/{id}', 'workspace:write'),
		'workspace.saved_replies.delete_post' => array('POST', '/workspace/saved-replies/{id}/delete', 'workspace:write'),
	);

	pg_assert_same(array_keys($expected), array_keys($routes), 'the routes, in order');

	foreach ($expected as $id => $shape) {
		if (isset($routes[$id])) {
			pg_assert_same($shape, array($routes[$id]['method'], $routes[$id]['path'], $routes[$id]['scope']), $id);
			pg_assert_true(function_exists($routes[$id]['handler']), $id . ' reaches a handler');
			pg_assert_same($routes[$id]['method'] !== 'GET', !empty($routes[$id]['dry_run']), $id . ': a write rehearses');
		}
	}

	pg_assert_same(array('PATCH'), $routes['workspace.saved_replies.update']['also_accepts'] ?? null);
	pg_assert_same($routes['workspace.saved_replies.delete']['handler'], $routes['workspace.saved_replies.delete_post']['handler']);

	foreach ($routes as $id => $route) {
		if (strpos($id, '.delete') !== false) {
			pg_assert_false(pg_mcp_route_is_offered($route), $id . ' is no tool: it cannot be undone');
		} else {
			pg_assert_true(pg_mcp_route_is_offered($route), $id . ' is a tool');
		}
	}

	// The text limits fit their columns (shortcut one more for its ;).
	$create = array();

	foreach ($routes['workspace.saved_replies.create']['params'] as $param) {
		$create[$param['name']] = $param['max_length'];
	}

	pg_assert_same(array('shortcut' => 33, 'title' => 100, 'body' => WS_MESSAGE_MAX), $create);
}

// Every write runs the store's check, then the rehearsal stops it, then the
// write; the rows read are always the owner's.
function test_ws_saved_replies_writes_check_before_the_rehearsal()
{
	foreach (array('ws_api_saved_replies_create', 'ws_api_saved_replies_update') as $handler) {
		$source = ws_saved_replies_test_source($handler);
		$check = strpos($source, 'ws_saved_reply_save_check(');
		$stop = strpos($source, 'api_dry_run_stop(');
		$write = strpos($source, 'ws_saved_reply_save($viewer');

		pg_assert_true(($check !== false) && ($check < $stop) && ($stop < $write), $handler . ': check, rehearsal, write');
	}

	$delete = ws_saved_replies_test_source('ws_api_saved_replies_delete');
	pg_assert_true(strpos($delete, 'api_dry_run_stop(') < strpos($delete, 'ws_saved_reply_delete('), 'delete: rehearsal before the write');

	foreach (array('ws_saved_replies_rows', 'ws_saved_reply_find') as $name) {
		pg_assert_contains('user_id = ? AND scope = ?', ws_saved_replies_test_source($name), $name . ' reads the person\'s own personal rows');
	}

	pg_assert_contains('(int) $viewer[\'id\']', ws_saved_replies_test_source('ws_api_saved_reply_or_404'));
}

// A new table is listed for a reinstall, and the rows go with their user.
function test_ws_saved_replies_table_wiring()
{
	pg_assert_contains("'ws_saved_replies',", file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php'));

	foreach (array('delete_users.php', 'edit_user.php') as $file) {
		pg_assert_contains('DELETE FROM ws_saved_replies WHERE user_id', file_get_contents(PG_FUNCTIONS_DIR . '/' . $file), $file);
	}

	$migration = file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	pg_assert_contains('upgrade_2026_11_0_workspace_saved_replies(); // 11.0.114', $migration);
	pg_assert_contains('UNIQUE KEY uq_shortcut (user_id, scope, shortcut)', $migration);
}

// {customer} and {order} filled from the channel's customer; one with no
// value stays as typed, so the writer sees what is left to fill.
function test_ws_saved_reply_fill()
{
	$values = array('customer' => 'Ayşe Yılmaz', 'order' => '1045');

	pg_assert_same('Dear Ayşe Yılmaz, order 1045 left.', ws_saved_reply_fill('Dear {customer}, order {order} left.', $values));
	pg_assert_same('Ayşe Yılmaz / Ayşe Yılmaz', ws_saved_reply_fill('{Customer} / {CUSTOMER}', $values), 'any case');
	pg_assert_same('Order {order}', ws_saved_reply_fill('Order {order}', array('customer' => 'A')), 'no order: kept');
	pg_assert_same('Dear {customer}', ws_saved_reply_fill('Dear {customer}', array()), 'a channel about nobody');
	pg_assert_same('Dear {customer}', ws_saved_reply_fill('Dear {customer}', array('customer' => '  ')), 'an empty value is no value');
	pg_assert_same('{name} {order', ws_saved_reply_fill('{name} {order', $values), 'other braces stay');
	pg_assert_same('a $1 \\1', ws_saved_reply_fill('a {customer}', array('customer' => '$1 \\1')), 'a value is never a pattern');
}
