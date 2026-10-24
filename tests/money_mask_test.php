<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel's money boxes (includes/fn/ecommerce.php): the
 * currency input mask takes the decimal and thousands separators of the
 * panel language (pg_money_mask_attributes(), pg_money_mask_html()), and
 * what the mask posts in either language reads back as the amount typed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_money_mask_takes_the_language_separators()
{
	$turkish = pg_money_mask_attributes(array('decimal' => ',', 'thousands' => '.'));

	pg_assert_same('currency', $turkish['data-inputmask-alias'], 'the currency mask');
	pg_assert_same(',', $turkish['data-inputmask-radixPoint'], 'a Turkish decimal comma');
	pg_assert_same('.', $turkish['data-inputmask-groupSeparator'], 'a Turkish thousands dot');
	pg_assert_same('2', $turkish['data-inputmask-digits'], 'two decimals');
	pg_assert_same('false', $turkish['data-inputmask-digitsOptional'], 'always shown');

	$english = pg_money_mask_attributes(array('decimal' => '.', 'thousands' => ','));

	pg_assert_same('.', $english['data-inputmask-radixPoint'], 'an English decimal point');
	pg_assert_same(',', $english['data-inputmask-groupSeparator'], 'an English thousands comma');

	$plain = pg_money_mask_attributes(array('decimal' => ',', 'thousands' => ''));

	pg_assert_same('', $plain['data-inputmask-groupSeparator'], 'a language without grouping');
}

function test_money_mask_html()
{
	$html = pg_money_mask_html(array('decimal' => ',', 'thousands' => "\u{00A0}"));

	pg_assert_contains(' data-inputmask-alias="currency"', $html, 'the alias');
	pg_assert_contains(' data-inputmask-radixPoint=","', $html, 'the decimal comma');
	pg_assert_contains(' data-inputmask-groupSeparator="' . "\u{00A0}" . '"', $html, 'a no-break space groups');
	pg_assert_same(pg_money_mask_html(array('decimal' => '"', 'thousands' => '<')), ' data-inputmask-alias="currency" data-inputmask-radixPoint="&quot;" data-inputmask-groupSeparator="&lt;" data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"', 'every value escaped');
}

// What the mask posts reads back as typed, in either language.
function test_money_mask_posted_values_read_back()
{
	pg_assert_same('12500', pg_normalize_amount('12.500,00'), 'Turkish twelve thousand five hundred');
	pg_assert_same('1234.56', pg_normalize_amount('1.234,56'), 'Turkish grouping and decimals');
	pg_assert_same('0.05', pg_normalize_amount('0,05'), 'Turkish five kuruş');
	pg_assert_same('12500', pg_normalize_amount('12,500.00'), 'English grouping');
	pg_assert_same('1234.56', pg_normalize_amount('1234,56'), 'what pg_format_input_amount() fills on a Turkish panel');
}

// The exchange rate box keeps the five decimals currencies.exchange_rate
// (decimal(10,5)) and the rate updater store, so a manual save does not
// round 0.02853 to 0.03.
function test_money_mask_exchange_rate_five_decimals()
{
	$separators = array('decimal' => ',', 'thousands' => '.');
	$rate = pg_money_mask_attributes($separators, 5);

	pg_assert_same('5', $rate['data-inputmask-digits'], 'five decimals for a rate');
	pg_assert_same('false', $rate['data-inputmask-digitsOptional'], 'always shown');
	pg_assert_same('2', pg_money_mask_attributes($separators)['data-inputmask-digits'], 'money stays at two decimals');
	pg_assert_contains(' data-inputmask-digits="5"', pg_money_mask_html($separators, 5), 'the tag form takes the decimals too');

	$decimal = pg_number_separators()['decimal'];

	pg_assert_same('0' . $decimal . '02853', pg_format_input_amount('0.02853', 5), 'a rate fills with five decimals');
	pg_assert_same('16000' . $decimal . '00000', pg_format_input_amount('16000', 5), 'no grouping in the filled rate');
	pg_assert_same('1234' . $decimal . '56', pg_format_input_amount('1234.56'), 'money still fills with two');
}

function test_money_mask_exchange_rate_posted_values_read_back()
{
	pg_assert_same('0.02853', pg_normalize_amount('0,02853'), 'Turkish rate below one');
	pg_assert_same('16000', pg_normalize_amount('16.000,00000'), 'Turkish grouped rate');
	pg_assert_same('16000.12345', pg_normalize_amount('16,000.12345'), 'English grouped rate');
}
