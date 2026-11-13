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
	pg_assert_contains('class="nav-link active" aria-current="page" href="/p/messages_sends.php"', $html, 'the open tab is current');
	pg_assert_contains('class="nav-link" href="/p/view_email_campaigns.php"', $html, 'another tab is a plain link');
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
