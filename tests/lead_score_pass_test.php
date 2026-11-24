<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visitor journey's scoring pass (includes/fn/lead_score.php):
 * which contacts a pass works out, and the score row a contact merge leaves
 * for the contact that stays.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/lead_score.php');

function test_lead_score_merge_plan_keeps_one_row_for_the_survivor()
{
	pg_assert_same(null, pg_lead_score_merge_plan(array(), 5), 'no score row, nothing to keep');

	$rows = array(
		array('contact_id' => 5, 'interest' => 10, 'hot' => 0, 'hot_at' => 0, 'returned_at' => 0, 'last_visit_at' => 1000),
		array('contact_id' => 8, 'interest' => 72, 'hot' => 1, 'hot_at' => 900, 'returned_at' => 950, 'last_visit_at' => 990),
		array('contact_id' => 9, 'interest' => 30, 'hot' => 0, 'hot_at' => 0, 'returned_at' => 0, 'last_visit_at' => 1200),
	);
	$plan = pg_lead_score_merge_plan($rows, 5);

	pg_assert_same(8, $plan['from'], 'the strongest row is kept');
	pg_assert_same(array(5, 9), $plan['drop'], 'the other rows go');
	pg_assert_same(1, $plan['hot'], 'a merged contact already told hot is not told again');
	pg_assert_same(900, $plan['hot_at'], 'when it turned hot');
	pg_assert_same(950, $plan['returned_at'], 'the last return told');
	pg_assert_same(1200, $plan['last_visit_at'], 'the newest visit of all');

	$only = pg_lead_score_merge_plan(array(array('contact_id' => 7, 'interest' => 0, 'hot' => 0, 'hot_at' => 0, 'returned_at' => 0, 'last_visit_at' => 50)), 5);
	pg_assert_same(7, $only['from'], 'a merged contact\'s row moves to a survivor with none');
	pg_assert_same(array(), $only['drop'], 'nothing else to drop');
}

function test_lead_score_candidates_take_a_row_marked_for_work()
{
	$sql = pg_lead_score_candidates_sql(1000, 2000, 90000, 500);

	pg_assert_contains("scored_at = 0", $sql, 'a row marked after a merge is worked out on the next pass');
}

function test_lead_score_candidates_take_a_row_past_the_keep_period()
{
	$sql = pg_lead_score_candidates_sql(1000, 2000, 90000, 500);

	pg_assert_contains("last_visit_at < '500'", $sql, 'a row whose visits are past the keep period is worked out (and goes), whatever its interest');
}

function test_lead_score_settings_change_reworks_the_scores()
{
	$defaults = pg_lead_score_settings(array());

	pg_assert_false(pg_lead_score_settings_rework($defaults, $defaults), 'nothing changed, nothing to rework');
	pg_assert_false(pg_lead_score_settings_rework($defaults, array('keep_months' => 6) + $defaults), 'the keep period changes no score');
	pg_assert_true(pg_lead_score_settings_rework($defaults, array('threshold' => 40) + $defaults), 'a new threshold: the hot flags the view reads change');
	pg_assert_true(pg_lead_score_settings_rework($defaults, array('half_life' => 7) + $defaults), 'a new half-life');
	pg_assert_true(pg_lead_score_settings_rework($defaults, array('pricing_pages' => array(4)) + $defaults), 'other pricing pages');

	$weights = $defaults;
	$weights['weights']['form'] = 50;
	pg_assert_true(pg_lead_score_settings_rework($defaults, $weights), 'an act\'s points');
	pg_assert_true(pg_lead_score_settings_rework('', json_encode($weights)), 'stored texts are read');
}
