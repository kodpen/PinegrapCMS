<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the kind of a design template's product
 * (_pg_tpl_product_kind() in includes/fn/designer.php): a plain product is
 * taxed and shipped and keeps the column's own selection type; a product
 * that says 'shippable' => false is not shipped; a gift that says
 * 'donation' => true takes the donation selection type, so the visitor
 * types the amount, and is neither taxed nor shipped, whatever else it says.
 * The column's values are the ones the product screen offers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_tpl_product_kind_plain_and_not_shipped()
{
	pg_assert_same(array('selection_type' => '', 'taxable' => '1', 'shippable' => '1'),
		_pg_tpl_product_kind(array('sku' => 'A-1')), 'a plain product is taxed and shipped');
	pg_assert_same(array('selection_type' => '', 'taxable' => '1', 'shippable' => '0'),
		_pg_tpl_product_kind(array('sku' => 'T-1', 'shippable' => false)), 'a ticket is not shipped');
	pg_assert_same('1', _pg_tpl_product_kind(array('shippable' => true))['shippable'], 'shippable true ships');
	pg_assert_same('1', _pg_tpl_product_kind('not an array')['shippable'], 'a broken entry is a plain product');
}

function test_tpl_product_kind_donation()
{
	$gift = array('selection_type' => 'donation', 'taxable' => '0', 'shippable' => '0');
	pg_assert_same($gift, _pg_tpl_product_kind(array('sku' => 'CH-GENERAL', 'donation' => true)), 'a gift');
	pg_assert_same($gift, _pg_tpl_product_kind(array('donation' => true, 'shippable' => true)), 'a gift is never shipped');
	pg_assert_same('', _pg_tpl_product_kind(array('donation' => false))['selection_type'], 'donation false is a plain product');
	// The selection type is one the product screen offers, the one the cart
	// and the order read as a typed amount.
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/product_builder.php');
	pg_assert_contains("'donation' => lang('Donation')", $src, 'the product screen offers the donation type');
}
