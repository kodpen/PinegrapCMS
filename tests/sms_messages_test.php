<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order's text messages (includes/fn/sms_messages.php): filling
 * a template in, reading the stored switches and what a post of the SMS
 * Messages card asks for. No database and no provider.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function sms_messages_test_post($overrides = array())
{
	return $overrides + array(
		'sms_msg_card'                 => '1',
		'sms_msg_order_received_text'  => '',
		'sms_msg_order_shipped_text'   => '',
		'sms_msg_order_delivered_text' => '',
	);
}

// Fields are filled in; one without a value leaves no gap behind.
function test_sms_messages_render_fills_and_closes_gaps()
{
	pg_assert_same(
		'Shop: Order 1001 shipped. 123456789 https://t.example/1',
		pg_sms_message_render('^^store^^: Order ^^order_number^^ shipped. ^^carrier^^ ^^tracking_number^^ ^^tracking_url^^', array(
			'store'           => 'Shop',
			'order_number'    => '1001',
			'carrier'         => '',
			'tracking_number' => '123456789',
			'tracking_url'    => 'https://t.example/1',
		)));

	pg_assert_same('Hi Ann.', pg_sms_message_render('Hi ^^name^^ ^^unknown^^.', array('name' => 'Ann')));
}

// A value typed with a line break goes in on one line.
function test_sms_messages_render_keeps_values_on_one_line()
{
	pg_assert_same("Dear Ann Lee,\nthanks", pg_sms_message_render("Dear ^^name^^,\nthanks", array('name' => "Ann\r\nLee")));
}

function test_sms_messages_template_fields_in_order()
{
	pg_assert_same(array('store', 'order_number'), pg_sms_message_template_fields('^^store^^ ^^order_number^^ ^^store^^ ^^not a field^^'));
}

// Every event is there and off until it is switched on.
function test_sms_messages_normalize_defaults_off()
{
	$events = pg_sms_message_normalize(null);

	pg_assert_same(array('order_received', 'order_shipped', 'order_delivered', 'stock_back', 'cod_code', 'signin_code'), array_keys($events));
	pg_assert_same(array('on' => false, 'text' => ''), $events['order_shipped']);

	$events = pg_sms_message_normalize(array('events' => array('order_received' => array('on' => 1, 'text' => 'X'), 'order_shipped' => 'junk')));
	pg_assert_same(array('on' => true, 'text' => 'X'), $events['order_received']);
	pg_assert_same(array('on' => false, 'text' => ''), $events['order_shipped']);
}

// A pane without the card says nothing about it.
function test_sms_messages_decide_ignores_other_panes()
{
	pg_assert_same(null, pg_sms_message_settings_decide(array('sms_provider' => 'netgsm'), pg_sms_message_normalize(null)));
}

// The built-in text sent back unchanged is stored as the built-in one.
function test_sms_messages_decide_keeps_builtin_text_empty()
{
	$events = pg_sms_message_events();

	$decision = pg_sms_message_settings_decide(sms_messages_test_post(array(
		'sms_msg_order_received_on'   => '1',
		'sms_msg_order_received_text' => $events['order_received']['text'],
	)), pg_sms_message_normalize(null));

	pg_assert_same(array('on' => true, 'text' => ''), $decision['events']['order_received']);
	pg_assert_true($decision['changed']);
	pg_assert_same(array(), $decision['marks']);
}

// Nothing changed is not written again.
function test_sms_messages_decide_unchanged()
{
	$before = pg_sms_message_normalize(array('events' => array('order_delivered' => array('on' => true, 'text' => 'Delivered ^^order_number^^'))));

	$decision = pg_sms_message_settings_decide(sms_messages_test_post(array(
		'sms_msg_order_delivered_on'   => '1',
		'sms_msg_order_delivered_text' => "Delivered ^^order_number^^\r\n",
	)), $before);

	pg_assert_false($decision['changed']);
}

// A field the event has no value for, and a text too long, are marked.
function test_sms_messages_decide_marks_unknown_field_and_length()
{
	$decision = pg_sms_message_settings_decide(sms_messages_test_post(array(
		'sms_msg_order_received_text'  => 'Order ^^order_number^^ via ^^tracking_url^^',
		'sms_msg_order_delivered_text' => str_repeat('a', pg_sms_message_max_length() + 1),
	)), pg_sms_message_normalize(null));

	pg_assert_contains('^^tracking_url^^', $decision['marks']['sms_msg_order_received_text']);
	pg_assert_true(isset($decision['marks']['sms_msg_order_delivered_text']));
	pg_assert_false(isset($decision['marks']['sms_msg_order_shipped_text']));
}

// The built-in texts use only their own event's fields.
function test_sms_messages_builtin_texts_use_known_fields()
{
	foreach (pg_sms_message_events() as $event => $definition) {
		pg_assert_same(array(), array_values(array_diff(pg_sms_message_template_fields($definition['text']), $definition['fields'])), $event);
		pg_assert_true(isset(pg_sms_kinds()[$definition['kind']]) && !pg_sms_kinds()[$definition['kind']], $event);
	}
}

// A marketplace's order is not texted by the shop: the marketplace tells its
// own buyer, and the phone on the order may be the marketplace's relay. The
// order screen, a bulk mark or an import adding tracking to such an order
// sends nothing.
function test_sms_messages_marketplace_order_is_not_texted()
{
	pg_assert_false(pg_sms_order_texted(array('type' => 'marketplace')), 'a marketplace order');
	pg_assert_true(pg_sms_order_texted(array('type' => 'online')), 'a web order');
	pg_assert_true(pg_sms_order_texted(array('type' => 'offline')), 'an order taken by the shop');
	pg_assert_true(pg_sms_order_texted(array()), 'a row without the type');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/sms_messages.php');
	pg_assert_contains('if (!$order || !pg_sms_order_texted($order)) {', $source, 'every order message reads the order through the check');
}


// A code's message must carry the code; the built-in one stays allowed.
function test_sms_messages_code_event_needs_code()
{
	$decision = pg_sms_message_settings_decide(sms_messages_test_post(array(
		'sms_msg_cod_code_on'   => '1',
		'sms_msg_cod_code_text' => '^^store^^: confirm your order',
	)), pg_sms_message_normalize(null));

	pg_assert_contains('^^code^^', $decision['marks']['sms_msg_cod_code_text']);

	$decision = pg_sms_message_settings_decide(sms_messages_test_post(array(
		'sms_msg_cod_code_on'   => '1',
		'sms_msg_cod_code_text' => 'Code ^^code^^',
	)), pg_sms_message_normalize(null));

	pg_assert_same(array(), $decision['marks']);
	pg_assert_same('otp', pg_sms_message_events()['cod_code']['kind']);
	pg_assert_false(pg_sms_message_normalize(null)['cod_code']['on'], 'off by default');
}
