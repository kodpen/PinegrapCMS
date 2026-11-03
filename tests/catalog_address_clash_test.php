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

// Every caller that saves an existing item names its kind, so no save falls
// back to skipping the id in both tables.
function test_catalog_address_clash_callers_name_their_item_type()
{
	$calls = array(
		'edit_product_group.php' => "prepare_catalog_item_address_name(\$address_name, \$_POST['id'], 'product_group')",
		'product_builder.php'    => "prepare_catalog_item_address_name(\$address_source, \$product_id, 'product')",
		'import_products.php'    => "prepare_catalog_item_address_name(\$column_value, \$product_id, 'product')",
	);

	foreach ($calls as $file => $call) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_true(strpos($source, $call) !== false, $file . ' names its item type');
	}
}

// A long name is cut before the uniqueness check, with room for "[n]".
function test_catalog_address_slug_leaves_room_for_the_suffix()
{
	$long = pg_catalog_address_slug('pg-test-' . str_repeat('u', 252));

	pg_assert_same(245, strlen($long), 'cut to 245 characters');
	pg_assert_same('pg-test-', substr($long, 0, 8), 'the start is kept');
	pg_assert_true(strlen($long . '[12345]') <= 255, 'a suffix still fits the column');
	pg_assert_same('a_b', pg_catalog_address_slug(str_repeat('a', 1) . '_b'), 'a short name is untouched');
	pg_assert_same(244, strlen(pg_catalog_address_slug(str_repeat('a', 244) . '_' . str_repeat('b', 20))), 'no separator left at the cut');
}

// The "[n]" numbering skips the item being saved, as the clash check does:
// a product at "X[1]" given the taken address "X" keeps "X[1]" instead of
// moving to "X[2]" because its own row held "X[1]".
function test_catalog_address_duplicate_number_skips_the_item_being_saved()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/content.php');

	pg_assert_contains("function get_duplicate_catalog_item_address_name_number(\$address_name, \$number = 1, \$excluded = null)", $source, 'the numbering takes the excluded ids');
	pg_assert_contains("\$address_name .= '[' . get_duplicate_catalog_item_address_name_number(\$address_name, 1, \$excluded) . ']';", $source, 'the save passes its own ids');
	pg_assert_contains("SELECT id FROM products where address_name = ? AND id != ?\";\n    \$result = db_q(\$query, array(\$address_name . '[' . \$number . ']', \$excluded['products']))", $source, 'products skip their own row');
	pg_assert_contains("SELECT id FROM product_groups where address_name = ? AND id != ?\";\n    \$result = db_q(\$query, array(\$address_name . '[' . \$number . ']', \$excluded['product_groups']))", $source, 'groups skip their own row');
}
