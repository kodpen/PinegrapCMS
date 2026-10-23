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
