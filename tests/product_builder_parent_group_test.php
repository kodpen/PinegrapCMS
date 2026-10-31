<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Product Builder's parent group list: a new variant group is always
 * filed under a group, so the list offers no empty choice and opens on the
 * top of the catalogue.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function product_builder_parent_group_test_select()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/product_builder.php');
	$start = strpos($source, 'id="parent_group_id" name="parent_group_id"');
	$end = strpos($source, '</select>', $start);

	pg_assert_true(($start !== false) && ($end !== false), 'the parent group select is found');

	return substr($source, $start, $end - $start);
}

function test_product_builder_parent_group_offers_no_empty_choice()
{
	$select = product_builder_parent_group_test_select();

	pg_assert_true(strpos($select, '<option value="0">') === false, 'no -None- option');
	pg_assert_true(strpos($select, "lang('None')") === false, 'no None label');
}

function test_product_builder_parent_group_opens_on_the_top_group()
{
	$select = product_builder_parent_group_test_select();

	pg_assert_contains('get_product_group_options(pg_catalog_group_top_id(), 0, 0, 0,', $select, 'the top of the catalogue is selected');
}
