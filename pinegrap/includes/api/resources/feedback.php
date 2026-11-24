<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The satisfaction surveys over the API: the sent surveys with their answers
// (GET /feedback/responses) and the figures of Communication > Surveys >
// Results (GET /feedback/summary). Read only. The scope is customers:read,
// the scope of the contacts the Results screen is opened with. A staff
// member's figures are given to the manager role and up, as on the screen;
// for the user role a contact outside its contact groups answers contact_id
// null and staff_user_id is never given.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/feedback_report.php');

// The kinds and bands as words, by their stored numbers.
function api_feedback_kind_words() {

	return array(PG_FEEDBACK_NPS => 'nps', PG_FEEDBACK_CSAT => 'csat', PG_FEEDBACK_EFFORT => 'effort');

}

function api_feedback_band_words() {

	return array(PG_FEEDBACK_BAND_LOW => 'low', PG_FEEDBACK_BAND_MIDDLE => 'middle', PG_FEEDBACK_BAND_HIGH => 'high');

}

// The status filter of GET /feedback/responses as stored numbers: queued
// and cancelled rows were never sent and are not listed.
function api_feedback_status_codes($word) {

	$map = array(
		'answered'  => array(PG_FEEDBACK_ANSWERED),
		'waiting'   => array(PG_FEEDBACK_SENT),
		'no_answer' => array(PG_FEEDBACK_NO_ANSWER),
		'all'       => array(PG_FEEDBACK_SENT, PG_FEEDBACK_ANSWERED, PG_FEEDBACK_NO_ANSWER)
	);

	return isset($map[$word]) ? $map[$word] : $map['answered'];

}

// The query of a page of GET /feedback/responses, newest first by id.
// $filters: status (word), kind (word), survey_id, band (word), touchpoint,
// contact_id, since (unix, sent at or after), before_id (the cursor),
// limit, contact_rule (SQL marking a contact in reach, '' for every one).
// Pure.
function api_feedback_responses_query(array $filters) {

	$codes = api_feedback_status_codes(isset($filters['status']) ? (string)$filters['status'] : 'answered');

	$rule = isset($filters['contact_rule']) ? (string)$filters['contact_rule'] : '';

	$sql = "SELECT r.id, r.survey_id, s.name AS survey, s.kind, s.touchpoint, r.contact_id, r.record_type, r.record_id,
			r.subject_id, r.staff_user_id, r.channel, r.status, r.sent_at, r.reminded_at, r.answered_at, r.score, r.band,
			r.comment, r.answers, r.stopped, r.owner_user_id, r.task_id, r.escalated_at, r.outcome, "
		. (($rule !== '') ? '(' . $rule . ')' : '1') . " AS contact_ok
		FROM feedback_responses r INNER JOIN feedback_surveys s ON s.id = r.survey_id
		WHERE r.status IN (" . implode(', ', array_map('intval', $codes)) . ")";

	$values = array();

	$kinds = array_flip(api_feedback_kind_words());

	if (!empty($filters['kind']) && isset($kinds[$filters['kind']])) {

		$sql .= ' AND s.kind = ?';

		$values[] = (int)$kinds[$filters['kind']];

	}

	$bands = array_flip(api_feedback_band_words());

	if (!empty($filters['band']) && isset($bands[$filters['band']])) {

		// A band is the answer's, so only an answered row has one.
		$sql .= ' AND r.band = ? AND r.answered_at > 0';

		$values[] = (int)$bands[$filters['band']];

	}

	// One contact's answers are listed only when the caller may open it.
	if (!empty($filters['contact_id']) && ($rule !== '')) {

		$sql .= ' AND ' . $rule;

	}

	foreach (array('survey_id' => 'r.survey_id', 'contact_id' => 'r.contact_id') as $key => $column) {

		if (!empty($filters[$key])) {

			$sql .= ' AND ' . $column . ' = ?';

			$values[] = (int)$filters[$key];

		}

	}

	if (!empty($filters['touchpoint'])) {

		$sql .= ' AND s.touchpoint = ?';

		$values[] = (string)$filters['touchpoint'];

	}

	if (!empty($filters['since'])) {

		$sql .= ' AND r.sent_at >= ?';

		$values[] = (int)$filters['since'];

	}

	if (!empty($filters['before_id'])) {

		$sql .= ' AND r.id < ?';

		$values[] = (int)$filters['before_id'];

	}

	$sql .= ' ORDER BY r.id DESC LIMIT ' . ((int)$filters['limit'] + 1);

	return array($sql, $values);

}

