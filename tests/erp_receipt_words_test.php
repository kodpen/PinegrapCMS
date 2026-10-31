<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the words the accountant pack and the receipts CSV use
 * (includes/erp/accountant.php, includes/erp/export.php) and the automatic
 * invoice description (includes/settings/accounting.php): a receipt is a
 * Receipt, not the designer's Collection; the method column is Payment
 * method, not the short Method key; the description agrees with the
 * marketplace switch below it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_erp_receipt_words_in_pack_and_csv()
{
	$pack = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/accountant.php');
	$csv = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/export.php');

	pg_assert_contains("'collection' => lang('Receipt')", $pack, 'pack: a receipt');
	pg_assert_contains("lang('Payment method'), 'type' => 'text'", $pack, 'pack: the method column');
	pg_assert_false(strpos($pack, "lang('Collection')") !== false, 'pack: no Collection key');
	pg_assert_false(strpos($pack, "lang('Method')") !== false, 'pack: no Method key');
	pg_assert_contains("'collection' => lang('Receipt')", $csv, 'CSV: a receipt');
	pg_assert_false(strpos($csv, "lang('Collection')") !== false, 'CSV: no Collection key');
}

function test_erp_auto_invoice_description_names_the_marketplace_switch()
{
	$card = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/accounting.php');

	pg_assert_contains('marketplace orders from their screen unless the switch below is on', $card, 'the description agrees with the switch');
}
