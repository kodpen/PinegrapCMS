<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of the e-document registry
 * (includes/erp/edoc/registry.php): what the provider log masks, which
 * capabilities a driver declares.
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

// A provider's error keeps its title, so the log says what went wrong. A
// title outside the error list (a card's) is still masked, and so are the
// names and the numbers inside the error itself.
function test_erp_edoc_mask_keeps_error_titles()
{
	$masked = erp_edoc_mask('{"errors":[{"title":"Geçersiz vergi numarası","detail":"VKN 3130557669 kayıtlı değil","source":{"pointer":"/data/attributes/tax_number"}}]}');
	pg_assert_contains('"title":"Geçersiz vergi numarası"', $masked, 'the error title');
	pg_assert_contains('"pointer":"/data/attributes/tax_number"', $masked, 'the field it points at');
	pg_assert_false(strpos($masked, '3130557669') !== false, 'no VKN in the detail');

	$masked = erp_edoc_mask('{"errors":[{"title":"10000000146 bulunamadı","name":"Ayşe Kaya","email":"a@b.co"}]}');
	pg_assert_contains('"title":"********** bulunamadı"', $masked, 'a number in the title');
	pg_assert_contains('"name":"***"', $masked, 'a name inside the error');
	pg_assert_contains('"email":"***"', $masked, 'an address inside the error');

	$masked = erp_edoc_mask('{"data":{"attributes":{"title":"DSM Grup"}},"errors":[{"title":"Kayıt bulunamadı"}],"included":[{"attributes":{"title":"Ali Veli"}}]}');
	pg_assert_contains('"attributes":{"title":"***"}},', $masked, 'a card title before the list');
	pg_assert_contains('"errors":[{"title":"Kayıt bulunamadı"}]', $masked, 'the error title between them');
	pg_assert_false(strpos($masked, 'Ali Veli') !== false, 'a card title after the list');

	$masked = erp_edoc_mask('{"errors":[{"title":"Satır [2] hatalı"},{"title":"Eksik alan"}],"title":"DSM Grup"}');
	pg_assert_contains('"title":"Satır [2] hatalı"', $masked, 'a bracket in the title');
	pg_assert_contains('"title":"Eksik alan"', $masked, 'every error in the list');
	pg_assert_contains('}],"title":"***"}', $masked, 'a title after the list');

	// A list nested deeper than one bracket is masked like any other text.
	$masked = erp_edoc_mask('{"errors":[{"title":"Ali Veli","meta":[[1]]}]}');
	pg_assert_contains('"title":"***"', $masked, 'a list it cannot read');

	pg_assert_same('{"title":"***","note":"' . "\x00" . '7' . "\x00" . '"}', erp_edoc_mask('{"title":"x","note":"' . "\x00" . '7' . "\x00" . '"}'), 'a stray marker is left alone');
}

// The UBL button is drawn for a provider that hands the UBL back: İşbaşı
// does, Paraşüt's API gives the PDF alone (its driver refuses 'xml').
function test_erp_edoc_ubl_capability()
{
	pg_assert_true(erp_edoc_has_capability('ubl', 'isbasi'), 'İşbaşı');
	pg_assert_false(erp_edoc_has_capability('ubl', 'parasut'), 'Paraşüt');
	pg_assert_same('Paraşüt hands back the PDF of an e-document, not its UBL.', erp_edoc_parasut_fetch_document(array('id' => 1), 'xml')['error']);
	pg_assert_true(isset(erp_edoc_capability_labels()['ubl']), 'a badge on the settings card');
}
