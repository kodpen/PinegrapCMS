<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Sends (includes/fn/messages_sends.php): the row ids
 * across branches, the filters and their address, the old mail queue door,
 * each branch's SQL with its period, filters and rights, the row buttons
 * under each queue's rules, and the e-mail source kind.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/mail_queue.php';
require_once PG_FUNCTIONS_DIR . '/includes/fn/messages_sends.php';

function test_messages_sends_sid_round_trip()
{
	foreach (pg_messages_sends_branches() as $number => $branch) {
		$sid = pg_messages_sends_sid(1044, $number);
		pg_assert_same(array('branch' => $branch, 'id' => 1044), pg_messages_sends_sid_parts($sid), $branch . ' comes back');
		pg_assert_same(array('branch' => $branch, 'id' => 1044), pg_messages_sends_sid_parts((string) $sid), $branch . ' from the address');
	}

	pg_assert_same(null, pg_messages_sends_sid_parts(1044 * 8 + 3), 'the WhatsApp number has no branch yet');
	pg_assert_same(null, pg_messages_sends_sid_parts(1044 * 8), 'branch 0 is none');
	pg_assert_same(null, pg_messages_sends_sid_parts(1), 'no row id 0');
	pg_assert_same(null, pg_messages_sends_sid_parts('12abc'), 'not a number');
	pg_assert_same(null, pg_messages_sends_sid_parts(array(9)), 'not a scalar');
}

function test_messages_sends_filters_and_address()
{
	pg_assert_same(array('channel' => '', 'source' => '', 'state' => '', 'period' => '7'), pg_messages_sends_filters(array()), 'defaults: everything, 7 days');
	pg_assert_same(array('channel' => '', 'source' => '', 'state' => '', 'period' => '7'), pg_messages_sends_filters(array('channel' => 'fax', 'source' => array('x'), 'state' => "x' OR 1", 'period' => '0')), 'unknown values read as the defaults');

	$filters = pg_messages_sends_filters(array('channel' => 'sms', 'source' => 'otp', 'state' => 'uncertain', 'period' => '30'));
	pg_assert_same('messages_sends.php?channel=sms&source=otp&state=uncertain&period=30', pg_messages_sends_url($filters), 'every chosen filter in the address');
	pg_assert_same('messages_sends.php', pg_messages_sends_url(pg_messages_sends_filters(array())), 'defaults leave the address bare');
}

function test_messages_sends_mail_queue_door()
{
	pg_assert_same('messages_sends.php?channel=email', pg_messages_sends_queue_door(array()), 'the e-mail queue');
	pg_assert_same('messages_sends.php?channel=sms', pg_messages_sends_queue_door(array('tab' => 'sms', 'x' => 'y')), 'the SMS tab, nothing else carried');
	pg_assert_same('messages_sends.php?channel=email', pg_messages_sends_queue_door(array('tab' => array('sms'))), 'a nested tab is the e-mail queue');
}

/**
 * Whether every placeholder of a branch has its value.
 */
function messages_sends_test_bound(array $sql)
{
	return substr_count($sql['sql'], '?') === count($sql['params']);
}

function test_messages_sends_branches_hold_the_period_and_bind_every_value()
{
	$now = 1700000000;
	$manager = array('id' => 4, 'role' => 2);

	foreach (array(array(), array('state' => 'scheduled'), array('state' => 'failed', 'source' => 'campaign'), array('period' => '1')) as $input) {
		$filters = pg_messages_sends_filters($input);

		foreach (pg_messages_sends_branches() as $branch) {
			$sql = pg_messages_sends_branch_sql($branch, $filters, $manager, $now);
			pg_assert_true(is_array($sql), $branch . ' is read by a manager');
			pg_assert_true(messages_sends_test_bound($sql), $branch . ' binds every value: ' . json_encode($input));
			pg_assert_true(in_array($now - ((int) $filters['period'] * 86400), $sql['params'], true), $branch . ' holds the period');
		}
	}
}

function test_messages_sends_channel_filter_drops_branches()
{
	$filters = pg_messages_sends_filters(array('channel' => 'sms'));
	$manager = array('id' => 4, 'role' => 1);

	pg_assert_same(null, pg_messages_sends_branch_sql('mail', $filters, $manager, 1700000000), 'no e-mail queue');
	pg_assert_same(null, pg_messages_sends_branch_sql('email_campaign', $filters, $manager, 1700000000), 'no e-mail campaigns');
	pg_assert_same(null, pg_messages_sends_branch_sql('automatic', $filters, $manager, 1700000000), 'no automatic campaigns');
	pg_assert_true(is_array(pg_messages_sends_branch_sql('sms', $filters, $manager, 1700000000)), 'the SMS queue');
	pg_assert_true(is_array(pg_messages_sends_branch_sql('sms_campaign', $filters, $manager, 1700000000)), 'the SMS campaigns');
}

