<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the user and session screens: the Reset & Send Password
 * confirmation survives an apostrophe in any language, session and password
 * dates are drawn by get_absolute_time(), and the controls of the users list
 * and the user forms carry a translated accessible name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/user_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/user_list.php');

function user_screens_names_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_user_screens_reset_password_confirm_is_valid_json()
{
	$html = pg_user_form_reset_password_button();

	pg_assert_true((bool) preg_match('/onclick="([^"]*)"/', $html, $match), 'the link has an onclick');

	$script = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');

	pg_assert_true((bool) preg_match('/pgConfirm\((\{.*?\})\)\.then/', $script, $options), 'the options are one object');

	$decoded = json_decode($options[1], true);

	pg_assert_true(is_array($decoded), 'the options are valid JSON, so an apostrophe cannot end a string');
	pg_assert_same(lang('Are you sure you want to reset the user\'s password and email a new password to the user?'), $decoded['message'], 'the message keeps its apostrophe');
	pg_assert_same(lang('Reset & Send Password'), $decoded['title'], 'the title');
	pg_assert_same(lang('Cancel'), $decoded['cancelText'], 'the cancel text');
	pg_assert_contains('document.reset_password_form.submit()', $script, 'confirming submits the form');
}

function test_user_screens_session_dates_follow_site_clock()
{
	foreach (array('edit_user.php', 'view_sessions.php') as $file) {
		$source = user_screens_names_test_source($file);

		pg_assert_false(strpos($source, "date('Y-m-d H:i'") !== false, $file . ' draws no fixed 24-hour date');
		pg_assert_false(strpos($source, "date('d.m.Y'") !== false, $file . ' draws no fixed day-first date');
	}

	pg_assert_true(strpos(user_screens_names_test_source('edit_user.php'), "'type' => 'date'") !== false, 'the password change is a plain date');
}

function test_user_screens_badge_label_named()
{
	foreach (array('add_user.php', 'edit_user.php', 'import_users.php') as $file) {
		pg_assert_true((bool) preg_match('/<input[^>]*id="badge_label"[^>]*aria-label="\' \. h\(lang\(\'Badge Label\'\)\) \. \'"/', user_screens_names_test_source($file)), $file . ' names the badge label box');
	}
}

function test_user_screens_landmarks_translated()
{
	foreach (array('add_user.php', 'edit_user.php', 'import_users.php', 'view_users.php') as $file) {
		$source = user_screens_names_test_source($file);

		pg_assert_false(strpos($source, 'aria-label="data edit buttons') !== false, $file . ' has no literal save bar name');
		pg_assert_false(strpos($source, 'aria-label="Button Bar"') !== false, $file . ' has no literal toolbar name');
	}

	pg_assert_true(strpos(user_screens_names_test_source('view_users.php'), "aria-label=\"' . h(lang('Button Bar')) . '\"") !== false, 'the toolbar name is translated');
}

function test_user_screens_list_checkbox_named()
{
	$cell = pg_user_list_select_cell(5, 'bob<', true);
	pg_assert_contains('aria-label="' . h(lang(array('string' => 'Select {var:1}', 'vars' => 'bob<'))) . '"', $cell['html'], 'the row checkbox names the user');
	pg_assert_contains('name="users[]" value="5"', $cell['html'], 'the posted value');

	$locked = pg_user_list_select_cell(6, 'admin', false);
	pg_assert_contains('disabled', $locked['html'], 'the last administrator cannot be selected');
	pg_assert_contains('aria-label="' . h(lang(array('string' => 'Select {var:1}', 'vars' => 'admin'))) . '"', $locked['html'], 'the disabled checkbox is named too');
}

function test_user_screens_names_menu_named()
{
	$cell = pg_user_list_names_cell(array('Docs'), 'folder', lang('Folders'));
	pg_assert_contains('aria-label="' . h(lang('Folders')) . '"', $cell['html'], 'the menu button is named');
	pg_assert_contains('<span class="material-icons d-inline" aria-hidden="true">folder</span>', $cell['html'], 'the ligature is not read');

	$empty = pg_user_list_names_cell(array(), 'today', lang('Calendars'));
	pg_assert_false(strpos($empty['html'], 'data-bs-toggle') !== false, 'no menu when there is nothing to list');
	pg_assert_contains('&mdash;', $empty['html'], 'a dash instead');
}

function test_user_screens_check_mark_reads_yes()
{
	$yes = pg_user_list_mark(true);
	pg_assert_contains('<span class="material-icons" aria-hidden="true">task_alt</span>', $yes['html'], 'the ligature is hidden');
	pg_assert_contains('<span class="visually-hidden">' . h(lang('Yes')) . '</span>', $yes['html'], 'the mark reads Yes');

	$no = pg_user_list_mark(false);
	pg_assert_false(strpos($no['html'], 'task_alt') !== false, 'no mark');
}

function test_user_screens_folder_expiry_named()
{
	$source = user_screens_names_test_source('includes/fn/auth.php');

	pg_assert_true((bool) preg_match('/<input[^>]*pg-acl-expiry-input[^>]*aria-label="\' \. h\(lang\(array\(\'string\' => \'Expiration date of {var:1}\'/', $source), 'the expiry box names its folder');
	pg_assert_true(strpos($source, 'class="pg-acl-expiry-clear" title="\' . h(lang(\'Remove the expiry\')) . \'" aria-label="\' . h(lang(\'Remove the expiry\')) . \'"') !== false, 'the clear button is named');
}
