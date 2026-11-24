<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the opt-out reading a commercial campaign recipient gets at the
 * send (includes/fn/email_campaign_send.php), in Send now and the campaign
 * job: a recipient who unticked an included subscription group after the
 * campaign was made is not mailed, and an automatic campaign's recipient
 * stored without a contact is refused when every contact with the address
 * opted out. No database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_send.php');

function test_email_campaign_opt_out_rule()
{
	pg_assert_same('contact', pg_email_campaign_opt_out_rule('manual', 12), 'a recipient picked from the contacts needs an opted-in contact');
	pg_assert_same('contact', pg_email_campaign_opt_out_rule('automatic', '12'), 'so does an automatic one with a contact');
	pg_assert_same('address', pg_email_campaign_opt_out_rule('automatic', 0), 'an automatic recipient stored without a contact is read by its address');
	pg_assert_same('address', pg_email_campaign_opt_out_rule('automatic', ''), 'an empty contact id is no contact');
	pg_assert_same('', pg_email_campaign_opt_out_rule('manual', 0), 'an address the sender typed in is sent as entered');
}

function test_email_campaign_address_refused()
{
	pg_assert_true(pg_email_campaign_address_refused(2, 0), 'contacts with the address, none opted in: refused');
	pg_assert_false(pg_email_campaign_address_refused(2, 1), 'one opted-in contact keeps the address');
	pg_assert_false(pg_email_campaign_address_refused(0, 0), 'an address no contact has is sent');
}

function test_email_campaign_left_lists()
{
	pg_assert_true(pg_email_campaign_left_lists(1, 0), 'unticked the only included list: left');
	pg_assert_false(pg_email_campaign_left_lists(1, 1), 'still takes mail from another included list');
	pg_assert_false(pg_email_campaign_left_lists(0, 0), 'no refusal recorded (a group deleted, a member removed by staff): the global opt-in decides');
	pg_assert_false(pg_email_campaign_left_lists('0', '3'), 'counts may come as strings');
}

function test_email_campaign_list_counts_query()
{
	$sql = pg_email_campaign_list_counts_query("7'", "a'b@example.com");

	pg_assert_contains("contact_groups_email_campaigns_xref.email_campaign_id = '7'", $sql, 'the campaign id is cast');
	pg_assert_contains("a\\'b@example.com", $sql, 'the address is escaped');
	pg_assert_contains("contact_groups_email_campaigns_xref.type = 'included'", $sql, 'only included groups are read');
	pg_assert_contains('opt_in.opt_in = 0', $sql, 'a refusal is an opt_in row of 0');
	pg_assert_contains('contact_groups.email_subscription = 1', $sql, 'refusals count for subscription groups only');
	pg_assert_contains('(opt_in.opt_in IS NULL) OR (opt_in.opt_in = 1)', $sql, 'a member with no row or a yes still takes the list, as the pick reads it');
	pg_assert_contains('contacts.opt_in = 1', $sql, 'a list still taken needs the global opt-in');
	pg_assert_contains('AS opted_out', $sql, 'opted_out column');
	pg_assert_contains('AS still_in', $sql, 'still_in column');
}

function test_email_campaign_both_paths_use_the_opt_out_reading()
{
	$send_now = (string) file_get_contents(PG_FUNCTIONS_DIR . '/send_email_campaign.php');
	$job = (string) file_get_contents(PG_FUNCTIONS_DIR . '/email_campaign_job.php');

	pg_assert_contains('pg_email_campaign_recipient_opted_out(', $send_now, 'Send now asks the shared opt-out reading');
	pg_assert_contains('pg_email_campaign_recipient_opted_out(', $job, 'the job asks the shared opt-out reading');
}

function test_email_campaign_send_now_fills_order_reference_code()
{
	$send_now = (string) file_get_contents(PG_FUNCTIONS_DIR . '/send_email_campaign.php');

	pg_assert_contains("'order_reference_code'", $send_now, 'Send now fills ^^order_reference_code^^ of an abandoned-cart campaign, as the job does');
}
