<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the sales quote expiry follow-up (includes/erp/quotes.php,
 * includes/erp/alerts.php) that need no database: the days the bell and the
 * dashboard count look at, the Workspace follow-up a sales quote about to
 * run out becomes, and the switch that keeps it off unless the site asks.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/quotes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/alerts.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// An open sales quote as the hourly job reads it.
function erp_quote_expiry_followup_test_quote($extra = array())
{
	return array_merge(array(
		'id'          => 31,
		'full_number' => 'TS2026000031',
		'account_id'  => 9,
		'valid_until' => '2026-10-29',
		'created_by'  => 7,
	), $extra);
}

// Today and the days ahead, today included; never backwards.
function test_erp_quote_expiry_window()
{
	pg_assert_same(array('2026-10-26', '2026-10-29'), erp_quote_expiry_window('2026-10-26', 3), 'three days ahead');
	pg_assert_same(array('2026-10-30', '2026-11-06'), erp_quote_expiry_window('2026-10-30', 7), 'across a month end');
	pg_assert_same(array('2026-10-26', '2026-10-26'), erp_quote_expiry_window('2026-10-26', -2), 'today alone for no days');
}

// The bell and the dashboard count read the same window.
function test_erp_quote_expiry_window_is_shared()
{
	pg_assert_contains('erp_quote_expiry_window(', pg_test_function_source('erp_quote_expiring_count'), 'the dashboard count');
	pg_assert_contains('erp_quote_expiry_window(', pg_test_function_source('erp_alert_quote_expiry'), 'the bell');
}

// Tagged with the sales quote, for the person who made it, due on its last
// day through the end of it and reminded at the day's reminder time.
function test_erp_quote_followup_input_shape()
{
	$input = erp_quote_followup_input(erp_quote_expiry_followup_test_quote(), 40, '09:00');

	pg_assert_same('Sales quote TS2026000031 runs out', $input['title'], 'named by its number, never its account');
	pg_assert_same(array(array('type' => 'erp_quote', 'id' => 31)), $input['refs'], 'tagged with the sales quote');
	pg_assert_same(40, $input['channel_id'], 'on the customer channel');
	pg_assert_same(array(7), $input['assignees'], 'for the person who made it');
	pg_assert_same('2026-10-29', $input['due_date'], 'due on its last day');
	pg_assert_same('23:59', $input['due_time'], 'valid through the end of the day');
	pg_assert_same(899, $input['remind_minutes'], 'reminded at 09:00 that day');
}

// No channel: the follow-up is the maker's own.
function test_erp_quote_followup_input_without_channel()
{
	$input = erp_quote_followup_input(erp_quote_expiry_followup_test_quote(), 0, '08:30');

	pg_assert_same(0, $input['channel_id'], 'no channel');
	pg_assert_same(array(7), $input['assignees'], 'still for the maker');
	pg_assert_same(929, $input['remind_minutes'], 'reminded at the day\'s time');
}

// A quote nobody made, or with no date, or with no number is not brought back
// on its own; a missing number falls back to the id.
function test_erp_quote_followup_input_refusals()
{
	pg_assert_same(null, erp_quote_followup_input(erp_quote_expiry_followup_test_quote(array('created_by' => 0)), 40, '09:00'), 'nobody to open it as');
	pg_assert_same(null, erp_quote_followup_input(erp_quote_expiry_followup_test_quote(array('valid_until' => '0000-00-00')), 40, '09:00'), 'never runs out');
	pg_assert_same(null, erp_quote_followup_input(erp_quote_expiry_followup_test_quote(array('id' => 0)), 40, '09:00'), 'no quote');

	$input = erp_quote_followup_input(erp_quote_expiry_followup_test_quote(array('full_number' => '')), 40, '09:00');
	pg_assert_same('Sales quote #31 runs out', $input['title'], 'the id when there is no number');
}

// What the follow-up input becomes is a task the follow-up code takes, and
// one whose last day is today is still reminded (at once, after 09:00).
function test_erp_quote_followup_input_passes_the_followup_check()
{
	$now = mktime(10, 0, 0, 10, 29, 2026);
	$check = ws_followup_task_data(7, erp_quote_followup_input(erp_quote_expiry_followup_test_quote(), 40, WS_TASK_REMIND_DAY_TIME), $now);

	pg_assert_true($check['ok'], 'taken on its last day');
	pg_assert_same(array('<#erp_quote:31>'), $check['data']['refs'], 'the sales quote tag');
	pg_assert_same(array(7), $check['data']['assignees'], 'for the maker');
}

// Off unless the site switches it on in config.php.
function test_erp_quote_expiry_followup_is_off_by_default()
{
	pg_assert_false(ERP_QUOTE_EXPIRY_FOLLOWUP, 'off by default');
	pg_assert_contains('ERP_QUOTE_EXPIRY_FOLLOWUP', pg_test_function_source('erp_alert_quote_expiry'), 'the bell asks the switch');
	pg_assert_contains('erp_quote_expiry_followups(', pg_test_function_source('erp_alert_quote_expiry'), 'the bell opens them');
}

// One open follow-up per sales quote: the job runs every hour and a date
// moved forward announces the quote again.
function test_erp_quote_expiry_followups_open_once()
{
	$source = pg_test_function_source('erp_quote_expiry_followups');

	pg_assert_true((bool) preg_match('/ws_followup_task_open\([^;]*,\s*true\)/', $source), 'opened with $once');
	pg_assert_contains('pg_load_user_row(', $source, 'as the person who made it');
	pg_assert_contains("['member']", $source, 'members only');
	pg_assert_false(strpos($source, 'erp_account_for_contact') !== false, 'never opens an account');
}
