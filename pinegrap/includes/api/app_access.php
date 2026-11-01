<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Helpers for Application Access (api_settings.php): which OAuth application
// the site serves, and each application's own request log for the drawer.

if (!defined('PG_API_PANEL')) {
	exit;
}

// The id of the OAuth application pg_oauth_app() serves, read from api_apps
// rows (id, kind, status, expires_at): the oldest active one. When that one
// has expired no application is served (0), as pg_oauth_app() does not fall
// through to the next.
function api_app_oauth_served_id($rows, $now) {

	$oldest = null;

	foreach ((array)$rows as $row) {

		if (!isset($row['kind'], $row['status'], $row['id'])
			|| ($row['kind'] !== 'oauth') || ($row['status'] !== 'active')) {

			continue;

		}

		if (($oldest === null) || ((int)$row['id'] < (int)$oldest['id'])) {

			$oldest = $row;

		}

	}

	if ($oldest === null) {

		return 0;

	}

	$expires = isset($oldest['expires_at']) ? (int)$oldest['expires_at'] : 0;

	if (($expires !== 0) && ($expires <= (int)$now)) {

		return 0;

	}

	return (int)$oldest['id'];

}

// An active OAuth application the site does not serve: its scopes reach no
// connection, so the screen says so instead of listing it as working.
function api_app_oauth_unused($row, $served_id) {

	return isset($row['kind'], $row['status'], $row['id'])
		&& ($row['kind'] === 'oauth')
		&& ($row['status'] === 'active')
		&& ((int)$row['id'] !== (int)$served_id);

}

// The site serves one OAuth application (pg_oauth_app()), so a new one is
// refused while another is active: its scopes would never be used. Returns
// the message, or '' when the row may be made.
function api_app_oauth_create_refusal($kind, $existing_name) {

	if (($kind !== 'oauth') || ((string)$existing_name === '')) {

		return '';

	}

	return lang(array(
		'string' => 'This site already has an OAuth app: {var:1}; change its scopes there.',
		'vars'   => h((string)$existing_name)
	));

}

// The first of the drawer's text fields that is longer than its api_apps
// column, or '' when all fit. The session is non-strict, so MySQL would cut
// a longer value without an error; for the IP allow list a cut inside its
// last range (10.0.0.0/16 -> 10.0.0.0/1) admits addresses never listed.
// Counted in characters, as varchar(N) in utf8mb4 is.
function api_app_text_too_long($fields) {

	$widths = array('name' => 190, 'description' => 500, 'ip_allowlist' => 500);

	foreach ($widths as $field => $width) {

		if (isset($fields[$field]) && is_string($fields[$field])
			&& (mb_strlen($fields[$field], 'UTF-8') > $width)) {

			return $field;

		}

	}

	return '';

}

// One query for the drawer's Logs tab: for every application its own latest
// requests and its own latest errors, each a bounded subquery read backwards
// on the (app_id, timestamp) index, so a busy application cannot push the
// others' requests out of view. The column `list` says which list a row is in.
function api_app_log_sql($app_ids, $limit) {

	$ids = array();

	foreach ((array)$app_ids as $id) {

		$id = (int)$id;

		if ($id > 0) {

			$ids[$id] = true;

		}

	}

	$limit = max(1, (int)$limit);

	$parts = array();

	foreach (array_keys($ids) as $id) {

		$columns = "app_id, method, path, status_code, error_code, duration_ms, timestamp FROM api_request_log WHERE app_id = '" . $id . "'";

		$order = " ORDER BY timestamp DESC, id DESC LIMIT " . $limit;

		$parts[] = "(SELECT 'all' AS list, " . $columns . $order . ")";

		$parts[] = "(SELECT 'errors' AS list, " . $columns . " AND status_code >= 400" . $order . ")";

	}

	return implode(' UNION ALL ', $parts);

}

// The rows of api_app_log_sql() as the drawer reads them: per application an
// 'all' and an 'errors' list. $time_label turns a timestamp into its text.
function api_app_log_group($rows, $time_label) {

	$grouped = array();

	foreach ((array)$rows as $row) {

		$key = (int)$row['app_id'];

		if (!isset($grouped[$key])) {

			$grouped[$key] = array('all' => array(), 'errors' => array());

		}

		$list = ($row['list'] === 'errors') ? 'errors' : 'all';

		$grouped[$key][$list][] = array(
			'method'   => (string)$row['method'],
			'path'     => (string)$row['path'],
			'status'   => (int)$row['status_code'],
			'error'    => (string)$row['error_code'],
			'duration' => (int)$row['duration_ms'],
			'time'     => call_user_func($time_label, (int)$row['timestamp'])
		);

	}

	return $grouped;

}

// How a log row's moment is written: the time alone for today (a site day),
// the date as well for an older one, its year only when it is another year's.
// The result is the type and year get_absolute_time() is asked for.
function api_app_log_time_type($timestamp, $now) {

	if (date('Y-m-d', (int)$timestamp) === date('Y-m-d', (int)$now)) {

		return array('type' => 'time', 'year' => false);

	}

	return array('type' => '', 'year' => (date('Y', (int)$timestamp) !== date('Y', (int)$now)));

}

// The text of a log row's moment, in the site's date and time format.
function api_app_log_time_label($timestamp) {

	$shape = api_app_log_time_type($timestamp, time());

	return get_absolute_time(array(
		'timestamp' => (int)$timestamp,
		'type'      => $shape['type'],
		'year'      => $shape['year'],
		'format'    => 'plain_text'
	));

}
