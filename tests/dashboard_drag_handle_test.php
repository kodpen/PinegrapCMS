<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard card drag: a card is picked up only by its grip, pinned to the
 * right end of the header, at once for a mouse and after a short hold on
 * touch; the header itself carries no grab cursor and the tip names the grip.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function dashboard_drag_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// The options object of the dashboard's Sortable.create() call.
function dashboard_drag_options()
{
	$source = dashboard_drag_source('welcome.php');

	if (!preg_match('/Sortable\.create\(widgets,\s*\{(.*?)onUpdate:/s', $source, $match)) {
		return '';
	}

	return $match[1];
}

function test_dashboard_drag_handle_is_the_grip()
{
	$options = dashboard_drag_options();

	pg_assert_true($options !== '', 'the dashboard creates its Sortable');
	pg_assert_true(strpos($options, 'handle: ".pg-widget-move"') !== false, 'the grip is the only handle');
	pg_assert_true(strpos($options, '.pg-widget-handle') === false, 'the whole header is no longer the handle');
}

function test_dashboard_drag_delay_on_touch_only()
{
	$options = dashboard_drag_options();

	pg_assert_true(strpos($options, 'delayOnTouchOnly: true') !== false, 'the hold applies to touch only');
	pg_assert_true(preg_match('/touchStartThreshold:\s*(\d+)/', $options, $match) === 1 && (int) $match[1] >= 4, 'a small finger tremble does not cancel the hold');

	if (preg_match('/\bdelay:\s*(\d+)/', $options, $match) && (int) $match[1] > 0) {
		pg_assert_true(strpos($options, 'delayOnTouchOnly: true') !== false, 'a delay never applies to the mouse');
	}
}

function test_dashboard_drag_grip_pinned_right()
{
	$welcome = dashboard_drag_source('welcome.php');
	$css = dashboard_drag_source('assets/css/backend.src.css');

	pg_assert_false((bool) preg_match('/\.pg-widget-handle\s*\{[^}]*cursor:\s*grab/', $welcome . $css), 'the header carries no grab cursor');
	pg_assert_true((bool) preg_match('/\.pg-widget-move\s*\{[^}]*position:\s*absolute;[^}]*right:/s', $css), 'the grip is pinned to the right of the header');
	pg_assert_true((bool) preg_match('/\.pg-widget-move\s*\{[^}]*cursor:\s*grab/s', $css), 'the grip shows a grab cursor');
}

function test_dashboard_drag_tip_names_the_grip()
{
	$brief = dashboard_drag_source('includes/dashboard/brief.php');

	pg_assert_true(strpos($brief, 'dragged around by their headers') === false, 'the tip no longer sends the reader to the header');
	pg_assert_true(strpos($brief, "lang('Did you know the widgets can be dragged by the grip at the right of their headers?')") !== false, 'the tip names the grip');
}
