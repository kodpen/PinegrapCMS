<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the stock screens: the minimum stock screen's "Low" filter lists
 * the products its counter counts (on sale only), and one product's
 * movements are titled with its name whether or not it has a cost.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_erp_stock_low_filter_matches_the_counter()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/stock_levels.php');
	$start = strpos($source, 'function erp_stock_minimum_rows(');
	$body = substr($source, $start, strpos($source, "\n}", $start) - $start);

	pg_assert_contains("\$where[] = \"m.min_quantity > 0 AND p.enabled = '1' AND p.inventory_quantity <= m.min_quantity\";", $body, 'low filter keeps to products on sale');
}

function test_erp_stock_movements_title_without_a_cost()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/erp_stock.php');

	pg_assert_contains('FROM products WHERE id = \'" . $product_id . "\' LIMIT 1', $page, 'name read from products');
	pg_assert_contains("\$product_title = lang('Product not found.');", $page, 'a missing product says so');
	pg_assert_false(strpos($page, "('#' . \$product_id)") !== false, 'no number for a title');
}
