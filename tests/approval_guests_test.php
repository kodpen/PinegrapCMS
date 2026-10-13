<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure rules of approval requests answered from outside the
 * team (includes/workspace/approval_guests.php) and for the record a request
 * is about (ws_approval_record_of(), includes/workspace/approvals.php): who
 * may be named and how they are kept, until when a link opens, what an
 * outsider's note may carry, what a link opens to, and which tag a request
 * points at.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Function definitions only: nothing in these files runs a query when loaded.
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/approvals.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/approval_guests.php');

function test_approval_guests_clean_keeps_name_and_lower_cased_address()
{
	$result = ws_approval_guests_clean(array(
		array('name' => '  Ayşe   Yılmaz ', 'email' => ' Ayse@Example.COM '),
	));

	pg_assert_same('', $result['problem']);
	pg_assert_same(array(array('name' => 'Ayşe Yılmaz', 'email' => 'ayse@example.com')), $result['guests']);
}

function test_approval_guests_clean_drops_rows_left_empty()
{
	$result = ws_approval_guests_clean(array(
		array('name' => '', 'email' => ''),
		array('name' => 'Can', 'email' => 'can@example.com'),
		array('name' => ' ', 'email' => ' '),
	));

	pg_assert_same('', $result['problem']);
	pg_assert_same(1, count($result['guests']));
	pg_assert_same(array(), ws_approval_guests_clean(array())['guests']);
}

function test_approval_guests_clean_takes_markup_and_new_lines_out_of_the_name()
{
	$result = ws_approval_guests_clean(array(
		array('name' => "<b>Can</b>\n**Demir**", 'email' => 'can@example.com'),
	));

	pg_assert_same('bCan/b Demir', $result['guests'][0]['name']);
}

function test_approval_guests_clean_refuses_a_bad_address()
{
	pg_assert_same('email', ws_approval_guests_clean(array(array('name' => 'Can', 'email' => 'not an address')))['problem']);
	pg_assert_same('email', ws_approval_guests_clean(array(array('name' => 'Can', 'email' => '')))['problem']);
	// A header written into the address is not an address.
	pg_assert_same('email', ws_approval_guests_clean(array(array('name' => 'Can', 'email' => "can@example.com\r\nBcc: x@example.com")))['problem']);
	pg_assert_same('email', ws_approval_guests_clean(array('can@example.com'))['problem']);
}

function test_approval_guests_clean_needs_a_name()
{
	pg_assert_same('name', ws_approval_guests_clean(array(array('name' => '  ', 'email' => 'can@example.com')))['problem']);
}

function test_approval_guests_clean_refuses_the_same_address_twice()
{
	$result = ws_approval_guests_clean(array(
		array('name' => 'Can', 'email' => 'can@example.com'),
		array('name' => 'Can Demir', 'email' => 'CAN@example.com'),
	));

	pg_assert_same('twice', $result['problem']);
	pg_assert_same(array(), $result['guests']);
}

function test_approval_guests_clean_counts_against_the_most()
{
	$given = array();

	for ($i = 1; $i <= 3; $i++) {
		$given[] = array('name' => 'Guest ' . $i, 'email' => 'guest' . $i . '@example.com');
	}

	pg_assert_same('', ws_approval_guests_clean($given, 3)['problem']);
	pg_assert_same('many', ws_approval_guests_clean($given, 2)['problem']);
}

function test_approval_guests_clean_cuts_a_long_name()
{
	$result = ws_approval_guests_clean(array(array('name' => str_repeat('ş', 80), 'email' => 'a@example.com')));

	pg_assert_same(60, mb_strlen($result['guests'][0]['name']));
}

function test_approval_guest_expiry_follows_the_deadline()
{
	$now = 1000000;

	pg_assert_same($now + 3600, ws_approval_guest_expiry($now, $now + 3600));
}

function test_approval_guest_expiry_without_a_deadline()
{
	$now = 1000000;

	pg_assert_same($now + (WS_APPROVAL_GUEST_DAYS * 86400), ws_approval_guest_expiry($now, 0));
	// A deadline already passed is no deadline for a new link.
	pg_assert_same($now + (WS_APPROVAL_GUEST_DAYS * 86400), ws_approval_guest_expiry($now, $now - 10));
}

