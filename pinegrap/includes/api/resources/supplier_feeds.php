<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Supplier feeds, read and pulled from outside.
//
// GET /supplier-feeds lists the feeds as Products > Supplier feeds does: the
// host (never the address, user, password or key, which stay in the feed's
// encrypted blob), how often it is pulled, its state and its last run.
// GET /supplier-feeds/{id}/runs lists a feed's pulls, newest first.
// POST /supplier-feeds/{id}/run is the screen's Pull now: a paused feed is
// switched on again and pulled past the mass-drop brake, the pull runs for a
// few seconds in the call and the general job goes on with the rest.
//
// The screen opens to the same people as the products scopes (role under 3
// or manage_ecommerce), so the scopes are the whole gate.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {

	exit;

}

require_once(dirname(dirname(__FILE__)) . '/list_order.php');

// Seconds a pull runs inside POST /supplier-feeds/{id}/run before the job
// takes it over: short, as an MCP call waits for it.
define('API_SUPPLIER_FEED_RUN_BUDGET', 8);

// The feed's stored status by name.
function api_supplier_feed_statuses() {

	return array(0 => 'off', 1 => 'on', 2 => 'paused');

}

// A run's stored status and kind by name.
function api_supplier_feed_run_statuses() {

	return array(0 => 'running', 1 => 'ok', 2 => 'stopped', 3 => 'failed');

}

function api_supplier_feed_run_kinds() {

	return array(0 => 'scheduled', 1 => 'by_hand');

}

// The helpers of the feed screen, after the gate every route shares: a site
// whose database has not been updated answers 503.
function api_supplier_feed_load() {

	require_once(PG_FUNCTIONS_DIR . '/includes/fn/supplier_feed_screen.php');

	if (!pg_supplier_feed_ready()) {

		api_fail(503, 'service_unavailable', lang('The database has not been upgraded yet. Run the software update first.'));

	}

}

// The feed a call names, or 404.
function api_supplier_feed_find($id) {

	$feed = pg_supplier_feed_get((int)$id);

	if ($feed === null) {

		api_fail_not_found(lang('Supplier feed'));

	}

	return $feed;

}

// One run as both the list and the feed's last_run carry it. The detail's
// reason is a brake's or a failure's; failed_lines are the first lines that
// could not be written (at most 500 are kept), each with its reason.
function api_supplier_feed_run_present($run) {

	$statuses = api_supplier_feed_run_statuses();

	$kinds = api_supplier_feed_run_kinds();

	$detail = json_decode((string)$run['detail'], true);

	$reason = pg_supplier_feed_detail_reason($run['detail']);

	$lines = array();

	foreach ((is_array($detail) && isset($detail['failed']) && is_array($detail['failed'])) ? $detail['failed'] : array() as $line) {

		$lines[] = api_supplier_feed_run_line_present($line);

	}

	return array(
		'id'           => (int)$run['id'],
		'feed_id'      => (int)$run['feed_id'],
		'kind'         => isset($kinds[(int)$run['kind']]) ? $kinds[(int)$run['kind']] : 'scheduled',
		'status'       => isset($statuses[(int)$run['status']]) ? $statuses[(int)$run['status']] : 'failed',
		'started_at'   => api_time($run['started_at']),
		'ended_at'     => api_time($run['ended_at']),
		'records'      => (int)$run['records'],
		'added'        => (int)$run['added'],
		'changed'      => (int)$run['changed'],
		'closed'       => (int)$run['closed'],
		'reopened'     => (int)$run['reopened'],
		'held'         => (int)$run['held'],
		'failed'       => (int)$run['failed'],
		'reason'       => ($reason !== '') ? $reason : null,
		'failed_lines' => $lines
	);

}

// One failed line of a run: the record's line and why it was not written.
function api_supplier_feed_run_line_present($line) {

	return array(
		'line'   => (string)($line['line'] ?? ''),
		'reason' => (string)($line['reason'] ?? '')
	);

}

// One feed as the list answers it. Only the host of its address is given.
function api_supplier_feed_present($feed, $run, $matched) {

	$statuses = api_supplier_feed_statuses();

	$format = (string)$feed['format'];

	return array(
		'id'                 => (int)$feed['id'],
		'name'               => (string)$feed['name'],
		'host'               => (string)$feed['host'],
		'format'             => in_array($format, array('xml', 'csv', 'json'), true) ? $format : 'auto',
		'interval_hours'     => (int)$feed['interval_hours'],
		'currency'           => ((string)$feed['currency'] !== '') ? strtoupper((string)$feed['currency']) : null,
		'prices_include_vat' => !empty($feed['vat_included']),
		'erp_account_id'     => ((int)$feed['erp_account_id'] > 0) ? (int)$feed['erp_account_id'] : null,
		'status'             => isset($statuses[(int)$feed['status']]) ? $statuses[(int)$feed['status']] : 'off',
		'pause_reason'       => ((string)$feed['pause_reason'] !== '') ? (string)$feed['pause_reason'] : null,
		'pulling'            => trim((string)$feed['state']) !== '',
		'matched_products'   => (int)$matched,
		'next_run_at'        => ((int)$feed['status'] === 1) ? api_time($feed['next_run_at']) : null,
		'last_run_at'        => api_time($feed['last_run_at']),
		'last_run'           => is_array($run) ? api_supplier_feed_run_present($run) : null,
		'created_at'         => api_time($feed['created_at'])
	);

}

