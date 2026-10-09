<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of the workspace's search box
 * (includes/workspace/palette.php) and of a channel's board
 * (includes/workspace/channel_board.php): what the first character of a
 * search asks for, the piece of a long text shown around a match, the date
 * column a due date falls in, the due date a card dropped on a date column
 * gets, and the order of the cards in a column.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/palette.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/channel_board.php');

function test_workspace_board_date_bucket_on_a_friday()
{
	// 2026-10-09 is a Friday: the week runs to Sunday the 11th.
	$today = '2026-10-09';

	pg_assert_same('none', ws_channel_board_date_bucket(null, $today), 'no date');
	pg_assert_same('none', ws_channel_board_date_bucket('', $today), 'empty date');
	pg_assert_same('none', ws_channel_board_date_bucket('0000-00-00', $today), 'zero date');
	pg_assert_same('overdue', ws_channel_board_date_bucket('2026-10-08', $today), 'yesterday');
	pg_assert_same('overdue', ws_channel_board_date_bucket('2025-12-31', $today), 'last year');
	pg_assert_same('today', ws_channel_board_date_bucket('2026-10-09', $today), 'today');
	pg_assert_same('week', ws_channel_board_date_bucket('2026-10-10', $today), 'Saturday');
	pg_assert_same('week', ws_channel_board_date_bucket('2026-10-11', $today), 'Sunday');
	pg_assert_same('later', ws_channel_board_date_bucket('2026-10-12', $today), 'next Monday');
	pg_assert_same('later', ws_channel_board_date_bucket('2027-01-05', $today), 'next year');
}

function test_workspace_board_date_bucket_week_edges()
{
	// On a Monday the rest of the week is this week.
	pg_assert_same('week', ws_channel_board_date_bucket('2026-10-11', '2026-10-05'), 'Monday to Sunday');
	pg_assert_same('later', ws_channel_board_date_bucket('2026-10-12', '2026-10-05'), 'Monday to next Monday');

	// On a Sunday there is nothing left of the week after today.
	pg_assert_same('today', ws_channel_board_date_bucket('2026-10-11', '2026-10-11'), 'Sunday itself');
	pg_assert_same('later', ws_channel_board_date_bucket('2026-10-12', '2026-10-11'), 'Sunday to Monday');

	// Across a month and a year.
	pg_assert_same('week', ws_channel_board_date_bucket('2026-11-01', '2026-10-30'), 'Friday to Sunday across months');
	pg_assert_same('week', ws_channel_board_date_bucket('2027-01-03', '2026-12-31'), 'Thursday to Sunday across years');
	pg_assert_same('later', ws_channel_board_date_bucket('2027-01-04', '2026-12-31'), 'across years, next week');
}

function test_workspace_board_week_end()
{
	pg_assert_same('2026-10-11', ws_channel_board_week_end('2026-10-05'), 'Monday');
	pg_assert_same('2026-10-11', ws_channel_board_week_end('2026-10-09'), 'Friday');
	pg_assert_same('2026-10-11', ws_channel_board_week_end('2026-10-11'), 'Sunday');
	// The week the clocks go back in (last Sunday of October in Europe).
	pg_assert_same('2026-10-25', ws_channel_board_week_end('2026-10-19'), 'over a clock change');
}

function test_workspace_board_drop_date()
{
	pg_assert_same('2026-10-09', ws_channel_board_drop_date('today', '2026-10-09'), 'today');
	pg_assert_same('2026-10-10', ws_channel_board_drop_date('week', '2026-10-09'), 'this week: tomorrow');
	pg_assert_same(null, ws_channel_board_drop_date('week', '2026-10-11'), 'this week on a Sunday: no drop');
	pg_assert_same('2026-10-12', ws_channel_board_drop_date('later', '2026-10-09'), 'later: the Monday after');
	pg_assert_same('2026-10-19', ws_channel_board_drop_date('later', '2026-10-12'), 'later from a Monday');
	pg_assert_same('', ws_channel_board_drop_date('none', '2026-10-09'), 'no date');
	pg_assert_same(null, ws_channel_board_drop_date('overdue', '2026-10-09'), 'overdue: no drop');
	pg_assert_same(null, ws_channel_board_drop_date('nonsense', '2026-10-09'), 'unknown column');
}

