<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visitor's events of includes/fn/ad_conversions.php: Lead for
 * the forms the shop picks, CompleteRegistration, add to cart (at most 20 a
 * cart) and begin checkout (once per order), each only with consent; their
 * event ids; the receipt page's event id for a browser pixel; Meta's ticks;
 * and the places outside the module that call them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');

function ad_conversions_visitor_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function ad_conversions_visitor_test_settings(array $more = array())
{
	return $more + array('connected' => true, 'events' => array('purchase'), 'person_notice_off' => false, 'without_person' => array(), 'lead_forms' => array());
}

// The settings keep the forms picked for Lead, as numbers.
function test_ad_conversions_visitor_settings_lead_forms()
{
	$parsed = pg_ad_conversion_settings_parse('{"connected":true,"events":["lead"],"lead_forms":[12,"7","x",-3,0]}');
	pg_assert_same(array(12, 7), $parsed['lead_forms'], 'page ids only');

	$parsed = pg_ad_conversion_settings_parse('');
	pg_assert_same(array(), $parsed['lead_forms'], 'none by default');
}

// Which services take a visitor's event: connected, the event ticked, the
// visitor's consent; for Lead the form among the picked ones.
function test_ad_conversions_visitor_plan()
{
	$settings = array(
		'meta_capi' => ad_conversions_visitor_test_settings(array('events' => array('purchase', 'add_to_cart', 'lead'), 'lead_forms' => array(5))),
		'ga4_mp' => ad_conversions_visitor_test_settings(array('events' => array('add_to_cart'), 'person_notice_off' => true)),
		'tiktok_events' => ad_conversions_visitor_test_settings(array('events' => array('add_to_cart'), 'connected' => false)),
	);

	pg_assert_same(array('meta_capi', 'ga4_mp'), pg_ad_conversion_visitor_plan('add_to_cart', true, array('marketing' => true), $settings), 'notice on, yes');
	pg_assert_same(array(), pg_ad_conversion_visitor_plan('add_to_cart', true, array(), $settings), 'notice on, no answer: nothing');
	pg_assert_same(array(), pg_ad_conversion_visitor_plan('add_to_cart', true, array('marketing' => false), $settings), 'notice on, no');
	pg_assert_same(array('ga4_mp'), pg_ad_conversion_visitor_plan('add_to_cart', false, array('marketing' => true), $settings), 'notice off: only the box');
	pg_assert_same(array(), pg_ad_conversion_visitor_plan('begin_checkout', true, array('marketing' => true), $settings), 'not ticked: off by default');
	pg_assert_same(array('meta_capi'), pg_ad_conversion_visitor_plan('lead', true, array('marketing' => true), $settings, 5), 'a picked form');
	pg_assert_same(array(), pg_ad_conversion_visitor_plan('lead', true, array('marketing' => true), $settings, 6), 'a form not picked');
	pg_assert_same(array(), pg_ad_conversion_visitor_plan('purchase', true, array('marketing' => true), $settings), 'the purchase has its own path');
}

// Begin checkout's value is the cart's lines (price times quantity, minor
// units): at the payment step the order's total column is not written yet.
function test_ad_conversions_begin_checkout_detail()
{
	$lines = array(
		array('product_name' => 'SKU-1', 'quantity' => '2', 'price' => '1250'),
		array('product_name' => 'SKU-2', 'quantity' => '1', 'price' => '999'),
	);

	$detail = pg_ad_conversion_begin_checkout_detail($lines, 'EUR', 1700000000);
	pg_assert_same(3499, $detail['value'], 'the lines, not the empty total');
	pg_assert_same('EUR', $detail['currency'], 'the currency');
	pg_assert_same('', $detail['order_number'], 'no order number yet');
	pg_assert_same(2, count($detail['contents']), 'the lines');
	pg_assert_same(0, pg_ad_conversion_begin_checkout_detail(array(), 'EUR', 1)['value'], 'an empty cart');

	$module = ad_conversions_visitor_test_source('includes/fn/ad_conversions.php');
	$start = strpos($module, 'function pg_ad_conversion_begin_checkout(');
	$end = strpos($module, 'function pg_ad_conversion_switch_row(');
	pg_assert_false(strpos(substr($module, $start, $end - $start), 'SELECT total FROM orders') !== false, 'the total column is not read');
}

// The event ids: one per form record, account, order (begin checkout) and
// cart add.
function test_ad_conversions_visitor_event_ids()
{
	pg_assert_same('lead-ABC123', pg_ad_conversion_visitor_event_id('lead', 'ABC123'), 'lead: the record reference');
	pg_assert_same('reg-42', pg_ad_conversion_visitor_event_id('complete_registration', 42), 'registration: the account');
	pg_assert_same('checkout-R1', pg_ad_conversion_visitor_event_id('begin_checkout', 'R1'), 'begin checkout: once per order');
	pg_assert_same('cart-R1-3', pg_ad_conversion_visitor_event_id('add_to_cart', 'R1', 3), 'add to cart: the cart and the count');
	pg_assert_same(64, strlen(pg_ad_conversion_visitor_event_id('lead', str_repeat('x', 100))), 'at most 64 characters');
	pg_assert_same('', pg_ad_conversion_visitor_event_id('lead', ''), 'no key, no id');
}

