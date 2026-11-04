<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the container form (includes/forms/container_form.php): which
 * posted length, width and height a container may be saved with, and the
 * wide edit layout of its create and edit screens.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/container_form.php');

function test_container_dimension_must_be_a_positive_number()
{
	foreach (array('12', '0.5', '12.25', ' 3 ', 7, 1.5) as $value) {
		pg_assert_true(pg_container_dimension_positive($value), var_export($value, true) . ' is kept');
	}

	// The columns are unsigned decimals under a non-strict sql_mode: a
	// negative or non-numeric value was stored as 0, a container no item
	// fits in.
	foreach (array('0', '0.0', '-5', '-0.1', 'abc', '', '1e', array('3'), null) as $value) {
		pg_assert_same(false, pg_container_dimension_positive($value), var_export($value, true) . ' is refused');
	}
}

function test_container_number_check_holds_values_to_their_columns()
{
	$dimension = pg_container_dimension_max();

	// Length, width and height: decimal(12,4) unsigned, greater than zero.
	foreach (array('12', '0.0001', '99999999.9999', ' 3 ') as $value) {
		pg_assert_same('', pg_container_number_check($value, $dimension, true), var_export($value, true) . ' is a dimension');
	}
	foreach (array('0', '0.00004', '-2', 'abc', '') as $value) {
		pg_assert_same('number', pg_container_number_check($value, $dimension, true), var_export($value, true) . ' is not a dimension');
	}
	foreach (array('1000000000', '99999999.99996') as $value) {
		pg_assert_same('max', pg_container_number_check($value, $dimension, true), var_export($value, true) . ' is above the column');
	}

	// Weight: zero or more, blank allowed by the caller; a non-strict
	// sql_mode stored -2 and "abc" as 0 with "The container has been saved".
	pg_assert_same('', pg_container_number_check('0', $dimension, false), '0 is a weight');
	pg_assert_same('', pg_container_number_check('2.25', $dimension, false), '2.25 is a weight');
	pg_assert_same('number', pg_container_number_check('-2', $dimension, false), '-2 is not a weight');
	pg_assert_same('number', pg_container_number_check('abc', $dimension, false), 'abc is not a weight');

	// Cost: int(10) unsigned in cents.
	$cost = pg_container_cost_max();
	pg_assert_same('', pg_container_number_check('42949672.95', $cost, false), 'the largest cost is kept');
	pg_assert_same('', pg_container_number_check('11,25', $cost, false), 'a comma decimal is read');
	pg_assert_same('max', pg_container_number_check('99999999', $cost, false), 'a cost above the column is refused');
	pg_assert_same('number', pg_container_number_check('-5', $cost, false), 'a negative cost is refused');
}

function test_container_measure_is_formatted_without_trailing_zeros()
{
	pg_assert_same('9.5', pg_container_format_measure('9.5000'), 'trailing zeros go');
	pg_assert_same('12', pg_container_format_measure('12.0000'), 'a whole number has no decimal point');
	pg_assert_same('11.811', pg_container_format_measure('11.8110'), 'four places are kept');
	pg_assert_same('1,234.5', pg_container_format_measure('1234.5'), 'thousands are grouped');
}

function test_container_figure_is_the_stored_decimal()
{
	pg_assert_same('2.5000', pg_container_figure('2,5'), 'a decimal comma is read');
	pg_assert_same('11.8110', pg_container_figure('11.811'), 'four places');
	pg_assert_same('0', pg_container_figure(''), 'blank is 0');
}

function test_container_form_uses_the_wide_edit_layout()
{
	if (!defined('BASE_CURRENCY_SYMBOL')) {
		define('BASE_CURRENCY_SYMBOL', '$');
	}

	$liveform = new class {
		public function get_field_value($name)
		{
			return array('name' => 'Box <A>', 'enabled' => 1, 'length' => '10', 'width' => '8', 'height' => '4')[$name] ?? '';
		}

		public function check_field_error($name)
		{
			return false;
		}
	};

	$html = pg_container_form_layout(pg_container_form_cards($liveform, pg_container_form_status_row('Last Modified', 'today')), '<button>Save</button>');

	$form = strpos($html, 'lg:col-8 2xl:col-9 order-1');
	$status = strpos($html, 'lg:col-4 2xl:col-3 order-2');
	$bar = strpos($html, 'col-12 order-3 position-sticky');
	pg_assert_true(($form !== false) && ($status !== false) && ($bar !== false), 'form column, status column and save bar are drawn');
	pg_assert_true(($form < $status) && ($status < $bar), 'the status column follows the form column in source order');
	pg_assert_contains('class="position-sticky" style="top:3.3rem;"', $html, 'the status box sticks below the top bar');
	pg_assert_true(strpos($html, 'id="enabled"') > $status, 'Enabled sits in the status column');
	pg_assert_true(strpos($html, 'id="name"') < $status, 'the name stays in the form column');
	pg_assert_contains('id="container_summary_dimensions"', $html, 'the Summary shows the dimensions');
	pg_assert_contains('Last Modified', $html, 'the extra status row is printed');
	pg_assert_contains('Box &lt;A&gt;', $html, 'the name is escaped');
	pg_assert_true(strpos($html, '<button>Save</button>') > $bar, 'the buttons sit in the save bar');
}
