<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order's WhatsApp messages (includes/fn/whatsapp_messages.php):
 * a template's variables, the components a send carries, the templates the
 * card offers, what a post of the WhatsApp Messages card asks for, and where
 * the events are wired. No database and no call to Meta.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function whatsapp_messages_test_template($overrides = array())
{
	return $overrides + array(
		'id' => 7, 'name' => 'order_shipped', 'language' => 'tr', 'category' => 'UTILITY', 'status' => 'APPROVED',
		'parameter_format' => 'POSITIONAL',
		'components' => array(
			array('type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Order {{1}}'),
			array('type' => 'BODY', 'text' => 'Hi {{1}}, order {{2}} left with {{3}}.'),
			array('type' => 'FOOTER', 'text' => 'Thanks'),
			array('type' => 'BUTTONS', 'buttons' => array(
				array('type' => 'QUICK_REPLY', 'text' => 'Stop'),
				array('type' => 'URL', 'text' => 'Track', 'url' => 'https://shop.example/track/{{1}}'),
			)),
		),
	);
}

// Every off event, nothing chosen, from nothing stored.
function test_whatsapp_messages_normalize()
{
	$events = pg_whatsapp_message_normalize(null);
	pg_assert_same(array('order_received', 'order_shipped', 'order_delivered', 'stock_back'), array_keys($events));
	pg_assert_same(array('on' => false, 'template' => '', 'vars' => array()), $events['order_received']);

	// On without a template cannot be on; a broken var is dropped.
	$events = pg_whatsapp_message_normalize(array('events' => array(
		'order_received' => array('on' => true),
		'order_shipped'  => array('on' => true, 'template' => 'a|tr', 'vars' => array('body:1' => 'name', 'body:2' => array(), 'body:3' => ''))),
	));
	pg_assert_false($events['order_received']['on']);
	pg_assert_same(array('on' => true, 'template' => 'a|tr', 'vars' => array('body:1' => 'name')), $events['order_shipped']);
}

function test_whatsapp_messages_text_variables()
{
	pg_assert_same(array('1', '2', '10'), pg_whatsapp_message_text_variables('{{10}} {{2}} {{1}} {{2}}'));
	pg_assert_same(array('first_name', 'order'), pg_whatsapp_message_text_variables('Hi {{ first_name }}, {{order}} {{first_name}}'));
	pg_assert_same(array(), pg_whatsapp_message_text_variables('No variable'));
}

function test_whatsapp_messages_slots()
{
	$read = pg_whatsapp_message_slots(whatsapp_messages_test_template()['components']);
	pg_assert_same('', $read['unsupported']);
	pg_assert_same(array('header:1', 'body:1', 'body:2', 'body:3', 'button:1'), array_column($read['slots'], 'key'));
	pg_assert_same(1, $read['slots'][4]['index']);

	$read = pg_whatsapp_message_slots(array(array('type' => 'HEADER', 'format' => 'IMAGE'), array('type' => 'BODY', 'text' => 'Hi')));
	pg_assert_true($read['unsupported'] !== '', 'a media header needs a file');

	$read = pg_whatsapp_message_slots(array(array('type' => 'BUTTONS', 'buttons' => array(array('type' => 'COPY_CODE', 'example' => 'X')))));
	pg_assert_true($read['unsupported'] !== '', 'a copy code waits for the offer codes');

	pg_assert_same(array('slots' => array(), 'unsupported' => ''), pg_whatsapp_message_slots('not components'));
}

function test_whatsapp_messages_components_positional()
{
	$vars = array('header:1' => 'order_number', 'body:1' => 'name', 'body:2' => 'order_number', 'body:3' => 'carrier', 'button:1' => 'tracking_number');
	$values = array('order_number' => '1001', 'name' => "Ann\nLee", 'carrier' => '', 'tracking_number' => 'AB 12/3');

	$filled = pg_whatsapp_message_components(whatsapp_messages_test_template(), $vars, $values);
	pg_assert_true($filled['ok']);
	pg_assert_same(array(
		array('type' => 'header', 'parameters' => array(array('type' => 'text', 'text' => '1001'))),
		array('type' => 'body', 'parameters' => array(
			array('type' => 'text', 'text' => 'Ann Lee'),
			array('type' => 'text', 'text' => '1001'),
			// Meta refuses an empty parameter.
			array('type' => 'text', 'text' => '-'),
		)),
		array('type' => 'button', 'sub_type' => 'url', 'index' => '1', 'parameters' => array(array('type' => 'text', 'text' => 'AB%2012%2F3'))),
	), $filled['components']);
}

function test_whatsapp_messages_components_named_and_refusals()
{
	$template = whatsapp_messages_test_template(array(
		'parameter_format' => 'NAMED',
		'components'       => array(array('type' => 'BODY', 'text' => 'Hi {{first_name}}, {{order}} is ready.')),
	));

	$filled = pg_whatsapp_message_components($template, array('body:first_name' => 'name', 'body:order' => 'order_number'), array('name' => 'Ann', 'order_number' => '1001'));
	pg_assert_same(array(array('type' => 'body', 'parameters' => array(
		array('type' => 'text', 'parameter_name' => 'first_name', 'text' => 'Ann'),
		array('type' => 'text', 'parameter_name' => 'order', 'text' => '1001'),
	))), $filled['components']);

	$filled = pg_whatsapp_message_components($template, array('body:first_name' => 'name'), array('name' => 'Ann'));
	pg_assert_false($filled['ok'], 'a variable without a value chosen');

	pg_assert_false(pg_whatsapp_message_components(whatsapp_messages_test_template(array('status' => 'PAUSED')), array(), array())['ok'], 'paused');
	pg_assert_false(pg_whatsapp_message_components(null, array(), array())['ok'], 'missing');

	// A header value is cut to Meta's length.
	$template = whatsapp_messages_test_template(array('components' => array(array('type' => 'HEADER', 'format' => 'TEXT', 'text' => '{{1}}'))));
	$filled = pg_whatsapp_message_components($template, array('header:1' => 'store'), array('store' => str_repeat('a', 80)));
	pg_assert_same(60, strlen($filled['components'][0]['parameters'][0]['text']));
}

