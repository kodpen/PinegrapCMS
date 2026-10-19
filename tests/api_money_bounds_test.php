<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the bounds on the external API's money parameters: the route
 * table declares them (the router refuses a figure outside them with 422
 * before any handler runs), and the OpenAPI document shows them.
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');

// Every money parameter of the core table and of the ERP module, keyed
// "<route id>.<name>". The ERP rows are read directly, so the test does not
// depend on the module being switched on here.
function api_money_bounds_test_params()
{
	$out = array();

	foreach (array_merge(api_schema(), erp_api_routes()) as $route) {

		foreach ($route['params'] as $param) {

			if ((isset($param['type']) ? $param['type'] : '') === 'money') {

				$out[$route['id'] . '.' . $param['name']] = $param;

			}

		}

	}

	return $out;
}

// A money field with no floor takes a negative figure. The ones meant to are
// named here, so a new field cannot come in unbounded by accident.
function test_api_money_bounds_every_money_field_has_a_floor()
{
	$signed = array('erp.accounts.create.opening_balance');

	$params = api_money_bounds_test_params();

	pg_assert_true(count($params) >= 14, 'the money fields are found');

	foreach ($params as $key => $param) {

		if (in_array($key, $signed, true)) {

			pg_assert_false(isset($param['min']), $key . ' takes a negative figure');

			continue;

		}

		pg_assert_true(isset($param['min']), $key . ' declares min');

	}
}

function test_api_money_bounds_match_what_the_records_take()
{
	$params = api_money_bounds_test_params();

	$expect = array(
		// products.price is a signed INT column.
		'products.create.price'           => array(0, 2147483647),
		'products.update.price'           => array(0, 2147483647),
		// products.compare_at_price too; 0 is none, like null.
		'products.create.compare_at_price' => array(0, 2147483647),
		'products.update.compare_at_price' => array(0, 2147483647),
		// gift_cards.amount and balance are INT UNSIGNED.
		'gift_cards.create.amount'        => array(1, 4294967295),
		'gift_cards.update.balance'       => array(0, 4294967295),
		// The ERP columns are BIGINT; only the floor is the rule.
		'erp.accounts.create.credit_limit' => array(0, null),
		'erp.accounts.update.credit_limit' => array(0, null),
		'erp.receipts.create.amount'       => array(1, null),
		'erp.cash_transfers.create.amount' => array(1, null),
		'erp.cheques.create.amount'        => array(1, null),
		'erp.expenses.create.amount'       => array(1, null),
		'erp.expenses.create.tax_amount'   => array(0, null),
		'erp.expenses.update.amount'       => array(1, null),
		'erp.expenses.update.tax_amount'   => array(0, null)
	);

	foreach ($expect as $key => $bounds) {

		pg_assert_true(isset($params[$key]), $key . ' is a money field');

		if (!isset($params[$key])) {

			continue;

		}

		pg_assert_same($bounds[0], $params[$key]['min'], $key . ' min');
		pg_assert_same($bounds[1], isset($params[$key]['max']) ? $params[$key]['max'] : null, $key . ' max');

	}
}

function test_api_money_bounds_reach_the_openapi_document()
{
	$price = api_openapi_type(array('name' => 'price', 'type' => 'money', 'min' => 0, 'max' => 2147483647));

	pg_assert_same('integer', $price['type']);
	pg_assert_same(0, $price['minimum']);
	pg_assert_same(2147483647, $price['maximum']);

	$opening = api_openapi_type(array('name' => 'opening_balance', 'type' => 'money'));

	pg_assert_false(isset($opening['minimum']), 'no bound declared, none described');
	pg_assert_false(isset($opening['maximum']));
}
