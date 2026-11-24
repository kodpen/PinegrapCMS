<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an application programme's report that need no database
 * (includes/fn/application_report.php): reviewer progress, the spread by
 * criterion and reviewer, the entries by state, the workbook's sheets; the
 * two events' payload, and the API's programme and entry presenters.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/application_report.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhook_payloads.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/application_programs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

// The presenters' time helper lives in includes/api/response.php, which loads
// only behind the API's own entry: a stand-in of the same shape.
if (!function_exists('api_time')) {
	function api_time($unix)
	{
		return ((int) $unix > 0) ? gmdate('Y-m-d\\TH:i:s\\Z', (int) $unix) : null;
	}
}

function _application_report_reviewers()
{
	return array(array('id' => 4, 'display' => 'Ann'), array('id' => 7, 'display' => 'Bo'), array('id' => 9, 'display' => 'Cy'));
}

function _application_report_reviews()
{
	return array(
		array('reviewer_id' => 4, 'status' => 2, 'total' => 750, 'scores' => '{"c1":{"score":8,"note":"x"},"c2":{"score":7}}'),
		array('reviewer_id' => 4, 'status' => 2, 'total' => 550, 'scores' => '{"c1":{"score":6},"c2":{"score":5}}'),
		array('reviewer_id' => 4, 'status' => 1, 'total' => 0, 'scores' => '{"c1":{"score":3}}'),
		array('reviewer_id' => 7, 'status' => 2, 'total' => 900, 'scores' => '{"c1":{"score":9},"c2":{"score":9.5}}'),
		array('reviewer_id' => 7, 'status' => 3, 'total' => 0, 'scores' => null),
		array('reviewer_id' => 7, 'status' => 0, 'total' => 0, 'scores' => null),
		// A removed reviewer's review is not in the report.
		array('reviewer_id' => 12, 'status' => 2, 'total' => 100, 'scores' => '{"c1":{"score":1}}'),
	);
}

function test_application_report_reviewer_progress()
{
	$lines = pg_application_report_reviewers(_application_report_reviewers(), _application_report_reviews());

	pg_assert_same(3, count($lines));
	pg_assert_same(array('id' => 4, 'name' => 'Ann', 'given' => 3, 'started' => 1, 'sent' => 2, 'conflicts' => 0, 'mean' => 6.5, 'lowest' => 5.5, 'highest' => 7.5), $lines[0]);
	pg_assert_same(array('id' => 7, 'name' => 'Bo', 'given' => 2, 'started' => 0, 'sent' => 1, 'conflicts' => 1, 'mean' => 9.0, 'lowest' => 9.0, 'highest' => 9.0), $lines[1]);
	pg_assert_same(array('id' => 9, 'name' => 'Cy', 'given' => 0, 'started' => 0, 'sent' => 0, 'conflicts' => 0, 'mean' => null, 'lowest' => null, 'highest' => null), $lines[2]);
}

function test_application_report_spread_by_criterion_and_reviewer()
{
	$criteria = array(array('id' => 'c1', 'name' => 'Idea', 'weight' => 60), array('id' => 'c2', 'name' => 'Plan', 'weight' => 40));
	$spread = pg_application_report_criteria($criteria, _application_report_reviewers(), _application_report_reviews());

	// Cy sent nothing; a fractional score (9.5) is not a score.
	pg_assert_same(array(
		array('name' => 'Ann', 'means' => array('c1' => 7.0, 'c2' => 6.0)),
		array('name' => 'Bo', 'means' => array('c1' => 9.0, 'c2' => null)),
	), $spread['reviewers']);
	pg_assert_same(array('id' => 'c1', 'name' => 'Idea', 'weight' => 60, 'count' => 3, 'mean' => 7.67, 'lowest' => 6, 'highest' => 9, 'deviation' => 1.25), $spread['criteria'][0]);
	pg_assert_same(array('id' => 'c2', 'name' => 'Plan', 'weight' => 40, 'count' => 2, 'mean' => 6.0, 'lowest' => 5, 'highest' => 7, 'deviation' => 1.0), $spread['criteria'][1]);

	$empty = pg_application_report_criteria($criteria, _application_report_reviewers(), array());
	pg_assert_same(array(), $empty['reviewers']);
	pg_assert_same(array('id' => 'c2', 'name' => 'Plan', 'weight' => 40, 'count' => 0, 'mean' => null, 'lowest' => null, 'highest' => null, 'deviation' => null), $empty['criteria'][1]);
}

