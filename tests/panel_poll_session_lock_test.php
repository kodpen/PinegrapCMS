<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the panel polls every page fires (unread notifications,
 * online status, sitemap check): each releases the session file lock with
 * session_write_close() once its session and token checks have passed, so
 * the poll does not hold back the same person's other panel requests.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_poll_session_lock_body($path, $function)
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
	$start = strpos($source, 'function ' . $function . '(');
	pg_assert_true($start !== false, $path . ': ' . $function . ' not found');
	$next = strpos($source, "\nfunction ", $start + 1);
	return $next === false ? substr($source, $start) : substr($source, $start, $next - $start);
}

function test_panel_poll_handlers_release_session_after_checks()
{
	$cases = array(
		array('includes/panel/notifications.php', 'pg_panel_check_unread_notifications', 'validate_user();'),
		array('includes/panel/dashboard.php', 'pg_panel_user_online_check', 'validate_token();'),
		array('includes/panel/dashboard.php', 'pg_panel_sitemap_check', 'validate_token();'),
	);
	foreach ($cases as $case) {
		list($path, $function, $check) = $case;
		$body = panel_poll_session_lock_body($path, $function);
		$check_at = strpos($body, $check);
		$close_at = strpos($body, 'session_write_close();');
		pg_assert_true($check_at !== false, $function . ': ' . $check);
		pg_assert_true($close_at !== false, $function . ': releases the session lock');
		pg_assert_true($close_at > $check_at, $function . ': releases the lock only after ' . $check);
		pg_assert_true(!preg_match('/\$_SESSION\[[^\]]*\]\s*=[^=]/', substr($body, $close_at)), $function . ': writes the session after closing it');
	}
}
