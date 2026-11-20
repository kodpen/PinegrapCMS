<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an ERP account's "Uses the prices of" over the API: the
 * account answer carries price_account_id, the account update writes it
 * with the panel's one-hop check, a refusal is a 422 on that field, and a
 * rehearsal stops after the check and before the pointer is written. The
 * runner has no connection, so the presenter is called and the handler's
 * shape is read.
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

if (!defined('PG_ERP_ENTRY')) {
	define('PG_ERP_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/price_lists.php');
require_once(__DIR__ . '/helpers/source_reader.php');

function _erp_shared_prices_api_route($id)
{
	foreach (erp_api_routes() as $route) {
		if ($route['id'] === $id) {
			return $route;
		}
	}

	return array();
}

function _erp_shared_prices_api_source($function)
{
	$function = new ReflectionFunction($function);
	$lines = file($function->getFileName());

	return preg_replace('~^\s*(//|\*).*$~m', '', implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1)));
}

// The account answer says whose prices it buys at; 0 is its own, also on a
// site whose column is not there yet.
function test_erp_shared_prices_api_account_answer_names_the_pointer()
{
	$row = array(
		'id' => 5, 'kind' => 'customer', 'title' => 'Shop', 'is_person' => 0, 'tax_number' => '', 'tax_office' => '',
		'email' => '', 'phone' => '', 'address' => '', 'district' => '', 'city' => '', 'postcode' => '', 'country_code' => 'TR',
		'currency' => 'TRY', 'balance' => 0, 'balance_fc' => 0, 'contact_id' => 0, 'status' => 'active', 'notes' => '',
		'created_at' => 0, 'updated_at' => 0,
	);

	pg_assert_same(0, erp_api_account_present($row)['price_account_id'], 'no column: its own prices');

	$row['price_account_id'] = '9';
	pg_assert_same(9, erp_api_account_present($row)['price_account_id'], 'the account it uses the prices of');

	$schema = erp_api_account_schema();
	pg_assert_same('integer', $schema['price_account_id'] ?? null, 'declared for the OpenAPI document');
	pg_assert_same(array_keys($schema), array_keys(erp_api_account_present($row)), 'the declaration follows the answer');
}

// The update route takes the pointer; null or 0 lets go.
function test_erp_shared_prices_api_update_route_takes_the_pointer()
{
	$route = _erp_shared_prices_api_route('erp.accounts.update');
	$param = array();

	foreach ($route['params'] as $p) {
		if ($p['name'] === 'price_account_id') {
			$param = $p;
		}
	}

	pg_assert_same('body', $param['in'] ?? null, 'a body field of the update');
	pg_assert_same('int', $param['type'] ?? null, 'an account id');
	pg_assert_same(0, $param['min'] ?? null, '0 lets go');
	pg_assert_true(!empty($param['nullable']), 'null lets go');

	foreach (array('erp.accounts.create', 'erp.accounts.list', 'erp.accounts.get') as $id) {
		foreach (_erp_shared_prices_api_route($id)['params'] as $p) {
			pg_assert_true($p['name'] !== 'price_account_id', $id . ' does not take the pointer');
		}
	}
}

// The handler asks the panel's one-hop check before the rehearsal stops,
// answers a refusal on the field, and writes through the panel's save after
// the card.
function test_erp_shared_prices_api_update_checks_before_the_rehearsal_stops()
{
	$source = _erp_shared_prices_api_source('erp_api_accounts_update');
	$check = strpos($source, 'erp_account_price_pointer_check(');
	$stop = strpos($source, 'api_dry_run_stop(');
	$save = strpos($source, 'erp_account_price_pointer_save(');

	pg_assert_true($check !== false, 'the one-hop check is asked');
	pg_assert_true(($stop !== false) && ($check < $stop), 'the check runs before a rehearsal stops');
	pg_assert_true(($save !== false) && ($save > $stop), 'the pointer is written after the stop');
	pg_assert_true($save > strpos($source, 'erp_account_save('), 'after the card');
	pg_assert_true(strpos($source, "api_fail_validation(\$refusal, 'price_account_id')") !== false, 'a refusal is a 422 on the field');
	pg_assert_true(strpos($source, 'erp_shared_prices_ready()') !== false, 'a site without the column refuses the field');
	pg_assert_true(strpos($source, 'UPDATE erp_accounts') === false, 'no write of its own');
}

// The panel's save asks the same check, so both refuse the same pointers.
function test_erp_shared_prices_api_panel_save_shares_the_check()
{
	$source = _erp_shared_prices_api_source('erp_account_price_pointer_save');

	pg_assert_true(strpos($source, 'erp_account_price_pointer_check(') !== false, 'the panel\'s save asks the shared check');
	pg_assert_true(strpos(_erp_shared_prices_api_source('erp_account_price_pointer_check'), 'erp_account_price_pointer_refusal(') !== false, 'the check is the pure one-hop rule');
}
