<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the field widths of the add and edit screens in look A:
 * the card's field grid has 16 px gaps, and each field spans as many of its
 * 12 columns as its content asks (a price, a rate or a count 3, a code 4, a
 * select 6, a name or a text the whole row), from md up; a narrow field alone
 * in its row keeps the row and spans its control only.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function look_field_widths_test_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

/** The class list of the column holding the field with this id. */
function look_field_widths_test_column($source, $id)
{
	$at = strpos($source, 'id="' . $id . '"');

	if ($at === false) {
		$at = strpos($source, "'" . $id . "'");
	}

	if ($at === false) {
		return null;
	}

	$before = substr($source, 0, $at);

	if (!preg_match_all('/class="(col-12(?: [\w:-]+)*)/', $before, $matches)) {
		return null;
	}

	return end($matches[1]);
}

function test_field_grid_has_sixteen_pixel_gaps()
{
	$css = look_field_widths_test_source('assets/css/backend.src.css');

	pg_assert_true((bool) preg_match('/body\.pg-measure-edit \.order-1 \.card-body \.row \{\s*--bs-gutter-x: 16px;/', $css), 'the field grid of an edit screen card has 16 px gaps');

	foreach (array(3, 4, 6) as $span) {
		pg_assert_true(strpos($css, '.pg-field-span-' . $span . ' > :not(.form-label, .form-text, .invalid-feedback, legend) {' . "\n" . '    max-width: calc((100% + var(--bs-gutter-x)) * ' . $span . ' / 12 - var(--bs-gutter-x));') !== false, 'a field alone in its row spans ' . $span . ' columns with its control only');
	}
}

function test_fields_span_their_content()
{
	$cases = array(
		array('product_builder.php', 'inventory_quantity', 'col-12 pg-field-span-3'),
		array('product_builder.php', 'compare_at_price', 'col-12 pg-field-span-3'),
		array('product_builder.php', 'commission_rate_limit', 'col-12 pg-field-span-3'),
		array('product_builder.php', 'preorder_date', 'col-12 pg-field-span-3'),
		array('product_builder.php', 'payment_period', 'col-12 md:col-6 my-1'),
		array('product_builder.php', 'address_name', 'col-12 my-3'),
		array('product_builder.php', 'required_product', 'col-12 pg-field-span-6 my-3'),
	);

	foreach ($cases as $case) {
		list($file, $id, $expected) = $case;
		$column = look_field_widths_test_column(look_field_widths_test_source($file), $id);
		pg_assert_same($expected, $column, $file . ' ' . $id . ' spans its content');
	}
}
