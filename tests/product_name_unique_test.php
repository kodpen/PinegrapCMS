<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the product code rule of the product screen: a product code
 * (products.name, "Product ID / SKU") another product holds is refused on
 * create and on edit, a variant set may not write one code twice, and the
 * refusal names the product that holds it.
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

function test_product_name_repeated_in_one_post()
{
	pg_assert_same('', pg_product_names_repeated(array('A-1', 'A-2', 'B-1')));
	pg_assert_same('a-1', pg_product_names_repeated(array('A-1', 'A-2', 'a-1')), 'a case difference is the same code');
	pg_assert_same('A-2', pg_product_names_repeated(array('A-1', ' A-2', 'A-2 ')), 'trimmed, as it is written');
	pg_assert_same('', pg_product_names_repeated(array('', ' ', 'A-1')), 'an empty row is not a code');
}

function test_product_name_posted_names_single_product()
{
	pg_assert_same(array('KORNA-12'), pg_pb_posted_product_names(array('name' => ' KORNA-12 ')));
	pg_assert_same(array(), pg_pb_posted_product_names(array('name' => '  ')), 'an empty code is the required check\'s');
	pg_assert_same(array('KORNA-12V'),
		pg_pb_posted_product_names(array('name' => 'KORNA', 'variants_json' => '[{"name":"KORNA-12V"}]')),
		'a single matrix row writes its own code');
	pg_assert_same(array('KORNA'),
		pg_pb_posted_product_names(array('name' => 'KORNA', 'variants_json' => '[{"name":""}]')),
		'a matrix row without a code keeps the typed one');
}

function test_product_name_posted_names_variant_set()
{
	pg_assert_same(array('KORNA-12V', 'KORNA-24V'),
		pg_pb_posted_product_names(array('name' => 'KORNA', 'variants_json' => '[{"name":"KORNA-12V"},{"name":"KORNA-24V"}]')),
		'a set writes one code per row; the typed code is only their prefix');
}

function test_product_name_posted_names_edit()
{
	pg_assert_same(array('KORNA-12'),
		pg_pb_posted_product_names(array('name' => 'KORNA-12', 'variants_json' => '[{"name":"X"},{"name":"Y"}]'), 7),
		'editing writes one product, whatever else was posted');
}

function test_product_name_taken_text()
{
	$holder = array('id' => 42, 'name' => 'KORNA-12', 'short_description' => '12 Volt <Korna>', 'recycled' => '0');

	$text = pg_product_name_taken_text('korna-12', $holder);

	pg_assert_contains('korna-12', $text);
	pg_assert_contains('12 Volt <Korna>', $text, 'the holder is named by its title');
	pg_assert_false(strpos($text, 'Recycle Bin') !== false);

	pg_assert_contains('12 Volt &lt;Korna&gt;', pg_product_name_taken_text('korna-12', $holder, true), 'escaped for a page');

	$untitled = pg_product_name_taken_text('X', array_merge($holder, array('short_description' => ' ')));
	pg_assert_contains('#42', $untitled, 'a product without a title is named by its number');

	pg_assert_contains('Recycle Bin', pg_product_name_taken_text('X', array_merge($holder, array('recycled' => '1'))),
		'a binned product still holds its code, and the text says where it is');
}

function test_product_name_checked_before_anything_is_written()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/product_builder.php');
	$start = strpos($source, "\nfunction pg_pb_validate_product_post(");
	$end = strpos($source, "\nfunction ", $start + 10);
	$body = substr($source, $start, $end - $start);

	pg_assert_contains('pg_pb_posted_product_names($_POST, $product_id)', $body);
	pg_assert_contains('pg_product_name_holder(', $body);
	pg_assert_contains('pg_product_names_repeated(', $body);

	foreach (array('add_product.php', 'edit_product.php') as $screen) {
		$screen_source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen);
		pg_assert_contains('pg_pb_validate_product_post(', $screen_source, $screen . ' runs the checks');
	}
}
