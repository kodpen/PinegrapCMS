<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for series tickets: a repeating event whose dates share their
 * places gives one ticket for the whole series, kept on date 0, entered at
 * the door once on each date (event_ticket_checkins). Which events give
 * them, the dates, the door's matching, entry and undo, the counts, the
 * pick list's first open date, the ticket page's first and last date and
 * its calendar file of every date, the API's entries and the table's step.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/event_check_in.php');

function event_ticket_series_test_event($overrides = array())
{
	return $overrides + array(
		'recurrence'              => '1',
		'recurrence_type'         => 'week',
		'separate_reservations'   => '0',
		'total_recurrence_number' => '9',
	);
}

function event_ticket_series_test_ticket($overrides = array())
{
	return $overrides + array(
		'id'                => 41,
		'order_id'          => 9001,
		'order_item_id'     => 501,
		'unit'              => 1,
		'product_id'        => 77,
		'calendar_event_id' => 12,
		'recurrence_number' => 0,
		'state'             => 'valid',
		'used_at'           => 0,
		'used_by'           => 0,
	);
}

// The entries kept in memory, and a stand-in for pg_event_tickets_query()
// that runs the door's statements the way MySQL does.
function event_ticket_series_test_query($sql, array $params)
{
	$GLOBALS['series_test_queries'][] = array('sql' => $sql, 'params' => $params);

	if (strpos($sql, 'INSERT IGNORE INTO event_ticket_checkins') !== false) {
		list($date, $user, $ticket) = $params;
		$key = $ticket . ':' . $date;

		if (($GLOBALS['series_test_state'] !== 'valid') || isset($GLOBALS['series_test_entries'][$key])) {
			return 0;
		}

		$GLOBALS['series_test_entries'][$key] = array('checked_at' => 1763600000, 'checked_by' => $user);

		return 1;
	}

	if (strpos($sql, 'DELETE FROM event_ticket_checkins') !== false) {
		$key = $params[0] . ':' . $params[1];
		$had = isset($GLOBALS['series_test_entries'][$key]);
		unset($GLOBALS['series_test_entries'][$key]);

		return $had ? 1 : 0;
	}

	return 0;
}

function event_ticket_series_test_reset($state = 'valid')
{
	$GLOBALS['series_test_queries'] = array();
	$GLOBALS['series_test_entries'] = array();
	$GLOBALS['series_test_state'] = $state;
}

// The ticket on a pick's date as the door reads it (pg_check_in_series_overlay()).
function event_ticket_series_test_read($date)
{
	$key = '41:' . $date;
	$entry = $GLOBALS['series_test_entries'][$key] ?? null;

	return pg_check_in_series_state(event_ticket_series_test_ticket(array('state' => $GLOBALS['series_test_state'])), range(0, 9), $date, $entry);
}

// A repeating event whose dates share their places; none other.
function test_event_ticket_series_which_events()
{
	pg_assert_true(pg_event_ticket_is_series(event_ticket_series_test_event()), 'weekly, shared places');
	pg_assert_false(pg_event_ticket_is_series(event_ticket_series_test_event(array('separate_reservations' => '1'))), 'places per date');
	pg_assert_false(pg_event_ticket_is_series(event_ticket_series_test_event(array('recurrence' => '0'))), 'a single event');
	pg_assert_false(pg_event_ticket_is_series(event_ticket_series_test_event(array('total_recurrence_number' => '0'))), 'no repeat');
	pg_assert_false(pg_event_ticket_is_series(event_ticket_series_test_event(array('recurrence_type' => ''))), 'an unknown repeat type is no repeat');
	pg_assert_false(pg_event_ticket_is_series(false), 'no event');

	pg_assert_same(array(0, 1, 3, 5), pg_event_ticket_series_numbers(5, array('2', 4, 9)), 'dates 0 to the repeat count, removed ones left out');
	pg_assert_same(array(0), pg_event_ticket_series_numbers(0, array()), 'one date');
}

