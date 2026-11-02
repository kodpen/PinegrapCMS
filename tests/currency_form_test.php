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

function test_currency_form_exchange_rate_error()
{
	$message = lang('Enter an exchange rate greater than zero.');

	pg_assert_same('', pg_currency_form_exchange_rate_error('1.5', ''), 'a positive rate is accepted');
	pg_assert_same('', pg_currency_form_exchange_rate_error('0,00006', '0'), 'a small masked rate is accepted');
	pg_assert_same('', pg_currency_form_exchange_rate_error('', '1'), 'the base currency needs no rate (it is saved as 1)');
	pg_assert_same($message, pg_currency_form_exchange_rate_error('', ''), 'an empty rate is refused: it is saved as 0');
	pg_assert_same($message, pg_currency_form_exchange_rate_error('0', '0'), 'a zero rate is refused: checkout divides by it');
	pg_assert_same($message, pg_currency_form_exchange_rate_error('0.000001', ''), 'a rate that rounds to 0 at five decimals is refused');
	pg_assert_same($message, pg_currency_form_exchange_rate_error('-2', ''), 'a negative rate is refused');
	pg_assert_same($message, pg_currency_form_exchange_rate_error('abc', ''), 'a non-numeric rate is refused');
	pg_assert_same($message, pg_currency_form_exchange_rate_error(array('1'), ''), 'a non-scalar rate is refused');
}

function test_currency_form_screens_use_shared_form()
{
	foreach (array('add_currency.php', 'edit_currency.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/currency_form.php', $source, $file . ' loads the form');
		pg_assert_contains('pg_currency_form_card($liveform', $source, $file . ' prints the shared card');
		pg_assert_contains('pg_currency_form_code_in_use(', $source, $file . ' checks the code the shared way');
		pg_assert_contains('pg_currency_form_exchange_rate(', $source, $file . ' saves the rate the shared way');
		pg_assert_contains('pg_currency_form_exchange_rate_error(', $source, $file . ' refuses a rate that is not above zero');
		pg_assert_contains('pg_currency_form_code_error(', $source, $file . ' refuses a code that is not ISO 4217');
		pg_assert_contains("assign_field_value('code', pg_currency_form_code(", $source, $file . ' checks and saves the code in capitals');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_contains('validate_ecommerce_access($user)', $source, $file . ' keeps the commerce gate');
		pg_assert_false(strpos($source, "'id'=>'exchange_rate'") !== false, $file . ' keeps no copy of the card');
	}
}

function test_currency_form_rate_above_column_is_refused()
{
	pg_assert_same('', pg_currency_form_exchange_rate_error('99999.99999', ''), 'the column top is accepted');
	pg_assert_same(lang(array('string' => 'Enter an exchange rate no greater than {var:1}.', 'vars' => array(h(pg_format_number(99999.99999, 5))))), pg_currency_form_exchange_rate_error('123456.5', ''), 'a rate the column cannot hold is refused, not cut');
	pg_assert_same('', pg_currency_form_exchange_rate_error('123456.5', '1'), 'the base currency is saved with 1 whatever is posted');
}

function test_currency_form_code()
{
	$message = lang('The code must be a three-letter ISO 4217 code (e.g. USD, EUR).');

	pg_assert_same('USD', pg_currency_form_code(' usd '), 'the code is trimmed and in capitals');
	pg_assert_same('', pg_currency_form_code(array('USD')), 'a non-scalar code is empty');
	pg_assert_same('', pg_currency_form_code_error('EUR'), 'three capitals are accepted');
	pg_assert_same($message, pg_currency_form_code_error('USDX'), 'a fourth letter is refused: the column would cut it to a second USD');
	pg_assert_same($message, pg_currency_form_code_error('PG'), 'two letters are refused');
	pg_assert_same($message, pg_currency_form_code_error(pg_currency_form_code('ğ1')), 'a non-Latin letter or a digit is refused');
}

function test_currency_form_base_cannot_be_removed()
{
	pg_assert_same(lang('The base currency cannot be switched off. Make another currency the base instead.'), pg_currency_form_base_error('1', ''), 'the stored base posted without the switch is refused');
	pg_assert_same('', pg_currency_form_base_error('1', '1'), 'the stored base saved as base is accepted');
	pg_assert_same('', pg_currency_form_base_error('0', ''), 'another currency may stay off');
	pg_assert_same('', pg_currency_form_base_error('0', '1'), 'another currency may become the base');
	pg_assert_same(lang('The base currency cannot be deleted. Make another currency the base first.'), pg_currency_form_delete_error('1'), 'the base currency cannot be deleted');
	pg_assert_same('', pg_currency_form_delete_error('0'), 'another currency can be deleted');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_currency.php');

	pg_assert_contains('pg_currency_form_delete_error($stored_base)', $source, 'the delete branch checks the stored base');
	pg_assert_contains('pg_currency_form_base_error($stored_base,', $source, 'the save checks the stored base');
	pg_assert_contains('pg_currency_form_card($liveform, $stored_base == 1)', $source, 'the base currency gets a locked switch');
	pg_assert_same(substr_count($source, '<form '), substr_count($source, '</form>'), 'the edit screen closes its form once');
}

function test_currency_list_sort()
{
	$rows = array(
		array('code' => 'AAA', 'name' => 'US Dolar', 'symbol' => '&#67;', 'base' => '0', 'exchange_rate' => '2.00000', 'created_timestamp' => '3', 'last_modified_timestamp' => '1'),
		array('code' => 'BBB', 'name' => 'US Dolb', 'symbol' => 'B', 'base' => '1', 'exchange_rate' => '1.00000', 'created_timestamp' => '1', 'last_modified_timestamp' => '1'),
		array('code' => 'CCC', 'name' => 'Zed', 'symbol' => 'A', 'base' => '0', 'exchange_rate' => '10.00000', 'created_timestamp' => '2', 'last_modified_timestamp' => '2'),
	);

	$codes = function ($sorted) {
		return implode(',', array_map(function ($row) { return $row['code']; }, $sorted));
	};

	pg_assert_same('BBB,AAA,CCC', $codes(pg_currency_list_sort($rows, array(array('sort' => 'name', 'order' => 'asc')))), 'the name sorts by its label ("US Dollar"), not the stored "US Dolar"');
	pg_assert_same('CCC,BBB,AAA', $codes(pg_currency_list_sort($rows, array(array('sort' => 'symbol', 'order' => 'asc')))), 'the symbol sorts by its character, not the entity');
	pg_assert_same('CCC,AAA,BBB', $codes(pg_currency_list_sort($rows, array(array('sort' => 'exchange_rate', 'order' => 'desc')))), 'a rate sorts by number, descending');
	pg_assert_same('CCC,BBB,AAA', $codes(pg_currency_list_sort($rows, array(array('sort' => 'last_modified', 'order' => 'desc'), array('sort' => 'created', 'order' => 'asc')))), 'a tie falls to the next level');
	pg_assert_same('AAA,BBB,CCC', $codes(pg_currency_list_sort($rows, array(array('sort' => 'unknown', 'order' => 'asc')))), 'an unknown code keeps the order');
}
