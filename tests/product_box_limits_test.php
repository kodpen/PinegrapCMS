<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the product screen and product group form boxes: each box
 * takes no more than its products / product_groups column holds (the session
 * is not strict, so a longer value is cut and a decimal is truncated without
 * a warning), and a box sized smaller than its column does not refuse a value
 * the column can store.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function product_box_limits_test_read($path)
{
	return (string) file_get_contents(__DIR__ . '/../pinegrap/' . $path);
}

/**
 * The opening tag of the input whose id is $id.
 */
function product_box_limits_test_tag($source, $id)
{
	if (!preg_match('/<input\b[^>]*\bid="' . preg_quote($id, '/') . '"[^>]*>/', $source, $match)) {
		return '';
	}
	return $match[0];
}

function test_product_box_limits_sku_and_short_description_hold_varchar_100()
{
	$source = product_box_limits_test_read('product_builder.php');

	$sku = product_box_limits_test_tag($source, 'name');
	pg_assert_contains('maxlength="100"', $sku);
	pg_assert_contains('required', $sku);

	pg_assert_contains('maxlength="100"', product_box_limits_test_tag($source, 'short_description'));
}

function test_product_box_limits_weight_points_take_whole_numbers()
{
	$source = product_box_limits_test_read('product_builder.php');

	foreach (array('primary_weight_points', 'secondary_weight_points') as $id) {
		$tag = product_box_limits_test_tag($source, $id);
		pg_assert_contains('step="1"', $tag);
		pg_assert_false(strpos($tag, 'step="0.01"') !== false, $id . ' still takes decimals');
	}
}

function test_product_box_limits_email_subjects_hold_varchar_255()
{
	$source = product_box_limits_test_read('product_builder.php');

	foreach (array('gift_card_email_subject', 'recurring_profile_disabled_email_subject') as $id) {
		pg_assert_contains('maxlength="255"', product_box_limits_test_tag($source, $id));
	}
}

function test_product_box_limits_delete_skips_browser_validation()
{
	$source = product_box_limits_test_read('product_builder.php');
	pg_assert_true((bool) preg_match('/<button type="submit" name="submit_delete"[^>]*\bformnovalidate\b/', $source), 'product Delete blocked by a blank SKU');

	$group = product_box_limits_test_read('edit_product_group.php');
	pg_assert_true((bool) preg_match('/<button type="submit" name="submit_button" value="Delete"[^>]*\bformnovalidate\b/', $group), 'group Delete blocked by a blank name');
}

function test_product_box_limits_variant_cells_hold_varchar_100()
{
	$script = product_box_limits_test_read('assets/js/product_builder.js');

	foreach (array('pg-pb-v-name', 'pg-pb-v-short') as $class) {
		pg_assert_true((bool) preg_match('/<input [^\n]*' . $class . '"[^\n]*maxlength="100"/', $script), $class . ' has no maxlength');
	}
}

function test_product_box_limits_group_name_and_title()
{
	$source = product_box_limits_test_read('includes/forms/product_group_form.php');

	pg_assert_contains("'maxlength'=>'100'", $source);

	$edit_name = product_box_limits_test_tag($source, 'name');
	pg_assert_contains('maxlength="100"', $edit_name);
	pg_assert_contains('required', $edit_name);

	pg_assert_contains('maxlength="255"', product_box_limits_test_tag($source, 'title'));
}

function test_product_box_limits_save_refuses_a_code_longer_than_the_column()
{
	$source = product_box_limits_test_read('product_builder.php');
	$start = strpos($source, 'function pg_pb_validate_product_post(');
	pg_assert_true($start !== false, 'pg_pb_validate_product_post() not found');

	$body = substr($source, (int) $start, 6000);
	pg_assert_contains("mb_strlen(\$name, 'UTF-8') > 100", $body);
	pg_assert_true(strpos($body, 'mb_strlen') < strpos($body, 'pg_product_names_repeated('), 'length not checked before the uniqueness check');
}