function test_application_report_entries_by_state()
{
	$entry = function ($round, $category, $eligible, $shortlisted, $decision, $sent) {
		return array('round' => $round, 'category' => $category, 'eligible' => $eligible, 'shortlisted' => $shortlisted, 'decision' => $decision, 'sent' => $sent);
	};
	$lines = pg_application_report_states(array(
		$entry(2, 'b', 1, 1, 1, 2),
		$entry(1, 'b', 2, 0, 0, 0),
		$entry(1, 'a', 3, 0, 2, 1),
		$entry(1, 'b', 1, 1, 3, 2),
		$entry(2, 'b', 1, 0, 4, 0),
	));

	pg_assert_same(array('1|a', '1|b', '2|b'), array_map(function ($line) {
		return $line['round'] . '|' . $line['category'];
	}, $lines));
	pg_assert_same(array('round' => 1, 'category' => 'a', 'entries' => 1, 'not_eligible' => 0, 'let_in' => 1, 'reviewed' => 1, 'shortlisted' => 0, 'accepted' => 0, 'rejected' => 1, 'waitlisted' => 0, 'declined' => 0), $lines[0]);
	pg_assert_same(array('round' => 1, 'category' => 'b', 'entries' => 2, 'not_eligible' => 1, 'let_in' => 0, 'reviewed' => 1, 'shortlisted' => 1, 'accepted' => 0, 'rejected' => 0, 'waitlisted' => 1, 'declined' => 0), $lines[1]);
	pg_assert_same(array('round' => 2, 'category' => 'b', 'entries' => 2, 'not_eligible' => 0, 'let_in' => 0, 'reviewed' => 1, 'shortlisted' => 1, 'accepted' => 1, 'rejected' => 0, 'waitlisted' => 0, 'declined' => 1), $lines[2]);
	pg_assert_same(array(), pg_application_report_states(array()));
}

function test_application_report_workbook_sheets()
{
	$report = array(
		'reviewers' => array(array('id' => 4, 'name' => '=Ann', 'given' => 3, 'started' => 1, 'sent' => 2, 'conflicts' => 0, 'mean' => 6.5, 'lowest' => 5.5, 'highest' => 7.5)),
		'spread' => array(
			'reviewers' => array(array('name' => 'Ann', 'means' => array('c1' => 7.0, 'c2' => null))),
			'criteria' => array(
				array('id' => 'c1', 'name' => 'Idea', 'weight' => 60, 'count' => 2, 'mean' => 7.0, 'lowest' => 6, 'highest' => 8, 'deviation' => 1.0),
				array('id' => 'c2', 'name' => 'Plan', 'weight' => 40, 'count' => 0, 'mean' => null, 'lowest' => null, 'highest' => null, 'deviation' => null),
			),
		),
		'states' => array(array('round' => 1, 'category' => 'a', 'entries' => 1, 'not_eligible' => 0, 'let_in' => 1, 'reviewed' => 1, 'shortlisted' => 0, 'accepted' => 0, 'rejected' => 1, 'waitlisted' => 0, 'declined' => 0)),
	);
	$sheets = pg_application_report_sheets($report, array('a' => 'Arts'));

	pg_assert_same(3, count($sheets));
	// A name starting with "=" stays text, never a formula.
	pg_assert_same(array('t' => 's', 'v' => '=Ann'), $sheets[0]['rows'][2][0]);
	pg_assert_same(array('t' => 'n', 'v' => '6.5'), $sheets[0]['rows'][2][5]);
	pg_assert_same(array('t' => 's', 'v' => 'Idea'), $sheets[1]['rows'][1][1]);
	pg_assert_same(array('t' => 'n', 'v' => '7'), $sheets[1]['rows'][2][1]);
	pg_assert_same(null, $sheets[1]['rows'][2][2]);
	// The reviewer's line, then mean, lowest, highest and deviation.
	pg_assert_same(6, count($sheets[1]['rows']));
	pg_assert_same(array('t' => 's', 'v' => 'Arts'), $sheets[2]['rows'][2][1]);
	pg_assert_same(11, count($sheets[2]['rows'][1]));
}

