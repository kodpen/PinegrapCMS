<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the address name clash check of products and product groups
 * (includes/fn/content.php): an item skips only its own row, in its own
 * table, so a product and a product group with the same id number still
 * see each other's address name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_catalog_address_clash_skips_only_the_items_own_table()
{
	pg_assert_same(array('products' => '5', 'product_groups' => '0'), pg_catalog_address_excluded_ids(5, 'product'), 'a product skips its own row only');
	pg_assert_same(array('products' => '0', 'product_groups' => '5'), pg_catalog_address_excluded_ids(5, 'product_group'), 'a group skips its own row only');
	pg_assert_same(array('products' => '0', 'product_groups' => '0'), pg_catalog_address_excluded_ids('', 'product'), 'a new item skips nothing');
	pg_assert_same(array('products' => '0', 'product_groups' => '0'), pg_catalog_address_excluded_ids(0, 'product_group'), 'id 0 skips nothing');
	// Callers that do not name the type keep skipping the id in both tables.
	pg_assert_same(array('products' => '5', 'product_groups' => '5'), pg_catalog_address_excluded_ids(5, ''), 'no type: both tables');
}
