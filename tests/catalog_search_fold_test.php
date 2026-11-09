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

function test_catalog_listing_search_folds_i_letters_inline()
{
	$sql = pg_catalog_listing_search_sql('LAMBASI', array('products.name', 'products.keywords'), 'utf8mb4');

	pg_assert_contains("products.name LIKE '%LAMBASI%'", $sql, 'the plain LIKE stays');
	pg_assert_contains("REPLACE(REPLACE(CONVERT(products.name USING utf8mb4), 'İ', 'i'), 'ı', 'i') LIKE '%lambasi%'", $sql, 'the column is folded against the folded word');
	pg_assert_contains("REPLACE(REPLACE(CONVERT(products.keywords USING utf8mb4), 'İ', 'i'), 'ı', 'i') LIKE '%lambasi%'", $sql, 'every column is folded');
	pg_assert_false(strpos($sql, '?') !== false, 'no placeholder is left for a query run without values');
}

function test_catalog_listing_search_escapes_the_word()
{
	$sql = pg_catalog_listing_search_sql("O'Neil 50%", array('products.name'), '');

	pg_assert_same("(products.name LIKE '%O\\'Neil 50\\\\%%')", $sql, 'quote escaped, percent sign kept literal');
	pg_assert_same("(products.name LIKE '%a?b%')", pg_catalog_listing_search_sql('a?b', array('products.name'), ''), 'a question mark in the word is not a placeholder');
}

function test_catalog_listing_widget_uses_the_folded_search()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_catalog.php');

	pg_assert_same(4, substr_count($source, 'pg_catalog_listing_search_sql('), 'the helper, the product columns, the attribute labels and the sub-group cards');
	pg_assert_false(strpos($source, "LIKE '\$q_like'") !== false, 'no plain LIKE of the listing search is left');
	pg_assert_false(strpos($source, "LIKE '\$_q'") !== false, 'no plain LIKE of the sub-group search is left');
}
