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
