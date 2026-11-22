<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an application programme's decisions that need no database
 * (includes/fn/application_decisions.php): the decision codes, which change
 * is allowed, the next on the waitlist, the decision e-mails' counts and
 * when the submitter sees the result.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/application_decisions.php');

function test_application_decisions_codes_and_keys()
{
	pg_assert_same(1, pg_application_decision_code('accepted'));
	pg_assert_same(2, pg_application_decision_code('rejected'));
	pg_assert_same(3, pg_application_decision_code('waitlisted'));
	pg_assert_same(4, pg_application_decision_code('declined'));
	pg_assert_same(0, pg_application_decision_code('none'));
	pg_assert_same(0, pg_application_decision_code(array('accepted')));
	pg_assert_same('accepted', pg_application_decision_key(1));
	pg_assert_same('declined', pg_application_decision_key('4'));
	pg_assert_same('', pg_application_decision_key(0));
	pg_assert_same('', pg_application_decision_key(9));
}

function test_application_decisions_allowed_changes()
{
	// Any of the three, or back to none; never the same again.
	pg_assert_true(pg_application_decision_allowed(0, 1));
	pg_assert_true(pg_application_decision_allowed(3, 2));
	pg_assert_true(pg_application_decision_allowed(1, 0));
	pg_assert_false(pg_application_decision_allowed(2, 2));
	pg_assert_false(pg_application_decision_allowed(0, 0));
	pg_assert_false(pg_application_decision_allowed(1, 7));

	// Declined only follows an acceptance.
	pg_assert_true(pg_application_decision_allowed(1, 4));
	pg_assert_false(pg_application_decision_allowed(0, 4));
	pg_assert_false(pg_application_decision_allowed(3, 4));

	// A declined place is not handed back by a change of decision.
	pg_assert_false(pg_application_decision_allowed(4, 1));
	pg_assert_false(pg_application_decision_allowed(4, 0));
}

function test_application_decisions_waitlist_next_in_the_category_by_score()
{
	$entries = array(
		10 => array('category' => 'Art', 'decision' => 3, 'round' => 1, 'score_raw' => 700, 'score_adjusted' => 650),
		11 => array('category' => 'Art', 'decision' => 3, 'round' => 1, 'score_raw' => 650, 'score_adjusted' => 720),
		12 => array('category' => 'Music', 'decision' => 3, 'round' => 1, 'score_raw' => 990, 'score_adjusted' => 990),
		13 => array('category' => 'Art', 'decision' => 2, 'round' => 1, 'score_raw' => 999, 'score_adjusted' => 999),
		14 => array('category' => 'Art', 'decision' => 4, 'round' => 1, 'score_raw' => 999, 'score_adjusted' => 999),
	);

	pg_assert_same(10, pg_application_waitlist_next($entries, 'Art', 'raw'));
	pg_assert_same(11, pg_application_waitlist_next($entries, 'Art', 'corrected'));
	pg_assert_same(12, pg_application_waitlist_next($entries, 'Music', 'raw'));
	pg_assert_same(0, pg_application_waitlist_next($entries, 'Dance', 'raw'));
	pg_assert_same(0, pg_application_waitlist_next(array(), '', 'raw'));
}

function test_application_decisions_waitlist_next_prefers_the_later_round_then_the_first_entry()
{
	$entries = array(
		20 => array('category' => '', 'decision' => 3, 'round' => 1, 'score_raw' => 900, 'score_adjusted' => 0),
		21 => array('category' => '', 'decision' => 3, 'round' => 2, 'score_raw' => 500, 'score_adjusted' => 0),
		22 => array('category' => '', 'decision' => 3, 'round' => 2, 'score_raw' => 500, 'score_adjusted' => 0),
	);

	// A later round got further; a tie goes to the earlier entry.
	pg_assert_same(21, pg_application_waitlist_next($entries, '', 'raw'));
	unset($entries[21]);
	pg_assert_same(22, pg_application_waitlist_next($entries, '', 'raw'));
}

function test_application_decisions_mail_counts()
{
	$texts = array(
		'accepted' => array('subject' => 'Welcome', 'body' => 'You are in, ^^name^^.'),
		'rejected' => array('subject' => 'Thank you', 'body' => ''),
	);
	$rows = array(
		array('decision' => 1, 'mailed_at' => 0, 'email' => 'a@example.com'),
		array('decision' => 1, 'mailed_at' => 0, 'email' => ''),
		array('decision' => 1, 'mailed_at' => 1700000000, 'email' => 'b@example.com'),
		array('decision' => 2, 'mailed_at' => 0, 'email' => 'c@example.com'),
		array('decision' => 3, 'mailed_at' => 0, 'email' => 'd@example.com'),
		array('decision' => 4, 'mailed_at' => 0, 'email' => 'e@example.com'),
		array('decision' => 0, 'mailed_at' => 0, 'email' => 'f@example.com'),
	);

	pg_assert_same(array(
		'accepted' => array('send' => 1, 'no_address' => 1, 'written' => true),
		'rejected' => array('send' => 1, 'no_address' => 0, 'written' => false),
		'waitlisted' => array('send' => 1, 'no_address' => 0, 'written' => false),
	), pg_application_mail_counts($rows, $texts));
	pg_assert_same(1, pg_application_mail_sendable(pg_application_mail_counts($rows, $texts)));
}

function test_application_decisions_result_shows_only_once_mailed()
{
	pg_assert_false(pg_application_result_visible(1, 0));
	pg_assert_true(pg_application_result_visible(1, 1700000000));
	pg_assert_true(pg_application_result_visible('3', '1700000000'));
	pg_assert_true(pg_application_result_visible(4, 1700000000));
	pg_assert_false(pg_application_result_visible(0, 1700000000));
}

function test_application_decisions_posted_ids_keep_only_the_programme_s()
{
	pg_assert_same(array(3, 5), pg_application_posted_ids(array('3', '5', '5', 'x', '9', array(1)), array(3 => true, 5 => true, 7 => true)));
	pg_assert_same(array(), pg_application_posted_ids('3', array(3 => true)));
}
