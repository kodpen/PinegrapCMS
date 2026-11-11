<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new dashboard's My notes card as a shortcut to My Notes
 * (includes/dashboard/v2/cards/notes.php, personal.php): who is offered the
 * card, the one move of the card's old text into My Notes, and the rows the
 * card lists.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/layout.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/run.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/personal.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/notes.php');

/**
 * A stored layout and the notes written from it, kept in memory, with the
 * three callables pg_dashboard_v2_notes_move() takes.
 *
 * @param string $text the card's text of before
 * @param bool   $save_works
 * @return array state (by reference: layout, notes), read, save, clear
 */
function dashboard_v2_notes_store($text, $save_works = true)
{
	$state = new stdClass();
	$state->layout = json_encode(array('cards' => array(array('id' => 'notes')), 'notes' => $text));
	$state->notes = array();

	return array(
		'state' => $state,
		'read'  => function () use ($state) {
			return pg_dashboard_v2_notes_stored($state->layout);
		},
		'save'  => function ($body) use ($state, $save_works) {
			if (!$save_works) {
				return 0;
			}
			$state->notes[] = $body;
			return count($state->notes);
		},
		'clear' => function () use ($state) {
			$state->layout = pg_dashboard_v2_notes_cleared($state->layout);
			return true;
		},
	);
}

// The card's text becomes one note, and the layout keeps no text after it:
// the card asked again moves nothing.
function test_dashboard_v2_notes_move_once()
{
	$store = dashboard_v2_notes_store("Call the printer\nOrder paper");

	pg_assert_same(1, pg_dashboard_v2_notes_move($store['read'], $store['save'], $store['clear']), 'the new note');
	pg_assert_same(array("Call the printer\nOrder paper"), $store['state']->notes, 'the text as written');
	pg_assert_same('', pg_dashboard_v2_notes_stored($store['state']->layout), 'cleared from the layout');
	pg_assert_same(array(array('id' => 'notes')), json_decode($store['state']->layout, true)['cards'], 'the cards stay');

	pg_assert_same(0, pg_dashboard_v2_notes_move($store['read'], $store['save'], $store['clear']), 'nothing the second time');
	pg_assert_same(1, count($store['state']->notes), 'never two notes');
}

// An empty text makes no note; a text of blanks makes none either and is
// cleared.
function test_dashboard_v2_notes_move_empty_text()
{
	$store = dashboard_v2_notes_store('');
	pg_assert_same(0, pg_dashboard_v2_notes_move($store['read'], $store['save'], $store['clear']));
	pg_assert_same(array(), $store['state']->notes, 'no note for an empty text');

	$blank = dashboard_v2_notes_store(" \n\t ");
	pg_assert_same(0, pg_dashboard_v2_notes_move($blank['read'], $blank['save'], $blank['clear']));
	pg_assert_same(array(), $blank['state']->notes, 'no note for blanks');
	pg_assert_same('', pg_dashboard_v2_notes_stored($blank['state']->layout), 'blanks cleared');
}

// A note that could not be written leaves the text where it was, so the
// next visit moves it.
function test_dashboard_v2_notes_move_failed_save_keeps_text()
{
	$store = dashboard_v2_notes_store('Keep me', false);

	pg_assert_same(0, pg_dashboard_v2_notes_move($store['read'], $store['save'], $store['clear']));
	pg_assert_same('Keep me', pg_dashboard_v2_notes_stored($store['state']->layout), 'the text stays');
}

// The stored text read from a layout, and the layout with it taken out.
function test_dashboard_v2_notes_stored_and_cleared()
{
	pg_assert_same('mine', pg_dashboard_v2_notes_stored(json_encode(array('notes' => 'mine'))));
	pg_assert_same('', pg_dashboard_v2_notes_stored(json_encode(array('notes' => array('x')))), 'not a text');
	pg_assert_same('', pg_dashboard_v2_notes_stored('not json'));
	pg_assert_same('', pg_dashboard_v2_notes_stored(''));

	$cleared = json_decode(pg_dashboard_v2_notes_cleared(json_encode(array('cards' => array(), 'pins' => array('p'), 'notes' => 'gone'))), true);
	pg_assert_same(array('cards' => array(), 'pins' => array('p'), 'notes' => ''), $cleared, 'only the text goes');
	pg_assert_same(null, pg_dashboard_v2_notes_cleared('not json'), 'nothing to write');
}

// The card is offered only to someone who can use My Notes: a member of the
// team while the notes' tables are there.
function test_dashboard_v2_notes_card_gate()
{
	pg_assert_false(pg_dashboard_v2_notes_gate(null, true), 'no workspace rights');
	pg_assert_false(pg_dashboard_v2_notes_gate(array('id' => 3, 'member' => false), true), 'not a member');
	pg_assert_false(pg_dashboard_v2_notes_gate(array('id' => 3, 'member' => true), false), 'notes not installed');
	pg_assert_true(pg_dashboard_v2_notes_gate(array('id' => 3, 'member' => true), true), 'a member');

	pg_assert_true(in_array('notes', pg_dashboard_v2_gates(), true), 'a known gate');
	pg_assert_same('notes', pg_dashboard_v2_cards()['notes']['gate'], 'the card stands behind it');
}

// The rows: title and date, the address that opens the note on My Notes,
// at most as many as asked; a note behind a closed lock keeps the name the
// notes list gives it.
function test_dashboard_v2_notes_card_rows()
{
	$presented = array(
		array('id' => 7, 'name' => 'Printer', 'updated' => '10:42', 'locked' => false, 'lock_closed' => false),
		array('id' => 5, 'name' => 'Locked note', 'updated' => 'Yesterday 09:00', 'locked' => true, 'lock_closed' => true),
		array('id' => 2, 'name' => 'Third', 'updated' => '03.11 12:00'),
	);

	$rows = pg_dashboard_v2_notes_rows($presented, 'workspace_notes.php?note=', 2);

	pg_assert_same(2, count($rows), 'cut to the limit');
	pg_assert_same(array('id' => 7, 'text' => 'Printer', 'note' => '10:42', 'url' => 'workspace_notes.php?note=7', 'locked' => false), $rows[0]);
	pg_assert_same('Locked note', $rows[1]['text']);
	pg_assert_true($rows[1]['locked'], 'drawn with the lock');
	pg_assert_same(array(), pg_dashboard_v2_notes_rows(array(), 'x', 5));
}
