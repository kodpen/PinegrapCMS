<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for two panel timers in backend.src.js: error and warning
 * toasts (pgToast) stay until they are closed and are not stacked twice,
 * and the 20 s unread poll rebuilds the open notifications list only when
 * the unread count really rises, keeping its scroll and focused row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_timers_backend()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/js/backend.src.js');
}

// The source from $start to the next occurrence of $end (or $length bytes).
function panel_timers_slice($js, $start, $end, $length = 6000)
{
	$from = strpos($js, $start);
	pg_assert_true($from !== false, 'found: ' . $start);
	$to = strpos($js, $end, (int) $from + strlen($start));
	return (string) substr($js, (int) $from, $to === false ? $length : $to - $from);
}

function test_panel_toast_errors_stay_until_closed()
{
	$toast = panel_timers_slice(panel_timers_backend(), 'window.pgToast = function', 'window.pgConfirm = function');

	pg_assert_contains("var sticky = variant === 'danger' || variant === 'warning';", $toast, 'danger and warning are sticky');
	pg_assert_contains('autohide: !sticky', $toast, 'sticky toasts do not hide on a timer');
	pg_assert_contains('if (!sticky) {', $toast, 'the fallback timer skips sticky toasts');
	pg_assert_false(strpos($toast, "setTimeout(function () { el.remove(); }, delay);\n            return null;") !== false, 'the fallback no longer removes every toast on a timer');
	pg_assert_contains("getAttribute('data-pg-toast-key') === key", $toast, 'an identical sticky toast on screen is not stacked again');
}

function test_panel_notification_poll_keeps_the_open_list()
{
	$js = panel_timers_backend();
	$check = panel_timers_slice($js, 'function check_unread_notifications(refresh_type)', 'function remove_all_notifications()');

	pg_assert_false(strpos($check, '$unreadsCallback = 0;') !== false, 'the poll does not reset the last seen count');
	pg_assert_contains('var $unreadsCallback = 0;', $js, 'the last seen count is declared once');
	pg_assert_contains('get_notifications(false, true);', $check, 'a poll-driven rebuild keeps scroll and focus');

	$get = panel_timers_slice($js, 'function get_notifications($read_mark, keep_place)', '// Opens the page a notification points to.');
	pg_assert_contains('$unreadsCallback = 0;', $get, 'marking all read resets the last seen count');
	pg_assert_contains("closest('li[notification-id]')", $get, 'the focused row is remembered');
	pg_assert_contains('.trigger(\'focus\')', $get, 'focus goes back to the same row');
	pg_assert_contains('scroll_area.scrollTop(kept_scroll)', $get, 'the list keeps its scroll position');
}
