<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the exemption code of a zero-rated invoice line
 * (includes/erp/rules.php): which code a line takes - its own, its
 * product's, the store's default - and the refusal an e-document send gives
 * before any request when a line at 0% VAT is left without one.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/rules.php');

// A line is zero-rated when its VAT rate is 0, however the column writes it.
function test_erp_vat_line_zero_rated()
{
	pg_assert_true(erp_vat_line_zero_rated(array('tax_rate' => '0.000')));
	pg_assert_true(erp_vat_line_zero_rated(array('tax_rate' => 0)));
	pg_assert_true(erp_vat_line_zero_rated(array()), 'a line without a rate');
	pg_assert_false(erp_vat_line_zero_rated(array('tax_rate' => '20.000')));
	pg_assert_false(erp_vat_line_zero_rated(array('tax_rate' => '1')));
}

// The line's own code first, then its product's, then the store's default;
// a line that carries VAT takes none, whatever is set.
function test_erp_vat_exemption_code_pick()
{
	$zero = array('tax_rate' => '0.000', 'vat_exemption_code' => '');

	pg_assert_same('301', erp_vat_exemption_code_pick(array('vat_exemption_code' => '301') + $zero, '302', '351'), 'the line\'s own code');
	pg_assert_same('302', erp_vat_exemption_code_pick($zero, '302', '351'), 'the product\'s code');
	pg_assert_same('351', erp_vat_exemption_code_pick($zero, '', '351'), 'the store default');
	pg_assert_same('351', erp_vat_exemption_code_pick(array('vat_exemption_code' => '  ') + $zero, ' ', ' 351 '), 'blanks are no code');
	pg_assert_same('', erp_vat_exemption_code_pick($zero, '', ''), 'none anywhere');
	pg_assert_same('', erp_vat_exemption_code_pick(array('tax_rate' => '20.000', 'vat_exemption_code' => '301'), '302', '351'), 'a line with VAT');
}

// A 0% line with no code stops the send, named by its number and text, and
// the refusal says where a code comes from. A line with its code (its own,
// its product's or the default, already resolved onto the line) and a line
// that carries VAT pass.
function test_erp_vat_exemption_missing_refusal()
{
	$lines = array(
		array('line_no' => '1', 'description' => 'Book', 'tax_rate' => '20.000', 'vat_exemption_code' => ''),
		array('line_no' => '2', 'description' => 'Gift card', 'tax_rate' => '0.000', 'vat_exemption_code' => ''),
		array('line_no' => '3', 'description' => 'Export goods', 'tax_rate' => '0.000', 'vat_exemption_code' => '301'),
	);

	$refusal = erp_vat_exemption_missing_refusal($lines);
	pg_assert_contains('Line 2 (Gift card)', $refusal, 'the line is named');
	pg_assert_false(strpos($refusal, 'Line 1') !== false, 'a line with VAT needs no code');
	pg_assert_false(strpos($refusal, 'Line 3') !== false, 'a line with a code passes');
	pg_assert_contains('Accounting rules', $refusal, 'where the store default is set');
	pg_assert_contains('product', $refusal, 'or the product\'s own code');
	pg_assert_contains('nothing was sent', $refusal);

	unset($lines[1]);
	pg_assert_same('', erp_vat_exemption_missing_refusal($lines), 'every 0% line has its code');
	pg_assert_same('', erp_vat_exemption_missing_refusal(array()), 'no lines');

	$two = erp_vat_exemption_missing_refusal(array(
		array('line_no' => '0', 'description' => 'First', 'tax_rate' => '0.000', 'vat_exemption_code' => ''),
		array('line_no' => '0', 'description' => str_repeat('x', 100), 'tax_rate' => '0', 'vat_exemption_code' => ' '),
	));
	pg_assert_contains('Line 1 (First)', $two, 'numbered by position when line_no is not set');
	pg_assert_contains('Line 2 (' . str_repeat('x', 60) . '…)', $two, 'a long text is cut');
}
