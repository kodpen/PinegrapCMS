<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a sales quote's line amounts (erp_quote_line_amounts(),
 * includes/erp/quotes.php) as the guest page and the panel print them, and
 * for the wording the quote screens use: the editor's preview note names the
 * document being saved, and the signature note uses one term everywhere.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/quotes.php');

// A line's discount, VAT rate, VAT and total come from its stored amounts:
// the total is the line after its discount with its VAT added.
function test_erp_quote_line_amounts_discount_and_vat()
{
	$amounts = erp_quote_line_amounts(array('line_total' => 375150, 'discount_amount' => 37515, 'tax_rate' => '20.000', 'tax_total' => 67527));

	pg_assert_same(37515, $amounts['discount']);
	pg_assert_same(20.0, $amounts['tax_rate']);
	pg_assert_same(67527, $amounts['tax']);
	pg_assert_same(405162, $amounts['total']);
}

// Lines stored without a discount or VAT read as zero.
function test_erp_quote_line_amounts_missing_fields_are_zero()
{
	$amounts = erp_quote_line_amounts(array('line_total' => 99999));

	pg_assert_same(0, $amounts['discount']);
	pg_assert_same(0.0, $amounts['tax_rate']);
	pg_assert_same(99999, $amounts['total']);
}

// The line totals add up to the quote's total before withholding:
// subtotal - discount + VAT.
function test_erp_quote_line_amounts_add_up_to_the_totals()
{
	$lines = array(
		array('line_total' => 375150, 'discount_amount' => 37515, 'tax_rate' => 20, 'tax_total' => 67527),
		array('line_total' => 99999, 'discount_amount' => 0, 'tax_rate' => 20, 'tax_total' => 20000),
	);
	$sum = 0;
	foreach ($lines as $line) {
		$sum += erp_quote_line_amounts($line)['total'];
	}

	pg_assert_same(475149 - 37515 + 87527, $sum);
}

// The guest page prints each line's discount and VAT rate as the panel does,
// and says what the line total holds.
function test_erp_quote_guest_page_prints_discount_and_vat_columns()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_quote.php');

	pg_assert_contains('erp_quote_line_amounts(', $source);
	pg_assert_contains("lang('Discount')", $source);
	pg_assert_contains("erp_tax_label('percent')", $source);
	pg_assert_contains('erp_percent_text(', $source);
	pg_assert_contains("lang('Line totals are after the discount and include VAT.')", $source);
	pg_assert_false(strpos($source, "\$base = (int) \$line['line_total']") !== false, 'the guest page has no line arithmetic of its own');
}

// The panel's read-only quote reads its line amounts from the same function.
function test_erp_quote_panel_view_uses_the_line_amounts()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_erp_quote.php');

	pg_assert_contains('erp_quote_line_amounts(', $source);
}

// The editor's preview note names the document by its type.
function test_erp_invoice_form_preview_note_by_document_type()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/invoice_form.php');

	pg_assert_contains("lang('A preview; the figures are worked out again when the quote is saved.')", $source);
	pg_assert_contains("lang('A preview; the figures are worked out again when the purchase order is saved.')", $source);
	pg_assert_contains("lang('A preview; the figures are worked out again when the invoice is saved.')", $source);
}

// The new notes and the signature notes are translated, and every signature
// note calls the signature by the same Turkish term.
function test_erp_quote_wording_turkish_terms()
{
	$tr = array();
	foreach (glob(PG_FUNCTIONS_DIR . '/includes/local/tr/*.json') as $file) {
		$tr += (array) json_decode((string) file_get_contents($file), true);
	}

	foreach (array(
		'A preview; the figures are worked out again when the quote is saved.',
		'A preview; the figures are worked out again when the purchase order is saved.',
		'Line totals are after the discount and include VAT.',
	) as $key) {
		pg_assert_true(isset($tr[$key]), $key);
	}

	pg_assert_contains('teklif', (string) ($tr['A preview; the figures are worked out again when the quote is saved.'] ?? ''));

	foreach (array(
		'An ordinary electronic signature drawn on the screen; it is not a qualified electronic signature.',
		'By signing you accept this quote as shown. An ordinary electronic signature drawn on the screen; it is not a qualified electronic signature.',
		'This is an ordinary electronic signature. It is not a qualified electronic signature and does not carry the legal effect of a handwritten signature.',
	) as $key) {
		$value = mb_strtolower((string) ($tr[$key] ?? ''));
		pg_assert_true(preg_match('/\\badi (bir )?elektronik imza/u', $value) === 1, $key . ': ' . $value);
		pg_assert_false(strpos($value, 'basit') !== false, $key . ': ' . $value);
	}
}
