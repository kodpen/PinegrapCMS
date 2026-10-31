<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Log lines that name the record they are about, in the panel
 * language: Products > Export, Menu Items reorder, the Edit Product barcode
 * card and the Catalog Manager publish switch.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * The source of a product file.
 *
 * @param string $path relative to pinegrap/
 * @return string
 */
function site_log_named_lines_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

/**
 * The body of one function in a source file, up to the next top-level
 * function.
 *
 * @param string $source
 * @param string $name
 * @return string
 */
function site_log_named_lines_function($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

// Products > Export writes the translated count key, not glued English.
function test_site_log_named_lines_product_export()
{
	$source = site_log_named_lines_source('view_products.php');

	pg_assert_true(strpos($source, "' exported'") === false, 'view_products.php: no English export line glued outside lang()');
	pg_assert_true(strpos($source, "'{var:1} product(s) were exported'") !== false, 'view_products.php: the export line uses the translated count key');
}

// Menu Items reorder names the menu, as view_menu_items.php does.
function test_site_log_named_lines_menu_reorder()
{
	$body = site_log_named_lines_function(site_log_named_lines_source('includes/panel/content.php'), 'pg_panel_sort_menu_items');

	pg_assert_true($body !== '', 'content.php: pg_panel_sort_menu_items exists');
	pg_assert_true(strpos($body, "'vars' => \$menu_id") === false, 'content.php: no log line prints the raw menu id');
	pg_assert_true(preg_match("/'menu \\(\\{var:1\\}\\) items were reordered', 'vars' => \\\$menu_name/", $body) === 1, 'content.php: the reorder line names the menu');
	pg_assert_true(preg_match("/edit menu \\(\\{var:1\\}\\)', 'vars' => \\\$menu_name/", $body) === 1, 'content.php: the access-denied line names the menu');
}

// The barcode card names the product and the barcode, and logs a delete
// only when a row went.
function test_site_log_named_lines_barcodes()
{
	$source = site_log_named_lines_source('includes/panel/commerce.php');
	$save = site_log_named_lines_function($source, 'pg_panel_save_product_barcode');
	$delete = site_log_named_lines_function($source, 'pg_panel_delete_product_barcode');

	pg_assert_true($save !== '' && $delete !== '', 'commerce.php: both barcode handlers exist');
	pg_assert_true(strpos($source, 'for product #{var:2}') === false, 'commerce.php: no barcode line names the product by id');
	pg_assert_true(strpos($source, "'Deleted barcode #{var:1}") === false, 'commerce.php: the delete line does not give the row id');
	pg_assert_true(strpos($save, "'Saved barcode {var:1} for product {var:2}.'") !== false, 'commerce.php: the save line names the product');
	pg_assert_true(strpos($delete, "'Deleted barcode {var:1} from product {var:2}.'") !== false, 'commerce.php: the delete line names barcode and product');

	$affected = strpos($delete, 'mysqli_affected_rows(');
	$logged = strrpos($delete, 'log_activity(');
	pg_assert_true($affected !== false && $logged !== false && $affected < $logged, 'commerce.php: the delete line is written only after a row was deleted');
}

// The Catalog Manager publish switch says which way it went.
function test_site_log_named_lines_catalog_publish()
{
	$source = site_log_named_lines_source('includes/explorer/handle_catalog.php');

	pg_assert_true(strpos($source, "'product group ({var:1}) was updated'") === false, 'handle_catalog.php: a group publish is not logged as a bare update');
	pg_assert_true(strpos($source, "'product ({var:1}) was updated'") === false, 'handle_catalog.php: a product publish is not logged as a bare update');

	foreach (array('product group ({var:1}) was enabled', 'product group ({var:1}) was disabled', 'product ({var:1} - {var:2}) was enabled', 'product ({var:1} - {var:2}) was disabled') as $key) {
		pg_assert_true(strpos($source, "'" . $key . "'") !== false, 'handle_catalog.php: writes "' . $key . '"');
	}
}
