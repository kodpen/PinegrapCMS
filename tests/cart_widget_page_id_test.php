<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the page id the shopping cart and express order widgets record
 * as the visitor's cart page (_pg_cart_widget_page_id(),
 * includes/fn/widgets_cart.php): the page named in the address, else the
 * page being drawn, as on the home page at the site root.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page the address names wins.
function test_cart_widget_page_id_prefers_the_named_page()
{
	pg_assert_same(12, _pg_cart_widget_page_id(12, 7), 'named page');
}

// The home page at the site root has no name in the address: the page being
// drawn is the cart's page.
function test_cart_widget_page_id_falls_back_to_the_rendered_page()
{
	pg_assert_same(7, _pg_cart_widget_page_id(0, 7), 'site root');
	pg_assert_same(7, _pg_cart_widget_page_id('0', '7'), 'database strings');
	pg_assert_same(0, _pg_cart_widget_page_id(0, 0), 'neither known');
}
