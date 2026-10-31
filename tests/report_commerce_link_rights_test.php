<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for links from screens a commerce-reports user may open to
 * screens that ask for manage_ecommerce (view_order.php, view_orders.php,
 * edit_product.php): the Order and Shipping Reports, the Ecommerce Summary
 * card and View Campaign print the label as plain text without that right.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function report_commerce_link_rights_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function test_order_report_order_numbers_link_only_for_commerce()
{
	$source = report_commerce_link_rights_source('view_order_report.php');

	pg_assert_contains('$can_open_orders = (defined(\'USER_MANAGE_ECOMMERCE\') && USER_MANAGE_ECOMMERCE);', $source, 'order right read once');
	pg_assert_contains("if (!\$can_open_orders) {\n            return h(\$order['order_number']);", $source, 'plain order number without the right');
	pg_assert_same(4, substr_count($source, "' . \$output_order_number(\$order) . '"), 'the four detail rows use the guarded cell');
	pg_assert_same(1, substr_count($source, "'view_order.php?id='"), 'one place builds the order url');
	pg_assert_same(0, substr_count($source, "<a href=\"' . h(\$output_link_url) . '\">"), 'no unguarded order link');
}

function test_shipping_report_order_and_product_links_only_for_commerce()
{
	$source = report_commerce_link_rights_source('view_shipping_report.php');

	pg_assert_contains('$can_open_commerce = (defined(\'USER_MANAGE_ECOMMERCE\') && USER_MANAGE_ECOMMERCE);', $source, 'commerce right read once');
	pg_assert_same(0, substr_count($source, 'href="view_orders.php'), 'no literal view_orders.php link');
	pg_assert_same(4, substr_count($source, '$output_orders_heading(\''), 'day, Before, After and Total headings use the guarded helper');
	pg_assert_contains("if (!\$can_open_commerce) {\n            return (\$text_class !== '') ? '<span class=\"' . \$text_class . '\">' . \$label . '</span>' : \$label;", $source, 'heading label alone without the right');
	pg_assert_contains("(\$can_open_commerce ? '<a href=\"' . h('edit_product.php?id=' . (int) \$product['id']) . '\">' . \$output_description . '</a>' : \$output_description)", $source, 'product row header linked only with the right');
	pg_assert_same(1, substr_count($source, "'edit_product.php?id="), 'one edit_product.php link');
}

function test_ecommerce_card_best_sellers_link_only_for_commerce()
{
	$source = report_commerce_link_rights_source('includes/dashboard/widgets/widget_3.php');

	pg_assert_contains("'href'  => \$ec_can_open ? 'edit_product.php?id=' . (int) \$best['id'] : '',", $source, 'Best Sellers row unlinked without the right');
	pg_assert_contains('$ec_can_open = (defined(\'USER_MANAGE_ECOMMERCE\') && USER_MANAGE_ECOMMERCE);', $source, 'commerce right read');
	pg_assert_same(1, substr_count($source, "'edit_product.php?id="), 'one edit_product.php link');
}

function test_view_campaign_order_link_only_for_commerce_and_escaped()
{
	$source = report_commerce_link_rights_source('edit_email_campaign.php');

	pg_assert_contains('$output_order_reference = h($order_reference_code);', $source, 'reference code escaped');
	pg_assert_contains("if (defined('USER_MANAGE_ECOMMERCE') && USER_MANAGE_ECOMMERCE) {\n                \$output_order_reference = '<a href=\"view_order.php?id=' . (int) \$order_id . '\">' . \$output_order_reference . '</a>';", $source, 'order link behind the right');
	pg_assert_same(1, substr_count($source, 'href="view_order.php'), 'one view_order.php link');
	pg_assert_same(0, substr_count($source, "'\">' . \$order_reference_code . '</a>"), 'no raw reference code');
}
