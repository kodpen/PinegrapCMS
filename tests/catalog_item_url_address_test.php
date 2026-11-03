<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_catalog_address_from_page_parameter() (includes/fn/content.php),
 * the address name get_catalog_item_from_url() looks up. A page name with no
 * slash lost its first letter ("urun-detay" looked up "run-detay"), an empty
 * address ("urun-detay/") found whichever item had a blank address name, and
 * a page[] array stopped PHP 8 with a TypeError.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_catalog_address_from_page_parameter()
{
	$cases = array(
		array('urun-detay/masa-lambasi', 'masa-lambasi'),
		array('ürün/ışık', 'ışık'),
		array('urun-detay/a/b', 'a/b'),
		// No slash: the page shows no item.
		array('urun-detay', ''),
		array('x', ''),
		array('', ''),
		// An empty address finds nothing.
		array('urun-detay/', ''),
		// A value that is not a string finds nothing.
		array(array('urun-detay/x'), ''),
		array(null, ''),
	);

	foreach ($cases as $case) {
		pg_assert_same($case[1], pg_catalog_address_from_page_parameter($case[0]), var_export($case[0], true));
	}
}
