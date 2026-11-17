<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the "Uses the prices of" picker on an account's prices screen
 * (erp_account_prices.php, includes/erp/price_lists.php): which accounts it
 * offers, what it says while the account follows another's prices or while
 * others follow this one's, and the screen's save path. The runner has no
 * connection, so the pure rules are called and the screen's shape is read.
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

function _erp_picker_test_accounts()
{
	return array(
		array('id' => 3, 'title' => 'Dealer list', 'kind' => 'customer', 'status' => 'passive', 'price_account_id' => 0),
		array('id' => 5, 'title' => 'This buyer', 'kind' => 'customer', 'status' => 'active', 'price_account_id' => 0),
		array('id' => 7, 'title' => 'Another buyer', 'kind' => 'both', 'status' => 'active', 'price_account_id' => 3),
		array('id' => 8, 'title' => 'A supplier', 'kind' => 'supplier', 'status' => 'active', 'price_account_id' => 0),
		array('id' => 9, 'title' => 'Wholesale list', 'kind' => 'both', 'status' => 'active', 'price_account_id' => 0),
	);
}

// Offered: every customer account but itself and the accounts that follow
// another's prices (one hop); a passive price list is offered.
function test_erp_shared_prices_picker_offers()
{
	pg_assert_same(array(3 => 'Dealer list', 9 => 'Wholesale list'), erp_account_price_pointer_options(_erp_picker_test_accounts(), 5, 0), 'the accounts offered');
}

// An account already chosen stays in the list, so the screen shows it even
// when it would not be offered any more.
function test_erp_shared_prices_picker_keeps_current_choice()
{
	$options = erp_account_price_pointer_options(_erp_picker_test_accounts(), 5, 7);

	pg_assert_true(isset($options[7]), 'the current choice is kept');
	pg_assert_same(array(3, 7, 9), array_keys($options), 'in the order the accounts come (erp_accounts() sorts by title)');
}

function test_erp_shared_prices_picker_screen_saves_through_the_check()
{
	$screen = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/erp_account_prices.php');

	pg_assert_contains("\$action === 'price_account'", $screen, 'its own action');
	pg_assert_contains('erp_account_price_pointer_save(', $screen, 'saved through the one-hop check');
	pg_assert_contains('erp_account_price_pointer_options(', $screen, 'the offered accounts');
	pg_assert_contains('erp_account_price_users(', $screen, 'the accounts that use this one\'s prices');
	pg_assert_contains("lang('Uses the prices of')", $screen, 'the label');
	pg_assert_contains("lang('Only the prices and the discount follow; payment terms and the credit limit stay this account\\'s. Making the chosen account passive does not stop its prices.')", $screen, 'the hint the architect asked for');
	pg_assert_contains('data-pg-combobox', $screen, 'a searchable list');
	pg_assert_contains('pg_fields_script()', $screen, 'its script');
}
