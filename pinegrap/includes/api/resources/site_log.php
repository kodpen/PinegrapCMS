<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The site log: what was done on the site, by whom and from which address, as
// the Site Log screen lists it. Read only - an audit trail an application
// could rewrite would not be one. The PHP error logs on the same screen stay
// there: they are files on the server, not records.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

function api_site_log_present($row) {

	return array(
		'id'          => (int)$row['log_id'],
		'description' => (string)$row['log_description'],
		// The account that did it; null for what visitors and the site's own
		// jobs did.
		'user'        => ((string)$row['log_user'] !== '') ? (string)$row['log_user'] : null,
		'ip'          => ((string)$row['log_ip'] !== '') ? (string)$row['log_ip'] : null,
		'created_at'  => api_time($row['log_timestamp'])
	);

}

function api_site_log_schema() {

	return array(
		'id'          => 'integer',
		'description' => 'string',
		'user'        => 'string?',
		'ip'          => 'string?',
		'created_at'  => 'string?'
	);

}

// Newest first.
function api_site_log_list($params) {

	$where = array();

	if (isset($params['from'])) {

		$where[] = "log_timestamp >= '" . (int)$params['from'] . "'";

	}

	if (isset($params['to'])) {

		$where[] = "log_timestamp <= '" . (int)$params['to'] . "'";

	}

	if (isset($params['user']) && $params['user'] !== '') {

		$where[] = "log_user = '" . escape($params['user']) . "'";

	}

	if (isset($params['q']) && $params['q'] !== '') {

		$where[] = "log_description LIKE '%" . escape(escape_like($params['q'])) . "%'";

	}

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "log_id < '" . (int)$cursor['i'] . "'";

	}

	$limit = (int)$params['limit'];

	$rows = api_rows("SELECT log_id, log_description, log_ip, log_user, log_timestamp
		FROM log"
		. (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)) . "
		ORDER BY log_id DESC
		LIMIT " . ($limit + 1));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['log_id'], (int)$last['log_id']);

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = api_site_log_present($row);

	}

	api_ok_list($out, $limit, $next);

}
