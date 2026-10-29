<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for answers a screen reader hears without moving: the panel's
 * shared announcer, the System Status jobs that hand focus back to their
 * redrawn button, the e-Document test result boxes, the Site Settings page
 * filter's count and the message dialog's translated close name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_live_status_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// Body of a named JS function, up to the next top-level-looking function.
function panel_live_status_js_function($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	return substr($source, $start, 4000);
}

// One live region per politeness, made empty at load, so the words written
// into it later are announced.
function test_panel_live_status_announcer_exists()
{
	$source = panel_live_status_source('assets/js/backend.src.js');

	pg_assert_contains('window.pgAnnounce = function (text, assertive)', $source);
	pg_assert_contains("'pg_live_polite'", $source);
	pg_assert_contains("'pg_live_assertive'", $source);
}

// A health job focuses the redrawn button and says its message.
function test_panel_live_status_health_job_keeps_focus()
{
	$job = panel_live_status_js_function(panel_live_status_source('assets/js/backend.src.js'), 'pg_health_job');

	pg_assert_contains('window.pgAnnounce(', $job);
	pg_assert_contains('preventScroll: true', $job);
}

// The answer of "Test the connection" and Paraşüt "Connect" lands in a
// polite region, and the spinner says what it is.
function test_panel_live_status_edoc_result_is_live()
{
	$commerce = panel_live_status_source('includes/settings/commerce.php');

	pg_assert_same(2, substr_count($commerce, '<div class="pg-edoc-test-result mt-3" role="status" aria-live="polite"></div>'));
	pg_assert_same(0, substr_count($commerce, '<div class="pg-edoc-test-result mt-3"></div>'));

	$source = panel_live_status_source('assets/js/backend.src.js');

	pg_assert_contains('<span class="spinner-border spinner-border-sm me-3" aria-hidden="true"></span><span class="visually-hidden">\' + h(pgLang(\'Please Wait\')) + \'</span>', $source);
}

// Filtering the cards says how many are left, and a word that matches none
// says so on screen.
function test_panel_live_status_settings_filter_counts()
{
	$screen = panel_live_status_source('includes/settings/screen.php');

	pg_assert_contains('id="pg_settings_filter_status"', $screen);
	pg_assert_contains("lang('{var:1} of {var:2} sections shown')", $screen);
	pg_assert_contains("lang('No section matches.')", $screen);

	$filter = panel_live_status_js_function(panel_live_status_source('assets/js/backend.src.js'), 'initSettingsFilter');

	pg_assert_contains("getElementById('pg_settings_filter_status')", $filter);
}

// The message dialog's close button is named in the panel language.
function test_panel_live_status_message_dialog_close_translated()
{
	$source = panel_live_status_source('assets/js/backend.src.js');

	pg_assert_false(strpos($source, 'aria-label="Close"') !== false, 'literal English close name');
}
