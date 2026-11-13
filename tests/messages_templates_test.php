<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Templates (includes/fn/messages_templates.php): the SMS
 * texts as list rows, their state, own or built-in text, the events the
 * card does not offer left out, the link to the card.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/list_table.php';
require_once PG_FUNCTIONS_DIR . '/includes/fn/sms_messages.php';
require_once PG_FUNCTIONS_DIR . '/includes/fn/messages_templates.php';

function messages_templates_test_definitions()
{
	return array(
		'order_received' => array('kind' => 'order', 'label' => 'Order received', 'text' => 'Built <in> ^^order_number^^', 'fields' => array('store', 'order_number')),
		'erp_overdue' => array('kind' => 'reminder', 'label' => 'Overdue', 'text' => 'Due', 'fields' => array('store'), 'erp' => true),
		'booking_confirmed' => array('kind' => 'booking', 'label' => 'Booked', 'text' => 'Booked', 'fields' => array('store'), 'booking' => true),
		'stock_back' => array('kind' => 'stock', 'label' => 'Back', 'text' => 'Back ^^url^^', 'fields' => array('store', 'url')),
	);
}

function test_messages_templates_rows_follow_the_card()
{
	$settings = array(
		'order_received' => array('on' => true, 'text' => ''),
		'stock_back' => array('on' => false, 'text' => 'Our own <b> text'),
	);

	$rows = pg_messages_template_sms_rows(messages_templates_test_definitions(), $settings, false, false, 'settings.php?x=1&y=2');
	pg_assert_same(2, count($rows), 'the ERP and booking events stay out while those are off');
	pg_assert_same(array(1, 4), array_column($rows, 'id'), 'ids keep the event\'s place');

	$received = $rows[0]['cells'];
	pg_assert_contains('Built &lt;in&gt; ^^order_number^^', $received['text']['html'], 'an empty text is the built-in one, escaped');
	pg_assert_contains(lang('Built-in'), $received['text']['html'], 'and is marked so');
	pg_assert_contains('theme-success', $received['state']['html'], 'a switched-on event reads On');
	pg_assert_contains('href="settings.php?x=1&amp;y=2"', $received['name']['html'], 'the name leads to the card');
	pg_assert_contains('^^store^^ ^^order_number^^', $received['fields']['html'], 'its fields');

	$back = $rows[1]['cells'];
	pg_assert_contains('Our own &lt;b&gt; text', $back['text']['html'], 'a saved text is shown, escaped');
	pg_assert_false(strpos($back['text']['html'], lang('Built-in')) !== false, 'an own text is not marked built-in');
	pg_assert_contains('theme-secondary', $back['state']['html'], 'an event never switched on reads Off');

	pg_assert_same(4, count(pg_messages_template_sms_rows(messages_templates_test_definitions(), array(), true, true, 'x')), 'every event once ERP and bookings are on');
}

function test_messages_templates_every_card_event_has_a_kind_label()
{
	$kinds = pg_sms_queue_list_kinds();

	foreach (pg_sms_message_events() as $event => $definition) {
		pg_assert_true(isset($kinds[$definition['kind']]), $event . ' has a kind label');
	}
}
