<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the ERP line editor and the inbox e-invoice lines: the
 * column, withholding and barcode hints are printed text (not hover-only
 * titles), a barcode with no product gets a visible message, the zone-rate
 * source sits in the line's hint, and the inbox line marks carry their text.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_editor_hints_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_erp_editor_hints_form_prints_hints()
{
	$source = erp_editor_hints_source('includes/erp/invoice_form.php');

	pg_assert_true(strpos($source, '" title="\' . h(lang(\'The store\\\'s campaign') === false, 'the campaign hint is not a th title');
	pg_assert_true(strpos($source, '" title="\' . h(lang(\'Your discount') === false, 'the discount hint is not a th title');
	pg_assert_true(strpos($source, ' title="\' . h(lang(\'Withholding code') === false, 'the withholding column hint is not a th title');
	pg_assert_true(strpos($source, 'title="\' . h(lang(\'The buyer pays part') === false, 'the switch hint is not a wrapper title');
	pg_assert_true(strpos($source, 'title="\' . h(lang(\'Scan a barcode or type') === false, 'the barcode hint is not a wrapper title');
	pg_assert_contains('id="erp_lines_columns_hint"', $source);
	pg_assert_contains('<span id="erp_barcode_hint">', $source);
	pg_assert_contains('<span id="erp_withholding_hint">', $source);
	pg_assert_contains('data-erp-withholding-toggle aria-describedby="erp_withholding_hint"', $source);
	pg_assert_contains('data-erp-barcode aria-describedby="erp_barcode_hint"', $source);
}

function test_erp_editor_hints_script()
{
	$source = erp_editor_hints_source('assets/js/erp_invoice_editor.js');

	pg_assert_true(strpos($source, 'barcodeInput.title') === false, 'the not-found reason is not a title');
	pg_assert_true(strpos($source, 'taxRate.title') === false, 'the zone-rate source is not a title on the labelled box');
	pg_assert_contains('window.pgFieldInvalid(barcodeInput, on, texts.notFound)', $source);
	pg_assert_contains('parts.push(texts.zoneRate)', $source);
}

function test_erp_editor_hints_inbox_marks()
{
	$source = erp_editor_hints_source('erp_inbox_document.php');

	pg_assert_true(strpos($source, 'aria-hidden="true" title="') === false, 'the inbox line marks keep no hover-only title');
	pg_assert_contains('<div class="small fg-emphasis-warning"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>', $source);
	pg_assert_contains('<div class="small fg-secondary"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>', $source);
}