function test_application_report_event_payload_and_record()
{
	$row = array('id' => 88, 'form_id' => 2210, 'page_id' => 14, 'reference_code' => 'K4M9QZ', 'category' => 'painting', 'round' => 2, 'score_raw' => 725, 'decision' => 3, 'reviewer_id' => 5, 'eligibility_note' => '{"override":{"note":"x"}}');
	$program = array('id' => 3, 'name' => 'Young artists');
	$payload = pg_application_event_payload($row, $program) + array('previous_decision' => 'none');

	pg_assert_same(array('id' => 2210, 'form_id' => 14, 'entry_id' => 88, 'program_id' => 3, 'program_name' => 'Young artists', 'reference_code' => 'K4M9QZ', 'category' => 'painting', 'round' => 2, 'score_raw' => 7.25, 'decision' => 'waitlisted', 'previous_decision' => 'none'), $payload);
	pg_assert_same(null, pg_application_event_payload(array('score_raw' => 0, 'decision' => 9) + $row, $program)['score_raw']);
	pg_assert_same('none', pg_application_event_payload(array('decision' => 9) + $row, $program)['decision']);

	// Every key is documented with a sample, and the event is a known one.
	$documented = api_webhook_event_payloads();

	foreach (array('application.scored' => array('total' => 7.5, 'reviews_sent' => 2), 'application.decided' => array('previous_decision' => 'none')) as $event => $extra) {
		pg_assert_true(api_webhook_event_is_valid($event), $event);
		pg_assert_same(array_keys($documented[$event]['fields']), array_keys(pg_application_event_payload($row, $program) + $extra), $event);
		pg_assert_true(in_array($event, pg_event_workspace_events(), true), $event . ' is kept for the Workspace');
	}
}

function test_application_report_event_names_the_submission()
{
	pg_assert_same(array('form', 2210), ws_events_record_of('application.scored', array('id' => 2210, 'form_id' => 14, 'entry_id' => 88)));
	pg_assert_same(array('form', 2210), ws_events_record_of('application.decided', array('id' => 2210, 'form_id' => 14, 'entry_id' => 88)));
	pg_assert_same(array('reference_code', 'program_name', 'category', 'round', 'decision', 'previous_decision', 'score_raw'), array_column(ws_flow_event_outputs()['application.decided'], 'key'));
}

function test_application_report_api_presenters()
{
	$program = array('id' => 3, 'name' => 'Young artists', 'page_id' => 14, 'status' => 1, 'opens_at' => 0, 'closes_at' => 1800000000, 'round' => 0, 'blind' => 1, 'created_at' => 0, 'updated_at' => 0,
		'rounds' => array(array('criteria' => array(array('id' => 'c1', 'name' => 'Idea', 'weight' => '100')), 'reviewers' => 2, 'ends_at' => 0, 'cut' => array('mode' => 'top', 'value' => 3))));
	$out = api_application_program_present($program, 5);

	pg_assert_same(array_keys(api_application_program_schema()), array_keys($out));
	pg_assert_same('open', $out['status']);
	pg_assert_same(1, $out['round']);
	pg_assert_same(null, $out['opens_at']);
	pg_assert_same(array(array('round' => 1, 'criteria' => array(array('id' => 'c1', 'name' => 'Idea', 'weight' => 100)), 'reviewers_per_entry' => 2, 'ends_at' => null)), $out['rounds']);

	$entry = array('id' => 88, 'form_id' => 2210, 'reference_code' => 'K4M9QZ', 'category' => 'a', 'round' => 1, 'eligible' => 3, 'score_raw' => 725, 'score_adjusted' => 700, 'spread' => 0,
		'consensus' => 2, 'shortlisted' => 1, 'decision' => 4, 'decided_at' => 0, 'mailed_at' => 0, 'created_at' => 0, 'eligibility_note' => 'secret');
	$out = api_application_entry_present($entry, array(array('round' => 1, 'total' => 750, 'scores' => '{"c1":{"score":7,"note":"never"}}', 'submitted_at' => 0, 'reviewer_id' => 5)));

	pg_assert_same(array_keys(api_application_entry_schema()), array_keys($out));
	pg_assert_same(array('let_in', 7.25, 7.0, 0.0, 'settled', true, 'declined'), array($out['eligible'], $out['score_raw'], $out['score_adjusted'], $out['spread'], $out['consensus'], $out['shortlisted'], $out['decision']));
	pg_assert_same(array_keys(api_application_review_schema()), array_keys($out['reviews'][0]));
	pg_assert_same('{"c1":7}', json_encode($out['reviews'][0]['scores']), 'only the score: no note, no reviewer');

	$none = api_application_entry_present(array('score_raw' => 0, 'score_adjusted' => 0, 'spread' => 0) + $entry, array());
	pg_assert_same(array(null, null, null, array()), array($none['score_raw'], $none['score_adjusted'], $none['spread'], $none['reviews']));
}
