<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the address of a product group made with the Catalog Manager's
 * New Product Group: the group is created under the placeholder name and
 * named in place right after, and that first name gives it its address. A
 * group renamed later keeps its address (a live URL is not moved).
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
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/handle_catalog.php');

function test_catalog_new_group_address_slug()
{
	pg_assert_same('Yeni_Urun_Grubu', pg_catalog_address_slug('Yeni Ürün Grubu'));
	pg_assert_same('Cay_Bardagi', pg_catalog_address_slug('  Çay Bardağı '));
	pg_assert_same('a[1]', pg_catalog_address_slug('a[1]'), 'the bracket stays');
	pg_assert_same('item', pg_catalog_address_slug('中文'), 'a script it cannot spell');
}

function test_catalog_new_group_address_follows_placeholder()
{
	$placeholder = 'Yeni Ürün Grubu';
	$row = function ($name, $address) {
		return array('name' => $name, 'address_name' => $address);
	};

	pg_assert_true(pg_explorer_catalog_address_follows_name($row($placeholder, 'Yeni_Urun_Grubu'), $placeholder), 'the first one');
	pg_assert_true(pg_explorer_catalog_address_follows_name($row($placeholder, 'Yeni_Urun_Grubu[3]'), $placeholder), 'a numbered one');
	pg_assert_false(pg_explorer_catalog_address_follows_name($row('Kupalar', 'Yeni_Urun_Grubu[3]'), $placeholder), 'already named: the address stays');
	pg_assert_false(pg_explorer_catalog_address_follows_name($row($placeholder, 'kampanya'), $placeholder), 'an address set by hand');
	pg_assert_false(pg_explorer_catalog_address_follows_name($row($placeholder, 'Yeni_Urun_Grubu_x'), $placeholder));
	pg_assert_false(pg_explorer_catalog_address_follows_name(null, $placeholder), 'no row');
}
