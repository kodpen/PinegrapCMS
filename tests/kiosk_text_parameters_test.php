<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that kiosk.php keeps a dialog message or button label from the
 * query string only when it is a non-empty string: an array
 * (?dialog_message[]=x) was stored in the session, and escape_javascript()
 * returns a non-scalar unchanged, so every page of the kiosk session printed
 * "Array" with an "Array to string conversion" warning.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_kiosk_text_parameters_are_strings()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/kiosk.php');

	foreach (array('dialog_message', 'continue_button_label', 'logout_button_label') as $name) {
		pg_assert_same(0, preg_match_all("/\\(\\\$_GET\\['" . $name . "'\\] \\?\\? ''\\) != ''/", $source), $name . ': no loose non-empty test');
		pg_assert_same(1, preg_match_all("/is_string\\(\\\$_GET\\['" . $name . "'\\] \\?\\? null\\) && \\(\\\$_GET\\['" . $name . "'\\] !== ''\\)/", $source), $name . ': kept only as a non-empty string');
	}
}

function test_kiosk_logout_needs_kiosk_mode_and_the_token()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/kiosk.php');
	$logout = substr($source, (int) strpos($source, "case 'logout':"));
	pg_assert_contains("if (!\$kiosk_on || (!\$token_ok && pg_session_signed_in())) {", $logout, 'kiosk.php logout gate');
	pg_assert_true(strpos($logout, '$kiosk_on') < strpos($logout, 'logout();'), 'gate before the logout');

	$default = substr($source, 0, (int) strpos($source, "case 'update_activity':"));
	pg_assert_contains('if (!$token_ok && pg_session_signed_in()) {', $default, 'signed-in session confirms kiosk mode');

	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/logout.php');
	pg_assert_true(strpos($page, "kiosk.php?action=logout&token=") > strpos($page, 'hash_equals($session_token, $request_token)'), 'logout.php sends to the kiosk logout only after its token check');

	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/frontend.src.js');
	pg_assert_contains("'/kiosk.php?action=logout&token=' + encodeURIComponent(software_token)", $script, 'kiosk script sends the token');
}

function test_kiosk_logout_clears_the_visitor_cookies()
{
	// The next customer at the kiosk starts without the last one's guest
	// wishlist (merged into the next account signed in there) and without
	// the partner or tracking code the last one arrived with.
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/kiosk.php');
	$logout = substr($source, (int) strpos($source, "case 'logout':"));
	$gate = (int) strpos($logout, '$kiosk_on');
	$end = (int) strpos($logout, 'logout();');

	foreach (array('pg_wishlist_cookie_clear();', "setcookie('software[affiliate_code]', '', time() - 1000, '/');", "setcookie('software[tracking_code]', '', time() - 1000, '/');") as $call) {
		$at = strpos($logout, $call);
		pg_assert_true($at !== false, $call . ' in the kiosk logout');
		pg_assert_true(($at > $gate) && ($at < $end), $call . ' after the gate, before the session ends');
	}
}
