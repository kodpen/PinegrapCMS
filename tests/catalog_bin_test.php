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
 * that what is in the bin is not offered by the screens a product or a group
 * is picked on, nor counted or dropped by the group screen, nor written by
 * the product screen or the group screen.
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

// A permanent delete through the API counts the group's subgroups in the
// Recycle Bin too: deleted outright, the group left them under a parent that
// no longer existed. A group restored in the Catalog Manager whose parent is
// gone goes under the root group.
function test_catalog_bin_binned_subgroups_are_not_left_without_a_parent()
{
	$groups = catalog_bin_test_source('includes/api/resources/product_groups.php');
	$delete = catalog_bin_test_function_body($groups, 'api_product_groups_delete');
	$binned = strpos($delete, "AND recycled = '1'\")");

	pg_assert_true($binned !== false, 'permanent delete counts binned subgroups');
	pg_assert_true(($binned !== false) && ($binned < strpos($delete, 'DELETE FROM product_groups WHERE')), 'and refuses before deleting');

	$explorer = catalog_bin_test_source('includes/explorer/handle_catalog.php');
	$start = strpos($explorer, 'function pg_explorer_handle_catalog_restore(');
	$restore = ($start === false) ? '' : (string) substr($explorer, $start, 4000);

	pg_assert_contains("SELECT id FROM product_groups WHERE parent_id = '0'", $restore, 'restore finds the root group for an orphan');
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

	$explorer = catalog_bin_test_source('includes/explorer/handle_catalog.php');

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
	pg_assert_contains("pg_catalog_bin_live_sql('products')", catalog_bin_test_source('includes/api/outbound/product_matches.php'), 'marketplace matching');

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
	pg_assert_contains("get_product_options(true, \$liveform->get_field_value('product_id'))", catalog_bin_test_source('edit_calendar_event.php'), 'edit_calendar_event.php');

	// The short link screen hands its product to the shared form.
	pg_assert_contains("'product_id' => \$liveform->get_field_value('product_id'),", catalog_bin_test_source('edit_short_link.php'), 'edit_short_link.php');
	pg_assert_contains("get_product_options(true, \$keep['product_id'])", catalog_bin_test_source('includes/forms/short_link_form.php'), 'short_link_form.php');

	// The campaign profile screen hands its product to the shared form.
	pg_assert_contains("pg_email_campaign_profile_form_cards(\$liveform, \$user, \$liveform->get_field_value('product_id'))", catalog_bin_test_source('edit_email_campaign_profile.php'), 'edit_email_campaign_profile.php');
	pg_assert_contains('get_product_options(true, $product_id)', catalog_bin_test_source('includes/forms/email_campaign_profile_form.php'), 'email_campaign_profile_form.php');
}

