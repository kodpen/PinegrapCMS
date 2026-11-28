<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for faults found in the Squarespace, Webflow and Wix readers of the
 * content import (includes/import/webflow.php, wix.php, collection.php and
 * the writers they feed): price cells read as the product import reads
 * them, and the cells each fault turned on.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/importers.php');
require_once(PG_FUNCTIONS_DIR . '/includes/import/wix.php');
require_once(PG_FUNCTIONS_DIR . '/includes/import/write_product.php');

function test_import_site_builders_price_cells_read_as_the_product_import()
{
	// A thousands comma was read as a decimal one: 1,299 became 1.30.
	pg_assert_same(129900, pg_import_webflow_money(array('variant price' => '1,299'))['price'], 'Webflow grouped price');
	pg_assert_same(129900, pg_import_webflow_money(array('variant price' => '1,299.00'))['price'], 'Webflow grouped price with cents');
	pg_assert_same(1999, pg_import_webflow_money(array('variant price' => '19.99 USD'))['price'], 'Webflow price with its currency');
	pg_assert_same(1250, pg_import_webflow_money(array('variant price' => '12,50'))['price'], 'Webflow decimal comma kept');
	pg_assert_same(129900, pg_import_wix_cents('1,299'), 'Wix grouped price');
	pg_assert_same(1999, pg_import_wix_cents('$19.99'), 'Wix price with its symbol');
	pg_assert_same(1250, pg_import_wix_cents('12,50'), 'Wix decimal comma kept');
	pg_assert_same(null, pg_import_wix_cents(''), 'empty');
	pg_assert_same(null, pg_import_wix_cents('soon'), 'words');
	pg_assert_contains('soon', pg_import_webflow_money(array('variant price' => 'soon'))['refused'], 'a word is still refused');
}