function test_approval_guest_expiry_never_longer_than_the_longest()
{
	$now = 1000000;

	pg_assert_same($now + WS_APPROVAL_GUEST_LONGEST, ws_approval_guest_expiry($now, $now + (365 * 86400)));
}

function test_approval_guest_note_clean_takes_tags_and_markup_out()
{
	pg_assert_same('Logo ‹b›bigger‹/b›', ws_approval_guest_note_clean('Logo <@user:1><b>bigger</b><#order:5>'));
	pg_assert_same('one two three', ws_approval_guest_note_clean("  one\ntwo\r\n\tthree "));
	pg_assert_same('', ws_approval_guest_note_clean("\n\t "));
}

function test_approval_guest_note_clean_cuts_at_the_most()
{
	pg_assert_same(WS_APPROVAL_GUEST_NOTE_MAX, mb_strlen(ws_approval_guest_note_clean(str_repeat('ğ', WS_APPROVAL_GUEST_NOTE_MAX + 50))));
}

function test_approval_guest_state_what_a_link_opens_to()
{
	$now = 5000;
	$open = array('closed_at' => 0, 'closes_at' => 0);
	$pending = array('decision' => 'pending', 'expires_at' => 6000);

	pg_assert_same('open', ws_approval_guest_state($pending, $open, $now));
	pg_assert_same('answered', ws_approval_guest_state(array('decision' => 'approved', 'expires_at' => 6000), $open, $now));
	// An answer given stays the answer, whatever happened to the request since.
	pg_assert_same('answered', ws_approval_guest_state(array('decision' => 'rejected', 'expires_at' => 0), array('closed_at' => 4000, 'closes_at' => 0), $now));
	pg_assert_same('closed', ws_approval_guest_state($pending, array('closed_at' => 4000, 'closes_at' => 0), $now));
	pg_assert_same('closed', ws_approval_guest_state($pending, array('closed_at' => 0, 'closes_at' => 5000), $now));
	pg_assert_same('expired', ws_approval_guest_state(array('decision' => 'pending', 'expires_at' => 5000), $open, $now));
}

function test_approval_guest_token_shape()
{
	pg_assert_true(ws_approval_guest_token_shape(str_repeat('a', 40) . '-_9'));
	pg_assert_false(ws_approval_guest_token_shape(str_repeat('a', 42)));
	pg_assert_false(ws_approval_guest_token_shape(str_repeat('a', 42) . '='));
	pg_assert_false(ws_approval_guest_token_shape(array()));
	pg_assert_false(ws_approval_guest_token_shape(''));
}

function test_approval_record_of_prefers_a_record_of_the_site()
{
	$id = -1;
	$type = ws_approval_record_of(array(
		array('sigil' => '@', 'type' => 'user', 'id' => 3),
		array('sigil' => '#', 'type' => 'task', 'id' => 7),
		array('sigil' => '#', 'type' => 'order', 'id' => 12),
	), $id);

	pg_assert_same('order', $type);
	pg_assert_same(12, $id);
}

function test_approval_record_of_falls_back_to_the_first_task_or_plan_item()
{
	$id = -1;

	pg_assert_same('task', ws_approval_record_of(array(
		array('sigil' => '#', 'type' => 'task', 'id' => 7),
		array('sigil' => '#', 'type' => 'plan', 'id' => 9),
	), $id));
	pg_assert_same(7, $id);

	pg_assert_same('plan', ws_approval_record_of(array(
		array('sigil' => '#', 'type' => 'channel', 'id' => 2),
		array('sigil' => '#', 'type' => 'plan', 'id' => 9),
	), $id));
	pg_assert_same(9, $id);
}

function test_approval_record_of_nothing_tagged()
{
	$id = -1;

	pg_assert_same('', ws_approval_record_of(array(array('sigil' => '@', 'type' => 'user', 'id' => 3)), $id));
	pg_assert_same(0, $id);
	pg_assert_same('', ws_approval_record_of(array(), $id));
}
