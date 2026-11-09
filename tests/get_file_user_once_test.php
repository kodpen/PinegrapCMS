<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Guard over get_file.php's initialize_user(): one request may reach it
 * twice. A workspace channel's file reads the visitor first (a public
 * channel's file opens to the whole team), and the folder's private access
 * check asks again when the channel is not public. A second run must not
 * define the USER_* constants again: PHP 8 warns on a redefined constant (an
 * error from PHP 9), and where errors are shown the eight warnings went out
 * ahead of the file's bytes, so every picture of a private channel arrived
 * broken.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_get_file_initialize_user_runs_once()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/get_file.php');

	$start = strpos($source, 'function initialize_user()');
	pg_assert_true($start !== false, 'get_file.php has initialize_user()');

	$query = strpos($source, 'mysqli_query(', (int) $start);
	$define = strpos($source, "define('USER_LOGGED_IN'", (int) $start);
	pg_assert_true(($query !== false) && ($define !== false), 'initialize_user() reads the config and defines USER_LOGGED_IN');

	// Everything the function does before its first query or definition.
	$head = substr($source, (int) $start, (int) min($query, $define) - (int) $start);

	pg_assert_true(
		(bool) preg_match("/if\s*\(\s*defined\(\s*'USER_LOGGED_IN'\s*\)\s*\)\s*\{\s*return;\s*\}/", $head),
		'a second call returns before it reads or defines anything');
}

function test_get_file_private_channel_file_reaches_initialize_user_twice()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/get_file.php');

	// The workspace block reads the visitor, and the folder check after it
	// calls initialize_user() with no guard of its own: the function's own
	// guard is what keeps that second call quiet.
	$workspace = strpos($source, '$workspace_file = false;');
	$folder = strpos($source, '$access_control_type = ($erp_document || $workspace_file)');

	pg_assert_true(($workspace !== false) && ($folder !== false) && ($workspace < $folder), 'the workspace block comes before the folder check');
	pg_assert_true(strpos(substr($source, (int) $workspace, (int) $folder - (int) $workspace), 'initialize_user();') !== false, 'the workspace block reads the visitor');
	pg_assert_true(strpos($source, 'initialize_user();', (int) $folder) !== false, 'the folder check reads the visitor again');
}
