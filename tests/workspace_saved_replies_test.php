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

	pg_assert_same(array(7, 'personal', 'hi', 'Hello', 'Order {order}', null, '1970-01-02T00:00:00Z'),
		array($out['id'], $out['scope'], $out['shortcut'], $out['title'], $out['body'], $out['created_at'], $out['updated_at']));
	pg_assert_same(array_keys(ws_api_saved_reply_schema()), array_keys($out), 'the schema names every field');
	pg_assert_false(isset($out['user_id']), 'whose stays inside');
	pg_assert_same('team', ws_saved_reply_present(array_merge(array('id' => 1, 'shortcut' => 'a', 'title' => 'a', 'body' => 'b', 'created_at' => 0, 'updated_at' => 0), array('scope' => 'team')))['scope']);
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
		if (isset($param['max_length'])) {
			$create[$param['name']] = $param['max_length'];
		}
	}

	pg_assert_same(array('shortcut' => 33, 'title' => 100, 'body' => WS_MESSAGE_MAX), $create);

	// The scope: chosen on a create and on the list, kept by the reply after.
	foreach (array('workspace.saved_replies.create' => 'body', 'workspace.saved_replies.list' => 'query') as $id => $in) {
		$scope = null;

		foreach ($routes[$id]['params'] as $param) {
			if ($param['name'] === 'scope') {
				$scope = $param;
			}
		}

		pg_assert_same(array('enum', $in, array('personal', 'team'), 'personal'), array($scope['type'] ?? null, $scope['in'] ?? null, $scope['values'] ?? null, $scope['default'] ?? null), $id . ' takes scope');
	}
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

	foreach (array('ws_saved_replies_rows', 'ws_saved_reply_find', 'ws_saved_reply_delete') as $name) {
		$source = ws_saved_replies_test_source($name);
		pg_assert_contains('user_id = ? AND scope = ?', $source, $name . ' names whose and which scope');
		pg_assert_contains('ws_saved_reply_owner_id(', $source, $name . ': a team row is user_id 0, a personal one the person\'s');
	}

	pg_assert_contains('(int) $viewer[\'id\']', ws_saved_replies_test_source('ws_api_saved_reply_or_404'));

	// A team delete without the right is refused before the rehearsal.
	pg_assert_true(strpos($delete, 'not_allowed') < strpos($delete, 'api_dry_run_stop('), 'delete: the right before the rehearsal');
	pg_assert_contains('ws_saved_reply_can_manage_team(', ws_saved_replies_test_source('ws_saved_reply_delete'), 'the store refuses a team delete without the right too');
}

// A team row has user_id 0; any other scope sent is personal.
function test_ws_saved_reply_scope_and_owner()
{
	pg_assert_same('team', ws_saved_reply_scope('team'));
	pg_assert_same('personal', ws_saved_reply_scope('Team'), 'only the exact word');
	pg_assert_same('personal', ws_saved_reply_scope(null));
	pg_assert_same('personal', ws_saved_reply_scope(array('team')));
	pg_assert_same(0, ws_saved_reply_owner_id(9, 'team'));
	pg_assert_same(9, ws_saved_reply_owner_id('9', 'personal'));
	pg_assert_same(WS_SAVED_REPLIES_MAX, ws_saved_reply_limit('personal'));
	pg_assert_same(WS_SAVED_REPLIES_TEAM_MAX, ws_saved_reply_limit('team'), 'the team has a limit of its own');
}

// Roles 0-2 hold the right by role, a role-3 member by the column; nobody
// without membership.
function test_ws_saved_reply_team_right()
{
	foreach (array(0, 1, 2) as $role) {
		pg_assert_true(ws_rights_team_replies($role, true, 0), 'role ' . $role . ' by role');
		pg_assert_false(ws_rights_team_replies($role, false, 1), 'role ' . $role . ' outside the team');
	}

	pg_assert_false(ws_rights_team_replies(3, true, 0), 'role 3 without the column');
	pg_assert_false(ws_rights_team_replies(3, true, null), 'role 3 before the upgrade');
	pg_assert_true(ws_rights_team_replies(3, true, '1'), 'role 3 with the column');
	pg_assert_false(ws_rights_team_replies(3, false, 1), 'the column without membership');

	pg_assert_true(ws_saved_reply_can_manage_team(array('member' => true, 'replies' => true)));
	pg_assert_false(ws_saved_reply_can_manage_team(array('member' => true, 'replies' => false)));
	pg_assert_false(ws_saved_reply_can_manage_team(array('member' => false, 'replies' => true)));
	pg_assert_false(ws_saved_reply_can_manage_team(array()));

	// A team write without the right is refused before anything is read;
	// a personal one is not refused for it.
	$member = array('id' => 4, 'member' => true, 'replies' => false);
	$refused = ws_saved_reply_save_check($member, array('shortcut' => 'hi', 'body' => 'x'), null, 'team');
	pg_assert_same(array(false, 403, 'not_allowed'), array($refused['ok'], $refused['status'], $refused['error']));

	$change = ws_saved_reply_save_check($member, array('body' => 'x'), array('id' => 3, 'scope' => 'team', 'shortcut' => 'hi'), 'personal');
	pg_assert_same('not_allowed', $change['error'], 'a change keeps the row\'s scope, whatever is sent');

	$personal = ws_saved_reply_save_check($member, array('shortcut' => 'hi', 'body' => 'x'));
	pg_assert_true($personal['error'] !== 'not_allowed', 'a personal reply needs no right');

	pg_assert_contains("'replies'", ws_saved_replies_test_source('ws_rights'), 'ws_rights() carries the right');
	pg_assert_contains('ws_team_replies_column()', ws_saved_replies_test_source('ws_rights'), 'the column is read behind its own probe');
}

