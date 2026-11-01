<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the product search condition of the site and catalog search
 * (pg_catalog_search_condition() in includes/fn/content.php): I, İ, ı and i
 * are one letter, so "LAMBASI" finds "Masa Lambası" and "Işık" finds "ışık".
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_catalog_search_contains_folds_i_letters()
{
	$fold = "REPLACE(REPLACE(CONVERT(products.name USING utf8mb4), 'İ', 'i'), 'ı', 'i') LIKE ?";
	$condition = pg_catalog_search_condition('products.name', 'contains', 'LAMBASI', 'utf8mb4');
	pg_assert_contains($fold, $condition['sql'], 'the column is folded');
	pg_assert_contains('products.name LIKE ?', $condition['sql'], 'the plain LIKE stays');
	pg_assert_same(true, in_array('%lambasi%', $condition['params'], true), 'the word is folded');
	pg_assert_same(true, in_array('%LAMBASI%', $condition['params'], true), 'the word as typed');

	foreach (array('Işık', 'IŞIK', 'ışık') as $term) {
		$condition = pg_catalog_search_condition('details', 'contains', $term, 'utf8mb4');
		pg_assert_same(true, in_array('%işik%', $condition['params'], true), $term);
	}
}

function test_catalog_search_contains_keeps_wildcards_literal()
{
	$condition = pg_catalog_search_condition('name', 'contains', '50%_off', 'utf8mb4');
	pg_assert_same('(name LIKE ?)', $condition['sql']);
	pg_assert_same(array('%50\\%\\_off%'), $condition['params']);
}

function test_catalog_search_equal_folds_i_letters()
{
	$condition = pg_catalog_search_condition('name', 'is equal to', 'MASA LAMBASI', 'utf8mb4');
	pg_assert_same("(name = ? OR REPLACE(REPLACE(CONVERT(name USING utf8mb4), 'İ', 'i'), 'ı', 'i') = ?)", $condition['sql']);
	pg_assert_same(array('MASA LAMBASI', 'masa lambasi'), $condition['params']);

	$condition = pg_catalog_search_condition('name', 'is equal to', 'Kupa', 'utf8mb4');
	pg_assert_same('(name = ?)', $condition['sql'], 'no i letter, no fold');
	pg_assert_same(array('Kupa'), $condition['params']);
}

function test_catalog_search_without_connection_charset_is_plain()
{
	$condition = pg_catalog_search_condition('name', 'contains', 'LAMBASI', '');
	pg_assert_same('(name LIKE ?)', $condition['sql']);
	$condition = pg_catalog_search_condition('name', 'is equal to', 'LAMBASI', '');
	pg_assert_same('(name = ?)', $condition['sql']);
}
