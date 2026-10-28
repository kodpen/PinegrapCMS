<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the dashboard's keyboard access: Online Engagement's rows
 * are buttons and links rather than clickable divs, every System Status chip
 * can take focus and its popover opens on focus, and a card redrawn by the
 * refresh gives focus back to the control that had it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function dashboard_keyboard_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// A click handler on a div or an icon is out of the keyboard's reach.
function test_dashboard_keyboard_engagement_has_no_click_only_rows()
{
	$source = dashboard_keyboard_source('includes/dashboard/widgets/widget_4.php');

	pg_assert_false((bool) preg_match('/<(div|i|span)\b[^>]*\bonclick=/', $source), 'onclick on a div, i or span');
	pg_assert_false(strpos($source, 'window.location.href') !== false, 'navigation in a script');
	pg_assert_contains('<button type="button" class="d-flex', $source);
	pg_assert_contains('data-pg-focus-key="', $source);
}

// A chip without a link is a button, so it takes focus and its explanation
// can be read without a mouse.
function test_dashboard_keyboard_status_chips_take_focus()
{
	$source = dashboard_keyboard_source('includes/dashboard/widgets/widget_2.php');

	pg_assert_false(strpos($source, ": '<div';") !== false, 'href-less chip printed as a div');
	pg_assert_contains(": '<button type=\"button\"';", $source);
}

// Bootstrap 6 binds focus on a delegated popover only when the parent's
// trigger lists it: the chip's own data-bs-trigger is ignored there.
function test_dashboard_keyboard_status_popover_opens_on_focus()
{
	$source = dashboard_keyboard_source('welcome.php');

	pg_assert_contains("selector: \\'.status-popover\\',\n            trigger: \"hover focus\"", $source);
}

// The refresh replaces a card's body; the control that had focus is found
// again by its key and focused, as the scroll position is restored.
function test_dashboard_keyboard_refresh_keeps_focus()
{
	$source = dashboard_keyboard_source('welcome.php');

	pg_assert_contains('function pg_widget_focus_save(', $source);
	pg_assert_contains('pg_widget_focus_restore($widget[0], focus_key);', $source);
	pg_assert_contains('data-pg-focus-key', $source);
}
