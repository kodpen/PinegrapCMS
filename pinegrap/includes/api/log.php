<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// One row per request, written on the way out of api_send().
//
// The previous surface logged only successful writes, through log_activity(),
// which meant a key being refused, a key hammering the site, or a key that had
// silently stopped calling all looked identical from the panel: nothing at all.
// Reads are the majority of API traffic and the ones that go wrong quietly.

if (!defined('PG_API_ENTRY')) {
	exit;
}

// Called exactly once per request by api_send(). The guard is not paranoia: a
// handler that fails inside another failure path would otherwise write two rows
// for one request and make the counts wrong.
function api_log_request($status_code, $error_code = '') {

	static $written = false;

	if ($written) {

		return;

	}

	$written = true;

	if (!defined('DB_CONNECTED')) {

		return;

	}

	$app = api_current_app();

	$app_id = ($app === null) ? 0 : (int)$app['id'];

	$duration = 0;

	if (isset($GLOBALS['pg_api_started_at'])) {

		$duration = (int)round((microtime(true) - $GLOBALS['pg_api_started_at']) * 1000);

	}

	// The path is stored without the query string. Credentials belong in the
	// Authorization header, but a client that puts a test key in the URL should
	// not have it copied into a table that the panel prints on screen.
	$path = api_request_path();

	api_exec("INSERT INTO api_request_log
		(app_id, request_id, method, path, status_code, error_code, ip, duration_ms, timestamp)
		VALUES (
			'" . $app_id . "',
			'" . escape(api_request_id()) . "',
			'" . escape(substr(api_request_method(), 0, 10)) . "',
			'" . escape(substr($path, 0, 255)) . "',
			'" . (int)$status_code . "',
			'" . escape(substr($error_code, 0, 50)) . "',
			'" . escape(substr(api_client_ip(), 0, 45)) . "',
			'" . $duration . "',
			UNIX_TIMESTAMP()
		)");

	// Last seen, kept on the application itself so the list screen can show it
	// without touching the log table. Only for calls that got as far as being
	// recognised - a refused key has no application to stamp.
	if ($app_id > 0) {

		api_exec("UPDATE api_apps
			SET last_used_timestamp = UNIX_TIMESTAMP(),
			    last_used_ip = '" . escape(substr(api_client_ip(), 0, 45)) . "'
			WHERE id = '" . $app_id . "'");

	}

}