// The newest run of each of these feeds, one query.
function api_supplier_feed_last_runs($feed_ids) {

	$ids = array_values(array_filter(array_map('intval', (array)$feed_ids)));

	if (empty($ids)) {

		return array();

	}

	$runs = array();

	foreach ((array)api_rows("SELECT r.* FROM supplier_feed_runs AS r
		INNER JOIN (SELECT feed_id, MAX(id) AS id FROM supplier_feed_runs
			WHERE feed_id IN (" . implode(',', $ids) . ") GROUP BY feed_id) AS last ON last.id = r.id") as $run) {

		$runs[(int)$run['feed_id']] = $run;

	}

	return $runs;

}

// GET /supplier-feeds
//
// One page, by name: a store keeps a handful of feeds.
function api_supplier_feeds_list($params) {

	api_supplier_feed_load();

	$feeds = (array)api_rows("SELECT supplier_feeds.*,
			(SELECT COUNT(DISTINCT supplier_feed_items.product_id) FROM supplier_feed_items
				WHERE supplier_feed_items.feed_id = supplier_feeds.id AND supplier_feed_items.product_id > 0) AS matched
		FROM supplier_feeds
		ORDER BY supplier_feeds.name, supplier_feeds.id");

	$runs = api_supplier_feed_last_runs(array_column($feeds, 'id'));

	$out = array();

	foreach ($feeds as $feed) {

		$out[] = api_supplier_feed_present($feed, $runs[(int)$feed['id']] ?? null, (int)$feed['matched']);

	}

	api_ok_list($out, max(1, count($out)));

}

// GET /supplier-feeds/{id}/runs
//
// Newest first by default, paged by run id. Runs are kept 90 days.
function api_supplier_feeds_runs($params) {

	api_supplier_feed_load();

	$feed = api_supplier_feed_find($params['id']);

	$descending = !isset($params['sort']) || ($params['sort'] !== 'oldest');

	$cursor = api_list_cursor($params, $descending);

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	$rows = (array)api_rows("SELECT * FROM supplier_feed_runs
		WHERE feed_id = '" . (int)$feed['id'] . "'"
		. (($cursor !== null) ? " AND " . api_list_keyset_sql('', 'id', $cursor, $descending) : '') . "
		ORDER BY " . api_list_order_sql('', 'id', $descending) . "
		LIMIT " . ($limit + 1));

	$next_cursor = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next_cursor = api_list_cursor_encode((int)$last['id'], (int)$last['id'], $descending);

	}

	api_ok_list(array_map('api_supplier_feed_run_present', $rows), $limit, $next_cursor);

}

// POST /supplier-feeds/{id}/run
//
// The screen's Pull now. A feed switched off, or one whose fields were never
// confirmed, is refused with 409; a feed already pulling answers 409
// already_pulling. Answers the feed after the call's share of the pull.
function api_supplier_feeds_run($params) {

	api_supplier_feed_load();

	$feed = api_supplier_feed_find($params['id']);

	if ((int)$feed['status'] === 0) {

		api_fail(409, 'feed_off', lang('This feed is switched off. Switch it on on Products > Supplier feeds first.'));

	}

	$mapping = pg_supplier_feed_mapping_decode($feed['mapping']);

	if (empty($mapping['confirmed'])) {

		api_fail(409, 'not_mapped', lang('The fields of this feed have not been confirmed yet.'));

	}

	if (trim((string)$feed['state']) !== '') {

		api_fail(409, 'already_pulling', lang('This feed is being pulled. Try again when the pull has ended.'));

	}

	api_dry_run_stop('started', 'supplier_feed_run', array('id' => (int)$feed['id']));

	$pull = pg_supplier_feed_pull_now($feed, API_SUPPLIER_FEED_RUN_BUDGET);

	if ($pull['result'] === 'locked') {

		api_fail(409, 'already_pulling', lang('This feed is being pulled. Try again when the pull has ended.'));

	}

	$feed = api_supplier_feed_find($feed['id']);

	api_ok(api_supplier_feed_present($feed, is_array($pull['run']) ? $pull['run'] : null, pg_supplier_feed_matched_count($feed['id'])));

}

// What api_supplier_feed_present() returns, declared for the OpenAPI
// document.
function api_supplier_feed_schema() {

	return array(
		'id'                 => 'integer',
		'name'               => 'string',
		'host'               => 'string',
		'format'             => 'string',
		'interval_hours'     => 'integer',
		'currency'           => 'string?',
		'prices_include_vat' => 'boolean',
		'erp_account_id'     => 'integer?',
		'status'             => 'string',
		'pause_reason'       => 'string?',
		'pulling'            => 'boolean',
		'matched_products'   => 'integer',
		'next_run_at'        => 'datetime?',
		'last_run_at'        => 'datetime?',
		'last_run'           => 'SupplierFeedRun?',
		'created_at'         => 'datetime?'
	);

}

// What api_supplier_feed_run_present() returns.
function api_supplier_feed_run_schema() {

	return array(
		'id'           => 'integer',
		'feed_id'      => 'integer',
		'kind'         => 'string',
		'status'       => 'string',
		'started_at'   => 'datetime?',
		'ended_at'     => 'datetime?',
		'records'      => 'integer',
		'added'        => 'integer',
		'changed'      => 'integer',
		'closed'       => 'integer',
		'reopened'     => 'integer',
		'held'         => 'integer',
		'failed'       => 'integer',
		'reason'       => 'string?',
		'failed_lines' => 'SupplierFeedRunLine[]'
	);

}

// One line of failed_lines: the record's line and why it was not written.
function api_supplier_feed_run_line_schema() {

	return array(
		'line'   => 'string',
		'reason' => 'string'
	);

}
