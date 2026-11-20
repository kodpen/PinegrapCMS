<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the application review rules (includes/fn/application_review.php)
 * that need no database: eligibility, weighted sums, the mean, the leniency
 * correction, the spread flag, balanced assignment and the shortlist cut.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_application_review_eligibility_passes_and_fails_with_reasons()
{
	$rules = array(
		array('type' => 'file', 'field' => 'cv'),
		array('type' => 'range', 'field' => 'age', 'min' => 18, 'max' => 30),
		array('type' => 'choice', 'field' => 'city', 'values' => array('Ankara', 'İzmir')),
	);

	$ok = pg_application_eligibility($rules, array('cv' => 'cv.pdf', 'age' => '30', 'city' => 'İzmir'));
	pg_assert_true($ok['eligible']);
	pg_assert_same(array(), $ok['reasons']);

	$bad = pg_application_eligibility($rules, array('cv' => '', 'age' => '31', 'city' => array('Bursa')));
	pg_assert_false($bad['eligible']);
	pg_assert_same(array(
		array('rule' => 0, 'field' => 'cv', 'code' => 'missing'),
		array('rule' => 1, 'field' => 'age', 'code' => 'above'),
		array('rule' => 2, 'field' => 'city', 'code' => 'not_listed'),
	), $bad['reasons']);

	// A missing or non-numeric number fails its range; a multi-choice answer
	// passes when one of its values is listed.
	$other = pg_application_eligibility($rules, array('cv' => 'a.pdf', 'age' => 'abc', 'city' => array('Bursa', 'Ankara')));
	pg_assert_same(array(array('rule' => 1, 'field' => 'age', 'code' => 'not_number')), $other['reasons']);
	$young = pg_application_eligibility(array($rules[1]), array('age' => '17.5'));
	pg_assert_same('below', $young['reasons'][0]['code']);

	// No rules: every entry is eligible.
	pg_assert_true(pg_application_eligibility(array(), array())['eligible']);
}

function test_application_review_criteria_weights_must_add_to_100()
{
	pg_assert_true(pg_application_criteria_valid(array(array('id' => 'a', 'weight' => 60), array('id' => 'b', 'weight' => 40))));
	pg_assert_false(pg_application_criteria_valid(array(array('id' => 'a', 'weight' => 60), array('id' => 'b', 'weight' => 30))));
	pg_assert_false(pg_application_criteria_valid(array(array('id' => 'a', 'weight' => 100), array('id' => 'b', 'weight' => 0))));
	pg_assert_false(pg_application_criteria_valid(array(array('id' => 'a', 'weight' => 50), array('id' => 'a', 'weight' => 50))));
	pg_assert_false(pg_application_criteria_valid(array()));
}

function test_application_review_weighted_sum()
{
	$criteria = array(array('id' => 'idea', 'weight' => 60), array('id' => 'team', 'weight' => 40));

	pg_assert_same(7.6, pg_application_weighted_sum($criteria, array('idea' => 8, 'team' => 7)));
	pg_assert_same(10.0, pg_application_weighted_sum($criteria, array('idea' => '10', 'team' => '10')));

	// A missing, out-of-range or fractional score leaves the review unscored.
	pg_assert_same(null, pg_application_weighted_sum($criteria, array('idea' => 8)));
	pg_assert_same(null, pg_application_weighted_sum($criteria, array('idea' => 11, 'team' => 5)));
	pg_assert_same(null, pg_application_weighted_sum($criteria, array('idea' => 0, 'team' => 5)));
	pg_assert_same(null, pg_application_weighted_sum($criteria, array('idea' => 7.5, 'team' => 5)));
}

function test_application_review_mean()
{
	pg_assert_same(null, pg_application_mean(array()));
	pg_assert_same(7.0, pg_application_mean(array(6, 8)));
	pg_assert_same(7.3333, pg_application_mean(array(7, 7, 8)));
}

/**
 * A strict reviewer (low scores) and a lenient one (high scores) over the
 * same five entries in the same order: after the correction both rank the
 * entries alike and land on the same scale.
 */
