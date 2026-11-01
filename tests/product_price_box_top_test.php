<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the product screen's money boxes (unit price, compare-at price,
 * extra shipping cost): each takes the largest amount its INT column holds
 * (21.474.836,47, 13 characters with the mask's grouping), and the save
 * refuses an amount above that instead of letting MySQL cut it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/product_builder.php');

function test_product_price_above_column_top()
{
	foreach (array('', '0', '9.999.999,99', '21.474.836,47', '21,474,836.47', '21474836,47', '₺15,00') as $fits) {
		pg_assert_same(false, pg_pb_price_above_column_top($fits), 'fits: ' . $fits);
	}

	foreach (array('21.474.836,48', '25000000', '25.000.000,00', '2147483647', '99999999999999999999') as $too_large) {
		pg_assert_same(true, pg_pb_price_above_column_top($too_large), 'too large: ' . $too_large);
	}
}

// The boxes hold 13 characters, and the save checks the unit price and the
// extra shipping cost as it already checked the compare-at price.
function test_product_price_boxes_take_the_column_top()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/product_builder.php');

	foreach (array('price', 'compare_at_price', 'extra_shipping_cost') as $name) {
		$at = strpos($source, 'name="' . $name . '" id="' . $name . '"');
		pg_assert_true($at !== false, 'box found: ' . $name);
		pg_assert_contains('maxlength="13"', substr($source, (int) $at, 200), 'room for 21.474.836,47: ' . $name);
	}

	$validate = substr($source, (int) strpos($source, 'function pg_pb_validate_product_post('), 6000);

	pg_assert_contains("pg_pb_price_above_column_top(isset(\$_POST['price'])", $validate, 'unit price checked');
	pg_assert_contains("pg_pb_price_above_column_top(isset(\$_POST['extra_shipping_cost'])", $validate, 'extra shipping cost checked');
}
