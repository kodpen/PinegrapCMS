<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the walk-in queue's lobby screen and kiosk
 * (includes/fn/walk_in_queue_screens.php): the signed display links, the
 * lobby board (now served, the last five calls, the chime's stamp, never a
 * name), the kiosk's service line and the wiring of the two pages.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_walk_in_queue_screens_token_is_per_place_and_screen()
{
	$key = 'site-key';
	$lobby = pg_waiting_screen_token(4, 'lobby', $key);

	pg_assert_same(hash_hmac('sha256', 'waiting-lobby:4', $key), $lobby);
	pg_assert_true(pg_waiting_screen_token_valid($lobby, 4, 'lobby', $key));
	pg_assert_false(pg_waiting_screen_token_valid($lobby, 4, 'kiosk', $key), 'the lobby link takes no numbers');
	pg_assert_false(pg_waiting_screen_token_valid($lobby, 5, 'lobby', $key), 'another place');
	pg_assert_false(pg_waiting_screen_token_valid($lobby, 4, 'lobby', 'other-key'), 'a changed site key');
	pg_assert_false(pg_waiting_screen_token_valid(array($lobby), 4, 'lobby', $key), 'an array');
	pg_assert_false(pg_waiting_screen_token_valid(strtoupper($lobby), 4, 'lobby', $key), 'not lower hex');
	pg_assert_same('', pg_waiting_screen_token(4, 'lobby', ''), 'no site key');
	pg_assert_same('', pg_waiting_screen_token(0, 'lobby', $key), 'no place');
	pg_assert_same('', pg_waiting_screen_token(4, 'desk', $key), 'no such screen');
	pg_assert_false(pg_waiting_screen_token_valid('', 4, 'lobby', ''), 'nothing opens without a key');
}

function test_walk_in_queue_screens_lobby_board_shows_numbers_and_desks()
{
	$desks = array(1 => 'Desk 1', 2 => 'Desk 2');
	$calls = array(
		array('id' => 10, 'letter' => 'A', 'number' => 1, 'state' => 'served', 'desk_id' => 1, 'called_at' => 100, 'name' => 'Ayşe'),
		array('id' => 11, 'letter' => 'A', 'number' => 2, 'state' => 'called', 'desk_id' => 1, 'called_at' => 300),
		array('id' => 12, 'letter' => 'B', 'number' => 1, 'state' => 'called', 'desk_id' => 2, 'called_at' => 250),
		array('id' => 13, 'letter' => 'B', 'number' => 2, 'state' => 'no_show', 'desk_id' => 2, 'called_at' => 200),
		array('id' => 14, 'letter' => 'A', 'number' => 3, 'state' => 'waiting', 'desk_id' => 0, 'called_at' => 400),
	);
	$board = pg_waiting_lobby_board($calls, $desks);

	pg_assert_same(array(array('number' => 'A-002', 'desk' => 'Desk 1'), array('number' => 'B-001', 'desk' => 'Desk 2')), $board['now']);
	pg_assert_same(array(array('number' => 'B-002', 'desk' => 'Desk 2'), array('number' => 'A-001', 'desk' => 'Desk 1')), $board['past']);
	pg_assert_same('300-11', $board['stamp'], 'a moved-back number waiting again makes no chime');
	pg_assert_false(strpos(json_encode($board), 'Ay') !== false, 'never a name');

	pg_assert_same(array('now' => array(), 'past' => array(), 'stamp' => ''), pg_waiting_lobby_board(array(), $desks));
}

function test_walk_in_queue_screens_lobby_lists_at_most_five_past_calls()
{
	$calls = array();

	for ($i = 1; $i <= 9; $i++) {
		$calls[] = array('id' => $i, 'letter' => 'A', 'number' => $i, 'state' => 'served', 'desk_id' => 1, 'called_at' => 100 + $i);
	}

	$board = pg_waiting_lobby_board($calls, array(1 => 'Desk 1'));

	pg_assert_same(PG_WAITING_LOBBY_PAST, count($board['past']));
	pg_assert_same('A-009', $board['past'][0]['number'], 'newest first');
	pg_assert_same('', pg_waiting_lobby_board(array(array('id' => 1, 'letter' => 'A', 'number' => 1, 'state' => 'called', 'desk_id' => 7, 'called_at' => 5)), array())['now'][0]['desk'], 'a removed desk');
}

function test_walk_in_queue_screens_call_again_changes_the_stamp()
{
	$row = array('id' => 11, 'letter' => 'A', 'number' => 2, 'state' => 'called', 'desk_id' => 1, 'called_at' => 300);
	$first = pg_waiting_lobby_board(array($row), array())['stamp'];
	$row['called_at'] = 360;

	pg_assert_false($first === pg_waiting_lobby_board(array($row), array())['stamp']);
}

function test_walk_in_queue_screens_kiosk_line_and_counts()
{
	pg_assert_same(lang('Nobody waiting'), pg_waiting_kiosk_line(0, null));
	pg_assert_same(lang(array('string' => '{var:1} waiting', 'vars' => array(3))), pg_waiting_kiosk_line(3, null));
	pg_assert_same(lang(array('string' => '{var:1} waiting', 'vars' => array(3))) . ' · ' . lang(array('string' => 'about {var:1} min', 'vars' => array(15))), pg_waiting_kiosk_line(3, 15));
	pg_assert_same(array('s1' => 2, 's2' => 1), pg_waiting_count_by_service(array(array('service' => 's1'), array('service' => 's2'), array('service' => 's1'))));
	pg_assert_same(array(), pg_waiting_count_by_service(array()));
}

function test_walk_in_queue_screens_pages_are_wired()
{
	$lobby = (string) file_get_contents(PG_FUNCTIONS_DIR . '/queue_lobby.php');
	$kiosk = (string) file_get_contents(PG_FUNCTIONS_DIR . '/queue_kiosk.php');
	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/walk_in_queue_screens.php');

	pg_assert_true(strpos($lobby, "define('PG_NO_SESSION', true)") !== false, 'the lobby starts no session');
	pg_assert_true(strpos($lobby, "pg_waiting_screen_place(\$_GET, 'lobby')") !== false);
	pg_assert_true(strpos($kiosk, "pg_waiting_screen_place(\$qk_post ? \$_POST : \$_GET, 'kiosk')") !== false);
	pg_assert_true(strpos($kiosk, 'validate_token_field()') !== false, 'a number is taken with the form token');
	pg_assert_true(strpos($kiosk, "pg_waiting_take(\$qk_place, \$qk_join['values'], 'kiosk')") !== false, 'way kiosk');
	pg_assert_true(strpos($kiosk, "pg_waiting_way_open(\$qk_place, 'kiosk')") !== false, 'the Kiosk switch');
	pg_assert_true(strpos($module, 'SELECT id, letter, number, state, desk_id, called_at FROM waiting_tickets') !== false, 'the board reads no name');
}
