<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the short description of a bulk copy (duplicate_products.php):
 * "Number of Letters to Remove" counts letters, not bytes, so a Turkish
 * description loses exactly that many letters and stays valid UTF-8.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_duplicate_products_description_removes_letters()
{
	pg_assert_same('Shirt Blue', pg_duplicate_products_description('Shirt Red', 3, 'Blue'));
	pg_assert_same('Gömlek Mavi', pg_duplicate_products_description('Gömlek Kırmızı', 7, 'Mavi'), 'seven letters, of which three are two bytes');

	$cut = pg_duplicate_products_description('Kumaş', 1, '');

	pg_assert_same('Kuma', $cut, 'a two-byte last letter goes whole');
	pg_assert_true(mb_check_encoding($cut, 'UTF-8'));
}

function test_duplicate_products_description_more_letters_than_the_text()
{
	pg_assert_same('Yeni', pg_duplicate_products_description('Eski', 10, 'Yeni'));
	pg_assert_same('X', pg_duplicate_products_description('', 1, 'X'));
}

function test_duplicate_products_uses_the_letter_cut()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/duplicate_products.php');

	pg_assert_contains("\$short_description = pg_duplicate_products_description(\$row['short_description'], \$lettertodelete, \$newletter);", $source);
	pg_assert_false(strpos($source, "substr(\$row['short_description'], 0, -\$lettertodelete)") !== false, 'no byte cut left');
}
