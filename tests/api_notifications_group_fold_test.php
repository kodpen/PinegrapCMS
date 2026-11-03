<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the bell's group filter and fold key on GET /notifications
 * (api_notifications_group_where() and api_notification_fold(),
 * includes/api/resources/account.php): the group narrows the query before
 * paging the way the panel's chips split the bell, and a folded flow row
 * carries the key the panel folds it by.
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/account.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

function test_api_notifications_group_workspace_keeps_the_workspace_rows()
{
	pg_assert_same("notifications.action = 'workspace'", api_notifications_group_where('workspace'));
}

function test_api_notifications_group_site_keeps_every_other_row()
{
	pg_assert_same("(notifications.action IS NULL OR notifications.action <> 'workspace')", api_notifications_group_where('site'), 'a row without an action is a site row, as in the panel');
}

function test_api_notifications_no_group_lists_every_row()
{
	pg_assert_same(null, api_notifications_group_where(null));
	pg_assert_same(null, api_notifications_group_where(''));
}

function test_api_notification_fold_carries_a_flow_run_key()
{
	pg_assert_same('flow:12:failed', api_notification_fold(array('fold' => 'flow:12:failed')), 'a failed run of flow 12');
	pg_assert_same(null, api_notification_fold(array('fold' => '')), 'a row that stands alone');
	pg_assert_same(null, api_notification_fold(array()), 'a display without the key');
}

function test_api_notification_schema_lists_fold()
{
	$schema = api_notification_schema();

	pg_assert_same('string?', $schema['fold'] ?? null);
}

function test_api_notifications_list_route_takes_the_group()
{
	$found = null;

	foreach (api_schema() as $route) {

		if ($route['id'] === 'notifications.list') {
			$found = $route;
		}

	}

	pg_assert_true(is_array($found), 'the route exists');

	$group = null;

	foreach ($found['params'] as $param) {

		if ($param['name'] === 'group') {
			$group = $param;
		}

	}

	pg_assert_true(is_array($group), 'the route declares group');
	pg_assert_same('enum', $group['type']);
	pg_assert_same(array('site', 'workspace'), $group['values']);
}
