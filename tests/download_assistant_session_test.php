<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the download assistant (download_assistant.php at the root)
 * leaves the session of an installed site to pinegrap/init.php. A session
 * the assistant started first left init.php unable to set the panel's cookie
 * settings (HttpOnly, SameSite, strict mode): PHP logged "Session ini
 * settings cannot be changed when a session is active" on every visit.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function download_assistant_session_test_source()
{
	return (string) file_get_contents(dirname(PG_FUNCTIONS_DIR) . '/download_assistant.php');
}

// No session is started before init.php is included; the assistant's own
// start skips a session that is already running.
function test_download_assistant_session_started_after_init()
{
	$source = download_assistant_session_test_source();
	$include = strpos($source, "include('pinegrap/init.php');");

	pg_assert_true($include !== false, 'the assistant includes init.php on an installed site');
	pg_assert_false((bool) preg_match('/^session_start\(\);/m', $source), 'no session started at the top of the file');

	if (preg_match_all('/(?<![\w>])session_start\(\);/', $source, $calls, PREG_OFFSET_CAPTURE)) {
		foreach ($calls[0] as $call) {
			$guard = substr($source, max(0, $call[1] - 80), 80);

			pg_assert_contains('session_status() !== PHP_SESSION_ACTIVE', $guard, 'a session start skips a running session');
		}
	}

	pg_assert_true(strpos($source, 'function da_session_start()') !== false, 'one helper starts the session');
	pg_assert_true((bool) preg_match("/include\\('pinegrap\\/init\\.php'\\);\\s+da_session_start\\(\\);/", $source), 'init.php starts the session of an installed site first');
}

// Before an install, where no init.php is included, the assistant still
// starts a session before it reads $_SESSION.
function test_download_assistant_session_started_without_init()
{
	$source = download_assistant_session_test_source();
	$first_read = strpos($source, '$_SESSION');
	$first_start = strpos($source, 'da_session_start();');

	pg_assert_true($first_start !== false, 'the helper is called');
	pg_assert_true($first_start < $first_read, 'the session starts before it is read');
	pg_assert_same(3, substr_count($source, 'da_session_start();'), 'started on each of the three branches');
}
