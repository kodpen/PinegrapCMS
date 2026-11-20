<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ticket check-in (event_check_in.php,
 * includes/fn/event_check_in.php): the one conditional UPDATE under two
 * scans at once, each answer at the door, the rights (an event's ticket
 * needs the calendar's right, a product's the commerce right), the pick,
 * the guest's name, the counts and the undo.
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

// One event_tickets row kept in memory, and a stand-in for
// pg_event_tickets_query() that runs the check-in's UPDATEs against it the
// way MySQL does: the WHERE decides, and the answer is the rows changed.
function check_in_test_store($state = 'valid', $overrides = array())
{
	$GLOBALS['check_in_test_row'] = $overrides + array(
		'id'                => 41,
		'order_id'          => 9001,
		'order_item_id'     => 501,
		'unit'              => 2,
		'product_id'        => 77,
		'calendar_event_id' => 12,
		'recurrence_number' => 4,
		'state'             => $state,
		'used_at'           => 0,
		'used_by'           => 0,
		'line_tickets'      => 4,
		'guest_name'        => 'Ayşe Demir',
		'order_number'      => '1044',
	);
	$GLOBALS['check_in_test_queries'] = array();
}

function check_in_test_query($sql, array $params)
{
	$GLOBALS['check_in_test_queries'][] = array('sql' => $sql, 'params' => $params);
	$row = &$GLOBALS['check_in_test_row'];

	if ((strpos($sql, "SET state = 'used'") !== false) && (strpos($sql, "(state = 'valid')") !== false)) {
		if (($row['id'] !== $params[1]) || ($row['state'] !== 'valid')) {
			return 0;
		}

		$row['state'] = 'used';
		$row['used_at'] = 1763600000;
		$row['used_by'] = $params[0];

		return 1;
	}

	if ((strpos($sql, "SET state = 'valid'") !== false) && (strpos($sql, "(state = 'used')") !== false)) {
		if (($row['id'] !== $params[0]) || ($row['state'] !== 'used')) {
			return 0;
		}

		$row['state'] = 'valid';
		$row['used_at'] = 0;
		$row['used_by'] = 0;

		return 1;
	}

	return false;
}

function check_in_test_find()
{
	return $GLOBALS['check_in_test_row'];
}

function check_in_test_pick()
{
	return pg_check_in_pick_parse('e-12-4');
}

function check_in_test_staff($overrides = array())
{
	return $overrides + array('id' => 7, 'role' => 3, 'manage_calendars' => true, 'manage_ecommerce' => false);
}

function check_in_test_calendar_yes($event_id)
{
	return true;
}

function check_in_test_calendar_no($event_id)
{
	return false;
}

function test_check_in_two_scans_at_once_mark_once()
{
	check_in_test_store();

	// Both phones read the ticket as valid before either writes.
	$stale = check_in_test_find();
	$reads = 0;
	$stale_find = function () use ($stale, &$reads) {
		$reads++;

		return ($reads === 1) ? $stale : check_in_test_find();
	};

	$first = pg_check_in_run('check_in_test_find', check_in_test_pick(), check_in_test_staff(), 'check_in_test_calendar_yes', 'check_in_test_query');
	$second = pg_check_in_run($stale_find, check_in_test_pick(), check_in_test_staff(array('id' => 8)), 'check_in_test_calendar_yes', 'check_in_test_query');

	pg_assert_same('valid', $first['answer'], 'the first scan checks the ticket in');
	pg_assert_same('used', $second['answer'], 'the second scan, which read it valid too, is told it was used');
	pg_assert_same(7, $GLOBALS['check_in_test_row']['used_by'], 'the row keeps the first checker');

	$updates = $GLOBALS['check_in_test_queries'];
	pg_assert_same(2, count($updates), 'one UPDATE per scan, nothing else written');
	pg_assert_contains("WHERE (id = ?) AND (state = 'valid')", $updates[0]['sql'], 'the UPDATE is conditional on the row still being valid');
	pg_assert_same('used', $second['ticket']['state'], 'the losing scan reads the row again');
}

