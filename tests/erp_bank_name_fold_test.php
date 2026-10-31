<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the account name a bank statement line is suggested by
 * (includes/erp/bank_import.php): the line's words and the account's name are
 * folded alike by erp_bank_name_fold(), so a name the bank wrote in capitals
 * (İ, I) or in ASCII letters still names the account
 * (erp_bank_line_names()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bank_import.php');

// İ, I, ı and i are one letter; ş, ç, ğ, ö and ü fold to their ASCII letter.
function test_erp_bank_name_fold_turkish_letters()
{
	pg_assert_same('eft pg-test musteri a.s. odeme', erp_bank_name_fold('EFT PG-TEST MÜŞTERİ A.Ş. ÖDEME'));
	pg_assert_same('pg-test musteri a.s.', erp_bank_name_fold('pg-test Müşteri A.Ş.'));
	pg_assert_same('ayse yilmaz', erp_bank_name_fold('AYŞE  YILMAZ'));
	pg_assert_same('ayse yilmaz', erp_bank_name_fold('Ayşe Yılmaz'));
	pg_assert_same('cag dis', erp_bank_name_fold('ÇAĞ DIŞ'));
}

// A name in capitals, in ASCII spelling or with doubled spaces still names the account.
function test_erp_bank_line_names_capitals_and_ascii()
{
	pg_assert_true(erp_bank_line_names('EFT PG-TEST MÜŞTERİ A.Ş. ÖDEME', 'pg-test Müşteri A.Ş.'), 'capitals with İ');
	pg_assert_true(erp_bank_line_names('GELEN FAST AYSE YILMAZ', 'Ayşe Yılmaz'), 'ASCII spelling with I');
	pg_assert_true(erp_bank_line_names('HAVALE DIS TICARET  LTD', ' Dış Ticaret Ltd '), 'trimmed name, doubled space');
}

// A short name, an empty line or another name gives no suggestion.
function test_erp_bank_line_names_refusals()
{
	pg_assert_false(erp_bank_line_names('EFT ALI VELI', 'Ali'), 'a name under six letters');
	pg_assert_false(erp_bank_line_names('', 'pg-test Müşteri A.Ş.'), 'no words');
	pg_assert_false(erp_bank_line_names('EFT BAŞKA FİRMA', 'pg-test Müşteri A.Ş.'), 'another name');
}

// The line's stored key keeps mb_strtolower(): stored hashes depend on it.
function test_erp_bank_line_key_unchanged()
{
	pg_assert_same('3|2026-10-31|30000|' . mb_strtolower('EFT MÜŞTERİ'), erp_bank_line_key(3, '2026-10-31', 30000, 'EFT MÜŞTERİ'));
}
