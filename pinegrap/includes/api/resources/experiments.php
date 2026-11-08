<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Page experiments: an A/B experiment of a designed page (includes/fn/
// experiments.php), its variants and the views and goals the hourly rollup
// counted for each, with the comparison the results screen shows
// (includes/fn/experiments_results.php).
//
// An application reads them and starts, pauses or ends one; setting one up,
// editing the variant draft and publishing the winner's design stay in the
// panel, where the page lock and the content gate apply. A move goes through
// the same function as the panel's buttons (pg_experiment_sheet_move()),
// which announces experiment.started and experiment.ended.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// An ISO-8601 UTC moment, null for 0.
function api_experiment_time($unix) {

	return ((int)$unix > 0) ? gmdate('Y-m-d\TH:i:s\Z', (int)$unix) : null;

}

// An experiment row (with page_name) and its variants (key => id,
// draft_page_id, variant_key, weight, page_name, views, goals). Pure.
function api_experiment_present($row, $variants) {

	$results = function_exists('pg_experiment_results_rows') ? pg_experiment_results_rows($variants) : array();

	$out_variants = array();

	$views = 0;

	$goals = 0;

	foreach ($results as $key => $variant) {

		$result = $variant['result'];

		$views += $variant['views'];

		$goals += $variant['goals'];

		$out_variants[] = array(
			'id'            => (int)$variant['id'],
			'key'           => (string)$key,
			'control'       => (bool)$variant['control'],
			'draft_page_id' => ((int)$variant['draft_page_id'] > 0) ? (int)$variant['draft_page_id'] : null,
			'page_name'     => (isset($variant['page_name']) && ((string)$variant['page_name'] !== '')) ? (string)$variant['page_name'] : null,
			'weight'        => (int)$variant['weight'],
			'views'         => (int)$variant['views'],
			'goals'         => (int)$variant['goals'],
			'rate'          => ($variant['rate'] === null) ? null : round($variant['rate'], 4),
			'lift'          => ($variant['lift'] === null) ? null : round($variant['lift'], 4),
			'p_value'       => (($result === null) || ($result['p'] === null)) ? null : round($result['p'], 4),
			'significant'   => ($result !== null) && !empty($result['significant']),
			'verdict'       => ($result === null) ? null : pg_experiment_verdict_kind($result),
			'needed_views'  => (($result === null) || ($result['needed'] === null)) ? null : (int)$result['needed'],
		);

	}

	return array(
		'id'                => (int)$row['id'],
		'page_id'           => (int)$row['page_id'],
		'page_name'         => (isset($row['page_name']) && ((string)$row['page_name'] !== '')) ? (string)$row['page_name'] : null,
		'name'              => (string)$row['name'],
		'status'            => (string)$row['status'],
		'goal_kind'         => (string)$row['goal_kind'],
		'goal_target'       => ((string)$row['goal_target'] !== '') ? (string)$row['goal_target'] : null,
		'started_at'        => api_experiment_time($row['started_at']),
		'ended_at'          => api_experiment_time($row['ended_at']),
		'winner_variant_id' => ((int)$row['winner_variant_id'] > 0) ? (int)$row['winner_variant_id'] : null,
		'created_at'        => api_experiment_time($row['created_at']),
		'updated_at'        => api_experiment_time($row['updated_at']),
		'views'             => $views,
		'goals'             => $goals,
		'variants'          => $out_variants,
	);

}

// What api_experiment_present() returns.
function api_experiment_schema() {

	return array(
		'id'                => 'integer',
		'page_id'           => 'integer',
		'page_name'         => 'string?',
		'name'              => 'string',
		'status'            => 'string',
		'goal_kind'         => 'string',
		'goal_target'       => 'string?',
		'started_at'        => 'string?',
		'ended_at'          => 'string?',
		'winner_variant_id' => 'integer?',
		'created_at'        => 'string?',
		'updated_at'        => 'string?',
		'views'             => 'integer',
		'goals'             => 'integer',
		'variants'          => 'ExperimentVariant[]',
	);

}

// One variant inside api_experiment_present().
function api_experiment_variant_schema() {

	return array(
		'id'            => 'integer',
		'key'           => 'string',
		'control'       => 'boolean',
		'draft_page_id' => 'integer?',
		'page_name'     => 'string?',
		'weight'        => 'integer',
		'views'         => 'integer',
		'goals'         => 'integer',
		'rate'          => 'number?',
		'lift'          => 'number?',
		'p_value'       => 'number?',
		'significant'   => 'boolean',
		'verdict'       => 'string?',
		'needed_views'  => 'integer?',
	);

}

// The status a move route asks for: start, pause or end. Pure.
function api_experiment_move_target($action) {

	$moves = array('start' => 'running', 'pause' => 'paused', 'end' => 'ended');

	return isset($moves[$action]) ? $moves[$action] : '';

}

// 503 until the upgrade created the tables.
function api_experiments_ready_or_fail() {

	if (!function_exists('pg_experiment_sheet_ready') || !pg_experiment_sheet_ready()) {

		api_fail(503, 'service_unavailable', lang('Experiments need the software upgrade.'));

	}

}

function api_experiment_select() {

	return "SELECT experiments.*, page.page_name
		FROM experiments
		LEFT JOIN page ON page.page_id = experiments.page_id";

}

// The variants of some experiments with their page and summed figures, by
// experiment id then key: two queries for the whole page of rows.
function api_experiment_variants_for($ids) {

	$ids = array_values(array_filter(array_map('intval', (array)$ids)));

	if (!$ids) {

		return array();

	}

	$list = implode(',', $ids);

	$sums = array();

	if (pg_schema_has('experiment_hourly')) {

		foreach (api_rows("SELECT variant_id, SUM(views) AS views, SUM(goals) AS goals FROM experiment_hourly WHERE experiment_id IN ($list) GROUP BY variant_id") as $sum) {

			$sums[(int)$sum['variant_id']] = $sum;

		}

	}

	$out = array();

	foreach (api_rows("SELECT experiment_variants.*, page.page_name
		FROM experiment_variants
		LEFT JOIN page ON page.page_id = experiment_variants.draft_page_id
		WHERE experiment_variants.experiment_id IN ($list)
		ORDER BY experiment_variants.experiment_id, experiment_variants.variant_key") as $variant) {

		$id = (int)$variant['id'];

		$variant['views'] = isset($sums[$id]) ? (int)$sums[$id]['views'] : 0;

		$variant['goals'] = isset($sums[$id]) ? (int)$sums[$id]['goals'] : 0;

		$out[(int)$variant['experiment_id']][(string)$variant['variant_key']] = $variant;

	}

	return $out;

}

function api_experiment_load_or_fail($id) {

	$row = api_row(api_experiment_select() . " WHERE experiments.id = '" . (int)$id . "' LIMIT 1");

	if (!$row) {

		api_fail_not_found(lang('Experiment'));

	}

	return $row;

}

function api_experiment_answer($row) {

	$variants = api_experiment_variants_for(array($row['id']));

	return api_experiment_present($row, isset($variants[(int)$row['id']]) ? $variants[(int)$row['id']] : array());

}

function api_experiments_list($params) {

	api_experiments_ready_or_fail();

	$where = array('1');

	if (isset($params['status'])) {

		$where[] = "experiments.status = '" . escape($params['status']) . "'";

	}

	if (isset($params['page_id'])) {

		$where[] = "experiments.page_id = '" . (int)$params['page_id'] . "'";

	}

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "experiments.id < '" . (int)$cursor['i'] . "'";

	}

	$limit = (int)$params['limit'];

	$rows = api_rows(api_experiment_select() . "
		WHERE " . implode(' AND ', $where) . "
		ORDER BY experiments.id DESC
		LIMIT " . ($limit + 1));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['id'], (int)$last['id']);

	}

	$ids = array();

	foreach ($rows as $row) {

		$ids[] = (int)$row['id'];

	}

	$variants = api_experiment_variants_for($ids);

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_experiment_present($row, isset($variants[(int)$row['id']]) ? $variants[(int)$row['id']] : array());

	}

	api_ok_list($out, $limit, $next);

}