function test_check_in_mark_used_and_undo_are_conditional()
{
	check_in_test_store('void');
	pg_assert_false(pg_check_in_mark_used(41, 7, 'check_in_test_query'), 'a void ticket is never marked used');
	pg_assert_false(pg_check_in_undo(41, 'check_in_test_query'), 'an undo leaves a void ticket void');
	pg_assert_same('void', $GLOBALS['check_in_test_row']['state'], 'still void');

	check_in_test_store('used', array('used_at' => 1763600000, 'used_by' => 7));
	pg_assert_true(pg_check_in_undo(41, 'check_in_test_query'), 'a used ticket is undone');
	pg_assert_same(array('valid', 0, 0), array($GLOBALS['check_in_test_row']['state'], $GLOBALS['check_in_test_row']['used_at'], $GLOBALS['check_in_test_row']['used_by']), 'valid again, with no time or checker');
	pg_assert_false(pg_check_in_undo(41, 'check_in_test_query'), 'a second undo changes nothing');
	pg_assert_false(pg_check_in_mark_used(0, 7, 'check_in_test_query'), 'no ticket id, no write');
}

function test_check_in_each_answer_at_the_door()
{
	$pick = check_in_test_pick();
	$staff = check_in_test_staff();

	check_in_test_store();
	pg_assert_same('valid', pg_check_in_run('check_in_test_find', $pick, $staff, 'check_in_test_calendar_yes', 'check_in_test_query')['answer'], 'a valid ticket');

	check_in_test_store('used', array('used_at' => 1763600000, 'used_by' => 9));
	$used = pg_check_in_run('check_in_test_find', $pick, $staff, 'check_in_test_calendar_yes', 'check_in_test_query');
	pg_assert_same('used', $used['answer'], 'a ticket used before');
	pg_assert_same(array(), $GLOBALS['check_in_test_queries'], 'is not marked again');
	pg_assert_same(9, $used['ticket']['used_by'], 'and keeps who checked it in');

	check_in_test_store('valid', array('recurrence_number' => 5));
	pg_assert_same('other', pg_check_in_run('check_in_test_find', $pick, $staff, 'check_in_test_calendar_yes', 'check_in_test_query')['answer'], 'another date of the event');
	pg_assert_same(array(), $GLOBALS['check_in_test_queries'], 'is not marked');

	check_in_test_store('valid', array('calendar_event_id' => 13));
	pg_assert_same('other', pg_check_in_run('check_in_test_find', $pick, $staff, 'check_in_test_calendar_yes', 'check_in_test_query')['answer'], 'another event');

	check_in_test_store('void');
	pg_assert_same('void', pg_check_in_run('check_in_test_find', $pick, $staff, 'check_in_test_calendar_yes', 'check_in_test_query')['answer'], 'a void ticket');

	check_in_test_store('used', array('recurrence_number' => 5));
	pg_assert_same('other', pg_check_in_answer($GLOBALS['check_in_test_row'], $pick, true), 'a used ticket of another date is "another date", not "used"');

	$unknown = pg_check_in_run(function () { return null; }, $pick, $staff, 'check_in_test_calendar_yes', 'check_in_test_query');
	pg_assert_same('unknown', $unknown['answer'], 'an unknown code');
	pg_assert_same(null, $unknown['ticket'], 'gives no ticket');

	$texts = array();

	foreach (array('valid', 'used', 'other', 'void', 'denied', 'unknown') as $answer) {
		$texts[$answer] = pg_check_in_answer_text($answer, ($answer === 'unknown' || $answer === 'denied') ? null : $GLOBALS['check_in_test_row'])['title'];
	}

	pg_assert_same(6, count(array_unique($texts)), 'each answer has its own words');
	pg_assert_contains('ticket 2 of 4', pg_check_in_answer_text('valid', $GLOBALS['check_in_test_row'])['detail'], 'a valid ticket shows "ticket 2 of 4"');
	pg_assert_contains('Ayşe Demir', pg_check_in_answer_text('valid', $GLOBALS['check_in_test_row'])['detail'], 'and the name');
}

