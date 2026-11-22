<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an application programme's ranking that need no database
 * (includes/fn/application_ranking.php): a round's ranking settings, the
 * cut and bound typed, combining the sent reviews, the consensus state, the
 * ranking per category with its shortlist.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/application_ranking.php');

function test_application_ranking_round_settings_and_their_defaults()
{
	pg_assert_same(array('spread' => null, 'rank_by' => 'raw', 'cut_mode' => '', 'cut_value' => 0), pg_application_round_settings(array()));
	pg_assert_same(
		array('spread' => 2.5, 'rank_by' => 'corrected', 'cut_mode' => 'top', 'cut_value' => 3),
		pg_application_round_settings(array('spread' => 2.5, 'rank_by' => 'corrected', 'cut' => array('mode' => 'top', 'value' => '3')))
	);
	pg_assert_same(
		array('spread' => null, 'rank_by' => 'raw', 'cut_mode' => 'cutoff', 'cut_value' => 7.25),
		pg_application_round_settings(array('spread' => -1, 'rank_by' => 'odd', 'cut' => array('mode' => 'cutoff', 'value' => 7.25)))
	);
	pg_assert_same('', pg_application_round_settings(array('cut' => array('mode' => 'all', 'value' => 1)))['cut_mode']);
}

function test_application_ranking_bound_and_cut_input()
{
	pg_assert_same(array('spread' => null, 'rank_by' => 'raw'), pg_application_round_ranking_input(array('spread' => ' ')));
	pg_assert_same(array('spread' => 2.5, 'rank_by' => 'corrected'), pg_application_round_ranking_input(array('spread' => '2,5', 'rank_by' => 'corrected')));
	pg_assert_same(array('spread' => 0.0, 'rank_by' => 'raw'), pg_application_round_ranking_input(array('spread' => '0')));
	pg_assert_false(pg_application_round_ranking_input(array('spread' => '10')));
	pg_assert_false(pg_application_round_ranking_input(array('spread' => 'two')));

	pg_assert_same(array('mode' => 'cutoff', 'value' => 7.5), pg_application_cut_input('cutoff', '7,5'));
	pg_assert_same(array('mode' => 'cutoff', 'value' => 10.0), pg_application_cut_input('cutoff', '10'));
	pg_assert_false(pg_application_cut_input('cutoff', '0.5'));
	pg_assert_false(pg_application_cut_input('cutoff', '11'));
	pg_assert_same(array('mode' => 'top', 'value' => 5), pg_application_cut_input('top', '5'));
	pg_assert_false(pg_application_cut_input('top', '0'));
	pg_assert_false(pg_application_cut_input('top', '2.5'));
	pg_assert_false(pg_application_cut_input('all', '5'));
	pg_assert_false(pg_application_cut_input('top', array('5')));
}

function test_application_ranking_set_up_save_keeps_the_cut()
{
	$stored = array(array('criteria' => array(), 'cut' => array('mode' => 'top', 'value' => 4)), array('criteria' => array()));
	$posted = array(array('criteria' => array('x')), array('criteria' => array('y')), array('criteria' => array('z')));

	$kept = pg_application_rounds_keep_cut($stored, $posted);

	pg_assert_same(array('mode' => 'top', 'value' => 4), $kept[0]['cut']);
	pg_assert_false(isset($kept[1]['cut']));
	pg_assert_same(array('z'), $kept[2]['criteria']);
}

function test_application_ranking_combines_means_spread_and_consensus()
{
	$reviews = array(
		array('entry' => 1, 'reviewer' => 10, 'total' => 8.0),
		array('entry' => 1, 'reviewer' => 11, 'total' => 4.0),
		array('entry' => 2, 'reviewer' => 10, 'total' => 6.5),
		array('entry' => 2, 'reviewer' => 11, 'total' => 7.0),
		array('entry' => 9, 'reviewer' => 11, 'total' => 9.0),
	);

	$combined = pg_application_combine(array(1, 2, 3), $reviews, 3);

	// Fewer than five reviews each: the corrected mean is the raw one.
	pg_assert_same(array('done' => 2, 'raw' => 6.0, 'corrected' => 6.0, 'spread' => 4.0, 'needs' => true), $combined[1]);
	pg_assert_same(array('done' => 2, 'raw' => 6.75, 'corrected' => 6.75, 'spread' => 0.5, 'needs' => false), $combined[2]);
	pg_assert_same(array('done' => 0, 'raw' => null, 'corrected' => null, 'spread' => 0.0, 'needs' => false), $combined[3]);
	pg_assert_false(isset($combined[9]), 'a review of another round\'s entry is left out');

	// No bound: never flagged.
	pg_assert_false(pg_application_combine(array(1), $reviews, null)[1]['needs']);
}