function test_catalog_bin_group_screen_neither_lists_nor_drops_binned_rows()
{
	$source = catalog_bin_test_source('edit_product_group.php');

	pg_assert_contains("parent_id = ?\" . pg_catalog_bin_live_sql('product_groups')", $source, 'sub-groups in the bin are not listed or counted');
	pg_assert_contains("product_group = ?\" . pg_catalog_bin_live_sql('products')", $source, 'products in the bin are not listed');

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

function test_catalog_bin_keep_ids_are_whole_positive_ids_once()
{
	pg_assert_same(array(5), pg_catalog_keep_ids(5), 'a stored id');
	pg_assert_same(array(5), pg_catalog_keep_ids('5'), 'a stored id as a string');
	pg_assert_same(array(), pg_catalog_keep_ids(''), 'nothing chosen');
	pg_assert_same(array(), pg_catalog_keep_ids(0), 'the blank option');
	pg_assert_same(array(3, 8), pg_catalog_keep_ids(array(3, '3', 'x', array(1), 0, -2, '8')), 'a multiple select, and what a hand-made request adds to it');
}

function test_catalog_bin_group_picker_rows_skip_the_bin_above_them()
{
	// 1 is the catalogue, 2 a live group under it; 3 went to the bin with 4
	// under it; 4 is the group a setting points at, kept on the list; 5 was
	// restored on its own while 3 stayed in the bin.
	$rows = array(
		array('id' => '1', 'parent_id' => '0'),
		array('id' => '2', 'parent_id' => '1'),
		array('id' => '4', 'parent_id' => '3'),
		array('id' => '5', 'parent_id' => '3'),
		array('id' => '6', 'parent_id' => '4'));

	$binned = array(
		3 => array('parent_id' => '2'),
		4 => array('parent_id' => '3'));

	$parents = array();

	foreach (pg_catalog_group_rows_past_bin($rows, $binned) as $row) {
		$parents[(int) $row['id']] = (int) $row['parent_id'];
	}

	pg_assert_same(array(1 => 0, 2 => 1, 4 => 2, 5 => 2, 6 => 4), $parents, 'hung on the nearest listed group; a listed binned parent is kept');
}

function test_catalog_bin_group_picker_rows_only_skip_binned_groups()
{
	// 7 is off the list for another reason (switched off on a list of live
	// groups only); what is under it stays out of reach, as before.
	$rows = array(array('id' => '9', 'parent_id' => '7'));

	pg_assert_same($rows, pg_catalog_group_rows_past_bin($rows, array()), 'a group under one that is not in the bin');

	// A parent_id loop in the bin leaves the row where it was rather than
	// hanging the request.
	$loop = array(
		7 => array('parent_id' => '8'),
		8 => array('parent_id' => '7'));

	pg_assert_same($rows, pg_catalog_group_rows_past_bin($rows, $loop), 'a loop');
}

function test_catalog_bin_group_pickers_leave_the_bin_out()
{
	$body = catalog_bin_test_function_body(catalog_bin_test_source('includes/fn/forms.php'), 'get_product_group_options');

	pg_assert_contains("pg_catalog_bin_live_sql('product_groups', array_merge(pg_catalog_keep_ids(\$product_group_id), pg_catalog_keep_ids(\$keep_ids)))", $body, 'the selected group and the kept ones stay');
	pg_assert_contains('pg_catalog_group_rows_past_bin($product_groups, pg_catalog_group_map(true))', $body, 'and are reached by the walk');
	pg_assert_contains("lang('IN RECYCLE BIN')", $body, 'and are marked');

	// Page settings: search results, catalog, express order, order form and
	// shopping cart.
	$page_form = catalog_bin_test_source('includes/forms/page_form.php');

	pg_assert_same(5, substr_count($page_form, "'text', false, true, false)"), 'page settings: five group pickers');
	pg_assert_same(5, substr_count($page_form, 'get_product_group_options('), 'page settings: no other');

	$builder = catalog_bin_test_source('product_builder.php');

	pg_assert_contains("get_product_group_options(0, 0, 0, 0, array(), FALSE, 'text', false, true, false)", $builder, 'product screen: parent group');
	pg_assert_contains("get_product_group_options(0, 0, 0, 0, array(), TRUE, 'text', false, true, false, \$v('catalog_group_ids', array()))", $builder, 'product screen: the groups it is in stay');

	pg_assert_contains("'product_group_id' => \$liveform->get_field_value('product_group_id'),", catalog_bin_test_source('edit_short_link.php'), 'short link: the group it points at stays');
	pg_assert_contains("get_product_group_options(0, 0, 0, 0, array(), TRUE, 'array', TRUE, TRUE, FALSE, \$keep['product_group_id'])", catalog_bin_test_source('includes/forms/short_link_form.php'), 'short link form: the bin left out');

	$group = catalog_bin_test_source('edit_product_group.php');

	pg_assert_contains("get_product_group_options(\$parent_id, 0, \$_GET['id'], 0, array(), FALSE, 'text', false, true, false)", $group, 'group screen: parent');
	pg_assert_contains("get_product_group_options(0, 0, \$_POST['id'], 0, array(), FALSE, 'text', false, true, false)", $group, 'group screen: whether a parent is required');
}

function test_catalog_bin_group_screen_does_not_write_a_binned_group()
{
	$source = catalog_bin_test_source('edit_product_group.php');

	pg_assert_contains('$group_binned = pg_catalog_group_binned($id);', $source, 'the screen asks');

	$guard = strpos($source, 'if ($group_binned) {' . "\n" . '        go(');

	pg_assert_true($guard !== false, 'the guard is there');
	pg_assert_true($guard > strpos($source, 'validate_token_field();'), 'after the token check');
	pg_assert_true($guard < strpos($source, 'pg_catalog_bin_group('), 'before the delete');
	pg_assert_true($guard < strpos($source, 'DELETE FROM product_groups_images_xref'), 'before the save');

	pg_assert_contains("lang('This product group is in the Recycle Bin. Restore it from the Recycle Bin in the Catalog Manager before changing it.')", $source, 'it says so');
	pg_assert_contains("(\$group_binned\n                                    ? '<a class=\"btn my-1 btn-solid theme-secondary\"", $source, 'the bin instead of Save');
	pg_assert_contains("if (\$group_binned) {\n        \$output_delete_button = '';", $source, 'and no Delete');
}

// A change proposed in the Workspace (changes.php) or run by a scheduled
// action goes through ws_change_check() when it is proposed, applied and run
// in bulk: a product, its stock or a group that sits in the bin is refused
// there, creating and deleting are left to their own checks.
function test_catalog_bin_workspace_changes_do_not_write_a_binned_record()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');

	$viewer = array('id' => 3, 'role' => 2);
	$binned = array('id' => 12, 'recycled' => '1');
	$live = array('id' => 12, 'recycled' => '0');

	pg_assert_true(ws_change_check($viewer, 'product', 'update', $binned, array('price' => 100)) !== '', 'a binned product is not changed');
	pg_assert_true(ws_change_check($viewer, 'stock', 'update', $binned, array('quantity' => 5)) !== '', 'nor its stock');
	pg_assert_true(ws_change_check($viewer, 'product_group', 'add', $binned, array('product_ids' => array(1))) !== '', 'nor a binned group');
	pg_assert_same('', ws_change_check($viewer, 'product', 'update', $live, array('price' => 100)), 'a live product is');
	pg_assert_same('', ws_change_check($viewer, 'product', 'update', array('id' => 12), array('price' => 100)), 'a database without the column is live');
	pg_assert_same('', ws_change_check($viewer, 'product', 'create', null, array('name' => 'x')), 'creating has no record to be binned');
	pg_assert_same('', ws_change_check($viewer, 'product', 'delete', $binned, array()), 'deleting again is ws_change_delete_blocked()\'s to refuse');
	pg_assert_true(ws_change_delete_blocked('product', $binned) !== '', 'and it does');
}
