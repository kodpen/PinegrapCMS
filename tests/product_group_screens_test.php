<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the product group screens: the parent Edit Product Group saves
 * is checked on the server (a group cannot go under itself or under one of
 * its own sub-groups), and a duplicated name keeps its [n] suffix inside the
 * 100-character name columns.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/product_group_form.php');

function product_group_screens_test_map()
{
	// 1 is the root; 2 under it with 3 under 2; 4 a sibling of 2.
	return array(
		1 => array('id' => 1, 'parent_id' => 0),
		2 => array('id' => 2, 'parent_id' => 1),
		3 => array('id' => 3, 'parent_id' => 2),
		4 => array('id' => 4, 'parent_id' => 1),
	);
}

function test_product_group_screens_parent_ok()
{
	$map = product_group_screens_test_map();

	pg_assert_true(pg_product_group_form_parent_ok($map, 2, 4, 1), 'under a sibling');
	pg_assert_true(pg_product_group_form_parent_ok($map, 3, 1, 2), 'up to the root');
	pg_assert_true(pg_product_group_form_parent_ok($map, 1, 0, 0), 'the root keeps no parent');
	pg_assert_false(pg_product_group_form_parent_ok($map, 2, 2, 1), 'under itself');
	pg_assert_false(pg_product_group_form_parent_ok($map, 2, 3, 1), 'under its own sub-group');
	pg_assert_false(pg_product_group_form_parent_ok($map, 1, 3, 0), 'the root under a group of its tree');
	pg_assert_false(pg_product_group_form_parent_ok($map, 2, 99, 1), 'a group that is not live');
	pg_assert_true(pg_product_group_form_parent_ok($map, 2, 99, 99), 'the parent it already has is kept');
	pg_assert_false(pg_product_group_form_parent_ok($map, 2, array(4), 1), 'a list is no id');
}

function test_product_group_screens_new_group_parent_checked()
{
	$map = product_group_screens_test_map();

	// A new group has no id and no stored parent yet.
	pg_assert_true(pg_product_group_form_parent_ok($map, 0, 2, 0), 'a new group under a live group');
	pg_assert_false(pg_product_group_form_parent_ok($map, 0, 99, 0), 'a new group under a group deleted or binned meanwhile');

	$add = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_product_group.php');
	$check = strpos($add, 'pg_product_group_form_parent_ok(pg_catalog_group_map(), 0, $_POST[\'parent_id\'], 0)');
	$insert = strpos($add, 'INSERT INTO product_groups');
	pg_assert_true($check !== false, 'Create Product Group checks the posted parent against the live tree');
	pg_assert_true(($check !== false) && ($insert !== false) && ($check < $insert), 'before the group is stored');
}

function test_product_group_screens_unique_name_limit()
{
	pg_assert_same(100, pg_unique_name_limit('product_group'), 'product_groups.name is varchar(100)');
	pg_assert_same(100, pg_unique_name_limit('product'), 'products.name');
	pg_assert_same(100, pg_unique_name_limit('page'), 'page.page_name');
	pg_assert_same(100, pg_unique_name_limit('folder'), 'folder.folder_name');
	pg_assert_same(0, pg_unique_name_limit('menu'), 'no limit for other types');

	$long = 'pg-test ' . str_repeat('ğ', 92);
	$copy = pg_bracket_suffix_name($long, pg_unique_name_limit('product_group'));
	pg_assert_same(100, mb_strlen($copy, 'UTF-8'), 'the copy fits the column');
	pg_assert_true($copy !== $long, 'the copy differs from its source');
}
