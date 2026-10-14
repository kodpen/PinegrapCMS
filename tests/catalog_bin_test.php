<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the catalogue's Recycle Bin: the statements that bin a product or
 * a product group (pg_catalog_bin_statements(), includes/fn/ecommerce.php),
 * and the rule that every place which deletes a product or a group - the
 * product screen, the product list, the variant set list, the group screen,
 * the Catalog Manager, the external API and the Workspace - bins it through
 * those statements, with the permanent delete left to emptying the bin; and
 * that what is in the bin is not offered by the screens a product is picked
 * on, nor counted or dropped by the group screen, nor written by the product
 * screen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Whitespace folded to single spaces, so a statement can be compared without
// caring how it is wrapped in the source.
function catalog_bin_test_flat($sql)
{
	return trim(preg_replace('/\s+/', ' ', (string) $sql));
}

function catalog_bin_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The body of one function in a source file, up to the next top-level
// function. Enough to ask what a function calls without loading the file.
function catalog_bin_test_function_body($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

function test_catalog_bin_product_is_flagged_switched_off_and_recorded()
{
	$statements = array_map('catalog_bin_test_flat', pg_catalog_bin_statements('product', 41, array(41), 0, 7));

	pg_assert_same(3, count($statements), 'three statements');

	pg_assert_same(
		"UPDATE products SET recycled = '1', recycled_enabled = enabled, enabled = '0' WHERE (id IN (41)) AND (recycled = '0')",
		$statements[0],
		'flag, keep what enabled was, switch off');

	pg_assert_same(
		"DELETE FROM recycle_bin WHERE (item_type = 'product') AND (item_id = '41')",
		$statements[1],
		'an earlier record is replaced, not added to');

	pg_assert_same(
		"INSERT INTO recycle_bin (item_type, item_id, original_parent_id, deleted_at, deleted_by) VALUES ('product', '41', '0', UNIX_TIMESTAMP(), '7')",
		$statements[2],
		'one record, with who deleted it');
}

function test_catalog_bin_never_binds_a_row_that_is_already_in_the_bin()
{
	// A second pass over a binned row would copy enabled, already forced to 0,
	// over the kept value, and the row would come back unpublished.
	foreach (array('product', 'product_group') as $type) {
		$statements = pg_catalog_bin_statements($type, 3, array(3), 1, 1);

		pg_assert_contains("AND (recycled = '0')", catalog_bin_test_flat($statements[0]), $type);
	}
}

function test_catalog_bin_group_flags_its_subtree_under_one_record()
{
	$statements = array_map('catalog_bin_test_flat', pg_catalog_bin_statements('product_group', 12, array(12, 15, 16, 15), 4, 2));

	pg_assert_same(
		"UPDATE product_groups SET recycled = '1', recycled_enabled = enabled, enabled = '0' WHERE (id IN (12,15,16)) AND (recycled = '0')",
		$statements[0],
		'the whole subtree, each id once');

	pg_assert_same(
		"DELETE FROM recycle_bin WHERE (item_type = 'product_group') AND (item_id = '12')",
		$statements[1],
		'the record is the deleted group\'s');

	pg_assert_contains("VALUES ('product_group', '12', '4', UNIX_TIMESTAMP(), '2')", $statements[2], 'the parent is where it goes back to');
}

function test_catalog_bin_variant_keeps_the_set_it_went_with()
{
	// pg_pb_bin_variant_set() records each variant with its set, which is how
	// restoring the set in the Catalog Manager brings them back with it.
	$statements = pg_catalog_bin_statements('product', 90, array(90), 33, 1);

	pg_assert_contains("VALUES ('product', '90', '33',", catalog_bin_test_flat($statements[2]));
}

function test_catalog_bin_ids_are_numbers_whatever_arrives()
{
	$statements = array_map('catalog_bin_test_flat', pg_catalog_bin_statements('product', "5' OR '1'='1", array("5' OR '1'='1", '-3', 0, 'x'), "2'", "9 OR 1"));

	pg_assert_same("UPDATE products SET recycled = '1', recycled_enabled = enabled, enabled = '0' WHERE (id IN (5)) AND (recycled = '0')", $statements[0], 'only positive integers reach the IN list');
	pg_assert_contains("VALUES ('product', '5', '2', UNIX_TIMESTAMP(), '9')", $statements[2], 'every value cast');

	// An unknown type is a product, never a table name taken from the caller.
	$statements = pg_catalog_bin_statements('page; DROP TABLE x', 1, array(1), 0, 0);

	pg_assert_contains('UPDATE products SET', catalog_bin_test_flat($statements[0]), 'type is not interpolated');
	pg_assert_contains("(item_type = 'product')", catalog_bin_test_flat($statements[1]), 'nor written into the record');
}

function test_catalog_bin_nothing_to_bin_writes_nothing()
{
	pg_assert_same(array(), pg_catalog_bin_statements('product', 0, array(0), 0, 1), 'no id');
	pg_assert_same(array(), pg_catalog_bin_statements('product_group', 8, array(), 0, 1), 'no rows');
	pg_assert_same(array(), pg_catalog_bin_statements('product_group', 8, array(-1, 'a'), 0, 1), 'no usable rows');
}

function test_catalog_bin_api_deletes_to_the_bin_unless_permanent()
{
	$products = catalog_bin_test_source('includes/api/resources/products.php');
	$delete = catalog_bin_test_function_body($products, 'api_products_delete');

	pg_assert_contains("\$permanent = !empty(\$params['permanent']);", $delete, 'products: the bin is the default');
	pg_assert_contains("pg_catalog_bin_statements('product'", $delete, 'products: the shared statements');
	pg_assert_true(strpos($delete, 'pg_catalog_bin_statements') < strpos($delete, 'pg_pb_delete_product('), 'products: the bin branch comes before the permanent delete');

	$groups = catalog_bin_test_source('includes/api/resources/product_groups.php');
	$delete = catalog_bin_test_function_body($groups, 'api_product_groups_delete');

	pg_assert_contains("\$permanent = !empty(\$params['permanent']);", $delete, 'groups: the bin is the default');
	pg_assert_contains("pg_catalog_bin_statements('product_group'", $delete, 'groups: the shared statements');
	pg_assert_true(strpos($delete, 'pg_catalog_bin_statements') < strpos($delete, 'DELETE FROM product_groups WHERE'), 'groups: the bin branch comes before the permanent delete');
}

function test_catalog_bin_panel_screens_send_deletes_to_the_bin()
{
	$edit_product = catalog_bin_test_source('edit_product.php');

	pg_assert_contains('pg_catalog_bin_product($product_id', $edit_product, 'product screen');
	pg_assert_true(strpos($edit_product, 'pg_catalog_bin_product(') < strpos($edit_product, 'pg_pb_delete_product('), 'product screen: permanent delete only as the fallback');

	$edit_products = catalog_bin_test_source('edit_products.php');

	pg_assert_contains('pg_catalog_bin_product($product_id', $edit_products, 'product list bulk delete');
	pg_assert_same(0, preg_match('/DELETE\s+FROM\s+products\s/i', $edit_products), 'product list: no delete of its own');

	pg_assert_contains('pg_pb_bin_variant_set($group_id', catalog_bin_test_source('variant_set_action.php'), 'variant set list');
	pg_assert_contains('pg_catalog_bin_group(', catalog_bin_test_source('edit_product_group.php'), 'product group screen');

	$explorer = catalog_bin_test_source('view_folder_and_files_f.php');

	pg_assert_contains("pg_catalog_bin_product(\$item_id", $explorer, 'Catalog Manager: products');
	pg_assert_contains("pg_catalog_bin_statements('product_group'", $explorer, 'Catalog Manager: groups');

	$workspace = catalog_bin_test_source('includes/workspace/changes.php');

	pg_assert_contains('pg_catalog_bin_product($id', $workspace, 'Workspace: products');
	pg_assert_contains("pg_catalog_bin_statements('product_group'", $workspace, 'Workspace: groups');
}

function test_catalog_bin_product_rows_are_deleted_in_one_place_only()
{
	// The product row itself is deleted by pg_pb_delete_product() and nowhere
	// else, so emptying the bin and the screens' fallback cannot come apart, and
	// a new screen that deletes a product has to go through it - or through the
	// bin - to get past this test.
	$found = array();

	$directory = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PG_FUNCTIONS_DIR, FilesystemIterator::SKIP_DOTS));

	foreach ($directory as $file) {
		if (substr($file->getFilename(), -4) !== '.php') {
			continue;
		}

		$source = (string) file_get_contents($file->getPathname());

		if (preg_match('/DELETE\s+FROM\s+`?products`?\s+WHERE/i', $source)
			|| preg_match('/DELETE\s+FROM\s+`?products`?\s*"/i', $source)) {
			$found[] = str_replace('\\', '/', substr($file->getPathname(), strlen(PG_FUNCTIONS_DIR) + 1));
		}
	}

	sort($found);

	pg_assert_same(array('product_builder.php'), $found, 'files that delete product rows');
}