function test_messages_sends_campaign_maker_sees_only_their_campaigns()
{
	$filters = pg_messages_sends_filters(array());
	$maker = array('id' => 77, 'role' => 3, 'manage_emails' => 1);

	pg_assert_same(null, pg_messages_sends_branch_sql('mail', $filters, $maker, 1700000000), 'the e-mail queue holds password resets');
	pg_assert_same(null, pg_messages_sends_branch_sql('sms', $filters, $maker, 1700000000), 'nor the SMS queue');

	foreach (array('email_campaign', 'sms_campaign', 'automatic') as $branch) {
		$sql = pg_messages_sends_branch_sql($branch, $filters, $maker, 1700000000);
		pg_assert_true(in_array(77, $sql['params'], true), $branch . ' is kept to its maker');
		pg_assert_true((strpos($sql['sql'], 'created_user_id = ?') !== false) || (strpos($sql['sql'], 'created_by = ?') !== false), $branch . ' names the maker column');
	}

	$from = pg_messages_sends_from($filters, $maker, 1700000000, array('mail', 'sms'));
	pg_assert_true(strpos($from['sql'], 'WHERE 1 = 0') !== false, 'no branch left reads an empty table');
	pg_assert_same(null, pg_messages_sends_list_source(array('id' => 77, 'role' => 3), array()), 'no Campaign Manager right, no list');
}

function test_messages_sends_queue_state_filter_reads_the_stored_status()
{
	$manager = array('id' => 4, 'role' => 2);
	$sql = pg_messages_sends_branch_sql('mail', pg_messages_sends_filters(array('state' => 'blocked')), $manager, 1700000000);
	pg_assert_true(in_array('suppressed', $sql['params'], true), 'Blocked is the stored suppressed');
	pg_assert_true(strpos($sql['sql'], 'mail_outbox.status = ?') !== false, 'on the indexed status');

	$from = pg_messages_sends_from(pg_messages_sends_filters(array()), $manager, 1700000000, array('mail', 'sms'));
	pg_assert_same(1, substr_count($from['sql'], 'UNION ALL'), 'two branches, one union');
	pg_assert_true(substr_count($from['sql'], '?') === count($from['params']), 'the union binds every value in order');
}

function test_messages_sends_buttons_follow_each_queue()
{
	$row = function ($branch, $state, $kind = 'message') {
		return array('sid' => pg_messages_sends_sid(5, array_search($branch, pg_messages_sends_branches(), true)), 'state' => $state, 'row_kind' => $kind);
	};

	pg_assert_same(array('retry', 'delete'), pg_messages_sends_buttons($row('mail', 'failed')), 'a failed e-mail is retried');
	pg_assert_same(array('delete'), pg_messages_sends_buttons($row('mail', 'blocked')), 'a blocked e-mail is never retried');
	pg_assert_same(array('delete'), pg_messages_sends_buttons($row('mail', 'sent')), 'a sent e-mail is not sent again');
	pg_assert_same(array('retry_confirm', 'delete'), pg_messages_sends_buttons($row('sms', 'uncertain')), 'an unknown text is asked first');
	pg_assert_same(array('delete'), pg_messages_sends_buttons($row('sms', 'failed'), true), 'an expired text is not retried');
	pg_assert_same(array('delete'), pg_messages_sends_buttons($row('automatic', 'failed')), 'a campaign recipient has no Retry');
	pg_assert_same(array('delete'), pg_messages_sends_buttons($row('email_campaign', 'sending', 'campaign')), 'a campaign is deleted, not retried');
}

function test_mail_source_kind()
{
	pg_assert_same('order', pg_mail_source_kind(array('source' => 'order:1044', 'type' => 'system')), 'an order mail');
	pg_assert_same('flow', pg_mail_source_kind(array('source' => 'flow:12', 'type' => 'campaign')), 'a flow mail');
	pg_assert_same('account', pg_mail_source_kind(array('source' => 'account')), 'a kind without a record');
	pg_assert_same('campaign', pg_mail_source_kind(array('source' => 'evil:1', 'type' => 'campaign')), 'an unknown kind keeps the type');
	pg_assert_same('system', pg_mail_source_kind(array('source' => array('order'))), 'a source that is not text');
	pg_assert_same('system', pg_mail_source_kind(array()), 'nothing named is system');
}

function test_messages_sends_list_waits_for_the_branch_bound()
{
	pg_assert_same(false, pg_messages_sends_list_open(), 'the list opens with the driver step that bounds each branch');
	pg_assert_same(null, pg_messages_sends_list_source(array('id' => 1, 'role' => 0), array()), 'no source while it is closed');
}

function test_mail_queue_kinds_name_every_source_kind()
{
	require_once PG_FUNCTIONS_DIR . '/includes/fn/mail_queue_list.php';

	foreach (pg_mail_source_kinds() as $kind) {
		pg_assert_true(isset(pg_mail_queue_list_types()[$kind]), $kind . ' has a label on the mail queue');
	}
}
