<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Router script for PHP's built-in server in the sandbox
 * (tools/setup_sandbox.sh). The built-in server knows no rewrite rules, so
 * this script does what the root .htaccess and web.config do in production:
 *
 * - a request for a file or folder that exists is left to the server, like
 *   the "!-f" / "!-d" conditions leave it to Apache (a script followed by
 *   path info counts as that script);
 * - the data and includes folders are refused with 403, like the
 *   "deny from all" files under pinegrap/ and the web.config rules, and so
 *   are hidden folders and paths that climb out of the document root;
 * - everything else (theme CSS, logos and other files from the files table,
 *   friendly page URLs) runs pinegrap/router.php, as the RewriteRule does.
 *
 * Usage:  php -S 127.0.0.1:8000 -t <repository root> tools/sandbox_router.php
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (PHP_SAPI !== 'cli-server' && !defined('PG_TEST_RUNNER')) {
	http_response_code(403);
	exit('Forbidden');
}

/**
 * Decides how the sandbox answers a request path: 'static' when the built-in
 * server should serve the file or folder itself, 'forbidden' for a path the
 * production rules refuse, 'router' for one pinegrap/router.php answers.
 *
 * @param string $root     The document root (the repository root).
 * @param string $uri_path The path part of the request URI, still encoded.
 * @return string
 */
function sandbox_router_route($root, $uri_path)
{
	$path = rawurldecode($uri_path);

	if (strpos($path, "\0") !== false) {
		return 'forbidden';
	}

	$segments = array_values(array_filter(explode('/', str_replace('\\', '/', $path)), 'strlen'));

	foreach ($segments as $segment) {
		if (($segment === '..') || in_array(strtolower($segment), array('.git', '.svn', '.vscode', 'node_modules'), true)) {
			return 'forbidden';
		}
	}

	if (preg_match('#^/(pinegrap/)?(includes|data)(/|$)#i', '/' . implode('/', $segments))) {
		return 'forbidden';
	}

	// The longest leading part of the path that is a file wins, so that
	// /pinegrap/integration.php/v1/pages is integration.php with path info.
	$candidate = rtrim($root, '/');

	if (is_dir($candidate . '/' . implode('/', $segments))) {
		return 'static';
	}

	foreach ($segments as $segment) {
		$candidate .= '/' . $segment;

		if (is_file($candidate)) {
			return 'static';
		}

		if (!is_dir($candidate)) {
			break;
		}
	}

	return 'router';
}

if (defined('PG_TEST_RUNNER')) {
	return;
}

$sandbox_router_root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$sandbox_router_path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

switch (sandbox_router_route($sandbox_router_root, $sandbox_router_path)) {
	case 'static':
		return false;

	case 'forbidden':
		http_response_code(403);
		exit('Forbidden');
}

// pinegrap/router.php works out the site's URL path from SCRIPT_NAME, which
// in production is the rewrite target; the built-in server would report this
// script instead. REQUEST_URI stays the one the browser sent.
$_SERVER['SCRIPT_NAME'] = '/pinegrap/router.php';
$_SERVER['PHP_SELF'] = '/pinegrap/router.php';
$_SERVER['SCRIPT_FILENAME'] = $sandbox_router_root . '/pinegrap/router.php';

chdir($sandbox_router_root . '/pinegrap');

require $sandbox_router_root . '/pinegrap/router.php';