function test_catalog_bin_product_pickers_leave_the_bin_out()
{
	// Every list a product is chosen from reads the bin through the one
	// helper, so a product deleted here is not offered for an order line, a
	// group, a short link, a document or a reference somewhere else.
	$forms = catalog_bin_test_source('includes/fn/forms.php');

	pg_assert_contains("pg_catalog_bin_live_sql('products', \$keep_id)", catalog_bin_test_function_body($forms, 'get_product_options'), 'get_product_options()');
	pg_assert_contains("pg_catalog_bin_live_sql('products', \$keep_id)", catalog_bin_test_function_body($forms, 'select_product'), 'select_product()');

	pg_assert_contains("pg_catalog_bin_live_sql('products')", catalog_bin_test_function_body(catalog_bin_test_source('includes/panel/commerce.php'), 'pg_panel_get_unselected_products'), 'group screen: products loaded on scroll');
	pg_assert_contains("pg_catalog_bin_live_sql('products')", catalog_bin_test_source('add_product_group.php'), 'new group screen');
	pg_assert_contains("pg_catalog_bin_live_sql('p')", catalog_bin_test_function_body(catalog_bin_test_source('includes/erp/products.php'), 'erp_product_search'), 'ERP document lines');
	pg_assert_contains('pg_catalog_product_binned($by_barcode', catalog_bin_test_function_body(catalog_bin_test_source('includes/erp/products.php'), 'erp_product_search'), 'ERP document lines: by barcode');
	pg_assert_contains("pg_catalog_bin_live_sql('products')", catalog_bin_test_source('includes/workspace/refs.php'), 'Workspace references');
	pg_assert_contains("pg_catalog_bin_live_sql('products')", catalog_bin_test_source('includes/panel/search.php'), 'panel search: products');
	pg_assert_contains("pg_catalog_bin_live_sql('product_groups')", catalog_bin_test_source('includes/panel/search.php'), 'panel search: groups');
	pg_assert_contains("pg_catalog_bin_live_sql('products')", catalog_bin_test_source('marketplace_settings.php'), 'marketplace matching');

	// The offer editor keeps the products an offer already names, flagged,
	// and offers only the rest.
	pg_assert_contains("'recycled' =>", catalog_bin_test_function_body(catalog_bin_test_source('edit_offer_f.php'), '_pg_offer_products'), 'offers: flagged');

	$editor = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/offer_editor.js');

	pg_assert_contains('if (p.recycled && (selectedId != p.id)) { return; }', $editor, 'offers: not offered unless chosen');
}

