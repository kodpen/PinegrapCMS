<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * An invoice line box refused with mark_error() comes back with what was
 * typed, marked invalid, instead of empty (a later save wrote it as 0).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!class_exists('liveform', false)) {
	require_once(PG_FUNCTIONS_DIR . '/liveform.class.php');
}
require_once(PG_FUNCTIONS_DIR . '/includes/erp/invoice_form.php');

function test_erp_invoice_form_line_row_keeps_refused_value()
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array();

	$form = new liveform('erp_line_row_test');
	$form->mark_error('lines[1][tax_rate]', 'Line 2: VAT rate must be between 0 and 100.');

	$line = array(
		'product_id' => 0, 'product_name' => '', 'description' => 'Box', 'quantity' => '2',
		'unit_code' => 'C62', 'unit_price' => '10', 'offer_id' => 0, 'offer_discount_rate' => '',
		'discount_rate' => '', 'tax_rate' => '150', 'tax2_rate' => '', 'withholding_code' => '', 'withholding_rate' => '');
	$html = erp_invoice_form_line_row($form, 1, $line);

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}

	pg_assert_true(preg_match('/<input[^>]*name="lines\[1\]\[tax_rate\]"[^>]*>/', $html, $match) === 1, 'the VAT box is drawn');
	pg_assert_contains('value="150"', $match[0], 'the refused box keeps what was typed');
	pg_assert_contains('is-invalid', $match[0], 'the refused box is marked invalid');
	pg_assert_true(preg_match('/<input[^>]*name="lines\[1\]\[quantity\]"[^>]*>/', $html, $match) === 1, 'the quantity box is drawn');
	pg_assert_contains('value="2"', $match[0], 'an unrefused box keeps its value');
}