// GET /feedback/responses
function api_feedback_responses($params) {

	$limit = (int)$params['limit'];

	$owner = api_current_app()['owner'];

	$manager = ((int)$owner['role'] <= 2);

	$filters = array(
		'status'       => isset($params['status']) ? (string)$params['status'] : 'answered',
		'kind'         => isset($params['kind']) ? (string)$params['kind'] : '',
		'band'         => isset($params['band']) ? (string)$params['band'] : '',
		'survey_id'    => isset($params['survey_id']) ? (int)$params['survey_id'] : 0,
		'contact_id'   => isset($params['contact_id']) ? (int)$params['contact_id'] : 0,
		'touchpoint'   => isset($params['touchpoint']) ? (string)$params['touchpoint'] : '',
		'since'        => isset($params['since']) ? (int)$params['since'] : 0,
		'before_id'    => 0,
		'limit'        => $limit,
		'contact_rule' => $manager ? '' : pg_contact_owner_sql((int)$owner['id'], 'r.contact_id')
	);

	if (isset($params['cursor']) && ($params['cursor'] !== '')) {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$filters['before_id'] = (int)$cursor['i'];

	}

	// Before the update that adds the survey tables there is nothing to list.
	if (!pg_feedback_surveys_ready()) {

		api_ok_list(array(), $limit, null);

	}

	list($sql, $values) = api_feedback_responses_query($filters);

	$rows = array();

	$result = db_q($sql, $values);

	while ($result && ($row = mysqli_fetch_assoc($result))) {

		$rows[] = $row;

	}

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$next = api_cursor_encode(0, (int)$rows[count($rows) - 1]['id']);

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_feedback_response_present($row, $manager);

	}

	api_ok_list($out, $limit, $next);

}

// One sent survey of GET /feedback/responses. Pure: $row is a row of
// api_feedback_responses_query(); $manager false (the user role) leaves out
// the staff member and a contact outside the caller's groups (contact_ok).
function api_feedback_response_present($row, $manager) {

	$answered = ((int)$row['answered_at'] > 0);

	$kinds = api_feedback_kind_words();

	$bands = api_feedback_band_words();

	$statuses = array(PG_FEEDBACK_SENT => 'waiting', PG_FEEDBACK_ANSWERED => 'answered', PG_FEEDBACK_NO_ANSWER => 'no_answer');

	$outcomes = array(1 => 'called', 2 => 'solved', 3 => 'not_reached');

	$answers = null;

	if ($answered && is_string($row['answers']) && ($row['answers'] !== '')) {

		$decoded = json_decode($row['answers'], true);

		$answers = is_array($decoded) ? $decoded : null;

	}

	$comment = $answered ? trim((string)$row['comment']) : '';

	$contact = ((int)$row['contact_id'] > 0) && !empty($row['contact_ok']);

	$id = function ($value) {

		return ((int)$value > 0) ? (int)$value : null;

	};

	return array(
		'id'            => (int)$row['id'],
		'survey_id'     => (int)$row['survey_id'],
		'survey'        => (string)$row['survey'],
		'kind'          => isset($kinds[(int)$row['kind']]) ? $kinds[(int)$row['kind']] : '',
		'touchpoint'    => (string)$row['touchpoint'],
		'channel'       => (string)$row['channel'],
		'status'        => isset($statuses[(int)$row['status']]) ? $statuses[(int)$row['status']] : '',
		'contact_id'    => $contact ? (int)$row['contact_id'] : null,
		'record_type'   => ((string)$row['record_type'] !== '') ? (string)$row['record_type'] : null,
		'record_id'     => $id($row['record_id']),
		'subject_id'    => $id($row['subject_id']),
		'staff_user_id' => $manager ? $id($row['staff_user_id']) : null,
		'sent_at'       => api_time($row['sent_at']),
		'reminded_at'   => api_time($row['reminded_at']),
		'answered_at'   => api_time($row['answered_at']),
		'score'         => $answered ? (int)$row['score'] : null,
		'band'          => ($answered && isset($bands[(int)$row['band']])) ? $bands[(int)$row['band']] : null,
		'comment'       => ($comment !== '') ? $comment : null,
		'answers'       => $answers,
		'stopped'       => ((int)$row['stopped'] === 1),
		'owner_user_id' => $id($row['owner_user_id']),
		'task_id'       => $id($row['task_id']),
		'escalated_at'  => api_time($row['escalated_at']),
		'outcome'       => isset($outcomes[(int)$row['outcome']]) ? $outcomes[(int)$row['outcome']] : null
	);

}