// The second week's scan enters the ticket; the same week twice answers used.
function test_event_ticket_series_entered_once_per_date()
{
	event_ticket_series_test_reset();
	$staff = array('id' => 5, 'role' => 1);
	$yes = function () {
		return true;
	};
	$week2 = array('kind' => 'event', 'event_id' => 12, 'recurrence_number' => 1, 'product_id' => 0);
	$week3 = array('kind' => 'event', 'event_id' => 12, 'recurrence_number' => 2, 'product_id' => 0);
	$find2 = function () {
		return event_ticket_series_test_read(1);
	};
	$find3 = function () {
		return event_ticket_series_test_read(2);
	};

	$first = pg_check_in_run($find2, $week2, $staff, $yes, 'event_ticket_series_test_query');
	pg_assert_same('valid', $first['answer'], 'week 2 lets it in');
	pg_assert_same(1, $first['ticket']['series_date'], 'on week 2');
	pg_assert_same('used', $first['ticket']['state'], 'read again as entered');
	pg_assert_contains('INSERT IGNORE INTO event_ticket_checkins', $GLOBALS['series_test_queries'][0]['sql'], 'an entry, not the row');
	pg_assert_contains("(state = 'valid')", $GLOBALS['series_test_queries'][0]['sql'], 'only from a valid row');
	pg_assert_same(array(1, 5, 41), $GLOBALS['series_test_queries'][0]['params'], 'date, who, ticket');

	pg_assert_same('used', pg_check_in_run($find2, $week2, $staff, $yes, 'event_ticket_series_test_query')['answer'], 'week 2 again: already checked in');
	pg_assert_same('valid', pg_check_in_run($find3, $week3, $staff, $yes, 'event_ticket_series_test_query')['answer'], 'week 3 lets it in again');

	pg_assert_same(1, pg_check_in_ticket_event($first['ticket'])['recurrence_number'], 'ticket.checked_in names the date entered');

	// Undo deletes that date's entry only.
	pg_assert_true(pg_check_in_undo(41, 'event_ticket_series_test_query', pg_check_in_series_date($first['ticket'], $week2)), 'undone on week 2');
	pg_assert_false(isset($GLOBALS['series_test_entries']['41:1']), 'week 2 entry gone');
	pg_assert_true(isset($GLOBALS['series_test_entries']['41:2']), 'week 3 entry kept');
	pg_assert_false(pg_check_in_undo(41, 'event_ticket_series_test_query', 1), 'nothing to undo twice');
}

// A void series ticket is refused on every date; its row's state decides.
function test_event_ticket_series_void_refused_on_every_date()
{
	event_ticket_series_test_reset('void');
	$staff = array('id' => 5, 'role' => 1);
	$yes = function () {
		return true;
	};

	foreach (array(0, 4, 9) as $date) {
		$pick = array('kind' => 'event', 'event_id' => 12, 'recurrence_number' => $date, 'product_id' => 0);
		$find = function () use ($date) {
			return event_ticket_series_test_read($date);
		};

		pg_assert_same('void', pg_check_in_run($find, $pick, $staff, $yes, 'event_ticket_series_test_query')['answer'], 'void on date ' . $date);
	}

	pg_assert_same(array(), $GLOBALS['series_test_queries'], 'nothing written');
}

// A series ticket belongs to every date of its series, and to no other event.
function test_event_ticket_series_matches_its_dates()
{
	$ticket = pg_check_in_series_state(event_ticket_series_test_ticket(), array(0, 1, 3), 3, null);

	pg_assert_true(pg_check_in_ticket_matches($ticket, array('kind' => 'event', 'event_id' => 12, 'recurrence_number' => 3, 'product_id' => 0)), 'a date of the series');
	pg_assert_false(pg_check_in_ticket_matches($ticket, array('kind' => 'event', 'event_id' => 12, 'recurrence_number' => 2, 'product_id' => 0)), 'a removed date');
	pg_assert_false(pg_check_in_ticket_matches($ticket, array('kind' => 'event', 'event_id' => 13, 'recurrence_number' => 3, 'product_id' => 0)), 'another event');
	pg_assert_same(3, pg_check_in_series_date($ticket, array('kind' => 'event', 'event_id' => 12, 'recurrence_number' => 3, 'product_id' => 0)), 'written on the pick\'s date');

	$plain = event_ticket_series_test_ticket(array('recurrence_number' => 4));
	pg_assert_same(null, pg_check_in_series_date($plain, array('kind' => 'event', 'event_id' => 12, 'recurrence_number' => 4, 'product_id' => 0)), 'a ticket of one date writes its row');
	pg_assert_false(pg_check_in_ticket_matches($plain, array('kind' => 'event', 'event_id' => 12, 'recurrence_number' => 1, 'product_id' => 0)), 'a ticket of one date stays on it');

	$other = pg_check_in_series_state(event_ticket_series_test_ticket(), array(0, 1), null, null);
	pg_assert_false(isset($other['series_date']), 'another event\'s pick reads no date');
	pg_assert_same('valid', $other['state'], 'and keeps the row');

	$entered = pg_check_in_series_state(event_ticket_series_test_ticket(), array(0, 1), 1, array('checked_at' => 1763600000, 'checked_by' => 5, 'checked_by_name' => 'kapı'));
	pg_assert_same(array('used', 1763600000, 5, 'kapı'), array($entered['state'], $entered['used_at'], $entered['used_by'], $entered['used_by_name']), 'entered: when and by whom');
}

