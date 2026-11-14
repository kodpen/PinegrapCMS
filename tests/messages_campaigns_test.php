<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Campaigns (includes/fn/messages_campaigns.php): the
 * channel and state filters, the list ids of both tables, the merged order,
 * "New campaign" and the old list addresses that lead to the tab.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/messages_campaigns.php';

function test_messages_campaigns_filters()
{
	pg_assert_same(array('channel' => 'all', 'state' => 'active'), pg_messages_campaign_filters(array(), true), 'defaults');
	pg_assert_same(array('channel' => 'sms', 'state' => 'finished'), pg_messages_campaign_filters(array('channel' => 'sms', 'state' => 'finished'), true), 'both read');
	pg_assert_same('all', pg_messages_campaign_filters(array('channel' => 'sms'), false)['channel'], 'no sms without the SMS campaigns');
	pg_assert_same(array('channel' => 'all', 'state' => 'active'), pg_messages_campaign_filters(array('channel' => array('x'), 'state' => 'nope'), true), 'odd values fall back');
}

function test_messages_campaigns_channels()
{
	pg_assert_same(array('email', 'sms'), pg_messages_campaign_channels('all', true), 'all with SMS');
	pg_assert_same(array('email'), pg_messages_campaign_channels('all', false), 'all without SMS');
	pg_assert_same(array(), pg_messages_campaign_channels('sms', false), 'sms without the tables lists nothing');
	pg_assert_same(array('email'), pg_messages_campaign_channels('email', true), 'e-mail only');
}

function test_messages_campaigns_state_statuses()
{
	$statuses = pg_messages_campaign_state_statuses();
	pg_assert_same(array('ready', 'paused'), $statuses['email']['active'], 'e-mail active as My Campaigns was');
	pg_assert_same(array('complete', 'cancelled'), $statuses['email']['finished'], 'e-mail finished as My Campaign History was');
	pg_assert_same(array('queued', 'cancelled'), $statuses['sms']['finished'], 'a queued SMS campaign is finished');
}

function test_messages_campaigns_row_ids_round_trip()
{
	pg_assert_same(14, pg_messages_campaign_row_id('email', 7), 'e-mail even');
	pg_assert_same(15, pg_messages_campaign_row_id('sms', 7), 'SMS odd');
	pg_assert_same(array('email' => array(7), 'sms' => array(7, 3)), pg_messages_campaign_split_ids(array('14', 15, '7', '14', 'x', '-4', array(2), '1', '0')), 'split, deduplicated, junk dropped');
}

function test_messages_campaigns_sort()
{
	$rows = pg_messages_campaign_sort(array(
		array('id' => 2, 'modified' => 10),
		array('id' => 5, 'modified' => 30),
		array('id' => 9, 'modified' => 10),
	));
	pg_assert_same(array(5, 9, 2), array_column($rows, 'id'), 'newest change first, then higher id');
}

function test_messages_campaigns_new_button()
{
	$button = pg_messages_new_campaign_button(false);
	pg_assert_same('add_email_campaign.php', $button['url'], 'e-mail only: a plain button');
	$menu = pg_messages_new_campaign_button(true);
	pg_assert_contains('data-bs-toggle="menu"', $menu, 'a menu');
	pg_assert_contains('href="add_email_campaign.php"', $menu, 'e-mail choice');
	pg_assert_contains('href="edit_sms_campaign.php"', $menu, 'SMS choice');
}

function test_messages_campaigns_old_lists_are_doors()
{
	$history = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_email_campaign_history.php');
	pg_assert_contains("go(PATH . SOFTWARE_DIRECTORY . '/view_email_campaigns.php?state=finished')", $history, 'history leads to the finished campaigns');
	$sms = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_sms_campaigns.php');
	pg_assert_contains("go(PATH . SOFTWARE_DIRECTORY . '/view_email_campaigns.php?channel=sms')", $sms, 'SMS list leads to the SMS channel');
}

function test_messages_campaign_start_time_reads_no_time_as_zero()
{
	pg_assert_same(0, pg_messages_campaign_start_time(''), 'empty');
	pg_assert_same(0, pg_messages_campaign_start_time(null), 'null');
	pg_assert_same(0, pg_messages_campaign_start_time('0000-00-00 00:00:00'), 'the zero date');
	pg_assert_same(0, pg_messages_campaign_start_time('1000-01-01 00:00:00'), 'the API marker, shown as year 1000 before');
	pg_assert_same(0, pg_messages_campaign_start_time('not a date'), 'unreadable');
	pg_assert_same(strtotime('2026-11-14 09:30:00'), pg_messages_campaign_start_time('2026-11-14 09:30:00'), 'a real time');
}
