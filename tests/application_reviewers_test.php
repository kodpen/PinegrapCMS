<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an application programme's reviewers that need no database
 * (includes/fn/application_reviewers.php): the pool a reviewer serves and
 * the assignment run worked out per category, with kept, dropped and
 * conflicting reviews.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/application_reviewers.php');

function test_application_reviewers_pool_and_categories()
{
	pg_assert_same(null, pg_application_reviewer_categories(null));
	pg_assert_same(null, pg_application_reviewer_categories('not json'));
	pg_assert_same(array('Music', 'Dance'), pg_application_reviewer_categories('["Music","Dance","Music",""]'));

	// No categories on the reviewer: every pool; none on the programme:
	// one pool for everyone.
	pg_assert_true(pg_application_reviewer_in_pool(null, 'Music', true));
	pg_assert_true(pg_application_reviewer_in_pool(array('Music'), 'Music', true));
	pg_assert_false(pg_application_reviewer_in_pool(array('Music'), 'Dance', true));
	pg_assert_true(pg_application_reviewer_in_pool(array('Music'), 'Dance', false));
}

function test_application_reviewers_plan_gives_each_category_its_pool()
{
	$entries = array(array('id' => 1, 'category' => 'Music'), array('id' => 2, 'category' => 'Dance'), array('id' => 3, 'category' => 'Music'));
	$reviewers = array(
		array('id' => 10, 'categories' => array('Music'), 'revoked_at' => 0),
		array('id' => 11, 'categories' => array('Dance'), 'revoked_at' => 0),
		array('id' => 12, 'categories' => null, 'revoked_at' => 0),
		array('id' => 13, 'categories' => null, 'revoked_at' => 99),
	);

	$plan = pg_application_assignment_plan($entries, $reviewers, array(), 2, true, false);

	// Dance first (categories in name order), then Music; the removed
	// reviewer 13 is in no pool.
	pg_assert_same(array(array(2, 11), array(2, 12), array(1, 10), array(1, 12), array(3, 10), array(3, 12)), $plan['inserts']);
	pg_assert_same(array(), $plan['deletes']);
	pg_assert_same(array(), $plan['short']);
	pg_assert_same(3, $plan['entries']);
	pg_assert_same(3, $plan['reviewers']);
}

function test_application_reviewers_plan_rerun_keeps_and_drops()
{
	$entries = array(array('id' => 1, 'category' => ''), array('id' => 2, 'category' => ''));
	$reviewers = array(
		array('id' => 10, 'categories' => null, 'revoked_at' => 0),
		array('id' => 11, 'categories' => null, 'revoked_at' => 0),
		array('id' => 12, 'categories' => null, 'revoked_at' => 5),
	);
	$reviews = array(
		// Entry 1: 10 has sent, 12 (since removed) has not started.
		array('id' => 100, 'entry_id' => 1, 'reviewer_id' => 10, 'status' => 2),
		array('id' => 101, 'entry_id' => 1, 'reviewer_id' => 12, 'status' => 0),
		// Entry 2: 10 declared a conflict; 11 started.
		array('id' => 102, 'entry_id' => 2, 'reviewer_id' => 10, 'status' => 3),
		array('id' => 103, 'entry_id' => 2, 'reviewer_id' => 11, 'status' => 1),
		// A review of an entry no longer given out is left alone.
		array('id' => 104, 'entry_id' => 9, 'reviewer_id' => 11, 'status' => 0),
	);

	$plan = pg_application_assignment_plan($entries, $reviewers, $reviews, 2, false, false);

	// Entry 1 keeps 10 and gets 11 in place of the removed 12; entry 2 keeps
	// 11 and lacks one, as 10 is in conflict and 12 is gone.
	pg_assert_same(array(array(1, 11)), $plan['inserts']);
	pg_assert_same(array(101), $plan['deletes']);
	pg_assert_same(array(2 => 1), $plan['short']);
	pg_assert_same(1, $plan['entries']);
	pg_assert_same(2, $plan['reviewers']);

	// A second run over the result changes nothing.
	$after = array(
		array('id' => 100, 'entry_id' => 1, 'reviewer_id' => 10, 'status' => 2),
		array('id' => 105, 'entry_id' => 1, 'reviewer_id' => 11, 'status' => 0),
		array('id' => 102, 'entry_id' => 2, 'reviewer_id' => 10, 'status' => 3),
		array('id' => 103, 'entry_id' => 2, 'reviewer_id' => 11, 'status' => 1),
	);
	$again = pg_application_assignment_plan($entries, $reviewers, $after, 2, false, false);
	pg_assert_same(array(), $again['inserts']);
	pg_assert_same(array(), $again['deletes']);
	pg_assert_same(0, $again['entries']);
}

function test_application_reviewers_plan_drops_a_reviewer_moved_out_of_the_pool()
{
	$entries = array(array('id' => 1, 'category' => 'Music'));
	$reviewers = array(
		array('id' => 10, 'categories' => array('Dance'), 'revoked_at' => 0),
		array('id' => 11, 'categories' => array('Music'), 'revoked_at' => 0),
	);
	$reviews = array(array('id' => 100, 'entry_id' => 1, 'reviewer_id' => 10, 'status' => 0));

	$plan = pg_application_assignment_plan($entries, $reviewers, $reviews, 1, true, false);
	pg_assert_same(array(array(1, 11)), $plan['inserts']);
	pg_assert_same(array(100), $plan['deletes']);
}
