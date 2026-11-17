<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the automation contract (pg_automation_contract()): every event
 * it names is in its catalogue, every route is in the route table under the
 * scope the row says, every newest-first order is one the list takes, no
 * route moves money, and no scope the contract asks for holds a route that
 * does.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/automation_contract.php');

// The routes of the core or of one module, by id.
function automation_contract_test_routes($module)
{
	$lists = array(
		''          => api_schema(),
		'erp'       => erp_api_routes(),
		'workspace' => ws_api_routes(),
	);

	$routes = array();

	foreach (isset($lists[$module]) ? $lists[$module] : array() as $route) {
		$routes[(string) $route['id']] = $route;
	}

	return $routes;
}

// The events of the core catalogue or of one module's seam.
function automation_contract_test_events($module)
{
	if ($module === 'erp') {
		return erp_webhook_events();
	}

	if ($module === 'workspace') {
		return ws_webhook_events();
	}

	return ($module === '') ? api_webhook_events() : array();
}

// Every route of the core and of every module, switched on or not.
function automation_contract_test_all_routes()
{
	return array_merge(api_schema(), erp_api_routes(), ws_api_routes(), translate_api_routes());
}

// The {placeholders} of a route's path, in order.
function automation_contract_test_placeholders($path)
{
	preg_match_all('/\{([a-z_]+)\}/', (string) $path, $found);

	return $found[1];
}

// The route ids a row names, each with the method it must have.
function automation_contract_test_row_routes($row)
{
	if ($row['kind'] === 'trigger') {
		return array($row['record'] => 'GET', $row['list'] => 'GET');
	}

	return array($row['route'] => ($row['kind'] === 'action') ? 'POST' : 'GET');
}

function test_automation_contract_rows_are_well_formed()
{
	$keys = array();

	foreach (pg_automation_contract() as $row) {
		$key = (string) $row['key'];

		pg_assert_true(preg_match('/^[a-z][a-z0-9_]*$/', $key) === 1, $key . ' is a plain key');
		pg_assert_true(!isset($keys[$key]), $key . ' is named once');
		pg_assert_true(in_array($row['kind'], array('trigger', 'action', 'search'), true), $key . ' has a known kind');
		pg_assert_true(in_array($row['module'], array('', 'erp', 'workspace'), true), $key . ' names a known module');

		$keys[$key] = true;
	}

	pg_assert_true(count($keys) > 10, 'the contract was read');
}

function test_automation_contract_events_are_in_their_catalogue()
{
	$missing = array();

	foreach (pg_automation_contract() as $row) {
		if ($row['kind'] !== 'trigger') {
			continue;
		}

		pg_assert_true(count($row['events']) > 0, $row['key'] . ' names an event');

		$events = automation_contract_test_events($row['module']);

		foreach ($row['events'] as $event) {
			if (!isset($events[$event])) {
				$missing[] = $row['key'] . ': ' . $event;
			}
		}
	}

	pg_assert_same(array(), $missing, 'events not in the catalogue of the row\'s module');
}

function test_automation_contract_routes_are_in_the_table_under_the_scope()
{
	$wrong = array();

	foreach (pg_automation_contract() as $row) {
		$routes = automation_contract_test_routes($row['module']);

		foreach (automation_contract_test_row_routes($row) as $id => $method) {
			if (!isset($routes[$id])) {
				$wrong[] = $row['key'] . ': ' . $id . ' is not a route of its module';
				continue;
			}

			if ((string) $routes[$id]['scope'] !== $row['scope']) {
				$wrong[] = $row['key'] . ': ' . $id . ' is under ' . $routes[$id]['scope'];
			}

			if ((string) $routes[$id]['method'] !== $method) {
				$wrong[] = $row['key'] . ': ' . $id . ' is not ' . $method;
			}
		}
	}

	pg_assert_same(array(), $wrong, 'routes outside the table, the scope or the method');
}

// A trigger reads its record by the ids its event carries: every
// placeholder of the record route (and of the list route) is given a field.
function test_automation_contract_trigger_ids_fill_the_paths()
{
	$wrong = array();

	foreach (pg_automation_contract() as $row) {
		if ($row['kind'] !== 'trigger') {
			continue;
		}

		$routes = automation_contract_test_routes($row['module']);

		foreach (array('record' => 'record_ids', 'list' => 'list_ids') as $which => $field) {
			if (!isset($routes[$row[$which]])) {
				continue;
			}

			$holes = automation_contract_test_placeholders($routes[$row[$which]]['path']);
			$given = array_keys($row[$field]);

			sort($holes);
			sort($given);

			if ($holes !== $given) {
				$wrong[] = $row['key'] . ': ' . $field . ' gives ' . implode(',', $given) . ' for ' . implode(',', $holes);
			}

			foreach ($row[$field] as $data_field) {
				if (preg_match('/^[a-z_]+(\.[a-z_]+)?$/', (string) $data_field) !== 1) {
					$wrong[] = $row['key'] . ': ' . $data_field . ' is not a field of data';
				}
			}
		}

		if ($row['record_ids'] === array()) {
			$wrong[] = $row['key'] . ': the record route takes no id';
		}
	}

	pg_assert_same(array(), $wrong, 'record and list ids');
}