// The door's counts of a series date come from the entries.
function test_event_ticket_series_counts_sql()
{
	$pick = array('kind' => 'event', 'event_id' => 12, 'recurrence_number' => 3, 'product_id' => 0);
	$series = pg_check_in_counts_sql($pick, true);

	pg_assert_contains('LEFT JOIN event_ticket_checkins AS c ON (c.ticket_id = t.id) AND (c.recurrence_number = ?)', $series['sql'], 'the date\'s entries');
	pg_assert_same(array(3, 12, 0), $series['params'], 'the date, the event, the series tickets on date 0');

	$plain = pg_check_in_counts_sql($pick, false);
	pg_assert_same(array(12, 3), $plain['params'], 'a date of its own reads its rows');
	pg_assert_false(strpos($plain['sql'], 'event_ticket_checkins') !== false, 'and no entries');

	$search = pg_check_in_search_sql($pick, 'Ayşe', true);
	pg_assert_same(array(12, 0), array_slice($search['params'], 0, 2), 'the name search reads the series tickets');
}

// The pick list reads only a few dates of a long series to find the first open one.
function test_event_ticket_series_first_open_date()
{
	$numbers = range(0, 364);
	$reads = 0;
	$end_of = function ($number) use (&$reads) {
		$reads++;

		return 1000 + ($number * 86400);
	};

	pg_assert_same(100, pg_check_in_series_first_open($numbers, $end_of, 1000 + (100 * 86400)), 'the first date not ended');
	pg_assert_true($reads <= 10, 'a handful of dates read');
	pg_assert_same(0, pg_check_in_series_first_open($numbers, $end_of, 0), 'none ended');
	pg_assert_same(365, pg_check_in_series_first_open($numbers, $end_of, PHP_INT_MAX), 'all ended');
	pg_assert_same(0, pg_check_in_series_first_open(array(), $end_of, 0), 'no dates');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/event_check_in.php');
	pg_assert_contains('pg_check_in_series_first_open($series[\'dates\']', $source, 'the pick list uses it');
	pg_assert_contains('PG_CHECK_IN_SERIES_PICKS', $source, 'and lists a bounded number of dates');
}

// The ticket page names the first and last date; its file holds every date.
function test_event_ticket_series_page()
{
	$first = array('start_date_and_time' => '2026-12-01 19:00:00', 'end_date_and_time' => '2026-12-01 21:00:00', 'all_day' => '0', 'show_start_time' => '1', 'show_end_time' => '1');
	$last = array('start_date_and_time' => '2027-02-02 19:00:00') + $first;

	pg_assert_same('01.12.2026 – 02.02.2027, 19:00 – 21:00', pg_event_ticket_series_when($first, $last, 'd.m.Y', 'H:i'), 'first and last date, the times');
	pg_assert_same('01.12.2026 – 02.02.2027', pg_event_ticket_series_when(array('all_day' => '1') + $first, $last, 'd.m.Y', 'H:i'), 'all day: dates only');

	$file = function ($uid) {
		return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:" . $uid . "\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
	};
	$merged = pg_event_tickets_ics_merge(array($file('12-0'), $file('12-1'), $file('12-2')));

	pg_assert_same(3, substr_count($merged, 'BEGIN:VEVENT'), 'every date');
	pg_assert_same(1, substr_count($merged, 'BEGIN:VCALENDAR'), 'one calendar');
	pg_assert_same(1, substr_count($merged, 'END:VCALENDAR'), 'ended once');
	pg_assert_contains("UID:12-2\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n", $merged, 'the last date last');
	pg_assert_same('', pg_event_tickets_ics_merge(array()), 'no dates, no file');

	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/event_tickets_page.php');
	pg_assert_contains('pg_event_ticket_row_is_series($row)', $page, 'the page reads a series ticket as the series');
}