function test_whatsapp_messages_offered()
{
	$offered = pg_whatsapp_message_offered(array(
		whatsapp_messages_test_template(array('components' => json_encode(whatsapp_messages_test_template()['components']))),
		whatsapp_messages_test_template(array('name' => 'paused_one', 'status' => 'PAUSED')),
		whatsapp_messages_test_template(array('name' => 'with_image', 'components' => array(array('type' => 'HEADER', 'format' => 'IMAGE')))),
	));

	pg_assert_same(array('order_shipped|tr'), array_keys($offered));
	pg_assert_same(5, count($offered['order_shipped|tr']['slots']));
}

function whatsapp_messages_test_post($overrides = array())
{
	return $overrides + array('wa_msg_card' => '1');
}

function test_whatsapp_messages_decide()
{
	$before = pg_whatsapp_message_normalize(null);
	pg_assert_same(null, pg_whatsapp_message_settings_decide(array(), $before, array()));

	$offered = pg_whatsapp_message_offered(array(whatsapp_messages_test_template()));
	$key = 'order_shipped|tr';
	$var = function ($position) use ($key) {
		return pg_whatsapp_message_var_name('order_shipped', $key, $position);
	};

	// Nothing posted: nothing changes.
	$decision = pg_whatsapp_message_settings_decide(whatsapp_messages_test_post(), $before, $offered);
	pg_assert_false($decision['changed']);
	pg_assert_same(array(), $decision['marks']);

	// On without a template.
	$decision = pg_whatsapp_message_settings_decide(whatsapp_messages_test_post(array('wa_msg_order_received_on' => '1')), $before, $offered);
	pg_assert_true(isset($decision['marks']['wa_msg_order_received_template']));

	// On with a variable left empty, and one with a value of another event.
	$post = whatsapp_messages_test_post(array(
		'wa_msg_order_shipped_on' => '1', 'wa_msg_order_shipped_template' => $key,
		$var(0) => 'order_number', $var(1) => 'name', $var(2) => 'product', $var(3) => '', $var(4) => 'tracking_number',
	));
	$decision = pg_whatsapp_message_settings_decide($post, $before, $offered);
	pg_assert_true(isset($decision['marks'][$var(2)]), 'product is not a shipped value');
	pg_assert_true(isset($decision['marks'][$var(3)]), 'empty while on');

	// All filled.
	$post[$var(2)] = 'order_number';
	$post[$var(3)] = 'carrier';
	$decision = pg_whatsapp_message_settings_decide($post, $before, $offered);
	pg_assert_same(array(), $decision['marks']);
	pg_assert_true($decision['changed']);
	pg_assert_same(array('on' => true, 'template' => $key, 'vars' => array(
		'header:1' => 'order_number', 'body:1' => 'name', 'body:2' => 'order_number', 'body:3' => 'carrier', 'button:1' => 'tracking_number',
	)), $decision['events']['order_shipped']);

	// A stored template Meta no longer offers is kept, off, with its choices.
	$stored = $before;
	$stored['order_shipped'] = $decision['events']['order_shipped'];
	$decision = pg_whatsapp_message_settings_decide(whatsapp_messages_test_post(array('wa_msg_order_shipped_template' => $key)), $stored, array());
	pg_assert_same(array(), $decision['marks']);
	pg_assert_same(array('on' => false, 'template' => $key, 'vars' => $stored['order_shipped']['vars']), $decision['events']['order_shipped']);

	// Switched on again while it is not offered: refused.
	$decision = pg_whatsapp_message_settings_decide(whatsapp_messages_test_post(array('wa_msg_order_shipped_on' => '1', 'wa_msg_order_shipped_template' => $key)), $stored, array());
	pg_assert_true(isset($decision['marks']['wa_msg_order_shipped_template']));
}

function test_whatsapp_messages_value()
{
	pg_assert_same('-', pg_whatsapp_message_value('  ', 'body'));
	pg_assert_same('-', pg_whatsapp_message_value(array(), 'body'));
	pg_assert_same('a b', pg_whatsapp_message_value("a\t\n b", 'body'));
	pg_assert_same('a%2Fb', pg_whatsapp_message_value('a/b', 'button'));
}

function test_whatsapp_messages_wiring()
{
	pg_assert_contains("includes/fn/whatsapp_messages.php", file_get_contents(PG_FUNCTIONS_DIR . '/functions.php'));
	pg_assert_contains('pg_whatsapp_order_received($order_id);', file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/order_completion.php'));
	pg_assert_contains('pg_whatsapp_order_shipped(', file_get_contents(PG_FUNCTIONS_DIR . '/update_order.php'));
	pg_assert_contains('pg_whatsapp_order_delivered(', file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/shipping.php'));
	pg_assert_contains("pg_whatsapp_message_queue('stock_back'", file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/stock_notify.php'));
	pg_assert_contains('pg_stock_notify_phone_asked()', file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_catalog.php'));

	$contact = file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/contact.php');
	pg_assert_contains('pgset-whatsapp-messages', $contact);
	pg_assert_contains('pg_whatsapp_message_settings_script()', $contact);

	$save = file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/contact.save.php');
	pg_assert_contains('pg_whatsapp_message_settings_check($_POST, $liveform)', $save);
	pg_assert_contains('pg_whatsapp_message_settings_save($whatsapp_messages_post, USER_ID)', $save);
}