function test_check_in_rights()
{
	$pick = check_in_test_pick();

	check_in_test_store();
	$denied = pg_check_in_run('check_in_test_find', $pick, check_in_test_staff(), 'check_in_test_calendar_no', 'check_in_test_query');
	pg_assert_same('denied', $denied['answer'], 'an event\'s ticket is refused without that calendar\'s right');
	pg_assert_same(array(), $GLOBALS['check_in_test_queries'], 'and nothing is written');
	pg_assert_same(null, $denied['ticket'], 'nor anything of the ticket shown');

	check_in_test_store();
	pg_assert_same('denied', pg_check_in_run('check_in_test_find', $pick, check_in_test_staff(array('manage_calendars' => false, 'manage_ecommerce' => true)), 'check_in_test_calendar_yes', 'check_in_test_query')['answer'], 'the commerce right does not open an event\'s ticket');

	$product_pick = pg_check_in_pick_parse('p-77');

	check_in_test_store('valid', array('calendar_event_id' => 0, 'recurrence_number' => 0));
	pg_assert_same('denied', pg_check_in_run('check_in_test_find', $product_pick, check_in_test_staff(), 'check_in_test_calendar_yes', 'check_in_test_query')['answer'], 'a product\'s ticket is refused without the commerce right');

	check_in_test_store('valid', array('calendar_event_id' => 0, 'recurrence_number' => 0));
	pg_assert_same('valid', pg_check_in_run('check_in_test_find', $product_pick, check_in_test_staff(array('manage_calendars' => false, 'manage_ecommerce' => true)), 'check_in_test_calendar_no', 'check_in_test_query')['answer'], 'and checked in with it');

	pg_assert_true(pg_check_in_allowed(12, array('role' => 2), 'check_in_test_calendar_no'), 'a manager may check in any event');
	pg_assert_true(pg_check_in_allowed(0, array('role' => 1), 'check_in_test_calendar_no'), 'a designer any product');
	pg_assert_false(pg_check_in_allowed(12, array('role' => 3, 'manage_calendars' => false), 'check_in_test_calendar_yes'), 'a user without the calendars right, even on an event of a shared calendar');

	pg_assert_true(pg_check_in_may_undo(array('role' => 2)), 'a manager may undo');
	pg_assert_false(pg_check_in_may_undo(array('role' => 3, 'manage_calendars' => true, 'manage_ecommerce' => true)), 'a user with rights may not');
}

function test_check_in_pick()
{
	pg_assert_same(array('kind' => 'event', 'event_id' => 12, 'recurrence_number' => 0, 'product_id' => 0), pg_check_in_pick_parse('e-12-0'), 'an event\'s first date');
	pg_assert_same(array('kind' => 'product', 'event_id' => 0, 'recurrence_number' => 0, 'product_id' => 77), pg_check_in_pick_parse('p-77'), 'a product');
	pg_assert_same('e-12-4', pg_check_in_pick_value(pg_check_in_pick_parse('e-12-4')), 'written back');

	foreach (array('', 'e-0-1', 'e-12', 'p-0', 'p-1 OR 1', 'x-1', array('p-1'), null, 'p-01') as $bad) {
		pg_assert_same(null, pg_check_in_pick_parse($bad), 'not a pick: ' . json_encode($bad));
	}

	$event_ticket = array('calendar_event_id' => 12, 'recurrence_number' => 0, 'product_id' => 77);
	pg_assert_false(pg_check_in_ticket_matches($event_ticket, pg_check_in_pick_parse('p-77')), 'an event\'s ticket is not its product\'s');

	$where = pg_check_in_pick_where(pg_check_in_pick_parse('p-77'));
	pg_assert_contains('t.calendar_event_id = 0', $where['sql'], 'a product pick reads only tickets without an event');
}

