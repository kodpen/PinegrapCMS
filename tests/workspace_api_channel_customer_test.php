<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a channel's customer in the Workspace API: the create and
 * update routes take customer_type and customer_id as the channel form does,
 * one half alone is refused unless it clears, and a clear reaches
 * ws_customer_input() as none.
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

// The parameters of a route, by name.
function ws_api_channel_customer_test_params($id)
{
	foreach (ws_api_routes() as $route) {
		if ($route['id'] === $id) {
			$params = array();

			foreach ($route['params'] as $param) {
				$params[$param['name']] = $param;
			}

			return $params;
		}
	}

	return array();
}

// Both writes take the two fields; the kinds are the channel form's.
function test_ws_api_channel_routes_take_the_customer()
{
	foreach (array('workspace.channels.create', 'workspace.channels.update') as $id) {
		$params = ws_api_channel_customer_test_params($id);

		pg_assert_true(isset($params['customer_type'], $params['customer_id']), $id . ': both fields');
		pg_assert_same(ws_customer_types(), $params['customer_type']['values'], $id . ': the kinds');
		pg_assert_true(!empty($params['customer_type']['nullable']) && !empty($params['customer_id']['nullable']), $id . ': null clears');
		pg_assert_same('int', $params['customer_id']['type'], $id . ': an id');
	}
}

// One half alone is refused when it names a customer, not when it clears.
function test_ws_api_channel_customer_missing()
{
	pg_assert_same('', ws_api_channel_customer_missing(array()), 'neither');
	pg_assert_same('', ws_api_channel_customer_missing(array('customer_type' => 'contact', 'customer_id' => 5)), 'both');
	pg_assert_same('customer_id', ws_api_channel_customer_missing(array('customer_type' => 'contact')), 'a kind with no id');
	pg_assert_same('customer_type', ws_api_channel_customer_missing(array('customer_id' => 5)), 'an id with no kind');
	pg_assert_same('', ws_api_channel_customer_missing(array('customer_type' => '')), 'the kind cleared');
	pg_assert_same('', ws_api_channel_customer_missing(array('customer_id' => 0)), 'the id cleared');
}

// The fields ws_customer_input() reads: none when nothing is sent, both
// otherwise, empty and 0 for a clear.
function test_ws_api_channel_customer_fields()
{
	pg_assert_same(array(), ws_api_channel_customer_fields(array('name' => 'x')), 'nothing sent');
	pg_assert_same(array('customer_type' => 'erp_account', 'customer_id' => 12), ws_api_channel_customer_fields(array('customer_type' => 'erp_account', 'customer_id' => 12)), 'a current account');
	pg_assert_same(array('customer_type' => '', 'customer_id' => 0), ws_api_channel_customer_fields(array('customer_type' => '')), 'cleared');
}
