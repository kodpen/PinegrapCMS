<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Privacy centre's request box (includes/fn/privacy_requests.php):
 * the 30 days from the proof, the due pill, the reminders at 7 and 2 days,
 * the 7-day sweep of unproven rows, the list's views, a request typed in by
 * staff, and the table's place in the erasure, the inventory and the
 * closed-requests retention rule.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/privacy_requests.php');

// The 30 days run from the proof; an unproven row has no due date.
function test_privacy_request_due_at_is_thirty_days_after_the_proof()
{
	$received = mktime(10, 0, 0, 11, 1, 2026);

	pg_assert_same($received + (30 * 86400), pg_privacy_request_due_at($received), '30 days');
	pg_assert_same(0, pg_privacy_request_due_at(0), 'not proven');
	pg_assert_same($received + (7 * 86400), pg_privacy_request_sweep_at($received), 'an unproven row goes after 7 days');
}

// Whole days left, counted up; overdue is negative.
function test_privacy_request_days_left_and_tone()
{
	$now = mktime(12, 0, 0, 11, 20, 2026);

	pg_assert_same(10, pg_privacy_request_days_left($now + (9 * 86400) + 60, $now), 'a part day counts');
	pg_assert_same(0, pg_privacy_request_days_left($now, $now), 'today');
	pg_assert_same(-2, pg_privacy_request_days_left($now - (2 * 86400), $now), 'overdue');

	pg_assert_same('gray', pg_privacy_request_due_tone(8), 'grey above 7 days');
	pg_assert_same('amber', pg_privacy_request_due_tone(7), 'amber from 7 days');
	pg_assert_same('amber', pg_privacy_request_due_tone(3), 'amber at 3 days');
	pg_assert_same('rose', pg_privacy_request_due_tone(2), 'red from 2 days');
	pg_assert_same('rose', pg_privacy_request_due_tone(-1), 'red when overdue');
}

// A reminder at 7 days and one at 2, each once, only for an open request.
function test_privacy_request_reminder_stage()
{
	$now = mktime(12, 0, 0, 11, 20, 2026);
	$row = array('status' => 'open', 'due_at' => $now + (10 * 86400), 'reminded' => 0);

	pg_assert_same(0, pg_privacy_request_reminder_stage($row, $now), 'ten days: none');

	$row['due_at'] = $now + (6 * 86400);
	pg_assert_same(7, pg_privacy_request_reminder_stage($row, $now), 'within 7 days');

	$row['reminded'] = 7;
	pg_assert_same(0, pg_privacy_request_reminder_stage($row, $now), 'the 7-day one only once');

	$row['due_at'] = $now + 86400;
	pg_assert_same(2, pg_privacy_request_reminder_stage($row, $now), 'within 2 days');

	$row['reminded'] = 0;
	pg_assert_same(2, pg_privacy_request_reminder_stage($row, $now), 'a request met first at 1 day gets the 2-day one only');

	$row['reminded'] = 2;
	pg_assert_same(0, pg_privacy_request_reminder_stage($row, $now), 'the 2-day one only once');

	foreach (array('unproven', 'answered', 'refused') as $status) {
		pg_assert_same(0, pg_privacy_request_reminder_stage(array('status' => $status, 'due_at' => $now + 86400, 'reminded' => 0), $now), $status . ': none');
	}
}

// The sweep removes unproven rows past their 7 days, nothing else.
function test_privacy_request_sweep_query()
{
	$query = pg_privacy_request_sweep_query(1700000000, 500);

	pg_assert_contains('DELETE FROM privacy_requests', $query, 'deletes');
	pg_assert_contains("(status = 'unproven')", $query, 'unproven only');
	pg_assert_contains("(received_at = 0)", $query, 'never proven');
	pg_assert_contains("(due_at < '1700000000')", $query, 'past the sweep moment');
	pg_assert_contains('LIMIT 500', $query, 'batch');
}

// The list's views; an unproven row is in none of them.
function test_privacy_request_view_where()
{
	pg_assert_same("(received_at > 0) AND (status = 'open')", pg_privacy_request_view_where('open'), 'open');
	pg_assert_same("(received_at > 0) AND (status IN ('answered', 'refused'))", pg_privacy_request_view_where('answered'), 'answered');
	pg_assert_same("(received_at > 0) AND (status <> 'unproven')", pg_privacy_request_view_where('all'), 'all');
	pg_assert_same("(received_at > 0) AND (status = 'open')", pg_privacy_request_view_where('other'), 'unknown: open');
	pg_assert_same("(received_at > 0) AND (status = 'open') AND (due_at < '" . (1700000000 + (7 * 86400)) . "')", pg_privacy_request_view_where('ending', 1700000000), 'ending within 7 days');
}

// An unproven row keeps its sweep moment in due_at: every read of the box
// (the list, the Requests count, the reminders) asks for received_at > 0,
// never due_at alone, so such a row can never show as due.
function test_privacy_request_unproven_rows_are_never_due()
{
	$now = 1700000000;

	foreach (array('open', 'ending', 'answered', 'all') as $view) {
		pg_assert_contains('(received_at > 0)', pg_privacy_request_view_where($view, $now), $view);
	}

	pg_assert_contains('(received_at > 0)', pg_privacy_request_counts_where(), 'the Requests count');
	pg_assert_contains('(r.received_at > 0)', pg_privacy_request_remind_where($now), 'the reminders');
	pg_assert_same(0, pg_privacy_request_reminder_stage(array('status' => 'open', 'received_at' => 0, 'due_at' => $now + 86400, 'reminded' => 0), $now), 'an unproven row is reminded of nothing');
}