// newest: the query parameters that list the newest first, array() where
// the list does so by itself, null where it cannot yet.
function test_automation_contract_newest_orders_are_taken()
{
	$wrong = array();

	foreach (pg_automation_contract() as $row) {
		if (($row['kind'] !== 'trigger') || ($row['newest'] === null)) {
			continue;
		}

		$routes = automation_contract_test_routes($row['module']);

		if (!isset($routes[$row['list']])) {
			continue;
		}

		$route = $routes[$row['list']];

		if ($row['newest'] === array()) {
			if (stripos((string) $route['description'], 'newest first') === false) {
				$wrong[] = $row['key'] . ': ' . $row['list'] . ' does not list the newest first by itself';
			}

			continue;
		}

		$params = array();

		foreach ($route['params'] as $param) {
			$params[(string) $param['name']] = $param;
		}

		foreach ($row['newest'] as $name => $value) {
			if (!isset($params[$name]) || ($params[$name]['in'] !== 'query')) {
				$wrong[] = $row['key'] . ': ' . $row['list'] . ' takes no ' . $name;
			} elseif (isset($params[$name]['values']) && !in_array($value, $params[$name]['values'], true)) {
				$wrong[] = $row['key'] . ': ' . $name . ' takes no ' . $value;
			}
		}
	}

	pg_assert_same(array(), $wrong, 'newest-first orders the lists do not take');
}

function test_automation_contract_searches_take_their_parameters()
{
	$wrong = array();

	foreach (pg_automation_contract() as $row) {
		if ($row['kind'] !== 'search') {
			continue;
		}

		$routes = automation_contract_test_routes($row['module']);

		if (!isset($routes[$row['route']])) {
			continue;
		}

		$names = array();

		foreach ($routes[$row['route']]['params'] as $param) {
			if ($param['in'] === 'query') {
				$names[] = (string) $param['name'];
			}
		}

		pg_assert_true(count($row['by']) > 0, $row['key'] . ' searches by something');

		foreach ($row['by'] as $name) {
			if (!in_array($name, $names, true)) {
				$wrong[] = $row['key'] . ': ' . $row['route'] . ' takes no ' . $name;
			}
		}
	}

	pg_assert_same(array(), $wrong, 'search parameters the routes do not take');
}

// No route the contract names moves money or makes a cost.
function test_automation_contract_names_no_money_route()
{
	$money = array();

	foreach (automation_contract_test_all_routes() as $route) {
		if (!empty($route['moves_money'])) {
			$money[(string) $route['id']] = true;
		}
	}

	pg_assert_true(isset($money['orders.create']), 'the money routes were read');

	$inside = array();

	foreach (pg_automation_contract() as $row) {
		foreach (array_keys(automation_contract_test_row_routes($row)) as $id) {
			if (isset($money[$id])) {
				$inside[] = $row['key'] . ': ' . $id;
			}
		}
	}

	pg_assert_same(array(), $inside, 'money routes in the contract');
}

// The scopes a new application for an automation platform is given hold no
// route that moves money: a scope is the whole of its routes, not the one
// the contract uses.
function test_automation_contract_scopes_hold_no_money_route()
{
	$scopes = pg_automation_contract_scopes();
	$inside = array();

	pg_assert_true(in_array('webhooks:manage', $scopes, true), 'webhooks:manage is asked for');
	pg_assert_true(in_array('meta:read', $scopes, true), 'meta:read is asked for');
	pg_assert_same(array_values(array_unique($scopes)), $scopes, 'each scope once');

	foreach (pg_automation_contract() as $row) {
		pg_assert_true(in_array($row['scope'], $scopes, true), $row['scope'] . ' is asked for');
	}

	foreach (automation_contract_test_all_routes() as $route) {
		if (!empty($route['moves_money']) && in_array((string) $route['scope'], $scopes, true)) {
			$inside[] = $route['id'] . ' (' . $route['scope'] . ')';
		}
	}

	pg_assert_same(array(), $inside, 'money routes under a scope the contract asks for');
}

// Once an app relies on a row it stays: a key may be added, never dropped.
function test_automation_contract_keeps_its_keys()
{
	$kept = array(
		'order_created', 'order_status_changed', 'order_shipped', 'order_cancelled',
		'order_return_requested', 'customer_created', 'form_submitted', 'product_created',
		'stock_low', 'subscription_renewed', 'subscription_failed', 'subscription_cancelled',
		'workspace_task_created', 'workspace_task_completed', 'workspace_table_row_created',
		'erp_invoice_created', 'erp_invoice_paid', 'erp_quote_created', 'erp_quote_accepted',
		'create_product', 'update_product', 'set_stock', 'create_form_submission',
		'create_workspace_task', 'create_workspace_message', 'create_workspace_table_row',
		'find_order', 'find_customer', 'find_product', 'find_form_submission', 'find_erp_invoice'
	);

	$keys = array();

	foreach (pg_automation_contract() as $row) {
		$keys[] = (string) $row['key'];
	}

	pg_assert_same(array(), array_values(array_diff($kept, $keys)), 'rows dropped from the contract');
}
