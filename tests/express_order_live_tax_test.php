<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the express order page's live tax (the eo_tax_totals action,
 * includes/panel/commerce.php): the address fields it takes from a request
 * (pg_express_order_live_tax_fields()) and the answer it gives
 * (pg_express_order_live_tax_answer(), includes/fn/widgets_cart.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Only the order's own recipients are read; any other id is dropped.
function test_express_order_live_tax_fields_own_recipients_only()
{
	$posted = pg_express_order_live_tax_fields(
		array(
			'billing_country' => ' TR ', 'billing_state' => 'İzmir',
			'shipping_7_country' => 'DE', 'shipping_7_state' => '',
			'shipping_9_country' => 'US', 'shipping_9_state' => 'CA',
			'billing_first_name' => 'Ada',
		),
		array('7'),
		true
	);
	pg_assert_same(
		array('billing_country' => 'TR', 'billing_state' => 'İzmir', 'shipping_7_country' => 'DE', 'shipping_7_state' => ''),
		$posted['fields'],
		'billing and recipient 7'
	);
	pg_assert_same('', $posted['same_as_shipping'], 'not ticked');
}

// Shipping off: no recipient field, whatever is posted.
function test_express_order_live_tax_fields_shipping_off()
{
	$posted = pg_express_order_live_tax_fields(
		array('billing_country' => 'TR', 'shipping_7_country' => 'DE', 'billing_same_as_shipping' => '1'),
		array(7),
		false
	);
	pg_assert_same(array('billing_country' => 'TR', 'billing_state' => ''), $posted['fields'], 'billing alone');
	pg_assert_same('1', $posted['same_as_shipping'], 'ticked');
}

// A value that is not a string is read as empty; a long one is cut.
function test_express_order_live_tax_fields_odd_values()
{
	$posted = pg_express_order_live_tax_fields(
		array('billing_country' => array('TR'), 'billing_state' => str_repeat('a', 300), 'billing_same_as_shipping' => 'yes'),
		array(),
		true
	);
	pg_assert_same('', $posted['fields']['billing_country'], 'an array');
	pg_assert_same(100, mb_strlen($posted['fields']['billing_state']), 'cut');
	pg_assert_same('', $posted['same_as_shipping'], 'only 1 ticks');
}

// The answer carries the figures the page redraws, as integers.
function test_express_order_live_tax_answer()
{
	$answer = pg_express_order_live_tax_answer(array(
		'subtotal' => 10000, 'tax' => '2000', 'total' => 12500, 'total_with_surcharge' => 12750,
		'surcharge' => 250, 'shipping' => 499, 'shipping_drawn' => 500, 'discount' => 0,
	));
	pg_assert_same(
		array('status' => 'success', 'tax' => 2000, 'total' => 12500, 'total_with_surcharge' => 12750,
			'surcharge' => 250, 'shipping_drawn' => 500, 'shipping' => 499),
		$answer
	);
}

// The page script asks for the tax on an address change even with nothing to
// ship: the call is wired before the script stops for want of recipients.
function test_express_order_live_tax_script_wired_without_recipients()
{
	$js = _eo_render_widget_js(7, 'eo_form', false, array(), '/', array('shipping_drawn' => 0, 'shipping' => 0, 'total' => 12000));
	$call = strpos($js, "action: 'eo_tax_totals'");
	$stop = strpos($js, 'if (!RIDS.length) return;');
	pg_assert_true($call !== false && $stop !== false && $call < $stop, 'wired before the recipient check');
	pg_assert_contains('billing_same_as_shipping', $js, 'the tick asks too');
}
