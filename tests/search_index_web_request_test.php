<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a search index run started from the panel: it is accepted only as
 * a POST carrying the session's form token (a cross-site link cannot start a
 * crawl in a manager's session), and it saves and closes the session before
 * the first page is drawn, so the manager's other panel requests do not wait
 * on the session lock for the whole run. The run itself needs a database, so
 * its wiring is read from the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/site_search.php');

function test_search_index_web_request_needs_post_and_token()
{
	pg_assert_true(pg_search_index_web_request_valid('POST', 'abc', 'abc'), 'POST with the token');
	pg_assert_true(pg_search_index_web_request_valid('post', 'abc', 'abc'), 'method letter case');
	pg_assert_false(pg_search_index_web_request_valid('GET', 'abc', 'abc'), 'GET, even with the token');
	pg_assert_false(pg_search_index_web_request_valid('POST', '', 'abc'), 'no token posted');
	pg_assert_false(pg_search_index_web_request_valid('POST', 'abd', 'abc'), 'wrong token');
	pg_assert_false(pg_search_index_web_request_valid('POST', '', ''), 'no session token');
	pg_assert_false(pg_search_index_web_request_valid('POST', array('abc'), 'abc'), 'token posted as an array');
	pg_assert_false(pg_search_index_web_request_valid(null, 'abc', 'abc'), 'no method');
}

function test_search_index_closes_session_before_rendering()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/update_search_index.php');
	$read = strpos($src, "\$userid = \$_SESSION['sessionuserid']");
	$close = strpos($src, 'session_write_close();');
	$check = strpos($src, 'AND (user_role < \'3\')');
	$valid = strpos($src, 'pg_search_index_web_request_valid(');
	$render = strpos($src, 'get_page_content(');
	pg_assert_true($read !== false && $close !== false && $check !== false && $valid !== false && $render !== false, 'wiring present');
	pg_assert_true($read < $close && $close < $check && $check < $valid && $valid < $render, 'session read, closed, user checked, request checked, then pages drawn');
	pg_assert_false(strpos($src, 'unset($_SESSION);'), 'session closed, not only unset');
	pg_assert_false(strpos($src, 'window.location.replace('), 'the window carries on with a POST, not a GET');
}

function test_search_audits_screen_posts_index_step()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/search_audits.php');
	pg_assert_false(strpos($src, 'fetch("update_search_index.php?continue=1", { credentials: "same-origin" })'), 'no bare GET');
	pg_assert_contains('fetch("update_search_index.php", { method: "POST", body: indexBody, credentials: "same-origin" })', $src);
	pg_assert_contains('indexBody.append("token", token);', $src);
}
