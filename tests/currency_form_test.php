<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the currency form shared by the create and the edit screens
 * (includes/forms/currency_form.php): the exchange rate a posted currency
 * is saved with, and that both screens use the shared form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/currency_form.php');

function test_currency_form_exchange_rate()
{
	pg_assert_same('1.50000', pg_currency_form_exchange_rate('1.5', ''), 'a rate is saved with five decimals');
	pg_assert_same('0.00006', pg_currency_form_exchange_rate('0.00006', '0'), 'a small rate is not written in exponent form');
	pg_assert_same(1, pg_currency_form_exchange_rate('3.2', '1'), 'the base currency is saved with rate 1');
	pg_assert_same('', pg_currency_form_exchange_rate('', ''), 'an empty rate stays empty');
}

function test_currency_form_screens_use_shared_form()
{
	foreach (array('add_currency.php', 'edit_currency.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/currency_form.php', $source, $file . ' loads the form');
		pg_assert_contains('pg_currency_form_card($liveform)', $source, $file . ' prints the shared card');
		pg_assert_contains('pg_currency_form_code_in_use(', $source, $file . ' checks the code the shared way');
		pg_assert_contains('pg_currency_form_exchange_rate(', $source, $file . ' saves the rate the shared way');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_contains('validate_ecommerce_access($user)', $source, $file . ' keeps the commerce gate');
		pg_assert_false(strpos($source, "'id'=>'exchange_rate'") !== false, $file . ' keeps no copy of the card');
	}
}
