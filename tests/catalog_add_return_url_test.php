<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the address an add to cart from a catalog listing card or a
 * product page comes back to when catalog_detail.php refuses it
 * (_pg_catalog_return_url(), includes/fn/widgets_catalog.php): the page the
 * form was drawn on without the ?cart_added= of an earlier add, so a refusal
 * does not also show the "added to cart" notice.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The notice parameter goes; everything else stays as it was written.
function test_catalog_return_url_drops_cart_added()
{
	pg_assert_same('/urun/sandalye', _pg_catalog_return_url('/urun/sandalye?cart_added=12'), 'only parameter');
	pg_assert_same('/urunler?sort=price_asc&page_number=2', _pg_catalog_return_url('/urunler?sort=price_asc&cart_added=12&page_number=2'), 'middle parameter');
	pg_assert_same('/urunler?q=a%20b&pg_attr_3=7', _pg_catalog_return_url('/urunler?q=a%20b&pg_attr_3=7&cart_added=5'), 'other values kept as written');
	pg_assert_same('/urunler?x=1#top', _pg_catalog_return_url('/urunler?cart_added=5&x=1&cart_added=6#top'), 'every copy dropped, fragment kept');
}

// An address without the notice parameter is returned unchanged.
function test_catalog_return_url_keeps_other_addresses()
{
	pg_assert_same('/urunler', _pg_catalog_return_url('/urunler'), 'no query');
	pg_assert_same('/urunler?cart_added_by=1', _pg_catalog_return_url('/urunler?cart_added_by=1'), 'a longer name is another parameter');
	pg_assert_same('', _pg_catalog_return_url(''), 'empty');
}

// The card form, the product page form and the back-in-stock form send the
// visitor back through it; the success address still carries the notice.
function test_catalog_add_forms_use_return_url()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_catalog.php');
	pg_assert_true(strpos($src, "\$atc_request_uri  = _pg_catalog_return_url(") !== false, 'listing card form');
	pg_assert_true(strpos($src, "\$_civ_current_url = _pg_catalog_return_url(") !== false, 'product page form');
}
