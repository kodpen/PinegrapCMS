<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the My Account order screen and the order history actions. A
 * customer opening one of their orders wrote warnings on every visit: the
 * custom field labels come from the billing information page of this
 * visit's checkout, which a customer looking back at an order has not
 * opened, and an order without items left the recurring and offer lists
 * unset. With a custom layout a completed order whose ship to had no
 * tracking number ended in an error page (count() of an unset list). The
 * order history actions answered in English only.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The lists the totals and the layout read are set before either layout runs.
function test_order_view_screen_defaults_before_layouts()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/get_view_order_screen_content.php');
	$layouts = strpos($source, "if (\$layout_type == 'system') {");

	foreach (array("\$recurring_products_exist = false;", "\$applied_offers = array();", "\$output_recurring_products = '';") as $default) {
		$position = strpos($source, $default);
		pg_assert_true(($position !== false) && ($position < $layouts), $default);
	}

	pg_assert_same(2, substr_count($source, "\$_SESSION['ecommerce']['billing_information_page_id'] ?? ''"), 'billing information page of the visit');
	pg_assert_same(0, preg_match("/\\\$_SESSION\['ecommerce'\]\['(billing_information_page_id|shipping_address_and_arrival_page_id)'\]\)/", $source), 'checkout page read without a default');
	pg_assert_same(2, substr_count($source, "\$custom_field_1_label = \$row['custom_field_1_label'] ?? '';"), 'custom field 1 label');
	pg_assert_contains("\$recipient['shipping_tracking_numbers'] = array();", $source, 'tracking numbers list');
	pg_assert_contains("\$auto_registration_email_address = '';", $source, 'auto registration e-mail address');
}

// The layout shows the tracking line only when the ship to has a number.
function test_order_view_layout_tracking_line()
{
	pg_assert_contains("<?php if (\$status != 'incomplete' and \$recipient['shipping_tracking_numbers']):?>", file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/view_order.php'));
}

// The order history actions answer through lang().
function test_order_history_messages_translated()
{
	foreach (array('order_history_retrieve_order', 'order_history_reorder', 'order_history_delete_order') as $screen) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen . '.php');

		pg_assert_contains("output_error(lang('The order could not be found.') . ' <a href=\"javascript:history.go(-1)\">' . lang('Go back') . '</a>.');", $source, $screen);
		pg_assert_same(0, preg_match("/output_error\('The order|output_error\('You do not/", $source), $screen . ': English-only message');
	}
}
