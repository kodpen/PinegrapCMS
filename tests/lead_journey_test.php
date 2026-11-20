<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visitor journey's pure rules (includes/fn/lead_journey.php):
 * the browser key and the marketing consent, the tie and its move on a
 * shared browser, a panel user, the visits tied back inside the keep period,
 * and the interest score fading by its half-life.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/lead_journey.php');

// 2026-11-20 12:00:00 UTC.
define('LEAD_JOURNEY_TEST_NOW', 1795176000);

function lead_journey_test_tie($browser_contact, $contact_id, $act = 'form', $panel = false, $consent = 'yes', $has_key = true)
{
	return pg_lead_tie_decision(array(
		'consent' => $consent,
		'has_key' => $has_key,
		'browser_contact_id' => $browser_contact,
		'contact_id' => $contact_id,
		'act' => $act,
		'panel_user' => $panel,
	));
}

function test_lead_journey_no_key_without_the_yes()
{
	foreach (array('no', 'unanswered', 'off', '', 'YES ') as $consent) {
		pg_assert_same('none', pg_lead_key_verdict($consent, false), 'no key for ' . var_export($consent, true));
	}

	pg_assert_same('write', pg_lead_key_verdict('yes', false), 'the yes writes the key');
	pg_assert_same('keep', pg_lead_key_verdict('yes', true), 'the yes keeps it');
	pg_assert_same('no_consent', lead_journey_test_tie(0, 5, 'form', false, 'unanswered', false)['verdict'], 'unanswered: nothing tied');
	pg_assert_same('no_consent', lead_journey_test_tie(0, 5, 'form', false, 'off', false)['verdict'], 'notice off: nothing tied');
}

function test_lead_journey_refusal_after_a_yes_deletes_the_key()
{
	pg_assert_same('delete', pg_lead_key_verdict('no', true), 'a refusal deletes the key');
	pg_assert_same('delete', pg_lead_key_verdict('off', true), 'the notice turned off deletes it');

	$decision = lead_journey_test_tie(7, 7, 'sign_in', false, 'no', true);
	pg_assert_same('no_consent', $decision['verdict'], 'no new tie after the refusal');
	pg_assert_same(0, $decision['contact_id'], 'no contact named');
}

function test_lead_journey_first_tie_by_the_persons_act()
{
	foreach (pg_lead_tie_acts() as $act) {
		$decision = lead_journey_test_tie(0, 12, $act);
		pg_assert_same('tie', $decision['verdict'], $act);
		pg_assert_same(12, $decision['contact_id'], $act . ' contact');
		pg_assert_same(0, $decision['moved_from'], $act . ' moved from');
	}

	pg_assert_same('not_an_act', lead_journey_test_tie(0, 12, 'ip_guess')['verdict'], 'never by a guess');
	pg_assert_same('not_an_act', lead_journey_test_tie(0, 12, 'page_view')['verdict'], 'never by a page view');
	pg_assert_same('no_contact', lead_journey_test_tie(0, 0)['verdict'], 'no contact');
	pg_assert_same('no_key', lead_journey_test_tie(0, 12, 'form', false, 'yes', false)['verdict'], 'no key yet');
	pg_assert_same('same', lead_journey_test_tie(12, 12, 'order')['verdict'], 'already tied to the same contact');
}

function test_lead_journey_two_contacts_on_one_browser_move_the_key()
{
	$decision = lead_journey_test_tie(12, 30, 'form');
	pg_assert_same('move', $decision['verdict'], 'a second contact moves the key');
	pg_assert_same(30, $decision['contact_id'], 'the key points at the new contact');
	pg_assert_same(12, $decision['moved_from'], 'the earlier contact is named');

	// The earlier contact's visits stay with it after the move.
	$visits = array(
		array('id' => 1, 'started_at' => LEAD_JOURNEY_TEST_NOW - 5 * 86400, 'contact_id' => 12),
		array('id' => 2, 'started_at' => LEAD_JOURNEY_TEST_NOW - 3600, 'contact_id' => 0),
	);
	pg_assert_same(array(2), pg_lead_tie_back_ids($visits, LEAD_JOURNEY_TEST_NOW - 30 * 86400, pg_lead_keep_start(LEAD_JOURNEY_TEST_NOW, 13)), 'only the untied visit goes to the new contact');
}

function test_lead_journey_panel_user_is_never_tied()
{
	foreach (array(array(0, 'tie'), array(4, 'same'), array(9, 'move')) as $case) {
		$decision = lead_journey_test_tie($case[0], 4, 'sign_in', true);
		pg_assert_same('panel_user', $decision['verdict'], 'panel user instead of ' . $case[1]);
		pg_assert_same(0, $decision['contact_id'], 'no contact for a panel user');
	}
}

