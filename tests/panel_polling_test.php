<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel's background polls and shell: the bell and heartbeat
 * handlers release the session lock, their timers pause in a hidden tab,
 * the account menu's My Account link is resolved only when followed and the
 * closed settings modal's loading bar does not loop.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_polling_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function panel_polling_test_function($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');
	if ($start === false) {
		return '';
	}
	$end = strpos($source, "\n}\n", $start);
	return substr($source, $start, $end === false ? null : $end - $start);
}

function test_panel_polls_release_session_lock()
{
	$notifications = panel_polling_test_source('includes/panel/notifications.php');
	$dashboard = panel_polling_test_source('includes/panel/dashboard.php');

	foreach (array(
		array($notifications, 'pg_panel_check_unread_notifications', 'validate_user();'),
		array($dashboard, 'pg_panel_user_online_check', 'validate_token();'),
		array($dashboard, 'pg_panel_sitemap_check', 'validate_token();'),
	) as $case) {
		$body = panel_polling_test_function($case[0], $case[1]);
		$check = strpos($body, $case[2]);
		$close = strpos($body, 'session_write_close();');
		pg_assert_true($close !== false, $case[1] . ' releases the session');
		pg_assert_true($check !== false && $check < $close, $case[1] . ' checks before it releases');
	}
}

function test_panel_polls_pause_in_hidden_tab()
{
	$js = panel_polling_test_source('assets/js/backend.src.js');
	$helper = panel_polling_test_function($js, 'pg_poll_while_visible');

	pg_assert_true($helper !== '', 'the helper exists');
	pg_assert_true(strpos($helper, 'visibilitychange') !== false, 'it listens for visibility');
	pg_assert_true(strpos($helper, 'clearInterval') !== false, 'it stops in a hidden tab');
	pg_assert_true(strpos($js, "pg_poll_while_visible(sendHeartbeat, 50000)") !== false, 'heartbeat');
	pg_assert_true(preg_match("~pg_poll_while_visible\(function \(\) \{\s*check_unread_notifications\('scheduled_check'\);~", $js) === 1, 'bell');
	pg_assert_same(0, preg_match('~setInterval\(sendHeartbeat~', $js), 'no bare heartbeat interval');
	pg_assert_same(0, preg_match("~setInterval\(function \(\) \{[^}]*scheduled_check~", $js), 'no bare bell interval');
}

function test_account_menu_my_account_link_is_lazy()
{
	$menu = panel_polling_test_function(panel_polling_test_source('includes/fn/output.php'), 'pg_account_menu');

	pg_assert_true($menu !== '', 'pg_account_menu found');
	pg_assert_same(false, strpos($menu, 'get_page_type_url('), 'no page lookup while drawing');
	pg_assert_true(strpos($menu, "'/my_account_link.php'") !== false, 'links to the redirect');

	$link = panel_polling_test_source('my_account_link.php');
	pg_assert_true(strpos($link, 'validate_user();') !== false, 'the redirect asks for a session');
	pg_assert_true(strpos($link, "get_page_type_url('my account')") !== false, 'the redirect looks the page up');
}

function test_settings_modal_bar_loops_only_when_busy()
{
	$css = panel_polling_test_source('assets/css/backend.src.css');

	pg_assert_true(strpos($css, 'animation: pg-sm-slide 1.1s ease-in-out infinite paused;') !== false, 'paused by default');
	pg_assert_true(strpos($css, '.pg-sm.is-busy .pg-sm-progress::after { animation-play-state: running; }') !== false, 'running while busy');
}
