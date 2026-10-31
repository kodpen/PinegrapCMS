<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The product form field screens (field list, add, edit, Delete Selected)
 * stop with "Record not found." (404) when the product or the variant set
 * they were opened for no longer exists, instead of drawing a blank heading,
 * logging warnings and, on add, inserting a field that belongs to nothing.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function product_form_fields_missing_record_files()
{
	return array('view_fields.php', 'add_field.php', 'delete_fields.php', 'edit_field.php');
}

function product_form_fields_missing_record_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The 404 every branch prints, the same as the screens' other missing-record stops.
function product_form_fields_missing_record_stop()
{
	return "output_error(lang('Record not found.') . ' <a href=\"javascript:history.go(-1)\">' . lang('Go back') . '</a>.', 404);";
}

// The product row is checked right after it is read.
function test_product_form_field_screens_stop_on_missing_product()
{
	foreach (product_form_fields_missing_record_files() as $file) {
		$source = product_form_fields_missing_record_source($file);
		$query = strpos($source, 'FROM products');
		pg_assert_true($query !== false, $file . ' reads the product row');
		$fetch = strpos($source, '$row = mysqli_fetch_assoc($result);', $query);
		pg_assert_true($fetch !== false, $file . ' fetches the product row');
		$after = preg_replace('/\s+/', ' ', preg_replace('~^\s*//.*$~m', '', substr($source, $fetch, 500)));
		pg_assert_contains('$row = mysqli_fetch_assoc($result); if (!$row) { ' . product_form_fields_missing_record_stop() . ' }', $after, $file . ' stops when the product is gone');
	}
}

// The variant set is checked too, with no blank stand-in row.
function test_product_form_field_screens_stop_on_missing_variant_set()
{
	foreach (product_form_fields_missing_record_files() as $file) {
		$source = product_form_fields_missing_record_source($file);
		pg_assert_same(false, strpos($source, "array('name' => '', 'short_description' => '', 'form_name' => '')"), $file . ' has no blank variant-set fallback');
		$query = strpos($source, 'FROM product_groups');
		pg_assert_true($query !== false, $file . ' reads the variant set');
		$after = preg_replace('/\s+/', ' ', preg_replace('~^\s*//.*$~m', '', substr($source, $query, 700)));
		pg_assert_contains('if (!$product_group) { ' . product_form_fields_missing_record_stop() . ' } $product_group = $product_group[0];', $after, $file . ' stops when the variant set is gone');
	}
}
