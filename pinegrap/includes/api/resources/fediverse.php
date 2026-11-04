<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The Fediverse account's followers and its delivery log, as Settings ›
// Fediverse lists them. Read only: following is the follower's own act, and
// blocking or trying a delivery again stays on the screen.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// The ActivityPub modules are loaded on demand, here only by these routes.
function api_fediverse_load() {

	require_once(PG_FUNCTIONS_DIR . '/includes/fn/activitypub_settings.php');

	if (!pg_ap_outbox_ready()) {

		api_fail(503, 'service_unavailable', lang('The database has not been upgraded yet. Run the software update first.'));

	}

}

function api_fediverse_follower_present($row) {

	return array(
		'id'          => (int)$row['id'],
		'actor'       => (string)$row['actor_id'],
		'name'        => ((string)$row['name'] !== '') ? (string)$row['name'] : null,
		'avatar'      => ((string)$row['avatar'] !== '') ? (string)$row['avatar'] : null,
		'server'      => pg_ap_host((string)$row['actor_id']),
		'followed_at' => api_time($row['followed_at'])
	);

}

function api_fediverse_follower_schema() {

	return array(
		'id'          => 'integer',
		'actor'       => 'string',
		'name'        => 'string?',
		'avatar'      => 'string?',
		'server'      => 'string',
		'followed_at' => 'string?'
	);

}

function api_fediverse_delivery_present($row) {

	$view = pg_ap_delivery_view($row);

	return array(
		'id'              => $view['id'],
		'activity'        => (string)$row['activity_id'],
		'type'            => ($view['type'] !== '') ? $view['type'] : null,
		'object'          => ($view['object'] !== '') ? $view['object'] : null,
		'inbox'           => (string)$row['inbox'],
		'server'          => $view['host'],
		'state'           => $view['state'],
		'attempts'        => $view['attempts'],
		// Only a waiting delivery has a next attempt.
		'next_attempt_at' => ($view['state'] === 'queued') ? api_time($view['next_at']) : null,
		'created_at'      => api_time($view['created_at'])
	);

}

function api_fediverse_delivery_schema() {

	return array(
		'id'              => 'integer',
		'activity'        => 'string',
		'type'            => 'string?',
		'object'          => 'string?',
		'inbox'           => 'string',
		'server'          => 'string',
		'state'           => 'string',
		'attempts'        => 'integer',
		'next_attempt_at' => 'string?',
		'created_at'      => 'string?'
	);

}

// The id paging both lists share: newest first, $where extra conditions.
function api_fediverse_page($params, $sql, $where) {

	if (isset($params['cursor']) && $params['cursor'] !== '') {

		$cursor = api_cursor_decode($params['cursor']);

		if ($cursor === null) {

			api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

		}

		$where[] = "id < '" . (int)$cursor['i'] . "'";

	}

	$limit = (int)$params['limit'];

	$rows = api_rows($sql
		. (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where)) . "
		ORDER BY id DESC
		LIMIT " . ($limit + 1));

	$next = null;

	if (count($rows) > $limit) {

		array_pop($rows);

		$last = end($rows);

		$next = api_cursor_encode((int)$last['id'], (int)$last['id']);

	}

	return array($rows, $limit, $next);

}

function api_fediverse_followers_list($params) {

	api_fediverse_load();

	list($rows, $limit, $next) = api_fediverse_page($params, "SELECT id, actor_id, name, avatar, followed_at FROM ap_followers", array());

	api_ok_list(array_map('api_fediverse_follower_present', $rows), $limit, $next);

}

function api_fediverse_deliveries_list($params) {

	api_fediverse_load();

	// The log rows the outbox keeps for itself carry no inbox.
	$where = array("inbox != ''");

	if (isset($params['state']) && $params['state'] !== '') {

		$where[] = "state = '" . escape($params['state']) . "'";

	}

	list($rows, $limit, $next) = api_fediverse_page($params, "SELECT id, activity_id, inbox, attempts, next_at, state, created_at FROM ap_outbox", $where);

	api_ok_list(array_map('api_fediverse_delivery_present', $rows), $limit, $next);

}
