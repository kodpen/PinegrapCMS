<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the satisfaction surveys' pure rules (includes/fn/feedback.php):
 * the score bands of each kind, the NPS, CSAT and effort sums, the fatigue
 * rule, the low-score rule, the owner chooser and the signed answer token.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function feedback_test_key()
{
	return 'test-key-0123456789abcdef';
}

function test_feedback_kinds_and_ranges()
{
	pg_assert_same(array(0, 10), pg_feedback_range(PG_FEEDBACK_NPS), 'NPS runs 0 to 10');
	pg_assert_same(array(1, 5), pg_feedback_range(PG_FEEDBACK_CSAT), 'CSAT runs 1 to 5');
	pg_assert_same(array(1, 7), pg_feedback_range(PG_FEEDBACK_EFFORT), 'effort runs 1 to 7');
	pg_assert_same(array(), pg_feedback_range(9), 'an unknown kind has no range');

	pg_assert_true(pg_feedback_score_valid(PG_FEEDBACK_NPS, 0), '0 is an NPS answer');
	pg_assert_true(pg_feedback_score_valid(PG_FEEDBACK_NPS, '10'), 'a score from a link reads');
	pg_assert_false(pg_feedback_score_valid(PG_FEEDBACK_NPS, 11), '11 is no NPS answer');
	pg_assert_false(pg_feedback_score_valid(PG_FEEDBACK_CSAT, 0), '0 is no CSAT answer');
	pg_assert_false(pg_feedback_score_valid(PG_FEEDBACK_EFFORT, 8), '8 is no effort answer');
	pg_assert_false(pg_feedback_score_valid(PG_FEEDBACK_NPS, '7.5'), 'a fraction is refused');
	pg_assert_false(pg_feedback_score_valid(PG_FEEDBACK_NPS, ''), 'an empty score is refused');
	pg_assert_false(pg_feedback_score_valid(PG_FEEDBACK_NPS, null), 'no score is refused');
}

function test_feedback_bands()
{
	pg_assert_same(PG_FEEDBACK_BAND_LOW, pg_feedback_band(PG_FEEDBACK_NPS, 6), 'an NPS of 6 is low');
	pg_assert_same(PG_FEEDBACK_BAND_MIDDLE, pg_feedback_band(PG_FEEDBACK_NPS, 7), 'an NPS of 7 is middle');
	pg_assert_same(PG_FEEDBACK_BAND_MIDDLE, pg_feedback_band(PG_FEEDBACK_NPS, 8), 'an NPS of 8 is middle');
	pg_assert_same(PG_FEEDBACK_BAND_HIGH, pg_feedback_band(PG_FEEDBACK_NPS, 9), 'an NPS of 9 is high');
	pg_assert_same(PG_FEEDBACK_BAND_LOW, pg_feedback_band(PG_FEEDBACK_NPS, 0), 'an NPS of 0 is low');

	pg_assert_same(PG_FEEDBACK_BAND_LOW, pg_feedback_band(PG_FEEDBACK_CSAT, 2), 'a CSAT of 2 is low');
	pg_assert_same(PG_FEEDBACK_BAND_MIDDLE, pg_feedback_band(PG_FEEDBACK_CSAT, 3), 'a CSAT of 3 is middle');
	pg_assert_same(PG_FEEDBACK_BAND_HIGH, pg_feedback_band(PG_FEEDBACK_CSAT, 4), 'a CSAT of 4 is high');

	// Effort: 1 is "very easy", so the high numbers are the low band.
	pg_assert_same(PG_FEEDBACK_BAND_HIGH, pg_feedback_band(PG_FEEDBACK_EFFORT, 2), 'an effort of 2 is high');
	pg_assert_same(PG_FEEDBACK_BAND_MIDDLE, pg_feedback_band(PG_FEEDBACK_EFFORT, 5), 'an effort of 5 is middle');
	pg_assert_same(PG_FEEDBACK_BAND_LOW, pg_feedback_band(PG_FEEDBACK_EFFORT, 6), 'an effort of 6 is low');

	pg_assert_same(0, pg_feedback_band(PG_FEEDBACK_NPS, 11), 'an invalid score has no band');
	pg_assert_same(0, pg_feedback_band(9, 3), 'an unknown kind has no band');
}