function test_application_review_leniency_correction_strict_and_lenient()
{
	$reviews = array();
	$strict = array(2, 3, 4, 5, 6);
	$lenient = array(6, 7, 8, 9, 10);

	foreach ($strict as $i => $score) {
		$reviews[] = array('reviewer' => 's', 'entry' => $i + 1, 'score' => $score);
		$reviews[] = array('reviewer' => 'l', 'entry' => $i + 1, 'score' => $lenient[$i]);
	}

	$corrected = pg_application_leniency_correct($reviews);
	pg_assert_same(10, count($corrected));

	$by = array();

	foreach ($corrected as $review) {
		pg_assert_true($review['corrected_applied']);
		$by[$review['reviewer']][$review['entry']] = $review['corrected'];
	}

	foreach (array(1, 2, 3, 4, 5) as $entry) {
		pg_assert_same($by['s'][$entry], $by['l'][$entry]);
	}

	// Both reviewers' middle entry lands on the pool's mean (6), the order holds.
	pg_assert_same(6.0, $by['s'][3]);
	pg_assert_true($by['s'][1] < $by['s'][2] && $by['s'][4] < $by['s'][5]);

	// The raw score stays beside the corrected one.
	pg_assert_same(2, $corrected[0]['score']);
}

function test_application_review_leniency_needs_five_scores()
{
	$reviews = array();

	foreach (array(9, 8, 9, 10) as $i => $score) {
		$reviews[] = array('reviewer' => 'four', 'entry' => $i + 1, 'score' => $score);
	}

	foreach (array(3, 5, 4, 6, 2) as $i => $score) {
		$reviews[] = array('reviewer' => 'five', 'entry' => $i + 1, 'score' => $score);
	}

	$corrected = pg_application_leniency_correct($reviews);

	foreach ($corrected as $review) {
		if ($review['reviewer'] === 'four') {
			// Four scores: not corrected, the raw score is carried.
			pg_assert_false($review['corrected_applied']);
			pg_assert_same((float) $review['score'], $review['corrected']);
		} else {
			pg_assert_true($review['corrected_applied']);
			pg_assert_true($review['corrected'] >= 1.0 && $review['corrected'] <= 10.0);
		}
	}

	// A reviewer giving one score to all keeps their raw scores (no spread to
	// standardise against).
	$flat = array();

	foreach (range(1, 5) as $entry) {
		$flat[] = array('reviewer' => 'flat', 'entry' => $entry, 'score' => 7);
	}

	foreach (pg_application_leniency_correct($flat) as $review) {
		pg_assert_false($review['corrected_applied']);
		pg_assert_same(7.0, $review['corrected']);
	}

	pg_assert_same(array(), pg_application_leniency_correct(array()));
}

function test_application_review_spread_flag()
{
	pg_assert_same(3.5, pg_application_spread(array(5, 8.5, 6)));
	pg_assert_same(0.0, pg_application_spread(array(7)));
	pg_assert_same(0.0, pg_application_spread(array()));

	pg_assert_true(pg_application_needs_consensus(array(3, 8), 4));
	pg_assert_false(pg_application_needs_consensus(array(4, 8), 4));
	pg_assert_false(pg_application_needs_consensus(array(1), 0));
	// No bound set: never flagged.
	pg_assert_false(pg_application_needs_consensus(array(1, 10), null));
}

function test_application_review_assignment_is_balanced()
{
	$result = pg_application_assign(array(1, 2, 3, 4, 5, 6), array('a', 'b', 'c'), 2, array(), array(), false);

	$load = array('a' => 0, 'b' => 0, 'c' => 0);

	foreach ($result['assigned'] as $entry => $reviewers) {
		pg_assert_same(2, count($reviewers));
		pg_assert_same(2, count(array_unique($reviewers)));

		foreach ($reviewers as $reviewer) {
			$load[$reviewer]++;
		}
	}

	pg_assert_same(array('a' => 4, 'b' => 4, 'c' => 4), $load);
	pg_assert_same(array(), $result['short']);

	// Random tie-breaking still balances.
	$random = pg_application_assign(array(1, 2, 3), array('a', 'b', 'c'), 1, array(), array());
	$seen = array();

	foreach ($random['assigned'] as $reviewers) {
		$seen[] = $reviewers[0];
	}

	sort($seen);
	pg_assert_same(array('a', 'b', 'c'), $seen);
}

