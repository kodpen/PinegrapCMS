<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for answers that come back without a page load: Page
 * Designer reads the save answer's status before it says Saved; the file
 * manager's notices and its search count, the picture picker's Unsplash
 * status and the picture editor's status are live regions. A unit test
 * cannot run the browser's accessibility tree, so these pin the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_live_status_local_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The text between a marker and the next occurrence of an end marker.
function panel_live_status_local_slice($source, $from, $to)
{
	$start = strpos($source, $from);
	if ($start === false) {
		return '';
	}
	$end = strpos($source, $to, $start + strlen($from));

	return $end === false ? substr($source, $start) : substr($source, $start, $end - $start);
}

function test_page_designer_save_reads_the_answer_status()
{
	$js = panel_live_status_local_source('assets/js/page_designer.js');
	$save = panel_live_status_local_slice($js, "url: 'api.php',\n                                data: JSON.stringify(data),", '// When the cancel button is clicked');

	pg_assert_true($save !== '', 'the save request is found');
	pg_assert_contains("response.status !== 'success'", $save, 'an answer that is not success is a failure');
	pg_assert_contains('error: function', $save, 'a network failure has its own callback');
	pg_assert_contains('saveFailed(', $save, 'both failure paths say why');

	// The failure check comes before the content is recorded as saved.
	$check = strpos($save, "response.status !== 'success'");
	$cleared = strpos($save, 'content_changed = false;');
	pg_assert_true($check !== false && $cleared !== false && $check < $cleared, 'content_changed stays true for a refused save');
}

function test_page_designer_save_failure_is_an_alert()
{
	$js = panel_live_status_local_source('assets/js/page_designer.js');
	pg_assert_contains('class="page_designer_save_error', $js, 'the footer holds the failure line');
	pg_assert_true(
		(bool) preg_match('/page_designer_save_error[^>]*role="alert"/', $js),
		'the failure line is an alert'
	);
	pg_assert_contains("labels['The changes could not be saved.']", $js, 'the failure line has a translated lead');

	$php = panel_live_status_local_source('page_designer.php');
	pg_assert_contains('"The changes could not be saved.":"\' . escape_javascript(lang(\'The changes could not be saved.\'))', $php, 'the label comes from page_designer.php');
}

function test_explorer_toasts_are_announced()
{
	$source = panel_live_status_local_source('view_folders.php');
	pg_assert_true(
		(bool) preg_match('/id="explorer_toasts"[^>]*aria-live="polite"|aria-live="polite"[^>]*id="explorer_toasts"/', $source),
		'#explorer_toasts is a polite live region'
	);

	$toast = panel_live_status_local_slice($source, "\n    function toast(", "\n    function ");
	pg_assert_contains("ok === false ? 'alert'", $toast, 'a failure notice is an alert');
	pg_assert_false(strpos($toast, "setAttribute('role', 'status')") !== false, 'a notice is not a status region of its own inside the live container');
}

function test_explorer_search_count_is_announced()
{
	$source = panel_live_status_local_source('view_folders.php');
	pg_assert_true(
		(bool) preg_match('/id="explorer_search_status"[^>]*role="status"/', $source),
		'a hidden status region holds the search count'
	);

	$input = panel_live_status_local_slice($source, "document.getElementById('filter_input').addEventListener('input'", "\n    });");
	pg_assert_contains('announceSearchCount()', $input, 'typing in the search box announces the count');

	$announce = panel_live_status_local_slice($source, "\n    function announceSearchCount(", "\n    function ");
	pg_assert_contains('L.filters_showing', $announce, 'the count uses the status bar wording');
	pg_assert_contains('setTimeout', $announce, 'the count is said once typing stops');
}

function test_unsplash_status_is_live_and_counts_in_words()
{
	$source = panel_live_status_local_source('editor_select_image.php');
	pg_assert_true(
		(bool) preg_match('/id="unsplash_status"[^>]*role="status"[^>]*aria-live="polite"/', $source),
		'#unsplash_status is a polite status region'
	);
	pg_assert_contains("lang('{var:1} result(s)')", $source, 'the count is a translated template');
	pg_assert_contains('pg_number_separators()', $source, 'the count uses the panel number format');
	pg_assert_false(strpos($source, "' result(s)'") !== false, 'no raw number glued to result(s)');
	pg_assert_false(strpos($source, "lang('result(s)')") !== false, 'no raw number glued to result(s)');
}

function test_image_editor_hint_is_live_and_escapes_the_server_message()
{
	$js = panel_live_status_local_source('assets/js/image_editor_modal.js');
	pg_assert_true(
		(bool) preg_match('/id="pgImageEditorHint"[^\']*role="status"/', $js),
		'#pgImageEditorHint is a status region'
	);
	pg_assert_false(strpos($js, "'<span class=\"fg-danger\">' + msg + '</span>'") !== false, 'the server message is not inserted raw');
	pg_assert_contains('_footError(msg)', $js, 'the server message goes through the escaping error line');
}
