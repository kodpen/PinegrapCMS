<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the addresses the express order widget of a page built with the
 * visual editor takes the tax on when it draws the page
 * (pg_express_order_tax_addresses(), includes/fn/widgets_cart.php): the
 * address the form shows and will post, so the tax shown is the tax
 * submit_order() charges.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// An empty stored recipient takes the address the form shows.
function test_express_order_tax_address_empty_recipient_takes_form()
{
	$addresses = pg_express_order_tax_addresses(
		array(array('id' => 7, 'country' => '', 'state' => '')),
		array('shipping_7_country' => 'TR', 'shipping_7_state' => 'İzmir', 'billing_country' => 'TR', 'billing_state' => ''),
		''
	);
	pg_assert_same(array('country' => 'TR', 'state' => 'İzmir'), $addresses['ship_tos'][7], 'the form address');
	pg_assert_same(array('country' => 'TR', 'state' => ''), $addresses['billing'], 'billing its own');
}

// A stored recipient keeps its address when the form holds nothing for it.
function test_express_order_tax_address_stored_recipient_kept()
{
	$addresses = pg_express_order_tax_addresses(
		array(array('id' => 7, 'country' => 'US', 'state' => 'CA')),
		array(),
		''
	);
	pg_assert_same(array('country' => 'US', 'state' => 'CA'), $addresses['ship_tos'][7], 'the stored address');
	pg_assert_same(array('country' => '', 'state' => ''), $addresses['billing'], 'no billing address');
}

// An empty form country falls back to the stored row, state included.
function test_express_order_tax_address_empty_form_country_falls_back()
{
	$addresses = pg_express_order_tax_addresses(
		array(array('id' => 7, 'country' => 'US', 'state' => 'CA')),
		array('shipping_7_country' => '', 'shipping_7_state' => 'NY'),
		''
	);
	pg_assert_same(array('country' => 'US', 'state' => 'CA'), $addresses['ship_tos'][7], 'the stored row');
}

// Same as shipping with one recipient: billing is that recipient's address.
function test_express_order_tax_address_same_as_shipping_one_recipient()
{
	$addresses = pg_express_order_tax_addresses(
		array(array('id' => 7, 'country' => '', 'state' => '')),
		array('shipping_7_country' => 'TR', 'shipping_7_state' => 'İzmir', 'billing_country' => 'US', 'billing_state' => 'CA'),
		'1'
	);
	pg_assert_same(array('country' => 'TR', 'state' => 'İzmir'), $addresses['billing'], 'the recipient address');
}

// Same as shipping with two recipients: billing keeps its own address.
function test_express_order_tax_address_same_as_shipping_two_recipients()
{
	$addresses = pg_express_order_tax_addresses(
		array(array('id' => 7, 'country' => 'TR', 'state' => 'İzmir'), array('id' => 8, 'country' => 'DE', 'state' => '')),
		array('billing_country' => 'US', 'billing_state' => 'CA'),
		'1'
	);
	pg_assert_same(array('country' => 'US', 'state' => 'CA'), $addresses['billing'], 'billing its own');
	pg_assert_same(array('country' => 'DE', 'state' => ''), $addresses['ship_tos'][8], 'the second recipient');
}
