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

function test_messages_templates_whatsapp_card_opens_the_designer()
{
	$templates = messages_templates_test_whatsapp();

	foreach ($templates as $index => $template) {
		$templates[$index]['id'] = (string) ($index + 11);
	}

	$rows = pg_messages_template_whatsapp_rows($templates);
	pg_assert_same(array(11, 12, 13, 14, 15), array_column($rows, 'id'), 'each row keeps its template\'s id');

	$state = array('sub' => 'Read', 'error' => '', 'refresh' => '', 'token' => '', 'settings' => 'settings.php#pgset-whatsapp', 'empty' => 'None', 'designer' => 'whatsapp_template.php');
	$html = pg_messages_template_whatsapp_card($rows, $state);
	pg_assert_contains('href="whatsapp_template.php?id=15"', $html, 'a draft opens in the designer');
	pg_assert_contains('href="whatsapp_template.php?id=12"', $html, 'a rejected template opens in the designer');
	pg_assert_contains('<a class="pg-button pg-button-sm" href="whatsapp_template.php">', $html, 'New template opens an empty designer');
	pg_assert_contains('href="settings.php#pgset-whatsapp"', $html, 'the way to the settings stays');

	$state['refresh'] = 'messages_templates.php';
	$html = pg_messages_template_whatsapp_card($rows, $state);
	pg_assert_contains('value="whatsapp_refresh"', $html, 'Refresh beside New template');
	pg_assert_contains('href="whatsapp_template.php"', $html, 'New template beside Refresh');

	$state['designer'] = '';
	$html = pg_messages_template_whatsapp_card($rows, $state);
	pg_assert_false(strpos($html, 'whatsapp_template.php') !== false, 'no designer for a campaign maker');
}

function test_messages_templates_email_designs_card()
{
	$rows = pg_messages_template_email_rows(array(
		array('page_id' => '7', 'page_name' => 'spring <sale>', 'page_style' => '3', 'page_timestamp' => '0'),
	));
	pg_assert_same(array(array('title' => 'spring <sale>', 'url' => 'edit_system_style.php?id=3&page=7', 'stamp' => 0)), $rows, 'a row per own design, opening its editor');

	$html = pg_messages_template_email_card($rows, true, '/pinegrap/messages_templates.php');
	pg_assert_same(5, substr_count($html, '<figure class="pg-email-ready">'), 'the five ready designs');
	pg_assert_same(5, substr_count($html, 'sandbox="" tabindex="-1"'), 'each preview frame runs nothing');
	pg_assert_contains('src="/pinegrap/messages_templates.php?preview=newsletter"', $html, 'drawn by the tab');
	pg_assert_contains('href="add_system_style.php?start=email&amp;ready=sale"', $html, 'Use this design opens it in the editor');
	pg_assert_contains('spring &lt;sale&gt;', $html, 'an own design, escaped');
	pg_assert_contains('href="edit_system_style.php?id=3&amp;page=7"', $html, 'its editor');
	pg_assert_false(strpos($html, 'Soon') !== false, 'no Soon row any more');

	$reader = pg_messages_template_email_card(array(), false, '/x.php');
	pg_assert_false(strpos($reader, 'add_system_style.php') !== false, 'Use this design only for a designer');
	pg_assert_contains(h(lang('No e-mail designs of your own yet: start one from a ready design.')), $reader, 'none of one\'s own');
	pg_assert_true(strpos($reader, 'pg-email-ready-grid') < strpos($reader, 'pg-email-own-title'), 'with none: the ready designs first');
	pg_assert_true(strpos($html, 'pg-email-own-title') < strpos($html, 'pg-email-ready-grid'), 'with some: one\'s own designs first');
	pg_assert_contains(h(lang('Your own designs first, then the ready designs to start a new one from.')), $html, 'the line follows the order');
}

function test_messages_templates_email_copies()
{
	$own = pg_messages_template_email_rows(array());
	$copies = array(
		array('page_id' => 9, 'title' => 'Welcome <E-mail>', 'kind' => 'template', 'stamp' => 0),
		array('page_id' => 21, 'title' => 'spring', 'kind' => 'campaign', 'stamp' => 0),
	);
	$html = pg_messages_template_email_card($own, true, '/x.php', $copies);
	pg_assert_contains('href="add_system_style.php?start=email&amp;copy=9"', $html, 'a copy opens as a new e-mail design');
	pg_assert_contains('Welcome &lt;E-mail&gt;', $html, 'its name, escaped');
	pg_assert_contains(h(lang('From the template')), $html, 'where it comes from');
	pg_assert_contains(h(lang('From a campaign')), $html, 'a campaign\'s design');
	pg_assert_same(2, substr_count($html, h(lang('Make a copy'))), 'a button per page');
	pg_assert_true(strpos($html, 'pg-email-ready-grid') < strpos($html, 'pg-email-copies'), 'after the ready designs');

	pg_assert_false(strpos(pg_messages_template_email_card($own, true, '/x.php'), 'pg-email-copies') !== false, 'none: no list');
	pg_assert_false(strpos(pg_messages_template_email_card($own, false, '/x.php', $copies), 'copy=') !== false, 'copies only for a designer');
}

function test_messages_templates_email_copies_have_an_end()
{
	$copies = array();
	for ($i = 1; $i <= 7; $i++) {
		$copies[] = array('page_id' => 100 + $i, 'title' => 'campaign ' . $i, 'kind' => 'campaign', 'stamp' => 0);
	}
	$copies[] = array('page_id' => 9, 'title' => 'welcome', 'kind' => 'template', 'stamp' => 0);
	$html = pg_messages_template_email_card(array(), true, '/x.php', $copies);
	$more = strpos($html, '<details class="pg-email-copies-more">');

	pg_assert_true($more !== false, 'the rest behind a summary');
	pg_assert_contains(h(lang(array('string' => 'Show all ({var:1})', 'vars' => array(8)))), $html, 'Show all, with every row counted');
	pg_assert_true(strpos($html, 'copy=105"') < $more && strpos($html, 'copy=106"') > $more, 'the newest five campaigns shown, the sixth behind it');
	pg_assert_true(strpos($html, 'copy=9"') < $more, 'a template row shown: five of each kind');
	pg_assert_same(3, substr_count($html, 'class="pg-email-part'), 'three parts, split the same way');
	pg_assert_false(strpos(pg_messages_template_email_card(array(), true, '/x.php', array_slice($copies, 2)), 'pg-email-copies-more') !== false, 'five of each kind: no summary');

	$own = pg_messages_template_email_rows(array(array('page_id' => '7', 'page_name' => 'mine', 'page_style' => '3', 'page_timestamp' => '0')));
	pg_assert_same(3, substr_count(pg_messages_template_email_card($own, true, '/x.php', $copies), 'class="pg-email-part'), 'with one\'s own designs too');
}
