<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an ERP account that uses another account's prices
 * (erp_accounts.price_account_id, includes/erp/price_lists.php): whose
 * prices an account buys at, the terms taken from that account's rows and
 * discount, one hop only at the save, an account others point at kept from
 * deletion, the recorded rule against a campaign, the retail price in
 * public places, and an account that uses nobody's prices priced as before.
 * The runner has no connection, so the pure rules are called and the
 * code's shape is read.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_ERP_ENTRY')) {
	define('PG_ERP_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/price_lists.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/storefront.php');

function _erp_shared_prices_test_file($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// Account 5 points at the template account 9: it buys at 9's rows and
// discount, not its own.
function test_erp_shared_prices_account_buys_at_another_accounts_prices()
{
	pg_assert_same(9, erp_account_price_source_id(5, 9), 'the pointer is followed');

	$template = array(
		'entries'       => array(42 => array('price' => 7000, 'discount_rate' => 0.0)),
		'discount_rate' => 10.0,
	);

	pg_assert_same(array('price' => 7000, 'discount_rate' => 0.0, 'source' => 'product'), erp_account_price_terms_pick($template, 42), 'the template\'s own price');
	pg_assert_same(array('price' => 0, 'discount_rate' => 10.0, 'source' => 'account'), erp_account_price_terms_pick($template, 43), 'the template\'s account discount');

	$pick = erp_storefront_price(10000, erp_account_price_terms_pick($template, 43));
	pg_assert_same(9000, $pick['price'], '10% off the list price');
	pg_assert_same('account', $pick['source'], 'priced by the account');
}

function test_erp_shared_prices_no_pointer_prices_as_today()
{
	pg_assert_same(5, erp_account_price_source_id(5, 0), 'no pointer: its own prices');
	pg_assert_same(5, erp_account_price_source_id(5, 5), 'a pointer at itself is read as none');
	pg_assert_same(0, erp_account_price_source_id(0, 9), 'no account: nobody\'s prices');

	pg_assert_same(null, erp_account_price_terms_pick(array('entries' => array(), 'discount_rate' => 0.0), 42), 'no rows and no discount: no terms');

	$pick = erp_storefront_price(10000, null, 9500);
	pg_assert_same(array(9500, 'offer'), array($pick['price'], $pick['source']), 'the campaign as before');
}

function test_erp_shared_prices_second_hop_refused_at_save()
{
	pg_assert_same('', erp_account_price_pointer_refusal(5, 9, true, 0, 0), 'a plain pointer');
	pg_assert_same('', erp_account_price_pointer_refusal(5, 0, false, 0, 3), 'letting go is always allowed');

	pg_assert_true(erp_account_price_pointer_refusal(5, 9, true, 7, 0) !== '', 'the chosen account uses another\'s prices itself');
	pg_assert_true(erp_account_price_pointer_refusal(5, 9, true, 0, 2) !== '', 'others use this account\'s prices');
	pg_assert_true(erp_account_price_pointer_refusal(5, 5, true, 0, 0) !== '', 'its own prices');
	pg_assert_true(erp_account_price_pointer_refusal(5, 9, false, 0, 0) !== '', 'an account that does not exist');
}

function test_erp_shared_prices_pointed_at_account_delete_refused()
{
	pg_assert_true(erp_account_delete_refusal(1) !== '', 'one account points at it');
	pg_assert_same('', erp_account_delete_refusal(0), 'nobody points at it');
}

// Against a campaign the recorded rule stays: the lower wins, a tie leaves
// the campaign, they never stack.
function test_erp_shared_prices_recorded_rule_against_campaign()
{
	$terms = erp_account_price_terms_pick(array('entries' => array(), 'discount_rate' => 10.0), 42);

	$pick = erp_storefront_price(10000, $terms, 8500);
	pg_assert_same(array(8500, 'offer'), array($pick['price'], $pick['source']), 'a lower campaign wins');

	$pick = erp_storefront_price(10000, $terms, 9000);
	pg_assert_same(array(9000, 'offer'), array($pick['price'], $pick['source']), 'a tie leaves the campaign');

	$pick = erp_storefront_price(10000, $terms, 9500);
	pg_assert_same(array(9000, 'account'), array($pick['price'], $pick['source']), 'a lower shared price wins, not stacked');
}

// In public the retail price stands: feeds ask for prices without the
// member's, the structured data reads the list price, and a page drawn with
// a member's own price is marked personal so the page cache never keeps it.
function test_erp_shared_prices_retail_in_public()
{
	pg_assert_contains('get_discounted_product_prices(false)', _erp_shared_prices_test_file('includes/fn/product_feeds.php'), 'the product feed');

	$catalog = _erp_shared_prices_test_file('includes/fn/widgets_catalog.php');
	pg_assert_contains("'price'           => sprintf('%01.2f', ((int)\$product['price']) / 100)", $catalog, 'the structured data reads the list price');

	$ecommerce = _erp_shared_prices_test_file('includes/fn/ecommerce.php');
	$start = strpos($ecommerce, 'function pg_erp_member_prices()');
	$body = substr($ecommerce, $start, strpos($ecommerce, "\n}\n", $start) - $start);
	pg_assert_contains("pg_page_cache_personal('member_price')", $body, 'a page with a member\'s price is never kept');
	pg_assert_contains('erp_account_storefront_scope(', $body, 'the store follows the pointer');
}

function test_erp_shared_prices_schema_step()
{
	$schema = _erp_shared_prices_test_file('includes/migrations/2026.11.0.php');

	pg_assert_contains("install_add_column('erp_accounts', 'price_account_id', \"INT UNSIGNED NOT NULL DEFAULT 0", $schema, 'the column');
	pg_assert_contains("install_add_index('erp_accounts', 'idx_price_account'", $schema, 'the index for who points at an account');
}