function test_feedback_low_score_rule()
{
	pg_assert_true(pg_feedback_is_low(PG_FEEDBACK_NPS, 6), 'an NPS of 6 is followed up');
	pg_assert_false(pg_feedback_is_low(PG_FEEDBACK_NPS, 7), 'an NPS of 7 is not');
	pg_assert_true(pg_feedback_is_low(PG_FEEDBACK_CSAT, 1), 'a CSAT of 1 is followed up');
	pg_assert_false(pg_feedback_is_low(PG_FEEDBACK_CSAT, 3), 'a CSAT of 3 is not');
	pg_assert_true(pg_feedback_is_low(PG_FEEDBACK_EFFORT, 7), 'an effort of 7 is followed up');
	pg_assert_false(pg_feedback_is_low(PG_FEEDBACK_EFFORT, 1), 'an effort of 1 is not');
	pg_assert_false(pg_feedback_is_low(PG_FEEDBACK_NPS, -1), 'an invalid score is not');
}

function test_feedback_nps_sum()
{
	$sum = pg_feedback_nps(array(10, 9, 8, 7, 6, 0, 'x', 11));

	pg_assert_same(6, $sum['count'], 'invalid scores are left out');
	pg_assert_same(2, $sum['promoters'], 'promoters are 9 and 10');
	pg_assert_same(2, $sum['passives'], 'passives are 7 and 8');
	pg_assert_same(2, $sum['detractors'], 'detractors are 0 to 6');
	pg_assert_same(0, $sum['score'], 'equal promoters and detractors make 0');

	pg_assert_same(100, pg_feedback_nps(array(9, 10))['score'], 'only promoters make 100');
	pg_assert_same(-100, pg_feedback_nps(array(6, 6, 6))['score'], 'only detractors make -100');
	pg_assert_same(33, pg_feedback_nps(array(10, 8, 8))['score'], 'the score is rounded');
	pg_assert_same(null, pg_feedback_nps(array())['score'], 'no answers: no score');
}

function test_feedback_csat_and_effort_sums()
{
	$csat = pg_feedback_csat(array(5, 4, 3, 1, 9));

	pg_assert_same(4, $csat['count'], 'an invalid CSAT is left out');
	pg_assert_same(2, $csat['satisfied'], 'satisfied are 4 and 5');
	pg_assert_same(50, $csat['score'], 'CSAT is the share of 4 and 5');
	pg_assert_same(3.3, $csat['average'], 'the average has one decimal');
	pg_assert_same(null, pg_feedback_csat(array())['score'], 'no answers: no CSAT');

	$effort = pg_feedback_effort(array(1, 2, 7, 6, 0));

	pg_assert_same(4, $effort['count'], 'an invalid effort is left out');
	pg_assert_same(2, $effort['easy'], 'easy are 1 and 2');
	pg_assert_same(50, $effort['score'], 'the effort score is the share of easy answers');
	pg_assert_same(4.0, $effort['average'], 'the average effort');
	pg_assert_same(null, pg_feedback_effort(array())['average'], 'no answers: no average');

	pg_assert_same(-100, pg_feedback_sum(PG_FEEDBACK_NPS, array(3))['score'], 'the sum by kind: NPS');
	pg_assert_same(100, pg_feedback_sum(PG_FEEDBACK_CSAT, array(5))['score'], 'the sum by kind: CSAT');
	pg_assert_same(array(), pg_feedback_sum(9, array(5)), 'an unknown kind has no sum');
}

function test_feedback_person_fold_and_hash()
{
	pg_assert_same('ayse@example.com', pg_feedback_fold_person(' Ayse@Example.COM '), 'an address is trimmed and lower-cased');
	pg_assert_same('905321234567', pg_feedback_fold_person('+90 (532) 123-45-67'), 'a number keeps its digits');
	pg_assert_same('', pg_feedback_fold_person('  '), 'nothing folds to nothing');

	pg_assert_same(40, strlen(pg_feedback_person_hash('Ayse@example.com')), 'the hash fits CHAR(40)');
	pg_assert_same(pg_feedback_person_hash('ayse@example.com'), pg_feedback_person_hash(' AYSE@example.com'), 'the same person, the same hash');
	pg_assert_same('', pg_feedback_person_hash(''), 'no person: no hash');
}