function test_check_in_guest_name_and_counts()
{
	$buyer = array('billing_first_name' => 'Mehmet', 'billing_last_name' => 'Kaya');

	pg_assert_same('Elif Şahin', pg_check_in_guest_name(array('first_name' => ' Elif ', 'last_name' => 'Şahin'), $buyer), 'the unit\'s form names the guest');
	pg_assert_same('Elif', pg_check_in_guest_name(array('first_name' => 'Elif'), $buyer), 'a first name alone');
	pg_assert_same('Mehmet Kaya', pg_check_in_guest_name(array(), $buyer), 'else the buyer');

	pg_assert_same(array('checked_in' => 3, 'sold' => 10),
		pg_check_in_counts_from_rows(array(array('state' => 'valid', 'n' => '7'), array('state' => 'used', 'n' => '3'), array('state' => 'void', 'n' => '2'))),
		'void tickets are not sold');
	pg_assert_same(array('checked_in' => 0, 'sold' => 0), pg_check_in_counts_from_rows(array()), 'nothing sold');
}

function test_check_in_screen_source()
{
	$screen = file_get_contents(PG_FUNCTIONS_DIR . '/event_check_in.php');
	$script = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/event_check_in.js');

	pg_assert_contains('hash_equals($session_token, $post_token)', $screen, 'every action checks the token');
	pg_assert_contains("validate_calendar_event_access", $screen, 'the calendar right of the event');
	pg_assert_contains('pg_check_in_may_undo($user)', $screen, 'only a manager undoes');
	pg_assert_contains('BarcodeDetector', $script, 'the browser\'s own barcode reader');
	pg_assert_false(strpos($script, 'innerHTML') !== false, 'the script writes text only');
}

function test_check_in_picks_keep_the_newest_dates_under_the_cap()
{
	// The pick list reads at most 500 event dates and drops the ended ones
	// afterwards; without an order the oldest dates filled the 500 and a
	// new event never reached the list.
	$sql = pg_check_in_picks_groups_sql();

	pg_assert_contains('GROUP BY calendar_event_id, recurrence_number', $sql, 'one row per event date');
	pg_assert_contains("(state <> 'void')", $sql, 'void tickets left out');
	pg_assert_contains('ORDER BY MAX(id) DESC', $sql, 'the dates sold most lately first');
	pg_assert_true(strpos($sql, 'ORDER BY') < strpos($sql, 'LIMIT 500'), 'ordered before the cap');
}

function test_check_in_lost_write_answers_from_the_row_read_again()
{
	$pick = check_in_test_pick();
	$staff = check_in_test_staff();

	// Read valid, then the order is cancelled before the UPDATE: the row is
	// void, and the guest must hear "void", not "already checked in".
	check_in_test_store('void');
	$stale = array_merge($GLOBALS['check_in_test_row'], array('state' => 'valid'));
	$reads = 0;
	$find = function () use ($stale, &$reads) {
		$reads++;

		return ($reads === 1) ? $stale : check_in_test_find();
	};

	pg_assert_same('void', pg_check_in_run($find, $pick, $staff, 'check_in_test_calendar_yes', 'check_in_test_query')['answer'], 'voided between the read and the write');

	// Read valid, then the order is deleted: no row is left.
	$reads = 0;
	$gone = function () use ($stale, &$reads) {
		$reads++;

		return ($reads === 1) ? $stale : null;
	};
	$deleted = pg_check_in_run($gone, $pick, $staff, 'check_in_test_calendar_yes', 'check_in_test_query');
	pg_assert_same('unknown', $deleted['answer'], 'deleted between the read and the write');
	pg_assert_same(null, $deleted['ticket'], 'and nothing of it shown');

	// The write fails and the row still reads valid: never "used", never
	// "valid" (it was not marked); the screen asks for a second scan.
	check_in_test_store();
	$failed = pg_check_in_run('check_in_test_find', $pick, $staff, 'check_in_test_calendar_yes', function () {
		return false;
	});
	pg_assert_same('failed', $failed['answer'], 'a failed write is its own answer');
	pg_assert_same('valid', $GLOBALS['check_in_test_row']['state'], 'the ticket stays valid');
	pg_assert_true(pg_check_in_answer_text('failed', $failed['ticket'])['title'] !== pg_check_in_answer_text('unknown', null)['title'], 'with its own words');
}

