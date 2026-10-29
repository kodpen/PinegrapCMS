<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that three panel layouts fit a phone: the Shipping Method variable
 * base rate tiers wrap (a Bootstrap 6 .input-group never wraps), the sticky
 * save bar lays its actions out two per row, and the Login Region code
 * textareas carry no fixed width.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function phone_width_rows_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_phone_width_variable_base_rate_tiers_wrap()
{
	$source = phone_width_rows_test_source('includes/forms/shipping_method_form.php');

	pg_assert_true(preg_match('/\$output_variable_base_rates \.= \'(.*?)\';\n/s', $source, $loop) === 1, 'tier loop found');
	$tier = $loop[1];

	// The amount and the subtotal condition are two groups in a wrapping row.
	pg_assert_true(preg_match('/<div class="d-flex flex-wrap gap-1">/', $tier) === 1, 'tier row wraps');
	pg_assert_same(2, preg_match_all('/<div class="input-group w-auto flex-grow-1">/', $tier), 'two groups that share the row');

	// One style attribute per input, so text-align survives the parser.
	preg_match_all('/<input [^>]*\/>/', $tier, $inputs);
	pg_assert_same(2, count($inputs[0]), 'two inputs per tier');
	foreach ($inputs[0] as $input) {
		pg_assert_same(1, substr_count($input, 'style='), 'one style attribute');
		pg_assert_true(strpos($input, 'text-align: right') !== false, 'amount aligned right');
	}
}

function test_phone_width_save_bar_two_actions_per_row()
{
	$css = phone_width_rows_test_source('assets/css/backend.src.css');

	pg_assert_true(preg_match('/@media \(max-width: 575\.98px\) \{\s*nav\.buttons\.navigation \{.*?\n\}/s', $css, $block) === 1, 'phone block found');
	$phone = $block[0];

	pg_assert_true(strpos($phone, 'flex-direction: column') === false, 'actions are not one per row');
	pg_assert_true(preg_match('/\nnav\.buttons\.navigation > \.container \{[^}]*flex-wrap: wrap;/', $css) === 1, 'bar wraps');
	pg_assert_true(preg_match('/nav\.buttons\.navigation \.btn \{[^}]*flex: 1 1 calc\(50% - 0\.25rem\);\s*min-width: 0;\s*white-space: normal;/', $phone) === 1, 'two actions per row, long labels wrap');
}

function test_phone_width_login_region_code_fields_have_no_fixed_width()
{
	$source = phone_width_rows_test_source('includes/forms/login_region_form.php');

	pg_assert_same(0, substr_count($source, 'width: 600px'), 'no fixed width');
	pg_assert_same(4, substr_count($source, "'style'=>'height: 200px'"), 'four code fields keep their height');
}
