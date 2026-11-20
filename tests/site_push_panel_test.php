<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the screen "Send a notification" (assets/js/pg_site_push_panel.js):
 * the form sends once. After the confirmed news or a test goes through, both
 * submit buttons are disabled and a further submit is turned away, so a
 * second press cannot send the news again without asking. The runner has no
 * browser, so the script's shape is checked here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _site_push_panel_test_script()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_site_push_panel.js');
}

// The body of the form's submit listener, up to the picker's code.
function _site_push_panel_test_submit_handler($js)
{
	$start = strpos($js, "form.addEventListener('submit'");
	$end = strpos($js, 'if (!pick || !results)');

	if (($start === false) || ($end === false) || ($end < $start)) {
		return '';
	}

	return substr($js, $start, $end - $start);
}

function test_site_push_panel_submit_refused_while_sending()
{
	$handler = _site_push_panel_test_submit_handler(_site_push_panel_test_script());

	pg_assert_true($handler !== '', 'the submit listener is found');
	pg_assert_true((bool) preg_match('/if \(sending\) \{\s*event\.preventDefault\(\);\s*return;/', $handler), 'a submit while sending is cancelled first');
	pg_assert_false(strpos($handler, 'if (confirmed') !== false, 'a confirmed send no longer lets later submits through');
}

function test_site_push_panel_first_send_disables_buttons()
{
	$js = _site_push_panel_test_script();
	$handler = _site_push_panel_test_submit_handler($js);

	pg_assert_contains("button[name=\"send\"]", $js, 'both submit buttons are looked up');
	pg_assert_true((bool) preg_match('/function sendOnce\(action\) \{.*sending = true;.*button\.disabled = true;/s', $js), 'the first send sets the flag and disables the buttons');
	pg_assert_true((bool) preg_match("/input\\.name = 'send';.*input\\.value = action;/s", $js), 'the chosen action travels in a hidden send input');
	pg_assert_true((bool) preg_match("/sendOnce\\('news'\\);\\s*form\\.submit\\(\\);/", $handler), 'the confirmed news locks the form before it posts');
	pg_assert_contains("sendOnce(isTest ? 'test' : 'news')", $handler, 'a test (or a send without the dialog) locks the form too');
}
