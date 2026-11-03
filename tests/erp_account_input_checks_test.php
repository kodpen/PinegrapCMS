<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the checks of what is typed on an ERP account: the opening
 * balance amount (includes/erp/money.php), the account-wide discount
 * (includes/erp/price_lists.php), the tax number the card form saves
 * (includes/erp/account_form.php) and the key two tax numbers are matched by.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/accounts.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/account_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/price_lists.php');

// An amount is a sign, digits and separators: letters or an exponent are
// refused instead of being dropped ('12abc' read 12,00 and '1e3' 13,00).
function test_erp_amount_text_valid()
{
	foreach (array('12', '-12,50', '1.250,00', '1250.00', '1 250,00', '0') as $text) {
		pg_assert_true(erp_amount_text_valid($text), 'accepted: ' . $text);
	}

	foreach (array('', '12abc', '1e3', '5-', '--5', '-', ',', 'abc', '12,5TL') as $text) {
		pg_assert_false(erp_amount_text_valid($text), 'refused: ' . $text);
	}
}

// The account discount is digits with one decimal mark from 0 to 100; a
// minus or a typo is refused instead of saving 5 for -5 or 0 for 'abc'.
function test_erp_account_discount_rate_parse()
{
	pg_assert_same(12.5, erp_account_discount_rate_parse('12,5'));
	pg_assert_same(12.5, erp_account_discount_rate_parse(' 12.5 '));
	pg_assert_same(100.0, erp_account_discount_rate_parse('100'));
	pg_assert_same(0.0, erp_account_discount_rate_parse(''));

	foreach (array('-5', 'abc', '101', '1.2.3', '5%', '1e1') as $text) {
		pg_assert_same(null, erp_account_discount_rate_parse($text), 'refused: ' . $text);
	}
}

// The card form saves a Turkish number as its digits, like the API and the
// New account dialog; a number elsewhere stays as typed.
function test_erp_account_form_save_fields_tax_number_digits()
{
	$post = new pg_test_account_input_post(array('tax_number' => ' 734.512.8907 '));

	pg_assert_same('7345128907', erp_account_form_save_fields($post, 'TR', 'TRY', 0, '', '', '')['tax_number']);

	$post = new pg_test_account_input_post(array('tax_number' => 'DE 123.456'));

	pg_assert_same('DE 123.456', erp_account_form_save_fields($post, 'DE', 'EUR', 0, '', '', '')['tax_number']);
}

// Stands in for the liveform: the builder only reads posted values.
class pg_test_account_input_post
{
	public $values = array();

	public function __construct($values)
	{
		$this->values = $values;
	}

	public function get_field_value($name)
	{
		return $this->values[$name] ?? '';
	}
}

// The SQL side of the tax key drops what the PHP key drops for the
// separators a card may hold, so a dotted or spaced number is found.
function test_erp_account_tax_key_sql()
{
	$sql = erp_account_tax_key_sql('tax_number');

	foreach (array("' '", "'.'", "'-'", "'/'") as $separator) {
		pg_assert_true(strpos($sql, $separator) !== false, 'drops ' . $separator);
	}

	pg_assert_true(strpos($sql, 'UPPER(tax_number)') !== false, 'upper case');
}
