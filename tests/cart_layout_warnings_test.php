<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the warnings the shopping cart and express order pages wrote on
 * every visit. With quick add off the cart still handed the layout a quick
 * add list holding an empty 'system' key, so the layout drew an empty quick
 * add form and read the keys it did not have. A product form field read the
 * submitted form title of its quantity even when there was none, which the
 * express order layout repeated after every rejected submit. A forced
 * shipping method check, an unknown gift card code and an express order form
 * without a quantity box read keys that were not there either.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Quick add stays an empty list unless its product group has products.
function test_cart_layout_quick_add_empty_when_off()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/get_shopping_cart.php');

	pg_assert_same(0, substr_count($source, "\$quick_add['system'] = '';"), 'empty system key before the product group check');
	pg_assert_same(0, substr_count($source, "\$quick_add['system'] .="), 'system key appended to');
	pg_assert_same(1, substr_count($source, "\$quick_add['system'] =\n"), 'system key set with the products');
	pg_assert_true(strpos($source, "\$quick_add['label'] = \$quick_add_label;") < strpos($source, "\$quick_add['system'] =\n"), 'system key set after the label');
}

// Every product form field has an empty title for each quantity before a
// reference code can fill it.
function test_cart_layout_product_form_titles_default()
{
	$default = "\$item['fields'][\$field_key]['titles'][\$quantity_number] = array('title' => '', 'title_label' => '');";

	foreach (array('get_shopping_cart.php', 'get_express_order.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		$position = strpos($source, $default);

		pg_assert_true($position !== false, $file . ': title default');
		pg_assert_true($position < strpos($source, "\$item['fields'][\$field_key]['titles'][\$quantity_number]['title'] = \$title;"), $file . ': default before the reference code title');
		pg_assert_same(0, substr_count($source, "if (\$_SESSION['ecommerce']['recipients'])"), $file . ': recipients read without empty()');
	}
}

// The forced shipping method check, an unknown gift card code and a missing
// quantity box read no missing key.
function test_cart_layout_order_error_paths()
{
	$shipping = file_get_contents(PG_FUNCTIONS_DIR . '/shipping.php');
	pg_assert_contains("\$arrival_date_id = \$properties['arrival_date_id'] ?? 0;", $shipping, 'arrival date of the forced method check');
	pg_assert_contains("!empty(\$response['shipping_methods'])", $shipping, 'error response without a method list');

	$express_order = file_get_contents(PG_FUNCTIONS_DIR . '/express_order.php');
	pg_assert_contains("} elseif (isset(\$_POST['quantity'][\$order_item['id']])) {", $express_order, 'quantity box not posted');

	foreach (array('express_order.php', 'order_preview.php') as $file) {
		pg_assert_contains("?: array('id' => ''); // no card with this code", file_get_contents(PG_FUNCTIONS_DIR . '/' . $file), $file . ': unknown gift card code');
	}
}
