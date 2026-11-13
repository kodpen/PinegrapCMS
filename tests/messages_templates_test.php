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
	$kinds = array('order' => 'Order', 'stock' => 'Back in stock');

	$rows = pg_messages_template_sms_rows(messages_templates_test_definitions(), $settings, false, false, $kinds);
	pg_assert_same(array('order_received', 'stock_back'), array_column($rows, 'event'), 'the ERP and booking events stay out while those are off');
	pg_assert_same(array(true, false), array_column($rows, 'on'), 'each event\'s switch');
	pg_assert_same(array(false, true), array_column($rows, 'own'), 'an empty text is the built-in one');
	pg_assert_same('Built <in> ^^order_number^^', $rows[0]['text'], 'the built-in text');
	pg_assert_same('Our own <b> text', $rows[1]['text'], 'the saved text');
	pg_assert_same('Back in stock', $rows[1]['kind'], 'the kind shown by its label');

	pg_assert_same(4, count(pg_messages_template_sms_rows(messages_templates_test_definitions(), array(), true, true, $kinds)), 'every event once ERP and bookings are on');
}

function test_messages_templates_card_markup()
{
	$rows = pg_messages_template_sms_rows(messages_templates_test_definitions(), array('stock_back' => array('on' => true, 'text' => 'Our own <b> text')), false, false, array());
	$html = pg_messages_template_sms_card($rows, 'settings.php?x=1&y=2');

	pg_assert_contains('<section class="pg-card pg-messages-card">', $html, 'a look A card');
	pg_assert_contains('<h2 class="pg-title-section">', $html, 'its section title');
	pg_assert_contains('href="settings.php?x=1&amp;y=2"', $html, 'rows and the button lead to the card, escaped');
	pg_assert_contains('Built &lt;in&gt; ^^order_number^^', $html, 'a built-in text, escaped');
	pg_assert_contains('Our own &lt;b&gt; text', $html, 'a saved text, escaped');
	pg_assert_same(1, substr_count($html, '<b>' . h(lang('Built-in')) . '</b>'), 'only the built-in text is marked');
	pg_assert_same(1, substr_count($html, 'pg-pill pg-tone-teal'), 'one event on');
	pg_assert_same(1, substr_count($html, 'pg-pill pg-tone-gray'), 'one event off');

	pg_assert_contains('pgset-whatsapp', pg_messages_template_coming_card('settings.php#pgset-whatsapp'), 'WhatsApp leads to its card for a manager');
	pg_assert_false(strpos(pg_messages_template_coming_card(''), 'href=') !== false, 'and nowhere for a campaign maker');
}

function test_messages_templates_every_card_event_has_a_kind_label()
{
	$kinds = pg_sms_queue_list_kinds();

	foreach (pg_sms_message_events() as $event => $definition) {
		pg_assert_true(isset($kinds[$definition['kind']]), $event . ' has a kind label');
	}
}
