<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the field width rules of the add and edit screens in look
 * A: the card's field grid has 16 px gaps, and a narrow field alone in its
 * row keeps the row while pg-field-span-N caps its control to N of the 12
 * columns. Each screen group sets its own fields' spans.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function look_field_widths_test_css()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
}

function test_field_grid_has_sixteen_pixel_gaps()
{
	pg_assert_true((bool) preg_match('/body\.pg-measure-edit \.order-1 \.card-body \.row \{\s*--bs-gutter-x: 16px;/', look_field_widths_test_css()), 'the field grid of an edit screen card has 16 px gaps');
}

function test_a_field_alone_in_its_row_spans_its_control_only()
{
	$css = look_field_widths_test_css();

	foreach (array(3, 4, 6) as $span) {
		pg_assert_true(strpos($css, '.pg-field-span-' . $span . ' > :not(.form-label, .form-text, .invalid-feedback, legend) {' . "\n" . '    max-width: calc((100% + var(--bs-gutter-x)) * ' . $span . ' / 12 - var(--bs-gutter-x));') !== false, 'a field alone in its row spans ' . $span . ' columns with its control only');
	}
}
