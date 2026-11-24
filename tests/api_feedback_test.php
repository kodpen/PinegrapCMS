<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for GET /feedback/responses and GET /feedback/summary
 * (includes/api/resources/feedback.php): the list's query and its filters,
 * a sent survey's fields with a score only once answered, what an account
 * with the user role is not given, and the summary's figures with a staff
 * member under the minimum held back.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/feedback.php');

// The presenter's helpers live in includes/api/response.php, which loads only
// behind the API's own entry. A stand-in of the same shape.
if (!function_exists('api_time')) {
	function api_time($unix)
	{
		$unix = (int) $unix;

		return ($unix <= 0) ? null : gmdate('Y-m-d\TH:i:s\Z', $unix);
	}
}

function _api_feedback_test_row(array $more = array())
{
	return array_merge(array(
		'id' => 41, 'survey_id' => 3, 'survey' => 'After a visit', 'kind' => 1, 'touchpoint' => 'booking',
		'contact_id' => 9, 'record_type' => 'booking', 'record_id' => 120, 'subject_id' => 5, 'staff_user_id' => 7,
		'channel' => 'email', 'status' => 2, 'sent_at' => 1764000000, 'reminded_at' => 0, 'answered_at' => 1764003600,
		'score' => 0, 'band' => 1, 'comment' => ' Too slow ', 'answers' => '{"q1":"No"}', 'stopped' => 0,
		'owner_user_id' => 7, 'task_id' => 88, 'escalated_at' => 0, 'outcome' => 2, 'contact_ok' => 1,
	), $more);
}

function test_api_feedback_responses_query_filters()
{
	list($sql, $values) = api_feedback_responses_query(array('limit' => 50));
	pg_assert_true(strpos($sql, 'r.status IN (' . PG_FEEDBACK_ANSWERED . ')') !== false, 'answered by default');
	pg_assert_true(strpos($sql, 'LIMIT 51') !== false, 'one more row tells whether a page follows');
	pg_assert_true(strpos($sql, ' 1 AS contact_ok') !== false, 'every contact in reach without a rule');
	pg_assert_same(array(), $values);

	list($sql) = api_feedback_responses_query(array('status' => 'all', 'limit' => 10));
	pg_assert_true(strpos($sql, 'r.status IN (' . PG_FEEDBACK_SENT . ', ' . PG_FEEDBACK_ANSWERED . ', ' . PG_FEEDBACK_NO_ANSWER . ')') !== false, 'all is every sent row, never queued or cancelled');

	list($sql, $values) = api_feedback_responses_query(array(
		'status' => 'answered', 'kind' => 'csat', 'band' => 'low', 'survey_id' => 3, 'contact_id' => 9,
		'touchpoint' => 'form', 'since' => 1764000000, 'before_id' => 500, 'limit' => 20,
	));
	pg_assert_same(array(PG_FEEDBACK_CSAT, PG_FEEDBACK_BAND_LOW, 3, 9, 'form', 1764000000, 500), $values, 'every filter bound in order');
	pg_assert_true(strpos($sql, 'r.band = ? AND r.answered_at > 0') !== false, 'a band only of an answered row');
	pg_assert_true(strpos($sql, 'r.id < ?') !== false, 'the cursor reads older ids');

	list($sql, $values) = api_feedback_responses_query(array('kind' => 'unknown', 'band' => 'none', 'limit' => 5));
	pg_assert_same(array(), $values, 'an unknown word filters nothing');
}

function test_api_feedback_responses_query_user_role_contact()
{
	$rule = pg_contact_owner_sql(12, 'r.contact_id');

	list($sql) = api_feedback_responses_query(array('limit' => 5, 'contact_rule' => $rule));
	pg_assert_true(strpos($sql, '(' . $rule . ') AS contact_ok') !== false, 'the rule marks a contact in reach');
	pg_assert_true(strpos($sql, 'WHERE r.status IN (2) AND ' . $rule) === false, 'the rows are not narrowed without a contact');

	list($sql) = api_feedback_responses_query(array('limit' => 5, 'contact_id' => 9, 'contact_rule' => $rule));
	pg_assert_true(strpos($sql, ' AND ' . $rule . ' AND r.contact_id = ?') !== false, 'one contact is listed only when in reach');
}

function test_api_feedback_response_present()
{
	$out = api_feedback_response_present(_api_feedback_test_row(), true);
	pg_assert_same('nps', $out['kind']);
	pg_assert_same('answered', $out['status']);
	pg_assert_same(0, $out['score'], 'an NPS 0 is an answer');
	pg_assert_same('low', $out['band']);
	pg_assert_same('Too slow', $out['comment']);
	pg_assert_same(array('q1' => 'No'), $out['answers']);
	pg_assert_same('solved', $out['outcome']);
	pg_assert_same(7, $out['staff_user_id']);
	pg_assert_same(9, $out['contact_id']);
	pg_assert_same(null, $out['reminded_at'], 'a zero time is null');
	pg_assert_same(false, $out['stopped']);
	pg_assert_same(array_keys(api_feedback_response_schema()), array_keys($out), 'the declared fields in order');

	$out = api_feedback_response_present(_api_feedback_test_row(array('status' => 1, 'answered_at' => 0, 'band' => 0, 'comment' => null, 'answers' => null, 'outcome' => 0, 'task_id' => 0)), true);
	pg_assert_same('waiting', $out['status']);
	pg_assert_same(null, $out['score'], 'no score before an answer');
	pg_assert_same(null, $out['band']);
	pg_assert_same(null, $out['comment']);
	pg_assert_same(null, $out['outcome']);
	pg_assert_same(null, $out['task_id']);
}

