<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of the e-document registry
 * (includes/erp/edoc/registry.php): what the provider log masks.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/registry.php');

// A provider's own record ids stay readable in the log: Paraşüt's are ten
// digits, as long as a VKN.
function test_erp_edoc_mask_keeps_record_ids()
{
	$response = '{"data":{"id":"1103928198","type":"sales_invoices","attributes":{"net_total":"107.94"},'
		. '"relationships":{"contact":{"data":{"id":"1073906281","type":"contacts"}}}}}';
	$masked = erp_edoc_mask($response);
	pg_assert_contains('"id":"1103928198"', $masked, 'the invoice id');
	pg_assert_contains('"id":"1073906281"', $masked, 'the related card id');

	$request = '{"vat_withholding_params":[{"detail_id":1104000123,"vat_withholding_code":"601"}],"Id": 12345678901}';
	$masked = erp_edoc_mask($request);
	pg_assert_contains('"detail_id":1104000123', $masked, 'a line id the request carries');
	pg_assert_contains('"Id": 12345678901', $masked, 'an id key in any case');
}

// Tax and identity numbers stay masked wherever they appear.
function test_erp_edoc_mask_hides_personal_numbers()
{
	$masked = erp_edoc_mask('{"id":"1073906281","attributes":{"tax_number":"3130557669","name":"DSM Grup","tax_id":"3130557669"}}');
	pg_assert_contains('"tax_number":"***"', $masked, 'a tax number key');
	pg_assert_contains('"name":"***"', $masked, 'a name');
	pg_assert_false(strpos($masked, '3130557669') !== false, 'no VKN under any key');

	$masked = erp_edoc_mask('{"errors":[{"detail":"10000000146 numarası geçersiz, VKN 3130557669 kayıtlı değil"}]}');
	pg_assert_false(strpos($masked, '10000000146') !== false, 'no TCKN in words');
	pg_assert_false(strpos($masked, '3130557669') !== false, 'no VKN in words');

	pg_assert_same('Authorization: Bearer ***', erp_edoc_mask('Authorization: Bearer abc.def.ghi'));
}
