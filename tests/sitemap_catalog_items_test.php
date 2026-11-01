<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_sitemap_catalog_item_path() in includes/fn/seo.php: the
 * address sitemap.xml lists for an item under a legacy catalog page. An item
 * with no address name was listed as the bare catalog or catalog detail page
 * ("/shop-detail/"), which shows no item; it is now left out, as the Catalog
 * widget branch already did.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_sitemap_catalog_item_without_address_name_is_left_out()
{
	foreach (array('', null) as $address_name) {
		pg_assert_same('', pg_sitemap_catalog_item_path(array('type' => 'product', 'address_name' => $address_name), '/', 'shop', 'shop-detail'), 'product');
		pg_assert_same('', pg_sitemap_catalog_item_path(array('type' => 'product_group', 'display_type' => 'browse', 'address_name' => $address_name), '/', 'shop', 'shop-detail'), 'browse group');
	}
}

function test_sitemap_catalog_item_paths()
{
	pg_assert_same('/site/shop/Coffee_Cups', pg_sitemap_catalog_item_path(array('type' => 'product_group', 'display_type' => 'browse', 'address_name' => 'Coffee_Cups'), '/site/', 'shop', 'shop-detail'), 'browse group under the catalog page');
	pg_assert_same('/site/shop-detail/Mugs', pg_sitemap_catalog_item_path(array('type' => 'product_group', 'display_type' => 'select', 'address_name' => 'Mugs'), '/site/', 'shop', 'shop-detail'), 'select group under the detail page');
	pg_assert_same('/site/shop-detail/Mug%201', pg_sitemap_catalog_item_path(array('type' => 'product', 'address_name' => 'Mug 1'), '/site/', 'shop', 'shop-detail'), 'product under the detail page, encoded');
}

function test_sitemap_catalog_items_legacy_branch_uses_helper()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/get_sitemap_info.php');

	pg_assert_true(strpos($source, 'pg_sitemap_catalog_item_path($catalog_item, PATH, $catalog_page[\'name\'], $catalog_detail_page_name)') !== false, 'legacy catalog branch does not use the helper');
}
