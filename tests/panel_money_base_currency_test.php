<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel amounts are base-currency figures printed under the base symbol
 * (prepare_amount()), never converted to the currency the operator's browser
 * picked for the shop (prepare_price_for_output() applies the visitor's
 * exchange rate and symbol). The Workspace's money helper and the
 * Marketplace screens follow that; the Workspace chips of ERP documents
 * kept in another currency print it in the document's own currency.
 *
 * Branches that run queries are read from source
 * (tests/helpers/source_reader.php).
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
require_once(__DIR__ . '/helpers/source_reader.php');

// The code of one branch of ws_refs_resolve(), comments left out.
function panel_money_base_currency_test_ref_branch($kind)
{
	$source = pg_test_function_source('ws_refs_resolve');
	$start = strpos($source, "if (!empty(\$ids['" . $kind . "']))");
	pg_assert_true($start !== false, 'ws_refs_resolve() has a ' . $kind . ' branch');

	$end = strpos($source, 'if (!empty($ids[', (int) $start + 1);
	$branch = substr($source, (int) $start, ($end === false) ? strlen($source) : $end - (int) $start);

	return implode('', array_map('pg_test_source_text', pg_test_source_tokens($branch)));
}

// The code of a whole file, comments left out.
function panel_money_base_currency_test_file($file)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

	return implode('', array_map('pg_test_source_text', pg_test_source_tokens($source)));
}

// ws_money_out() writes a base amount as the panel does: the base symbol and
// the figure as stored, decoded for the caller to escape.
function test_panel_money_base_currency_ws_money_out()
{
	if (!defined('BASE_CURRENCY_SYMBOL')) {
		define('BASE_CURRENCY_SYMBOL', '$');
	}

	$symbol = html_entity_decode(BASE_CURRENCY_SYMBOL, ENT_QUOTES | ENT_HTML5, 'UTF-8');

	pg_assert_same($symbol . pg_format_number(1234.56, 2), ws_money_out(123456), 'base symbol, no conversion');
	pg_assert_same('-' . $symbol . pg_format_number(5, 2), ws_money_out(-500), 'a negative amount keeps its sign');

	$code = implode('', array_map('pg_test_source_text', pg_test_source_tokens(pg_test_function_source('ws_money_out'))));
	pg_assert_true(strpos($code, 'prepare_price_for_output') === false, 'not converted to the visitor\'s currency');
}

// An invoice, a cash receipt and an incoming e-invoice keep their amount in
// their own currency (erp_invoices, erp_cash_transactions, erp_edoc_inbox
// all have a currency column), so their chips print it in that currency.
function test_panel_money_base_currency_erp_chips_in_their_currency()
{
	$cases = array(
		'invoice' => array('i.currency', "ws_erp_money_out(\$row['grand_total'], \$row['currency'])"),
		'receipt' => array('c.currency', "ws_erp_money_out(\$row['amount'], \$row['currency'])"),
		'edoc'    => array('total, currency,', "ws_erp_money_out(\$row['total'], \$row['currency'])"),
	);

	foreach ($cases as $kind => $case) {
		$branch = panel_money_base_currency_test_ref_branch($kind);

		pg_assert_contains($case[0], $branch, 'the ' . $kind . '\'s currency is read');
		pg_assert_contains($case[1], $branch, 'the ' . $kind . ' amount is printed in its currency');
		pg_assert_true(strpos($branch, 'ws_money_out(') === false, 'the ' . $kind . ' amount is not printed as a base amount');
	}

	// The helper loads the ERP money helpers alone outside the ERP screens
	// and prints in the document's currency (erp_money_out_currency() is
	// loaded by the test runner).
	pg_assert_contains("'/includes/erp/money.php'", pg_test_function_source('ws_erp_money_out'), 'the money helpers are loaded');
	$base = defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '';
	pg_assert_same(erp_money_out_currency(123456, $base), ws_erp_money_out(123456, ''), 'no currency is the base currency');
}

// The Marketplace products screen prints the shop price sent to the
// marketplace and the listed price as base amounts under the base symbol.
function test_panel_money_base_currency_marketplace_products()
{
	$code = panel_money_base_currency_test_file('marketplace_products.php');

	pg_assert_true(strpos($code, 'prepare_price_for_output') === false, 'no price is converted to the visitor\'s currency');
	pg_assert_contains('pg_format_money(mp_product_price($product) / 100, BASE_CURRENCY_SYMBOL)', $code, 'the shop price under the base symbol');
	pg_assert_contains("pg_format_money(((int) \$row['price']) / 100, BASE_CURRENCY_SYMBOL)", $code, 'the listed price under the base symbol');
}

// Marketplace settings prints an imported order's total unconverted: under
// the base symbol, or with the order's own currency code when the
// marketplace sold it in another currency.
function test_panel_money_base_currency_marketplace_order_total()
{
	$code = panel_money_base_currency_test_file('marketplace_settings.php');

	pg_assert_true(strpos($code, 'prepare_price_for_output') === false, 'no total is converted to the visitor\'s currency');
	pg_assert_contains('orders.total, orders.currency_code', $code, 'the order\'s currency is read');
	pg_assert_contains('mp_settings_order_total($row)', $code, 'the total is printed through the helper');
	pg_assert_contains("pg_format_money(\$total, '', ' ' . h(\$code))", $code, 'another currency with its code');
	pg_assert_contains('pg_format_money($total, BASE_CURRENCY_SYMBOL)', $code, 'the base currency under the base symbol');
}
