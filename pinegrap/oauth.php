<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The OAuth 2.1 authorization server (includes/oauth/):
//
//   GET/POST oauth.php/authorize   the consent screen, in the person's browser
//   POST     oauth.php/token       codes and refresh tokens for access tokens
//   POST     oauth.php/register    dynamic client registration
//   POST     oauth.php/revoke      ends a connection
//
// and, handed over by the site's router, the discovery documents under
// /.well-known/. The tokens it issues are used on integration.php.
//
// Like integration.php the endpoint is addressed through PATH_INFO, so no
// rewrite rule is needed for anything but the well-known names.

define('PG_OAUTH_ENTRY', true);

// The external API's code is loaded as it is for integration.php: tokens are
// API device rows, and the owner and scope rules are the API's own.
define('PG_API_ENTRY', true);

$GLOBALS['pg_api_started_at'] = microtime(true);

// Only the consent screen is a browser page that needs the person's session.
// Everything else is called by a machine and runs with no session, for the
// reason integration.php gives: a signed-in operator's cookie must never decide
// what a call that presented no credentials may do.
function pg_oauth_entry_route() {

	if (defined('PG_OAUTH_WELL_KNOWN')) {

		return 'well-known';

	}

	// PATH_INFO, or on IIS the whole address in ORIG_PATH_INFO, or the query
	// fallback the external API also accepts. Only the last segment counts.
	$path = isset($_SERVER['PATH_INFO']) ? (string)$_SERVER['PATH_INFO'] : '';

	if (($path === '') && isset($_SERVER['ORIG_PATH_INFO'])) {

		$path = (string)$_SERVER['ORIG_PATH_INFO'];

	}

	if (($path === '') && isset($_GET['path']) && is_string($_GET['path'])) {

		$path = $_GET['path'];

	}

	return preg_match('#(?:^|/)(authorize|token|register|revoke)/?$#', $path, $match) ? $match[1] : '';

}

$pg_oauth_route = pg_oauth_entry_route();

if ($pg_oauth_route !== 'authorize') {

	define('PG_NO_SESSION', true);

	$_SESSION = array();

	ob_start();

}

include('init.php');

require_once(dirname(__FILE__) . '/includes/api/bootstrap.php');
require_once(dirname(__FILE__) . '/includes/oauth/endpoints.php');

if ($pg_oauth_route === 'authorize') {

	require_once(dirname(__FILE__) . '/includes/oauth/authorize.php');

	pg_oauth_authorize();

	exit();

}

if (isset($_SERVER['REQUEST_METHOD']) && ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')) {

	pg_oauth_preflight();

}

switch ($pg_oauth_route) {

	case 'well-known':
		pg_oauth_well_known(PG_OAUTH_WELL_KNOWN);
		break;

	case 'token':
		pg_oauth_token();
		break;

	case 'register':
		pg_oauth_register();
		break;

	case 'revoke':
		pg_oauth_revoke();
		break;

}

pg_oauth_fail(404, 'not_found');
