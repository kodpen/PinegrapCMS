<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the overdue receivable follow-up (includes/erp/notify.php) that
 * need no database: the Workspace follow-up an overdue sales invoice becomes,
 * who it is opened as, and the switch that keeps it off unless the site asks.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/notify.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// An overdue sales invoice as the digest reads it.
function erp_overdue_followup_test_row($extra = array())
{
	return array_merge(array(
		'id'          => 52,
		'full_number' => 'PGF2026000052',
		'account_id'  => 9,
		'days'        => 15,
	), $extra);
}

// Tagged with the invoice, for the person it is opened as, due on the day it
// is announced through the end of it and reminded at the day's time.
function test_erp_overdue_followup_input_shape()
{
	$input = erp_overdue_followup_input(erp_overdue_followup_test_row(), 7, 40, '2026-10-26', '09:00');

	pg_assert_same('Collect overdue invoice PGF2026000052', $input['title'], 'named by its number, never its account');
	pg_assert_same(array(array('type' => 'invoice', 'id' => 52)), $input['refs'], 'tagged with the invoice');
	pg_assert_same(40, $input['channel_id'], 'on the customer channel');
	pg_assert_same(array(7), $input['assignees'], 'for the person it is opened as');
	pg_assert_same('2026-10-26', $input['due_date'], 'due on the day it is announced');
	pg_assert_same('23:59', $input['due_time'], 'through the end of the day');
	pg_assert_same(899, $input['remind_minutes'], 'reminded at 09:00 that day');
}

// No channel: the follow-up is the person's own; no number: the id.
function test_erp_overdue_followup_input_without_channel_or_number()
{
	$input = erp_overdue_followup_input(erp_overdue_followup_test_row(array('full_number' => '')), 7, 0, '2026-10-26', '08:30');

	pg_assert_same(0, $input['channel_id'], 'no channel');
	pg_assert_same(array(7), $input['assignees'], 'still theirs');
	pg_assert_same(929, $input['remind_minutes'], 'reminded at the day\'s time');
	pg_assert_same('Collect overdue invoice #52', $input['title'], 'the id when there is no number');
}

// Nobody to open it as, no invoice, or no day: nothing.
function test_erp_overdue_followup_input_refusals()
{
	pg_assert_same(null, erp_overdue_followup_input(erp_overdue_followup_test_row(), 0, 40, '2026-10-26', '09:00'), 'nobody');
	pg_assert_same(null, erp_overdue_followup_input(erp_overdue_followup_test_row(array('id' => 0)), 7, 40, '2026-10-26', '09:00'), 'no invoice');
	pg_assert_same(null, erp_overdue_followup_input(erp_overdue_followup_test_row(), 7, 40, '', '09:00'), 'no day');
}

// The chosen person wins over the invoice's maker; neither: nobody.
function test_erp_overdue_followup_owner()
{
	pg_assert_same(12, erp_overdue_followup_owner(12, 7), 'the chosen person');
	pg_assert_same(7, erp_overdue_followup_owner(0, 7), 'the maker when nobody is chosen');
	pg_assert_same(0, erp_overdue_followup_owner(0, 0), 'nobody: an order\'s automatic invoice');
	pg_assert_same(7, erp_overdue_followup_owner('', '7'), 'read as numbers');
}

// The input is a task the follow-up code takes, even after 09:00 on the day.
function test_erp_overdue_followup_input_passes_the_followup_check()
{
	$now = mktime(10, 0, 0, 10, 26, 2026);
	$check = ws_followup_task_data(7, erp_overdue_followup_input(erp_overdue_followup_test_row(), 7, 40, date('Y-m-d', $now), WS_TASK_REMIND_DAY_TIME), $now);

	pg_assert_true($check['ok'], 'taken on the day');
	pg_assert_same(array('<#invoice:52>'), $check['data']['refs'], 'the invoice tag');
	pg_assert_same(array(7), $check['data']['assignees'], 'for the person');
}

// Off unless the site switches it on in config.php; only invoices announced
// for the first time open one, after their stamp.
function test_erp_overdue_followup_is_off_by_default()
{
	$source = pg_test_function_source('erp_overdue_check');

	pg_assert_false(ERP_OVERDUE_FOLLOWUP, 'off by default');
	pg_assert_same(0, (int) ERP_OVERDUE_FOLLOWUP_USER, 'nobody chosen by default');
	pg_assert_contains('ERP_OVERDUE_FOLLOWUP', $source, 'the check asks the switch');
	pg_assert_contains("erp_overdue_followups(\$candidates['new'])", $source, 'only the newly announced');
}

// One open follow-up per invoice, opened as a Workspace member.
function test_erp_overdue_followups_open_once()
{
	$source = pg_test_function_source('erp_overdue_followups');

	pg_assert_true((bool) preg_match('/ws_followup_task_open\([^;]*,\s*true\)/', $source), 'opened with $once');
	pg_assert_contains('pg_load_user_row(', $source, 'as a person');
	pg_assert_contains("['member']", $source, 'members only');
	pg_assert_contains('ERP_OVERDUE_FOLLOWUP_USER', $source, 'the chosen person');
	pg_assert_contains("ws_customer_channels('erp_account'", $source, 'on the customer channel');
}