function test_api_feedback_response_present_user_role()
{
	$out = api_feedback_response_present(_api_feedback_test_row(array('contact_ok' => 0)), false);
	pg_assert_same(null, $out['staff_user_id'], 'no staff member below the manager role');
	pg_assert_same(null, $out['contact_id'], 'a contact outside the groups is not named');

	$out = api_feedback_response_present(_api_feedback_test_row(array('contact_ok' => '1')), false);
	pg_assert_same(9, $out['contact_id'], 'a contact in reach is named');
}

function test_api_feedback_summary_present()
{
	$fold = pg_feedback_report_fold(PG_FEEDBACK_NPS, array(
		array('k' => '7', 's' => 10, 'a' => 1, 'n' => 3),
		array('k' => '7', 's' => 3, 'a' => 1, 'n' => 1),
		array('k' => '8', 's' => 10, 'a' => 1, 'n' => 6),
		array('k' => '8', 's' => 0, 'a' => 0, 'n' => 2),
	));

	$out = api_feedback_summary_present(array(
		'ready' => true, 'kind' => PG_FEEDBACK_NPS, 'survey_id' => 0, 'touchpoint' => '', 'period' => 'year',
		'months' => array('2025-12', '2026-01', '2026-11'), 'group' => 'staff', 'fold' => $fold,
		'labels' => array('8' => 'ayse'), 'open' => 2, 'words' => array('slow' => 4, 'kind' => 2),
	));

	pg_assert_same(array_keys(api_feedback_summary_schema()), array_keys($out), 'the declared fields in order');
	pg_assert_same('nps', $out['kind']);
	pg_assert_same(null, $out['survey_id']);
	pg_assert_same(null, $out['touchpoint']);
	pg_assert_same('2025-12', $out['from_month']);
	pg_assert_same('2026-11', $out['to_month']);
	pg_assert_same(12, $out['total']['sent']);
	pg_assert_same(10, $out['total']['answered']);
	pg_assert_same(80, $out['total']['score'], 'nine promoters and one detractor of ten');
	pg_assert_same(1, $out['total']['detractors']);
	pg_assert_same(2, $out['open_followups']);

	pg_assert_same('7', $out['rows'][0]['key']);
	pg_assert_same('7', $out['rows'][0]['name'], 'a key without a name reads as itself');
	pg_assert_same(true, $out['rows'][0]['few'], 'four answers are under the minimum');
	pg_assert_same(4, $out['rows'][0]['answered']);
	pg_assert_same(null, $out['rows'][0]['score'], 'held back');
	pg_assert_same(null, $out['rows'][0]['promoters'], 'held back');

	pg_assert_same('ayse', $out['rows'][1]['name']);
	pg_assert_same(false, $out['rows'][1]['few']);
	pg_assert_same(100, $out['rows'][1]['score']);
	pg_assert_same(75, $out['rows'][1]['rate']);

	pg_assert_same(array(array('word' => 'slow', 'comments' => 4), array('word' => 'kind', 'comments' => 2)), $out['words']);
}

function test_api_feedback_summary_present_csat_has_average()
{
	$fold = pg_feedback_report_fold(PG_FEEDBACK_CSAT, array(array('k' => 'booking', 's' => 4, 'a' => 1, 'n' => 1), array('k' => 'booking', 's' => 5, 'a' => 1, 'n' => 1)));

	$out = api_feedback_summary_present(array(
		'ready' => true, 'kind' => PG_FEEDBACK_CSAT, 'survey_id' => 3, 'touchpoint' => 'booking', 'period' => 'month',
		'months' => array('2026-11'), 'group' => 'touchpoint', 'fold' => $fold, 'labels' => array(), 'open' => 0, 'words' => array(),
	));

	pg_assert_same('csat', $out['kind']);
	pg_assert_same(3, $out['survey_id']);
	pg_assert_same(100, $out['total']['score']);
	pg_assert_same(4.5, $out['total']['average']);
	pg_assert_true(!isset($out['total']['promoters']), 'promoters only for NPS');
	pg_assert_same('2026-11', $out['from_month']);
	pg_assert_same('2026-11', $out['to_month']);
}

function test_api_feedback_routes_declared()
{
	$routes = array();

	foreach (api_schema() as $route) {
		$routes[$route['id']] = $route;
	}

	foreach (array('feedback.responses', 'feedback.summary') as $id) {
		pg_assert_true(isset($routes[$id]), $id . ' is declared');
		pg_assert_same('customers:read', $routes[$id]['scope'], $id . ' reads with the contacts scope');
		pg_assert_same('GET', $routes[$id]['method']);
		pg_assert_true(empty($routes[$id]['moves_money']));
	}
}
