<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Paraşüt product card a Pinegrap product gets
 * (includes/fn/parasut.php): the attributes of the create request, and, read
 * from the source, that parasut_sync_product() sends them and asks for no
 * separate opening stock.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * The source of one function of includes/fn/parasut.php, from its
 * declaration to the next one.
 *
 * @param string $function
 * @return string  '' when the function is not found
 */
function parasut_product_test_source($function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/parasut.php');
	$start = strpos($source, 'function ' . $function . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", (int) $start + 1);

	return ($end === false) ? substr($source, (int) $start) : substr($source, (int) $start, $end - (int) $start);
}

// A stock-tracked product: the price goes as list_price (Paraşüt keeps no
// price given as unit_price), the opening stock as initial_stock_count, and
// no product_type, which v4 products do not have.
function test_parasut_product_attributes_of_a_tracked_product()
{
	$attributes = parasut_product_attributes(array(
		'name' => 'MUG-1',
		'short_description' => 'Mug',
		'price' => '1050',
		'inventory' => '1',
		'inventory_quantity' => '3',
		'tax_rate' => '20',
	));

	pg_assert_same(array(
		'name' => 'Mug',
		'list_price' => 10.5,
		'currency' => 'TRL',
		'vat_rate' => 20.0,
		'inventory_tracking' => true,
		'initial_stock_count' => 3,
	), $attributes);
	pg_assert_false(array_key_exists('unit_price', $attributes), 'no unit_price');
	pg_assert_false(array_key_exists('product_type', $attributes), 'no product_type');
}

// A product without stock tracking carries no opening stock.
function test_parasut_product_attributes_of_an_untracked_product()
{
	$attributes = parasut_product_attributes(array(
		'name' => 'SERVICE-1',
		'short_description' => '',
		'price' => '1999',
		'inventory' => '0',
		'inventory_quantity' => '5',
		'tax_rate' => null,
	));

	pg_assert_same(array(
		'name' => 'SERVICE-1',
		'list_price' => 19.99,
		'currency' => 'TRL',
		'vat_rate' => 0,
		'inventory_tracking' => false,
	), $attributes);
	pg_assert_false(array_key_exists('unit_price', $attributes), 'no unit_price');
	pg_assert_false(array_key_exists('product_type', $attributes), 'no product_type');
	pg_assert_false(array_key_exists('initial_stock_count', $attributes), 'no opening stock');
}

// A count below zero opens the card with none.
function test_parasut_product_attributes_never_open_below_zero()
{
	$attributes = parasut_product_attributes(array(
		'name' => 'MUG-2',
		'short_description' => 'Mug',
		'price' => '0',
		'inventory' => '1',
		'inventory_quantity' => '-2',
		'tax_rate' => '',
	));

	pg_assert_same(0, $attributes['initial_stock_count']);
	pg_assert_same(0, $attributes['vat_rate']);
	pg_assert_same(0.0, $attributes['list_price']);
}

// The create sends the helper's attributes, and nothing posts a
// stock_updates request afterwards: initial_stock_count already sets the
// stock, and Paraşüt answered every such request with 422 "Güncellenen stok
// miktarı bulunamadı."
function test_parasut_sync_product_sends_the_attributes_and_no_stock_update()
{
	$body = parasut_product_test_source('parasut_sync_product');

	pg_assert_true($body !== '', 'parasut_sync_product() is found');
	pg_assert_contains("'attributes' => parasut_product_attributes(\$product, \$taken_out),", $body);
	pg_assert_contains("_parasut_request('POST', _parasut_prefix() . '/products', \$body, \$token);", $body);
	pg_assert_false(strpos($body, 'stock_updates') !== false, 'no stock_updates request');
	pg_assert_false(strpos($body, 'parasut_ensure_default_warehouse(') !== false, 'no warehouse lookup');
	pg_assert_false(strpos($body, 'unit_price') !== false, 'no unit_price');
	pg_assert_false(strpos($body, 'product_type') !== false, 'no product_type');
}
