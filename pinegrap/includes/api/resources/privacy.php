<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The Privacy centre over the API, read only: the personal data inventory
// with its transfers (includes/fn/privacy_inventory.php) and the request box
// (includes/fn/privacy_requests.php). Every write stays on the panel, where a
// person confirms it.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {

	exit;

}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/privacy_requests.php');

/* ----- Inventory ---------------------------------------------------------- */

// Keys as answered, pure: each with its English label; a key the label list
// lacks keeps itself as its label.
function api_privacy_labelled($keys, array $labels) {

	$out = array();

	foreach ((array)$keys as $key) {

		$key = (string)$key;

		$out[] = array('key' => $key, 'label' => isset($labels[$key]) ? (string)$labels[$key] : $key);

	}

	return $out;

}

// The inventory as answered, pure: one line per catalogue table with its
// keys and labels and live counts (null when not known, never 0), and the
// transfers.
function api_privacy_inventory_present(array $inventory, array $counts, array $transfers, $site_country) {

	$categories = pg_privacy_inventory_categories();
	$subjects = pg_privacy_inventory_subjects();
	$grounds = pg_privacy_inventory_grounds();
	$retentions = pg_privacy_inventory_retentions();

	$lines = array();

	foreach ($inventory as $table => $line) {

		$count = isset($counts[$table]) ? $counts[$table] : array();
		$ground = api_privacy_labelled(array($line['ground']), $grounds);

		$lines[] = array(
			'table'      => (string)$table,
			'categories' => api_privacy_labelled($line['categories'], $categories),
			'subjects'   => api_privacy_labelled($line['subjects'], $subjects),
			'purpose'    => (string)$line['purpose'],
			'ground'     => $ground[0],
			'retention'  => api_privacy_labelled($line['retention'], $retentions),
			'records'    => isset($count['records']) ? (int)$count['records'] : null,
			'people'     => isset($count['people']) ? (int)$count['people'] : null
		);

	}

	$out = array();

	foreach ($transfers as $id => $row) {

		$out[] = array(
			'service'       => (string)$id,
			'name'          => (string)$row['name'],
			'group'         => (string)$row['group'],
			'uses'          => (string)$row['uses'],
			'country'       => (string)$row['country'],
			'data'          => api_privacy_labelled($row['data'], $categories),
			'personal'      => (bool)$row['personal'],
			'abroad'        => (bool)$row['abroad'],
			'state_unknown' => (bool)$row['unknown']
		);

	}

	$site_country = strtoupper(trim((string)$site_country));

	return array(
		'site_country' => ($site_country === '') ? null : $site_country,
		'lines'        => $lines,
		'transfers'    => $out
	);

}

function api_privacy_inventory_schema() {

	$labelled = array('key' => 'string', 'label' => 'string');

	return array(
		'site_country' => 'string?',
		'lines'        => array(array(
			'table'      => 'string',
			'categories' => array($labelled),
			'subjects'   => array($labelled),
			'purpose'    => 'string',
			'ground'     => $labelled,
			'retention'  => array($labelled),
			'records'    => 'integer?',
			'people'     => 'integer?'
		)),
		'transfers'    => array(array(
			'service'       => 'string',
			'name'          => 'string',
			'group'         => 'string',
			'uses'          => 'string',
			'country'       => 'string',
			'data'          => array($labelled),
			'personal'      => 'boolean',
			'abroad'        => 'boolean',
			'state_unknown' => 'boolean'
		))
	);

}

// GET /privacy/inventory
function api_privacy_inventory($params) {

	$country = function_exists('pg_erp_store_country') ? (string)pg_erp_store_country() : '';

	api_ok(api_privacy_inventory_present(pg_privacy_inventory(), pg_privacy_inventory_counts(), pg_privacy_transfers_live($country), $country));

}

/* ----- Requests ----------------------------------------------------------- */

// The views of the request box, pure: the Requests screen's filters.
function api_privacy_request_views() {

	return array('open', 'ending', 'answered', 'all');

}

// One page of a view, newest first, pure: the screen's own condition (proven
// rows only), bounded by the cursor's id; one row more than the page to tell
// whether another follows.
function api_privacy_requests_sql($view, $after, $limit, $now) {

	$where = pg_privacy_request_view_where((string)$view, (int)$now);

	if ((int)$after > 0) {

		$where .= " AND (id < '" . (int)$after . "')";

	}

	return "SELECT * FROM privacy_requests WHERE " . $where . " ORDER BY id DESC LIMIT " . ((int)$limit + 1);

}