function test_feedback_fatigue_rule()
{
	$now = 1800000000;
	$day = 86400;
	$rules = array('staff' => array('Staff@Example.com'), 'test' => array('qa@example.com'));

	pg_assert_same('', pg_feedback_fatigue('ayse@example.com', array(), $rules, $now), 'never asked: asked');
	pg_assert_same('recent', pg_feedback_fatigue('ayse@example.com', array('last_asked_at' => $now - 29 * $day), $rules, $now), 'asked 29 days ago: not again');
	pg_assert_same('', pg_feedback_fatigue('ayse@example.com', array('last_asked_at' => $now - 31 * $day), $rules, $now), 'asked 31 days ago: asked');
	pg_assert_same('staff', pg_feedback_fatigue('staff@example.com', array(), $rules, $now), 'a staff address is never asked');
	pg_assert_same('test', pg_feedback_fatigue(' QA@example.com', array(), $rules, $now), 'a listed test address is never asked');
	pg_assert_same('stopped', pg_feedback_fatigue('ayse@example.com', array('stopped' => 1, 'last_asked_at' => $now - 400 * $day), $rules, $now), '"Don\'t ask me again" holds for good');
	pg_assert_same('none', pg_feedback_fatigue('', array(), $rules, $now), 'no address or number: nobody to ask');
	pg_assert_same('', pg_feedback_fatigue('905321234567', array(), $rules, $now), 'a number is asked like an address');
}

function test_feedback_owner_chooser()
{
	pg_assert_same(12, pg_feedback_owner('booking', array('staff_user_id' => 12), 5), 'a booking goes to its staff user');
	pg_assert_same(5, pg_feedback_owner('booking', array('staff_user_id' => 0), 5), 'a booking with no staff user goes to the fallback');
	pg_assert_same(5, pg_feedback_owner('booking', array(), 5), 'a booking with no staff facts goes to the fallback');
	pg_assert_same(14, pg_feedback_owner('form', array('form_editor_user_id' => 14), 5), 'a form goes to its Form Editor');
	pg_assert_same(16, pg_feedback_owner('conversation', array('assignee_user_id' => 16), 5), 'a conversation goes to its assignee');
	pg_assert_same(5, pg_feedback_owner('order', array('staff_user_id' => 12), 5), 'an order goes to the fallback');
	pg_assert_same(0, pg_feedback_owner('ticket', array(), 0), 'no owner at all: 0');
}

function test_feedback_token()
{
	$key = feedback_test_key();
	$now = 1800000000;
	$token = pg_feedback_token_make('answer', 41, $now, $key);

	pg_assert_true(preg_match('/^[0-9a-z]+\.[0-9a-z]+\.[0-9a-f]{64}$/', $token) === 1, 'the token has its shape');
	pg_assert_same(41, pg_feedback_token_check($token, 'answer', $now + 86400, $key), 'a fresh token names its response');
	pg_assert_same(0, pg_feedback_token_check($token, 'stop', $now, $key), 'a token of another purpose is refused');
	pg_assert_same(0, pg_feedback_token_check($token, 'answer', $now + 31 * 86400, $key), 'a token older than 30 days is refused');
	pg_assert_same(0, pg_feedback_token_check($token, 'answer', $now, 'another-key'), 'a token under another key is refused');
	pg_assert_same(0, pg_feedback_token_check(base_convert('42', 10, 36) . substr($token, strpos($token, '.')), 'answer', $now, $key), 'a changed response is refused');
	pg_assert_same(0, pg_feedback_token_check('junk', 'answer', $now, $key), 'junk is refused');
	pg_assert_same(0, pg_feedback_token_check($token, 'answer', $now - 7200, $key), 'a token from the future is refused');
	pg_assert_same('', pg_feedback_token_make('answer', 41, $now, ''), 'no site key: no token');
	pg_assert_same('', pg_feedback_token_make('other', 41, $now, $key), 'an unknown purpose: no token');
	pg_assert_same('', pg_feedback_token_make('answer', 0, $now, $key), 'no response: no token');

	pg_assert_same(64, strlen(pg_feedback_token_hash($token)), 'the stored hash fits CHAR(64)');
	pg_assert_true(pg_feedback_token_hash($token) !== pg_feedback_token_hash(pg_feedback_token_make('stop', 41, $now, $key)), 'each purpose has its own token');
}
