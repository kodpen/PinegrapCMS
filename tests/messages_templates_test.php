<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Templates (includes/fn/messages_templates.php): the SMS
 * texts as list rows, their state, own or built-in text, the events the
 * card does not offer left out, the link to the card; the WhatsApp
 * templates with their state, tone, reason and Refresh.
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
require_once PG_FUNCTIONS_DIR . '/includes/fn/whatsapp.php';

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

	pg_assert_false(strpos(pg_messages_template_coming_card(), 'WhatsApp') !== false, 'WhatsApp has its own card, no Soon row');
}

function test_messages_templates_every_card_event_has_a_kind_label()
{
	$kinds = pg_sms_queue_list_kinds();

	foreach (pg_sms_message_events() as $event => $definition) {
		pg_assert_true(isset($kinds[$definition['kind']]), $event . ' has a kind label');
	}
}

function messages_templates_test_whatsapp()
{
	return array(
		array('name' => 'order_shipped', 'language' => 'tr', 'category' => 'UTILITY', 'status' => 'APPROVED', 'reason' => ''),
		array('name' => 'spring <sale>', 'language' => 'en_US', 'category' => 'MARKETING', 'status' => 'REJECTED', 'reason' => 'PROMOTIONAL'),
		array('name' => 'code', 'language' => 'tr', 'category' => 'AUTHENTICATION', 'status' => 'PENDING', 'reason' => 'NONE_LEFT'),
		array('name' => 'old', 'language' => 'tr', 'category' => 'UTILITY', 'status' => 'APPROVED', 'reason' => 'SCAM'),
		array('name' => 'draft', 'language' => 'tr', 'category' => 'UTILITY', 'status' => 'DRAFT', 'reason' => ''),
	);
}

function test_messages_templates_whatsapp_rows()
{
	$rows = pg_messages_template_whatsapp_rows(messages_templates_test_whatsapp());

	pg_assert_same(array('teal', 'rose', 'amber', 'teal', 'gray'), array_column($rows, 'tone'), 'approved, rejected, in review, approved, draft');
	pg_assert_same(lang('Approved'), $rows[0]['pill'], 'the state by its label');
	pg_assert_same(lang('Utility'), $rows[0]['category'], 'the type by its label');
	pg_assert_same(lang('Promotional content'), $rows[1]['reason'], 'a rejection\'s reason by its label');
	pg_assert_same('', $rows[2]['reason'], 'no reason while not rejected');
	pg_assert_same('', $rows[3]['reason'], 'an old reason left on an approved template is not shown');

	pg_assert_same('SOMETHING_NEW', pg_messages_template_whatsapp_reason('SOMETHING_NEW'), 'a new code as it comes');
	pg_assert_same('rose', pg_messages_template_whatsapp_tone('DISABLED'), 'disabled is rose');
	pg_assert_same('amber', pg_messages_template_whatsapp_tone('PAUSED'), 'paused is amber');
	pg_assert_same('gray', pg_messages_template_whatsapp_tone('ARCHIVED'), 'archived is gray');
}

function test_messages_templates_whatsapp_card()
{
	$rows = pg_messages_template_whatsapp_rows(messages_templates_test_whatsapp());
	$state = array('sub' => 'Read', 'error' => '', 'refresh' => 'messages_templates.php?a=1&b=2', 'token' => '<input type="hidden" name="token" value="t">', 'settings' => 'settings.php#pgset-whatsapp', 'empty' => 'None');
	$html = pg_messages_template_whatsapp_card($rows, $state);

	pg_assert_contains('<section class="pg-card pg-messages-card">', $html, 'a look A card');
	pg_assert_contains('action="messages_templates.php?a=1&amp;b=2"', $html, 'Refresh posts to the tab, escaped');
	pg_assert_contains('name="token"', $html, 'with the CSRF field');
	pg_assert_contains('value="whatsapp_refresh"', $html, 'and its action');
	pg_assert_contains('spring &lt;sale&gt;', $html, 'a name, escaped');
	pg_assert_contains('en_US', $html, 'the language');
	pg_assert_same(1, substr_count($html, h(lang('Promotional content'))), 'the one rejection\'s reason');
	pg_assert_false(strpos($html, 'None') !== false, 'no empty line while there are templates');
	pg_assert_same(5, substr_count($html, 'bi-whatsapp'), 'a row per template');

	$state['refresh'] = '';
	$html = pg_messages_template_whatsapp_card(array(), $state);
	pg_assert_false(strpos($html, 'whatsapp_refresh') !== false, 'no Refresh without its address');
	pg_assert_contains('href="settings.php#pgset-whatsapp"', $html, 'the way to the card instead');
	pg_assert_contains('None', $html, 'the empty line');

	$state['settings'] = '';
	$state['error'] = 'Meta <said> no';
	$html = pg_messages_template_whatsapp_card(array(), $state);
	pg_assert_false(strpos($html, 'href=') !== false, 'nothing to follow for a campaign maker');
	pg_assert_contains('Meta &lt;said&gt; no', $html, 'a failed read, escaped');
}
