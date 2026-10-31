<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Application Access's list and drawer helpers
 * (includes/api/app_access.php), that need no database: which OAuth
 * application the site serves and which rows it does not, the refusal of a
 * second active OAuth application, the per-application log query, and the
 * log's time label.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/app_access.php');

// The served OAuth application is the oldest active one, as pg_oauth_app()
// reads it; an expired oldest one means none is served, not the next.
function test_api_app_access_oauth_served_id()
{
	$now = 1800000000;

	$rows = array(
		array('id' => 9, 'kind' => 'oauth', 'status' => 'active', 'expires_at' => 0),
		array('id' => 4, 'kind' => 'oauth', 'status' => 'active', 'expires_at' => 0),
		array('id' => 2, 'kind' => 'oauth', 'status' => 'disabled', 'expires_at' => 0),
		array('id' => 1, 'kind' => 'server', 'status' => 'active', 'expires_at' => 0)
	);

	pg_assert_same(4, api_app_oauth_served_id($rows, $now), 'oldest active OAuth row');

	pg_assert_same(0, api_app_oauth_served_id(array(), $now), 'no rows');

	$rows[1]['expires_at'] = $now - 10;

	pg_assert_same(0, api_app_oauth_served_id($rows, $now), 'oldest active is expired: none served');

	$rows[1]['expires_at'] = $now + 10;

	pg_assert_same(4, api_app_oauth_served_id($rows, $now), 'not yet expired');
}

// Only an active OAuth row other than the served one is "not in use".
function test_api_app_access_oauth_unused()
{
	pg_assert_true(api_app_oauth_unused(array('id' => 9, 'kind' => 'oauth', 'status' => 'active'), 4), 'newer active row');
	pg_assert_false(api_app_oauth_unused(array('id' => 4, 'kind' => 'oauth', 'status' => 'active'), 4), 'the served row');
	pg_assert_false(api_app_oauth_unused(array('id' => 9, 'kind' => 'oauth', 'status' => 'disabled'), 4), 'a switched-off row');
	pg_assert_false(api_app_oauth_unused(array('id' => 9, 'kind' => 'server', 'status' => 'active'), 4), 'an application key');
	pg_assert_true(api_app_oauth_unused(array('id' => 9, 'kind' => 'oauth', 'status' => 'active'), 0), 'none served (the oldest expired)');
}

// A second OAuth application is refused while one is active, naming it.
function test_api_app_access_oauth_create_refusal()
{
	pg_assert_same('', api_app_oauth_create_refusal('server', 'erwer'), 'an application key is never refused');
	pg_assert_same('', api_app_oauth_create_refusal('device', 'erwer'), 'team devices are never refused here');
	pg_assert_same('', api_app_oauth_create_refusal('oauth', ''), 'no active OAuth application');

	$refusal = api_app_oauth_create_refusal('oauth', 'erwer <b>');

	pg_assert_contains('erwer &lt;b&gt;', $refusal, 'names the existing one, escaped');
	pg_assert_contains('change its scopes there', $refusal, 'says where to change the scopes');
}

// Every application gets its own bounded list from the index on
// (app_id, timestamp), and the errors list is that application's own errors.
function test_api_app_access_log_sql()
{
	pg_assert_same('', api_app_log_sql(array(), 15), 'no applications, no query');

	$sql = api_app_log_sql(array(3, '7', 0, 3), 15);

	pg_assert_same(4, substr_count($sql, '(SELECT '), 'two lists per distinct application, zero left out');
	pg_assert_same(3, substr_count($sql, 'UNION ALL'), 'one query');
	pg_assert_contains("WHERE app_id = '3' ORDER BY timestamp DESC, id DESC LIMIT 15", $sql, 'latest of application 3');
	pg_assert_contains("WHERE app_id = '7' AND status_code >= 400 ORDER BY timestamp DESC, id DESC LIMIT 15", $sql, 'latest errors of application 7');
	pg_assert_false(strpos($sql, 'app_id > 0') !== false, 'no site-wide window');
}

// Grouping keeps each application's two lists apart.
function test_api_app_access_log_group()
{
	$rows = array(
		array('app_id' => 3, 'list' => 'all', 'method' => 'GET', 'path' => '/meta', 'status_code' => 200, 'error_code' => '', 'duration_ms' => 5, 'timestamp' => 100),
		array('app_id' => 3, 'list' => 'errors', 'method' => 'GET', 'path' => '/x', 'status_code' => 403, 'error_code' => 'forbidden', 'duration_ms' => 2, 'timestamp' => 90),
		array('app_id' => 7, 'list' => 'all', 'method' => 'POST', 'path' => '/y', 'status_code' => 201, 'error_code' => '', 'duration_ms' => 9, 'timestamp' => 80)
	);

	$grouped = api_app_log_group($rows, function ($timestamp) { return 't' . $timestamp; });

	pg_assert_same(1, count($grouped[3]['all']), 'application 3 latest');
	pg_assert_same(1, count($grouped[3]['errors']), 'application 3 errors');
	pg_assert_same(403, $grouped[3]['errors'][0]['status'], 'error status');
	pg_assert_same('t100', $grouped[3]['all'][0]['time'], 'time label from the callback');
	pg_assert_same(array(), $grouped[7]['errors'], 'application 7 has no errors list rows');
}

// A request from today shows the time; an older one the date as well, with
// the year only when it is not this year's.
function test_api_app_access_log_time_type()
{
	$now = mktime(10, 0, 0, 10, 31, 2026);

	pg_assert_same(array('type' => 'time', 'year' => false), api_app_log_time_type(mktime(0, 5, 0, 10, 31, 2026), $now), 'today');
	pg_assert_same(array('type' => '', 'year' => false), api_app_log_time_type(mktime(23, 50, 0, 10, 30, 2026), $now), 'yesterday');
	pg_assert_same(array('type' => '', 'year' => true), api_app_log_time_type(mktime(12, 0, 0, 12, 31, 2025), $now), 'last year');
}
