<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Communication area's shell (includes/fn/messages_area.php): the five
 * tabs and their gates, the tab strip, the menu row's entries and the rows
 * that lead to today's screens.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/messages_area.php';

function test_messages_area_five_tabs_in_order()
{
	pg_assert_same(array('campaigns', 'automatic', 'templates', 'sends', 'channels'), array_keys(pg_messages_area_tabs()), 'the five tabs in the drawn order');
	$tabs = pg_messages_area_tabs();
	pg_assert_same('view_email_campaigns.php', $tabs['campaigns']['file'], 'Campaigns stays the old list address');
}

function test_messages_area_every_tab_file_exists()
{
	foreach (pg_messages_area_tabs() as $key => $tab) {
		pg_assert_true(is_file(PG_FUNCTIONS_DIR . '/' . $tab['file']), $key . ' has its screen');
	}
}

function test_messages_area_tabs_by_role()
{
	$all = array('campaigns', 'automatic', 'templates', 'sends', 'channels');
	pg_assert_same($all, pg_messages_tabs_for(0, false), 'administrator');
	pg_assert_same($all, pg_messages_tabs_for(1, false), 'designer');
	pg_assert_same($all, pg_messages_tabs_for(2, false), 'manager');
	pg_assert_same(array('campaigns', 'automatic', 'templates', 'sends'), pg_messages_tabs_for(3, true), 'campaign manager: no Channels');
	pg_assert_same(array(), pg_messages_tabs_for(3, false), 'a user without the right gets nothing');
	pg_assert_same(array(), pg_messages_tabs_for_user(array()), 'an empty user row reads as role 3 without the right');
	pg_assert_same(array('campaigns', 'automatic', 'templates', 'sends'), pg_messages_tabs_for_user(array('role' => '3', 'manage_emails' => '1')), 'a database row');
}

function test_messages_area_unknown_gate_refuses()
{
	pg_assert_false(pg_messages_gate_allows('other', 0, true), 'an unknown gate admits nobody');
}

function test_messages_area_tab_strip_marks_the_open_tab()
{
	$html = pg_messages_tab_strip('sends', array('campaigns', 'sends'), '/p/');
	pg_assert_contains('href="/p/messages_sends.php"', $html, 'the tab address');
	pg_assert_contains('class="pg-seg-item is-active" aria-current="page" href="/p/messages_sends.php"', $html, 'the open tab is current');
	pg_assert_contains('class="pg-seg-item" href="/p/view_email_campaigns.php"', $html, 'another tab is a plain link');
	pg_assert_contains('<div class="pg-seg">', $html, 'drawn as the segmented control');
	pg_assert_contains('title="Campaigns"', $html, 'a tab keeps its label as the title for its icon alone at a phone width');
	pg_assert_false(strpos($html, 'messages_channels.php') !== false, 'a tab outside the list is not drawn');
}

function test_messages_area_tab_strip_needs_two_tabs()
{
	pg_assert_same('', pg_messages_tab_strip('campaigns', array('campaigns'), ''), 'one tab draws no strip');
	pg_assert_same('', pg_messages_tab_strip('campaigns', array('nope', 'campaigns'), ''), 'an unknown key does not count');
}

function test_messages_area_menu_entries()
{
	$html = pg_messages_menu_entries(array('campaigns', 'sends'), '/p/', '');
	pg_assert_contains('/p/view_email_campaigns.php', $html, 'Campaigns entry');
	pg_assert_contains('/p/messages_sends.php', $html, 'Sends entry');
	pg_assert_contains('/p/add_email_campaign.php', $html, 'New campaign');
	pg_assert_false(strpos($html, 'messages_channels.php') !== false, 'no Channels for who may not open it');
	pg_assert_same('', pg_messages_menu_entries(array(), '/p/', ''), 'nothing for nobody');
}

function test_messages_area_place_rows()
{
	pg_assert_same('', pg_messages_place_rows('Head', array(array('title' => 'x'), 'junk')), 'rows without an address are left out');
	$html = pg_messages_place_rows('Head', array(array('title' => 'Mail <queue>', 'url' => 'mail_queue.php?tab=sms&x=1', 'text' => 'T', 'icon' => 'bi-inbox', 'tone' => 'system')));
	pg_assert_contains('href="mail_queue.php?tab=sms&amp;x=1"', $html, 'escaped address');
	pg_assert_contains('Mail &lt;queue&gt;', $html, 'escaped title');
	pg_assert_contains('pg-tone-system', $html, 'the tone');
	pg_assert_contains('<h2 class="pg-title-section">Head</h2>', $html, 'the heading');
}

function test_messages_area_menu_row_uses_the_shell()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/output.php');
	pg_assert_contains("\$menu_items[9]['title'] = 'Communication';", $source, 'row 9 is titled Communication');
	pg_assert_contains('pg_messages_menu_entries(', $source, 'row 9 lists the tabs');
	foreach (array('messages_automatic.php', 'messages_templates.php', 'messages_sends.php', 'messages_channels.php', 'view_sms_campaigns.php', 'edit_sms_campaign.php') as $file) {
		pg_assert_contains("case '" . $file . "':", $source, $file . ' marks a row');
	}
}