// The API carries each entry's date; the export too.
function test_event_ticket_series_api_and_export()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/event_tickets.php');

	pg_assert_same(
		array(array('recurrence_number' => 2, 'checked_in_at' => api_time(1763600000), 'checked_in_by' => 5)),
		api_event_ticket_check_ins_present(array(array('ticket_id' => 41, 'recurrence_number' => 2, 'checked_at' => 1763600000, 'checked_by' => 5, 'checked_by_name' => 'kapı'))),
		'date, when, who; no name');
	pg_assert_same(array(), api_event_ticket_present(event_ticket_series_test_ticket())['check_ins'], 'none for a ticket without entries');
	pg_assert_true(isset(api_event_ticket_schema()['check_ins']), 'in the schema');

	$row = pg_event_tickets_export_row(event_ticket_series_test_ticket(), array(), array(array('event_date' => '2026-12-08 19:00:00', 'checked_at' => 1763600000)));
	pg_assert_same(array(array('event_date' => '2026-12-08 19:00:00', 'checked_in_at' => 1763600000)), $row['check_ins'], 'the export lists each date entered');

	// The export and the route read the entries and hand them on.
	$export = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/privacy_export.php');
	pg_assert_contains("pg_event_ticket_checkins(array_column(\$sections['event_tickets']['rows'], 'id'))", $export, 'the export reads the entries');
	pg_assert_contains("pg_event_tickets_export_row(\$row, \$event, \$check_ins[(int) \$row['id']] ?? array())", $export, 'and lists them per ticket');

	$route = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/event_tickets.php');
	pg_assert_contains("pg_event_ticket_checkins(array_column(\$rows, 'id'))", $route, 'the route reads the entries');
	pg_assert_contains("\$rows[\$i]['check_ins'] = \$entries[(int)\$row['id']] ?? array();", $route, 'and gives them to each ticket');
}

// Deleting an order removes its tickets' entries with its tickets.
function test_event_ticket_series_entries_go_with_the_order()
{
	$queries = array();
	$record = function ($sql, array $params) use (&$queries) {
		$queries[] = $sql;

		return 1;
	};

	pg_event_tickets_delete_for_order(9001, $record);

	pg_assert_same(2, count($queries), 'two statements');
	pg_assert_contains('DELETE event_ticket_checkins FROM event_ticket_checkins', $queries[0], 'the entries first, while the tickets name the order');
	pg_assert_contains('DELETE FROM event_tickets WHERE order_id = ?', $queries[1], 'then the tickets');

	$delete_order = (string) file_get_contents(PG_FUNCTIONS_DIR . '/delete_order.php');
	pg_assert_contains("pg_event_tickets_delete_for_order(\$order['id']);", $delete_order, 'delete_order.php runs it');
}

// The table's step and its place in get_tables().
function test_event_ticket_series_table_step()
{
	$migration = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_true((bool) preg_match('~\tupgrade_2026_11_0_event_ticket_checkins\(\); +// 11\.0\.267\n~', $migration), 'under 11.0.267');
	pg_assert_contains("install_create_table('event_ticket_checkins'", $migration, 'the idempotent helper');

	// A fresh install runs every step from the starter site, so it creates
	// the table with this same statement; the step holds no other statement,
	// so a second run only finds the table there.
	$start = strpos($migration, 'function upgrade_2026_11_0_event_ticket_checkins()');
	$body = substr($migration, $start, strpos($migration, "\n}\n", $start) - $start);
	pg_assert_false((bool) preg_match('~\bdb(_q)?\(~', $body), 'no statement outside the helpers');
	pg_assert_same(1, substr_count($body, 'install_create_table('), 'one table');
	pg_assert_contains('UNIQUE KEY idx_ticket_date (ticket_id, recurrence_number)', $migration, 'one entry a date');
	pg_assert_contains('KEY idx_event_date (calendar_event_id, recurrence_number)', $migration, 'the door\'s counts');

	$install = (string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');
	pg_assert_contains("\t\t'event_ticket_checkins',\n", $install, 'listed in get_tables()');
}