function test_check_in_guest_name_is_the_tickets_page_name()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/event_tickets_page.php');

	// A product form that asks "Guest name" (variable guest_name, mapped to
	// no contact field): the ticket shows that name, so the door and the API
	// show it too, not the buyer's.
	$tickets = array(
		array('order_item_id' => 501, 'unit' => 1, 'billing_first_name' => 'Mehmet', 'billing_last_name' => 'Kaya'),
		array('order_item_id' => 501, 'unit' => 2, 'billing_first_name' => 'Mehmet', 'billing_last_name' => 'Kaya'),
		array('order_item_id' => 502, 'unit' => 1, 'billing_first_name' => 'Mehmet', 'billing_last_name' => 'Kaya'),
	);
	$form_rows = array(
		array('order_item_id' => 501, 'quantity_number' => 1, 'name' => 'guest_name', 'data' => 'Ayşe Demir', 'contact_field' => null),
		array('order_item_id' => 501, 'quantity_number' => 2, 'name' => 'first', 'data' => 'Elif', 'contact_field' => 'first_name'),
		array('order_item_id' => 501, 'quantity_number' => 2, 'name' => 'last', 'data' => 'Şahin', 'contact_field' => 'last_name'),
		array('order_item_id' => 502, 'quantity_number' => 1, 'name' => 'diet', 'data' => 'Vegan', 'contact_field' => null),
	);

	$named = pg_check_in_names_from_forms($tickets, $form_rows);

	pg_assert_same('Ayşe Demir', $named[0]['guest_name'], 'a field named for the guest');
	pg_assert_same('Elif Şahin', $named[1]['guest_name'], 'the contact name fields');
	pg_assert_same('Mehmet Kaya', $named[2]['guest_name'], 'else the buyer');

	// The name search finds the same names.
	$sql = pg_check_in_search_sql(pg_check_in_pick_parse('e-12-4'));
	pg_assert_contains('REGEXP', $sql['sql'], 'the search reads a field named for the guest too');
	pg_assert_contains(PG_EVENT_TICKET_GUEST_FIELD_PATTERN, implode(' ', array_map('strval', $sql['params'])) . $sql['sql'], 'with the page\'s rule');
}

function test_check_in_product_label()
{
	// A product ticket shown at another pick names its product: the short
	// description, else the product's name, as the pick list writes it.
	pg_assert_same('Museum pass', pg_check_in_product_label(array('short_description' => 'Museum pass', 'name' => 'MUS-1')), 'the short description');
	pg_assert_same('MUS-1', pg_check_in_product_label(array('short_description' => '', 'name' => 'MUS-1')), 'else the name');
	pg_assert_same('MUS-1', pg_check_in_product_label(array('short_description' => null, 'name' => 'MUS-1')), 'a NULL description too');
	pg_assert_same('', pg_check_in_product_label(null), 'a product that is gone');

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/event_check_in.php');
	pg_assert_contains('pg_check_in_product_label(db_item(', $source, 'the wrong-event answer reads the label');
}

function test_check_in_typed_code_kept_while_a_check_runs()
{
	// A code typed and sent while a camera scan is still being answered was
	// not checked, yet the field was emptied: the code was lost.
	$script = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/event_check_in.js');

	pg_assert_contains('return Promise.resolve(false);', $script, 'a check refused while one runs says so');
	pg_assert_contains("check('scan', { code: code }).then(function (ran) {", $script, 'the typed code waits for the answer');
	pg_assert_contains('if (ran) {', $script, 'and is cleared only when it was checked');
}