// At most 20 add to cart events a cart.
function test_ad_conversions_cart_add_bound()
{
	pg_assert_same(20, PG_AD_CONVERSION_CART_ADDS, 'the bound');
	pg_assert_true(pg_ad_conversion_cart_add_allowed(0), 'the first');
	pg_assert_true(pg_ad_conversion_cart_add_allowed(19), 'the twentieth');
	pg_assert_false(pg_ad_conversion_cart_add_allowed(20), 'no twenty-first');
}

// The add to cart detail: the line in minor units, nothing about a person.
function test_ad_conversions_cart_add_detail()
{
	$detail = pg_ad_conversion_cart_add_detail('SKU-1', 3, 1250, 'TRY', 1700000000);

	pg_assert_same(array(
		'value' => 3750,
		'currency' => 'TRY',
		'order_number' => '',
		'time' => 1700000000,
		'contents' => array(array('id' => 'SKU-1', 'quantity' => 3, 'price' => 1250)),
	), $detail, 'the line');

	$event = pg_ad_conversion_event(array('event' => 'add_to_cart', 'event_id' => 'cart-R1-1', 'person' => 1, 'ids' => json_encode(array('fbp' => 'fb.1.1.2')), 'detail' => json_encode($detail)), array());
	pg_assert_same('37.50', $event['value'], 'as a decimal at send');
	pg_assert_same(array('fbp' => 'fb.1.1.2'), $event['user'], 'the ids with the person');
}

// Lead and registration have no order: the e-mail goes hashed in the ids,
// never plain; without an address nothing is added.
function test_ad_conversions_visitor_ids_email()
{
	$ids = pg_ad_conversion_visitor_ids(array('ip' => '1.2.3.4'), ' Ada@Example.com ');
	pg_assert_same(hash('sha256', 'ada@example.com'), $ids['email_sha256'], 'hashed');
	pg_assert_false(strpos(json_encode($ids), 'example.com') !== false, 'never plain');
	pg_assert_same(array('ip' => '1.2.3.4'), pg_ad_conversion_visitor_ids(array('ip' => '1.2.3.4'), ''), 'no address');

	$event = pg_ad_conversion_event(array('event' => 'lead', 'event_id' => 'lead-A', 'person' => 1, 'ids' => json_encode($ids), 'detail' => json_encode(array('time' => 5))), array());
	pg_assert_same(hash('sha256', 'ada@example.com'), $event['user']['email_sha256'], 'the sender gets it');
}

// The receipt page carries the purchase's event id for a pasted pixel.
function test_ad_conversions_receipt_marker()
{
	pg_assert_same('<span hidden data-pg-event-id="R-1&amp;2"></span>', pg_ad_conversion_receipt_marker('R-1&2'), 'escaped');
	pg_assert_same('', pg_ad_conversion_receipt_marker(''), 'no reference, nothing');

	pg_assert_contains('pg_ad_conversion_receipt_marker(', ad_conversions_visitor_test_source('get_order_receipt.php'), 'legacy receipt');
	pg_assert_contains('pg_ad_conversion_receipt_marker(', ad_conversions_visitor_test_source('includes/fn/widgets.php'), 'designer order view');
}

// Meta's page ticks every event; without the person is never saved from it.
function test_ad_conversions_visitor_meta_ticks()
{
	pg_assert_same(array('purchase', 'add_to_cart', 'begin_checkout', 'complete_registration', 'lead'), array_keys(pg_ad_conversion_meta_events()), 'the ticks, in page order');

	$saved = pg_ad_conversion_meta_settings_merge(pg_ad_conversion_meta_stored(''), array(
		'pixel_id' => '123456',
		'events' => array('lead', 'add_to_cart', 'nope'),
		'without_person' => array('purchase', 'lead'),
		'lead_forms' => array('9', 'x', 4),
	), 'sealed');

	pg_assert_same(array('add_to_cart', 'lead'), $saved['events'], 'known events only, in page order');
	pg_assert_same(array(), $saved['without_person'], 'without the person: not taken from Meta\'s page');
	pg_assert_same(array(9, 4), $saved['lead_forms'], 'the picked forms');
}

// The places that call the module.
function test_ad_conversions_visitor_callers()
{
	pg_assert_contains('pg_ad_conversion_add_to_cart(', ad_conversions_visitor_test_source('cart_action.php'), 'cart_action.php');
	pg_assert_contains('pg_ad_conversion_lead(', ad_conversions_visitor_test_source('custom_form.php'), 'custom_form.php');
	pg_assert_contains('pg_ad_conversion_registration(', ad_conversions_visitor_test_source('includes/fn/auth.php'), 'auth.php');
	pg_assert_contains('pg_ad_conversion_registration(', ad_conversions_visitor_test_source('includes/fn/signin.php'), 'signin.php');

	$module = ad_conversions_visitor_test_source('includes/fn/ad_conversions.php');
	$start = strpos($module, 'function pg_ad_conversion_payment_step(');
	$end = strpos($module, "\n}\n", $start);
	pg_assert_contains('pg_ad_conversion_begin_checkout(', substr($module, $start, $end - $start), 'begin checkout at the payment step');
}
