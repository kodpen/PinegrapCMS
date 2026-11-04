<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Sessions screen and the users list: push failure reasons,
 * lock and sign-out explanations and the disabled Lock button's reason are
 * printed as text a keyboard, touch or screen-reader user can reach, never
 * only in a hover title.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/signin.php');

function session_screen_reasons_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The text just before each occurrence of $needle must not open a title
// attribute: a title is a hover-only popover on the panel.
function session_screen_reasons_test_not_in_title($source, $needle, $what)
{
	$offset = 0;
	$found = false;

	while (($at = strpos($source, $needle, $offset)) !== false) {
		$found = true;
		$before = substr($source, max(0, $at - 40), min(40, $at));
		pg_assert_true(strpos($before, 'title="') === false, $what . ' is not only in a hover title');
		$offset = $at + strlen($needle);
	}

	pg_assert_true($found, $what . ' is still on the screen');
}

function test_session_screen_reasons_are_visible_text()
{
	$source = session_screen_reasons_test_source('view_sessions.php');

	session_screen_reasons_test_not_in_title($source, "(string) \$subscription['last_error']", 'the last delivery error');
	session_screen_reasons_test_not_in_title($source, "lang('This subscription was made before", 'the bare dash explanation');
	session_screen_reasons_test_not_in_title($source, "lang('This browser has no live sign-in", 'the Signed out explanation');
	session_screen_reasons_test_not_in_title($source, "lang('Locked by the site owner", 'the Locked badge explanation');
	session_screen_reasons_test_not_in_title($source, "lang('With a limit of one, the setting", 'the disabled Lock button reason');
	pg_assert_contains('text-break', $source, 'a long delivery error wraps');
}

function test_session_screen_disabled_lock_button_is_reachable()
{
	$source = session_screen_reasons_test_source('view_sessions.php');

	pg_assert_true(strpos($source, 'disabled" disabled') === false, 'the Locked button is not disabled, so it takes focus');
	pg_assert_contains('aria-disabled="true"', $source, 'the Locked button says it does nothing');
	pg_assert_contains('aria-describedby="pg_pin_redundant_reason"', $source, 'the Locked button points at its reason');
	pg_assert_contains('id="pg_pin_redundant_reason"', $source, 'the reason is on the screen');
}

// The inert button stands in for the Lock action on a session that is not
// locked: it must not carry the Locked badge's word and filled pin.
function test_session_screen_inert_lock_button_names_the_action()
{
	$source = session_screen_reasons_test_source('view_sessions.php');

	$at = strpos($source, 'aria-describedby="pg_pin_redundant_reason"');
	pg_assert_true($at !== false, 'the inert button is still there');
	$start = strrpos(substr($source, 0, $at), '<button');
	$end = strpos($source, '</button>', $at);
	$button = substr($source, $start, $end - $start);

	pg_assert_contains("lang('Lock')", $button, 'the inert button is labelled Lock');
	pg_assert_true(strpos($button, "lang('Locked')") === false, 'the inert button is not labelled Locked');
	pg_assert_contains('bi bi-pin-angle me-1', $button, 'the inert button has the outline pin');
	pg_assert_true(strpos($button, 'style="') === false, 'the inert look comes from a class');
	pg_assert_contains('pg-btn-inert', $button, 'the inert look comes from a class');
}

// The raw user agent is a compact tooltip on a focusable span, never a
// title on the cell (that becomes a large header-only popover).
function test_session_screen_user_agent_is_a_tooltip()
{
	$source = session_screen_reasons_test_source('view_sessions.php');

	pg_assert_true(strpos($source, '<td class="align-middle" title=') === false, 'no cell carries a hover title');
	pg_assert_true(substr_count($source, 'class="no-popover" data-bs-toggle="tooltip" tabindex="0" title="') >= 2, 'both device cells use a focusable tooltip');
}

function test_device_pairing_reads_an_array_action_as_empty()
{
	$source = session_screen_reasons_test_source('device_pairing.php');

	pg_assert_contains("is_scalar(\$_POST['pairing_action'])", $source, 'pairing_action[] reads as empty');
	pg_assert_contains('id="pairing_clock"', $source, 'the countdown has its own span');
	pg_assert_true(strpos($source, 'line.textContent = minutes') === false, 'the countdown keeps the sentence');
}

function test_session_screen_button_hints_are_tooltips()
{
	$source = session_screen_reasons_test_source('view_sessions.php');

	foreach (array("lang('Sends one notification", "lang('The device stops receiving", "lang('Unlocks this session") as $needle) {
		$at = strpos($source, $needle);
		pg_assert_true($at !== false, $needle . ' is still there');
		$before = substr($source, max(0, $at - 200), min(200, $at));
		pg_assert_contains('no-popover', $before, $needle . ' is not a hover popover');
		pg_assert_contains('data-bs-toggle="tooltip"', $before, $needle . ' shows on focus too');
	}
}

function test_session_screen_user_list_lock_minutes_visible()
{
	$source = session_screen_reasons_test_source('includes/fn/user_list.php');

	session_screen_reasons_test_not_in_title($source, "'string' => 'Clears in {var:1} minute{suffix:1}.'", 'the lock minutes');
	pg_assert_true(strpos($source, "'Signing in is locked after repeated failed attempts. Clears in {var:1} minutes.'") === false, 'the hover-only sentence is gone');
}

function test_session_screen_provider_only_warning_visible()
{
	$html = pg_signin_identity_badges(array('google'), 3);

	pg_assert_true(strpos($html, 'title=') === false, 'the warning is not a hover title');
	pg_assert_contains('would lock it out', $html, 'the warning is printed');
	pg_assert_contains('Google only', $html, 'the badge stays');
}
