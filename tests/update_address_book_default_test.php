<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the default update address book screen
 * (get_update_address_book.php), drawn with no properties when the site has
 * no update address book page: it reads no missing key and no offset of null.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_update_address_book_default_screen_reads_no_missing_key()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_update_address_book.php');

	pg_assert_contains("\$page_id = (int) (\$properties['page_id'] ?? 0);", $source, 'page_id defaults to 0');
	pg_assert_contains("\$address_type = \$properties['address_type'] ?? 0;", $source, 'address type off without a page');
	pg_assert_contains("\$address_type_page_id = \$properties['address_type_page_id'] ?? 0;", $source, 'no address type page without a page');
	pg_assert_false(strpos($source, "\$page_id = \$properties['page_id'];") !== false, 'no bare page_id read');
}

function test_update_address_book_id_read_once_as_a_number()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_update_address_book.php');

	// ?id[]=1 reached h(), and htmlspecialchars() of an array is a TypeError on PHP 8.
	pg_assert_contains("\$address_id = (isset(\$_GET['id']) && is_scalar(\$_GET['id'])) ? (int) \$_GET['id'] : 0;", $source, 'the id is read once as a number');
	pg_assert_false(strpos($source, "h(\$_GET['id']") !== false, 'the raw id is never printed');
	pg_assert_false(strpos($source, "(string) \$_GET['id']") !== false, 'the raw id never reaches the query');
	pg_assert_same(2, substr_count($source, "value=\"' . (\$address_id ?: '') . '\""), 'both layouts post the number back');
}

function test_update_address_book_missing_address_fills_nothing()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_update_address_book.php');

	// Another member's address, or a removed one, gives no row: each column read was a warning.
	pg_assert_contains('if ($row) {', $source, 'fields are filled only from a found row');
	pg_assert_contains("\$user_id = (int) (\$row['user_id'] ?? 0);", $source, 'no offset of a missing user row');
	pg_assert_contains("(string) (\$_SESSION['sessionusername'] ?? '')", $source, 'no missing session key');
}
