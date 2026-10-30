<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Product group copies (duplicate_product_group.php and the Catalog
 * Manager's pg_catalog_copy_group()): a product copy is linked only to the
 * new groups, groups and products in the Recycle Bin are left out, the
 * "you are editing the duplicate" notice survives the redirect and each
 * attribute keeps its default option. These checks pin the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function product_group_copy_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// Returns the source with its comments removed.
function product_group_copy_code($file)
{
	$code = '';
	foreach (token_get_all(product_group_copy_source($file)) as $token) {
		if (is_array($token)) {
			$code .= in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true) ? '' : $token[1];
		} else {
			$code .= $token;
		}
	}
	return $code;
}

// A fresh product copy drops the group links duplicate_product() copied
// from the original, so the original groups do not list the copy.
function test_product_group_copy_unlinks_fresh_product_copy()
{
	$code = product_group_copy_code('duplicate_product_group.php');
	$call = strpos($code, '$product_id = duplicate_product($product_reference[\'id\']);');
	pg_assert_true($call !== false, 'duplicate_product() call');
	$delete = strpos($code, "DELETE FROM products_groups_xref", (int) $call);
	pg_assert_true($delete !== false, 'copy links removed after the copy');
	$link = strpos($code, 'INSERT INTO products_groups_xref', (int) $call);
	pg_assert_true($delete < $link, 'removed before the new group link');
	pg_assert_contains("WHERE product = '\" . e((int) \$product_id) . \"'", $code, 'only the copy\'s links');
}

// remove_form() comes before add_notice(), so Edit Product Group prints it.
function test_product_group_copy_keeps_notice()
{
	$code = product_group_copy_code('duplicate_product_group.php');
	$notice = strpos($code, '$liveform_edit_product_group->add_notice(');
	$remove = strpos($code, '$liveform_edit_product_group->remove_form();');
	pg_assert_true($notice !== false, 'notice queued');
	pg_assert_true($remove === false || $remove < $notice, 'notice not wiped after it is queued');
}

// Neither copy path carries subgroups or products from the Recycle Bin.
function test_product_group_copy_skips_recycle_bin()
{
	$code = product_group_copy_code('duplicate_product_group.php');
	pg_assert_contains("WHERE parent_id = '\" . \$product_group['id'] . \"'\" . pg_catalog_bin_live_sql('product_groups') . \"", $code, 'legacy children live');
	pg_assert_contains("JOIN products ON products.id = products_groups_xref.product", $code, 'legacy members joined to products');
	pg_assert_contains("WHERE products_groups_xref.product_group = '\" . \$product_group['id'] . \"'\" . pg_catalog_bin_live_sql('products')", $code, 'legacy members live');

	$code = product_group_copy_code('includes/explorer/catalog_pages.php');
	pg_assert_contains("JOIN products ON products.id = products_groups_xref.product", $code, 'catalog members joined to products');
	pg_assert_contains("WHERE products_groups_xref.product_group = '\" . e((int) \$group_id) . \"'\" . pg_catalog_bin_live_sql('products')", $code, 'catalog members live');
	pg_assert_contains("WHERE parent_id = '\" . e((int) \$group_id) . \"'\" . pg_catalog_bin_live_sql('product_groups') . \"", $code, 'catalog children live');
}

// The Catalog Manager copy keeps each attribute's default option.
function test_product_group_copy_keeps_default_option()
{
	$code = product_group_copy_code('includes/explorer/catalog_pages.php');
	pg_assert_contains('SELECT attribute_id, sort_order, default_option_id FROM product_groups_attributes_xref', $code, 'default option read');
	pg_assert_contains('(product_group_id, attribute_id, sort_order, default_option_id)', $code, 'default option written');
	pg_assert_contains("e((int) \$attribute['default_option_id'])", $code, 'default option as a whole number');
}
