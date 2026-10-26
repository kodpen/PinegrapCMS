<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The audit trail through the API: who changed what in the panel, through
 * the API or in a scheduled job, with the changed fields before and after,
 * as the Audit trail screen (view_audit_log.php) lists them. Read only - a
 * trail an application could rewrite would not be one.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// A stored side, decoded as it was written: only the changed fields, secrets
// as "***", long texts as {truncated, length, sha1, start}, a side too large
// as a whole as {truncated, fields}. null when the side holds nothing (before
// a create, after a delete).
function api_audit_log_side($json) {

	$values = (is_string($json) && ($json !== '')) ? json_decode($json, true) : null;

	return is_array($values) ? $values : null;

}

function api_audit_log_present($row) {

	return array(
		'id'          => (int)$row['id'],
		'created_at'  => api_time($row['created_at']),
		'source'      => (string)$row['source'],
		// The operator, or the owner of the application for an API line; null
		// for a job.
		'user_id'     => ((int)$row['user_id'] > 0) ? (int)$row['user_id'] : null,
		'username'    => ((string)$row['username'] !== '') ? (string)$row['username'] : null,
		'ip'          => ((string)$row['ip'] !== '') ? (string)$row['ip'] : null,
		'action'      => (string)$row['action'],
		'entity_type' => (string)$row['entity_type'],
		'entity_id'   => ((int)$row['entity_id'] > 0) ? (int)$row['entity_id'] : null,
		'label'       => (string)$row['label'],
		'request_id'  => ((string)$row['request_id'] !== '') ? (string)$row['request_id'] : null,
		'before'      => api_audit_log_side($row['before_json']),
		'after'       => api_audit_log_side($row['after_json'])
	);

}

function api_audit_log_schema() {

	return array(
		'id'          => 'integer',
		'created_at'  => 'string?',
		'source'      => 'string',
		'user_id'     => 'integer?',
		'username'    => 'string?',
		'ip'          => 'string?',
		'action'      => 'string',
		'entity_type' => 'string',
		'entity_id'   => 'integer?',
		'label'       => 'string',
		'request_id'  => 'string?',
		'before'      => 'object?',
		'after'       => 'object?'
	);

}

// Newest first, the filters of the screen (pg_audit_view_filters()), the
// window as moments rather than whole days.
function api_audit_log_list($params) {

	if (!pg_audit_ready()) {

		api_fail(503, 'service_unavailable', lang('The database has not been upgraded yet. Run the software update first.'));

	}

	$input = array();

	foreach (array('source' => 'source', 'user' => 'user', 'action' => 'action', 'entity_type' => 'type', 'entity_id' => 'id', 'q' => 'q', 'request_id' => 'request') as $param => $filter) {

		if (isset($params[$param]) && ($params[$param] !== '')) {

			$input[$filter] = (string)$params[$param];

		}

	}

	$filters = pg_audit_view_filters($input);

	// A filter the screen would drop must not widen the answer to every line.
	foreach (array('action' => 'action', 'entity_type' => 'type', 'request_id' => 'request') as $param => $filter) {

		if (isset($input[$filter]) && ($filters[$filter] === '')) {

			api_fail_validation(lang(array('string' => '{var:1} is not a value the audit trail holds.', 'vars' => $param)), $param);

		}

	}

	$where = array(pg_audit_view_where($filters));

	if (isset($params['from'])) {

		$where[] = "created_at >= '" . (int)$params['from'] . "'";

	}

	if (isset($params['to'])) {

		$where[] = "created_at <= '" . (int)$params['to'] . "'";

	}

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "id < '" . (int)$cursor['i'] . "'";

	}

	$limit = (int)$params['limit'];

	$rows = api_rows("SELECT id, created_at, request_id, source, user_id, username, ip, action, entity_type, entity_id, label, before_json, after_json
		FROM audit_log
		WHERE (" . implode(') AND (', $where) . ")
		ORDER BY id DESC
		LIMIT " . ($limit + 1));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['id'], (int)$last['id']);

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_audit_log_present($row);

	}

	api_ok_list($out, $limit, $next);

}