// A request typed in by staff: a kind, an address, open from now.
function test_privacy_request_from_input()
{
	$now = mktime(9, 0, 0, 11, 20, 2026);
	$out = pg_privacy_request_from_input(array('kind' => 'erasure', 'email' => ' Ayse@Example.com ', 'name' => ' Ayşe Demir ', 'message' => 'Please erase me.'), $now, 4);

	pg_assert_same(array(), $out['errors'], 'taken');
	pg_assert_same('erasure', $out['row']['kind'], 'kind');
	pg_assert_same('open', $out['row']['status'], 'open at once');
	pg_assert_same('staff', $out['row']['source'], 'typed in by staff');
	pg_assert_same('ayse@example.com', $out['row']['email'], 'address normalised');
	pg_assert_same('Ayşe Demir', $out['row']['name'], 'name trimmed');
	pg_assert_same($now, $out['row']['received_at'], 'received now');
	pg_assert_same($now + (30 * 86400), $out['row']['due_at'], 'due in 30 days');
	pg_assert_same(4, $out['row']['created_by'], 'who typed it');

	$letter = pg_privacy_request_from_input(array('kind' => 'information', 'email' => 'a@example.com', 'received_on' => '2026-11-12'), $now, 4);
	$day = mktime(0, 0, 0, 11, 12, 2026);

	pg_assert_same(array(), $letter['errors'], 'a letter of some days ago');
	pg_assert_same($day, $letter['row']['received_at'], 'its 30 days start on the day it came');
	pg_assert_same($day + (30 * 86400), $letter['row']['due_at'], 'and end 30 days after');

	$future = pg_privacy_request_from_input(array('kind' => 'information', 'email' => 'a@example.com', 'received_on' => '2026-11-21'), $now, 4);
	pg_assert_true(isset($future['errors']['received_on']), 'never in the future');

	$wrong = pg_privacy_request_from_input(array('kind' => 'information', 'email' => 'a@example.com', 'received_on' => '2026-02-31'), $now, 4);
	pg_assert_true(isset($wrong['errors']['received_on']), 'not a day');

	$today = pg_privacy_request_from_input(array('kind' => 'information', 'email' => 'a@example.com', 'received_on' => '2026-11-20'), $now, 4);
	pg_assert_same($now, $today['row']['received_at'], 'today: now');

	$bad = pg_privacy_request_from_input(array('kind' => 'gossip', 'email' => 'nobody'), $now, 4);

	pg_assert_true(isset($bad['errors']['kind']), 'an unknown kind is refused');
	pg_assert_true(isset($bad['errors']['email']), 'an address is needed');
}

// The table is in the erasure (anonymised), its label, the inventory.
function test_privacy_requests_are_anonymised_by_the_erasure()
{
	$tables = pg_privacy_erase_tables();
	$labels = pg_privacy_erase_labels();
	$inventory = pg_privacy_inventory();

	pg_assert_true(isset($tables['privacy_requests']), 'an erase entry');
	pg_assert_same('anonymise', $tables['privacy_requests']['action'], 'anonymised, not deleted');
	pg_assert_same('{token}', $tables['privacy_requests']['fields']['email'], 'the address becomes its token');
	pg_assert_same('', $tables['privacy_requests']['fields']['message'], 'the message goes');
	pg_assert_same('request_ids', $tables['privacy_requests']['find']['id'], 'a rule finds it by id');
	pg_assert_true(isset($labels['privacy_requests']), 'a label');
	pg_assert_true(isset($inventory['privacy_requests']), 'in the catalogue');
	pg_assert_same('closed_requests', $inventory['privacy_requests']['retention'], 'kept by the closed-requests rule');
}

// The closed-requests rule: 3 years after closing, its own entry only.
function test_privacy_requests_closed_rule()
{
	$catalogue = pg_privacy_rules_catalogue();
	$now = mktime(12, 0, 0, 11, 20, 2026);

	pg_assert_true(isset($catalogue['requests']), 'a rule');
	pg_assert_same(3, $catalogue['requests']['default'], '3 years by default');
	pg_assert_same('privacy_requests', $catalogue['requests']['table'], 'its table');
	pg_assert_same(array('privacy_requests'), pg_privacy_rule_entries('requests'), 'its own entry only');

	$where = pg_privacy_rule_candidate_where('requests', array('period' => 3), $now, array('pending' => "(`email` <> '')"));

	pg_assert_contains("(status IN ('answered', 'refused'))", $where, 'closed requests only');
	pg_assert_contains("(closed_at < '" . mktime(12, 0, 0, 11, 20, 2023) . "')", $where, 'closed 3 years ago');
	pg_assert_contains("(`email` <> '')", $where, 'still personal');
	pg_assert_same('', pg_privacy_rule_candidate_where('requests', array('period' => 3), $now, array('pending' => '')), 'nothing personal left: nothing');
}