function test_application_review_assignment_pool_of_one()
{
	$result = pg_application_assign(array(1, 2), array('only'), 3, array(), array(), false);

	pg_assert_same(array(1 => array('only'), 2 => array('only')), $result['assigned']);
	pg_assert_same(array(1 => 2, 2 => 2), $result['short']);

	// An empty pool gives no one and reports the whole shortfall.
	$empty = pg_application_assign(array(1), array(), 2, array(), array(), false);
	pg_assert_same(array(1 => array()), $empty['assigned']);
	pg_assert_same(array(1 => 2), $empty['short']);
}

function test_application_review_assignment_conflict_and_rerun()
{
	// Reviewer a declared a conflict on entry 1: it goes to another of the pool.
	$result = pg_application_assign(array(1), array('a', 'b', 'c'), 2, array(), array(1 => array('a')), false);
	pg_assert_same(array('b', 'c'), $result['assigned'][1]);

	// A re-run keeps the reviews already given and only tops up, counting
	// the kept ones in the load.
	$rerun = pg_application_assign(array(1, 2), array('a', 'b', 'c'), 2, array(1 => array('a', 'b'), 2 => array('a')), array(), false);
	pg_assert_same(array('a', 'b'), $rerun['assigned'][1]);
	pg_assert_same(array('a', 'c'), $rerun['assigned'][2]);

	// A conflict with an already given reviewer drops them and finds another.
	$moved = pg_application_assign(array(1), array('a', 'b', 'c'), 2, array(1 => array('a', 'b')), array(1 => array('a')), false);
	pg_assert_same(array('b', 'c'), $moved['assigned'][1]);
	pg_assert_same(array(), $moved['short']);

	// Every one of a pool of two in conflict: no one, the shortfall reported.
	$none = pg_application_assign(array(1), array('a', 'b'), 1, array(), array(1 => array('a', 'b')), false);
	pg_assert_same(array(1 => array()), $none['assigned']);
	pg_assert_same(array(1 => 1), $none['short']);
}

function test_application_review_rank_and_shortlist()
{
	$scores = array(10 => 7.5, 11 => 9.0, 12 => null, 13 => 7.5, 14 => 6.0);

	// Highest first, equal scores by entry id; an unscored entry is left out.
	pg_assert_same(array(11, 10, 13, 14), pg_application_rank($scores));

	pg_assert_same(array(11, 10, 13), pg_application_shortlist($scores, 'cutoff', 7.5));
	pg_assert_same(array(11), pg_application_shortlist($scores, 'cutoff', 8));
	pg_assert_same(array(11), pg_application_shortlist($scores, 'top', 1));
	pg_assert_same(array(), pg_application_shortlist($scores, 'top', 0));
	pg_assert_same(array(), pg_application_shortlist($scores, 'other', 3));
}

function test_application_review_shortlist_tie_at_cut_off()
{
	// Top 2 with a tie for second place: both tied entries are taken.
	$scores = array(1 => 9.0, 2 => 8.0, 3 => 8.0, 4 => 7.0);
	pg_assert_same(array(1, 2, 3), pg_application_shortlist($scores, 'top', 2));

	// Float noise from the weighted sums does not split a tie.
	$noisy = array(1 => 0.1 + 0.2, 2 => 0.3, 3 => 0.2);
	pg_assert_same(array(1, 2), pg_application_shortlist($noisy, 'top', 1));
	pg_assert_same(array(1, 2), pg_application_shortlist($noisy, 'cutoff', 0.3));
}

function test_application_review_rank_by_category()
{
	$entries = array(
		1 => array('category' => 'film', 'score' => 6.0),
		2 => array('category' => 'music', 'score' => 9.0),
		3 => array('category' => 'film', 'score' => 8.0),
		4 => array('category' => '', 'score' => 5.0),
	);

	pg_assert_same(array('film' => array(3, 1), 'music' => array(2), '' => array(4)), pg_application_rank_by_category($entries));
}
