<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the product import's marketplace queue: an import that writes
 * price, extra shipping, stock or the enabled flag queues each product it
 * updated for the connected marketplaces, as every other stock and price
 * writer does through pg_marketplace_product_changed(). Before, the import
 * queued nothing and the marketplaces kept the old price and stock.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Only the columns a marketplace receives make the import queue products.
function test_products_import_marketplace_columns()
{
	pg_assert_true(pg_products_import_marketplace_columns(array('name', 'price')), 'price');
	pg_assert_true(pg_products_import_marketplace_columns(array('name', 'extra_shipping_cost')), 'extra shipping');
	pg_assert_true(pg_products_import_marketplace_columns(array('name', 'inventory_quantity')), 'stock');
	pg_assert_true(pg_products_import_marketplace_columns(array('name', 'inventory')), 'tracking');
	pg_assert_true(pg_products_import_marketplace_columns(array('enabled', 'name')), 'enabled');
	pg_assert_false(pg_products_import_marketplace_columns(array('name', 'short_description', 'full_description')), 'texts only');
	pg_assert_false(pg_products_import_marketplace_columns(array()), 'no columns');
}

// The update path collects the product and the end of the import queues the
// collected products after the bundles were refreshed (a bundle queues
// itself in pg_bundle_refresh()).
function test_products_import_queues_updated_products()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/import_products.php');

	$update = strpos($source, 'pg_price_history_queue($product_id, \'import\');');
	$collect = strpos($source, '$marketplace_product_ids[] = $product_id;');
	$refresh = strpos($source, 'pg_bundle_refresh_all();');
	$queue = strpos($source, 'pg_marketplace_products_changed($marketplace_product_ids);');

	pg_assert_true($update !== false, 'update path not found');
	pg_assert_true($collect !== false, 'updated products are not collected');
	pg_assert_true($queue !== false, 'collected products are not queued');
	pg_assert_true(($refresh !== false) && ($queue !== false) && ($refresh < $queue), 'queued before the bundle refresh');
}

// The queue of many products skips the per-product bundle refresh the
// import replaces with pg_bundle_refresh_all(), and reads each id as a number.
function test_marketplace_products_changed_skips_bad_ids()
{
	pg_assert_same(0, pg_marketplace_products_changed(array()), 'nothing to queue');
	pg_assert_same(0, pg_marketplace_products_changed(array(0, '', 'x')), 'no product id');
}
