<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that a search index run renders pages as a visitor who is not signed
 * in, whoever starts it: initialize_user() does not load the session user
 * while UPDATE_SEARCH_INDEX is set (pg_auth_session_user_applies() in
 * includes/fn/auth.php), so the classic comments area's watcher management
 * and its e-mail addresses never reach the public index. The run itself needs
 * a database, so its wiring is read from the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/auth.php');

function test_search_index_session_user_skipped_during_index_run()
{
	pg_assert_false(pg_auth_session_user_applies(5, true), 'an index run renders anonymously');
	pg_assert_false(pg_auth_session_user_applies('5', true), 'string id, index run');
}

function test_search_index_session_user_loaded_otherwise()
{
	pg_assert_true(pg_auth_session_user_applies(5, false), 'an ordinary request loads its user');
	pg_assert_true(pg_auth_session_user_applies('5', false), 'string id');
	pg_assert_false(pg_auth_session_user_applies(null, false), 'no session user');
	pg_assert_false(pg_auth_session_user_applies('', false), 'empty session user');
	pg_assert_false(pg_auth_session_user_applies(0, false), 'zero session user');
}

function test_search_index_initialize_user_uses_guard()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/auth.php');
	pg_assert_contains("pg_auth_session_user_applies(\$_SESSION['sessionuserid'] ?? null,", $src);
}

function test_search_index_access_check_reads_session_before_unset()
{
	// The person who pressed the button is still checked: the job reads the
	// session id itself, after init.php, and only then drops $_SESSION.
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/update_search_index.php');
	$read = strpos($src, "\$userid = \$_SESSION['sessionuserid']");
	$unset = strpos($src, 'unset($_SESSION);');
	$check = strpos($src, 'AND (user_role < \'3\')');
	pg_assert_true($read !== false && $unset !== false && $check !== false, 'access check present');
	pg_assert_true($read < $unset && $unset < $check, 'session read, then dropped, then checked');
	pg_assert_true(strpos($src, "define('UPDATE_SEARCH_INDEX', true);") < strpos($src, "include('init.php');"), 'flag set before init.php');
}
