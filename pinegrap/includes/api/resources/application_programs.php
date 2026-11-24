<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * GET /application-programs and GET /application-programs/{id}/entries: a
 * form's application programmes with their rounds and criteria, and each
 * entry's state, combined scores, decision and sent reviews. A review gives
 * its round, weighted total and criterion scores, never the reviewer or a
 * note (the line of the personal data export). An owner with the user role
 * reaches only programmes on forms in folders it may edit, as on the panel's
 * programme screens.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {

	exit;

}

// The word of a stored code, the list's first outside it. Pure.
function api_application_word($code, array $words) {

	return isset($words[(int)$code]) ? $words[(int)$code] : $words[0];

}

// A stored score (times 100) as a number, null when nothing was combined:
// a mean is never below 1, so 0 is "no score yet". Pure.
function api_application_score($stored, $scored = null) {

	$scored = ($scored === null) ? ((int)$stored > 0) : (bool)$scored;

	return $scored ? round(((int)$stored) / 100, 2) : null;

}

// What GET /application-programs gives for one programme row. Pure.
function api_application_program_present(array $program, $entry_count) {

	$rounds = array();

	foreach ((array)$program['rounds'] as $index => $round) {

		$criteria = array();

		foreach ((array)(isset($round['criteria']) ? $round['criteria'] : array()) as $criterion) {

			$criteria[] = array('id' => (string)$criterion['id'], 'name' => (string)$criterion['name'], 'weight' => (int)$criterion['weight']);

		}

		$rounds[] = array(
			'round'               => $index + 1,
			'criteria'            => $criteria,
			'reviewers_per_entry' => (int)(isset($round['reviewers']) ? $round['reviewers'] : 0),
			'ends_at'             => api_time(isset($round['ends_at']) ? $round['ends_at'] : 0)
		);

	}

	return array(
		'id'          => (int)$program['id'],
		'name'        => (string)$program['name'],
		'form_id'     => (int)$program['page_id'],
		'status'      => api_application_word($program['status'], array('draft', 'open', 'closed')),
		'opens_at'    => api_time($program['opens_at']),
		'closes_at'   => api_time($program['closes_at']),
		'round'       => max(1, (int)$program['round']),
		'rounds'      => $rounds,
		'blind'       => ((int)$program['blind'] === 1),
		'entry_count' => (int)$entry_count,
		'created_at'  => api_time($program['created_at']),
		'updated_at'  => api_time($program['updated_at'])
	);

}

// What api_application_program_present() returns.
function api_application_program_schema() {

	return array(
		'id'          => 'integer',
		'name'        => 'string',
		'form_id'     => 'integer',
		'status'      => 'string',
		'opens_at'    => 'datetime?',
		'closes_at'   => 'datetime?',
		'round'       => 'integer',
		'rounds'      => 'ApplicationRound[]',
		'blind'       => 'boolean',
		'entry_count' => 'integer',
		'created_at'  => 'datetime?',
		'updated_at'  => 'datetime?'
	);

}

// One round of a programme.
function api_application_round_schema() {

	return array(
		'round'               => 'integer',
		'criteria'            => 'ApplicationCriterion[]',
		'reviewers_per_entry' => 'integer',
		'ends_at'             => 'datetime?'
	);

}

// One criterion of a round.
function api_application_criterion_schema() {

	return array(
		'id'     => 'string',
		'name'   => 'string',
		'weight' => 'integer'
	);

}

// What GET /application-programs/{id}/entries gives for one entry, with its
// sent reviews (application_reviews rows of status 2). Pure.
function api_application_entry_present(array $entry, array $reviews) {

	$sent = array();

	foreach ($reviews as $review) {

		$export = pg_application_export_review($review);
		$sent[] = array(
			'round'        => $export['round'],
			'total'        => $export['total'],
			'scores'       => (object)$export['scores'],
			'submitted_at' => api_time(isset($review['submitted_at']) ? $review['submitted_at'] : 0)
		);

	}

	$scored = ((int)$entry['score_raw'] > 0);

	return array(
		'id'             => (int)$entry['id'],
		'submission_id'  => (int)$entry['form_id'],
		'reference_code' => (string)$entry['reference_code'],
		'category'       => (string)$entry['category'],
		'round'          => (int)$entry['round'],
		'eligible'       => api_application_word($entry['eligible'], array('unchecked', 'yes', 'no', 'let_in')),
		'score_raw'      => api_application_score($entry['score_raw']),
		'score_adjusted' => api_application_score($entry['score_adjusted'], $scored),
		'spread'         => api_application_score($entry['spread'], $scored),
		'consensus'      => api_application_word($entry['consensus'], array('none', 'needed', 'settled')),
		'shortlisted'    => ((int)$entry['shortlisted'] === 1),
		'decision'       => api_application_word($entry['decision'], array('none', 'accepted', 'rejected', 'waitlisted', 'declined')),
		'decided_at'     => api_time($entry['decided_at']),
		'mailed_at'      => api_time($entry['mailed_at']),
		'reviews'        => $sent,
		'created_at'     => api_time($entry['created_at'])
	);

}

