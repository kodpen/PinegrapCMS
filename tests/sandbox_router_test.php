<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for tools/sandbox_router.php, the router PHP's built-in server runs in
 * the sandbox: which requests it leaves to the server as physical files, which
 * it refuses like the production rules do, and which it hands to
 * pinegrap/router.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The router returns before handling a request when PG_TEST_RUNNER is set.
require_once(dirname(__DIR__) . '/tools/sandbox_router.php');

function sandbox_router_test_root()
{
	return dirname(__DIR__);
}

// Files and folders that exist are the built-in server's to serve, as the
// "!-f" and "!-d" conditions leave them to Apache.
function test_sandbox_router_existing_files_are_served_directly()
{
	$root = sandbox_router_test_root();

	pg_assert_same('static', sandbox_router_route($root, '/'));
	pg_assert_same('static', sandbox_router_route($root, '/index.php'));
	pg_assert_same('static', sandbox_router_route($root, '/pinegrap/install/'));
	pg_assert_same('static', sandbox_router_route($root, '/pinegrap/router.php'));
}

// A script followed by path info is still that script (Apache maps
// REQUEST_FILENAME to the file, IIS has a rule for integration.php).
function test_sandbox_router_path_info_keeps_the_script()
{
	pg_assert_same('static', sandbox_router_route(sandbox_router_test_root(), '/pinegrap/integration.php/v1/pages'));
}

// Theme CSS, logos and other files from the files table, and friendly URLs,
// have no physical file and go to the Pinegrap router.
function test_sandbox_router_missing_files_go_to_the_router()
{
	$root = sandbox_router_test_root();

	pg_assert_same('router', sandbox_router_route($root, '/theme.css'));
	pg_assert_same('router', sandbox_router_route($root, '/logo.png'));
	pg_assert_same('router', sandbox_router_route($root, '/staff-home'));
	pg_assert_same('router', sandbox_router_route($root, '/blog/some-post'));
	pg_assert_same('router', sandbox_router_route($root, '/a%20file.pdf'));
}

// The data and includes folders are refused as the deny rules and web.config
// refuse them, whether the file exists or not and in any letter case.
function test_sandbox_router_refuses_data_and_includes()
{
	$root = sandbox_router_test_root();

	pg_assert_same('forbidden', sandbox_router_route($root, '/pinegrap/data/config.php'));
	pg_assert_same('forbidden', sandbox_router_route($root, '/pinegrap/data/files/logo.png'));
	pg_assert_same('forbidden', sandbox_router_route($root, '/pinegrap/includes/db_guard.php'));
	pg_assert_same('forbidden', sandbox_router_route($root, '/Pinegrap/Includes/db_guard.php'));
	pg_assert_same('forbidden', sandbox_router_route($root, '/pinegrap/%64ata/config.php'));
}

// Hidden folders and a path that climbs out of the document root never reach
// the file system.
function test_sandbox_router_refuses_hidden_segments_and_traversal()
{
	$root = sandbox_router_test_root();

	pg_assert_same('forbidden', sandbox_router_route($root, '/.git/config'));
	pg_assert_same('forbidden', sandbox_router_route($root, '/pinegrap/../.git/HEAD'));
	pg_assert_same('forbidden', sandbox_router_route($root, '/%2e%2e/etc/passwd'));
	pg_assert_same('forbidden', sandbox_router_route($root, "/index.php\0.css"));
}