// What api_feedback_response_present() answers with, declared for the
// OpenAPI document.
function api_feedback_response_schema() {

	return array(
		'id'            => 'integer',
		'survey_id'     => 'integer',
		'survey'        => 'string',
		'kind'          => 'string',
		'touchpoint'    => 'string',
		'channel'       => 'string',
		'status'        => 'string',
		'contact_id'    => 'integer?',
		'record_type'   => 'string?',
		'record_id'     => 'integer?',
		'subject_id'    => 'integer?',
		'staff_user_id' => 'integer?',
		'sent_at'       => 'datetime?',
		'reminded_at'   => 'datetime?',
		'answered_at'   => 'datetime?',
		'score'         => 'integer?',
		'band'          => 'string?',
		'comment'       => 'string?',
		'answers'       => 'object?',
		'stopped'       => 'boolean',
		'owner_user_id' => 'integer?',
		'task_id'       => 'integer?',
		'escalated_at'  => 'datetime?',
		'outcome'       => 'string?'
	);

}

// GET /feedback/summary
function api_feedback_summary($params) {

	$now = time();

	$owner = api_current_app()['owner'];

	$manager = ((int)$owner['role'] <= 2);

	$month = isset($params['month']) ? (string)$params['month'] : '';

	if (($month !== '') && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {

		api_fail_validation(lang('month must be a month written as YYYY-MM.'), 'month');

	}

	$group = isset($params['group']) ? (string)$params['group'] : 'touchpoint';

	if (!isset(pg_feedback_report_groupings($manager)[$group])) {

		api_fail(403, 'forbidden', lang('A staff member\'s figures are given to the manager role and up.'), 'group');

	}

	$touchpoint = isset($params['touchpoint']) ? (string)$params['touchpoint'] : '';

	$month = pg_feedback_report_month($month, $now);

	$year = (isset($params['period']) && ($params['period'] === 'year'));

	$months = pg_feedback_report_months($month, $year);

	$kinds = array_flip(api_feedback_kind_words());

	$kind = isset($params['kind'], $kinds[$params['kind']]) ? (int)$kinds[$params['kind']] : PG_FEEDBACK_NPS;

	$ready = pg_feedback_surveys_ready();

	$survey_id = isset($params['survey_id']) ? (int)$params['survey_id'] : 0;

	$scope = array('kind' => $kind, 'survey' => 0);

	$fold = pg_feedback_report_fold($kind, array());

	$open = 0;

	$labels = array();

	$words = array();

	if ($ready) {

		if ($survey_id > 0) {

			$surveys = pg_feedback_report_surveys();

			if (!isset($surveys[$survey_id])) {

				api_fail_not_found(lang('Survey'));

			}

			// A survey's own kind wins: its scores add up only with its kind.
			$scope = array('kind' => (int)$surveys[$survey_id]['kind'], 'survey' => $survey_id);

		}

		$report = pg_feedback_report_read($scope, $touchpoint, $group, $months);

		$fold = pg_feedback_report_fold($scope['kind'], $report['cells']);

		$open = $report['open'];

		if ($group !== 'month') {

			$labels = pg_feedback_report_labels($group, array_keys($fold['rows']));

		}

		$comments = pg_feedback_report_comments($scope, $touchpoint, pg_feedback_report_bounds($months), 0);

		$words = pg_feedback_words(array_column($comments, 'comment'), array_keys(pg_feedback_survey_languages())[0], PG_FEEDBACK_WORDS_MAX);

	}

	api_ok(api_feedback_summary_present(array(
		'ready'      => $ready,
		'kind'       => $scope['kind'],
		'survey_id'  => $scope['survey'],
		'touchpoint' => $touchpoint,
		'period'     => $year ? 'year' : 'month',
		'months'     => $months,
		'group'      => $group,
		'fold'       => $fold,
		'labels'     => $labels,
		'open'       => $open,
		'words'      => $words
	)));

}

// The figures of a sum (pg_feedback_sum()) as the answer gives them: the
// kind's score, the average for CSAT and effort, and the counts behind
// them. Pure.
function api_feedback_figures_present($kind, array $row) {

	$sum = isset($row['sum']) ? (array)$row['sum'] : array();

	$out = array(
		'sent'     => (int)$row['sent'],
		'answered' => (int)$row['answered'],
		'rate'     => isset($row['rate']) ? $row['rate'] : null,
		'low'      => (int)$row['low'],
		'score'    => isset($sum['score']) ? (int)$sum['score'] : null,
		'average'  => isset($sum['average']) ? (float)$sum['average'] : null
	);

	if ((int)$kind === PG_FEEDBACK_NPS) {

		$out['promoters'] = isset($sum['promoters']) ? (int)$sum['promoters'] : 0;
		$out['passives'] = isset($sum['passives']) ? (int)$sum['passives'] : 0;
		$out['detractors'] = isset($sum['detractors']) ? (int)$sum['detractors'] : 0;

	}

	return $out;

}

// The answer of GET /feedback/summary. Pure: $report holds ready, kind,
// survey_id, touchpoint, period, months, group, fold
// (pg_feedback_report_fold()), labels (key => name), open, words
// (word => comments).
function api_feedback_summary_present(array $report) {

	$kind = (int)$report['kind'];

	$kinds = api_feedback_kind_words();

	$rows = array();

	foreach ($report['fold']['rows'] as $key => $row) {

		$key = (string)$key;

		$few = pg_feedback_report_few($report['group'], $row);

		$figures = api_feedback_figures_present($kind, $row);

		if ($few) {

			// A staff member under the minimum keeps the sent and answered
			// counts only, as on the screen.
			foreach ($figures as $name => $value) {

				if (!in_array($name, array('sent', 'answered'), true)) {

					$figures[$name] = null;

				}

			}

		}

		$rows[] = array(
			'key'  => $key,
			'name' => (isset($report['labels'][$key]) && ($report['labels'][$key] !== '')) ? (string)$report['labels'][$key] : $key,
			'few'  => $few
		) + $figures;

	}

	$words = array();

	foreach ($report['words'] as $word => $count) {

		$words[] = array('word' => (string)$word, 'comments' => (int)$count);

	}

	return array(
		'ready'          => (bool)$report['ready'],
		'kind'           => isset($kinds[$kind]) ? $kinds[$kind] : '',
		'survey_id'      => ((int)$report['survey_id'] > 0) ? (int)$report['survey_id'] : null,
		'touchpoint'     => ((string)$report['touchpoint'] !== '') ? (string)$report['touchpoint'] : null,
		'period'         => (string)$report['period'],
		'from_month'     => (string)$report['months'][0],
		'to_month'       => (string)end($report['months']),
		'group'          => (string)$report['group'],
		'staff_min'      => PG_FEEDBACK_STAFF_MIN,
		'total'          => api_feedback_figures_present($kind, $report['fold']['total']),
		'open_followups' => (int)$report['open'],
		'rows'           => $rows,
		'words'          => $words
	);

}

// What api_feedback_summary_present() answers with, declared for the
// OpenAPI document.
function api_feedback_summary_schema() {

	return array(
		'ready'          => 'boolean',
		'kind'           => 'string',
		'survey_id'      => 'integer?',
		'touchpoint'     => 'string?',
		'period'         => 'string',
		'from_month'     => 'string',
		'to_month'       => 'string',
		'group'          => 'string',
		'staff_min'      => 'integer',
		'total'          => 'object',
		'open_followups' => 'integer',
		'rows'           => 'array',
		'words'          => 'array'
	);

}
