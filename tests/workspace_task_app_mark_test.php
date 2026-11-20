<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the mark a task carries when an application created it
 * (includes/workspace/tasks.php): which application a route writes, the
 * "Made by agents" filter of My Tasks and GET /workspace/tasks, and the
 * application as the task row, the board card, the drawer and the API show
 * it.
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

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

// The route names the application, or the request's caller is taken: an
// application key or an OAuth connection is an application, a person's own
// device or the panel is 0.
function test_workspace_task_app_of_route()
{
	pg_assert_same(0, ws_task_app_of(array(), 0), 'a person in the panel');
	pg_assert_same(12, ws_task_app_of(array(), 12), 'the API with an application key');
	pg_assert_same(7, ws_task_app_of(array('app_id' => 7), 0), 'a route that names the application (a flow run, a proposal)');
	pg_assert_same(0, ws_task_app_of(array('app_id' => 0), 12), 'a route that names a person wins over the request');
	pg_assert_same(0, ws_task_app_of(array('app_id' => -3), 0), 'never below 0');
}

function test_workspace_task_made_by_mode()
{
	pg_assert_same('', ws_task_made_by_mode(''), 'all');
	pg_assert_same('', ws_task_made_by_mode('all'), 'all, spelled');
	pg_assert_same('people', ws_task_made_by_mode('people'), 'without them');
	pg_assert_same('apps', ws_task_made_by_mode('apps'), 'them alone');
	pg_assert_same('', ws_task_made_by_mode('robots'), 'an unknown value is all');
	pg_assert_same('', ws_task_made_by_mode(array('apps')), 'not a string');
}

function test_workspace_task_made_by_sql()
{
	pg_assert_same('', ws_task_made_by_sql('', 'mine', true), 'all filters nothing');
	pg_assert_same('t.app_id = 0', ws_task_made_by_sql('people', 'mine', true), 'without them');
	pg_assert_same('t.app_id > 0', ws_task_made_by_sql('apps', 'department', true), 'them alone');
	pg_assert_same('app_id > 0', ws_task_made_by_sql('apps', 'all', true, 'app_id'), 'another column');
}

// "Created by me" no longer lists what an application made with the
// reader's key unless the filter asks for them alone.
function test_workspace_task_made_by_created_scope()
{
	pg_assert_same('t.app_id = 0', ws_task_made_by_sql('', 'created', true), 'created, all: the person\'s own');
	pg_assert_same('t.app_id = 0', ws_task_made_by_sql('people', 'created', true), 'created, without them');
	pg_assert_same('t.app_id > 0', ws_task_made_by_sql('apps', 'created', true), 'created, them alone');
}

// Before the upgrade every task reads as a person's: no filter, or nothing
// when only applications' tasks are asked for.
function test_workspace_task_made_by_before_upgrade()
{
	pg_assert_same('', ws_task_made_by_sql('', 'created', false), 'created lists all');
	pg_assert_same('', ws_task_made_by_sql('people', 'mine', false), 'people is everything');
	pg_assert_same('0 = 1', ws_task_made_by_sql('apps', 'mine', false), 'apps is nothing');
}

function test_workspace_task_app_present()
{
	pg_assert_same(null, ws_task_app_present(0, array()), 'a person');
	pg_assert_same(array('id' => 4, 'name' => 'Builder'), ws_task_app_present(4, array(4 => 'Builder')), 'id and name');
	pg_assert_same(array('id' => 9, 'name' => null), ws_task_app_present(9, array(4 => 'Builder')), 'a deleted application has no name');
}

function test_workspace_task_app_label()
{
	pg_assert_same('', ws_task_app_label(null), 'nothing for a person');
	pg_assert_same('Made by Builder', ws_task_app_label(array('id' => 4, 'name' => 'Builder')), 'the application');
	pg_assert_same('Made by an application', ws_task_app_label(array('id' => 9, 'name' => null)), 'a deleted application');
}