function test_messages_area_health_worst()
{
	pg_assert_same('ok', pg_messages_health_worst(array('ok', 'ok', 'ok')), 'all good');
	pg_assert_same('error', pg_messages_health_worst(array('ok', 'error', 'warning')), 'error wins');
	pg_assert_same('warning', pg_messages_health_worst(array('unknown', 'warning')), 'warning over unknown');
	pg_assert_same('unknown', pg_messages_health_worst(array('ok', 'odd')), 'an odd value reads as unknown');
	pg_assert_same('unknown', pg_messages_health_worst(array()), 'nothing is unknown');
}

function messages_area_channel_urls()
{
	return array('email' => 'smtp_settings.php', 'sms' => '/s#sms', 'whatsapp' => '/s#wa', 'iys' => '/s#iys');
}

function test_messages_area_channel_cards_nothing_set_up()
{
	$cards = pg_messages_channel_cards(array(), messages_area_channel_urls());
	pg_assert_same(array('email', 'sms', 'whatsapp', 'iys'), array_map(function ($c) { return $c['key']; }, $cards), 'four cards in order');
	pg_assert_same('off', $cards[0]['state'], 'e-mail with no health known');
	pg_assert_same('No sender address', $cards[0]['line'], 'e-mail without an address');
	foreach (array(1, 2, 3) as $i) {
		pg_assert_same('off', $cards[$i]['state'], $cards[$i]['key'] . ' off');
		pg_assert_same('Off', $cards[$i]['state_label'], $cards[$i]['key'] . ' label');
	}
	pg_assert_same('No provider chosen', $cards[1]['line'], 'SMS line');
	pg_assert_same('/s#wa', $cards[2]['url'], 'each card leads to its settings');
}

function test_messages_area_channel_cards_set_up()
{
	$cards = pg_messages_channel_cards(array(
		'email'    => array('from' => 'info@example.org', 'via' => 'Postmark', 'domain' => 'example.org', 'health' => 'warning'),
		'sms'      => array('provider' => 'Netgsm', 'sender' => 'SHOP', 'started' => true),
		'whatsapp' => array('ready' => false, 'started' => true),
		'iys'      => array('ready' => true, 'brand' => '123', 'test' => true, 'started' => true),
	), messages_area_channel_urls());

	pg_assert_same('check', $cards[0]['state'], 'a domain warning asks for a check');
	pg_assert_same('Sender: info@example.org · Postmark · Sending domain example.org', $cards[0]['line'], 'e-mail line');
	pg_assert_same('on', $cards[1]['state'], 'SMS connected');
	pg_assert_same('Netgsm · Sender: SHOP', $cards[1]['line'], 'SMS line');
	pg_assert_same('check', $cards[2]['state'], 'WhatsApp switched on but incomplete');
	pg_assert_same('Incomplete', $cards[2]['state_label'], 'its label');
	pg_assert_same('Brand code 123 · Test environment', $cards[3]['line'], 'İYS line');
}

function test_messages_area_channel_cards_html()
{
	$html = pg_messages_channel_cards_html(pg_messages_channel_cards(array('email' => array('from' => 'a<b>@x', 'health' => 'ok')), messages_area_channel_urls()));
	pg_assert_contains('a&lt;b&gt;@x', $html, 'escaped line');
	pg_assert_contains('<span class="pg-pill pg-tone-teal">Good</span>', $html, 'a good domain is a teal pill');
	pg_assert_contains('<span class="pg-pill pg-tone-gray">Off</span>', $html, 'an off channel is gray');
	pg_assert_contains('href="smtp_settings.php"', $html, 'the e-mail card leads to SMTP Settings');
}

function test_messages_area_seg_filter_keeps_the_other_filters()
{
	$html = pg_messages_seg_filter('state', array('active' => 'Active', 'finished' => 'Finished'), 'finished', 'active', 'list.php', array('channel' => 'sms'), 'Status');
	pg_assert_contains('<div class="pg-seg" role="group" aria-label="Status">', $html, 'a labelled segmented control');
	pg_assert_contains('class="pg-seg-item" href="list.php?channel=sms">Active</a>', $html, 'the default choice leaves its parameter out');
	pg_assert_contains('class="pg-seg-item is-active" aria-current="true" href="list.php?channel=sms&amp;state=finished">Finished</a>', $html, 'the chosen one is raised and keeps the channel');
	$plain = pg_messages_seg_filter('channel', array('all' => 'All'), 'all', 'all', 'list.php', array('state' => ''), 'Channel');
	pg_assert_contains('href="list.php"', $plain, 'no query when every filter is its default');
}

function test_messages_area_note()
{
	$html = pg_messages_note('Not <set> up', 'Set up', '/s?a=1&b=2');
	pg_assert_contains('Not &lt;set&gt; up', $html, 'the sentence is escaped');
	pg_assert_contains('href="/s?a=1&amp;b=2">Set up</a>', $html, 'the button leads to its address');
	pg_assert_contains('pg-tone-amber', $html, 'amber by default');
	pg_assert_false(strpos(pg_messages_note('Text'), '<a ') !== false, 'no button without a label');
}

function test_messages_area_channel_cards_are_links_in_a_padded_card()
{
	$html = pg_messages_channel_cards_html(pg_messages_channel_cards(array(), messages_area_channel_urls()));
	pg_assert_contains('<section class="pg-card pg-messages-card">', $html, 'the heading sits inside a padded card');
	pg_assert_contains('<a class="pg-card-inner pg-messages-channel" href="smtp_settings.php">', $html, 'the whole card is the link');
	pg_assert_contains('<div class="pg-messages-channels">', $html, 'the even grid');
}