function test_application_ranking_corrects_a_strict_reviewer_from_five_reviews()
{
	// Reviewer 10 is strict (2 below 11 on every entry); both order the five
	// entries alike, so the corrected means are equal per entry.
	$reviews = array();

	foreach (array(1 => 7.0, 2 => 6.0, 3 => 5.0, 4 => 8.0, 5 => 4.0) as $entry => $lenient) {
		$reviews[] = array('entry' => $entry, 'reviewer' => 11, 'total' => $lenient);
		$reviews[] = array('entry' => $entry, 'reviewer' => 10, 'total' => $lenient - 2);
	}

	$raw = pg_application_combine(array(1, 2, 3, 4, 5), $reviews, 1);

	pg_assert_same(6.0, $raw[1]['raw']);
	pg_assert_same(2.0, $raw[1]['spread']);
	pg_assert_true($raw[1]['needs']);

	// Ranked by the corrected mean: both reviews of an entry come out alike
	// (pool mean 5, deviation sqrt(3); each reviewer's sqrt(2)), so no flag.
	$corrected = pg_application_combine(array(1, 2, 3, 4, 5), $reviews, 1, 'corrected');

	pg_assert_same(6.0, $corrected[1]['raw']);
	pg_assert_same(round(5 + sqrt(3) / sqrt(2), 4), $corrected[1]['corrected']);
	pg_assert_same(0.0, $corrected[1]['spread']);
	pg_assert_false($corrected[1]['needs']);
	pg_assert_true($corrected[4]['corrected'] > $corrected[1]['corrected']);

	$corrected = pg_application_leniency_correct(array(
		array('reviewer' => 10, 'entry' => 1, 'score' => 5.0),
	));
	pg_assert_false($corrected[0]['corrected_applied']);
}

function test_application_ranking_consensus_state()
{
	pg_assert_same(1, pg_application_consensus_state(true, 0));
	pg_assert_same(0, pg_application_consensus_state(false, 1), 'a flag that no longer holds is lifted');
	pg_assert_same(2, pg_application_consensus_state(true, 2), 'a settled entry stays settled');
	pg_assert_same(2, pg_application_consensus_state(false, '2'));
}

function test_application_ranking_per_category_with_the_cut()
{
	$entries = array(
		1 => array('category' => 'Music', 'eligible' => 1, 'raw' => 7.0, 'corrected' => 6.0),
		2 => array('category' => 'Music', 'eligible' => 3, 'raw' => 6.0, 'corrected' => 8.0),
		3 => array('category' => 'Music', 'eligible' => 2, 'raw' => 9.0, 'corrected' => 9.0),
		4 => array('category' => 'Dance', 'eligible' => 1, 'raw' => 5.0, 'corrected' => 5.0),
		5 => array('category' => 'Dance', 'eligible' => 1, 'raw' => 5.0, 'corrected' => 5.0),
		6 => array('category' => 'Dance', 'eligible' => 1, 'raw' => 4.0, 'corrected' => 4.0),
		7 => array('category' => 'Dance', 'eligible' => 1, 'raw' => null, 'corrected' => null),
		8 => array('category' => 'Art', 'eligible' => 2, 'raw' => 3.0, 'corrected' => 3.0),
	);

	$raw = pg_application_ranking($entries, 'raw', 'top', 1);

	pg_assert_same(array('Art', 'Dance', 'Music'), array_keys($raw), 'categories in name order, one with no eligible entry too');
	pg_assert_same(array('ranked' => array(), 'shortlist' => array()), $raw['Art']);
	pg_assert_same(array(1, 2), $raw['Music']['ranked'], 'a not eligible entry is not ranked');
	pg_assert_same(array(1), $raw['Music']['shortlist']);
	pg_assert_same(array(4, 5, 6), $raw['Dance']['ranked'], 'an unscored entry is not ranked');
	pg_assert_same(array(4, 5), $raw['Dance']['shortlist'], 'a tie at the top N is not split');

	$corrected = pg_application_ranking($entries, 'corrected', 'cutoff', 6);
	pg_assert_same(array(2, 1), $corrected['Music']['ranked']);
	pg_assert_same(array(2, 1), $corrected['Music']['shortlist']);
	pg_assert_same(array(), $corrected['Dance']['shortlist']);

	pg_assert_same(array(), pg_application_ranking($entries, 'raw', '', 0)['Music']['shortlist'], 'no cut, no shortlist');
}

function test_application_ranking_next_round_needs_a_next_round_and_a_shortlist()
{
	$program = array('status' => 2, 'round' => 1, 'rounds' => array(array('criteria' => array()), array('criteria' => array())));

	pg_assert_true(pg_application_next_round_ready($program, 3));
	pg_assert_false(pg_application_next_round_ready($program, 0));
	pg_assert_false(pg_application_next_round_ready(array('round' => 2) + $program, 3), 'the last round has no next');
	pg_assert_false(pg_application_next_round_ready(array('status' => 1) + $program, 3), 'not while entries still come in');
	pg_assert_false(pg_application_next_round_ready(array('status' => 0) + $program, 3), 'not while a draft');
}

function test_application_ranking_set_up_reads_a_rounds_bound_and_score()
{
	$round = array('cid' => array(''), 'cname' => array('Idea'), 'cweight' => array('100'), 'reviewers' => '2', 'ends' => '');

	$input = pg_application_program_input(array('name' => 'A', 'rounds' => array($round + array('spread' => '2.5', 'rank_by' => 'corrected'), $round)), array());

	pg_assert_same(array(), $input['errors']);
	pg_assert_same(2.5, $input['program']['rounds'][0]['spread']);
	pg_assert_same('corrected', $input['program']['rounds'][0]['rank_by']);
	pg_assert_same(null, $input['program']['rounds'][1]['spread']);
	pg_assert_same('raw', $input['program']['rounds'][1]['rank_by']);

	$input = pg_application_program_input(array('name' => 'A', 'rounds' => array($round + array('spread' => '12'))), array());
	pg_assert_same(1, count($input['errors']));
	pg_assert_contains('0 to 9', $input['errors'][0]);
}