// What api_application_entry_present() returns.
function api_application_entry_schema() {

	return array(
		'id'             => 'integer',
		'submission_id'  => 'integer',
		'reference_code' => 'string',
		'category'       => 'string',
		'round'          => 'integer',
		'eligible'       => 'string',
		'score_raw'      => 'number?',
		'score_adjusted' => 'number?',
		'spread'         => 'number?',
		'consensus'      => 'string',
		'shortlisted'    => 'boolean',
		'decision'       => 'string',
		'decided_at'     => 'datetime?',
		'mailed_at'      => 'datetime?',
		'reviews'        => 'ApplicationReview[]',
		'created_at'     => 'datetime?'
	);

}

// One sent review of an entry: scores is criterion id => whole score 1-10.
function api_application_review_schema() {

	return array(
		'round'        => 'integer',
		'total'        => 'number',
		'scores'       => 'object',
		'submitted_at' => 'datetime?'
	);

}

// 503 before the database update that made the programme tables.
function api_application_ready() {

	if (!function_exists('pg_application_program_ready') || !pg_application_program_ready()) {

		api_fail(503, 'service_unavailable', lang('The database has not been upgraded yet. Run the software update first.'));

	}

}

// The programme of the path when the owner reaches its form, else 404.
function api_application_program_reached($id) {

	$app = api_current_app();
	$reach = api_form_owner_filter($app['owner'], 'application_programs.page_id');
	$found = (int)api_value("SELECT id FROM application_programs WHERE id = '" . (int)$id . "'" . (($reach !== '') ? ' AND ' . $reach : ''));
	$program = ($found > 0) ? pg_application_program_get($found) : null;

	if ($program === null) {

		api_fail_not_found(lang('Application programme'));

	}

	return $program;

}

function api_application_programs_list($params) {

	api_application_ready();

	$app = api_current_app();
	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;
	$where = array('1 = 1');
	$reach = api_form_owner_filter($app['owner'], 'application_programs.page_id');

	if ($reach !== '') {

		$where[] = $reach;

	}

	if (isset($params['form_id'])) {

		$where[] = "application_programs.page_id = '" . (int)$params['form_id'] . "'";

	}

	if (isset($params['status'])) {

		$where[] = "application_programs.status = '" . (int)array_search($params['status'], array('draft', 'open', 'closed'), true) . "'";

	}

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "application_programs.id > '" . (int)$cursor['i'] . "'";

	}

	$rows = api_rows("SELECT application_programs.*,
			(SELECT COUNT(*) FROM application_entries WHERE application_entries.program_id = application_programs.id) AS entry_count
		FROM application_programs
		WHERE " . implode(' AND ', $where) . "
		ORDER BY application_programs.id ASC
		LIMIT " . ($limit + 1));
	$has_more = (count($rows) > $limit);

	if ($has_more) {

		array_pop($rows);

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_application_program_present(pg_application_program_decode($row), $row['entry_count']);

	}

	$last = end($rows);

	api_ok_list($out, $limit, ($has_more && $last) ? api_cursor_encode(0, $last['id']) : null);

}

function api_application_program_entries($params) {

	api_application_ready();

	$program = api_application_program_reached($params['id']);
	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;
	$where = array("application_entries.program_id = '" . (int)$program['id'] . "'");

	if (isset($params['round'])) {

		$where[] = "application_entries.round = '" . (int)$params['round'] . "'";

	}

	if (isset($params['category'])) {

		$where[] = "application_entries.category = '" . escape($params['category']) . "'";

	}

	if (isset($params['decision'])) {

		$where[] = "application_entries.decision = '" . (int)array_search($params['decision'], array('none', 'accepted', 'rejected', 'waitlisted', 'declined'), true) . "'";

	}

	if (isset($params['shortlisted'])) {

		$where[] = "application_entries.shortlisted = '" . ($params['shortlisted'] ? 1 : 0) . "'";

	}

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "application_entries.id > '" . (int)$cursor['i'] . "'";

	}

	$rows = api_rows("SELECT application_entries.*, forms.reference_code
		FROM application_entries
		LEFT JOIN forms ON forms.id = application_entries.form_id
		WHERE " . implode(' AND ', $where) . "
		ORDER BY application_entries.id ASC
		LIMIT " . ($limit + 1));
	$has_more = (count($rows) > $limit);

	if ($has_more) {

		array_pop($rows);

	}

	// The page's sent reviews in one query.
	$reviews = array();
	$ids = array();

	foreach ($rows as $row) {

		$ids[] = (int)$row['id'];

	}

	if ($ids) {

		foreach (api_rows("SELECT entry_id, round, total, scores, submitted_at FROM application_reviews WHERE status = 2 AND entry_id IN (" . implode(',', $ids) . ") ORDER BY round, submitted_at, id") as $review) {

			$reviews[(int)$review['entry_id']][] = $review;

		}

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_application_entry_present($row, isset($reviews[(int)$row['id']]) ? $reviews[(int)$row['id']] : array());

	}

	$last = end($rows);

	api_ok_list($out, $limit, ($has_more && $last) ? api_cursor_encode(0, $last['id']) : null);

}
