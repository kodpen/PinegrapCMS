<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that panel layouts fit a phone: the Shipping Method variable base
 * rate tiers wrap (a Bootstrap 6 .input-group never wraps), the Login Region
 * code textareas carry no fixed width, and rows that put buttons beside a
 * sentence (order documents card, Edit User sign-in rows) wrap, since a
 * Bootstrap 6 .btn never wraps its label.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_flow.php');

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


function test_phone_width_login_region_code_fields_have_no_fixed_width()
{
	$source = phone_width_rows_test_source('includes/forms/login_region_form.php');

	pg_assert_same(0, substr_count($source, 'width: 600px'), 'no fixed width');
	pg_assert_same(4, substr_count($source, "'style'=>'height: 200px'"), 'four code fields keep their height');
}


// A step with a print button and an action, as the e-Archive step waiting for
// the tax office shows: the row wraps, the text keeps a basis, and the buttons
// share one wrapping box that drops under the text on a phone.
function test_phone_width_order_flow_step_buttons_drop_under_the_text()
{
	$step = array(
		'label' => 'Invoice', 'state' => 'waiting', 'title' => 'EAR2026000000001', 'facts' => array(), 'links' => array(), 'action' => 'invoice',
		'reason' => 'Waiting for the tax office.',
		'print' => array('label' => 'Print e-Archive', 'url' => 'print.php?id=1'),
	);
	$html = erp_order_flow_card(17, array('steps' => array('invoice' => $step), 'next' => 'invoice'));

	pg_assert_true(preg_match('/<li class="list-group-item d-flex flex-wrap gap-5 /', $html) === 1, 'step row wraps');
	pg_assert_contains('<div class="flex-grow-1 min-w-0" style="flex-basis: 10rem">', $html, 'text column keeps a basis');
	pg_assert_true(preg_match('/<div class="d-flex flex-wrap gap-3">\s*<div class="text-nowrap"><a class="btn[^"]*" href="print\.php\?id=1"/', $html) === 1, 'buttons share one wrapping box');
	pg_assert_contains('value="invoice"', $html, 'action still offered');
}

function test_phone_width_order_flow_step_without_buttons_has_no_button_box()
{
	$step = array('label' => 'Invoice', 'state' => 'done', 'title' => '', 'facts' => array(), 'links' => array(), 'action' => '', 'reason' => '');
	$html = erp_order_flow_card(17, array('steps' => array('invoice' => $step), 'next' => ''));

	pg_assert_same(0, substr_count($html, '<div class="d-flex flex-wrap gap-3">'), 'no empty button box');
}

function test_phone_width_edit_user_sign_in_rows_wrap()
{
	$source = phone_width_rows_test_source('edit_user.php');

	pg_assert_same(0, substr_count($source, 'd-flex align-items-start gap-5 border rounded-4 p-5"'), 'no row without wrap');
	pg_assert_same(2, substr_count($source, '<div class="d-flex flex-wrap align-items-start gap-5 border rounded-4 p-5">'), 'identity and two-step rows wrap');
	pg_assert_same(2, substr_count($source, '<div class="flex-grow-1 min-w-0" style="flex-basis: 10rem">'), 'their text columns keep a basis');

	pg_assert_contains('<div class="alert theme-danger d-flex flex-wrap align-items-center gap-3 py-3">', $source, 'lock alert wraps');
	pg_assert_contains('<span class="flex-grow-1 min-w-0" style="flex-basis: 10rem"><b>', $source, 'lock sentence keeps a basis');
}
