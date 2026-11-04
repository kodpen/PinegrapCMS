<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the product group copy's variant set part
 * (includes/fn/variant_set_copy.php): the later group columns the copy keeps
 * and the link of a product copy's generated form fields to the copied
 * template.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Only the columns the source row has are copied, so a database the upgrade
// has not reached yet gets no unknown column.
function test_variant_set_copy_columns()
{
	pg_assert_same(array(), pg_variant_set_copy_columns(array('id' => 4, 'name' => 'Shirt')));
	pg_assert_same(array(), pg_variant_set_copy_columns(null));

	$row = array(
		'id' => 4,
		'name' => 'Shirt',
		'form' => '1',
		'form_name' => 'Engraving',
		'form_label_column_width' => '30',
		'form_quantity_type' => 'One Form per Quantity',
		'variant_price_rules' => '{"version":1,"base":1000,"attributes":[]}',
	);

	pg_assert_same(array(
		'form' => '1',
		'form_name' => 'Engraving',
		'form_label_column_width' => '30',
		'form_quantity_type' => 'One Form per Quantity',
		'variant_price_rules' => '{"version":1,"base":1000,"attributes":[]}',
	), pg_variant_set_copy_columns($row));

	// No rules is NULL, never an empty string.
	$row['variant_price_rules'] = null;
	pg_assert_same(null, pg_variant_set_copy_columns($row)['variant_price_rules']);
}

// A copied product's field that was generated from the template is linked to
// the template copy by name; a hand-added field and a field generated from
// another set's template stay standalone.
function test_variant_set_copy_field_links()
{
	$original = array(
		array('id' => 10, 'name' => 'engraving', 'template_field_id' => 100),
		array('id' => 11, 'name' => 'gift_note', 'template_field_id' => 0),
		array('id' => 12, 'name' => 'colour_note', 'template_field_id' => 900),
		array('id' => 13, 'name' => 'font', 'template_field_id' => 101),
	);

	$copy = array(
		array('id' => 50, 'name' => 'engraving'),
		array('id' => 51, 'name' => 'gift_note'),
		array('id' => 52, 'name' => 'colour_note'),
		array('id' => 53, 'name' => 'font'),
	);

	pg_assert_same(
		array(50 => 200, 53 => 201),
		pg_variant_set_copy_field_links($original, $copy, array(100 => 200, 101 => 201)));

	// Nothing to link without a template.
	pg_assert_same(array(), pg_variant_set_copy_field_links($original, $copy, array()));

	// Two copies with one name: each is linked once.
	pg_assert_same(
		array(60 => 200, 61 => 201),
		pg_variant_set_copy_field_links(
			array(
				array('id' => 1, 'name' => 'x', 'template_field_id' => 100),
				array('id' => 2, 'name' => 'x', 'template_field_id' => 101),
			),
			array(array('id' => 60, 'name' => 'x'), array('id' => 61, 'name' => 'x')),
			array(100 => 200, 101 => 201)));
}

// Both group copies hand the variant set part to the helper.
function test_variant_set_copy_callers()
{
	$duplicate = (string) file_get_contents(PG_FUNCTIONS_DIR . '/duplicate_product_group.php');
	pg_assert_contains('pg_variant_set_copy(', $duplicate);

	$catalog = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/catalog_pages.php');
	pg_assert_contains('pg_variant_set_copy(', $catalog);
}
