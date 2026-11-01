<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * update_address_book.php draws its default screen with
 * get_update_address_book() and no arguments when the site has no
 * "update address book" page; get_page_type_properties() then returns null.
 * The function must read page_id and the address type settings with
 * defaults, or every visit writes "Undefined array key" and "array offset
 * on null" warnings.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_update_address_book_reads_properties_with_defaults()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_update_address_book.php');

	foreach (array('page_id', 'address_type', 'address_type_page_id') as $key) {
		pg_assert_same(0, preg_match('/\$properties\[\'' . $key . '\'\](?!\s*\?\?)/', $src),
			$key . ': read only with a ?? default');
		pg_assert_same(1, preg_match('/\$properties\[\'' . $key . '\'\]\s*\?\?\s*0\b/', $src),
			$key . ': default is 0');
	}
}
