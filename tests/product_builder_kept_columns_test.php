<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Edit Product save (pg_pb_update_product() in
 * product_builder.php): a column whose control the screen does not draw is
 * left out of the update, so the stored value stays. Shipping, customs and
 * the allowed zones need the store's Shipping switch, the gift card columns
 * need gift cards, and a custom product field needs its label.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/product_builder.php');

function pg_kept_shipping_columns()
{
	return array(
		'shippable', 'weight', 'length', 'width', 'height',
		'primary_weight_points', 'secondary_weight_points', 'container_required',
		'preparation_time', 'free_shipping', 'extra_shipping_cost',
		'hs_code', 'origin_country',
	);
}

function pg_kept_gift_card_columns()
{
	return array(
		'gift_card', 'gift_card_email_subject', 'gift_card_email_format',
		'gift_card_email_body', 'gift_card_email_page_id',
	);
}

function pg_kept_row()
{
	$row = array('price' => 1000, 'notes' => 'n');

	foreach (array_merge(pg_kept_shipping_columns(), pg_kept_gift_card_columns()) as $column) {
		$row[$column] = 'x';
	}

	for ($n = 1; $n <= 4; $n++) {
		$row['custom_field_' . $n] = 'c' . $n;
	}

	return $row;
}

function pg_kept_all_labels()
{
	return array(1 => 'a', 2 => 'b', 3 => 'c', 4 => 'd');
}

function pg_kept_function_body($name)
{
	$function = new ReflectionFunction($name);

	return implode('', array_slice(
		file($function->getFileName()),
		$function->getStartLine() - 1,
		$function->getEndLine() - $function->getStartLine() + 1));
}

function test_kept_columns_everything_drawn()
{
	pg_assert_same(pg_kept_row(), pg_pb_drop_undrawn_columns(pg_kept_row(), TRUE, TRUE, pg_kept_all_labels()),
		'every block drawn keeps every column');
}

function test_kept_columns_shipping_off()
{
	$result = pg_pb_drop_undrawn_columns(pg_kept_row(), FALSE, TRUE, pg_kept_all_labels());

	foreach (pg_kept_shipping_columns() as $column) {
		pg_assert_false(array_key_exists($column, $result), 'shipping off leaves ' . $column . ' out');
	}

	pg_assert_same(1000, $result['price'], 'shipping off keeps the price write');
	pg_assert_same('x', $result['gift_card'], 'shipping off keeps the gift card write');
}

function test_kept_columns_gift_cards_off()
{
	$result = pg_pb_drop_undrawn_columns(pg_kept_row(), TRUE, FALSE, pg_kept_all_labels());

	foreach (pg_kept_gift_card_columns() as $column) {
		pg_assert_false(array_key_exists($column, $result), 'gift cards off leaves ' . $column . ' out');
	}

	pg_assert_same('x', $result['weight'], 'gift cards off keeps the weight write');
}

function test_kept_columns_unlabelled_custom_fields()
{
	$result = pg_pb_drop_undrawn_columns(pg_kept_row(), TRUE, TRUE, array(1 => 'Colour code', 2 => '', 3 => 'Batch', 4 => ''));

	pg_assert_same('c1', $result['custom_field_1'], 'labelled custom field 1 is written');
	pg_assert_false(array_key_exists('custom_field_2', $result), 'unlabelled custom field 2 is left out');
	pg_assert_same('c3', $result['custom_field_3'], 'labelled custom field 3 is written');
	pg_assert_false(array_key_exists('custom_field_4', $result), 'unlabelled custom field 4 is left out');

	// The screen skips only an empty label, so the helper follows it.
	$result = pg_pb_drop_undrawn_columns(pg_kept_row(), TRUE, TRUE, array(1 => ' '));
	pg_assert_true(array_key_exists('custom_field_1', $result), 'a label of spaces is drawn, so it is written');
	pg_assert_false(array_key_exists('custom_field_2', $result), 'a missing label is not drawn');
}

function test_kept_columns_labels_read()
{
	pg_assert_same(array(1, 2, 3, 4), array_keys(pg_pb_custom_product_field_labels()), 'four custom field labels are read');
}

function test_kept_columns_update_path_only()
{
	$body = pg_kept_function_body('pg_pb_update_product');

	pg_assert_contains('pg_pb_drop_undrawn_columns(', $body, 'the update drops the columns the screen did not draw');
	pg_assert_true(
		(bool) preg_match('/if \(\$shipping_on\) \{\s*db\("DELETE FROM products_zones_xref/', $body),
		'the zone rows are rewritten only while shipping is on');

	pg_assert_false(strpos(pg_kept_function_body('pg_pb_save_new_product'), 'pg_pb_drop_undrawn_columns(') !== false,
		'the create path writes every column as before');
}
