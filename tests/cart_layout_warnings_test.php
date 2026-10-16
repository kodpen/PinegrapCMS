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
 * The built-in (system) layouts of both pages read quick add rows, totals,
 * the visitor's contact and an unshipped items' recipient that were never
 * set; the express order quick add appended to a value that did not exist
 * yet; the recurring area of the layouts read a field title that was never
 * given; and a gift card deleted after it was applied was read as a row when
 * the order was submitted.
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

// The built-in layouts start every value they print or add to.
function test_cart_layout_system_layout_defaults()
{
	$ship_to_row = "\$row = mysqli_fetch_assoc(\$result) ?: array_fill_keys(array_column(mysqli_fetch_fields(\$result), 'name'), null);";

	foreach (array('get_shopping_cart.php', 'get_express_order.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		foreach (array('$output_ship_to_and_add_name_rows', '$output_quantity_row', '$output_amount_row') as $row) {
			pg_assert_true(strpos($source, $row . " = '';") < strpos($source, $row . " =\n"), $file . ': ' . $row . ' starts empty');
		}

		pg_assert_same(1, substr_count($source, $ship_to_row), $file . ': ship to 0 has empty values');
	}

	$express_order = file_get_contents(PG_FUNCTIONS_DIR . '/get_express_order.php');

	foreach (array("\$output_special_offers = '';", '$subtotal = 0;', '$grand_tax = 0;', '$order_discount = 0;', "\$contact_id = '';", "\$output_card_verification_number_help = '';") as $default) {
		pg_assert_contains($default, $express_order, 'express order: ' . $default);
	}

	pg_assert_true(strpos($express_order, '$subtotal = 0;') < strpos($express_order, '$subtotal = $subtotal + $total_price;'), 'subtotal starts before the items');
	pg_assert_true(strpos($express_order, "\$contact = array_fill_keys(") < strpos($express_order, "\$contact['salutation']"), 'contact columns start empty');
	pg_assert_same(0, substr_count($express_order, "\$quick_add['system'] .="), 'express order quick add appended to');
}

// The recurring area of the layouts reads one title per product form field.
function test_cart_layout_recurring_field_title_default()
{
	foreach (array('get_shopping_cart.php', 'get_express_order.php') as $file) {
		pg_assert_contains("\$item['fields'][\$field_key] += array('title' => '', 'title_label' => '');", file_get_contents(PG_FUNCTIONS_DIR . '/' . $file), $file . ': field title default');
	}

	$field = array('id' => 7, 'title' => 'Ticket #12', 'title_label' => 'Ticket');
	$field += array('title' => '', 'title_label' => '');
	pg_assert_same('Ticket #12', $field['title'], 'a title that is set is kept');
}

// A quick add field that a hand-made request leaves out counts as empty
// before the product, recipient, quantity or amount is read.
function test_cart_layout_quick_add_fields_not_posted()
{
	$default = "        \$_POST[\$pg_post_field] = \$_POST[\$pg_post_field] ?? '';";

	foreach (array('shopping_cart.php', 'express_order.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		$position = strpos($source, $default);

		pg_assert_true($position > strpos($source, "if (!empty(\$_POST['quick_add'])) {"), $file . ': inside the quick add branch');
		pg_assert_true($position < strpos($source, "if (\$_POST['quick_add_product_id']) {"), $file . ': before the product is read');
	}
}

// A gift card deleted after it was applied is dropped from the order with the
// existing message, at the balance check and at redemption.
function test_cart_layout_deleted_gift_card()
{
	$submit_order = file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');

	pg_assert_same(2, substr_count($submit_order, "?: array('id' => ''); // the card was deleted after it was applied"), 'both gift card reads');
	pg_assert_contains('Sorry, we could not find a gift card for that code ({var:1}).  Therefore, the gift card has been removed from your order.', $submit_order, 'message kept');
}