function api_experiments_get($params) {

	api_experiments_ready_or_fail();

	api_ok(api_experiment_answer(api_experiment_load_or_fail($params['id'])));

}

// The checks of a move, as the panel's buttons make it: refused with 409
// experiment_state when the status cannot move there now or a start's checks
// fail. Writes nothing; a dry run stops after it.
function api_experiments_move_prepare($params, $action) {

	api_experiments_ready_or_fail();

	$row = api_experiment_load_or_fail($params['id']);

	$to = api_experiment_move_target($action);

	if (($to === '') || !pg_experiment_may_move($row['status'], $to)) {

		api_fail(409, 'experiment_state', lang('The experiment cannot make that change now.'));

	}

	$checked = pg_experiment_sheet_move_check($row, $to);

	if (!$checked['ok']) {

		api_fail(409, 'experiment_state', $checked['error']);

	}

	return array('row' => $row, 'to' => $to, 'detail' => array('id' => (int)$row['id'], 'status' => (string)$row['status'], 'to' => $to));

}

// The move itself, after api_experiments_move_prepare(). The status in the
// write's WHERE still refuses a move another caller made meanwhile.
function api_experiments_move_commit($move) {

	$done = pg_experiment_sheet_move($move['row'], $move['to']);

	if (!$done['ok']) {

		api_fail(409, 'experiment_state', $done['error']);

	}

	api_ok(api_experiment_answer(api_experiment_load_or_fail($move['row']['id'])));

}

function api_experiments_start($params) {

	$move = api_experiments_move_prepare($params, 'start');

	api_dry_run_stop('start', 'experiment', $move['detail']);

	api_experiments_move_commit($move);

}

function api_experiments_pause($params) {

	$move = api_experiments_move_prepare($params, 'pause');

	api_dry_run_stop('pause', 'experiment', $move['detail']);

	api_experiments_move_commit($move);

}

function api_experiments_end($params) {

	$move = api_experiments_move_prepare($params, 'end');

	api_dry_run_stop('end', 'experiment', $move['detail']);

	api_experiments_move_commit($move);

}