// A request as listed, pure: no message or answer, which only one request's
// read carries. days_left only while it is open.
function api_privacy_request_present($row, $now) {

	$id_or_null = function ($value) {

		return ((int)$value > 0) ? (int)$value : null;

	};

	$open = ((string)$row['status'] === 'open');

	return array(
		'id'               => (int)$row['id'],
		'kind'             => (string)$row['kind'],
		'status'           => (string)$row['status'],
		'source'           => (string)$row['source'],
		'result'           => ((string)$row['result'] === '') ? null : (string)$row['result'],
		'contact_id'       => $id_or_null($row['contact_id']),
		'user_id'          => $id_or_null($row['user_id']),
		'assigned_user_id' => $id_or_null($row['assigned_user_id']),
		'email'            => (string)$row['email'],
		'name'             => (string)$row['name'],
		'verified_at'      => api_time($row['verified_at']),
		'received_at'      => api_time($row['received_at']),
		'due_at'           => api_time($row['due_at']),
		'days_left'        => $open ? pg_privacy_request_days_left($row['due_at'], $now) : null,
		'closed_at'        => api_time($row['closed_at'])
	);

}

function api_privacy_request_schema() {

	return array(
		'id'               => 'integer',
		'kind'             => 'string',
		'status'           => 'string',
		'source'           => 'string',
		'result'           => 'string?',
		'contact_id'       => 'integer?',
		'user_id'          => 'integer?',
		'assigned_user_id' => 'integer?',
		'email'            => 'string',
		'name'             => 'string',
		'verified_at'      => 'datetime?',
		'received_at'      => 'datetime?',
		'due_at'           => 'datetime?',
		'days_left'        => 'integer?',
		'closed_at'        => 'datetime?'
	);

}

// One request with its text and steps, pure. A step is an audit line of the
// request (audit_log: created_at, action, user_id, username, after_json): its
// action, when, the panel user by id and name, and the kind, status and
// result it left; nothing else of the line, never its address.
function api_privacy_request_detail_present($row, array $history, $now) {

	$steps = array();

	foreach ($history as $line) {

		$after = json_decode((string)$line['after_json'], true);
		$after = is_array($after) ? $after : array();
		$name = trim((string)$line['username']);

		$step = array(
			'action'    => (string)$line['action'],
			'at'        => api_time($line['created_at']),
			'user_id'   => ((int)$line['user_id'] > 0) ? (int)$line['user_id'] : null,
			'user_name' => ($name === '') ? null : $name
		);

		foreach (array('kind', 'status', 'result') as $key) {

			$step[$key] = (isset($after[$key]) && is_scalar($after[$key]) && ((string)$after[$key] !== '')) ? (string)$after[$key] : null;

		}

		$steps[] = $step;

	}

	return api_privacy_request_present($row, $now) + array(
		'message' => (string)$row['message'],
		'answer'  => (string)$row['answer'],
		'steps'   => $steps
	);

}

function api_privacy_request_detail_schema() {

	return api_privacy_request_schema() + array(
		'message' => 'string',
		'answer'  => 'string',
		'steps'   => array(array(
			'action'    => 'string',
			'at'        => 'datetime?',
			'user_id'   => 'integer?',
			'user_name' => 'string?',
			'kind'      => 'string?',
			'status'    => 'string?',
			'result'    => 'string?'
		))
	);

}

// A request's steps as read, newest first, pure: its audit lines, only the
// columns a step shows.
function api_privacy_request_steps_sql($id) {

	return "SELECT created_at, action, user_id, username, after_json FROM audit_log"
		. " WHERE (entity_type = 'privacy_request') AND (entity_id = '" . (int)$id . "')"
		. " ORDER BY created_at DESC, id DESC LIMIT 200";

}

// Before the migration step that adds the request box, every request route
// answers 503: an empty list would read as a box with nothing in it.
function api_privacy_requests_require_table() {

	if (!pg_privacy_requests_ready()) {

		api_fail(503, 'service_unavailable', lang('The database has to be updated first.'));

	}

}

// GET /privacy/requests
function api_privacy_requests_list($params) {

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;
	$view = isset($params['view']) ? (string)$params['view'] : 'open';
	$after = 0;

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$after = (int)$cursor['i'];

	}

	api_privacy_requests_require_table();

	$now = time();
	$result = api_query(api_privacy_requests_sql($view, $after, $limit, $now));
	$out = array();
	$next_cursor = null;

	while ($result && ($row = mysqli_fetch_assoc($result))) {

		if (count($out) === $limit) {

			$last = $out[$limit - 1]['id'];
			$next_cursor = api_cursor_encode($last, $last);
			break;

		}

		$out[] = api_privacy_request_present($row, $now);

	}

	api_ok_list($out, $limit, $next_cursor);

}

// GET /privacy/requests/{id}
function api_privacy_request_read($params) {

	api_privacy_requests_require_table();

	$row = pg_privacy_request_get((int)$params['id']);

	if ($row === null) {

		api_fail_not_found(lang('Privacy request'));

	}

	$history = pg_schema_has('audit_log') ? db_items(api_privacy_request_steps_sql((int)$row['id'])) : array();

	api_ok(api_privacy_request_detail_present($row, is_array($history) ? $history : array(), time()));

}
