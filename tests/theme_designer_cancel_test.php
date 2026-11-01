<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Theme Designer's Cancel button. It called
 * theme_designer_cancel_confirm(), which no script defines, so a click threw
 * a ReferenceError and nothing happened. Cancel is now a link to
 * clear_theme_designer_session.php, which drops the unsaved designer session
 * of the theme and sends the user on; the panel's data-confirm-content
 * handler asks first.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// No onclick calls a function that no script defines.
function test_theme_designer_cancel_calls_no_undefined_function()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/theme_designer.php');

	pg_assert_true(strpos($source, 'theme_designer_cancel_confirm(') === false, 'Cancel does not call the undefined theme_designer_cancel_confirm()');
}

// Cancel links to the session clearing endpoint with the theme's id and the
// screen to go back to, and asks before it leaves.
function test_theme_designer_cancel_clears_the_session_and_goes_back()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/theme_designer.php');

	$cancel = strpos($source, "h('clear_theme_designer_session.php?file_id=' . urlencode(\$_GET['id']) . '&send_to=' . urlencode(\$send_to))");
	pg_assert_true($cancel !== false, 'Cancel links to clear_theme_designer_session.php with file_id and send_to');

	$tag = substr($source, $cancel, 400);
	pg_assert_true(strpos($tag, "data-confirm-content=\"' . h(lang('WARNING: If you leave this page, then your unsaved changes will be lost.')) . '\"") !== false, 'Cancel asks before the unsaved changes are dropped');
	pg_assert_true(strpos($tag, ">' . lang('Cancel') . '</a>") !== false, 'Cancel keeps its label');
}

// The endpoint clears the session under the key the designer stores it in
// and only sends the user to a path on this site.
function test_clear_theme_designer_session_clears_by_file_id()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/clear_theme_designer_session.php');

	pg_assert_true(strpos($source, "unset(\$_SESSION['software']['theme_designer'][\$_GET['file_id'] ?? ''])") !== false, 'the session is cleared by file_id');
	pg_assert_true(strpos($source, 'pg_safe_redirect_path($_GET[\'send_to\']') !== false, 'send_to goes through pg_safe_redirect_path()');
}