function test_catalog_bin_edit_screens_keep_the_product_they_point_at()
{
	// Left off its own select, the stored product falls back to the first
	// option and the next save points the record elsewhere.
	foreach (array('edit_short_link.php', 'edit_calendar_event.php', 'edit_email_campaign_profile.php') as $file) {
		pg_assert_contains("get_product_options(true, \$liveform->get_field_value('product_id'))", catalog_bin_test_source($file), $file);
	}
}

function test_catalog_bin_group_screen_neither_lists_nor_drops_binned_rows()
{
	$source = catalog_bin_test_source('edit_product_group.php');

	pg_assert_contains("parent_id = '\" . e(\$_GET['id']) . \"'\" . pg_catalog_bin_live_sql('product_groups')", $source, 'sub-groups in the bin are not listed or counted');
	pg_assert_contains("product_group = '\" . e(\$_GET['id']) . \"'\" . pg_catalog_bin_live_sql('products')", $source, 'products in the bin are not listed');

	// Their group links survive the save that cannot post them back.
	pg_assert_contains("AND product NOT IN (SELECT id FROM products WHERE recycled = '1')", $source, 'the save keeps the links of binned products');
	pg_assert_contains('in_array((int) $product_id, $kept_product_ids, true)', $source, 'and does not add them a second time');
}

function test_catalog_bin_product_screen_does_not_write_a_binned_product()
{
	$source = catalog_bin_test_source('edit_product.php');

	$guard = strpos($source, 'if ($product_binned) {');

	pg_assert_true($guard !== false, 'the guard is there');
	pg_assert_true($guard < strpos($source, 'pg_catalog_bin_product('), 'before the delete');
	pg_assert_true($guard < strpos($source, 'pg_pb_update_product('), 'before the save');
	pg_assert_contains("'recycled'   => \$product_binned", $source, 'the screen is told');

	$builder = catalog_bin_test_source('product_builder.php');

	pg_assert_contains("(\$pg_recycled ? \$pg_bin_link :", $builder, 'no save or delete button for it');
	pg_assert_contains("pg_catalog_bin_live_sql('products')", catalog_bin_test_function_body($builder, 'pg_pb_quick_update'), 'the list\'s quick edit refuses it too');
}