function test_workspace_board_card_order()
{
	$cards = array(
		array('id' => 1, 'priority' => 'normal', 'due_date' => null),
		array('id' => 2, 'priority' => 'urgent', 'due_date' => '2026-10-20'),
		array('id' => 3, 'priority' => 'normal', 'due_date' => '2026-10-10'),
		array('id' => 4, 'priority' => 'low', 'due_date' => '2026-10-01'),
		array('id' => 5, 'priority' => 'normal', 'due_date' => '2026-10-10'),
		array('id' => 6, 'priority' => 'high', 'due_date' => null),
	);

	usort($cards, 'ws_channel_board_compare');

	pg_assert_same(array(2, 6, 5, 3, 1, 4), array_map(function ($card) { return $card['id']; }, $cards),
		'priority first, then the sooner due (none last), then the newer');
}

function test_workspace_palette_mode()
{
	pg_assert_same(array('mode' => 'all', 'query' => 'kraft ambalaj'), ws_palette_mode('  kraft   ambalaj '), 'plain words');
	pg_assert_same(array('mode' => 'records', 'query' => 'sip 1042'), ws_palette_mode('#sip 1042'), 'a record');
	pg_assert_same(array('mode' => 'people', 'query' => 'ayşe'), ws_palette_mode('@ayşe'), 'a person');
	pg_assert_same(array('mode' => 'people', 'query' => ''), ws_palette_mode('@'), 'every person');
	pg_assert_same(array('mode' => 'commands', 'query' => 'gor'), ws_palette_mode('/gor'), 'a command');
	pg_assert_same(array('mode' => 'all', 'query' => ''), ws_palette_mode(''), 'nothing');
}

function test_workspace_palette_snippet()
{
	pg_assert_same('Kısa bir metin.', ws_palette_snippet('Kısa   bir metin.', 'metin', 120), 'short text as it is');

	$long = str_repeat('başta duran sözcükler ', 10) . 'kraft ambalaj kararı burada ' . str_repeat('sonra gelen sözcükler ', 10);
	$piece = ws_palette_snippet($long, 'kraft', 60);

	pg_assert_true(mb_strlen($piece) <= 60, 'at most the length');
	pg_assert_contains('kraft', $piece, 'the match is in the piece');
	pg_assert_same('…', mb_substr($piece, 0, 1), 'cut at the front');
	pg_assert_same('…', mb_substr($piece, -1), 'cut at the end');

	$start = ws_palette_snippet($long, 'başta', 60);
	pg_assert_same('başta', mb_substr($start, 0, 5), 'a match at the start keeps the start');

	$none = ws_palette_snippet($long, 'yok', 60);
	pg_assert_same('başta', mb_substr($none, 0, 5), 'no match: the start');
	pg_assert_same('…', mb_substr($none, -1), 'no match: cut at the end');

	// Case is ignored, Turkish letters included.
	pg_assert_contains('KRAFT', ws_palette_snippet(str_repeat('x ', 80) . 'KRAFT ambalaj', 'kraft', 40), 'case-insensitive match');
}

function test_workspace_palette_record_type()
{
	$types = array(
		'order'   => array('record' => true, 'prefixes' => array('sip', 'siparis', 'sipariş', 'order', 'o')),
		'task'    => array('record' => false, 'prefixes' => array('gorev', 'görev', 'task', 'g')),
		'contact' => array('record' => true, 'prefixes' => array('kisi', 'kişi', 'contact', 'k')),
	);

	pg_assert_same(array('type' => 'order', 'query' => '1042'), ws_palette_record_type($types, 'sip 1042'), 'a kind and a number');
	pg_assert_same(array('type' => 'order', 'query' => '1042'), ws_palette_record_type($types, 'SİPARİŞ 1042'), 'Turkish capitals');
	pg_assert_same(array('type' => 'contact', 'query' => 'ayşe yılmaz'), ws_palette_record_type($types, 'kişi ayşe yılmaz'), 'several words after it');
	pg_assert_same(array('type' => '', 'query' => 'gorev logo'), ws_palette_record_type($types, 'gorev logo'), 'not a record kind');
	pg_assert_same(array('type' => '', 'query' => 'sip'), ws_palette_record_type($types, 'sip'), 'only the prefix');
	pg_assert_same(array('type' => '', 'query' => 'ambalaj'), ws_palette_record_type($types, 'ambalaj'), 'no prefix');
}
