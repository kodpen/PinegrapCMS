<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the product code of a bulk copy (duplicate_products.php): the
 * original's last letter is swapped for the typed one, then the result is
 * made unique, so a second run or two originals that end the same never
 * write one product code twice, and a multibyte last letter is cut whole.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_duplicate_products_base_name_swaps_the_last_letter()
{
	pg_assert_same('SHIRT-B', pg_duplicate_products_base_name('SHIRT-A', 'B'));
	pg_assert_same('SHIRT-XL', pg_duplicate_products_base_name('SHIRT-S', 'XL'), 'a word replaces the letter');
	pg_assert_same('SHIRT-', pg_duplicate_products_base_name('SHIRT-A', ''), 'nothing typed only drops the letter');
}

function test_duplicate_products_base_name_cuts_a_multibyte_letter_whole()
{
	$name = pg_duplicate_products_base_name('GÖMLEK-Ş', 'Ç');

	pg_assert_same('GÖMLEK-Ç', $name);
	pg_assert_true(mb_check_encoding($name, 'UTF-8'), 'the code stays valid UTF-8');
}

function test_duplicate_products_base_name_never_returns_empty()
{
	pg_assert_same('A', pg_duplicate_products_base_name('A', ''), 'a one-letter code with nothing typed keeps the original');
	pg_assert_same('B', pg_duplicate_products_base_name('A', 'B'));
}

function test_duplicate_products_makes_the_new_code_unique()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/duplicate_products.php');

	// The swapped code itself is checked: cutting the "[1]" of the
	// original's unique name gave a code that a second run, or another
	// original ending the same, already holds.
	pg_assert_contains("get_unique_name(array(\n            'name' => pg_duplicate_products_base_name(\$row['name'], \$newletter),", $source);
	pg_assert_false(strpos($source, 'substr($product_name, 0, -4)') !== false, 'the "[1]" is no longer cut off a unique name');
}