// A team shortcut is unique among the team's replies; a personal one among
// the person's; the two lists do not meet.
function test_ws_saved_reply_rows_check()
{
	$checked = ws_saved_reply_input_check(array('shortcut' => 'iade', 'body' => 'x'));
	$team = array(array('id' => '11', 'scope' => 'team', 'shortcut' => 'iade'));

	$taken = ws_saved_reply_rows_check($checked, $team, null, 'team');
	pg_assert_same(array(false, 409, 'shortcut_taken', 'shortcut'), array($taken['ok'], $taken['status'], $taken['error'], $taken['field']), 'a team shortcut already taken');

	pg_assert_true(ws_saved_reply_rows_check($checked, array(), null, 'personal')['ok'], 'free among the person\'s own though the team holds it');
	pg_assert_true(ws_saved_reply_rows_check(ws_saved_reply_input_check(array('body' => 'y'), true), $team, $team[0], 'team')['ok'], 'the reply being changed keeps its shortcut');

	$full = array_fill(0, WS_SAVED_REPLIES_MAX, array('id' => '1', 'shortcut' => 'x'));
	pg_assert_same('limit_reached', ws_saved_reply_rows_check($checked, $full, null, 'personal')['error'] ?? null, 'a person\'s limit');
	pg_assert_true(ws_saved_reply_rows_check($checked, $full, null, 'team')['ok'], 'the team\'s limit is its own');

	pg_assert_same(lang('Another team reply has this shortcut.'), ws_saved_reply_error_text('shortcut_taken', 'team'));
	pg_assert_same(lang('Another of your saved replies has this shortcut.'), ws_saved_reply_error_text('shortcut_taken'));
}

// The person's own replies come first, so on a shared shortcut theirs wins.
function test_ws_saved_replies_order_on_a_shared_shortcut()
{
	$personal = array(array('id' => 2, 'scope' => 'personal', 'shortcut' => 'iade'));
	$team = array(
		array('id' => 5, 'scope' => 'team', 'shortcut' => 'iade'),
		array('id' => 6, 'scope' => 'team', 'shortcut' => 'kargo'),
	);

	pg_assert_same(array(2, 5, 6), array_column(ws_saved_replies_order($personal, $team), 'id'));
	pg_assert_same(array(5, 6), array_column(ws_saved_replies_order(array(), $team), 'id'), 'only the team\'s');

	$box = ws_saved_replies_test_source('ws_saved_replies_for_box');
	pg_assert_contains('ws_saved_replies_order(', $box);
	pg_assert_contains("empty(\$viewer['member'])", $box, 'a non-member reads none of the team\'s');
}

// A new table is listed for a reinstall, and the rows go with their user.
function test_ws_saved_replies_table_wiring()
{
	pg_assert_contains("'ws_saved_replies',", file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php'));

	pg_assert_contains('DELETE FROM ws_saved_replies WHERE user_id', file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/user_write.php'), 'pg_user_delete()');

	$migration = file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	pg_assert_contains('upgrade_2026_11_0_workspace_saved_replies(); // 11.0.114', $migration);
	pg_assert_contains('UNIQUE KEY uq_shortcut (user_id, scope, shortcut)', $migration);
	pg_assert_contains('upgrade_2026_11_0_ws_team_replies();         // 11.0.177', $migration);
	pg_assert_contains("install_add_column('user', 'manage_workspace_replies', \"TINYINT UNSIGNED NOT NULL DEFAULT 0\")", $migration);

	// The right is drawn and saved with the other Workspace rights, behind
	// its own probe; the login query never names it.
	$permissions = file_get_contents(PG_FUNCTIONS_DIR . '/includes/user_permissions.php');
	pg_assert_contains("pg_user_permission_switch('manage_workspace_replies'", $permissions);
	pg_assert_contains("\$fields[] = 'manage_workspace_replies';", $permissions);
	pg_assert_false(strpos(file_get_contents(PG_FUNCTIONS_DIR . '/includes/authentication.php'), 'manage_workspace_replies'), 'not in the sign-in path');
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
