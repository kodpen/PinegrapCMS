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
