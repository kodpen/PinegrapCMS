<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the parent a new product group is filed under
 * (pg_catalog_group_parent_id(), includes/fn/ecommerce.php): a group with no
 * parent named goes under the top of the catalogue, so the catalogue keeps a
 * single root. Read from the source, since they need a database: the variant
 * set writer and the browse group writer both resolve through it, and the
 * product screen's group mode no longer offers a parent of 0.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function catalog_group_parent_test_body($relative, $name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

function test_catalog_group_parent_named_is_kept()
{
	$asked = 0;
	$top = function () use (&$asked) {
		$asked++;
		return 31;
	};

	pg_assert_same(62, pg_catalog_group_parent_id(62, $top), 'the parent named');
	pg_assert_same(62, pg_catalog_group_parent_id('62', $top), 'a posted string');
	pg_assert_same(0, $asked, 'the top is not read when a parent is named');
}

function test_catalog_group_parent_none_goes_under_the_top()
{
	$top = function () {
		return 31;
	};

	pg_assert_same(31, pg_catalog_group_parent_id(0, $top), '-None- (0)');
	pg_assert_same(31, pg_catalog_group_parent_id('0', $top), 'a posted 0');
	pg_assert_same(31, pg_catalog_group_parent_id('', $top), 'an empty choice');
	pg_assert_same(31, pg_catalog_group_parent_id(null, $top), 'no parent sent');
	pg_assert_same(31, pg_catalog_group_parent_id(-4, $top), 'below zero');
}

function test_catalog_group_parent_empty_catalogue_makes_the_first_root()
{
	pg_assert_same(0, pg_catalog_group_parent_id(0, function () {
		return 0;
	}), 'no group yet: the new one is the root');
}

function test_catalog_group_parent_writers_resolve_through_it()
{
	$set = catalog_group_parent_test_body('includes/fn/ecommerce.php', 'pg_catalog_variant_set_create');
	$resolved = strpos($set, 'pg_catalog_group_parent_id(');
	$written = strpos($set, 'pg_pb_create_group(');

	pg_assert_true($resolved !== false, 'the variant set writer resolves the parent');
	pg_assert_true(($resolved !== false) && ($written !== false) && ($resolved < $written), 'before the group row is written');

	$input = catalog_group_parent_test_body('includes/fn/ecommerce.php', 'pg_catalog_group_create_input');

	pg_assert_contains('pg_catalog_group_parent_id(', $input, 'the browse group writer shares the resolver');
}

function test_catalog_group_parent_screen_offers_no_zero_parent()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/product_builder.php');
	$start = strpos($screen, 'id="parent_group_id"');
	$field = ($start === false) ? '' : substr($screen, $start, 600);

	pg_assert_true($field !== '', 'the parent field is there');
	pg_assert_false(strpos($field, '<option value="0">') !== false, 'no -None- option that would make a second root');
	pg_assert_contains('get_product_group_options(pg_catalog_group_top_id()', $field, 'the top of the catalogue is selected first');
}
