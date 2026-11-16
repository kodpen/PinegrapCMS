<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for whom an application's question asks (interact.php): the people
 * it mentions who read the channel, else the channel's owners who read it,
 * so a question that mentions only people who cannot read it still asks
 * somebody.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/interact.php');

// Only 4 and 1 read the channel in these tests.
function ws_question_asked_test_reads()
{
	return function ($user_id) {
		return in_array((int) $user_id, array(1, 4), true);
	};
}

// The mentioned people who read the channel are asked; the others drop out.
function test_ws_question_ring_pick_keeps_the_mentioned_readers()
{
	pg_assert_same(array(4), ws_question_ring_pick(array(9, 4, 4), array(1), ws_question_asked_test_reads()), 'the reader among the mentioned');
}

// A question that mentions only people who cannot read the channel asks the
// channel's owners who read it, not nobody.
function test_ws_question_ring_pick_falls_back_to_the_owners()
{
	pg_assert_same(array(1), ws_question_ring_pick(array(9, 7), array(1, 5), ws_question_asked_test_reads()), 'the owners when no mentioned person reads it');
	pg_assert_same(array(1), ws_question_ring_pick(array(), array(5, 1, 1), ws_question_asked_test_reads()), 'the owners who read it when it mentions nobody');
	pg_assert_same(array(), ws_question_ring_pick(array(9), array(5), ws_question_asked_test_reads()), 'nobody when no owner reads it either');
}

// Without a reader check the pick is as before.
function test_ws_question_ring_pick_without_a_reader_check()
{
	pg_assert_same(array(9, 4), ws_question_ring_pick(array(9, 4), array(1)), 'the mentioned');
	pg_assert_same(array(1), ws_question_ring_pick(array(), array(1)), 'the owners');
}

// ws_question_asked_ids() reads the owners only when no mentioned reader is
// left, and picks with the reader check.
function test_ws_question_asked_ids_filters_before_it_picks()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/interact.php');
	$body = substr($source, strpos($source, 'function ws_question_asked_ids('));
	$body = substr($body, 0, strpos($body, "\n}\n"));

	pg_assert_contains('ws_can_read_channel(', $body, 'the reader check');
	pg_assert_true(substr_count($body, 'ws_question_ring_pick(') >= 1, 'the pick');
	pg_assert_true(strpos($body, '$reads') !== false, 'one reader check for both lists');
	pg_assert_true(strpos($body, '$reads') < strpos($body, "role = 'owner'"), 'the mentioned are filtered before the owners are read');
}
