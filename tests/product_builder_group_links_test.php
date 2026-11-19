<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the groups an Edit Product save links the product to
 * (product_builder.php): only the groups added or taken off on the screen
 * are written, so the rows it stays in keep their sort order, featured mark
 * and new date.
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

function pg_group_links_function_body($name)
{
	$function = new ReflectionFunction($name);

	return implode('', array_slice(
		file($function->getFileName()),
		$function->getStartLine() - 1,
		$function->getEndLine() - $function->getStartLine() + 1));
}

function test_group_links_unchanged_groups_are_not_written()
{
	pg_assert_same(
		array('add' => array(), 'remove' => array()),
		pg_pb_group_links_change(array(4, 9), array('9', '4')),
		'the same groups posted again write nothing');
}

function test_group_links_only_the_difference_is_written()
{
	pg_assert_same(
		array('add' => array(12), 'remove' => array(4)),
		pg_pb_group_links_change(array(4, 9), array('9', '12')),
		'one group taken off and one added');
}

function test_group_links_empty_post_removes_every_group()
{
	pg_assert_same(
		array('add' => array(), 'remove' => array(4, 9)),
		pg_pb_group_links_change(array(4, 9), array()),
		'nothing ticked takes the product out of every group');
}

function test_group_links_noise_and_repeats_are_ignored()
{
	pg_assert_same(
		array('add' => array(7), 'remove' => array()),
		pg_pb_group_links_change(array(4, 4), array('4', '7', '7', '0', '', 'x', array(3))),
		'zero, text, arrays and repeats are not groups');
}

function test_group_links_update_does_not_rewrite_every_row()
{
	$body = pg_group_links_function_body('pg_pb_update_product');

	pg_assert_true(strpos($body, 'pg_pb_group_links_change(') !== FALSE, 'the save writes the difference');
	pg_assert_true(strpos($body, "DELETE FROM products_groups_xref WHERE product = '\" . e(\$product_id) . \"'\")") === FALSE, 'the save no longer deletes every group row');
}