function test_lead_journey_tie_back_inside_and_outside_the_keep_period()
{
	$keep_start = pg_lead_keep_start(LEAD_JOURNEY_TEST_NOW, 13);
	pg_assert_same(gmmktime(12, 0, 0, 10, 20, 2025), $keep_start, '13 months back');

	$consent_at = gmmktime(0, 0, 0, 1, 1, 2025);
	$visits = array(
		array('id' => 1, 'started_at' => gmmktime(0, 0, 0, 12, 31, 2024), 'contact_id' => 0),
		array('id' => 2, 'started_at' => gmmktime(0, 0, 0, 6, 1, 2025), 'contact_id' => 0),
		array('id' => 3, 'started_at' => $keep_start - 1, 'contact_id' => 0),
		array('id' => 4, 'started_at' => $keep_start, 'contact_id' => 0),
		array('id' => 5, 'started_at' => LEAD_JOURNEY_TEST_NOW - 60, 'contact_id' => 0),
		array('id' => 6, 'started_at' => LEAD_JOURNEY_TEST_NOW - 120, 'contact_id' => 44),
	);

	pg_assert_same(array(4, 5), pg_lead_tie_back_ids($visits, $consent_at, $keep_start), 'after the consent and inside the keep period');
	pg_assert_same(array(5), pg_lead_tie_back_ids($visits, LEAD_JOURNEY_TEST_NOW - 3600, $keep_start), 'nothing before the consent');
}

function test_lead_journey_keep_start_on_a_short_month()
{
	pg_assert_same(gmmktime(8, 0, 0, 2, 28, 2026), pg_lead_keep_start(gmmktime(8, 0, 0, 3, 31, 2027), 13), 'the 31st falls to the last day');
	pg_assert_same(gmmktime(8, 0, 0, 2, 29, 2024), pg_lead_keep_start(gmmktime(8, 0, 0, 3, 31, 2025), 13), 'a leap February');
	pg_assert_same(pg_lead_keep_start(LEAD_JOURNEY_TEST_NOW, 13), pg_lead_keep_start(LEAD_JOURNEY_TEST_NOW, 0), 'a bad period falls back to 13 months');
}

function test_lead_journey_score_after_its_half_life()
{
	pg_assert_same(10.0, pg_lead_score_decay(20, 30 * 86400, 30), 'half after one half-life');
	pg_assert_same(5.0, pg_lead_score_decay(20, 60 * 86400, 30), 'a quarter after two');
	pg_assert_same(20.0, pg_lead_score_decay(20, -3600, 30), 'a future time counts as now');
	pg_assert_same(10.0, pg_lead_score_decay(20, 30 * 86400, 0), 'a bad half-life falls back to 30 days');

	$events = array(
		array('kind' => 'form', 'at' => LEAD_JOURNEY_TEST_NOW - 30 * 86400),
		array('kind' => 'booking', 'at' => LEAD_JOURNEY_TEST_NOW),
		array('kind' => 'pricing_page', 'at' => LEAD_JOURNEY_TEST_NOW - 60 * 86400),
		array('kind' => 'download', 'at' => LEAD_JOURNEY_TEST_NOW - 30 * 86400),
		array('kind' => 'not_a_kind', 'at' => LEAD_JOURNEY_TEST_NOW),
	);
	$score = pg_lead_interest_score($events, LEAD_JOURNEY_TEST_NOW);

	// 30 + 12.5 + 7.5 + 2.5 = 52.5.
	pg_assert_same(53, $score['score'], 'faded sum, rounded');
	pg_assert_same(
		array(array('kind' => 'booking', 'points' => 30.0), array('kind' => 'form', 'points' => 12.5), array('kind' => 'download', 'points' => 7.5)),
		$score['reasons'],
		'the top three reasons as codes'
	);
}

function test_lead_journey_score_caps_and_site_weights()
{
	$events = array();
	for ($i = 0; $i < 6; $i++) {
		$events[] = array('kind' => 'booking', 'at' => LEAD_JOURNEY_TEST_NOW);
	}
	pg_assert_same(100, pg_lead_interest_score($events, LEAD_JOURNEY_TEST_NOW)['score'], 'capped at 100');
	pg_assert_same(0, pg_lead_interest_score(array(), LEAD_JOURNEY_TEST_NOW)['score'], 'nothing seen');

	$weights = pg_lead_score_weights(array('form' => 40, 'download' => -5, 'invented' => 50));
	pg_assert_same(40, $weights['form'], 'a site weight overrides');
	pg_assert_same(0, $weights['download'], 'never below 0');
	pg_assert_false(isset($weights['invented']), 'no unknown kind');

	$score = pg_lead_interest_score(array(array('kind' => 'form', 'at' => LEAD_JOURNEY_TEST_NOW - 10 * 86400)), LEAD_JOURNEY_TEST_NOW, array('form' => 40), 10);
	pg_assert_same(20, $score['score'], 'site weight and half-life');
}

function test_lead_journey_quick_return()
{
	pg_assert_true(pg_lead_is_quick_return(LEAD_JOURNEY_TEST_NOW - 7 * 86400, LEAD_JOURNEY_TEST_NOW), 'within 7 days');
	pg_assert_false(pg_lead_is_quick_return(LEAD_JOURNEY_TEST_NOW - 7 * 86400 - 1, LEAD_JOURNEY_TEST_NOW), 'after 7 days');
	pg_assert_false(pg_lead_is_quick_return(0, LEAD_JOURNEY_TEST_NOW), 'a first visit is no return');
	pg_assert_false(pg_lead_is_quick_return(LEAD_JOURNEY_TEST_NOW, LEAD_JOURNEY_TEST_NOW), 'the same visit is no return');
}
